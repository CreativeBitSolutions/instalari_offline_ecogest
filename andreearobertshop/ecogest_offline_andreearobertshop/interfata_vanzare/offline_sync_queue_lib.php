<?php

require_once __DIR__ . '/offline_external_config.php';

function offline_sync_queue_config(): array
{
    $config = offline_config_all();
    $clientId = (int)($config['sync_client_id'] ?? $config['client_id'] ?? 0);
    $location = (int)($config['cod_locatie_default'] ?? 1);
    $apiRoot = trim((string)($config['api_root_absolute'] ?? $config['offline_api_path'] ?? ''));
    $caBundlePath = trim((string)($config['ca_bundle_path'] ?? ''));
    if ($caBundlePath === '' && $apiRoot !== '') {
        $caBundlePath = rtrim($apiRoot, "\\/") . DIRECTORY_SEPARATOR . 'certificates' . DIRECTORY_SEPARATOR . 'cacert.pem';
    }
    return [
        'client_id' => $clientId,
        'cod_locatie' => $location,
        'installation_uuid' => preg_replace('/[^A-Za-z0-9_-]/', '_', (string)($config['transaction_uuid'] ?? $config['installation_uuid'] ?? ('client' . $clientId . '_loc' . $location))),
        'profile' => (string)($config['sync_profile'] ?? ($clientId === 2 ? 'dailycoffee' : 'agremprejba')),
        'url' => trim((string)($config['sync_import_url'] ?? '')),
        'api_key' => trim((string)($config['sync_api_key'] ?? '')),
        'ca_bundle_path' => $caBundlePath,
    ];
}

function offline_sync_queue_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ? LIMIT 1");
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function offline_sync_queue_columns(PDO $pdo, string $table): array
{
    if (!offline_sync_queue_table_exists($pdo, $table)) {
        return [];
    }
    $rows = $pdo->query('PRAGMA table_info("' . str_replace('"', '""', $table) . '")')->fetchAll(PDO::FETCH_ASSOC);
    return array_map(static function (array $row): string { return (string)$row['name']; }, $rows);
}

