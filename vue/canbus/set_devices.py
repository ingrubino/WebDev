"""Set devices: legge la configurazione dei canali (tabella channel_config) e, per
ogni canale usato, carica i parametri del dispositivo (curva tempo/corrente e
vettore dalla tabella dataset) e li invia con il sender scelto (oggi: a video).

Uso:
  python set_devices.py            esegue una volta e stampa a terminale
  python set_devices.py --serve    servizio HTTP per il pulsante "Set devices"
                                   (POST /apply, GET /health), porta $PORT (8000)

Connessione al database da variabili d'ambiente: DB_HOST, DB_PORT, DB_NAME,
DB_USER, DB_PASSWORD (come l'API). CAN_SENDER sceglie il sender (default console).
"""
import json
import os
import sys
import threading
from datetime import datetime
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

import pymysql

from can_sender import SENDERS


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


def apply(log=print):
    """Esegue Set devices. Restituisce il riepilogo per l'interfaccia."""
    lines = []

    def out(line=""):
        lines.append(line)
        log(line)

    sender_name = os.environ.get("CAN_SENDER", "console")
    sender = SENDERS[sender_name](out)
    summary = []
    out(f"=== Set devices {datetime.now():%Y-%m-%d %H:%M:%S} (sender: {sender_name}) ===")
    conn = connect()
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT channel, device, mode FROM channel_config ORDER BY channel")
            channels = cur.fetchall()
            if not channels:
                out("No channel configuration saved yet.")
            for ch in channels:
                entry = {"channel": ch["channel"], "device": ch["device"], "mode": ch["mode"]}
                if not ch["device"]:
                    entry["status"] = "not used"
                else:
                    params = load_device(cur, ch["device"])
                    if params is None:
                        out(f"CH {ch['channel']:02d}  device={ch['device']}  NOT FOUND, skipped")
                        entry["status"] = "device not found"
                    else:
                        curve, vector = params
                        sender.send_channel(ch["channel"], ch["device"], ch["mode"], curve, vector)
                        entry.update(status="sent", points=len(curve))
                summary.append(entry)
    finally:
        conn.close()
        sender.close()
    sent = sum(1 for e in summary if e["status"] == "sent")
    out(f"=== {sent} channel(s) sent ===")
    return {"sent": sent, "channels": summary, "log": lines}


class Handler(BaseHTTPRequestHandler):
    lock = threading.Lock()  # un solo Set devices alla volta

    def _json(self, status, body):
        data = json.dumps(body).encode()
        self.send_response(status)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(data)))
        self.end_headers()
        self.wfile.write(data)

    def do_GET(self):
        if self.path == "/health":
            return self._json(200, {"status": "ok"})
        self._json(404, {"error": "Not found"})

    def do_POST(self):
        if self.path != "/apply":
            return self._json(404, {"error": "Not found"})
        if not self.lock.acquire(blocking=False):
            return self._json(409, {"error": "Set devices already running"})
        try:
            self._json(200, apply(lambda line: print(line, flush=True)))
        except Exception as e:  # errore di database o di invio: lo vede anche l'interfaccia
            print(f"ERROR: {e}", file=sys.stderr, flush=True)
            self._json(500, {"error": f"Set devices failed: {e}"})
        finally:
            self.lock.release()

    def log_message(self, fmt, *args):
        pass  # niente log di accesso: nel terminale restano solo i parametri inviati


def serve():
    port = int(os.environ.get("PORT", "8000"))
    print(f"Set devices service listening on port {port}", flush=True)
    ThreadingHTTPServer(("0.0.0.0", port), Handler).serve_forever()


if __name__ == "__main__":
    if "--serve" in sys.argv:
        serve()
    else:
        apply()
