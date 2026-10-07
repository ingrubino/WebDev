<?php
// API REST JSON. Tutte le richieste /api/... arrivano qui (vedi .htaccess).
//
//   GET    /api/health              stato API + database
//   GET    /api/rules               regole di validazione (config/device-rules.json)
//   GET    /api/devices             elenco dispositivi
//   GET    /api/devices/{id}        dettaglio dispositivo
//   POST   /api/devices             crea dispositivo          (409 se esiste già)
//   PUT    /api/devices/{id}        aggiorna / rinomina       (404 se non esiste)
//   DELETE /api/devices/{id}        elimina dispositivo
//   GET    /api/channels            configurazione dei canali (sempre N righe, vedi config/channel-rules.json)
//   PUT    /api/channels            sostituisce l'intera configurazione {channels:[{device, mode}, ...]}
//   ("Set devices" e la pagina SCADA non passano dall'API: usano MQTT, vedi vue/canbus/TOPICS.md)
declare(strict_types=1);

require __DIR__ . '/../src/Db.php';
require __DIR__ . '/../src/DeviceValidator.php';
require __DIR__ . '/../src/DeviceRepository.php';
require __DIR__ . '/../src/ChannelValidator.php';
require __DIR__ . '/../src/ChannelRepository.php';

header('Content-Type: application/json; charset=utf-8');

function respond(int $status, $body = null): void
{
    http_response_code($status);
    if ($body !== null) {
        echo json_encode($body, JSON_UNESCAPED_UNICODE);
    }
    exit;
}

function jsonBody(): array
{
    $data = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($data)) respond(400, ['error' => 'Invalid JSON body']);
    return $data;
}

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$path = preg_replace('#^/api#', '', rtrim($path, '/')) ?: '/';
$segments = array_map('rawurldecode', array_values(array_filter(explode('/', $path), 'strlen')));

$rulesFile = getenv('RULES_FILE') ?: __DIR__ . '/../config/device-rules.json';
$validator = new DeviceValidator($rulesFile);
$channelRulesFile = getenv('CHANNEL_RULES_FILE') ?: __DIR__ . '/../config/channel-rules.json';

try {
    if ($segments === ['health'] && $method === 'GET') {
        Db::pdo()->query('SELECT 1');
        respond(200, ['status' => 'ok']);
    }

    if ($segments === ['rules'] && $method === 'GET') {
        respond(200, $validator->rules());
    }

    if ($segments === ['channels'] && in_array($method, ['GET', 'PUT'], true)) {
        $channelValidator = new ChannelValidator($channelRulesFile);
        $cr = $channelValidator->rules();
        $pdo = Db::pdo();
        ChannelRepository::ensureSchema($pdo);
        $channels = new ChannelRepository($pdo);
        if ($method === 'PUT') {
            [$rows, $errors] = $channelValidator->validate(jsonBody(), (new DeviceRepository($pdo))->identifiers());
            if ($errors) respond(422, ['error' => 'Validation failed', 'fields' => $errors]);
            $channels->replaceAll($rows);
        }
        respond(200, ['channels' => $channels->all($cr['channels'], $cr['defaultMode'])]);
    }

    if (($segments[0] ?? null) === 'devices') {
        $repo = new DeviceRepository(Db::pdo());
        $id = $segments[1] ?? null;

        if ($id === null && $method === 'GET') {
            respond(200, $repo->list());
        }

        if ($id === null && $method === 'POST') {
            [$device, $errors] = $validator->validate(jsonBody());
            if ($errors) respond(422, ['error' => 'Validation failed', 'fields' => $errors]);
            if ($repo->exists($device['identifier'])) {
                respond(409, ['error' => 'Device already exists', 'fields' => ['identifier' => 'Already exists']]);
            }
            $repo->save($device);
            respond(201, $repo->find($device['identifier']));
        }

        if ($id !== null && count($segments) === 2) {
            if ($method === 'GET') {
                $device = $repo->find($id);
                $device ? respond(200, $device) : respond(404, ['error' => 'Device not found']);
            }
            if ($method === 'PUT') {
                if (!$repo->exists($id)) respond(404, ['error' => 'Device not found']);
                [$device, $errors] = $validator->validate(jsonBody());
                if ($errors) respond(422, ['error' => 'Validation failed', 'fields' => $errors]);
                if ($device['identifier'] !== $id && $repo->exists($device['identifier'])) {
                    respond(409, ['error' => 'Device already exists', 'fields' => ['identifier' => 'Already exists']]);
                }
                $repo->save($device, $id);
                respond(200, $repo->find($device['identifier']));
            }
            if ($method === 'DELETE') {
                $repo->delete($id) > 0 ? respond(204) : respond(404, ['error' => 'Device not found']);
            }
        }
    }

    respond(404, ['error' => "No route for $method $path"]);
} catch (PDOException $e) {
    error_log((string)$e);
    respond(503, ['error' => 'Database error']);
} catch (Throwable $e) {
    error_log((string)$e);
    respond(500, ['error' => 'Internal error']);
}
