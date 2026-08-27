<?php //procesare_vanzare.php
include('session.php');

// --- Setări inițiale ---
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.log');
error_reporting(E_ALL);
date_default_timezone_set("Europe/Bucharest");

// --- Constante și variabile esențiale ---
define('PAGINA_VANZARE', 'vanzare_magazin.php');
$adm_id = $_SESSION['admin_id'] ?? null;
$nr_bon = $_SESSION['nr_bon'] ?? null;
$cod_locatie = $_SESSION['cod_locatie'] ?? null;
$data_bon = date("Y-m-d");
$ora_bon = date("H:i:s");

// Verificări esențiale
if (!$adm_id || !$nr_bon || !$cod_locatie) {
    error_log("Lipsesc date esențiale: adm_id=$adm_id, nr_bon=$nr_bon, cod_locatie=$cod_locatie");
    header('Location: ' . PAGINA_VANZARE);
    exit();
}

// --- Tabele finale (nume generic, pot fi prefixate în session/config) ---
$tabel_final_note          = $_SESSION['tabel_final_note']          ?? 'note';
$tabel_final_det_note      = $_SESSION['tabel_final_det_note']      ?? 'det_note';
$tabel_final_miscari       = $_SESSION['tabel_final_miscari']       ?? 'miscari';
$tabel_final_retete        = $_SESSION['tabel_final_retete']        ?? 'retete';
$tabel_final_inchideri     = 'inchideri_r_12';
$tabel_final_date_firma    = $_SESSION['tabel_final_date_firma']    ?? 'date_firma';

// =====================================================================
// FUNCȚIE AJUTĂTOARE: calculează totalurile de pe bon din det_note
// =====================================================================
function calculeazaTotalBon(PDO $pdo, $tabel_final_det_note, $nr_bon) {
    $sql = "SELECT 
                COALESCE(SUM(valoare_vanzare_cu_tva), 0) AS total_val,
                COALESCE(SUM(tva_col), 0) AS total_tva,
                COALESCE(SUM(discount), 0) AS total_disc
            FROM $tabel_final_det_note
            WHERE nr_bon = :nr_bon";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['nr_bon' => $nr_bon]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total_val' => 0, 'total_tva' => 0, 'total_disc' => 0];
}

// =====================================================================
// FUNCȚIE AJUTĂTOARE: înregistrează mișcările de stoc pentru vânzare
// =====================================================================
/**
 * inregistreazaMiscariVanzare
 * - Generează mișcări BC/BT/BF în funcție de tipul produselor și de rețete.
 * - Suportă produse compuse, semi-fabricate și marfă simplă.
 * - Face descărcare de stoc atât pentru materii prime, cât și pentru
 *   produsele finite care conțin alte produse finite (semi-fabricate).
 * - Toate interogările folosesc prepared statements pentru securitate maximă.
 */
