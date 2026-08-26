<?php

require_once __DIR__ . '/offline_external_config.php';
require_once __DIR__ . '/offline_license_lib.php';

function offline_stock_online_url(): string
{
    $config = offline_config_all();
    $url = trim((string)($config['stock_check_url'] ?? ''));
    if ($url !== '') {
        return $url;
    }

    $syncUrl = trim((string)($config['sync_import_url'] ?? ''));
    if ($syncUrl === '') {
        return '';
    }
    return rtrim(str_replace('api_import_operatiuni_offline.php', '', $syncUrl), '/')
        . '/api_verificare_stoc_online.php';
}

function offline_stock_online_request(string $action, array $parameters = array()): array
{
    $config = offline_config_all();
    $licenseConfig = offline_license_config();
    $url = offline_stock_online_url();
    $clientId = (int)($config['sync_client_id'] ?? $config['client_id'] ?? 0);
    $location = (int)($_SESSION['cod_locatie'] ?? $config['cod_locatie_default'] ?? 0);
    $apiKey = trim((string)$licenseConfig['api_key']);
    $installationUuid = trim((string)$licenseConfig['installation_uuid']);

    if ($url === '' || $clientId <= 0 || $location <= 0 || $apiKey === '' || $installationUuid === '') {
        return array(
            'ok' => false,
            'code' => 'configuration_invalid',
            'message' => 'Configurarea verificarii stocului online este incompleta.',
            'http_status' => 500,
        );
    }

    $licenseStatus = offline_license_status(false);
    if (empty($licenseStatus['valid'])) {
        return array(
            'ok' => false,
            'code' => 'local_license_invalid',
            'message' => 'Licenta offline locala nu este valida. Verificati licenta online.',
            'http_status' => 403,
        );
    }

    $identity = offline_license_hardware_identity(false);
    $payload = array_merge($parameters, array(
        'action' => $action,
        'client_id' => $clientId,
        'cod_locatie' => $location,
        'hardware_serial' => (string)($identity['serial'] ?? ''),
        'installation_uuid' => $installationUuid,
        'app_name' => (string)$licenseConfig['app_name'],
    ));
    $body = json_encode($payload, offline_license_json_flags());
    if (!is_string($body)) {
        return array(
            'ok' => false,
            'code' => 'request_encode_failed',
            'message' => 'Cererea de stoc nu a putut fi pregatita.',
            'http_status' => 500,
        );
    }

    $http = offline_license_http_post($url, $apiKey, $body, 20);
    if ($http['body'] === '') {
        return array(
            'ok' => false,
            'code' => 'connection_failed',
            'message' => $http['error'] !== ''
                ? 'Stocul online nu a putut fi accesat: ' . $http['error']
                : 'Serverul online nu a raspuns.',
            'http_status' => 503,
        );
    }

    $response = json_decode((string)$http['body'], true);
    if (!is_array($response)) {
        return array(
            'ok' => false,
            'code' => 'invalid_response',
            'message' => 'Serverul online a trimis un raspuns invalid.',
            'http_status' => 502,
        );
    }

    $response['http_status'] = (int)$http['status'];
    return $response;
}

function offline_stock_online_send(string $action, array $parameters = array()): void
{
    $response = offline_stock_online_request($action, $parameters);
    $status = (int)($response['http_status'] ?? 200);
    if ($status < 100 || $status > 599) {
        $status = empty($response['ok']) ? 500 : 200;
    }

    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('X-Content-Type-Options: nosniff');
    unset($response['http_status']);
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

