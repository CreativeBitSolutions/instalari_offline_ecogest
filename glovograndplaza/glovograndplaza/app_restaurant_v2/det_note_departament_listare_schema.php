<?php

if (!function_exists('agecs_ensure_det_note_departament_listare')) {
    function agecs_ensure_det_note_departament_listare(PDO $pdo, string $tableName = 'det_note'): bool
    {
        static $results = [];

        if (!preg_match('/^[A-Za-z0-9_]+$/D', $tableName)) {
            error_log('agecs_ensure_det_note_departament_listare: nume de tabel invalid');
            return false;
        }

        $cacheKey = spl_object_hash($pdo) . ':' . $tableName;
        if (array_key_exists($cacheKey, $results)) {
            return $results[$cacheKey];
        }

        try {
            $driver = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
            if ($driver === 'sqlite') {
                $columns = $pdo->query('PRAGMA table_info("' . $tableName . '")')->fetchAll(PDO::FETCH_ASSOC);
                foreach ($columns as $column) {
                    if (($column['name'] ?? '') === 'departament_listare') {
                        return $results[$cacheKey] = true;
                    }
                }
                $pdo->exec('ALTER TABLE "' . $tableName . '" ADD COLUMN "departament_listare" TEXT DEFAULT NULL');
            } else {
                $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$tableName}` LIKE ?");
                $stmt->execute(['departament_listare']);
                if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                    $pdo->exec("ALTER TABLE `{$tableName}` ADD COLUMN `departament_listare` VARCHAR(50) NULL DEFAULT NULL");
                }
            }

            return $results[$cacheKey] = true;
        } catch (Throwable $e) {
            error_log('agecs_ensure_det_note_departament_listare: ' . $e->getMessage());
            return $results[$cacheKey] = false;
        }
    }
}

if (!function_exists('agecs_snapshot_det_note_departamente')) {
    function agecs_snapshot_det_note_departamente(
        PDO $pdo,
        int $nrBon,
        string $detNoteTable = 'det_note',
        string $produseTable = 'produse_servicii'
    ): bool {
        if ($nrBon <= 0) {
            return false;
        }

        foreach ([$detNoteTable, $produseTable] as $tableName) {
            if (!preg_match('/^[A-Za-z0-9_]+$/D', $tableName)) {
                error_log('agecs_snapshot_det_note_departamente: nume de tabel invalid');
                return false;
            }
        }

        if (!agecs_ensure_det_note_departament_listare($pdo, $detNoteTable)) {
            return false;
        }

        try {
            $driver = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
            if ($driver === 'sqlite') {
                $stmt = $pdo->prepare("
                    UPDATE \"{$detNoteTable}\"
                    SET departament_listare = NULLIF(TRIM((
                        SELECT ps.departament
                        FROM \"{$produseTable}\" ps
                        WHERE ps.cod_produs = \"{$detNoteTable}\".cod_p
                        LIMIT 1
                    )), '')
                    WHERE nr_bon = :nr_bon
                      AND (departament_listare IS NULL OR TRIM(departament_listare) = '')
                ");
            } else {
                $stmt = $pdo->prepare("
                    UPDATE `{$detNoteTable}` dn
                    INNER JOIN `{$produseTable}` ps ON ps.cod_produs = dn.cod_p
                    SET dn.departament_listare = NULLIF(TRIM(ps.departament), '')
                    WHERE dn.nr_bon = :nr_bon
                      AND (dn.departament_listare IS NULL OR TRIM(dn.departament_listare) = '')
                ");
            }
            $stmt->execute([':nr_bon' => $nrBon]);
            return true;
        } catch (Throwable $e) {
            error_log('agecs_snapshot_det_note_departamente: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('agecs_departament_listare_sql')) {
    function agecs_departament_listare_sql(string $detAlias = 'dn', string $produsAlias = 'ps'): string
    {
        foreach ([$detAlias, $produsAlias] as $alias) {
            if (!preg_match('/^[A-Za-z0-9_]+$/D', $alias)) {
                throw new InvalidArgumentException('Alias SQL invalid pentru departament_listare.');
            }
        }

        return "COALESCE(NULLIF(TRIM({$detAlias}.departament_listare), ''), NULLIF(TRIM({$produsAlias}.departament), ''))";
    }
}
