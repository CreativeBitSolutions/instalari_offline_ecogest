<?php

function offline_import_schema_quote_identifier(string $identifier): string
{
    return '"' . str_replace('"', '""', $identifier) . '"';
}

function offline_import_schema_table_columns(PDO $pdo, string $table): array
{
    $statement = $pdo->query('PRAGMA table_info(' . offline_import_schema_quote_identifier($table) . ')');
    $columns = [];

    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $columns[strtolower((string)$column['name'])] = true;
    }

    return $columns;
}

function offline_import_schema_ensure(PDO $pdo): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }
    $initialized = true;

    $tables = [
        'note',
        'det_note',
        'discounturi_acordate',
        'bonuri_casa_marcat',
        'inchideri_r_12',
        'rapoarte_z',
        'miscari',
        'log_reglari_casa_marcat',
    ];

    foreach ($tables as $table) {
        $columns = offline_import_schema_table_columns($pdo, $table);
        if ($columns === []) {
            continue;
        }

        $quotedTable = offline_import_schema_quote_identifier($table);
        if (!isset($columns['identificator_offline'])) {
            $pdo->exec('ALTER TABLE ' . $quotedTable . ' ADD COLUMN "identificator_offline" TEXT');
        }

        // Older sales tables use the legacy `locatie` field. The import contract
        // also requires `cod_locatie`, fixed to this installation's location.
        if (!isset($columns['cod_locatie'])) {
            $pdo->exec('ALTER TABLE ' . $quotedTable . ' ADD COLUMN "cod_locatie" INTEGER NOT NULL DEFAULT 1');
        }

        $index = offline_import_schema_quote_identifier('idx_' . $table . '_identificator_offline');
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS ' . $index
            . ' ON ' . $quotedTable . ' ("identificator_offline")'
        );
    }
}
