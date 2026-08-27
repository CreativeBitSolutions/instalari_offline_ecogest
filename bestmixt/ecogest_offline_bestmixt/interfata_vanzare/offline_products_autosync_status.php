<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function products_autosync_status_exit(array $payload, int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    require_once __DIR__ . '/database_connection.php';
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $tableStmt = $pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'offline_products_sync_logs'");
    $tableStmt->execute();
    if ((int)$tableStmt->fetchColumn() === 0) {
        products_autosync_status_exit([
            'ok' => true,
            'state' => 'never',
            'message' => 'Nu există încă o sincronizare înregistrată.',
        ]);
    }

    $row = $pdo->query('SELECT * FROM offline_products_sync_logs ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        products_autosync_status_exit([
            'ok' => true,
            'state' => 'never',
            'message' => 'Nu există încă o sincronizare înregistrată.',
        ]);
    }

    $status = strtolower(trim((string)($row['status'] ?? '')));
    $isError = $status === 'error';
    $changed = (int)($row['inserted_count'] ?? 0)
        + (int)($row['updated_count'] ?? 0)
        + (int)($row['deleted_count'] ?? 0)
        + (int)($row['lookup_inserted'] ?? 0)
        + (int)($row['lookup_updated'] ?? 0);
    $dateRaw = trim((string)($row['data_ora'] ?? ''));
    $dateLabel = $dateRaw;
    if ($dateRaw !== '') {
        try {
            $dateLabel = (new DateTimeImmutable($dateRaw))->format('d.m.Y H:i:s');
        } catch (Throwable $e) {
        }
    }

    $message = $isError
        ? trim((string)($row['erori'] ?? 'Ultima sincronizare a eșuat.'))
        : 'Ultima rulare: ' . ($dateLabel !== '' ? $dateLabel : 'dată necunoscută')
            . ', produse verificate: ' . (int)($row['received_count'] ?? 0)
            . ', modificări: ' . $changed . '.';

    products_autosync_status_exit([
        'ok' => true,
        'state' => $isError ? 'error' : 'ok',
        'status' => $status,
        'message' => $message,
        'last_run_at' => $dateRaw,
        'received' => (int)($row['received_count'] ?? 0),
        'inserted' => (int)($row['inserted_count'] ?? 0),
        'updated' => (int)($row['updated_count'] ?? 0),
        'deleted' => (int)($row['deleted_count'] ?? 0),
        'changed' => $changed,
    ]);
} catch (Throwable $e) {
    products_autosync_status_exit([
        'ok' => false,
        'state' => 'error',
        'message' => 'Starea autosincronizării nu a putut fi citită.',
    ], 500);
}
