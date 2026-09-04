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

function offline_sequence_ensure_schema(PDO $pdo): void
{
    require_once __DIR__ . '/tools/sqlite_schema.php';
    lorand_sqlite_apply_schema_if_needed($pdo);
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
        $code = offline_sequence_next_closure($pdo, $closuresTable, $location);
        $stmt = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(valoare_vanzare_cu_tva), 0), COALESCE(SUM(tva_colectata), 0)
            FROM {$notesTable} WHERE COALESCE(cod_inchidere, 0) = 0 AND status = 'F' AND locatie = :location AND operator = :operator");
        $stmt->execute([':location' => $location, ':operator' => $operator]);
        [$saleCount, $total, $vat] = $stmt->fetch(PDO::FETCH_NUM);
        if ((int)$saleCount === 0) {
            throw new RuntimeException('Nu exista bonuri finalizate pentru inchiderea turei curente.');
        }

        $config = offline_sequence_config();
        $identifier = $config['installation_uuid'] . '_inchideri_r_12_' . $code;
        $stmt = $pdo->prepare("INSERT INTO {$closuresTable}
            (cod_inchidere, operator, valoare_cu_tva, tva_colectata, data_inchiderii, ora_inchiderii, locatie, cod_locatie, identificator_offline)
            VALUES (:code, :operator, :total, :vat, :date, :time, :location, :location, :identifier)");
        $stmt->execute([
            ':code' => $code, ':operator' => $operator, ':total' => $total, ':vat' => $vat,
            ':date' => $date, ':time' => $time, ':location' => $location, ':identifier' => $identifier,
        ]);

        $stmt = $pdo->prepare("UPDATE {$notesTable} SET cod_inchidere = :code, cod_locatie = :location
            WHERE COALESCE(cod_inchidere, 0) = 0 AND status = 'F' AND locatie = :location AND operator = :operator");
        $stmt->execute([':code' => $code, ':location' => $location, ':operator' => $operator]);
        offline_sequence_record($pdo, 'cod_inchidere', $code, $location, '', 'shift_closed', 'inchideri_r_12', $identifier);
        $stmt = $pdo->prepare("SELECT nrbon FROM {$notesTable} WHERE status = 'F' AND locatie = ? AND cod_inchidere = ? ORDER BY nrbon");
        $stmt->execute([$location, $code]);
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
        $stmt = $pdo->prepare('SELECT COALESCE(serie_casa_marcat, \'\') FROM loc_mese_12 WHERE cod_locatie = ? LIMIT 1');
        $stmt->execute([$location]);
        $series = trim((string)$stmt->fetchColumn());

        $stmt = $pdo->prepare('SELECT 1 FROM rapoarte_z WHERE cod_locatie = ? AND serie_casa_marcat = ? AND nr_raport_z = ? LIMIT 1');
        $stmt->execute([$location, $series, $reportNumber]);
        if ($stmt->fetchColumn()) {
            throw new RuntimeException('Raportul Z exista deja pentru aceasta locatie si serie de casa.');
        }

        $placeholders = implode(',', array_fill(0, count($closureCodes), '?'));
        $stmt = $pdo->prepare("SELECT DISTINCT i.cod_inchidere
            FROM inchideri_r_12 i
            WHERE CAST(COALESCE(NULLIF(i.cod_locatie, 0), i.locatie, 0) AS INTEGER) = ?
              AND COALESCE(i.nr_raport_z, 0) = 0
              AND i.cod_inchidere IN ({$placeholders})
              AND EXISTS (
                  SELECT 1 FROM note n
                  WHERE n.cod_inchidere = i.cod_inchidere
                    AND n.locatie = ? AND n.status = 'F' AND COALESCE(n.nr_raport_z, 0) = 0
              )
            ORDER BY i.cod_inchidere");
        $stmt->execute(array_merge([$location], $closureCodes, [$location]));
        $validCodes = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        sort($validCodes);
        $requestedCodes = $closureCodes;
        sort($requestedCodes);
        if ($validCodes !== $requestedCodes) {
            throw new RuntimeException('Una dintre ture nu mai este disponibila pentru acest raport Z. Reincarcati pagina.');
        }

        $config = offline_sequence_config();
        $identifier = $config['installation_uuid'] . '_rapoarte_z_' . $location . '_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $series) . '_' . $reportNumber;
        $stmt = $pdo->prepare("INSERT INTO rapoarte_z
            (nr_raport_z, cod_locatie, serie_casa_marcat, numerar, card, credit, tichete_masa, tichete_valorice, plata_moderna, avans_in_numerar, alte_metode, data_ora_raport_z, identificator_offline)
            VALUES (:report, :location, :series, :cash, :card, :credit, :meal, :value_tickets, :modern, :advance, :other, :date_time, :identifier)");
        $stmt->execute([
            ':report' => $reportNumber, ':location' => $location, ':series' => $series,
            ':cash' => $payments['numerar'], ':card' => $payments['card'], ':credit' => $payments['credit'],
            ':meal' => $payments['tichete_masa'], ':value_tickets' => $payments['tichete_valorice'],
            ':modern' => $payments['plata_moderna'], ':advance' => $payments['avans_in_numerar'],
            ':other' => $payments['alte_metode'], ':date_time' => $dateTime, ':identifier' => $identifier,
        ]);

        $stmt = $pdo->prepare("UPDATE note SET nr_raport_z = ?, cod_locatie = ?
            WHERE status = 'F' AND locatie = ? AND COALESCE(nr_raport_z, 0) = 0
              AND cod_inchidere IN ({$placeholders})");
        $stmt->execute(array_merge([$reportNumber, $location, $location], $closureCodes));
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Turele selectate nu contin bonuri disponibile pentru raportul Z.');
        }

        $stmt = $pdo->prepare("UPDATE inchideri_r_12 SET nr_raport_z = ?, cod_locatie = ?
            WHERE COALESCE(nr_raport_z, 0) = 0
              AND CAST(COALESCE(NULLIF(cod_locatie, 0), locatie, 0) AS INTEGER) = ?
              AND cod_inchidere IN ({$placeholders})");
        $stmt->execute(array_merge([$reportNumber, $location, $location], $closureCodes));

        $updates = [
            ['BF', 'nr_doc', true],
            ['BC', 'nr_nota', false],
            ['BT', 'nr_nota', false],
        ];
        foreach ($updates as [$document, $linkColumn, $outOnly]) {
            $extra = $outOnly ? " AND miscari.tip_miscare = 'O'" : '';
            $sql = "UPDATE miscari SET nr_raport_z = ?, cod_locatie = ?
                WHERE fel_doc = ?{$extra}
                  AND EXISTS (SELECT 1 FROM note n WHERE n.nrbon = miscari.{$linkColumn}
                    AND n.locatie = ? AND n.nr_raport_z = ?
                    AND n.cod_inchidere IN ({$placeholders}))";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_merge([$reportNumber, $location, $document, $location, $reportNumber], $closureCodes));
        }

        offline_sequence_record($pdo, 'nr_raport_z', $reportNumber, $location, $series, 'z_closed', 'rapoarte_z', $identifier);
        $stmt = $pdo->prepare("SELECT nrbon FROM note WHERE status = 'F' AND locatie = ? AND nr_raport_z = ? ORDER BY nrbon");
        $stmt->execute([$location, $reportNumber]);
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
