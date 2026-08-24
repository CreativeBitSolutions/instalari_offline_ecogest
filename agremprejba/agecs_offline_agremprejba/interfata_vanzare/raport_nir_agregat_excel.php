<?php
ini_set('display_errors', 0); // Recomandat pentru producție
ini_set('log_errors', 1);
error_reporting(E_ALL);

// 1. Validare request
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['start_date'])) {
    die("Acces invalid sau lipsesc datele necesare.");
}

require_once __DIR__ . '/phpspreadsheet_bootstrap.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

set_time_limit(300);

// Funcție utilitară pentru diacritice
function remove_diacritics($text) {
    $diacritics = ['ă', 'Ă', 'â', 'Â', 'î', 'Î', 'ș', 'Ș', 'ț', 'Ț'];
    $replacements = ['a', 'A', 'a', 'A', 'i', 'I', 's', 'S', 't', 'T'];
    return str_replace($diacritics, $replacements, $text);
}

// 2. Preluare date din formular
$start_date = $_POST['start_date'];
$end_date   = !empty($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d');
// Cota TVA este eliminată intenționat

include 'db.php';
if (!isset($pdo)) {
    die("<h1>Eroare: Conexiunea la baza de date nu a fost stabilită. Reconectați-vă.</h1>");
}

// 3. Interogare SQL Agregată (identică cu cea din PDF)
$params = [
    ':start_date' => $start_date,
    ':end_date'   => $end_date,
];

$sql = "
    SELECT 
        f.nume AS furnizor_nume, f.cod_fiscal AS furnizor_cif, f.adresa AS furnizor_adresa,
        n.serie_doc_int, n.nr_doc_int, n.data_doc_int, n.data_nir,
        SUM(COALESCE(a.valoare_achizitie, 0)) AS total_valoare_fara_tva, 
        SUM(COALESCE(a.valoare_tva_achizitie, 0)) AS total_tva,
        SUM(COALESCE(a.valoare_achizitie_cu_tva, 0)) AS total_valoare_factura, 
        SUM(COALESCE(a.valoare_pret_amanunt, 0)) AS total_valoare_cu_adaos
    FROM nir n
    JOIN furnizori f ON n.cod_tert = f.id_furnizor
    LEFT JOIN achizitii a ON n.nr_nir = a.nr_nir
    WHERE n.data_nir BETWEEN :start_date AND :end_date
    GROUP BY n.id_nir, f.nume, f.cod_fiscal, f.adresa, n.serie_doc_int, n.nr_doc_int, n.data_doc_int, n.data_nir
    ORDER BY n.data_nir ASC, n.id_nir ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$raport = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Logică generare Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Raport NIR Agregat');

$row_index = 1;

// Header modificat (10 coloane)
$headers = [
    'Furnizor', 'CIF', 'Adresa', 'Factura', 'Data emiterii', 'Data NIR', 
    'Valoare fara TVA', 'TVA', 'Valoare totala factura', 'Valoare cu adaos'
];
$sheet->fromArray($headers, NULL, 'A' . $row_index);
// Aplicare stil Bold la header
$sheet->getStyle('A1:J1')->getFont()->setBold(true);

$row_index++;

// 5. Populare date
foreach ($raport as $row) {
    $sheet->setCellValue('A' . $row_index, remove_diacritics($row['furnizor_nume']));
    $sheet->setCellValueExplicit('B' . $row_index, $row['furnizor_cif'], DataType::TYPE_STRING);
    $sheet->setCellValue('C' . $row_index, remove_diacritics($row['furnizor_adresa']));
    $sheet->setCellValue('D' . $row_index, $row['serie_doc_int'] . ' ' . $row['nr_doc_int']);
    
    // Formatare date
    $sheet->setCellValue('E' . $row_index, \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($row['data_doc_int']));
    $sheet->setCellValue('F' . $row_index, \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($row['data_nir']));
    
    // Coloana G a fost eliminată (Cota TVA)
    
    // Populare valori numerice
    $sheet->setCellValue('G' . $row_index, $row['total_valoare_fara_tva']);
    $sheet->setCellValue('H' . $row_index, $row['total_tva']);
    $sheet->setCellValue('I' . $row_index, $row['total_valoare_factura']);
    $sheet->setCellValue('J' . $row_index, $row['total_valoare_cu_adaos']);
    
    $row_index++;
}

// Adăugare rând total
$last_row = $row_index - 1;
if ($last_row >= 2) {
    $sheet->setCellValue('F' . $row_index, 'TOTAL');
    $sheet->getStyle('F' . $row_index)->getFont()->setBold(true);
    
    $sheet->setCellValue('G' . $row_index, "=SUM(G2:G{$last_row})");
    $sheet->setCellValue('H' . $row_index, "=SUM(H2:H{$last_row})");
    $sheet->setCellValue('I' . $row_index, "=SUM(I2:I{$last_row})");
    $sheet->setCellValue('J' . $row_index, "=SUM(J2:J{$last_row})");
    
    // Aplicare stil Bold și formatare la totaluri
    $sheet->getStyle("G{$row_index}:J{$row_index}")->getFont()->setBold(true);
    $sheet->getStyle("G{$row_index}:J{$row_index}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
}


// 6. Stiluri și formatare
// Formatare coloane dată (E și F)
$sheet->getStyle('E2:F' . $last_row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_DATE_DDMMYYYY);
// Formatare coloane numerice (G, H, I, J)
$sheet->getStyle('G2:J' . $last_row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

// Auto-size coloane (de la A la J)
foreach (range('A', 'J') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// 7. Output Excel
// Nume de fișier modificat
$filename_parts = ['Raport_NIR_Agregat', 'de_la_' . $start_date, 'pana_la_' . $end_date];
$filename = implode('_', $filename_parts) . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . urlencode($filename) . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>