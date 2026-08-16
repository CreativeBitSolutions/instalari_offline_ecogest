<?php
declare(strict_types=1);

require_once __DIR__ . '/database_connection.php';
require_once __DIR__ . '/offline_sync_queue_lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
date_default_timezone_set('Europe/Bucharest');

function restaurant_sync_export_exit(array $payload, int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
        restaurant_sync_export_exit(['status' => 'error', 'message' => 'Metoda permisă este POST.'], 405);
    }
    if (!isset($_SESSION['admin_id'])) {
        restaurant_sync_export_exit(['status' => 'error', 'message' => 'Sesiunea a expirat.'], 401);
    }
    if (!function_exists('restaurantIsOfflineSqlite') || !restaurantIsOfflineSqlite()) {
        restaurant_sync_export_exit(['status' => 'error', 'message' => 'Coada este disponibilă numai în modul offline SQLite.'], 409);
    }

    $actorId = (int)$_SESSION['admin_id'];
    $actor = restaurant_sync_queue_row($pdo, 'SELECT admin_id, rank, locatie FROM admins_12 WHERE admin_id = ? LIMIT 1', [$actorId]);
    if (!$actor || !in_array(strtolower((string)$actor['rank']), ['sefsala', 'administrator', 'admin'], true)) {
        restaurant_sync_export_exit(['status' => 'error', 'message' => 'Sincronizarea poate fi pornită numai de șeful de sală.'], 403);
    }

    $config = restaurant_sync_queue_config($restaurantConfig);
    restaurant_sync_queue_assert_config($config);
    $trigger = !empty($_POST['automatic']) ? 'automat' : 'manual';

    $lockPath = RESTAURANT_OFFLINE_API_DIR . DIRECTORY_SEPARATOR . 'offline_sales_sync.lock';
    $lockHandle = fopen($lockPath, 'c');
    if ($lockHandle === false) {
        throw new RuntimeException('Blocarea sincronizării nu poate fi inițializată.');
    }
    if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
        restaurant_sync_export_exit([
            'status' => 'success',
            'empty' => true,
            'busy' => true,
            'message' => 'O altă sincronizare este deja în curs.',
            'queue' => restaurant_sync_queue_counts($pdo),
        ]);
    }

    $releasedBlocked = $trigger === 'manual' ? restaurant_sync_queue_release_blocked($pdo) : 0;
    $discovered = restaurant_sync_queue_discover($pdo, $config, $actorId);
    $result = restaurant_sync_queue_process($pdo, $config, $trigger, 20);
    $processed = $result['processed'];
    $failed = $result['failed'];
    $publicCounts = ['note' => 0, 'det_note' => 0, 'inchideri_r_12' => 0, 'rapoarte_z' => 0, 'discounturi_acordate' => 0];
    $inserted = [];
    $duplicates = [];
    foreach ($processed as $item) {
        foreach ($publicCounts as $table => $value) {
            $publicCounts[$table] += (int)($item['counts'][$table] ?? 0);
            $inserted[$table] = (int)($inserted[$table] ?? 0) + (int)($item['online']['inserted'][$table] ?? 0);
            $duplicates[$table] = (int)($duplicates[$table] ?? 0) + (int)($item['online']['duplicates'][$table] ?? 0);
        }
    }

    if ($failed && !$processed) {
        restaurant_sync_export_exit([
            'status' => 'error',
            'message' => (string)$failed['message'],
            'queue' => $result['queue'],
            'event' => $failed,
            'online_sync' => ['enabled' => true, 'status' => 'error', 'message' => (string)$failed['message'], 'http_code' => (int)$failed['http_code']],
        ], 502);
    }

    if (!$processed) {
        restaurant_sync_export_exit([
            'status' => 'success',
            'empty' => true,
            'message' => $discovered > 0 ? 'Evenimentele au fost puse în coadă și așteaptă următoarea încercare.' : 'Coada de sincronizare este la zi.',
            'released_blocked' => $releasedBlocked,
            'counts' => $publicCounts,
            'queue' => $result['queue'],
        ]);
    }

    $last = $processed[count($processed) - 1];
    restaurant_sync_export_exit([
        'status' => 'success',
        'message' => count($processed) . ' eveniment(e) au fost confirmate online.',
        'empty' => false,
        'processed_events' => count($processed),
        'released_blocked' => $releasedBlocked,
        'events' => array_map(static fn(array $item): array => ['event_uuid' => $item['event_uuid'], 'event_type' => $item['event_type']], $processed),
        'counts' => $publicCounts,
        'queue' => $result['queue'],
        'file' => (string)$last['file'],
        'online_sync' => [
            'enabled' => true,
            'status' => 'success',
            'message' => count($processed) . ' eveniment(e) confirmate.',
            'inserted' => $inserted,
            'duplicates' => $duplicates,
            'http_code' => (int)($last['online']['http_code'] ?? 0),
        ],
        'warning' => $failed,
    ]);
} catch (Throwable $e) {
    restaurant_sync_export_exit(['status' => 'error', 'message' => 'Sincronizarea a eșuat: ' . $e->getMessage()], 500);
}
