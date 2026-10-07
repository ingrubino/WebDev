# Docker: Mac M3 e Raspberry Pi

Questa cartella contiene tutto il necessario per eseguire l'interfaccia Vue (`../vue`) in container, identica su Mac Apple Silicon, Raspberry Pi e PC.

| Servizio | Immagine | Cosa fa | Porta |
|---|---|---|---|
| `web` | `webdev/web` (da `frontend.Dockerfile`) | build Vue + nginx; inoltra `/api/` al backend | `8080` |
| `api` | `webdev/api` (da `../vue/api/Dockerfile`) | API PHP JSON | interna |
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
├─ nginx/default.conf          SPA + proxy /api (produzione)
├─ nginx/dev.conf              proxy verso Vite + /api (sviluppo)
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

## Architetture supportate

| Piattaforma | Esempio | Supporto |
|---|---|---|
| `linux/arm64` | Mac M1/M2/M3, Raspberry Pi 3/4/5 con OS **64 bit** | completo (consigliato) |
| `linux/amd64` | PC Intel/AMD | completo |
| `linux/arm/v7` | Raspberry Pi con OS 32 bit | solo `web`, `api`, `phpmyadmin`: **l'immagine ufficiale MariaDB non esiste per armv7** |

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
docker save webdev/web:latest webdev/api:latest | gzip > webdev-arm64.tar.gz
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
