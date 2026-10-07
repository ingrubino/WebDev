# Docker: Mac M3 e Raspberry Pi

Questa cartella contiene tutto il necessario per eseguire l'interfaccia Vue (`../vue`) in container, identica su Mac Apple Silicon, Raspberry Pi e PC.

| Servizio | Immagine | Cosa fa | Porta |
|---|---|---|---|
| `web` | `webdev/web` (da `frontend.Dockerfile`) | build Vue + nginx; inoltra `/api/` al backend e `/mqtt` (WebSocket) al broker | `8080` |
| `api` | `webdev/api` (da `../vue/api/Dockerfile`) | API PHP JSON | interna |
| `mqtt` | `eclipse-mosquitto:2` | broker MQTT: unico canale tra pagina SCADA e dispositivi (accesso anonimo, rete locale) | `127.0.0.1:1883` (debug) |
| `canbus` | `webdev/canbus` (da `../vue/canbus/Dockerfile`) | gateway Python CAN ↔ MQTT (`gateway.py`) e "Set devices"; dispositivi da `DEVICE_BUS`, default `emulator` | interna |
| `db` | `mariadb:11.4` | database; schema da `../vue/db/init` al primo avvio | `127.0.0.1:3306` |
| `phpmyadmin` | `phpmyadmin:5` | amministrazione DB (profilo `tools`) | `8081` |
| `legacy` | `webdev/api` + `../src` | vecchia app PHP per confronto (profilo `legacy`) | `8082` |

## File

```
docker/
├─ compose.yml                 stack di produzione (Mac, Raspberry, PC)
├─ compose.dev.yml             override per sviluppo con hot reload
├─ frontend.Dockerfile         multi-stage: node (build) -> nginx (runtime)
├─ frontend.Dockerfile.dockerignore
├─ nginx/default.conf          SPA + proxy /api e /mqtt (produzione)
├─ nginx/dev.conf              proxy verso Vite + /api e /mqtt (sviluppo)
├─ mosquitto/mosquitto.conf    broker MQTT: listener 1883 + WebSocket 9001
├─ docker-bake.hcl             build multi-architettura con buildx
└─ .env.example                porte, password, nomi immagini
```

## Avvio rapido (Mac o Raspberry)

```bash
cd WebDev/docker
cp .env.example .env          # poi cambia le password in .env
docker compose up -d --build
docker compose ps             # api e db devono risultare "healthy"
```

Apri http://localhost:8080 (sul Raspberry: `http://<ip-del-raspberry>:8080`).

```bash
docker compose --profile tools up -d    # aggiunge phpMyAdmin su :8081
docker compose logs -f api              # log di un servizio
docker compose down                     # ferma (i dati restano nel volume)
docker compose down -v                  # ferma e CANCELLA il database
```

## Sviluppo sul Mac con hot reload

```bash
docker compose -f compose.yml -f compose.dev.yml up
```

Su http://localhost:8080 nginx inoltra `/` al server Vite e `/api/` al PHP. Le modifiche in `../vue/frontend` e `../vue/api` si vedono subito, senza ricostruire le immagini. Non serve installare Node sul Mac.

## MQTT e pagina SCADA

```
browser ── WebSocket /mqtt ──> nginx (web) ──> mqtt:9001
                                                  │  MQTT 1883
                                       canbus (gateway.py) ── emulatore / bus CAN
```

- Il browser non apre porte nuove: si collega a `ws://<host>:8080/mqtt`, che nginx inoltra al broker.
- I topic sono descritti in `../vue/canbus/TOPICS.md`.
- Per guardare il traffico dalla macchina che esegue lo stack: `mosquitto_sub -h localhost -t '#' -v` (porta `MQTT_PORT`, default 1883, esposta solo su `127.0.0.1`).
- `DEVICE_BUS=emulator` (default) simula i dispositivi; log con `docker compose logs -f canbus`.
- `SYNC_POWER=off` (default) oppure `on`: stato del pulsante Sync della pagina SCADA quando parte il gateway. Dopo averlo cambiato in `.env`: `docker compose up -d canbus`.
- `mqtt` è un'immagine pronta, quindi `docker-bake.hcl` non cambia.

### Bus CAN reale (in futuro)

L'interfaccia `can0` del Raspberry vive nella rete dell'host, non in quella dei container. Il servizio `canbus` dovrà quindi girare con `network_mode: host`, e da lì raggiungerà broker e database sulle porte già esposte su `127.0.0.1`:

```yaml
  canbus:
    network_mode: host
    environment:
      MQTT_HOST: 127.0.0.1
      DB_HOST: 127.0.0.1
      DEVICE_BUS: can          # valore definito in vue/canbus
```

## Architetture supportate

| Piattaforma | Esempio | Supporto |
|---|---|---|
| `linux/arm64` | Mac M1/M2/M3, Raspberry Pi 3/4/5 con OS **64 bit** | completo (consigliato) |
| `linux/amd64` | PC Intel/AMD | completo |
| `linux/arm/v7` | Raspberry Pi con OS 32 bit | non supportato per lo stack completo: **l'immagine ufficiale MariaDB non esiste per armv7** (Mosquitto usa la variante arm/v6) |

Sul Raspberry usa Raspberry Pi OS **64 bit** (verifica con `uname -m`: deve dare `aarch64`).

La build Vue gira sempre sull'architettura della macchina che compila (`--platform=$BUILDPLATFORM`): il risultato è HTML/JS/CSS uguale per tutti, quindi compilare per il Raspberry dal Mac non richiede emulazione per la parte Node.

