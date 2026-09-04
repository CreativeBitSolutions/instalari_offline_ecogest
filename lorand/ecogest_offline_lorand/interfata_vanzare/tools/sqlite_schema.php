<?php
declare(strict_types=1);

if (!defined('LORAND_SQLITE_SCHEMA_VERSION')) {
    define('LORAND_SQLITE_SCHEMA_VERSION', 1);
}

function lorand_sqlite_quote_identifier(string $identifier): string
{
    return '"' . str_replace('"', '""', $identifier) . '"';
}

function lorand_sqlite_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ? LIMIT 1");
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function lorand_sqlite_table_columns(PDO $pdo, string $table): array
{
    if (!lorand_sqlite_table_exists($pdo, $table)) {
        return [];
    }

    $stmt = $pdo->query('PRAGMA table_info(' . lorand_sqlite_quote_identifier($table) . ')');
    $columns = [];
    foreach ($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [] as $row) {
        $columns[strtolower((string)($row['name'] ?? ''))] = true;
    }
    return $columns;
}

function lorand_sqlite_add_missing_columns(PDO $pdo, string $table, array $definitions): array
{
    $columns = lorand_sqlite_table_columns($pdo, $table);
    if ($columns === []) {
        return [];
    }

    $added = [];
    foreach ($definitions as $column => $definition) {
        if (isset($columns[strtolower($column)])) {
            continue;
        }
        $pdo->exec(
            'ALTER TABLE ' . lorand_sqlite_quote_identifier($table)
            . ' ADD COLUMN ' . lorand_sqlite_quote_identifier($column) . ' ' . $definition
        );
        $columns[strtolower($column)] = true;
        $added[] = $column;
    }
    return $added;
}

function lorand_sqlite_create_index_if_table_exists(PDO $pdo, string $table, string $sql): void
{
    if (lorand_sqlite_table_exists($pdo, $table)) {
        $pdo->exec($sql);
    }
}

function lorand_sqlite_ensure_audit_schema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS audit_log (
            audit_id INTEGER PRIMARY KEY AUTOINCREMENT,
            table_name TEXT NOT NULL,
            operation TEXT NOT NULL,
            changed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            old_data TEXT
        )"
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_audit_log_table_changed ON audit_log (table_name, changed_at)');

    $columns = lorand_sqlite_table_columns($pdo, 'det_note');
    if ($columns === []) {
        return;
    }

    $jsonPairs = [];
    foreach (array_keys($columns) as $name) {
        $quotedName = str_replace('"', '""', $name);
        $jsonPairs[] = "'" . str_replace("'", "''", $name) . "', OLD.\"{$quotedName}\"";
    }

    $pdo->exec(
        "CREATE TRIGGER IF NOT EXISTS audit_det_note_delete
         AFTER DELETE ON det_note
         BEGIN
             INSERT INTO audit_log (table_name, operation, changed_at, old_data)
             VALUES ('det_note', 'DELETE', CURRENT_TIMESTAMP, json_object(" . implode(', ', $jsonPairs) . "));
         END"
    );
}

