<?php
// Start output buffering to prevent the "already output" error
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'db.php';
require_once __DIR__ . '/tcpdf_bootstrap.php';

/** PDF cu footer pentru totaluri pe pagină */
class NIRPDF extends TCPDF {
    /** datele de footer per pagină (pageNo => ['gestiuni_title','gestiuni_lines'=>[], 'coloane_title','cols6'=>[]]) */
    public $pageFooter = [];
    /** înălțimea totală rezervată footerului (mm) */
    public $footerHeight = 20;
public $cellRatio    = 0.90; // pentru spațiere mai mică între rânduri

    public function Footer() {
    $p = $this->getPage();
    if (empty($this->pageFooter[$p])) { return; }

    $data = $this->pageFooter[$p];

    $m = $this->getMargins();
    $x = $m['left'];
    $w = $this->getPageWidth() - $m['left'] - $m['right'];

    // poziționare la footer
    $this->SetY(-$this->footerHeight);
    $y = $this->GetY();

    // linie separatoare
    $this->SetDrawColor(0,0,0);
    $this->Line($x, $y, $x+$w, $y);

    // spațiere de rând mai mică
    $this->setCellHeightRatio($this->cellRatio);

    // ===== Totaluri pe gestiuni – o singură frază cu bullets =====
    $gestStr = '-';
    if (!empty($data['gestiuni_lines'])) {
        $gestStr = htmlspecialchars(implode(' • ', $data['gestiuni_lines']));
    }
    $htmlG = '<div style="font-size:10px;line-height:1;">'
           . '<span style="font-weight:bold;">' . htmlspecialchars($data['gestiuni_title']) . '</span> '
           . $gestStr
           . '</div>';

    // scriem fraza
    $this->writeHTMLCell($w, 0, $x, $y+1.2, $htmlG, 0, 1, 0, true, 'L', true);

    // ===== Totaluri pe coloane – un singur rând cu 6 celule =====
    $cells = '';
    foreach ($data['cols6'] as $txt) {
        $cells .= '<td style="padding:0 2px;white-space:nowrap;">' . htmlspecialchars($txt) . '</td>';
    }
    $htmlC = '<div style="font-size:10px;line-height:1;"><span style="font-weight:bold;">'
           . htmlspecialchars($data['coloane_title'])
           . '</span></div>'
           . '<table width="100%" border="0" cellpadding="0" cellspacing="0" '
           . 'style="font-size:10px;line-height:1;"><tr>' . $cells . '</tr></table>';

    $this->writeHTMLCell($w, 0, $x, '', $htmlC, 0, 1, 0, true, 'L', true);
}

}

// (3) Preluăm nr_nir, id_gestiune și cota_tva din GET:
$nr_nir = isset($_GET['nr_nir']) ? $_GET['nr_nir'] : '';
$id_gestiune = isset($_GET['id_gestiune']) ? $_GET['id_gestiune'] : '';
$cota_tva = isset($_GET['cota_tva']) ? $_GET['cota_tva'] : ''; // MODIFICARE: Adăugat parametrul pentru cota TVA

if (empty($nr_nir)) {
    die("Numărul NIR nu a fost specificat.");
}

// Construim interogarea de bază:
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
        a.valoare_achizitie_cu_tva,
        a.procent_adaos,
        a.adaos_unitar,
        a.valoare_adaos,
        a.pret_cu_adaos_unitar_fara_tva,
        a.tva_adaos_comercial,
        a.pret_unitar_cu_amanuntul_cu_tva,
        a.valoare_pret_amanunt,
        a.tva_total_unitar,
        ps.pret_cu_tva AS pret_cu_tva_ps
    FROM achizitii a
    JOIN produse_servicii ps ON a.cod_p = ps.cod_produs
    WHERE a.nr_nir = :nr_nir
";

// Inițializăm array-ul de parametri pentru prepared statement:
$params = ['nr_nir' => $nr_nir];

// Dacă s-a selectat și o gestiune, adăugăm condiția de filtrare:
if (!empty($id_gestiune)) {
    $sqlAchiz .= " AND ps.id_gestiune = :id_gestiune";
    $params['id_gestiune'] = $id_gestiune;
}

