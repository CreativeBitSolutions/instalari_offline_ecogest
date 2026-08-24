<?php
ini_set('display_errors', 0); // Nu afișează erorile utilizatorului
ini_set('log_errors', 1); // Activează logarea erorilor
ini_set('error_log', 'error_log.log'); // Specifică calea către fișierul de log
error_reporting(E_ALL); // Raportează toate tipurile de erori
session_start();
include('database_connection.php');
require('../fpdf186/fpdf.php');

// Obține datele administratorului
$adm_id = $_SESSION['admin_id'];
$dsql = "SELECT * FROM $tabel_final_admins WHERE admin_id = :adm_id";
$dstmt = $pdo->prepare($dsql);
$dstmt->execute([':adm_id' => $adm_id]);
if ($row = $dstmt->fetch(PDO::FETCH_ASSOC)) {
    $admin_firstname = $row['admin_firstname'];
    $admin_lastname  = $row['admin_lastname'];
}

// Obține datele firmei
$dsql = "SELECT * FROM $tabel_final_date_firma";
$dstmt = $pdo->prepare($dsql);
$dstmt->execute();
if ($row = $dstmt->fetch(PDO::FETCH_ASSOC)) {
    $den_ent         = $row['den_ent'];
    $sediu           = $row['sediu'];
    $cod_fiscal_ent  = $row['cod_fiscal'];
}

// Obține primul și ultimul timp al bonurilor
$min_datetime_sql = "
    SELECT 
        MIN(datetime($tabel_final_note.data_bon || ' ' || $tabel_final_note.ora_bon)) AS min_dt,
        MAX(datetime($tabel_final_note.data_bon || ' ' || $tabel_final_note.ora_bon)) AS max_dt
    FROM $tabel_final_note 
    WHERE $tabel_final_note.cod_inchidere = 0 
      AND $tabel_final_note.operator = :adm_id 
      AND $tabel_final_note.status = 'F' 
      AND $tabel_final_note.locatie = 1";
$min_datetime_stmt = $pdo->prepare($min_datetime_sql);
$min_datetime_stmt->execute([':adm_id' => $adm_id]);
if ($row = $min_datetime_stmt->fetch(PDO::FETCH_ASSOC)) {
    $time_primul_bon  = $row['min_dt'];
    $time_ultimul_bon = $row['max_dt'];
}

// Obține lista produselor vândute astăzi
$f_sql = "
    SELECT 
        $tabel_final_nomenclator.um,
        $tabel_final_nomenclator.nume AS produs,
        SUM($tabel_final_det_note.cantitate) AS cantitate_vanduta,
        SUM($tabel_final_det_note.valoare_vanzare_cu_tva) - SUM($tabel_final_det_note.discount) AS valoare_vanduta
    FROM $tabel_final_det_note
    INNER JOIN $tabel_final_nomenclator 
        ON $tabel_final_det_note.cod_p = $tabel_final_nomenclator.cod_produs
    INNER JOIN $tabel_final_note 
        ON $tabel_final_det_note.nr_bon = $tabel_final_note.nrbon
    WHERE $tabel_final_note.cod_inchidere = 0 
      AND $tabel_final_note.operator = :adm_id 
      AND $tabel_final_note.status = 'F' 
      AND $tabel_final_note.locatie = 1
    GROUP BY $tabel_final_nomenclator.nume";
$f_stmt = $pdo->prepare($f_sql);
$f_stmt->execute([':adm_id' => $adm_id]);
$count = $f_stmt->rowCount();

// Stabilim dimensiunea paginii în funcție de numărul de produse
$lungime = 80 + $count * 20;
$latime  = 80;

// Inițializare PDF
$pdf = new FPDF('P', 'mm', array($latime, $lungime));
$pdf->SetLeftMargin(1);
$pdf->SetRightMargin(1);
$pdf->SetTopMargin(1);
$pdf->SetAutoPageBreak(false);
$pdf->AddPage();

// Setăm fontul
$pdf->SetFont('Arial', '', 6);

// Afișăm datele firmei și ale operatorului
$pdf->MultiCell($latime * 0.95, 5, $den_ent, 0, 'C');
$pdf->MultiCell($latime * 0.95, 5, $sediu, 0, 'C');
$pdf->MultiCell($latime * 0.95, 5, 'C.I.F.: ' . $cod_fiscal_ent, 0, 'C');
$pdf->MultiCell($latime * 0.95, 5, 'OPERATOR: ' . $admin_firstname . ' ' . $admin_lastname, 0, 'C');