function lorand_sqlite_apply_schema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS offline_sequence_state (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            installation_uuid TEXT NOT NULL,
            client_id INTEGER NOT NULL,
            cod_locatie INTEGER NOT NULL,
            serie_casa_marcat TEXT NOT NULL DEFAULT '',
            sequence_name TEXT NOT NULL,
            last_online_value INTEGER NOT NULL DEFAULT 0,
            last_local_value INTEGER NOT NULL DEFAULT 0,
            last_synced_at TEXT NULL,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (installation_uuid, cod_locatie, serie_casa_marcat, sequence_name)
        )"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS offline_sequence_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            installation_uuid TEXT NOT NULL,
            client_id INTEGER NOT NULL,
            cod_locatie INTEGER NOT NULL,
            serie_casa_marcat TEXT NOT NULL DEFAULT '',
            sequence_name TEXT NOT NULL,
            sequence_value INTEGER NOT NULL,
            event_type TEXT NOT NULL,
            source TEXT NOT NULL DEFAULT 'offline',
            reference_type TEXT NULL,
            reference_id TEXT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_offline_sequence_events_lookup ON offline_sequence_events (installation_uuid, cod_locatie, sequence_name, sequence_value)');

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS offline_sync_outbox (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            event_uuid TEXT NOT NULL UNIQUE,
            event_type TEXT NOT NULL,
            aggregate_type TEXT NOT NULL,
            aggregate_id TEXT NOT NULL,
            cod_locatie INTEGER NOT NULL,
            payload_xml TEXT NOT NULL,
            payload_sha256 TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'pending',
            attempts INTEGER NOT NULL DEFAULT 0,
            next_attempt_at TEXT NULL,
            locked_at TEXT NULL,
            last_http_code INTEGER NULL,
            last_error TEXT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            sent_at TEXT NULL
        )"
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_offline_sync_outbox_due ON offline_sync_outbox (status, next_attempt_at, id)');
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS offline_sync_entity_state (
            entity_type TEXT NOT NULL,
            entity_id TEXT NOT NULL,
            payload_sha256 TEXT NOT NULL,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (entity_type, entity_id)
        )"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS offline_sync_runtime (
            id INTEGER PRIMARY KEY CHECK (id = 1),
            lock_token TEXT NULL,
            locked_until TEXT NULL,
            last_tick_at TEXT NULL,
            last_success_at TEXT NULL,
            last_error TEXT NULL
        )"
    );
    $pdo->exec('INSERT OR IGNORE INTO offline_sync_runtime (id) VALUES (1)');

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS offline_license_runtime (
            id INTEGER PRIMARY KEY CHECK (id = 1),
            last_seen_epoch INTEGER NOT NULL DEFAULT 0,
            last_server_epoch INTEGER NOT NULL DEFAULT 0,
            last_attempt_epoch INTEGER NOT NULL DEFAULT 0,
            last_success_epoch INTEGER NOT NULL DEFAULT 0,
            last_error TEXT NOT NULL DEFAULT '',
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
    );
    $pdo->exec('INSERT OR IGNORE INTO offline_license_runtime (id) VALUES (1)');

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS observatii_predefinite (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            text_observatie TEXT NOT NULL DEFAULT '',
            ordine INTEGER NOT NULL DEFAULT 0,
            activ INTEGER NOT NULL DEFAULT 1,
            toate_produsele INTEGER NOT NULL DEFAULT 1
        )"
    );
    lorand_sqlite_add_missing_columns($pdo, 'observatii_predefinite', [
        'toate_produsele' => 'INTEGER NOT NULL DEFAULT 1',
    ]);
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_observatii_predefinite_active_order ON observatii_predefinite(activ, ordine, id)');
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS atribuiri_observatii_produse (
            id_observatie INTEGER NOT NULL,
            cod_produs INTEGER NOT NULL,
            PRIMARY KEY (id_observatie, cod_produs)
        )"
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_atribuiri_observatii_produs ON atribuiri_observatii_produse(cod_produs)');
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS offline_reference_sync_runtime (
            id INTEGER PRIMARY KEY CHECK(id = 1),
            vat_mirrored INTEGER NOT NULL DEFAULT 0,
            last_sync_at TEXT DEFAULT NULL
        )"
    );
    $pdo->exec('INSERT OR IGNORE INTO offline_reference_sync_runtime(id, vat_mirrored) VALUES(1, 0)');
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS lorand_printer_queue_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            document_key TEXT NOT NULL UNIQUE,
            nrbon INTEGER NOT NULL,
            locatie INTEGER NOT NULL,
            status TEXT NOT NULL DEFAULT 'preparing',
            queue_file TEXT DEFAULT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
    );

    if (lorand_sqlite_table_exists($pdo, 'setari_platforma')) {
        $addedSettings = lorand_sqlite_add_missing_columns($pdo, 'setari_platforma', [
            'operator_acces_rapoarte' => 'INTEGER NOT NULL DEFAULT 1',
            'listare_nota_dupa_fiscalizare' => 'INTEGER NOT NULL DEFAULT 1',
        ]);
        $hasSettings = (bool)$pdo->query('SELECT 1 FROM setari_platforma LIMIT 1')->fetchColumn();
        if (!$hasSettings) {
            $pdo->exec('INSERT INTO setari_platforma (operator_acces_rapoarte, listare_nota_dupa_fiscalizare) VALUES (1, 1)');
        } elseif ($addedSettings !== []) {
            $pdo->exec('UPDATE setari_platforma SET operator_acces_rapoarte = 1, listare_nota_dupa_fiscalizare = 1');
        }
    }

    $transactionTables = [
        'note', 'det_note', 'discounturi_acordate', 'bonuri_casa_marcat',
        'inchideri_r_12', 'rapoarte_z', 'miscari', 'log_reglari_casa_marcat',
        'nir', 'achizitii',
    ];
    foreach ($transactionTables as $table) {
        if (!lorand_sqlite_table_exists($pdo, $table)) {
            continue;
        }
        $columns = lorand_sqlite_table_columns($pdo, $table);
        if (!isset($columns['identificator_offline'])) {
            lorand_sqlite_add_missing_columns($pdo, $table, ['identificator_offline' => 'TEXT NULL']);
        }
        $columns = lorand_sqlite_table_columns($pdo, $table);
        if (!isset($columns['cod_locatie'])) {
            lorand_sqlite_add_missing_columns($pdo, $table, ['cod_locatie' => 'INTEGER NOT NULL DEFAULT 0']);
            if (isset($columns['locatie'])) {
                $quoted = lorand_sqlite_quote_identifier($table);
                $pdo->exec("UPDATE {$quoted} SET cod_locatie = locatie WHERE cod_locatie = 0 AND COALESCE(locatie, 0) > 0");
            }
        }
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS ' . lorand_sqlite_quote_identifier('idx_' . $table . '_offline_id')
            . ' ON ' . lorand_sqlite_quote_identifier($table) . ' (identificator_offline)'
        );
    }

    lorand_sqlite_add_missing_columns($pdo, 'det_note', [
        'departament_listare' => 'TEXT NULL DEFAULT NULL',
    ]);

    $noteColumns = lorand_sqlite_table_columns($pdo, 'note');
    foreach (['nr_raport_z', 'cod_inchidere', 'valoare_vanzare_cu_tva', 'tva_colectata', 'discount', 'numerar', 'card', 'tichete', 'rest', 'protocol', 'glovo'] as $column) {
        if (isset($noteColumns[$column])) {
            $quotedColumn = lorand_sqlite_quote_identifier($column);
            $pdo->exec("UPDATE note SET {$quotedColumn} = 0 WHERE {$quotedColumn} IS NULL");
        }
    }
    $closureColumns = lorand_sqlite_table_columns($pdo, 'inchideri_r_12');
    if (isset($closureColumns['nr_raport_z'])) {
        $pdo->exec('UPDATE inchideri_r_12 SET nr_raport_z = 0 WHERE nr_raport_z IS NULL');
    }

    lorand_sqlite_create_index_if_table_exists($pdo, 'produse_servicii', 'CREATE INDEX IF NOT EXISTS idx_produse_activ_nume ON produse_servicii(activ, nume)');
    lorand_sqlite_create_index_if_table_exists($pdo, 'produse_servicii', 'CREATE INDEX IF NOT EXISTS idx_produse_categorie_activ_nume ON produse_servicii(id_categorie, activ, nume)');
    lorand_sqlite_create_index_if_table_exists($pdo, 'categorii', 'CREATE INDEX IF NOT EXISTS idx_categorii_vanzare_nume ON categorii(se_vinde, den_categ)');
    lorand_sqlite_create_index_if_table_exists($pdo, 'note', 'CREATE INDEX IF NOT EXISTS idx_note_status_locatie_operator_inchidere_nrbon ON note(status, locatie, operator, cod_inchidere, nrbon)');
    lorand_sqlite_create_index_if_table_exists($pdo, 'det_note', 'CREATE INDEX IF NOT EXISTS idx_det_note_nr_bon_id_vanz ON det_note(nr_bon, id_vanz)');
    lorand_sqlite_create_index_if_table_exists($pdo, 'det_note', 'CREATE INDEX IF NOT EXISTS idx_det_note_bon_produs_pachet_pret ON det_note(nr_bon, cod_p, pachet, pret_vanzare, id_vanz)');
    lorand_sqlite_create_index_if_table_exists($pdo, 'discounturi_acordate', 'CREATE INDEX IF NOT EXISTS idx_discounturi_id_vanz ON discounturi_acordate(id_vanz)');

    try {
        lorand_sqlite_create_index_if_table_exists($pdo, 'rapoarte_z', 'CREATE UNIQUE INDEX IF NOT EXISTS uq_rapoarte_z_offline_fiscal ON rapoarte_z (cod_locatie, serie_casa_marcat, nr_raport_z)');
    } catch (Throwable $e) {
        error_log('Nu s-a putut crea indexul fiscal Lorand pentru rapoarte_z: ' . $e->getMessage());
    }
    try {
        lorand_sqlite_create_index_if_table_exists($pdo, 'inchideri_r_12', 'CREATE UNIQUE INDEX IF NOT EXISTS uq_inchideri_offline_locatie_cod ON inchideri_r_12 (cod_locatie, cod_inchidere)');
    } catch (Throwable $e) {
        error_log('Nu s-a putut crea indexul Lorand pentru inchideri: ' . $e->getMessage());
    }

    lorand_sqlite_ensure_audit_schema($pdo);
}

