# Device Console: codice dell'applicazione

Interfaccia **Vue 3 + Vite** e **API PHP 8.2** che sostituiscono le pagine PHP in `../src`.
Installazione e avvio dei container: vedi [`../docker`](../docker/README.md). Procedura schermate e manutenzione: vedi [`../docs`](../docs).

## Schermate

| Schermata | URL | File | Sostituisce |
|---|---|---|---|
| Elenco dispositivi (con filtro) | `/` | `frontend/src/views/DeviceList.vue` | `list_identifiers.php` |
| Nuovo / modifica dispositivo, grafico dal vivo, caricamento XML, eliminazione | `/devices/new`, `/devices/<nome>` | `frontend/src/views/DeviceEdit.vue` | `storeTable2.php`, `save.php`, `save_xml.php`, `delete.php` |
| Import CSV con anteprima e controlli | `/import` | `frontend/src/views/ImportCsv.vue` | `import.php` |
| Configurazione canali (12 righe: dispositivo + AC/DC), link "Configure channels" nell'elenco, pulsante "Set devices" | `/channels` | `frontend/src/views/ChannelConfig.vue` | nuova |
| SCADA: 12 moduli in griglia (corrente, temperatura, codice errore, LED verde/rosso, interruttori sync/manual e ON/OFF) più un pulsante Sync ON/OFF unico, dati in tempo reale via MQTT | `/scada` | `frontend/src/views/Scada.vue`, `components/ScadaModule.vue` | nuova |

Il salvataggio ora **sostituisce** le righe del dispositivo (la versione PHP le duplicava a ogni salvataggio) e permette di rinominarlo. Il file XML riempie il form: si controlla e poi si salva.

## Struttura

```
vue/
├─ config/device-rules.json      soglie di validazione (lette da frontend e API)
├─ config/channel-rules.json     numero di canali e modalità ammesse (AC, DC)
├─ config/scada.json             pagina SCADA: colonne, secondi prima di mostrare ---, limiti dei valori, soglia LED
├─ db/init/01_schema.sql         tabella `dataset`, stesso schema della versione PHP
├─ db/init/02_channel_config.sql tabella `channel_config` (configurazione canali)
├─ db/migrations/                modifiche allo schema per installazioni esistenti
├─ canbus/                       unico programma Python verso i dispositivi (container canbus)
│  ├─ gateway.py                 ponte CAN <-> MQTT: stati, comandi, start di Set devices
│  ├─ bus.py                     collegamento ai dispositivi: oggi EmulatorBus (dispositivi simulati)
│  ├─ set_devices.py             legge channel_config + dataset e carica i parametri; lancio a mano via MQTT
│  ├─ TOPICS.md                  riferimento dei topic e dei messaggi MQTT
│  ├─ requirements.txt, Dockerfile
├─ api/
│  ├─ Dockerfile                 php:8.2-apache (build context = vue/)
│  ├─ public/index.php           router REST: /api/health, /api/rules, /api/devices[/{id}], /api/channels
│  └─ src/
│     ├─ Db.php                  connessione PDO (variabili DB_HOST, DB_NAME, DB_USER, DB_PASSWORD)
│     ├─ DeviceValidator.php     validazione lato server
│     ├─ DeviceRepository.php    query sulla tabella `dataset`
│     ├─ ChannelValidator.php    validazione della configurazione canali
│     └─ ChannelRepository.php   query sulla tabella `channel_config`
└─ frontend/
   ├─ src/
   │  ├─ App.vue                 layout, menu, spia online/offline
   │  ├─ router.js               una riga per schermata
   │  ├─ api/client.js           chiamate all'API
   │  ├─ mqtt.js                 connessione MQTT condivisa (WebSocket /mqtt), comandi, Set devices
   │  ├─ scada.js                logica SCADA senza dipendenze (griglia, lettura messaggi)
   │  ├─ validation/device.js    validazione nel browser (stesse chiavi d'errore dell'API)
   │  ├─ validation/parsers.js   lettura file XML e CSV
   │  ├─ validation/channels.js  validazione della configurazione canali
   │  ├─ components/             FormField (input + errore), CartesianChart (grafico SVG),
   │  │                          ScadaModule + SlideSwitch (modulo SCADA), FilePicker, ConfirmDialog
   │  ├─ views/                  le schermate
   │  └─ assets/cockpit.css      stile ripreso da stile.css
   └─ tests/                     test delle regole (Vitest): device.test.js, channels.test.js, scada.test.js
```

