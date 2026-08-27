<?php
// Start output buffering to prevent the "already output" error
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'db.php';
require_once __DIR__ . '/tcpdf_bootstrap.php';

// (3) Preluăm nr_nir din GET
// Preluăm nr_nir, id_gestiune și cota_tva din GET:
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
        a.tva_total_unitar
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

// (6) Inițializăm TCPDF
$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Autorul tau');
$pdf->SetTitle('Nota de receptie si constatare de diferente');
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 20);
$pdf->AddPage();

// (7) Titlul principal
$pdf->SetFont('dejavusans', 'B', 14);
$pdf->Cell(0, 10, 'NOTA DE RECEPTIE SI CONSTATARE DE DIFERENTE', 0, 1, 'C');
$pdf->Ln(2);

// Linie orizontală sub titlu
$pdf->SetDrawColor(0, 0, 0);
$pdf->Line(10, $pdf->GetY(), 287, $pdf->GetY());
$pdf->Ln(2);

// (8) Informații antet - date firmă / furnizor / NIR
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

// (9) Definim 16 coloane, astfel încât să ne încadrăm în max. 277 mm
$cols = [
    'denumire_produs'               => 38, // (Col 1)
    'unitate_masura'                => 10, // (Col 2)
    'cantitate'                     => 12, // (Col 3)
    'pret_unitar_achizitie'         => 15, // (Col 4)
    'valoare_achizitie'             => 15, // (Col 5)
    'tva_unitar_achizitie'          => 15, // (Col 6)
    'valoare_tva_achizitie'         => 15, // (Col 7)
    'valoare_achizitie_cu_tva'      => 20, // (Col 8)
    'procent_adaos'                 => 12, // (Col 9)
    'adaos_unitar'                  => 15, // (Col 10)
    'valoare_adaos'                 => 15, // (Col 11)
    'pret_cu_adaos_unitar_fara_tva' => 20, // (Col 12)
    'tva_adaos_comercial'           => 15, // (Col 13)
    'pret_unitar_cu_amanuntul_cu_tva'=> 20, // (Col 14)
    'valoare_pret_amanunt'          => 20, // (Col 15)
    'tva_total_unitar'              => 15, // (Col 16)
];

// Calculăm lățimea totală
$total_width = array_sum($cols);
if ($total_width > 277) {
    die("Tabelul depășește lățimea disponibilă (277 mm). Ajustează lățimile coloanelor.");
}

// Construim un tabel HTML cu 2 rânduri de antet
$html = '<table border="1" cellpadding="3" cellspacing="0" style="font-size:8px;">';

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
$html .= '<tr style="background-color:#EEEEEE; font-size:7px;">';
$html .= '<td align="center">1</td>';                                       // col1
$html .= '<td align="center">2</td>';                                          // col2
$html .= '<td align="center">3</td>';                                       // col3
$html .= '<td align="center">4</td>';                                          // col4
$html .= '<td align="center">5= 3*4</td>';                                      // col5
$html .= '<td align="center">6= Valoarea TVA<br>pentru o unitate</td>';     // col6
$html .= '<td align="center">7= 3*6</td>';                                      // col7
$html .= '<td align="center">8= 5+7</td>';                                      // col8
$html .= '<td align="center">9</td>';                                          // col9
$html .= '<td align="center">10= 4*9/100</td>';                                 // col10
$html .= '<td align="center">11= 3*10</td>';                                     // col11
$html .= '<td align="center">12= 4+10</td>';                                     // col12
$html .= '<td align="center">13= 10 * Cota TVA</td>';                           // col13
$html .= '<td align="center">14=4+6+10+13</td>';                                 // col14
$html .= '<td align="center">15= 3*14</td>';                                     // col15
$html .= '<td align="center">16= 6+13</td>';                                     // col16
$html .= '</tr>';
$html .= '</thead>';
// ---- Rânduri cu datele efective din `achizitii` ----
$html .= '<tbody>';

// --------------------------------------------------------------------------------------
// AICI MODIFICĂM LOGICA DE CALCUL PE GESTIUNI, CĂUTÂND DUPĂ denumire_produs ÎN produse_servicii
// --------------------------------------------------------------------------------------