function lorand_sqlite_apply_schema_if_needed(PDO $pdo): bool
{
    static $checkedConnections;
    if (!$checkedConnections instanceof SplObjectStorage) {
        $checkedConnections = new SplObjectStorage();
    }
    if ($checkedConnections->contains($pdo)) {
        return false;
    }

    $currentVersion = (int)$pdo->query('PRAGMA user_version')->fetchColumn();
    if ($currentVersion >= LORAND_SQLITE_SCHEMA_VERSION) {
        if ($currentVersion > LORAND_SQLITE_SCHEMA_VERSION) {
            error_log(
                'Schema SQLite Lorand este mai noua decat aplicatia: baza=' . $currentVersion
                . ', aplicatie=' . LORAND_SQLITE_SCHEMA_VERSION
            );
        }
        $checkedConnections->attach($pdo);
        return false;
    }

    $pdo->exec('PRAGMA journal_mode = WAL');
    $startedTransaction = false;
    try {
        $pdo->exec('BEGIN IMMEDIATE');
        $startedTransaction = true;

        $currentVersion = (int)$pdo->query('PRAGMA user_version')->fetchColumn();
        if ($currentVersion >= LORAND_SQLITE_SCHEMA_VERSION) {
            $pdo->exec('COMMIT');
            $startedTransaction = false;
            $checkedConnections->attach($pdo);
            return false;
        }

        lorand_sqlite_apply_schema($pdo);
        $pdo->exec('PRAGMA user_version = ' . LORAND_SQLITE_SCHEMA_VERSION);
        $pdo->exec('COMMIT');
        $startedTransaction = false;
        $checkedConnections->attach($pdo);
        return true;
    } catch (Throwable $error) {
        if ($startedTransaction) {
            try {
                $pdo->exec('ROLLBACK');
            } catch (Throwable $rollbackError) {
            }
        }
        throw $error;
    }
}
