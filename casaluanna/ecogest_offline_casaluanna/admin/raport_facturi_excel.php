<?php
// raport_facturi_excel.php (versiune simplificată)

ini_set('display_errors', 0);
ini_set('log_errors', 1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['start_date'])) {
    die("Acces invalid sau lipsesc datele necesare.");
}

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

// --- LOGICA DE EXTRAGERE A DATELOR ---
set_time_limit(300);

function remove_diacritics($text) {
    $diacritics = ['ă', 'Ă', 'â', 'Â', 'î', 'Î', 'ș', 'Ș', 'ț', 'Ț'];
    $replacements = ['a', 'A', 'a', 'A', 'i', 'I', 's', 'S', 't', 'T'];
    return str_replace($diacritics, $replacements, $text);
}

$start_date = $_POST['start_date'];
$end_date   = !empty($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d');

require_once __DIR__.'/database_connection.php';
if (!isset($pdo)) {
    die("<h1>Eroare: Conexiunea la baza de date nu a fost stabilită. Intrați din nou în aplicație Reconectați-vă.</h1>");
}

$sql = "
    SELECT 
        f.nume,
        f.cod_fiscal,
        f.adresa,
        f.serie_factura,
        f.nr_factura,
        f.data_factura,
        f.data_scadenta,
        SUM(v.valoare_vanzare) AS total_fara_tva,
        SUM(v.tva_col) AS total_tva,
        SUM(v.valoare_vanzare_cu_tva) AS total_cu_tva
    FROM facturi f
    LEFT JOIN vanzari v ON f.id_factura = v.id_factura
    WHERE f.data_factura BETWEEN :start_date AND :end_date AND f.nr_factura != 0
    GROUP BY f.id_factura
    ORDER BY f.data_factura ASC, f.nr_factura ASC
";

$stmt = $pdo->prepare($sql);
$stmt->bindParam(':start_date', $start_date, PDO::PARAM_STR);
$stmt->bindParam(':end_date', $end_date, PDO::PARAM_STR);
$stmt->execute();
$raport = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- LOGICA DE GENERARE A FIȘIERULUI EXCEL ---
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Raport Facturi');

$row_index = 1;

// Antetul tabelului (începe direct pe primul rând)
$headers = ['Client', 'CIF', 'Adresa', 'Factura', 'Data emiterii', 'Data scadenta', 'Valoare fara TVA', 'TVA', 'Valoare totala'];
$sheet->fromArray($headers, NULL, 'A'.$row_index);
$row_index++;

// Popularea tabelului cu date
if (!empty($raport)) {
    foreach ($raport as $factura) {
        $sheet->setCellValue('A'.$row_index, remove_diacritics($factura['nume']));
        $sheet->setCellValueExplicit('B'.$row_index, $factura['cod_fiscal'], DataType::TYPE_STRING);
        $sheet->setCellValue('C'.$row_index, remove_diacritics($factura['adresa']));
        $sheet->setCellValue('D'.$row_index, $factura['serie_factura'] . ' ' . $factura['nr_factura']);
        $sheet->setCellValue('E'.$row_index, \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($factura['data_factura']));
        $sheet->setCellValue('F'.$row_index, \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($factura['data_scadenta']));
        $sheet->setCellValue('G'.$row_index, $factura['total_fara_tva'] ?? 0);
        $sheet->setCellValue('H'.$row_index, $factura['total_tva'] ?? 0);
        $sheet->setCellValue('I'.$row_index, $factura['total_cu_tva'] ?? 0);
        $row_index++;
    }
}

// --- FORMATĂRI MINIME ---
// Se formatează coloanele de la rândul 2 (primul rând de date) până la final
$sheet->getStyle('E2:F'.$row_index)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_DATE_DDMMYYYY);
$sheet->getStyle('G2:I'.$row_index)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

// Redimensionare automată coloane
foreach (range('A', 'I') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// --- TRIMITERE FIȘIER CĂTRE BROWSER ---
$filename = "Raport_Facturi_" . date('Y-m-d') . ".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . urlencode($filename) . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>