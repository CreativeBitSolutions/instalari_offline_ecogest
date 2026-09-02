<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/db.php';

if (!function_exists('restaurantLoadJsonConfig')) {
    function restaurantLoadJsonConfig(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}

$restaurantAppConfig = offline_config_all();
$restaurantConfig = [
    'driver' => (string)($restaurantAppConfig['driver'] ?? 'sqlite'),
    'live_id' => (int)($restaurantAppConfig['live_id'] ?? 12),
    'client_id' => (int)($restaurantAppConfig['client_id'] ?? ($_SESSION['client_id'] ?? 0)),
    'cod_locatie' => (int)($restaurantAppConfig['cod_locatie_default'] ?? ($_SESSION['cod_locatie'] ?? 1)),
    'sqlite_path' => (string)$restaurantAppConfig['db_runtime_file'],
    'api_root_absolute' => (string)($restaurantAppConfig['api_root_absolute'] ?? ''),
    'ca_bundle_path' => (string)($restaurantAppConfig['ca_bundle_path'] ?? ''),
    'no_session_validation' => (int)($restaurantAppConfig['no_session_validation'] ?? ($_SESSION['no_session_validation'] ?? 0)),
    'online_products_sync' => is_array($restaurantAppConfig['online_products_sync'] ?? null) ? $restaurantAppConfig['online_products_sync'] : [],
    'offline_sales_sync' => is_array($restaurantAppConfig['offline_sales_sync'] ?? null) ? $restaurantAppConfig['offline_sales_sync'] : [],
    'online_tablet_sync' => is_array($restaurantAppConfig['online_tablet_sync'] ?? null) ? $restaurantAppConfig['online_tablet_sync'] : [],
];

if (!defined('RESTAURANT_OFFLINE_API_DIR')) {
    define('RESTAURANT_OFFLINE_API_DIR', rtrim((string)$restaurantAppConfig['api_root_absolute'], '/\\'));
}

$_SESSION['client_id'] = (int)($restaurantConfig['client_id'] ?? 0);
$_SESSION['cod_locatie'] = (int)($restaurantConfig['cod_locatie'] ?? ($_SESSION['cod_locatie'] ?? 1));
$_SESSION['d'] = $_SESSION['d'] ?? 0;

if (array_key_exists('no_session_validation', $restaurantConfig)) {
    $_SESSION['no_session_validation'] = (int)$restaurantConfig['no_session_validation'];
}

$restaurantDriver = strtolower((string)($restaurantConfig['driver'] ?? 'sqlite'));
if (!defined('RESTAURANT_DB_DRIVER')) {
    define('RESTAURANT_DB_DRIVER', $restaurantDriver);
}

if (!function_exists('restaurantIsOfflineSqlite')) {
    function restaurantIsOfflineSqlite(): bool
    {
        return defined('RESTAURANT_DB_DRIVER') && RESTAURANT_DB_DRIVER === 'sqlite';
    }
}

if (!function_exists('restaurantPdoDriver')) {
    function restaurantPdoDriver(PDO $pdo): string
    {
        try {
            return strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
        } catch (Throwable $e) {
            return '';
        }
    }
}