function inregistreazaMiscariVanzare($pdo, $nr_bon, $data_bon) {
    // Folosim variabilele globale pentru numele tabelelor
    global $tabel_final_nomenclator, $tabel_final_det_note, $tabel_final_retete, $tabel_final_miscari;
    global $cod_locatie, $ora_bon;

    $misc_sql = "SELECT 
                    n.pret_achizitie, n.pret_cu_tva, n.cota_tva, n.nume, 
                    g.denumire_gestiune, d.cod_p, d.cantitate 
                   FROM $tabel_final_det_note d
                   INNER JOIN $tabel_final_nomenclator n ON d.cod_p = n.cod_produs
                   INNER JOIN gestiuni g ON n.id_gestiune = g.id_gestiune
                   WHERE d.nr_bon = :nr_bon";
    $misc_stmt = $pdo->prepare($misc_sql);
    $misc_stmt->execute(['nr_bon' => $nr_bon]);

    while ($row = $misc_stmt->fetch(PDO::FETCH_ASSOC)) {
        $prod = $row['cod_p'];
        $qt   = $row['cantitate'];

        $pret_unitar_produs_selectat = $row['pret_achizitie'];
        $pret_vanzare_produs_selectat = $row['pret_cu_tva'];
        $cota_tva_produs_vandut = $row['cota_tva'];
        $nume_produs = $row['nume'];
        $gest = $row['denumire_gestiune'];

        // Verificăm dacă produsul are rețetă (produs finit)
        $sql_reteta = "SELECT cod_mat, cant_folos FROM $tabel_final_retete WHERE cod_p = :cod_p";
        $stmt_reteta = $pdo->prepare($sql_reteta);
        $stmt_reteta->execute(['cod_p' => $prod]);

        if ($stmt_reteta->rowCount() > 0) {
            // Produs finit cu rețetă: descărcăm materiile prime
            while ($reteta_row = $stmt_reteta->fetch(PDO::FETCH_ASSOC)) {
                $cod_mat = $reteta_row['cod_mat'];
                $qt_total = $reteta_row['cant_folos'] * $qt;

                // Preluăm detalii materie primă
                $sql_nomenclator = "SELECT pret_achizitie, pret_cu_tva, cota_tva, nume, 
                                           g.denumire_gestiune
                                    FROM $tabel_final_nomenclator n
                                    INNER JOIN gestiuni g ON n.id_gestiune = g.id_gestiune
                                    WHERE n.cod_produs = :cod_mat";
                $nomenclator_stmt = $pdo->prepare($sql_nomenclator);
                $nomenclator_stmt->execute(['cod_mat' => $cod_mat]);
                $nomenclator_row = $nomenclator_stmt->fetch(PDO::FETCH_ASSOC);

                if ($nomenclator_row) {
                    $pret_unitar_mat = $nomenclator_row['pret_achizitie'];
                    $pret_vanzare_mat = $nomenclator_row['pret_cu_tva'];
                    $cota_tva_mat = $nomenclator_row['cota_tva'];
                    $nume_mat = $nomenclator_row['nume'];
                    $gestiune_mat = $nomenclator_row['denumire_gestiune'];

                    // Verificăm dacă materia primă este la rândul ei produs finit (semi-fabricat)
                    $stmt_is_sub = $pdo->prepare("SELECT COUNT(*) FROM $tabel_final_retete WHERE cod_p = :cod_p");
                    $stmt_is_sub->execute(['cod_p' => $cod_mat]);
                    $is_subproduct = $stmt_is_sub->fetchColumn() > 0;

                    if ($is_subproduct) {
                        // Materia este produs finit intermediar (semi-fabricat)
                        // Se inserează mișcare de tip BT (Bon Transfer) și apoi rețeta lui se descarcă separat
                  $sql_ins_bt = "INSERT INTO $tabel_final_miscari (
                    data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota,
                    pu, pret_vanzare, valoare_achizitie, valoare_vanzare,
                    diminueaza_pe, nr_nir, id_doc, gestiune,
                    denumire_produs, cota_tva, cod_locatie , ora_miscarii,
                    produs_obtinut, nume_produs_obtinut, ramas, id_achiz,
                    id_vanz_fact, id_rand_bon_consum_manual, id_rand_bon_consum_productie,
                    id_rand_proces_verbal_inventar, id_retur, id_rand_pv_deteriorare, nr_raport_z
               ) VALUES (
                    :data, :cod_p, :cant, 'O', 'BT', :nr_doc, :nr_nota,
                    :pu, :pv, :val_ach, :val_vanz,
                    'S', 0, 0, :gest,
                    :nume, :tva, :loc, :ora,
                    0, '', 0, 0,
                    0, 0, 0,
                    0, 0, 0, 0
               )";




                        $ultim_bt = 0;
                        $stmt_bt_nr = $pdo->query("SELECT COALESCE(MAX(nr_doc), 0) FROM $tabel_final_miscari WHERE fel_doc='BT'");
                        $ultim_bt = (int)$stmt_bt_nr->fetchColumn() + 1;

                        $stmt_ins_bt = $pdo->prepare($sql_ins_bt);
                        $stmt_ins_bt->execute([
                            'data' => $data_bon, 'cod_p' => $cod_mat, 'cant' => $qt_total,
                            'nr_doc' => $ultim_bt, 'nr_nota' => $nr_bon,
                            'pu' => $pret_unitar_mat, 'pv' => $pret_vanzare_mat,
                            'val_ach' => $pret_unitar_mat * $qt_total,
                            'val_vanz' => $pret_vanzare_mat * $qt_total,
                            'gest' => $gestiune_mat, 'nume' => $nume_mat, 'tva' => $cota_tva_mat,
                            'loc' => $cod_locatie, 'ora' => $ora_bon
                        ]);

                        // Recursiv: descărcăm rețeta semi-fabricatului
                        $reteta_sub_sql = "SELECT cod_mat, cant_folos FROM $tabel_final_retete WHERE cod_p = :cod_p";
                        $reteta_sub_stmt = $pdo->prepare($reteta_sub_sql);
                        $reteta_sub_stmt->execute(['cod_p' => $cod_mat]);

                        while ($sub_row = $reteta_sub_stmt->fetch(PDO::FETCH_ASSOC)) {
                            $sub_cod_mat = $sub_row['cod_mat'];
                            $sub_qt_total = $sub_row['cant_folos'] * $qt_total;

                            $nomenclator_stmt->execute(['cod_mat' => $sub_cod_mat]);
                            $sub_nomenclator_row = $nomenclator_stmt->fetch(PDO::FETCH_ASSOC);

                            if ($sub_nomenclator_row) {
                                $sub_pret_unitar = $sub_nomenclator_row['pret_achizitie'];
                                $sub_pret_vanzare = $sub_nomenclator_row['pret_cu_tva'];
                                $sub_cota_tva = $sub_nomenclator_row['cota_tva'];
                                $sub_nume = $sub_nomenclator_row['nume'];
                                $sub_gestiune = $sub_nomenclator_row['denumire_gestiune'];

                        $sql_ins_bt_sub = "INSERT INTO $tabel_final_miscari (
                        data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota,
                        pu, pret_vanzare, valoare_achizitie, valoare_vanzare,
                        diminueaza_pe, nr_nir, id_doc, gestiune,
                        denumire_produs, cota_tva, cod_locatie , ora_miscarii,
                        produs_obtinut, nume_produs_obtinut, ramas, id_achiz,
                        id_vanz_fact, id_rand_bon_consum_manual, id_rand_bon_consum_productie,
                        id_rand_proces_verbal_inventar, id_retur, id_rand_pv_deteriorare, nr_raport_z
                   ) VALUES (
                        :data, :cod_p, :cant, 'O', 'BT', :nr_doc, :nr_nota,
                        :pu, :pv, :val_ach, :val_vanz,
                        'S', 0, 0, :gest,
                        :nume, :tva, :loc, :ora,
                        0, '', 0, 0,
                        0, 0, 0,
                        0, 0, 0, 0
                   )";




                                $stmt_ins_bt_sub = $pdo->prepare($sql_ins_bt_sub);
                                $ultim_bt_sub = $ultim_bt; // legăm de același BT

                                $stmt_ins_bt_sub->execute([
                                    'data' => $data_bon, 'cod_p' => $sub_cod_mat, 'cant' => $sub_qt_total,
                                    'nr_doc' => $ultim_bt_sub, 'nr_nota' => $nr_bon,
                                    'pu' => $sub_pret_unitar, 'pv' => $sub_pret_vanzare,
                                    'val_ach' => $sub_pret_unitar * $sub_qt_total,
                                    'val_vanz' => $sub_pret_vanzare * $sub_qt_total,
                                    'gest' => $sub_gestiune, 'nume' => $sub_nume, 'tva' => $sub_cota_tva,
                                    'loc' => $cod_locatie, 'ora' => $ora_bon
                                ]);
                            }
                        }

                    } else {
                        // Materie primă simplă: BC (Bon Consum)
                     $sql_ins_misc = "INSERT INTO $tabel_final_miscari (
                    data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota,
                    pu, pret_vanzare, valoare_achizitie, valoare_vanzare,
                    diminueaza_pe, nr_nir, id_doc, gestiune,
                    denumire_produs, cota_tva, cod_locatie , ora_miscarii,
                    produs_obtinut, nume_produs_obtinut, ramas, id_achiz,
                    id_vanz_fact, id_rand_bon_consum_manual, id_rand_bon_consum_productie,
                    id_rand_proces_verbal_inventar, id_retur, id_rand_pv_deteriorare, nr_raport_z
                 )
                 VALUES (
                    :data, :cod_p, :cant, 'O', 'BC', :nr_doc, :nr_nota,
                    :pu, :pv, :val_ach, :val_vanz,
                    'S', 0, 0, :gest,
                    :nume, :tva, :loc, :ora,
                    0, '', 0, 0,
                    0, 0, 0,
                    0, 0, 0, 0
                 )";





                        $stmt_ultim_bc = $pdo->query("SELECT COALESCE(MAX(nr_doc), 0) FROM $tabel_final_miscari WHERE fel_doc='BC'");
                        $ultim_bc = (int)$stmt_ultim_bc->fetchColumn() + 1;

                        $stmt_ins_misc = $pdo->prepare($sql_ins_misc);
                        $stmt_ins_misc->execute([
                            'data' => $data_bon, 'cod_p' => $cod_mat, 'cant' => $qt_total,
                            'nr_doc' => $ultim_bc, 'nr_nota' => $nr_bon,
                            'pu' => $pret_unitar_mat, 'pv' => $pret_vanzare_mat,
                            'val_ach' => $pret_unitar_mat * $qt_total,
                            'val_vanz' => $pret_vanzare_mat * $qt_total,
                            'gest' => $gestiune_mat, 'nume' => $nume_mat, 'tva' => $cota_tva_mat,
                            'loc' => $cod_locatie, 'ora' => $ora_bon
                        ]);
                    }
                }
            }

            // Înregistrăm și produsul finit în BF (Bon Fiscal) ca ieșire din gestiune (vânzare)
         $sql_ins_bf = "INSERT INTO $tabel_final_miscari (
                    data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota,
                    pu, pret_vanzare, valoare_achizitie, valoare_vanzare,
                    diminueaza_pe, nr_nir, id_doc, gestiune,
                    denumire_produs, cota_tva, cod_locatie , ora_miscarii,
                    produs_obtinut, nume_produs_obtinut, ramas, id_achiz,
                    id_vanz_fact, id_rand_bon_consum_manual, id_rand_bon_consum_productie,
                    id_rand_proces_verbal_inventar, id_retur, id_rand_pv_deteriorare, nr_raport_z
               )
               VALUES (
                    :data, :cod_p, :cant, 'O', 'BF', :nr_doc, :nr_nota,
                    :pu, :pv, :val_ach, :val_vanz,
                    'S', 0, 0, :gest,
                    :nume, :tva, :loc, :ora,
                    0, '', 0, 0,
                    0, 0, 0,
                    0, 0, 0, 0
               )";





            $stmt_ultim_bf = $pdo->query("SELECT COALESCE(MAX(nr_doc), 0) FROM $tabel_final_miscari WHERE fel_doc='BF'");
            $ultim_bf = (int)$stmt_ultim_bf->fetchColumn() + 1;

            $stmt_ins_bf = $pdo->prepare($sql_ins_bf);
            $stmt_ins_bf->execute([
                'data' => $data_bon, 'cod_p' => $prod, 'cant' => $qt,
                'nr_doc' => $ultim_bf, 'nr_nota' => $nr_bon,
                'pu' => $pret_unitar_produs_selectat,
                'pv' => $pret_vanzare_produs_selectat,
                'val_ach' => $pret_unitar_produs_selectat * $qt,
                'val_vanz' => $pret_vanzare_produs_selectat * $qt,
                'gest' => $gest, 'nume' => $nume_produs, 'tva' => $cota_tva_produs_vandut,
                'loc' => $cod_locatie, 'ora' => $ora_bon
            ]);

        } else {
            // Produsul NU are rețetă => este marfă simplă
            // Produsul este marfă, se face o singură ieșire pe bon fiscal (BF)
        $sql_iesire_marfa = "INSERT INTO $tabel_final_miscari (
                          data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota,
                          pu, pret_vanzare, valoare_achizitie, valoare_vanzare,
                          diminueaza_pe, nr_nir, id_doc, gestiune,
                          denumire_produs, cota_tva, cod_locatie , ora_miscarii,
                          produs_obtinut, nume_produs_obtinut, ramas, id_achiz,
                          id_vanz_fact, id_rand_bon_consum_manual, id_rand_bon_consum_productie,
                          id_rand_proces_verbal_inventar, id_retur, id_rand_pv_deteriorare, nr_raport_z
                     ) 
                     VALUES (
                          :data, :cod_p, :cant, 'O', 'BF', :nr_bon, :nr_nota,
                          :pu, :pv, :val_ach, :val_vanz,
                          'S', 0, 0, :gest,
                          :nume, :tva, :loc, :ora,
                          0, '', 0, 0,
                          0, 0, 0,
                          0, 0, 0, 0
                     )";




            $stmt_iesire_marfa = $pdo->prepare($sql_iesire_marfa);
            $stmt_iesire_marfa->execute([
                'data' => $data_bon, 'cod_p' => $prod, 'cant' => $qt,
                'nr_bon' => $nr_bon, 'nr_nota' => $nr_bon,
                'pu' => $pret_unitar_produs_selectat,
                'pv' => $pret_vanzare_produs_selectat,
                'val_ach' => $pret_unitar_produs_selectat * $qt,
                'val_vanz' => $pret_vanzare_produs_selectat * $qt,
                'gest' => $gest, 'nume' => $nume_produs, 'tva' => $cota_tva_produs_vandut,
                'loc' => $cod_locatie, 'ora' => $ora_bon
            ]);
        }
    }

    // Curățarea finală, conform logicii originale
    // Șterge mișcările de consum pentru semi-fabricatele care au fost create și consumate în același bon
    $curat_sql = "DELETE FROM $tabel_final_miscari WHERE produs_obtinut IN (
                      SELECT cod_p FROM $tabel_final_det_note WHERE nr_bon = :nr_bon
                  ) AND tip_miscare = 'I' AND fel_doc IN ('BT', 'BC') AND nr_nota = :nr_bon";
    $curat_stmt = $pdo->prepare($curat_sql);
    $curat_stmt->execute(['nr_bon' => $nr_bon]);
}
 
