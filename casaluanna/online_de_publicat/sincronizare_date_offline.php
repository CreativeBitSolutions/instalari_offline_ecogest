<?php
date_default_timezone_set('Europe/Bucharest');
ini_set('display_errors', '0');
ini_set('log_errors', '1');

define('OFFLINE_SYNC_SCHEMA_VERSION', 'offline-sync-v1');
define('OFFLINE_SYNC_SCHEMA_VERSION_V2', 'offline-sync-v2');
define('OFFLINE_PRODUCTS_SCHEMA_VERSION', 'offline-products-v1');
define('OFFLINE_PRODUCTS_SCHEMA_VERSION_V2', 'offline-products-v2-observatii');
define('OFFLINE_SYNC_MAX_BODY', 50 * 1024 * 1024);
$GLOBALS['offline_sync_columns_cache'] = array();

class OfflineSyncHttpException extends Exception
{
    private $httpCode;
    private $errors;

    public function __construct($httpCode, $message, array $errors = array())
    {
        parent::__construct($message);
        $this->httpCode = (int)$httpCode;
        $this->errors = $errors;
    }

    public function getHttpCode()
    {
        return $this->httpCode;
    }

    public function getErrors()
    {
        return $this->errors;
    }
}

function offline_sync_json_flags()
{
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }

    return $flags;
}

function offline_sync_send_common_headers()
{
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS, GET');
    header('Access-Control-Allow-Headers: Content-Type, X-Api-Key, Authorization, X-Requested-With');
    header('X-Content-Type-Options: nosniff');
}

function offline_sync_send_json($httpCode, array $payload)
{
    http_response_code((int)$httpCode);
    offline_sync_send_common_headers();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, offline_sync_json_flags());
    exit;
}

function offline_sync_send_no_content($httpCode = 204)
{
    http_response_code((int)$httpCode);
    offline_sync_send_common_headers();
    exit;
}

function offline_sync_is_health_request()
{
    return isset($_GET['health']) || isset($_GET['status']);
}

function offline_sync_health_payload()
{
    return array(
        'status' => 'success',
        'service' => 'offline-sales-sync',
        'schema_version' => OFFLINE_SYNC_SCHEMA_VERSION,
        'build' => 'offline-sync-20260630-1702',
        'method' => 'POST',
        'content_type' => 'application/json; charset=utf-8',
        'auth' => array('api_key', 'X-Api-Key', 'Authorization: Bearer'),
        'endpoints' => array(
            '/sincronizare_online_app_restaurant/sincronizare_date_offline.php',
            '/api/offline-sync',
            '/api/offline-sync.php',
            '/api/offline-sync-import',
            '/api/sincronizare-date-offline',
            '/api/sincronizare_date_offline',
            '/api/sincronizare_date_offline.php',
        ),
        'server_time' => date('Y-m-d H:i:s'),
    );
}

function offline_sync_get_header($name)
{
    $headers = function_exists('getallheaders') ? getallheaders() : array();
    foreach ($headers as $key => $value) {
        if (strtolower($key) === strtolower($name)) {
            return $value;
        }
    }

    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return isset($_SERVER[$serverKey]) ? $_SERVER[$serverKey] : null;
}

function offline_sync_extract_api_key()
{
    if (isset($_GET['api_key']) && trim((string)$_GET['api_key']) !== '') {
        return trim((string)$_GET['api_key']);
    }

    $headerKey = offline_sync_get_header('X-Api-Key');
    if ($headerKey !== null && trim((string)$headerKey) !== '') {
        return trim((string)$headerKey);
    }

    $auth = offline_sync_get_header('Authorization');
    if ($auth !== null && preg_match('/^Bearer\s+(.+)$/i', (string)$auth, $matches)) {
        return trim($matches[1]);
    }

    return '';
}

function offline_sync_debug_db_enabled()
{
    if (isset($_GET['debug_db']) && (string)$_GET['debug_db'] === '1') {
        return true;
    }

    $headerValue = offline_sync_get_header('X-Debug-Db');
    return $headerValue !== null && in_array(strtolower(trim((string)$headerValue)), array('1', 'true', 'yes', 'da', 'on'), true);
}

function offline_sync_db_debug_payload(array $client, $pdo = null)
{
    if (!offline_sync_debug_db_enabled()) {
        return null;
    }

    $debug = array(
        'client_id' => isset($client['id_client']) ? (int)$client['id_client'] : 0,
        'tip_client' => isset($client['tip_client']) ? (string)$client['tip_client'] : '',
        'target_database' => isset($client['b_d']) ? (string)$client['b_d'] : '',
        'target_user' => isset($client['u_bd']) ? (string)$client['u_bd'] : '',
        'server_host' => isset($_SERVER['HTTP_HOST']) ? (string)$_SERVER['HTTP_HOST'] : '',
        'endpoint' => isset($_SERVER['SCRIPT_NAME']) ? (string)$_SERVER['SCRIPT_NAME'] : '',
    );

    if ($pdo instanceof PDO) {
        try {
            $debug['connected_database'] = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
        } catch (Throwable $e) {
            $debug['connected_database'] = '';
        }
    }

    return $debug;
}

function offline_sync_has_uploaded_sync_file()
{
    return isset($_FILES['sync_file']['tmp_name']) && (string)$_FILES['sync_file']['tmp_name'] !== '';
}

function offline_sync_validate_content_type()
{
    if (offline_sync_has_uploaded_sync_file()) {
        return;
    }

    $contentType = isset($_SERVER['CONTENT_TYPE']) ? (string)$_SERVER['CONTENT_TYPE'] : (string)offline_sync_get_header('Content-Type');
    if ($contentType === '' || stripos($contentType, 'application/json') !== 0) {
        throw new OfflineSyncHttpException(415, 'Content-Type trebuie să fie application/json; charset=utf-8.');
    }
}

function offline_sync_pdo($dsn, $user, $password)
{
    $pdo = new PDO($dsn, $user, $password, array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ));
    $pdo->exec("SET NAMES utf8mb4");
    // Toate valorile SQL generate pe server, inclusiv NOW(), folosesc ora României.
    // Datele notei și ale liniilor vândute rămân cele primite explicit din aplicația offline.
    $romaniaOffsetSeconds = (new DateTimeImmutable('now', new DateTimeZone('Europe/Bucharest')))->getOffset();
    $romaniaAbsoluteOffset = abs($romaniaOffsetSeconds);
    $romaniaOffset = sprintf(
        '%s%02d:%02d',
        $romaniaOffsetSeconds < 0 ? '-' : '+',
        intdiv($romaniaAbsoluteOffset, 3600),
        intdiv($romaniaAbsoluteOffset % 3600, 60)
    );
    $pdo->exec('SET time_zone = ' . $pdo->quote($romaniaOffset));

    return $pdo;
}

function offline_sync_connect_central()
{
    $host = 'localhost';
    $db = 'u681731335_clientiagecs';
    $user = 'u681731335_clientiagecs';
    $pass = 'w8>DS]pPeS8m';
    $dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";

    return offline_sync_pdo($dsn, $user, $pass);
}

function offline_sync_start_session_if_needed()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
}

function offline_sync_connect_client(PDO $centralPdo)
{
    $apiKey = offline_sync_extract_api_key();
    $client = false;

    if ($apiKey !== '') {
        $stmt = $centralPdo->prepare("
            SELECT id_client, tip_client, b_d, u_bd, p_bd
            FROM clienti
            WHERE api_key = :api_key
              AND api_key != ''
            LIMIT 1
        ");
        $stmt->execute(array(':api_key' => $apiKey));
        $client = $stmt->fetch();
    } else {
        offline_sync_start_session_if_needed();
        $clientId = isset($_SESSION['client_id']) ? (int)$_SESSION['client_id'] : 0;
        $adminId = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : 0;

        if ($clientId > 0 && $adminId > 0) {
            $stmt = $centralPdo->prepare("
                SELECT id_client, tip_client, b_d, u_bd, p_bd
                FROM clienti
                WHERE id_client = :client_id
                LIMIT 1
            ");
            $stmt->execute(array(':client_id' => $clientId));
            $client = $stmt->fetch();
        }
    }

    if (!$client) {
        throw new OfflineSyncHttpException(401, 'API key invalid sau sesiune lipsă.');
    }

    $db = trim((string)$client['b_d']);
    $user = trim((string)$client['u_bd']);
    $pass = trim((string)$client['p_bd']);

    if ($db === '' || $user === '') {
        throw new OfflineSyncHttpException(500, 'Datele de conectare ale clientului sunt incomplete.');
    }

    $pdo = offline_sync_pdo("mysql:host=localhost;dbname={$db};charset=utf8mb4", $user, $pass);

    return array($pdo, $client);
}

function offline_sync_read_body()
{
    if (offline_sync_has_uploaded_sync_file() && is_uploaded_file($_FILES['sync_file']['tmp_name'])) {
        $raw = file_get_contents($_FILES['sync_file']['tmp_name']);
        if ($raw === false) {
            throw new OfflineSyncHttpException(400, 'Fișierul încărcat nu a putut fi citit.');
        }

        return $raw;
    }

    $raw = file_get_contents('php://input');
    if ($raw === false) {
        throw new OfflineSyncHttpException(400, 'Corpul cererii nu a putut fi citit.');
    }

    if (strlen($raw) > OFFLINE_SYNC_MAX_BODY) {
        throw new OfflineSyncHttpException(413, 'Payload-ul este prea mare.');
    }

    return $raw;
}

function offline_sync_decode_payload($raw)
{
    if (trim((string)$raw) === '') {
        throw new OfflineSyncHttpException(400, 'Payload-ul JSON este gol.');
    }

    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        throw new OfflineSyncHttpException(400, 'JSON invalid.');
    }

    return $payload;
}

function offline_sync_normalize_received_payload(array $payload)
{
    if (array_key_exists('miscari', $payload)) {
        unset($payload['miscari']);
    }

    if (isset($payload['counts']) && is_array($payload['counts']) && array_key_exists('miscari', $payload['counts'])) {
        unset($payload['counts']['miscari']);
    }

    return $payload;
}

function offline_sync_tables()
{
    return array('note', 'det_note', 'inchideri_r_12', 'rapoarte_z', 'discounturi_acordate');
}

function offline_sync_identifier_tables()
{
    return array('note', 'det_note', 'inchideri_r_12', 'rapoarte_z', 'discounturi_acordate', 'miscari');
}

function offline_sync_client_id(array $client)
{
    return isset($client['id_client']) ? (int)$client['id_client'] : 0;
}

function offline_sync_identifier_part($value)
{
    $value = preg_replace('/[^A-Za-z0-9_.-]+/', '_', (string)$value);
    $value = trim((string)$value, '_');

    return $value === '' ? '0' : $value;
}

function offline_sync_identifier($clientId, $codLocatie, $table, $sourcePk, $installationUuid = '')
{
    $identifier = 'client'
        . (int)$clientId
        . '_loc'
        . (int)$codLocatie
        . ($installationUuid !== '' ? '_install_' . offline_sync_identifier_part($installationUuid) : '')
        . '_restaurant_'
        . offline_sync_identifier_part($table)
        . '_'
        . offline_sync_identifier_part($sourcePk);

    return substr($identifier, 0, 191);
}

function offline_sync_identifier_from_sync(array $sync, $clientId)
{
    return offline_sync_identifier(
        $clientId,
        isset($sync['cod_locatie']) ? (int)$sync['cod_locatie'] : 0,
        isset($sync['source_table']) ? (string)$sync['source_table'] : '',
        isset($sync['source_pk']) ? (string)$sync['source_pk'] : '',
        isset($sync['installation_uuid']) ? (string)$sync['installation_uuid'] : ''
    );
}

function offline_sync_source_pk_from_sync_id($syncId, $expectedTable = '')
{
    $parts = explode(':', (string)$syncId);
    if (count($parts) < 3) {
        return '';
    }

    if ($expectedTable !== '' && (string)$parts[0] !== (string)$expectedTable) {
        return '';
    }

    return (string)$parts[count($parts) - 1];
}

function offline_sync_received_counts(array $payload)
{
    $counts = array();
    foreach (offline_sync_tables() as $table) {
        $counts[$table] = isset($payload[$table]) && is_array($payload[$table]) ? count($payload[$table]) : 0;
    }

    return $counts;
}

function offline_sync_validate_payload(array $payload)
{
    $errors = array();
    $requiredScalars = array('schema_version', 'sync_export_id', 'cod_locatie', 'data_sync');

    foreach ($requiredScalars as $field) {
        if (!isset($payload[$field]) || trim((string)$payload[$field]) === '') {
            $errors[] = "Câmpul {$field} lipsește.";
        }
    }

    $schemaVersion = isset($payload['schema_version']) ? (string)$payload['schema_version'] : '';
    if ($schemaVersion !== '' && !in_array($schemaVersion, array(OFFLINE_SYNC_SCHEMA_VERSION, OFFLINE_SYNC_SCHEMA_VERSION_V2), true)) {
        $errors[] = 'schema_version nu este acceptată.';
    }

    if ($schemaVersion === OFFLINE_SYNC_SCHEMA_VERSION_V2) {
        foreach (array('event_uuid', 'event_type', 'aggregate_type', 'aggregate_id', 'installation_uuid') as $field) {
            if (!isset($payload[$field]) || trim((string)$payload[$field]) === '') {
                $errors[] = "Câmpul {$field} lipsește pentru schema v2.";
            }
        }
        if (isset($payload['event_type']) && !in_array((string)$payload['event_type'], array('sale_finalized', 'shift_closed', 'z_closed'), true)) {
            $errors[] = 'event_type nu este acceptat.';
        }
    }

    if (!isset($payload['utilizator_sync']) || !is_array($payload['utilizator_sync'])) {
        $errors[] = 'Câmpul utilizator_sync lipsește sau nu este obiect JSON.';
    }

    $codLocatie = isset($payload['cod_locatie']) ? (int)$payload['cod_locatie'] : 0;
    if ($codLocatie <= 0) {
        $errors[] = 'cod_locatie trebuie să fie un număr pozitiv.';
    }

    foreach (offline_sync_tables() as $table) {
        if (!isset($payload[$table]) || !is_array($payload[$table])) {
            $errors[] = "Câmpul {$table} lipsește sau nu este array.";
            continue;
        }

        foreach ($payload[$table] as $index => $row) {
            if (!is_array($row)) {
                $errors[] = "{$table}[{$index}] nu este obiect JSON.";
                continue;
            }

            if (!isset($row['_sync']) || !is_array($row['_sync'])) {
                $errors[] = "{$table}[{$index}] nu are blocul _sync.";
                continue;
            }

            $sync = $row['_sync'];
            foreach (array('source_table', 'source_pk', 'cod_locatie', 'sync_id') as $field) {
                if (!isset($sync[$field]) || trim((string)$sync[$field]) === '') {
                    $errors[] = "{$table}[{$index}]._sync.{$field} lipsește.";
                }
            }

            if ($schemaVersion === OFFLINE_SYNC_SCHEMA_VERSION_V2 && (!isset($sync['installation_uuid']) || (string)$sync['installation_uuid'] !== (string)$payload['installation_uuid'])) {
                $errors[] = "{$table}[{$index}]._sync.installation_uuid nu corespunde cu payloadul.";
            }

            if (isset($sync['source_table']) && (string)$sync['source_table'] !== $table) {
                $errors[] = "{$table}[{$index}]._sync.source_table nu corespunde cu tabela.";
            }

            if (isset($sync['cod_locatie']) && (int)$sync['cod_locatie'] !== $codLocatie) {
                $errors[] = "{$table}[{$index}]._sync.cod_locatie nu corespunde cu cod_locatie din payload.";
            }
        }
    }

    if (isset($payload['counts']) && is_array($payload['counts'])) {
        $actualCounts = offline_sync_received_counts($payload);
        foreach ($actualCounts as $table => $count) {
            if (isset($payload['counts'][$table]) && (int)$payload['counts'][$table] !== $count) {
                $errors[] = "counts.{$table} nu corespunde cu numărul de rânduri primit.";
            }
        }
    }

    if (!empty($errors)) {
        throw new OfflineSyncHttpException(422, 'Payload invalid.', $errors);
    }
}

function offline_sync_quote_identifier($identifier)
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', (string)$identifier)) {
        throw new RuntimeException('Identificator SQL invalid.');
    }

    return '`' . str_replace('`', '``', (string)$identifier) . '`';
}

