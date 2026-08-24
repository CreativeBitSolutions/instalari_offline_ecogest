<?php
// api_vanzari_magazin_import_offline.php
// Endpoint JSON pentru import automat din aplicatia offline.

function sync_api_json($code, array $data)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function sync_api_header($name)
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    foreach ($headers as $key => $value) {
        if (strtolower($key) === strtolower($name)) {
            return (string)$value;
        }
    }

    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return isset($_SERVER[$serverKey]) ? (string)$_SERVER[$serverKey] : '';
}

function sync_api_extract_api_key(array $payload)
{
    $headerKey = sync_api_header('X-Sync-Api-Key');
    if ($headerKey !== '') {
        return trim($headerKey);
    }

    $headerKey = sync_api_header('X-Api-Key');
    if ($headerKey !== '') {
        return trim($headerKey);
    }

    $auth = sync_api_header('Authorization');
    if ($auth !== '' && preg_match('/^Bearer\s+(.+)$/i', $auth, $matches)) {
        return trim((string)$matches[1]);
    }

    if (isset($payload['api_key']) && trim((string)$payload['api_key']) !== '') {
        return trim((string)$payload['api_key']);
    }

    if (isset($_GET['api_key']) && trim((string)$_GET['api_key']) !== '') {
        return trim((string)$_GET['api_key']);
    }

    return '';
}

function sync_api_pdo($dsn, $user, $password)
{
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('SET NAMES utf8mb4');

    return $pdo;
}

function sync_api_connect_central()
{
    $host = 'localhost';
    $db = 'u681731335_clientiagecs';
    $user = 'u681731335_clientiagecs';
    $pass = 'w8>DS]pPeS8m';

    return sync_api_pdo("mysql:host={$host};dbname={$db};charset=utf8mb4", $user, $pass);
}

function sync_api_auth_client(array $payload)
{
    $apiKey = sync_api_extract_api_key($payload);
    if ($apiKey === '') {
        sync_api_json(401, [
            'status' => 'error',
            'message' => 'API key lipseste.',
        ]);
    }

    try {
        $central = sync_api_connect_central();
        $stmt = $central->prepare("
            SELECT id_client, tip_client, b_d, u_bd, p_bd
            FROM clienti
            WHERE api_key = :api_key
              AND api_key != ''
            LIMIT 1
        ");
        $stmt->execute([':api_key' => $apiKey]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        sync_api_json(500, [
            'status' => 'error',
            'message' => 'Conexiunea la baza centrala clientiagecs a esuat.',
            'error' => $e->getMessage(),
        ]);
    }

    if (!$client) {
        sync_api_json(401, [
            'status' => 'error',
            'message' => 'API key invalid.',
        ]);
    }

    $clientId = (int)$client['id_client'];
    $requestedClientId = 0;
    if (isset($payload['client_id'])) {
        $requestedClientId = (int)$payload['client_id'];
    } elseif (isset($payload['cod_client'])) {
        $requestedClientId = (int)$payload['cod_client'];
    }

    if ($requestedClientId > 0 && $requestedClientId !== $clientId) {
        sync_api_json(403, [
            'status' => 'error',
            'message' => 'client_id nu corespunde cu API key.',
        ]);
    }

    return $client;
}

function sync_api_find_import_script()
{
    $candidates = [
        __DIR__ . '/import_operatiuni_offline.php',
        __DIR__ . '/../import_operatiuni_offline.php',
        __DIR__ . '/../sincronizare_online_app_vanzare/import_operatiuni_offline.php',
        dirname(__DIR__) . '/Inspiratie/import_operatiuni_offline.php',
    ];

    foreach ($candidates as $file) {
        if (is_file($file)) {
            return $file;
        }
    }

    return '';
}

function sync_api_load_import_runtime()
{
    global $pdo, $tabel_final_admins, $tabel_final_nir, $tabel_final_achizitii;

    if (function_exists('sync_profiles') && function_exists('sync_import')) {
        return '';
    }

    $importScript = sync_api_find_import_script();
    if ($importScript === '') {
        sync_api_json(500, [
            'status' => 'error',
            'message' => 'Nu gasesc import_operatiuni_offline.php. Pune API-ul in acelasi folder cu importul online.',
        ]);
    }

    $oldMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $oldPost = $_POST;
    $oldFiles = $_FILES;

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION['admin_id'] = $_SESSION['admin_id'] ?? 1;
    $_SESSION['rang'] = 'admin';
    if (empty($_SESSION['client_id'])) {
        sync_api_json(500, [
            'status' => 'error',
            'message' => 'Clientul nu a fost stabilit din API key.',
        ]);
    }

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_POST = [];
    $_FILES = [];

    ob_start();
    require_once $importScript;
    ob_end_clean();

    $_SERVER['REQUEST_METHOD'] = $oldMethod;
    $_POST = $oldPost;
    $_FILES = $oldFiles;

    if (!function_exists('sync_profiles') || !function_exists('sync_import')) {
        sync_api_json(500, [
            'status' => 'error',
            'message' => 'Functiile de import nu au fost incarcate.',
        ]);
    }

    return $importScript;
}

function sync_api_request_payload()
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode((string)$raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    return $_POST;
}

function sync_api_temp_file(array $payload)
{
    if (!empty($_FILES['fisier']['tmp_name']) && is_uploaded_file($_FILES['fisier']['tmp_name'])) {
        $name = (string)($_FILES['fisier']['name'] ?? 'export.xml');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['xml', 'sql'], true)) {
            sync_api_json(400, ['status' => 'error', 'message' => 'Se accepta doar XML sau SQL.']);
        }

        $tmp = sys_get_temp_dir() . '/sync_api_' . bin2hex(random_bytes(8)) . '.' . $ext;
        if (!move_uploaded_file($_FILES['fisier']['tmp_name'], $tmp)) {
            sync_api_json(500, ['status' => 'error', 'message' => 'Fisierul uploadat nu a putut fi salvat temporar.']);
        }

        return [$tmp, $ext, $name];
    }

    $content = isset($payload['content']) ? (string)$payload['content'] : '';
    if ($content === '') {
        sync_api_json(400, ['status' => 'error', 'message' => 'Payload-ul nu contine fisier sau content.']);
    }

    $filename = (string)($payload['filename'] ?? 'export.xml');
    $ext = strtolower((string)($payload['format'] ?? pathinfo($filename, PATHINFO_EXTENSION)));
    if (!in_array($ext, ['xml', 'sql'], true)) {
        $ext = 'xml';
    }

    $tmp = sys_get_temp_dir() . '/sync_api_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (file_put_contents($tmp, $content, LOCK_EX) === false) {
        sync_api_json(500, ['status' => 'error', 'message' => 'Payload-ul nu a putut fi salvat temporar.']);
    }

    return [$tmp, $ext, $filename];
}