// ===================== BLOC NOU ADAUGAT =====================
// Dacă s-a selectat și o cotă TVA, adăugăm condiția de filtrare:
if ($cota_tva !== '') { // Verificăm cu '' pentru a permite filtrarea pe cota 0
    $sqlAchiz .= " AND a.cota_tva = :cota_tva";
    $params['cota_tva'] = $cota_tva;
}
// ===================== SFÂRȘIT BLOC NOU ADAUGAT =====================

$stmtAchiz = $pdo->prepare($sqlAchiz);
$stmtAchiz->execute($params);
$rowsAchiz = $stmtAchiz->fetchAll(PDO::FETCH_ASSOC);

// (4) Facem un query pentru datele generale din nir (tabelul `nir`)
$sqlNir = "SELECT * FROM nir WHERE nr_nir = :nr_nir";
$stmtNir = $pdo->prepare($sqlNir);
$stmtNir->execute(['nr_nir' => $nr_nir]);
$nirData = $stmtNir->fetch(PDO::FETCH_ASSOC);

if (!$nirData) {
    die("Nu există NIR cu acest număr: $nr_nir");
}

// Extragem datele principale 
$data_nir      = date('d-m-Y', strtotime($nirData['data_nir']));
$id_furnizor   = $nirData['cod_tert'];  
$serie_doc_int = $nirData['serie_doc_int'];
$nr_doc_int    = $nirData['nr_doc_int'];
$data_doc_int  = date('d-m-Y', strtotime($nirData['data_doc_int']));
$observatii    = $nirData['observatii'];

// Obținem denumirea terțului din tabelul `furnizori`
$sqlFurnizor= "SELECT nume FROM furnizori WHERE id_furnizor= :id_furnizor";
$stmtfurnizor= $pdo->prepare($sqlFurnizor);
$stmtfurnizor->execute(['id_furnizor' => $id_furnizor]);
$furnizorData = $stmtfurnizor->fetch(PDO::FETCH_ASSOC);
$denumire_furnizor= $furnizorData ? $furnizorData['nume'] : "Numele terțului necunoscut";

// Pornim sesiunea, dacă nu este deja pornită
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$client_agecs = isset($_SESSION['client_id']) ? $_SESSION['client_id'] : null;

// (6) Inițializăm TCPDF
$pdf = new NIRPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Autorul tau');
$pdf->SetTitle('Nota de receptie si constatare de diferente');
$pdf->SetMargins(10, 10, 10);

/* rezervăm spațiu pentru subsolul fix (footer) ca să nu fie acoperit de tabel */
$pdf->SetAutoPageBreak(true, $pdf->footerHeight);


// Pregătim un statement pentru a identifica gestiunea pe baza denumirii produsului
$sqlGetGestiune = "
    SELECT 
        g.denumire_gestiune
    FROM produse_servicii p
    JOIN gestiuni g ON p.id_gestiune = g.id_gestiune
    WHERE p.cod_produs = :codul_produsului
    LIMIT 1
    ";
$stmtGestiune = $pdo->prepare($sqlGetGestiune);

// Inițializăm array pentru grupare pe cote TVA
$groupedRows = [];
$sumsPerCota = [];
$totalsPerCota = [];
$grandTotals = [
    'valoare_achizitie' => 0.0,
    'valoare_tva_achizitie' => 0.0,
    'valoare_achizitie_cu_tva' => 0.0,
    'valoare_adaos' => 0.0,
    'valoare_pret_amanunt' => 0.0,
    'tva_adaos_total' => 0.0,
    'tva_total' => 0.0,
];

// Pregătim variabilele pentru sume (păstrăm logica veche pentru compatibilitate)
$sumMateriiPrime = 0.0;
$sumMaterialeConsumabile = 0.0;
$sumMateriiPrime5 = 0.0;
$sumMateriiPrime9 = 0.0;
$sumMateriiPrime11 = 0.0;
$sumMateriiPrime19 = 0.0;
$sumMateriiPrime21 = 0.0;
$sumMateriiPrime0 = 0.0;
$sumMarfuriAchTot = 0.0;
$sumMarf5Ach = 0.0;
$sumMarf9Ach = 0.0;
$sumMarf11Ach = 0.0;
$sumMarf19Ach = 0.0;
$sumMarf21Ach = 0.0;
$sumMarf0Ach = 0.0;
$sumMarfuriVanzTot = 0.0;
$sumMarf5Vanz = 0.0;
$sumMarf9Vanz = 0.0;
$sumMarf11Vanz = 0.0;
$sumMarf19Vanz = 0.0;
$sumMarf21Vanz = 0.0;
$sumMarf0Vanz = 0.0;
$sumGestiuniAll = [];

