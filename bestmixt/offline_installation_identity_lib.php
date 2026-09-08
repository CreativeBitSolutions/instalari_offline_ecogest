<?php

if (!function_exists('offline_installation_identity_client_id')) {
    function offline_installation_identity_client_id(array $config): int
    {
        return (int)($config['sync_client_id'] ?? $config['client_id'] ?? 0);
    }
}

if (!function_exists('offline_installation_identity_location')) {
    function offline_installation_identity_location(array $config): int
    {
        return (int)($config['cod_locatie_default'] ?? $config['cod_locatie'] ?? 1);
    }
}

if (!function_exists('offline_installation_identity_database_path')) {
    function offline_installation_identity_database_path(array $config): string
    {
        return trim((string)($config['db_runtime_file'] ?? $config['sqlite_path'] ?? ''));
    }
}

if (!function_exists('offline_installation_identity_state_path')) {
    function offline_installation_identity_state_path(array $config): string
    {
        $configured = trim((string)($config['installation_identity_file'] ?? ''));
        if ($configured !== '') {
            return $configured;
        }

        $databasePath = offline_installation_identity_database_path($config);
        if ($databasePath === '') {
            throw new RuntimeException('Calea bazei locale lipseste din configurarea identitatii instalarii.');
        }

        return dirname($databasePath) . DIRECTORY_SEPARATOR . 'offline_installation_identity.json';
    }
}

if (!function_exists('offline_installation_identity_sanitize')) {
    function offline_installation_identity_sanitize(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9_-]+/', '-', trim($value));
        $value = trim((string)$value, '-_');
        return $value !== '' ? $value : 'offline-installation';
    }
}

if (!function_exists('offline_installation_identity_random_suffix')) {
    function offline_installation_identity_random_suffix(): string
    {
        try {
            return bin2hex(random_bytes(8));
        } catch (Throwable $e) {
            return substr(hash('sha256', uniqid((string)mt_rand(), true)), 0, 16);
        }
    }
}

if (!function_exists('offline_installation_identity_read_state')) {
    function offline_installation_identity_read_state(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('offline_installation_identity_write_state')) {
    function offline_installation_identity_write_state(string $path, array $state): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException('Folderul identitatii instalarii nu poate fi creat: ' . $directory);
        }
        $json = json_encode($state, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false || file_put_contents($path, $json . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Identitatea instalarii nu poate fi salvata: ' . $path);
        }
    }
}

if (!function_exists('offline_installation_identity_pk_map')) {
    function offline_installation_identity_pk_map(): array
    {
        return [
            'note' => 'nrbon',
            'det_note' => 'id_vanz',
            'discounturi_acordate' => 'id_discount',
            'bonuri_casa_marcat' => 'id',
            'inchideri_r_12' => 'id_inch',
            'rapoarte_z' => 'id',
            'nir' => 'id_nir',
            'achizitii' => 'id_achiz',
            'miscari' => 'id',
            'log_reglari_casa_marcat' => 'id',
        ];
    }
}

if (!function_exists('offline_installation_identity_table_exists')) {
    function offline_installation_identity_table_exists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ? LIMIT 1");
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    }
}

if (!function_exists('offline_installation_identity_columns')) {
    function offline_installation_identity_columns(PDO $pdo, string $table): array
    {
        $columns = [];
        foreach ($pdo->query('PRAGMA table_info("' . $table . '")') as $row) {
            $columns[(string)$row['name']] = true;
        }
        return $columns;
    }
}

if (!function_exists('offline_installation_identity_ensure_column')) {
    function offline_installation_identity_ensure_column(PDO $pdo, string $table): bool
    {
        static $checkedConnections;
        if (!$checkedConnections instanceof SplObjectStorage) {
            $checkedConnections = new SplObjectStorage();
        }
        $checkedTables = $checkedConnections->contains($pdo) ? $checkedConnections[$pdo] : [];
        if (isset($checkedTables[$table])) {
            return true;
        }
        if (!offline_installation_identity_table_exists($pdo, $table)) {
            return false;
        }
        $columns = offline_installation_identity_columns($pdo, $table);
        if (!isset($columns['identificator_offline'])) {
            $pdo->exec('ALTER TABLE "' . $table . '" ADD COLUMN identificator_offline TEXT NULL');
        }
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS "uq_offline_ident_' . $table . '" ON "' . $table . '" (identificator_offline) WHERE identificator_offline IS NOT NULL AND TRIM(identificator_offline) <> \'\'');
        $checkedTables[$table] = true;
        $checkedConnections[$pdo] = $checkedTables;
        return true;
    }
}

