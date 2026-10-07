"""Collegamento ai dispositivi esterni (lato CAN del gateway).

Ogni "bus" ha la stessa interfaccia, così gateway.py non cambia passando
dall'emulatore al bus CAN reale (variabile d'ambiente DEVICE_BUS):

  start(on_state)          avvia; on_state(channel, state) va chiamata a ogni misura ricevuta
  send_command(ch, cmd)    comando a un modulo: {"control": "sync"|"manual"} e/o {"power": "on"|"off"}
  send_parameters(...)     caricamento parametri (Set devices)
  close()

Oggi c'è solo EmulatorBus. Per il bus reale: scrivere una classe con gli stessi metodi
(es. con python-can, vedi l'esempio in fondo) e registrarla in BUSES.
"""
import random
import threading
import time


class EmulatorBus:
    """Simula N moduli: corrente, temperatura e codici di errore plausibili.

    - acceso: corrente intorno al valore nominale (prima corrente della curva caricata
      con Set devices, altrimenti 90 + 4*canale A); la temperatura sale verso 25 + 0.4*|I| °C
      (al massimo 149 °C)
    - spento: corrente 0, la temperatura scende verso 25 °C
    - codice errore: 0 = nessun errore; 5 sopra 70 °C (avviso, LED verde);
      20 sopra 85 °C (sovratemperatura, LED rosso), che rientra sotto 80 °C
    - all'avvio tutti i moduli sono in sync e spenti: il gateway li allinea al pulsante sync
    """

    name = "emulator"
    WARN_TEMP, OVERTEMP_ON, OVERTEMP_OFF = 70.0, 85.0, 80.0
    WARN_CODE, OVERTEMP_CODE = 5, 20

    def __init__(self, channels=12, interval=1.0, log=print):
        self.interval = interval
        self.log = log
        self.lock = threading.Lock()
        self.stop_event = threading.Event()
        self.channels = {
            ch: {"current": 0.0, "temperature": 25.0, "error": 0,
                 "control": "sync", "power": "off", "nominal": 90.0 + 4 * ch}
            for ch in range(1, channels + 1)
        }

    def start(self, on_state):
        self.on_state = on_state
        threading.Thread(target=self._run, daemon=True).start()

    def _run(self):
        while not self.stop_event.wait(self.interval):
            with self.lock:
                states = {ch: self._step(s) for ch, s in self.channels.items()}
            for ch, state in states.items():
                self.on_state(ch, state)

    def _step(self, s):
        if s["power"] == "on":
            s["current"] = s["nominal"] * random.uniform(0.97, 1.03)
            target = min(25.0 + 0.4 * abs(s["current"]), 149.0)
        else:
            s["current"] = 0.0
            target = 25.0
        s["temperature"] += (target - s["temperature"]) * 0.1 + random.uniform(-0.3, 0.3)
        t = s["temperature"]
        if t > self.OVERTEMP_ON or (s["error"] == self.OVERTEMP_CODE and t >= self.OVERTEMP_OFF):
            s["error"] = self.OVERTEMP_CODE
        else:
            s["error"] = self.WARN_CODE if t > self.WARN_TEMP else 0
        return {"channel": None, "current": round(s["current"]), "temperature": round(t),
                "error": s["error"], "control": s["control"], "power": s["power"],
                "ts": round(time.time(), 3)}

    def send_command(self, channel, command):
        with self.lock:
            s = self.channels.get(channel)
            if s is None:
                return False
            s.update({k: v for k, v in command.items() if k in ("control", "power")})
        self.log(f"CH {channel:02d}  command {command}")
        return True

    def send_parameters(self, channel, device, acdc, curve, vector):
        """curve = [(tempo_ms, corrente_A), ...]; vector = [valore o None, ...]. Stampa e memorizza."""
        self.log(f"CH {channel:02d}  device={device}  acdc={acdc}")
        self.log("  curve (time ms -> current A): " + ", ".join(f"{fmt(t)} -> {fmt(c)}" for t, c in curve))
        values = [fmt(v) for v in vector if v is not None]
        self.log("  vector: " + (", ".join(values) if values else "(empty)"))
        with self.lock:
            if channel in self.channels and curve:
                self.channels[channel]["nominal"] = curve[0][1]

    def close(self):
        self.stop_event.set()


# Esempio per il futuro (non attivo): stessa interfaccia sul bus CAN reale con python-can.
#
# class CanBus:
#     name = "can"
#     def __init__(self, channels=12, log=print, channel="can0", interface="socketcan", **_):
#         import can
#         self.log = log
#         self.bus = can.Bus(channel=channel, interface=interface)
#
#     def start(self, on_state):
#         import can
#         def on_message(msg):                      # frame ricevuto dal dispositivo
#             ch, state = decode_state(msg)          # protocollo da definire
#             if ch: on_state(ch, state)
#         self.notifier = can.Notifier(self.bus, [on_message])
#
#     def send_command(self, channel, command):
#         self.bus.send(encode_command(channel, command))
#         return True
#
#     def send_parameters(self, channel, device, acdc, curve, vector):
#         for msg in encode_parameters(channel, acdc, curve, vector):
#             self.bus.send(msg)
#         self.log(f"CH {channel:02d} parameters sent to CAN bus")
#
#     def close(self):
#         self.notifier.stop()
#         self.bus.shutdown()

BUSES = {"emulator": EmulatorBus}


def fmt(x):
    """Numero senza decimali inutili: 100.0 -> 100, 1.5 -> 1.5"""
    return f"{x:g}"