// Pregătim un statement pentru a identifica gestiunea pe baza denumirii produsului
// (Notă: nu se mai preia cota TVA din tabela produse_servicii, ci se va folosi valoarea din achizitii.cota_tva)
$sqlGetGestiune = "
    SELECT 
        g.denumire_gestiune
    FROM produse_servicii p
    JOIN gestiuni g ON p.id_gestiune = g.id_gestiune
    WHERE p.cod_produs = :codul_produsului
    LIMIT 1
    ";
$stmtGestiune = $pdo->prepare($sqlGetGestiune);

// Pregătim variabilele pentru sume:
$sumMateriiPrime = 0.0;
$sumMaterialeConsumabile = 0.0;
// Adăugăm variabile pentru materii prime pe cote TVA:
$sumMateriiPrime5 = 0.0;
$sumMateriiPrime9 = 0.0;
$sumMateriiPrime11 = 0.0; // MODIFICARE: Adăugat
$sumMateriiPrime19 = 0.0;
$sumMateriiPrime21 = 0.0; // MODIFICARE: Adăugat
$sumMateriiPrime0 = 0.0;
    
// Sume pentru marfuri (achiziție)
$sumMarfuriAchTot = 0.0;
$sumMarf5Ach = 0.0;
$sumMarf9Ach = 0.0;
$sumMarf11Ach = 0.0; // MODIFICARE: Adăugat
$sumMarf19Ach = 0.0;
$sumMarf21Ach = 0.0; // MODIFICARE: Adăugat
$sumMarf0Ach = 0.0;
    
// Sume pentru marfuri (vânzare) => bazat pe `valoare_pret_amanunt`
$sumMarfuriVanzTot = 0.0;
$sumMarf5Vanz = 0.0;
$sumMarf9Vanz = 0.0;
$sumMarf11Vanz = 0.0; // MODIFICARE: Adăugat
$sumMarf19Vanz = 0.0;
$sumMarf21Vanz = 0.0; // MODIFICARE: Adăugat
$sumMarf0Vanz = 0.0;

// --- ADĂUGARE: Inițializăm agregarea generală pentru toate gestiunile ---
$sumGestiuniAll = [];

