<?php // casa_marcat_vanzare.php
ini_set('display_errors', 0); // Nu afișează erorile utilizatorului
ini_set('log_errors', 1); // Activează logarea erorilor
ini_set('error_log', 'error_log.log'); // Specifică calea către fișierul de log
include('session.php');
require_once __DIR__ . '/offline_api_path.php';
require_once __DIR__ . '/lorand_payment_note_printer.php';
require_once __DIR__ . '/offline_printer_flow_helper.php';

$lorandClientId = (int)($_SESSION['client_id'] ?? 0);
$lorandLocationId = (int)($_SESSION['cod_locatie'] ?? 0);
$lorandPrinterQueued = false;
$lorandRequestedRelist = (int)($_POST['nota_de_relistat'] ?? $_GET['nota_de_relistat'] ?? 0);
if ($lorandClientId === 8 && is_file(lorand_fiscal_queue_path($lorandClientId, $lorandLocationId))) {
    $_SESSION['lorand_fiscal_after_url'] = $lorandRequestedRelist > 0
        ? 'casa_marcat_vanzare.php?nota_de_relistat=' . rawurlencode((string)$lorandRequestedRelist)
        : 'casa_marcat_vanzare.php';
    unset($_SESSION['bon_procesat']);
    header('Location: asteapta_casa_marcat.php');
    exit;
}

// --- START BLOC NOU: Prevenire Execuție Dublă ---
// Verificăm dacă acest bon a fost deja procesat în această sesiune
if (isset($_SESSION['bon_procesat']) && isset($_SESSION['nr_bon']) && $_SESSION['bon_procesat'] == $_SESSION['nr_bon']) {
    // Bonul a fost deja procesat. Oprim scriptul pentru a evita re-execuția.
    // Mesajul nu va fi vizibil utilizatorului deoarece va urma un redirect oricum.
    exit("Bon deja procesat. Se previne execuția dublă.");
}

// Dacă nu a fost procesat, setăm "lacătul" acum
if (isset($_SESSION['nr_bon'])) {
    $_SESSION['bon_procesat'] = $_SESSION['nr_bon'];
}
// --- END BLOC NOU ---

error_reporting(E_ALL); // Raportează toate tipurile de erori
// Presupunem că variabila nota_de_relistat vine din sesiune sau este definită undeva
$nota_de_relistat = $lorandRequestedRelist;

