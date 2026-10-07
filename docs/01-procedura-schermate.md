# Procedura passo passo per definire e completare le schermate

Questa procedura porta l'interfaccia da "le schermate che esistono oggi" a "il sistema completo di gestione e monitoraggio", una schermata alla volta, senza riscrivere quello che già funziona.

> Percorsi relativi alla radice `WebDev/`. Il codice dell'applicazione è in `vue/` (descritto in [vue/README.md](../vue/README.md)), i container in `docker/` (descritti in [docker/README.md](../docker/README.md)). I comandi `docker compose` si lanciano da `WebDev/docker/`.

---

## Passo 0. Stato di partenza

La versione PHP originale (`src/`) è già stata migrata così:

| File originale | Nuova schermata / componente | Note |
|---|---|---|
| `list_identifiers.php` | `vue/frontend/src/views/DeviceList.vue` (`/`) | aggiunti filtro per nome, numero di punti, data |
| `storeTable2.php`, `storeTable.php` | `views/DeviceEdit.vue` (`/devices/new`, `/devices/<nome>`) | validazione campo per campo, grafico aggiornato mentre si scrive, avviso se si esce con modifiche non salvate |
| `GraficoCartesiano.php`, `grafico.php` | `components/CartesianChart.vue` | stesso grafico SVG, generato nel browser |
| `save.php` | `POST` / `PUT /api/devices` | ora **sostituisce** le righe invece di duplicarle; permette di rinominare |
| `save_xml.php`, `upload_xml.php`, `process_xml.php` | pulsante "Load data from XML file" in `DeviceEdit.vue` | il file riempie il form; si controlla e poi si salva |
| `delete.php` | pulsante "Remove" + `DELETE /api/devices/<nome>` | con richiesta di conferma |
| `import.php` (non aveva una pagina) | `views/ImportCsv.vue` (`/import`) | anteprima, controlli, importazione dei soli dispositivi validi |
| (nuova) | `views/ChannelConfig.vue` (`/channels`) | configurazione dei 12 canali: dispositivo e modalità AC/DC, salvata nella tabella `channel_config` per il programma di backend |
| (nuova) | `views/Scada.vue` (`/scada`), `components/ScadaModule.vue` | pagina SCADA: 12 moduli con misure e comandi in tempo reale via MQTT |
| `header.php`, `footer.php`, `stile.css` | `App.vue`, `assets/cockpit.css` | stesso tema "cockpit", più una spia di stato API/database |
| `index.php` (phpinfo), `index2.php`, `script.js`, `test_class_genGraph.php` | non migrati | erano pagine di prova |

Difetti della versione PHP corretti nella migrazione:

1. Salvare di nuovo un dispositivo esistente aggiungeva altre 10 righe con lo stesso nome; ora il salvataggio sostituisce le righe in un'unica transazione.
2. Nessun controllo sui valori (vuoti, testo, tempi non ordinati); ora le regole sono applicate sia nel browser sia nell'API.
3. Cancellazione con un semplice link e nome stampato senza escape; ora c'è la conferma e Vue mostra i dati sempre con escape.
4. Credenziali `root/root` ripetute in ogni file; ora stanno solo in `docker/.env`.

La vecchia interfaccia resta consultabile per confronto con `docker compose --profile legacy up -d` su http://localhost:8082 (solo in locale: usa `root/root`).

---

## Passo 1. Fare l'inventario delle schermate

Elencare tutte le schermate che il sistema finale deve avere, anche quelle non ancora progettate. Proposta di partenza da confermare:

| # | Schermata | Stato | Priorità |
|---|---|---|---|
| 1 | Elenco dispositivi | fatta | |
| 2 | Nuovo / dettaglio / modifica dispositivo (con import XML) | fatta | |
| 3 | Import CSV | fatta | |
| 3b | Configurazione canali (Configure channels) | fatta | |
| 4 | SCADA: stato e comandi dei 12 moduli in tempo reale (MQTT, oggi con emulatore) | fatta | |
| 5 | Stato del singolo dispositivo (storico delle misure, grafico nel tempo) | da definire | alta |
| 6 | Configurazione parametri del dispositivo (soglie, comandi) | da definire | media |
| 7 | Registro eventi e allarmi | da definire | media |
| 8 | Accesso e utenti (login) | da definire | bassa in rete locale, alta se esposto |