// Titlu pentru lista de produse
$pdf->MultiCell($latime * 0.95, 5, 'PRODUSE VANDUTE ASTAZI', 'T', 'C');
$pdf->MultiCell($latime * 0.95, 5, 'De la: ' . $time_primul_bon, 0, 'C');
$pdf->MultiCell($latime * 0.95, 5, 'Pana la: ' . $time_ultimul_bon, 'B', 'C');

// Header pentru lista de produse
$pdf->MultiCell($latime * 0.95, 5, 'Produs X Cantitate | Valoare', 'B', 'L');

// Parcurgem lista de produse și le afișăm
while ($row = $f_stmt->fetch(PDO::FETCH_ASSOC)) {
    $produs          = $row['produs'];
    $cantitate       = $row['cantitate_vanduta'];
    $valoare_vanduta = $row['valoare_vanduta'];
    $um              = $row['um'];

    $txt = ' ' . $produs . "\n" . $cantitate . ' ' . $um . ' | ' . $valoare_vanduta . ' LEI';
    $pdf->MultiCell($latime * 0.95, 8, $txt, 1, 'L');
}

// Obține totalurile din bonuri
$f_total_sql = "
    SELECT 
        SUM($tabel_final_note.numerar) AS total_numerar,
        SUM($tabel_final_note.tichete) AS total_tichete,
        SUM($tabel_final_note.rest) AS total_rest,
        SUM($tabel_final_note.protocol) AS total_protocol,
        SUM($tabel_final_note.card) AS total_card,
        SUM($tabel_final_note.discount) AS total_discount,
        SUM(valoare_vanzare_cu_tva) AS total_valoare
    FROM $tabel_final_note
    WHERE cod_inchidere = 0 
      AND operator = :adm_id 
      AND $tabel_final_note.status = 'F' 
      AND $tabel_final_note.locatie = 1";
$f_total_stmt = $pdo->prepare($f_total_sql);
$f_total_stmt->execute([':adm_id' => $adm_id]);
if ($row = $f_total_stmt->fetch(PDO::FETCH_ASSOC)) {
    $total_numerar  = $row['total_numerar'];
    $total_tichete  = $row['total_tichete'];
    $total_rest     = $row['total_rest'];
    $total_protocol = $row['total_protocol'];
    $total_card     = $row['total_card'];
    $total_discount = $row['total_discount'];
    $total_valoare  = $row['total_valoare'];
}

// Afișăm totalurile
$pdf->Cell($latime * 0.325, 5, 'TOTAL VALOARE', 'T', 0);
$pdf->Cell($latime * 0.575, 5, $total_valoare, 'T', 0, 'R');
$pdf->Cell($latime * 0.375, 5, '', 0, 1, 'R');

$pdf->Cell($latime * 0.325, 5, 'TOTAL NUMERAR', 'T', 0);
$pdf->Cell($latime * 0.575, 5, $total_numerar, 'T', 0, 'R');
$pdf->Cell($latime * 0.375, 5, '', 0, 1, 'R');

$pdf->Cell($latime * 0.325, 5, 'TOTAL REST', 'T', 0);
$pdf->Cell($latime * 0.575, 5, $total_rest, 'T', 0, 'R');
$pdf->Cell($latime * 0.375, 5, '', 0, 1, 'R');

$pdf->Cell($latime * 0.325, 5, 'TOTAL CARD', 'T', 0);
$pdf->Cell($latime * 0.575, 5, $total_card, 'T', 0, 'R');
$pdf->Cell($latime * 0.375, 5, '', 0, 1, 'R');

$pdf->Cell($latime * 0.325, 5, 'TOTAL TICHETE', 'T', 0);
$pdf->Cell($latime * 0.575, 5, $total_tichete, 'T', 0, 'R');
$pdf->Cell($latime * 0.375, 5, '', 0, 1, 'R');

$pdf->Cell($latime * 0.325, 5, 'TOTAL PROTOCOL', 'T', 0);
$pdf->Cell($latime * 0.575, 5, $total_protocol, 'T', 0, 'R');
$pdf->Cell($latime * 0.375, 5, '', 0, 1, 'R');

$pdf->Cell($latime * 0.325, 5, 'TOTAL DISCOUNT', 'T', 0);
$pdf->Cell($latime * 0.575, 5, $total_discount, 'T', 0, 'R');
$pdf->Cell($latime * 0.375, 5, '', 0, 1, 'R');

// Generăm PDF-ul
$pdf->Output();
?>