function offline_sync_table_exists(PDO $pdo, $table)
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = :table_name
    ");
    $stmt->execute(array(':table_name' => (string)$table));

    return ((int)$stmt->fetchColumn()) > 0;
}

function offline_sync_get_columns(PDO $pdo, $table)
{
    if (!isset($GLOBALS['offline_sync_columns_cache']) || !is_array($GLOBALS['offline_sync_columns_cache'])) {
        $GLOBALS['offline_sync_columns_cache'] = array();
    }

    if (isset($GLOBALS['offline_sync_columns_cache'][$table])) {
        return $GLOBALS['offline_sync_columns_cache'][$table];
    }

    if (!offline_sync_table_exists($pdo, $table)) {
        $GLOBALS['offline_sync_columns_cache'][$table] = array();
        return $GLOBALS['offline_sync_columns_cache'][$table];
    }

    $stmt = $pdo->query('SHOW FULL COLUMNS FROM ' . offline_sync_quote_identifier($table));
    $columns = array();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $columns[$row['Field']] = $row;
    }

    $GLOBALS['offline_sync_columns_cache'][$table] = $columns;
    return $columns;
}

function offline_products_sync_table_columns($table)
{
    $columns = array(
        'produse_servicii' => array(
            'cod_produs',
            'cod_bare',
            'nume',
            'nume_site',
            'nume_en',
            'descriere',
            'descriere_site',
            'descriere_en',
            'um',
            'pret_cu_tva',
            'pret_achizitie',
            'pret_site',
            'cota_tva',
            'id_categorie',
            'id_gestiune',
            'activ',
            'produs_activ_site',
            'stoc_status_site',
            'woo_product_id',
            'se_vinde',
            'departament',
            'dep_casa_marcat',
            'tip',
            'fel_mancare',
            'ask_obs',
            'imagine',
            'imagine_site',
            'stoc_critic',
            'nc8',
            'infopret_kg',
            'consumabil_de_personal',
            'sgr',
            'sgr_pet',
            'sgr_alumin',
            'sgr_sticla',
        ),
        'categorii' => array(
            'id_categorie',
            'den_categ',
            'se_vinde',
        ),
        'categorii_locatii' => array(
            'id',
            'id_categorie',
            'cod_locatie',
        ),
        'gestiuni' => array(
            'id_gestiune',
            'denumire_gestiune',
        ),
        'cote_tva' => array(
            'cota',
            'dep_casa',
        ),
        'observatii_predefinite' => array(
            'id',
            'text_observatie',
            'ordine',
            'activ',
            'toate_produsele',
        ),
        'atribuiri_observatii_produse' => array(
            'id_observatie',
            'cod_produs',
        ),
    );

    return isset($columns[$table]) ? $columns[$table] : array();
}

function offline_products_hash_column_type($column)
{
    $integerColumns = array(
        'cod_produs',
        'id_categorie',
        'id_gestiune',
        'activ',
        'produs_activ_site',
        'woo_product_id',
        'se_vinde',
        'dep_casa_marcat',
        'fel_mancare',
        'ask_obs',
        'consumabil_de_personal',
        'sgr',
        'sgr_pet',
        'sgr_alumin',
        'sgr_sticla',
        'id',
        'cod_locatie',
        'dep_casa',
        'id_observatie',
        'ordine',
        'toate_produsele',
    );
    $numericColumns = array(
        'pret_cu_tva',
        'pret_achizitie',
        'pret_site',
        'cota_tva',
        'stoc_critic',
        'infopret_kg',
    );

    if (in_array($column, $integerColumns, true)) {
        return 'int';
    }
    if (in_array($column, $numericColumns, true)) {
        return 'float';
    }

    return 'text';
}

function offline_products_hash_value($value, $column)
{
    if ($value === null) {
        return null;
    }

    $type = offline_products_hash_column_type($column);
    if ($type === 'int') {
        return $value === '' ? 0 : (int)$value;
    }
    if ($type === 'float') {
        return $value === '' ? 0.0 : round((float)$value, 6);
    }

    return (string)$value;
}

function offline_products_normalize_for_hash(array $payload)
{
    foreach ($payload as $key => $rows) {
        if (!is_array($rows)) {
            continue;
        }

        $normalizedRows = array();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            foreach ($row as $column => $value) {
                $row[$column] = offline_products_hash_value($value, (string)$column);
            }
            ksort($row);
            $normalizedRows[] = $row;
        }
        usort($normalizedRows, function (array $left, array $right) {
            return strcmp(
                (string)json_encode($left, offline_sync_json_flags() & ~JSON_PRETTY_PRINT),
                (string)json_encode($right, offline_sync_json_flags() & ~JSON_PRETTY_PRINT)
            );
        });
        $payload[$key] = $normalizedRows;
    }
    ksort($payload);

    return $payload;
}

function offline_products_payload_hash(array $payload)
{
    $json = json_encode(offline_products_normalize_for_hash($payload), offline_sync_json_flags() & ~JSON_PRETTY_PRINT);
    if ($json === false) {
        throw new RuntimeException('Hash-ul nomenclatorului nu a putut fi generat.');
    }

    return hash('sha256', $json);
}

function offline_products_fetch_table(PDO $pdo, $table, array $orderCandidates)
{
    if (!offline_sync_table_exists($pdo, $table)) {
        return array(
            'columns' => array(),
            'data' => array(),
        );
    }

    $dbColumns = offline_sync_get_columns($pdo, $table);
    $syncColumns = offline_products_sync_table_columns($table);
    $columns = array();
    foreach ($syncColumns as $column) {
        if (isset($dbColumns[$column])) {
            $columns[] = $column;
        }
    }

    if (!$columns) {
        return array(
            'columns' => array(),
            'data' => array(),
        );
    }

    $orderColumn = '';
    foreach ($orderCandidates as $candidate) {
        if (in_array($candidate, $columns, true)) {
            $orderColumn = $candidate;
            break;
        }
    }

    $quotedColumns = array();
    foreach ($columns as $column) {
        $quotedColumns[] = offline_sync_quote_identifier($column);
    }

    $sql = 'SELECT ' . implode(', ', $quotedColumns) . ' FROM ' . offline_sync_quote_identifier($table);
    if ($orderColumn !== '') {
        $sql .= ' ORDER BY ' . offline_sync_quote_identifier($orderColumn);
    }

    return array(
        'columns' => $columns,
        'data' => $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC),
    );
}

function offline_products_validate_requested_client(array $client)
{
    $clientId = isset($client['id_client']) ? (int)$client['id_client'] : 0;
    $requestedClientId = isset($_GET['cod_client']) ? (int)$_GET['cod_client'] : 0;

    if ($requestedClientId > 0 && $requestedClientId !== $clientId) {
        throw new OfflineSyncHttpException(403, 'cod_client nu corespunde cu API key.');
    }
}

function offline_products_send_export(PDO $pdo, array $client)
{
    offline_products_validate_requested_client($client);
    $includeObservations = isset($_GET['include_observations'])
        && (string)$_GET['include_observations'] === '1';

    $products = offline_products_fetch_table($pdo, 'produse_servicii', array('cod_produs'));
    if (!in_array('cod_produs', $products['columns'], true)) {
        throw new OfflineSyncHttpException(500, 'Tabela produse_servicii nu conține cod_produs.');
    }

    $categorii = offline_products_fetch_table($pdo, 'categorii', array('id_categorie'));
    $categoriiLocatii = offline_products_fetch_table($pdo, 'categorii_locatii', array('id'));
    $gestiuni = offline_products_fetch_table($pdo, 'gestiuni', array('id_gestiune'));
    $coteTva = offline_products_fetch_table($pdo, 'cote_tva', array('cota'));
    $observatiiPredefinite = $includeObservations
        ? offline_products_fetch_table($pdo, 'observatii_predefinite', array('ordine', 'id'))
        : array('columns' => array(), 'data' => array());
    $atribuiriObservatii = $includeObservations
        ? offline_products_fetch_table($pdo, 'atribuiri_observatii_produse', array('id_observatie', 'cod_produs'))
        : array('columns' => array(), 'data' => array());
    if ($includeObservations && !in_array('toate_produsele', $observatiiPredefinite['columns'], true)) {
        $observatiiPredefinite['columns'][] = 'toate_produsele';
        foreach ($observatiiPredefinite['data'] as &$observatiePredefinita) {
            if (is_array($observatiePredefinita) && !array_key_exists('toate_produsele', $observatiePredefinita)) {
                $observatiePredefinita['toate_produsele'] = 0;
            }
        }
        unset($observatiePredefinita);
    }

    $hashPayload = array(
        'produse_servicii' => $products['data'],
        'categorii' => $categorii['data'],
        'categorii_locatii' => $categoriiLocatii['data'],
        'gestiuni' => $gestiuni['data'],
        'cote_tva' => $coteTva['data'],
    );
    if ($includeObservations) {
        $hashPayload['observatii_predefinite'] = $observatiiPredefinite['data'];
        $hashPayload['atribuiri_observatii_produse'] = $atribuiriObservatii['data'];
    }
    $productsHash = offline_products_payload_hash($hashPayload);

    $localHash = isset($_GET['local_hash']) ? trim((string)$_GET['local_hash']) : '';
    $changed = $localHash === '' ? null : !hash_equals($productsHash, $localHash);
    $hashOnly = (isset($_GET['hash_only']) && (string)$_GET['hash_only'] === '1')
        || (isset($_GET['mode']) && strtolower((string)$_GET['mode']) === 'hash');
    $includeData = !$hashOnly && $changed !== false;

    $response = array(
        'status' => 'success',
        'schema_version' => $includeObservations
            ? OFFLINE_PRODUCTS_SCHEMA_VERSION_V2
            : OFFLINE_PRODUCTS_SCHEMA_VERSION,
        'client_id' => isset($client['id_client']) ? (int)$client['id_client'] : 0,
        'cod_client' => isset($client['id_client']) ? (int)$client['id_client'] : 0,
        'tip_client' => isset($client['tip_client']) ? (string)$client['tip_client'] : '',
        'generated_at' => date('Y-m-d H:i:s'),
        'columns' => $products['columns'],
        'required_columns' => array(
            'cod_produs',
            'nume',
            'pret_cu_tva',
            'cota_tva',
            'id_categorie',
            'id_gestiune',
            'activ',
            'se_vinde',
        ),
        'products_count' => count($products['data']),
        'products_hash' => $productsHash,
        'changed' => $changed,
        'data' => $includeData ? $products['data'] : array(),
        'categorii' => $includeData ? $categorii['data'] : array(),
        'categorii_locatii' => $includeData ? $categoriiLocatii['data'] : array(),
        'gestiuni' => $includeData ? $gestiuni['data'] : array(),
        'cote_tva' => $includeData ? $coteTva['data'] : array(),
    );
    if ($includeObservations) {
        $response['observatii_predefinite'] = $includeData ? $observatiiPredefinite['data'] : array();
        $response['atribuiri_observatii_produse'] = $includeData ? $atribuiriObservatii['data'] : array();
        $response['observatii_predefinite_columns'] = $observatiiPredefinite['columns'];
        $response['atribuiri_observatii_produse_columns'] = $atribuiriObservatii['columns'];
        $response['observatii_predefinite_count'] = count($observatiiPredefinite['data']);
        $response['atribuiri_observatii_produse_count'] = count($atribuiriObservatii['data']);
    }

    offline_sync_send_json(200, $response);
}

function offline_sync_clear_column_cache()
{
    $GLOBALS['offline_sync_columns_cache'] = array();
}

function offline_sync_column_exists(PDO $pdo, $table, $column)
{
    $columns = offline_sync_get_columns($pdo, $table);
    return isset($columns[$column]);
}

function offline_sync_add_column_if_missing(PDO $pdo, $table, $column, $definition)
{
    if (!offline_sync_column_exists($pdo, $table, $column)) {
        $pdo->exec(
            'ALTER TABLE ' . offline_sync_quote_identifier($table) .
            ' ADD COLUMN ' . offline_sync_quote_identifier($column) . ' ' . $definition
        );
    }
}

function offline_sync_index_exists(PDO $pdo, $table, $index)
{
    $stmt = $pdo->query('SHOW INDEX FROM ' . offline_sync_quote_identifier($table));
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ((string)($row['Key_name'] ?? '') === (string)$index) {
            return true;
        }
    }
    return false;
}

