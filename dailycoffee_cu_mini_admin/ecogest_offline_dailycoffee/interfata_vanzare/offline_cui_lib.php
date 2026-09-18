<?php

function offline_cui_normalize($value)
{
    $value = strtoupper(trim((string)$value));
    $hasRo = strpos($value, 'RO') === 0;
    $digits = substr((string)preg_replace('/\D+/', '', $value), 0, 10);
    return $digits === '' ? '' : ($hasRo ? 'RO' : '') . $digits;
}

function offline_cui_digits_local($value)
{
    return substr((string)preg_replace('/\D+/', '', (string)$value), 0, 10);
}

function offline_cui_is_valid_local($value)
{
    $digits = offline_cui_digits_local($value);
    $length = strlen($digits);
    if ($length < 2 || $length > 10) {
        return false;
    }
    $controlDigit = (int)substr($digits, -1);
    $base = str_pad(substr($digits, 0, -1), 9, '0', STR_PAD_LEFT);
    $weights = '753217532';
    $sum = 0;
    for ($index = 0; $index < 9; $index++) {
        $sum += ((int)$base[$index]) * ((int)$weights[$index]);
    }
    $calculated = ($sum * 10) % 11;
    return ($calculated === 10 ? 0 : $calculated) === $controlDigit;
}

function offline_cui_config(array $config)
{
    $products = isset($config['online_products_sync']) && is_array($config['online_products_sync'])
        ? $config['online_products_sync']
        : array();
    $sales = isset($config['offline_sales_sync']) && is_array($config['offline_sales_sync'])
        ? $config['offline_sales_sync']
        : array();
    $apiRoot = (string)($config['api_root_absolute'] ?? $config['offline_api_path'] ?? '');
    $caBundlePath = trim((string)($config['ca_bundle_path'] ?? ''));
    if ($caBundlePath === '' && $apiRoot !== '') {
        $caBundlePath = rtrim($apiRoot, "\\/") . DIRECTORY_SEPARATOR . 'certificates' . DIRECTORY_SEPARATOR . 'cacert.pem';
    }
    return array(
        'url' => trim((string)($config['company_lookup_url'] ?? '')),
        'client_id' => (int)($config['sync_client_id'] ?? $config['client_id'] ?? 0),
        'api_key' => trim((string)($config['sync_api_key'] ?? $sales['api_key'] ?? $products['api_key'] ?? '')),
        'timeout' => max(5, (int)($config['company_lookup_timeout_seconds'] ?? 20)),
        'ca_bundle_path' => $caBundlePath,
    );
}

function offline_cui_http_post($url, $apiKey, array $payload, $timeout, $caBundlePath = '')
{
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($body)) {
        return array('status' => 0, 'body' => '', 'error' => 'Cererea CUI nu a putut fi codificata.');
    }
    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        $curlOptions = array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => min(10, (int)$timeout),
            CURLOPT_TIMEOUT => (int)$timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json',
                'Content-Type: application/json; charset=utf-8',
                'X-Api-Key: ' . $apiKey,
            ),
        );
        if ($caBundlePath !== '' && is_file($caBundlePath)) {
            $curlOptions[CURLOPT_CAINFO] = $caBundlePath;
        }
        curl_setopt_array($curl, $curlOptions);
        $response = curl_exec($curl);
        $error = curl_error($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        return array('status' => $status, 'body' => is_string($response) ? $response : '', 'error' => $error);
    }
    return array('status' => 0, 'body' => '', 'error' => 'Extensia cURL nu este activa in aplicatie.');
}

function offline_cui_remote_lookup(array $config, $cui)
{
    $settings = offline_cui_config($config);
    if ($settings['url'] === '' || $settings['api_key'] === '' || $settings['client_id'] <= 0) {
        return array(
            'ok' => false,
            'code' => 'configuration_invalid',
            'message' => 'Configurarea verificarii CUI este incompleta.',
            'manual_allowed' => true,
        );
    }
    $http = offline_cui_http_post($settings['url'], $settings['api_key'], array(
        'client_id' => $settings['client_id'],
        'cui' => offline_cui_digits_local($cui),
    ), $settings['timeout'], $settings['ca_bundle_path']);
    if ($http['body'] === '') {
        return array(
            'ok' => false,
            'code' => 'connection_failed',
            'message' => $http['error'] !== '' ? $http['error'] : 'Serviciul online nu a raspuns.',
            'manual_allowed' => true,
            'http_status' => $http['status'],
        );
    }
    $response = json_decode($http['body'], true);
    if (!is_array($response)) {
        return array(
            'ok' => false,
            'code' => 'invalid_response',
            'message' => 'Serviciul online a trimis un raspuns invalid.',
            'manual_allowed' => true,
            'http_status' => $http['status'],
        );
    }
    $response['http_status'] = $http['status'];
    if (empty($response['ok'])) {
        $response['manual_allowed'] = !empty($response['manual_allowed'])
            || $http['status'] === 0
            || $http['status'] >= 500;
    }
    return $response;
}
