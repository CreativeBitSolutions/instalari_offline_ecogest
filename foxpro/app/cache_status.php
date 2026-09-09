<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $status = (new DbfCache(Config::load()))->status();
    echo json_encode([
        'ok' => true,
        'needs_refresh' => !$status['built'] || !$status['fresh'],
        'status' => $status,
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'needs_refresh' => true,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_SLASHES);
}
