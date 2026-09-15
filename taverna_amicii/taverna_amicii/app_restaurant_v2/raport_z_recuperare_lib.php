<?php
declare(strict_types=1);

/**
 * Recuperarea offline a unui raport Z repară doar baza SQLite locală și,
 * opțional, pune rapoartele informative în coada locală de imprimare.
 * Nu trimite comenzi fiscale către casa de marcat.
 */

function restaurant_offline_z_recovery_normalize_memory(string $value): string
{
    $value = trim($value);
    return $value === '0' ? '' : $value;
}

function restaurant_offline_z_recovery_validate_date(string $value): string
{
    $value = trim($value);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        || $date->format('Y-m-d') !== $value) {
        throw new InvalidArgumentException('Data raportului Z nu este validă.');
    }
    return $value;
}

function restaurant_offline_z_recovery_validate_time(string $value): string
{
    $value = trim($value);
    if (preg_match('/^\d{2}:\d{2}$/', $value)) {
        $value .= ':00';
    }
    $time = DateTimeImmutable::createFromFormat('!H:i:s', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$time || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        || $time->format('H:i:s') !== $value) {
        throw new InvalidArgumentException('Ora raportului Z nu este validă.');
    }
    return $value;
}

function restaurant_offline_z_recovery_ensure_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS raport_z_recuperari (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        client_id INTEGER NOT NULL DEFAULT 0,
        admin_id INTEGER NOT NULL DEFAULT 0,
        cod_locatie INTEGER NOT NULL DEFAULT 0,
        data_raport TEXT NOT NULL,
        nr_raport_z INTEGER NOT NULL DEFAULT 0,
        nui INTEGER NOT NULL DEFAULT 0,
        serie_casa_marcat TEXT NOT NULL DEFAULT '',
        serie_memorie_fiscala TEXT NOT NULL DEFAULT '',
        mod_tiparire TEXT NOT NULL DEFAULT 'none',
        raport_id INTEGER NOT NULL DEFAULT 0,
        note_count INTEGER NOT NULL DEFAULT 0,
        closure_count INTEGER NOT NULL DEFAULT 0,
        detalii_json TEXT DEFAULT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
}

function restaurant_offline_z_recovery_current_identity(PDO $pdo, int $locationId): array
{
    $identity = restaurant_sqlite_raport_z_current_identification($pdo, $locationId);
    return [
        'serie_casa_marcat' => trim((string)($identity['serie_casa_marcat'] ?? '')),
        'nui' => max(0, (int)($identity['nui'] ?? 0)),
        'serie_memorie_fiscala' => restaurant_offline_z_recovery_normalize_memory((string)($identity['serie_memorie_fiscala'] ?? '')),
    ];
}

function restaurant_offline_z_recovery_historical_identities(PDO $pdo, int $locationId, string $date): array
{
    $date = restaurant_offline_z_recovery_validate_date($date);
    $stmt = $pdo->prepare("SELECT DISTINCT
                                  COALESCE(serie_casa_marcat, '') AS serie_casa_marcat,
                                  COALESCE(nui, 0) AS nui,
                                  COALESCE(serie_memorie_fiscala, '') AS serie_memorie_fiscala
                             FROM note
                            WHERE locatie = ?
                              AND DATE(data_bon) = ?
                              AND status = 'F'
                            ORDER BY serie_casa_marcat, nui, serie_memorie_fiscala");
    $stmt->execute([$locationId, $date]);
    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[] = [
            'serie_casa_marcat' => trim((string)$row['serie_casa_marcat']),
            'nui' => max(0, (int)$row['nui']),
            'serie_memorie_fiscala' => restaurant_offline_z_recovery_normalize_memory((string)$row['serie_memorie_fiscala']),
        ];
    }
    return $rows;
}

