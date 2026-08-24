<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

$externalConfig = __DIR__ . '/offline_external_config.php';
$restaurantLocalConfig = __DIR__ . '/offline_config.local.php';
if (is_file($externalConfig)) {
    require_once $externalConfig;
} elseif (is_file($restaurantLocalConfig)) {
    $restaurantConfig = require $restaurantLocalConfig;
    if (!is_array($restaurantConfig)) {
        http_response_code(500);
        echo json_encode(array('ok' => false, 'status' => 'configuration_invalid'));
        exit;
    }
} else {
    http_response_code(500);
    echo json_encode(array('ok' => false, 'status' => 'configuration_missing'));
    exit;
}

require_once __DIR__ . '/offline_license_lib.php';

if (strtoupper((string)(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET')) !== 'POST') {
    http_response_code(405);
    echo json_encode(array('ok' => false, 'status' => 'method_not_allowed'));
    exit;
}

$remoteAddress = (string)(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
if (!in_array($remoteAddress, array('127.0.0.1', '::1', ''), true)) {
    http_response_code(403);
    echo json_encode(array('ok' => false, 'status' => 'local_request_required'));
    exit;
}

try {
    echo json_encode(offline_license_background_refresh(), offline_license_json_flags());
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(array(
        'ok' => false,
        'status' => 'error',
        'message' => 'Verificarea automata a licentei nu a putut fi finalizata.',
    ), offline_license_json_flags());
}