function offline_sync_ensure_support_tables(PDO $pdo)
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS offline_sync_imported (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sync_export_id VARCHAR(100) NOT NULL,
            source_table VARCHAR(100) NOT NULL,
            source_pk VARCHAR(100) NOT NULL,
            cod_locatie INT NOT NULL,
            sync_id VARCHAR(180) NOT NULL,
            target_table VARCHAR(100) NOT NULL DEFAULT '',
            target_pk VARCHAR(100) NOT NULL DEFAULT '',
            target_ref TEXT NULL,
            payload_hash VARCHAR(64) DEFAULT '',
            imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_offline_sync_row (source_table, source_pk, cod_locatie),
            KEY idx_sync_export_id (sync_export_id),
            KEY idx_sync_id (sync_id),
            KEY idx_target_row (target_table, target_pk)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS offline_sync_import_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sync_export_id VARCHAR(100) DEFAULT '',
            client_id INT DEFAULT 0,
            data_primire DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            cod_locatie INT NOT NULL DEFAULT 0,
            utilizator_sync_id INT DEFAULT 0,
            utilizator_sync_nume VARCHAR(255) DEFAULT '',
            note_primite INT DEFAULT 0,
            det_note_primite INT DEFAULT 0,
            inchideri_primite INT DEFAULT 0,
            rapoarte_z_primite INT DEFAULT 0,
            miscari_primite INT DEFAULT 0,
            discounturi_primite INT DEFAULT 0,
            note_insert INT DEFAULT 0,
            det_note_insert INT DEFAULT 0,
            inchideri_insert INT DEFAULT 0,
            rapoarte_z_insert INT DEFAULT 0,
            miscari_insert INT DEFAULT 0,
            discounturi_insert INT DEFAULT 0,
            note_duplicate INT DEFAULT 0,
            det_note_duplicate INT DEFAULT 0,
            inchideri_duplicate INT DEFAULT 0,
            rapoarte_z_duplicate INT DEFAULT 0,
            miscari_duplicate INT DEFAULT 0,
            discounturi_duplicate INT DEFAULT 0,
            note_updated INT DEFAULT 0,
            det_note_updated INT DEFAULT 0,
            inchideri_updated INT DEFAULT 0,
            rapoarte_z_updated INT DEFAULT 0,
            miscari_updated INT DEFAULT 0,
            discounturi_updated INT DEFAULT 0,
            duplicate_total INT DEFAULT 0,
            status VARCHAR(30) DEFAULT '',
            payload_hash VARCHAR(64) DEFAULT '',
            erori TEXT DEFAULT NULL,
            request_ip VARCHAR(45) DEFAULT '',
            KEY idx_sync_export_id (sync_export_id),
            KEY idx_data_primire (data_primire)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS offline_sync_event_inbox (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_uuid VARCHAR(191) NOT NULL,
            installation_uuid VARCHAR(100) NOT NULL DEFAULT '',
            event_type VARCHAR(50) NOT NULL DEFAULT '',
            aggregate_type VARCHAR(50) NOT NULL DEFAULT '',
            aggregate_id VARCHAR(100) NOT NULL DEFAULT '',
            payload_hash VARCHAR(64) NOT NULL DEFAULT '',
            status VARCHAR(30) NOT NULL DEFAULT 'processing',
            response_json LONGTEXT NULL,
            received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            processed_at DATETIME NULL,
            UNIQUE KEY uniq_offline_event_uuid (event_uuid),
            KEY idx_offline_event_status (status, received_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    offline_sync_add_column_if_missing($pdo, 'offline_sync_imported', 'installation_uuid', "VARCHAR(100) NOT NULL DEFAULT ''");
    offline_sync_add_column_if_missing($pdo, 'offline_sync_imported', 'target_table', "VARCHAR(100) NOT NULL DEFAULT ''");
    offline_sync_add_column_if_missing($pdo, 'offline_sync_imported', 'target_pk', "VARCHAR(100) NOT NULL DEFAULT ''");
    offline_sync_add_column_if_missing($pdo, 'offline_sync_imported', 'target_ref', 'TEXT NULL');
    offline_sync_add_column_if_missing($pdo, 'offline_sync_import_logs', 'client_id', 'INT DEFAULT 0');
    offline_sync_add_column_if_missing($pdo, 'offline_sync_import_logs', 'note_duplicate', 'INT DEFAULT 0');
    offline_sync_add_column_if_missing($pdo, 'offline_sync_import_logs', 'det_note_duplicate', 'INT DEFAULT 0');
    offline_sync_add_column_if_missing($pdo, 'offline_sync_import_logs', 'inchideri_duplicate', 'INT DEFAULT 0');
    offline_sync_add_column_if_missing($pdo, 'offline_sync_import_logs', 'rapoarte_z_duplicate', 'INT DEFAULT 0');
    offline_sync_add_column_if_missing($pdo, 'offline_sync_import_logs', 'miscari_primite', 'INT DEFAULT 0');
    offline_sync_add_column_if_missing($pdo, 'offline_sync_import_logs', 'miscari_insert', 'INT DEFAULT 0');
    offline_sync_add_column_if_missing($pdo, 'offline_sync_import_logs', 'miscari_duplicate', 'INT DEFAULT 0');
    offline_sync_add_column_if_missing($pdo, 'offline_sync_import_logs', 'discounturi_duplicate', 'INT DEFAULT 0');
    offline_sync_add_column_if_missing($pdo, 'offline_sync_import_logs', 'note_updated', 'INT DEFAULT 0');
    offline_sync_add_column_if_missing($pdo, 'offline_sync_import_logs', 'det_note_updated', 'INT DEFAULT 0');
    offline_sync_add_column_if_missing($pdo, 'offline_sync_import_logs', 'inchideri_updated', 'INT DEFAULT 0');
    offline_sync_add_column_if_missing($pdo, 'offline_sync_import_logs', 'rapoarte_z_updated', 'INT DEFAULT 0');
    offline_sync_add_column_if_missing($pdo, 'offline_sync_import_logs', 'miscari_updated', 'INT DEFAULT 0');
    offline_sync_add_column_if_missing($pdo, 'offline_sync_import_logs', 'discounturi_updated', 'INT DEFAULT 0');
    offline_sync_add_column_if_missing($pdo, 'offline_sync_import_logs', 'request_ip', "VARCHAR(45) DEFAULT ''");
    offline_sync_add_column_if_missing($pdo, 'offline_sync_import_logs', 'event_uuid', "VARCHAR(191) DEFAULT ''");
    offline_sync_add_column_if_missing($pdo, 'offline_sync_import_logs', 'event_type', "VARCHAR(50) DEFAULT ''");
    offline_sync_add_column_if_missing($pdo, 'offline_sync_import_logs', 'installation_uuid', "VARCHAR(100) DEFAULT ''");

    $importedColumns = offline_sync_get_columns($pdo, 'offline_sync_imported');
    if (!isset($importedColumns['sync_export_id']) || stripos((string)$importedColumns['sync_export_id']['Type'], 'varchar(191)') === false) {
        $pdo->exec('ALTER TABLE offline_sync_imported MODIFY sync_export_id VARCHAR(191) NOT NULL');
        offline_sync_clear_column_cache();
    }
    $logColumns = offline_sync_get_columns($pdo, 'offline_sync_import_logs');
    if (!isset($logColumns['sync_export_id']) || stripos((string)$logColumns['sync_export_id']['Type'], 'varchar(191)') === false) {
        $pdo->exec("ALTER TABLE offline_sync_import_logs MODIFY sync_export_id VARCHAR(191) NOT NULL DEFAULT ''");
        offline_sync_clear_column_cache();
    }
    if (offline_sync_index_exists($pdo, 'offline_sync_imported', 'uniq_offline_sync_row')) {
        $pdo->exec('ALTER TABLE offline_sync_imported DROP INDEX uniq_offline_sync_row');
    }
    if (!offline_sync_index_exists($pdo, 'offline_sync_imported', 'uniq_offline_sync_row_v2')) {
        $pdo->exec('ALTER TABLE offline_sync_imported ADD UNIQUE KEY uniq_offline_sync_row_v2 (installation_uuid, source_table, source_pk, cod_locatie)');
    }
}

function offline_sync_ensure_target_schema(PDO $pdo)
{
    foreach (offline_sync_identifier_tables() as $table) {
        if (!offline_sync_table_exists($pdo, $table)) {
            continue;
        }
        offline_sync_add_column_if_missing($pdo, $table, 'identificator_offline', 'VARCHAR(191) NULL DEFAULT NULL');
        offline_sync_clear_column_cache();
        $columns = offline_sync_get_columns($pdo, $table);
        $column = isset($columns['identificator_offline']) ? $columns['identificator_offline'] : array();
        if (stripos((string)($column['Type'] ?? ''), 'varchar(191)') === false || (string)($column['Null'] ?? '') !== 'YES') {
            $pdo->exec('UPDATE ' . offline_sync_quote_identifier($table) . " SET identificator_offline = NULL WHERE identificator_offline = ''");
            $pdo->exec('ALTER TABLE ' . offline_sync_quote_identifier($table) . ' MODIFY identificator_offline VARCHAR(191) NULL DEFAULT NULL');
            offline_sync_clear_column_cache();
        }

        $duplicateStmt = $pdo->query('SELECT identificator_offline FROM ' . offline_sync_quote_identifier($table)
            . " WHERE identificator_offline IS NOT NULL AND identificator_offline <> ''"
            . ' GROUP BY identificator_offline HAVING COUNT(*) > 1 LIMIT 1');
        if ($duplicateStmt->fetchColumn()) {
            throw new OfflineSyncHttpException(409, "Tabela {$table} conține identificatori offline dublați.");
        }

        $indexName = 'uniq_' . $table . '_offline_identifier';
        if (!offline_sync_index_exists($pdo, $table, $indexName)) {
            $pdo->exec('ALTER TABLE ' . offline_sync_quote_identifier($table)
                . ' ADD UNIQUE KEY ' . offline_sync_quote_identifier($indexName) . ' (identificator_offline)');
        }
    }

    if (offline_sync_table_exists($pdo, 'note')) {
        offline_sync_add_column_if_missing($pdo, 'note', 'nrbon_offline', 'BIGINT UNSIGNED NULL DEFAULT NULL');
        offline_sync_clear_column_cache();
    }
}

function offline_sync_preflight_schema(PDO $pdo, array $payload)
{
    $errors = array();

    foreach (offline_sync_tables() as $table) {
        if (empty($payload[$table])) {
            continue;
        }

        if (!offline_sync_table_exists($pdo, $table)) {
            $errors[] = "Tabela țintă {$table} nu există.";
            continue;
        }

        if (!offline_sync_column_exists($pdo, $table, 'identificator_offline')) {
            $errors[] = "Tabela țintă {$table} nu are coloana identificator_offline.";
        }
    }

    $needsMiscari = !empty($payload['note']) || !empty($payload['det_note']);
    if ($needsMiscari && offline_sync_table_exists($pdo, 'miscari') && !offline_sync_column_exists($pdo, 'miscari', 'identificator_offline')) {
        $errors[] = 'Tabela țintă miscari nu are coloana identificator_offline.';
    }

    if (!empty($errors)) {
        throw new OfflineSyncHttpException(409, 'Schema online nu este pregătită pentru import.', $errors);
    }
}

function offline_sync_primary_column($table, array $columns = array())
{
    if ($table === 'miscari') {
        if (isset($columns['id'])) {
            return 'id';
        }

        if (isset($columns['id_miscare'])) {
            return 'id_miscare';
        }

        return 'id';
    }

    $map = array(
        'note' => 'nrbon',
        'det_note' => 'id_vanz',
        'inchideri_r_12' => 'id_inch',
        'rapoarte_z' => 'id',
        'discounturi_acordate' => 'id_discount',
    );

    return isset($map[$table]) ? $map[$table] : '';
}

function offline_sync_is_auto_increment(array $columns, $column)
{
    return isset($columns[$column]) && stripos((string)$columns[$column]['Extra'], 'auto_increment') !== false;
}

function offline_sync_import_stats()
{
    $stats = array(
        'inserted' => array(),
        'duplicates' => array(),
        'updated' => array(),
    );

    foreach (offline_sync_tables() as $table) {
        $stats['inserted'][$table] = 0;
        $stats['duplicates'][$table] = 0;
        $stats['updated'][$table] = 0;
    }
    $stats['inserted']['miscari'] = 0;
    $stats['duplicates']['miscari'] = 0;
    $stats['updated']['miscari'] = 0;

    return $stats;
}

function offline_sync_find_imported(PDO $pdo, $sourceTable, $sourcePk, $codLocatie, $installationUuid = '')
{
    $stmt = $pdo->prepare("
        SELECT *
        FROM offline_sync_imported
        WHERE source_table = :source_table
          AND source_pk = :source_pk
          AND cod_locatie = :cod_locatie
          AND (installation_uuid = :installation_uuid OR (:allow_legacy = 1 AND installation_uuid = ''))
        ORDER BY CASE WHEN installation_uuid = :installation_uuid_order THEN 0 ELSE 1 END
        LIMIT 1
    ");
    $stmt->execute(array(
        ':source_table' => $sourceTable,
        ':source_pk' => (string)$sourcePk,
        ':cod_locatie' => (int)$codLocatie,
        ':installation_uuid' => (string)$installationUuid,
        ':allow_legacy' => $installationUuid !== '' ? 1 : 0,
        ':installation_uuid_order' => (string)$installationUuid,
    ));

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row : null;
}

function offline_sync_find_imported_by_sync_id(PDO $pdo, $syncId)
{
    $stmt = $pdo->prepare("
        SELECT *
        FROM offline_sync_imported
        WHERE sync_id = :sync_id
        LIMIT 1
    ");
    $stmt->execute(array(':sync_id' => (string)$syncId));

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row : null;
}

function offline_sync_imported_target_exists(PDO $pdo, array $imported)
{
    $targetTable = isset($imported['target_table']) && (string)$imported['target_table'] !== ''
        ? (string)$imported['target_table']
        : (string)($imported['source_table'] ?? '');
    $targetPk = isset($imported['target_pk']) ? (string)$imported['target_pk'] : '';

    if ($targetTable === '' || $targetPk === '' || !offline_sync_table_exists($pdo, $targetTable)) {
        return false;
    }

    $columns = offline_sync_get_columns($pdo, $targetTable);
    $pkColumn = offline_sync_primary_column($targetTable, $columns);
    if ($pkColumn === '' || !isset($columns[$pkColumn])) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT 1 FROM ' . offline_sync_quote_identifier($targetTable) .
        ' WHERE ' . offline_sync_quote_identifier($pkColumn) . ' = ? LIMIT 1'
    );
    $stmt->execute(array($targetPk));

    return (bool)$stmt->fetchColumn();
}

function offline_sync_delete_imported_marker(PDO $pdo, array $imported)
{
    if (isset($imported['id']) && (int)$imported['id'] > 0) {
        $stmt = $pdo->prepare('DELETE FROM offline_sync_imported WHERE id = :id');
        $stmt->execute(array(':id' => (int)$imported['id']));
        return;
    }

    $stmt = $pdo->prepare("
        DELETE FROM offline_sync_imported
        WHERE source_table = :source_table
          AND source_pk = :source_pk
          AND cod_locatie = :cod_locatie
    ");
    $stmt->execute(array(
        ':source_table' => (string)($imported['source_table'] ?? ''),
        ':source_pk' => (string)($imported['source_pk'] ?? ''),
        ':cod_locatie' => (int)($imported['cod_locatie'] ?? 0),
    ));
}

function offline_sync_find_valid_imported_by_sync_id(PDO $pdo, $syncId)
{
    $imported = offline_sync_find_imported_by_sync_id($pdo, $syncId);
    if (!$imported) {
        return null;
    }

    if (offline_sync_imported_target_exists($pdo, $imported)) {
        return $imported;
    }

    offline_sync_delete_imported_marker($pdo, $imported);
    return null;
}

function offline_sync_mark_imported(PDO $pdo, $syncExportId, $payloadHash, array $sync, $targetTable, $targetPk, array $targetRef = array())
{
    $stmt = $pdo->prepare("
        INSERT INTO offline_sync_imported
            (sync_export_id, installation_uuid, source_table, source_pk, cod_locatie, sync_id, target_table, target_pk, target_ref, payload_hash, imported_at)
        VALUES
            (:sync_export_id, :installation_uuid, :source_table, :source_pk, :cod_locatie, :sync_id, :target_table, :target_pk, :target_ref, :payload_hash, NOW())
        ON DUPLICATE KEY UPDATE
            sync_export_id = VALUES(sync_export_id),
            sync_id = VALUES(sync_id),
            target_table = VALUES(target_table),
            target_pk = VALUES(target_pk),
            target_ref = VALUES(target_ref),
            payload_hash = VALUES(payload_hash),
            imported_at = NOW()
    ");
    $stmt->execute(array(
        ':sync_export_id' => $syncExportId,
        ':installation_uuid' => (string)($sync['installation_uuid'] ?? ''),
        ':source_table' => (string)$sync['source_table'],
        ':source_pk' => (string)$sync['source_pk'],
        ':cod_locatie' => (int)$sync['cod_locatie'],
        ':sync_id' => (string)$sync['sync_id'],
        ':target_table' => $targetTable,
        ':target_pk' => (string)$targetPk,
        ':target_ref' => empty($targetRef) ? null : json_encode($targetRef, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':payload_hash' => $payloadHash,
    ));
}

function offline_sync_target_value_exists(PDO $pdo, $table, $column, $value)
{
    if ($value === null || (string)$value === '' || (string)$value === '0') {
        return true;
    }

    if (!offline_sync_table_exists($pdo, $table)) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT 1 FROM ' . offline_sync_quote_identifier($table) .
        ' WHERE ' . offline_sync_quote_identifier($column) . ' = ? LIMIT 1'
    );
    $stmt->execute(array($value));

    return (bool)$stmt->fetchColumn();
}

function offline_sync_find_target_by_identifier(PDO $pdo, $table, $identifier)
{
    $identifier = trim((string)$identifier);
    if ($identifier === '' || !offline_sync_table_exists($pdo, $table) || !offline_sync_column_exists($pdo, $table, 'identificator_offline')) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT * FROM ' . offline_sync_quote_identifier($table) .
        ' WHERE identificator_offline = ? LIMIT 1'
    );
    $stmt->execute(array($identifier));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ? $row : null;
}

function offline_sync_find_target_by_offline_source(PDO $pdo, $table, $sourcePk, $codLocatie, $clientId, $installationUuid = '')
{
    $identifier = offline_sync_identifier($clientId, $codLocatie, $table, $sourcePk, $installationUuid);
    $target = offline_sync_find_target_by_identifier($pdo, $table, $identifier);
    if (!$target && $installationUuid !== '') {
        $target = offline_sync_find_target_by_identifier($pdo, $table, offline_sync_identifier($clientId, $codLocatie, $table, $sourcePk));
    }
    return $target;
}

function offline_sync_target_pk_from_row(PDO $pdo, $table, array $row)
{
    $columns = offline_sync_get_columns($pdo, $table);
    $pkColumn = offline_sync_primary_column($table, $columns);
    if ($pkColumn !== '' && array_key_exists($pkColumn, $row)) {
        return (string)$row[$pkColumn];
    }

    return '';
}

function offline_sync_target_result_from_row(PDO $pdo, $table, array $row)
{
    $targetPk = offline_sync_target_pk_from_row($pdo, $table, $row);
    return array($targetPk, offline_sync_build_target_ref($table, $row, $targetPk));
}

function offline_sync_set_target_identifier_if_empty(PDO $pdo, $table, array $row, $identifier)
{
    $identifier = trim((string)$identifier);
    if ($identifier === '' || !offline_sync_column_exists($pdo, $table, 'identificator_offline')) {
        return $row;
    }

    $current = isset($row['identificator_offline']) ? trim((string)$row['identificator_offline']) : '';
    if ($current === $identifier) {
        return $row;
    }

    if ($current !== '') {
        return $row;
    }

    $columns = offline_sync_get_columns($pdo, $table);
    $pkColumn = offline_sync_primary_column($table, $columns);
    if ($pkColumn === '' || !array_key_exists($pkColumn, $row)) {
        return $row;
    }

    $stmt = $pdo->prepare(
        'UPDATE ' . offline_sync_quote_identifier($table) .
        ' SET identificator_offline = ? WHERE ' . offline_sync_quote_identifier($pkColumn) . ' = ?'
    );
    $stmt->execute(array($identifier, $row[$pkColumn]));
    $row['identificator_offline'] = $identifier;

    return $row;
}

function offline_sync_source_value(array $row, $column)
{
    $originalColumn = (string)$column . '_original';
    if (array_key_exists($originalColumn, $row)) {
        return (string)$row[$originalColumn];
    }

    if (array_key_exists($column, $row)) {
        return (string)$row[$column];
    }

    return '';
}

function offline_sync_source_positive(array $row, $column)
{
    return (int)offline_sync_source_value($row, $column) > 0;
}

function offline_sync_find_valid_imported(PDO $pdo, $sourceTable, $sourcePk, $codLocatie, $installationUuid = '')
{
    $imported = offline_sync_find_imported($pdo, $sourceTable, $sourcePk, $codLocatie, $installationUuid);
    if (!$imported) {
        return null;
    }

    if (offline_sync_imported_target_exists($pdo, $imported)) {
        return $imported;
    }

    offline_sync_delete_imported_marker($pdo, $imported);
    return null;
}

function offline_sync_decode_target_ref(array $imported)
{
    $raw = isset($imported['target_ref']) ? trim((string)$imported['target_ref']) : '';
    if ($raw === '') {
        return array();
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : array();
}

function offline_sync_fetch_imported_target_row(PDO $pdo, array $imported)
{
    $targetTable = isset($imported['target_table']) && (string)$imported['target_table'] !== ''
        ? (string)$imported['target_table']
        : (string)($imported['source_table'] ?? '');
    $targetPk = isset($imported['target_pk']) ? (string)$imported['target_pk'] : '';

    if ($targetTable === '' || $targetPk === '' || !offline_sync_table_exists($pdo, $targetTable)) {
        return array();
    }

    $columns = offline_sync_get_columns($pdo, $targetTable);
    $pkColumn = offline_sync_primary_column($targetTable, $columns);
    if ($pkColumn === '' || !isset($columns[$pkColumn])) {
        return array();
    }

    $stmt = $pdo->prepare(
        'SELECT * FROM ' . offline_sync_quote_identifier($targetTable) .
        ' WHERE ' . offline_sync_quote_identifier($pkColumn) . ' = ? LIMIT 1'
    );
    $stmt->execute(array($targetPk));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ? $row : array();
}

function offline_sync_imported_target_ref(PDO $pdo, array $imported)
{
    $targetRef = offline_sync_decode_target_ref($imported);
    if (!empty($targetRef)) {
        return $targetRef;
    }

    $row = offline_sync_fetch_imported_target_row($pdo, $imported);
    if (empty($row)) {
        return array();
    }

    $table = isset($imported['target_table']) && (string)$imported['target_table'] !== ''
        ? (string)$imported['target_table']
        : (string)($imported['source_table'] ?? '');
    $ref = array();
    foreach (array('nr_raport_z', 'cod_inchidere', 'nrbon', 'id_vanz', 'id_inch', 'id') as $column) {
        if (isset($row[$column])) {
            $ref[$column] = (string)$row[$column];
        }
    }
    $ref['target_table'] = $table;

    return $ref;
}

function offline_sync_new_maps()
{
    return array(
        'rapoarte_z_by_nr' => array(),
        'inchideri_by_cod' => array(),
        'note_by_nr' => array(),
        'det_note_by_sync_id' => array(),
    );
}

function offline_sync_register_mapping($table, array $row, array $sync, $targetPk, array $targetRef, array &$maps, array &$noteNumbersForMiscari, $trackMiscari = true)
{
    if ($table === 'rapoarte_z') {
        $sourceNr = offline_sync_source_value($row, 'nr_raport_z');
        $targetNr = isset($targetRef['nr_raport_z']) ? (string)$targetRef['nr_raport_z'] : '';
        if ($sourceNr !== '' && $targetNr !== '') {
            $maps['rapoarte_z_by_nr'][$sourceNr] = $targetNr;
        }
        return;
    }

    if ($table === 'inchideri_r_12') {
        $sourceCod = offline_sync_source_value($row, 'cod_inchidere');
        $targetCod = isset($targetRef['cod_inchidere']) ? (string)$targetRef['cod_inchidere'] : '';
        if ($sourceCod !== '' && $targetCod !== '') {
            $maps['inchideri_by_cod'][$sourceCod] = $targetCod;
        }
        return;
    }

    if ($table === 'note') {
        $sourceNr = offline_sync_source_value($row, 'nrbon');
        if ($sourceNr !== '' && (string)$targetPk !== '') {
            $maps['note_by_nr'][$sourceNr] = (string)$targetPk;
            if ($trackMiscari) {
                $noteNumbersForMiscari[] = (string)$targetPk;
            }
        }
        return;
    }

    if ($table === 'det_note') {
        if (isset($sync['sync_id']) && (string)$targetPk !== '') {
            $maps['det_note_by_sync_id'][(string)$sync['sync_id']] = (string)$targetPk;
        }
        if ($trackMiscari && isset($targetRef['nr_bon']) && (string)$targetRef['nr_bon'] !== '') {
            $noteNumbersForMiscari[] = (string)$targetRef['nr_bon'];
        }
    }
}

function offline_sync_register_existing_mapping(PDO $pdo, $table, array $row, array $sync, array $imported, array &$maps, array &$noteNumbersForMiscari)
{
    $targetPk = isset($imported['target_pk']) ? (string)$imported['target_pk'] : '';
    $targetRef = offline_sync_imported_target_ref($pdo, $imported);
    offline_sync_register_mapping($table, $row, $sync, $targetPk, $targetRef, $maps, $noteNumbersForMiscari, false);
}

function offline_sync_hydrate_note_map(PDO $pdo, $sourceNrBon, $codLocatie, $clientId, array &$maps, $installationUuid = '')
{
    $sourceNrBon = (string)$sourceNrBon;
    if ($sourceNrBon === '' || isset($maps['note_by_nr'][$sourceNrBon])) {
        return;
    }

    $imported = offline_sync_find_valid_imported($pdo, 'note', $sourceNrBon, $codLocatie, $installationUuid);
    if ($imported) {
        $targetRef = offline_sync_imported_target_ref($pdo, $imported);
        if (isset($targetRef['nrbon']) && (string)$targetRef['nrbon'] !== '') {
            $maps['note_by_nr'][$sourceNrBon] = (string)$targetRef['nrbon'];
            return;
        }

        if (isset($imported['target_pk']) && (string)$imported['target_pk'] !== '') {
            $maps['note_by_nr'][$sourceNrBon] = (string)$imported['target_pk'];
            return;
        }
    }

    $existingNote = offline_sync_find_target_by_offline_source($pdo, 'note', $sourceNrBon, $codLocatie, $clientId, $installationUuid);
    if ($existingNote) {
        $targetPk = offline_sync_target_pk_from_row($pdo, 'note', $existingNote);
        if ($targetPk !== '') {
            $maps['note_by_nr'][$sourceNrBon] = $targetPk;
        }
    }
}

function offline_sync_next_int_value(PDO $pdo, $table, $column, $locationColumn = '', $codLocatie = 0)
{
    $sql = 'SELECT COALESCE(MAX(' . offline_sync_quote_identifier($column) . '), 0) + 1 FROM ' . offline_sync_quote_identifier($table);
    $params = array();

    if ($locationColumn !== '' && offline_sync_column_exists($pdo, $table, $locationColumn) && (int)$codLocatie > 0) {
        $sql .= ' WHERE ' . offline_sync_quote_identifier($locationColumn) . ' = :cod_locatie';
        $params[':cod_locatie'] = (int)$codLocatie;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (int)$stmt->fetchColumn();
}

function offline_sync_generated_location_column($table, array $columns)
{
    if ($table === 'rapoarte_z' && isset($columns['cod_locatie'])) {
        return 'cod_locatie';
    }

    if ($table === 'inchideri_r_12' && isset($columns['locatie'])) {
        return 'locatie';
    }

    return '';
}

function offline_sync_next_closure_code(PDO $pdo, $codLocatie)
{
    $values = array();

    if (offline_sync_table_exists($pdo, 'note') && offline_sync_column_exists($pdo, 'note', 'cod_inchidere')) {
        $sql = 'SELECT COALESCE(MAX(cod_inchidere), 0) FROM note';
        $params = array();
        if (offline_sync_column_exists($pdo, 'note', 'locatie') && (int)$codLocatie > 0) {
            $sql .= ' WHERE locatie = :cod_locatie';
            $params[':cod_locatie'] = (int)$codLocatie;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $values[] = (int)$stmt->fetchColumn();
    }

    if (offline_sync_table_exists($pdo, 'inchideri_r_12') && offline_sync_column_exists($pdo, 'inchideri_r_12', 'cod_inchidere')) {
        $sql = 'SELECT COALESCE(MAX(cod_inchidere), 0) FROM inchideri_r_12';
        $params = array();
        if (offline_sync_column_exists($pdo, 'inchideri_r_12', 'locatie') && (int)$codLocatie > 0) {
            $sql .= ' WHERE locatie = :cod_locatie';
            $params[':cod_locatie'] = (int)$codLocatie;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $values[] = (int)$stmt->fetchColumn();
    }

    return (empty($values) ? 1 : max($values) + 1);
}

function offline_sync_string_set(array $rows, $field)
{
    $set = array();
    foreach ($rows as $row) {
        if (isset($row[$field]) && (string)$row[$field] !== '') {
            $set[(string)$row[$field]] = true;
        }
    }

    return $set;
}

function offline_sync_validate_relations(PDO $pdo, array $payload, $clientId)
{
    $errors = array();
    $noteSet = offline_sync_string_set($payload['note'], 'nrbon');
    $inchideriSet = offline_sync_string_set($payload['inchideri_r_12'], 'cod_inchidere');
    $rapoarteSet = offline_sync_string_set($payload['rapoarte_z'], 'nr_raport_z');
    $detSyncSet = array();
    $codLocatie = (int)($payload['cod_locatie'] ?? 0);
    $installationUuid = (string)($payload['installation_uuid'] ?? '');

    foreach ($payload['det_note'] as $row) {
        if (isset($row['_sync']['sync_id'])) {
            $detSyncSet[(string)$row['_sync']['sync_id']] = true;
        }
    }

    foreach ($payload['det_note'] as $index => $row) {
        $nrBon = offline_sync_source_value($row, 'nr_bon');
        $hasImportedNote = offline_sync_find_valid_imported($pdo, 'note', $nrBon, $codLocatie, $installationUuid)
            || offline_sync_find_target_by_offline_source($pdo, 'note', $nrBon, $codLocatie, $clientId, $installationUuid);
        if ($nrBon !== '' && !isset($noteSet[$nrBon]) && !$hasImportedNote) {
            $errors[] = "det_note[{$index}] referă o notă inexistentă: {$nrBon}.";
        }
    }

    foreach ($payload['note'] as $index => $row) {
        $codInchidere = offline_sync_source_value($row, 'cod_inchidere');
        if ($codInchidere !== '' && $codInchidere !== '0' && !isset($inchideriSet[$codInchidere])) {
            $errors[] = "note[{$index}] referă o închidere inexistentă: {$codInchidere}.";
        }

        $nrRaport = offline_sync_source_value($row, 'nr_raport_z');
        if ($nrRaport !== '' && $nrRaport !== '0' && !isset($rapoarteSet[$nrRaport])) {
            $errors[] = "note[{$index}] referă un raport Z inexistent: {$nrRaport}.";
        }
    }

    foreach ($payload['inchideri_r_12'] as $index => $row) {
        $nrRaport = offline_sync_source_value($row, 'nr_raport_z');
        if ($nrRaport !== '' && $nrRaport !== '0' && !isset($rapoarteSet[$nrRaport])) {
            $errors[] = "inchideri_r_12[{$index}] referă un raport Z inexistent: {$nrRaport}.";
        }
    }

    foreach ($payload['discounturi_acordate'] as $index => $row) {
        $syncRef = isset($row['id_vanz_sync_ref']) ? (string)$row['id_vanz_sync_ref'] : '';
        $detSourcePk = offline_sync_source_pk_from_sync_id($syncRef, 'det_note');
        $hasImportedDet = offline_sync_find_valid_imported_by_sync_id($pdo, $syncRef)
            || ($detSourcePk !== '' && offline_sync_find_target_by_offline_source($pdo, 'det_note', $detSourcePk, $codLocatie, $clientId, $installationUuid));
        if ($syncRef !== '' && !isset($detSyncSet[$syncRef]) && !$hasImportedDet) {
            $errors[] = "discounturi_acordate[{$index}] nu are mapare pentru det_note: {$syncRef}.";
        }
    }

    if (!empty($errors)) {
        throw new OfflineSyncHttpException(409, 'Relațiile din payload nu sunt valide.', $errors);
    }
}

function offline_sync_number_value($value)
{
    if ($value === null || $value === '') {
        return 0.0;
    }

    return (float)str_replace(',', '.', (string)$value);
}

function offline_sync_apply_miscari_insert_defaults(array $data, array $row, array $columns)
{
    $cantitate = offline_sync_number_value(isset($data['cantitate_misc']) ? $data['cantitate_misc'] : ($row['cantitate_misc'] ?? 0));
    $pu = offline_sync_number_value(isset($data['pu']) ? $data['pu'] : ($row['pu'] ?? 0));
    $pretVanzare = offline_sync_number_value(isset($data['pret_vanzare']) ? $data['pret_vanzare'] : ($row['pret_vanzare'] ?? 0));

    $defaults = array(
        'valoare_achizitie' => $cantitate * $pu,
        'valoare_vanzare' => $cantitate * $pretVanzare,
        'diminueaza_pe' => 0,
        'nume_produs_obtinut' => '',
        'ramas' => $cantitate,
        'nr_nir' => '',
        'id_doc' => 0,
        'id_achiz' => 0,
        'id_vanz_fact' => 0,
        'id_rand_bon_consum_manual' => 0,
        'id_rand_bon_consum_productie' => 0,
        'id_rand_proces_verbal_inventar' => 0,
        'id_retur' => 0,
        'id_rand_pv_deteriorare' => 0,
    );

    foreach ($defaults as $column => $value) {
        if (isset($columns[$column]) && !array_key_exists($column, $data)) {
            $data[$column] = $value;
        }
    }

    return $data;
}

function offline_sync_should_skip_source_column($table, $column)
{
    $skip = array(
        'rapoarte_z' => array('id', 'nr_raport_z'),
        'inchideri_r_12' => array('id_inch', 'cod_inchidere', 'nr_raport_z'),
        'note' => array('nrbon', 'cod_inchidere', 'nr_raport_z'),
        'det_note' => array('id_vanz', 'nr_bon'),
        'discounturi_acordate' => array('id_discount', 'id_vanz'),
        'miscari' => array('id', 'id_miscare'),
    );

    return isset($skip[$table]) && in_array((string)$column, $skip[$table], true);
}

function offline_sync_ensure_generated_pk(PDO $pdo, $table, array $columns, array &$data, $codLocatie)
{
    $pkColumn = offline_sync_primary_column($table, $columns);
    if ($pkColumn === '' || !isset($columns[$pkColumn]) || offline_sync_is_auto_increment($columns, $pkColumn)) {
        return;
    }

    if (array_key_exists($pkColumn, $data) && (string)$data[$pkColumn] !== '') {
        return;
    }

    $data[$pkColumn] = offline_sync_next_int_value($pdo, $table, $pkColumn);
}

function offline_sync_require_map_value(array $map, $sourceValue, $message)
{
    $key = (string)$sourceValue;
    if ($key === '' || $key === '0') {
        return 0;
    }

    if (!isset($map[$key]) || (string)$map[$key] === '') {
        throw new RuntimeException($message . ': ' . $key);
    }

    return (int)$map[$key];
}

function offline_sync_apply_generated_fields(PDO $pdo, $table, array $row, array $columns, array &$data, array $maps, $codLocatie)
{
    if ($table === 'rapoarte_z') {
        offline_sync_ensure_generated_pk($pdo, $table, $columns, $data, $codLocatie);
        if (isset($columns['nr_raport_z'])) {
            $data['nr_raport_z'] = offline_sync_next_int_value($pdo, 'rapoarte_z', 'nr_raport_z', offline_sync_generated_location_column('rapoarte_z', $columns), $codLocatie);
        }
        return;
    }

    if ($table === 'inchideri_r_12') {
        offline_sync_ensure_generated_pk($pdo, $table, $columns, $data, $codLocatie);
        if (isset($columns['cod_inchidere'])) {
            $data['cod_inchidere'] = offline_sync_next_closure_code($pdo, $codLocatie);
        }
        if (isset($columns['nr_raport_z'])) {
            $sourceRaport = offline_sync_source_value($row, 'nr_raport_z');
            $data['nr_raport_z'] = offline_sync_require_map_value($maps['rapoarte_z_by_nr'], $sourceRaport, 'Nu există mapare online pentru raportul Z offline');
        }
        return;
    }

    if ($table === 'note') {
        offline_sync_ensure_generated_pk($pdo, $table, $columns, $data, $codLocatie);
        if (isset($columns['nrbon_offline'])) {
            $sourceNrBon = offline_sync_source_value($row, 'nrbon');
            if ((int)$sourceNrBon > 0) {
                $data['nrbon_offline'] = (int)$sourceNrBon;
            }
        }
        if (isset($columns['cod_inchidere'])) {
            $sourceCod = offline_sync_source_value($row, 'cod_inchidere');
            $data['cod_inchidere'] = offline_sync_require_map_value($maps['inchideri_by_cod'], $sourceCod, 'Nu există mapare online pentru închiderea offline');
        }
        if (isset($columns['nr_raport_z'])) {
            $sourceRaport = offline_sync_source_value($row, 'nr_raport_z');
            $data['nr_raport_z'] = offline_sync_require_map_value($maps['rapoarte_z_by_nr'], $sourceRaport, 'Nu există mapare online pentru raportul Z offline');
        }
        return;
    }

    if ($table === 'det_note') {
        offline_sync_ensure_generated_pk($pdo, $table, $columns, $data, $codLocatie);
        if (isset($columns['nr_bon'])) {
            $sourceNrBon = offline_sync_source_value($row, 'nr_bon');
            $data['nr_bon'] = offline_sync_require_map_value($maps['note_by_nr'], $sourceNrBon, 'Nu există mapare online pentru nota offline');
        }
        return;
    }

    if ($table === 'discounturi_acordate') {
        offline_sync_ensure_generated_pk($pdo, $table, $columns, $data, $codLocatie);
        if (isset($columns['id_vanz'])) {
            $syncRef = isset($row['id_vanz_sync_ref']) ? (string)$row['id_vanz_sync_ref'] : '';
            $data['id_vanz'] = offline_sync_require_map_value($maps['det_note_by_sync_id'], $syncRef, 'Nu există mapare online pentru linia det_note');
        }
    }
}

function offline_sync_row_to_insert_data(PDO $pdo, $table, array $row, array $columns, array $maps, $codLocatie, $identifier = '')
{
    $data = array();

    foreach ($row as $column => $value) {
        if ($column === '_sync' || $column === 'id_vanz_sync_ref' || substr((string)$column, -9) === '_original') {
            continue;
        }

        if (!isset($columns[$column])) {
            continue;
        }

        if (offline_sync_should_skip_source_column($table, $column)) {
            continue;
        }

        $pkColumn = offline_sync_primary_column($table, $columns);
        if ($pkColumn !== '' && $column === $pkColumn && offline_sync_is_auto_increment($columns, $pkColumn)) {
            continue;
        }

        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } elseif (is_bool($value)) {
            $value = $value ? 1 : 0;
        }

        $data[$column] = $value;
    }

    if (isset($columns['cod_locatie'])) {
        $data['cod_locatie'] = (int)$codLocatie;
    }

    if ($identifier !== '' && isset($columns['identificator_offline'])) {
        $data['identificator_offline'] = (string)$identifier;
    }

    offline_sync_apply_generated_fields($pdo, $table, $row, $columns, $data, $maps, $codLocatie);

    if ($table === 'miscari') {
        $data = offline_sync_apply_miscari_insert_defaults($data, $row, $columns);
    }

    return $data;
}

function offline_sync_build_target_ref($table, array $data, $targetPk)
{
    $ref = array(
        'target_table' => $table,
        'target_pk' => (string)$targetPk,
    );

    foreach (array('nr_raport_z', 'cod_inchidere', 'nrbon', 'nr_bon', 'id_vanz', 'id_inch', 'id', 'identificator_offline') as $column) {
        if (array_key_exists($column, $data)) {
            $ref[$column] = (string)$data[$column];
        }
    }

    return $ref;
}

function offline_sync_insert_target_row(PDO $pdo, $table, array $row, array $maps, $codLocatie, $identifier = '')
{
    $columns = offline_sync_get_columns($pdo, $table);
    $data = offline_sync_row_to_insert_data($pdo, $table, $row, $columns, $maps, $codLocatie, $identifier);

    if (empty($data)) {
        throw new RuntimeException("Nu există coloane valide pentru inserare în {$table}.");
    }

    $quotedColumns = array();
    $placeholders = array();
    $values = array();

    foreach ($data as $column => $value) {
        $quotedColumns[] = offline_sync_quote_identifier($column);
        $placeholders[] = '?';
        $values[] = $value;
    }

    $sql = 'INSERT INTO ' . offline_sync_quote_identifier($table)
        . ' (' . implode(', ', $quotedColumns) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);

    $pkColumn = offline_sync_primary_column($table, $columns);
    if ($pkColumn !== '' && array_key_exists($pkColumn, $data)) {
        $targetPk = (string)$data[$pkColumn];
        return array($targetPk, offline_sync_build_target_ref($table, $data, $targetPk));
    }

    $lastId = (string)$pdo->lastInsertId();
    if ($lastId !== '' && $lastId !== '0') {
        if ($pkColumn !== '') {
            $data[$pkColumn] = $lastId;
        }
        return array($lastId, offline_sync_build_target_ref($table, $data, $lastId));
    }

    if ($pkColumn !== '' && isset($row[$pkColumn])) {
        $targetPk = (string)$row[$pkColumn];
        return array($targetPk, offline_sync_build_target_ref($table, $data, $targetPk));
    }

    return array('', offline_sync_build_target_ref($table, $data, ''));
}

function offline_sync_event_updates_table($eventType, $table)
{
    return ((string)$eventType === 'shift_closed' && (string)$table === 'note')
        || ((string)$eventType === 'z_closed' && in_array((string)$table, array('note', 'inchideri_r_12'), true));
}

function offline_sync_is_v2_event(array $payload)
{
    return isset($payload['schema_version']) && (string)$payload['schema_version'] === OFFLINE_SYNC_SCHEMA_VERSION_V2;
}

function offline_sync_event_inbox_row(PDO $pdo, $eventUuid)
{
    $stmt = $pdo->prepare('SELECT * FROM offline_sync_event_inbox WHERE event_uuid = ? LIMIT 1');
    $stmt->execute(array((string)$eventUuid));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row : null;
}

function offline_sync_event_existing_response(PDO $pdo, array $payload, $payloadHash)
{
    if (!offline_sync_is_v2_event($payload)) {
        return null;
    }
    $row = offline_sync_event_inbox_row($pdo, (string)$payload['event_uuid']);
    if ($row && !hash_equals((string)$row['payload_hash'], (string)$payloadHash)) {
        throw new OfflineSyncHttpException(409, 'event_uuid a fost reutilizat cu alt conținut.');
    }
    if (!$row || (string)$row['status'] !== 'processed') {
        return null;
    }
    $response = json_decode((string)($row['response_json'] ?? ''), true);
    if (!is_array($response)) {
        $response = array('status' => 'success', 'message' => 'Evenimentul fusese deja procesat.');
    }
    $response['status'] = 'success';
    $response['acknowledgement'] = 'already_processed';
    $response['event_uuid'] = (string)$payload['event_uuid'];
    return $response;
}

function offline_sync_event_begin(PDO $pdo, array $payload, $payloadHash)
{
    if (!offline_sync_is_v2_event($payload)) {
        return;
    }
    $stmt = $pdo->prepare("INSERT IGNORE INTO offline_sync_event_inbox
        (event_uuid, installation_uuid, event_type, aggregate_type, aggregate_id, payload_hash, status, received_at)
        VALUES (?, ?, ?, ?, ?, ?, 'processing', NOW())");
    $stmt->execute(array(
        (string)$payload['event_uuid'], (string)$payload['installation_uuid'], (string)$payload['event_type'],
        (string)$payload['aggregate_type'], (string)$payload['aggregate_id'], (string)$payloadHash,
    ));
    if ($stmt->rowCount() === 0) {
        $row = offline_sync_event_inbox_row($pdo, (string)$payload['event_uuid']);
        if ($row && !hash_equals((string)$row['payload_hash'], (string)$payloadHash)) {
            throw new OfflineSyncHttpException(409, 'event_uuid a fost reutilizat cu alt conținut.');
        }
        if ($row && (string)$row['status'] === 'processed') {
            return;
        }
        throw new OfflineSyncHttpException(503, 'Evenimentul este deja în curs de procesare.');
    }
}

function offline_sync_event_complete(PDO $pdo, array $payload, array $response)
{
    if (!offline_sync_is_v2_event($payload)) {
        return;
    }
    $stmt = $pdo->prepare("UPDATE offline_sync_event_inbox
        SET status = 'processed', response_json = ?, processed_at = NOW()
        WHERE event_uuid = ?");
    $stmt->execute(array(
        json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        (string)$payload['event_uuid'],
    ));
}

function offline_sync_update_event_target(PDO $pdo, $table, array $sourceRow, array $targetRow, array $maps, $eventType)
{
    $updates = array();

    if ($table === 'note') {
        $sourceNrBon = offline_sync_source_value($sourceRow, 'nrbon');
        if ((int)$sourceNrBon > 0) {
            $updates['nrbon_offline'] = (int)$sourceNrBon;
        }
    }

    if ($table === 'note' && in_array((string)$eventType, array('shift_closed', 'z_closed'), true)) {
        $sourceClosure = offline_sync_source_value($sourceRow, 'cod_inchidere');
        if ((int)$sourceClosure > 0) {
            $updates['cod_inchidere'] = offline_sync_require_map_value($maps['inchideri_by_cod'], $sourceClosure, 'Nu există mapare online pentru închiderea offline');
        }
        if ((string)$eventType === 'z_closed') {
            $sourceReport = offline_sync_source_value($sourceRow, 'nr_raport_z');
            if ((int)$sourceReport > 0) {
                $updates['nr_raport_z'] = offline_sync_require_map_value($maps['rapoarte_z_by_nr'], $sourceReport, 'Nu există mapare online pentru raportul Z offline');
            }
        }
    }

    if ($table === 'inchideri_r_12' && (string)$eventType === 'z_closed') {
        $sourceReport = offline_sync_source_value($sourceRow, 'nr_raport_z');
        if ((int)$sourceReport > 0) {
            $updates['nr_raport_z'] = offline_sync_require_map_value($maps['rapoarte_z_by_nr'], $sourceReport, 'Nu există mapare online pentru raportul Z offline');
        }
    }

    if (empty($updates)) {
        return $targetRow;
    }

    $columns = offline_sync_get_columns($pdo, $table);
    $pkColumn = offline_sync_primary_column($table, $columns);
    if ($pkColumn === '' || !isset($targetRow[$pkColumn])) {
        throw new RuntimeException('Înregistrarea online nu poate fi actualizată deoarece lipsește cheia primară.');
    }

    $assignments = array();
    $values = array();
    foreach ($updates as $column => $value) {
        if (!isset($columns[$column])) {
            continue;
        }
        $assignments[] = offline_sync_quote_identifier($column) . ' = ?';
        $values[] = $value;
        $targetRow[$column] = $value;
    }
    if (empty($assignments)) {
        return $targetRow;
    }
    $values[] = $targetRow[$pkColumn];
    $stmt = $pdo->prepare('UPDATE ' . offline_sync_quote_identifier($table) . ' SET ' . implode(', ', $assignments)
        . ' WHERE ' . offline_sync_quote_identifier($pkColumn) . ' = ?');
    $stmt->execute($values);

    return $targetRow;
}

function offline_sync_import_table(PDO $pdo, array $payload, $table, $payloadHash, array &$stats, array &$maps, array &$noteNumbersForMiscari, $clientId)
{
    $codLocatie = (int)$payload['cod_locatie'];
    $syncExportId = (string)$payload['sync_export_id'];
    $eventType = (string)($payload['event_type'] ?? 'legacy_batch');
    $installationUuid = (string)($payload['installation_uuid'] ?? '');

    foreach ($payload[$table] as $row) {
        $sync = $row['_sync'];
        $identifier = offline_sync_identifier_from_sync($sync, $clientId);
        $existingTarget = offline_sync_find_target_by_identifier($pdo, $table, $identifier);

        if ($existingTarget) {
            $existingTarget = offline_sync_update_event_target($pdo, $table, $row, $existingTarget, $maps, $eventType);
            list($targetPk, $targetRef) = offline_sync_target_result_from_row($pdo, $table, $existingTarget);
            offline_sync_mark_imported($pdo, $syncExportId, $payloadHash, $sync, $table, $targetPk, $targetRef);
            offline_sync_register_mapping($table, $row, $sync, $targetPk, $targetRef, $maps, $noteNumbersForMiscari, false);
            $stats['duplicates'][$table]++;
            if (offline_sync_event_updates_table($eventType, $table)) {
                $stats['updated'][$table]++;
            }
            continue;
        }

        $existing = offline_sync_find_imported($pdo, $sync['source_table'], $sync['source_pk'], $codLocatie, (string)($sync['installation_uuid'] ?? $installationUuid));
        if ($existing) {
            if (offline_sync_imported_target_exists($pdo, $existing)) {
                $targetRow = offline_sync_fetch_imported_target_row($pdo, $existing);
                if (!empty($targetRow)) {
                    $targetRow = offline_sync_set_target_identifier_if_empty($pdo, $table, $targetRow, $identifier);
                    $targetRow = offline_sync_update_event_target($pdo, $table, $row, $targetRow, $maps, $eventType);
                    list($targetPk, $targetRef) = offline_sync_target_result_from_row($pdo, $table, $targetRow);
                    offline_sync_mark_imported($pdo, $syncExportId, $payloadHash, $sync, $table, $targetPk, $targetRef);
                    offline_sync_register_mapping($table, $row, $sync, $targetPk, $targetRef, $maps, $noteNumbersForMiscari, false);
                } else {
                    offline_sync_register_existing_mapping($pdo, $table, $row, $sync, $existing, $maps, $noteNumbersForMiscari);
                }
                $stats['duplicates'][$table]++;
                if (offline_sync_event_updates_table($eventType, $table)) {
                    $stats['updated'][$table]++;
                }
                continue;
            }

            offline_sync_delete_imported_marker($pdo, $existing);
        }

        if ($table === 'discounturi_acordate') {
            $syncRef = isset($row['id_vanz_sync_ref']) ? (string)$row['id_vanz_sync_ref'] : '';
            if ($syncRef !== '' && !isset($maps['det_note_by_sync_id'][$syncRef])) {
                $importedDet = offline_sync_find_valid_imported_by_sync_id($pdo, $syncRef);
                if ($importedDet && (string)$importedDet['target_pk'] !== '') {
                    $maps['det_note_by_sync_id'][$syncRef] = (string)$importedDet['target_pk'];
                }
            }

            if ($syncRef !== '' && !isset($maps['det_note_by_sync_id'][$syncRef])) {
                $detSourcePk = offline_sync_source_pk_from_sync_id($syncRef, 'det_note');
                $existingDet = $detSourcePk !== ''
                    ? offline_sync_find_target_by_offline_source($pdo, 'det_note', $detSourcePk, $codLocatie, $clientId, $installationUuid)
                    : null;
                if ($existingDet) {
                    $maps['det_note_by_sync_id'][$syncRef] = offline_sync_target_pk_from_row($pdo, 'det_note', $existingDet);
                }
            }

            if ($syncRef !== '' && !isset($maps['det_note_by_sync_id'][$syncRef])) {
                throw new RuntimeException('Nu există mapare online pentru linia det_note ' . $syncRef . '.');
            }
        }

        if ($table === 'det_note') {
            offline_sync_hydrate_note_map($pdo, offline_sync_source_value($row, 'nr_bon'), $codLocatie, $clientId, $maps, $installationUuid);
        }

        list($targetPk, $targetRef) = offline_sync_insert_target_row($pdo, $table, $row, $maps, $codLocatie, $identifier);
        offline_sync_mark_imported($pdo, $syncExportId, $payloadHash, $sync, $table, $targetPk, $targetRef);
        offline_sync_register_mapping($table, $row, $sync, $targetPk, $targetRef, $maps, $noteNumbersForMiscari);

        $stats['inserted'][$table]++;
    }
}

function offline_sync_next_miscari_doc(PDO $pdo, $felDoc)
{
    $stmt = $pdo->prepare("SELECT MAX(nr_doc) AS ultim_doc FROM miscari WHERE fel_doc = :fel_doc");
    $stmt->execute(array(':fel_doc' => (string)$felDoc));
    $value = $stmt->fetchColumn();

    return ((int)$value) + 1;
}

function offline_sync_note_location(array $nota, $fallback)
{
    if (isset($nota['cod_locatie']) && (int)$nota['cod_locatie'] > 0) {
        return (int)$nota['cod_locatie'];
    }
    if (isset($nota['locatie']) && (int)$nota['locatie'] > 0) {
        return (int)$nota['locatie'];
    }

    return (int)$fallback;
}

function offline_sync_product_info(PDO $pdo, $codProdus)
{
    $stmt = $pdo->prepare("
        SELECT produse_servicii.pret_achizitie,
               produse_servicii.pret_cu_tva,
               produse_servicii.cota_tva,
               produse_servicii.nume,
               gestiuni.denumire_gestiune
        FROM produse_servicii
        INNER JOIN gestiuni ON gestiuni.id_gestiune = produse_servicii.id_gestiune
        WHERE produse_servicii.cod_produs = :cod_produs
        LIMIT 1
    ");
    $stmt->execute(array(':cod_produs' => $codProdus));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ? $row : null;
}

function offline_sync_delete_miscari_for_note(PDO $pdo, $nrBon, $codLocatie)
{
    $columns = offline_sync_get_columns($pdo, 'miscari');
    $where = "((fel_doc = 'BF' AND nr_doc = :nr_bon_doc) OR (fel_doc IN ('BC', 'BT') AND nr_nota = :nr_bon_nota))";
    $params = array(
        ':nr_bon_doc' => (string)$nrBon,
        ':nr_bon_nota' => (string)$nrBon,
    );

    if (isset($columns['cod_locatie'])) {
        $where .= " AND cod_locatie = :cod_locatie";
        $params[':cod_locatie'] = (int)$codLocatie;
    }

    $stmt = $pdo->prepare("DELETE FROM miscari WHERE {$where}");
    $stmt->execute($params);

    return (int)$stmt->rowCount();
}

function offline_sync_insert_generated_miscare(PDO $pdo, array $row, array &$identifierContext)
{
    if (!empty($identifierContext)) {
        $identifierContext['seq'] = isset($identifierContext['seq']) ? ((int)$identifierContext['seq'] + 1) : 1;
        $row['identificator_offline'] = offline_sync_identifier(
            isset($identifierContext['client_id']) ? (int)$identifierContext['client_id'] : 0,
            isset($identifierContext['cod_locatie']) ? (int)$identifierContext['cod_locatie'] : 0,
            'miscari',
            'generate_note_' . (string)($identifierContext['nr_bon'] ?? '') . '_' . (int)$identifierContext['seq']
        );
    }

    $columns = offline_sync_get_columns($pdo, 'miscari');
    $data = array();

    foreach ($row as $column => $value) {
        if (isset($columns[$column])) {
            $data[$column] = $value;
        }
    }

    $data = offline_sync_apply_miscari_insert_defaults($data, $row, $columns);

    if (empty($data)) {
        throw new RuntimeException('Nu exista coloane valide pentru miscari.');
    }

    $quotedColumns = array();
    $placeholders = array();
    $values = array();
    foreach ($data as $column => $value) {
        $quotedColumns[] = offline_sync_quote_identifier($column);
        $placeholders[] = '?';
        $values[] = $value;
    }

    $sql = 'INSERT INTO miscari (' . implode(', ', $quotedColumns) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
}

function offline_sync_generate_miscari_for_note(PDO $pdo, $nrBon, $fallbackCodLocatie, $clientId)
{
    $inserted = 0;

    $stmtNota = $pdo->prepare("SELECT * FROM note WHERE nrbon = :nrbon LIMIT 1");
    $stmtNota->execute(array(':nrbon' => $nrBon));
    $nota = $stmtNota->fetch(PDO::FETCH_ASSOC);
    if (!$nota) {
        throw new RuntimeException('Bonul importat nu exista pentru generarea miscarilor: ' . (string)$nrBon);
    }

    $dataBon = isset($nota['data_bon']) ? (string)$nota['data_bon'] : '';
    $oraBon = isset($nota['ora_bon']) ? (string)$nota['ora_bon'] : '';
    if ($dataBon === '' || $oraBon === '') {
        throw new RuntimeException('Bonul importat nu are data_bon sau ora_bon: ' . (string)$nrBon);
    }

    $nrBonString = (string)$nrBon;
    $codLocatie = offline_sync_note_location($nota, $fallbackCodLocatie);
    $nrRaportZ = isset($nota['nr_raport_z']) ? $nota['nr_raport_z'] : 0;
    $miscariIdentifierContext = array(
        'client_id' => (int)$clientId,
        'cod_locatie' => (int)$codLocatie,
        'nr_bon' => $nrBonString,
        'seq' => 0,
    );

    $miscStmt = $pdo->prepare("
        SELECT produse_servicii.pret_achizitie,
               produse_servicii.pret_cu_tva,
               produse_servicii.cota_tva,
               produse_servicii.nume,
               gestiuni.denumire_gestiune,
               det_note.cod_p,
               det_note.cantitate,
               det_note.pret_vanzare AS pret_vanzare_det
        FROM det_note
        INNER JOIN produse_servicii ON det_note.cod_p = produse_servicii.cod_produs
        INNER JOIN gestiuni ON produse_servicii.id_gestiune = gestiuni.id_gestiune
        WHERE det_note.nr_bon = :nr_bon
        ORDER BY det_note.id_vanz
    ");
    $miscStmt->execute(array(':nr_bon' => $nrBonString));

    $psCheckStmt = $pdo->prepare("
        SELECT produse_servicii.tip, gestiuni.denumire_gestiune
        FROM produse_servicii
        INNER JOIN gestiuni ON produse_servicii.id_gestiune = gestiuni.id_gestiune
        WHERE produse_servicii.cod_produs = :cod_produs
        LIMIT 1
    ");

    while ($row = $miscStmt->fetch(PDO::FETCH_ASSOC)) {
        $pretUnitarProdus = $row['pret_achizitie'];
        $pretVanzareProdus = $row['pret_cu_tva'];
        $numeProdus = $row['nume'];
        $cotaTvaProdus = $row['cota_tva'];
        $prod = $row['cod_p'];
        $qt = $row['cantitate'];
        $gest = $row['denumire_gestiune'];
        $esteCazSpecial = false;

        if (strtoupper((string)$gest) === 'BACSIS') {
            $esteCazSpecial = true;
        }

        if (!$esteCazSpecial) {
            try {
                $psCheckStmt->execute(array(':cod_produs' => $prod));
                $ps = $psCheckStmt->fetch(PDO::FETCH_ASSOC);
                if ($ps) {
                    $tipPS = strtolower(trim((string)($ps['tip'] ?? '')));
                    $gestPS = strtoupper(trim((string)($ps['denumire_gestiune'] ?? '')));
                    if ($tipPS === 'serviciu' || strpos($gestPS, 'SERVICII') !== false) {
                        $esteCazSpecial = true;
                    }
                }
            } catch (PDOException $e) {
            }
        }

        if ($esteCazSpecial && isset($row['pret_vanzare_det']) && $row['pret_vanzare_det'] !== null) {
            $pretVanzareProdus = (float)$row['pret_vanzare_det'];
            $pretUnitarProdus = 0;
        }

        if ($gest == 'PRODUSE FINITE') {
            $retetaStmt = $pdo->prepare("SELECT cod_mat, cant_folos FROM retete WHERE cod_p = :cod_p");
            $retetaStmt->execute(array(':cod_p' => $prod));

            $ultimBc = offline_sync_next_miscari_doc($pdo, 'BC');

            while ($rowReteta = $retetaStmt->fetch(PDO::FETCH_ASSOC)) {
                $cM = $rowReteta['cod_mat'];
                $cantFolosDeProdusFinit = $rowReteta['cant_folos'];
                $qtM = $rowReteta['cant_folos'] * $qt;
                $nomenclatorRow = offline_sync_product_info($pdo, $cM);

                if (!$nomenclatorRow) {
                    continue;
                }

                $pretUnitar = $nomenclatorRow['pret_achizitie'];
                $pretVanzare = $nomenclatorRow['pret_cu_tva'];
                $denumireGestiuneMaterial = $nomenclatorRow['denumire_gestiune'];
                $cotaTvaMaterial = $nomenclatorRow['cota_tva'];
                $numeMaterial = $nomenclatorRow['nume'];

                if ($denumireGestiuneMaterial == 'PRODUSE FINITE') {
                    $retetaSubStmt = $pdo->prepare("SELECT cod_mat, cant_folos FROM retete WHERE cod_p = :produs");
                    $retetaSubStmt->execute(array(':produs' => $cM));

                    while ($subRow = $retetaSubStmt->fetch(PDO::FETCH_ASSOC)) {
                        $subCodMat = $subRow['cod_mat'];
                        $subQt = $subRow['cant_folos'] * $qtM;
                        $subNomenclatorRow = offline_sync_product_info($pdo, $subCodMat);

                        if (!$subNomenclatorRow) {
                            continue;
                        }

                        $subPretUnitar = $subNomenclatorRow['pret_achizitie'];
                        $subPretVanzare = $subNomenclatorRow['pret_cu_tva'];
                        $subDenumireGestiune = $subNomenclatorRow['denumire_gestiune'];
                        $cotaTvaSubmaterial = $subNomenclatorRow['cota_tva'];
                        $numeSubmaterial = $subNomenclatorRow['nume'];

                        offline_sync_insert_generated_miscare($pdo, array(
                            'data' => $dataBon,
                            'cod_p' => $subCodMat,
                            'cantitate_misc' => $subQt,
                            'tip_miscare' => 'O',
                            'fel_doc' => 'BC',
                            'nr_doc' => $ultimBc,
                            'nr_nota' => $nrBonString,
                            'produs_obtinut' => $cM,
                            'pu' => $subPretUnitar,
                            'pret_vanzare' => $subPretVanzare,
                            'gestiune' => $subDenumireGestiune,
                            'denumire_produs' => $numeSubmaterial,
                            'cota_tva' => $cotaTvaSubmaterial,
                            'cod_locatie' => $codLocatie,
                            'ora_miscarii' => $oraBon,
                            'nr_raport_z' => $nrRaportZ,
                        ), $miscariIdentifierContext);
                        $inserted++;

                        if ($subDenumireGestiune == 'PRODUSE FINITE') {
                            $retetaSubSubStmt = $pdo->prepare("SELECT cod_mat, cant_folos FROM retete WHERE cod_p = :produs");
                            $retetaSubSubStmt->execute(array(':produs' => $subCodMat));

                            while ($subSubRow = $retetaSubSubStmt->fetch(PDO::FETCH_ASSOC)) {
                                $subSubCodMat = $subSubRow['cod_mat'];
                                $subSubQt = $subSubRow['cant_folos'] * $subQt;
                                $subSubNomenclatorRow = offline_sync_product_info($pdo, $subSubCodMat);

                                if (!$subSubNomenclatorRow) {
                                    continue;
                                }

                                offline_sync_insert_generated_miscare($pdo, array(
                                    'data' => $dataBon,
                                    'cod_p' => $subSubCodMat,
                                    'cantitate_misc' => $subSubQt,
                                    'tip_miscare' => 'O',
                                    'fel_doc' => 'BC',
                                    'nr_doc' => $ultimBc,
                                    'nr_nota' => $nrBonString,
                                    'produs_obtinut' => $subCodMat,
                                    'pu' => $subSubNomenclatorRow['pret_achizitie'],
                                    'pret_vanzare' => $subSubNomenclatorRow['pret_cu_tva'],
                                    'gestiune' => $subSubNomenclatorRow['denumire_gestiune'],
                                    'denumire_produs' => $subSubNomenclatorRow['nume'],
                                    'cota_tva' => $subSubNomenclatorRow['cota_tva'],
                                    'cod_locatie' => $codLocatie,
                                    'ora_miscarii' => $oraBon,
                                    'nr_raport_z' => $nrRaportZ,
                                ), $miscariIdentifierContext);
                                $inserted++;
                            }

                            $ultimBt = offline_sync_next_miscari_doc($pdo, 'BT');
                            offline_sync_insert_generated_miscare($pdo, array(
                                'data' => $dataBon,
                                'cod_p' => $subCodMat,
                                'cantitate_misc' => $subQt,
                                'tip_miscare' => 'I',
                                'fel_doc' => 'BT',
                                'nr_doc' => $ultimBt,
                                'nr_nota' => $nrBonString,
                                'pu' => $subPretUnitar,
                                'pret_vanzare' => $subPretVanzare,
                                'gestiune' => 'SEMIFABRICATE',
                                'denumire_produs' => $numeSubmaterial,
                                'cota_tva' => $cotaTvaSubmaterial,
                                'cod_locatie' => $codLocatie,
                                'ora_miscarii' => $oraBon,
                                'nr_raport_z' => $nrRaportZ,
                            ), $miscariIdentifierContext);
                            $inserted++;

                            $ultimBc = offline_sync_next_miscari_doc($pdo, 'BC');
                            offline_sync_insert_generated_miscare($pdo, array(
                                'data' => $dataBon,
                                'cod_p' => $subCodMat,
                                'cantitate_misc' => $subQt,
                                'tip_miscare' => 'O',
                                'fel_doc' => 'BC',
                                'nr_doc' => $ultimBc,
                                'nr_nota' => $nrBonString,
                                'produs_obtinut' => $cM,
                                'pu' => $subPretUnitar,
                                'pret_vanzare' => $subPretVanzare,
                                'gestiune' => 'SEMIFABRICATE',
                                'denumire_produs' => $numeSubmaterial,
                                'cota_tva' => $cotaTvaSubmaterial,
                                'cod_locatie' => $codLocatie,
                                'ora_miscarii' => $oraBon,
                                'nr_raport_z' => $nrRaportZ,
                            ), $miscariIdentifierContext);
                            $inserted++;
                        }
                    }

                    $ultimBt = offline_sync_next_miscari_doc($pdo, 'BT');
                    offline_sync_insert_generated_miscare($pdo, array(
                        'data' => $dataBon,
                        'cod_p' => $cM,
                        'cantitate_misc' => $cantFolosDeProdusFinit,
                        'tip_miscare' => 'I',
                        'fel_doc' => 'BT',
                        'nr_doc' => $ultimBt,
                        'nr_nota' => $nrBonString,
                        'pu' => $pretUnitar,
                        'pret_vanzare' => $pretVanzare,
                        'gestiune' => 'SEMIFABRICATE',
                        'denumire_produs' => $numeMaterial,
                        'cota_tva' => $cotaTvaMaterial,
                        'cod_locatie' => $codLocatie,
                        'ora_miscarii' => $oraBon,
                        'nr_raport_z' => $nrRaportZ,
                    ), $miscariIdentifierContext);
                    $inserted++;

                    $ultimBc = offline_sync_next_miscari_doc($pdo, 'BC');
                    offline_sync_insert_generated_miscare($pdo, array(
                        'data' => $dataBon,
                        'cod_p' => $cM,
                        'cantitate_misc' => $cantFolosDeProdusFinit,
                        'tip_miscare' => 'O',
                        'fel_doc' => 'BC',
                        'nr_doc' => $ultimBc,
                        'nr_nota' => $nrBonString,
                        'produs_obtinut' => $prod,
                        'pu' => $pretUnitar,
                        'pret_vanzare' => $pretVanzare,
                        'gestiune' => 'SEMIFABRICATE',
                        'denumire_produs' => $numeMaterial,
                        'cota_tva' => $cotaTvaMaterial,
                        'cod_locatie' => $codLocatie,
                        'ora_miscarii' => $oraBon,
                        'nr_raport_z' => $nrRaportZ,
                    ), $miscariIdentifierContext);
                    $inserted++;
                } else {
                    offline_sync_insert_generated_miscare($pdo, array(
                        'data' => $dataBon,
                        'cod_p' => $cM,
                        'cantitate_misc' => $qtM,
                        'tip_miscare' => 'O',
                        'fel_doc' => 'BC',
                        'nr_doc' => $ultimBc,
                        'nr_nota' => $nrBonString,
                        'produs_obtinut' => $prod,
                        'pu' => $pretUnitar,
                        'pret_vanzare' => $pretVanzare,
                        'gestiune' => $denumireGestiuneMaterial,
                        'denumire_produs' => $numeMaterial,
                        'cota_tva' => $cotaTvaMaterial,
                        'cod_locatie' => $codLocatie,
                        'ora_miscarii' => $oraBon,
                        'nr_raport_z' => $nrRaportZ,
                    ), $miscariIdentifierContext);
                    $inserted++;
                }
            }

            $ultimBt = offline_sync_next_miscari_doc($pdo, 'BT');
            offline_sync_insert_generated_miscare($pdo, array(
                'data' => $dataBon,
                'cod_p' => $prod,
                'cantitate_misc' => $qt,
                'tip_miscare' => 'I',
                'fel_doc' => 'BT',
                'nr_doc' => $ultimBt,
                'nr_nota' => $nrBonString,
                'pu' => $pretUnitarProdus,
                'pret_vanzare' => $pretVanzareProdus,
                'gestiune' => $gest,
                'denumire_produs' => $numeProdus,
                'cota_tva' => $cotaTvaProdus,
                'cod_locatie' => $codLocatie,
                'ora_miscarii' => $oraBon,
                'nr_raport_z' => $nrRaportZ,
            ), $miscariIdentifierContext);
            $inserted++;

            offline_sync_insert_generated_miscare($pdo, array(
                'data' => $dataBon,
                'cod_p' => $prod,
                'cantitate_misc' => $qt,
                'tip_miscare' => 'O',
                'fel_doc' => 'BF',
                'nr_doc' => $nrBonString,
                'pu' => $pretUnitarProdus,
                'pret_vanzare' => $pretVanzareProdus,
                'gestiune' => $gest,
                'denumire_produs' => $numeProdus,
                'cota_tva' => $cotaTvaProdus,
                'cod_locatie' => $codLocatie,
                'ora_miscarii' => $oraBon,
                'nr_raport_z' => $nrRaportZ,
            ), $miscariIdentifierContext);
            $inserted++;
        } else {
            offline_sync_insert_generated_miscare($pdo, array(
                'data' => $dataBon,
                'cod_p' => $prod,
                'cantitate_misc' => $qt,
                'tip_miscare' => 'O',
                'fel_doc' => 'BF',
                'nr_doc' => $nrBonString,
                'pu' => $pretUnitarProdus,
                'pret_vanzare' => $pretVanzareProdus,
                'gestiune' => $gest,
                'denumire_produs' => $numeProdus,
                'cota_tva' => $cotaTvaProdus,
                'cod_locatie' => $codLocatie,
                'ora_miscarii' => $oraBon,
                'nr_raport_z' => $nrRaportZ,
            ), $miscariIdentifierContext);
            $inserted++;
        }
    }

    $curatStmt = $pdo->prepare("
        DELETE FROM miscari
        WHERE produs_obtinut != 0
          AND gestiune = 'PRODUSE FINITE'
          AND fel_doc = 'BC'
          AND nr_nota = :nr_bon
    ");
    $curatStmt->execute(array(':nr_bon' => $nrBonString));

    return $inserted;
}

function offline_sync_generate_miscari_for_imported_notes(PDO $pdo, array $noteNumbers, $codLocatie, $clientId, array &$stats)
{
    foreach (array_values(array_unique($noteNumbers)) as $nrBon) {
        if ((string)$nrBon === '') {
            continue;
        }
        $stats['duplicates']['miscari'] += offline_sync_delete_miscari_for_note($pdo, $nrBon, $codLocatie);
        $stats['inserted']['miscari'] += offline_sync_generate_miscari_for_note($pdo, $nrBon, $codLocatie, $clientId);
    }
}

function offline_sync_update_miscari_report_for_z(PDO $pdo, array $payload, array &$maps, $clientId, array &$stats)
{
    if ((string)($payload['event_type'] ?? '') !== 'z_closed' || !offline_sync_table_exists($pdo, 'miscari')) {
        return;
    }

    $columns = offline_sync_get_columns($pdo, 'miscari');
    if (!isset($columns['nr_raport_z'])) {
        return;
    }

    $codLocatie = (int)$payload['cod_locatie'];
    $installationUuid = (string)($payload['installation_uuid'] ?? '');
    foreach ($payload['note'] as $sourceNote) {
        $sourceNrBon = offline_sync_source_value($sourceNote, 'nrbon');
        $sourceNrRaport = offline_sync_source_value($sourceNote, 'nr_raport_z');
        if ($sourceNrBon === '' || (int)$sourceNrRaport <= 0) {
            continue;
        }

        offline_sync_hydrate_note_map($pdo, $sourceNrBon, $codLocatie, $clientId, $maps, $installationUuid);
        $targetNrBon = offline_sync_require_map_value($maps['note_by_nr'], $sourceNrBon, 'Nu există mapare online pentru nota offline');
        $targetNrRaport = offline_sync_require_map_value($maps['rapoarte_z_by_nr'], $sourceNrRaport, 'Nu există mapare online pentru raportul Z offline');

        $where = "((fel_doc = 'BF' AND nr_doc = ?) OR (fel_doc IN ('BC', 'BT') AND nr_nota = ?))";
        $values = array($targetNrRaport, $targetNrBon, $targetNrBon);
        if (isset($columns['cod_locatie'])) {
            $where .= ' AND cod_locatie = ?';
            $values[] = $codLocatie;
        }
        $where .= ' AND COALESCE(nr_raport_z, 0) <> ?';
        $values[] = $targetNrRaport;

        $stmt = $pdo->prepare('UPDATE miscari SET nr_raport_z = ? WHERE ' . $where);
        $stmt->execute($values);
        $stats['updated']['miscari'] += (int)$stmt->rowCount();
    }
}

function offline_sync_log_import(PDO $pdo, array $payload, array $client, array $received, array $stats, $status, $payloadHash, array $errors)
{
    $utilizator = isset($payload['utilizator_sync']) && is_array($payload['utilizator_sync']) ? $payload['utilizator_sync'] : array();
    $duplicateTotal = array_sum($stats['duplicates']);

    $stmt = $pdo->prepare("
        INSERT INTO offline_sync_import_logs
            (sync_export_id, client_id, data_primire, cod_locatie, utilizator_sync_id, utilizator_sync_nume,
             note_primite, det_note_primite, inchideri_primite, rapoarte_z_primite, miscari_primite, discounturi_primite,
             note_insert, det_note_insert, inchideri_insert, rapoarte_z_insert, miscari_insert, discounturi_insert,
             note_duplicate, det_note_duplicate, inchideri_duplicate, rapoarte_z_duplicate, miscari_duplicate, discounturi_duplicate,
             note_updated, det_note_updated, inchideri_updated, rapoarte_z_updated, miscari_updated, discounturi_updated,
             duplicate_total, status, payload_hash, erori, request_ip, event_uuid, event_type, installation_uuid)
        VALUES
            (:sync_export_id, :client_id, NOW(), :cod_locatie, :utilizator_sync_id, :utilizator_sync_nume,
             :note_primite, :det_note_primite, :inchideri_primite, :rapoarte_z_primite, :miscari_primite, :discounturi_primite,
             :note_insert, :det_note_insert, :inchideri_insert, :rapoarte_z_insert, :miscari_insert, :discounturi_insert,
             :note_duplicate, :det_note_duplicate, :inchideri_duplicate, :rapoarte_z_duplicate, :miscari_duplicate, :discounturi_duplicate,
             :note_updated, :det_note_updated, :inchideri_updated, :rapoarte_z_updated, :miscari_updated, :discounturi_updated,
             :duplicate_total, :status, :payload_hash, :erori, :request_ip, :event_uuid, :event_type, :installation_uuid)
    ");

    $stmt->execute(array(
        ':sync_export_id' => isset($payload['sync_export_id']) ? (string)$payload['sync_export_id'] : '',
        ':client_id' => isset($client['id_client']) ? (int)$client['id_client'] : 0,
        ':cod_locatie' => isset($payload['cod_locatie']) ? (int)$payload['cod_locatie'] : 0,
        ':utilizator_sync_id' => isset($utilizator['id']) ? (int)$utilizator['id'] : 0,
        ':utilizator_sync_nume' => isset($utilizator['nume']) ? (string)$utilizator['nume'] : '',
        ':note_primite' => $received['note'],
        ':det_note_primite' => $received['det_note'],
        ':inchideri_primite' => $received['inchideri_r_12'],
        ':rapoarte_z_primite' => $received['rapoarte_z'],
        ':miscari_primite' => isset($received['miscari']) ? (int)$received['miscari'] : 0,
        ':discounturi_primite' => $received['discounturi_acordate'],
        ':note_insert' => $stats['inserted']['note'],
        ':det_note_insert' => $stats['inserted']['det_note'],
        ':inchideri_insert' => $stats['inserted']['inchideri_r_12'],
        ':rapoarte_z_insert' => $stats['inserted']['rapoarte_z'],
        ':miscari_insert' => $stats['inserted']['miscari'],
        ':discounturi_insert' => $stats['inserted']['discounturi_acordate'],
        ':note_duplicate' => $stats['duplicates']['note'],
        ':det_note_duplicate' => $stats['duplicates']['det_note'],
        ':inchideri_duplicate' => $stats['duplicates']['inchideri_r_12'],
        ':rapoarte_z_duplicate' => $stats['duplicates']['rapoarte_z'],
        ':miscari_duplicate' => $stats['duplicates']['miscari'],
        ':discounturi_duplicate' => $stats['duplicates']['discounturi_acordate'],
        ':note_updated' => $stats['updated']['note'],
        ':det_note_updated' => $stats['updated']['det_note'],
        ':inchideri_updated' => $stats['updated']['inchideri_r_12'],
        ':rapoarte_z_updated' => $stats['updated']['rapoarte_z'],
        ':miscari_updated' => $stats['updated']['miscari'],
        ':discounturi_updated' => $stats['updated']['discounturi_acordate'],
        ':duplicate_total' => $duplicateTotal,
        ':status' => (string)$status,
        ':payload_hash' => (string)$payloadHash,
        ':erori' => empty($errors) ? null : implode("\n", $errors),
        ':request_ip' => isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '',
        ':event_uuid' => isset($payload['event_uuid']) ? (string)$payload['event_uuid'] : '',
        ':event_type' => isset($payload['event_type']) ? (string)$payload['event_type'] : '',
        ':installation_uuid' => isset($payload['installation_uuid']) ? (string)$payload['installation_uuid'] : '',
    ));
}

function offline_sync_success_response(array $payload, array $stats, $debugDb = null)
{
    $response = array(
        'status' => 'success',
        'sync_export_id' => (string)$payload['sync_export_id'],
        'message' => 'Pachetul offline a fost importat.',
        'inserted' => $stats['inserted'],
        'duplicates' => $stats['duplicates'],
        'updated' => $stats['updated'],
        'acknowledgement' => 'processed',
    );

    if (isset($payload['event_uuid'])) {
        $response['event_uuid'] = (string)$payload['event_uuid'];
        $response['event_type'] = (string)($payload['event_type'] ?? '');
    }

    if (is_array($debugDb)) {
        $response['debug_db'] = $debugDb;
    }

    return $response;
}

if (defined('CASA_INVOICE_LIBRARY_ONLY') && CASA_INVOICE_LIBRARY_ONLY) { return; }

$pdo = null;
$client = array();
$payload = array();
$received = array(
    'note' => 0,
    'det_note' => 0,
    'inchideri_r_12' => 0,
    'rapoarte_z' => 0,
    'discounturi_acordate' => 0,
);
$stats = offline_sync_import_stats();
$payloadHash = '';

try {
    $method = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '');
    if ($method === 'OPTIONS') {
        offline_sync_send_no_content();
    }

    if ($method === 'GET' && offline_sync_is_health_request()) {
        offline_sync_send_json(200, offline_sync_health_payload());
    }

    if ($method === 'GET') {
        $centralPdo = offline_sync_connect_central();
        list($pdo, $client) = offline_sync_connect_client($centralPdo);
        offline_products_send_export($pdo, $client);
    }

    if (strtoupper(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '') !== 'POST') {
        throw new OfflineSyncHttpException(405, 'Metoda permisă este POST.');
    }

    offline_sync_validate_content_type();

    $raw = offline_sync_read_body();
    $payloadHash = hash('sha256', $raw);
    $payload = offline_sync_decode_payload($raw);
    $payload = offline_sync_normalize_received_payload($payload);
    offline_sync_validate_payload($payload);
    $received = offline_sync_received_counts($payload);

    $centralPdo = offline_sync_connect_central();
    list($pdo, $client) = offline_sync_connect_client($centralPdo);

    offline_sync_ensure_support_tables($pdo);
    offline_sync_ensure_target_schema($pdo);
    $existingEventResponse = offline_sync_event_existing_response($pdo, $payload, $payloadHash);
    if (is_array($existingEventResponse)) {
        offline_sync_send_json(200, $existingEventResponse);
    }
    offline_sync_preflight_schema($pdo, $payload);
    $clientId = offline_sync_client_id($client);
    offline_sync_validate_relations($pdo, $payload, $clientId);

    $pdo->beginTransaction();
    offline_sync_event_begin($pdo, $payload, $payloadHash);

    $syncMaps = offline_sync_new_maps();
    $noteNumbersForMiscari = array();
    foreach (array('rapoarte_z', 'inchideri_r_12', 'note', 'det_note', 'discounturi_acordate') as $table) {
        offline_sync_import_table($pdo, $payload, $table, $payloadHash, $stats, $syncMaps, $noteNumbersForMiscari, $clientId);
    }
    if (!offline_sync_is_v2_event($payload) || (string)($payload['event_type'] ?? '') === 'sale_finalized') {
        offline_sync_generate_miscari_for_imported_notes($pdo, $noteNumbersForMiscari, (int)$payload['cod_locatie'], $clientId, $stats);
    }
    offline_sync_update_miscari_report_for_z($pdo, $payload, $syncMaps, $clientId, $stats);

    $successResponse = offline_sync_success_response($payload, $stats, offline_sync_db_debug_payload($client, $pdo));
    offline_sync_log_import($pdo, $payload, $client, $received, $stats, 'success', $payloadHash, array());
    offline_sync_event_complete($pdo, $payload, $successResponse);
    $pdo->commit();

    offline_sync_send_json(201, $successResponse);
} catch (OfflineSyncHttpException $e) {
    $errors = $e->getErrors();
    if (empty($errors)) {
        $errors = array($e->getMessage());
    }

    try {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($pdo instanceof PDO) {
            offline_sync_ensure_support_tables($pdo);
            offline_sync_log_import($pdo, $payload, $client, $received, $stats, 'error', $payloadHash, $errors);
        }
    } catch (Throwable $logError) {
        error_log('Eroare log offline sync online: ' . $logError->getMessage());
    }

    $errorResponse = array(
        'status' => 'error',
        'sync_export_id' => isset($payload['sync_export_id']) ? (string)$payload['sync_export_id'] : '',
        'message' => $e->getMessage(),
        'errors' => $errors,
    );
    $debugDb = offline_sync_db_debug_payload($client, $pdo);
    if (is_array($debugDb)) {
        $errorResponse['debug_db'] = $debugDb;
    }

    offline_sync_send_json($e->getHttpCode(), $errorResponse);
} catch (Throwable $e) {
    $errors = array($e->getMessage());

    try {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($pdo instanceof PDO) {
            offline_sync_ensure_support_tables($pdo);
            offline_sync_log_import($pdo, $payload, $client, $received, $stats, 'error', $payloadHash, $errors);
        }
    } catch (Throwable $logError) {
        error_log('Eroare log offline sync online: ' . $logError->getMessage());
    }

    $errorResponse = array(
        'status' => 'error',
        'sync_export_id' => isset($payload['sync_export_id']) ? (string)$payload['sync_export_id'] : '',
        'message' => 'Importul offline a eșuat.',
        'errors' => $errors,
    );
    $debugDb = offline_sync_db_debug_payload($client, $pdo);
    if (is_array($debugDb)) {
        $errorResponse['debug_db'] = $debugDb;
    }

    offline_sync_send_json(500, $errorResponse);
}
