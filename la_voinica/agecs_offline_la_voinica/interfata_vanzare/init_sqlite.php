<?php
// init_sqlite.php
//
// Rulezi din browser: http://localhost/.../init_sqlite.php
// Dump structura MySQL: data/u806910449_elgringov2.sql
// Dump date JSON:       data/u806910449_elgringov2.json
// Baza SQLite:          C:\xampp\htdocs\instalari_offline\api_offline_agecs_la_voinica\db_local\pos.db

require __DIR__ . '/db.php';

header('Content-Type: text/html; charset=utf-8');
echo "<pre>\n";

$sqlDumpPath  = __DIR__ . '/data/u806910449_elgringov2.sql';
$jsonDumpPath = __DIR__ . '/data/u806910449_elgringov2.json';

if (!is_file($sqlDumpPath)) {
    echo "Eroare: nu găsesc dump-ul SQL: {$sqlDumpPath}\n";
    exit;
}

if (!is_file($jsonDumpPath)) {
    echo "Eroare: nu găsesc dump-ul JSON: {$jsonDumpPath}\n";
    exit;
}

echo "Pornesc inițializarea bazei SQLite...\n\n";

// Pentru import dezactivăm cheile străine
$pdo->exec('PRAGMA foreign_keys = OFF;');

/**
 * Adaugă DEFAULT la toate coloanele NOT NULL fără DEFAULT.
 * Reguli:
 *   - tipuri numerice (int, tinyint, bigint, decimal, double, float): DEFAULT 0
 *   - datetime / timestamp: DEFAULT '0000-00-00 00:00:00'
 *   - date: DEFAULT '0000-00-00'
 *   - time: DEFAULT '00:00:00'
 *   - char / varchar / text / blob: DEFAULT ''
 */
function addDefaultOnNotNull(string $sql): string
{
    return preg_replace_callback(
        '~^\s*`[^`]+`[^\n]*NOT NULL[^\n]*,?~mi',
        function (array $m): string {
            $line = $m[0];

            // Dacă există deja DEFAULT, nu umblăm la linia asta
            if (stripos($line, 'DEFAULT') !== false) {
                return $line;
            }

            if (!preg_match('~`[^`]+`\s+([^\s,]+)~', $line, $mt)) {
                return $line;
            }

            $type = strtolower($mt[1]);
            if (strpos($type, 'datetime') !== false || strpos($type, 'timestamp') !== false) {
                $default = "'0000-00-00 00:00:00'";
            } elseif (preg_match('~\bdate\b~', $type)) {
                $default = "'0000-00-00'";
            } elseif (preg_match('~\btime\b~', $type)) {
                $default = "'00:00:00'";
            } elseif (preg_match('~char|text|blob~', $type)) {
                $default = "''";
            } else {
                $default = "0";
            }

            return preg_replace('~NOT NULL~i', 'NOT NULL DEFAULT ' . $default, $line, 1);
        },
        $sql
    );
}

/**
 * Transformă dump-ul MySQL în SQL acceptat de SQLite.
 *
 * Important: înainte să eliminăm ALTER TABLE, extragem din ele
 * PRIMARY KEY-urile și coloanele cu AUTO_INCREMENT și le băgăm
 * direct în CREATE TABLE:
 *   - dacă o coloană este și PRIMARY KEY și AUTO_INCREMENT,
 *     devine `INTEGER PRIMARY KEY AUTOINCREMENT`
 *   - dacă este doar PRIMARY KEY (fără AUTO_INCREMENT),
 *     adăugăm `PRIMARY KEY (coloana)` la finalul definiției
 */
