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
            if ($driver === 'sqlite') {
                require_once __DIR__ . '/tools/sqlite_schema.php';
                lorand_sqlite_apply_schema_if_needed($pdo);
                $results[$connectionKey] = true;
                return true;
            }
            $columns = [];

            $stmt = $pdo->query('SHOW COLUMNS FROM `setari_platforma`');
            foreach ($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [] as $column) {
                $columns[strtolower((string)($column['Field'] ?? ''))] = true;
            }

            $addedColumn = false;
            if (!isset($columns['operator_acces_rapoarte'])) {
                $pdo->exec('ALTER TABLE `setari_platforma` ADD COLUMN `operator_acces_rapoarte` TINYINT(1) NOT NULL DEFAULT 1');
                $addedColumn = true;
            }

            if (!isset($columns['listare_nota_dupa_fiscalizare'])) {
                $pdo->exec('ALTER TABLE `setari_platforma` ADD COLUMN `listare_nota_dupa_fiscalizare` TINYINT(1) NOT NULL DEFAULT 1');
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

            require_once __DIR__ . '/tools/sqlite_schema.php';
            lorand_sqlite_apply_schema_if_needed($pdo);

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
