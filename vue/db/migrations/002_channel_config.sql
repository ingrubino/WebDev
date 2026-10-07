-- Migrazione 002: tabella channel_config (pagina "Configure channels").
-- L'API la crea comunque da sola se manca; questo file serve per applicarla a mano o da script.
CREATE TABLE IF NOT EXISTS channel_config (
    channel TINYINT UNSIGNED NOT NULL PRIMARY KEY,   -- 1..N (N = channels in config/channel-rules.json)
    device VARCHAR(100) NULL,                        -- identifier in `dataset`, NULL = canale non usato
    mode VARCHAR(10) NOT NULL DEFAULT 'AC',          -- 'AC' oppure 'DC'
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_device (device)
);
