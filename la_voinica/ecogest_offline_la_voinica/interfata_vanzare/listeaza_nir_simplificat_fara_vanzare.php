<?php
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'db.php';
require_once __DIR__ . '/tcpdf_bootstrap.php';

$nr_nir      = isset($_GET['nr_nir']) ? $_GET['nr_nir'] : '';
$id_gestiune = isset($_GET['id_gestiune']) ? $_GET['id_gestiune'] : '';
$cota_tva    = isset($_GET['cota_tva']) ? $_GET['cota_tva'] : '';

if (empty($nr_nir)) {
    die("Numărul NIR nu a fost specificat.");
}

// Query doar cu câmpurile necesare pentru coloanele 1-8
$sqlAchiz = "
    SELECT 
        a.id_achiz,
        a.nr_nir,
        a.cod_p,
        a.cota_tva,
        a.denumire_produs,
        a.unitate_masura,
        a.cantitate,
        a.pret_unitar_achizitie,
        a.valoare_achizitie,
        a.tva_unitar_achizitie,
        a.valoare_tva_achizitie,
        a.valoare_achizitie_cu_tva
    FROM achizitii a
    JOIN produse_servicii ps ON a.cod_p = ps.cod_produs
    WHERE a.nr_nir = :nr_nir
";

$params = ['nr_nir' => $nr_nir];

if (!empty($id_gestiune)) {
    $sqlAchiz .= " AND ps.id_gestiune = :id_gestiune";
    $params['id_gestiune'] = $id_gestiune;
}

if ($cota_tva !== '') {
    $sqlAchiz .= " AND a.cota_tva = :cota_tva";
    $params['cota_tva'] = $cota_tva;
}

$stmtAchiz = $pdo->prepare($sqlAchiz);
$stmtAchiz->execute($params);
$rowsAchiz = $stmtAchiz->fetchAll(PDO::FETCH_ASSOC);

// Date NIR
$sqlNir = "SELECT * FROM nir WHERE nr_nir = :nr_nir";
$stmtNir = $pdo->prepare($sqlNir);
$stmtNir->execute(['nr_nir' => $nr_nir]);
$nirData = $stmtNir->fetch(PDO::FETCH_ASSOC);

if (!$nirData) {
    die("Nu există NIR cu acest număr: $nr_nir");
}

$data_nir      = date('d-m-Y', strtotime($nirData['data_nir']));
$id_furnizor   = $nirData['cod_tert'];
$serie_doc_int = $nirData['serie_doc_int'];
$nr_doc_int    = $nirData['nr_doc_int'];
$data_doc_int  = date('d-m-Y', strtotime($nirData['data_doc_int']));
$observatii    = $nirData['observatii'];

// Furnizor
$sqlFurnizor = "SELECT nume FROM furnizori WHERE id_furnizor = :id_furnizor";
$stmtfurnizor = $pdo->prepare($sqlFurnizor);
$stmtfurnizor->execute(['id_furnizor' => $id_furnizor]);
$furnizorData = $stmtfurnizor->fetch(PDO::FETCH_ASSOC);
$denumire_furnizor = $furnizorData ? $furnizorData['nume'] : "Numele terțului necunoscut";

// PDF
$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Autorul tau');
$pdf->SetTitle('Nota de receptie si constatare de diferente');
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 20);
$pdf->AddPage();

// Titlu
$pdf->SetFont('dejavusans', 'B', 14);
$pdf->Cell(0, 10, 'NOTA DE RECEPTIE SI CONSTATARE DE DIFERENTE', 0, 1, 'C');
$pdf->Ln(2);

$pdf->SetDrawColor(0, 0, 0);
$pdf->Line(10, $pdf->GetY(), 287, $pdf->GetY());
$pdf->Ln(2);

// Date firmă
$pdf->SetFont('dejavusans', '', 10);