foreach ($rowsAchiz as $row) {

    /* ================== NOU: override pentru vânzare când col.4 = 0 ==================
       Dacă prețul unitar de achiziție (col.4) este 0, calculăm coloanele 10..16
       pornind de la produse_servicii.pret_cu_tva (ps.pret_cu_tva_ps pentru cod_p).
       Convenție:
         - col.14 (preț unitar cu amănuntul cu TVA) = ps.pret_cu_tva
         - col.12 (fără TVA) = col.14 / (1 + cota/100)
         - col.16 (TVA pe UM) = col.14 - col.12
         - col.10 (adaos unitar) = col.12 - col.4  (= col.12, căci col.4=0)
         - col.11 = cantitate * col.10
         - col.13 (TVA aferent adaos) = col.16 - col.6 (= col.16, căci col.6=0)
         - col.15 = cantitate * col.14
       Notă: lăsăm col.6/7/8 (achiziție) așa cum vin din DB (vor fi 0 dacă 4=0).
*/
    // Grupăm rows pe cota_tva
    $cota = (float)$row['cota_tva'];
    if ((float)$row['pret_unitar_achizitie'] == 0 && !empty($row['pret_cu_tva_ps']) && (float)$row['pret_cu_tva_ps'] > 0) {
        $cant              = (float)$row['cantitate'];
        $pretVanzCuTVA     = (float)$row['pret_cu_tva_ps'];
        $factorTVA         = 1 + ($cota / 100.0);
        $pretVanzFaraTVA   = $factorTVA > 0 ? ($pretVanzCuTVA / $factorTVA) : $pretVanzCuTVA;

        // TVA extras din prețul de vânzare (pe UM)
        $tvaExtrasDinPret  = $pretVanzCuTVA - $pretVanzFaraTVA; // TVA total UM derivat din prețul cu TVA

        // col.6 din DB (poate fi 0 dacă și 4=0)
        $tvaUnitarAchiz    = (float)$row['tva_unitar_achizitie'];

        // Col.9 (procent adaos) ar fi nedefinit (div/0); îl setăm 0 ca să nu afişeze NaN
        $row['procent_adaos']                 = 0.0;

        // 10..16 recalibrate din prețul de vânzare
        $row['adaos_unitar']                  = $pretVanzFaraTVA - (float)$row['pret_unitar_achizitie']; // col.10
        $row['valoare_adaos']                 = $row['adaos_unitar'] * $cant;                            // col.11
        $row['pret_cu_adaos_unitar_fara_tva'] = $pretVanzFaraTVA;                                       // col.12

        // col.13 = TVA aferent adaos = TVA extras din preț - TVA unitar achiziție (nu negativ)
        $col13 = $tvaExtrasDinPret - $tvaUnitarAchiz;
        if ($col13 < 0) { $col13 = 0.0; }
        $row['tva_adaos_comercial']           = $col13;                                                 // col.13

        $row['pret_unitar_cu_amanuntul_cu_tva']= $pretVanzCuTVA;                                        // col.14
        $row['valoare_pret_amanunt']          = $pretVanzCuTVA * $cant;                                 // col.15

        // col.16 = 6 + 13 (asigurăm identitatea 16=6+13)
        $row['tva_total_unitar']              = $tvaUnitarAchiz + $row['tva_adaos_comercial'];          // col.16
    }

    // Grupăm DUPĂ override, pentru ca HTML-ul și totalurile să folosească valorile noi
    $groupedRows[(int)$cota][] = $row;

    // ---- Determinăm gestiunea efectivă ----
    $stmtGestiune->execute([':codul_produsului' => $row['cod_p']]);
    $rowGes = $stmtGestiune->fetch(PDO::FETCH_ASSOC);
    if (!$rowGes) {
        continue;
    }

    $denumireGestiune = strtoupper($rowGes['denumire_gestiune'] ?? '');
    $cotaTvaProdus = (int)$cota;
    $valAch = (float)$row['valoare_achizitie'];
    $valVanz = (float)$row['valoare_pret_amanunt'];

    // Calcul sume per cota per gestiune (noua structură generală)
    if (!isset($sumsPerCota[$cota])) {
        $sumsPerCota[$cota] = [];
    }
    if (!isset($sumsPerCota[$cota][$denumireGestiune])) {
        $sumsPerCota[$cota][$denumireGestiune] = ['ach' => 0.0, 'vanz' => 0.0];
    }
    $sumsPerCota[$cota][$denumireGestiune]['ach'] += $valAch;
    $sumsPerCota[$cota][$denumireGestiune]['vanz'] += $valVanz;

    // Calcul totaluri coloane per cota
    if (!isset($totalsPerCota[$cota])) {
        $totalsPerCota[$cota] = [
            'valoare_achizitie' => 0.0,
            'valoare_tva_achizitie' => 0.0,
            'valoare_achizitie_cu_tva' => 0.0,
            'valoare_adaos' => 0.0,
            'valoare_pret_amanunt' => 0.0,
            'tva_adaos_total' => 0.0,
            'tva_total' => 0.0,
        ];
    }
    $totalsPerCota[$cota]['valoare_achizitie'] += (float)$row['valoare_achizitie'];
    $totalsPerCota[$cota]['valoare_tva_achizitie'] += (float)$row['valoare_tva_achizitie'];
    $totalsPerCota[$cota]['valoare_achizitie_cu_tva'] += (float)$row['valoare_achizitie_cu_tva'];
    $totalsPerCota[$cota]['valoare_adaos'] += (float)$row['valoare_adaos'];
    $totalsPerCota[$cota]['valoare_pret_amanunt'] += (float)$row['valoare_pret_amanunt'];
    $totalsPerCota[$cota]['tva_adaos_total'] += (float)$row['tva_adaos_comercial'] * (float)$row['cantitate'];
    $totalsPerCota[$cota]['tva_total'] += (float)$row['tva_total_unitar'] * (float)$row['cantitate'];

    // Adăugăm la totaluri generale
    $grandTotals['valoare_achizitie'] += (float)$row['valoare_achizitie'];
    $grandTotals['valoare_tva_achizitie'] += (float)$row['valoare_tva_achizitie'];
    $grandTotals['valoare_achizitie_cu_tva'] += (float)$row['valoare_achizitie_cu_tva'];
    $grandTotals['valoare_adaos'] += (float)$row['valoare_adaos'];
    $grandTotals['valoare_pret_amanunt'] += (float)$row['valoare_pret_amanunt'];
    $grandTotals['tva_adaos_total'] += (float)$row['tva_adaos_comercial'] * (float)$row['cantitate'];
    $grandTotals['tva_total'] += (float)$row['tva_total_unitar'] * (float)$row['cantitate'];

    // Păstrăm logica veche de calcul sume
    if (strpos($denumireGestiune, 'MATERII') !== false) {
        $sumMateriiPrime += $valAch;
        if ($cotaTvaProdus === 5) {
            $sumMateriiPrime5 += $valAch;
        } elseif ($cotaTvaProdus === 9) {
            $sumMateriiPrime9 += $valAch;
        } elseif ($cotaTvaProdus === 11) {
            $sumMateriiPrime11 += $valAch;
        } elseif ($cotaTvaProdus === 19) {
            $sumMateriiPrime19 += $valAch;
        } elseif ($cotaTvaProdus === 21) {
            $sumMateriiPrime21 += $valAch;
        } else {
            $sumMateriiPrime0 += $valAch;
        }
    } elseif (strpos($denumireGestiune, 'CONSUM') !== false || strpos($denumireGestiune, 'CONSUMABILE') !== false) {
        $sumMaterialeConsumabile += $valAch;
    } elseif (strpos($denumireGestiune, 'MARF') !== false) {
        $sumMarfuriAchTot += $valAch;
        $sumMarfuriVanzTot += $valVanz;
        if ($cotaTvaProdus === 5) {
            $sumMarf5Ach += $valAch;
            $sumMarf5Vanz += $valVanz;
        } elseif ($cotaTvaProdus === 9) {
            $sumMarf9Ach += $valAch;
            $sumMarf9Vanz += $valVanz;
        } elseif ($cotaTvaProdus === 11) {
            $sumMarf11Ach += $valAch;
            $sumMarf11Vanz += $valVanz;
        } elseif ($cotaTvaProdus === 19) {
            $sumMarf19Ach += $valAch;
            $sumMarf19Vanz += $valVanz;
        } elseif ($cotaTvaProdus === 21) {
            $sumMarf21Ach += $valAch;
            $sumMarf21Vanz += $valVanz;
        } else {
            $sumMarf0Ach += $valAch;
            $sumMarf0Vanz += $valVanz;
        }
    }

    // Agregare generală pentru toate gestiunile
    if (!isset($sumGestiuniAll[$denumireGestiune])) {
        $sumGestiuniAll[$denumireGestiune] = ['achizitie' => 0.0, 'vanzare' => 0.0];
    }
    $sumGestiuniAll[$denumireGestiune]['achizitie'] += $valAch;
    $sumGestiuniAll[$denumireGestiune]['vanzare'] += $valVanz;
}