foreach ($rowsAchiz as $row) {
    
    // Construim rândul
    $html .= '<tr style="font-size:7px;">';
    // 1: denumire_produs
    $html .= '<td width="'.$cols['denumire_produs'].'mm" align="left">'.htmlspecialchars($row['denumire_produs']).'</td>';
    // 2: unitate_masura
    $html .= '<td width="'.$cols['unitate_masura'].'mm" align="center">'.htmlspecialchars($row['unitate_masura']).'</td>';
    // 3: cantitate
    $html .= '<td width="'.$cols['cantitate'].'mm" align="right">'.number_format($row['cantitate'], 2, ',', '.').'</td>';
    // 4: pret_unitar_achizitie
    $html .= '<td width="'.$cols['pret_unitar_achizitie'].'mm" align="right">'.number_format($row['pret_unitar_achizitie'], 3, ',', '.').' ('.htmlspecialchars($row['cota_tva']).'%)</td>';
    // 5: valoare_achizitie
    $html .= '<td width="'.$cols['valoare_achizitie'].'mm" align="right">'.number_format($row['valoare_achizitie'], 2, ',', '.').'</td>';
    // 6: tva_unitar_achizitie
    $html .= '<td width="'.$cols['tva_unitar_achizitie'].'mm" align="right">'.number_format($row['tva_unitar_achizitie'], 2, ',', '.').'</td>';
    // 7: valoare_tva_achizitie
    $html .= '<td width="'.$cols['valoare_tva_achizitie'].'mm" align="right">'.number_format($row['valoare_tva_achizitie'], 2, ',', '.').'</td>';
    // 8: valoare_achizitie_cu_tva
    $html .= '<td width="'.$cols['valoare_achizitie_cu_tva'].'mm" align="right">'.number_format($row['valoare_achizitie_cu_tva'], 2, ',', '.').'</td>';
    // 9: procent_adaos
    $html .= '<td width="'.$cols['procent_adaos'].'mm" align="right">'.number_format($row['procent_adaos'], 2, ',', '.').'</td>';
    // 10: adaos_unitar
    $html .= '<td width="'.$cols['adaos_unitar'].'mm" align="right">'.number_format($row['adaos_unitar'], 2, ',', '.').'</td>';
    // 11: valoare_adaos
    $html .= '<td width="'.$cols['valoare_adaos'].'mm" align="right">'.number_format($row['valoare_adaos'], 2, ',', '.').'</td>';
    // 12: pret_cu_adaos_unitar_fara_tva
    $html .= '<td width="'.$cols['pret_cu_adaos_unitar_fara_tva'].'mm" align="right">'.number_format($row['pret_cu_adaos_unitar_fara_tva'], 2, ',', '.').'</td>';
    // 13: tva_adaos_comercial
    $html .= '<td width="'.$cols['tva_adaos_comercial'].'mm" align="right">'.number_format($row['tva_adaos_comercial'], 2, ',', '.').'</td>';
    // 14: pret_unitar_cu_amanuntul_cu_tva
    $html .= '<td width="'.$cols['pret_unitar_cu_amanuntul_cu_tva'].'mm" align="right">'.number_format($row['pret_unitar_cu_amanuntul_cu_tva'], 2, ',', '.').'</td>';
    // 15: valoare_pret_amanunt
    $html .= '<td width="'.$cols['valoare_pret_amanunt'].'mm" align="right">'.number_format($row['valoare_pret_amanunt'], 2, ',', '.').'</td>';
    // 16: tva_total_unitar
    $html .= '<td width="'.$cols['tva_total_unitar'].'mm" align="right">'.number_format($row['tva_total_unitar'], 2, ',', '.').'</td>';
    $html .= '</tr>';

    // ---- Determinăm gestiunea efectivă pe baza denumirii produsului ----

    // Căutăm în tabela produse_servicii:
    $stmtGestiune->execute([':codul_produsului' => $row['cod_p']]);
    $rowGes = $stmtGestiune->fetch(PDO::FETCH_ASSOC);
    if (!$rowGes) {
        // Dacă nu s-a găsit, îl ignorăm sau îl considerăm "NECUNOSCUT"
        continue;
    }
    $denumireGestiune = strtoupper($rowGes['denumire_gestiune'] ?? '');
    // Se preia cota TVA din achizitii (nu din produse_servicii)
    $cotaTvaProdus    = (int)$row['cota_tva']; 

    // Valorile de achiziție și vânzare
    $valAch   = (float)$row['valoare_achizitie'];      // Achiziție
    $valVanz  = (float)$row['valoare_pret_amanunt'];   // Vânzare

    // Se face insumarea pe bază de gestiune + cota tva
    if (strpos($denumireGestiune, 'MATERII') !== false) {
        $sumMateriiPrime += $valAch;
        // Adăugăm și defalcarea pe cote TVA pentru materii prime:
        if ($cotaTvaProdus === 5) {
            $sumMateriiPrime5 += $valAch;
        } elseif ($cotaTvaProdus === 9) {
            $sumMateriiPrime9 += $valAch;
        } elseif ($cotaTvaProdus === 11) { // MODIFICARE: Adăugat
            $sumMateriiPrime11 += $valAch;
        } elseif ($cotaTvaProdus === 19) {
            $sumMateriiPrime19 += $valAch;
        } elseif ($cotaTvaProdus === 21) { // MODIFICARE: Adăugat
            $sumMateriiPrime21 += $valAch;
        } else {
            $sumMateriiPrime0 += $valAch;
        }
    }
    elseif (strpos($denumireGestiune, 'CONSUM') !== false || strpos($denumireGestiune, 'CONSUMABILE') !== false) {
        $sumMaterialeConsumabile += $valAch;
    }
    elseif (strpos($denumireGestiune, 'MARF') !== false) {
        // Este marfă, deci adunăm la total marfuri + pe cote
        $sumMarfuriAchTot += $valAch;
        $sumMarfuriVanzTot += $valVanz;

        if ($cotaTvaProdus === 5) {
            $sumMarf5Ach += $valAch;
            $sumMarf5Vanz += $valVanz;
        } elseif ($cotaTvaProdus === 9) {
            $sumMarf9Ach += $valAch;
            $sumMarf9Vanz += $valVanz;
        } elseif ($cotaTvaProdus === 11) { // MODIFICARE: Adăugat
            $sumMarf11Ach += $valAch;
            $sumMarf11Vanz += $valVanz;
        } elseif ($cotaTvaProdus === 19) {
            $sumMarf19Ach += $valAch;
            $sumMarf19Vanz += $valVanz;
        } elseif ($cotaTvaProdus === 21) { // MODIFICARE: Adăugat
            $sumMarf21Ach += $valAch;
            $sumMarf21Vanz += $valVanz;
        } else {
            // presupunem cota 0
            $sumMarf0Ach += $valAch;
            $sumMarf0Vanz += $valVanz;
        }
    }
    // la fel poți adăuga logică pt. SGR dacă vrei

    // --- ADĂUGARE: Agregăm suma pentru orice gestiune (inclusiv SGR și altele)
    if (!isset($sumGestiuniAll[$denumireGestiune])) {
        $sumGestiuniAll[$denumireGestiune] = ['achizitie' => 0.0, 'vanzare' => 0.0];
    }
    $sumGestiuniAll[$denumireGestiune]['achizitie'] += $valAch;
    $sumGestiuniAll[$denumireGestiune]['vanzare'] += $valVanz;
}
$html .= '</tbody></table>';

