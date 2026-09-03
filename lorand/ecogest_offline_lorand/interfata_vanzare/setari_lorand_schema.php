<?php
declare(strict_types=1);

if (!function_exists('vanzare_v2_ensure_lorand_settings')) {
    function vanzare_v2_ensure_lorand_settings(PDO $pdo): bool
    {
        static $results = [];
        $connectionKey = function_exists('spl_object_id') ? spl_object_id($pdo) : 1;
        if (array_key_exists($connectionKey, $results)) {
            return $results[$connectionKey];
        }

        try {
            $driver = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
            $columns = [];

            if ($driver === 'sqlite') {
                $stmt = $pdo->query("PRAGMA table_info('setari_platforma')");
                foreach ($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [] as $column) {
                    $columns[strtolower((string)($column['name'] ?? ''))] = true;
                }
            } else {
                $stmt = $pdo->query('SHOW COLUMNS FROM `setari_platforma`');
                foreach ($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [] as $column) {
                    $columns[strtolower((string)($column['Field'] ?? ''))] = true;
                }
            }

            $addedColumn = false;
            if (!isset($columns['operator_acces_rapoarte'])) {
                $pdo->exec($driver === 'sqlite'
                    ? 'ALTER TABLE setari_platforma ADD COLUMN operator_acces_rapoarte INTEGER NOT NULL DEFAULT 1'
                    : 'ALTER TABLE `setari_platforma` ADD COLUMN `operator_acces_rapoarte` TINYINT(1) NOT NULL DEFAULT 1');
                $addedColumn = true;
            }

            if (!isset($columns['listare_nota_dupa_fiscalizare'])) {
                $pdo->exec($driver === 'sqlite'
                    ? 'ALTER TABLE setari_platforma ADD COLUMN listare_nota_dupa_fiscalizare INTEGER NOT NULL DEFAULT 1'
                    : 'ALTER TABLE `setari_platforma` ADD COLUMN `listare_nota_dupa_fiscalizare` TINYINT(1) NOT NULL DEFAULT 1');
                $addedColumn = true;
            }

            $hasRow = (bool)$pdo->query('SELECT 1 FROM setari_platforma LIMIT 1')->fetchColumn();
            if (!$hasRow) {
                $pdo->exec('INSERT INTO setari_platforma (operator_acces_rapoarte, listare_nota_dupa_fiscalizare) VALUES (1, 1)');
            } elseif ($addedColumn) {
                $pdo->exec('UPDATE setari_platforma SET operator_acces_rapoarte = 1, listare_nota_dupa_fiscalizare = 1');
            }

            $results[$connectionKey] = true;
        } catch (Throwable $e) {
            error_log('vanzare_v2_ensure_lorand_settings: ' . $e->getMessage());
            $results[$connectionKey] = false;
        }

        return $results[$connectionKey];
    }
}

if (!function_exists('vanzare_v2_ensure_lorand_offline_schema')) {
    function vanzare_v2_ensure_lorand_offline_schema(PDO $pdo): bool
    {
        static $results = [];
        $connectionKey = function_exists('spl_object_id') ? spl_object_id($pdo) : 1;
        if (array_key_exists($connectionKey, $results)) {
            return $results[$connectionKey];
        }

        try {
            if (strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) !== 'sqlite') {
                $results[$connectionKey] = true;
                return true;
            }

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS observatii_predefinite (
                    id INTEGER PRIMARY KEY,
                    text_observatie TEXT NOT NULL DEFAULT \'\',
                    ordine INTEGER NOT NULL DEFAULT 0,
                    activ INTEGER NOT NULL DEFAULT 1,
                    toate_produsele INTEGER NOT NULL DEFAULT 1
                )'
            );
            $observationColumns = [];
            $observationColumnsStmt = $pdo->query("PRAGMA table_info('observatii_predefinite')");
            foreach ($observationColumnsStmt ? $observationColumnsStmt->fetchAll(PDO::FETCH_ASSOC) : [] as $column) {
                $observationColumns[strtolower((string)($column['name'] ?? ''))] = true;
            }
            if (!isset($observationColumns['toate_produsele'])) {
                $pdo->exec('ALTER TABLE observatii_predefinite ADD COLUMN toate_produsele INTEGER NOT NULL DEFAULT 1');
            }
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS atribuiri_observatii_produse (
                    id_observatie INTEGER NOT NULL,
                    cod_produs INTEGER NOT NULL,
                    PRIMARY KEY (id_observatie, cod_produs)
                )'
            );
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_atribuiri_observatii_produs ON atribuiri_observatii_produse(cod_produs)');
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS offline_reference_sync_runtime (
                    id INTEGER PRIMARY KEY CHECK(id = 1),
                    vat_mirrored INTEGER NOT NULL DEFAULT 0,
                    last_sync_at TEXT DEFAULT NULL
                )'
            );
            $pdo->exec('INSERT OR IGNORE INTO offline_reference_sync_runtime(id, vat_mirrored) VALUES(1, 0)');
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS lorand_printer_queue_history (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    document_key TEXT NOT NULL UNIQUE,
                    nrbon INTEGER NOT NULL,
                    locatie INTEGER NOT NULL,
                    status TEXT NOT NULL DEFAULT \'preparing\',
                    queue_file TEXT DEFAULT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
                )'
            );

            $results[$connectionKey] = true;
        } catch (Throwable $e) {
            error_log('vanzare_v2_ensure_lorand_offline_schema: ' . $e->getMessage());
            $results[$connectionKey] = false;
        }

        return $results[$connectionKey];
    }
}

if (!function_exists('vanzare_v2_lorand_settings')) {
    function vanzare_v2_lorand_settings(PDO $pdo): array
    {
        $defaults = [
            'operator_acces_rapoarte' => false,
            'listare_nota_dupa_fiscalizare' => false,
        ];
        if (!vanzare_v2_ensure_lorand_settings($pdo)) {
            return $defaults;
        }

        try {
            $stmt = $pdo->query('SELECT operator_acces_rapoarte, listare_nota_dupa_fiscalizare FROM setari_platforma LIMIT 1');
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
            if (!$row) {
                return $defaults;
            }

            return [
                'operator_acces_rapoarte' => (int)($row['operator_acces_rapoarte'] ?? 0) === 1,
                'listare_nota_dupa_fiscalizare' => (int)($row['listare_nota_dupa_fiscalizare'] ?? 0) === 1,
            ];
        } catch (Throwable $e) {
            error_log('vanzare_v2_lorand_settings: ' . $e->getMessage());
            return $defaults;
        }
    }
}

if (!function_exists('vanzare_v2_save_lorand_settings')) {
    function vanzare_v2_save_lorand_settings(PDO $pdo, bool $reportsEnabled, bool $printAfterFiscal): bool
    {
        if (!vanzare_v2_ensure_lorand_settings($pdo)) {
            return false;
        }

        try {
            $stmt = $pdo->prepare(
                'UPDATE setari_platforma
                    SET operator_acces_rapoarte = :reports,
                        listare_nota_dupa_fiscalizare = :printing'
            );
            return $stmt->execute([
                ':reports' => $reportsEnabled ? 1 : 0,
                ':printing' => $printAfterFiscal ? 1 : 0,
            ]);
        } catch (Throwable $e) {
            error_log('vanzare_v2_save_lorand_settings: ' . $e->getMessage());
            return false;
        }
    }
}
