<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Acest script ruleaza doar din CLI.\n");
    exit(1);
}

foreach (['pdo_mysql', 'pdo_sqlite'] as $extension) {
    if (!extension_loaded($extension)) {
        fwrite(STDERR, "Lipseste extensia PHP: {$extension}\n");
        exit(1);
    }
}

require_once __DIR__ . '/sqlite_schema.php';

$options = getopt('', [
    'mysql-dsn:',
    'mysql-user:',
    'mysql-pass:',
    'client-id:',
    'locatie:',
    'out:',
]);

foreach (['mysql-dsn', 'mysql-user', 'client-id', 'locatie', 'out'] as $required) {
    if (!array_key_exists($required, $options)) {
        fwrite(STDERR, "Lipseste argumentul --{$required}.\n");
        exit(1);
    }
}

$mysqlDsn = trim((string)$options['mysql-dsn']);
$mysqlUser = (string)$options['mysql-user'];
$mysqlPass = (string)($options['mysql-pass'] ?? '');
$clientId = (int)$options['client-id'];
$locatie = (int)$options['locatie'];
$out = trim((string)$options['out']);

if (!preg_match('/^[1-9][0-9]*$/', (string)$clientId) || !preg_match('/^[1-9][0-9]*$/', (string)$locatie)) {
    fwrite(STDERR, "--client-id si --locatie trebuie sa fie numere pozitive.\n");
    exit(1);
}

if ($mysqlDsn === '' || $mysqlUser === '' || $out === '') {
    fwrite(STDERR, "--mysql-dsn, --mysql-user si --out nu pot fi goale.\n");
    exit(1);
}

if (stripos($mysqlDsn, 'mysql:') === 0 && stripos($mysqlDsn, 'charset=') === false) {
    $mysqlDsn .= ';charset=utf8mb4';
}

$outPath = $out;
if (!preg_match('/^[A-Za-z]:[\\\\\/]/', $outPath) && !preg_match('/^[\\\\\/]/', $outPath)) {
    $outPath = getcwd() . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $outPath);
}
$outPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $outPath);
$outDir = dirname($outPath);

if (!is_dir($outDir) && !mkdir($outDir, 0777, true) && !is_dir($outDir)) {
    fwrite(STDERR, "Nu pot crea folderul SQLite: {$outDir}\n");
    exit(1);
}

if (is_file($outPath)) {
    $backupBase = preg_replace('/\.(sqlite|sqlite3|db)$/i', '', $outPath) ?: $outPath;
    $backup = $backupBase . '_' . date('Ymd_His') . '.sqlite.bak';

    if (!copy($outPath, $backup)) {
        fwrite(STDERR, "Nu pot crea backup pentru SQLite existent.\n");
        exit(1);
    }

    if (!unlink($outPath)) {
        fwrite(STDERR, "Nu pot sterge SQLite existent dupa backup: {$outPath}\n");
        exit(1);
    }

    foreach ([$outPath . '-wal', $outPath . '-shm'] as $sidecarFile) {
        if (is_file($sidecarFile) && !unlink($sidecarFile)) {
            fwrite(STDERR, "Nu pot sterge fisierul SQLite auxiliar: {$sidecarFile}\n");
            exit(1);
        }
    }
}

