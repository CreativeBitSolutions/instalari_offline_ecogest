<?php
declare(strict_types=1);

require_once __DIR__ . '/database_connection.php';
require_once __DIR__ . '/offline_sync_queue_lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
date_default_timezone_set('Europe/Bucharest');

function restaurant_sync_status_exit(array $payload, int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function restaurant_sync_status_scalar(PDO $pdo, string $sql, array $params = []): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function restaurant_sync_status_rows(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function restaurant_sync_status_decode_counts($value): array
{
    $decoded = json_decode((string)$value, true);
    if (!is_array($decoded)) {
        return [];
    }

    $result = [];
    foreach ($decoded as $key => $count) {
        $result[(string)$key] = (int)$count;
    }
    return $result;
}

function restaurant_sync_status_lock_state(): bool
{
    if (!defined('RESTAURANT_OFFLINE_API_DIR')) {
        return false;
    }

    $path = RESTAURANT_OFFLINE_API_DIR . DIRECTORY_SEPARATOR . 'offline_sales_sync.lock';
    $handle = @fopen($path, 'c');
    if ($handle === false) {
        return false;
    }

    $available = @flock($handle, LOCK_SH | LOCK_NB);
    if ($available) {
        @flock($handle, LOCK_UN);
    }
    fclose($handle);

    return !$available;
}

try {
    if (!isset($_SESSION['admin_id'])) {
        restaurant_sync_status_exit([
            'status' => 'error',
            'message' => 'Sesiunea a expirat.',
        ], 401);
    }

    if (!function_exists('restaurantIsOfflineSqlite') || !restaurantIsOfflineSqlite()) {
        restaurant_sync_status_exit([
            'status' => 'error',
            'message' => 'Situația sincronizării este disponibilă doar în modul offline SQLite.',
        ], 409);
    }

    $actorId = (int)$_SESSION['admin_id'];
    $actorStmt = $pdo->prepare('SELECT admin_id, rank, locatie FROM admins_12 WHERE admin_id = ? LIMIT 1');
    $actorStmt->execute([$actorId]);
    $actor = $actorStmt->fetch(PDO::FETCH_ASSOC);
    if (!$actor) {
        restaurant_sync_status_exit(['status' => 'error', 'message' => 'Utilizator invalid.'], 403);
    }

    $rank = strtolower(trim((string)($actor['rank'] ?? '')));
    if (!in_array($rank, ['sefsala', 'administrator', 'admin'], true)) {
        restaurant_sync_status_exit([
            'status' => 'error',
            'message' => 'Situația sincronizării poate fi consultată doar din contul șefului de sală.',
        ], 403);
    }

    $codLocatie = (int)($actor['locatie'] ?? 0);
    if ($codLocatie <= 0) {
        $codLocatie = (int)($restaurantConfig['cod_locatie'] ?? ($_SESSION['cod_locatie'] ?? 0));
    }
    if ($codLocatie <= 0) {
        throw new RuntimeException('Locația nu a putut fi determinată.');
    }

    $syncConfig = isset($restaurantConfig['offline_sales_sync']) && is_array($restaurantConfig['offline_sales_sync'])
        ? $restaurantConfig['offline_sales_sync']
        : [];
    $automatic = filter_var($syncConfig['automatic'] ?? false, FILTER_VALIDATE_BOOL);
    $enabled = filter_var($syncConfig['enabled'] ?? false, FILTER_VALIDATE_BOOL);
    $strict = filter_var($syncConfig['strict'] ?? true, FILTER_VALIDATE_BOOL);
    $intervalSeconds = max(60, (int)($syncConfig['automatic_interval_seconds'] ?? 120));
    $queueConfig = restaurant_sync_queue_config($restaurantConfig);
    restaurant_sync_queue_discover($pdo, $queueConfig, $actorId);
    $queueCounts = restaurant_sync_queue_counts($pdo);

    $params = [':locatie' => $codLocatie];
    $eligibleCondition = "
        n.locatie = :locatie
        AND n.status = 'F'
        AND NOT EXISTS (
            SELECT 1
            FROM offline_sync_exported e
            WHERE e.source_table = 'note'
              AND e.source_pk = CAST(n.nrbon AS TEXT)
              AND e.cod_locatie = n.locatie
        )
    ";

    $counts = [
        'eligible_notes' => restaurant_sync_status_scalar($pdo, "SELECT COUNT(*) FROM note n WHERE {$eligibleCondition}", $params),
        'eligible_lines' => restaurant_sync_status_scalar($pdo, "
            SELECT COUNT(*)
            FROM det_note d
            INNER JOIN note n ON n.nrbon = d.nr_bon
            WHERE {$eligibleCondition}
        ", $params),
        'waiting_closure' => restaurant_sync_status_scalar($pdo, "
            SELECT COUNT(*) FROM note n
            WHERE n.locatie = :locatie AND n.status = 'F' AND COALESCE(n.cod_inchidere, 0) = 0
        ", $params),
        'waiting_z' => restaurant_sync_status_scalar($pdo, "
            SELECT COUNT(*) FROM note n
            WHERE n.locatie = :locatie AND n.status = 'F'
              AND COALESCE(n.cod_inchidere, 0) <> 0 AND COALESCE(n.nr_raport_z, 0) = 0
        ", $params),
        'open_notes' => restaurant_sync_status_scalar($pdo, "
            SELECT COUNT(*) FROM note n WHERE n.locatie = :locatie AND n.status = 'S'
        ", $params),
        'exported_notes' => restaurant_sync_status_scalar($pdo, "
            SELECT COUNT(*) FROM offline_sync_exported e
            WHERE e.cod_locatie = :locatie AND e.source_table = 'note'
        ", $params),
        'attempts' => restaurant_sync_status_scalar($pdo, "
            SELECT COUNT(*) FROM offline_sync_logs l WHERE l.cod_locatie = :locatie
        ", $params),
        'successful_attempts' => restaurant_sync_status_scalar($pdo, "
            SELECT COUNT(*) FROM offline_sync_logs l
            WHERE l.cod_locatie = :locatie AND l.status IN ('success', 'success_online')
        ", $params),
        'failed_attempts' => restaurant_sync_status_scalar($pdo, "
            SELECT COUNT(*) FROM offline_sync_logs l
            WHERE l.cod_locatie = :locatie AND l.status IN ('error', 'success_local_online_error')
        ", $params),
        'queue_pending' => (int)$queueCounts['pending'],
        'queue_sending' => (int)$queueCounts['sending'],
        'queue_retry' => (int)$queueCounts['retry'],
        'queue_blocked' => (int)$queueCounts['blocked'],
        'queue_sent' => (int)$queueCounts['sent'],
    ];

    $pending = restaurant_sync_status_rows($pdo, "
        SELECT n.nrbon, n.data_bon, n.ora_bon, n.data_deschidere, n.operator,
               TRIM(COALESCE(a.admin_firstname, '') || ' ' || COALESCE(a.admin_lastname, '')) AS operator_nume,
               n.valoare_vanzare_cu_tva, n.cod_inchidere, n.nr_raport_z,
               (SELECT COUNT(*) FROM det_note d WHERE d.nr_bon = n.nrbon) AS linii
        FROM note n
        LEFT JOIN admins_12 a ON a.admin_id = n.operator
        WHERE {$eligibleCondition}
        ORDER BY COALESCE(NULLIF(n.data_bon, ''), n.data_deschidere) ASC, n.nrbon ASC
        LIMIT 100
    ", $params);

    foreach ($pending as &$row) {
        $row['nrbon'] = (int)$row['nrbon'];
        $row['operator'] = (int)$row['operator'];
        $row['valoare_vanzare_cu_tva'] = (float)$row['valoare_vanzare_cu_tva'];
        $row['cod_inchidere'] = (int)$row['cod_inchidere'];
        $row['nr_raport_z'] = (int)$row['nr_raport_z'];
        $row['linii'] = (int)$row['linii'];
    }
    unset($row);

    $notEligible = restaurant_sync_status_rows($pdo, "
        SELECT n.nrbon, n.data_bon, n.ora_bon, n.data_deschidere, n.operator,
               TRIM(COALESCE(a.admin_firstname, '') || ' ' || COALESCE(a.admin_lastname, '')) AS operator_nume,
               n.valoare_vanzare_cu_tva, n.cod_inchidere, n.nr_raport_z,
               CASE
                   WHEN COALESCE(n.cod_inchidere, 0) = 0 THEN 'Lipsește închiderea de tură'
                   WHEN COALESCE(n.nr_raport_z, 0) = 0 THEN 'Lipsește raportul Z'
                   ELSE 'Nota nu este pregătită'
               END AS motiv
        FROM note n
        LEFT JOIN admins_12 a ON a.admin_id = n.operator
        WHERE n.locatie = :locatie
          AND n.status = 'F'
          AND (COALESCE(n.cod_inchidere, 0) = 0 OR COALESCE(n.nr_raport_z, 0) = 0)
        ORDER BY COALESCE(NULLIF(n.data_bon, ''), n.data_deschidere) ASC, n.nrbon ASC
        LIMIT 100
    ", $params);

    foreach ($notEligible as &$row) {
        $row['nrbon'] = (int)$row['nrbon'];
        $row['operator'] = (int)$row['operator'];
        $row['valoare_vanzare_cu_tva'] = (float)$row['valoare_vanzare_cu_tva'];
        $row['cod_inchidere'] = (int)$row['cod_inchidere'];
        $row['nr_raport_z'] = (int)$row['nr_raport_z'];
    }
    unset($row);

    $history = restaurant_sync_status_rows($pdo, "
        SELECT o.id, o.event_uuid AS export_id, o.event_type, o.aggregate_type, o.aggregate_id,
               o.created_at AS data_ora, o.cod_locatie, o.payload_json, o.payload_sha256 AS payload_hash,
               o.status AS queue_status, o.attempts, o.next_attempt_at, o.sent_at,
               o.last_http_code, o.last_error,
               COALESCE(l.declansare, 'coada') AS declansare,
               COALESCE(l.durata_ms, 0) AS durata_ms,
               COALESCE(l.online_status, '') AS online_status,
               COALESCE(l.online_http_code, o.last_http_code, 0) AS online_http_code,
                COALESCE(l.online_message, o.last_error, '') AS online_message,
                COALESCE(l.online_inserted_json, '') AS online_inserted_json,
                COALESCE(l.online_duplicates_json, '') AS online_duplicates_json,
                COALESCE(l.online_updated_json, '') AS online_updated_json
        FROM offline_sync_outbox o
        LEFT JOIN offline_sync_logs l ON l.id = (
            SELECT MAX(l2.id) FROM offline_sync_logs l2 WHERE l2.export_id = o.event_uuid
        )
        WHERE o.cod_locatie = :locatie
        ORDER BY o.id DESC
        LIMIT 75
    ", $params);

    foreach ($history as &$row) {
        $payloadRow = json_decode((string)($row['payload_json'] ?? ''), true);
        $payloadRow = is_array($payloadRow) ? $payloadRow : [];
        $payloadCounts = is_array($payloadRow['counts'] ?? null) ? $payloadRow['counts'] : [];
        $payloadActor = is_array($payloadRow['utilizator_sync'] ?? null) ? $payloadRow['utilizator_sync'] : [];
        $row['utilizator_id'] = (int)($payloadActor['id'] ?? 0);
        $row['utilizator_nume'] = (string)($payloadActor['nume'] ?? '');
        $row['note_count'] = (int)($payloadCounts['note'] ?? 0);
        $row['det_note_count'] = (int)($payloadCounts['det_note'] ?? 0);
        $row['inchideri_count'] = (int)($payloadCounts['inchideri_r_12'] ?? 0);
        $row['rapoarte_z_count'] = (int)($payloadCounts['rapoarte_z'] ?? 0);
        $row['discounturi_count'] = (int)($payloadCounts['discounturi_acordate'] ?? 0);
        unset($row['payload_json']);
        foreach (['id', 'utilizator_id', 'cod_locatie', 'note_count', 'det_note_count', 'inchideri_count', 'rapoarte_z_count', 'discounturi_count', 'durata_ms', 'online_http_code', 'attempts', 'last_http_code'] as $field) {
            $row[$field] = (int)($row[$field] ?? 0);
        }
        $filePath = rtrim((string)$queueConfig['export_path'], '/\\') . DIRECTORY_SEPARATOR
            . preg_replace('/[^A-Za-z0-9_.-]/', '_', (string)$row['export_id']) . '.json';
        $row['fisier_nume'] = $filePath !== '' ? basename($filePath) : '';
        $row['fisier_exista'] = $filePath !== '' && is_file($filePath);
        $row['fisier_marime'] = $row['fisier_exista'] ? (int)filesize($filePath) : 0;
        $row['payload_hash_scurt'] = substr((string)($row['payload_hash'] ?? ''), 0, 16);
        unset($row['payload_hash']);
        $row['online_inserted'] = restaurant_sync_status_decode_counts($row['online_inserted_json'] ?? '');
        $row['online_duplicates'] = restaurant_sync_status_decode_counts($row['online_duplicates_json'] ?? '');
        $row['online_updated'] = restaurant_sync_status_decode_counts($row['online_updated_json'] ?? '');
        unset($row['online_inserted_json'], $row['online_duplicates_json'], $row['online_updated_json']);
        $row['erori'] = (string)($row['last_error'] ?? '');
        $row['status'] = $row['queue_status'] === 'sent' ? 'success_online' : (string)$row['queue_status'];
    }
    unset($row);

    $lastSuccess = restaurant_sync_status_rows($pdo, "
        SELECT data_ora, export_id
        FROM offline_sync_logs
        WHERE cod_locatie = :locatie AND status IN ('success', 'success_online')
        ORDER BY id DESC LIMIT 1
    ", $params);
    $lastFailure = restaurant_sync_status_rows($pdo, "
        SELECT data_ora, erori
        FROM offline_sync_logs
        WHERE cod_locatie = :locatie AND status IN ('error', 'success_local_online_error')
        ORDER BY id DESC LIMIT 1
    ", $params);

    $tabletRuntime = restaurant_sync_status_rows($pdo, "SELECT * FROM offline_tablet_sync_runtime WHERE id = 1");
    $tabletCounts = [
        'waiting_import' => restaurant_sync_status_scalar($pdo, "SELECT COUNT(*) FROM com_tableta WHERE locatie = :locatie AND stare = 'TRIMISA'", $params),
        'imported_local' => restaurant_sync_status_scalar($pdo, "SELECT COUNT(*) FROM com_tableta WHERE locatie = :locatie AND stare = 'IMPORTATA'", $params),
        'ack_pending' => restaurant_sync_status_scalar($pdo, "SELECT COUNT(*) FROM com_tableta WHERE locatie = :locatie AND online_ack_status IN ('pending', 'retry')", $params),
        'ack_sent' => restaurant_sync_status_scalar($pdo, "SELECT COUNT(*) FROM com_tableta WHERE locatie = :locatie AND online_ack_status = 'sent'", $params),
    ];
    $tabletPending = restaurant_sync_status_rows($pdo, "
        SELECT ct.nrbon, ct.data_bon, ct.ora_bon, ct.cod_masa, ct.operator AS tablet_admin_id,
               ct.owner_operator_id, ct.owner_operator_name, ct.valoare_vanzare_cu_tva,
               ct.fetched_at, ct.payload_hash,
               (SELECT COUNT(*) FROM det_com_tableta d WHERE d.nr_bon = ct.nrbon) AS linii
        FROM com_tableta ct
        WHERE ct.locatie = :locatie AND ct.stare = 'TRIMISA'
        ORDER BY ct.nrbon ASC
        LIMIT 100
    ", $params);
    $tabletHistory = restaurant_sync_status_rows($pdo, "
        SELECT id, action, status, data_ora, received_count, inserted_count, updated_count,
               acknowledged_count, http_code, message
        FROM offline_tablet_sync_logs
        ORDER BY id DESC
        LIMIT 75
    ");
    foreach ($tabletPending as &$tabletRow) {
        foreach (['nrbon', 'cod_masa', 'tablet_admin_id', 'owner_operator_id', 'linii'] as $field) {
            $tabletRow[$field] = (int)($tabletRow[$field] ?? 0);
        }
        $tabletRow['valoare_vanzare_cu_tva'] = (float)($tabletRow['valoare_vanzare_cu_tva'] ?? 0);
        $tabletRow['payload_hash_scurt'] = substr((string)($tabletRow['payload_hash'] ?? ''), 0, 16);
        unset($tabletRow['payload_hash']);
    }
    unset($tabletRow);

    restaurant_sync_status_exit([
        'status' => 'success',
        'generated_at' => date('Y-m-d H:i:s'),
        'client_id' => (int)($restaurantConfig['client_id'] ?? ($_SESSION['client_id'] ?? 0)),
        'cod_locatie' => $codLocatie,
        'configuration' => [
            'enabled' => $enabled,
            'automatic' => $automatic,
            'interval_seconds' => $intervalSeconds,
            'strict_confirmation' => $strict,
            'in_progress' => restaurant_sync_status_lock_state(),
            'selection_rule' => "Nota F se trimite imediat. Închiderea turei și raportul Z se transmit ulterior prin evenimente separate.",
        ],
        'counts' => $counts,
        'last_success' => $lastSuccess[0] ?? null,
        'last_failure' => $lastFailure[0] ?? null,
        'pending' => $pending,
        'not_eligible' => $notEligible,
        'history' => $history,
        'tablet_sync' => [
            'runtime' => $tabletRuntime[0] ?? null,
            'counts' => $tabletCounts,
            'pending' => $tabletPending,
            'history' => $tabletHistory,
        ],
    ]);
} catch (Throwable $e) {
    restaurant_sync_status_exit([
        'status' => 'error',
        'message' => 'Situația sincronizării nu a putut fi citită: ' . $e->getMessage(),
    ], 500);
}
