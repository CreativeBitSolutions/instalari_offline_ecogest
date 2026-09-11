<?php
require_once __DIR__ . '/offline_external_config.php';
require_once __DIR__ . '/offline_sync_queue_lib.php';

function offline_sequence_config(): array
{
    static $config;
    if (is_array($config)) {
        return $config;
    }

    $decoded = offline_config_all();
    $clientId = (int)($decoded['sync_client_id'] ?? $decoded['client_id'] ?? 0);
    $location = (int)($decoded['cod_locatie_default'] ?? 1);
    $fallback = 'client' . $clientId . '_loc' . $location . '_pos1';

    $config = [
        'client_id' => $clientId,
        'cod_locatie' => $location,
        'installation_uuid' => preg_replace('/[^A-Za-z0-9_-]/', '_', (string)($decoded['transaction_uuid'] ?? $decoded['installation_uuid'] ?? $fallback)),
    ];
    return $config;
}

function offline_raport_z_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ? LIMIT 1");
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function offline_raport_z_table_columns(PDO $pdo, string $table): array
{
    if (!offline_raport_z_table_exists($pdo, $table)) {
        return [];
    }

    $columns = [];
    $quoted = '"' . str_replace('"', '""', $table) . '"';
    foreach ($pdo->query('PRAGMA table_info(' . $quoted . ')') as $row) {
        $columns[strtolower((string)($row['name'] ?? ''))] = true;
    }
    return $columns;
}

function offline_raport_z_ensure_schema(PDO $pdo): void
{
    $definitions = [
        'serie_casa_marcat' => "TEXT NOT NULL DEFAULT ''",
        'nui' => 'INTEGER NOT NULL DEFAULT 0',
        'serie_memorie_fiscala' => "TEXT NOT NULL DEFAULT ''",
    ];

    foreach (['date_firma', 'loc_mese_12', 'rapoarte_z', 'note', 'inchideri_r_12', 'miscari'] as $table) {
        $columns = offline_raport_z_table_columns($pdo, $table);
        if (!$columns) {
            continue;
        }

        $quotedTable = '"' . str_replace('"', '""', $table) . '"';
        foreach ($definitions as $column => $definition) {
            if (isset($columns[$column])) {
                continue;
            }
            $quotedColumn = '"' . str_replace('"', '""', $column) . '"';
            $pdo->exec("ALTER TABLE {$quotedTable} ADD COLUMN {$quotedColumn} {$definition}");
        }
    }

    $columns = offline_raport_z_table_columns($pdo, 'rapoarte_z');
    if (!$columns || !isset($columns['cod_locatie'], $columns['serie_casa_marcat'], $columns['nr_raport_z'])) {
        return;
    }

    try {
        $duplicateSql = "SELECT COUNT(*) FROM (
            SELECT cod_locatie,
                   COALESCE(serie_casa_marcat, ''),
                   COALESCE(nui, 0),
                   COALESCE(serie_memorie_fiscala, ''),
                   nr_raport_z
            FROM rapoarte_z
            GROUP BY cod_locatie,
                     COALESCE(serie_casa_marcat, ''),
                     COALESCE(nui, 0),
                     COALESCE(serie_memorie_fiscala, ''),
                     nr_raport_z
            HAVING COUNT(*) > 1
        )";
        $duplicates = (int)$pdo->query($duplicateSql)->fetchColumn();
        if ($duplicates > 0) {
            error_log('Indexul fiscal extins pentru rapoarte_z nu a fost schimbat deoarece exista duplicate istorice.');
            return;
        }

        $indexes = $pdo->query("PRAGMA index_list('rapoarte_z')")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($indexes as $index) {
            $name = strtolower((string)($index['name'] ?? ''));
            if ($name === 'uq_rapoarte_z_offline_fiscal') {
                $pdo->exec('DROP INDEX IF EXISTS "uq_rapoarte_z_offline_fiscal"');
            }
        }

        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_rapoarte_z_offline_fiscal_identity
            ON rapoarte_z (cod_locatie, serie_casa_marcat, nui, serie_memorie_fiscala, nr_raport_z)');
    } catch (Throwable $e) {
        error_log('Nu s-a putut actualiza indexul fiscal local pentru rapoarte_z: ' . $e->getMessage());
    }
}

