<?php
declare(strict_types=1);

class RestaurantSyncQueueHttpException extends RuntimeException
{
    private int $httpCodeValue;
    private bool $retryableValue;

    public function __construct(string $message, int $httpCode = 0, bool $retryable = true)
    {
        parent::__construct($message);
        $this->httpCodeValue = $httpCode;
        $this->retryableValue = $retryable;
    }

    public function httpCode(): int
    {
        return $this->httpCodeValue;
    }

    public function retryable(): bool
    {
        return $this->retryableValue;
    }
}

function restaurant_sync_queue_config(array $restaurantConfig): array
{
    $sync = isset($restaurantConfig['offline_sales_sync']) && is_array($restaurantConfig['offline_sales_sync'])
        ? $restaurantConfig['offline_sales_sync']
        : [];

    return [
        'client_id' => (int)($restaurantConfig['client_id'] ?? 0),
        'cod_locatie' => (int)($restaurantConfig['cod_locatie'] ?? 0),
        'installation_uuid' => trim((string)($restaurantConfig['installation_uuid'] ?? '')),
        'enabled' => filter_var($sync['enabled'] ?? false, FILTER_VALIDATE_BOOL),
        'automatic' => filter_var($sync['automatic'] ?? false, FILTER_VALIDATE_BOOL),
        'allow_login_worker' => filter_var($sync['allow_login_worker'] ?? false, FILTER_VALIDATE_BOOL),
        'api_url' => trim((string)($sync['api_url'] ?? '')),
        'api_key' => trim((string)($sync['api_key'] ?? '')),
        'timeout_seconds' => max(5, (int)($sync['timeout_seconds'] ?? 45)),
        'send_api_key_in_query' => filter_var($sync['send_api_key_in_query'] ?? true, FILTER_VALIDATE_BOOL),
        'verify_ssl' => filter_var($sync['verify_ssl'] ?? true, FILTER_VALIDATE_BOOL),
        'debug_db' => filter_var($sync['debug_db'] ?? false, FILTER_VALIDATE_BOOL),
        'export_path' => (string)($restaurantConfig['sync_export_path'] ?? (RESTAURANT_OFFLINE_API_DIR . DIRECTORY_SEPARATOR . 'offline_sync_exports')),
    ];
}

function restaurant_sync_queue_assert_config(array $config): void
{
    if ($config['client_id'] <= 0 || $config['cod_locatie'] <= 0) {
        throw new RuntimeException('Clientul sau locația sincronizării nu sunt configurate.');
    }
    if ($config['installation_uuid'] === '') {
        throw new RuntimeException('Lipsește installation_uuid din configurația offline.');
    }
}