if (!function_exists('offline_installation_identity_for_row')) {
    function offline_installation_identity_for_row(array $config, string $table, array $row, ?string $uuid = null): string
    {
        $existing = trim((string)($row['identificator_offline'] ?? ''));
        if ($existing !== '') {
            return substr($existing, 0, 191);
        }

        $pkMap = offline_installation_identity_pk_map();
        $pk = $pkMap[$table] ?? 'id';
        $value = preg_replace('/[^A-Za-z0-9_-]/', '_', (string)($row[$pk] ?? '0'));
        $clientId = offline_installation_identity_client_id($config);
        $location = offline_installation_identity_location($config);
        $uuid = offline_installation_identity_sanitize((string)(
            $uuid ?? $config['transaction_uuid'] ?? $config['installation_uuid'] ?? ''
        ));
        $format = strtolower(trim((string)($config['installation_identity_format'] ?? 'store')));

        if ($format === 'restaurant') {
            return substr($table . ':' . $location . ':' . $uuid . ':' . $value, 0, 191);
        }

        if ($clientId === 2) {
            $prefix = $uuid . '_dailycoffee';
            if ($table === 'nir') {
                $nrNir = preg_replace('/[^A-Za-z0-9_-]/', '_', (string)($row['nr_nir'] ?? '0'));
                return substr($prefix . '_nir_offline_nr_nir_' . $nrNir . '_id_nir_' . $value, 0, 191);
            }
            return substr($prefix . '_' . $table . '_' . $value, 0, 191);
        }

        return substr($uuid . '_' . $table . '_' . $value, 0, 191);
    }
}

if (!function_exists('offline_installation_identity_backfill')) {
    function offline_installation_identity_backfill(PDO $pdo, array $config, string $uuid): void
    {
        foreach (offline_installation_identity_pk_map() as $table => $pk) {
            if (!offline_installation_identity_ensure_column($pdo, $table)) {
                continue;
            }
            $columns = offline_installation_identity_columns($pdo, $table);
            if (!isset($columns[$pk])) {
                continue;
            }
            $rows = $pdo->query('SELECT * FROM "' . $table . '" WHERE TRIM(COALESCE(identificator_offline, \'\')) = \'\'')->fetchAll(PDO::FETCH_ASSOC);
            if (!$rows) {
                continue;
            }
            $update = $pdo->prepare('UPDATE "' . $table . '" SET identificator_offline = ? WHERE "' . $pk . '" = ? AND TRIM(COALESCE(identificator_offline, \'\')) = \'\'');
            foreach ($rows as $row) {
                $update->execute([
                    offline_installation_identity_for_row($config, $table, $row, $uuid),
                    $row[$pk],
                ]);
            }
        }
    }
}

