<?php
include('database_connection.php');
session_start();

try {
    // Obținem parametrii din POST și din sesiune
    $nr_bon      = isset($_POST['nota_de_relistat']) ? intval($_POST['nota_de_relistat']) : 0;
    $cod_locatie = isset($_SESSION['cod_locatie']) ? intval($_SESSION['cod_locatie']) : 0;
    $client_id   = $_SESSION['client_id'];

    // Preluăm datele notei din tabela note
    $sql_note = "SELECT * FROM note WHERE nrbon = :nrbon";
    $stmt_note = $pdo->prepare($sql_note);
    $stmt_note->execute([':nrbon' => $nr_bon]);
    $noteRow = $stmt_note->fetch(PDO::FETCH_ASSOC);

    if (!$noteRow) {
        error_log("Nota pentru casă cu nrbon " . $nr_bon . " nu a fost găsită.");
        exit("Nota pentru casă nu a fost găsită.");
    }

    // Preluăm câmpurile necesare din nota
    $data_bon    = $noteRow['data_bon'];
    $ora_bon     = $noteRow['ora_bon'];
    $cif_client  = $noteRow['cif_client'];
    $masa_curenta= $noteRow['cod_masa'];
    $adm_id      = $noteRow['operator'];
    $numerar     = $noteRow['numerar'];
    $card        = $noteRow['card'];
    $tichete     = $noteRow['tichete'];
    $protocol    = $noteRow['protocol'];
    $rest        = $noteRow['rest'];
    // Pentru locație se preia din nota
    $cod_locatie = $noteRow['locatie'];

    // Definim calea către folderul unde se va salva fișierul JSON
    $folder_path = RESTAURANT_OFFLINE_API_DIR . "/" . $client_id . "/" . $cod_locatie;
    if (!is_dir($folder_path)) {
        mkdir($folder_path, 0777, true);
    }

    // Preluăm numele mesei
    $nume_masa = "";
    $masa_sql = "SELECT nume_masa FROM mese WHERE cod_masa = :cod_masa LIMIT 1";
    $masa_stmt = $pdo->prepare($masa_sql);
    $masa_stmt->execute([':cod_masa' => $masa_curenta]);
    $masa_data = $masa_stmt->fetch(PDO::FETCH_ASSOC);
    if ($masa_data && isset($masa_data['nume_masa'])) {
        $nume_masa = $masa_data['nume_masa'];
    }

    // Preluăm datele firmei pentru antetul bonului
    $df_sql = "SELECT * FROM date_firma LIMIT 1";
    $df_stmt = $pdo->prepare($df_sql);
    $df_stmt->execute();
    $date_firma = $df_stmt->fetch(PDO::FETCH_ASSOC);
    $pseudonim_firma = $date_firma['pseudonim_firma'] ?? "";

    // Preluăm datele despre operator
    $adminQuery = "SELECT * FROM $tabel_final_admins WHERE admin_id = :adm_id LIMIT 1";
    $stmtAdmin = $pdo->prepare($adminQuery);
    $stmtAdmin->execute([':adm_id' => $adm_id]);
    $admin = $stmtAdmin->fetch(PDO::FETCH_ASSOC);
    if (!$admin) {
        error_log("Operatorul cu ID $adm_id nu a fost găsit în $tabel_final_admins.");
        $admin_firstname = "Operator";
        $admin_lastname  = "";
    } else {
        $admin_firstname = $admin['firstname'] ?? "Operator";
        $admin_lastname  = $admin['lastname'] ?? "";
    }
    // Se pot folosi valorile din sesiune dacă există
    $admin_firstname = $_SESSION['admin_firstname'] ?? $admin_firstname;
    $admin_lastname  = $_SESSION['admin_lastname'] ?? $admin_lastname;

    // Setăm data și ora curentă
    $current_date = date('Y-m-d');
    $current_time = date('H:i:s');

    // Construim conținutul bonului în stilul imprimantei NEFISCAL
    $continut = "";
    $continut .= "BON NEFISCAL" . "\n";
    $continut .= $pseudonim_firma . "\n";
    $continut .= $data_bon . " " . $ora_bon . "\n";
    $continut .= "OPERATOR: " . $admin_firstname . " " . $admin_lastname . "\n";
    $continut .= "-----\n";

    // Preluăm produsele din nota
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
        WHERE dn.nr_bon = :nrbon
    ";
    $products_stmt = $pdo->prepare($products_sql);
    $products_stmt->execute([':nrbon' => $nr_bon]);
    $products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Grupăm produsele identice (dacă observatia este goală, se combină)
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
    // Construim liniile bonului pentru fiecare produs
    foreach ($groupedProducts as $product) {
        $produs = $product['nume'];
        $observatie_produs = $product['observatie_produs'];
        $cantitate = round($product['cantitate'], 2);
        $valoare = $product['valoare_vanzare_cu_tva'];
        $total_nota += $valoare;

        $line = $produs;
        if (!empty(trim($observatie_produs))) {
            $line .= " " . $observatie_produs;
        }
        $line .= " x " . $cantitate . " = " . number_format($valoare, 2) . " LEI";
        $continut .= $line . "\n";
    }

    // Adăugăm metodele de plată dacă valoarea nu este 0
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
        $continut .= "Prot.: " . number_format($protocol, 2) . " LEI\n";
    }
    $continut .= "-----\n";

    $continut .= "TOTAL: " . number_format($total_nota, 2) . " LEI\n";

    // Calculăm și adăugăm TVA-ul pentru produsele din nota
    $sql_tva = "SELECT cota_tva, SUM(tva_col) AS total_tva FROM $tabel_final_det_note WHERE nr_bon = :nrbon GROUP BY cota_tva";
    $tva_stmt = $pdo->prepare($sql_tva);
    $tva_stmt->execute([':nrbon' => $nr_bon]);
    while($tva_row = $tva_stmt->fetch(PDO::FETCH_ASSOC)) {
        if($tva_row['total_tva'] > 0) {
            $continut .= "TVA " . $tva_row['cota_tva'] . "%: " . number_format($tva_row['total_tva'], 2) . " LEI\n";
        }
    }
    $continut .= "-----\n";

    $continut .= "Nr. nota: " . $nr_bon . "\n";
    $continut .= "-----\n";

    $continut .= "Masa: " . $nume_masa . "\n";
    $continut .= "-----\n";

    $continut .= "VĂ MULȚUMIM!";

    // Pregătim array-ul de date pentru imprimantă (departamentul BAR)
    $printData = [];
    $printData[] = [
        'data'                    => $current_date,
        'ora'                     => $current_time,
        'de_trimis_la_imprimanta' => 1,
        'nrbon'                   => $nr_bon,
        'locatie'                 => $cod_locatie,
        'departament_listare'     => "BAR",
        'continut'                => $continut
    ];

    $json_array_imprimanta = [
        "status"  => "success",
        "message" => "Date pentru imprimantă generate cu succes.",
        "data"    => $printData
    ];

    $json_data_imprimanta = json_encode($json_array_imprimanta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $json_file_path_imprimanta = $folder_path . "/de_listat_la_imprimanta.json";
    file_put_contents($json_file_path_imprimanta, $json_data_imprimanta);

    // Resetăm eventualele variabile de sesiune și redirecționăm către pagina de vânzare
    unset($_SESSION['nr_bon'], $_SESSION['numerarprim'], $_SESSION['cardprim'],$_SESSION['glovo'],  $_SESSION['cif_client'], $_SESSION['rest_tichete'], $_SESSION['total_tichete'], $_SESSION['masa_curenta']);
    printf("<script>location.href='vanzare_restaurant.php'</script>");
    
} catch (PDOException $e) {
    error_log("Eroare la generarea datelor pentru nota încasată: " . $e->getMessage());
}
?>