function restaurant_sync_queue_rows(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function restaurant_sync_queue_row(PDO $pdo, string $sql, array $params = []): ?array
{
    $rows = restaurant_sync_queue_rows($pdo, $sql, $params);
    return $rows[0] ?? null;
}

function restaurant_sync_queue_source_id(string $table, $pk, int $codLocatie, string $installationUuid): string
{
    return $table . ':' . $codLocatie . ':' . $installationUuid . ':' . (string)$pk;
}

function restaurant_sync_queue_meta(string $table, $pk, int $codLocatie, string $installationUuid, string $eventUuid): array
{
    return [
        'export_id' => $eventUuid,
        'source_table' => $table,
        'source_pk' => (string)$pk,
        'cod_locatie' => $codLocatie,
        'installation_uuid' => $installationUuid,
        'sync_id' => restaurant_sync_queue_source_id($table, $pk, $codLocatie, $installationUuid),
    ];
}

function restaurant_sync_queue_transform_rows(string $table, array $rows, int $codLocatie, string $installationUuid, string $eventUuid): array
{
    $pkMap = [
        'note' => 'nrbon',
        'det_note' => 'id_vanz',
        'inchideri_r_12' => 'id_inch',
        'rapoarte_z' => 'id',
        'discounturi_acordate' => 'id_discount',
    ];
    $pkColumn = $pkMap[$table];

    foreach ($rows as &$row) {
        $pk = $row[$pkColumn] ?? '';
        $row['cod_locatie'] = $codLocatie;
        $row['_sync'] = restaurant_sync_queue_meta($table, $pk, $codLocatie, $installationUuid, $eventUuid);

        if ($table === 'note') {
            $row['nrbon_original'] = (int)($row['nrbon'] ?? 0);
            $row['cod_inchidere_original'] = (int)($row['cod_inchidere'] ?? 0);
            $row['nr_raport_z_original'] = (int)($row['nr_raport_z'] ?? 0);
        } elseif ($table === 'det_note') {
            $row['id_vanz_original'] = (int)($row['id_vanz'] ?? 0);
            $row['nr_bon_original'] = (int)($row['nr_bon'] ?? 0);
        } elseif ($table === 'inchideri_r_12') {
            $row['id_inch_original'] = (int)($row['id_inch'] ?? 0);
            $row['cod_inchidere_original'] = (int)($row['cod_inchidere'] ?? 0);
            $row['nr_raport_z_original'] = (int)($row['nr_raport_z'] ?? 0);
        } elseif ($table === 'rapoarte_z') {
            $row['id_original'] = (int)($row['id'] ?? 0);
            $row['nr_raport_z_original'] = (int)($row['nr_raport_z'] ?? 0);
        } elseif ($table === 'discounturi_acordate') {
            $row['id_discount_original'] = (int)($row['id_discount'] ?? 0);
            $row['id_vanz_original'] = (int)($row['id_vanz'] ?? 0);
            $row['id_vanz_sync_ref'] = restaurant_sync_queue_source_id('det_note', $row['id_vanz'] ?? 0, $codLocatie, $installationUuid);
        }
    }
    unset($row);

    return $rows;
}

function restaurant_sync_queue_actor(PDO $pdo, int $actorId): array
{
    if ($actorId <= 0) {
        return ['id' => 0, 'nume' => 'Sincronizare automată', 'rank' => 'system'];
    }

    $row = restaurant_sync_queue_row($pdo, 'SELECT admin_id, admin_firstname, admin_lastname, rank FROM admins_12 WHERE admin_id = ? LIMIT 1', [$actorId]);
    if (!$row) {
        return ['id' => $actorId, 'nume' => 'Operator ' . $actorId, 'rank' => ''];
    }

    return [
        'id' => (int)$row['admin_id'],
        'nume' => trim((string)$row['admin_firstname'] . ' ' . (string)$row['admin_lastname']),
        'rank' => (string)$row['rank'],
    ];
}

function restaurant_sync_queue_sale_data(PDO $pdo, int $nrBon, int $codLocatie): ?array
{
    $note = restaurant_sync_queue_row($pdo, "SELECT * FROM note WHERE nrbon = ? AND locatie = ? AND status = 'F' LIMIT 1", [$nrBon, $codLocatie]);
    if (!$note) {
        return null;
    }

    $note['cod_inchidere'] = 0;
    $note['nr_raport_z'] = 0;
    $details = restaurant_sync_queue_rows($pdo, 'SELECT * FROM det_note WHERE nr_bon = ? ORDER BY id_vanz', [$nrBon]);
    if (!$details) {
        return null;
    }
    $detailIds = array_values(array_filter(array_map(static fn(array $row): int => (int)($row['id_vanz'] ?? 0), $details)));
    $discounts = [];
    if ($detailIds) {
        $placeholders = implode(',', array_fill(0, count($detailIds), '?'));
        $discounts = restaurant_sync_queue_rows($pdo, "SELECT * FROM discounturi_acordate WHERE id_vanz IN ({$placeholders}) ORDER BY id_discount", $detailIds);
    }

    return [
        'note' => [$note],
        'det_note' => $details,
        'inchideri_r_12' => [],
        'rapoarte_z' => [],
        'discounturi_acordate' => $discounts,
    ];
}

function restaurant_sync_queue_shift_data(PDO $pdo, int $idInchidere, int $codLocatie): ?array
{
    $closure = restaurant_sync_queue_row($pdo, 'SELECT * FROM inchideri_r_12 WHERE id_inch = ? AND locatie = ? LIMIT 1', [$idInchidere, $codLocatie]);
    if (!$closure) {
        return null;
    }

    $closure['nr_raport_z'] = 0;
    $notes = restaurant_sync_queue_rows($pdo, "SELECT * FROM note WHERE locatie = ? AND status = 'F' AND cod_inchidere = ? ORDER BY nrbon", [$codLocatie, (int)$closure['cod_inchidere']]);
    foreach ($notes as &$note) {
        $note['nr_raport_z'] = 0;
    }
    unset($note);

    return [
        'note' => $notes,
        'det_note' => [],
        'inchideri_r_12' => [$closure],
        'rapoarte_z' => [],
        'discounturi_acordate' => [],
    ];
}

function restaurant_sync_queue_z_data(PDO $pdo, int $idRaport, int $codLocatie): ?array
{
    $report = restaurant_sync_queue_row($pdo, 'SELECT * FROM rapoarte_z WHERE id = ? AND cod_locatie = ? LIMIT 1', [$idRaport, $codLocatie]);
    if (!$report) {
        return null;
    }

    $nrRaport = (int)($report['nr_raport_z'] ?? 0);
    $closures = restaurant_sync_queue_rows($pdo, 'SELECT * FROM inchideri_r_12 WHERE locatie = ? AND nr_raport_z = ? ORDER BY id_inch', [$codLocatie, $nrRaport]);
    $notes = restaurant_sync_queue_rows($pdo, "SELECT * FROM note WHERE locatie = ? AND status = 'F' AND nr_raport_z = ? ORDER BY nrbon", [$codLocatie, $nrRaport]);

    return [
        'note' => $notes,
        'det_note' => [],
        'inchideri_r_12' => $closures,
        'rapoarte_z' => [$report],
        'discounturi_acordate' => [],
    ];
}

function restaurant_sync_queue_store(PDO $pdo, array $config, string $eventType, string $aggregateType, string $aggregateId, array $tables, array $actor): bool
{
    restaurant_sync_queue_assert_config($config);
    $businessData = [
        'event_type' => $eventType,
        'aggregate_type' => $aggregateType,
        'aggregate_id' => $aggregateId,
        'cod_locatie' => $config['cod_locatie'],
        'tables' => $tables,
    ];
    $contentJson = json_encode($businessData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($contentJson === false) {
        throw new RuntimeException('Conținutul evenimentului nu poate fi serializat.');
    }
    $contentHash = hash('sha256', $contentJson);

    $state = restaurant_sync_queue_row($pdo, 'SELECT payload_sha256 FROM offline_sync_entity_state WHERE entity_type = ? AND entity_id = ? LIMIT 1', [$aggregateType, $aggregateId]);
    if ($state && hash_equals((string)$state['payload_sha256'], $contentHash)) {
        return false;
    }

    $eventUuid = substr($config['installation_uuid'] . ':' . $eventType . ':' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $aggregateId) . ':' . substr($contentHash, 0, 24), 0, 191);
    $payloadTables = [];
    foreach (['note', 'det_note', 'inchideri_r_12', 'rapoarte_z', 'discounturi_acordate'] as $table) {
        $payloadTables[$table] = restaurant_sync_queue_transform_rows(
            $table,
            $tables[$table] ?? [],
            $config['cod_locatie'],
            $config['installation_uuid'],
            $eventUuid
        );
    }

    $counts = [];
    foreach ($payloadTables as $table => $rows) {
        $counts[$table] = count($rows);
    }
    $payload = array_merge([
        'schema_version' => 'offline-sync-v2',
        'event_uuid' => $eventUuid,
        'event_type' => $eventType,
        'aggregate_type' => $aggregateType,
        'aggregate_id' => $aggregateId,
        'installation_uuid' => $config['installation_uuid'],
        'sync_export_id' => $eventUuid,
        'cod_locatie' => $config['cod_locatie'],
        'data_sync' => date('Y-m-d H:i:s'),
        'utilizator_sync' => $actor,
        'counts' => $counts,
    ], $payloadTables);
    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($payloadJson === false) {
        throw new RuntimeException('Payload-ul evenimentului nu poate fi serializat.');
    }

    $pdo->beginTransaction();
    try {
        $insert = $pdo->prepare("INSERT OR IGNORE INTO offline_sync_outbox
            (event_uuid, event_type, aggregate_type, aggregate_id, cod_locatie, payload_json, payload_sha256, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
        $insert->execute([$eventUuid, $eventType, $aggregateType, $aggregateId, $config['cod_locatie'], $payloadJson, $contentHash, date('Y-m-d H:i:s')]);
        $inserted = $insert->rowCount() > 0;

        $stateStmt = $pdo->prepare("INSERT INTO offline_sync_entity_state(entity_type, entity_id, payload_sha256, updated_at)
            VALUES (?, ?, ?, ?)
            ON CONFLICT(entity_type, entity_id) DO UPDATE SET payload_sha256 = excluded.payload_sha256, updated_at = excluded.updated_at");
        $stateStmt->execute([$aggregateType, $aggregateId, $contentHash, date('Y-m-d H:i:s')]);
        $pdo->commit();
        return $inserted;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function restaurant_sync_queue_enqueue_sale(PDO $pdo, array $config, int $nrBon, int $actorId = 0): bool
{
    $tables = restaurant_sync_queue_sale_data($pdo, $nrBon, $config['cod_locatie']);
    return $tables ? restaurant_sync_queue_store($pdo, $config, 'sale_finalized', 'sale', (string)$nrBon, $tables, restaurant_sync_queue_actor($pdo, $actorId)) : false;
}

function restaurant_sync_queue_enqueue_shift(PDO $pdo, array $config, int $idInchidere, int $actorId = 0): bool
{
    $tables = restaurant_sync_queue_shift_data($pdo, $idInchidere, $config['cod_locatie']);
    return $tables ? restaurant_sync_queue_store($pdo, $config, 'shift_closed', 'shift', (string)$idInchidere, $tables, restaurant_sync_queue_actor($pdo, $actorId)) : false;
}

function restaurant_sync_queue_enqueue_z(PDO $pdo, array $config, int $idRaport, int $actorId = 0): bool
{
    $tables = restaurant_sync_queue_z_data($pdo, $idRaport, $config['cod_locatie']);
    return $tables ? restaurant_sync_queue_store($pdo, $config, 'z_closed', 'z_report', (string)$idRaport, $tables, restaurant_sync_queue_actor($pdo, $actorId)) : false;
}

function restaurant_sync_queue_enqueue_safely(callable $callback): bool
{
    try {
        return (bool)$callback();
    } catch (Throwable $e) {
        error_log('Evenimentul de sincronizare nu a putut fi pus în coadă: ' . $e->getMessage());
        return false;
    }
}

function restaurant_sync_queue_discover(PDO $pdo, array $config, int $actorId = 0): int
{
    restaurant_sync_queue_assert_config($config);
    $queued = 0;
    $noteIds = restaurant_sync_queue_rows($pdo, "SELECT nrbon FROM note WHERE locatie = ? AND status = 'F' ORDER BY nrbon", [$config['cod_locatie']]);
    foreach ($noteIds as $row) {
        $queued += restaurant_sync_queue_enqueue_sale($pdo, $config, (int)$row['nrbon'], $actorId) ? 1 : 0;
    }

    $closureIds = restaurant_sync_queue_rows($pdo, 'SELECT id_inch FROM inchideri_r_12 WHERE locatie = ? ORDER BY id_inch', [$config['cod_locatie']]);
    foreach ($closureIds as $row) {
        $queued += restaurant_sync_queue_enqueue_shift($pdo, $config, (int)$row['id_inch'], $actorId) ? 1 : 0;
    }

    $reportIds = restaurant_sync_queue_rows($pdo, 'SELECT id FROM rapoarte_z WHERE cod_locatie = ? ORDER BY id', [$config['cod_locatie']]);
    foreach ($reportIds as $row) {
        $queued += restaurant_sync_queue_enqueue_z($pdo, $config, (int)$row['id'], $actorId) ? 1 : 0;
    }

    return $queued;
}

function restaurant_sync_queue_counts(PDO $pdo): array
{
    $counts = ['pending' => 0, 'sending' => 0, 'retry' => 0, 'sent' => 0, 'blocked' => 0];
    foreach ($pdo->query('SELECT status, COUNT(*) AS total FROM offline_sync_outbox GROUP BY status') as $row) {
        $counts[(string)$row['status']] = (int)$row['total'];
    }
    return $counts;
}

function restaurant_sync_queue_release_blocked(PDO $pdo): int
{
    $stmt = $pdo->prepare("UPDATE offline_sync_outbox
        SET status = 'retry', next_attempt_at = ?, locked_at = NULL
        WHERE status = 'blocked'");
    $stmt->execute([date('Y-m-d H:i:s')]);
    return (int)$stmt->rowCount();
}

function restaurant_sync_queue_retry_delay(int $attempts): int
{
    $delays = [15, 30, 60, 120, 300, 600, 1800];
    return $delays[min(max(0, $attempts - 1), count($delays) - 1)];
}

function restaurant_sync_queue_append_query(string $url, array $params): string
{
    $params = array_filter($params, static fn($value): bool => $value !== '' && $value !== null);
    return $params ? $url . (strpos($url, '?') === false ? '?' : '&') . http_build_query($params) : $url;
}

function restaurant_sync_queue_send(string $json, array $config): array
{
    if (!$config['enabled']) {
        throw new RestaurantSyncQueueHttpException('Sincronizarea online este dezactivată.', 0, false);
    }
    if ($config['api_url'] === '' || $config['api_key'] === '') {
        throw new RestaurantSyncQueueHttpException('URL-ul sau cheia API nu sunt configurate.', 0, false);
    }

    $query = [];
    if ($config['send_api_key_in_query']) {
        $query['api_key'] = $config['api_key'];
    }
    if ($config['debug_db']) {
        $query['debug_db'] = 1;
    }
    $url = restaurant_sync_queue_append_query($config['api_url'], $query);
    $headers = [
        'Content-Type: application/json; charset=utf-8',
        'Accept: application/json',
        'X-Api-Key: ' . $config['api_key'],
        'Authorization: Bearer ' . $config['api_key'],
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_CONNECTTIMEOUT => $config['timeout_seconds'],
        CURLOPT_TIMEOUT => $config['timeout_seconds'],
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => $config['verify_ssl'],
        CURLOPT_SSL_VERIFYHOST => $config['verify_ssl'] ? 2 : 0,
    ]);
    $raw = curl_exec($ch);
    $error = curl_error($ch);
    $errno = curl_errno($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        throw new RestaurantSyncQueueHttpException('Endpointul online nu a putut fi apelat: ' . ($error !== '' ? $error : 'eroare cURL ' . $errno), 0, true);
    }
    $decoded = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', (string)$raw), true);
    if (!is_array($decoded)) {
        throw new RestaurantSyncQueueHttpException('Răspunsul online nu este JSON valid.', $httpCode, $httpCode === 0 || $httpCode >= 500);
    }
    if ($httpCode < 200 || $httpCode >= 300 || ($decoded['status'] ?? '') !== 'success') {
        $message = (string)($decoded['message'] ?? 'Endpointul online a returnat eroare.');
        if (!empty($decoded['errors']) && is_array($decoded['errors'])) {
            $message .= ' ' . implode(' | ', array_map('strval', $decoded['errors']));
        }
        $retryable = $httpCode === 0 || $httpCode === 408 || $httpCode === 429 || $httpCode >= 500;
        throw new RestaurantSyncQueueHttpException($message, $httpCode, $retryable);
    }

    $decoded['http_code'] = $httpCode;
    return $decoded;
}

function restaurant_sync_queue_write_export(array $event, array $config): string
{
    $dir = $config['export_path'];
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Folderul exporturilor nu poate fi creat.');
    }
    $file = $dir . DIRECTORY_SEPARATOR . preg_replace('/[^A-Za-z0-9_.-]/', '_', (string)$event['event_uuid']) . '.json';
    if (file_put_contents($file, (string)$event['payload_json']) === false) {
        throw new RuntimeException('Fișierul evenimentului nu poate fi salvat.');
    }
    return $file;
}

function restaurant_sync_queue_log(PDO $pdo, array $event, array $payload, array $online, string $status, string $error, string $file, string $trigger, int $durationMs): void
{
    try {
        $counts = $payload['counts'] ?? [];
        $stmt = $pdo->prepare("INSERT INTO offline_sync_logs
            (export_id, data_ora, utilizator_id, utilizator_nume, cod_locatie, note_count, det_note_count,
             inchideri_count, rapoarte_z_count, miscari_count, discounturi_count, status, fisier_export,
             payload_hash, erori, declansare, durata_ms, online_status, online_http_code, online_message,
             online_inserted_json, online_duplicates_json, online_updated_json)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $actor = $payload['utilizator_sync'] ?? [];
        $stmt->execute([
            (string)$event['event_uuid'], date('Y-m-d H:i:s'), (int)($actor['id'] ?? 0), (string)($actor['nume'] ?? ''),
            (int)$event['cod_locatie'], (int)($counts['note'] ?? 0), (int)($counts['det_note'] ?? 0),
            (int)($counts['inchideri_r_12'] ?? 0), (int)($counts['rapoarte_z'] ?? 0),
            (int)($counts['discounturi_acordate'] ?? 0), $status, $file, (string)$event['payload_sha256'],
            $error, $trigger, $durationMs, (string)($online['status'] ?? ($error === '' ? 'success' : 'error')),
            (int)($online['http_code'] ?? 0), (string)($online['message'] ?? $error),
            json_encode($online['inserted'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            json_encode($online['duplicates'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            json_encode($online['updated'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    } catch (Throwable $e) {
        error_log('Jurnalul cozii nu a putut fi scris: ' . $e->getMessage());
    }
}

function restaurant_sync_queue_mark_exported(PDO $pdo, array $payload, string $eventUuid, string $hash): void
{
    $pkMap = ['note' => 'nrbon', 'det_note' => 'id_vanz', 'inchideri_r_12' => 'id_inch', 'rapoarte_z' => 'id', 'discounturi_acordate' => 'id_discount'];
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO offline_sync_exported
        (export_id, source_table, source_pk, cod_locatie, original_id, sync_id, payload_hash, exported_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($pkMap as $table => $pkColumn) {
        foreach ($payload[$table] ?? [] as $row) {
            $pk = (string)($row[$pkColumn] ?? ($row['_sync']['source_pk'] ?? ''));
            $stmt->execute([$eventUuid, $table, $pk, (int)$payload['cod_locatie'], $pk, (string)($row['_sync']['sync_id'] ?? ''), $hash, date('Y-m-d H:i:s')]);
        }
    }
}

function restaurant_sync_queue_process(PDO $pdo, array $config, string $trigger = 'manual', int $limit = 12): array
{
    $processed = [];
    $failed = null;
    $now = date('Y-m-d H:i:s');
    $pdo->prepare("UPDATE offline_sync_outbox SET status = 'retry', locked_at = NULL, next_attempt_at = ?
        WHERE status = 'sending' AND locked_at IS NOT NULL AND locked_at < ?")
        ->execute([$now, date('Y-m-d H:i:s', time() - 300)]);

    for ($i = 0; $i < $limit; $i++) {
        $event = restaurant_sync_queue_row($pdo, "SELECT * FROM offline_sync_outbox
            WHERE status IN ('pending', 'retry') AND (next_attempt_at IS NULL OR next_attempt_at <= ?)
            ORDER BY id LIMIT 1", [$now]);
        if (!$event) {
            break;
        }

        $started = microtime(true);
        $attempts = (int)$event['attempts'] + 1;
        $pdo->prepare("UPDATE offline_sync_outbox SET status = 'sending', attempts = ?, locked_at = ?, last_error = '' WHERE id = ?")
            ->execute([$attempts, date('Y-m-d H:i:s'), (int)$event['id']]);
        $payload = json_decode((string)$event['payload_json'], true);
        $file = '';
        try {
            if (!is_array($payload)) {
                throw new RestaurantSyncQueueHttpException('Payload-ul din coadă nu este JSON valid.', 0, false);
            }
            $file = restaurant_sync_queue_write_export($event, $config);
            $online = restaurant_sync_queue_send((string)$event['payload_json'], $config);
            $ack = (string)($online['acknowledgement'] ?? 'processed');
            if (!in_array($ack, ['processed', 'already_processed'], true)) {
                throw new RestaurantSyncQueueHttpException('Online nu a confirmat procesarea evenimentului.', (int)($online['http_code'] ?? 0), true);
            }

            $pdo->beginTransaction();
            $pdo->prepare("UPDATE offline_sync_outbox SET status = 'sent', sent_at = ?, locked_at = NULL,
                next_attempt_at = NULL, last_http_code = ?, last_error = '' WHERE id = ?")
                ->execute([date('Y-m-d H:i:s'), (int)($online['http_code'] ?? 0), (int)$event['id']]);
            restaurant_sync_queue_mark_exported($pdo, $payload, (string)$event['event_uuid'], (string)$event['payload_sha256']);
            $pdo->prepare("UPDATE offline_sync_runtime SET last_tick_at = ?, last_success_at = ?, last_error = '', updated_at = ? WHERE id = 1")
                ->execute([date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
            $pdo->commit();
            restaurant_sync_queue_log($pdo, $event, $payload, $online, 'success_online', '', $file, $trigger, (int)round((microtime(true) - $started) * 1000));
            $processed[] = ['event_uuid' => $event['event_uuid'], 'event_type' => $event['event_type'], 'counts' => $payload['counts'] ?? [], 'online' => $online, 'file' => $file];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $httpCode = $e instanceof RestaurantSyncQueueHttpException ? $e->httpCode() : 0;
            $retryable = $e instanceof RestaurantSyncQueueHttpException ? $e->retryable() : true;
            $status = $retryable ? 'retry' : 'blocked';
            $next = $retryable ? date('Y-m-d H:i:s', time() + restaurant_sync_queue_retry_delay($attempts)) : null;
            $pdo->prepare('UPDATE offline_sync_outbox SET status = ?, next_attempt_at = ?, locked_at = NULL, last_http_code = ?, last_error = ? WHERE id = ?')
                ->execute([$status, $next, $httpCode, $e->getMessage(), (int)$event['id']]);
            $pdo->prepare('UPDATE offline_sync_runtime SET last_tick_at = ?, last_error = ?, updated_at = ? WHERE id = 1')
                ->execute([date('Y-m-d H:i:s'), $e->getMessage(), date('Y-m-d H:i:s')]);
            restaurant_sync_queue_log($pdo, $event, is_array($payload) ? $payload : [], ['status' => 'error', 'http_code' => $httpCode, 'message' => $e->getMessage()], 'error', $e->getMessage(), $file, $trigger, (int)round((microtime(true) - $started) * 1000));
            $failed = ['event_uuid' => $event['event_uuid'], 'event_type' => $event['event_type'], 'status' => $status, 'http_code' => $httpCode, 'message' => $e->getMessage()];
            break;
        }
    }

    return ['processed' => $processed, 'failed' => $failed, 'queue' => restaurant_sync_queue_counts($pdo)];
}