function sync_api_xml_meta($path, $ext)
{
    if ($ext !== 'xml') {
        return [];
    }

    $xml = @simplexml_load_file($path);
    if (!$xml) {
        return [];
    }

    return [
        'client_id' => (string)$xml['client_id'],
        'cod_locatie' => (string)$xml['cod_locatie'],
        'perioada_start' => (string)$xml['perioada_start'],
        'perioada_end' => (string)$xml['perioada_end'],
    ];
}

function sync_api_table_counts(array $tables)
{
    $counts = [];
    foreach ($tables as $table => $rows) {
        $counts[$table] = is_array($rows) ? count($rows) : 0;
    }

    return $counts;
}

function sync_api_current_database(PDO $pdo)
{
    try {
        return (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    } catch (Throwable $e) {
        return '';
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sync_api_json(405, ['status' => 'error', 'message' => 'Metoda HTTP nu este permisa.']);
}

$payload = sync_api_request_payload();

$client = sync_api_auth_client($payload);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION['client_id'] = (int)$client['id_client'];

sync_api_load_import_runtime();

list($tmp, $ext, $filename) = sync_api_temp_file($payload);
$profiles = sync_profiles();
$profile = (string)($payload['profile'] ?? 'auto');

try {
    $parsed = sync_parse_file($tmp, $ext);
    $detectedProfile = sync_detect_profile($parsed, $profile);
    $filtered = sync_filter_tables($parsed, $detectedProfile);
    $tableCounts = sync_api_table_counts($filtered);
    $meta = sync_api_xml_meta($tmp, $ext);

    $clientId = (int)$client['id_client'];
    $locationInfo = sync_detect_location_info($filtered);
    $codLocatie = (int)$locationInfo['cod_locatie'];
    if ($codLocatie <= 0) {
        $codLocatie = (int)($payload['cod_locatie'] ?? $meta['cod_locatie'] ?? 0);
    }

    $schemaMessages = sync_ensure_location_columns($pdo, $profiles[$detectedProfile]['tables']);
    $schema = sync_schema_status($pdo, $profiles[$detectedProfile]['tables']);
    $schemaErrors = sync_schema_errors($schema);

    if ($schemaErrors) {
        sync_api_json(422, [
            'status' => 'error',
            'message' => 'Import blocat din cauza schemei online.',
            'errors' => $schemaErrors,
            'schema_messages' => $schemaMessages,
        ]);
    }

    if ($clientId <= 0 || $codLocatie <= 0) {
        sync_api_json(422, [
            'status' => 'error',
            'message' => 'client_id si cod_locatie trebuie sa fie pozitive.',
            'client_id' => $clientId,
            'cod_locatie' => $codLocatie,
        ]);
    }

    $pdo->beginTransaction();
    $results = sync_import($pdo, $filtered, $detectedProfile, $clientId, $codLocatie);
    $pdo->commit();

    @unlink($tmp);

    sync_api_json(200, [
        'status' => 'ok',
        'message' => 'Importul automat a fost finalizat.',
        'file' => $filename,
        'profile' => $detectedProfile,
        'client_id' => $clientId,
        'cod_locatie' => $codLocatie,
        'schema_messages' => $schemaMessages,
        'results' => $results,
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    @unlink($tmp);

    sync_api_json(500, [
        'status' => 'error',
        'message' => 'Importul automat a fost oprit.',
        'error' => $e->getMessage(),
    ]);
}