// =================================================================================
// --- ÎNCEPUTUL SCRIPTULUI PRINCIPAL ---
// =================================================================================

// --- Acțiune de Închidere Tură ---
if (isset($_POST['inchidere_zi'])) {
    try {
        $cod_inchidere_curenta = offline_close_shift($pdo, $tabel_final_note, $tabel_final_inchideri, (int)$cod_locatie, (int)$adm_id, $data_bon, $ora_bon);
        $_SESSION['cod_inchidere'] = $cod_inchidere_curenta;
        $_SESSION['offline_shift_closed'] = $cod_inchidere_curenta;
        header('Location: vanzare_magazin.php?tura_inchisa=' . rawurlencode((string)$cod_inchidere_curenta));
        exit();

    } catch (Throwable $e) {
        error_log("Eroare la închiderea de tură: " . $e->getMessage());
        die("Eroare la închiderea de tură: " . $e->getMessage());
    }
}

// --- Acțiune de Deconectare ---
if (isset($_POST['deconectare'])) {
    $adminloggedin = $_SESSION['adminloggedin'] ?? null;
    $client_id     = $_SESSION['client_id']     ?? null;
    $cod_locatie   = $_SESSION['cod_locatie']   ?? null;
    session_unset();
    if ($adminloggedin !== null) $_SESSION['adminloggedin'] = $adminloggedin;
    if ($client_id     !== null) $_SESSION['client_id']     = $client_id;
    if ($cod_locatie   !== null) $_SESSION['cod_locatie']   = $cod_locatie;
    header('Location: agecs_login.php');
    exit();
}

