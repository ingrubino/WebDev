# WebDev
esercitazioni sviluppo web 

Perfetto 👍 ora è chiarissimo: se hai un progetto su GitHub con dentro un `docker-compose.yaml`, puoi ricostruire tutto da riga di comando in pochi passaggi.

---

## 🚀 Procedura

### 1. Clonare il repository

Sulla nuova macchina, apri il terminale e lancia:

```bash
git clone https://github.com/ingrubino/WebDev.git
```

Questo crea una cartella `WebDev/` con tutto il materiale.

---

### 2. Entrare nella cartella di lavoro

```bash
cd WebDev
```

Dentro dovresti trovare il file `docker-compose.yaml`.

---

### 3. Avviare i container

Con Docker installato, puoi lanciare:

```bash
docker compose up -d
```

oppure, se usi la versione “classica”:

```bash
docker-compose up -d
```

* L’opzione `-d` serve per avviare i servizi in **background**.
* Se ci sono immagini definite con `build: .`, Docker ricostruirà le immagini dai `Dockerfile`.
* Se invece ci sono solo `image: ...`, verranno scaricate dal registry (es. Docker Hub).

---

### 4. Verificare che i container girino

```bash
docker ps
```

Vedrai l’elenco dei container attivi, con porte esposte e stato.

---

### 5. (Opzionale) Fermare i servizi

Per spegnere lo stack:

```bash
docker compose down
```

---
### Configurazione MariaDB
Perfetto 👌. Ti faccio uno “step by step” per **creare una tabella MariaDB su Raspberry** da script, partendo dal tuo `docker-compose.yaml` con `mariadb`.

---

## 🔹 1. Accedere a MariaDB dentro Docker

Con i container attivi (`docker compose up -d`), puoi collegarti così:

```bash
docker exec -it mariadb mysql -u root -p
```

👉 ti chiederà la password (`root` nel tuo caso, perché hai `MARIADB_ROOT_PASSWORD=root`).

---

## 🔹 2. Creare un database (se non esiste già)

Una volta dentro la console MariaDB:

```sql
CREATE DATABASE IF NOT EXISTS testdb;
USE testdb;
```

---

## 🔹 3. Creare la tabella

Puoi lanciare il tuo script SQL direttamente nella console:

```sql
CREATE TABLE dataset (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(100) NOT NULL,
    row_index INT NOT NULL,
    col1 VARCHAR(100),
    col2 VARCHAR(100),
    vector_values JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

⚠️ Nota: MariaDB **supporta JSON solo dalla 10.2.7**, ma in realtà lo implementa come alias di `LONGTEXT` + un parser JSON.
Quindi funziona, ma non è potente come il JSON “vero” di MySQL 5.7+.
Se ti serve compatibilità totale, puoi comunque usare `JSON` in MariaDB ≥10.11 (quella che ti ho consigliato).

---

## 🔹 4. Eseguire lo script da file (alternativa più comoda)

1. Crea un file `init.sql` con il contenuto:

```sql
CREATE DATABASE IF NOT EXISTS testdb;
USE testdb;

CREATE TABLE dataset (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(100) NOT NULL,
    row_index INT NOT NULL,
    col1 VARCHAR(100),
    col2 VARCHAR(100),
    vector_values JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

2. Poi eseguilo con:

```bash
docker exec -i mariadb mysql -u root -proot < init.sql
```

---

## 🔹 5. Verificare la tabella

Dentro MariaDB:

```sql
USE testdb;
SHOW TABLES;
DESCRIBE dataset;
```

---

### Inizializzazione automatica della macchina
Perfetto 🚀. Con MariaDB puoi inizializzare database, tabelle e dati usando la cartella speciale **`/docker-entrypoint-initdb.d/`**: tutti gli `.sql` (o `.sh`) lì dentro vengono eseguiti **solo al primo avvio** del container (quando la cartella dei dati è ancora vuota).

Ecco una versione aggiornata del tuo `docker-compose.yaml` con init script automatico:

```yaml
version: "3.8"

services:
  web:
    build: .
    image: php:8.2-apache
    container_name: php-apache
    volumes:
      - ./src:/var/www/html
    ports:
      - "8080:80"
    depends_on:
      - db

  db:
    image: mariadb:10.11
    container_name: mariadb
    restart: always
    environment:
      MARIADB_ROOT_PASSWORD: root
      MARIADB_DATABASE: testdb
      MARIADB_USER: user
      MARIADB_PASSWORD: password
    ports:
      - "3306:3306"
    volumes:
      - db_data:/var/lib/mysql
      - ./initdb:/docker-entrypoint-initdb.d  # <-- cartella init script

  phpmyadmin:
    image: phpmyadmin/phpmyadmin
    container_name: phpmyadmin
    environment:
      PMA_HOST: db
      PMA_USER: root
      PMA_PASSWORD: root
    ports:
      - "8081:80"
    depends_on:
      - db

volumes:
  db_data:
```

---

## 📂 Struttura delle cartelle

Organizza così il progetto:

```
WebDev/
├─ docker-compose.yaml
├─ src/
│   └─ index.php
└─ initdb/
   └─ init.sql
```

---

## 📜 Esempio di `init.sql`

Metti il tuo script dentro `initdb/init.sql`:

```sql
CREATE DATABASE IF NOT EXISTS testdb;
USE testdb;

CREATE TABLE dataset (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(100) NOT NULL,
    row_index INT NOT NULL,
    col1 VARCHAR(100),
    col2 VARCHAR(100),
    vector_values JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## 🚀 Primo avvio

Poi fai:

```bash
docker compose up -d --build
```

Al primo avvio il container **mariadb** eseguirà automaticamente lo script e la tabella sarà già pronta.


---

# Test
per il test: http://localhost:8080/index2.php
oppure: http://localhost:8080

per PhPmyAdmin:
http://localhost:8081

http://localhost:8080/list_identifiers2.php?identifier=luigi

# Formato tabella mySQL
```SQL
CREATE TABLE dataset ( id INT AUTO_INCREMENT PRIMARY KEY, 
identifier VARCHAR(100) NOT NULL, 
row_index INT NOT NULL, 
col1 VARCHAR(100), 
col2 VARCHAR(100), 
vector_values JSON, -- MySQL 5.7+ supporta JSON created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP 
);
```
---

