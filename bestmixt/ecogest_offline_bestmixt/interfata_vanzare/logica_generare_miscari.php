<?php
// logica_generare_miscari.php
// Acest fișier conține logica centralizată pentru generarea mișcărilor de stoc,
// atât pentru notele fiscale (bonuri), cât și pentru facturi.
// Funcțiile de aici sunt apelate de scripturile de reparație.

/**
 * Generează mișcările de stoc pentru un bon fiscal dat (note.nrbon).
 * Replică 100% logica din funcția originală inregistreazaMiscariVanzare.
 *
 * @param PDO $pdo Obiectul conexiunii la baza de date.
 * @param int $nr_bon Numărul bonului fiscal de procesat.
 * @throws Exception Dacă apar erori în timpul procesării.
 */
function generareMiscarePentruNota($pdo, $nr_bon) {
    // Definirea numelor de tabele pentru a face funcția autonomă
    $tabel_final_nomenclator = 'produse_servicii';
    $tabel_final_det_note = 'det_note';
    $tabel_final_retete = 'retete';
    $tabel_final_miscari = 'miscari';
    
    // Preluăm data bonului, esențială pentru înregistrările în mișcări
    $data_bon_sql = "SELECT data_bon FROM note WHERE nrbon = :nr_bon";
    $data_stmt = $pdo->prepare($data_bon_sql);
    $data_stmt->execute(['nr_bon' => $nr_bon]);
    $data_bon = $data_stmt->fetchColumn();
    if (!$data_bon) {
        throw new Exception("Data pentru bonul {$nr_bon} nu a fost găsită în tabela 'note'.");
    }
    
    $pdo->beginTransaction();
    try {
        // =================================================================================
        // --- START LOGICĂ REPLICATĂ 1:1 PENTRU NOTE ---
        // =================================================================================
      $misc_sql = "SELECT 
                n.pret_achizitie, 
                n.pret_cu_tva, 
                n.cota_tva, 
                n.nume, 
                g.denumire_gestiune, 
                d.cod_p, 
                d.cantitate,
                d.pret_vanzare AS pret_vanzare_det
            FROM {$tabel_final_det_note} d
            INNER JOIN {$tabel_final_nomenclator} n ON d.cod_p = n.cod_produs
            INNER JOIN gestiuni g ON n.id_gestiune = g.id_gestiune
            WHERE d.nr_bon = :nr_bon";

    
        $misc_stmt = $pdo->prepare($misc_sql);
        $misc_stmt->execute(['nr_bon' => $nr_bon]);
// Verificare serviciu pe baza nomenclatorului + gestiune
$psCheckSql  = "SELECT ps.tip, g.denumire_gestiune 
                FROM produse_servicii ps 
                INNER JOIN gestiuni g ON ps.id_gestiune = g.id_gestiune
                WHERE ps.cod_produs = :cod_produs
                LIMIT 1";
$psCheckStmt = $pdo->prepare($psCheckSql);

        while ($row_prod_vandut = $misc_stmt->fetch(PDO::FETCH_ASSOC)) {
            $pret_unitar_produs_selectat = $row_prod_vandut['pret_achizitie'];
            $pret_vanzare_produs_selectat = $row_prod_vandut['pret_cu_tva'];
            $nume_produs = $row_prod_vandut['nume'];
            $cota_tva_produs_vandut = $row_prod_vandut['cota_tva'];
            $prod = $row_prod_vandut['cod_p'];
            $qt = $row_prod_vandut['cantitate'];
            $gest = $row_prod_vandut['denumire_gestiune'];
// Dacă tip = 'serviciu' sau gestiunea conține 'SERVICII', folosim pretul din det_note
try {
    $psCheckStmt->execute([':cod_produs' => $prod]);
    $ps = $psCheckStmt->fetch(PDO::FETCH_ASSOC);

    if ($ps) {
        $tipPS  = strtolower(trim($ps['tip'] ?? ''));
        // Prefer gestiunea din query-ul separat; dacă lipsește, cădem pe $gest din SELECT-ul principal
        $gestPS = strtoupper(trim($ps['denumire_gestiune'] ?? $gest ?? ''));
        $esteServiciu = ($tipPS === 'serviciu') || (strpos($gestPS, 'SERVICII') !== false);

        if ($esteServiciu && isset($row_prod_vandut['pret_vanzare_det']) && $row_prod_vandut['pret_vanzare_det'] !== null) {
            $pret_vanzare_produs_selectat = (float)$row_prod_vandut['pret_vanzare_det'];
        }
    }
} catch (PDOException $e) {
    // optional: log/ignore – nu blocăm generarea mișcărilor
}

            if ($gest == 'PRODUSE FINITE') {
                $b_c_sql = "SELECT max(nr_doc) FROM {$tabel_final_miscari} WHERE fel_doc = 'BC'";
                $ultim_bc = ($pdo->query($b_c_sql)->fetchColumn() ?? 0) + 1;

                $reteta_sql = "SELECT cod_mat, cant_folos FROM {$tabel_final_retete} WHERE cod_p = :cod_p";
                $reteta_stmt = $pdo->prepare($reteta_sql);
                $reteta_stmt->execute(['cod_p' => $prod]);

                while ($row_reteta = $reteta_stmt->fetch(PDO::FETCH_ASSOC)) {
                    $c_m = $row_reteta['cod_mat'];
                    $qt_m = $row_reteta['cant_folos'] * $qt;

                    $nomenclator_sql = "SELECT n.pret_achizitie, n.pret_cu_tva, g.denumire_gestiune, n.nume, n.cota_tva 
                                        FROM {$tabel_final_nomenclator} n
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
                            $reteta_sub_sql = "SELECT cod_mat, cant_folos FROM {$tabel_final_retete} WHERE cod_p = :cod_p";
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

                                    if ($sub_gestiune == 'PRODUSE FINITE') {
                                        $reteta_sub_sub_stmt = $pdo->prepare($reteta_sub_sql);
                                        $reteta_sub_sub_stmt->execute(['cod_p' => $sub_cod_mat]);

                                        while ($sub_sub_row = $reteta_sub_sub_stmt->fetch(PDO::FETCH_ASSOC)) {
                                            $sub_sub_cod_mat = $sub_sub_row['cod_mat'];
                                            $sub_sub_qt_total = $sub_sub_row['cant_folos'] * $sub_qt_total;
                                            $nomenclator_stmt->execute(['cod_mat' => $sub_sub_cod_mat]);
                                            $sub_sub_nomenclator_row = $nomenclator_stmt->fetch(PDO::FETCH_ASSOC);
                                            if ($sub_sub_nomenclator_row) {
                                                $sql_ins_misc = "INSERT INTO {$tabel_final_miscari} (data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota, produs_obtinut, pu, pret_vanzare, gestiune, denumire_produs, cota_tva) VALUES (:data, :cod_p, :cant, 'O', 'BC', :nr_doc, :nr_nota, :prod_obtinut, :pu, :pv, :gest, :nume, :tva)";
                                                $pdo->prepare($sql_ins_misc)->execute(['data' => $data_bon, 'cod_p' => $sub_sub_cod_mat, 'cant' => $sub_sub_qt_total, 'nr_doc' => $ultim_bc, 'nr_nota' => $nr_bon, 'prod_obtinut' => $sub_cod_mat, 'pu' => $sub_sub_nomenclator_row['pret_achizitie'], 'pv' => $sub_sub_nomenclator_row['pret_cu_tva'], 'gest' => $sub_sub_nomenclator_row['denumire_gestiune'], 'nume' => $sub_sub_nomenclator_row['nume'], 'tva' => $sub_sub_nomenclator_row['cota_tva']]);
                                            }
                                        }
                                        $ultim_bt_sub = ($pdo->query("SELECT max(nr_doc) FROM {$tabel_final_miscari} WHERE fel_doc = 'BT'")->fetchColumn() ?? 0) + 1;
                                        $sql_ins_bt_sub = "INSERT INTO {$tabel_final_miscari} (data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota, pu, pret_vanzare, gestiune, denumire_produs, cota_tva) VALUES (:data, :cod_p, :cant, 'I', 'BT', :nr_doc, :nr_nota, :pu, :pv, :gest, :nume, :tva)";
                                        $pdo->prepare($sql_ins_bt_sub)->execute(['data' => $data_bon, 'cod_p' => $sub_cod_mat, 'cant' => $sub_qt_total, 'nr_doc' => $ultim_bt_sub, 'nr_nota' => $nr_bon, 'pu' => $sub_pret_unitar, 'pv' => $sub_pret_vanzare, 'gest' => 'SEMIFABRICATE', 'nume' => $sub_nume, 'tva' => $sub_cota_tva]);
                                        $sql_ins_bc_sub = "INSERT INTO {$tabel_final_miscari} (data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota, produs_obtinut, pu, pret_vanzare, gestiune, denumire_produs, cota_tva) VALUES (:data, :cod_p, :cant, 'O', 'BC', :nr_doc, :nr_nota, :prod_obtinut, :pu, :pv, :gest, :nume, :tva)";
                                        $pdo->prepare($sql_ins_bc_sub)->execute(['data' => $data_bon, 'cod_p' => $sub_cod_mat, 'cant' => $sub_qt_total, 'nr_doc' => $ultim_bc, 'nr_nota' => $nr_bon, 'prod_obtinut' => $c_m, 'pu' => $sub_pret_unitar, 'pv' => $sub_pret_vanzare, 'gest' => 'SEMIFABRICATE', 'nume' => $sub_nume, 'tva' => $sub_cota_tva]);
                                    } else {
                                        $sql_ins_misc = "INSERT INTO {$tabel_final_miscari} (data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota, produs_obtinut, pu, pret_vanzare, gestiune, denumire_produs, cota_tva) VALUES (:data, :cod_p, :cant, 'O', 'BC', :nr_doc, :nr_nota, :prod_obtinut, :pu, :pv, :gest, :nume, :tva)";
                                        $pdo->prepare($sql_ins_misc)->execute(['data' => $data_bon, 'cod_p' => $sub_cod_mat, 'cant' => $sub_qt_total, 'nr_doc' => $ultim_bc, 'nr_nota' => $nr_bon, 'prod_obtinut' => $c_m, 'pu' => $sub_pret_unitar, 'pv' => $sub_pret_vanzare, 'gest' => $sub_gestiune, 'nume' => $sub_nume, 'tva' => $sub_cota_tva]);
                                    }
                                }
                            }
                            $ultim_bt_interm = ($pdo->query("SELECT max(nr_doc) FROM {$tabel_final_miscari} WHERE fel_doc = 'BT'")->fetchColumn() ?? 0) + 1;
                            $sql_ins_bt_interm = "INSERT INTO {$tabel_final_miscari} (data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota, pu, pret_vanzare, gestiune, denumire_produs, cota_tva) VALUES (:data, :cod_p, :cant, 'I', 'BT', :nr_doc, :nr_nota, :pu, :pv, :gest, :nume, :tva)";
                            $pdo->prepare($sql_ins_bt_interm)->execute(['data' => $data_bon, 'cod_p' => $c_m, 'cant' => $qt_m, 'nr_doc' => $ultim_bt_interm, 'nr_nota' => $nr_bon, 'pu' => $pret_unitar_mat, 'pv' => $pret_vanzare_mat, 'gest' => 'SEMIFABRICATE', 'nume' => $nume_mat, 'tva' => $cota_tva_mat]);
                            $sql_ins_bc_interm = "INSERT INTO {$tabel_final_miscari} (data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota, produs_obtinut, pu, pret_vanzare, gestiune, denumire_produs, cota_tva) VALUES (:data, :cod_p, :cant, 'O', 'BC', :nr_doc, :nr_nota, :prod_obtinut, :pu, :pv, :gest, :nume, :tva)";
                            $pdo->prepare($sql_ins_bc_interm)->execute(['data' => $data_bon, 'cod_p' => $c_m, 'cant' => $qt_m, 'nr_doc' => $ultim_bc, 'nr_nota' => $nr_bon, 'prod_obtinut' => $prod, 'pu' => $pret_unitar_mat, 'pv' => $pret_vanzare_mat, 'gest' => 'SEMIFABRICATE', 'nume' => $nume_mat, 'tva' => $cota_tva_mat]);
                        } else {
                            $sql_ins_misc = "INSERT INTO {$tabel_final_miscari} (data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota, produs_obtinut, pu, pret_vanzare, gestiune, denumire_produs, cota_tva) VALUES (:data, :cod_p, :cant, 'O', 'BC', :nr_doc, :nr_nota, :prod_obtinut, :pu, :pv, :gest, :nume, :tva)";
                            $pdo->prepare($sql_ins_misc)->execute(['data' => $data_bon, 'cod_p' => $c_m, 'cant' => $qt_m, 'nr_doc' => $ultim_bc, 'nr_nota' => $nr_bon, 'prod_obtinut' => $prod, 'pu' => $pret_unitar_mat, 'pv' => $pret_vanzare_mat, 'gest' => $gestiune_mat, 'nume' => $nume_mat, 'tva' => $cota_tva_mat]);
                        }
                    }
                }
                $bt_sql = "SELECT max(nr_doc) FROM {$tabel_final_miscari} WHERE fel_doc = 'BT'";
                $ultim_bt = ($pdo->query($bt_sql)->fetchColumn() ?? 0) + 1;
                $sql_ins_bt = "INSERT INTO {$tabel_final_miscari} (data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota, pu, pret_vanzare, gestiune, denumire_produs, cota_tva) VALUES (:data, :cod_p, :cant, 'I', 'BT', :nr_doc, :nr_nota, :pu, :pv, :gest, :nume, :tva)";
                $pdo->prepare($sql_ins_bt)->execute(['data' => $data_bon, 'cod_p' => $prod, 'cant' => $qt, 'nr_doc' => $ultim_bt, 'nr_nota' => $nr_bon, 'pu' => $pret_unitar_produs_selectat, 'pv' => $pret_vanzare_produs_selectat, 'gest' => $gest, 'nume' => $nume_produs, 'tva' => $cota_tva_produs_vandut]);
                
                // Am păstrat logica originală unde `nr_doc` pentru BF era legat de `:nr_nota` ($nr_bon)
                // Am adăugat și `nr_nota` explicit pentru claritate.
                $sql_ins_bf = "INSERT INTO {$tabel_final_miscari} (data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota, pu, pret_vanzare, gestiune, denumire_produs, cota_tva) VALUES (:data, :cod_p, :cant, 'O', 'BF', :nr_doc, :nr_nota, :pu, :pv, :gest, :nume, :tva)";
                $pdo->prepare($sql_ins_bf)->execute(['data' => $data_bon, 'cod_p' => $prod, 'cant' => $qt, 'nr_doc' => $nr_bon, 'nr_nota' => $nr_bon, 'pu' => $pret_unitar_produs_selectat, 'pv' => $pret_vanzare_produs_selectat, 'gest' => $gest, 'nume' => $nume_produs, 'tva' => $cota_tva_produs_vandut]);
            } else {
                // Logica originală pentru marfă, unde nr_doc este nr_bon
                $sql_iesire_marfa = "INSERT INTO {$tabel_final_miscari} (data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota, pu, pret_vanzare, gestiune, denumire_produs, cota_tva) VALUES (:data, :cod_p, :cant, 'O', 'BF', :nr_doc, :nr_nota, :pu, :pv, :gest, :nume, :tva)";
                $pdo->prepare($sql_iesire_marfa)->execute(['data' => $data_bon, 'cod_p' => $prod, 'cant' => $qt, 'nr_doc' => $nr_bon, 'nr_nota' => $nr_bon, 'pu' => $pret_unitar_produs_selectat, 'pv' => $pret_vanzare_produs_selectat, 'gest' => $gest, 'nume' => $nume_produs, 'tva' => $cota_tva_produs_vandut]);
            }
        }
        $curat_sql = "DELETE FROM {$tabel_final_miscari} WHERE produs_obtinut != 0 AND gestiune = 'PRODUSE FINITE' AND fel_doc = 'BC' AND nr_nota = :nr_bon";
        $pdo->prepare($curat_sql)->execute(['nr_bon' => $nr_bon]);
        // =================================================================================
        // --- END LOGICĂ REPLICATĂ PENTRU NOTE ---
        // =================================================================================
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Generează mișcările de stoc pentru o factură dată (facturi.id_factura).
 * Replică 100% logica din scriptul original add_product.php, iterând pentru fiecare produs de pe factură.
 *
 * @param PDO $pdo Obiectul conexiunii la baza de date.
 * @param int $id_factura ID-ul facturii de procesat.
 * @throws Exception Dacă apar erori în timpul procesării.
 */
