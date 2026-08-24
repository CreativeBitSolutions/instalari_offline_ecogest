<?php
// Start output buffering to prevent the "already output" error
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'db.php';
require_once __DIR__ . '/tcpdf_bootstrap.php';

function nir_pdf_text($value) {
    $text = (string)$value;
    $text = strtr($text, [
        'ă' => 'a', 'Ă' => 'A',
        'â' => 'a', 'Â' => 'A',
        'î' => 'i', 'Î' => 'I',
        'ș' => 's', 'Ș' => 'S',
        'ş' => 's', 'Ş' => 'S',
        'ț' => 't', 'Ț' => 'T',
        'ţ' => 't', 'Ţ' => 'T',
    ]);
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($converted !== false) {
            $text = $converted;
        }
    }
    return preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '', $text);
}

function nir_pdf_html($value) {
    return htmlspecialchars(nir_pdf_text($value), ENT_QUOTES, 'UTF-8');
}

/** PDF cu footer pentru totaluri pe paginÄƒ */
class NIRPDF extends TCPDF {
    /** datele de footer per paginÄƒ (pageNo => ['gestiuni_title','gestiuni_lines'=>[], 'coloane_title','cols6'=>[]]) */
    public $pageFooter = [];
    /** Ã®nÄƒlÈ›imea totalÄƒ rezervatÄƒ footerului (mm) */
    public $footerHeight = 20;
public $cellRatio    = 0.90; // pentru spaÈ›iere mai micÄƒ Ã®ntre rÃ¢nduri

    public function Footer() {
    $p = $this->getPage();
    if (empty($this->pageFooter[$p])) { return; }

    $data = $this->pageFooter[$p];

    $m = $this->getMargins();
    $x = $m['left'];
    $w = $this->getPageWidth() - $m['left'] - $m['right'];

    // poziÈ›ionare la footer
    $this->SetY(-$this->footerHeight);
    $y = $this->GetY();

    // linie separatoare
    $this->SetDrawColor(0,0,0);
    $this->Line($x, $y, $x+$w, $y);

    // spaÈ›iere de rÃ¢nd mai micÄƒ
    $this->setCellHeightRatio($this->cellRatio);

    // ===== Totaluri pe gestiuni â€“ o singurÄƒ frazÄƒ cu bullets =====
    $gestStr = '-';
    if (!empty($data['gestiuni_lines'])) {
        $gestStr = nir_pdf_html(implode(' | ', $data['gestiuni_lines']));
    }
    $htmlG = '<div style="font-size:10px;line-height:1;">'
           . '<span style="font-weight:bold;">' . nir_pdf_html($data['gestiuni_title']) . '</span> '
           . $gestStr
           . '</div>';

    // scriem fraza
    $this->writeHTMLCell($w, 0, $x, $y+1.2, $htmlG, 0, 1, 0, true, 'L', true);

    // ===== Totaluri pe coloane â€“ un singur rÃ¢nd cu 6 celule =====
    $cells = '';
    foreach ($data['cols6'] as $txt) {
        $cells .= '<td style="padding:0 2px;white-space:nowrap;">' . nir_pdf_html($txt) . '</td>';
    }
    $htmlC = '<div style="font-size:10px;line-height:1;"><span style="font-weight:bold;">'
           . nir_pdf_html($data['coloane_title'])
           . '</span></div>'
           . '<table width="100%" border="0" cellpadding="0" cellspacing="0" '
           . 'style="font-size:10px;line-height:1;"><tr>' . $cells . '</tr></table>';

    $this->writeHTMLCell($w, 0, $x, '', $htmlC, 0, 1, 0, true, 'L', true);
}

}

// (3) PreluÄƒm nr_nir, id_gestiune È™i cota_tva din GET:
$nr_nir = isset($_GET['nr_nir']) ? $_GET['nr_nir'] : '';
$id_gestiune = isset($_GET['id_gestiune']) ? $_GET['id_gestiune'] : '';
$cota_tva = isset($_GET['cota_tva']) ? $_GET['cota_tva'] : ''; // MODIFICARE: AdÄƒugat parametrul pentru cota TVA
$rezumat_cu_spatii = !isset($_GET['spatii']) || (string)$_GET['spatii'] !== '0';

if (empty($nr_nir)) {
    die("Numarul NIR nu a fost specificat.");
}

// Construim interogarea de bazÄƒ:
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

// IniÈ›ializÄƒm array-ul de parametri pentru prepared statement:
$params = ['nr_nir' => $nr_nir];

