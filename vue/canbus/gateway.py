"""Gateway CAN <-> MQTT: unico programma Python verso i dispositivi esterni.

- pubblica lo stato di ogni modulo su devices/<ch>/state (pagina SCADA)
- inoltra al bus i comandi ricevuti su devices/<ch>/cmd
- pulsante sync unico: i moduli in modalità sync seguono system/sync/cmd (ON/OFF per tutti),
  quelli in manual il proprio pulsante ON/OFF
- scarta i valori fuori dai limiti (LIMITS) prima di pubblicarli
- su system/set_devices/start esegue Set devices (carica i parametri dal database)
  e risponde su system/set_devices/result
Topic e payload: TOPICS.md.

Variabili d'ambiente:
  MQTT_HOST, MQTT_PORT      broker (default mqtt:1883)
  DEVICE_BUS                emulator (default); in futuro il bus CAN reale, vedi bus.py
  CHANNELS                  numero di canali (default 12)
  EMULATOR_INTERVAL         secondi tra due misure dell'emulatore (default 1)
  SYNC_POWER                stato del pulsante sync all'avvio: off (default) oppure on
  DB_HOST, DB_NAME, ...     database, per Set devices (vedi set_devices.py)
"""
import json
import os
import signal
import threading
import time

import paho.mqtt.client as mqtt

import set_devices
from bus import BUSES

STATUS_TOPIC = "system/gateway/status"
START_TOPIC = "system/set_devices/start"
RESULT_TOPIC = "system/set_devices/result"
SYNC_CMD_TOPIC = "system/sync/cmd"
SYNC_STATE_TOPIC = "system/sync/state"
ALLOWED = {"control": ("sync", "manual"), "power": ("on", "off")}
# Limiti dei valori misurati: uguali a "limits" in vue/config/scada.json (letto dalla pagina).
# Un valore fuori limite viene pubblicato come null (la pagina mostra ---).
LIMITS = {"current": (-10000, 10000), "temperature": (0, 150), "error": (0, 255)}
RESYNC_SECONDS = 2  # un modulo in sync non allineato riceve di nuovo il comando dopo questo tempo


def sanitize(state):
    """Valori fuori limite o di tipo sbagliato -> None; il codice errore deve essere intero."""
    for key, (low, high) in LIMITS.items():
        v = state.get(key)
        ok = isinstance(v, (int, float)) and not isinstance(v, bool) and low <= v <= high
        if key == "error" and ok and v != int(v):
            ok = False
        if v is not None and not ok:
            log(f"CH {state.get('channel', 0):02d}  {key}={v!r} out of range {low}..{high}, discarded")
        state[key] = (int(v) if key == "error" else v) if ok else None
    return state


def log(line=""):
    print(line, flush=True)