function offline_sync_queue_ensure_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS offline_sync_outbox (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        event_uuid TEXT NOT NULL UNIQUE,
        event_type TEXT NOT NULL,
        aggregate_type TEXT NOT NULL,
        aggregate_id TEXT NOT NULL,
        cod_locatie INTEGER NOT NULL,
        payload_xml TEXT NOT NULL,
        payload_sha256 TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'pending',
        attempts INTEGER NOT NULL DEFAULT 0,
        next_attempt_at TEXT NULL,
        locked_at TEXT NULL,
        last_http_code INTEGER NULL,
        last_error TEXT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        sent_at TEXT NULL
    )");
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_offline_sync_outbox_due ON offline_sync_outbox (status, next_attempt_at, id)');
    $pdo->exec("CREATE TABLE IF NOT EXISTS offline_sync_entity_state (
        entity_type TEXT NOT NULL,
        entity_id TEXT NOT NULL,
        payload_sha256 TEXT NOT NULL,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (entity_type, entity_id)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS offline_sync_runtime (
        id INTEGER PRIMARY KEY CHECK (id = 1),
        lock_token TEXT NULL,
        locked_until TEXT NULL,
        last_tick_at TEXT NULL,
        last_success_at TEXT NULL,
        last_error TEXT NULL
    )");
    $pdo->exec('INSERT OR IGNORE INTO offline_sync_runtime (id) VALUES (1)');
    $pdo->exec("UPDATE offline_sync_outbox SET status = 'retry', locked_at = NULL, next_attempt_at = CURRENT_TIMESTAMP
        WHERE status = 'sending' AND datetime(COALESCE(locked_at, created_at)) < datetime('now', '-3 minutes')");
}

function offline_sync_queue_pk(string $table): string
{
    $map = [
        'note' => 'nrbon', 'det_note' => 'id_vanz', 'discounturi_acordate' => 'id_discount',
        'bonuri_casa_marcat' => 'id', 'inchideri_r_12' => 'id_inch', 'rapoarte_z' => 'id',
        'nir' => 'id_nir', 'achizitii' => 'id_achiz', 'miscari' => 'id', 'log_reglari_casa_marcat' => 'id',
    ];
    return $map[$table] ?? 'id';
}

function offline_sync_queue_rows(PDO $pdo, string $table, string $where = '1=1', array $params = [], string $order = ''): array
{
    if (!offline_sync_queue_table_exists($pdo, $table)) {
        return [];
    }
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        throw new InvalidArgumentException('Tabela locala invalida.');
    }
    $sql = 'SELECT * FROM "' . $table . '" WHERE ' . $where;
    if ($order !== '') {
        $sql .= ' ORDER BY ' . $order;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (function_exists('offline_installation_identity_stabilize_rows')) {
        $rows = offline_installation_identity_stabilize_rows($pdo, $table, $rows, offline_config_all());
    }
    return $rows;
}

function offline_sync_queue_placeholders(array $values): string
{
    return implode(',', array_fill(0, count($values), '?'));
}

function offline_sync_queue_identifier(string $table, array $row, array $config): string
{
    if (trim((string)($row['identificator_offline'] ?? '')) !== '') {
        return substr(trim((string)$row['identificator_offline']), 0, 191);
    }
    $pk = offline_sync_queue_pk($table);
    $value = (string)($row[$pk] ?? reset($row) ?? md5(json_encode($row)));
    $value = preg_replace('/[^A-Za-z0-9_-]/', '_', $value);
    if ((int)$config['client_id'] === 2) {
        $prefix = $config['installation_uuid'] . '_dailycoffee';
        if ($table === 'nir') {
            $nrNir = preg_replace('/[^A-Za-z0-9_-]/', '_', (string)($row['nr_nir'] ?? '0'));
            $idNir = preg_replace('/[^A-Za-z0-9_-]/', '_', (string)($row['id_nir'] ?? '0'));
            return substr($prefix . '_nir_offline_nr_nir_' . $nrNir . '_id_nir_' . $idNir, 0, 191);
        }
        return substr($prefix . '_' . $table . '_' . $value, 0, 191);
    }
    return substr($config['installation_uuid'] . '_' . $table . '_' . $value, 0, 191);
}

function offline_sync_queue_xml(array $tables, int $location): string
{
    $config = offline_sync_queue_config();
    $escape = static function ($value): string {
        return $value === null ? '' : htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    };
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<export client_id="' . $config['client_id'] . '" cod_locatie="' . $location . '" offline_instance="' . $escape($config['installation_uuid']) . '">' . "\n";
    $order = $config['profile'] === 'dailycoffee'
        ? ['rapoarte_z', 'inchideri_r_12', 'note', 'det_note', 'discounturi_acordate', 'bonuri_casa_marcat', 'nir', 'achizitii', 'miscari', 'log_reglari_casa_marcat']
        : ['rapoarte_z', 'inchideri_r_12', 'note', 'det_note', 'discounturi_acordate', 'bonuri_casa_marcat', 'miscari', 'log_reglari_casa_marcat'];
    foreach ($order as $table) {
        $xml .= '  <table name="' . $table . '">' . "\n";
        foreach (($tables[$table] ?? []) as $sourceRow) {
            $row = $sourceRow;
            $row['cod_locatie'] = (int)($row['cod_locatie'] ?? $row['locatie'] ?? $location) ?: $location;
            $row['identificator_offline'] = offline_sync_queue_identifier($table, $row, $config);
            $xml .= "    <row>\n";
            foreach ($row as $column => $value) {
                if (!preg_match('/^[A-Za-z0-9_]+$/', (string)$column)) {
                    continue;
                }
                $xml .= '      <' . $column . '>' . $escape($value) . '</' . $column . ">\n";
            }
            $xml .= "    </row>\n";
        }
        $xml .= "  </table>\n";
    }
    return $xml . "</export>\n";
}

function offline_sync_queue_sale_payload(PDO $pdo, int $nrBon): ?array
{
    $notes = offline_sync_queue_rows($pdo, 'note', "nrbon = ? AND status = 'F'", [$nrBon]);
    if (!$notes) {
        return null;
    }
    $note = $notes[0];
    $location = (int)($note['cod_locatie'] ?? $note['locatie'] ?? offline_sync_queue_config()['cod_locatie']);
    if ($location <= 0) {
        $location = (int)offline_sync_queue_config()['cod_locatie'];
    }
    $details = offline_sync_queue_rows($pdo, 'det_note', 'nr_bon = ?', [$nrBon], 'id_vanz');
    if (!$details) {
        return null;
    }
    $detailIds = array_values(array_filter(array_map(static function (array $row): int { return (int)($row['id_vanz'] ?? 0); }, $details)));
    $tables = ['note' => $notes, 'det_note' => $details];
    $tables['discounturi_acordate'] = $detailIds
        ? offline_sync_queue_rows($pdo, 'discounturi_acordate', 'id_vanz IN (' . offline_sync_queue_placeholders($detailIds) . ')', $detailIds, 'id_discount')
        : [];
    $tables['bonuri_casa_marcat'] = offline_sync_queue_rows($pdo, 'bonuri_casa_marcat', 'nrbon = ?', [$nrBon], 'id');
    $closure = (int)($note['cod_inchidere'] ?? 0);
    $report = (int)($note['nr_raport_z'] ?? 0);
    $tables['inchideri_r_12'] = $closure > 0 ? offline_sync_queue_rows($pdo, 'inchideri_r_12', 'cod_inchidere = ? AND CAST(COALESCE(NULLIF(cod_locatie, 0), locatie, 0) AS INTEGER) = ?', [$closure, $location], 'id_inch') : [];
    $tables['rapoarte_z'] = $report > 0 ? offline_sync_queue_rows($pdo, 'rapoarte_z', 'nr_raport_z = ? AND cod_locatie = ?', [$report, $location], 'id') : [];
    if ((int)offline_sync_queue_config()['client_id'] === 2) {
        $tables['miscari'] = [];
    } else {
        $where = "(fel_doc = 'BF' AND nr_doc = ?) OR nr_nota = ?";
        $params = [$nrBon, $nrBon];
        if ($detailIds) {
            $where .= ' OR id_vanz_fact IN (' . offline_sync_queue_placeholders($detailIds) . ')';
            $params = array_merge($params, $detailIds);
        }
        $tables['miscari'] = offline_sync_queue_rows($pdo, 'miscari', '(' . $where . ')', $params, 'id');
    }
    $tables['log_reglari_casa_marcat'] = [];
    $xml = offline_sync_queue_xml($tables, $location);
    return ['xml' => $xml, 'hash' => hash('sha256', $xml), 'location' => $location];
}

function offline_sync_queue_nir_payload(PDO $pdo, int $idNir): ?array
{
    if ((int)offline_sync_queue_config()['client_id'] !== 2) {
        return null;
    }
    $nirRows = offline_sync_queue_rows($pdo, 'nir', "id_nir = ? AND status = 'F'", [$idNir]);
    if (!$nirRows) {
        return null;
    }
    $nir = $nirRows[0];
    $nrNir = (int)($nir['nr_nir'] ?? 0);
    $location = (int)($nir['cod_locatie'] ?? offline_sync_queue_config()['cod_locatie']);
    $tables = [
        'nir' => $nirRows,
        'achizitii' => offline_sync_queue_rows($pdo, 'achizitii', 'nr_nir = ?', [$nrNir], 'id_achiz'),
        'miscari' => offline_sync_queue_rows($pdo, 'miscari', "fel_doc = 'NIR' AND nr_doc = ?", [$nrNir], 'id'),
    ];
    $xml = offline_sync_queue_xml($tables, $location);
    return ['xml' => $xml, 'hash' => hash('sha256', $xml), 'location' => $location];
}

function offline_sync_queue_store(PDO $pdo, string $eventType, string $aggregateType, string $aggregateId, array $payload): bool
{
    offline_sync_queue_ensure_schema($pdo);
    $config = offline_sync_queue_config();
    $eventUuid = substr($config['installation_uuid'] . ':' . $eventType . ':' . preg_replace('/[^A-Za-z0-9_-]/', '_', $aggregateId) . ':' . substr($payload['hash'], 0, 24), 0, 191);
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO offline_sync_outbox
        (event_uuid, event_type, aggregate_type, aggregate_id, cod_locatie, payload_xml, payload_sha256)
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$eventUuid, $eventType, $aggregateType, $aggregateId, (int)$payload['location'], $payload['xml'], $payload['hash']]);
    $inserted = $stmt->rowCount() > 0;
    $stmt = $pdo->prepare("INSERT INTO offline_sync_entity_state (entity_type, entity_id, payload_sha256, updated_at)
        VALUES (?, ?, ?, CURRENT_TIMESTAMP)
        ON CONFLICT(entity_type, entity_id) DO UPDATE SET payload_sha256 = excluded.payload_sha256, updated_at = CURRENT_TIMESTAMP");
    $stmt->execute([$aggregateType, $aggregateId, $payload['hash']]);
    return $inserted;
}

