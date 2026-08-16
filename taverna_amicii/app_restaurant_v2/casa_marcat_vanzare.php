<?php //casa_marcat_vanzare.php
ini_set('display_errors', 0); // Nu afișează erorile utilizatorului
ini_set('log_errors', 1); // Activează logarea erorilor
ini_set('error_log', 'error_log.log'); // Specifică calea către fișierul de log
include('session.php');
error_reporting(E_ALL); // Raportează toate tipurile de erori
// Presupunem că variabila nota_de_relistat vine din sesiune sau este definită undeva

// === Helperi pentru formatare zecimale (ADĂUGAT) ===
if (!function_exists('fmt2')) {
    function fmt2($v) {
        return number_format((float)$v, 2, '.', '');
    }
}
if (!function_exists('fmt3max')) {
    // până la 3 zecimale, fără zerouri forțate
    function fmt3max($v) {
        $s = number_format((float)$v, 3, '.', '');
        $s = rtrim(rtrim($s, '0'), '.');
        return $s === '' ? '0' : $s;
    }
}
if (!isset($_POST['nota_de_relistat'])) {

    // === Codul tău existent pentru situația în care nota_de_relistat NU este setată ===

    // Inițializarea variabilelor...

    
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
    $cod_locatie = isset($_SESSION['cod_locatie']) ? intval($_SESSION['cod_locatie']) : 0;
    
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
    $omitH = (isset($_SESSION['client_id']) && intval($_SESSION['client_id']) === 23); // (ADĂUGAT)

    $nr_bon = $_SESSION['nr_bon'] ?? '';
    $cu_bacsis = 0;
    
    // Se execută interogarea pentru produsele bonului curent
 $f_sql = "SELECT 
             $tabel_final_det_note.pachet,
             $tabel_final_det_note.discount,
             $tabel_final_det_note.cod_p,
             $tabel_final_nomenclator.nume,
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
             $tabel_final_det_note.nr_bon = :nr_bon and $tabel_final_det_note.pret_vanzare>0;";
    
    $f_stmt = $pdo->prepare($f_sql);
    $f_stmt->execute([':nr_bon' => $nr_bon]);
    $cif_client = $_SESSION['cif_client'];
    
    // (MODIFICAT pentru a omite H când client_id=23)
    if ($cif_client) {
        $myBuffer = $K . $cif_client . $cr;
        if (!$omitH) { $myBuffer .= $H . $cr; }
    } else {
        $myBuffer = $omitH ? '' : $H . $cr;
    }
    
    while ($row = $f_stmt->fetch(PDO::FETCH_ASSOC)) {
        $pachet = $row['pachet'] ?? 0;
        $pret_vanzare = $row['pret_vanzare'] ?? 0;
        $dep_casa = $row['dep_casa'] ?? 0;
        $cod_p = $row['cod_p'] ?? 0;
    $cota_tva=$row['cota'];
        if ($cod_p == 9999) {
            $cu_bacsis = 1;
            $bacsis = $pret_vanzare;
        }
    
        $um = $row['um'] ?? '';
        if ($um === 'H87') { $um = 'BUC'; }

        $produs = substr(($row['nume'] ?? '') . ($cota_tva == 9 && $pachet == 1 ? " P" : ''), 0, 22);
        $cantitate = $row['cantitate'] ?? 0;
    
        $valoare_vanzare = $pret_vanzare * $cantitate;
        $discount = $row['discount'] ?? 0;
       
        if ($discount > 0) {
            // indiferent de suma discountului lăsăm la fel ca să nu se deregleze la casa de marcat
            $pret_vanzare_fin = round($pret_vanzare,2);
        } else {
            // Calculul original
            $pret_vanzare_fin = round($pret_vanzare,2);
        }
                        $client_agecs = $_SESSION['client_id']; 
if ($client_agecs === 8 || $client_agecs === 23) { $um = ''; }
// Citim direct departamentul din produse_servicii.dep_casa_marcat
$dept         = (int)($row['dep_casa_marcat'] ?? 0); // departament pentru casa de marcat
$cod_cota_tva = (int)($row['dep_casa'] ?? 0);      // cod cota TVA (1=A,2=B,3=C,... din cote_tva)

if($dept == 0) {
    $dept = 1; // Dacă departamentul este 0, îl setăm implicit la 1
}

// (MODIFICAT: formatăm prețul la 2 zecimale cu zerouri, cantitatea max 3 zecimale)
$pret_vanzare_fin_fmt = fmt2($pret_vanzare_fin);
$cantitate_fmt = fmt3max($cantitate);

$myBuffer .= "S,1,______,_,__;"
          . "$produs;$pret_vanzare_fin_fmt;$cantitate_fmt;$dept;$dept;$cod_cota_tva;0;0;$um"
          . $cr;
        
        }
    
    $paymentTypes = [
        'numerarprim' => 0,
        'cardprim' => 1,
        'glovo' => 6,
        'total_tichete' => 3,
    ];
    
    foreach ($paymentTypes as $key => $type) {
        if (!empty($_SESSION[$key])) {
            // (MODIFICAT: formatare 2 zecimale cu zerouri)
            $amount = (float)$_SESSION[$key];
            $myBuffer .= $T . "$type;" . fmt2($amount) . ";;;;" . $cr;
        }
    }
    date_default_timezone_set("Europe/Bucharest");
    
    // --- Inserția în tabela bonuri_casa_marcat și generarea fișierelor JSON (codul existent) ---
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
        $id_bon_casa_marcat = (int)$pdo->lastInsertId();
    
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
    
            $folder_path = RESTAURANT_OFFLINE_API_DIR . "/" . $client_id . "/" . $locatie_val;
    
            if (!is_dir($folder_path)) {
                mkdir($folder_path, 0777, true);
            }
    
            $json_file_path = $folder_path . "/bon_casa_marcat.json";


            // --- START: LOGICA DE AȘTEPTARE PENTRU FIȘIERUL CASEI DE MARCAT ---

// Așteptăm în pași de 10 secunde, până la maximum 60 secunde, dacă fișierul există
$totalWait = 0;
while (file_exists($json_file_path) && $totalWait < 60) {
    sleep(10); // Așteaptă 10 secunde
    clearstatcache(true, $json_file_path);
    $totalWait = $totalWait + 10;
}


// --- END: LOGICA DE AȘTEPTARE ---
            if (file_exists($json_file_path)) {
                if (!empty($id_bon_casa_marcat)) {
                    $delete_stmt = $pdo->prepare("DELETE FROM bonuri_casa_marcat WHERE id = :id");
                    $delete_stmt->execute([':id' => $id_bon_casa_marcat]);
                }
                error_log("Fisierul bon_casa_marcat.json exista deja si nu a fost procesat in 60 secunde: " . $json_file_path);
                echo "<script>alert('Casa de marcat inca proceseaza bonul anterior. Incasarea nu a fost retrimisa. Reincercati dupa cateva secunde.');location.href='vanzare_restaurant.php';</script>";
                exit;
            }

            file_put_contents($json_file_path, $json_data); // scriere protejata de verificarea de mai sus
    
            $update_sql = "UPDATE bonuri_casa_marcat 
                           SET de_trimis_la_casa_marcat = 0 
                           WHERE nrbon = :nrbon AND de_trimis_la_casa_marcat = 1";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->bindParam(':nrbon', $nr_bon, PDO::PARAM_INT);
            $update_stmt->execute();
        } else {
            error_log("Client_id nu este setat în sesiune.");
        }
    
    } catch (PDOException $e) {
        error_log("Eroare la inserția bonului: " . $e->getMessage());
    }
    
    // === MODIFICARE: Generarea datelor pentru imprimantă pentru bonul NEFISCAL ===
    try {
        // Pentru bonurile nefiscale se trimit toate produsele pe o singură imprimantă (departamentul BAR)
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
                dn.observatie_produs
            FROM $tabel_final_det_note dn
            JOIN $tabel_final_nomenclator ps ON dn.cod_p = ps.cod_produs
            WHERE dn.nr_bon = :nrbon and dn.pret_vanzare>0
        ";
        $products_stmt = $pdo->prepare($products_sql);
        $products_stmt->execute([':nrbon' => $nr_bon]);
        $products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);
    
        $printData = [];
        $current_date = date('Y-m-d');
        $current_time = date('H:i:s');
        $de_trimis = 1;
        $df_sql = "SELECT * FROM date_firma LIMIT 1";
        $df_stmt = $pdo->prepare($df_sql);
        $df_stmt->execute();
        $date_firma = $df_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Variabile pentru antet - se folosește doar pseudonimul firmei
        $pseudonim_firma = $date_firma['pseudonim_firma'] ?? "";
        // MODIFICARE: Design prescurtat și gruparea produselor (combinare dacă nu au observații)
        $continut = "";
        $continut .= "BON NEFISCAL" . "\n";
        $continut .= $pseudonim_firma . "\n";
        $continut .= $data_bon . " " . $ora_bon . "\n";
        $continut .= "OPERATOR: " . $admin_firstname . " " . $admin_lastname . "\n";
        $continut .= "-----\n";
    
        $groupedProducts = [];
        foreach ($products as $product) {
            $obs = trim($product['observatie_produs']);
            if ($obs === "") {
                $key = $product['nume'];
            } else {
                $key = $product['nume'] . "_" . $obs . "_" . uniqid();
            }
            if (!isset($groupedProducts[$key])) {
                $groupedProducts[$key] = $product;
            } else {
                $groupedProducts[$key]['cantitate'] += $product['cantitate'];
                $groupedProducts[$key]['valoare_vanzare_cu_tva'] += $product['valoare_vanzare_cu_tva'];
            }
        }
    
        $total_nota = 0;
        foreach ($groupedProducts as $product) {
            $produs = $product['nume'];
            $observatie_produs = $product['observatie_produs'];
            $cantitate = round($product['cantitate'], 2); // (lăsată linia existentă)
            $valoare = $product['valoare_vanzare_cu_tva'];
            $total_nota += $valoare;

            // (ADĂUGAT: formatare cantitate max 3 zecimale, valoare 2 zecimale cu zerouri)
            $cantitate_fmt_nf = fmt3max($product['cantitate']);
    
            $line = $produs;
            if (!empty(trim($observatie_produs))) {
                $line .= " " . $observatie_produs;
            }
            $line .= " x " . $cantitate_fmt_nf . " = " . fmt2($valoare) . " LEI";
            $continut .= $line . "\n";
        }
    
        // Adăugare: Selectăm datele notei pentru a prelua valorile metodelor de plată și a masei
        $sql_note = "SELECT * FROM note WHERE nrbon = :nrbon";
        $stmt_note = $pdo->prepare($sql_note);
        $stmt_note->execute([':nrbon' => $nr_bon]);
        $noteRow = $stmt_note->fetch(PDO::FETCH_ASSOC);
        if (!$noteRow) {
            error_log("Nota pentru casa cu nrbon " . $nr_bon . " nu a fost găsită.");
            exit("Nota pentru casa nu a fost găsită.");
        }
        // Preluăm valorile de plată și masa din nota găsită
        $masa_nota = $noteRow['cod_masa'];
        $numerar = $noteRow['numerar'];
        $card = $noteRow['card'];
        $tichete = $noteRow['tichete'];
        $protocol = $noteRow['protocol'];
    
        if ($numerar != 0) {
            $continut .= "Numerar: " . fmt2($numerar) . " LEI\n";
        }
        if ($tichete != 0) {
            $continut .= "Tichete: " . fmt2($tichete) . " LEI\n";
        }
        if ($card != 0) {
            $continut .= "Card: " . fmt2($card) . " LEI\n";
        }
        if ($protocol != 0) {
            $continut .= "Prot.: " . fmt2($protocol) . " LEI\n";
        }
    
        $nume_masa = "";
        $masa_sql = "SELECT nume_masa FROM mese WHERE cod_masa = :cod_masa LIMIT 1";
        $masa_stmt = $pdo->prepare($masa_sql);
        $masa_stmt->execute([':cod_masa' => $masa_nota]);
        $masa_data = $masa_stmt->fetch(PDO::FETCH_ASSOC);
        if ($masa_data && isset($masa_data['nume_masa'])) {
            $nume_masa = $masa_data['nume_masa'];
        }
    
     
        $continut .= "-----\n";
        $continut .= "TOTAL: " . fmt2($total_nota) . " LEI\n";
        // Nou: Afișăm totalul TVA pe fiecare tip (doar dacă totalul este diferit de 0)
        $sql_tva = "SELECT cota_tva, SUM(tva_col) AS total_tva FROM $tabel_final_det_note WHERE nr_bon = :nrbon GROUP BY cota_tva";
        $tva_stmt = $pdo->prepare($sql_tva);
        $tva_stmt->execute([':nrbon' => $nr_bon]);
        while($tva_row = $tva_stmt->fetch(PDO::FETCH_ASSOC)) {
            if($tva_row['total_tva'] > 0) {
                $continut .= "TVA " . $tva_row['cota_tva'] . "%: " . fmt2($tva_row['total_tva']) . " LEI\n";
            }
        }
        $continut .= "-----\n";

        $continut .= "Nr. nota: " . $nr_bon . "\n";
        $continut .= "-----\n";

        $continut .= "Masa: " . $nume_masa . "\n";
        $continut .= "-----\n";
        $continut .= "VĂ MULȚUMIM!";

        $printData[] = [
            'data'                => $current_date,
            'ora'                 => $current_time,
            'de_trimis_la_imprimanta' => $de_trimis,
            'nrbon'               => 0,
            'locatie'             => $cod_locatie,
            'departament_listare' => "BAR",
            'continut'            => $continut
        ];
    
        $json_array_imprimanta = [
            "status"  => "success",
            "message" => "Date pentru imprimantă generate cu succes.",
            "data"    => $printData
        ];
        $json_data_imprimanta = json_encode($json_array_imprimanta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
        $json_file_path_imprimanta = $folder_path . "/de_listat_la_imprimanta.json";

// Verificăm dacă client_id este 8 înainte de a salva fișierul
if ($_SESSION['client_id'] == 8) {
    file_put_contents($json_file_path_imprimanta, $json_data_imprimanta);
}    
        // Resetare variabile (dacă este necesar)
        unset($_SESSION['nr_bon']);
        unset($_SESSION['numerarprim']);
        unset($_SESSION['cardprim']);
                        unset($_SESSION['glovo']);

        unset($_SESSION['cif_client']);
        unset($_SESSION['rest_tichete']);
        unset($_SESSION['total_tichete']);
if (isset($_SESSION['cod_locatie']) && $_SESSION['cod_locatie'] == 2) {

    if (!isset($_SESSION['nextbon']) || $_SESSION['nextbon'] == 0) {
        if (isset($_SESSION['masa_curenta'])) {
            unset($_SESSION['masa_curenta']);
        }
    } else {
        $_SESSION['nr_bon'] = $_SESSION['nextbon'];
        unset($_SESSION['nextbon']);
    }

}
else{
                unset($_SESSION['masa_curenta']);

}
//redirect factura automata 
if ($client_agecs == 3 || $client_agecs==8) {
if (!empty($cif_client)) {
// Preluăm folder-ul și numele scriptului curent
        $folder = basename(__DIR__);            // ex: "app_vanzare" sau "app_restaurant"
        $script="";
        if($folder=="app_vanzare"){$script="vanzare_magazin.php";}
   elseif($folder=="app_restaurant"){$script="vanzare_restaurant.php";}
                elseif($folder=="app_vanzare_v2"){$script="vanzare_magazin.php";}
                elseif($folder=="app_restaurant_v2"){$script="vanzare_restaurant.php";}
                elseif($folder=="app_restaurant_hp"){$script="vanzare_restaurant.php";}
        $path   = $folder . '/' . $script;      // ex: "app_vanzare/casa_marcat_vanzare.php"

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

        printf("<script>location.href='vanzare_restaurant.php'</script>");
    
    } catch (PDOException $e) {
        error_log("Eroare la generarea datelor pentru imprimantă (nota relistata): " . $e->getMessage());
    }
}
else {
    // === Dacă nota_de_relistat este setată (diferită de 0): se preiau datele notei din tabela "note" ===
    $nota_de_relistat = intval($_POST['nota_de_relistat']);

    // Preluăm datele notei din baza de date folosind nrbon = $nota_de_relistat
    $sql_note = "SELECT * FROM note WHERE nrbon = :nrbon";
    $stmt_note = $pdo->prepare($sql_note);
    $stmt_note->execute([':nrbon' => $nota_de_relistat]);
    $noteRow = $stmt_note->fetch(PDO::FETCH_ASSOC);
    
    if (!$noteRow) {
        error_log("Nota de relistat la casa cu nrbon " . $nota_de_relistat . " nu a fost găsită.");
        exit("Nota de relistat la casa nu a fost găsită.");
    }
    
    // Preluăm câmpurile necesare din nota găsită
    $data_bon       = $noteRow['data_bon'];
    $ora_bon        = $noteRow['ora_bon'];
    $cif_client     = $noteRow['cif_client'];
    $cod_locatie    = $noteRow['locatie'];
    
    // Preluăm și valorile de plată din nota (numerar, card, tichete, protocol, rest)
    $numerar        = $noteRow['numerar'];
    $card           = $noteRow['card'];
    $tichete        = $noteRow['tichete'];
    $protocol       = $noteRow['protocol'];
    $rest           = $noteRow['rest'];
    
    // Alte variabile se setează similar
  
    
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
    $omitH = (isset($_SESSION['client_id']) && intval($_SESSION['client_id']) === 23); // (ADĂUGAT)

    // Folosim nota din baza de date, deci $nr_bon va fi $nota_de_relistat
    $nr_bon = $nota_de_relistat;
    
    // Se execută interogarea pentru produsele bonului (la fel ca mai sus)
  $f_sql = "SELECT 
             $tabel_final_det_note.pachet,
             $tabel_final_det_note.discount,
             $tabel_final_det_note.cod_p,
             $tabel_final_nomenclator.nume,
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
             $tabel_final_det_note.nr_bon = :nr_bon and $tabel_final_det_note.pret_vanzare>0;";
    
    $f_stmt = $pdo->prepare($f_sql);
    $f_stmt->execute([':nr_bon' => $nr_bon]);
    
    // Construim $myBuffer pornind de la CIF-ul clientului (din nota)
    // (MODIFICAT pentru a omite H când client_id=23)
    if ($cif_client) {
        $myBuffer = $K . $cif_client . $cr;
        if (!$omitH) { $myBuffer .= $H . $cr; }
    } else {
        $myBuffer = $omitH ? '' : $H . $cr;
    }
    
    while ($row = $f_stmt->fetch(PDO::FETCH_ASSOC)) {
        $pachet = $row['pachet'] ?? 0;
        $pret_vanzare = $row['pret_vanzare'] ?? 0;
        $dep_casa = $row['dep_casa'] ?? 0;
        $cod_p = $row['cod_p'] ?? 0;
        $cota_tva=$row['cota'];

        if ($cod_p == 9999) {
            $cu_bacsis = 1;
            $bacsis = $pret_vanzare;
        }
    
        $um = $row['um'] ?? '';
        if ($um === 'H87') { $um = 'BUC'; }

        $produs = substr(($row['nume'] ?? '') . ($cota_tva == 9 && $pachet == 1 ? " P" : ''), 0, 22);
        $cantitate = $row['cantitate'] ?? 0;
    
        $valoare_vanzare = $pret_vanzare * $cantitate;
        $discount = $row['discount'] ?? 0;
       
        if ($discount > 0) {
            $pret_vanzare_fin = round($pret_vanzare,2);
        } else {
            $pret_vanzare_fin = round($pret_vanzare,2);
        }
      $client_agecs = $_SESSION['client_id']; 
if ($client_agecs === 8 || $client_agecs === 23) { $um = ''; }
// Citim direct departamentul din produse_servicii.dep_casa_marcat
$dept         = (int)($row['dep_casa_marcat'] ?? 0); // departament pentru casa de marcat
$cod_cota_tva = (int)($row['dep_casa'] ?? 0);      // cod cota TVA (1=A,2=B,3=C,... din cote_tva)

if($dept == 0) {
    $dept = 1; // Dacă departamentul este 0, îl setăm implicit la 1
}

// (MODIFICAT: formatăm prețul la 2 zecimale cu zerouri, cantitatea max 3 zecimale)
$pret_vanzare_fin_fmt = fmt2($pret_vanzare_fin);
$cantitate_fmt = fmt3max($cantitate);

$myBuffer .= "S,1,______,_,__;"
          . "$produs;$pret_vanzare_fin_fmt;$cantitate_fmt;$dept;$dept;$cod_cota_tva;0;0;$um"
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
        // (MODIFICAT: formatare 2 zecimale cu zerouri)
        $myBuffer .= $T . "$type;" . fmt2((float)$noteRow[$column]) . ";;;;" . $cr;
    }
}
    
    date_default_timezone_set("Europe/Bucharest");
    
    // --- Inserția în tabela bonuri_casa_marcat și generarea fișierelor JSON ---
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
        $id_bon_casa_marcat = (int)$pdo->lastInsertId();
    
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
    
            $folder_path = RESTAURANT_OFFLINE_API_DIR . "/" . $client_id . "/" . $locatie_val;
    
            if (!is_dir($folder_path)) {
                mkdir($folder_path, 0777, true);
            }
    
            $json_file_path = $folder_path . "/bon_casa_marcat.json";
            // --- START: LOGICA DE AȘTEPTARE PENTRU FIȘIERUL CASEI DE MARCAT ---

// Așteptăm în pași de 10 secunde, până la maximum 60 secunde, dacă fișierul există
$totalWait = 0;
while (file_exists($json_file_path) && $totalWait < 60) {
    sleep(10); // Așteaptă 10 secunde
    clearstatcache(true, $json_file_path);
    $totalWait = $totalWait + 10;
}



// --- END: LOGICA DE AȘTEPTARE ---
            if (file_exists($json_file_path)) {
                if (!empty($id_bon_casa_marcat)) {
                    $delete_stmt = $pdo->prepare("DELETE FROM bonuri_casa_marcat WHERE id = :id");
                    $delete_stmt->execute([':id' => $id_bon_casa_marcat]);
                }
                error_log("Fisierul bon_casa_marcat.json exista deja si nu a fost procesat in 60 secunde: " . $json_file_path);
                echo "<script>alert('Casa de marcat inca proceseaza bonul anterior. Incasarea nu a fost retrimisa. Reincercati dupa cateva secunde.');location.href='sefsala.php';</script>";
                exit;
            }

            file_put_contents($json_file_path, $json_data); // scriere protejata de verificarea de mai sus
    
            $update_sql = "UPDATE bonuri_casa_marcat 
                           SET de_trimis_la_casa_marcat = 0 
                           WHERE nrbon = :nrbon AND de_trimis_la_casa_marcat = 1";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->bindParam(':nrbon', $nr_bon, PDO::PARAM_INT);
            $update_stmt->execute();
        } else {
            error_log("Client_id nu este setat în sesiune.");
        }
    
    } catch (PDOException $e) {
        error_log("Eroare la inserția bonului (nota relistata): " . $e->getMessage());
    }
    
    // === MODIFICARE: Generarea datelor pentru imprimantă pentru bonul NEFISCAL RELISTAT ===
    try {
        // Pentru nota relistată, se trimite totul pe o singură imprimantă, departamentul BAR
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
                dn.observatie_produs
            FROM $tabel_final_det_note dn
            JOIN $tabel_final_nomenclator ps ON dn.cod_p = ps.cod_produs
            WHERE dn.nr_bon = :nrbon and dn.pret_vanzare>0
        ";
        $products_stmt = $pdo->prepare($products_sql);
        $products_stmt->execute([':nrbon' => $nr_bon]);
        $products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);
    
        $printData = [];
        $current_date = date('Y-m-d');
        $current_time = date('H:i:s');
        $de_trimis = 1;
        $df_sql = "SELECT * FROM date_firma LIMIT 1";
        $df_stmt = $pdo->prepare($df_sql);
        $df_stmt->execute();
        $date_firma = $df_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Variabile pentru antet - se folosește doar pseudonimul firmei
        $pseudonim_firma = $date_firma['pseudonim_firma'] ?? "";
        // MODIFICARE: Design prescurtat și gruparea produselor (combinare dacă nu au observații)
        $continut = "";
        $continut .= "BON NEFISCAL" . "\n";
        $continut .= $pseudonim_firma . "\n";
        $continut .= $data_bon . " " . $ora_bon . "\n";
        $continut .= "OPERATOR: " . $admin_firstname . " " . $admin_lastname . "\n";
        $continut .= "-----\n";
    
        $groupedProducts = [];
        foreach ($products as $product) {
            $obs = trim($product['observatie_produs']);
            if ($obs === "") {
                $key = $product['nume'];
            } else {
                $key = $product['nume'] . "_" . $obs . "_" . uniqid();
            }
            if (!isset($groupedProducts[$key])) {
                $groupedProducts[$key] = $product;
            } else {
                $groupedProducts[$key]['cantitate'] += $product['cantitate'];
                $groupedProducts[$key]['valoare_vanzare_cu_tva'] += $product['valoare_vanzare_cu_tva'];
            }
        }
    
        $total_nota = 0;
        foreach ($groupedProducts as $product) {
            $produs = $product['nume'];
            $observatie_produs = $product['observatie_produs'];
            $cantitate = round($product['cantitate'], 2); // (lăsată linia existentă)
            $valoare = $product['valoare_vanzare_cu_tva'];
            $total_nota += $valoare;
    
            // (ADĂUGAT: formatare cantitate max 3 zecimale, valoare 2 zecimale cu zerouri)
            $cantitate_fmt_nf = fmt3max($product['cantitate']);

            $line = $produs;
            if (!empty(trim($observatie_produs))) {
                $line .= " " . $observatie_produs;
            }
            $line .= " x " . $cantitate_fmt_nf . " = " . fmt2($valoare) . " LEI";
            $continut .= $line . "\n";
        }
    
        // Adăugare: Selectăm datele notei pentru a prelua valorile metodelor de plată și a masei
        $sql_note = "SELECT * FROM note WHERE nrbon = :nrbon";
        $stmt_note = $pdo->prepare($sql_note);
        $stmt_note->execute([':nrbon' => $nr_bon]);
        $noteRow = $stmt_note->fetch(PDO::FETCH_ASSOC);
        if (!$noteRow) {
            error_log("Nota pentru casa cu nrbon " . $nr_bon . " nu a fost găsită.");
            exit("Nota pentru casa nu a fost găsită.");
        }
        $masa_nota = $noteRow['cod_masa'];
        $numerar = $noteRow['numerar'];
        $card = $noteRow['card'];
        $tichete = $noteRow['tichete'];
        $protocol = $noteRow['protocol'];
    
        if ($numerar != 0) {
            $continut .= "Numerar: " . fmt2($numerar) . " LEI\n";
        }
        if ($tichete != 0) {
            $continut .= "Tichete: " . fmt2($tichete) . " LEI\n";
        }
        if ($card != 0) {
            $continut .= "Card: " . fmt2($card) . " LEI\n";
        }
        if ($protocol != 0) {
            $continut .= "Prot.: " . fmt2($protocol) . " LEI\n";
        }
    
        $nume_masa = "";
        $masa_sql = "SELECT nume_masa FROM mese WHERE cod_masa = :cod_masa LIMIT 1";
        $masa_stmt = $pdo->prepare($masa_sql);
        $masa_stmt->execute([':cod_masa' => $masa_nota]);
        $masa_data = $masa_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($masa_data && isset($masa_data['nume_masa'])) {
            $nume_masa = $masa_data['nume_masa'];
        }
     
        $continut .= "-----\n";
        $continut .= "TOTAL: " . fmt2($total_nota) . " LEI\n";
        // Nou: Afișăm totalul TVA pe fiecare tip (doar dacă totalul este diferit de 0)
        $sql_tva = "SELECT cota_tva, SUM(tva_col) AS total_tva FROM $tabel_final_det_note WHERE nr_bon = :nrbon GROUP BY cota_tva";
        $tva_stmt = $pdo->prepare($sql_tva);
        $tva_stmt->execute([':nrbon' => $nr_bon]);
        while($tva_row = $tva_stmt->fetch(PDO::FETCH_ASSOC)) {
            if($tva_row['total_tva'] > 0) {
                $continut .= "TVA " . $tva_row['cota_tva'] . "%: " . fmt2($tva_row['total_tva']) . " LEI\n";
            }
        }
        $continut .= "-----\n";

        $continut .= "Nr. nota: " . $nr_bon . "\n";
        $continut .= "-----\n";

        $continut .= "Masa: " . $nume_masa . "\n";
        $continut .= "-----\n";
        $continut .= "VĂ MULȚUMIM!";
        $printData[] = [
            'data'                => $current_date,
            'ora'                 => $current_time,
            'de_trimis_la_imprimanta' => $de_trimis,
            'nrbon'               => 0,
            'locatie'             => $cod_locatie,
            'departament_listare' => "BAR",
            'continut'            => $continut
        ];
    
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
        unset($_SESSION['numerarprim']);
        unset($_SESSION['cardprim']);
                unset($_SESSION['glovo']);

        unset($_SESSION['cif_client']);
        unset($_SESSION['rest_tichete']);
        unset($_SESSION['total_tichete']);
        unset($_SESSION['masa_curenta']);
    
        printf("<script>location.href='sefsala.php'</script>");
    
    } catch (PDOException $e) {
        error_log("Eroare la generarea datelor pentru imprimantă (nota relistata): " . $e->getMessage());
    }
}
?>