// --- Acțiune de Finalizare Bon ---
if (isset($_POST['finaliz_bon'])) {
    // Pas 1: calculează totalurile reale din det_note
    $totals = calculeazaTotalBon($pdo, $tabel_final_det_note, $nr_bon);
    $total_de_plata = (float)($totals['total_val'] ?? 0);

    // Pas 2: determină tipul de plată și sumele
    $tip_plata = $_POST['finaliz_bon'];
    $plata_numerar = 0.00;
    $plata_card = 0.00;
    $plata_protocol = 0.00;
    $plata_glovo = 0.00;
    $cif_client = $_POST['cif_client'] ?? $_POST['cif_client_m'] ?? null;
    $_SESSION['cif_client'] = $cif_client; //stocam cif_client in sesiune pt ca ne va trebui mai incolo
      $client_agecs = $_SESSION['client_id']; 
if ($client_agecs == 20 || $client_agecs == 8) {
        $redirect_url = 'casa_marcat_vanzare_dep_personalizat.php';

 }
 else{
    $redirect_url = 'casa_marcat_vanzare.php';

 }
    switch ($tip_plata) {
        case 'numerar':
            $plata_numerar = $total_de_plata;
            $_SESSION['numerarprim']=$total_de_plata;
            break;
        case 'card':
            $plata_card = $total_de_plata;
                        $_SESSION['cardprim']=$total_de_plata;

            break;
        case 'protocol':
            $plata_protocol = $total_de_plata;
                        $_SESSION['protocol']=$total_de_plata;

            $redirect_url = 'listeaza_nota_fin.php';
            break;
             // --- START MODIFICARE ---
        case 'glovo': // Am adăugat cazul pentru plata online/glovo
            $plata_glovo = $total_de_plata;
            break;
        // --- END MODIFICARE ---
        case 'numerar_si_card':
            $plata_numerar = $_POST['numerar'] ?? 0;
                        $_SESSION['numerarprim']=$total_de_plata;

            $plata_card = $_POST['card'] ?? 0;
                        $_SESSION['cardprim']=$total_de_plata;

            break;
    }
    
    try {
        $pdo->beginTransaction();

        // Pas 3: Actualizează nota de vânzare
        // --- START MODIFICARE ---
        // Pas 3: Actualizează nota de vânzare (am adăugat coloana `glovo`)
        $sql_update_nota = "UPDATE $tabel_final_note SET
                                status = 'F', data_bon = :data, ora_bon = :ora, valoare_vanzare_cu_tva = :val,
                                discount = :disc, tva_colectata = :tva, numerar = :num, card = :card,
                                protocol = :prot, glovo = :glovo, cif_client = :cif
                            WHERE nrbon = :nr_bon";
        $stmt_update_nota = $pdo->prepare($sql_update_nota);
        $stmt_update_nota->execute([
            'data' => $data_bon, 'ora' => $ora_bon, 'val' => $total_de_plata, 'disc' => $totals['total_disc'] ?? 0,
            'tva' => $totals['total_tva'] ?? 0, 'num' => $plata_numerar, 'card' => $plata_card,
            'prot' => $plata_protocol, 'glovo' => $plata_glovo, 'cif' => $cif_client, 'nr_bon' => $nr_bon
        ]);
        // --- END MODIFICARE ---

        // Pas 4: Înregistrare mișcări de stoc pentru vânzare
        inregistreazaMiscariVanzare($pdo, $nr_bon, $data_bon);
        offline_sequence_record($pdo, 'nrbon', (int)$nr_bon, (int)$cod_locatie, '', 'sale_finalized', 'note', (string)$nr_bon);
        offline_sync_enqueue_sale_safely($pdo, (int)$nr_bon);

        // Pas 5: Commit și redirect către casa de marcat / listare
        $pdo->commit();

        if ($tip_plata === 'protocol') {
            // pentru protocol se listează altfel
            header('Location: ' . $redirect_url . '?nr_bon=' . $nr_bon);
        } else {
            header('Location: ' . $redirect_url);
        }
        exit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Eroare la finalizarea bonului ($nr_bon): " . $e->getMessage());
        die("Eroare la finalizarea bonului: " . $e->getMessage());
    }
}

// Fallback: dacă nici-o acțiune nu este recunoscută, revino la pagina de vânzare
header('Location: ' . PAGINA_VANZARE);
exit();
?>