$mysql = new PDO($mysqlDsn, $mysqlUser, $mysqlPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$sqlite = new PDO('sqlite:' . $outPath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$sqlite->exec('PRAGMA journal_mode = WAL');
$sqlite->exec('PRAGMA foreign_keys = ON');
$sqlite->exec('PRAGMA busy_timeout = 5000');
restaurant_sqlite_apply_schema($sqlite);
restaurant_sqlite_set_cod_locatie_context($sqlite, $locatie);

$locationColumns = ['cod_locatie', 'locatie'];
$clientColumns = ['client_id', 'id_client', 'cod_client', 'client'];

$tables = [
    'admins_12' => [
        'location_filter' => ['columns' => ['locatie'], 'allow_global' => true],
    ],
    'setari_platforma' => [],
    'loc_mese_12' => [
        'optional' => true,
        'location_filter' => ['columns' => ['cod_locatie', 'locatie']],
    ],
    'mese' => [
        'location_filter' => ['columns' => ['cod_locatie', 'locatie']],
    ],
    'categorii' => [],
    'categorii_locatii' => [
        'location_filter' => ['columns' => ['cod_locatie', 'locatie']],
    ],
    'produse_servicii' => [],
    'gestiuni' => [],
    'cote_tva' => [],
    'date_firma' => [],
    'note' => ['truncate_only' => true],
    'det_note' => ['truncate_only' => true],
    'com_tableta' => ['truncate_only' => true],
    'det_com_tableta' => ['truncate_only' => true],
    'retete' => [],
    'miscari' => ['truncate_only' => true],
    'bonuri_casa_marcat' => ['truncate_only' => true],
    'discounturi_acordate' => ['truncate_only' => true],
    'eliberari_mese' => ['truncate_only' => true],
    'incasari_bratari' => ['truncate_only' => true],
    'rapoarte_z' => [
        'optional' => true,
        'location_filter' => ['columns' => ['cod_locatie', 'locatie']],
    ],
    'utilizatori' => ['optional' => true],
    'conectari_operatori' => ['truncate_only' => true],
    'ultima_conexiune' => ['truncate_only' => true],
    'ultim_bon_conectat' => ['truncate_only' => true],
];

function quote_mysql_identifier(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function quote_sqlite_identifier(string $identifier): string
{
    return '"' . str_replace('"', '""', $identifier) . '"';
}

function mysql_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = :table_name
    ");
    $stmt->execute([':table_name' => $table]);
    return ((int)$stmt->fetchColumn()) > 0;
}

function table_columns(PDO $pdo, string $driver, string $table): array
{
    if ($driver === 'sqlite') {
        $stmt = $pdo->query('PRAGMA table_info(' . quote_sqlite_identifier($table) . ')');
        return array_map(static fn(array $row): string => (string)$row['name'], $stmt->fetchAll());
    }

    $stmt = $pdo->query('SHOW COLUMNS FROM ' . quote_mysql_identifier($table));
    return array_map(static fn(array $row): string => (string)$row['Field'], $stmt->fetchAll());
}

function first_existing_column(array $columns, array $candidates): ?string
{
    $index = [];
    foreach ($columns as $column) {
        $index[strtolower((string)$column)] = (string)$column;
    }

    foreach ($candidates as $candidate) {
        $key = strtolower((string)$candidate);
        if (isset($index[$key])) {
            return $index[$key];
        }
    }

    return null;
}

function build_filters(array $rule, array $mysqlColumns, int $clientId, int $locatie, array $clientColumns): array
{
    $whereParts = [];
    $params = [];
    $applied = [];

    if (!empty($rule['where'])) {
        $whereParts[] = '(' . $rule['where'] . ')';
        $params = array_merge($params, $rule['params'] ?? []);
        $applied[] = 'custom';
    }

    if (!empty($rule['location_filter'])) {
        $locationRule = $rule['location_filter'];
        $locationColumn = first_existing_column($mysqlColumns, $locationRule['columns'] ?? ['cod_locatie', 'locatie']);

        if ($locationColumn !== null) {
            if (!empty($locationRule['allow_global'])) {
                $whereParts[] = '(' . quote_mysql_identifier($locationColumn) . ' = :locatie OR ' . quote_mysql_identifier($locationColumn) . ' IS NULL OR ' . quote_mysql_identifier($locationColumn) . ' = 0)';
            } else {
                $whereParts[] = quote_mysql_identifier($locationColumn) . ' = :locatie';
            }
            $params[':locatie'] = $locatie;
            $applied[] = 'locatie:' . $locationColumn;
        }
    }

    if (empty($rule['disable_client_filter'])) {
        $clientColumn = first_existing_column($mysqlColumns, $rule['client_columns'] ?? $clientColumns);
        if ($clientColumn !== null) {
            $whereParts[] = quote_mysql_identifier($clientColumn) . ' = :client_id';
            $params[':client_id'] = $clientId;
            $applied[] = 'client:' . $clientColumn;
        }
    }

    return [
        'where' => $whereParts ? ' WHERE ' . implode(' AND ', $whereParts) : '',
        'params' => $params,
        'applied' => $applied,
    ];
}

function copy_table(PDO $mysql, PDO $sqlite, string $table, array $rule, int $clientId, int $locatie, array $clientColumns): array
{
    $sqlite->exec('DELETE FROM ' . quote_sqlite_identifier($table));

    if (!empty($rule['truncate_only'])) {
        return ['count' => 0, 'filters' => ['truncate_only']];
    }

    if (!mysql_table_exists($mysql, $table)) {
        if (!empty($rule['optional'])) {
            return ['count' => 0, 'filters' => ['optional_missing']];
        }
        throw new RuntimeException("Tabela MySQL lipseste: {$table}");
    }

    $mysqlColumns = table_columns($mysql, 'mysql', $table);
    $sqliteColumns = table_columns($sqlite, 'sqlite', $table);
    $columns = array_values(array_intersect($sqliteColumns, $mysqlColumns));

    if (!$columns) {
        return ['count' => 0, 'filters' => ['no_common_columns']];
    }

    $insertColumns = $columns;
    $injectCodLocatie = false;
    if (in_array('cod_locatie', $sqliteColumns, true) && !in_array('cod_locatie', $insertColumns, true)) {
        $insertColumns[] = 'cod_locatie';
        $injectCodLocatie = true;
    }

    $filters = build_filters($rule, $mysqlColumns, $clientId, $locatie, $clientColumns);

    $quotedMysqlCols = implode(', ', array_map('quote_mysql_identifier', $columns));
    $sql = 'SELECT ' . $quotedMysqlCols . ' FROM ' . quote_mysql_identifier($table) . $filters['where'];

    $select = $mysql->prepare($sql);
    $select->execute($filters['params']);

    $quotedSqliteCols = implode(', ', array_map('quote_sqlite_identifier', $insertColumns));
    $placeholders = implode(', ', array_map(static fn(string $column): string => ':' . $column, $insertColumns));
    $insert = $sqlite->prepare('INSERT INTO ' . quote_sqlite_identifier($table) . " ({$quotedSqliteCols}) VALUES ({$placeholders})");

    $count = 0;
    while ($row = $select->fetch(PDO::FETCH_ASSOC)) {
        $params = [];
        foreach ($insertColumns as $column) {
            if ($column === 'cod_locatie' && $injectCodLocatie) {
                $params[':' . $column] = array_key_exists('locatie', $row) && $row['locatie'] !== null && $row['locatie'] !== ''
                    ? (int)$row['locatie']
                    : $locatie;
                continue;
            }
            $params[':' . $column] = $row[$column] ?? null;
        }
        $insert->execute($params);
        $count++;
    }

    return ['count' => $count, 'filters' => $filters['applied'] ?: ['none']];
}

function validate_sqlite_database(PDO $sqlite): void
{
    $integrity = $sqlite->query('PRAGMA integrity_check')->fetchColumn();
    if ($integrity !== 'ok') {
        throw new RuntimeException('SQLite integrity_check esuat: ' . (string)$integrity);
    }

    $fkErrors = $sqlite->query('PRAGMA foreign_key_check')->fetchAll(PDO::FETCH_ASSOC);
    if ($fkErrors) {
        throw new RuntimeException('SQLite foreign_key_check a gasit erori.');
    }
}

$sqlite->beginTransaction();
try {
    $counts = [];
    $appliedFilters = [];

    foreach ($tables as $table => $rule) {
        $result = copy_table($mysql, $sqlite, $table, $rule, $clientId, $locatie, $clientColumns);
        $counts[$table] = (int)$result['count'];
        $appliedFilters[$table] = $result['filters'];
    }

    $sqlite->commit();
} catch (Throwable $e) {
    if ($sqlite->inTransaction()) {
        $sqlite->rollBack();
    }
    fwrite(STDERR, "Export esuat: " . $e->getMessage() . "\n");
    exit(1);
}

try {
    restaurant_sqlite_apply_schema($sqlite);
    restaurant_sqlite_set_cod_locatie_context($sqlite, $locatie);
    validate_sqlite_database($sqlite);
} catch (Throwable $e) {
    fwrite(STDERR, "Validare SQLite esuata: " . $e->getMessage() . "\n");
    exit(1);
}

echo "SQLite generat: {$outPath}\n";
echo "client_id={$clientId}, locatie={$locatie}\n";
echo "Nota: filtrul client-id se aplica automat doar tabelelor care contin una dintre coloanele: " . implode(', ', $clientColumns) . "\n";

foreach ($counts as $table => $count) {
    $filtersText = implode(', ', $appliedFilters[$table] ?? ['none']);
    echo str_pad($table, 28) . str_pad((string)$count, 10, ' ', STR_PAD_LEFT) . " filtre: {$filtersText}\n";
}