## Portare le immagini sul Raspberry

### Opzione A: compilare direttamente sul Raspberry (più semplice)

Copia o clona il progetto sul Raspberry ed esegui l'avvio rapido. Su un Pi 4/5 la prima build richiede qualche minuto.

### Opzione B: compilare sul Mac con buildx e pubblicare su un registry

Una sola volta, sul Mac:

```bash
docker login                                  # Docker Hub (o: docker login ghcr.io)
docker buildx create --name multi --use       # builder multi-architettura
docker buildx inspect --bootstrap             # deve elencare linux/arm64 e linux/arm/v7
```

Poi, a ogni rilascio, nella cartella `docker/`:

```bash
export IMAGE_PREFIX=<utente-dockerhub>/webdev   # oppure ghcr.io/<utente>/webdev
export TAG=1.0.0
docker buildx bake -f docker-bake.hcl --allow=fs.read=.. --push
```

`--allow=fs.read=..` autorizza buildx a leggere la cartella `WebDev`, che sta sopra `docker/`. Per compilare solo alcune architetture: `PLATFORMS=linux/arm64,linux/amd64 docker buildx bake ...`.

Sul Raspberry, nel file `.env` imposta gli stessi `IMAGE_PREFIX` e `TAG`, poi:

```bash
docker compose pull
docker compose up -d --no-build
```

### Opzione C: senza registry (file .tar)

Sul Mac:

```bash
PLATFORMS=linux/arm64 docker buildx bake -f docker-bake.hcl --allow=fs.read=.. --load
docker save webdev/web:latest webdev/api:latest webdev/canbus:latest | gzip > webdev-arm64.tar.gz
scp webdev-arm64.tar.gz pi@<ip-del-raspberry>:~/WebDev/docker/
```

Sul Raspberry:

```bash
gunzip -c webdev-arm64.tar.gz | docker load
docker compose up -d --no-build
```

Su un Mac M3 le immagini native sono già `arm64`, quindi anche un semplice `docker compose build` seguito da `docker save` produce immagini adatte al Raspberry 64 bit.

## Note

- Le password stanno solo in `.env` (non va messo su Git). Senza `DB_PASSWORD` e `DB_ROOT_PASSWORD` il compose si rifiuta di partire.
- Lo schema in `../vue/db/init` viene applicato **solo** quando il volume del database è vuoto. Per ricrearlo da zero: `docker compose down -v` (cancella i dati).
- La porta 3306 è esposta solo su `127.0.0.1`; phpMyAdmin chiede utente e password (niente login automatico come root).
- Il profilo `legacy` serve la vecchia cartella `src`, che si collega come `root`/`root`: funziona solo se in `.env` metti `DB_ROOT_PASSWORD=root`. Da usare solo in locale durante la migrazione.
- I vecchi `Dockerfile`, `docker-compose.yaml` e `docker-compose_raspberry.yaml` nella radice non sono stati modificati.

## Problemi frequenti

### Download interrotto: `failed to copy: httpReadSeeker: failed open ... EOF`

Docker ha perso la connessione mentre scaricava un'immagine da Docker Hub (spesso `mariadb`). Non è un errore del compose. Prova in quest'ordine:

1. Scarica l'immagine da sola, poi rilancia lo stack:
   ```bash
   docker pull mariadb:11.4
   docker compose up -d --build
   ```
2. Chiudi l'eventuale VPN o proxy e riavvia Docker Desktop (icona della balena → Restart).
3. Se l'errore resta: Docker Desktop → Settings → General, disattiva **"Use containerd for pulling and storing images"**, premi *Apply & restart* e riprova.

### `Bind for 0.0.0.0:1883 failed: port is already allocated`

Sulla macchina c'è già un altro broker MQTT (di solito un Mosquitto installato sul Mac) sulla porta 1883. Puoi:

- scoprire chi la occupa: `lsof -nP -iTCP:1883 -sTCP:LISTEN`, e fermarlo se non serve (con Homebrew: `brew services stop mosquitto`);
- oppure lasciarlo e spostare la porta di debug dello stack: in `.env` metti `MQTT_PORT=1884`, poi `docker compose up -d`.

La porta interna resta 1883: interfaccia e gateway funzionano lo stesso. Cambia solo il comando di debug: `mosquitto_sub -h localhost -p 1884 -t '#' -v`.

### `429 Too Many Requests` scaricando un'immagine

Docker Hub limita i download anonimi. Accedi con `docker login` (account gratuito) oppure scarica l'immagine dal mirror di Google e rinominala:

```bash
docker pull mirror.gcr.io/library/eclipse-mosquitto:2
docker tag mirror.gcr.io/library/eclipse-mosquitto:2 eclipse-mosquitto:2
docker compose up -d
```

Lo stesso vale per le altre immagini ufficiali (`mariadb:11.4`, `nginx`, `node`, `php`): `mirror.gcr.io/library/<nome>:<tag>`.

### `container webdev-db-1 is unhealthy` e compaiono i container `mysql` / `php-apache`

Il vecchio stack nella radice (`docker-compose.yaml`) e le prime versioni di questo usavano lo stesso nome di progetto, `webdev`, e quindi lo stesso volume del database: MariaDB non riesce ad avviarsi sui file del vecchio MySQL. Ora questo stack si chiama `webdev-vue` e ha un volume suo. Per pulire una volta sola:

```bash
cd WebDev/docker
docker compose -p webdev down --remove-orphans   # rimuove i container del vecchio progetto (i volumi restano)
docker compose up -d --build
```
