<?php
// Validazione della configurazione canali: stesse regole del frontend
// (frontend/src/validation/channels.js), soglie in config/channel-rules.json.
final class ChannelValidator
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
     * $in = {channels: [{device, mode}, ...]} con esattamente N righe (canale = posizione + 1).
     * $knownDevices = elenco degli identifier esistenti.
     * Restituisce [righePulite, errori]; chiavi d'errore: channels, channels.3.device, channels.3.mode.
     */
    public function validate(array $in, array $knownDevices): array
    {
        $n = $this->rules['channels'];
        $modes = $this->rules['modes'];
        $errors = [];
        $rows = is_array($in['channels'] ?? null) ? array_values($in['channels']) : null;
        if ($rows === null || count($rows) !== $n) {
            return [[], ['channels' => "Exactly $n channels expected"]];
        }
        $known = array_flip($knownDevices);
        $clean = [];
        foreach ($rows as $i => $row) {
            $device = trim((string)($row['device'] ?? ''));
            $device = $device === '' ? null : $device;
            $mode = (string)($row['mode'] ?? '');
            if ($device !== null && !isset($known[$device])) {
                $errors["channels.$i.device"] = 'Unknown device';
            }
            if (!in_array($mode, $modes, true)) {
                $errors["channels.$i.mode"] = 'Choose ' . implode(' or ', $modes);
            }
            $clean[] = ['channel' => $i + 1, 'device' => $device, 'mode' => $mode];
        }
        return [$clean, $errors];
    }
}