## API

| Metodo | URL | Risposta |
|---|---|---|
| `GET` | `/api/health` | `{status:"ok"}` se API e database rispondono |
| `GET` | `/api/rules` | contenuto di `device-rules.json` |
| `GET` | `/api/devices` | `[{identifier, points, updated_at}]` |
| `GET` | `/api/devices/{id}` | `{identifier, matrix:[[t,c],...], vector:[...]}` oppure 404 |
| `POST` | `/api/devices` | crea; 201, 409 se il nome esiste, 422 se non valido |
| `PUT` | `/api/devices/{id}` | aggiorna o rinomina; 200, 404, 409, 422 |
| `DELETE` | `/api/devices/{id}` | 204 oppure 404 |
| `GET` | `/api/channels` | `{channels:[{channel, device, mode, updated_at}, ...]}`, sempre 12 righe (i canali mai salvati hanno `device: null`, `mode: "AC"`) |
| `PUT` | `/api/channels` | corpo `{channels:[{device, mode}, ...]}` con 12 righe in ordine di canale; sostituisce tutta la configurazione; 200 oppure 422 |

"Set devices" e la pagina SCADA non usano l'API ma MQTT (sezione sotto).

Errori di validazione: `422 {error, fields: {"identifier": "...", "matrix.3.0": "...", "vector.2": "..."}}`. Le chiavi sono le stesse usate dal frontend, così l'errore compare accanto al campo giusto.

## Tabella `channel_config`

Una riga per canale, pensata per essere letta dal programma di backend:

| Colonna | Tipo | Significato |
|---|---|---|
| `channel` | `TINYINT` (chiave) | numero del canale, da 1 a 12 |
| `device` | `VARCHAR(100)` o `NULL` | `identifier` del dispositivo (tabella `dataset`); `NULL` = canale non usato |
| `mode` | `VARCHAR(10)` | `AC` oppure `DC` |
| `updated_at` | `TIMESTAMP` | ultimo salvataggio |

```sql
SELECT channel, device, mode FROM channel_config WHERE device IS NOT NULL ORDER BY channel;
```

Ogni salvataggio dalla pagina riscrive tutte e 12 le righe. Se un dispositivo viene rinominato, i canali seguono il nuovo nome; se viene eliminato, i suoi canali restano senza dispositivo. Lo stesso dispositivo può essere assegnato a più canali.
Sulle installazioni già esistenti l'API crea la tabella da sola al primo uso (oppure applicare `db/migrations/002_channel_config.sql`). Numero di canali e modalità si cambiano in `config/channel-rules.json` (poi `docker compose up -d --build`).

## Comunicazione con i dispositivi: MQTT

```
browser (SCADA, Set devices) ── WebSocket /mqtt ──> nginx ──> mqtt (Mosquitto)
                                                               │
                         canbus: gateway.py ───────────────────┘
                           ├─ bus.py: EmulatorBus oggi, bus CAN reale domani
                           └─ set_devices.py: parametri da MariaDB
```

Tutto passa dal broker MQTT; verso i dispositivi parla **un solo programma Python** (`canbus/gateway.py`),
così stati, comandi e caricamento dei parametri non si contendono il bus. Topic e payload: [`canbus/TOPICS.md`](canbus/TOPICS.md).

- **SCADA**: il gateway pubblica lo stato di ogni canale su `devices/<ch>/state` circa ogni secondo; gli
  interruttori della pagina pubblicano su `devices/<ch>/cmd` e mostrano sempre lo stato *riportato* dal
  dispositivo. Se il broker o il gateway non rispondono (o mancano dati da più di `staleAfterSeconds`),
  i visualizzatori mostrano `---` e gli interruttori si disattivano.
