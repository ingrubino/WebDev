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

# Test
per il test: http://localhost:8080/index2.php
oppure: http://localhost:8080

per PhPmyAdmin:
http://localhost:8081

http://localhost:8080/list_identifiers2.php?identifier=luigi

# Formato tabella mySQL
```SQL
CREATE TABLE dataset ( id INT AUTO_INCREMENT PRIMARY KEY, identifier VARCHAR(100) NOT NULL, row_index INT NOT NULL, col1 VARCHAR(100), col2 VARCHAR(100), vector_values JSON, -- MySQL 5.7+ supporta JSON created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP );
```
---

