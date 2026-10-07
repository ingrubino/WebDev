# Metodo di manutenzione e integrazione di nuove funzioni

Come far evolvere l'interfaccia senza rompere quello che funziona: dove sta cosa, flusso di lavoro, ricette per i cambiamenti più comuni, aggiornamenti, backup e rilascio sul Raspberry.

> Percorsi relativi alla radice `WebDev/`. Tutti i comandi `docker compose` si lanciano dalla cartella `WebDev/docker/`, dove stanno `compose.yml` e `.env`.

---

## 1. Principi

1. **Le soglie stanno in un solo posto**, `vue/config/device-rules.json`, letto sia dal frontend sia dall'API.
2. **Ogni controllo sui dati esiste due volte**: nel browser (risposta immediata all'utente) e nell'API (l'unica vera garanzia, perché il browser si può aggirare), con le stesse chiavi di errore e gli stessi messaggi.
3. **Il frontend non parla mai col database**: tutto passa dall'API, che è l'unico codice che conosce lo SQL.
4. **Configurazione fuori dal codice**: password, porte e nomi delle immagini solo in `docker/.env`, che non va mai su GitHub (si versiona `docker/.env.example`).
5. **Ogni modifica si prova prima sul Mac**, poi si porta sul Raspberry con la procedura di rilascio.
6. **Cambiamenti piccoli**: una funzione, un branch, un commit (o una pull request) che si può annullare da solo.
7. **Tutti i testi dell'interfaccia sono in inglese.** Non usare mai `<input type="file">` visibile né `confirm()` / `alert()` del browser: il browser li mostra nella sua lingua (es. "Nessun file selezionato", "Annulla"). Usare al loro posto `FilePicker.vue` e `askConfirm()`.

---

## 2. Dove si trova cosa

| Voglio cambiare… | File |
|---|---|
| una soglia o un limite (righe, min/max, formato del nome) | `vue/config/device-rules.json` |
| come viene controllato un dato | `vue/frontend/src/validation/device.js` **e** `vue/api/src/DeviceValidator.php` |
| come vengono letti i file XML e CSV | `vue/frontend/src/validation/parsers.js` |
| l'aspetto (colori, spaziature) | `vue/frontend/src/assets/cockpit.css` |
| una schermata | `vue/frontend/src/views/*.vue` |
| il menu / gli indirizzi delle pagine | `vue/frontend/src/App.vue`, `vue/frontend/src/router.js` |
| il grafico | `vue/frontend/src/components/CartesianChart.vue` |
| le chiamate all'API dal browser | `vue/frontend/src/api/client.js` |
| gli endpoint | `vue/api/public/index.php` |
| le query SQL | `vue/api/src/DeviceRepository.php` |
| le tabelle | `vue/db/init/` (nuove installazioni) + `vue/db/migrations/` (installazioni esistenti) |
| i test delle regole | `vue/frontend/tests/device.test.js`, `vue/frontend/tests/channels.test.js` |
| numero di canali, modalità ammesse (AC/DC), modalità predefinita | `vue/config/channel-rules.json` |
| come viene controllata la configurazione dei canali | `vue/frontend/src/validation/channels.js` **e** `vue/api/src/ChannelValidator.php` |
| la schermata dei canali e la sua tabella | `vue/frontend/src/views/ChannelConfig.vue`, `vue/api/src/ChannelRepository.php`, tabella `channel_config` (`vue/db/init/02_channel_config.sql`) |
| cosa fa "Set devices" (lettura di canali e curve dal database) | `vue/canbus/set_devices.py` |
| come vengono inviati i parametri (oggi: stampa a video) | `vue/canbus/can_sender.py` (classe `ConsoleSender`, registro `SENDERS`, scelto con la variabile `CAN_SENDER`) |
| le librerie Python del servizio | `vue/canbus/requirements.txt` (oggi solo PyMySQL) |
| container, porte, versioni delle immagini | `docker/compose.yml`, `docker/frontend.Dockerfile`, `vue/api/Dockerfile`, `docker/.env` |

### Componenti da riutilizzare

| Componente | Uso |
|---|---|
| `components/FormField.vue` | qualsiasi campo di input con il suo messaggio d'errore |
| `components/CartesianChart.vue` | qualsiasi grafico X/Y |
| `components/FilePicker.vue` | scelta di un file: pulsante "Choose file" e testo "No file selected" o nome del file; proprietà `accept` e `label`, evento `select` |
| `components/ConfirmDialog.vue` + `askConfirm()` da `src/confirm.js` | richiesta di conferma al posto di `window.confirm()`: `await askConfirm('Remove device?', { okLabel: 'Remove', danger: true })` restituisce `true` o `false`; pulsanti ad es. Leave/Cancel, Remove/Cancel, Overwrite/Cancel |
| classi `.panel`, `.list`, `.split`, `.message`, `.indicator` in `assets/cockpit.css` | riquadri, tabelle, messaggi, spie di stato |

---

## 3. Ciclo di lavoro

```bash
git checkout main && git pull
git checkout -b feature/nome-funzione

cd docker
docker compose -f compose.yml -f compose.dev.yml up    # sviluppo con ricarica automatica, http://localhost:8080
```

In modalità sviluppo le modifiche a `vue/frontend`, `vue/api` e `vue/config` si vedono subito, senza ricostruire. Non serve Node sul Mac: Vite gira in un container. Se Node è installato sul Mac si può anche usare `cd vue/frontend && npm run dev` (http://localhost:5173, con lo stack normale avviato).

Prima del commit:

```bash
# test e build del frontend (con Node sul Mac)
cd vue/frontend && npm test && npm run build
# oppure senza Node, in un container
docker run --rm -v "$PWD/vue/frontend":/app -v "$PWD/vue/config":/config -w /app node:22-alpine \
    sh -c 'npm ci && npm test && npm run build'

# prova finale nello stack completo
cd docker && docker compose up -d --build
```

Poi:

```bash
git add -A && git commit -m "Aggiunge ..."
git push -u origin feature/nome-funzione     # pull request su GitHub, oppure merge in main
```

**Versioni.** Tag semantici a ogni rilascio (`git tag v1.1.0 && git push --tags`):

- `v1.0.1` correzione di un errore, nessun cambiamento visibile nell'uso;
- `v1.1.0` nuova schermata o nuovo campo, compatibile con i dati esistenti;
- `v2.0.0` modifica incompatibile (schema del database o API).

**CHANGELOG.md** nella radice: ogni modifica aggiunge una riga sotto `## [Non rilasciato]`; al rilascio la sezione prende il numero di versione. Le migrazioni del database da applicare vanno scritte lì.

```markdown
## [Non rilasciato]
### Aggiunto
- Registro eventi (/events).
### Database
- Applicare vue/db/migrations/003_event_table.sql
```

---

## 4. Ricette

### Cambiare una soglia

Esempio: portare la corrente massima da 8000 A (valore attuale) a 10000 A e il nome al massimo a 40 caratteri. In `vue/config/device-rules.json`:

```json
"identifier": { "maxLength": 40, ... },
"current": { "label": "current (A)", "min": 0, "max": 10000, "order": "nonIncreasing" }
```

Poi `docker compose up -d --build`: il frontend legge il file **al momento della build**, quindi vanno ricostruite entrambe le immagini. In modalità sviluppo basta salvare il file.

Anche `rows` e `vectorLength` stanno lì: portare `rows` a 20 dà una tabella di 20 righe senza altre modifiche.

L'ordine dei valori tra una riga e la successiva si sceglie con la chiave `order` di `time` e `current`: `"increasing"` (strettamente crescente), `"nonIncreasing"` (uguale o minore della riga precedente) oppure `null` (nessun vincolo). Oggi il tempo è `increasing` e la corrente `nonIncreasing`, con un massimo di 8000 A.

### Aggiungere una regola nuova

Esempio: la curva deve **partire dal tempo 0**.

1. `vue/config/device-rules.json`: `"time": { ..., "startsAtZero": true }`
2. `vue/frontend/src/validation/device.js`, in `validateDevice()`, dopo il ciclo sulle righe:
   ```js
   if (r.time.startsAtZero && matrix.length && matrix[0][0] !== 0 && !errors['matrix.0.0']) {
     errors['matrix.0.0'] = 'First time must be 0'
   }
   ```
   (la chiave d'errore indica la prima riga della tabella; se la prima riga può essere vuota, usare l'indice della prima riga compilata).
3. `vue/api/src/DeviceValidator.php`: stessa logica, stessa chiave `"matrix.0.0"`, stesso messaggio.
4. `vue/frontend/tests/device.test.js`: un caso che passa e uno che fallisce, poi `npm test`.
5. Aggiornare la scheda della schermata ([01-procedura-schermate.md](01-procedura-schermate.md#passo-2-compilare-una-scheda-per-ogni-schermata)).

Per regole sull'ordine tra righe non serve codice nuovo: basta la chiave `order` (vedi "Cambiare una soglia").

### Aggiungere un campo al dispositivo

Esempio: corrente nominale ("rated current").

1. **Database**: creare `vue/db/migrations/003_device_table.sql`
   ```sql
   CREATE TABLE IF NOT EXISTS device (
     identifier VARCHAR(100) PRIMARY KEY,
     rated_current DECIMAL(10,3) NULL
   );
   ```
   copiarlo anche in `vue/db/init/` (nuove installazioni) e applicarlo a quelle esistenti ([Modificare il database](#modificare-il-database)).
2. **Regole**: in `device-rules.json`, `"ratedCurrent": { "label": "rated current (A)", "min": 0, "max": 5000, "required": false }`.
3. **Validazione**: in `validateDevice()` e in `DeviceValidator::validate()` leggere `ratedCurrent`, controllarlo con `numberError` e aggiungerlo al risultato pulito.
4. **Repository** (`DeviceRepository.php`): in `save()` un `INSERT ... ON DUPLICATE KEY UPDATE` su `device`; in `find()` leggerlo con una `LEFT JOIN`; in `delete()` cancellarlo.
5. **Form**: in `emptyDevice()` / `toFormModel()` aggiungere `ratedCurrent: ''`; in `DeviceEdit.vue`:
   ```vue
   <FormField v-model="form.ratedCurrent" label="Rated current (A)" numeric
              :error="errors.ratedCurrent" :show="submitted" />
   ```
6. **XML/CSV**: se il campo arriva anche dai file, leggerlo in `validation/parsers.js`.

### Aggiungere una schermata

Prima si compila la scheda ([01-procedura-schermate.md](01-procedura-schermate.md#passo-2-compilare-una-scheda-per-ogni-schermata)). Esempio: registro eventi `/events`.

1. **API** in `vue/api/public/index.php`, prima del `respond(404, ...)` finale, e nell'elenco in testa al file:
   ```php
   if ($segments === ['events'] && $method === 'GET') {
       $rows = Db::pdo()->query("SELECT ts, device, message FROM event ORDER BY ts DESC LIMIT 200")->fetchAll();
       respond(200, $rows);
   }
   ```
   Quando gli endpoint di un'entità diventano più di due, spostare le query in una classe `vue/api/src/EventRepository.php` come `DeviceRepository`. Valori dall'utente sempre con `prepare()` + `execute()`, mai concatenati nella stringa SQL.
2. **Client** in `vue/frontend/src/api/client.js`, dentro l'oggetto `api`: `listEvents: () => request('GET', '/events'),`
3. **Vista** `vue/frontend/src/views/EventLog.vue`:
   ```vue
   <script setup>
   import { onMounted, ref } from 'vue'
   import { api } from '@/api/client'

   const events = ref([])
   const error = ref('')
   onMounted(async () => {
     try { events.value = await api.listEvents() } catch (e) { error.value = e.message }
   })
   </script>

   <template>
     <h1>Events</h1>
     <section class="panel">
       <p v-if="error" class="error">{{ error }}</p>
       <p v-else-if="!events.length">No events.</p>
       <table v-else class="list">
         <tr v-for="e in events" :key="e.ts + e.device">
           <td>{{ e.ts }}</td><td>{{ e.device }}</td><td>{{ e.message }}</td>
         </tr>
       </table>
     </section>
   </template>
   ```
4. **Rotta** in `router.js`, prima della riga finale `/:pathMatch(.*)*`:
   `{ path: '/events', name: 'events', component: () => import('./views/EventLog.vue') },`
   (l'`import()` carica la pagina solo quando serve).
5. **Menu** in `App.vue`: `<RouterLink to="/events">Events</RouterLink>`.
6. Provare con `curl http://localhost:8080/api/events`, poi la schermata, poi la checklist di collaudo.

Per una schermata di **monitoraggio** con aggiornamento periodico, copiare lo schema di `App.vue`: `setInterval` in `onMounted`, `clearInterval` in `onUnmounted`.

### Usare "Set devices" e passare al bus CAN reale

Il servizio `canbus` (`vue/canbus/`) è un programma Python in un container a parte. L'API lo chiama quando si preme "Set devices" (`POST /api/channels/apply` → `http://canbus:8000/apply`); risponde anche su `GET /health`. Si può lanciare anche a mano:

```bash
docker compose logs -f canbus                         # vedere cosa viene inviato
docker compose exec canbus python set_devices.py      # un invio da terminale, senza interfaccia
```

Per ogni canale usato legge da `channel_config` il dispositivo e la modalità AC/DC, poi da `dataset` la curva tempo/corrente e il vettore, e li passa al "sender". Oggi l'unico sender è `ConsoleSender`, che stampa a video. Per inviare davvero sul bus CAN:

1. In `vue/canbus/can_sender.py` scrivere una classe con gli stessi metodi di `ConsoleSender` (`send_channel(channel, device, mode, curve, vector)` e `close()`) che usa la libreria python-can; nel file c'è già un esempio commentato (`CanBusSender`). La codifica dei messaggi (ID, formato dei dati) dipende dal protocollo dei dispositivi e va definita.
2. Registrarla nel dizionario `SENDERS` con un nome, es. `"socketcan"`.
3. In `vue/canbus/requirements.txt` attivare la riga `python-can==4.*`.
4. In `docker/.env` impostare `CAN_SENDER=socketcan`.
5. Dare al container l'accesso all'interfaccia `can0` del Raspberry: nel servizio `canbus` di `docker/compose.yml` aggiungere `network_mode: host` (con la rete host il nome `canbus` non è più raggiungibile dagli altri container: nel servizio `api` di `compose.yml` cambiare `CANBUS_URL` con l'IP del Raspberry sulla rete locale, es. `http://192.168.1.50:8000`). L'interfaccia `can0` va attivata sul Raspberry (es. `sudo ip link set can0 up type can bitrate 500000`).
6. `docker compose up -d --build` e prova con `docker compose exec canbus python set_devices.py`.

Sul Mac non c'è un bus CAN: lì si lascia `CAN_SENDER=console`.

### Modificare il database

Gli script in `vue/db/init/` girano **solo alla prima installazione** (volume del database vuoto). Per le installazioni già attive:

1. fare un backup (sezione 7);
2. scrivere la modifica in `vue/db/migrations/NNN_descrizione.sql` (numerazione crescente; non modificare mai un file già applicato, se serve correggerlo si scrive la migrazione successiva); preferire modifiche compatibili con i dati esistenti (colonne nuove `NULL` o con valore predefinito);
3. applicarla, dalla cartella `docker/`:
   ```bash
   docker compose exec -T db sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"' \
       < ../vue/db/migrations/NNN_descrizione.sql
   ```
4. riportare la stessa modifica in `vue/db/init/` perché le nuove installazioni partano già aggiornate;
5. annotarla nel `CHANGELOG.md`.

---

## 5. Test

| Livello | Strumento | Cosa si prova | Comando |
|---|---|---|---|
| Regole e lettura file | Vitest | ogni regola con un caso valido, uno al limite, uno non valido; parser CSV/XML | `cd vue/frontend && npm test` |
| API | `curl` | ogni endpoint risponde; i dati non validi danno `422` con il campo giusto | vedi sotto |
| Schermate | a mano | la checklist di collaudo ([Passo 7](01-procedura-schermate.md#passo-7-collaudare)) | — |

```bash
curl -s http://localhost:8080/api/health
curl -s http://localhost:8080/api/devices
curl -s -X POST http://localhost:8080/api/devices -H 'Content-Type: application/json' \
     -d '{"identifier":"Prova1","matrix":[[0,100],[0,90]],"vector":[]}'
# atteso: 422 con "matrix.1.0": "Must be greater than previous row"
```

---

## 6. Aggiornamenti

| Cosa | Come | Quando |
|---|---|---|
| librerie del frontend | `cd vue/frontend && npm outdated && npm update && npm audit && npm test && npm run build` | ogni pochi mesi, su un branch `chore/aggiornamenti` |
| salti di versione maggiore (es. Vite) | leggere la guida di migrazione della libreria, poi `npm install vite@<versione>` | quando serve |
| immagini Docker | cambiare il tag (`php:8.2-apache` in `vue/api/Dockerfile`; `node:22-alpine`, `nginx:1.28-alpine` in `docker/frontend.Dockerfile`; `mariadb:11.4`, `phpmyadmin:5` in `docker/compose.yml`), poi `docker compose build --pull && docker compose up -d` | PHP 8.2 riceve patch di sicurezza fino a fine 2026: passare poi a 8.4. MariaDB 11.4 è una versione a supporto lungo |

Non usare il tag `latest` per le immagini di terze parti: un aggiornamento automatico può rompere il Raspberry senza che il codice sia cambiato. Prima di adottare un'immagine nuova verificare su Docker Hub che esista per `linux/arm64`.

Dopo ogni aggiornamento: checklist di collaudo.

---

## 7. Backup e ripristino

Dalla cartella `docker/`:

```bash
# backup
mkdir -p ../backup
docker compose exec -T db sh -c 'mariadb-dump -uroot -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"' \
    > ../backup/webdev_$(date +%F).sql

# ripristino
docker compose exec -T db sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"' \
    < ../backup/webdev_AAAA-MM-GG.sql
```

Sul Raspberry, backup ogni notte con `crontab -e`:

```
0 3 * * * cd /home/pi/WebDev/docker && docker compose exec -T db sh -c 'mariadb-dump -uroot -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"' > /home/pi/WebDev/backup/webdev_$(date +\%F).sql
```

La cartella `backup/` va esclusa da Git e copiata ogni tanto fuori dal Raspberry (la scheda SD si può guastare).

---

## 8. Rilascio sul Raspberry

Le opzioni sono descritte in dettaglio in [docker/README.md](../docker/README.md#portare-le-immagini-sul-raspberry). Il flusso di rilascio:

**Costruire sul Raspberry** (più semplice):

```bash
cd ~/WebDev && git pull            # oppure: git checkout v1.1.0
cd docker
# backup + eventuali migrazioni indicate nel CHANGELOG
docker compose up -d --build
docker compose ps                  # api e db devono risultare "healthy"
```

**Costruire sul Mac e copiare le immagini**, utile perché la build del frontend sul Raspberry è lenta. Mac M3 e Raspberry a 64 bit sono entrambi `arm64`:

```bash
# sul Mac, in WebDev/docker
docker compose build
docker save webdev/web:latest webdev/api:latest | gzip > webdev-arm64.tar.gz
scp webdev-arm64.tar.gz pi@raspberrypi.local:~/WebDev/docker/

# sul Raspberry, in ~/WebDev/docker
gunzip -c webdev-arm64.tar.gz | docker load
docker compose up -d --no-build
```

**Con un registry** (Docker Hub o GitHub Container Registry), con `IMAGE_PREFIX` e `TAG` impostati in `.env`:

```bash
# sul Mac
docker buildx bake -f docker-bake.hcl --allow=fs.read=.. --push
# sul Raspberry
docker compose pull && docker compose up -d --no-build
```

Usare come `TAG` il numero di versione (es. `1.1.0`) permette di tornare indietro cambiando solo il `.env`.

**Tornare alla versione precedente:** `git checkout v1.0.0 && docker compose up -d --build` (oppure il `TAG` precedente con `--no-build`); se la versione nuova aveva applicato una migrazione, ripristinare il backup fatto prima del rilascio.

---

## 9. Sicurezza

Da controllare a ogni rilascio, e sempre prima di esporre il sistema fuori dalla rete locale:

- [ ] `docker/.env` con password robuste, diverse da quelle di esempio, non presente su GitHub;
- [ ] phpMyAdmin (`--profile tools`) e la vecchia app (`--profile legacy`, usa `root/root`) spenti sul Raspberry;
- [ ] porta del database pubblicata solo su `127.0.0.1` (impostazione predefinita di `compose.yml`);
- [ ] query sempre con istruzioni preparate PDO;
- [ ] nessun `v-html` con dati inseriti dall'utente;
- [ ] `npm audit` senza vulnerabilità alte;
- [ ] l'interfaccia oggi **non ha login**: se deve essere raggiungibile da fuori, aggiungerlo come schermata (inventario, riga 8) oppure metterla dietro un reverse proxy con autenticazione.

---

## 10. Problemi frequenti

| Sintomo | Causa probabile | Soluzione |
|---|---|---|
| Spia "offline" nell'interfaccia | API ferma o database non ancora pronto | `docker compose ps`, `docker compose logs api db` |
| "Set devices" risponde con errore 502 | servizio `canbus` fermo o in errore | `docker compose ps canbus`, `docker compose logs canbus`, poi `docker compose up -d canbus` |
| "Set devices" risponde con errore 409 | un invio precedente è ancora in corso | attendere e riprovare |
| `imposta DB_PASSWORD nel file .env` | manca `docker/.env` | `cp .env.example .env` nella cartella `docker/` |
| Una soglia cambiata non ha effetto | il frontend legge le regole alla build | `docker compose up -d --build` |
| La tabella non esiste | volume del database creato prima dello schema | applicare `vue/db/init/01_schema.sql` a mano come una migrazione oppure, **perdendo i dati**, `docker compose down -v` e riavviare |
| Una modifica in `vue/db/init/` non ha effetto | lo schema iniziale gira solo al primo avvio | usare una migrazione |
| `no matching manifest for linux/arm/v7` | Raspberry con sistema a 32 bit (MariaDB non esiste per armv7) | installare Raspberry Pi OS 64 bit |
| Le modifiche al frontend non si vedono dopo `up -d` | immagine non ricostruita | `docker compose up -d --build`, poi ricaricare la pagina |
| Build lenta o interrotta sul Raspberry | memoria insufficiente durante `npm run build` | costruire sul Mac e copiare le immagini (sezione 8) |