function offline_sync_enqueue_sale(PDO $pdo, int $nrBon): bool
{
    $payload = offline_sync_queue_sale_payload($pdo, $nrBon);
    return $payload ? offline_sync_queue_store($pdo, 'sale_state', 'sale', (string)$nrBon, $payload) : false;
}

function offline_sync_enqueue_sale_safely(PDO $pdo, int $nrBon): bool
{
    try {
        return offline_sync_enqueue_sale($pdo, $nrBon);
    } catch (Throwable $e) {
        error_log('Nu s-a putut inscrie bonul ' . $nrBon . ' in coada offline: ' . $e->getMessage());
        return false;
    }
}

function offline_sync_enqueue_nir(PDO $pdo, int $idNir): bool
{
    $payload = offline_sync_queue_nir_payload($pdo, $idNir);
    return $payload ? offline_sync_queue_store($pdo, 'nir_finalized', 'nir', (string)$idNir, $payload) : false;
}

function offline_sync_enqueue_nir_safely(PDO $pdo, int $idNir): bool
{
    try {
        return offline_sync_enqueue_nir($pdo, $idNir);
    } catch (Throwable $e) {
        error_log('Nu s-a putut inscrie NIR-ul ' . $idNir . ' in coada offline: ' . $e->getMessage());
        return false;
    }
}

