<?php

if (!function_exists('restaurant_v2_ensure_det_note_site_import_column')) {
    function restaurant_v2_ensure_det_note_site_import_column(PDO $pdo, string $tableName = 'det_note'): bool
    {
        static $results = [];

        if (!preg_match('/^[A-Za-z0-9_]+$/D', $tableName)) {
            error_log('restaurant_v2_ensure_det_note_site_import_column: nume de tabel invalid');
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
                    if (($column['name'] ?? '') === 'importat_din_site') {
                        return $results[$cacheKey] = true;
                    }
                }
                $pdo->exec('ALTER TABLE "' . $tableName . '" ADD COLUMN "importat_din_site" INTEGER DEFAULT NULL');
            } else {
                $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$tableName}` LIKE ?");
                $stmt->execute(['importat_din_site']);
                if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                    $pdo->exec("ALTER TABLE `{$tableName}` ADD COLUMN `importat_din_site` BIGINT UNSIGNED NULL DEFAULT NULL");
                }
            }

            return $results[$cacheKey] = true;
        } catch (Throwable $e) {
            error_log('restaurant_v2_ensure_det_note_site_import_column: ' . $e->getMessage());
            return $results[$cacheKey] = false;
        }
    }
}
