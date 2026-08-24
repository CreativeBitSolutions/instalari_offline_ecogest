<?php
// export_vanzari_offline.php

session_start();
include('db.php');
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.log');
error_reporting(E_ALL);

// Prevenire cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
header("Pragma: no-cache");

date_default_timezone_set("Europe/Bucharest");

if (!isset($_SESSION['adminloggedin'])) {
    printf("<script>location.href='agecs_login.php'</script>");
    exit();
}

function escape_sql_value($value)
{
    if ($value === null) {
        return 'NULL';
    }

    if (is_numeric($value)) {
        return $value;
    }

    $value = str_replace("'", "''", $value);
    $value = str_replace(["\r", "\n"], ['\r', '\n'], $value);

    return "'" . $value . "'";
}

function escape_xml_value($value)
{
    if ($value === null) {
        return '';
    }
    return htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function get_table_columns(PDO $pdo, $table)
{
    $stmt = $pdo->query("PRAGMA table_info($table)");
    $cols = [];
    foreach ($stmt as $row) {
        $cols[] = $row['name'];
    }
    return $cols;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $azi = date('Y-m-d');
    $luna_start = date('Y-m-01');
    echo '<!DOCTYPE html><html lang="ro"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Export Offline</title>
<link href="vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="css/sb-admin.css" rel="stylesheet">
</head><body class="bg-dark">
<div class="container mt-5">
  <div class="card card-login mx-auto" style="max-width:420px">
    <div class="card-header"><b>Export Offline</b></div>
    <div class="card-body">
      <form method="POST" action="export_vanzari_offline.php">
        <div class="form-group mb-3">
          <label><b>Data inceput</b></label>
          <input type="date" name="data_start" class="form-control" value="' . $luna_start . '" required>
        </div>
        <div class="form-group mb-3">
          <label><b>Data sfarsit</b></label>
          <input type="date" name="data_end" class="form-control" value="' . $azi . '" required>
        </div>
        <div class="form-group mb-4">
          <label><b>Format</b></label>
          <select name="format" class="form-control">
            <option value="xml">XML</option>
            <option value="sql">SQL</option>
          </select>
        </div>
        <div class="d-flex flex-column" style="gap:10px">
          <button type="submit" class="btn btn-primary btn-block w-100" style="cursor:pointer">Descarca Export</button>
          <button type="submit" formaction="trimite_export_xml_online.php" formmethod="post" class="btn btn-success btn-block w-100" style="cursor:pointer">Trimite XML catre online</button>
        </div>
      </form>
      <div class="mt-3 text-center">
        <a href="agecs_login.php" style="color:#aaa">&#8592; Inapoi</a>
      </div>
    </div>
  </div>
</div>
</body></html>';
    exit();
}

$data_start_raw = $_POST['data_start'] ?? '';
$data_end_raw   = $_POST['data_end'] ?? '';
$format_raw     = $_POST['format'] ?? 'sql';

if (!$data_start_raw || !$data_end_raw) {
    echo "Trebuie selectate ambele date pentru interval.";
    exit();
}

// normalizare date
$data_start = date('Y-m-d', strtotime($data_start_raw));
$data_end   = date('Y-m-d', strtotime($data_end_raw));

if ($data_start > $data_end) {
    $tmp = $data_start;
    $data_start = $data_end;
    $data_end = $tmp;
}

$format = strtolower($format_raw);
if ($format !== 'xml' && $format !== 'sql') {
    $format = 'sql';
}

// definim tabelele și câmpurile lor de dată pentru filtrare
$tables = [
    'note' => [
        'where' => 'date(data_bon) BETWEEN :data_start AND :data_end',
        'order' => 'date(data_bon), nrbon'
    ],
    'det_note' => [
        'where' => 'date(data) BETWEEN :data_start AND :data_end',
        'order' => 'date(data), nr_bon, id_vanz'
    ],
    'discounturi_acordate' => [
        'where' => 'date(data_acordare) BETWEEN :data_start AND :data_end',
        'order' => 'date(data_acordare)'
    ],
    'bonuri_casa_marcat' => [
        'where' => 'date(data) BETWEEN :data_start AND :data_end',
        'order' => 'date(data), nrbon, id'
    ],
    'inchideri_r_12' => [
        'where' => 'date(data_inchiderii) BETWEEN :data_start AND :data_end',
        'order' => 'date(data_inchiderii), cod_inchidere, id_inch'
    ],
    'rapoarte_z' => [
        'where' => '(
            date(data_ora_raport_z) BETWEEN :data_start AND :data_end
            OR nr_raport_z IN (
                SELECT nr_raport_z
                FROM inchideri_r_12
                WHERE date(data_inchiderii) BETWEEN :data_start AND :data_end
                  AND nr_raport_z <> 0
            )
        )',
        'order' => 'date(data_ora_raport_z), nr_raport_z, id'
    ],
    'nir' => [
        'where' => 'date(data_nir) BETWEEN :data_start AND :data_end',
        'order' => 'date(data_nir), nr_nir'
    ],
    'achizitii' => [
        'where' => 'nr_nir IN (
            SELECT nr_nir
            FROM nir
            WHERE date(data_nir) BETWEEN :data_start AND :data_end
        )',
        'order' => 'nr_nir, id_achiz'
    ],
    'miscari' => [
        'where' => 'date(data) BETWEEN :data_start AND :data_end',
        'order' => 'date(data), id'
    ],
    'log_reglari_casa_marcat' => [
        'where' => 'date(data_operatiune) BETWEEN :data_start AND :data_end',
        'order' => 'date(data_operatiune), id'
    ],
];