// DacÄƒ s-a selectat È™i o gestiune, adÄƒugÄƒm condiÈ›ia de filtrare:
if (!empty($id_gestiune)) {
    $sqlAchiz .= " AND ps.id_gestiune = :id_gestiune";
    $params['id_gestiune'] = $id_gestiune;
}

// ===================== BLOC NOU ADAUGAT =====================
// DacÄƒ s-a selectat È™i o cotÄƒ TVA, adÄƒugÄƒm condiÈ›ia de filtrare:
if ($cota_tva !== '') { // VerificÄƒm cu '' pentru a permite filtrarea pe cota 0
    $sqlAchiz .= " AND a.cota_tva = :cota_tva";
    $params['cota_tva'] = $cota_tva;
}
// ===================== SFÃ‚RÈ˜IT BLOC NOU ADAUGAT =====================

$stmtAchiz = $pdo->prepare($sqlAchiz);
$stmtAchiz->execute($params);
$rowsAchiz = $stmtAchiz->fetchAll(PDO::FETCH_ASSOC);

// (4) Facem un query pentru datele generale din nir (tabelul `nir`)
$sqlNir = "SELECT * FROM nir WHERE nr_nir = :nr_nir";
$stmtNir = $pdo->prepare($sqlNir);
$stmtNir->execute(['nr_nir' => $nr_nir]);
$nirData = $stmtNir->fetch(PDO::FETCH_ASSOC);

if (!$nirData) {
    die("Nu exista NIR cu acest numar: $nr_nir");
}

// Extragem datele principale 
$data_nir      = date('d-m-Y', strtotime($nirData['data_nir']));
$id_furnizor   = $nirData['cod_tert'];  
$serie_doc_int = $nirData['serie_doc_int'];
$nr_doc_int    = $nirData['nr_doc_int'];
$data_doc_int  = date('d-m-Y', strtotime($nirData['data_doc_int']));
$observatii    = $nirData['observatii'];

// ObÈ›inem denumirea terÈ›ului din tabelul `furnizori`
$sqlFurnizor= "SELECT nume FROM furnizori WHERE id_furnizor= :id_furnizor";
$stmtfurnizor= $pdo->prepare($sqlFurnizor);
$stmtfurnizor->execute(['id_furnizor' => $id_furnizor]);
$furnizorData = $stmtfurnizor->fetch(PDO::FETCH_ASSOC);
$denumire_furnizor= $furnizorData ? $furnizorData['nume'] : "Numele tertului necunoscut";

// Pornim sesiunea, dacÄƒ nu este deja pornitÄƒ
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$client_agecs = isset($_SESSION['client_id']) ? $_SESSION['client_id'] : null;

// (6) IniÈ›ializÄƒm TCPDF
$pdf = new NIRPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Autorul tau');
$pdf->SetTitle('Nota de receptie si constatare de diferente');
$pdf->SetMargins(10, 10, 10);

/* rezervÄƒm spaÈ›iu pentru subsolul fix (footer) ca sÄƒ nu fie acoperit de tabel */
$pdf->SetAutoPageBreak(true, $pdf->footerHeight);


// PregÄƒtim un statement pentru a identifica gestiunea pe baza denumirii produsului
$sqlGetGestiune = "
    SELECT 
        g.denumire_gestiune
    FROM produse_servicii p
    JOIN gestiuni g ON p.id_gestiune = g.id_gestiune
    WHERE p.cod_produs = :codul_produsului
    LIMIT 1
    ";
$stmtGestiune = $pdo->prepare($sqlGetGestiune);

// IniÈ›ializÄƒm array pentru grupare pe cote TVA
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