function transformMysqlToSqlite(string $sql): string
{
    // 1. PRIMARY KEY-urile definite prin ALTER TABLE ... ADD PRIMARY KEY(...)
    $pkMap = [];
    if (preg_match_all('~ALTER TABLE\s+`([^`]+)`\s+ADD PRIMARY KEY\s*\(([^)]+)\)~i', $sql, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $table = $m[1];
            $cols  = [];
            if (preg_match_all('~`([^`]+)`~', $m[2], $cm)) {
                $cols = $cm[1];
            }
            if (!empty($cols)) {
                $pkMap[$table] = $cols;
            }
        }
    }

    // 2. Coloanele cu AUTO_INCREMENT definite prin ALTER TABLE ... MODIFY ... AUTO_INCREMENT
    $aiMap = [];
    if (preg_match_all('~ALTER TABLE\s+`([^`]+)`\s+MODIFY\s+`([^`]+)`\s+[^\n]*AUTO_INCREMENT~i', $sql, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $table = $m[1];
            $col   = $m[2];
            $aiMap[$table] = $col;
        }
    }

    // 3. Curățăm SQL-ul de linii MySQL care nu ne interesează
    $lines = preg_split('~\R~', $sql);
    $clean = [];

    foreach ($lines as $line) {
        $trim  = trim($line);
        $upper = strtoupper($trim);

        if ($trim === '') {
            continue;
        }
        if (strpos($trim, '-- ') === 0) {
            continue;
        }
        if (strpos($trim, '/*') === 0 || strpos($trim, '*/') === 0) {
            continue;
        }
        if (strpos($upper, 'SET ') === 0) {
            continue;
        }
        if (strpos($upper, 'LOCK TABLES') === 0 || strpos($upper, 'UNLOCK TABLES') === 0) {
            continue;
        }
        if (strpos($upper, 'DELIMITER ') === 0) {
            continue;
        }
        if (strpos($upper, 'START TRANSACTION') === 0) {
            continue;
        }
        if (strpos($upper, 'COMMIT') === 0) {
            continue;
        }

        $clean[] = $line;
    }

    $sql = implode("\n", $clean);

    // Comentarii condiționale de tip /*!40101 ... */ ;
    $sql = preg_replace('~/\*![0-9]+\s.*?\*/;~s', '', $sql);

    // Scoatem toate ALTER TABLE (indexuri / PK / AUTO_INCREMENT în stil MySQL).
    // Informația de PK/AUTO_INCREMENT am pus-o deja în $pkMap și $aiMap.
    $sql = preg_replace('~ALTER TABLE[^;]+;~i', '', $sql);

    // UNSIGNED nu există în SQLite
    $sql = preg_replace('~\bUNSIGNED\b~i', '', $sql);

    // CHARACTER SET ... nu există în SQLite
    $sql = preg_replace('~\bCHARACTER SET\s+\w+~i', '', $sql);

    // COLLATE ... MySQL, nu collation-urile din SQLite
    $sql = preg_replace('~\bCOLLATE\s+\w+~i', '', $sql);

    // COMMENT '...' pe coloană
    $sql = preg_replace("~\\bCOMMENT\\s+'[^']*'~i", '', $sql);

    // Fix special pentru coloana `metoda_plata_implicita` din `date_firma`:
    // aruncăm tot ENUM-ul uriaș și îl înlocuim cu TEXT NOT NULL DEFAULT 'Card bancar'
    $sql = preg_replace(
        "~`metoda_plata_implicita`.*?DEFAULT 'Card bancar',~su",
        "`metoda_plata_implicita` TEXT NOT NULL DEFAULT 'Card bancar',",
        $sql
    );

    // ENUM(...) -> TEXT simplu
    $sql = preg_replace('~\benum\s*\([^)]*\)~i', 'TEXT', $sql);

    // DEFAULT current_timestamp() -> DEFAULT CURRENT_TIMESTAMP (sintaxa SQLite)
    $sql = preg_replace('~DEFAULT\s+current_timestamp\s*\(\s*\)~i', 'DEFAULT CURRENT_TIMESTAMP', $sql);

    // ON UPDATE current_timestamp() nu există în SQLite
    $sql = preg_replace('~ON UPDATE\s+current_timestamp\s*\(\s*\)~i', '', $sql);

    // Adăugăm DEFAULT-uri pentru toate NOT NULL fără DEFAULT
    $sql = addDefaultOnNotNull($sql);

    // ) ENGINE=... DEFAULT CHARSET=... COLLATE=...; -> );
    $sql = preg_replace('~\)\s*ENGINE=.*?;~i', ');', $sql);

    // 4. Integram PRIMARY KEY + AUTOINCREMENT în CREATE TABLE
    $sql = preg_replace_callback(
        '~CREATE TABLE\s+`([^`]+)`\s*\((.*?)\);\s*~is',
        function (array $m) use ($pkMap, $aiMap): string {
            $table = $m[1];
            $body  = $m[2];

            // Dacă nu avem PK definit prin ALTER TABLE, lăsăm tabela cum e
            if (!isset($pkMap[$table])) {
                return $m[0];
            }

            $pkCols = $pkMap[$table];
            $aiCol  = isset($aiMap[$table]) ? $aiMap[$table] : null;

            $hasPrimaryInBody = stripos($body, 'PRIMARY KEY') !== false;

            $singlePk       = count($pkCols) === 1;
            $pkColName      = $singlePk ? $pkCols[0] : null;
            $convertToRowId = $singlePk && $aiCol !== null && $aiCol === $pkColName;

            $lines    = preg_split('~\R~', $body);
            $newLines = [];

            // Dacă avem o singură coloană PK care este și AUTO_INCREMENT,
            // o transformăm în INTEGER PRIMARY KEY AUTOINCREMENT
            foreach ($lines as $line) {
                $lineMod = $line;

                if ($convertToRowId && $pkColName !== null) {
                    if (preg_match('~^\s*`' . preg_quote($pkColName, '~') . '`~', $line)) {
                        $trimLine = rtrim($line);
                        $comma    = substr($trimLine, -1) === ',' ? ',' : '';
                        $lineMod  = '  `' . $pkColName . '` INTEGER PRIMARY KEY AUTOINCREMENT' . $comma;
                    }
                }

                $newLines[] = $lineMod;
            }

            // Dacă PK nu este AUTO_INCREMENT sau este compus,
            // adăugăm PRIMARY KEY(...) la finalul definiției
            $needsSeparatePk = !$convertToRowId && !$hasPrimaryInBody;

            if ($needsSeparatePk && !empty($pkCols)) {
                $i = count($newLines) - 1;
                while ($i >= 0 && trim($newLines[$i]) === '') {
                    $i--;
                }
                if ($i >= 0) {
                    $trimLast = rtrim($newLines[$i]);
                    if (substr($trimLast, -1) !== ',') {
                        $newLines[$i] = $trimLast . ',';
                    }
                }

                $pkParts = [];
                foreach ($pkCols as $col) {
                    $pkParts[] = '`' . $col . '`';
                }

                $newLines[] = '  PRIMARY KEY (' . implode(',', $pkParts) . ')';
            }

            $newBody = implode("\n", $newLines);

            return "CREATE TABLE `{$table}` (\n{$newBody}\n);";
        },
        $sql
    );

    return $sql;
}

