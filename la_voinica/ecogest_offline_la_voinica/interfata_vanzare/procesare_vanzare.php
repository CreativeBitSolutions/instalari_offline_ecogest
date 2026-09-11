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

// Verificări de bază pentru a preveni erorile
if (!$adm_id || !$nr_bon || !$cod_locatie) {
    die("Eroare: Sesiune invalidă sau date esențiale lipsesc. Vă rugăm să vă reconectați.");
}

// --- Acțiune de Deconectare ---
if (isset($_POST['deconectare'])) {
    header('Location: logout.php');
    exit();
}

/**
 * =================================================================================
 * --- FUNCȚIA PENTRU ÎNREGISTRAREA MIȘCĂRILOR (CU LOGICĂ RECURSIVĂ INTEGRATĂ) ---
 * =================================================================================
 * Această funcție conține logica de descărcare a stocurilor, inclusiv pentru
 * produsele finite care conțin alte produse finite (semi-fabricate).
 * Toate interogările folosesc prepared statements pentru securitate maximă.
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

    while ($row_prod_vandut = $misc_stmt->fetch(PDO::FETCH_ASSOC)) {
        $pret_unitar_produs_selectat = $row_prod_vandut['pret_achizitie'];
        $pret_vanzare_produs_selectat = $row_prod_vandut['pret_cu_tva'];
        $nume_produs = $row_prod_vandut['nume'];
        $cota_tva_produs_vandut = $row_prod_vandut['cota_tva'];
        $prod = $row_prod_vandut['cod_p'];
        $qt = $row_prod_vandut['cantitate'];
        $gest = $row_prod_vandut['denumire_gestiune'];

        if ($gest == 'PRODUSE FINITE') {
            // Logica complexă pentru produse finite
            $b_c_sql = "SELECT max(nr_doc) FROM $tabel_final_miscari WHERE fel_doc = 'BC'";
            $ultim_bc = ($pdo->query($b_c_sql)->fetchColumn() ?? 0) + 1;

            $reteta_sql = "SELECT cod_mat, cant_folos FROM $tabel_final_retete WHERE cod_p = :cod_p";
            $reteta_stmt = $pdo->prepare($reteta_sql);
            $reteta_stmt->execute(['cod_p' => $prod]);

            while ($row_reteta = $reteta_stmt->fetch(PDO::FETCH_ASSOC)) {
                $c_m = $row_reteta['cod_mat'];
                $qt_m = $row_reteta['cant_folos'] * $qt;

                // Obținem detaliile materialului
                $nomenclator_sql = "SELECT n.pret_achizitie, n.pret_cu_tva, g.denumire_gestiune, n.nume, n.cota_tva 
                                    FROM $tabel_final_nomenclator n
                                    INNER JOIN gestiuni g ON g.id_gestiune = n.id_gestiune 
                                    WHERE n.cod_produs = :cod_mat";
                $nomenclator_stmt = $pdo->prepare($nomenclator_sql);
                $nomenclator_stmt->execute(['cod_mat' => $c_m]);
                $nomenclator_row = $nomenclator_stmt->fetch(PDO::FETCH_ASSOC);

                if ($nomenclator_row) {
                    $pret_unitar_mat = $nomenclator_row['pret_achizitie'];
                    $pret_vanzare_mat = $nomenclator_row['pret_cu_tva'];
                    $gestiune_mat = $nomenclator_row['denumire_gestiune'];
                    $cota_tva_mat = $nomenclator_row['cota_tva'];
                    $nume_mat = $nomenclator_row['nume'];

                    if ($gestiune_mat == 'PRODUSE FINITE') {
                        // ######################################################################
                        // ### START LOGICĂ INTEGRATĂ PENTRU SUB-REȚETE (SEMI-FABRICATE) ###
                        // ######################################################################
                        
                        // Acest material este un produs finit intermediar (semi-fabricat).
                        // Trebuie să descărcăm rețeta proprie.
                        $reteta_sub_sql = "SELECT cod_mat, cant_folos FROM $tabel_final_retete WHERE cod_p = :cod_p";
                        $reteta_sub_stmt = $pdo->prepare($reteta_sub_sql);
                        $reteta_sub_stmt->execute(['cod_p' => $c_m]);

                        while ($sub_row = $reteta_sub_stmt->fetch(PDO::FETCH_ASSOC)) {
                            $sub_cod_mat = $sub_row['cod_mat'];
                            $sub_qt_total = $sub_row['cant_folos'] * $qt_m;

                            $nomenclator_stmt->execute(['cod_mat' => $sub_cod_mat]);
                            $sub_nomenclator_row = $nomenclator_stmt->fetch(PDO::FETCH_ASSOC);

                            if ($sub_nomenclator_row) {
                                $sub_pret_unitar = $sub_nomenclator_row['pret_achizitie'];
                                $sub_pret_vanzare = $sub_nomenclator_row['pret_cu_tva'];
                                $sub_gestiune = $sub_nomenclator_row['denumire_gestiune'];
                                $sub_cota_tva = $sub_nomenclator_row['cota_tva'];
                                $sub_nume = $sub_nomenclator_row['nume'];

                                // Verificăm dacă sub-materialul este ȘI el un produs finit (sub-sub-rețetă)
                                if ($sub_gestiune == 'PRODUSE FINITE') {
                                    $reteta_sub_sub_stmt = $pdo->prepare($reteta_sub_sql); // Reutilizăm statement-ul
                                    $reteta_sub_sub_stmt->execute(['cod_p' => $sub_cod_mat]);

                                    while ($sub_sub_row = $reteta_sub_sub_stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $sub_sub_cod_mat = $sub_sub_row['cod_mat'];
                                        $sub_sub_qt_total = $sub_sub_row['cant_folos'] * $sub_qt_total;

                                        $nomenclator_stmt->execute(['cod_mat' => $sub_sub_cod_mat]);
                                        $sub_sub_nomenclator_row = $nomenclator_stmt->fetch(PDO::FETCH_ASSOC);

                                        if ($sub_sub_nomenclator_row) {
                                            // Inserăm consumul (BC) pentru materia primă a sub-sub-produsului
                                            $sql_ins_misc = "INSERT INTO $tabel_final_miscari (data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota, produs_obtinut, pu, pret_vanzare, gestiune, denumire_produs, cota_tva, cod_locatie , ora_miscarii) VALUES (:data, :cod_p, :cant, 'O', 'BC', :nr_doc, :nr_nota, :prod_obtinut, :pu, :pv, :gest, :nume, :tva, :loc, :ora)";
                                            $pdo->prepare($sql_ins_misc)->execute([
                                                'data' => $data_bon, 'cod_p' => $sub_sub_cod_mat, 'cant' => $sub_sub_qt_total, 'nr_doc' => $ultim_bc, 'nr_nota' => $nr_bon,
                                                'prod_obtinut' => $sub_cod_mat,
                                                'pu' => $sub_sub_nomenclator_row['pret_achizitie'], 'pv' => $sub_sub_nomenclator_row['pret_cu_tva'],
                                                'gest' => $sub_sub_nomenclator_row['denumire_gestiune'], 'nume' => $sub_sub_nomenclator_row['nume'], 'tva' => $sub_sub_nomenclator_row['cota_tva'],
                                                'loc' => $cod_locatie, 'ora' => $ora_bon
                                            ]);
                                        }
                                    }

                                    // Înregistrăm producția (BT) și consumul (BC) pentru sub-produs
                                    $ultim_bt_sub = ($pdo->query("SELECT max(nr_doc) FROM $tabel_final_miscari WHERE fel_doc = 'BT'")->fetchColumn() ?? 0) + 1;
                                    $sql_ins_bt_sub = "INSERT INTO $tabel_final_miscari (data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota, pu, pret_vanzare, gestiune, denumire_produs, cota_tva, cod_locatie , ora_miscarii) VALUES (:data, :cod_p, :cant, 'I', 'BT', :nr_doc, :nr_nota, :pu, :pv, :gest, :nume, :tva, :loc, :ora)";
                                    $pdo->prepare($sql_ins_bt_sub)->execute([
                                        'data' => $data_bon, 'cod_p' => $sub_cod_mat, 'cant' => $sub_qt_total, 'nr_doc' => $ultim_bt_sub, 'nr_nota' => $nr_bon,
                                        'pu' => $sub_pret_unitar, 'pv' => $sub_pret_vanzare, 'gest' => 'SEMIFABRICATE', 'nume' => $sub_nume, 'tva' => $sub_cota_tva,
                                        'loc' => $cod_locatie, 'ora' => $ora_bon
                                    ]);

                                    $sql_ins_bc_sub = "INSERT INTO $tabel_final_miscari (data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota, produs_obtinut, pu, pret_vanzare, gestiune, denumire_produs, cota_tva, cod_locatie , ora_miscarii) VALUES (:data, :cod_p, :cant, 'O', 'BC', :nr_doc, :nr_nota, :prod_obtinut, :pu, :pv, :gest, :nume, :tva, :loc, :ora)";
                                    $pdo->prepare($sql_ins_bc_sub)->execute([
                                        'data' => $data_bon, 'cod_p' => $sub_cod_mat, 'cant' => $sub_qt_total, 'nr_doc' => $ultim_bc, 'nr_nota' => $nr_bon,
                                        'prod_obtinut' => $c_m,
                                        'pu' => $sub_pret_unitar, 'pv' => $sub_pret_vanzare, 'gest' => 'SEMIFABRICATE', 'nume' => $sub_nume, 'tva' => $sub_cota_tva,
                                        'loc' => $cod_locatie, 'ora' => $ora_bon
                                    ]);

                                } else {
                                    // Sub-materialul este materie primă, se inserează direct consumul
                                    $sql_ins_misc = "INSERT INTO $tabel_final_miscari (data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota, produs_obtinut, pu, pret_vanzare, gestiune, denumire_produs, cota_tva, cod_locatie , ora_miscarii) VALUES (:data, :cod_p, :cant, 'O', 'BC', :nr_doc, :nr_nota, :prod_obtinut, :pu, :pv, :gest, :nume, :tva, :loc, :ora)";
                                    $pdo->prepare($sql_ins_misc)->execute([
                                        'data' => $data_bon, 'cod_p' => $sub_cod_mat, 'cant' => $sub_qt_total, 'nr_doc' => $ultim_bc, 'nr_nota' => $nr_bon,
                                        'prod_obtinut' => $c_m,
                                        'pu' => $sub_pret_unitar, 'pv' => $sub_pret_vanzare, 'gest' => $sub_gestiune, 'nume' => $sub_nume, 'tva' => $sub_cota_tva,
                                        'loc' => $cod_locatie, 'ora' => $ora_bon
                                    ]);
                                }
                            }
                        }

                        // Înregistrăm producția (BT) și consumul (BC) pentru materialul intermediar principal ($c_m)
                        $ultim_bt_interm = ($pdo->query("SELECT max(nr_doc) FROM $tabel_final_miscari WHERE fel_doc = 'BT'")->fetchColumn() ?? 0) + 1;
                        $sql_ins_bt_interm = "INSERT INTO $tabel_final_miscari (data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota, pu, pret_vanzare, gestiune, denumire_produs, cota_tva, cod_locatie , ora_miscarii) VALUES (:data, :cod_p, :cant, 'I', 'BT', :nr_doc, :nr_nota, :pu, :pv, :gest, :nume, :tva, :loc, :ora)";
                        $pdo->prepare($sql_ins_bt_interm)->execute([
                            'data' => $data_bon, 'cod_p' => $c_m, 'cant' => $qt_m, 'nr_doc' => $ultim_bt_interm, 'nr_nota' => $nr_bon,
                            'pu' => $pret_unitar_mat, 'pv' => $pret_vanzare_mat, 'gest' => 'SEMIFABRICATE', 'nume' => $nume_mat, 'tva' => $cota_tva_mat,
                            'loc' => $cod_locatie, 'ora' => $ora_bon
                        ]);

                        $sql_ins_bc_interm = "INSERT INTO $tabel_final_miscari (data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota, produs_obtinut, pu, pret_vanzare, gestiune, denumire_produs, cota_tva, cod_locatie , ora_miscarii) VALUES (:data, :cod_p, :cant, 'O', 'BC', :nr_doc, :nr_nota, :prod_obtinut, :pu, :pv, :gest, :nume, :tva, :loc, :ora)";
                        $pdo->prepare($sql_ins_bc_interm)->execute([
                            'data' => $data_bon, 'cod_p' => $c_m, 'cant' => $qt_m, 'nr_doc' => $ultim_bc, 'nr_nota' => $nr_bon,
                            'prod_obtinut' => $prod,
                            'pu' => $pret_unitar_mat, 'pv' => $pret_vanzare_mat, 'gest' => 'SEMIFABRICATE', 'nume' => $nume_mat, 'tva' => $cota_tva_mat,
                            'loc' => $cod_locatie, 'ora' => $ora_bon
                        ]);
                        // ####################################################################
                        // ### END LOGICĂ INTEGRATĂ PENTRU SUB-REȚETE (SEMI-FABRICATE) ###
                        // ####################################################################

                    } else {
                        // Dacă materia nu este produs finit, se inserează direct consumul (BC)
                        $sql_ins_misc = "INSERT INTO $tabel_final_miscari (data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota, produs_obtinut, pu, pret_vanzare, gestiune, denumire_produs, cota_tva, cod_locatie , ora_miscarii)
                                         VALUES (:data, :cod_p, :cant, 'O', 'BC', :nr_doc, :nr_nota, :prod_obtinut, :pu, :pv, :gest, :nume, :tva, :loc, :ora)";
                        $stmt_ins_misc = $pdo->prepare($sql_ins_misc);
                        $stmt_ins_misc->execute([
                            'data' => $data_bon, 'cod_p' => $c_m, 'cant' => $qt_m, 'nr_doc' => $ultim_bc, 'nr_nota' => $nr_bon,
                            'prod_obtinut' => $prod, 'pu' => $pret_unitar_mat, 'pv' => $pret_vanzare_mat, 'gest' => $gestiune_mat,
                            'nume' => $nume_mat, 'tva' => $cota_tva_mat,
                            'loc' => $cod_locatie, 'ora' => $ora_bon
                        ]);
                    }
                }
            }

            // Se inserează mișcarea de intrare (BT) și ieșire (BF) pentru produsul finit FINAL
            $bt_sql = "SELECT max(nr_doc) FROM $tabel_final_miscari WHERE fel_doc = 'BT'";
            $ultim_bt = ($pdo->query($bt_sql)->fetchColumn() ?? 0) + 1;
            
            $sql_ins_bt = "INSERT INTO $tabel_final_miscari (data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota, pu, pret_vanzare, gestiune, denumire_produs, cota_tva, cod_locatie , ora_miscarii)
                           VALUES (:data, :cod_p, :cant, 'I', 'BT', :nr_doc, :nr_nota, :pu, :pv, :gest, :nume, :tva, :loc, :ora)";
            $stmt_ins_bt = $pdo->prepare($sql_ins_bt);
            $stmt_ins_bt->execute([
                'data' => $data_bon, 'cod_p' => $prod, 'cant' => $qt, 'nr_doc' => $ultim_bt, 'nr_nota' => $nr_bon, 'pu' => $pret_unitar_produs_selectat,
                'pv' => $pret_vanzare_produs_selectat, 'gest' => $gest, 'nume' => $nume_produs, 'tva' => $cota_tva_produs_vandut,
                'loc' => $cod_locatie, 'ora' => $ora_bon
            ]);

            $sql_ins_bf = "INSERT INTO $tabel_final_miscari (data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, pu, pret_vanzare, gestiune, denumire_produs, cota_tva, cod_locatie , ora_miscarii)
                           VALUES (:data, :cod_p, :cant, 'O', 'BF', :nr_nota, :pu, :pv, :gest, :nume, :tva, :loc, :ora)";
            $stmt_ins_bf = $pdo->prepare($sql_ins_bf);
            $stmt_ins_bf->execute([
                'data' => $data_bon, 'cod_p' => $prod, 'cant' => $qt, 'nr_nota' => $nr_bon, 'pu' => $pret_unitar_produs_selectat,
                'pv' => $pret_vanzare_produs_selectat, 'gest' => $gest, 'nume' => $nume_produs, 'tva' => $cota_tva_produs_vandut,
                'loc' => $cod_locatie, 'ora' => $ora_bon
            ]);

        } else {
            // Produsul este marfă, se face o singură ieșire pe bon fiscal (BF)
            $sql_iesire_marfa = "INSERT INTO $tabel_final_miscari (data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, pu, pret_vanzare, gestiune, denumire_produs, cota_tva, cod_locatie , ora_miscarii) 
                                 VALUES (:data, :cod_p, :cant, 'O', 'BF', :nr_bon, :pu, :pv, :gest, :nume, :tva, :loc, :ora)";
            $stmt_iesire_marfa = $pdo->prepare($sql_iesire_marfa);
            $stmt_iesire_marfa->execute([
                'data' => $data_bon, 'cod_p' => $prod, 'cant' => $qt, 'nr_bon' => $nr_bon, 'pu' => $pret_unitar_produs_selectat,
                'pv' => $pret_vanzare_produs_selectat, 'gest' => $gest, 'nume' => $nume_produs, 'tva' => $cota_tva_produs_vandut,
                'loc' => $cod_locatie, 'ora' => $ora_bon
            ]);
        }
    }

    // Curățarea finală, conform logicii originale
    // Șterge mișcările de consum pentru semi-fabricatele care au fost create și consumate în același bon
    $curat_sql = "DELETE FROM $tabel_final_miscari WHERE produs_obtinut != 0 AND gestiune = 'PRODUSE FINITE' AND fel_doc = 'BC' AND nr_nota = :nr_bon";
    $curat_stmt = $pdo->prepare($curat_sql);
    $curat_stmt->execute(['nr_bon' => $nr_bon]);
}
 
// =================================================================================
// --- ÎNCEPUTUL SCRIPTULUI PRINCIPAL ---
// =================================================================================

// --- Acțiune de Închidere Tură ---
if (isset($_POST['inchidere_zi'])) {
    try {
        $cod_inchidere_curenta = offline_close_shift($pdo, $tabel_final_note, $tabel_final_inchideri_r, (int)$cod_locatie, (int)$adm_id, $data_bon, $ora_bon);
        $_SESSION['cod_inchidere'] = $cod_inchidere_curenta;
        $_SESSION['offline_shift_closed'] = $cod_inchidere_curenta;
        header('Location: vanzare_magazin.php?tura_inchisa=' . rawurlencode((string)$cod_inchidere_curenta));
        exit();
    } catch (Throwable $e) {
        die("Eroare la închiderea turei: " . $e->getMessage());
    }
}

// --- Acțiune de Finalizare Bon ---
if (isset($_POST['finaliz_bon'])) {
    
    // Pas 1: Calculează totalurile bonului
    $sql_totals = "SELECT SUM(valoare_vanzare_cu_tva) as total_val, SUM(discount) as total_disc, SUM(tva_col) as total_tva 
                   FROM $tabel_final_det_note WHERE nr_bon = :nr_bon";
    $stmt_totals = $pdo->prepare($sql_totals);
    $stmt_totals->execute(['nr_bon' => $nr_bon]);
    $totals = $stmt_totals->fetch(PDO::FETCH_ASSOC);
    $total_de_plata = ($totals['total_val'] ?? 0); // ramane totalul valoric fara discount, valoarea discountului nu trebuie sa afecteze totalul de plata pentru ca eu cand acord un discount modific direct pretul in det_note asa ca nu mai trebuie scazut total_disc

    // Pas 2: Pregătește datele pentru plată
    $tip_plata = $_POST['finaliz_bon'];
    $plata_numerar = 0.00;
    $plata_card = 0.00;
    $plata_protocol = 0.00;
    $plata_glovo=0;
    $cif_client = $_POST['cif_client'] ?? $_POST['cif_client_m'] ?? null;
    $_SESSION['cif_client'] = $cif_client; //stocam cif_client in sesiune pt ca ne va trebui mai incolo
    // === ADAUGAT: Stocam banii primiti in sesiune ===
    if (isset($_POST['baniprimiti']) && $_POST['baniprimiti'] != "") {
        $_SESSION['baniprimiti'] = $_POST['baniprimiti'];
    } else {
        // Fallback: daca nu vine nimic, consideram ca a dat fix suma totala
        $_SESSION['baniprimiti'] = $total_de_plata;
    }
    // ===============================================
      $client_agecs = $_SESSION['client_id']; 
if ($client_agecs == 16) {
    $redirect_url = 'casa_marcat_vanzare_cu_rest.php';

} elseif ($client_agecs == 20 ) {
    $redirect_url = 'casa_marcat_vanzare_dep_personalizat.php';

} else {
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
        $offlineFinalSaleIdentity = offline_raport_z_current_identification($pdo, (int)$cod_locatie);
        $offlineFinalSaleNui = (int)$offlineFinalSaleIdentity['nui'];
        $offlineFinalSaleMemory = (string)$offlineFinalSaleIdentity['serie_memorie_fiscala'];

        // Pas 3: Actualizează nota de vânzare
        // --- START MODIFICARE ---
        // Pas 3: Actualizează nota de vânzare (am adăugat coloana `glovo`)
        $sql_update_nota = "UPDATE $tabel_final_note SET
                                status = 'F', data_bon = :data, ora_bon = :ora, valoare_vanzare_cu_tva = :val,
                                discount = :disc, tva_colectata = :tva, numerar = :num, card = :card,
                                protocol = :prot, glovo = :glovo, cif_client = :cif,
                                nui = CASE WHEN COALESCE(nui, 0) = 0 THEN :nui ELSE nui END,
                                serie_memorie_fiscala = CASE WHEN COALESCE(serie_memorie_fiscala, '') = '' THEN :serie_memorie_fiscala ELSE serie_memorie_fiscala END
                            WHERE nrbon = :nr_bon";
        $stmt_update_nota = $pdo->prepare($sql_update_nota);
        $stmt_update_nota->execute([
            'data' => $data_bon, 'ora' => $ora_bon, 'val' => $total_de_plata, 'disc' => $totals['total_disc'] ?? 0,
            'tva' => $totals['total_tva'] ?? 0, 'num' => $plata_numerar, 'card' => $plata_card,
            'prot' => $plata_protocol, 'glovo' => $plata_glovo, 'cif' => $cif_client,
            'nui' => $offlineFinalSaleNui, 'serie_memorie_fiscala' => $offlineFinalSaleMemory, 'nr_bon' => $nr_bon
        ]);
        // --- END MODIFICARE ---

        // Pas 4: Apelează funcția care înregistrează mișcările de stoc
        inregistreazaMiscariVanzare($pdo, $nr_bon, $data_bon);
        offline_sequence_record($pdo, 'nrbon', (int)$nr_bon, (int)$cod_locatie, '', 'sale_finalized', 'note', (string)$nr_bon);
        offline_sync_enqueue_sale_safely($pdo, (int)$nr_bon);

        $pdo->commit();
        
        // Pas 5: Redirecționează
        if (($_SESSION['mod_listare'] ?? '') == 'complex' && $redirect_url != 'listeaza_nota_fin.php') {
            $redirect_url = 'dwred_restaurant_cu_listare.php';
        }
        header('Location: ' . $redirect_url);
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