$df_sql = "SELECT * FROM date_firma LIMIT 1";
$df_stmt = $pdo->prepare($df_sql);
$df_stmt->execute();
$date_firma = $df_stmt->fetch(PDO::FETCH_ASSOC);

if (!$date_firma) {
    die("Eroare: Datele firmei nu au fost găsite.");
}

$pdf->Cell(0, 6, 'Unitatea: ' . $date_firma['den_ent'], 0, 1);
$pdf->Cell(0, 6, 'Furnizor: ' . $denumire_furnizor, 0, 1);
$pdf->Cell(0, 6, "Factura/Aviz: $serie_doc_int $nr_doc_int din $data_doc_int", 0, 1);
$pdf->Cell(0, 6, "NIR: $nr_nir din $data_nir", 0, 1);
$pdf->Ln(3);

// Doar coloanele 1-8
$cols = [
    'denumire_produs'          => 70,
    'unitate_masura'           => 18,
    'cantitate'                => 22,
    'pret_unitar_achizitie'    => 35,
    'valoare_achizitie'        => 28,
    'tva_unitar_achizitie'     => 28,
    'valoare_tva_achizitie'    => 28,
    'valoare_achizitie_cu_tva' => 38,
];

$total_width = array_sum($cols);
if ($total_width > 277) {
    die("Tabelul depășește lățimea disponibilă.");
}

$html = '<table border="1" cellpadding="3" cellspacing="0" style="font-size:8px;">';
$html .= '<thead>';

// Antet 1
$html .= '<tr style="background-color:#CCCCCC; font-weight:bold;">';
$html .= '<th width="'.$cols['denumire_produs'].'mm" align="center">1: Denumire produs</th>';
$html .= '<th width="'.$cols['unitate_masura'].'mm" align="center">2: UM</th>';
$html .= '<th width="'.$cols['cantitate'].'mm" align="center">3: Cantitate</th>';
$html .= '<th width="'.$cols['pret_unitar_achizitie'].'mm" align="center">4: Preț unitar (cota TVA %)</th>';
$html .= '<th width="'.$cols['valoare_achizitie'].'mm" align="center">5: Valoare</th>';
$html .= '<th width="'.$cols['tva_unitar_achizitie'].'mm" align="center">6: TVA unitar ach.</th>';
$html .= '<th width="'.$cols['valoare_tva_achizitie'].'mm" align="center">7: TVA total</th>';
$html .= '<th width="'.$cols['valoare_achizitie_cu_tva'].'mm" align="center">8: Valoare totală cu TVA<br>(cont 401)</th>';
$html .= '</tr>';

// Antet 2
$html .= '<tr style="background-color:#EEEEEE; font-size:7px;">';
$html .= '<td align="center">1</td>';
$html .= '<td align="center">2</td>';
$html .= '<td align="center">3</td>';
$html .= '<td align="center">4</td>';
$html .= '<td align="center">5 = 3 × 4</td>';
$html .= '<td align="center">6 = TVA / UM</td>';
$html .= '<td align="center">7 = 3 × 6</td>';
$html .= '<td align="center">8 = 5 + 7</td>';
$html .= '</tr>';

$html .= '</thead>';
$html .= '<tbody>';

// Totaluri generale
$total_valoare = 0;
$total_tva = 0;
$total_cu_tva = 0;