// PregÄƒtim variabilele pentru sume (pÄƒstrÄƒm logica veche pentru compatibilitate)
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

    /* ================== NOU: override pentru vÃ¢nzare cÃ¢nd col.4 = 0 ==================
       DacÄƒ preÈ›ul unitar de achiziÈ›ie (col.4) este 0, calculÄƒm coloanele 10..16
       pornind de la produse_servicii.pret_cu_tva (ps.pret_cu_tva_ps pentru cod_p).
       ConvenÈ›ie:
         - col.14 (preÈ› unitar cu amÄƒnuntul cu TVA) = ps.pret_cu_tva
         - col.12 (fÄƒrÄƒ TVA) = col.14 / (1 + cota/100)
         - col.16 (TVA pe UM) = col.14 - col.12
         - col.10 (adaos unitar) = col.12 - col.4  (= col.12, cÄƒci col.4=0)
         - col.11 = cantitate * col.10
         - col.13 (TVA aferent adaos) = col.16 - col.6 (= col.16, cÄƒci col.6=0)
         - col.15 = cantitate * col.14
       NotÄƒ: lÄƒsÄƒm col.6/7/8 (achiziÈ›ie) aÈ™a cum vin din DB (vor fi 0 dacÄƒ 4=0).
*/
    // GrupÄƒm rows pe cota_tva
    $cota = (float)$row['cota_tva'];
    if ((float)$row['pret_unitar_achizitie'] == 0 && !empty($row['pret_cu_tva_ps']) && (float)$row['pret_cu_tva_ps'] > 0) {
        $cant              = (float)$row['cantitate'];
        $pretVanzCuTVA     = (float)$row['pret_cu_tva_ps'];
        $factorTVA         = 1 + ($cota / 100.0);
        $pretVanzFaraTVA   = $factorTVA > 0 ? ($pretVanzCuTVA / $factorTVA) : $pretVanzCuTVA;

        // TVA extras din preÈ›ul de vÃ¢nzare (pe UM)
        $tvaExtrasDinPret  = $pretVanzCuTVA - $pretVanzFaraTVA; // TVA total UM derivat din preÈ›ul cu TVA

        // col.6 din DB (poate fi 0 dacÄƒ È™i 4=0)
        $tvaUnitarAchiz    = (float)$row['tva_unitar_achizitie'];

        // Col.9 (procent adaos) ar fi nedefinit (div/0); Ã®l setÄƒm 0 ca sÄƒ nu afiÅŸeze NaN
        $row['procent_adaos']                 = 0.0;

        // 10..16 recalibrate din preÈ›ul de vÃ¢nzare
        $row['adaos_unitar']                  = $pretVanzFaraTVA - (float)$row['pret_unitar_achizitie']; // col.10
        $row['valoare_adaos']                 = $row['adaos_unitar'] * $cant;                            // col.11
        $row['pret_cu_adaos_unitar_fara_tva'] = $pretVanzFaraTVA;                                       // col.12

        // col.13 = TVA aferent adaos = TVA extras din preÈ› - TVA unitar achiziÈ›ie (nu negativ)
        $col13 = $tvaExtrasDinPret - $tvaUnitarAchiz;
        if ($col13 < 0) { $col13 = 0.0; }
        $row['tva_adaos_comercial']           = $col13;                                                 // col.13

        $row['pret_unitar_cu_amanuntul_cu_tva']= $pretVanzCuTVA;                                        // col.14
        $row['valoare_pret_amanunt']          = $pretVanzCuTVA * $cant;                                 // col.15

        // col.16 = 6 + 13 (asigurÄƒm identitatea 16=6+13)
        $row['tva_total_unitar']              = $tvaUnitarAchiz + $row['tva_adaos_comercial'];          // col.16
    }

    // GrupÄƒm DUPÄ‚ override, pentru ca HTML-ul È™i totalurile sÄƒ foloseascÄƒ valorile noi
    $groupedRows[(int)$cota][] = $row;

    // ---- DeterminÄƒm gestiunea efectivÄƒ ----
    $stmtGestiune->execute([':codul_produsului' => $row['cod_p']]);
    $rowGes = $stmtGestiune->fetch(PDO::FETCH_ASSOC);
    if (!$rowGes) {
        continue;
    }

    $denumireGestiune = strtoupper($rowGes['denumire_gestiune'] ?? '');
    $cotaTvaProdus = (int)$cota;
    $valAch = (float)$row['valoare_achizitie'];
    $valVanz = (float)$row['valoare_pret_amanunt'];

    // Calcul sume per cota per gestiune (noua structurÄƒ generalÄƒ)
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

    // AdÄƒugÄƒm la totaluri generale
    $grandTotals['valoare_achizitie'] += (float)$row['valoare_achizitie'];
    $grandTotals['valoare_tva_achizitie'] += (float)$row['valoare_tva_achizitie'];
    $grandTotals['valoare_achizitie_cu_tva'] += (float)$row['valoare_achizitie_cu_tva'];
    $grandTotals['valoare_adaos'] += (float)$row['valoare_adaos'];
    $grandTotals['valoare_pret_amanunt'] += (float)$row['valoare_pret_amanunt'];
    $grandTotals['tva_adaos_total'] += (float)$row['tva_adaos_comercial'] * (float)$row['cantitate'];
    $grandTotals['tva_total'] += (float)$row['tva_total_unitar'] * (float)$row['cantitate'];

    // PÄƒstrÄƒm logica veche de calcul sume
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

    // Agregare generalÄƒ pentru toate gestiunile
    if (!isset($sumGestiuniAll[$denumireGestiune])) {
        $sumGestiuniAll[$denumireGestiune] = ['achizitie' => 0.0, 'vanzare' => 0.0];
    }
    $sumGestiuniAll[$denumireGestiune]['achizitie'] += $valAch;
    $sumGestiuniAll[$denumireGestiune]['vanzare'] += $valVanz;
}