- **Logiche SCADA** (decise da Luigi il 07/10/2026):
  - LED **rosso** se il codice errore è maggiore di 15 (`alarmAbove`), altrimenti **verde** (grigio senza dati).
  - Modulo in **manual**: comanda il proprio pulsante ON/OFF (passando a manual viene inviato il suo valore attuale).
  - Modulo in **sync**: segue il pulsante **Sync ON/OFF** unico in cima alla pagina (`system/sync/cmd`);
    il suo ON/OFF è bloccato. La logica sta nel gateway, che allinea i moduli in sync anche se un
    dispositivo cambia stato da solo. All'avvio il pulsante sync è OFF (`SYNC_POWER`).
  - Limiti: corrente da -10000 a 10000 A, temperatura da 0 a 150 °C, codice errore intero da 0 a 255.
    Valori fuori limite: il gateway li scarta (log) e la pagina mostra `---`. Limiti in `config/scada.json`
    **e** in `LIMITS` di `canbus/gateway.py`, da tenere uguali.
- **Set devices**: il pulsante della pagina canali pubblica `system/set_devices/start`; il gateway legge
  `channel_config` (la configurazione **salvata**: il pulsante resta disattivato finché ci sono modifiche
  non salvate), carica da `dataset` curva e vettore di ogni canale usato, li invia al bus e risponde su
  `system/set_devices/result` con il riepilogo mostrato nella pagina. Un solo caricamento alla volta.
- **Emulatore** (`DEVICE_BUS=emulator`, default): 12 moduli simulati; da accesi la corrente segue il valore
  nominale (prima corrente della curva caricata, altrimenti 90 + 4×canale A) e la temperatura sale verso
  25 + 0,4×|I| °C; codice 5 sopra 70 °C (LED verde), 20 sopra 85 °C (LED rosso). I parametri caricati vengono stampati.

```bash
cd ../docker
docker compose logs -f canbus                     # parametri caricati e comandi ricevuti
docker compose exec canbus python set_devices.py  # Set devices a mano (stesso percorso del pulsante)
docker compose exec mqtt mosquitto_sub -t '#' -v  # tutti i messaggi MQTT
```

Per il bus CAN reale: scrivere in `canbus/bus.py` una classe con gli stessi metodi di `EmulatorBus`
(c'è un esempio commentato con python-can), registrarla in `BUSES`, aggiungere `python-can` a
`requirements.txt` e avviare con `DEVICE_BUS=<nome>`. Pagina e topic non cambiano.
Sul Raspberry il container dovrà vedere l'interfaccia CAN (es. `network_mode: host` per `can0`);
in quel caso il broker e il database vanno pubblicati sull'host e raggiunti su `127.0.0.1` (`MQTT_HOST`, `DB_HOST`), vedi `../docker/README.md`.

Colonne della griglia SCADA: `config/scada.json` (`"columns": 3` → 4 righe da 3 moduli); letto alla build,
quindi dopo la modifica `docker compose up -d --build web`. Su schermi stretti le colonne scendono a 2 e poi a 1.

## Regole di validazione

Definite in `config/device-rules.json`, implementate in `frontend/src/validation/device.js` **e** `api/src/DeviceValidator.php`:

- nome obbligatorio, max 100 caratteri, solo lettere, cifre, spazio, `_ . -`;
- ogni riga tempo/corrente compilata in entrambe le colonne o lasciata vuota; almeno 2 righe complete;
- tempo e corrente numerici e ≥ 0; corrente al massimo `current.max` (oggi 8000 A); tempo strettamente crescente (`"order": "increasing"`), corrente decrescente, cioè uguale o minore della riga precedente (`"order": "nonIncreasing"`); l'ordine si cambia o si toglie (`null`) nel JSON;
- valori del vettore numerici (opzionali); virgola decimale accettata (`1,5`).

Il frontend legge il JSON **al momento della build**: dopo averlo modificato vanno ricostruite entrambe le immagini (`docker compose up -d --build`).

## Sviluppo senza Docker per il frontend

Con lo stack avviato da `../docker` (interfaccia su `localhost:8080`):

```bash
cd frontend
npm install
npm run dev     # http://localhost:5173, /api e /mqtt inoltrati a localhost:8080
npm test        # test delle regole
npm run build   # verifica che la build di produzione passi
```
