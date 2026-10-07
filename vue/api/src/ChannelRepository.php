<?php
// Accesso alla tabella `channel_config` (una riga per canale, letta dal programma di backend).
final class ChannelRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * Crea la tabella se manca (installazioni create prima della migrazione 002).
     * Va chiamata fuori dalle transazioni: in MariaDB un CREATE TABLE chiude la transazione aperta.
     */
    public static function ensureSchema(PDO $pdo): void
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS channel_config (
                channel TINYINT UNSIGNED NOT NULL PRIMARY KEY,
                device VARCHAR(100) NULL,
                mode VARCHAR(10) NOT NULL DEFAULT 'AC',
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_device (device)
            )"
        );
    }

    /** Tutti i canali 1..$count; quelli mai salvati tornano senza dispositivo e con $defaultMode. */
    public function all(int $count, string $defaultMode): array
    {
        $saved = [];
        foreach ($this->pdo->query("SELECT channel, device, mode, updated_at FROM channel_config") as $row) {
            $saved[(int)$row['channel']] = $row;
        }
        $out = [];
        for ($c = 1; $c <= $count; $c++) {
            $row = $saved[$c] ?? null;
            $out[] = [
                'channel' => $c,
                'device' => $row['device'] ?? null,
                'mode' => $row['mode'] ?? $defaultMode,
                'updated_at' => $row['updated_at'] ?? null,
            ];
        }
        return $out;
    }

    /** Sostituisce l'intera configurazione in un'unica transazione. */
    public function replaceAll(array $rows): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec("DELETE FROM channel_config");
            $ins = $this->pdo->prepare("INSERT INTO channel_config (channel, device, mode) VALUES (?, ?, ?)");
            foreach ($rows as $r) {
                $ins->execute([$r['channel'], $r['device'], $r['mode']]);
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
