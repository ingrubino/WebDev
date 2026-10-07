"""Set devices: legge la configurazione dei canali (tabella channel_config) e, per
ogni canale usato, carica i parametri del dispositivo (curva tempo/corrente e
vettore dalla tabella dataset) e li invia al bus (oggi l'emulatore, che li stampa).

La funzione apply() è usata dal gateway quando arriva il comando MQTT
system/set_devices/start (pulsante "Set devices" nella pagina Configure channels).

Lancio a mano, dal terminale (stesso percorso del pulsante, via MQTT):
  docker compose exec canbus python set_devices.py

Connessione al database da variabili d'ambiente: DB_HOST, DB_PORT, DB_NAME,
DB_USER, DB_PASSWORD (come l'API).
"""
import json
import os
import sys
import uuid
from datetime import datetime

import pymysql


def connect():
    return pymysql.connect(
        host=os.environ.get("DB_HOST", "db"),
        port=int(os.environ.get("DB_PORT", "3306")),
        database=os.environ.get("DB_NAME", "testdb"),
        user=os.environ.get("DB_USER", "app"),
        password=os.environ.get("DB_PASSWORD", ""),
        cursorclass=pymysql.cursors.DictCursor,
        charset="utf8mb4",
    )


def to_number(value):
    """I valori in dataset sono testo; vuoto -> None."""
    if value is None or str(value).strip() == "":
        return None
    return float(str(value).replace(",", "."))


def load_device(cur, identifier):
    """Parametri di un dispositivo: (curva [(t, c), ...], vettore) oppure None se non esiste."""
    cur.execute(
        "SELECT col1, col2, vector_values FROM dataset WHERE identifier = %s ORDER BY row_index",
        (identifier,),
    )
    rows = cur.fetchall()
    if not rows:
        return None
    curve = []
    for r in rows:
        t, c = to_number(r["col1"]), to_number(r["col2"])
        if t is not None and c is not None:
            curve.append((t, c))
    vector = [to_number(v) for v in json.loads(rows[0]["vector_values"] or "[]")]
    return curve, vector


def apply(bus, log=print):
    """Esegue Set devices sul bus indicato. Restituisce il riepilogo per l'interfaccia."""
    lines = []

    def out(line=""):
        lines.append(line)
        log(line)

    bus.log = out  # le righe stampate dal bus finiscono anche nel riepilogo
    summary = []
    out(f"=== Set devices {datetime.now():%Y-%m-%d %H:%M:%S} (bus: {bus.name}) ===")
    conn = connect()
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT channel, device, mode FROM channel_config ORDER BY channel")
            channels = cur.fetchall()
            if not channels:
                out("No channel configuration saved yet.")
            for ch in channels:
                entry = {"channel": ch["channel"], "device": ch["device"], "acdc": ch["mode"]}
                if not ch["device"]:
                    entry["status"] = "not used"
                else:
                    params = load_device(cur, ch["device"])
                    if params is None:
                        out(f"CH {ch['channel']:02d}  device={ch['device']}  NOT FOUND, skipped")
                        entry["status"] = "device not found"
                    else:
                        curve, vector = params
                        bus.send_parameters(ch["channel"], ch["device"], ch["mode"], curve, vector)
                        entry.update(status="sent", points=len(curve))
                summary.append(entry)
    finally:
        conn.close()
        bus.log = log
    sent = sum(1 for e in summary if e["status"] == "sent")
    out(f"=== {sent} channel(s) sent ===")
    return {"sent": sent, "channels": summary, "log": lines}


def trigger():
    """Invia lo start via MQTT al gateway e stampa il risultato (come il pulsante)."""
    import threading
    import paho.mqtt.client as mqtt

    request_id = uuid.uuid4().hex[:12]
    done = threading.Event()
    result = {}

    def on_connect(client, userdata, flags, reason_code, properties):
        client.subscribe("system/set_devices/result", qos=1)
        client.publish("system/set_devices/start", json.dumps({"id": request_id}), qos=1)

    def on_message(client, userdata, msg):
        body = json.loads(msg.payload)
        if body.get("id") == request_id:
            result.update(body)
            done.set()

    client = mqtt.Client(mqtt.CallbackAPIVersion.VERSION2)
    client.on_connect, client.on_message = on_connect, on_message
    client.connect(os.environ.get("MQTT_HOST", "mqtt"), int(os.environ.get("MQTT_PORT", "1883")))
    client.loop_start()
    if not done.wait(60):
        sys.exit("No answer from the gateway within 60 s")
    client.loop_stop()
    print("\n".join(result.get("log", [])))
    if not result.get("ok"):
        sys.exit(f"Set devices failed: {result.get('error')}")


if __name__ == "__main__":
    trigger()