Per ogni riga decidere: serve davvero? Chi la usa? Da dove arrivano i dati (database, dispositivo via rete, seriale, MQTT)?

---

## Passo 2. Compilare una scheda per ogni schermata

Copiare questo modello in `docs/schermate/<nome>.md` e compilarlo **prima** di scrivere codice:

```markdown
# <Nome schermata>

- Scopo: (una frase: cosa ottiene l'utente)
- URL: /...
- Chi la usa e quando:
- Dati mostrati: (endpoint di origine, frequenza di aggiornamento)
- Dati inseriti:

  | campo | tipo | obbligatorio | regole (min, max, formato) | messaggio d'errore |
  |---|---|---|---|---|

- Azioni: (pulsanti, cosa succede, conferme richieste)
- Stati: caricamento / nessun dato / errore API / dispositivo offline
- Navigazione: da dove si arriva, dove si va
- Schizzo: (foto di un disegno a mano o wireframe)
- Collaudo: (2–4 controlli verificabili a mano)
```

La tabella "dati inseriti" diventa direttamente le regole di validazione del Passo 4.

### Esempio: la scheda della schermata esistente "Dispositivo"

- **Scopo:** inserire, consultare e correggere un dispositivo e vederne la curva di protezione.
- **URL:** `/devices/new`, `/devices/<nome>` · **API:** `GET`, `POST`, `PUT`, `DELETE /api/devices[/<nome>]`
- **Dati inseriti:**

  | campo | tipo | obbligatorio | regole | messaggio |
  |---|---|---|---|---|
  | nome | testo | sì | max 100 caratteri; lettere, cifre, spazio, `_ . -` | `Required`, `Max 100 characters`, `Allowed: ...` |
  | tempo (ms) | numero | se c'è la corrente | 0 – 1.000.000; strettamente crescente | `Must be greater than previous row` |
  | corrente (A) | numero | se c'è il tempo | 0 – 8.000; uguale o minore della riga precedente | `Max 8000`, `Must not exceed previous row`, `Required when time is set` |
  | righe complete | | | da 2 a 10 | `At least 2 complete rows` |
  | vettore | numeri | no | fino a 10 valori; virgola decimale accettata | `Must be a number` |

- **Azioni:** Save (con conferma di sovrascrittura se il nome esiste già), Remove (con conferma), Load data from XML file.
- **Stati:** caricamento, dispositivo inesistente, API non raggiungibile, modifiche non salvate.
- **Collaudo:** `src/data.xml` si carica e si salva; tempi non crescenti, correnti che risalgono o valori negativi bloccano il salvataggio con il messaggio sulla riga giusta; salvare due volte non crea righe duplicate nel database.

### Esempio: la scheda della schermata "Configurazione canali"