// Sortăm cote TVA crescător
ksort($groupedRows);

// (9) Definim lățimile coloanelor (păstrăm același design)
$cols = [
    'denumire_produs'               => 38,
    'unitate_masura'                => 10,
    'cantitate'                     => 12,
    'pret_unitar_achizitie'         => 15,
    'valoare_achizitie'             => 15,
    'tva_unitar_achizitie'          => 15,
    'valoare_tva_achizitie'         => 15,
    'valoare_achizitie_cu_tva'      => 20,
    'procent_adaos'                 => 12,
    'adaos_unitar'                  => 15,
    'valoare_adaos'                 => 15,
    'pret_cu_adaos_unitar_fara_tva' => 20,
    'tva_adaos_comercial'           => 15,
    'pret_unitar_cu_amanuntul_cu_tva'=> 20,
    'valoare_pret_amanunt'          => 20,
    'tva_total_unitar'              => 15,
];

// Verificăm lățimea totală
$total_width = array_sum($cols);
if ($total_width > 277) {
    die("Tabelul depășește lățimea disponibilă (277 mm). Ajustează lățimile coloanelor.");
}

// Obținem datele firmei (păstrăm)
$df_sql = "SELECT * FROM date_firma LIMIT 1";
$df_stmt = $pdo->prepare($df_sql);
$df_stmt->execute();
$date_firma = $df_stmt->fetch(PDO::FETCH_ASSOC);

