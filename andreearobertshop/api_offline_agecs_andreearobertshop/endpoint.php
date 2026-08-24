<?php
// endpoint.php
// Endpoint pentru AGECS Scanner: primește JSON, validează secretul și salvează payload-ul într-un .json

// ------- Config -------
$SECRET   = 'AG138DS1H4RST3H5RS351VAS2G1BFGBF135'; // TODO: schimbă cu același secret ca în Hardcoded.SecretCode
$LOG_DIR  = __DIR__ . '/bonuri_procesate_fisco';    // baza pentru salvare (în loc de "logs")
$MAX_BODY = 5 * 1024 * 1024;                        // 5 MB limită de protecție

function send_json($code, $data) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}
function get_header_value($name) {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    foreach ($headers as $k => $v) if (strtolower($k) === strtolower($name)) return $v;
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return $_SERVER[$key] ?? null;
}
function sanitize_name($s) { return substr(preg_replace('/[^\w\-\.@\(\) ]+/u', '_', (string)$s), 0, 120); }
function detect_category($srcFolder) {
    $base = strtolower(trim(basename(str_replace('\\', '/', (string)$srcFolder))));
    if ($base === '') return 'Unknown';
    if (strpos($base, 'bonerr') !== false)    return 'BonERR';
    if (strpos($base, 'bonanswer') !== false) return 'BonANSWER';
    if (strpos($base, 'bonok') !== false)     return 'BonOK';
    return 'Unknown';
}
function ext_bucket($filename) {
    $ext = strtolower((string)pathinfo((string)$filename, PATHINFO_EXTENSION));
    if ($ext === '') return ['noext', ''];
    if (in_array($ext, ['inp','nrb','txt'], true)) return [$ext, $ext];
    return ['other', $ext];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') send_json(405, ['error' => 'Method Not Allowed']);
$hdrSecret = get_header_value('X-Secret-Code');
if (!$hdrSecret || !hash_equals($SECRET, $hdrSecret)) send_json(401, ['error' => 'Unauthorized']);

$raw = file_get_contents('php://input');
if ($raw === false) send_json(400, ['error' => 'Body read error']);
if (strlen($raw) > $MAX_BODY) send_json(413, ['error' => 'Payload too large']);

$payload = json_decode($raw, true);
if (!is_array($payload)) send_json(400, ['error' => 'Invalid JSON']);

$clientId   = trim((string)($payload['client_id']   ?? ''));
$locationId = trim((string)($payload['location_id'] ?? ''));
$appVersion = trim((string)($payload['app_version'] ?? ''));
if ($clientId === '' || $locationId === '') send_json(400, ['error' => 'client_id and location_id are required']);

$numeFisier = $payload['nume_fisier'] ?? 'necunoscut.txt';
$dataIso    = $payload['data'] ?? gmdate('c');
$srcFolder  = $payload['folderul_de_provenienta'] ?? '';
$continut   = $payload['continutul_fisierului'] ?? '';
$bodySecret = $payload['secretcode'] ?? '';

if ($bodySecret !== '' && !hash_equals($SECRET, $bodySecret)) send_json(401, ['error' => 'Unauthorized body secret']);

$category         = detect_category($srcFolder);
list($extBucket, $fileExt) = ext_bucket($numeFisier);

// Înregistrarea pentru scriere
$record = [
    'meta' => [
        'utc_time'         => gmdate('c'),
        'source_ip'        => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent'       => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'header_secret_ok' => true,
        'client_id'        => get_header_value('X-Client-Id') ?: null,
        'location_id'      => get_header_value('X-Location-Id') ?: null,
        'app_version'      => get_header_value('X-App-Version') ?: null,
        'category'         => $category,
        'file_ext'         => $fileExt,
    ],
    'payload' => [
        'nume_fisier'              => $numeFisier,
        'data'                     => $dataIso,
        'folderul_de_provenienta'  => $srcFolder,
        'continutul_fisierului'    => $continut,
    ],
];

// Asigură existența folderului de bază
if (!is_dir($LOG_DIR) && !mkdir($LOG_DIR, 0775, true) && !is_dir($LOG_DIR)) send_json(500, ['error' => 'Cannot create base directory']);

$sanClient = sanitize_name($clientId);
$sanLoc    = sanitize_name($locationId);

// Structură: bonuri_procesate_fisco/{client}/{loc}/{Categorie}/[ext] (subfolder ext doar pentru BonOK)
$TARGET_DIR = $LOG_DIR . '/' . $sanClient . '/' . $sanLoc . '/' . sanitize_name($category);
if ($category === 'BonOK') {
    $TARGET_DIR .= '/' . sanitize_name($extBucket);
}

if (!is_dir($TARGET_DIR) && !mkdir($TARGET_DIR, 0775, true) && !is_dir($TARGET_DIR)) {
    send_json(500, ['error' => 'Cannot create target directory']);
}

$ts     = gmdate('Ymd_His');
$safeFn = sanitize_name($numeFisier);
$file   = $TARGET_DIR . "/".$safeFn."_"."scan_{$ts}_" . uniqid('', true) . ".json";

$json = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
if (@file_put_contents($file, $json, LOCK_EX) === false) send_json(500, ['error' => 'Write failed']);

send_json(201, ['status' => 'ok', 'saved_to' => basename($file), 'bytes' => strlen($json)]);
