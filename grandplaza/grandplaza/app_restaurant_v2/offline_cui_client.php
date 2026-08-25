<?php

$restaurantConfig = require __DIR__ . '/offline_config.local.php';
if (!is_array($restaurantConfig)) {
    $restaurantConfig = array();
}
require_once __DIR__ . '/offline_cui_lib.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function restaurant_offline_cui_response($status, array $payload)
{
    http_response_code((int)$status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    restaurant_offline_cui_response(405, array('success' => false, 'message' => 'Metoda nepermisa.'));
}
if (empty($_SESSION['admin_id'])) {
    restaurant_offline_cui_response(401, array('success' => false, 'message' => 'Sesiunea operatorului a expirat.'));
}
$csrf = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
if (empty($_SESSION['offline_cui_csrf']) || !hash_equals((string)$_SESSION['offline_cui_csrf'], $csrf)) {
    restaurant_offline_cui_response(403, array('success' => false, 'message' => 'Cererea de verificare nu este valida.'));
}

$action = trim((string)($_POST['action'] ?? ''));
$cui = offline_cui_normalize($_POST['cui'] ?? '');
if ($action === 'clear') {
    $_SESSION['cif_client'] = '';
    $_SESSION['cif_client_verified_online'] = 0;
    unset($_SESSION['cif_client_company'], $_SESSION['offline_cui_lookup']);
    restaurant_offline_cui_response(200, array('success' => true, 'cif_client' => '', 'verified_online' => false));
}
if ($cui === '' || !offline_cui_is_valid_local($cui)) {
    restaurant_offline_cui_response(422, array('success' => false, 'code' => 'invalid_cui', 'message' => 'CUI-ul introdus nu este valid.'));
}

if ($action === 'lookup') {
    $result = offline_cui_remote_lookup($restaurantConfig, $cui);
    if (empty($result['ok']) || empty($result['data']) || !is_array($result['data'])) {
        restaurant_offline_cui_response(
            !empty($result['manual_allowed']) ? 503 : 422,
            array(
                'success' => false,
                'code' => (string)($result['code'] ?? 'lookup_failed'),
                'message' => (string)($result['message'] ?? 'CUI-ul nu a putut fi verificat.'),
                'manual_allowed' => !empty($result['manual_allowed']),
            )
        );
    }
    $data = $result['data'];
    $confirmed = offline_cui_normalize($data['cui_formatat'] ?? $data['cod_fiscal'] ?? $cui);
    if (offline_cui_digits_local($confirmed) !== offline_cui_digits_local($cui)) {
        restaurant_offline_cui_response(502, array('success' => false, 'message' => 'Datele firmei nu confirma CUI-ul introdus.', 'manual_allowed' => true));
    }
    $token = bin2hex(random_bytes(24));
    $_SESSION['offline_cui_lookup'] = array(
        'token' => $token,
        'cui' => $confirmed,
        'data' => $data,
        'expires_at' => time() + 600,
    );
    restaurant_offline_cui_response(200, array(
        'success' => true,
        'code' => 'company_found',
        'cif_client' => $confirmed,
        'lookup_token' => $token,
        'data' => $data,
    ));
}

if ($action === 'save_verified') {
    $lookup = isset($_SESSION['offline_cui_lookup']) && is_array($_SESSION['offline_cui_lookup'])
        ? $_SESSION['offline_cui_lookup']
        : array();
    $token = isset($_POST['lookup_token']) ? (string)$_POST['lookup_token'] : '';
    $validLookup = !empty($lookup['token'])
        && $token !== ''
        && hash_equals((string)$lookup['token'], $token)
        && (int)($lookup['expires_at'] ?? 0) >= time()
        && offline_cui_digits_local($lookup['cui'] ?? '') === offline_cui_digits_local($cui);
    if (!$validLookup) {
        restaurant_offline_cui_response(409, array('success' => false, 'message' => 'Verificarea firmei a expirat. Verificati din nou CUI-ul.'));
    }
    $savedCui = offline_cui_normalize($lookup['cui']);
    $_SESSION['cif_client'] = $savedCui;
    $_SESSION['cif_client_verified_online'] = 1;
    $_SESSION['cif_client_company'] = $lookup['data'];
    unset($_SESSION['offline_cui_lookup']);
    restaurant_offline_cui_response(200, array('success' => true, 'cif_client' => $savedCui, 'verified_online' => true));
}

if ($action === 'save_manual') {
    $_SESSION['cif_client'] = $cui;
    $_SESSION['cif_client_verified_online'] = 0;
    unset($_SESSION['cif_client_company'], $_SESSION['offline_cui_lookup']);
    restaurant_offline_cui_response(200, array('success' => true, 'cif_client' => $cui, 'verified_online' => false));
}

restaurant_offline_cui_response(400, array('success' => false, 'message' => 'Actiunea solicitata nu este valida.'));

