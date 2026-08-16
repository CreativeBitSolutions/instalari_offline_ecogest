<?php
$defaults = [
    'base_url' => 'https://pizza-sibiu-amicii.ro/wp-json/restaurant-sync/v1',
    'api_key' => (string)(getenv('AGECS_WOO_API_KEY') ?: ''),
    'api_secret' => (string)(getenv('AGECS_WOO_API_SECRET') ?: ''),
    'client_id' => 1008,
    'location_id' => 1,
    'statuses' => ['processing'],
    'timeout' => 20,
    'verify_ssl' => true,
    'use_hmac' => false,
    'initial_lookback_days' => 7,
    'automatic_interval_seconds' => 30,

    // Alias oferit de pluginul AGECS Offline WooCommerce Bridge pentru detalii complete.
    'wp_order_details_endpoint' => 'https://pizza-sibiu-amicii.ro/wp-json/grandplaza-pos/v1/order-details',
    'wp_order_details_api_key' => (string)(getenv('AGECS_WOO_DETAILS_API_KEY') ?: (getenv('AGECS_WOO_API_KEY') ?: '')),

    'printer_queue_base_dir' => defined('RESTAURANT_OFFLINE_API_DIR')
        ? RESTAURANT_OFFLINE_API_DIR
        : dirname(dirname(dirname(__DIR__))) . '/api_offline_taverna_amicii',

    // In SQLite offline transportul se mapeaza explicit dupa suma cu TVA
    // (ex. shipping:10.00 / shipping:15.00), fara coduri POS hardcodate.
    // Sectiunea ramane pentru compatibilitatea importerului MySQL existent.
    'delivery_fee' => [
        'enabled' => true,
        'amount_tolerance' => 0.01,
        'pickup_keywords' => [
            'ridicare', 'ridică', 'ridica', 'ridicare restaurant',
            'ridicare de la restaurant', 'pickup', 'pick-up', 'pick up',
            'local_pickup', 'local pickup', 'takeaway', 'take away', 'take-away',
        ],
        'mappings' => [],
    ],
];

$localFile = __DIR__ . '/woo_sync_config.local.php';
if (is_file($localFile)) {
    $local = require $localFile;
    if (is_array($local)) {
        $defaults = array_replace_recursive($defaults, $local);
    }
}

if (trim((string)$defaults['wp_order_details_api_key']) === '') {
    $defaults['wp_order_details_api_key'] = (string)$defaults['api_key'];
}

return $defaults;
