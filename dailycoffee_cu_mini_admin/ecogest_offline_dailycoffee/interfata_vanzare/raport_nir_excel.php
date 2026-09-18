<?php
// raport_nir_excel.php

ini_set('display_errors', 0);
ini_set('log_errors', 1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['start_date'])) {
    die("Acces invalid sau lipsesc datele necesare.");
}

require_once __DIR__ . '/phpspreadsheet_bootstrap.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

set_time_limit(300);

function remove_diacritics($text) {
    $diacritics = ['ă', 'Ă', 'â', 'Â', 'î', 'Î', 'ș', 'Ș', 'ț', 'Ț'];
    $replacements = ['a', 'A', 'a', 'A', 'i', 'I', 's', 'S', 't', 'T'];
    return str_replace($diacritics, $replacements, $text);
}

// Preluăm datele din formular
$start_date = $_POST['start_date'];
$end_date   = !empty($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d');
$cota_tva   = null;

// MODIFICAT: Verificare corectă pentru a include cota TVA 0
if (isset($_POST['cota_tva']) && $_POST['cota_tva'] !== '') {
    $cota_tva = (int)$_POST['cota_tva'];
}


include 'db.php';
if (!isset($pdo)) {
    die("<h1>Eroare: Conexiunea la baza de date nu a fost stabilită. Reconectați-vă.</h1>");
}

$sql_where_tva = "";
$params = [
    ':start_date' => $start_date,
    ':end_date'   => $end_date,
];
if ($cota_tva !== null) {
    $sql_where_tva = " AND a.cota_tva = :cota_tva";
    $params[':cota_tva'] = $cota_tva;
}

$sql = "
    SELECT 
        f.nume AS furnizor_nume, f.cod_fiscal AS furnizor_cif, f.adresa AS furnizor_adresa,
        n.serie_doc_int, n.nr_doc_int, n.data_doc_int, n.data_nir, a.cota_tva,
        SUM(a.valoare_achizitie) AS total_valoare_fara_tva, SUM(a.valoare_tva_achizitie) AS total_tva,
        SUM(a.valoare_achizitie_cu_tva) AS total_valoare_factura, SUM(a.valoare_pret_amanunt) AS total_valoare_cu_adaos
    FROM nir n
    JOIN furnizori f ON n.cod_tert = f.id_furnizor
    LEFT JOIN achizitii a ON n.nr_nir = a.nr_nir
    WHERE n.data_nir BETWEEN :start_date AND :end_date AND a.cota_tva IS NOT NULL
    {$sql_where_tva}
    GROUP BY n.id_nir, a.cota_tva
    ORDER BY n.data_nir ASC, n.id_nir ASC, a.cota_tva ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$raport = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- LOGICA DE GENERARE EXCEL ---
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Raport NIR');

$row_index = 1;

$headers = [
    'Furnizor', 'CIF', 'Adresa', 'Factura', 'Data emiterii', 'Data NIR', 
    'Cota TVA', 'Valoare fara TVA', 'TVA', 'Valoare totala factura', 'Valoare cu adaos'
];
$sheet->fromArray($headers, NULL, 'A' . $row_index);
$row_index++;

foreach ($raport as $row) {
    $sheet->setCellValue('A' . $row_index, remove_diacritics($row['furnizor_nume']));
    $sheet->setCellValueExplicit('B' . $row_index, $row['furnizor_cif'], DataType::TYPE_STRING);
    $sheet->setCellValue('C' . $row_index, remove_diacritics($row['furnizor_adresa']));
    $sheet->setCellValue('D' . $row_index, $row['serie_doc_int'] . ' ' . $row['nr_doc_int']);
    $sheet->setCellValue('E' . $row_index, \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($row['data_doc_int']));
    $sheet->setCellValue('F' . $row_index, \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($row['data_nir']));
    $sheet->setCellValue('G' . $row_index, $row['cota_tva']);
    $sheet->setCellValue('H' . $row_index, $row['total_valoare_fara_tva']);
    $sheet->setCellValue('I' . $row_index, $row['total_tva']);
    $sheet->setCellValue('J' . $row_index, $row['total_valoare_factura']);
    $sheet->setCellValue('K' . $row_index, $row['total_valoare_cu_adaos']);
    $row_index++;
}

$sheet->getStyle('E2:F' . $row_index)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_DATE_DDMMYYYY);
$sheet->getStyle('H2:K' . $row_index)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

foreach (range('A', 'K') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// NOU: Construim numele dinamic al fișierului
$filename_parts = ['Raport_NIR', 'de_la_' . $start_date, 'pana_la_' . $end_date];
if ($cota_tva !== null) {
    $filename_parts[] = 'TVA_' . $cota_tva;
}
$filename = implode('_', $filename_parts) . '.xlsx';

// MODIFICAT: Folosim numele dinamic
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . urlencode($filename) . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>