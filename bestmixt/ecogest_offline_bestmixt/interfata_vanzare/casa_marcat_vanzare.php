<?php // casa_marcat_vanzare.php
ini_set('display_errors', 0); // Nu afișează erorile utilizatorului
ini_set('log_errors', 1); // Activează logarea erorilor
ini_set('error_log', 'error_log.log'); // Specifică calea către fișierul de log
include('session.php');
require_once __DIR__ . '/offline_api_path.php';
require_once __DIR__ . '/offline_fiscal_flow_helper.php';

$bestmixtClientId = (int)($_SESSION['client_id'] ?? 0);
$bestmixtLocationId = (int)($_SESSION['cod_locatie'] ?? 0);
$bestmixtRequestedRelist = (int)($_POST['nota_de_relistat'] ?? $_GET['nota_de_relistat'] ?? 0);
if ($bestmixtClientId === 21 && is_file(bestmixt_fiscal_queue_path($bestmixtClientId, $bestmixtLocationId))) {
    $_SESSION['bestmixt_fiscal_after_url'] = $bestmixtRequestedRelist > 0
        ? 'casa_marcat_vanzare.php?nota_de_relistat=' . rawurlencode((string)$bestmixtRequestedRelist)
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
$nota_de_relistat = $bestmixtRequestedRelist;

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
        if (isset($_SESSION['client_id']) && (int)$_SESSION['client_id'] === 8 && $notaRow) {
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
            if (!bestmixt_fiscal_publish_json($json_file_path, $json_data)) {
                if ($bon_fiscal_insert_id > 0) {
                    $cleanup_stmt = $pdo->prepare("DELETE FROM bonuri_casa_marcat WHERE id = :id AND de_trimis_la_casa_marcat = 1");
                    $cleanup_stmt->execute([':id' => $bon_fiscal_insert_id]);
                }
                $_SESSION['bestmixt_fiscal_after_url'] = 'casa_marcat_vanzare.php';
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
            throw new RuntimeException('Client_id nu este setat în sesiune.');
        }
    
    } catch (Throwable $e) {
        error_log("Eroare la inserția/generarea bonului fiscal: " . $e->getMessage());
        if ($bon_fiscal_json_scris) {
            // Publicarea a reușit. O eroare SQL ulterioară nu autorizează retrimiterea bonului.
            unset($_SESSION['nr_bon'], $_SESSION['bon_procesat'], $_SESSION['numerarprim'], $_SESSION['cardprim'],
                $_SESSION['cif_client'], $_SESSION['rest_tichete'], $_SESSION['total_tichete'], $_SESSION['masa_curenta']);
            $_SESSION['bestmixt_fiscal_after_url'] = 'vanzare_magazin.php';
            header('Location: asteapta_casa_marcat.php');
            exit;
        }
        if ((int)$client_agecs === 21 && !$bon_fiscal_json_scris && $bon_fiscal_insert_id > 0) {
            try {
                $cleanup_stmt = $pdo->prepare("DELETE FROM bonuri_casa_marcat WHERE id = :id AND de_trimis_la_casa_marcat = 1");
                $cleanup_stmt->execute([':id' => $bon_fiscal_insert_id]);
            } catch (Throwable $cleanupError) {
                error_log('Curățare bon fiscal Bestmixt nepregătit: ' . $cleanupError->getMessage());
            }
        }
        unset($_SESSION['bon_procesat']);
        http_response_code(503);
        echo '<meta charset="utf-8"><p>Bonul fiscal nu a putut fi pregătit. Vânzarea nu trebuie încasată din nou.</p>';
        echo '<p>Verificați jurnalul local și spațiul disponibil, apoi reîncercați numai trimiterea fiscală.</p>';
        echo '<a href="casa_marcat_vanzare.php' . ($bestmixtRequestedRelist > 0 ? '?nota_de_relistat=' . $bestmixtRequestedRelist : '') . '">Reîncearcă trimiterea fiscală</a>';
        exit;
    }
    
    // Bestmixt nu are imprimantă pentru note de plată. Se așteaptă numai preluarea fiscală.
    unset($_SESSION['nr_bon'], $_SESSION['bon_procesat'], $_SESSION['numerarprim'], $_SESSION['cardprim'],
        $_SESSION['cif_client'], $_SESSION['rest_tichete'], $_SESSION['total_tichete'], $_SESSION['masa_curenta']);
    $_SESSION['bestmixt_fiscal_after_url'] = 'vanzare_magazin.php';
    header('Location: asteapta_casa_marcat.php');
    exit;

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
        if (isset($_SESSION['client_id']) && (int)$_SESSION['client_id'] === 8 && $noteRow) {
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
            if (!bestmixt_fiscal_publish_json($json_file_path, $json_data)) {
                if ($bon_fiscal_relistat_insert_id > 0) {
                    $cleanup_stmt = $pdo->prepare("DELETE FROM bonuri_casa_marcat WHERE id = :id AND de_trimis_la_casa_marcat = 1");
                    $cleanup_stmt->execute([':id' => $bon_fiscal_relistat_insert_id]);
                }
                $_SESSION['bestmixt_fiscal_after_url'] = 'casa_marcat_vanzare.php?nota_de_relistat=' . rawurlencode((string)$nr_bon);
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
            throw new RuntimeException('Client_id nu este setat în sesiune.');
        }
    
    } catch (Throwable $e) {
        error_log("Eroare la inserția/generarea bonului relistat: " . $e->getMessage());
        if ($bon_fiscal_relistat_scris) {
            // Publicarea a reușit. O eroare SQL ulterioară nu autorizează retrimiterea bonului.
            unset($_SESSION['nr_bon'], $_SESSION['bon_procesat'], $_SESSION['numerarprim'], $_SESSION['cardprim'],
                $_SESSION['cif_client'], $_SESSION['rest_tichete'], $_SESSION['total_tichete'], $_SESSION['masa_curenta']);
            $_SESSION['bestmixt_fiscal_after_url'] = 'vanzare_magazin.php';
            header('Location: asteapta_casa_marcat.php');
            exit;
        }
        if ((int)$client_agecs === 21 && !$bon_fiscal_relistat_scris && $bon_fiscal_relistat_insert_id > 0) {
            try {
                $cleanup_stmt = $pdo->prepare("DELETE FROM bonuri_casa_marcat WHERE id = :id AND de_trimis_la_casa_marcat = 1");
                $cleanup_stmt->execute([':id' => $bon_fiscal_relistat_insert_id]);
            } catch (Throwable $cleanupError) {
                error_log('Curățare bon fiscal Bestmixt relistat nepregătit: ' . $cleanupError->getMessage());
            }
        }
        unset($_SESSION['bon_procesat']);
        http_response_code(503);
        echo '<meta charset="utf-8"><p>Bonul fiscal nu a putut fi pregătit. Vânzarea nu trebuie încasată din nou.</p>';
        echo '<p>Verificați jurnalul local și spațiul disponibil, apoi reîncercați numai trimiterea fiscală.</p>';
        echo '<a href="casa_marcat_vanzare.php' . ($bestmixtRequestedRelist > 0 ? '?nota_de_relistat=' . $bestmixtRequestedRelist : '') . '">Reîncearcă trimiterea fiscală</a>';
        exit;
    }

    // Bestmixt nu are imprimantă pentru note de plată. Se așteaptă numai preluarea fiscală.
    unset($_SESSION['nr_bon'], $_SESSION['bon_procesat'], $_SESSION['numerarprim'], $_SESSION['cardprim'],
        $_SESSION['cif_client'], $_SESSION['rest_tichete'], $_SESSION['total_tichete'], $_SESSION['masa_curenta']);
    $_SESSION['bestmixt_fiscal_after_url'] = 'vanzare_magazin.php';
    header('Location: asteapta_casa_marcat.php');
    exit;
}
?>
