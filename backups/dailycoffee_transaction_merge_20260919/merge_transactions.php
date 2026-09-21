<?php

declare(strict_types=1);

$sourcePath = 'C:/Users/Mara/Desktop/pos_cu_vanzari_extra.db';
$targetPath = 'C:/xampp/htdocs/github/instalari_offline_ecogest/dailycoffee_cu_mini_admin/api_offline_ecogest_dailycoffee/db_local/pos.db';
$installationUuid = 'dailycoffee-c2-l2-pos1-i-e5ca6a38584a468b';
$location = 2;

$source = new PDO('sqlite:' . $sourcePath);
$target = new PDO('sqlite:' . $targetPath);
$source->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$target->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$target->exec('PRAGMA busy_timeout = 10000');

function quoteIdentifier(string $name): string
{
    return '"' . str_replace('"', '""', $name) . '"';
}

function tableColumns(PDO $db, string $table): array
{
    $rows = $db->query('PRAGMA table_info(' . quoteIdentifier($table) . ')')->fetchAll(PDO::FETCH_ASSOC);
    return array_map(static fn(array $row): string => (string)$row['name'], $rows);
}

function tableRows(PDO $db, string $table): array
{
    return $db->query('SELECT * FROM ' . quoteIdentifier($table))->fetchAll(PDO::FETCH_ASSOC);
}

function normalizeValue($value): string
{
    if ($value === null) {
        return '<NULL>';
    }
    return trim((string)$value);
}

function valuesDiffer($left, $right): bool
{
    return normalizeValue($left) !== normalizeValue($right);
}

function identifierFor(string $table, string $pk, $value, string $installationUuid): string
{
    $clean = preg_replace('/[^A-Za-z0-9_-]/', '_', (string)$value);
    if ($table === 'nir') {
        return substr($installationUuid . '_dailycoffee_nir_offline_id_nir_' . $clean, 0, 191);
    }
    return substr($installationUuid . '_dailycoffee_' . $table . '_' . $clean, 0, 191);
}

function locationValue(array $sourceRow, int $fallback): int
{
    foreach (['cod_locatie', 'locatie'] as $column) {
        if (isset($sourceRow[$column]) && (int)$sourceRow[$column] > 0) {
            return (int)$sourceRow[$column];
        }
    }
    return $fallback;
}

function defaultTargetValue(string $column, array $sourceRow, ?array $existingRow, int $fallbackLocation)
{
    if ($column === 'identificator_offline') {
        return null;
    }
    if ($column === 'cod_locatie') {
        return locationValue($sourceRow, $fallbackLocation);
    }
    if (in_array($column, ['serie_casa_marcat', 'serie_memorie_fiscala'], true)) {
        return $existingRow[$column] ?? '';
    }
    if ($column === 'nui') {
        return $existingRow[$column] ?? 0;
    }
    if ($column === 'departament_listare') {
        return null;
    }
    return null;
}

function tableCount(PDO $db, string $table): int
{
    return (int)$db->query('SELECT COUNT(*) FROM ' . quoteIdentifier($table))->fetchColumn();
}

$mergeConfig = [
    'note' => 'nrbon',
    'det_note' => 'id_vanz',
    'miscari' => 'id',
    'bonuri_casa_marcat' => 'id',
];

$protectedCounts = [];
foreach (['produse_servicii', 'categorii', 'produse_servicii_locatii', 'categorii_locatii', 'achizitii', 'nir', 'inchideri_r_12', 'rapoarte_z'] as $table) {
    $protectedCounts[$table] = tableCount($target, $table);
}
$outboxBefore = tableCount($target, 'offline_sync_outbox');

$report = [
    'updated_notes' => [],
    'inserted' => [],
    'skipped_existing' => [],
    'source_counts' => [],
    'target_counts_after' => [],
];