$params = [
    ':data_start' => $data_start,
    ':data_end'   => $data_end,
];

$now = date('Y-m-d H:i:s');
$output = '';

if ($format === 'sql') {
    $output .= "-- Export offline vanzari, miscari, inchideri, rapoarte Z si NIR\n";
    $output .= "-- Perioada: {$data_start} - {$data_end}\n";
    $output .= "-- Generat la: {$now}\n\n";
    $output .= "BEGIN TRANSACTION;\n\n";
} else {
    $output .= "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    $output .= "<export perioada_start=\"" . escape_xml_value($data_start) . "\" perioada_end=\"" . escape_xml_value($data_end) . "\" generated_at=\"" . escape_xml_value($now) . "\">\n";
}

foreach ($tables as $table => $cfg) {
    $where = $cfg['where'];

    $columns = get_table_columns($pdo, $table);
    if (empty($columns)) {
        if ($format === 'sql') {
            $output .= "-- ATENTIE: Tabelul $table nu exista in baza de date curenta.\n\n";
        } else {
            $output .= "  <table name=\"" . escape_xml_value($table) . "\" missing=\"1\" />\n";
        }
        continue;
    }

    $order = $cfg['order'] ?? '';
    $sql = "SELECT * FROM $table WHERE $where";
    if ($order !== '') {
        $sql .= " ORDER BY $order";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($format === 'sql') {
        $output .= "-- Tabel: $table\n";
        if (!$rows) {
            $output .= "-- (Nicio inregistrare pentru perioada selectata)\n\n";
            continue;
        }

        $cols_list = implode(', ', $columns);

        foreach ($rows as $row) {
            $values = [];
            foreach ($columns as $col) {
                $values[] = escape_sql_value($row[$col] ?? null);
            }
            $values_list = implode(', ', $values);
            $output .= "INSERT INTO $table ($cols_list) VALUES ($values_list);\n";
        }

        $output .= "\n";
    } else {
        $output .= "  <table name=\"" . escape_xml_value($table) . "\">\n";

        if ($rows) {
            foreach ($rows as $row) {
                $output .= "    <row>\n";
                foreach ($columns as $col) {
                    $val = escape_xml_value($row[$col] ?? null);
                    $output .= "      <{$col}>{$val}</{$col}>\n";
                }
                $output .= "    </row>\n";
            }
        }

        $output .= "  </table>\n";
    }
}

if ($format === 'sql') {
    $output .= "COMMIT;\n";
} else {
    $output .= "</export>\n";
}

$ext = $format;
$filename = "export_offline_{$data_start}_{$data_end}." . $ext;

if ($format === 'sql') {
    $contentType = 'application/sql';
} else {
    $contentType = 'application/xml';
}

header('Content-Type: ' . $contentType . '; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
// header('Content-Length: ' . strlen($output)); // opțional, îl poți lăsa comentat

echo $output;
exit;
