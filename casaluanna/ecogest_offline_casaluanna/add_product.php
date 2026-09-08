<?php
// add_product.php
require_once __DIR__.'/database_connection.php';

// Start the session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Obținem id_factura din datele POST
$id_factura = isset($_POST['id_factura']) ? intval($_POST['id_factura']) : 0;

if ($id_factura > 0) {
    // Citim nr_factura și serie_factura din tabelul facturi
    $factura_sql = "SELECT nr_factura, serie_factura,tip_factura,data_factura FROM facturi WHERE id_factura = :id_factura";
    $factura_stmt = $pdo->prepare($factura_sql);
    $factura_stmt->bindValue(':id_factura', $id_factura);
    $factura_stmt->execute();
    $factura = $factura_stmt->fetch(PDO::FETCH_ASSOC);

    if ($factura) {
        $nr_factura = $factura['nr_factura'];
        $serie_factura = $factura['serie_factura'];
        $data_factura = $factura['data_factura'];
        $cod_p = trim($_POST['produs']);
        $den_p = trim($_POST['nume_produs']);
        
        if ($_POST['detalii_denumire'] != "") {
            $den_p .= ' ' . $_POST['detalii_denumire'];
        }
        $pret_cu_tva = floatval($_POST['pret_cu_tva']);
        $cantitate = floatval($_POST['cantitate']);
        $cota_tva = floatval($_POST['cota_tva']);
        $um = trim($_POST['um']);

       // Calculăm valorile si impunem o rotunjire matematica la 2 zecimale
        // pentru a garanta ca o operatiune cu + este egala in oglinda cu o operatiune cu -
        $valoare_vanzare_cu_tva = round($pret_cu_tva * $cantitate, 2);
        $tva_col = round($valoare_vanzare_cu_tva * ($cota_tva / (100 + $cota_tva)), 2);
        $valoare_vanzare = $valoare_vanzare_cu_tva - $tva_col;

        // Verificăm dacă produsul există deja pentru id_factura dat
        $psql3 = "SELECT id_vanz FROM vanzari WHERE id_factura = :id_factura AND den_p = :den_p AND pret_vanzare = :pret_vanzare AND cota_tva = :cota_tva AND um = :um";
        $pstmt3 = $pdo->prepare($psql3);
        $pstmt3->execute([
            ':id_factura' => $id_factura,
            ':den_p' => $den_p,
            ':pret_vanzare' => $pret_cu_tva,
            ':cota_tva' => $cota_tva,
            ':um' => $um
        ]);
        $existingProduct=$pstmt3->fetch(PDO::FETCH_ASSOC);
        $prodcount = $existingProduct ? 1 : 0;
        $id_vanz = null;
        if ($prodcount > 0) {
            $row = $existingProduct;
            $id_vanz = $row['id_vanz'];
        }

        // Executăm operația de insert sau update în tabelul vanzari
        if ($prodcount == 0) {
            // Inserăm produsul nou și adăugăm nr_factura și serie_factura
            $psql = "INSERT INTO vanzari (id_factura, nr_factura, serie_factura, den_p, cantitate, tva_col, pret_vanzare, valoare_vanzare, valoare_vanzare_cu_tva, um, cota_tva, cod_p) 
                     VALUES (:id_factura, :nr_factura, :serie_factura, :den_p, :cantitate, :tva_col, :pret_vanzare, :valoare_vanzare, :valoare_vanzare_cu_tva, :um, :cota_tva, :cod_p)";
            try {
                $stmt = $pdo->prepare($psql);
                $stmt->execute([
                    ':id_factura' => $id_factura,
                    ':nr_factura' => $nr_factura,
                    ':serie_factura' => $serie_factura,
                    ':den_p' => $den_p,
                    ':cantitate' => $cantitate,
                    ':tva_col' => $tva_col,
                    ':pret_vanzare' => $pret_cu_tva,
                    ':valoare_vanzare' => $valoare_vanzare,
                    ':valoare_vanzare_cu_tva' => $valoare_vanzare_cu_tva,
                    ':um' => $um,
                    ':cota_tva' => $cota_tva,
                    ':cod_p' => $cod_p
                ]);
                // Obține ID-ul auto-incrementat al ultimei inserții
                $ultim_id_vanz_inserat = $pdo->lastInsertId();


             // Obține ID-ul auto-incrementat al ultimei inserții
                $ultim_id_vanz_inserat = $pdo->lastInsertId();


                // --- LOGICA PENTRU TAXA HOTELIERA ---
                // MODIFICARE: Verificăm strict ID-ul clientului
if (isset($_SESSION['client_id']) && 
    ($_SESSION['client_id'] == 4 || $_SESSION['client_id'] == 7 || $_SESSION['client_id'] == 1005 || $_SESSION['client_id'] == 1006 )) {

                    // Verificăm dacă produsul adăugat este o CAZARE (case insensitive)
                    if (stripos($den_p, 'CAZARE') !== false) {
                        
                        // 1. Recalculăm valoarea netă a cazării inserate
                        $valoare_baza_taxa = $valoare_vanzare; 
                        
                        // 2. Calculăm valoarea taxei (2% din net)
                     
                        $valoare_taxa = $valoare_baza_taxa * 0.02;
                        $valoare_taxa = round($valoare_taxa, 2); 

                        // Permitem introducerea taxei și pe minus pentru stornări (doar dacă e diferită de 0)
                        if ($valoare_taxa != 0) {
                            // 3. Căutăm produsul "TAXA HOTELIERA"
$sql_taxa = "SELECT cod_produs, um, nume
             FROM produse_servicii 
             WHERE nume IN ('TAXA HOTELIERA', 'TAXA DE ORAS') 
             ORDER BY 
                 CASE 
                     WHEN nume = 'TAXA DE ORAS' THEN 1
                     WHEN nume = 'TAXA HOTELIERA' THEN 2
                     ELSE 3
                 END
             LIMIT 1";
$stmt_taxa = $pdo->prepare($sql_taxa);
$stmt_taxa->execute();
$prod_taxa = $stmt_taxa->fetch(PDO::FETCH_ASSOC);

if ($prod_taxa) {
    $cod_p_taxa = $prod_taxa['cod_produs'];
    $um_taxa = $prod_taxa['um'];
    $nume_taxa = $prod_taxa['nume']; // aici ia exact numele găsit
} else {
    $cod_p_taxa = 'TAXA_AUTO'; 
    $um_taxa = 'BUC'; 
    $nume_taxa = 'TAXA HOTELIERA'; // fallback
}
                            $cantitate_taxa = ($cantitate < 0) ? -1 : 1; 
                            $cota_tva_taxa = 0; 
                            $pret_taxa = abs($valoare_taxa); // Pretul unitar obligatoriu pozitiv
                            $valoare_taxa_finala = $pret_taxa * $cantitate_taxa; // Va lua semnul cantitatii
                            $tva_col_taxa = 0;

                            // 5. Inseram Taxa Hoteliera in vanzari
                            // Stergem taxa existenta (daca e recalculare)
                         $del_taxa_sql = "DELETE FROM vanzari 
                 WHERE id_factura = :id_factura 
                 AND den_p IN ('TAXA HOTELIERA', 'TAXA DE ORAS')";
$del_stmt = $pdo->prepare($del_taxa_sql);
$del_stmt->execute([':id_factura' => $id_factura]);
                            // Inserare noua
                            $insert_taxa_sql = "INSERT INTO vanzari (id_factura, nr_factura, serie_factura, den_p, cantitate, tva_col, pret_vanzare, valoare_vanzare, valoare_vanzare_cu_tva, um, cota_tva, cod_p) 
                                                VALUES (:id_factura, :nr_factura, :serie_factura, :den_p, :cantitate, :tva_col, :pret_vanzare, :valoare_vanzare, :valoare_vanzare_cu_tva, :um, :cota_tva, :cod_p)";
                            
                            $stmt_ins_taxa = $pdo->prepare($insert_taxa_sql);
                            $stmt_ins_taxa->execute([
                                ':id_factura' => $id_factura,
                                ':nr_factura' => $nr_factura,
                                ':serie_factura' => $serie_factura,
                                ':den_p' => $nume_taxa,
                                ':cantitate' => $cantitate_taxa, // Preluat in functie de semn
                                ':tva_col' => $tva_col_taxa,
                                ':pret_vanzare' => $pret_taxa, // Valoarea absolută (fără minus)
                                ':valoare_vanzare' => $valoare_taxa_finala, // Ia minusul automat
                                ':valoare_vanzare_cu_tva' => $valoare_taxa_finala, // Ia minusul automat
                                ':um' => $um_taxa,
                                ':cota_tva' => $cota_tva_taxa,
                                ':cod_p' => $cod_p_taxa
                            ]);
                        }
                    }
                } // END IF CLIENT ID == 7 sau 4
                // --- FINAL LOGICA TAXA HOTELIERA ---
                // (echo json_encode(['success' => true]); a fost mutat mai jos)
            } catch (PDOException $e) {
                file_put_contents('error_log.txt', "Insert Product Error: " . $e->getMessage(), FILE_APPEND);
                echo json_encode(['success' => false, 'error' => 'A apărut o eroare la adăugarea produsului. Verifică logul pentru detalii.' . $prodcount]);
                exit();
            }
        } elseif ($prodcount > 0) {
            // Actualizăm produsul existent
            $psql4 = "UPDATE vanzari 
                      SET cantitate = cantitate + :cantitate, 
                          tva_col = tva_col + :tva_col, 
                          valoare_vanzare = valoare_vanzare + :valoare_vanzare, 
                          valoare_vanzare_cu_tva = valoare_vanzare_cu_tva + :valoare_vanzare_cu_tva 
                      WHERE id_factura = :id_factura AND id_vanz = :id_vanz";
            try {
                $stmt4 = $pdo->prepare($psql4);
                $stmt4->execute([
                    ':cantitate' => $cantitate,
                    ':tva_col' => $tva_col,
                    ':valoare_vanzare' => $valoare_vanzare,
                    ':valoare_vanzare_cu_tva' => $valoare_vanzare_cu_tva,
                    ':id_factura' => $id_factura,
                    ':id_vanz' => $id_vanz
                ]);
                // Pentru update, folosim id-ul existent pentru inserțiile ulterioare în miscari
                $ultim_id_vanz_inserat = $id_vanz;
                // (echo json_encode(['success' => true]); a fost mutat mai jos)
            } catch (PDOException $e) {
                file_put_contents('error_log.txt', "Update Product Error: " . $e->getMessage(), FILE_APPEND);
                echo json_encode(['success' => false, 'error' => 'A apărut o eroare la actualizarea produsului. Verifică logul pentru detalii.']);
                exit();
            }
        }

        // Fetch denumire_produs, UM, gestiune și cota_tva_vz + pret_vanzare din produse_servicii
  $sql_fetch_produs = "
    SELECT 
        g.denumire_gestiune,
        ps.cota_tva,
        ps.tip
    FROM produse_servicii ps
    INNER JOIN gestiuni g ON ps.id_gestiune = g.id_gestiune
    WHERE ps.cod_produs = :cod_p
";
        $stmt_fetch_produs = $pdo->prepare($sql_fetch_produs);
        $stmt_fetch_produs->execute(['cod_p' => $cod_p]);
        $produs_data = $stmt_fetch_produs->fetch(PDO::FETCH_ASSOC);
        $den_gestiune  = $produs_data['denumire_gestiune'];
        $cota_tva_produs_vandut  = $produs_data['cota_tva'];
$esteServiciu = (strtolower(trim($produs_data['tip'] ?? '')) === 'serviciu');

// === NOU: dacă este serviciu, folosim prețul exact de pe linia din VANZARI în inserările FAC
$stmtPV = $pdo->prepare("SELECT pret_vanzare FROM vanzari WHERE id_vanz = :id_vanz");
$stmtPV->execute([':id_vanz' => $ultim_id_vanz_inserat]);
$pret_vanzare_linie = (float)($stmtPV->fetchColumn() ?: $pret_cu_tva);
$pret_miscari_fac = $esteServiciu ? $pret_vanzare_linie : $pret_cu_tva;

        // Inainte de a insera în tabela miscari, verificăm tip_factura.
        // Dacă tip_factura este 751, nu se efectuează inserturile în tabela miscari.
        if ($factura['tip_factura'] != '751') {        
        $data_bon = $data_factura; // Data bonului
        
        if ($den_gestiune == 'PRODUSE FINITE') {
            //incepem inserarea în miscări pentru vânzările de pe nota
            // Interogarea rețetei pentru produsul final $cod_p
            $reteta_sql = "SELECT cod_mat, cant_folos 
                          FROM retete 
                          WHERE cod_p = :cod_p";
            $reteta_stmt = $pdo->prepare($reteta_sql);
            $reteta_stmt->execute(['cod_p' => $cod_p]);

            // Pregătește interogarea pentru produse_servicii (alături de gestiuni)
            $produse_servicii_sql = "SELECT pret_achizitie, pret_cu_tva,cota_tva, denumire_gestiune, nume 
                                FROM produse_servicii 
                                INNER JOIN gestiuni 
                                  ON gestiuni.id_gestiune = produse_servicii.id_gestiune 
                                WHERE cod_produs = :cod_mat";
            $produse_servicii_stmt = $pdo->prepare($produse_servicii_sql);

            // Se obține numărul următor pentru documentul de tip BC
            $b_c_sql = "SELECT max(nr_doc) as ultim_bc 
                        FROM miscari 
                        WHERE fel_doc = 'BC'";
            $b_c_stmt = $pdo->prepare($b_c_sql);  
            $b_c_stmt->execute(); 
            while ($row = $b_c_stmt->fetch(PDO::FETCH_ASSOC)) { 
                $ultim_bc = $row['ultim_bc'] + 1;
            }

            while ($row = $reteta_stmt->fetch(PDO::FETCH_ASSOC)) { 
                $c_m = $row['cod_mat'];
                // Calculăm cantitatea pentru materia din rețetă
                $cant_folos_de_produs_finit = $row['cant_folos'];
                $qt_m = $row['cant_folos'] * $cantitate;
                // Obținem detaliile materialului din produse_servicii
                $produse_servicii_stmt->execute(['cod_mat' => $c_m]);
                $produse_servicii_row = $produse_servicii_stmt->fetch(PDO::FETCH_ASSOC);

                if ($produse_servicii_row) {
                    $pret_unitar = $produse_servicii_row['pret_achizitie'];
                    $pret_vanzare = $produse_servicii_row['pret_cu_tva'];
                    $cota_tva_material= $produse_servicii_row['cota_tva'];
                    $denumire_gestiune_material = $produse_servicii_row['denumire_gestiune'];
                    $nume_material = $produse_servicii_row['nume'];
                    // Dacă materia se află în gestiunea "PRODUSE FINITE"
                    if ($denumire_gestiune_material == 'PRODUSE FINITE') {
                        
                        //  căutăm rețeta lui pentru a procesa doar ingredientele sale
                        $reteta_sub_sql = "SELECT cod_mat, cant_folos 
                                            FROM retete 
                                            WHERE cod_p = :produs";
                        $reteta_sub_stmt = $pdo->prepare($reteta_sub_sql);
                        $reteta_sub_stmt->execute(['produs' => $c_m]);

                        while ($sub_row = $reteta_sub_stmt->fetch(PDO::FETCH_ASSOC)) {
                            $sub_cod_mat = $sub_row['cod_mat'];
                            // Calculăm cantitatea pentru ingredientul din rețeta sub-produsului
                            $sub_qt = $sub_row['cant_folos'] * $qt_m;

                            // Obținem detaliile ingredientului
                            $produse_servicii_stmt->execute(['cod_mat' => $sub_cod_mat]);
                            $sub_produse_servicii_row = $produse_servicii_stmt->fetch(PDO::FETCH_ASSOC);

                            if ($sub_produse_servicii_row) {
                                $sub_pret_unitar = $sub_produse_servicii_row['pret_achizitie'];
                                $sub_pret_vanzare = $sub_produse_servicii_row['pret_cu_tva'];
                                $sub_denumire_gestiune = $sub_produse_servicii_row['denumire_gestiune'];
                                $nume_submaterial = $sub_produse_servicii_row['nume'];
                                $cota_tva_submaterial = $sub_produse_servicii_row['cota_tva'];

                                // Inserăm mișcarea pentru ingredientul produsului finit (sub-produs)
                                $iessql_sub = "INSERT INTO miscari
                                               (data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota, produs_obtinut, pu, pret_vanzare, gestiune, denumire_produs,id_vanz_fact,cota_tva)
                                               VALUES (:data, :cod_p, :cantitate_misc, 'O', 'BC', :nr_doc, :nr_nota, :produs_obtinut, :pu, :pret_vanzare, :gestiune, :denumire_produs,:id_vanz_fact,:cota_tva)";
                                try {
                                    $stmt_iessub = $pdo->prepare($iessql_sub);
                                    $stmt_iessub->execute([
                                        ':data' => $data_bon,
                                        ':cod_p' => $sub_cod_mat,
                                        ':cantitate_misc' => $sub_qt,
                                        ':nr_doc' => $ultim_bc,
                                        ':nr_nota' => $nr_factura,
                                        ':produs_obtinut' => $c_m,
                                        ':pu' => $sub_pret_unitar,
                                        ':pret_vanzare' => $sub_pret_vanzare,
                                        ':gestiune' => $sub_denumire_gestiune,
                                        ':denumire_produs' => $nume_submaterial,
                                        ':id_vanz_fact' => $ultim_id_vanz_inserat,
                                        ':cota_tva' => $cota_tva_submaterial
                                    ]);
                                } catch(PDOException $e) {
                                    echo $iessql_sub . "<br>" . $e->getMessage();
                                }
                            }
                        }
                        
                        //inseram intrarea de obținere a materialului ce se află în gestiunea produse finite
                        $b_c_sql_bt = "SELECT max(nr_doc) as ultim_bt FROM miscari WHERE fel_doc='BT'";    
                        $b_c_stmt_bt = $pdo->prepare($b_c_sql_bt);  
                        $b_c_stmt_bt->execute(); 
                        while ($row = $b_c_stmt_bt->fetch(PDO::FETCH_ASSOC)) { 
                            $ultim_bt = $row['ultim_bt'] + 1;
                        }       
                        $bt_sql = "INSERT INTO miscari(data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota, pu, pret_vanzare, gestiune, denumire_produs,id_vanz_fact,cota_tva) 
                                   VALUES (:data, :cod_p, :cantitate_misc, 'I', 'BT', :nr_doc, :nr_nota, :pu, :pret_vanzare, 'SEMIFABRICATE', :denumire_produs,:id_vanz_fact,:cota_tva)";
                        try {
                            $stmt_bt = $pdo->prepare($bt_sql);
                            $stmt_bt->execute([
                                ':data' => $data_bon,
                                ':cod_p' => $c_m,
                                ':cantitate_misc' => $cant_folos_de_produs_finit,
                                ':nr_doc' => $ultim_bt,
                                ':nr_nota' => $nr_factura,
                                ':pu' => $pret_unitar,
                                ':pret_vanzare' => $pret_vanzare,
                                ':denumire_produs' => $nume_material,
                                ':id_vanz_fact' => $ultim_id_vanz_inserat,
                                ':cota_tva' => $cota_tva_material
                            ]);
                        } catch(PDOException $e) {
                            echo $bt_sql . "<br>" . $e->getMessage();
                        }
                        
                        // Inserăm mișcarea ieșire bon consum pentru produsul finit ce se vinde
                        // Se obține numărul următor pentru documentul de tip BC
                        $b_c_sql = "SELECT max(nr_doc) as ultim_bc 
                                    FROM miscari 
                                    WHERE fel_doc = 'BC'";
                        $b_c_stmt = $pdo->prepare($b_c_sql);  
                        $b_c_stmt->execute(); 
                        while ($row = $b_c_stmt->fetch(PDO::FETCH_ASSOC)) { 
                            $ultim_bc = $row['ultim_bc'] + 1;
                        }
                        $iesire_bc_produs_finit = "INSERT INTO miscari
                                                   (data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota, produs_obtinut, pu, pret_vanzare, gestiune, denumire_produs,id_vanz_fact,cota_tva)
                                                   VALUES (:data, :cod_p, :cantitate_misc, 'O', 'BC', :nr_doc, :nr_nota, :produs_obtinut, :pu, :pret_vanzare, 'SEMIFABRICATE', :denumire_produs,:id_vanz_fact,:cota_tva)";
                        try {
                            $stmt_iesire_bc = $pdo->prepare($iesire_bc_produs_finit);
                            $stmt_iesire_bc->execute([
                                ':data' => $data_bon,
                                ':cod_p' => $c_m,
                                ':cantitate_misc' => $cant_folos_de_produs_finit,
                                ':nr_doc' => $ultim_bc,
                                ':nr_nota' => $nr_factura,
                                ':produs_obtinut' => $cod_p,
                                ':pu' => $pret_unitar,
                                ':pret_vanzare' => $pret_vanzare,
                                ':denumire_produs' => $nume_material,
                                ':id_vanz_fact' => $ultim_id_vanz_inserat,
                                ':cota_tva' => $cota_tva_material
                            ]);
                        } catch(PDOException $e) {
                            echo $iesire_bc_produs_finit . "<br>" . $e->getMessage();
                        }

                    } else {
                        // Dacă materia nu este în gestiunea "PRODUSE FINITE",
                        // se inserează mișcarea pentru ea direct.
                        $iessql = "INSERT INTO miscari
                                   (data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota, produs_obtinut, pu, pret_vanzare, gestiune, denumire_produs,id_vanz_fact,cota_tva)
                                   VALUES (:data, :cod_p, :cantitate_misc, 'O', 'BC', :nr_doc, :nr_nota, :produs_obtinut, :pu, :pret_vanzare, :gestiune, :denumire_produs,:id_vanz_fact,:cota_tva)";
                        try {
                            $stmt_iessql = $pdo->prepare($iessql);
                            $stmt_iessql->execute([
                                ':data' => $data_bon,
                                ':cod_p' => $c_m,
                                ':cantitate_misc' => $qt_m,
                                ':nr_doc' => $ultim_bc,
                                ':nr_nota' => $nr_factura,
                                ':produs_obtinut' => $cod_p,
                                ':pu' => $pret_unitar,
                                ':pret_vanzare' => $pret_vanzare,
                                ':gestiune' => $denumire_gestiune_material,
                                ':denumire_produs' => $nume_material,
                                ':id_vanz_fact' => $ultim_id_vanz_inserat,
                                ':cota_tva' => $cota_tva_material
                            ]);
                        } catch(PDOException $e) {
                            echo $iessql . "<br>" . $e->getMessage();
                        }
                    }
                } else {
                    // Poți trata situația în care codul de material nu se găsește în produse_servicii
                    echo "Materialul cu codul $c_m nu a fost găsit în produse_servicii.";
                }
            }

            // se inserează miscarea de intrare bon transfer a produsului finit ce s-a vândut la bun început și apoi ieșirea sa pe bon fiscal
            $b_c_sql_bt = "SELECT max(nr_doc) as ultim_bt FROM miscari WHERE fel_doc='BT'";    
            $b_c_stmt_bt = $pdo->prepare($b_c_sql_bt);  
            $b_c_stmt_bt->execute(); 
            while ($row = $b_c_stmt_bt->fetch(PDO::FETCH_ASSOC)) { 
                $ultim_bt = $row['ultim_bt'] + 1;
            }       
            $bt_sql = "INSERT INTO miscari(data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota, pu, pret_vanzare, gestiune, denumire_produs,id_vanz_fact,cota_tva)
                       VALUES (:data, :cod_p, :cantitate_misc, 'I', 'BT', :nr_doc, :nr_nota, :pu, :pret_vanzare, :gestiune, :denumire_produs,:id_vanz_fact,:cota_tva)";
            try {
                $stmt_bt = $pdo->prepare($bt_sql);
                $stmt_bt->execute([
                    ':data' => $data_bon,
                    ':cod_p' => $cod_p,
                    ':cantitate_misc' => $cantitate,
                    ':nr_doc' => $ultim_bt,
                    ':nr_nota' => $nr_factura,
                    ':pu' => $pret_cu_tva,
                    ':pret_vanzare' => $pret_cu_tva,
                    ':gestiune' => $den_gestiune,
                    ':denumire_produs' => $den_p,
                    ':id_vanz_fact' => $ultim_id_vanz_inserat,
                    ':cota_tva' => $cota_tva_produs_vandut 
                ]);
            } catch(PDOException $e) {
                echo $bt_sql . "<br>" . $e->getMessage();
            }
            $iesql = "INSERT INTO miscari(data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, pu, pret_vanzare, gestiune, denumire_produs,id_vanz_fact,cota_tva)
                      VALUES (:data, :cod_p, :cantitate_misc, 'O', 'FAC', :nr_doc, :pu, :pret_vanzare, :gestiune, :denumire_produs,:id_vanz_fact,:cota_tva)";
            try {
                $stmt_iesql = $pdo->prepare($iesql);
                $stmt_iesql->execute([
                    ':data' => $data_bon,
                    ':cod_p' => $cod_p,
                    ':cantitate_misc' => $cantitate,
                    ':nr_doc' => $nr_factura,
                    ':pu' => $pret_miscari_fac,
                    ':pret_vanzare' => $pret_miscari_fac,
                    ':gestiune' => $den_gestiune,
                    ':denumire_produs' => $den_p,
                    ':id_vanz_fact' => $ultim_id_vanz_inserat,
                    ':cota_tva' => $cota_tva_produs_vandut 
                ]);
            } catch(PDOException $e) {
                echo $iesql . "<br>" . $e->getMessage();
            }
        } else {
            // daca produsul ce se vinde nu este produs finit atunci probabil e marfă și else
            $iessql = "INSERT INTO miscari(data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, pu, pret_vanzare, gestiune, denumire_produs,id_vanz_fact,cota_tva)
                      VALUES (:data, :cod_p, :cantitate_misc, 'O', 'FAC', :nr_doc, :pu, :pret_vanzare, :gestiune, :denumire_produs,:id_vanz_fact,:cota_tva)";
            try {
                $stmt_iessql = $pdo->prepare($iessql);
                $stmt_iessql->execute([
                    ':data' => $data_bon,
                    ':cod_p' => $cod_p,
                    ':cantitate_misc' => $cantitate,
                    ':nr_doc' => $nr_factura,
                    ':pu' => $pret_miscari_fac,
                    ':pret_vanzare' => $pret_miscari_fac,
                    ':gestiune' => $den_gestiune,
                    ':denumire_produs' => $den_p,
                    ':id_vanz_fact' => $ultim_id_vanz_inserat,
                    ':cota_tva' => $cota_tva_produs_vandut
                ]);
            } catch(PDOException $e) {
                echo $iessql . "<br>" . $e->getMessage();
            }
        }
    }
    // Dacă tip_factura este 751, nu se efectuează inserțiile în tabela miscari.
    
    } else {
        echo json_encode(['success' => false, 'error' => 'Factură inexistentă.']);
        exit();
    }
} else {
    echo json_encode(['success' => false, 'error' => 'ID Factură invalid.']);
    exit();
}

// La final, după ce toate operațiile au fost efectuate cu succes, se trimite răspunsul JSON
echo json_encode(['success' => true]);
?>
