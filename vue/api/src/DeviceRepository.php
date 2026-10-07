<?php
// Accesso alla tabella `dataset` (stesso schema della versione PHP originale):
// una riga per ogni punto della curva, il vettore è ripetuto in JSON su ogni riga.
final class DeviceRepository
{
    public function __construct(private PDO $pdo) {}

    public function list(): array
    {
        return $this->pdo->query(
            "SELECT identifier, COUNT(*) AS points, MAX(created_at) AS updated_at
             FROM dataset GROUP BY identifier ORDER BY identifier ASC"
        )->fetchAll();
    }

    public function exists(string $identifier): bool
    {
        $st = $this->pdo->prepare("SELECT 1 FROM dataset WHERE identifier = ? LIMIT 1");
        $st->execute([$identifier]);
        return (bool)$st->fetchColumn();
    }

    public function find(string $identifier): ?array
    {
        $st = $this->pdo->prepare(
            "SELECT row_index, col1, col2, vector_values FROM dataset
             WHERE identifier = ? ORDER BY row_index ASC"
        );
        $st->execute([$identifier]);
        $rows = $st->fetchAll();
        if (!$rows) return null;

        $matrix = [];
        foreach ($rows as $row) {
            // I dati salvati dalla vecchia interfaccia possono contenere righe vuote: le saltiamo.
            if (($row['col1'] ?? '') === '' && ($row['col2'] ?? '') === '') continue;
            $matrix[] = [self::num($row['col1']), self::num($row['col2'])];
        }
        $vector = json_decode($rows[0]['vector_values'] ?? '[]', true) ?: [];
        $vector = array_map([self::class, 'num'], $vector);

        return ['identifier' => $identifier, 'matrix' => $matrix, 'vector' => $vector];
    }

    /**
     * Salva il dispositivo sostituendo le righe esistenti (la versione originale
     * inseriva sempre, duplicando i dati ad ogni salvataggio).
     * $previousIdentifier permette di rinominare il dispositivo.
     */
    public function save(array $device, ?string $previousIdentifier = null): void
    {
        $renamed = $previousIdentifier !== null && $previousIdentifier !== $device['identifier'];
        if ($renamed) ChannelRepository::ensureSchema($this->pdo);
        $this->pdo->beginTransaction();
        try {
            $del = $this->pdo->prepare("DELETE FROM dataset WHERE identifier = ?");
            $del->execute([$device['identifier']]);
            if ($renamed) {
                $del->execute([$previousIdentifier]);
                // i canali che usavano il vecchio nome seguono il dispositivo
                $this->pdo->prepare("UPDATE channel_config SET device = ? WHERE device = ?")
                    ->execute([$device['identifier'], $previousIdentifier]);
            }
            $ins = $this->pdo->prepare(
                "INSERT INTO dataset (identifier, row_index, col1, col2, vector_values) VALUES (?, ?, ?, ?, ?)"
            );
            $vectorJson = json_encode($device['vector']);
            foreach ($device['matrix'] as $i => [$t, $c]) {
                $ins->execute([$device['identifier'], $i, (string)$t, (string)$c, $vectorJson]);
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function delete(string $identifier): int
    {
        ChannelRepository::ensureSchema($this->pdo);
        $this->pdo->beginTransaction();
        try {
            $st = $this->pdo->prepare("DELETE FROM dataset WHERE identifier = ?");
            $st->execute([$identifier]);
            // i canali che usavano il dispositivo restano senza dispositivo
            $this->pdo->prepare("UPDATE channel_config SET device = NULL WHERE device = ?")->execute([$identifier]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
        return $st->rowCount();
    }

    /** @return string[] tutti gli identifier esistenti */
    public function identifiers(): array
    {
        return $this->pdo->query("SELECT DISTINCT identifier FROM dataset")->fetchAll(PDO::FETCH_COLUMN);
    }

    private static function num($v)
    {
        if ($v === null || $v === '') return null;
        return is_numeric($v) ? $v + 0 : $v;
    }
}
