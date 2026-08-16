<?php
require_once __DIR__ . '/session_device.php';

date_default_timezone_set('Europe/Bucharest');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
restaurantDeviceUid();

$restaurantLocalConfigFile = __DIR__ . '/offline_config.local.php';
if (!is_file($restaurantLocalConfigFile)) {
    echo '<h1>Lipseste fisierul de configurare offline_config.local.php.</h1>';
    exit;
}

$restaurantConfig = require $restaurantLocalConfigFile;
if (!is_array($restaurantConfig)) {
    echo '<h1>Fisierul offline_config.local.php trebuie sa returneze un array de configurare.</h1>';
    exit;
}

$restaurantEnvDriver = getenv('RESTAURANT_DB_DRIVER');
if ($restaurantEnvDriver !== false && $restaurantEnvDriver !== '') {
    $restaurantConfig['driver'] = strtolower((string)$restaurantEnvDriver);
}

$restaurantDriver = strtolower((string)($restaurantConfig['driver'] ?? 'sqlite'));

$restaurantOfflineApiPath = (string)($restaurantConfig['offline_api_path'] ?? (dirname(dirname(__DIR__)) . '/api_offline_taverna_amicii'));
if (!defined('RESTAURANT_OFFLINE_API_DIR')) {
    define('RESTAURANT_OFFLINE_API_DIR', rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $restaurantOfflineApiPath), DIRECTORY_SEPARATOR));
}

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

if (!function_exists('restaurantSqliteFindInSet')) {
    function restaurantSqliteFindInSet($needle, $haystack): int
    {
        $needle = trim((string)$needle);
        if ($needle === '') {
            return 0;
        }

        $parts = array_map('trim', explode(',', (string)$haystack));
        foreach ($parts as $index => $part) {
            if ($part === $needle) {
                return $index + 1;
            }
        }

        return 0;
    }
}

if (!function_exists('restaurantConfigureSqlitePdo')) {
    function restaurantConfigureSqlitePdo(PDO $pdo): void
    {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');

        if (method_exists($pdo, 'sqliteCreateFunction')) {
            $pdo->sqliteCreateFunction('FIND_IN_SET', 'restaurantSqliteFindInSet', 2);
            $pdo->sqliteCreateFunction('NOW', static fn(): string => date('Y-m-d H:i:s'), 0);
            $pdo->sqliteCreateFunction('CURDATE', static fn(): string => date('Y-m-d'), 0);
            $pdo->sqliteCreateFunction('CURTIME', static fn(): string => date('H:i:s'), 0);
            $pdo->sqliteCreateFunction('TIMESTAMP', static function (...$args): string {
                $parts = array_map(static fn($value): string => trim((string)$value), $args);
                return trim(implode(' ', array_filter($parts, static fn(string $value): bool => $value !== '')));
            }, -1);
            $pdo->sqliteCreateFunction('CONCAT', static function (...$args): string {
                return implode('', array_map(static fn($value): string => $value === null ? '' : (string)$value, $args));
            }, -1);
        }
    }
}

if ($restaurantDriver === 'sqlite') {
    if (!extension_loaded('pdo_sqlite')) {
        echo '<h1>Extensia pdo_sqlite nu este activata.</h1>';
        exit;
    }

    $sqlitePath = (string)($restaurantConfig['sqlite_path'] ?? (__DIR__ . '/data/restaurant.sqlite'));
    if (!is_dir(dirname($sqlitePath))) {
        mkdir(dirname($sqlitePath), 0777, true);
    }

    require_once __DIR__ . '/tools/sqlite_schema.php';

    try {
        $pdo = new PDO('sqlite:' . $sqlitePath);
        restaurantConfigureSqlitePdo($pdo);
        restaurant_sqlite_apply_schema($pdo);
    } catch (PDOException $e) {
        echo '<h1>Eroare la deschiderea bazei SQLite: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</h1>';
        exit;
    }

    $central_pdo = null;
    $client_id = (int)($restaurantConfig['client_id'] ?? ($_SESSION['client_id'] ?? 1));
    $cod_locatie = (int)($restaurantConfig['cod_locatie'] ?? ($_SESSION['cod_locatie'] ?? 1));
    $_SESSION['client_id'] = $client_id;
    $_SESSION['cod_locatie'] = $cod_locatie;
    $_SESSION['d'] = $_SESSION['d'] ?? 0;
    $_SESSION['mod_listare'] = $_SESSION['mod_listare'] ?? 'simplu';
    restaurant_sqlite_set_cod_locatie_context($pdo, $cod_locatie);

    if (array_key_exists('no_session_validation', $restaurantConfig)) {
        $_SESSION['no_session_validation'] = (int)$restaurantConfig['no_session_validation'];
    }
} else {
    $mysqlConfig = $restaurantConfig['mysql'] ?? [];
    $driver = (string)($mysqlConfig['driver'] ?? 'mysql');
    $host = (string)($mysqlConfig['host'] ?? 'localhost');
    $central_database = (string)($mysqlConfig['central_database'] ?? '');
    $dsn_central = "{$driver}:host={$host};dbname={$central_database};charset=utf8mb4";
    $central_username = (string)($mysqlConfig['central_username'] ?? '');
    $central_password = (string)($mysqlConfig['central_password'] ?? '');

    if ($central_database === '' || $central_username === '') {
        echo '<h1>Configuratia MySQL centrala este incompleta in offline_config.local.php.</h1>';
        exit;
    }

    try {
        $central_pdo = new PDO($dsn_central, $central_username, $central_password);
        $central_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $central_pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo '<h1>Conexiunea la baza de date centrala a esuat: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</h1>';
        exit;
    }

    if (isset($_SESSION['client_id'])) {
        $client_id = (int)$_SESSION['client_id'];

        try {
            $stmt = $central_pdo->prepare('SELECT b_d, u_bd, p_bd FROM clienti WHERE id_client = :client_id');
            $stmt->bindParam(':client_id', $client_id, PDO::PARAM_INT);
            $stmt->execute();
            $client = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$client) {
                echo '<h1>Clientul nu a fost gasit.</h1>';
                exit;
            }

            $client_db = trim((string)$client['b_d']);
            $client_user = trim((string)$client['u_bd']);
            $client_pass = trim((string)$client['p_bd']);
            $dsn_client = "{$driver}:host={$host};dbname={$client_db};charset=utf8mb4";

            try {
                $pdo = new PDO($dsn_client, $client_user, $client_pass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                echo '<h1>Eroare la conectarea la baza de date a clientului: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</h1>';
                exit;
            }
        } catch (PDOException $e) {
            echo '<h1>Eroare la interogarea bazei de date centrale: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</h1>';
            exit;
        }
    }
}