class Gateway:
    def __init__(self):
        self.channels = int(os.environ.get("CHANNELS", "12"))
        bus_name = os.environ.get("DEVICE_BUS", "emulator")
        self.bus = BUSES[bus_name](channels=self.channels,
                                   interval=float(os.environ.get("EMULATOR_INTERVAL", "1")), log=log)
        self.set_lock = threading.Lock()  # un solo Set devices alla volta (niente conflitti sul bus)
        self.sync_power = "on" if os.environ.get("SYNC_POWER", "off") == "on" else "off"
        self.control = {}   # canale -> "sync" | "manual", dall'ultimo stato ricevuto
        self.resync_at = {}  # canale -> ultimo comando di allineamento al pulsante sync

        self.client = mqtt.Client(mqtt.CallbackAPIVersion.VERSION2, client_id="canbus-gateway")
        self.client.will_set(STATUS_TOPIC, json.dumps({"status": "offline"}), qos=1, retain=True)
        self.client.on_connect = self.on_connect
        self.client.on_message = self.on_message
        self.client.reconnect_delay_set(1, 10)

    def run(self):
        host, port = os.environ.get("MQTT_HOST", "mqtt"), int(os.environ.get("MQTT_PORT", "1883"))
        log(f"Gateway starting: bus={self.bus.name}, channels={self.channels}, broker={host}:{port}")
        self.client.connect_async(host, port, keepalive=30)  # riprova da solo se il broker non c'è ancora
        self.client.loop_start()
        self.bus.start(self.publish_state)
        stop = threading.Event()
        signal.signal(signal.SIGTERM, lambda *_: stop.set())
        signal.signal(signal.SIGINT, lambda *_: stop.set())
        stop.wait()
        self.client.publish(STATUS_TOPIC, json.dumps({"status": "offline"}), qos=1, retain=True).wait_for_publish(2)
        self.bus.close()
        self.client.loop_stop()

    def on_connect(self, client, userdata, flags, reason_code, properties):
        if reason_code.is_failure:
            log(f"MQTT connection refused: {reason_code}")
            return
        log("Connected to MQTT broker")
        client.subscribe([("devices/+/cmd", 1), (START_TOPIC, 1), (SYNC_CMD_TOPIC, 1)])
        client.publish(STATUS_TOPIC, json.dumps({"status": "online", "bus": self.bus.name}), qos=1, retain=True)
        self.publish_sync()

    def publish_sync(self):
        self.client.publish(SYNC_STATE_TOPIC, json.dumps({"power": self.sync_power}), qos=1, retain=True)

    def publish_state(self, channel, state):
        state["channel"] = channel
        sanitize(state)
        self.client.publish(f"devices/{channel}/state", json.dumps(state), qos=0)
        self.control[channel] = state.get("control")
        # un modulo in sync deve avere lo stato del pulsante sync: se non lo ha, lo si allinea
        if state.get("control") == "sync" and state.get("power") != self.sync_power:
            now = time.monotonic()
            if now - self.resync_at.get(channel, 0) >= RESYNC_SECONDS:
                self.resync_at[channel] = now
                self.bus.send_command(channel, {"power": self.sync_power})

    def on_message(self, client, userdata, msg):
        try:
            body = json.loads(msg.payload or b"{}")
        except ValueError:
            log(f"Ignored invalid JSON on {msg.topic}")
            return
        if msg.topic == START_TOPIC:
            # in un thread a parte: il database non deve bloccare la ricezione MQTT
            threading.Thread(target=self.set_devices, args=(body.get("id"),), daemon=True).start()
            return
        if msg.topic == SYNC_CMD_TOPIC:
            self.sync(body)
            return
        parts = msg.topic.split("/")
        if len(parts) == 3 and parts[0] == "devices" and parts[2] == "cmd":
            self.command(parts[1], body)

    def command(self, channel, body):
        if not channel.isdigit() or not 1 <= int(channel) <= self.channels or not isinstance(body, dict):
            log(f"Ignored command for channel {channel!r}")
            return
        ch = int(channel)
        cmd = {k: v for k, v in body.items() if v in ALLOWED.get(k, ())}
        control = cmd.get("control", self.control.get(ch))
        if control == "sync":
            # in sync l'ON/OFF del modulo non conta: vale quello del pulsante sync
            if "power" in cmd and "control" not in cmd:
                log(f"CH {ch:02d}  power command ignored: module is in sync")
            cmd["power"] = self.sync_power
        if cmd:
            self.control[ch] = control
            self.bus.send_command(ch, cmd)

    def sync(self, body):
        power = body.get("power") if isinstance(body, dict) else None
        if power not in ALLOWED["power"]:
            log(f"Ignored sync command {body!r}")
            return
        self.sync_power = power
        log(f"SYNC  power={power}")
        self.publish_sync()
        for ch, control in self.control.items():
            if control == "sync":
                self.bus.send_command(ch, {"power": power})

    def set_devices(self, request_id):
        if not self.set_lock.acquire(blocking=False):
            self.reply({"id": request_id, "ok": False, "error": "Set devices already running"})
            return
        try:
            self.reply({"id": request_id, "ok": True, **set_devices.apply(self.bus, log)})
        except Exception as e:  # errore di database o di invio: lo vede anche l'interfaccia
            log(f"ERROR: {e}")
            self.reply({"id": request_id, "ok": False, "error": f"Set devices failed: {e}"})
        finally:
            self.set_lock.release()

    def reply(self, body):
        self.client.publish(RESULT_TOPIC, json.dumps(body), qos=1)


if __name__ == "__main__":
    Gateway().run()