if (!function_exists('offline_installation_identity_initialize_database')) {
    function offline_installation_identity_initialize_database(array $config, string $statePath, array &$state): void
    {
        if (!empty($state['legacy_backfill_completed'])) {
            return;
        }
        $databasePath = offline_installation_identity_database_path($config);
        if ($databasePath === '' || !is_file($databasePath)) {
            return;
        }

        $pdo = new PDO('sqlite:' . $databasePath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->beginTransaction();
        try {
            offline_installation_identity_backfill(
                $pdo,
                $config,
                (string)($state['legacy_installation_uuid'] ?? $config['installation_uuid_legacy'] ?? $config['installation_uuid'])
            );
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $state['legacy_backfill_completed'] = true;
        $state['legacy_backfill_completed_at'] = date('c');
        offline_installation_identity_write_state($statePath, $state);
    }
}

if (!function_exists('offline_installation_identity_apply_config')) {
    function offline_installation_identity_apply_config(array $config): array
    {
        $legacyUuid = offline_installation_identity_sanitize((string)($config['installation_uuid'] ?? ''));
        $clientId = offline_installation_identity_client_id($config);
        $location = offline_installation_identity_location($config);
        $statePath = offline_installation_identity_state_path($config);
        $lockPath = $statePath . '.lock';
        $lockDir = dirname($lockPath);
        if (!is_dir($lockDir) && !mkdir($lockDir, 0777, true) && !is_dir($lockDir)) {
            throw new RuntimeException('Folderul identitatii instalarii nu poate fi creat: ' . $lockDir);
        }

        $lock = fopen($lockPath, 'c+');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            throw new RuntimeException('Identitatea instalarii nu poate fi blocata pentru initializare.');
        }
        try {
            $state = offline_installation_identity_read_state($statePath);
            $stateIsValid =
                trim((string)($state['installation_uuid'] ?? '')) !== '' &&
                (int)($state['client_id'] ?? 0) === $clientId &&
                (int)($state['cod_locatie'] ?? 0) === $location;
            if (!$stateIsValid) {
                $state = [
                    'version' => 1,
                    'installation_uuid' => substr($legacyUuid . '-i-' . offline_installation_identity_random_suffix(), 0, 120),
                    'legacy_installation_uuid' => $legacyUuid,
                    'client_id' => $clientId,
                    'cod_locatie' => $location,
                    'created_at' => date('c'),
                    'legacy_backfill_completed' => false,
                ];
                $state['transaction_uuid'] = $state['installation_uuid'];
                offline_installation_identity_write_state($statePath, $state);
            }

            if (trim((string)($state['transaction_uuid'] ?? '')) === '') {
                $state['transaction_uuid'] = (string)$state['installation_uuid'];
                offline_installation_identity_write_state($statePath, $state);
            }

            $config['installation_uuid_configured'] = $legacyUuid;
            $config['installation_uuid_legacy'] = offline_installation_identity_sanitize((string)($state['legacy_installation_uuid'] ?? $legacyUuid));
            $config['installation_uuid'] = offline_installation_identity_sanitize((string)$state['installation_uuid']);
            $config['transaction_uuid'] = offline_installation_identity_sanitize((string)$state['transaction_uuid']);
            $config['installation_identity_file'] = $statePath;
            if (isset($config['online_tablet_sync']) && is_array($config['online_tablet_sync'])) {
                $config['online_tablet_sync']['installation_uuid'] = $config['installation_uuid'];
            }

            offline_installation_identity_initialize_database($config, $statePath, $state);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        return $config;
    }
}

if (!function_exists('offline_installation_identity_stabilize_rows')) {
    function offline_installation_identity_stabilize_rows(PDO $pdo, string $table, array $rows, array $config): array
    {
        if (!$rows || !isset(offline_installation_identity_pk_map()[$table])) {
            return $rows;
        }
        if (!offline_installation_identity_ensure_column($pdo, $table)) {
            return $rows;
        }
        $pk = offline_installation_identity_pk_map()[$table];
        $update = $pdo->prepare('UPDATE "' . $table . '" SET identificator_offline = ? WHERE "' . $pk . '" = ? AND TRIM(COALESCE(identificator_offline, \'\')) = \'\'');
        $read = $pdo->prepare('SELECT identificator_offline FROM "' . $table . '" WHERE "' . $pk . '" = ? LIMIT 1');

        foreach ($rows as &$row) {
            if (trim((string)($row['identificator_offline'] ?? '')) !== '') {
                continue;
            }
            $identifier = offline_installation_identity_for_row($config, $table, $row);
            $update->execute([$identifier, $row[$pk]]);
            if ($update->rowCount() === 0) {
                $read->execute([$row[$pk]]);
                $identifier = (string)$read->fetchColumn();
            }
            $row['identificator_offline'] = $identifier;
        }
        unset($row);
        return $rows;
    }
}

if (!function_exists('offline_installation_identity_rotate')) {
    function offline_installation_identity_rotate(array $config): string
    {
        $statePath = offline_installation_identity_state_path($config);
        $lockPath = $statePath . '.lock';
        $lockDir = dirname($lockPath);
        if (!is_dir($lockDir) && !mkdir($lockDir, 0777, true) && !is_dir($lockDir)) {
            throw new RuntimeException('Folderul identitatii instalarii nu poate fi creat: ' . $lockDir);
        }

        $lock = fopen($lockPath, 'c+');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            throw new RuntimeException('Identitatea instalarii nu poate fi blocata pentru resetare.');
        }

        try {
            $state = offline_installation_identity_read_state($statePath);
            $installationUuid = offline_installation_identity_sanitize((string)(
                $state['installation_uuid'] ?? $config['installation_uuid'] ?? 'offline-installation'
            ));
            $legacyUuid = offline_installation_identity_sanitize((string)(
                $state['legacy_installation_uuid']
                ?? $config['installation_uuid_configured']
                ?? $config['installation_uuid_legacy']
                ?? $installationUuid
            ));
            $newUuid = substr($installationUuid . '-t-' . offline_installation_identity_random_suffix(), 0, 120);
            $state = array_merge($state, [
                'version' => 1,
                'installation_uuid' => $installationUuid,
                'transaction_uuid' => $newUuid,
                'legacy_installation_uuid' => $legacyUuid,
                'client_id' => offline_installation_identity_client_id($config),
                'cod_locatie' => offline_installation_identity_location($config),
                'legacy_backfill_completed' => true,
                'transaction_rotated_at' => date('c'),
            ]);
            if (empty($state['created_at'])) {
                $state['created_at'] = date('c');
            }
            offline_installation_identity_write_state($statePath, $state);
            return $newUuid;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