function offline_raport_z_current_identification(PDO $pdo, int $location): array
{
    $dateFirma = [];
    if (offline_raport_z_table_exists($pdo, 'date_firma')) {
        $dateFirma = $pdo->query('SELECT * FROM date_firma LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    $locMese = [];
    $locColumns = offline_raport_z_table_columns($pdo, 'loc_mese_12');
    if ($locColumns) {
        $where = '';
        $params = [];
        if (isset($locColumns['cod_locatie'])) {
            $where = ' WHERE cod_locatie = ?';
            $params[] = $location;
        } elseif (isset($locColumns['locatie'])) {
            $where = ' WHERE locatie = ?';
            $params[] = $location;
        }
        $stmt = $pdo->prepare('SELECT * FROM loc_mese_12' . $where . ' LIMIT 1');
        $stmt->execute($params);
        $locMese = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    $series = trim((string)($dateFirma['serie_casa_marcat'] ?? ''));
    if ($series === '') {
        $series = trim((string)($locMese['serie_casa_marcat'] ?? ''));
    }

    $nui = (int)($dateFirma['nui'] ?? 0);
    if ($nui <= 0) {
        $nui = max(0, (int)($locMese['nui'] ?? 0));
    }

    $memory = trim((string)($dateFirma['serie_memorie_fiscala'] ?? ''));
    if ($memory === '' || $memory === '0') {
        $memory = trim((string)($locMese['serie_memorie_fiscala'] ?? ''));
    }
    if ($memory === '0') {
        $memory = '';
    }

    return [
        'serie_casa_marcat' => $series,
        'nui' => $nui,
        'serie_memorie_fiscala' => $memory,
    ];
}

function offline_raport_z_next_number(PDO $pdo, int $location, int $nui = -1, string $memory = ''): int
{
    offline_raport_z_ensure_schema($pdo);
    $identity = offline_raport_z_current_identification($pdo, $location);
    if ($nui < 0) {
        $nui = $identity['nui'];
    }
    if ($memory === '') {
        $memory = $identity['serie_memorie_fiscala'];
    }

    if (!offline_raport_z_table_exists($pdo, 'rapoarte_z')) {
        return 1;
    }

    $stmt = $pdo->prepare("SELECT COALESCE(MAX(nr_raport_z), 0) + 1
        FROM rapoarte_z
        WHERE cod_locatie = ?
          AND COALESCE(serie_casa_marcat, '') = ?
          AND COALESCE(nui, 0) = ?
          AND COALESCE(serie_memorie_fiscala, '') = ?");
    $stmt->execute([$location, $identity['serie_casa_marcat'], $nui, $memory]);
    return max(1, (int)$stmt->fetchColumn());
}

function offline_raport_z_sequence_key(string $series, int $nui, string $memory): string
{
    return $series . '|nui=' . $nui . '|mem=' . rawurlencode($memory);
}


function offline_sequence_ensure_schema(PDO $pdo): void
{
    require_once __DIR__ . '/tools/sqlite_schema.php';
    bestmixt_sqlite_apply_schema_if_needed($pdo);
    offline_raport_z_ensure_schema($pdo);
}

function offline_sequence_record(PDO $pdo, string $name, int $value, int $location, string $series = '', string $event = 'generated', string $referenceType = '', string $referenceId = ''): void
{
    $config = offline_sequence_config();
    $stmt = $pdo->prepare("INSERT INTO offline_sequence_state
        (installation_uuid, client_id, cod_locatie, serie_casa_marcat, sequence_name, last_local_value, updated_at)
        VALUES (:uuid, :client, :location, :series, :name, :value, CURRENT_TIMESTAMP)
        ON CONFLICT(installation_uuid, cod_locatie, serie_casa_marcat, sequence_name)
        DO UPDATE SET last_local_value = MAX(last_local_value, excluded.last_local_value), updated_at = CURRENT_TIMESTAMP");
    $stmt->execute([
        ':uuid' => $config['installation_uuid'], ':client' => $config['client_id'],
        ':location' => $location, ':series' => $series, ':name' => $name, ':value' => $value,
    ]);

    $stmt = $pdo->prepare("INSERT INTO offline_sequence_events
        (installation_uuid, client_id, cod_locatie, serie_casa_marcat, sequence_name, sequence_value, event_type, reference_type, reference_id)
        VALUES (:uuid, :client, :location, :series, :name, :value, :event, :reference_type, :reference_id)");
    $stmt->execute([
        ':uuid' => $config['installation_uuid'], ':client' => $config['client_id'],
        ':location' => $location, ':series' => $series, ':name' => $name, ':value' => $value,
        ':event' => $event, ':reference_type' => $referenceType, ':reference_id' => $referenceId,
    ]);
}

function offline_sequence_apply_online_state(PDO $pdo, array $state, int $location): void
{
    $config = offline_sequence_config();
    $stmt = $pdo->prepare("INSERT INTO offline_sequence_state
        (installation_uuid, client_id, cod_locatie, serie_casa_marcat, sequence_name, last_online_value, last_synced_at, updated_at)
        VALUES (:uuid, :client, :location, :series, :name, :value, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ON CONFLICT(installation_uuid, cod_locatie, serie_casa_marcat, sequence_name)
        DO UPDATE SET last_online_value = MAX(last_online_value, excluded.last_online_value), last_synced_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP");
    foreach ($state as $key => $value) {
        $name = (string)$key;
        $series = '';
        if (strpos($name, 'nr_raport_z:') === 0) {
            $series = substr($name, strlen('nr_raport_z:'));
            $name = 'nr_raport_z';
        }
        $stmt->execute([
            ':uuid' => $config['installation_uuid'], ':client' => $config['client_id'],
            ':location' => $location, ':series' => $series, ':name' => $name, ':value' => (int)$value,
        ]);
    }
}

function offline_sequence_next_closure(PDO $pdo, string $table, int $location): int
{
    $config = offline_sequence_config();
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(cod_inchidere), 0) FROM {$table} WHERE CAST(COALESCE(NULLIF(cod_locatie, 0), locatie, 0) AS INTEGER) = :location");
    $stmt->bindValue(':location', $location, PDO::PARAM_INT);
    $stmt->execute();
    $tableValue = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(MAX(last_local_value), 0) FROM offline_sequence_state
        WHERE installation_uuid = ? AND cod_locatie = ? AND sequence_name = 'cod_inchidere'");
    $stmt->execute([$config['installation_uuid'], $location]);
    return max($tableValue, (int)$stmt->fetchColumn()) + 1;
}

function offline_pending_closures(PDO $pdo, int $location): array
{
    if ($location <= 0) {
        return [];
    }

    $sql = "SELECT
                i.cod_inchidere,
                i.operator,
                i.data_inchiderii,
                i.ora_inchiderii,
                i.valoare_cu_tva,
                i.tva_colectata,
                COUNT(n.nrbon) AS bonuri,
                COALESCE(SUM(n.numerar - n.rest), 0) AS numerar,
                COALESCE(SUM(n.card), 0) AS card,
                COALESCE(SUM(n.tichete), 0) AS tichete_masa,
                COALESCE(SUM(n.glovo), 0) AS plata_moderna,
                COALESCE(SUM(n.protocol), 0) + COALESCE(SUM(n.virament_bancar), 0) AS alte_metode,
                COALESCE(SUM(n.rest), 0) AS rest,
                MIN(n.data_bon || ' ' || n.ora_bon) AS primul_bon,
                MAX(n.data_bon || ' ' || n.ora_bon) AS ultimul_bon
            FROM inchideri_r_12 i
            INNER JOIN note n
                ON n.cod_inchidere = i.cod_inchidere
               AND n.locatie = :location
               AND n.status = 'F'
               AND COALESCE(n.nr_raport_z, 0) = 0
            WHERE CAST(COALESCE(NULLIF(i.cod_locatie, 0), i.locatie, 0) AS INTEGER) = :location
              AND COALESCE(i.nr_raport_z, 0) = 0
            GROUP BY i.cod_inchidere, i.operator, i.data_inchiderii, i.ora_inchiderii,
                     i.valoare_cu_tva, i.tva_colectata
            ORDER BY i.data_inchiderii, i.ora_inchiderii, i.cod_inchidere";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':location' => $location]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function offline_close_shift(PDO $pdo, string $notesTable, string $closuresTable, int $location, int $operator, string $date, string $time): int
{
    $pdo->exec('BEGIN IMMEDIATE TRANSACTION');
    try {
        offline_raport_z_ensure_schema($pdo);
        $identity = offline_raport_z_current_identification($pdo, $location);
        $nui = $identity['nui'];
        $memory = $identity['serie_memorie_fiscala'];
        $code = offline_sequence_next_closure($pdo, $closuresTable, $location);
        $stmt = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(valoare_vanzare_cu_tva), 0), COALESCE(SUM(tva_colectata), 0)
            FROM {$notesTable} WHERE COALESCE(cod_inchidere, 0) = 0 AND status = 'F' AND locatie = :location AND operator = :operator
              AND (COALESCE(nui, 0) = :nui OR COALESCE(nui, 0) = 0)
              AND (COALESCE(serie_memorie_fiscala, '') = :memory OR COALESCE(serie_memorie_fiscala, '') = '')");
        $stmt->execute([':location' => $location, ':operator' => $operator, ':nui' => $nui, ':memory' => $memory]);
        [$saleCount, $total, $vat] = $stmt->fetch(PDO::FETCH_NUM);
        if ((int)$saleCount === 0) {
            throw new RuntimeException('Nu exista bonuri finalizate pentru inchiderea turei curente.');
        }

        $config = offline_sequence_config();
        $identifier = $config['installation_uuid'] . '_inchideri_r_12_' . $code;
        $stmt = $pdo->prepare("INSERT INTO {$closuresTable}
            (cod_inchidere, operator, valoare_cu_tva, tva_colectata, data_inchiderii, ora_inchiderii, locatie, cod_locatie, nui, serie_memorie_fiscala, identificator_offline)
            VALUES (:code, :operator, :total, :vat, :date, :time, :location, :location, :nui, :memory, :identifier)");
        $stmt->execute([
            ':code' => $code, ':operator' => $operator, ':total' => $total, ':vat' => $vat,
            ':date' => $date, ':time' => $time, ':location' => $location, ':nui' => $nui, ':memory' => $memory, ':identifier' => $identifier,
        ]);

        $stmt = $pdo->prepare("UPDATE {$notesTable} SET cod_inchidere = :code, cod_locatie = :location, nui = :nui, serie_memorie_fiscala = :memory
            WHERE COALESCE(cod_inchidere, 0) = 0 AND status = 'F' AND locatie = :location AND operator = :operator
              AND (COALESCE(nui, 0) = :filter_nui OR COALESCE(nui, 0) = 0)
              AND (COALESCE(serie_memorie_fiscala, '') = :filter_memory OR COALESCE(serie_memorie_fiscala, '') = '')");
        $stmt->execute([':code' => $code, ':location' => $location, ':operator' => $operator, ':nui' => $nui, ':memory' => $memory, ':filter_nui' => $nui, ':filter_memory' => $memory]);
        offline_sequence_record($pdo, 'cod_inchidere', $code, $location, '', 'shift_closed', 'inchideri_r_12', $identifier);
        $stmt = $pdo->prepare("SELECT nrbon FROM {$notesTable} WHERE status = 'F' AND locatie = ? AND cod_inchidere = ? AND COALESCE(nui, 0) = ? AND COALESCE(serie_memorie_fiscala, '') = ? ORDER BY nrbon");
        $stmt->execute([$location, $code, $nui, $memory]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $nrBon) {
            offline_sync_enqueue_sale_safely($pdo, (int)$nrBon);
        }
        $pdo->exec('COMMIT');
        return $code;
    } catch (Throwable $e) {
        try {
            $pdo->exec('ROLLBACK');
        } catch (Throwable $rollbackError) {
        }
        throw $e;
    }
}

function offline_close_z_report(PDO $pdo, int $location, int $reportNumber, array $closureCodes, array $payments, string $dateTime): void
{
    if ($reportNumber <= 0) {
        throw new InvalidArgumentException('Numarul raportului Z trebuie sa fie pozitiv.');
    }

    $closureCodes = array_values(array_unique(array_filter(array_map('intval', $closureCodes), static function (int $code): bool {
        return $code > 0;
    })));
    if (!$closureCodes) {
        throw new InvalidArgumentException('Selectati cel putin o tura pentru raportul Z.');
    }

    $pdo->exec('BEGIN IMMEDIATE TRANSACTION');
    try {
        offline_raport_z_ensure_schema($pdo);
        $identity = offline_raport_z_current_identification($pdo, $location);
        $series = $identity['serie_casa_marcat'];
        $nui = $identity['nui'];
        $memory = $identity['serie_memorie_fiscala'];
        if ($reportNumber <= 0) {
            $reportNumber = offline_raport_z_next_number($pdo, $location, $nui, $memory);
        }

        $stmt = $pdo->prepare("SELECT 1 FROM rapoarte_z
            WHERE cod_locatie = ?
              AND COALESCE(serie_casa_marcat, '') = ?
              AND COALESCE(nui, 0) = ?
              AND COALESCE(serie_memorie_fiscala, '') = ?
              AND nr_raport_z = ? LIMIT 1");
        $stmt->execute([$location, $series, $nui, $memory, $reportNumber]);
        if ($stmt->fetchColumn()) {
            throw new RuntimeException('Raportul Z exista deja pentru aceasta identitate fiscala.');
        }

        $placeholders = implode(',', array_fill(0, count($closureCodes), '?'));
        $stmt = $pdo->prepare("SELECT DISTINCT i.cod_inchidere
            FROM inchideri_r_12 i
            WHERE CAST(COALESCE(NULLIF(i.cod_locatie, 0), i.locatie, 0) AS INTEGER) = ?
              AND COALESCE(i.nr_raport_z, 0) = 0
              AND (COALESCE(i.nui, 0) = ? OR COALESCE(i.nui, 0) = 0)
              AND (COALESCE(i.serie_memorie_fiscala, '') = ? OR COALESCE(i.serie_memorie_fiscala, '') = '')
              AND i.cod_inchidere IN ({$placeholders})
              AND EXISTS (
                  SELECT 1 FROM note n
                  WHERE n.cod_inchidere = i.cod_inchidere
                    AND n.locatie = ? AND n.status = 'F' AND COALESCE(n.nr_raport_z, 0) = 0
                    AND (COALESCE(n.nui, 0) = ? OR COALESCE(n.nui, 0) = 0)
                    AND (COALESCE(n.serie_memorie_fiscala, '') = ? OR COALESCE(n.serie_memorie_fiscala, '') = '')
              )
            ORDER BY i.cod_inchidere");
        $stmt->execute(array_merge([$location, $nui, $memory], $closureCodes, [$location, $nui, $memory]));
        $validCodes = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        sort($validCodes);
        $requestedCodes = $closureCodes;
        sort($requestedCodes);
        if ($validCodes !== $requestedCodes) {
            throw new RuntimeException('Una dintre ture nu mai este disponibila pentru acest raport Z. Reincarcati pagina.');
        }

        $config = offline_sequence_config();
        $sequenceSeries = offline_raport_z_sequence_key($series, $nui, $memory);
        $identifier = $config['installation_uuid'] . '_rapoarte_z_' . $location . '_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $sequenceSeries) . '_' . $reportNumber;
        $stmt = $pdo->prepare("INSERT INTO rapoarte_z
            (nr_raport_z, cod_locatie, serie_casa_marcat, nui, serie_memorie_fiscala, numerar, card, credit, tichete_masa, tichete_valorice, plata_moderna, avans_in_numerar, alte_metode, data_ora_raport_z, identificator_offline)
            VALUES (:report, :location, :series, :nui, :memory, :cash, :card, :credit, :meal, :value_tickets, :modern, :advance, :other, :date_time, :identifier)");
        $stmt->execute([
            ':report' => $reportNumber, ':location' => $location, ':series' => $series, ':nui' => $nui, ':memory' => $memory,
            ':cash' => $payments['numerar'], ':card' => $payments['card'], ':credit' => $payments['credit'],
            ':meal' => $payments['tichete_masa'], ':value_tickets' => $payments['tichete_valorice'],
            ':modern' => $payments['plata_moderna'], ':advance' => $payments['avans_in_numerar'],
            ':other' => $payments['alte_metode'], ':date_time' => $dateTime, ':identifier' => $identifier,
        ]);

        $stmt = $pdo->prepare("UPDATE note SET nr_raport_z = ?, cod_locatie = ?, nui = ?, serie_memorie_fiscala = ?
            WHERE status = 'F' AND locatie = ? AND COALESCE(nr_raport_z, 0) = 0
            AND (COALESCE(nui, 0) = ? OR COALESCE(nui, 0) = 0)
            AND (COALESCE(serie_memorie_fiscala, '') = ? OR COALESCE(serie_memorie_fiscala, '') = '')
            AND cod_inchidere IN ({$placeholders})");
        $stmt->execute(array_merge([$reportNumber, $location, $nui, $memory, $location, $nui, $memory], $closureCodes));
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Turele selectate nu contin bonuri disponibile pentru raportul Z.');
        }

        $stmt = $pdo->prepare("UPDATE inchideri_r_12 SET nr_raport_z = ?, cod_locatie = ?, nui = ?, serie_memorie_fiscala = ?
            WHERE COALESCE(nr_raport_z, 0) = 0
            AND CAST(COALESCE(NULLIF(cod_locatie, 0), locatie, 0) AS INTEGER) = ?
            AND (COALESCE(nui, 0) = ? OR COALESCE(nui, 0) = 0)
            AND (COALESCE(serie_memorie_fiscala, '') = ? OR COALESCE(serie_memorie_fiscala, '') = '')
            AND cod_inchidere IN ({$placeholders})");
        $stmt->execute(array_merge([$reportNumber, $location, $nui, $memory, $location, $nui, $memory], $closureCodes));

        $updates = [
            ['BF', 'nr_doc', true],
            ['BC', 'nr_nota', false],
            ['BT', 'nr_nota', false],
        ];
        foreach ($updates as [$document, $linkColumn, $outOnly]) {
            $extra = $outOnly ? " AND miscari.tip_miscare = 'O'" : '';
            $sql = "UPDATE miscari SET nr_raport_z = ?, cod_locatie = ?, nui = ?, serie_memorie_fiscala = ?
                WHERE fel_doc = ?{$extra}
                  AND EXISTS (SELECT 1 FROM note n WHERE n.nrbon = miscari.{$linkColumn}
                    AND n.locatie = ? AND n.nr_raport_z = ?
                    AND (COALESCE(n.nui, 0) = ? OR COALESCE(n.nui, 0) = 0)
                    AND (COALESCE(n.serie_memorie_fiscala, '') = ? OR COALESCE(n.serie_memorie_fiscala, '') = '')
                    AND n.cod_inchidere IN ({$placeholders}))";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_merge([$reportNumber, $location, $nui, $memory, $document, $location, $reportNumber, $nui, $memory], $closureCodes));
        }

        offline_sequence_record($pdo, 'nr_raport_z', $reportNumber, $location, $sequenceSeries, 'z_closed', 'rapoarte_z', $identifier);
        $stmt = $pdo->prepare("SELECT nrbon FROM note
            WHERE status = 'F' AND locatie = ? AND nr_raport_z = ?
              AND COALESCE(nui, 0) = ? AND COALESCE(serie_memorie_fiscala, '') = ?
            ORDER BY nrbon");
        $stmt->execute([$location, $reportNumber, $nui, $memory]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $nrBon) {
            offline_sync_enqueue_sale_safely($pdo, (int)$nrBon);
        }
        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        try {
            $pdo->exec('ROLLBACK');
        } catch (Throwable $rollbackError) {
        }
        throw $e;
    }
}