function restaurant_offline_z_recovery_notes(PDO $pdo, int $locationId, string $date, string $cashSeries, int $nui, string $memory): array
{
    $date = restaurant_offline_z_recovery_validate_date($date);
    $stmt = $pdo->prepare("SELECT nrbon, operator, data_bon, ora_bon,
                                  status, cod_inchidere, nr_raport_z,
                                  COALESCE(serie_casa_marcat, '') AS serie_casa_marcat,
                                  COALESCE(nui, 0) AS nui,
                                  COALESCE(serie_memorie_fiscala, '') AS serie_memorie_fiscala,
                                  COALESCE(numerar, 0) AS numerar,
                                  COALESCE(card, 0) AS card,
                                  COALESCE(tichete, 0) AS tichete,
                                  COALESCE(protocol, 0) AS protocol,
                                  COALESCE(glovo, 0) AS glovo,
                                  COALESCE(virament_bancar, 0) AS virament_bancar,
                                  COALESCE(valoare_vanzare_cu_tva, 0) AS valoare_vanzare_cu_tva,
                                  COALESCE(tva_colectata, 0) AS tva_colectata
                             FROM note
                            WHERE locatie = ?
                              AND DATE(data_bon) = ?
                              AND status = 'F'
                              AND COALESCE(serie_casa_marcat, '') = ?
                               AND CAST(COALESCE(nui, 0) AS INTEGER) = CAST(? AS INTEGER)
                              AND COALESCE(serie_memorie_fiscala, '') = ?
                            ORDER BY data_bon, ora_bon, nrbon");
    $stmt->execute([
        $locationId,
        $date,
        trim($cashSeries),
        max(0, $nui),
        restaurant_offline_z_recovery_normalize_memory($memory),
    ]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function restaurant_offline_z_recovery_suggest_report_number(PDO $pdo, int $locationId, string $date, string $cashSeries, int $nui, string $memory): int
{
    $date = restaurant_offline_z_recovery_validate_date($date);
    $cashSeries = trim($cashSeries);
    $nui = max(0, $nui);
    $memory = restaurant_offline_z_recovery_normalize_memory($memory);
    $numbers = [];

    $noteStmt = $pdo->prepare("SELECT DISTINCT nr_raport_z
                                  FROM note
                                 WHERE locatie = ?
                                   AND DATE(data_bon) = ?
                                   AND status = 'F'
                                   AND nr_raport_z > 0
                                   AND COALESCE(serie_casa_marcat, '') = ?
                                   AND CAST(COALESCE(nui, 0) AS INTEGER) = CAST(? AS INTEGER)
                                   AND COALESCE(serie_memorie_fiscala, '') = ?
                                 ORDER BY nr_raport_z");
    $noteStmt->execute([$locationId, $date, $cashSeries, $nui, $memory]);
    foreach ($noteStmt->fetchAll(PDO::FETCH_COLUMN) as $number) {
        $number = (int)$number;
        if ($number > 0) {
            $numbers[$number] = true;
        }
    }

    $reportStmt = $pdo->prepare("SELECT DISTINCT nr_raport_z
                                    FROM rapoarte_z
                                   WHERE cod_locatie = ?
                                     AND nr_raport_z > 0
                                     AND COALESCE(serie_casa_marcat, '') = ?
                                     AND CAST(COALESCE(nui, 0) AS INTEGER) = CAST(? AS INTEGER)
                                     AND COALESCE(serie_memorie_fiscala, '') = ?
                                     AND (substr(COALESCE(data_raport, ''), 1, 10) = ?
                                          OR substr(COALESCE(data_ora_raport_z, ''), 1, 10) = ?)
                                   ORDER BY nr_raport_z");
    $reportStmt->execute([$locationId, $cashSeries, $nui, $memory, $date, $date]);
    foreach ($reportStmt->fetchAll(PDO::FETCH_COLUMN) as $number) {
        $number = (int)$number;
        if ($number > 0) {
            $numbers[$number] = true;
        }
    }

    return count($numbers) === 1 ? (int)array_key_first($numbers) : 0;
}

function restaurant_offline_z_recovery_closures(PDO $pdo, int $locationId, string $date, array $codes, string $cashSeries, int $nui, string $memory): array
{
    $date = restaurant_offline_z_recovery_validate_date($date);
    $codes = array_values(array_unique(array_filter(array_map('intval', $codes), static fn(int $code): bool => $code > 0)));
    if (!$codes) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($codes), '?'));
    $params = array_merge([$locationId], $codes, [$date, trim($cashSeries), max(0, $nui), restaurant_offline_z_recovery_normalize_memory($memory)]);
    $stmt = $pdo->prepare("SELECT id_inch, cod_inchidere, operator, valoare_cu_tva,
                                  tva_colectata, data_inchiderii, ora_inchiderii,
                                  nr_raport_z, COALESCE(serie_casa_marcat, '') AS serie_casa_marcat,
                                  COALESCE(nui, 0) AS nui,
                                  COALESCE(serie_memorie_fiscala, '') AS serie_memorie_fiscala,
                                  COALESCE(totaluri_plata_json, '') AS totaluri_plata_json
                             FROM inchideri_r_12
                            WHERE locatie = ?
                              AND cod_inchidere IN ({$placeholders})
                              AND DATE(data_inchiderii) = ?
                              AND (COALESCE(serie_casa_marcat, '') = ? OR COALESCE(serie_casa_marcat, '') = '')
                               AND (CAST(COALESCE(nui, 0) AS INTEGER) = CAST(? AS INTEGER) OR COALESCE(nui, 0) = 0)
                              AND (COALESCE(serie_memorie_fiscala, '') = ? OR COALESCE(serie_memorie_fiscala, '') = '')
                            ORDER BY id_inch");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function restaurant_offline_z_recovery_closure_date_conflicts(PDO $pdo, int $locationId, string $date, array $codes, string $cashSeries, int $nui, string $memory): array
{
    $date = restaurant_offline_z_recovery_validate_date($date);
    $codes = array_values(array_unique(array_filter(array_map('intval', $codes), static fn(int $code): bool => $code > 0)));
    if (!$codes) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($codes), '?'));
    $params = array_merge([$locationId], $codes, [trim($cashSeries), max(0, $nui), restaurant_offline_z_recovery_normalize_memory($memory)]);
    $stmt = $pdo->prepare("SELECT cod_inchidere, data_inchiderii
                             FROM inchideri_r_12
                            WHERE locatie = ?
                              AND cod_inchidere IN ({$placeholders})
                              AND (COALESCE(serie_casa_marcat, '') = ? OR COALESCE(serie_casa_marcat, '') = '')
                              AND (CAST(COALESCE(nui, 0) AS INTEGER) = CAST(? AS INTEGER) OR COALESCE(nui, 0) = 0)
                              AND (COALESCE(serie_memorie_fiscala, '') = ? OR COALESCE(serie_memorie_fiscala, '') = '')
                            ORDER BY cod_inchidere, id_inch");
    $stmt->execute($params);
    $conflicts = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $closureDate = substr(trim((string)($row['data_inchiderii'] ?? '')), 0, 10);
        if ($closureDate !== $date) {
            $label = $closureDate !== '' ? $closureDate : 'o dată neidentificată';
            $conflicts[] = 'Codul de închidere ' . (int)$row['cod_inchidere'] . ' este deja asociat cu ' . $label . '. Recuperarea zilei selectate este blocată pentru a nu atinge altă zi.';
        }
    }
    return array_values(array_unique($conflicts));
}

function restaurant_offline_z_recovery_report_candidates(PDO $pdo, int $locationId, int $reportNumber, string $cashSeries, int $nui, string $memory): array
{
    if ($reportNumber <= 0) {
        return [];
    }
    $seriesOptions = [$cashSeries];
    if (trim($cashSeries) !== '') {
        $seriesOptions[] = '';
    }
    $rows = [];
    foreach (array_values(array_unique(array_map('trim', $seriesOptions))) as $series) {
        $stmt = $pdo->prepare("SELECT id, nr_raport_z, cod_locatie,
                                      COALESCE(serie_casa_marcat, '') AS serie_casa_marcat,
                                      numerar, card, credit, tichete_masa,
                                      tichete_valorice, plata_moderna,
                                      avans_in_numerar, alte_metode,
                                      data_ora_raport_z, data_raport,
                                      COALESCE(nui, 0) AS nui,
                                      COALESCE(serie_memorie_fiscala, '') AS serie_memorie_fiscala
                                 FROM rapoarte_z
                                WHERE cod_locatie = ?
                                  AND nr_raport_z = ?
                                  AND COALESCE(serie_casa_marcat, '') = ?
                                  AND CAST(COALESCE(nui, 0) AS INTEGER) = CAST(? AS INTEGER)
                                  AND COALESCE(serie_memorie_fiscala, '') = ?
                                ORDER BY id");
        $stmt->execute([$locationId, $reportNumber, $series, max(0, $nui), restaurant_offline_z_recovery_normalize_memory($memory)]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[(int)$row['id']] = $row;
        }
    }
    return array_values($rows);
}

function restaurant_offline_z_recovery_report_dates(PDO $pdo, int $locationId, int $reportNumber, string $cashSeries, int $nui, string $memory): array
{
    $stmt = $pdo->prepare("SELECT DISTINCT DATE(data_bon) AS report_date
                             FROM note
                            WHERE locatie = ?
                              AND status = 'F'
                              AND nr_raport_z = ?
                              AND COALESCE(serie_casa_marcat, '') = ?
                              AND CAST(COALESCE(nui, 0) AS INTEGER) = CAST(? AS INTEGER)
                              AND COALESCE(serie_memorie_fiscala, '') = ?
                            ORDER BY report_date");
    $stmt->execute([$locationId, $reportNumber, trim($cashSeries), max(0, $nui), restaurant_offline_z_recovery_normalize_memory($memory)]);
    return array_values(array_filter(array_map(static fn(array $row): string => (string)$row['report_date'], $stmt->fetchAll(PDO::FETCH_ASSOC))));
}

function restaurant_offline_z_recovery_preview(PDO $pdo, int $locationId, string $date, string $cashSeries, int $nui, string $memory, int $reportNumber): array
{
    $date = restaurant_offline_z_recovery_validate_date($date);
    $cashSeries = trim($cashSeries);
    $nui = max(0, $nui);
    $memory = restaurant_offline_z_recovery_normalize_memory($memory);
    $notes = restaurant_offline_z_recovery_notes($pdo, $locationId, $date, $cashSeries, $nui, $memory);
    $codes = [];
    $assignedZs = [];
    $operators = [];
    $totals = [
        'numerar' => 0.0,
        'card' => 0.0,
        'tichete' => 0.0,
        'protocol' => 0.0,
        'glovo' => 0.0,
        'virament_bancar' => 0.0,
        'valoare_vanzare_cu_tva' => 0.0,
        'tva_colectata' => 0.0,
    ];
    foreach ($notes as $note) {
        $code = (int)$note['cod_inchidere'];
        if ($code > 0) {
            $codes[] = $code;
        }
        $assignedZ = (int)$note['nr_raport_z'];
        if ($assignedZ > 0) {
            $assignedZs[] = $assignedZ;
        }
        $operatorId = (int)$note['operator'];
        $operators[$operatorId] = ($operators[$operatorId] ?? 0) + 1;
        foreach ($totals as $key => $_value) {
            $totals[$key] += (float)$note[$key];
        }
    }
    $codes = array_values(array_unique($codes));
    $assignedZs = array_values(array_unique($assignedZs));
    sort($codes);
    sort($assignedZs);
    $closures = restaurant_offline_z_recovery_closures($pdo, $locationId, $date, $codes, $cashSeries, $nui, $memory);
    $closureDateConflicts = restaurant_offline_z_recovery_closure_date_conflicts($pdo, $locationId, $date, $codes, $cashSeries, $nui, $memory);
    $closureByCode = [];
    foreach ($closures as $closure) {
        $closureByCode[(int)$closure['cod_inchidere']][] = $closure;
    }

    $stmtOpen = $pdo->prepare("SELECT COUNT(*) FROM note
                                WHERE locatie = ? AND DATE(data_bon) = ? AND status = 'S'
                                  AND COALESCE(serie_casa_marcat, '') = ?
                                  AND CAST(COALESCE(nui, 0) AS INTEGER) = CAST(? AS INTEGER)
                                  AND COALESCE(serie_memorie_fiscala, '') = ?");
    $stmtOpen->execute([$locationId, $date, $cashSeries, $nui, $memory]);
    $openCount = (int)$stmtOpen->fetchColumn();

    $stmtUnassigned = $pdo->prepare("SELECT COUNT(*) FROM note
                                      WHERE locatie = ? AND DATE(data_bon) = ? AND status = 'F' AND nr_raport_z = 0
                                        AND COALESCE(serie_casa_marcat, '') = ?
                                        AND CAST(COALESCE(nui, 0) AS INTEGER) = CAST(? AS INTEGER)
                                        AND COALESCE(serie_memorie_fiscala, '') = ?");
    $stmtUnassigned->execute([$locationId, $date, $cashSeries, $nui, $memory]);
    $unassignedCount = (int)$stmtUnassigned->fetchColumn();

    $targetReports = restaurant_offline_z_recovery_report_candidates($pdo, $locationId, $reportNumber, $cashSeries, $nui, $memory);
    $targetDates = $reportNumber > 0
        ? restaurant_offline_z_recovery_report_dates($pdo, $locationId, $reportNumber, $cashSeries, $nui, $memory)
        : [];
    $conflicts = [];
    if ($openCount > 0) {
        $conflicts[] = 'Există ' . $openCount . ' note cu status S pe data selectată. Acestea trebuie închise înainte de recuperare.';
    }
    if (count($assignedZs) > 1 || (count($assignedZs) === 1 && $reportNumber > 0 && $assignedZs[0] !== $reportNumber)) {
        $conflicts[] = 'Notele selectate sunt legate de alte numere de raport Z. Recuperarea nu mută note între rapoarte.';
    }
    if (count($targetReports) > 1) {
        $conflicts[] = 'Există mai multe rânduri interne pentru același număr Z și aceeași identitate fiscală.';
    }
    foreach ($closureDateConflicts as $closureDateConflict) {
        $conflicts[] = $closureDateConflict;
    }
    foreach ($targetDates as $targetDate) {
        if ($targetDate !== $date) {
            $conflicts[] = 'Numărul Z introdus este deja legat de note din data ' . $targetDate . '. Nu este suprascris peste o altă zi.';
        }
    }
    if (!$notes) {
        $conflicts[] = 'Nu există note finalizate pentru identitatea fiscală și data selectate.';
    }
    if ($reportNumber <= 0) {
        $conflicts[] = 'Pentru executare trebuie introdus numărul Z efectiv, citit de pe casa de marcat.';
    }

    return [
        'date' => $date,
        'cash_series' => $cashSeries,
        'nui' => $nui,
        'serie_memorie_fiscala' => $memory,
        'report_number' => $reportNumber,
        'notes' => $notes,
        'note_count' => count($notes),
        'unassigned_count' => $unassignedCount,
        'open_count' => $openCount,
        'codes' => $codes,
        'closures' => $closures,
        'closure_by_code' => $closureByCode,
        'assigned_zs' => $assignedZs,
        'operators' => $operators,
        'totals' => $totals,
        'target_reports' => $targetReports,
        'target_report' => count($targetReports) === 1 ? $targetReports[0] : null,
        'target_report_dates' => $targetDates,
        'conflicts' => $conflicts,
        'can_execute' => !$conflicts,
    ];
}

function restaurant_offline_z_recovery_group_notes(array $notes): array
{
    $groups = [];
    foreach ($notes as $note) {
        $code = (int)$note['cod_inchidere'];
        $operator = (int)$note['operator'];
        $key = $code > 0 ? 'code:' . $code : 'operator:' . $operator;
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'code' => $code,
                'operator' => $operator,
                'notes' => [],
                'totals' => [
                    'numerar' => 0.0,
                    'card' => 0.0,
                    'tichete' => 0.0,
                    'protocol' => 0.0,
                    'glovo' => 0.0,
                    'virament_bancar' => 0.0,
                    'valoare_vanzare_cu_tva' => 0.0,
                    'tva_colectata' => 0.0,
                ],
            ];
        }
        $groups[$key]['notes'][] = $note;
        foreach ($groups[$key]['totals'] as $field => $_value) {
            $groups[$key]['totals'][$field] += (float)$note[$field];
        }
    }
    return array_values($groups);
}

function restaurant_offline_z_recovery_next_closure_code(PDO $pdo, int $locationId): int
{
    $stmt = $pdo->prepare("SELECT MAX(code) FROM (
        SELECT COALESCE(MAX(cod_inchidere), 0) AS code FROM note WHERE locatie = ?
        UNION ALL
        SELECT COALESCE(MAX(cod_inchidere), 0) AS code FROM inchideri_r_12 WHERE locatie = ?
    ) codes");
    $stmt->execute([$locationId, $locationId]);
    return max(1, (int)$stmt->fetchColumn() + 1);
}

function restaurant_offline_z_recovery_find_closure(PDO $pdo, int $locationId, int $code, string $date, string $cashSeries, int $nui, string $memory): ?array
{
    $date = restaurant_offline_z_recovery_validate_date($date);
    $stmt = $pdo->prepare("SELECT id_inch, cod_inchidere, operator, valoare_cu_tva,
                                  tva_colectata, data_inchiderii, ora_inchiderii,
                                  nr_raport_z, COALESCE(serie_casa_marcat, '') AS serie_casa_marcat,
                                  COALESCE(nui, 0) AS nui,
                                  COALESCE(serie_memorie_fiscala, '') AS serie_memorie_fiscala
                             FROM inchideri_r_12
                            WHERE locatie = ? AND cod_inchidere = ?
                              AND DATE(data_inchiderii) = ?
                              AND (COALESCE(serie_casa_marcat, '') = ? OR COALESCE(serie_casa_marcat, '') = '')
                              AND (CAST(COALESCE(nui, 0) AS INTEGER) = CAST(? AS INTEGER) OR COALESCE(nui, 0) = 0)
                              AND (COALESCE(serie_memorie_fiscala, '') = ? OR COALESCE(serie_memorie_fiscala, '') = '')
                            ORDER BY id_inch DESC LIMIT 1");
    $stmt->execute([$locationId, $code, $date, trim($cashSeries), max(0, $nui), restaurant_offline_z_recovery_normalize_memory($memory)]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function restaurant_offline_z_recovery_closure_json(array $group, int $code, int $locationId, string $date, string $time): string
{
    $numbers = array_map(static fn(array $note): int => (int)$note['nrbon'], $group['notes']);
    sort($numbers);
    $data = [
        'numerar' => round((float)$group['totals']['numerar'], 2),
        'card' => round((float)$group['totals']['card'], 2),
        'tichete' => round((float)$group['totals']['tichete'], 2),
        'protocol' => round((float)$group['totals']['protocol'], 2),
        'glovo' => round((float)$group['totals']['glovo'], 2),
        'virament_bancar' => round((float)$group['totals']['virament_bancar'], 2),
        'cod_inchidere' => $code,
        'nrbon_min' => $numbers ? min($numbers) : 0,
        'nrbon_max' => $numbers ? max($numbers) : 0,
        'interval_note' => $numbers ? min($numbers) . '-' . max($numbers) : '-',
        'operator_id' => (int)$group['operator'],
        'locatie' => $locationId,
        'generat_la' => $date . ' ' . $time,
        'sursa' => 'recuperare_raport_z',
    ];
    return (string)json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function restaurant_offline_z_recovery_update_miscari(PDO $pdo, int $locationId, string $date, string $cashSeries, int $nui, string $memory, int $reportNumber): void
{
    $columns = [];
    foreach ($pdo->query('PRAGMA table_info("miscari")')->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $columns[(string)$column['name']] = true;
    }
    $setBySource = [
        'nr_raport_z' => 'nr_raport_z = (SELECT n.nr_raport_z FROM note n WHERE n.nrbon = miscari.%s LIMIT 1)',
        'nui' => 'nui = (SELECT COALESCE(n.nui, 0) FROM note n WHERE n.nrbon = miscari.%s LIMIT 1)',
        'serie_memorie_fiscala' => "serie_memorie_fiscala = (SELECT COALESCE(n.serie_memorie_fiscala, '') FROM note n WHERE n.nrbon = miscari.%s LIMIT 1)",
    ];
    if (isset($columns['serie_casa_marcat'])) {
        $setBySource['serie_casa_marcat'] = "serie_casa_marcat = (SELECT COALESCE(n.serie_casa_marcat, '') FROM note n WHERE n.nrbon = miscari.%s LIMIT 1)";
    }

    $buildSet = static function (string $source) use ($setBySource): string {
        $assignments = [];
        foreach ($setBySource as $template) {
            $assignments[] = sprintf($template, $source);
        }
        return implode(",\n                           ", $assignments);
    };

    $params = [$reportNumber, $cashSeries, $nui, $memory, $locationId, $date];
    $pdo->prepare("UPDATE miscari
                      SET " . $buildSet('nr_doc') . "
                    WHERE tip_miscare = 'O' AND fel_doc = 'BF'
                      AND EXISTS (SELECT 1 FROM note n WHERE n.nrbon = miscari.nr_doc
                        AND n.nr_raport_z = ? AND COALESCE(n.serie_casa_marcat, '') = ?
                        AND CAST(COALESCE(n.nui, 0) AS INTEGER) = CAST(? AS INTEGER) AND COALESCE(n.serie_memorie_fiscala, '') = ?
                        AND n.locatie = ? AND DATE(n.data_bon) = ? AND n.status = 'F')")->execute($params);

    $params = [$reportNumber, $cashSeries, $nui, $memory, $locationId, $date];
    $pdo->prepare("UPDATE miscari
                      SET " . $buildSet('nr_nota') . "
                    WHERE fel_doc IN ('BC', 'BT')
                      AND EXISTS (SELECT 1 FROM note n WHERE n.nrbon = miscari.nr_nota
                        AND n.nr_raport_z = ? AND COALESCE(n.serie_casa_marcat, '') = ?
                        AND CAST(COALESCE(n.nui, 0) AS INTEGER) = CAST(? AS INTEGER) AND COALESCE(n.serie_memorie_fiscala, '') = ?
                        AND n.locatie = ? AND DATE(n.data_bon) = ? AND n.status = 'F')")->execute($params);
}

function restaurant_offline_z_recovery_execute(PDO $pdo, int $clientId, int $adminId, int $locationId, string $date, string $time, string $cashSeries, int $nui, string $memory, int $reportNumber, string $printMode): array
{
    $date = restaurant_offline_z_recovery_validate_date($date);
    $time = restaurant_offline_z_recovery_validate_time($time);
    $cashSeries = trim($cashSeries);
    $nui = max(0, $nui);
    $memory = restaurant_offline_z_recovery_normalize_memory($memory);
    if (!in_array($printMode, ['none', 'final', 'all'], true)) {
        throw new InvalidArgumentException('Modul de tipărire nu este valid.');
    }
    if ($reportNumber <= 0) {
        throw new InvalidArgumentException('Numărul efectiv al raportului Z este obligatoriu.');
    }

    $preview = restaurant_offline_z_recovery_preview($pdo, $locationId, $date, $cashSeries, $nui, $memory, $reportNumber);
    if (!$preview['can_execute']) {
        throw new RuntimeException(implode(' ', $preview['conflicts']));
    }
    $nextCode = restaurant_offline_z_recovery_next_closure_code($pdo, $locationId);
    $pdo->beginTransaction();
    try {
        $notes = restaurant_offline_z_recovery_notes($pdo, $locationId, $date, $cashSeries, $nui, $memory);
        if (count($notes) !== (int)$preview['note_count']) {
            throw new RuntimeException('Datele s-au schimbat în timpul verificării. Reia previzualizarea.');
        }
        foreach ($notes as $note) {
            if ((int)$note['nr_raport_z'] > 0 && (int)$note['nr_raport_z'] !== $reportNumber) {
                throw new RuntimeException('Cel puțin o notă este deja legată de alt raport Z.');
            }
        }

        $groups = restaurant_offline_z_recovery_group_notes($notes);
        $closureIds = [];
        $oldNumbers = [];
        foreach ($groups as &$group) {
            if ((int)$group['code'] <= 0) {
                $group['code'] = $nextCode++;
            }
            $code = (int)$group['code'];
            $closure = restaurant_offline_z_recovery_find_closure($pdo, $locationId, $code, $date, $cashSeries, $nui, $memory);
            if ($closure && (int)$closure['nr_raport_z'] > 0 && (int)$closure['nr_raport_z'] !== $reportNumber) {
                throw new RuntimeException('Închiderea cu codul ' . $code . ' este deja legată de alt raport Z.');
            }
            $closeTime = $closure && trim((string)$closure['ora_inchiderii']) !== ''
                ? trim((string)$closure['ora_inchiderii'])
                : $time;
            $json = restaurant_offline_z_recovery_closure_json($group, $code, $locationId, $date, $closeTime);
            if ($closure) {
                $closureId = (int)$closure['id_inch'];
                $oldNumbers[] = (int)$closure['nr_raport_z'];
                $stmt = $pdo->prepare("UPDATE inchideri_r_12
                    SET operator = ?, valoare_cu_tva = ?, tva_colectata = ?,
                        data_inchiderii = ?, ora_inchiderii = ?, nr_raport_z = ?,
                        serie_casa_marcat = ?, nui = ?, serie_memorie_fiscala = ?,
                        totaluri_plata_json = ?
                  WHERE id_inch = ?");
                $stmt->execute([
                    (int)$group['operator'],
                    round((float)$group['totals']['valoare_vanzare_cu_tva'], 2),
                    round((float)$group['totals']['tva_colectata'], 2),
                    $date,
                    $closeTime,
                    $reportNumber,
                    $cashSeries,
                    $nui,
                    $memory,
                    $json,
                    $closureId,
                ]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO inchideri_r_12
                    (cod_inchidere, operator, valoare_cu_tva, tva_colectata,
                     data_inchiderii, ora_inchiderii, locatie, nr_raport_z,
                     serie_casa_marcat, nui, serie_memorie_fiscala, totaluri_plata_json)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $code,
                    (int)$group['operator'],
                    round((float)$group['totals']['valoare_vanzare_cu_tva'], 2),
                    round((float)$group['totals']['tva_colectata'], 2),
                    $date,
                    $closeTime,
                    $locationId,
                    $reportNumber,
                    $cashSeries,
                    $nui,
                    $memory,
                    $json,
                ]);
                $closureId = (int)$pdo->lastInsertId();
            }
            $closureIds[] = $closureId;

            $stmtNote = $pdo->prepare("UPDATE note SET cod_inchidere = ?, nr_raport_z = ?,
                serie_casa_marcat = ?, nui = ?, serie_memorie_fiscala = ?
              WHERE nrbon = ? AND locatie = ? AND DATE(data_bon) = ? AND status = 'F'");
            foreach ($group['notes'] as $note) {
                $stmtNote->execute([$code, $reportNumber, $cashSeries, $nui, $memory, (int)$note['nrbon'], $locationId, $date]);
            }
        }
        unset($group);

        $totals = ['numerar' => 0.0, 'card' => 0.0, 'tichete' => 0.0, 'glovo' => 0.0, 'protocol' => 0.0, 'virament_bancar' => 0.0];
        foreach ($notes as $note) {
            foreach ($totals as $field => $_value) {
                $totals[$field] += (float)$note[$field];
            }
        }
        $otherMethods = $totals['protocol'] + $totals['virament_bancar'];
        $report = $preview['target_report'];
        if ($report) {
            $reportId = (int)$report['id'];
            $stmt = $pdo->prepare("UPDATE rapoarte_z
                SET serie_casa_marcat = ?, numerar = ?, card = ?, credit = 0,
                    tichete_masa = ?, tichete_valorice = 0, plata_moderna = ?,
                    avans_in_numerar = 0, alte_metode = ?, data_ora_raport_z = ?,
                    data_raport = ?, nui = ?, serie_memorie_fiscala = ?
              WHERE id = ?");
            $stmt->execute([$cashSeries, round($totals['numerar'], 2), round($totals['card'], 2), round($totals['tichete'], 2), round($totals['glovo'], 2), round($otherMethods, 2), $date . ' ' . $time, $date, $nui, $memory, $reportId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO rapoarte_z
                (nr_raport_z, cod_locatie, serie_casa_marcat, nui, serie_memorie_fiscala,
                 numerar, card, credit, tichete_masa, tichete_valorice,
                 plata_moderna, avans_in_numerar, alte_metode, data_ora_raport_z,
                 data_raport)
                VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, 0, ?, 0, ?, ?, ?)");
            $stmt->execute([$reportNumber, $locationId, $cashSeries, $nui, $memory, round($totals['numerar'], 2), round($totals['card'], 2), round($totals['tichete'], 2), round($totals['glovo'], 2), round($otherMethods, 2), $date . ' ' . $time, $date]);
            $reportId = (int)$pdo->lastInsertId();
        }

        restaurant_offline_z_recovery_update_miscari($pdo, $locationId, $date, $cashSeries, $nui, $memory, $reportNumber);
        $audit = [
            'old_report_numbers' => array_values(array_unique($oldNumbers)),
            'closure_codes' => array_values(array_map(static fn(array $group): int => (int)$group['code'], $groups)),
            'report_timestamp' => $date . ' ' . $time,
            'note_numbers' => array_values(array_map(static fn(array $note): int => (int)$note['nrbon'], $notes)),
        ];
        $stmtAudit = $pdo->prepare("INSERT INTO raport_z_recuperari
            (client_id, admin_id, cod_locatie, data_raport, nr_raport_z,
             nui, serie_casa_marcat, serie_memorie_fiscala, mod_tiparire,
             raport_id, note_count, closure_count, detalii_json, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtAudit->execute([$clientId, $adminId, $locationId, $date, $reportNumber, $nui, $cashSeries, $memory, $printMode, $reportId, count($notes), count($closureIds), json_encode($audit, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), date('Y-m-d H:i:s')]);
        $pdo->commit();

        return [
            'report_id' => $reportId,
            'report_number' => $reportNumber,
            'closure_ids' => array_values(array_unique($closureIds)),
            'closure_codes' => array_values(array_map(static fn(array $group): int => (int)$group['code'], $groups)),
            'note_count' => count($notes),
            'print_mode' => $printMode,
            'date' => $date,
            'time' => $time,
            'cash_series' => $cashSeries,
            'nui' => $nui,
            'memory' => $memory,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function restaurant_offline_z_recovery_shift_documents(PDO $pdo, int $locationId, array $codes, string $date, string $cashSeries, int $nui, string $memory, string $actorLabel): array
{
    $documents = [];
    foreach (array_values(array_unique(array_map('intval', $codes))) as $code) {
        if ($code <= 0) {
            continue;
        }
        $stmt = $pdo->prepare("SELECT n.numerar, n.card, n.tichete, n.protocol,
                                     n.glovo, n.virament_bancar, n.nrbon,
                                     n.ora_bon, a.admin_firstname, a.admin_lastname
                                FROM note n
                                LEFT JOIN admins_12 a ON a.admin_id = n.operator
                               WHERE n.locatie = ? AND DATE(n.data_bon) = ?
                                 AND n.status = 'F' AND n.cod_inchidere = ?
                                 AND COALESCE(n.serie_casa_marcat, '') = ?
                                 AND CAST(COALESCE(n.nui, 0) AS INTEGER) = CAST(? AS INTEGER)
                                 AND COALESCE(n.serie_memorie_fiscala, '') = ?
                               ORDER BY n.nrbon");
        $stmt->execute([$locationId, $date, $code, trim($cashSeries), max(0, $nui), restaurant_offline_z_recovery_normalize_memory($memory)]);
        $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$notes) {
            continue;
        }
        $totals = ['numerar' => 0.0, 'card' => 0.0, 'tichete' => 0.0, 'protocol' => 0.0, 'glovo' => 0.0, 'virament_bancar' => 0.0];
        $numbers = [];
        $operatorLabel = '';
        $lastTime = '';
        foreach ($notes as $note) {
            foreach ($totals as $field => $_value) {
                $totals[$field] += (float)$note[$field];
            }
            $numbers[] = (int)$note['nrbon'];
            $operatorLabel = trim((string)$note['admin_firstname'] . ' ' . (string)$note['admin_lastname']);
            $lastTime = (string)$note['ora_bon'];
        }
        sort($numbers);
        $content = str_repeat('=', 42) . "\nRETIPĂRIRE ÎNCHIDERE TURĂ\n" . str_repeat('=', 42) . "\n";
        $content .= 'Data vânzărilor: ' . $date . "\nCod închidere: " . $code . "\nInterval note: " . ($numbers ? min($numbers) . '-' . max($numbers) : '-') . "\n";
        $content .= str_repeat('-', 42) . "\n";
        $labels = ['numerar' => 'Numerar', 'card' => 'Card', 'tichete' => 'Tichete', 'protocol' => 'Protocol', 'glovo' => 'Online', 'virament_bancar' => 'Virament bancar'];
        foreach ($labels as $field => $label) {
            if (abs($totals[$field]) >= 0.005) {
                $content .= $label . ': ' . number_format($totals[$field], 2, ',', '.') . " LEI\n";
            }
        }
        $tipStmt = $pdo->prepare("SELECT
                                         COALESCE(SUM(d.pret_vanzare), 0) AS total_bacsis,
                                         COALESCE(SUM(CASE
                                             WHEN n.card > 0 AND n.numerar <= 0 THEN d.pret_vanzare
                                             WHEN n.card > 0 AND n.numerar > 0 THEN d.pret_vanzare * (n.card / NULLIF(n.card + n.numerar, 0))
                                             ELSE 0
                                         END), 0) AS bacsis_card,
                                         COALESCE(SUM(CASE
                                             WHEN n.numerar > 0 AND n.card <= 0 THEN d.pret_vanzare
                                             WHEN n.card > 0 AND n.numerar > 0 THEN d.pret_vanzare * (n.numerar / NULLIF(n.card + n.numerar, 0))
                                             ELSE 0
                                         END), 0) AS bacsis_numerar
                                    FROM det_note d
                                    INNER JOIN note n ON n.nrbon = d.nr_bon
                                   WHERE d.cod_p = -1
                                     AND n.locatie = ?
                                     AND DATE(n.data_bon) = ?
                                     AND n.status = 'F'
                                     AND n.cod_inchidere = ?
                                     AND COALESCE(n.serie_casa_marcat, '') = ?
                                     AND CAST(COALESCE(n.nui, 0) AS INTEGER) = CAST(? AS INTEGER)
                                     AND COALESCE(n.serie_memorie_fiscala, '') = ?");
        $tipStmt->execute([$locationId, $date, $code, trim($cashSeries), max(0, $nui), restaurant_offline_z_recovery_normalize_memory($memory)]);
        $tips = $tipStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $totalTips = (float)($tips['total_bacsis'] ?? 0);
        $tipsCard = (float)($tips['bacsis_card'] ?? 0);
        $tipsCash = (float)($tips['bacsis_numerar'] ?? 0);
        if (abs($totalTips) >= 0.005) {
            if (abs($tipsCash) >= 0.005) {
                $content .= 'BACȘIȘ numerar: ' . number_format($tipsCash, 2, ',', '.') . " LEI\n";
            }
            if (abs($tipsCard) >= 0.005) {
                $content .= 'BACȘIȘ card: ' . number_format($tipsCard, 2, ',', '.') . " LEI\n";
            }
            $content .= 'BACȘIȘ total: ' . number_format($totalTips, 2, ',', '.') . " LEI\n";
        }
        $content .= str_repeat('=', 42) . "\nOPERATOR TURĂ: " . ($operatorLabel !== '' ? $operatorLabel : 'ID necunoscut') . "\nREGENERAT DE: " . ($actorLabel !== '' ? $actorLabel : 'Șef sală') . "\n";
        $documents[] = [
            'id' => 0,
            'data' => $date,
            'ora' => $lastTime !== '' ? $lastTime : date('H:i:s'),
            'de_trimis_la_imprimanta' => 1,
            'nrbon' => 0,
            'locatie' => $locationId,
            'departament_listare' => 'BAR',
            'continut' => $content,
        ];
    }
    return $documents;
}

function restaurant_offline_z_recovery_printer_available(): bool
{
    if (!is_file(__DIR__ . '/offline_printer_flow_helper.php')
        || !is_file(__DIR__ . '/raport_z_imprimanta_helper.php')
        || !defined('RESTAURANT_OFFLINE_API_DIR')) {
        return false;
    }

    $queueHelper = rtrim((string)RESTAURANT_OFFLINE_API_DIR, '/\\')
        . DIRECTORY_SEPARATOR . 'printer_queue_atomic_helper.php';
    return is_file($queueHelper);
}

function restaurant_offline_z_recovery_queue_print(PDO $pdo, int $clientId, int $locationId, array $result, string $actorLabel): array
{
    if ((string)$result['print_mode'] === 'none') {
        return ['queued' => false, 'documents' => 0, 'message' => 'Datele au fost reparate fără tipărire.'];
    }
    if (!restaurant_offline_z_recovery_printer_available()) {
        return ['queued' => false, 'documents' => 0, 'message' => 'Datele au fost reparate. Această instalație nu are configurată coada locală pentru tipărire.'];
    }
    require_once __DIR__ . '/offline_printer_flow_helper.php';
    require_once __DIR__ . '/raport_z_imprimanta_helper.php';
    $printerFormatHelper = __DIR__ . '/printer_bold_helper.php';
    if (is_file($printerFormatHelper)) {
        require_once $printerFormatHelper;
    }
    $documents = [];
    if ((string)$result['print_mode'] === 'all') {
        $documents = restaurant_offline_z_recovery_shift_documents($pdo, $locationId, (array)$result['closure_codes'], (string)$result['date'], (string)$result['cash_series'], (int)$result['nui'], (string)$result['memory'], $actorLabel);
    }
    $zDocuments = agecs_z_print_documents($pdo, $clientId, $locationId, (int)$result['report_number']);
    foreach ($zDocuments as &$document) {
        $document['data'] = (string)$result['date'];
        $document['ora'] = (string)$result['time'];
    }
    unset($document);
    $documents = array_merge($documents, $zDocuments);
    if (!$documents) {
        return ['queued' => false, 'documents' => 0, 'message' => 'Datele au fost reparate. Nu există documente de tipărit.'];
    }
    if (function_exists('agecs_printer_bold_jobs')) {
        $documents = agecs_printer_bold_jobs($documents, $clientId);
    }
    agecs_offline_printer_enqueue($documents, 'Rapoartele recuperate au fost adăugate în coada imprimantei.');
    return ['queued' => true, 'documents' => count($documents), 'message' => 'Documentele au fost puse în coada locală a imprimantei.'];
}

function restaurant_offline_z_recovery_sync_ensure_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS offline_z_recovery_sync (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        event_uuid TEXT NOT NULL UNIQUE,
        raport_id INTEGER NOT NULL DEFAULT 0,
        cod_locatie INTEGER NOT NULL DEFAULT 0,
        data_raport TEXT NOT NULL,
        payload_json TEXT NOT NULL,
        payload_sha256 TEXT NOT NULL DEFAULT '',
        status TEXT NOT NULL DEFAULT 'pending',
        attempts INTEGER NOT NULL DEFAULT 0,
        last_error TEXT NOT NULL DEFAULT '',
        online_response_json TEXT DEFAULT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        sent_at TEXT DEFAULT NULL
    )");
}

function restaurant_offline_z_recovery_sync_pick(array $row, array $fields): array
{
    $picked = [];
    foreach ($fields as $field) {
        if (array_key_exists($field, $row)) {
            $picked[$field] = $row[$field];
        }
    }
    return $picked;
}

function restaurant_offline_z_recovery_sync_payload(PDO $pdo, array $restaurantConfig, array $result, array $actor = []): array
{
    $clientId = (int)($restaurantConfig['client_id'] ?? 0);
    $locationId = (int)($restaurantConfig['cod_locatie'] ?? 0);
    $installationUuid = trim((string)($restaurantConfig['transaction_uuid'] ?? $restaurantConfig['installation_uuid'] ?? ''));
    if ($clientId <= 0 || $locationId <= 0 || $installationUuid === '') {
        throw new RuntimeException('Configurația offline nu conține identitatea completă pentru sincronizarea recuperării Z.');
    }

    $reportId = (int)($result['report_id'] ?? 0);
    $reportNumber = (int)($result['report_number'] ?? 0);
    $date = restaurant_offline_z_recovery_validate_date((string)($result['date'] ?? ''));
    $time = restaurant_offline_z_recovery_validate_time((string)($result['time'] ?? ''));
    $cashSeries = trim((string)($result['cash_series'] ?? ''));
    $nui = max(0, (int)($result['nui'] ?? 0));
    $memory = restaurant_offline_z_recovery_normalize_memory((string)($result['memory'] ?? ''));
    if ($reportId <= 0 || $reportNumber <= 0) {
        throw new RuntimeException('Recuperarea Z nu are un raport local valid pentru sincronizarea online.');
    }

    $reportStmt = $pdo->prepare('SELECT * FROM rapoarte_z WHERE id = ? AND cod_locatie = ? LIMIT 1');
    $reportStmt->execute([$reportId, $locationId]);
    $reportRow = $reportStmt->fetch(PDO::FETCH_ASSOC);
    if (!$reportRow) {
        throw new RuntimeException('Raportul Z local nu mai există pentru pregătirea sincronizării online.');
    }
    if ((int)($reportRow['nr_raport_z'] ?? 0) !== $reportNumber
        || (int)($reportRow['cod_locatie'] ?? 0) !== $locationId
        || trim((string)($reportRow['serie_casa_marcat'] ?? '')) !== $cashSeries
        || (int)($reportRow['nui'] ?? 0) !== $nui
        || restaurant_offline_z_recovery_normalize_memory((string)($reportRow['serie_memorie_fiscala'] ?? '')) !== $memory
        || substr(trim((string)($reportRow['data_raport'] ?? '')), 0, 10) !== $date) {
        throw new RuntimeException('Raportul Z local nu corespunde exact recuperării selectate.');
    }

    $reportFields = [
        'id', 'nr_raport_z', 'cod_locatie', 'serie_casa_marcat',
        'numerar', 'card', 'credit', 'tichete_masa', 'tichete_valorice',
        'plata_moderna', 'avans_in_numerar', 'alte_metode', 'data_ora_raport_z',
        'data_raport', 'nui', 'serie_memorie_fiscala', 'identificator_offline',
    ];
    $reportPayload = restaurant_offline_z_recovery_sync_pick($reportRow, $reportFields);
    $reportPayload['source_pk'] = (string)$reportId;

    $closureIds = array_values(array_unique(array_filter(array_map('intval', (array)($result['closure_ids'] ?? [])), static fn(int $id): bool => $id > 0)));
    $closures = [];
    if ($closureIds) {
        $placeholders = implode(',', array_fill(0, count($closureIds), '?'));
        $closureStmt = $pdo->prepare("SELECT * FROM inchideri_r_12 WHERE locatie = ? AND id_inch IN ({$placeholders}) ORDER BY id_inch");
        $closureStmt->execute(array_merge([$locationId], $closureIds));
        $closureFields = [
            'id_inch', 'cod_inchidere', 'operator', 'valoare_cu_tva', 'tva_colectata',
            'data_inchiderii', 'ora_inchiderii', 'locatie', 'nr_raport_z',
            'serie_casa_marcat', 'nui', 'serie_memorie_fiscala', 'totaluri_plata_json',
            'identificator_offline',
        ];
        foreach ($closureStmt->fetchAll(PDO::FETCH_ASSOC) as $closureRow) {
            $closurePayload = restaurant_offline_z_recovery_sync_pick($closureRow, $closureFields);
            $closurePayload['source_pk'] = (string)((int)$closureRow['id_inch']);
            $closures[] = $closurePayload;
        }
    }
    if (count($closures) !== count($closureIds)) {
        throw new RuntimeException('Nu toate închiderile locale ale recuperării Z mai există. Reia previzualizarea.');
    }

    $codes = array_values(array_unique(array_filter(array_map('intval', (array)($result['closure_codes'] ?? [])), static fn(int $code): bool => $code > 0)));
    $noteSql = "SELECT * FROM note
                 WHERE locatie = ?
                   AND DATE(data_bon) = ?
                   AND status = 'F'
                   AND nr_raport_z = ?
                   AND (COALESCE(serie_casa_marcat, '') = ?)
                   AND CAST(COALESCE(nui, 0) AS INTEGER) = CAST(? AS INTEGER)
                   AND COALESCE(serie_memorie_fiscala, '') = ?";
    $noteParams = [$locationId, $date, $reportNumber, $cashSeries, $nui, $memory];
    if ($codes) {
        $noteSql .= ' AND cod_inchidere IN (' . implode(',', array_fill(0, count($codes), '?')) . ')';
        $noteParams = array_merge($noteParams, $codes);
    }
    $noteSql .= ' ORDER BY data_bon, ora_bon, nrbon';
    $noteStmt = $pdo->prepare($noteSql);
    $noteStmt->execute($noteParams);
    $noteFields = [
        'nrbon', 'operator', 'data_bon', 'ora_bon', 'status', 'cod_inchidere', 'nr_raport_z', 'locatie',
        'serie_casa_marcat', 'nui', 'serie_memorie_fiscala', 'numerar', 'card', 'tichete',
        'protocol', 'glovo', 'virament_bancar', 'valoare_vanzare_cu_tva', 'tva_colectata',
        'identificator_offline',
    ];
    $notes = [];
    foreach ($noteStmt->fetchAll(PDO::FETCH_ASSOC) as $noteRow) {
        $notePayload = restaurant_offline_z_recovery_sync_pick($noteRow, $noteFields);
        $notePayload['source_pk'] = (string)((int)$noteRow['nrbon']);
        $notes[] = $notePayload;
    }

    $payloadBase = [
        'schema_version' => 'offline-z-recovery-v1',
        'event_type' => 'z_recovery_sync',
        'source_type' => 'offline',
        'client_id' => $clientId,
        'cod_locatie' => $locationId,
        'installation_uuid' => $installationUuid,
        'data_raport' => $date,
        'ora_raport_z' => $time,
        'nr_raport_z' => $reportNumber,
        'serie_casa_marcat' => $cashSeries,
        'nui' => $nui,
        'serie_memorie_fiscala' => $memory,
        'utilizator_sync' => $actor,
        'report' => $reportPayload,
        'closures' => $closures,
        'notes' => $notes,
    ];
    $hashInput = json_encode($payloadBase, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($hashInput === false) {
        throw new RuntimeException('Pachetul dedicat de recuperare Z nu poate fi serializat.');
    }
    $contentHash = hash('sha256', $hashInput);
    $safeInstallation = preg_replace('/[^A-Za-z0-9_.:-]+/', '_', $installationUuid);
    $eventUuid = substr('offline-z-recovery:' . $safeInstallation . ':' . $reportId . ':' . substr($contentHash, 0, 24), 0, 191);
    return array_merge([
        'event_uuid' => $eventUuid,
        'sync_export_id' => $eventUuid,
        'payload_sha256' => $contentHash,
        'counts' => [
            'rapoarte_z' => 1,
            'inchideri_r_12' => count($closures),
            'note' => count($notes),
        ],
    ], $payloadBase);
}

function restaurant_offline_z_recovery_sync_store(PDO $pdo, array $payload): int
{
    restaurant_offline_z_recovery_sync_ensure_schema($pdo);
    $eventUuid = trim((string)($payload['event_uuid'] ?? ''));
    if ($eventUuid === '') {
        throw new InvalidArgumentException('Evenimentul de sincronizare al recuperării Z nu are identificator.');
    }
    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($payloadJson === false) {
        throw new RuntimeException('Pachetul de sincronizare al recuperării Z nu poate fi serializat.');
    }
    $payloadHash = hash('sha256', $payloadJson);
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO offline_z_recovery_sync
        (event_uuid, raport_id, cod_locatie, data_raport, payload_json, payload_sha256, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?)");
    $now = date('Y-m-d H:i:s');
    $stmt->execute([
        $eventUuid,
        (int)($payload['report']['source_pk'] ?? 0),
        (int)($payload['cod_locatie'] ?? 0),
        (string)($payload['data_raport'] ?? ''),
        $payloadJson,
        $payloadHash,
        $now,
        $now,
    ]);
    $read = $pdo->prepare('SELECT id FROM offline_z_recovery_sync WHERE event_uuid = ? LIMIT 1');
    $read->execute([$eventUuid]);
    $id = (int)$read->fetchColumn();
    if ($id <= 0) {
        throw new RuntimeException('Jurnalul local al recuperării Z nu a putut fi salvat.');
    }
    return $id;
}

function restaurant_offline_z_recovery_sync_endpoint_config(array $restaurantConfig): array
{
    $sync = isset($restaurantConfig['offline_sales_sync']) && is_array($restaurantConfig['offline_sales_sync'])
        ? $restaurantConfig['offline_sales_sync']
        : [];
    $apiUrl = trim((string)($sync['api_url'] ?? ''));
    $recoveryUrl = trim((string)($sync['recovery_api_url'] ?? ''));
    if ($recoveryUrl === '' && $apiUrl !== '') {
        $recoveryUrl = preg_replace(
            '~/(?:sincronizare_date_offline)\.php(?:\?.*)?$~i',
            '/raport_z_recuperare_offline_sync.php',
            $apiUrl
        );
        if (!is_string($recoveryUrl) || $recoveryUrl === $apiUrl) {
            $recoveryUrl = rtrim($apiUrl, '/') . '/raport_z_recuperare_offline_sync.php';
        }
    }
    return [
        'enabled' => filter_var($sync['enabled'] ?? false, FILTER_VALIDATE_BOOL),
        'api_url' => trim((string)$recoveryUrl),
        'api_key' => trim((string)($sync['api_key'] ?? '')),
        'timeout_seconds' => max(5, (int)($sync['timeout_seconds'] ?? 45)),
        'send_api_key_in_query' => filter_var($sync['send_api_key_in_query'] ?? true, FILTER_VALIDATE_BOOL),
        'verify_ssl' => filter_var($sync['verify_ssl'] ?? true, FILTER_VALIDATE_BOOL),
        'ca_bundle_path' => trim((string)($restaurantConfig['ca_bundle_path'] ?? '')),
    ];
}

function restaurant_offline_z_recovery_sync_send_http(string $payloadJson, array $config): array
{
    if (!$config['enabled']) {
        throw new RuntimeException('Sincronizarea online este dezactivată în configurația offline.');
    }
    if ($config['api_url'] === '' || $config['api_key'] === '') {
        throw new RuntimeException('URL-ul dedicat sau cheia API pentru recuperarea Z nu sunt configurate.');
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('Extensia cURL nu este disponibilă pentru sincronizarea recuperării Z.');
    }

    $url = $config['api_url'];
    if ($config['send_api_key_in_query']) {
        $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query(['api_key' => $config['api_key']]);
    }
    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('Conexiunea dedicată pentru recuperarea Z nu a putut fi inițiată.');
    }
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payloadJson,
        CURLOPT_CONNECTTIMEOUT => $config['timeout_seconds'],
        CURLOPT_TIMEOUT => $config['timeout_seconds'],
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json; charset=utf-8',
            'Accept: application/json',
            'X-Api-Key: ' . $config['api_key'],
            'Authorization: Bearer ' . $config['api_key'],
        ],
        CURLOPT_SSL_VERIFYPEER => $config['verify_ssl'],
        CURLOPT_SSL_VERIFYHOST => $config['verify_ssl'] ? 2 : 0,
    ];
    if ($config['verify_ssl'] && $config['ca_bundle_path'] !== '' && is_file($config['ca_bundle_path'])) {
        $options[CURLOPT_CAINFO] = $config['ca_bundle_path'];
    }
    curl_setopt_array($ch, $options);
    $raw = curl_exec($ch);
    $error = curl_error($ch);
    $errno = curl_errno($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false) {
        throw new RuntimeException('Endpointul dedicat pentru recuperarea Z nu a putut fi apelat: ' . ($error !== '' ? $error : 'eroare cURL ' . $errno));
    }
    $decoded = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', (string)$raw), true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Răspunsul endpointului dedicat pentru recuperarea Z nu este JSON valid.');
    }
    if ($httpCode < 200 || $httpCode >= 300 || (string)($decoded['status'] ?? '') !== 'success') {
        $message = (string)($decoded['message'] ?? 'Endpointul dedicat pentru recuperarea Z a returnat eroare.');
        if (!empty($decoded['errors']) && is_array($decoded['errors'])) {
            $message .= ' ' . implode(' | ', array_map('strval', $decoded['errors']));
        }
        throw new RuntimeException($message);
    }
    $decoded['http_code'] = $httpCode;
    return $decoded;
}

function restaurant_offline_z_recovery_sync_online(PDO $pdo, array $restaurantConfig, array $payload): array
{
    restaurant_offline_z_recovery_sync_ensure_schema($pdo);
    $recordId = restaurant_offline_z_recovery_sync_store($pdo, $payload);
    $read = $pdo->prepare('SELECT * FROM offline_z_recovery_sync WHERE id = ? LIMIT 1');
    $read->execute([$recordId]);
    $record = $read->fetch(PDO::FETCH_ASSOC) ?: [];
    if ((string)($record['status'] ?? '') === 'sent') {
        $stored = json_decode((string)($record['online_response_json'] ?? ''), true);
        return is_array($stored) ? $stored + ['local_status' => 'sent'] : ['status' => 'success', 'message' => 'Actualizarea online fusese deja confirmată.', 'local_status' => 'sent'];
    }

    $config = restaurant_offline_z_recovery_sync_endpoint_config($restaurantConfig);
    if (!$config['enabled'] || $config['api_url'] === '' || $config['api_key'] === '') {
        $message = 'Actualizarea online a fost păstrată local și așteaptă configurarea sau disponibilitatea sincronizării.';
        $pdo->prepare("UPDATE offline_z_recovery_sync SET status = 'pending', last_error = ?, updated_at = ? WHERE id = ?")
            ->execute([$message, date('Y-m-d H:i:s'), $recordId]);
        return ['status' => 'pending', 'message' => $message, 'local_status' => 'pending', 'event_uuid' => $payload['event_uuid']];
    }

    $attempts = (int)($record['attempts'] ?? 0) + 1;
    $pdo->prepare("UPDATE offline_z_recovery_sync SET status = 'sending', attempts = ?, last_error = '', updated_at = ? WHERE id = ?")
        ->execute([$attempts, date('Y-m-d H:i:s'), $recordId]);
    try {
        $online = restaurant_offline_z_recovery_sync_send_http((string)$record['payload_json'], $config);
        $pending = isset($online['pending']) && is_array($online['pending']) ? $online['pending'] : [];
        $localStatus = empty($pending) ? 'sent' : 'partial';
        $message = (string)($online['message'] ?? 'Actualizarea online a fost confirmată.');
        $pdo->prepare("UPDATE offline_z_recovery_sync
                          SET status = ?, last_error = ?, online_response_json = ?, sent_at = ?, updated_at = ?
                        WHERE id = ?")
            ->execute([
                $localStatus,
                empty($pending) ? '' : $message,
                json_encode($online, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                empty($pending) ? date('Y-m-d H:i:s') : null,
                date('Y-m-d H:i:s'),
                $recordId,
            ]);
        $online['local_status'] = $localStatus;
        $online['event_uuid'] = $payload['event_uuid'];
        return $online;
    } catch (Throwable $e) {
        $pdo->prepare("UPDATE offline_z_recovery_sync SET status = 'failed', last_error = ?, updated_at = ? WHERE id = ?")
            ->execute([$e->getMessage(), date('Y-m-d H:i:s'), $recordId]);
        throw $e;
    }
}

function restaurant_offline_z_recovery_sync_payload_by_event(PDO $pdo, string $eventUuid): ?array
{
    restaurant_offline_z_recovery_sync_ensure_schema($pdo);
    $stmt = $pdo->prepare('SELECT payload_json FROM offline_z_recovery_sync WHERE event_uuid = ? LIMIT 1');
    $stmt->execute([trim($eventUuid)]);
    $raw = $stmt->fetchColumn();
    if ($raw === false) {
        return null;
    }
    $payload = json_decode((string)$raw, true);
    return is_array($payload) ? $payload : null;
}

function restaurant_offline_z_recovery_sync_recent(PDO $pdo, int $locationId): array
{
    restaurant_offline_z_recovery_sync_ensure_schema($pdo);
    $stmt = $pdo->prepare('SELECT event_uuid, raport_id, data_raport, status, attempts, last_error, online_response_json, updated_at FROM offline_z_recovery_sync WHERE cod_locatie = ? ORDER BY id DESC LIMIT 8');
    $stmt->execute([$locationId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