try {
    $target->beginTransaction();

    foreach ($mergeConfig as $table => $pk) {
        $sourceColumns = tableColumns($source, $table);
        $targetColumns = tableColumns($target, $table);
        $commonColumns = array_values(array_intersect($sourceColumns, $targetColumns));
        $sourceRows = tableRows($source, $table);
        $targetRows = tableRows($target, $table);
        $sourceById = [];
        $targetById = [];

        foreach ($sourceRows as $row) {
            $sourceById[(string)$row[$pk]] = $row;
        }
        foreach ($targetRows as $row) {
            $targetById[(string)$row[$pk]] = $row;
        }

        $insertColumns = $commonColumns;
        foreach (['identificator_offline', 'cod_locatie', 'serie_casa_marcat', 'nui', 'serie_memorie_fiscala', 'departament_listare'] as $column) {
            if (in_array($column, $targetColumns, true) && !in_array($column, $insertColumns, true)) {
                $insertColumns[] = $column;
            }
        }

        $quotedInsertColumns = implode(', ', array_map('quoteIdentifier', $insertColumns));
        $insertStatement = $target->prepare(
            'INSERT INTO ' . quoteIdentifier($table) . ' (' . $quotedInsertColumns . ') VALUES (' . implode(', ', array_fill(0, count($insertColumns), '?')) . ')'
        );

        foreach ($sourceById as $id => $sourceRow) {
            $existingRow = $targetById[$id] ?? null;
            if ($existingRow !== null) {
                $updates = [];
                $updateValues = [];
                foreach ($commonColumns as $column) {
                    if (valuesDiffer($sourceRow[$column] ?? null, $existingRow[$column] ?? null)) {
                        $updates[] = quoteIdentifier($column) . ' = ?';
                        $updateValues[] = $sourceRow[$column] ?? null;
                    }
                }

                if (in_array('cod_locatie', $targetColumns, true)) {
                    $sourceLocation = locationValue($sourceRow, $location);
                    if ((int)($existingRow['cod_locatie'] ?? 0) !== $sourceLocation) {
                        $updates[] = quoteIdentifier('cod_locatie') . ' = ?';
                        $updateValues[] = $sourceLocation;
                    }
                }
                if (in_array('identificator_offline', $targetColumns, true) && trim((string)($existingRow['identificator_offline'] ?? '')) === '') {
                    $updates[] = quoteIdentifier('identificator_offline') . ' = ?';
                    $updateValues[] = identifierFor($table, $pk, $id, $installationUuid);
                }

                if ($updates) {
                    $updateValues[] = $existingRow[$pk];
                    $statement = $target->prepare(
                        'UPDATE ' . quoteIdentifier($table) . ' SET ' . implode(', ', $updates) . ' WHERE ' . quoteIdentifier($pk) . ' = ?'
                    );
                    $statement->execute($updateValues);
                    if ($table === 'note') {
                        $report['updated_notes'][] = (int)$id;
                    }
                } else {
                    $report['skipped_existing'][$table][] = (int)$id;
                }
                continue;
            }

            $values = [];
            foreach ($insertColumns as $column) {
                if (array_key_exists($column, $sourceRow)) {
                    $values[] = $sourceRow[$column];
                    continue;
                }
                if ($column === 'identificator_offline') {
                    $values[] = identifierFor($table, $pk, $id, $installationUuid);
                    continue;
                }
                $values[] = defaultTargetValue($column, $sourceRow, null, $location);
            }
            $insertStatement->execute($values);
            $report['inserted'][$table] = ($report['inserted'][$table] ?? 0) + 1;
        }
    }

    foreach ($protectedCounts as $table => $before) {
        $after = tableCount($target, $table);
        if ($after !== $before && in_array($table, ['produse_servicii', 'categorii', 'produse_servicii_locatii', 'categorii_locatii', 'achizitii', 'nir', 'inchideri_r_12', 'rapoarte_z'], true)) {
            throw new RuntimeException('Tabel protejat modificat: ' . $table);
        }
        $report['target_counts_after'][$table] = $after;
    }

    $report['target_counts_after']['offline_sync_outbox_before'] = $outboxBefore;
    $report['target_counts_after']['offline_sync_outbox_after'] = tableCount($target, 'offline_sync_outbox');
    if ($report['target_counts_after']['offline_sync_outbox_after'] !== $outboxBefore) {
        throw new RuntimeException('Coada offline a fost modificata neasteptat.');
    }

    foreach ($mergeConfig as $table => $pk) {
        $report['source_counts'][$table] = tableCount($source, $table);
        $report['target_counts_after'][$table] = tableCount($target, $table);
    }

    $target->commit();
    $report['status'] = 'success';
} catch (Throwable $exception) {
    if ($target->inTransaction()) {
        $target->rollBack();
    }
    $report['status'] = 'rolled_back';
    $report['error'] = $exception->getMessage();
    fwrite(STDERR, json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);
    exit(1);
}

echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
