<?php
declare(strict_types=1);

function offline_transaction_reset_password_is_valid(string $password): bool
{
    return hash_equals('!Ecosoft2026*', $password);
}

function offline_transaction_reset_groups(): array
{
    return [
        'Vanzari si miscari' => [
            'discounturi_acordate', 'det_note', 'bonuri_casa_marcat', 'miscari', 'note',
            'de_listat_la_imprimanta', 'log_bonuri', 'fiscalizare_raspunsuri',
        ],
        'Inchideri si rapoarte Z' => [
            'inchideri_r_12', 'rapoarte_z', 'log_reglari_casa_marcat',
        ],
        'Intrari si documente' => [
            'achizitii', 'nir', 'continut_facturi_achizitii', 'facturi_achizitii',
            'continut_bonuri_consum', 'bonuri_consum',
            'continut_bonuri_consum_productie', 'bonuri_consum_productie',
            'detalii_devize', 'devize', 'chitante', 'chitante_personalizate',
            'dispozitii', 'facturi', 'vanzari', 'oferte_vanzari', 'oferte',
            'proforme_vanzari', 'proforme', 'retururi', 'stornari',
        ],
        'Inventariere si operatiuni auxiliare' => [
            'continut_procese_verbal_inventariere', 'procese_verbal_inventariere',
            'continut_procese_verbale_deteriorare', 'procese_verbale_deteriorare',
            'det_com_tableta', 'com_tableta', 'det_comenzi', 'comenzi',
            'eliberari_mese', 'incasari_bratari', 'incasari', 'incasari_diverse',
            'plati_diverse', 'stoc_produse', 'produse_servicii_sterse',
            'woo_orders_inbox', 'woo_sync_log',
        ],
        'Jurnale operationale locale' => [
            'audit_log', 'conectari_operatori', 'istoric_conectari',
            'istoric_incarcari', 'istoric_validari', 'ultima_conexiune',
            'ultim_bon_conectat',
        ],
        'Coada si istoricul sincronizarii' => [
            'offline_sync_exported', 'offline_sync_logs', 'offline_sync_outbox',
            'offline_sync_entity_state', 'offline_sequence_events',
            'offline_sequence_state', 'offline_tablet_sync_logs',
        ],
    ];
}

function offline_transaction_reset_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ? LIMIT 1");
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function offline_transaction_reset_columns(PDO $pdo, string $table): array
{
    if (!offline_transaction_reset_table_exists($pdo, $table)) {
        return [];
    }
    $columns = [];
    foreach ($pdo->query('PRAGMA table_info("' . $table . '")') as $row) {
        $columns[(string)$row['name']] = true;
    }
    return $columns;
}

function offline_transaction_reset_preview(PDO $pdo): array
{
    $groups = [];
    $total = 0;
    foreach (offline_transaction_reset_groups() as $label => $tables) {
        $rows = [];
        $groupTotal = 0;
        foreach ($tables as $table) {
            if (!offline_transaction_reset_table_exists($pdo, $table)) {
                continue;
            }
            $count = (int)$pdo->query('SELECT COUNT(*) FROM "' . $table . '"')->fetchColumn();
            $rows[] = ['table' => $table, 'count' => $count];
            $groupTotal += $count;
        }
        if ($rows) {
            $groups[] = ['label' => $label, 'total' => $groupTotal, 'tables' => $rows];
            $total += $groupTotal;
        }
    }

    $queuePending = 0;
    if (offline_transaction_reset_table_exists($pdo, 'offline_sync_outbox')) {
        $queuePending = (int)$pdo->query("SELECT COUNT(*) FROM offline_sync_outbox WHERE status <> 'sent'")->fetchColumn();
    }

    return [
        'groups' => $groups,
        'total' => $total,
        'queue_pending' => $queuePending,
    ];
}

function offline_transaction_reset_database_path(PDO $pdo): string
{
    foreach ($pdo->query('PRAGMA database_list') as $row) {
        if ((string)$row['name'] === 'main') {
            return (string)$row['file'];
        }
    }
    throw new RuntimeException('Calea bazei SQLite locale nu a putut fi determinata.');
}