function generareMiscarePentruFactura($pdo, $id_factura) {
    $factura_sql = "SELECT nr_factura, serie_factura, tip_factura, data_factura FROM facturi WHERE id_factura = :id_factura";
    $factura_stmt = $pdo->prepare($factura_sql);
    $factura_stmt->execute(['id_factura' => $id_factura]);
    $factura = $factura_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$factura) {
        throw new Exception("Factura cu ID {$id_factura} nu a fost găsită.");
    }
    
    if (isset($factura['tip_factura']) && $factura['tip_factura'] == '751') {
        return; // Ieșim silențios, conform logicii originale
    }
    
    $vanzari_sql = "SELECT * FROM vanzari WHERE id_factura = :id_factura";
    $vanzari_stmt = $pdo->prepare($vanzari_sql);
    $vanzari_stmt->execute(['id_factura' => $id_factura]);
    $produse_vandute = $vanzari_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $pdo->beginTransaction();
    try {
        foreach ($produse_vandute as $produs) {
            $check_sql = "SELECT COUNT(*) FROM miscari WHERE id_vanz_fact = :id_vanz AND fel_doc = 'FAC'";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute(['id_vanz' => $produs['id_vanz']]);
            if ($check_stmt->fetchColumn() > 0) {
                continue; // Sărim peste acest produs, mișcarea există deja
            }

            // =================================================================================
            // --- START LOGICĂ REPLICATĂ 1:1 PENTRU FACTURI (din add_product.php) ---
            // =================================================================================
            $cod_p = $produs['cod_p'];
            $den_p = $produs['den_p'];
            $cantitate = $produs['cantitate'];
            $pret_cu_tva = $produs['pret_vanzare']; // În scriptul tău, prețul de vânzare din `vanzari` este folosit ca bază
            $ultim_id_vanz_inserat = $produs['id_vanz'];
            $data_bon = $factura['data_factura'];
            $nr_factura = $factura['nr_factura'];

            $sql_fetch_produs = "SELECT g.denumire_gestiune, ps.cota_tva FROM produse_servicii ps INNER JOIN gestiuni g ON ps.id_gestiune = g.id_gestiune WHERE ps.cod_produs = :cod_p";
            $stmt_fetch_produs = $pdo->prepare($sql_fetch_produs);
            $stmt_fetch_produs->execute(['cod_p' => $cod_p]);
            $produs_data = $stmt_fetch_produs->fetch(PDO::FETCH_ASSOC);

            if (!$produs_data) continue; // Dacă produsul nu e în nomenclator, sărim peste el

            $den_gestiune = $produs_data['denumire_gestiune'];
            $cota_tva_produs_vandut = $produs_data['cota_tva'];

            if ($den_gestiune == 'PRODUSE FINITE') {
                $reteta_sql = "SELECT cod_mat, cant_folos FROM retete WHERE cod_p = :cod_p";
                $reteta_stmt = $pdo->prepare($reteta_sql);
                $reteta_stmt->execute(['cod_p' => $cod_p]);
                $produse_servicii_sql = "SELECT pret_achizitie, pret_cu_tva, cota_tva, denumire_gestiune, nume FROM produse_servicii INNER JOIN gestiuni ON gestiuni.id_gestiune = produse_servicii.id_gestiune WHERE cod_produs = :cod_mat";
                $produse_servicii_stmt = $pdo->prepare($produse_servicii_sql);
                
                $b_c_sql = "SELECT max(nr_doc) as ultim_bc FROM miscari WHERE fel_doc = 'BC'";
                $ultim_bc = ($pdo->query($b_c_sql)->fetchColumn() ?? 0) + 1;

                while ($row = $reteta_stmt->fetch(PDO::FETCH_ASSOC)) {
                    $c_m = $row['cod_mat'];
                    $cant_folos_de_produs_finit = $row['cant_folos']; // Păstrăm această variabilă conform logicii tale
                    $qt_m = $row['cant_folos'] * $cantitate;
                    $produse_servicii_stmt->execute(['cod_mat' => $c_m]);
                    $produse_servicii_row = $produse_servicii_stmt->fetch(PDO::FETCH_ASSOC);

                    if ($produse_servicii_row) {
                        $pret_unitar = $produse_servicii_row['pret_achizitie'];
                        $pret_vanzare = $produse_servicii_row['pret_cu_tva'];
                        $cota_tva_material = $produse_servicii_row['cota_tva'];
                        $denumire_gestiune_material = $produse_servicii_row['denumire_gestiune'];
                        $nume_material = $produse_servicii_row['nume'];

                        if ($denumire_gestiune_material == 'PRODUSE FINITE') {
                             $reteta_sub_sql = "SELECT cod_mat, cant_folos FROM retete WHERE cod_p = :produs";
                             $reteta_sub_stmt = $pdo->prepare($reteta_sub_sql);
                             $reteta_sub_stmt->execute(['produs' => $c_m]);
                             while ($sub_row = $reteta_sub_stmt->fetch(PDO::FETCH_ASSOC)) {
                                 $sub_cod_mat = $sub_row['cod_mat'];
                                 $sub_qt = $sub_row['cant_folos'] * $qt_m;
                                 $produse_servicii_stmt->execute(['cod_mat' => $sub_cod_mat]);
                                 $sub_produse_servicii_row = $produse_servicii_stmt->fetch(PDO::FETCH_ASSOC);
                                 if ($sub_produse_servicii_row) {
                                     $sub_pret_unitar = $sub_produse_servicii_row['pret_achizitie'];
                                     $sub_pret_vanzare = $sub_produse_servicii_row['pret_cu_tva'];
                                     $sub_denumire_gestiune = $sub_produse_servicii_row['denumire_gestiune'];
                                     $nume_submaterial = $sub_produse_servicii_row['nume'];
                                     $cota_tva_submaterial = $sub_produse_servicii_row['cota_tva'];
                                     $iessql_sub = "INSERT INTO miscari (data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota, produs_obtinut, pu, pret_vanzare, gestiune, denumire_produs, id_vanz_fact, cota_tva) VALUES (:data, :cod_p, :cant, 'O', 'BC', :nr_doc, :nr_nota, :prod_obtinut, :pu, :pv, :gest, :nume, :id_vanz, :tva)";
                                     $pdo->prepare($iessql_sub)->execute([':data' => $data_bon,':cod_p' => $sub_cod_mat,':cant' => $sub_qt,':nr_doc' => $ultim_bc,':nr_nota' => $nr_factura,':prod_obtinut' => $c_m,':pu' => $sub_pret_unitar,':pv' => $sub_pret_vanzare,':gest' => $sub_denumire_gestiune,':nume' => $nume_submaterial,':id_vanz' => $ultim_id_vanz_inserat,':tva' => $cota_tva_submaterial]);
                                 }
                             }
                             $b_c_sql_bt_sub = "SELECT max(nr_doc) as ultim_bt FROM miscari WHERE fel_doc='BT'";
                             $ultim_bt_sub = ($pdo->query($b_c_sql_bt_sub)->fetchColumn() ?? 0) + 1;
                             $bt_sql_sub = "INSERT INTO miscari(data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota, pu, pret_vanzare, gestiune, denumire_produs, id_vanz_fact, cota_tva) VALUES (:data, :cod_p, :cant, 'I', 'BT', :nr_doc, :nr_nota, :pu, :pv, 'SEMIFABRICATE', :nume, :id_vanz, :tva)";
                             $pdo->prepare($bt_sql_sub)->execute([':data' => $data_bon,':cod_p' => $c_m,':cant' => $qt_m,':nr_doc' => $ultim_bt_sub,':nr_nota' => $nr_factura,':pu' => $pret_unitar,':pv' => $pret_vanzare,':nume' => $nume_material,':id_vanz' => $ultim_id_vanz_inserat,':tva' => $cota_tva_material]);
                             $iesire_bc_produs_finit = "INSERT INTO miscari (data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota, produs_obtinut, pu, pret_vanzare, gestiune, denumire_produs, id_vanz_fact, cota_tva) VALUES (:data, :cod_p, :cant, 'O', 'BC', :nr_doc, :nr_nota, :prod_obtinut, :pu, :pv, 'SEMIFABRICATE', :nume, :id_vanz, :tva)";
                             $pdo->prepare($iesire_bc_produs_finit)->execute([':data' => $data_bon,':cod_p' => $c_m,':cant' => $qt_m,':nr_doc' => $ultim_bc,':nr_nota' => $nr_factura,':prod_obtinut' => $cod_p,':pu' => $pret_unitar,':pv' => $pret_vanzare,':nume' => $nume_material,':id_vanz' => $ultim_id_vanz_inserat,':tva' => $cota_tva_material]);
                        } else {
                            $iessql = "INSERT INTO miscari (data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota, produs_obtinut, pu, pret_vanzare, gestiune, denumire_produs, id_vanz_fact, cota_tva) VALUES (:data, :cod_p, :cant, 'O', 'BC', :nr_doc, :nr_nota, :prod_obtinut, :pu, :pv, :gest, :nume, :id_vanz, :tva)";
                            $pdo->prepare($iessql)->execute([':data' => $data_bon,':cod_p' => $c_m,':cant' => $qt_m,':nr_doc' => $ultim_bc,':nr_nota' => $nr_factura,':prod_obtinut' => $cod_p,':pu' => $pret_unitar,':pv' => $pret_vanzare,':gest' => $denumire_gestiune_material,':nume' => $nume_material,':id_vanz' => $ultim_id_vanz_inserat,':tva' => $cota_tva_material]);
                        }
                    }
                }
                $b_c_sql_bt = "SELECT max(nr_doc) as ultim_bt FROM miscari WHERE fel_doc='BT'";
                $ultim_bt = ($pdo->query($b_c_sql_bt)->fetchColumn() ?? 0) + 1;
                $bt_sql = "INSERT INTO miscari(data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota, pu, pret_vanzare, gestiune, denumire_produs, id_vanz_fact, cota_tva) VALUES (:data, :cod_p, :cant, 'I', 'BT', :nr_doc, :nr_nota, :pu, :pv, :gest, :nume, :id_vanz, :tva)";
                $pdo->prepare($bt_sql)->execute([':data' => $data_bon,':cod_p' => $cod_p,':cant' => $cantitate,':nr_doc' => $ultim_bt,':nr_nota' => $nr_factura,':pu' => $pret_cu_tva,':pv' => $pret_cu_tva,':gest' => $den_gestiune,':nume' => $den_p,':id_vanz' => $ultim_id_vanz_inserat,':tva' => $cota_tva_produs_vandut]);
                $iesql = "INSERT INTO miscari(data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota, pu, pret_vanzare, gestiune, denumire_produs, id_vanz_fact, cota_tva) VALUES (:data, :cod_p, :cant, 'O', 'FAC', :nr_doc, :nr_nota, :pu, :pv, :gest, :nume, :id_vanz, :tva)";
                $pdo->prepare($iesql)->execute([':data' => $data_bon,':cod_p' => $cod_p,':cant' => $cantitate,':nr_doc' => $nr_factura,':nr_nota' => $nr_factura,':pu' => $pret_cu_tva,':pv' => $pret_cu_tva,':gest' => $den_gestiune,':nume' => $den_p,':id_vanz' => $ultim_id_vanz_inserat,':tva' => $cota_tva_produs_vandut]);
            } else {
                $iessql = "INSERT INTO miscari(data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota, pu, pret_vanzare, gestiune, denumire_produs, id_vanz_fact, cota_tva) VALUES (:data, :cod_p, :cant, 'O', 'FAC', :nr_doc, :nr_nota, :pu, :pv, :gest, :nume, :id_vanz, :tva)";
                $pdo->prepare($iessql)->execute([':data' => $data_bon,':cod_p' => $cod_p,':cant' => $cantitate,':nr_doc' => $nr_factura,':nr_nota' => $nr_factura,':pu' => $pret_cu_tva,':pv' => $pret_cu_tva,':gest' => $den_gestiune,':nume' => $den_p,':id_vanz' => $ultim_id_vanz_inserat,':tva' => $cota_tva_produs_vandut]);
            }
            // =================================================================================
            // --- END LOGICĂ REPLICATĂ PENTRU FACTURI ---
            // =================================================================================
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}
?>

