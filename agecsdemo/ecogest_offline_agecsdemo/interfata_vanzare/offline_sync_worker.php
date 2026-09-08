<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/database_connection.php';
require_once __DIR__ . '/offline_sync_queue_lib.php';
require_once __DIR__ . '/offline_sequence_state.php';

function offline_sync_worker_json(int $code, array $data): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function offline_sync_worker_acquire(PDO $pdo): string
{
    $token = bin2hex(random_bytes(12));
    $pdo->exec('BEGIN IMMEDIATE TRANSACTION');
    try {
        $row = $pdo->query('SELECT locked_until FROM offline_sync_runtime WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        if (!empty($row['locked_until']) && strtotime((string)$row['locked_until']) > time()) {
            $pdo->exec('ROLLBACK');
            return '';
        }
        $stmt = $pdo->prepare("UPDATE offline_sync_runtime SET lock_token = ?, locked_until = datetime('now', '+45 seconds'), last_tick_at = CURRENT_TIMESTAMP WHERE id = 1");
        $stmt->execute([$token]);
        $pdo->exec('COMMIT');
        return $token;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function offline_sync_worker_release(PDO $pdo, string $token, string $error = ''): void
{
    $stmt = $pdo->prepare('UPDATE offline_sync_runtime SET lock_token = NULL, locked_until = NULL, last_error = ? WHERE id = 1 AND lock_token = ?');
    $stmt->execute([$error !== '' ? $error : null, $token]);
}

function offline_sync_worker_retry_delay(int $attempts): int
{
    $steps = [30, 60, 120, 300, 600, 900];
    return $steps[min(max($attempts - 1, 0), count($steps) - 1)] + random_int(0, 12);
}

try {
    offline_sync_queue_ensure_schema($pdo);
    offline_sync_queue_recover_stale($pdo);
    offline_sync_queue_discover($pdo);
    $token = offline_sync_worker_acquire($pdo);
    if ($token === '') {
        offline_sync_worker_json(200, ['status' => 'busy', 'queue' => offline_sync_queue_counts($pdo)]);
    }

    $stmt = $pdo->query("SELECT * FROM offline_sync_outbox
        WHERE status IN ('pending', 'retry')
          AND (next_attempt_at IS NULL OR datetime(next_attempt_at) <= datetime('now'))
        ORDER BY id LIMIT 1");
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$event) {
        offline_sync_worker_release($pdo, $token);
        offline_sync_worker_json(200, ['status' => 'idle', 'queue' => offline_sync_queue_counts($pdo)]);
    }

    $config = offline_sync_queue_config();
    if ($config['url'] === '' || $config['api_key'] === '') {
        throw new RuntimeException('Configurarea sincronizarii automate este incompleta.');
    }

    $stmt = $pdo->prepare("UPDATE offline_sync_outbox SET status = 'sending', attempts = attempts + 1, locked_at = CURRENT_TIMESTAMP, last_error = NULL WHERE id = ?");
    $stmt->execute([(int)$event['id']]);
    $attempts = (int)$event['attempts'] + 1;

    $body = json_encode([
        'client_id' => $config['client_id'],
        'cod_locatie' => (int)$event['cod_locatie'],
        'profile' => $config['profile'],
        'installation_uuid' => $config['installation_uuid'],
        'event_uuid' => $event['event_uuid'],
        'event_type' => $event['event_type'],
        'payload_sha256' => $event['payload_sha256'],
        'filename' => preg_replace('/[^A-Za-z0-9_-]/', '_', (string)$event['event_uuid']) . '.xml',
        'format' => 'xml',
        'content' => $event['payload_xml'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $ch = curl_init($config['url']);
    $curlOptions = [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            'X-Sync-Api-Key: ' . $config['api_key'],
        ],
        CURLOPT_POSTFIELDS => $body,
    ];
    if ($config['ca_bundle_path'] !== '' && is_file($config['ca_bundle_path'])) {
        $curlOptions[CURLOPT_CAINFO] = $config['ca_bundle_path'];
    }
    curl_setopt_array($ch, $curlOptions);
    $responseBody = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $response = is_string($responseBody) ? json_decode($responseBody, true) : null;

    $ack = is_array($response) && is_array($response['event_ack'] ?? null) ? $response['event_ack'] : [];
    $accepted = $httpCode >= 200 && $httpCode < 300
        && in_array((string)($ack['status'] ?? ''), ['processed', 'already_processed'], true)
        && hash_equals((string)$event['event_uuid'], (string)($ack['event_uuid'] ?? ''))
        && hash_equals((string)$event['payload_sha256'], (string)($ack['payload_sha256'] ?? ''));

    if ($accepted) {
        $stmt = $pdo->prepare("UPDATE offline_sync_outbox SET status = 'sent', sent_at = CURRENT_TIMESTAMP, locked_at = NULL, last_http_code = ?, last_error = NULL WHERE id = ?");
        $stmt->execute([$httpCode, (int)$event['id']]);
        if (is_array($response['sequence_state'] ?? null)) {
            offline_sequence_apply_online_state($pdo, $response['sequence_state'], (int)$event['cod_locatie']);
        }
        $pdo->exec("UPDATE offline_sync_runtime SET last_success_at = CURRENT_TIMESTAMP WHERE id = 1");
        offline_sync_worker_release($pdo, $token);
        offline_sync_worker_json(200, ['status' => 'sent', 'event_uuid' => $event['event_uuid'], 'queue' => offline_sync_queue_counts($pdo)]);
    }

    $message = $curlError !== '' ? $curlError : (string)($response['message'] ?? ('Raspuns online HTTP ' . $httpCode));
    $missingAckFromSuccessfulResponse = $httpCode >= 200 && $httpCode < 300 && !$ack;
    $retryable = $httpCode === 0 || $httpCode === 429 || $httpCode >= 500 || $missingAckFromSuccessfulResponse || (bool)($response['retryable'] ?? false);
    if ($retryable) {
        $delay = offline_sync_worker_retry_delay($attempts);
        $stmt = $pdo->prepare("UPDATE offline_sync_outbox SET status = 'retry', next_attempt_at = datetime('now', '+' || ? || ' seconds'), locked_at = NULL, last_http_code = ?, last_error = ? WHERE id = ?");
        $stmt->execute([$delay, $httpCode ?: null, substr($message, 0, 1000), (int)$event['id']]);
    } else {
        $stmt = $pdo->prepare("UPDATE offline_sync_outbox SET status = 'blocked', locked_at = NULL, last_http_code = ?, last_error = ? WHERE id = ?");
        $stmt->execute([$httpCode ?: null, substr($message, 0, 1000), (int)$event['id']]);
    }
    offline_sync_worker_release($pdo, $token, $message);
    offline_sync_worker_json(200, ['status' => $retryable ? 'retry' : 'blocked', 'message' => $message, 'queue' => offline_sync_queue_counts($pdo)]);
} catch (Throwable $e) {
    if (isset($token) && $token !== '') {
        offline_sync_worker_release($pdo, $token, $e->getMessage());
    }
    offline_sync_worker_json(500, ['status' => 'error', 'message' => $e->getMessage(), 'queue' => isset($pdo) ? offline_sync_queue_counts($pdo) : []]);
}