function offline_transaction_reset_backup(PDO $pdo, array $config): array
{
    $databasePath = offline_transaction_reset_database_path($pdo);
    if ($databasePath === '' || !is_file($databasePath)) {
        throw new RuntimeException('Baza SQLite locala nu a fost gasita pentru backup.');
    }

    $backupDirectory = dirname($databasePath) . DIRECTORY_SEPARATOR . 'backups_transaction_reset';
    if (!is_dir($backupDirectory) && !mkdir($backupDirectory, 0777, true) && !is_dir($backupDirectory)) {
        throw new RuntimeException('Folderul pentru backup nu a putut fi creat.');
    }
    $stamp = date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
    $databaseBackup = $backupDirectory . DIRECTORY_SEPARATOR . 'before_transaction_reset_' . $stamp . '.sqlite';

    try {
        $pdo->exec('PRAGMA wal_checkpoint(FULL)');
    } catch (Throwable $e) {
        // Baza poate folosi journal_mode DELETE, caz in care checkpoint-ul nu este necesar.
    }
    $pdo->exec('VACUUM INTO ' . $pdo->quote($databaseBackup));
    if (!is_file($databaseBackup) || filesize($databaseBackup) === 0) {
        throw new RuntimeException('Backupul SQLite nu a fost creat corect. Curatarea a fost oprita.');
    }

    $identityBackup = '';
    if (function_exists('offline_installation_identity_state_path')) {
        $identityPath = offline_installation_identity_state_path($config);
        if (is_file($identityPath)) {
            $identityBackup = $backupDirectory . DIRECTORY_SEPARATOR . 'installation_identity_' . $stamp . '.json';
            if (!copy($identityPath, $identityBackup)) {
                throw new RuntimeException('Identitatea instalarii nu a putut fi inclusa in backup.');
            }
        }
    }

    return [
        'directory' => $backupDirectory,
        'database' => $databaseBackup,
        'identity' => $identityBackup,
    ];
}

function offline_transaction_reset_update_existing_columns(PDO $pdo, string $table, array $values): void
{
    $columns = offline_transaction_reset_columns($pdo, $table);
    $sets = [];
    foreach ($values as $column => $sqlValue) {
        if (isset($columns[$column])) {
            $sets[] = '"' . $column . '" = ' . $sqlValue;
        }
    }
    if ($sets) {
        $pdo->exec('UPDATE "' . $table . '" SET ' . implode(', ', $sets));
    }
}

function offline_transaction_reset_execute(PDO $pdo, array $config): array
{
    if (strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) !== 'sqlite') {
        throw new RuntimeException('Curatarea este permisa numai pentru baza SQLite locala.');
    }

    $preview = offline_transaction_reset_preview($pdo);
    $backup = offline_transaction_reset_backup($pdo, $config);
    $tables = [];
    foreach (offline_transaction_reset_groups() as $groupTables) {
        foreach ($groupTables as $table) {
            if (offline_transaction_reset_table_exists($pdo, $table)) {
                $tables[$table] = true;
            }
        }
    }

    $pdo->exec('PRAGMA busy_timeout = 10000');
    $pdo->exec('PRAGMA foreign_keys = OFF');
    try {
        $pdo->beginTransaction();
        foreach (array_keys($tables) as $table) {
            $pdo->exec('DELETE FROM "' . $table . '"');
        }

        if (offline_transaction_reset_table_exists($pdo, 'sqlite_sequence') && $tables) {
            $placeholders = implode(',', array_fill(0, count($tables), '?'));
            $stmt = $pdo->prepare('DELETE FROM sqlite_sequence WHERE name IN (' . $placeholders . ')');
            $stmt->execute(array_keys($tables));
        }

        offline_transaction_reset_update_existing_columns($pdo, 'admins_12', ['conectat' => '0']);
        offline_transaction_reset_update_existing_columns($pdo, 'mese', [
            'stare' => '0', 'sold' => '0', 'cod_bratara' => 'NULL',
            'date_posesor' => 'NULL', 'vandut_intrare' => '0', 'masa_comenzi_online' => '0',
        ]);
        offline_transaction_reset_update_existing_columns($pdo, 'offline_sync_runtime', [
            'lock_token' => 'NULL', 'locked_until' => 'NULL', 'last_tick_at' => 'NULL',
            'last_success_at' => 'NULL', 'last_error' => 'NULL', 'updated_at' => 'CURRENT_TIMESTAMP',
        ]);
        offline_transaction_reset_update_existing_columns($pdo, 'offline_tablet_sync_runtime', [
            'last_pull_at' => 'NULL', 'last_pull_success_at' => 'NULL', 'last_ack_at' => 'NULL',
            'last_ack_success_at' => 'NULL', 'last_error' => 'NULL', 'last_orders_received' => '0',
            'last_orders_inserted' => '0', 'last_orders_updated' => '0', 'updated_at' => 'CURRENT_TIMESTAMP',
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    } finally {
        $pdo->exec('PRAGMA foreign_keys = ON');
    }

    if (!function_exists('offline_installation_identity_rotate')) {
        throw new RuntimeException('Datele au fost golite, dar identitatea tranzactiilor nu a putut fi rotita. Backup: ' . $backup['database']);
    }
    $newTransactionUuid = offline_installation_identity_rotate($config);

    return [
        'deleted_rows' => (int)$preview['total'],
        'backup' => $backup,
        'new_transaction_uuid' => $newTransactionUuid,
    ];
}