$live_id = (int)($restaurantConfig['live_id'] ?? 12);

$tabel_final_consumuri = 'consumuri' . '_' . $live_id;
$tabel_final_bonuri_consum = 'bonuri_consum' . '_' . $live_id;
$tabel_final_abonati = 'abonati' . '_' . $live_id;
$tabel_final_achizitii = 'achizitii' . '_' . $live_id;
$tabel_final_admins = 'admins' . '_' . $live_id;
$tabel_final_bonuri = 'bonuri' . '_' . $live_id;
$tabel_final_categorii = 'categorii';
$tabel_final_chitante = 'chitante' . '_' . $live_id;
$tabel_final_comenzi = 'comenzi' . '_' . $live_id;
$tabel_final_comenzi_detalii = 'comenzi_detalii' . '_' . $live_id;
$tabel_final_cosuri = 'cosuri' . '_' . $live_id;
$tabel_final_customers = 'customers' . '_' . $live_id;
$tabel_final_date_firma = 'date_firma';
$tabel_final_det_bonuri = 'det_bonuri' . '_' . $live_id;
$tabel_final_det_monetar = 'det_monetar' . '_' . $live_id;
$tabel_final_det_note = 'det_note';
$tabel_final_det_procese_comp = 'det_procese_comp' . '_' . $live_id;
$tabel_final_det_stornari = 'det_stornari' . '_' . $live_id;
$tabel_final_det_stornari_fact = 'det_stornari_fact' . '_' . $live_id;
$tabel_final_det_stornari_rest = 'det_stornari_rest' . '_' . $live_id;
$tabel_final_de_listat_bar = 'de_listat_bar' . '_' . $live_id;
$tabel_final_de_listat_buc = 'de_listat_buc' . '_' . $live_id;
$tabel_final_dispozitii = 'dispozitii' . '_' . $live_id;
$tabel_final_extra_images = 'extra_images' . '_' . $live_id;
$tabel_final_facturi = 'facturi' . '_' . $live_id;
$tabel_final_inchideri_m = 'inchideri_m' . '_' . $live_id;
$tabel_final_inchideri_r = 'inchideri_r' . '_' . $live_id;
$tabel_final_loc_mese = 'loc_mese' . '_' . $live_id;
$tabel_final_mese = 'mese';
$tabel_final_miscari = 'miscari';
$tabel_final_monetar = 'monetar' . '_' . $live_id;
$tabel_final_nomenclator = 'produse_servicii';
$tabel_final_note = 'note';
$tabel_final_procese_comp = 'procese_comp' . '_' . $live_id;
$tabel_final_recenzii = 'recenzii' . '_' . $live_id;
$tabel_final_retete = 'retete';
$tabel_final_stoc = 'stoc' . '_' . $live_id;
$tabel_final_stornari = 'stornari' . '_' . $live_id;
$tabel_final_stornari_fact = 'stornari_fact' . '_' . $live_id;
$tabel_final_stornari_rest = 'stornari_rest' . '_' . $live_id;
$tabel_final_terti = 'clienti';
$tabel_final_vanzari = 'vanzari' . '_' . $live_id;
