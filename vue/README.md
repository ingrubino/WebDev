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

Il salvataggio ora **sostituisce** le righe del dispositivo (la versione PHP le duplicava a ogni salvataggio) e permette di rinominarlo. Il file XML riempie il form: si controlla e poi si salva.

## Struttura

```
vue/
├─ config/device-rules.json      soglie di validazione (lette da frontend e API)
├─ config/channel-rules.json     numero di canali e modalità ammesse (AC, DC)
├─ db/init/01_schema.sql         tabella `dataset`, stesso schema della versione PHP
├─ db/init/02_channel_config.sql tabella `channel_config` (configurazione canali)
├─ db/migrations/                modifiche allo schema per installazioni esistenti
├─ canbus/                       programma Python "Set devices" (container canbus)
│  ├─ set_devices.py             legge channel_config + dataset; servizio HTTP con --serve
│  ├─ can_sender.py              invio ai dispositivi: oggi ConsoleSender (stampa a video)
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
   │  ├─ validation/device.js    validazione nel browser (stesse chiavi d'errore dell'API)
   │  ├─ validation/parsers.js   lettura file XML e CSV
   │  ├─ validation/channels.js  validazione della configurazione canali
   │  ├─ components/             FormField (input + errore), CartesianChart (grafico SVG)
   │  ├─ views/                  le schermate
   │  └─ assets/cockpit.css      stile ripreso da stile.css
   └─ tests/                     test delle regole (Vitest): device.test.js, channels.test.js
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
| `POST` | `/api/channels/apply` | "Set devices": chiama il servizio canbus; `{sent, channels:[{channel, device, mode, status}], log:[...]}`; 502 se canbus non risponde, 409 se è già in corso |

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

## Set devices (programma Python)

Il pulsante "Set devices" della pagina canali chiama `POST /api/channels/apply`; l'API inoltra la richiesta al container `canbus`, che:

1. legge `channel_config` (la configurazione **salvata**: il pulsante resta disattivato finché ci sono modifiche non salvate);
2. per ogni canale con un dispositivo carica da `dataset` la curva tempo/corrente e il vettore;
3. li passa al *sender*: oggi `ConsoleSender` li stampa a video; l'output compare nella pagina e nel terminale.

```bash
cd ../docker
docker compose logs -f canbus                     # output di ogni "Set devices"
docker compose exec canbus python set_devices.py  # lancio a mano, senza interfaccia
```

Per il bus CAN reale: scrivere in `canbus/can_sender.py` una classe con lo stesso metodo `send_channel()` (c'è un esempio commentato con python-can), registrarla in `SENDERS`, aggiungere `python-can` a `requirements.txt` e avviare con `CAN_SENDER=<nome>` nel `.env`. Sul Raspberry il container dovrà poi vedere l'interfaccia CAN (es. `network_mode: host` per `can0`).

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
npm run dev     # http://localhost:5173, /api inoltrato a localhost:8080
npm test        # test delle regole
npm run build   # verifica che la build di produzione passi
```
