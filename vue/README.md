# Device Console: codice dell'applicazione

Interfaccia **Vue 3 + Vite** e **API PHP 8.2** che sostituiscono le pagine PHP in `../src`.
Installazione e avvio dei container: vedi [`../docker`](../docker/README.md). Procedura schermate e manutenzione: vedi [`../docs`](../docs).

## Schermate

| Schermata | URL | File | Sostituisce |
|---|---|---|---|
| Elenco dispositivi (con filtro) | `/` | `frontend/src/views/DeviceList.vue` | `list_identifiers.php` |
| Nuovo / modifica dispositivo, grafico dal vivo, caricamento XML, eliminazione | `/devices/new`, `/devices/<nome>` | `frontend/src/views/DeviceEdit.vue` | `storeTable2.php`, `save.php`, `save_xml.php`, `delete.php` |
| Import CSV con anteprima e controlli | `/import` | `frontend/src/views/ImportCsv.vue` | `import.php` |

Il salvataggio ora **sostituisce** le righe del dispositivo (la versione PHP le duplicava a ogni salvataggio) e permette di rinominarlo. Il file XML riempie il form: si controlla e poi si salva.

## Struttura

```
vue/
├─ config/device-rules.json      soglie di validazione (lette da frontend e API)
├─ db/init/01_schema.sql         tabella `dataset`, stesso schema della versione PHP
├─ db/migrations/                modifiche allo schema per installazioni esistenti
├─ api/
│  ├─ Dockerfile                 php:8.2-apache (build context = vue/)
│  ├─ public/index.php           router REST: /api/health, /api/rules, /api/devices[/{id}]
│  └─ src/
│     ├─ Db.php                  connessione PDO (variabili DB_HOST, DB_NAME, DB_USER, DB_PASSWORD)
│     ├─ DeviceValidator.php     validazione lato server
│     └─ DeviceRepository.php    query sulla tabella `dataset`
└─ frontend/
   ├─ src/
   │  ├─ App.vue                 layout, menu, spia online/offline
   │  ├─ router.js               una riga per schermata
   │  ├─ api/client.js           chiamate all'API
   │  ├─ validation/device.js    validazione nel browser (stesse chiavi d'errore dell'API)
   │  ├─ validation/parsers.js   lettura file XML e CSV
   │  ├─ components/             FormField (input + errore), CartesianChart (grafico SVG)
   │  ├─ views/                  le tre schermate
   │  └─ assets/cockpit.css      stile ripreso da stile.css
   └─ tests/device.test.js       test delle regole (Vitest)
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

Errori di validazione: `422 {error, fields: {"identifier": "...", "matrix.3.0": "...", "vector.2": "..."}}`. Le chiavi sono le stesse usate dal frontend, così l'errore compare accanto al campo giusto.

## Regole di validazione

Definite in `config/device-rules.json`, implementate in `frontend/src/validation/device.js` **e** `api/src/DeviceValidator.php`:

- nome obbligatorio, max 100 caratteri, solo lettere, cifre, spazio, `_ . -`;
- ogni riga tempo/corrente compilata in entrambe le colonne o lasciata vuota; almeno 2 righe complete;
- tempo e corrente numerici e ≥ 0; tempo strettamente crescente;
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