// (10) Scriem HTML-ul în PDF
$pdf->writeHTML($html, true, false, true, false, '');
// (11) Afișăm noile totaluri pe gestiuni și pe cota TVA
$pdf->Ln(1);
$pdf->SetFont('dejavusans', 'B', 9);
$pdf->Cell(0, 5, 'Totaluri pe gestiuni (în funcție de cota TVA de vânzare):', 0, 1, 'L');

$pdf->SetFont('dejavusans', '', 8);

// Exemplu: Materii prime, Consumabile
if ($sumMateriiPrime != 0) {
    $pdf->Cell(0, 5, 'Gestiune totala materii prime: ' . number_format($sumMateriiPrime, 2, ',', '.') . ' lei', 0, 1, 'L');
    // Afișăm și defalcarea pe cote TVA pentru materii prime:
    if ($sumMateriiPrime5 != 0) {
        $pdf->Cell(0, 5, 'Gestiune materii prime 5%: ' . number_format($sumMateriiPrime5, 2, ',', '.') . ' lei', 0, 1, 'L');
    }
    if ($sumMateriiPrime9 != 0) {
        $pdf->Cell(0, 5, 'Gestiune materii prime 9%: ' . number_format($sumMateriiPrime9, 2, ',', '.') . ' lei', 0, 1, 'L');
    }
    // MODIFICARE: Afișare pentru 11%
    if ($sumMateriiPrime11 != 0) {
        $pdf->Cell(0, 5, 'Gestiune materii prime 11%: ' . number_format($sumMateriiPrime11, 2, ',', '.') . ' lei', 0, 1, 'L');
    }
    if ($sumMateriiPrime19 != 0) {
        $pdf->Cell(0, 5, 'Gestiune materii prime 19%: ' . number_format($sumMateriiPrime19, 2, ',', '.') . ' lei', 0, 1, 'L');
    }
    // MODIFICARE: Afișare pentru 21%
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

// Marfă (achiziție) totală
if ($sumMarfuriAchTot != 0) {
    $pdf->Cell(0, 5, 'Gestiune totala marfuri (achizitie): ' . number_format($sumMarfuriAchTot, 2, ',', '.') . ' lei', 0, 1, 'L');
}
if ($sumMarf5Ach != 0) {
    $pdf->Cell(0, 5, 'Gestiune marfuri 5% (valoare achizitie): ' . number_format($sumMarf5Ach, 2, ',', '.') . ' lei', 0, 1, 'L');
}
if ($sumMarf9Ach != 0) {
    $pdf->Cell(0, 5, 'Gestiune marfuri 9% (valoare achizitie): ' . number_format($sumMarf9Ach, 2, ',', '.') . ' lei', 0, 1, 'L');
}
// MODIFICARE: Afișare pentru 11%
if ($sumMarf11Ach != 0) {
    $pdf->Cell(0, 5, 'Gestiune marfuri 11% (valoare achizitie): ' . number_format($sumMarf11Ach, 2, ',', '.') . ' lei', 0, 1, 'L');
}
if ($sumMarf19Ach != 0) {
    $pdf->Cell(0, 5, 'Gestiune marfuri 19% (valoare achizitie): ' . number_format($sumMarf19Ach, 2, ',', '.') . ' lei', 0, 1, 'L');
}
// MODIFICARE: Afișare pentru 21%
if ($sumMarf21Ach != 0) {
    $pdf->Cell(0, 5, 'Gestiune marfuri 21% (valoare achizitie): ' . number_format($sumMarf21Ach, 2, ',', '.') . ' lei', 0, 1, 'L');
}
if ($sumMarf0Ach != 0) {
    $pdf->Cell(0, 5, 'Gestiune marfuri 0% (valoare achizitie): ' . number_format($sumMarf0Ach, 2, ',', '.') . ' lei', 0, 1, 'L');
}

// Marfă (vânzare) totală
if ($sumMarfuriVanzTot != 0) {
    $pdf->Cell(0, 5, 'Gestiune totala marfuri (vanzare): ' . number_format($sumMarfuriVanzTot, 2, ',', '.') . ' lei', 0, 1, 'L');
}
if ($sumMarf5Vanz != 0) {
    $pdf->Cell(0, 5, 'Gestiune marfuri 5% (valoare vanzare): ' . number_format($sumMarf5Vanz, 2, ',', '.') . ' lei', 0, 1, 'L');
}
if ($sumMarf9Vanz != 0) {
    $pdf->Cell(0, 5, 'Gestiune marfuri 9% (valoare vanzare): ' . number_format($sumMarf9Vanz, 2, ',', '.') . ' lei', 0, 1, 'L');
}
// MODIFICARE: Afișare pentru 11%
if ($sumMarf11Vanz != 0) {
    $pdf->Cell(0, 5, 'Gestiune marfuri 11% (valoare vanzare): ' . number_format($sumMarf11Vanz, 2, ',', '.') . ' lei', 0, 1, 'L');
}
if ($sumMarf19Vanz != 0) {
    $pdf->Cell(0, 5, 'Gestiune marfuri 19% (valoare vanzare): ' . number_format($sumMarf19Vanz, 2, ',', '.') . ' lei', 0, 1, 'L');
}
// MODIFICARE: Afișare pentru 21%
if ($sumMarf21Vanz != 0) {
    $pdf->Cell(0, 5, 'Gestiune marfuri 21% (valoare vanzare): ' . number_format($sumMarf21Vanz, 2, ',', '.') . ' lei', 0, 1, 'L');
}
if ($sumMarf0Vanz != 0) {
    $pdf->Cell(0, 5, 'Gestiune marfuri 0% (valoare vanzare): ' . number_format($sumMarf0Vanz, 2, ',', '.') . ' lei', 0, 1, 'L');
}

// --- ADĂUGARE: Afișăm totalurile pentru toate gestiunile care nu au fost deja afișate mai sus ---
$pdf->Ln(3);
$pdf->SetFont('dejavusans', 'B', 9);
$alteGestiuni = [];

foreach ($sumGestiuniAll as $gestiune => $sums) {
    // Dacă gestiunea a fost deja afișată (ex.: MATERII, CONSUM, MARFURI), o omităm aici
    if (strpos($gestiune, 'MATERII') !== false || strpos($gestiune, 'CONSUM') !== false || strpos($gestiune, 'MARF') !== false) {
        continue;
    }

    // Verificăm dacă sumele sunt diferite de zero
    if ($sums['achizitie'] != 0 || $sums['vanzare'] != 0) {
        $alteGestiuni[$gestiune] = $sums;
    }
}

if (!empty($alteGestiuni)) {
    $pdf->Cell(0, 5, 'Totaluri pe alte gestiuni:', 0, 1, 'L');
    $pdf->SetFont('dejavusans', '', 8);

    foreach ($alteGestiuni as $gestiune => $sums) {
        $pdf->Cell(0, 5, 'Gestiune ' . $gestiune . ' - Achizitie: ' . number_format($sums['achizitie'], 2, ',', '.') . ' lei, Vanzare: ' . number_format($sums['vanzare'], 2, ',', '.') . ' lei', 0, 1, 'L');
    }
}
// Pornim sesiunea, dacă nu este deja pornită
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$client_agecs = isset($_SESSION['client_id']) ? $_SESSION['client_id'] : null;

// (12) Observații - afișăm secțiunea doar dacă clientul nu are ID-ul 6 sau 2
if ($client_agecs != 6 && $client_agecs != 2) {
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

$client_agecs = isset($_SESSION['client_id']) ? $_SESSION['client_id'] : null;

if ($client_agecs == 6 || $client_agecs == 2) {
    // Dacă clientul are ID-ul 6, folosim blocul alternativ cu 3 coloane (fără borduri)
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
    // Dacă clientul nu are ID-ul 6, păstrăm blocul inițial cu 4 celule și borduri
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

// Scriem blocul de semnături în PDF
$pdf->writeHTML($semnHTML, true, false, true, false, '');

// (15) Output
$pdf->Output('nir_'.$nr_nir.'.pdf', 'I');

// End output buffering and flush
ob_end_flush();
?>