if (!$date_firma) {
    die("Eroare: Datele firmei nu au fost găsite.");
}

// Handle empty data case to avoid writing without pages
if (empty($groupedRows)) {
    $pdf->AddPage();
    $pdf->SetFont('dejavusans', 'B', 14);
    $pdf->Cell(0, 10, 'NOTA DE RECEPTIE SI CONSTATARE DE DIFERENTE', 0, 1, 'C');
    $pdf->Ln(5);
    $pdf->SetFont('dejavusans', '', 10);
    $pdf->Cell(0, 10, 'Nu există date pentru acest NIR.', 0, 1, 'C');
} else {
    // Pentru fiecare cotă TVA, creăm o secțiune separată (pagină nouă)
    foreach ($groupedRows as $cota => $group) {
        $pdf->AddPage();  // Always add a page for each group/section

        // (7) Titlul principal
        $pdf->SetFont('dejavusans', 'B', 10);
        $pdf->Cell(0, 10, 'NOTA DE RECEPTIE SI CONSTATARE DE DIFERENTE', 0, 1, 'C');

        // Linie orizontală sub titlu
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->Line(10, $pdf->GetY(), 287, $pdf->GetY());
        $pdf->Ln(2);

        // (8) Informații antet - date firmă / furnizor / NIR
        $pdf->SetFont('dejavusans', '', 8);

        $pdf->Cell(0, 6, 'Unitatea: ' . $date_firma['den_ent'], 0, 1);
        $pdf->Cell(0, 6, 'Furnizor: ' . $denumire_furnizor, 0, 1);
        $pdf->Cell(0, 6, "Factura/Aviz: $serie_doc_int $nr_doc_int din $data_doc_int", 0, 1);
        $pdf->Cell(0, 6, "NIR: $nr_nir din $data_nir (Cota TVA: $cota%)", 0, 1);

        // Construim tabelul HTML doar pentru acest grup
        $html = '<table border="1" cellpadding="3" cellspacing="0" style="font-size:6px;">';

        // ---- Rândul 1: Denumiri coloane + indicii (1..16) ----
        $html .= '<thead>';
        $html .= '<tr style="background-color:#CCCCCC; font-weight:bold;">';
        $html .= '<th width="'.$cols['denumire_produs'].'mm"                     align="center">1: Denumire produs</th>';
        $html .= '<th width="'.$cols['unitate_masura'].'mm"                       align="center">2: UM</th>';
        $html .= '<th width="'.$cols['cantitate'].'mm"                             align="center">3: Cantitate</th>';
        $html .= '<th width="'.$cols['pret_unitar_achizitie'].'mm"         align="center">4: Preț unitar (cota tva %)</th>';
        $html .= '<th width="'.$cols['valoare_achizitie'].'mm"             align="center">5: Valoare</th>';
        $html .= '<th width="'.$cols['tva_unitar_achizitie'].'mm"          align="center">6: TVA unitar ach.</th>';
        $html .= '<th width="'.$cols['valoare_tva_achizitie'].'mm"         align="center">7: TVA total</th>';
        $html .= '<th width="'.$cols['valoare_achizitie_cu_tva'].'mm"      align="center">8: Valoare totală cu TVA<br>(cont 401)</th>';
        $html .= '<th width="'.$cols['procent_adaos'].'mm"                   align="center">9: Procent adaos</th>';
        $html .= '<th width="'.$cols['adaos_unitar'].'mm"                     align="center">10: Adaos com. unitar</th>';
        $html .= '<th width="'.$cols['valoare_adaos'].'mm"                   align="center">11: Adaos com. (Total)</th>';
        $html .= '<th width="'.$cols['pret_cu_adaos_unitar_fara_tva'].'mm"  align="center">12: Preț unitar cu amănuntul<br>fără TVA</th>';
        $html .= '<th width="'.$cols['tva_adaos_comercial'].'mm"           align="center">13: TVA unitar aferent<br>adaos</th>';
        $html .= '<th width="'.$cols['pret_unitar_cu_amanuntul_cu_tva'].'mm" align="center">14: Preț unitar cu<br>amănuntul cu TVA</th>';
        $html .= '<th width="'.$cols['valoare_pret_amanunt'].'mm"           align="center">15: Valoarea la<br>preț cu amănuntul</th>';
        $html .= '<th width="'.$cols['tva_total_unitar'].'mm"               align="center">16: Din care<br>TVA pe UM</th>';
        $html .= '</tr>';

        // ---- Rândul 2: Explicații / formule ----
        $html .= '<tr style="background-color:#EEEEEE; font-size:6px;">';
        $html .= '<td align="center">1</td>';                                       
        $html .= '<td align="center">2</td>';                                          
        $html .= '<td align="center">3</td>';                                       
        $html .= '<td align="center">4</td>';                                          
        $html .= '<td align="center">5= 3*4</td>';                                      
        $html .= '<td align="center">6= Valoarea TVA pe UM</td>';     
        $html .= '<td align="center">7= 3*6</td>';                                      
        $html .= '<td align="center">8= 5+7</td>';                                      
        $html .= '<td align="center">9</td>';                                          
        $html .= '<td align="center">10= 4*9/100</td>';                                 
        $html .= '<td align="center">11= 3*10</td>';                                    
        $html .= '<td align="center">12= 4+10</td>';                                    
        $html .= '<td align="center">13= 10 * Cota TVA</td>';                           
        $html .= '<td align="center">14=4+6+10+13</td>';                                 
        $html .= '<td align="center">15= 3*14</td>';                                    
        $html .= '<td align="center">16= 6+13</td>';                                    
        $html .= '</tr>';
        $html .= '</thead>';

        // ---- Rânduri cu datele efective din grup ----
        $html .= '<tbody>';
        foreach ($group as $row) {
            $html .= '<tr style="font-size:7px;">';
            $html .= '<td width="'.$cols['denumire_produs'].'mm" align="left">'.htmlspecialchars($row['denumire_produs']).'</td>';
            $html .= '<td width="'.$cols['unitate_masura'].'mm" align="center">'.htmlspecialchars($row['unitate_masura']).'</td>';
            $html .= '<td width="'.$cols['cantitate'].'mm" align="right">'.number_format($row['cantitate'], 2, ',', '.').'</td>';
            $html .= '<td width="'.$cols['pret_unitar_achizitie'].'mm" align="right">'.number_format($row['pret_unitar_achizitie'], 3, ',', '.').' ('.htmlspecialchars($row['cota_tva']).'%)</td>';
            $html .= '<td width="'.$cols['valoare_achizitie'].'mm" align="right">'.number_format($row['valoare_achizitie'], 2, ',', '.').'</td>';
            $html .= '<td width="'.$cols['tva_unitar_achizitie'].'mm" align="right">'.number_format($row['tva_unitar_achizitie'], 2, ',', '.').'</td>';
            $html .= '<td width="'.$cols['valoare_tva_achizitie'].'mm" align="right">'.number_format($row['valoare_tva_achizitie'], 2, ',', '.').'</td>';
            $html .= '<td width="'.$cols['valoare_achizitie_cu_tva'].'mm" align="right">'.number_format($row['valoare_achizitie_cu_tva'], 2, ',', '.').'</td>';
            $html .= '<td width="'.$cols['procent_adaos'].'mm" align="right">'.number_format($row['procent_adaos'], 2, ',', '.').'</td>';
            $html .= '<td width="'.$cols['adaos_unitar'].'mm" align="right">'.number_format($row['adaos_unitar'], 2, ',', '.').'</td>';
            $html .= '<td width="'.$cols['valoare_adaos'].'mm" align="right">'.number_format($row['valoare_adaos'], 2, ',', '.').'</td>';
            $html .= '<td width="'.$cols['pret_cu_adaos_unitar_fara_tva'].'mm" align="right">'.number_format($row['pret_cu_adaos_unitar_fara_tva'], 2, ',', '.').'</td>';
            $html .= '<td width="'.$cols['tva_adaos_comercial'].'mm" align="right">'.number_format($row['tva_adaos_comercial'], 2, ',', '.').'</td>';
            $html .= '<td width="'.$cols['pret_unitar_cu_amanuntul_cu_tva'].'mm" align="right">'.number_format($row['pret_unitar_cu_amanuntul_cu_tva'], 2, ',', '.').'</td>';
            $html .= '<td width="'.$cols['valoare_pret_amanunt'].'mm" align="right">'.number_format($row['valoare_pret_amanunt'], 2, ',', '.').'</td>';
            $html .= '<td width="'.$cols['tva_total_unitar'].'mm" align="right">'.number_format($row['tva_total_unitar'], 2, ',', '.').'</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        // (10) Scriem HTML-ul în PDF
        $pdf->writeHTML($html, true, false, true, false, '');

        // (11) Afișăm totalurile specifice acestei cote TVA (păstrând stilul vechi, dar doar relevante)
      // (11) Pregătim conținutul FIX de subsol pentru această pagină (footer)
$pageNo = $pdf->getPage();

// — Titlu și linii pentru “Totaluri pe gestiuni …”
$gestiuni_title = "Totaluri pe gestiuni pentru cota TVA $cota%:";
$gestiuni_lines = [];

// valorile pe care le afișai deja mai sus, dar acum ca linii pentru footer:
if (!empty(${"sumMateriiPrime$cota"})) {
    $gestiuni_lines[] = 'Gestiune materii prime ' . $cota . '%: ' . number_format(${"sumMateriiPrime$cota"}, 2, ',', '.') . ' lei';
}
if ($sumMaterialeConsumabile != 0 && isset($sumsPerCota[$cota]) && (array_key_exists('CONSUMABILE', $sumsPerCota[$cota]) || array_key_exists('CONSUM', $sumsPerCota[$cota]))) {
    $gestiuni_lines[] = 'Gestiune materiale consumabile: ' . number_format($sumMaterialeConsumabile, 2, ',', '.') . ' lei';
}
if (!empty(${"sumMarf{$cota}Ach"})) {
    $gestiuni_lines[] = 'Gestiune marfuri ' . $cota . '% (valoare achizitie): ' . number_format(${"sumMarf{$cota}Ach"}, 2, ',', '.') . ' lei';
}
if (!empty(${"sumMarf{$cota}Vanz"})) {
    $gestiuni_lines[] = 'Gestiune marfuri ' . $cota . '% (valoare vanzare): ' . number_format(${"sumMarf{$cota}Vanz"}, 2, ',', '.') . ' lei';
}

// alte gestiuni (ca înainte), dar doar adăugăm linii:
if (isset($sumsPerCota[$cota])) {
    foreach ($sumsPerCota[$cota] as $gestiune => $sums) {
        if (strpos($gestiune, 'MATERII') !== false || strpos($gestiune, 'CONSUM') !== false || strpos($gestiune, 'MARF') !== false) {
            continue; // deja tratate mai sus
        }
        if ($sums['ach'] != 0 || $sums['vanz'] != 0) {
            $gestiuni_lines[] = 'Gestiune ' . $gestiune . ' - Achizitie: ' . number_format($sums['ach'], 2, ',', '.') . ' lei, Vanzare: ' . number_format($sums['vanz'], 2, ',', '.') . ' lei';
        }
    }
}

// — Titlu și 6 coloane pentru “Totaluri coloane …”
// Notă: forțăm 6 coloane; în a 6-a celulă punem două valori una sub alta.
$coloane_title = "Totaluri coloane pentru cota TVA $cota%:";

$cols6 = [
    'C5 Val: ' . number_format($totalsPerCota[$cota]['valoare_achizitie'] ?? 0, 2, ',', '.') . ' lei',
    'C7 TVA ach: ' . number_format($totalsPerCota[$cota]['valoare_tva_achizitie'] ?? 0, 2, ',', '.') . ' lei',
    'C8 Val cu TVA (401): ' . number_format($totalsPerCota[$cota]['valoare_achizitie_cu_tva'] ?? 0, 2, ',', '.') . ' lei',
    'C11 Adaos: ' . number_format($totalsPerCota[$cota]['valoare_adaos'] ?? 0, 2, ',', '.') . ' lei',
    'C15 Val aman.: ' . number_format($totalsPerCota[$cota]['valoare_pret_amanunt'] ?? 0, 2, ',', '.') . ' lei',
    // 6: totul pe un rând, despărțit cu |
    'TVA adaos: ' . number_format($totalsPerCota[$cota]['tva_adaos_total'] ?? 0, 2, ',', '.') . ' lei'
    . ' | TVA total: ' . number_format($totalsPerCota[$cota]['tva_total'] ?? 0, 2, ',', '.') . ' lei',
];


// atașăm pachetul pentru footerul paginii curente
$pdf->pageFooter[$pageNo] = [
    'gestiuni_title' => $gestiuni_title,
    'gestiuni_lines' => $gestiuni_lines,
    'coloane_title'  => $coloane_title,
    'cols6'          => $cols6,
];


      
    }
}


// (12) Observații - afișăm secțiunea doar dacă clientul nu are ID-ul 6 sau 2
if ($client_agecs != 6 && $client_agecs != 2 && $client_agecs != 18) {
    $pdf->Ln(5);
    $pdf->SetFont('dejavusans', '', 9);
    $obs = $observatii ? $observatii : ' - ';
    $pdf->MultiCell(0, 0, 'Observații: ' . htmlspecialchars($obs), 0, 'L');
}

// (13) Verificare spațiu și adăugare pagină dacă este necesar
$required_space = 20; // mm, ajustează după necesități
$current_y = $pdf->GetY();
$page_height = $pdf->getPageHeight();
$bottom_margin = $pdf->getBreakMargin();
$available_space = $page_height - $bottom_margin - $current_y;

if ($available_space < $required_space) {
    $pdf->AddPage();
}

// (14) Secțiune semnături (opțional)
$pdf->SetFont('dejavusans', '', 9);

if ($client_agecs == 6 || $client_agecs == 2|| $client_agecs == 18) {
    // Dacă clientul are ID-ul 6, folosim blocul alternativ cu 3 coloane (fără borduri)
    $semnHTML = '
    <table border="0" cellpadding="4" cellspacing="0">
        <tr>
            <td align="center">CONTABIL</td>
            <td align="center">MANAGER</td>
            <td align="center">GESTIONAR</td>
        </tr>
    </table>
    ';
} else {
    // Dacă clientul nu are ID-ul 6, păstrăm blocul inițial cu 4 celule și borduri
    $semn1_width = 100;
    $semn2_width = 40;
    $semn3_width = 100;
    $semn4_width = 40;

    $semnHTML = '
    <table border="0" cellpadding="4" cellspacing="0">
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

// Scriem blocul de semnături în PDF
$pdf->writeHTML($semnHTML, true, false, true, false, '');

// (15) Output
$pdf->Output('nir_'.$nr_nir.'.pdf', 'I');

// End output buffering and flush
ob_end_flush();
?>
