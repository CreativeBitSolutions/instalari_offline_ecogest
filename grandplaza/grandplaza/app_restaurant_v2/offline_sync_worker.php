<?php
declare(strict_types=1);

require_once __DIR__ . '/database_connection.php';
require_once __DIR__ . '/offline_sync_queue_lib.php';
require_once __DIR__ . '/offline_tablet_sync_lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function restaurant_sync_worker_exit(array $payload, int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
        restaurant_sync_worker_exit(['status' => 'error', 'message' => 'Metoda permisă este POST.'], 405);
    }
    $config = restaurant_sync_queue_config($restaurantConfig);
    restaurant_sync_queue_assert_config($config);
    $actorId = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : 0;
    $remoteAddress = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $localRequest = in_array($remoteAddress, ['127.0.0.1', '::1'], true);
    $offlineSqlite = function_exists('restaurantIsOfflineSqlite') && restaurantIsOfflineSqlite();
    $loginWorkerAllowed = $config['allow_login_worker'] && $localRequest && $offlineSqlite;
    if ($actorId <= 0 && !$loginWorkerAllowed) {
        restaurant_sync_worker_exit(['status' => 'error', 'message' => 'Sesiunea a expirat.'], 401);
    }
    $lockPath = RESTAURANT_OFFLINE_API_DIR . DIRECTORY_SEPARATOR . 'offline_sales_sync.lock';
    $lockHandle = fopen($lockPath, 'c');
    if ($lockHandle === false) {
        throw new RuntimeException('Blocarea workerului nu poate fi inițializată.');
    }
    if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
        restaurant_sync_worker_exit(['status' => 'busy', 'queue' => restaurant_sync_queue_counts($pdo)]);
    }

    restaurant_sync_queue_discover($pdo, $config, $actorId);
    $trigger = $actorId > 0 ? 'automat' : 'automat_login';
    $result = restaurant_sync_queue_process($pdo, $config, $trigger, 10);
    $tabletResult = restaurant_tablet_sync_run($pdo, $restaurantConfig);
    $tabletPendingForOperator = 0;
    if ($actorId > 0) {
        $tabletCountStmt = $pdo->prepare("SELECT COUNT(*) FROM com_tableta WHERE stare='TRIMISA' AND owner_operator_id=? AND locatie=?");
        $tabletCountStmt->execute([$actorId, (int)($restaurantConfig['cod_locatie'] ?? 0)]);
        $tabletPendingForOperator = (int)$tabletCountStmt->fetchColumn();
    }
    restaurant_sync_worker_exit([
        'status' => $result['failed'] && !$result['processed'] ? 'waiting' : 'success',
        'processed_events' => count($result['processed']),
        'failed' => $result['failed'],
        'queue' => $result['queue'],
        'tablet_sync' => $tabletResult,
        'tablet_pending_for_operator' => $tabletPendingForOperator,
    ]);
} catch (Throwable $e) {
    restaurant_sync_worker_exit(['status' => 'error', 'message' => $e->getMessage()], 500);
}