if ($nota_de_relistat == 0) {

    // === Codul tău existent pentru situația în care nota_de_relistat NU este setată ===

    // Inițializarea variabilelor...
    $den_ent = "";
    $sediu = "";
    $cod_fiscal_ent = "";
    $data_bon = date('Y-m-d');
    $ora_bon = date('H:i:s');
    $admin_firstname = "";
    $admin_lastname = "";
    $numerar = 0;
    $tichete = 0;
    $card = 0;
    $protocol = 0;
    $rest = 0;
    $rest_numerar = 0;
    $cod_locatie = $_SESSION['cod_locatie'];
    
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', 'error_log.log');
    error_reporting(E_ALL);
    $cr = chr(13) . chr(10);
    $K = "K,1,______,_,__;";
    $H = "H,1,______,_,__;";
    $T = "T,1,______,_,__;";
    $T2 = "T,1,______,_,__;";
    $T3 = "T,1,______,_,__;";
    $F = "F,1,______,_,__;";
    $nr_bon = $_SESSION['nr_bon'] ?? '';
    $cu_bacsis = 0;
    
    // Se execută interogarea pentru produsele bonului curent
    // MODIFICARE: Am adaugat dep_casa_marcat in SELECT
    $f_sql = "SELECT 
                $tabel_final_det_note.pachet,
                $tabel_final_det_note.discount,
                $tabel_final_det_note.cod_p,
                $tabel_final_det_note.nume_produs as nume,
                $tabel_final_nomenclator.dep_casa_marcat, 
                cote_tva.cota,
                cote_tva.dep_casa,
                $tabel_final_nomenclator.um,
                $tabel_final_det_note.cantitate,
                $tabel_final_det_note.pret_vanzare,
                $tabel_final_det_note.id_vanz 
              FROM 
                $tabel_final_nomenclator 
              INNER JOIN 
                $tabel_final_det_note 
              ON 
                $tabel_final_det_note.cod_p = $tabel_final_nomenclator.cod_produs 
              INNER JOIN 
                cote_tva 
              ON 
                $tabel_final_nomenclator.cota_tva = cote_tva.cota 
              WHERE 
                nr_bon = :nr_bon;";
    
    $f_stmt = $pdo->prepare($f_sql);
    $f_stmt->execute([':nr_bon' => $nr_bon]);
    $cif_client = $_SESSION['cif_client'];
    
    $myBuffer = $cif_client ? $K . $cif_client . $cr . $H . $cr : $H . $cr;
    
    while ($row = $f_stmt->fetch(PDO::FETCH_ASSOC)) {
        $pachet = $row['pachet'] ?? 0;
        $pret_vanzare = $row['pret_vanzare'] ?? 0;
        $dep_casa = $row['dep_casa'] ?? 0; // Acesta este CODUL COTEI TVA (1,2,3,4,5 etc din cote_tva)
        $cod_p = $row['cod_p'] ?? 0;
    
        if ($cod_p == 9999) {
            $cu_bacsis = 1;
            $bacsis = $pret_vanzare;
        }
    
        $cota_tva = 0;
        if ($cota_tva == 9 && $pachet != 1) {
            if ($_SESSION['ajustare_adaos'] == 0) {
                $cota_tva = 5;
            }
            $dep_casa = 3;
        }
    
        $um = $row['um'] ?? '';
        if ($um === 'H87') { $um = 'BUC'; }

        $produs = substr(($row['nume'] ?? '') . ($cota_tva == 9 && $pachet == 1 ? " P" : ''), 0, 22);
        $cantitate = $row['cantitate'] ?? 0;
    
        $valoare_vanzare = $pret_vanzare * $cantitate;
        $discount = $row['discount'] ?? 0;

        if ($discount > 0) {
            // indiferent de suma discountului lasam la fel ca sa nu se deregleze la casa de marcat
            $pret_vanzare_fin = round($pret_vanzare,2);
        } else {
            // Calculul original
            $pret_vanzare_fin = round($pret_vanzare,2);
        }
    
        // --- LOGICA NOUA PENTRU DEPARTAMENT ---
        // $dept -> departamentul casei de marcat (raionul)
        // $cod_cota_tva -> indexul cotei de tva (A, B, C etc)
        
        $dept = (int)($row['dep_casa_marcat'] ?? 0); 
        if ($dept == 0) { $dept = 1; }
        
        $cod_cota_tva = $dep_casa; // Folosim valoarea extrasa deja mai sus din cote_tva.dep_casa

        $myBuffer .= "S,1,______,_,__;" 
                  . "$produs;$pret_vanzare_fin;$cantitate;$dept;$dept;$cod_cota_tva;0;0;$um" 
                  . $cr;
    }
    
    $sql_nota_plata = "SELECT numerar, card, tichete, glovo FROM note WHERE nrbon = :nrbon";
    $stmt_nota_plata = $pdo->prepare($sql_nota_plata);
    $stmt_nota_plata->execute([':nrbon' => $nr_bon]);
    $notaRow = $stmt_nota_plata->fetch(PDO::FETCH_ASSOC);

    if ($notaRow) {
        if (isset($notaRow['numerar']) && $notaRow['numerar'] > 0) {
            $myBuffer .= $T . "0;" . $notaRow['numerar'] . ";;;;" . $cr;
        }
        if (isset($notaRow['card']) && $notaRow['card'] > 0) {
            $myBuffer .= $T . "1;" . $notaRow['card'] . ";;;;" . $cr;
        }
        if (isset($notaRow['tichete']) && $notaRow['tichete'] > 0) {
            $myBuffer .= $T . "3;" . $notaRow['tichete'] . ";;;;" . $cr;
        }
        if (isset($notaRow['glovo']) && $notaRow['glovo'] > 0) {
            $myBuffer .= $T . "6;" . $notaRow['glovo'] . ";;;;" . $cr;
        }
    }

    // Adaugă la final linia T suplimentară pentru clientul cu ID = 4
    $client_agecs = $_SESSION['client_id'] ?? null;
    if ($client_agecs == 4) {
        $myBuffer .= "T,1,______,_,__;" . $cr;
    }

    // --- START BLOC NOU (NON-INTRUZIV): Double-check/ajustare T pentru client_id = 8 ---
    try {
        // Copia foloseste calculul fiscal AGECSDEMO, fara ajustarea istorica demo.
        if (false && $notaRow) {
            $vals = [
                'numerar' => (float)($notaRow['numerar'] ?? 0),
                'card'    => (float)($notaRow['card'] ?? 0),
                'tichete' => (float)($notaRow['tichete'] ?? 0),
                'glovo'   => (float)($notaRow['glovo'] ?? 0),
            ];
            $nonZero = array_filter($vals, function ($v) { return $v > 0.00001; });

            if (count($nonZero) === 1) {
                $singleKey = array_keys($nonZero)[0];
                $mapType   = ['numerar' => 0, 'card' => 1, 'tichete' => 3, 'glovo' => 6];
                $typeCode  = $mapType[$singleKey];

                $sum_sql  = "SELECT ROUND(SUM(cantitate * pret_vanzare), 2) AS total_linii
                             FROM $tabel_final_det_note
                             WHERE nr_bon = :nrbon";
                $sum_stmt = $pdo->prepare($sum_sql);
                $sum_stmt->execute([':nrbon' => $nr_bon]);
                $sum_row      = $sum_stmt->fetch(PDO::FETCH_ASSOC);
                $total_linii  = (float)($sum_row['total_linii'] ?? 0);
                $current_val  = (float)($nonZero[$singleKey] ?? 0);

                if (abs($current_val - $total_linii) > 0.001) {
                    $pattern = '/T,1,______,_,__;' . $typeCode . ';[0-9]+(?:\.[0-9]+)?;;;;/';
                    if (preg_match_all($pattern, $myBuffer, $m, PREG_OFFSET_CAPTURE)) {
                        $lastIndex   = count($m[0]) - 1;
                        $matchText   = $m[0][$lastIndex][0];
                        $startPos    = $m[0][$lastIndex][1];
                        $replacement = 'T,1,______,_,__;' . $typeCode . ';' . number_format($total_linii, 2, '.', '') . ';;;;';
                        $myBuffer    = substr_replace($myBuffer, $replacement, $startPos, strlen($matchText));
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Verificare/ajustare T (client 8) a eșuat: " . $e->getMessage());
    }
    // --- END BLOC NOU (NON-INTRUZIV) ---

    date_default_timezone_set("Europe/Bucharest");
    
    // --- Inserția în tabela bonuri_casa_marcat și generarea fișierelor JSON (codul existent) ---
    $bon_fiscal_json_scris = false;
    $bon_fiscal_insert_id = 0;
    try {
        $insert_sql = "INSERT INTO bonuri_casa_marcat (data, ora, continut_bon, de_trimis_la_casa_marcat, nrbon, locatie)
                          VALUES (:data, :ora, :continut_bon, :de_trimis, :nrbon, :locatie)";
        $insert_stmt = $pdo->prepare($insert_sql);
        $current_date = date('Y-m-d');
        $current_time = date('H:i:s');
        $de_trimis = 1;
    
        $insert_stmt->execute([
            ':data' => $current_date,
            ':ora' => $current_time,
            ':continut_bon' => $myBuffer,
            ':de_trimis' => $de_trimis,
            ':nrbon' => $nr_bon,
            ':locatie' => $cod_locatie
        ]);
        $bon_fiscal_insert_id = (int)$pdo->lastInsertId();
    
        if (isset($_SESSION['client_id'])) {
            $client_id = $_SESSION['client_id'];
    
            $select_sql = "SELECT * FROM bonuri_casa_marcat WHERE nrbon = :nrbon AND de_trimis_la_casa_marcat = 1 AND locatie = :cod_locatie";
            $select_stmt = $pdo->prepare($select_sql);
            $select_stmt->bindParam(':nrbon', $nr_bon, PDO::PARAM_INT);
            $select_stmt->bindParam(':cod_locatie', $cod_locatie, PDO::PARAM_INT);
            $select_stmt->execute();
            $bons = $select_stmt->fetchAll(PDO::FETCH_ASSOC);
    
            $json_array = [
                "status"  => "success",
                "message" => "Bonuri preluate cu succes.",
                "data"    => $bons
            ];
            $json_data = json_encode($json_array, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
            if (!empty($bons) && isset($bons[0]['locatie'])) {
                $locatie_val = $bons[0]['locatie'];
            } else {
                $locatie_val = "default";
            }
    
            $folder_path = offline_api_path($client_id, $locatie_val);
    
            if (!offline_api_ensure_dir($folder_path)) {
                throw new RuntimeException("Nu s-a putut crea directorul API offline: " . $folder_path);
            }
            if ($json_data === false) {
                throw new RuntimeException('Bonul fiscal nu a putut fi convertit în JSON.');
            }
    
            $json_file_path = $folder_path . "/bon_casa_marcat.json";
            if (!lorand_fiscal_publish_json($json_file_path, $json_data)) {
                if ($bon_fiscal_insert_id > 0) {
                    $cleanup_stmt = $pdo->prepare("DELETE FROM bonuri_casa_marcat WHERE id = :id AND de_trimis_la_casa_marcat = 1");
                    $cleanup_stmt->execute([':id' => $bon_fiscal_insert_id]);
                }
                $_SESSION['lorand_fiscal_after_url'] = 'casa_marcat_vanzare.php';
                unset($_SESSION['bon_procesat']);
                header('Location: asteapta_casa_marcat.php');
                exit;
            }
            $bon_fiscal_json_scris = true;
    
            // Marcarea ca preluat se face numai după scrierea cu succes a fișierului fiscal.
            $update_sql = "UPDATE bonuri_casa_marcat 
                           SET de_trimis_la_casa_marcat = 0 
                           WHERE nrbon = :nrbon AND de_trimis_la_casa_marcat = 1";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->bindParam(':nrbon', $nr_bon, PDO::PARAM_INT);
            $update_stmt->execute();
        } else {
            error_log("Client_id nu este setat în sesiune.");
        }
    
    } catch (Throwable $e) {
        error_log("Eroare la inserția/generarea bonului fiscal: " . $e->getMessage());
        if ((int)$client_agecs === 8 && !$bon_fiscal_json_scris && $bon_fiscal_insert_id > 0) {
            try {
                $cleanup_stmt = $pdo->prepare("DELETE FROM bonuri_casa_marcat WHERE id = :id AND de_trimis_la_casa_marcat = 1");
                $cleanup_stmt->execute([':id' => $bon_fiscal_insert_id]);
            } catch (Throwable $cleanupError) {
                error_log('Curățare bon fiscal AGECSDEMO nepregătit: ' . $cleanupError->getMessage());
            }
        }
    }
    
    // --- Generarea datelor pentru imprimantă ---
    try {
        if ((int)$client_agecs === 8) {
            $lorandSettings = vanzare_v2_lorand_settings($pdo);
            if ($bon_fiscal_json_scris && !empty($lorandSettings['listare_nota_dupa_fiscalizare'])) {
                lorand_payment_note_enqueue(
                    $pdo,
                    $tabel_final_det_note,
                    $tabel_final_nomenclator,
                    $tabel_final_admins,
                    (int)$nr_bon,
                    8,
                    (int)$cod_locatie
                );
                $lorandPrinterQueued = true;
            } elseif (!$bon_fiscal_json_scris) {
                error_log('Nota de plată AGECSDEMO nu a fost trimisă la BAR deoarece bon_casa_marcat.json nu a fost generat cu succes.');
            }
        } else {
        $departments_sql = "
            SELECT DISTINCT ps.departament
            FROM $tabel_final_det_note dn
            JOIN $tabel_final_nomenclator ps ON dn.cod_p = ps.cod_produs
            WHERE dn.nr_bon = :nrbon
              AND ps.departament IS NOT NULL
              AND ps.departament != ''
        ";
        $departments_stmt = $pdo->prepare($departments_sql);
        $departments_stmt->execute([':nrbon' => $nr_bon]);
        $departments = $departments_stmt->fetchAll(PDO::FETCH_COLUMN);
    
        $printData = [];
    
        if (!empty($departments)) {
            $current_date = date('Y-m-d');
            $current_time = date('H:i:s');
            $de_trimis = 1;
    
            foreach ($departments as $departament_listare) {
                $products_sql = "
                    SELECT 
                        dn.pachet,
                        dn.discount,
                        dn.cod_p,
                        ps.nume,
                        ps.um,
                        dn.cantitate,
                        dn.tva_col,
                        dn.pret_vanzare,
                        dn.valoare_vanzare,
                        dn.valoare_vanzare_cu_tva,
                        ps.cota_tva,
                        ps.departament
                    FROM $tabel_final_det_note dn
                    JOIN $tabel_final_nomenclator ps ON dn.cod_p = ps.cod_produs
                    WHERE dn.nr_bon = :nrbon
                      AND ps.departament = :departament
                ";
                $products_stmt = $pdo->prepare($products_sql);
                $products_stmt->execute([':nrbon' => $nr_bon, ':departament' => $departament_listare]);
                $products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);
    
                $continut = "";
                $continut .= $den_ent . "\n";
                $continut .= $sediu . "\n";
                $continut .= "C.I.F.: " . $cod_fiscal_ent . "\n";
                $continut .= "C.I.F. CLIENT: " . $cif_client . "\n";
                $continut .= $data_bon . " " . $ora_bon . "\n";
                $continut .= "LEI\n";
                $continut .= "OPERATOR: " . $admin_firstname . " " . $admin_lastname . "\n\n";
    
                foreach ($products as $product) {
                    $pachet = $product['pachet'];
                    $pprodus = $product['nume'];
                    $produs = substr($pprodus, 0, 20);
                    $um = $product['um'];
                    $cantitate = $product['cantitate'];
                    $tva_col = $product['tva_col'];
                    $pret_vanzare = $product['pret_vanzare'];
                    $valoare_vanzare_cu_tva = $product['valoare_vanzare_cu_tva'];
                    $cota_tva = $product['cota_tva'];
                    $departament = $product['departament'];
                    $discount = $product['discount'];
    
                    $continut .= "Produs: " . $produs . "\n";
                    $continut .= "Cantitate: " . $cantitate . " " . $um . " X " . number_format($pret_vanzare, 2) . " LEI\n";
                    $continut .= "Valoare Vânzare: " . number_format($valoare_vanzare_cu_tva, 2) . " LEI\n\n";
                }
    
                $f_tot_sql = "
                    SELECT SUM(dn.valoare_vanzare_cu_tva) AS total_vanzare
                    FROM $tabel_final_det_note dn 
                    JOIN $tabel_final_nomenclator ps ON dn.cod_p = ps.cod_produs 
                    WHERE dn.nr_bon = :nrbon 
                      AND ps.departament = :departament
                ";
                $f_tot_stmt = $pdo->prepare($f_tot_sql);
                $f_tot_stmt->execute([':nrbon' => $nr_bon, ':departament' => $departament_listare]);
                $row = $f_tot_stmt->fetch(PDO::FETCH_ASSOC);
                $total_val_vz_cu_tva = $row['total_vanzare'] ?? 0;
    
                $ds_tot_sql = "
                    SELECT SUM(dn.discount) AS total_discount
                    FROM $tabel_final_det_note dn 
                    JOIN $tabel_final_nomenclator ps ON dn.cod_p = ps.cod_produs 
                    WHERE dn.nr_bon = :nrbon 
                      AND ps.departament = :departament
                ";
                $ds_tot_stmt = $pdo->prepare($ds_tot_sql);
                $ds_tot_stmt->execute([':nrbon' => $nr_bon, ':departament' => $departament_listare]);
                $row = $ds_tot_stmt->fetch(PDO::FETCH_ASSOC);
                $total_disc = $row['total_discount'] ?? 0;
    
                $total_val_vz_cu_tva = $total_val_vz_cu_tva - $total_disc;
                $continut .= "TOTAL LEI: " . number_format($total_val_vz_cu_tva, 2) . " LEI\n";
    
                if ($numerar != 0) {
                    $continut .= "Numerar: " . number_format($numerar, 2) . " LEI\n";
                }
                if ($tichete != 0) {
                    $continut .= "Tichete: " . number_format($tichete, 2) . " LEI\n";
                }
                if ($card != 0) {
                    $continut .= "Card: " . number_format($card, 2) . " LEI\n";
                }
                if ($protocol != 0) {
                    $continut .= "Protocol: " . number_format($protocol, 2) . " LEI\n";
                }
                $continut .= "Rest: " . number_format($rest, 2) . " LEI\n\n";
    
                $cote_tva_sql = "
                    SELECT 
                        ps.cota_tva,
                        ps.departament,
                        dn.tva_col 
                    FROM $tabel_final_det_note dn
                    JOIN $tabel_final_nomenclator ps ON dn.cod_p = ps.cod_produs
                    WHERE dn.nr_bon = :nrbon 
                      AND ps.departament = :departament
                ";
                $cote_tva_stmt = $pdo->prepare($cote_tva_sql);
                $cote_tva_stmt->execute([':nrbon' => $nr_bon, ':departament' => $departament_listare]);
    
                $tva_a = 0;
                $tva_b = 0;
                $tva_c = 0;
                $faratva = 0;
    
                while ($row = $cote_tva_stmt->fetch(PDO::FETCH_ASSOC)) {
                    $cota_tva = $row['cota_tva'];
                    $tva_col = $row['tva_col'];
    
                    if ($cota_tva == 19) {
                        $tva_a += $tva_col;
                    } elseif ($cota_tva == 9) {
                        $tva_b += $tva_col;
                    } elseif ($cota_tva == 5) {
                        $tva_c += $tva_col;
                    } else {
                        $faratva += $tva_col;
                    }
                }
    
                if ($tva_a > 0) {
                    $continut .= "A: TVA A (19%): " . number_format($tva_a, 3) . " LEI\n";
                }
                if ($tva_b > 0) {
                    $continut .= "B: TVA B (9%): " . number_format($tva_b, 3) . " LEI\n";
                }
                if ($tva_c > 0) {
                    $continut .= "C: TVA C (5%): " . number_format($tva_c, 3) . " LEI\n";
                }
                if ($faratva > 0) {
                    $continut .= "FARA TVA: " . number_format($faratva, 3) . " LEI\n";
                }
                $continut .= "TOTAL TVA: " . number_format($tva_a + $tva_b + $tva_c + $faratva, 3) . " LEI\n\n";
    
                $continut .= "Nr. nota: " . $nr_bon . "\n";
    
                $printData[] = [
                    'data'                    => $current_date,
                    'ora'                     => $current_time,
                    'de_trimis_la_imprimanta' => $de_trimis,
                    'nrbon'                   => $nr_bon,
                    'locatie'                 => $cod_locatie,
                    'departament_listare'     => $departament_listare,
                    'continut'                => $continut
                ];
            }
        }
    
        $json_array_imprimanta = [
            "status"  => "success",
            "message" => "Date pentru imprimantă generate cu succes.",
            "data"    => $printData
        ];
        $json_data_imprimanta = json_encode($json_array_imprimanta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
        $json_file_path_imprimanta = $folder_path . "/de_listat_la_imprimanta.json";
        file_put_contents($json_file_path_imprimanta, $json_data_imprimanta);
        }
    
        unset($_SESSION['nr_bon']);
        unset($_SESSION['bon_procesat']); // <-- CURĂȚARE LOCK
        unset($_SESSION['numerarprim']);
        unset($_SESSION['cardprim']);
        unset($_SESSION['cif_client']);
        unset($_SESSION['rest_tichete']);
        unset($_SESSION['total_tichete']);
        unset($_SESSION['masa_curenta']);
    

    //redirect factura automata 
    if ($client_agecs == 2) {
        if (!empty($cif_client)) {
            // Preluăm folder-ul și numele scriptului curent
            $folder = basename(__DIR__);           // ex: "app_vanzare" sau "app_restaurant"
            $script="";
            if($folder=="app_vanzare"){$script="vanzare_magazin.php";}
            elseif($folder=="app_restaurant"){$script="vanzare_restaurant.php";}
            elseif($folder=="app_vanzare_v2"){$script="vanzare_magazin.php";}
            elseif($folder=="app_restaurant_v2"){$script="vanzare_restaurant.php";}
            elseif($folder=="app_restaurant_hp"){$script="vanzare_restaurant.php";}
            $path   = $folder . '/' . $script;       // ex: "app_vanzare/casa_marcat_vanzare.php"

            // 1) determinăm cod_metoda_plata (rămâne la fel)
            if ($numerar > 0 && $card == 0) {
                $cod_metoda_plata = 10;
            } elseif ($card > 0 && $numerar == 0) {
                $cod_metoda_plata = 48;
            } else {
                $cod_metoda_plata = 'ZZZ';
            }

            printf(
                "<script>location.href='../vanzare_genereaza_factura_nota.php?nr_bon=%s&cif_client=%s&cod_metoda_plata=%s&path=%s'</script>",
                urlencode($nr_bon),
                urlencode($cif_client),
                urlencode($cod_metoda_plata),
                urlencode($path)
            );

        }
    }
        if ((int)$client_agecs === 8 && $bon_fiscal_json_scris) {
            $_SESSION['lorand_fiscal_after_url'] = $lorandPrinterQueued
                ? lorand_printer_wait_url('vanzare_magazin.php', 'nota_plata')
                : 'vanzare_magazin.php';
            header('Location: asteapta_casa_marcat.php');
            exit;
        }
        printf("<script>location.href='vanzare_magazin.php'</script>");
    
    } catch (Throwable $e) {
        error_log("Eroare la generarea datelor pentru imprimantă: " . $e->getMessage());
        if ((int)$client_agecs === 8 && $bon_fiscal_json_scris) {
            $_SESSION['offline_printer_error'] = 'Nota de plată nu a putut fi pusă în coada imprimantei BAR: ' . $e->getMessage();
            $_SESSION['lorand_fiscal_after_url'] = lorand_printer_wait_url('vanzare_magazin.php', 'nota_plata');
            unset($_SESSION['nr_bon'], $_SESSION['bon_procesat'], $_SESSION['numerarprim'], $_SESSION['cardprim'], $_SESSION['cif_client'], $_SESSION['rest_tichete'], $_SESSION['total_tichete'], $_SESSION['masa_curenta']);
            header('Location: asteapta_casa_marcat.php');
            exit;
        }
    }
}
else {
    // === Dacă nota_de_relistat este setată (diferită de 0): se preiau datele notei din tabela "note" ===
    
    // Preluăm datele notei din baza de date folosind nrbon = $nota_de_relistat
    $sql_note = "SELECT * FROM note WHERE nrbon = :nrbon";
    $stmt_note = $pdo->prepare($sql_note);
    $stmt_note->execute([':nrbon' => $nota_de_relistat]);
    $noteRow = $stmt_note->fetch(PDO::FETCH_ASSOC);
    
    if (!$noteRow) {
        error_log("Nota cu nrbon " . $nota_de_relistat . " nu a fost găsită.");
        // Poți face un redirect sau afișa un mesaj de eroare
        exit("Nota nu a fost găsită.");
    }
    
    // Preluăm câmpurile necesare din nota găsită
    $data_bon       = $noteRow['data_bon'];
    $ora_bon        = $noteRow['ora_bon'];
    $cif_client     = $noteRow['cif_client'];
    $cod_locatie    = $noteRow['locatie'];
    
    // Dacă este necesar, poți prelua și valorile de plată din nota (numerar, card, tichete, protocol, rest)
    $numerar        = $noteRow['numerar'];
    $card           = $noteRow['card'];
    $tichete        = $noteRow['tichete'];
    $protocol       = $noteRow['protocol'];
    $rest           = $noteRow['rest'];
    
    // Alte variabile se setează similar
    $den_ent        = "";
    $sediu          = "";
    $cod_fiscal_ent = "";
    $admin_firstname = "";
    $admin_lastname  = "";
    $cu_bacsis      = 0;
    
    $cr = chr(13) . chr(10);
    $K = "K,1,______,_,__;";
    $H = "H,1,______,_,__;";
    $T = "T,1,______,_,__;";
    $T2 = "T,1,______,_,__;";
    $T3 = "T,1,______,_,__;";
    $F = "F,1,______,_,__;";
    
    // Folosim nota din baza de date, deci $nr_bon va fi $nota_de_relistat
    $nr_bon = $nota_de_relistat;
    
    // Se execută interogarea pentru produsele bonului (la fel ca mai sus)
    // MODIFICARE: Am adaugat dep_casa_marcat in SELECT si aici
    $f_sql = "SELECT 
                $tabel_final_det_note.pachet,
                $tabel_final_det_note.discount,
                $tabel_final_det_note.cod_p,
                 $tabel_final_det_note.nume_produs as nume,
                 $tabel_final_nomenclator.dep_casa_marcat, 
                cote_tva.cota,
                cote_tva.dep_casa,
                $tabel_final_nomenclator.um,
                $tabel_final_det_note.cantitate,
                $tabel_final_det_note.pret_vanzare,
                $tabel_final_det_note.id_vanz 
              FROM 
                $tabel_final_nomenclator 
              INNER JOIN 
                $tabel_final_det_note 
              ON 
                $tabel_final_det_note.cod_p = $tabel_final_nomenclator.cod_produs 
              INNER JOIN 
                cote_tva 
              ON 
                $tabel_final_nomenclator.cota_tva = cote_tva.cota 
              WHERE 
                nr_bon = :nr_bon;";
    
    $f_stmt = $pdo->prepare($f_sql);
    $f_stmt->execute([':nr_bon' => $nr_bon]);
    
    // Construim $myBuffer pornind de la CIF-ul clientului (din nota)
    $myBuffer = $cif_client ? $K . $cif_client . $cr . $H . $cr : $H . $cr;
    
    while ($row = $f_stmt->fetch(PDO::FETCH_ASSOC)) {
        $pachet = $row['pachet'] ?? 0;
        $pret_vanzare = $row['pret_vanzare'] ?? 0;
        $dep_casa = $row['dep_casa'] ?? 0; // COD COTA TVA
        $cod_p = $row['cod_p'] ?? 0;
    
        if ($cod_p == 9999) {
            $cu_bacsis = 1;
            $bacsis = $pret_vanzare;
        }
    
        $cota_tva = 0;
        if ($cota_tva == 9 && $pachet != 1) {
            if ($_SESSION['ajustare_adaos'] == 0) {
                $cota_tva = 5;
            }
            $dep_casa = 3;
        }
    
        $um = $row['um'] ?? '';
        if ($um === 'H87') { $um = 'BUC'; }

        $produs = substr(($row['nume'] ?? '') . ($cota_tva == 9 && $pachet == 1 ? " P" : ''), 0, 22);
        $cantitate = $row['cantitate'] ?? 0;
    
        $valoare_vanzare = $pret_vanzare * $cantitate;
        $discount = $row['discount'] ?? 0;

        if ($discount > 0) {
            // indiferent de suma discountului lasam la fel ca sa nu se deregleze la casa de marcat
            $pret_vanzare_fin = round($pret_vanzare,2);
        } else {
            // Calculul original
            $pret_vanzare_fin = round($pret_vanzare,2);
        }

        // --- LOGICA NOUA PENTRU DEPARTAMENT ---
        // $dept -> departamentul casei de marcat (raionul)
        // $cod_cota_tva -> indexul cotei de tva (A, B, C etc)
        
        $dept = (int)($row['dep_casa_marcat'] ?? 0); 
        if ($dept == 0) { $dept = 1; }
        
        $cod_cota_tva = $dep_casa; // Folosim valoarea extrasa deja mai sus

        $myBuffer .= "S,1,______,_,__;" 
                  . "$produs;$pret_vanzare_fin;$cantitate;$dept;$dept;$cod_cota_tva;0;0;$um" 
                  . $cr;
    }
    
    // Preluăm valorile de plată din nota (asigură-te că coloana "glovo" există în tabel)
    $numerar = $noteRow['numerar'];
    $card    = $noteRow['card'];
    $tichete = $noteRow['tichete'];
    $glovo   = $noteRow['glovo']; // Asigură-te că această coloană există în tabela note

    // Definim tipurile de plată cu codurile aferente
    $paymentTypes = [
        'numerar'  => 0,
        'card'     => 1,
        'glovo'    => 6,
        'tichete'  => 3,
    ];

    // Parcurgem fiecare tip de plată și, dacă valoarea este diferită de 0,
    // adăugăm în $myBuffer linia corespunzătoare
    foreach ($paymentTypes as $column => $type) {
        if (isset($noteRow[$column]) && $noteRow[$column] != 0) {
            $myBuffer .= $T . "$type;" . $noteRow[$column] . ";;;;" . $cr;
        }
    }

    // Adaugă la final linia T suplimentară pentru clientul cu ID = 4
    $client_agecs = $_SESSION['client_id'] ?? null;
    if ($client_agecs == 4) {
        $myBuffer .= "T,1,______,_,__;" . $cr;
    }

    // --- START BLOC NOU (NON-INTRUZIV): Double-check/ajustare T pentru client_id = 8 (nota relistată) ---
    try {
        if (false && $noteRow) {
            $vals = [
                'numerar' => (float)($noteRow['numerar'] ?? 0),
                'card'    => (float)($noteRow['card'] ?? 0),
                'tichete' => (float)($noteRow['tichete'] ?? 0),
                'glovo'   => (float)($noteRow['glovo'] ?? 0),
            ];
            $nonZero = array_filter($vals, function ($v) { return $v > 0.00001; });

            if (count($nonZero) === 1) {
                $singleKey = array_keys($nonZero)[0];
                $mapType   = ['numerar' => 0, 'card' => 1, 'tichete' => 3, 'glovo' => 6];
                $typeCode  = $mapType[$singleKey];

                $sum_sql  = "SELECT ROUND(SUM(cantitate * pret_vanzare), 2) AS total_linii
                             FROM $tabel_final_det_note
                             WHERE nr_bon = :nrbon";
                $sum_stmt = $pdo->prepare($sum_sql);
                $sum_stmt->execute([':nrbon' => $nr_bon]);
                $sum_row      = $sum_stmt->fetch(PDO::FETCH_ASSOC);
                $total_linii  = (float)($sum_row['total_linii'] ?? 0);
                $current_val  = (float)($nonZero[$singleKey] ?? 0);

                if (abs($current_val - $total_linii) > 0.001) {
                    $pattern = '/T,1,______,_,__;' . $typeCode . ';[0-9]+(?:\.[0-9]+)?;;;;/';
                    if (preg_match_all($pattern, $myBuffer, $m, PREG_OFFSET_CAPTURE)) {
                        $lastIndex   = count($m[0]) - 1;
                        $matchText   = $m[0][$lastIndex][0];
                        $startPos    = $m[0][$lastIndex][1];
                        $replacement = 'T,1,______,_,__;' . $typeCode . ';' . number_format($total_linii, 2, '.', '') . ';;;;';
                        $myBuffer    = substr_replace($myBuffer, $replacement, $startPos, strlen($matchText));
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Verificare/ajustare T (client 8, relistat) a eșuat: " . $e->getMessage());
    }
    // --- END BLOC NOU (NON-INTRUZIV) ---

    date_default_timezone_set("Europe/Bucharest");
    
    // --- Inserția în tabela bonuri_casa_marcat și generarea fișierelor JSON (la fel ca mai sus) ---
    $bon_fiscal_relistat_scris = false;
    $bon_fiscal_relistat_insert_id = 0;
    try {
        $insert_sql = "INSERT INTO bonuri_casa_marcat (data, ora, continut_bon, de_trimis_la_casa_marcat, nrbon, locatie)
                          VALUES (:data, :ora, :continut_bon, :de_trimis, :nrbon, :locatie)";
        $insert_stmt = $pdo->prepare($insert_sql);

        // Folosim data și ora preluate din nota
        $current_date = date('Y-m-d');
        $current_time = date('H:i:s');
        $de_trimis = 1;
    
        $insert_stmt->execute([
            ':data' => $current_date,
            ':ora' => $current_time,
            ':continut_bon' => $myBuffer,
            ':de_trimis' => $de_trimis,
            ':nrbon' => $nr_bon,
            ':locatie' => $cod_locatie
        ]);
        $bon_fiscal_relistat_insert_id = (int)$pdo->lastInsertId();
    
        if (isset($_SESSION['client_id'])) {
            $client_id = $_SESSION['client_id'];
    
            $select_sql = "SELECT * FROM bonuri_casa_marcat WHERE nrbon = :nrbon AND de_trimis_la_casa_marcat = 1 AND locatie = :cod_locatie";
            $select_stmt = $pdo->prepare($select_sql);
            $select_stmt->bindParam(':nrbon', $nr_bon, PDO::PARAM_INT);
            $select_stmt->bindParam(':cod_locatie', $cod_locatie, PDO::PARAM_INT);
            $select_stmt->execute();
            $bons = $select_stmt->fetchAll(PDO::FETCH_ASSOC);
    
            $json_array = [
                "status"  => "success",
                "message" => "Bonuri preluate cu succes.",
                "data"    => $bons
            ];
            $json_data = json_encode($json_array, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
            if (!empty($bons) && isset($bons[0]['locatie'])) {
                $locatie_val = $bons[0]['locatie'];
            } else {
                $locatie_val = "default";
            }
    
            $folder_path = offline_api_path($client_id, $locatie_val);
    
            if (!offline_api_ensure_dir($folder_path)) {
                throw new RuntimeException("Nu s-a putut crea directorul API offline: " . $folder_path);
            }
            if ($json_data === false) {
                throw new RuntimeException('Bonul fiscal relistat nu a putut fi convertit în JSON.');
            }
    
            $json_file_path = $folder_path . "/bon_casa_marcat.json";
            if (!lorand_fiscal_publish_json($json_file_path, $json_data)) {
                if ($bon_fiscal_relistat_insert_id > 0) {
                    $cleanup_stmt = $pdo->prepare("DELETE FROM bonuri_casa_marcat WHERE id = :id AND de_trimis_la_casa_marcat = 1");
                    $cleanup_stmt->execute([':id' => $bon_fiscal_relistat_insert_id]);
                }
                $_SESSION['lorand_fiscal_after_url'] = 'casa_marcat_vanzare.php?nota_de_relistat=' . rawurlencode((string)$nr_bon);
                header('Location: asteapta_casa_marcat.php');
                exit;
            }
            $bon_fiscal_relistat_scris = true;
    
            $update_sql = "UPDATE bonuri_casa_marcat 
                           SET de_trimis_la_casa_marcat = 0 
                           WHERE nrbon = :nrbon AND de_trimis_la_casa_marcat = 1";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->bindParam(':nrbon', $nr_bon, PDO::PARAM_INT);
            $update_stmt->execute();
        } else {
            error_log("Client_id nu este setat în sesiune.");
        }
    
    } catch (Throwable $e) {
        error_log("Eroare la inserția/generarea bonului relistat: " . $e->getMessage());
        if ((int)$client_agecs === 8 && !$bon_fiscal_relistat_scris && $bon_fiscal_relistat_insert_id > 0) {
            try {
                $cleanup_stmt = $pdo->prepare("DELETE FROM bonuri_casa_marcat WHERE id = :id AND de_trimis_la_casa_marcat = 1");
                $cleanup_stmt->execute([':id' => $bon_fiscal_relistat_insert_id]);
            } catch (Throwable $cleanupError) {
                error_log('Curățare bon fiscal AGECSDEMO relistat nepregătit: ' . $cleanupError->getMessage());
            }
        }
    }
    
    // --- Generarea datelor pentru imprimantă la relistare ---
    try {
        if ((int)$client_agecs === 8) {
            // Relistarea fiscală retrimite doar bonul la casa de marcat. Nu generează o nouă notă BAR.
            unset($_SESSION['nr_bon'], $_SESSION['bon_procesat'], $_SESSION['numerarprim'], $_SESSION['cardprim'], $_SESSION['cif_client'], $_SESSION['rest_tichete'], $_SESSION['total_tichete'], $_SESSION['masa_curenta']);
            if ($bon_fiscal_relistat_scris) {
                $_SESSION['lorand_fiscal_after_url'] = 'vanzare_magazin.php';
                header('Location: asteapta_casa_marcat.php');
            } else {
                header('Location: vanzare_magazin.php');
            }
            exit;
        }
        $departments_sql = "
            SELECT DISTINCT ps.departament
            FROM $tabel_final_det_note dn
            JOIN $tabel_final_nomenclator ps ON dn.cod_p = ps.cod_produs
            WHERE dn.nr_bon = :nrbon
              AND ps.departament IS NOT NULL
              AND ps.departament != ''
        ";
        $departments_stmt = $pdo->prepare($departments_sql);
        $departments_stmt->execute([':nrbon' => $nr_bon]);
        $departments = $departments_stmt->fetchAll(PDO::FETCH_COLUMN);
    
        $printData = [];
    
        if (!empty($departments)) {
            $current_date = date('Y-m-d');
            $current_time = date('H:i:s');
            $de_trimis = 1;
    
            foreach ($departments as $departament_listare) {
                $products_sql = "
                    SELECT 
                        dn.pachet,
                        dn.discount,
                        dn.cod_p,
                        ps.nume,
                        ps.um,
                        dn.cantitate,
                        dn.tva_col,
                        dn.pret_vanzare,
                        dn.valoare_vanzare,
                        dn.valoare_vanzare_cu_tva,
                        ps.cota_tva,
                        ps.departament
                    FROM $tabel_final_det_note dn
                    JOIN $tabel_final_nomenclator ps ON dn.cod_p = ps.cod_produs
                    WHERE dn.nr_bon = :nrbon
                      AND ps.departament = :departament
                ";
                $products_stmt = $pdo->prepare($products_sql);
                $products_stmt->execute([':nrbon' => $nr_bon, ':departament' => $departament_listare]);
                $products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);
    
                $continut = "";
                $continut .= $den_ent . "\n";
                $continut .= $sediu . "\n";
                $continut .= "C.I.F.: " . $cod_fiscal_ent . "\n";
                $continut .= "C.I.F. CLIENT: " . $cif_client . "\n";
                $continut .= $data_bon . " " . $ora_bon . "\n";
                $continut .= "LEI\n";
                $continut .= "OPERATOR: " . $admin_firstname . " " . $admin_lastname . "\n\n";
    
                foreach ($products as $product) {
                    $pachet = $product['pachet'];
                    $pprodus = $product['nume'];
                    $produs = substr($pprodus, 0, 20);
                    $um = $product['um'];
                    $cantitate = $product['cantitate'];
                    $tva_col = $product['tva_col'];
                    $pret_vanzare = $product['pret_vanzare'];
                    $valoare_vanzare_cu_tva = $product['valoare_vanzare_cu_tva'];
                    $cota_tva = $product['cota_tva'];
                    $departament = $product['departament'];
                    $discount = $product['discount'];
    
                    $continut .= "Produs: " . $produs . "\n";
                    $continut .= "Cantitate: " . $cantitate . " " . $um . " X " . number_format($pret_vanzare, 2) . " LEI\n";
                    $continut .= "Valoare Vânzare: " . number_format($valoare_vanzare_cu_tva, 2) . " LEI\n\n";
                }
    
                $f_tot_sql = "
                    SELECT SUM(dn.valoare_vanzare_cu_tva) AS total_vanzare
                    FROM $tabel_final_det_note dn 
                    JOIN $tabel_final_nomenclator ps ON dn.cod_p = ps.cod_produs 
                    WHERE dn.nr_bon = :nrbon 
                      AND ps.departament = :departament
                ";
                $f_tot_stmt = $pdo->prepare($f_tot_sql);
                $f_tot_stmt->execute([':nrbon' => $nr_bon, ':departament' => $departament_listare]);
                $row = $f_tot_stmt->fetch(PDO::FETCH_ASSOC);
                $total_val_vz_cu_tva = $row['total_vanzare'] ?? 0;
    
                $ds_tot_sql = "
                    SELECT SUM(dn.discount) AS total_discount
                    FROM $tabel_final_det_note dn 
                    JOIN $tabel_final_nomenclator ps ON dn.cod_p = ps.cod_produs 
                    WHERE dn.nr_bon = :nrbon 
                      AND ps.departament = :departament
                ";
                $ds_tot_stmt = $pdo->prepare($ds_tot_sql);
                $ds_tot_stmt->execute([':nrbon' => $nr_bon, ':departament' => $departament_listare]);
                $row = $ds_tot_stmt->fetch(PDO::FETCH_ASSOC);
                $total_disc = $row['total_discount'] ?? 0;
    
                $total_val_vz_cu_tva = $total_val_vz_cu_tva - $total_disc;
                $continut .= "TOTAL LEI: " . number_format($total_val_vz_cu_tva, 2) . " LEI\n";
    
                if ($numerar != 0) {
                    $continut .= "Numerar: " . number_format($numerar, 2) . " LEI\n";
                }
                if ($tichete != 0) {
                    $continut .= "Tichete: " . number_format($tichete, 2) . " LEI\n";
                }
                if ($card != 0) {
                    $continut .= "Card: " . number_format($card, 2) . " LEI\n";
                }
                if ($protocol != 0) {
                    $continut .= "Protocol: " . number_format($protocol, 2) . " LEI\n";
                }
                $continut .= "Rest: " . number_format($rest, 2) . " LEI\n\n";
    
                $cote_tva_sql = "
                    SELECT 
                        ps.cota_tva,
                        ps.departament,
                        dn.tva_col 
                    FROM $tabel_final_det_note dn
                    JOIN $tabel_final_nomenclator ps ON dn.cod_p = ps.cod_produs
                    WHERE dn.nr_bon = :nrbon 
                      AND ps.departament = :departament
                ";
                $cote_tva_stmt = $pdo->prepare($cote_tva_sql);
                $cote_tva_stmt->execute([':nrbon' => $nr_bon, ':departament' => $departament_listare]);
    
                $tva_a = 0;
                $tva_b = 0;
                $tva_c = 0;
                $faratva = 0;
    
                while ($row = $cote_tva_stmt->fetch(PDO::FETCH_ASSOC)) {
                    $cota_tva = $row['cota_tva'];
                    $tva_col = $row['tva_col'];
    
                    if ($cota_tva == 19) {
                        $tva_a += $tva_col;
                    } elseif ($cota_tva == 9) {
                        $tva_b += $tva_col;
                    } elseif ($cota_tva == 5) {
                        $tva_c += $tva_col;
                    } else {
                        $faratva += $tva_col;
                    }
                }
    
                if ($tva_a > 0) {
                    $continut .= "A: TVA A (19%): " . number_format($tva_a, 3) . " LEI\n";
                }
                if ($tva_b > 0) {
                    $continut .= "B: TVA B (9%): " . number_format($tva_b, 3) . " LEI\n";
                }
                if ($tva_c > 0) {
                    $continut .= "C: TVA C (5%): " . number_format($tva_c, 3) . " LEI\n";
                }
                if ($faratva > 0) {
                    $continut .= "FARA TVA: " . number_format($faratva, 3) . " LEI\n";
                }
                $continut .= "TOTAL TVA: " . number_format($tva_a + $tva_b + $tva_c + $faratva, 3) . " LEI\n\n";
    
                $continut .= "Nr. nota: " . $nr_bon . "\n";
    
                $printData[] = [
                    'data'                    => $current_date,
                    'ora'                     => $current_time,
                    'de_trimis_la_imprimanta' => $de_trimis,
                    'nrbon'                   => $nr_bon,
                    'locatie'                 => $cod_locatie,
                    'departament_listare'     => $departament_listare,
                    'continut'                => $continut
                ];
            }
        }
    
        $json_array_imprimanta = [
            "status"  => "success",
            "message" => "Date pentru imprimantă generate cu succes.",
            "data"    => $printData
        ];
        $json_data_imprimanta = json_encode($json_array_imprimanta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
        $json_file_path_imprimanta = $folder_path . "/de_listat_la_imprimanta.json";
        file_put_contents($json_file_path_imprimanta, $json_data_imprimanta);
    
        // Resetare variabile (dacă este necesar)
        unset($_SESSION['nr_bon']);
        unset($_SESSION['bon_procesat']); // <-- CURĂȚARE LOCK
        unset($_SESSION['numerarprim']);
        unset($_SESSION['cardprim']);
        unset($_SESSION['cif_client']);
        unset($_SESSION['rest_tichete']);
        unset($_SESSION['total_tichete']);
        unset($_SESSION['masa_curenta']);
    
        printf("<script>location.href='vanzare_magazin.php'</script>");
    
    } catch (PDOException $e) {
        error_log("Eroare la generarea datelor pentru imprimantă (nota relistata): " . $e->getMessage());
    }
}
?>