// SortÄƒm cote TVA crescÄƒtor
ksort($groupedRows);

// (9) Definim lÄƒÈ›imile coloanelor (pÄƒstrÄƒm acelaÈ™i design)
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

// VerificÄƒm lÄƒÈ›imea totalÄƒ
$total_width = array_sum($cols);
if ($total_width > 277) {
    die("Tabelul depaseste latimea disponibila (277 mm). Ajusteaza latimile coloanelor.");
}

// ObÈ›inem datele firmei (pÄƒstrÄƒm)
$df_sql = "SELECT * FROM date_firma LIMIT 1";
$df_stmt = $pdo->prepare($df_sql);
$df_stmt->execute();
$date_firma = $df_stmt->fetch(PDO::FETCH_ASSOC);

if (!$date_firma) {
    die("Eroare: Datele firmei nu au fost gasite.");
}

// Handle empty data case to avoid writing without pages
if (empty($groupedRows)) {
    $pdf->AddPage();
    $pdf->SetFont('dejavusans', 'B', 14);
    $pdf->Cell(0, 10, 'NOTA DE RECEPTIE SI CONSTATARE DE DIFERENTE', 0, 1, 'C');
    $pdf->Ln(5);
    $pdf->SetFont('dejavusans', '', 10);
    $pdf->Cell(0, 10, 'Nu exista date pentru acest NIR.', 0, 1, 'C');
} else {
    // Pentru fiecare cotÄƒ TVA, creÄƒm o secÈ›iune separatÄƒ (paginÄƒ nouÄƒ)
    foreach ($groupedRows as $cota => $group) {
        $pdf->AddPage();

        // (7) Titlul principal
        $pdf->SetFont('dejavusans', 'B', 10);
        $pdf->Cell(0, 10, 'NOTA DE RECEPTIE SI CONSTATARE DE DIFERENTE', 0, 1, 'C');

        // Linie orizontalÄƒ sub titlu
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->Line(10, $pdf->GetY(), 287, $pdf->GetY());
        $pdf->Ln(2);

        // (8) InformaÈ›ii antet - date firmÄƒ / furnizor / NIR
        $pdf->SetFont('dejavusans', '', 8);

        $pdf->Cell(0, 6, 'Unitatea: ' . nir_pdf_text($date_firma['den_ent']), 0, 1);
        $pdf->Cell(0, 6, 'Furnizor: ' . nir_pdf_text($denumire_furnizor), 0, 1);
        $pdf->Cell(0, 6, "Factura/Aviz: $serie_doc_int $nr_doc_int din $data_doc_int", 0, 1);
        $pdf->Cell(0, 6, "NIR: $nr_nir din $data_nir (Cota TVA: $cota%)", 0, 1);

        // Construim tabelul HTML doar pentru acest grup
        $html = '<table border="1" cellpadding="3" cellspacing="0" style="font-size:6px;">';

        // ---- RÃ¢ndul 1: Denumiri coloane + indicii (1..16) ----
        $html .= '<thead>';
        $html .= '<tr style="background-color:#CCCCCC; font-weight:bold;">';
        $html .= '<th width="'.$cols['denumire_produs'].'mm"                     align="center">1: Denumire produs</th>';
        $html .= '<th width="'.$cols['unitate_masura'].'mm"                       align="center">2: UM</th>';
        $html .= '<th width="'.$cols['cantitate'].'mm"                             align="center">3: Cantitate</th>';
        $html .= '<th width="'.$cols['pret_unitar_achizitie'].'mm"         align="center">4: Pret unitar (cota tva %)</th>';
        $html .= '<th width="'.$cols['valoare_achizitie'].'mm"             align="center">5: Valoare</th>';
        $html .= '<th width="'.$cols['tva_unitar_achizitie'].'mm"          align="center">6: TVA unitar ach.</th>';
        $html .= '<th width="'.$cols['valoare_tva_achizitie'].'mm"         align="center">7: TVA total</th>';
        $html .= '<th width="'.$cols['valoare_achizitie_cu_tva'].'mm"      align="center">8: Valoare totala cu TVA<br>(cont 401)</th>';
        $html .= '<th width="'.$cols['procent_adaos'].'mm"                   align="center">9: Procent adaos</th>';
        $html .= '<th width="'.$cols['adaos_unitar'].'mm"                     align="center">10: Adaos com. unitar</th>';
        $html .= '<th width="'.$cols['valoare_adaos'].'mm"                   align="center">11: Adaos com. (Total)</th>';
        $html .= '<th width="'.$cols['pret_cu_adaos_unitar_fara_tva'].'mm"  align="center">12: Pret unitar cu amanuntul<br>fara TVA</th>';
        $html .= '<th width="'.$cols['tva_adaos_comercial'].'mm"           align="center">13: TVA unitar aferent<br>adaos</th>';
        $html .= '<th width="'.$cols['pret_unitar_cu_amanuntul_cu_tva'].'mm" align="center">14: Pret unitar cu<br>amanuntul cu TVA</th>';
        $html .= '<th width="'.$cols['valoare_pret_amanunt'].'mm"           align="center">15: Valoarea la<br>pret cu amanuntul</th>';
        $html .= '<th width="'.$cols['tva_total_unitar'].'mm"               align="center">16: Din care<br>TVA pe UM</th>';
        $html .= '</tr>';

        // ---- RÃ¢ndul 2: ExplicaÈ›ii / formule ----
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

        // ---- RÃ¢nduri cu datele efective din grup ----
        $html .= '<tbody>';
        foreach ($group as $row) {
            $html .= '<tr style="font-size:7px;">';
            $html .= '<td width="'.$cols['denumire_produs'].'mm" align="left">'.nir_pdf_html($row['denumire_produs']).'</td>';
            $html .= '<td width="'.$cols['unitate_masura'].'mm" align="center">'.nir_pdf_html($row['unitate_masura']).'</td>';
            $html .= '<td width="'.$cols['cantitate'].'mm" align="right">'.number_format($row['cantitate'], 2, ',', '.').'</td>';
            $html .= '<td width="'.$cols['pret_unitar_achizitie'].'mm" align="right">'.number_format($row['pret_unitar_achizitie'], 3, ',', '.').' ('.nir_pdf_html($row['cota_tva']).'%)</td>';
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

        // (10) Scriem HTML-ul Ã®n PDF
        $pdf->writeHTML($html, true, false, true, false, '');

        // (11) AfiÈ™Äƒm totalurile specifice acestei cote TVA (pÄƒstrÃ¢nd stilul vechi, dar doar relevante)
      // (11) PregÄƒtim conÈ›inutul FIX de subsol pentru aceastÄƒ paginÄƒ (footer)
$pageNo = $pdf->getPage();

// â€” Titlu È™i linii pentru â€œTotaluri pe gestiuni â€¦â€
$gestiuni_title = "Totaluri pe gestiuni pentru cota TVA $cota%:";
$gestiuni_lines = [];

// valorile pe care le afiÈ™ai deja mai sus, dar acum ca linii pentru footer:
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

// alte gestiuni (ca Ã®nainte), dar doar adÄƒugÄƒm linii:
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

// â€” Titlu È™i 6 coloane pentru â€œTotaluri coloane â€¦â€
// NotÄƒ: forÈ›Äƒm 6 coloane; Ã®n a 6-a celulÄƒ punem douÄƒ valori una sub alta.
$coloane_title = "Totaluri coloane pentru cota TVA $cota%:";

$cols6 = [
    'C5 Val: ' . number_format($totalsPerCota[$cota]['valoare_achizitie'] ?? 0, 2, ',', '.') . ' lei',
    'C7 TVA ach: ' . number_format($totalsPerCota[$cota]['valoare_tva_achizitie'] ?? 0, 2, ',', '.') . ' lei',
    'C8 Val cu TVA (401): ' . number_format($totalsPerCota[$cota]['valoare_achizitie_cu_tva'] ?? 0, 2, ',', '.') . ' lei',
    'C11 Adaos: ' . number_format($totalsPerCota[$cota]['valoare_adaos'] ?? 0, 2, ',', '.') . ' lei',
    'C15 Val aman.: ' . number_format($totalsPerCota[$cota]['valoare_pret_amanunt'] ?? 0, 2, ',', '.') . ' lei',
    // 6: totul pe un rÃ¢nd, despÄƒrÈ›it cu |
    'TVA adaos: ' . number_format($totalsPerCota[$cota]['tva_adaos_total'] ?? 0, 2, ',', '.') . ' lei'
    . ' | TVA total: ' . number_format($totalsPerCota[$cota]['tva_total'] ?? 0, 2, ',', '.') . ' lei',
];


// ataÈ™Äƒm pachetul pentru footerul paginii curente
$pdf->pageFooter[$pageNo] = [
    'gestiuni_title' => $gestiuni_title,
    'gestiuni_lines' => $gestiuni_lines,
    'coloane_title'  => $coloane_title,
    'cols6'          => $cols6,
];


      
    }
}

// AdÄƒugÄƒm o paginÄƒ finalÄƒ cu totaluri generale dacÄƒ existÄƒ date
if (!empty($groupedRows)) {
    if ($rezumat_cu_spatii) {
        $pdf->AddPage();
    } else {
        $pdf->Ln(4);
    }

    if ($rezumat_cu_spatii) {
        $pdf->SetFont('dejavusans', 'B', 14);
        $pdf->Cell(0, 10, 'TOTALURI GENERALE NIR', 0, 1, 'C');
        $pdf->Ln(2);
    }
    $pdf->Line(10, $pdf->GetY(), 287, $pdf->GetY());
    $pdf->Ln(2);

    // AfiÈ™Äƒm totalurile generale Ã®n stilul vechi
    $pdf->SetFont('dejavusans', 'B', 9);
    $pdf->Cell(0, 5, 'Totaluri pe gestiuni (in functie de cota TVA de vanzare):', 0, 1, 'L');
    $pdf->SetFont('dejavusans', '', 8);

    if ($sumMateriiPrime != 0) {
        $pdf->Cell(0, 5, 'Gestiune totala materii prime: ' . number_format($sumMateriiPrime, 2, ',', '.') . ' lei', 0, 1, 'L');
        if ($sumMateriiPrime5 != 0) {
            $pdf->Cell(0, 5, 'Gestiune materii prime 5%: ' . number_format($sumMateriiPrime5, 2, ',', '.') . ' lei', 0, 1, 'L');
        }
        if ($sumMateriiPrime9 != 0) {
            $pdf->Cell(0, 5, 'Gestiune materii prime 9%: ' . number_format($sumMateriiPrime9, 2, ',', '.') . ' lei', 0, 1, 'L');
        }
        if ($sumMateriiPrime11 != 0) {
            $pdf->Cell(0, 5, 'Gestiune materii prime 11%: ' . number_format($sumMateriiPrime11, 2, ',', '.') . ' lei', 0, 1, 'L');
        }
        if ($sumMateriiPrime19 != 0) {
            $pdf->Cell(0, 5, 'Gestiune materii prime 19%: ' . number_format($sumMateriiPrime19, 2, ',', '.') . ' lei', 0, 1, 'L');
        }
        if ($sumMateriiPrime21 != 0) {
            $pdf->Cell(0, 5, 'Gestiune materii prime 21%: ' . number_format($sumMateriiPrime21, 2, ',', '.') . ' lei', 0, 1, 'L');
        }
        if ($sumMateriiPrime0 != 0) {
            $pdf->Cell(0, 5, 'Gestiune materii prime 0%: ' . number_format($sumMateriiPrime0, 2, ',', '.') . ' lei', 0, 1, 'L');
        }
    }
    if ($sumMaterialeConsumabile != 0) {
        $pdf->Cell(0, 5, 'Gestiune materiale consumabile: ' . number_format($sumMaterialeConsumabile, 2, ',', '.') . ' lei', 0, 1, 'L');
    }
    if ($sumMarfuriAchTot != 0) {
        $pdf->Cell(0, 5, 'Gestiune totala marfuri (achizitie): ' . number_format($sumMarfuriAchTot, 2, ',', '.') . ' lei', 0, 1, 'L');
    }
    if ($sumMarf5Ach != 0) {
        $pdf->Cell(0, 5, 'Gestiune marfuri 5% (valoare achizitie): ' . number_format($sumMarf5Ach, 2, ',', '.') . ' lei', 0, 1, 'L');
    }
    if ($sumMarf9Ach != 0) {
        $pdf->Cell(0, 5, 'Gestiune marfuri 9% (valoare achizitie): ' . number_format($sumMarf9Ach, 2, ',', '.') . ' lei', 0, 1, 'L');
    }
    if ($sumMarf11Ach != 0) {
        $pdf->Cell(0, 5, 'Gestiune marfuri 11% (valoare achizitie): ' . number_format($sumMarf11Ach, 2, ',', '.') . ' lei', 0, 1, 'L');
    }
    if ($sumMarf19Ach != 0) {
        $pdf->Cell(0, 5, 'Gestiune marfuri 19% (valoare achizitie): ' . number_format($sumMarf19Ach, 2, ',', '.') . ' lei', 0, 1, 'L');
    }
    if ($sumMarf21Ach != 0) {
        $pdf->Cell(0, 5, 'Gestiune marfuri 21% (valoare achizitie): ' . number_format($sumMarf21Ach, 2, ',', '.') . ' lei', 0, 1, 'L');
    }
    if ($sumMarf0Ach != 0) {
        $pdf->Cell(0, 5, 'Gestiune marfuri 0% (valoare achizitie): ' . number_format($sumMarf0Ach, 2, ',', '.') . ' lei', 0, 1, 'L');
    }
    if ($sumMarfuriVanzTot != 0) {
        $pdf->Cell(0, 5, 'Gestiune totala marfuri (vanzare): ' . number_format($sumMarfuriVanzTot, 2, ',', '.') . ' lei', 0, 1, 'L');
    }
    if ($sumMarf5Vanz != 0) {
        $pdf->Cell(0, 5, 'Gestiune marfuri 5% (valoare vanzare): ' . number_format($sumMarf5Vanz, 2, ',', '.') . ' lei', 0, 1, 'L');
    }
    if ($sumMarf9Vanz != 0) {
        $pdf->Cell(0, 5, 'Gestiune marfuri 9% (valoare vanzare): ' . number_format($sumMarf9Vanz, 2, ',', '.') . ' lei', 0, 1, 'L');
    }
    if ($sumMarf11Vanz != 0) {
        $pdf->Cell(0, 5, 'Gestiune marfuri 11% (valoare vanzare): ' . number_format($sumMarf11Vanz, 2, ',', '.') . ' lei', 0, 1, 'L');
    }
    if ($sumMarf19Vanz != 0) {
        $pdf->Cell(0, 5, 'Gestiune marfuri 19% (valoare vanzare): ' . number_format($sumMarf19Vanz, 2, ',', '.') . ' lei', 0, 1, 'L');
    }
    if ($sumMarf21Vanz != 0) {
        $pdf->Cell(0, 5, 'Gestiune marfuri 21% (valoare vanzare): ' . number_format($sumMarf21Vanz, 2, ',', '.') . ' lei', 0, 1, 'L');
    }
    if ($sumMarf0Vanz != 0) {
        $pdf->Cell(0, 5, 'Gestiune marfuri 0% (valoare vanzare): ' . number_format($sumMarf0Vanz, 2, ',', '.') . ' lei', 0, 1, 'L');
    }

    // Alte gestiuni generale
    $pdf->Ln(3);
    $pdf->SetFont('dejavusans', 'B', 9);
    $alteGestiuni = [];
    foreach ($sumGestiuniAll as $gestiune => $sums) {
        if (strpos($gestiune, 'MATERII') !== false || strpos($gestiune, 'CONSUM') !== false || strpos($gestiune, 'MARF') !== false) {
            continue;
        }
        if ($sums['achizitie'] != 0 || $sums['vanzare'] != 0) {
            $alteGestiuni[$gestiune] = $sums;
        }
    }
    if (!empty($alteGestiuni)) {
        $pdf->Cell(0, 5, 'Totaluri pe alte gestiuni:', 0, 1, 'L');
        $pdf->SetFont('dejavusans', '', 8);
        foreach ($alteGestiuni as $gestiune => $sums) {
            $pdf->Cell(0, 5, 'Gestiune ' . nir_pdf_text($gestiune) . ' - Achizitie: ' . number_format($sums['achizitie'], 2, ',', '.') . ' lei, Vanzare: ' . number_format($sums['vanzare'], 2, ',', '.') . ' lei', 0, 1, 'L');
        }
    }

    // AfiÈ™Äƒm totalurile coloanelor generale Ã®n 3 coloane
    $pdf->Ln(3);
    $pdf->SetFont('dejavusans', 'B', 9);
    $pdf->Cell(0, 5, 'Totaluri coloane generale:', 0, 1, 'L');
    $pdf->SetFont('dejavusans', '', 8);

    $col_width = 92;
    $y_start = $pdf->GetY();

    $totals_list = [
        'Total Col 5 - Valoare: ' . number_format($grandTotals['valoare_achizitie'], 2, ',', '.') . ' lei',
        'Total Col 7 - TVA total achizitie: ' . number_format($grandTotals['valoare_tva_achizitie'], 2, ',', '.') . ' lei',
        'Total Col 8 - Valoare totala cu TVA (cont 401): ' . number_format($grandTotals['valoare_achizitie_cu_tva'], 2, ',', '.') . ' lei',
        'Total Col 11 - Adaos comercial total: ' . number_format($grandTotals['valoare_adaos'], 2, ',', '.') . ' lei',
        'Total Col 15 - Valoarea la pret cu amanuntul: ' . number_format($grandTotals['valoare_pret_amanunt'], 2, ',', '.') . ' lei',
        'TVA total pe adaos comercial: ' . number_format($grandTotals['tva_adaos_total'], 2, ',', '.') . ' lei',
        'TVA total (achizitie + adaos): ' . number_format($grandTotals['tva_total'], 2, ',', '.') . ' lei'
    ];

    for ($i = 0; $i < count($totals_list); $i++) {
        $col = $i % 3;
        $row = floor($i / 3);
        $pdf->SetXY(10 + $col * $col_width, $y_start + $row * 5);
        $pdf->MultiCell($col_width, 5, $totals_list[$i], 0, 'L');
    }

    $pdf->SetY($y_start + (floor((count($totals_list) - 1) / 3) + 1) * 5);
}

// (12) ObservaÈ›ii - afiÈ™Äƒm secÈ›iunea doar dacÄƒ clientul nu are ID-ul 6 sau 2
if ($client_agecs != 6 && $client_agecs != 2 && $client_agecs != 18) {
    $obs = trim(nir_pdf_text($observatii ?? ''));
    if ($obs !== '' && $obs !== '-') {
        $pdf->Ln(2);
        $pdf->SetFont('dejavusans', '', 9);
        $pdf->MultiCell(0, 0, 'Observatii: ' . $obs, 0, 'L');
    }
}

// (13) Verificare spaÈ›iu È™i adÄƒugare paginÄƒ dacÄƒ este necesar
$required_space = 20; // mm, ajusteazÄƒ dupÄƒ necesitÄƒÈ›i
$current_y = $pdf->GetY();
$page_height = $pdf->getPageHeight();
$bottom_margin = $pdf->getBreakMargin();
$available_space = $page_height - $bottom_margin - $current_y;

if ($available_space < $required_space) {
    $pdf->AddPage();
}

// (14) SecÈ›iune semnÄƒturi (opÈ›ional)
$pdf->SetFont('dejavusans', '', 9);

if ($client_agecs == 6 || $client_agecs == 2|| $client_agecs == 18) {
    // DacÄƒ clientul are ID-ul 6, folosim blocul alternativ cu 3 coloane (fÄƒrÄƒ borduri)
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
    // DacÄƒ clientul nu are ID-ul 6, pÄƒstrÄƒm blocul iniÈ›ial cu 4 celule È™i borduri
    $semn1_width = 100;
    $semn2_width = 40;
    $semn3_width = 100;
    $semn4_width = 40;

    $semnHTML = '
    <table border="0" cellpadding="4" cellspacing="0">
        <tr>
            <td width="' . $semn1_width . 'mm" align="C">
                Numele si prenumele membrilor comisiei de receptie<br><br><br><br>
            </td>
            <td width="' . $semn2_width . 'mm" align="C">
                Semnatura<br><br><br><br>
            </td>
            <td width="' . $semn3_width . 'mm" align="C">
                Numele si prenumele gestionarului<br><br><br><br>
            </td>
            <td width="' . $semn4_width . 'mm" align="C">
                Semnatura<br><br><br><br>
            </td>
        </tr>
    </table>
    ';
}

// Scriem blocul de semnÄƒturi Ã®n PDF
$pdf->writeHTML($semnHTML, true, false, true, false, '');

// (15) Output
$pdf->Output('nir_'.$nr_nir.'.pdf', 'I');

// End output buffering and flush
ob_end_flush();
?>