foreach ($rowsAchiz as $row) {
    $total_valoare += (float)$row['valoare_achizitie'];
    $total_tva += (float)$row['valoare_tva_achizitie'];
    $total_cu_tva += (float)$row['valoare_achizitie_cu_tva'];

    $html .= '<tr style="font-size:7px;">';
    $html .= '<td width="'.$cols['denumire_produs'].'mm" align="left">'.htmlspecialchars($row['denumire_produs']).'</td>';
    $html .= '<td width="'.$cols['unitate_masura'].'mm" align="center">'.htmlspecialchars($row['unitate_masura']).'</td>';
    $html .= '<td width="'.$cols['cantitate'].'mm" align="right">'.number_format($row['cantitate'], 2, ',', '.').'</td>';
    $html .= '<td width="'.$cols['pret_unitar_achizitie'].'mm" align="right">'.number_format($row['pret_unitar_achizitie'], 3, ',', '.').' ('.htmlspecialchars($row['cota_tva']).'%)</td>';
    $html .= '<td width="'.$cols['valoare_achizitie'].'mm" align="right">'.number_format($row['valoare_achizitie'], 2, ',', '.').'</td>';
    $html .= '<td width="'.$cols['tva_unitar_achizitie'].'mm" align="right">'.number_format($row['tva_unitar_achizitie'], 2, ',', '.').'</td>';
    $html .= '<td width="'.$cols['valoare_tva_achizitie'].'mm" align="right">'.number_format($row['valoare_tva_achizitie'], 2, ',', '.').'</td>';
    $html .= '<td width="'.$cols['valoare_achizitie_cu_tva'].'mm" align="right">'.number_format($row['valoare_achizitie_cu_tva'], 2, ',', '.').'</td>';
    $html .= '</tr>';
}

// Rând total general
$html .= '<tr style="font-size:7px; font-weight:bold; background-color:#F2F2F2;">';
$html .= '<td align="right" colspan="4">TOTAL GENERAL</td>';
$html .= '<td align="right">'.number_format($total_valoare, 2, ',', '.').'</td>';
$html .= '<td align="right">-</td>';
$html .= '<td align="right">'.number_format($total_tva, 2, ',', '.').'</td>';
$html .= '<td align="right">'.number_format($total_cu_tva, 2, ',', '.').'</td>';
$html .= '</tr>';

$html .= '</tbody></table>';

$pdf->writeHTML($html, true, false, true, false, '');

// Observații
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$client_agecs = isset($_SESSION['client_id']) ? $_SESSION['client_id'] : null;

if ($client_agecs != 6 && $client_agecs != 2) {
    $pdf->Ln(5);
    $pdf->SetFont('dejavusans', '', 9);
    $obs = $observatii ? $observatii : ' - ';
    $pdf->MultiCell(0, 0, 'Observații: ' . htmlspecialchars($obs), 0, 'L');
}

// Verificare spațiu
$required_space = 20;
$current_y = $pdf->GetY();
$page_height = $pdf->getPageHeight();
$bottom_margin = $pdf->getBreakMargin();
$available_space = $page_height - $bottom_margin - $current_y;

if ($available_space < $required_space) {
    $pdf->AddPage();
}

// Semnături
$pdf->SetFont('dejavusans', '', 9);

if ($client_agecs == 6 || $client_agecs == 2) {
    $semnHTML = '
    <table cellpadding="4" cellspacing="0">
        <tr>
            <td align="center">CONTABIL</td>
            <td align="center">MANAGER</td>
            <td align="center">GESTIONAR</td>
        </tr>
    </table>
    ';
} else {
    $semn1_width = 100;
    $semn2_width = 40;
    $semn3_width = 100;
    $semn4_width = 40;

    $semnHTML = '
    <table border="1" cellpadding="4" cellspacing="0">
        <tr>
            <td width="' . $semn1_width . 'mm" align="C">
                Numele și prenumele membrilor comisiei de recepție<br><br><br><br>
            </td>
            <td width="' . $semn2_width . 'mm" align="C">
                Semnătura<br><br><br><br>
            </td>
            <td width="' . $semn3_width . 'mm" align="C">
                Numele și prenumele gestionarului<br><br><br><br>
            </td>
            <td width="' . $semn4_width . 'mm" align="C">
                Semnătura<br><br><br><br>
            </td>
        </tr>
    </table>
    ';
}

$pdf->writeHTML($semnHTML, true, false, true, false, '');

$pdf->Output('nir_'.$nr_nir.'.pdf', 'I');
ob_end_flush();
?>