function offline_sync_queue_log_payload(PDO $pdo, int $id): ?array
{
    $rows = offline_sync_queue_rows($pdo, 'log_reglari_casa_marcat', 'id = ?', [$id]);
    if (!$rows) {
        return null;
    }
    $location = (int)($rows[0]['cod_locatie'] ?? $rows[0]['locatie'] ?? offline_sync_queue_config()['cod_locatie']);
    $xml = offline_sync_queue_xml(['log_reglari_casa_marcat' => $rows], $location);
    return ['xml' => $xml, 'hash' => hash('sha256', $xml), 'location' => $location];
}

function offline_sync_queue_discover(PDO $pdo, int $limit = 40): int
{
    offline_sync_queue_ensure_schema($pdo);
    $queued = 0;
    if (offline_sync_queue_table_exists($pdo, 'note')) {
        $stmt = $pdo->prepare("SELECT nrbon FROM note n WHERE status = 'F'
            AND NOT EXISTS (SELECT 1 FROM offline_sync_entity_state s WHERE s.entity_type = 'sale' AND s.entity_id = CAST(n.nrbon AS TEXT))
            ORDER BY nrbon LIMIT ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $saleIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $stmt = $pdo->prepare("SELECT nrbon FROM note WHERE status = 'F' ORDER BY nrbon DESC LIMIT ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $saleIds = array_values(array_unique(array_merge($saleIds, $stmt->fetchAll(PDO::FETCH_COLUMN))));
        foreach ($saleIds as $nrBon) {
            $payload = offline_sync_queue_sale_payload($pdo, (int)$nrBon);
            if (!$payload) {
                continue;
            }
            $state = $pdo->prepare("SELECT payload_sha256 FROM offline_sync_entity_state WHERE entity_type = 'sale' AND entity_id = ?");
            $state->execute([(string)$nrBon]);
            if ((string)$state->fetchColumn() !== $payload['hash']) {
                $queued += offline_sync_queue_store($pdo, 'sale_state', 'sale', (string)$nrBon, $payload) ? 1 : 0;
            }
        }
    }
    if ((int)offline_sync_queue_config()['client_id'] === 2 && offline_sync_queue_table_exists($pdo, 'nir')) {
        $stmt = $pdo->prepare("SELECT id_nir FROM nir n WHERE status = 'F'
            AND NOT EXISTS (SELECT 1 FROM offline_sync_entity_state s WHERE s.entity_type = 'nir' AND s.entity_id = CAST(n.id_nir AS TEXT))
            ORDER BY id_nir LIMIT ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $nirIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $stmt = $pdo->prepare("SELECT id_nir FROM nir WHERE status = 'F' ORDER BY id_nir DESC LIMIT ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $nirIds = array_values(array_unique(array_merge($nirIds, $stmt->fetchAll(PDO::FETCH_COLUMN))));
        foreach ($nirIds as $idNir) {
            $payload = offline_sync_queue_nir_payload($pdo, (int)$idNir);
            if (!$payload) {
                continue;
            }
            $state = $pdo->prepare("SELECT payload_sha256 FROM offline_sync_entity_state WHERE entity_type = 'nir' AND entity_id = ?");
            $state->execute([(string)$idNir]);
            if ((string)$state->fetchColumn() !== $payload['hash']) {
                $queued += offline_sync_queue_store($pdo, 'nir_finalized', 'nir', (string)$idNir, $payload) ? 1 : 0;
            }
        }
    }
    if (offline_sync_queue_table_exists($pdo, 'log_reglari_casa_marcat')) {
        $stmt = $pdo->prepare("SELECT id FROM log_reglari_casa_marcat l
            WHERE NOT EXISTS (SELECT 1 FROM offline_sync_entity_state s WHERE s.entity_type = 'cash_adjustment' AND s.entity_id = CAST(l.id AS TEXT))
            ORDER BY id LIMIT ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $payload = offline_sync_queue_log_payload($pdo, (int)$id);
            if ($payload) {
                $queued += offline_sync_queue_store($pdo, 'cash_adjustment', 'cash_adjustment', (string)$id, $payload) ? 1 : 0;
            }
        }
    }
    return $queued;
}

function offline_sync_queue_counts(PDO $pdo): array
{
    offline_sync_queue_ensure_schema($pdo);
    $counts = ['pending' => 0, 'retry' => 0, 'sending' => 0, 'sent' => 0, 'blocked' => 0];
    foreach ($pdo->query('SELECT status, COUNT(*) AS total FROM offline_sync_outbox GROUP BY status') as $row) {
        $counts[(string)$row['status']] = (int)$row['total'];
    }
    return $counts;
}
