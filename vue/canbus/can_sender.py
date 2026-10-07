"""Invio dei parametri ai dispositivi esterni.

Oggi c'è solo ConsoleSender, che stampa a video quello che verrebbe inviato.
Per il bus CAN reale: scrivere una classe con lo stesso metodo send_channel()
(per esempio con la libreria python-can) e registrarla in SENDERS; poi avviare
con CAN_SENDER=<nome>. Il resto del programma non cambia.
"""


class ConsoleSender:
    """Stampa i parametri di ogni canale (modalità debug)."""

    def __init__(self, log):
        self.log = log  # funzione che stampa una riga

    def send_channel(self, channel, device, mode, curve, vector):
        """curve = [(tempo_ms, corrente_A), ...]; vector = [valore o None, ...]"""
        self.log(f"CH {channel:02d}  device={device}  mode={mode}")
        self.log("  curve (time ms -> current A): " + ", ".join(f"{fmt(t)} -> {fmt(c)}" for t, c in curve))
        values = [fmt(v) for v in vector if v is not None]
        self.log("  vector: " + (", ".join(values) if values else "(empty)"))

    def close(self):
        pass


# Esempio per il futuro (non attivo): stessa interfaccia, invio con python-can.
#
# class CanBusSender:
#     def __init__(self, log, channel="can0", interface="socketcan"):
#         import can
#         self.log = log
#         self.bus = can.Bus(channel=channel, interface=interface)
#
#     def send_channel(self, channel, device, mode, curve, vector):
#         for msg in encode(channel, mode, curve, vector):   # protocollo da definire
#             self.bus.send(msg)
#         self.log(f"CH {channel:02d} sent to CAN bus")
#
#     def close(self):
#         self.bus.shutdown()

SENDERS = {"console": ConsoleSender}


def fmt(x):
    """Numero senza decimali inutili: 100.0 -> 100, 1.5 -> 1.5"""
    return f"{x:g}"
