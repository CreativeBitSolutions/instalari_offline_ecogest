<?php

if (!function_exists('restaurant_v2_ensure_skip_bar_command_print_column')) {
    function restaurant_v2_ensure_skip_bar_command_print_column(PDO $pdo): bool
    {
        static $results = [];
        $connectionKey = function_exists('spl_object_id') ? spl_object_id($pdo) : 1;
        $sessionKey = 'schema_omite_listare_bar_la_trimitere_' . (int)($_SESSION['client_id'] ?? 0);

        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION[$sessionKey])) {
            return true;
        }

        if (array_key_exists($connectionKey, $results)) {
            return $results[$connectionKey];
        }

        try {
            $driver = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
            $columnExists = false;

            if ($driver === 'sqlite') {
                $columns = $pdo->query("PRAGMA table_info('setari_platforma')");
                foreach ($columns ? $columns->fetchAll(PDO::FETCH_ASSOC) : [] as $column) {
                    if (strcasecmp((string)($column['name'] ?? ''), 'omite_listare_bar_la_trimitere') === 0) {
                        $columnExists = true;
                        break;
                    }
                }
            } else {
                $stmt = $pdo->prepare("SHOW COLUMNS FROM `setari_platforma` LIKE ?");
                $stmt->execute(['omite_listare_bar_la_trimitere']);
                $columnExists = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
            }

            if (!$columnExists) {
                $pdo->exec($driver === 'sqlite'
                    ? "ALTER TABLE setari_platforma ADD COLUMN omite_listare_bar_la_trimitere INTEGER NOT NULL DEFAULT 0"
                    : "ALTER TABLE `setari_platforma` ADD COLUMN `omite_listare_bar_la_trimitere` TINYINT(1) NOT NULL DEFAULT 0");
            }

            $results[$connectionKey] = true;
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION[$sessionKey] = 1;
            }
        } catch (Throwable $e) {
            error_log('restaurant_v2_ensure_skip_bar_command_print_column: ' . $e->getMessage());
            $results[$connectionKey] = false;
        }

        return $results[$connectionKey];
    }
}

if (!function_exists('restaurant_v2_is_skip_bar_command_print_enabled')) {
    function restaurant_v2_is_skip_bar_command_print_enabled(PDO $pdo): bool
    {
        if (!restaurant_v2_ensure_skip_bar_command_print_column($pdo)) {
            return false;
        }

        try {
            $stmt = $pdo->query("SELECT COALESCE(omite_listare_bar_la_trimitere, 0) FROM setari_platforma LIMIT 1");
            $value = $stmt ? $stmt->fetchColumn() : false;
            return $value !== false && (int)$value === 1;
        } catch (Throwable $e) {
            error_log('restaurant_v2_is_skip_bar_command_print_enabled: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('restaurant_v2_set_skip_bar_command_print_enabled')) {
    function restaurant_v2_set_skip_bar_command_print_enabled(PDO $pdo, bool $enabled): bool
    {
        if (!restaurant_v2_ensure_skip_bar_command_print_column($pdo)) {
            return false;
        }

        try {
            $hasRow = (bool)$pdo->query("SELECT 1 FROM setari_platforma LIMIT 1")->fetchColumn();
            if ($hasRow) {
                $stmt = $pdo->prepare("UPDATE setari_platforma SET omite_listare_bar_la_trimitere = :enabled");
            } else {
                $stmt = $pdo->prepare("INSERT INTO setari_platforma (omite_listare_bar_la_trimitere) VALUES (:enabled)");
            }
            return $stmt->execute([':enabled' => $enabled ? 1 : 0]);
        } catch (Throwable $e) {
            error_log('restaurant_v2_set_skip_bar_command_print_enabled: ' . $e->getMessage());
            return false;
        }
    }
}

?>
