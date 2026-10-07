-- Eseguito automaticamente da MariaDB solo al primo avvio (volume dati vuoto).
-- Lo schema è identico a quello della versione PHP originale, per poter riusare i dati esistenti.
-- Il database (DB_NAME) viene creato dall'immagine MariaDB; lo script gira già al suo interno.

CREATE TABLE IF NOT EXISTS dataset (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(100) NOT NULL,
    row_index INT NOT NULL,
    col1 VARCHAR(100),
    col2 VARCHAR(100),
    vector_values JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_identifier (identifier)
);