- **Scopo:** associare a ciascuno dei 12 canali un dispositivo e la modalità AC/DC; la configurazione salvata viene letta da un programma di backend.
- **URL:** `/channels` (link "Configure channels" nell'elenco dispositivi, voce "Channels" nel menu) · **API:** `GET`, `PUT /api/channels`
- **Dati inseriti:**

  | campo | tipo | obbligatorio | regole | messaggio |
  |---|---|---|---|---|
  | dispositivo | menu a discesa | no ("— not used —") | deve essere un dispositivo esistente; lo stesso dispositivo può stare su più canali | `Unknown device` |
  | modalità | AC / DC | sì | uno dei valori di `modes`; predefinito `AC` | `Choose AC or DC` |

- **Azioni:** Save configuration, Discard changes, **Set devices**. "Set devices" pubblica il comando MQTT `system/set_devices/start`; il gateway Python (`canbus`) legge la configurazione salvata e, per ogni canale usato, la curva e il vettore del dispositivo, li invia ai dispositivi (oggi all'emulatore, che li stampa nel log) e risponde su `system/set_devices/result`. Il pulsante è disattivato se ci sono modifiche non salvate ("Save the configuration first"), se nessun canale è usato o se il broker MQTT non è collegato; il risultato compare nel pannello "Set devices output".
- **Dati salvati:** tabella `channel_config` (`channel` 1–12, `device` o `NULL`, `mode` `AC`/`DC`, `updated_at`). Rinominare un dispositivo aggiorna i canali; eliminarlo li lascia liberi (`NULL`).
- **Regole configurabili:** `vue/config/channel-rules.json` (`channels`: numero di righe, `modes`, `defaultMode`); dopo una modifica serve `docker compose up -d --build`.

### Esempio: la scheda della schermata "SCADA"

- **Scopo:** vedere in tempo reale lo stato dei 12 moduli e comandarli.
- **URL:** `/scada` (voce "SCADA" nella barra in alto) · **Dati:** MQTT, non l'API REST (topic in [vue/canbus/TOPICS.md](../vue/canbus/TOPICS.md)); intestazioni dei moduli da `GET /api/channels`.
- **Disposizione:** 12 moduli in una griglia; il numero di colonne si imposta in `vue/config/scada.json` (`columns: 3` → 4 righe; 4 → 3 righe; 6 → 2 righe). Su schermi stretti diventano 2 colonne (sotto 900 px) e 1 colonna (sotto 560 px).
- **Ogni modulo:** intestazione "CH n" con dispositivo e AC/DC presi da `channel_config`; display corrente (A), temperatura (°C) ed E (codice errore); LED; interruttori sync/manual e ON/OFF.
- **Valori ammessi** (`limits` in `vue/config/scada.json`, uguali a `LIMITS` in `vue/canbus/gateway.py`):

  | grandezza | minimo | massimo | note |
  |---|---|---|---|
  | corrente (A) | -10000 | 10000 | il segno negativo è ammesso |
  | temperatura (°C) | 0 | 150 | |
  | codice errore | 0 | 255 | intero; 0 = nessun errore |

  Un valore fuori limite viene scartato dal gateway (pubblicato come `null` e scritto nel log) e il display mostra `---`.
- **LED:** verde se il codice errore è ≤ 15, rosso se è > 15 (soglia `alarmAbove` in `scada.json`), grigio se mancano dati.
- **Logica sync/manual:**
  - in cima alla pagina c'è un interruttore unico **Sync ON/OFF** con il contatore "N module(s) in sync";
  - i moduli in **sync** seguono quell'interruttore e il loro ON/OFF è bloccato; il gateway ignora i comandi di accensione del singolo modulo e riallinea quelli che divergono (al massimo ogni 2 secondi);
  - i moduli in **manual** usano il proprio interruttore ON/OFF; passando a manual la pagina invia `{"control": "manual", "power": <valore attuale>}`, così il modulo non cambia stato;
  - all'avvio del gateway il Sync è OFF (variabile `SYNC_POWER`, predefinito `off`) e i moduli emulati partono in sync e spenti.
- **Dati mostrati:** `devices/<ch>/state`, circa ogni secondo. Gli interruttori mostrano lo stato **riportato dal dispositivo**, non quello richiesto: un comando (`devices/<ch>/cmd`) si vede solo quando arriva lo stato successivo.
- **Stati:** in cima alla pagina "Broker connected/disconnected" e "Gateway online (emulator)/offline". Se il broker o il gateway non rispondono, o un modulo non manda dati da `staleAfterSeconds` secondi (5), il modulo mostra `---` e gli interruttori sono disattivati.
- **Collaudo:** con l'emulatore i 12 moduli si aggiornano; con Sync ON tutti i moduli in sync si accendono insieme e il loro ON/OFF resta bloccato; un modulo in manual si accende e spegne da solo, la corrente va a 0 e la temperatura scende; sopra 70 °C l'emulatore dà errore 5 (LED verde), sopra 85 °C errore 20 (LED rosso); fermando il gateway (`docker compose stop canbus`) la pagina mostra "Gateway offline" e `---`.

---

## Passo 3. Definire dati e API

Per ogni schermata nuova:

1. **Tabelle.** Scrivere lo SQL in un nuovo file `vue/db/migrations/NNN_descrizione.sql` (procedura in [02-manutenzione.md](02-manutenzione.md#modificare-il-database)).
   - Per i dati del dispositivo che non sono punti della curva (modello, matricola, posizione) conviene una tabella `device` separata invece di ripeterli su ogni riga di `dataset`.
   - Per le misure del monitoraggio, una tabella `measurement (device, ts, nome, valore)` con indice su `(device, ts)`.
2. **Endpoint.** Aggiungerli all'elenco in testa a `vue/api/public/index.php` con metodo, URL, corpo della richiesta e risposta. Convenzioni già in uso:
   - JSON in ingresso e in uscita;
   - `422` con `{ "error": "...", "fields": { "campo": "messaggio" } }` per errori di validazione;
   - `404` risorsa inesistente, `409` conflitto (nome già usato).
3. **Aggiornamento in tempo reale**: i dati dei dispositivi arrivano via MQTT (broker Mosquitto, nel browser su WebSocket `/mqtt`), come nella pagina SCADA. Per un dato nuovo si definisce prima il topic e il payload in `vue/canbus/TOPICS.md`, poi lo si pubblica dal gateway e lo si legge nella pagina. Per dati che cambiano di rado e stanno nel database basta un polling ogni N secondi, come la spia online/offline in `App.vue`.

API attuale, per riferimento:

| Metodo | URL | Risposta |
|---|---|---|
| `GET` | `/api/health` | `{ "status": "ok" }` se API e database rispondono |
| `GET` | `/api/rules` | contenuto di `device-rules.json` |
| `GET` | `/api/devices` | `[{ identifier, points, updated_at }]` |
| `GET` | `/api/devices/<nome>` | `{ identifier, matrix: [[tempo, corrente], ...], vector: [...] }` oppure `404` |
| `POST` | `/api/devices` | crea; `201`, `409` se il nome esiste, `422` se non valido |
| `PUT` | `/api/devices/<nome>` | aggiorna o rinomina; `200`, `404`, `409`, `422` |
| `DELETE` | `/api/devices/<nome>` | `204` oppure `404` |
| `GET` | `/api/channels` | `{ channels: [{ channel, device, mode }, ...] }`, sempre 12 righe |
| `PUT` | `/api/channels` | sostituisce tutta la configurazione `{ channels: [{ device, mode }, ...] }`; `200`, `422` |

Le chiavi degli errori sono le stesse del form (`identifier`, `matrix`, `matrix.3.0`, `vector.2`, `channels.4.device`, `channels.4.mode`): così l'errore restituito dal server compare accanto al campo giusto.

---

## Passo 4. Scrivere le regole di validazione

1. Aggiungere le soglie in `vue/config/device-rules.json` (o in un nuovo file `vue/config/<entità>-rules.json`).
2. Implementare il controllo in JavaScript (`vue/frontend/src/validation/`) e in PHP (`vue/api/src/`), con **le stesse chiavi di errore e gli stessi messaggi**.
3. Scrivere i test in `vue/frontend/tests/`: per ogni regola un caso valido, uno al limite e uno non valido.

Il frontend legge il file JSON **al momento della build**: dopo una modifica vanno ricostruite le immagini (`docker compose up -d --build`).

---

## Passo 5. Disegnare la schermata con i componenti esistenti

Riutilizzare prima di creare:

| Componente | Uso |
|---|---|
| `components/FormField.vue` | qualsiasi campo di input con il suo messaggio d'errore |
| `components/CartesianChart.vue` | qualsiasi grafico X/Y (curve, andamento delle misure nel tempo) |
| `components/SlideSwitch.vue` | interruttore a due posizioni (es. sync/manual, ON/OFF) che mostra lo stato reale; `null` = sconosciuto |
| `components/ScadaModule.vue` | modulo completo di un canale (display, LED, interruttori), riusabile in altre pagine di monitoraggio |
| `src/mqtt.js` | collegamento al broker MQTT dal browser (stato della connessione, iscrizione ai topic, invio comandi) |
| `components/FilePicker.vue` | scelta di un file: pulsante "Choose file" e testo "No file selected" o nome del file; proprietà `accept` e `label`, evento `select` |
| `components/ConfirmDialog.vue` + `askConfirm()` da `src/confirm.js` | richiesta di conferma al posto di `window.confirm()`: `await askConfirm('Remove device?', { okLabel: 'Remove', danger: true })` restituisce `true` o `false`; pulsanti ad es. Leave/Cancel, Remove/Cancel, Overwrite/Cancel |
| classi `.panel`, `.list`, `.split`, `.message`, `.indicator` in `assets/cockpit.css` | riquadri, tabelle, messaggi, spie di stato |

Se un elemento si ripete in due schermate, estrarlo in `components/`.

**Tutti i testi dell'interfaccia sono in inglese.** Non usare mai `<input type="file">` visibile né `confirm()` / `alert()` del browser: il browser li mostra nella sua lingua (es. "Nessun file selezionato", "Annulla"). Usare al loro posto `FilePicker.vue` e `askConfirm()`.

---

## Passo 6. Implementare nell'ordine

Per ogni schermata, in quest'ordine (ogni passo si prova da solo prima del successivo):

1. migrazione del database, provata in phpMyAdmin (`docker compose --profile tools up -d`, http://localhost:8081);
2. endpoint API, provati con `curl` (es. `curl http://localhost:8080/api/devices`);
3. regole e test, con `npm test`;
4. vista in `vue/frontend/src/views/`, una riga in `router.js`, un link nel menu di `App.vue`;
5. prova manuale in modalità sviluppo (`docker compose -f compose.yml -f compose.dev.yml up`).

La ricetta dettagliata, con il codice, è in [02-manutenzione.md](02-manutenzione.md#aggiungere-una-schermata).

---

## Passo 7. Collaudare

Una schermata è finita quando:

- [ ] la sua scheda (Passo 2) è scritta e aggiornata;
- [ ] funziona con dati validi, al limite e non validi, con il messaggio accanto al campo giusto;
- [ ] l'API rifiuta gli stessi dati non validi anche se chiamata direttamente con `curl`;
- [ ] gli stati vuoto, caricamento ed errore sono visibili (provare fermando il database: `docker compose stop db`);
- [ ] resta leggibile su uno schermo piccolo (finestra stretta o telefono);
- [ ] tutti i testi visibili sono in inglese, senza finestre o controlli nativi del browser (`confirm()`, `alert()`, `<input type="file">`);
- [ ] `npm test` e `npm run build` passano senza errori;
- [ ] è stata provata nello stack Docker completo (`docker compose up -d --build`) sul Mac;
- [ ] la modifica è annotata nel `CHANGELOG.md`.

---

## Passo 8. Portare sul Raspberry

1. Commit e tag di versione (`git tag v1.1.0 && git push --tags`).
2. Sul Raspberry: backup, `git pull`, eventuali nuove migrazioni, `docker compose up -d --build` (oppure le immagini già costruite sul Mac: vedi [02-manutenzione.md](02-manutenzione.md#8-rilascio-sul-raspberry)).
3. Verificare la spia verde "online" e aprire almeno una schermata per tipo.

Il Mac M3 e il Raspberry con sistema a 64 bit sono entrambi `arm64`: quello che funziona sul Mac nei container funziona uguale sul Raspberry.
