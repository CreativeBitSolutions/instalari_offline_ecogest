<?php

function offline_import_schema_ensure(PDO $pdo): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }
    $initialized = true;

    require_once __DIR__ . '/tools/sqlite_schema.php';
    lorand_sqlite_apply_schema_if_needed($pdo);
}
