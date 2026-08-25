<?php
$config = [
    'driver' => 'sqlite',
    'live_id' => 12,
    'client_id' => 25,
    'cod_locatie' => 1,
    'installation_uuid' => 'grandplaza-c25-l1',
    'installation_identity_format' => 'restaurant',
    'app_name' => 'App Restaurant Offline Grand Plaza',
    'company_lookup_url' => 'https://agecs.agecs.in/sincronizare_online_app_vanzare/api_verificare_cui_offline.php',
    'company_lookup_timeout_seconds' => 20,
    'offline_api_path' => dirname(dirname(__DIR__)) . '/api_offline_grandplaza',
    'ca_bundle_path' => dirname(dirname(__DIR__)) . '/api_offline_grandplaza/certificates/cacert.pem',
    'sqlite_path' => dirname(dirname(__DIR__)) . '/api_offline_grandplaza/restaurant.sqlite',
    'sync_export_path' => dirname(dirname(__DIR__)) . '/api_offline_grandplaza/offline_sync_exports',
    'no_session_validation' => 0,
    'offline_license' => [
        'api_url' => 'https://agecs.agecs.in/sincronizare_online_app_vanzare/api_verificare_licenta_offline.php',
        'valid_days' => 30,
        'renew_before_days' => 7,
        'retry_seconds' => 21600,
        'clock_tolerance_seconds' => 300,
    ],
    'online_products_sync' => [
        'enabled' => true,
        'auto_check' => false,
        'strict' => true,
        'api_url' => 'https://agecs.agecs.in/sincronizare_online_app_restaurant/sincronizare_date_offline.php',
        'api_key' => '12345678',
        'cod_client' => 25,
        'timeout_seconds' => 30,
        'dry_run' => false,
        'rewrite_existing' => false,
        'send_api_key_in_query' => true,
        'verify_ssl' => true,
        'allow_http_without_session' => true,
    ],
    'offline_sales_sync' => [
        'enabled' => true,
        'automatic' => true,
        'allow_login_worker' => true,
        'automatic_interval_seconds' => 120,
        'strict' => true,
        'api_url' => 'https://agecs.agecs.in/sincronizare_online_app_restaurant/sincronizare_date_offline.php',
        'api_key' => '12345678',
        'timeout_seconds' => 45,
        'send_api_key_in_query' => true,
        'verify_ssl' => true,
        'debug_db' => false,
    ],
    'online_tablet_sync' => [
        'enabled' => true,
        'automatic' => true,
        'automatic_interval_seconds' => 30,
        'api_url' => 'https://agecs.agecs.in/api/offline-tablet-orders.php',
        'api_key' => '12345678',
        'client_id' => 25,
        'cod_locatie' => 1,
        'installation_uuid' => 'grandplaza-c25-l1',
        'timeout_seconds' => 30,
        'limit' => 200,
        'send_api_key_in_query' => true,
        'verify_ssl' => true,
    ],
];

require_once __DIR__ . '/offline_installation_identity_lib.php';
$config = offline_installation_identity_apply_config($config);

$caBundlePath = (string)$config['ca_bundle_path'];
if ($caBundlePath !== '' && is_file($caBundlePath)) {
    putenv('CURL_CA_BUNDLE=' . $caBundlePath);
    putenv('SSL_CERT_FILE=' . $caBundlePath);
}

return $config;
