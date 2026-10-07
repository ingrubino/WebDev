<?php
// Validazione lato server: stesse regole del frontend (config/device-rules.json).
// Il frontend valida per dare feedback immediato; l'API rivalida perché è l'unica garanzia.
final class DeviceValidator
{
    private array $rules;

    public function __construct(string $rulesFile)
    {
        $this->rules = json_decode(file_get_contents($rulesFile), true, 512, JSON_THROW_ON_ERROR);
    }

    public function rules(): array
    {
        return $this->rules;
    }

    /**
     * Normalizza e valida il payload {identifier, matrix, vector}.
     * Restituisce [datiPuliti, errori]; errori è una mappa campo => messaggio
     * con chiavi uguali al frontend: identifier, matrix, matrix.3.0, vector.2 ...
     */
    public function validate(array $in): array
    {
        $r = $this->rules;
        $errors = [];

        $identifier = trim((string)($in['identifier'] ?? ''));
        if ($identifier === '') {
            $errors['identifier'] = 'Required';
        } elseif (mb_strlen($identifier) > $r['identifier']['maxLength']) {
            $errors['identifier'] = "Max {$r['identifier']['maxLength']} characters";
        } elseif (!preg_match('/' . str_replace('/', '\/', $r['identifier']['pattern']) . '/u', $identifier)) {
            $errors['identifier'] = 'Allowed: ' . $r['identifier']['patternHint'];
        }

        $matrixIn = is_array($in['matrix'] ?? null) ? array_values($in['matrix']) : [];
        if (count($matrixIn) > $r['rows']) {
            $errors['matrix'] = "At most {$r['rows']} rows";
        }
        $matrix = [];
        $prevTime = null;
        foreach (array_slice($matrixIn, 0, $r['rows']) as $i => $row) {
            $t = self::blankToNull($row[0] ?? null);
            $c = self::blankToNull($row[1] ?? null);
            if ($t === null && $c === null) {
                continue; // riga vuota: ignorata
            }
            if ($t === null) { $errors["matrix.$i.0"] = 'Required when current is set'; continue; }
            if ($c === null) { $errors["matrix.$i.1"] = 'Required when time is set'; continue; }
            if ($e = self::numberError($t, $r['time'])) { $errors["matrix.$i.0"] = $e; }
            if ($e = self::numberError($c, $r['current'])) { $errors["matrix.$i.1"] = $e; }
            if (!isset($errors["matrix.$i.0"])) {
                if ($r['time']['strictlyIncreasing'] && $prevTime !== null && (float)$t <= $prevTime) {
                    $errors["matrix.$i.0"] = 'Must be greater than previous time';
                }
                $prevTime = (float)$t;
            }
            $matrix[] = [(float)$t, (float)$c];
        }
        if (!isset($errors['matrix']) && count($matrix) < $r['minPoints']) {
            $errors['matrix'] = "At least {$r['minPoints']} complete rows";
        }

        $vectorIn = is_array($in['vector'] ?? null) ? array_values($in['vector']) : [];
        if (count($vectorIn) > $r['vectorLength']) {
            $errors['vector'] = "At most {$r['vectorLength']} values";
        }
        $vector = [];
        for ($j = 0; $j < $r['vectorLength']; $j++) {
            $v = self::blankToNull($vectorIn[$j] ?? null);
            if ($v === null) {
                if ($r['vector']['required']) { $errors["vector.$j"] = 'Required'; }
                $vector[] = null;
                continue;
            }
            if ($e = self::numberError($v, $r['vector'])) { $errors["vector.$j"] = $e; }
            $vector[] = (float)$v;
        }

        return [['identifier' => $identifier, 'matrix' => $matrix, 'vector' => $vector], $errors];
    }

    private static function blankToNull($v)
    {
        if ($v === null) return null;
        // Virgola decimale accettata come nel frontend: "1,5" -> "1.5"
        $s = str_replace(',', '.', trim((string)$v));
        return $s === '' ? null : $s;
    }

    private static function numberError(string $v, array $rule): ?string
    {
        if (!is_numeric($v)) return 'Must be a number';
        $n = (float)$v;
        if (isset($rule['min']) && $rule['min'] !== null && $n < $rule['min']) return "Min {$rule['min']}";
        if (isset($rule['max']) && $rule['max'] !== null && $n > $rule['max']) return "Max {$rule['max']}";
        return null;
    }
}
