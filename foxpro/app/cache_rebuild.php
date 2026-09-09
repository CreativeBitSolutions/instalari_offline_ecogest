<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
@set_time_limit(0);

try {
    $status = (new DbfCache(Config::load()))->rebuild();
    echo json_encode([
        'ok' => true,
        'message' => 'Cache reincarcat in ' . app_money($status['seconds']) . ' secunde.',
        'status' => $status,
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_SLASHES);
}
