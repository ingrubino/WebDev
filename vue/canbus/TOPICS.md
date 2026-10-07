# Topic MQTT

Unico riferimento per i messaggi scambiati tra interfaccia web, gateway Python (`gateway.py`)
e dispositivi. Tutti i payload sono JSON UTF-8. `<ch>` = numero del canale, da 1 a 12 (senza zeri).

Broker: Mosquitto (container `mqtt`).
- dai container: `mqtt:1883`
- dal browser: WebSocket `ws://<host>/mqtt` (inoltrato da nginx a `mqtt:9001`)
- dal Mac / Raspberry per il debug: `localhost:1883`
  (`mosquitto_sub -h localhost -t '#' -v`)

## Stato dei dispositivi (gateway → interfaccia)

`devices/<ch>/state`, pubblicato circa ogni secondo, QoS 0, non retained.

```json
{"channel": 1, "current": 145, "temperature": 80, "error": 20,
 "control": "sync", "power": "on", "ts": 1760000000.0}
```

| campo         | tipo                  | significato                                                    |
|---------------|-----------------------|----------------------------------------------------------------|
| `current`     | numero, A             | corrente misurata, da -10000 a 10000 (può essere negativa)     |
| `temperature` | numero, °C            | temperatura, da 0 a 150                                        |
| `error`       | intero                | codice errore, da 0 a 255 (0 = nessun errore)                  |
| `control`     | `sync`/`manual`       | chi comanda l'ON/OFF: il pulsante sync unico o il modulo       |
| `power`       | `on`/`off`            | uscita accesa o spenta                                         |
| `ts`          | secondi Unix          | istante della misura                                           |

Il gateway scarta i valori fuori da questi limiti (o di tipo sbagliato) e li pubblica come `null`;
la pagina mostra `---`. Limiti: `LIMITS` in `gateway.py` e `limits` in `vue/config/scada.json`
(vanno tenuti uguali). Il LED del modulo è rosso se `error` > 15 (`alarmAbove` in `scada.json`),
altrimenti verde. Nell'emulatore: 5 = temperatura sopra 70 °C, 20 = sopra 85 °C.

Nota: `control` (sync/manual) non è la modalità AC/DC della configurazione canali,
che nei messaggi si chiama `acdc`.

## Comandi a un modulo (interfaccia → gateway)

`devices/<ch>/cmd`, QoS 1. Uno o più campi:

```json
{"control": "manual", "power": "on"}
{"control": "sync"}
{"power": "off"}
```

- `manual`: il modulo usa il proprio ON/OFF; passando a manual la pagina invia anche il valore
  attuale del pulsante ON/OFF del modulo.
- `sync`: il modulo segue il pulsante sync unico; il gateway gli invia subito quel valore e ignora
  i comandi `power` del singolo modulo finché resta in sync.

Il gateway li inoltra al dispositivo; il nuovo stato arriva con il messaggio `state` successivo
(l'interfaccia mostra sempre lo stato reale, non quello richiesto).

## Pulsante sync unico

`system/sync/cmd` (interfaccia → gateway), QoS 1: `{"power": "on"}` oppure `{"power": "off"}`.
Il gateway lo invia a tutti i moduli in modalità sync e pubblica il nuovo stato su

`system/sync/state` (gateway → interfaccia), retained: `{"power": "off"}`.

All'avvio del gateway il pulsante sync vale `SYNC_POWER` (default `off`). Se un modulo in sync
riporta un ON/OFF diverso, il gateway gli ripete il comando (al massimo ogni 2 secondi).

## Set devices (caricamento parametri)

`system/set_devices/start` (interfaccia → gateway), QoS 1:

```json
{"id": "a1b2c3"}
```

Il gateway legge `channel_config` e i parametri di ogni dispositivo dal database e li invia
ai canali usati. Un solo caricamento alla volta: se ne arriva un altro mentre il primo è in
corso, risponde subito con `ok: false`.

`system/set_devices/result` (gateway → interfaccia), QoS 1, stesso `id` della richiesta:

```json
{"id": "a1b2c3", "ok": true, "sent": 3,
 "channels": [{"channel": 1, "device": "DEV-A", "acdc": "AC", "status": "sent", "points": 4}],
 "log": ["=== Set devices ... ===", "CH 01  device=DEV-A  acdc=AC", "..."]}
```

In caso di errore: `{"id": "...", "ok": false, "error": "messaggio"}`.

## Stato del gateway

`system/gateway/status`, retained (con Last Will: se il gateway cade il broker pubblica `offline`):

```json
{"status": "online", "bus": "emulator"}
{"status": "offline"}
```
