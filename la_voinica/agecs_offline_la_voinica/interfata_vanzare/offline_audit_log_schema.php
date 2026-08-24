<?php

function offline_audit_log_ensure_schema(PDO $pdo): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }
    $initialized = true;

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS audit_log (
            audit_id INTEGER PRIMARY KEY AUTOINCREMENT,
            table_name TEXT NOT NULL,
            operation TEXT NOT NULL,
            changed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            old_data TEXT
        )'
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_audit_log_table_changed ON audit_log (table_name, changed_at)');

    $columns = $pdo->query('PRAGMA table_info(det_note)')->fetchAll(PDO::FETCH_ASSOC);
    if (!$columns) {
        return;
    }

    $jsonPairs = [];
    foreach ($columns as $column) {
        $name = (string)$column['name'];
        $quotedName = str_replace('"', '""', $name);
        $jsonPairs[] = "'" . str_replace("'", "''", $name) . "', OLD.\"{$quotedName}\"";
    }

    $pdo->exec(
        'CREATE TRIGGER IF NOT EXISTS audit_det_note_delete
         AFTER DELETE ON det_note
         BEGIN
             INSERT INTO audit_log (table_name, operation, changed_at, old_data)
             VALUES (\'det_note\', \'DELETE\', CURRENT_TIMESTAMP, json_object(' . implode(', ', $jsonPairs) . '));
         END'
    );
}