// ========================================
// 1. Import structură din u806910449_elgringov2.sql
// ========================================

$sqlDump = file_get_contents($sqlDumpPath);
if ($sqlDump === false) {
    echo "Eroare: nu pot citi fișierul SQL.\n";
    exit;
}

$sql = transformMysqlToSqlite($sqlDump);

// Ștergem toate tabelele existente (în afară de sqlite_sequence)
echo "Șterg tabelele existente...\n";
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
if ($tables) {
    foreach ($tables as $t) {
        if ($t === 'sqlite_sequence') {
            continue;
        }
        $pdo->exec('DROP TABLE IF EXISTS "' . str_replace('"', '""', $t) . '"');
    }
}

echo "Import structură din SQL...\n\n";

$executate_sql = 0;
$erori_sql     = 0;

// Împărțim după ; urmat de newline
$statements = preg_split('~;~', $sql);
foreach ($statements as $stmt) {
    $stmt = trim($stmt);
    if ($stmt === '') {
        continue;
    }

    try {
        $pdo->exec($stmt);
        $executate_sql++;
    } catch (PDOException $e) {
        $erori_sql++;
        echo "Eroare la execuția SQL:\n";
        echo $stmt . "\n";
        echo "Mesaj: " . $e->getMessage() . "\n\n";
    }
}

echo "Structură terminată.\n";
echo "Statement-uri structură reușite: {$executate_sql}\n";
echo "Statement-uri structură cu erori: {$erori_sql}\n\n";

// ========================================
// 2. Import date din u806910449_elgringov2.json
// ========================================

$executate_json = 0;
$erori_json     = 0;

echo "Import date din JSON...\n\n";

$json = file_get_contents($jsonDumpPath);
if ($json === false) {
    echo "Eroare: nu pot citi fișierul JSON.\n";
} else {
    $dump = json_decode($json, true);
    if (!is_array($dump)) {
        echo "Eroare: JSON invalid.\n";
    } else {
        foreach ($dump as $block) {
            if (!is_array($block)) {
                continue;
            }
            if (($block['type'] ?? '') !== 'table') {
                continue;
            }

            $table = $block['name'] ?? null;
            $rows  = $block['data'] ?? null;

            if (!$table || !is_array($rows) || empty($rows)) {
                continue;
            }

            echo "Tabel {$table} (" . count($rows) . " rânduri)...\n";

            foreach ($rows as $row) {
                if (!is_array($row) || empty($row)) {
                    continue;
                }

                $cols = array_keys($row);

                $placeholders = [];
                $quotedCols   = [];
                foreach ($cols as $c) {
                    $placeholders[] = '?';
                    $quotedCols[]   = '"' . str_replace('"', '""', $c) . '"';
                }

                $sqlInsert = 'INSERT INTO "' . str_replace('"', '""', $table) . '" (' .
                             implode(',', $quotedCols) .
                             ') VALUES (' . implode(',', $placeholders) . ')';

                try {
                    $stmt = $pdo->prepare($sqlInsert);
                    $stmt->execute(array_values($row));
                    $executate_json++;
                } catch (PDOException $e) {
                    $erori_json++;
                    echo "Eroare INSERT în {$table}: " . $e->getMessage() . "\n";
                }
            }

            echo "Gata tabel {$table}.\n\n";
        }
    }
}

// La final putem reactiva cheile străine pe conexiune
$pdo->exec('PRAGMA foreign_keys = ON;');

echo "----------------------------------------\n";
echo "Import structură SQL terminat.\n";
echo "Statement-uri structură reușite: {$executate_sql}\n";
echo "Statement-uri structură cu erori: {$erori_sql}\n\n";

echo "Import date JSON terminat.\n";
echo "INSERT-uri reușite: {$executate_json}\n";
echo "INSERT-uri cu erori: {$erori_json}\n\n";

echo "Baza SQLite este în: {$DB_PATH}\n";
echo "Poți închide această pagină și folosi aplicația.\n";
echo "</pre>\n";
