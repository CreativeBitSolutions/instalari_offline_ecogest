<?php
// sincronizare_online.php

session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/export_vanzari_offline_lib.php';

date_default_timezone_set('Europe/Bucharest');
if ((int)offline_export_app_config_value('client_id', 0) === 21) {
    sync_local_json(409, [
        'status' => 'error',
        'message' => 'Bestmixt transmite operațiunile numai prin coada protejată. Folosiți butonul Trimite operațiunile din pagina de conectare.',
    ]);
}

function sync_local_json($code, array $data)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function sync_local_http_post_json($url, array $payload, $apiKey)
{
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($body === false) {
        return [
            'ok' => false,
            'http_code' => 0,
            'body' => '',
            'error' => 'Payload-ul de sincronizare nu a putut fi serializat.',
        ];
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json; charset=utf-8',
                'Accept: application/json',
                'X-Sync-Api-Key: ' . $apiKey,
                'X-Api-Key: ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => $body,
        ]);
        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = $errno ? curl_error($ch) : '';
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'ok' => $errno === 0 && $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'body' => $response === false ? '' : (string)$response,
            'error' => $error,
        ];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'ignore_errors' => true,
            'timeout' => 60,
            'header' => implode("\r\n", [
                'Content-Type: application/json; charset=utf-8',
                'Accept: application/json',
                'X-Sync-Api-Key: ' . $apiKey,
                'X-Api-Key: ' . $apiKey,
            ]),
            'content' => $body,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    $headers = isset($http_response_header) && is_array($http_response_header) ? $http_response_header : [];
    $status = $headers ? $headers[0] : '';
    preg_match('/\s(\d{3})\s/', $status, $m);
    $httpCode = isset($m[1]) ? (int)$m[1] : 0;

    return [
        'ok' => $response !== false && $httpCode >= 200 && $httpCode < 300,
        'http_code' => $httpCode,
        'body' => $response === false ? '' : (string)$response,
        'error' => $response === false ? 'Conexiunea HTTP a esuat.' : '',
    ];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sync_local_json(405, ['status' => 'error', 'message' => 'Metoda HTTP nu este permisa.']);
}

if (!isset($_SESSION['adminloggedin'])) {
    sync_local_json(401, ['status' => 'error', 'message' => 'Sesiunea locala nu este activa.']);
}

$syncUrl = trim((string)offline_export_app_config_value('sync_import_url', ''));
$syncKey = trim((string)offline_export_app_config_value('sync_api_key', ''));
$profile = trim((string)offline_export_app_config_value('sync_profile', 'auto'));

if ($syncUrl === '') {
    sync_local_json(500, [
        'status' => 'error',
        'message' => 'Lipseste sync_import_url din configurarea externa.',
    ]);
}

if ($syncKey === '') {
    sync_local_json(500, [
        'status' => 'error',
        'message' => 'Lipseste sync_api_key din configurarea externa.',
    ]);
}

$dataStart = $_POST['data_start'] ?? date('Y-m-01');
$dataEnd = $_POST['data_end'] ?? date('Y-m-d');

try {
    $exportFile = offline_export_build($pdo, $dataStart, $dataEnd, 'xml');
} catch (Throwable $e) {
    sync_local_json(400, ['status' => 'error', 'message' => $e->getMessage()]);
}

$payload = [
    'api_key' => $syncKey,
    'profile' => $profile !== '' ? $profile : 'auto',
    'format' => 'xml',
    'filename' => $exportFile['filename'],
    'client_id' => (int)$exportFile['client_id'],
    'cod_locatie' => (int)$exportFile['cod_locatie'],
    'installation_uuid' => (string)$exportFile['installation_uuid'],
    'data_start' => $exportFile['data_start'],
    'data_end' => $exportFile['data_end'],
    'content' => $exportFile['content'],
];

$remote = sync_local_http_post_json($syncUrl, $payload, $syncKey);
$remoteJson = json_decode($remote['body'], true);

if (!$remote['ok']) {
    sync_local_json(502, [
        'status' => 'error',
        'message' => 'Importul online nu a fost confirmat.',
        'http_code' => $remote['http_code'],
        'transport_error' => $remote['error'],
        'remote_response' => is_array($remoteJson) ? $remoteJson : substr($remote['body'], 0, 600),
    ]);
}

if (is_array($remoteJson) && isset($remoteJson['sequence_state']) && is_array($remoteJson['sequence_state'])) {
    offline_sequence_apply_online_state($pdo, $remoteJson['sequence_state'], (int)$exportFile['cod_locatie']);
}

sync_local_json(200, [
    'status' => 'ok',
    'message' => 'Sincronizarea a fost finalizata.',
    'export' => [
        'filename' => $exportFile['filename'],
        'data_start' => $exportFile['data_start'],
        'data_end' => $exportFile['data_end'],
        'client_id' => (int)$exportFile['client_id'],
        'cod_locatie' => (int)$exportFile['cod_locatie'],
    ],
    'remote' => is_array($remoteJson) ? $remoteJson : $remote['body'],
]);
