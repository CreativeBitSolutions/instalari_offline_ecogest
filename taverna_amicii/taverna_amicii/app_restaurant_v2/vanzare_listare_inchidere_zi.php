<?php //vanzare_listare_inchidere_zi.php
include('session.php');

// --- FUNCȚII PENTRU ECRANUL DE AȘTEPTARE ---
function init_loading_screen() {
    while (ob_get_level()) { ob_end_clean(); }
    echo '<!DOCTYPE html><html><head><meta charset="utf-8">';
    echo '<style>body{background:#f4f7f6;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;font-family:sans-serif}.card{background:#fff;padding:30px 40px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.1);text-align:center}.spinner{margin-bottom:20px;color:#007bff;font-size:40px;display:inline-block;animation:spin 1.5s linear infinite;} @keyframes spin { 100% { transform: rotate(360deg); } } .text-muted{color:#6c757d;font-size:16px;margin-top:15px;}</style>';
    echo '</head><body>';
    echo '<div class="card"><div class="spinner">⏳</div>';
    echo '<h3>Vă rugăm așteptați...</h3>';
    echo '<p class="text-muted" id="loading-status">Se pregătesc datele...</p>';
    echo '</div>';
    echo '<script>function updateStatus(msg) { document.getElementById("loading-status").innerHTML = msg; }</script>';
    echo '</body></html>';
    flush();
}

function update_loading_status($msg) {
    echo '<script>updateStatus("' . addslashes($msg) . '");</script>';
    flush();
}
// -------------------------------------------

init_loading_screen();
update_loading_status("Se generează raportul Z din sistem...");

// --------------- RAPORT VANZARI TOTALE IMPRIMANTA TERMICA---------------

    try {
        // 0. Preia nr_raport_z din GET sau fallback la cel mai mare (MAX)
        $requested_z = isset($_GET['nr_raport_z']) ? (int)$_GET['nr_raport_z'] : null;
        $cod_locatie = isset($_SESSION['cod_locatie']) ? intval($_SESSION['cod_locatie']) : 0;

        // 1. Pregătește folderul JSON
        $client_id   = $_SESSION['client_id'];
        $folder_path = RESTAURANT_OFFLINE_API_DIR . "/" . $client_id . "/" . $cod_locatie;
        if (!is_dir($folder_path)) {
            mkdir($folder_path, 0777, true);
        }
        $json_file_path = $folder_path . "/de_listat_la_imprimanta.json";

        // 2. Așteaptă eliberarea fișierului (max 60 s, pași 10 s)
        update_loading_status("Se așteaptă eliberarea imprimantei (Raport Z)...");
        $waited = 0;
        while (file_exists($json_file_path) && $waited < 60) {
            sleep(10);
            $waited += 10;
        }

        // 3. Determină raportul Z curent: folosește GET dacă există, altfel MAX
        if ($requested_z > 0) {
            $cur_z = $requested_z;
        } else {
            $stmtZ = $pdo->prepare("SELECT MAX(nr_raport_z) FROM rapoarte_z WHERE cod_locatie = ?");
            $stmtZ->execute([$cod_locatie]);
            $cur_z = (int)$stmtZ->fetchColumn();
        }

        // 3 bis. Determină intervalul de vânzări (prima și ultima dată/ora de bon pentru raportul Z curent)
        $sqlInterval = "
            SELECT
                DATE(MIN(n.data_bon)) AS prima_data,
                DATE(MAX(n.data_bon)) AS ultima_data,
                TIME(MIN(n.ora_bon))  AS prima_ora,
                TIME(MAX(n.ora_bon))  AS ultima_ora
              FROM note n
             WHERE n.locatie     = :loc
               AND n.status      = 'F'
               AND n.nr_raport_z = :rz
        ";
        $stmtInterval = $pdo->prepare($sqlInterval);
        $stmtInterval->execute(['loc' => $cod_locatie, 'rz' => $cur_z]);
        $intervalRow = $stmtInterval->fetch(PDO::FETCH_ASSOC);
        $primaData   = $intervalRow['prima_data']  ?? '-';
        $ultimaData  = $intervalRow['ultima_data'] ?? '-';
        $primaOra    = $intervalRow['prima_ora']   ?? '-';
        $ultimaOra   = $intervalRow['ultima_ora']  ?? '-';

        // 4. Agregare produse vândute (cantitate & valoare cu TVA)
        $sqlProd = "
            SELECT ps.nume                     AS produs,
                   SUM(dn.cantitate)           AS cantitate,
                   SUM(dn.valoare_vanzare_cu_tva)      AS valoare
              FROM det_note dn
              JOIN note n  ON n.nrbon       = dn.nr_bon
              JOIN produse_servicii ps ON ps.cod_produs = dn.cod_p
             WHERE n.locatie = :loc
               AND n.status  = 'F'
               AND n.nr_raport_z = :rz
             GROUP BY ps.nume
             ORDER BY ps.nume
        ";
        $stmtProd = $pdo->prepare($sqlProd);
        $stmtProd->execute(['loc' => $cod_locatie, 'rz' => $cur_z]);
        $prodRows = $stmtProd->fetchAll(PDO::FETCH_ASSOC);

        $totalProduse = 0;
        $continut = "RAPORT Z PRODUSE VÂNDUTE\n";
        $continut .= "Nr. raport Z: {$cur_z}\n";
        $continut .= date('Y-m-d H:i:s') . "\n";                // data/ora generării raportului
        $continut .= "Interval vânzări note: {$primaData} - {$ultimaData}\n";
        $continut .= "Interval ore bonuri: {$primaOra} - {$ultimaOra}\n";
        $continut .= "Locația: {$cod_locatie}\n";
        $continut .= "---\n";

        foreach ($prodRows as $r) {
            $line = number_format($r['cantitate'], 3) . " x " . $r['produs']
                  . " = " . number_format($r['valoare'], 2) . " LEI\n";
            $continut .= $line;
            $totalProduse += $r['valoare'];
        }
        $continut .= "---\n";
        $continut .= "TOTAL PRODUSE: " . number_format($totalProduse, 2) . " LEI\n";
        $continut .= "====================\n";

       // 5. ÎNCASĂRI PE OSPĂTARI
    $sqlOp = "
        SELECT n.operator,
               COALESCE(SUM(n.numerar),  0) AS numerar,
               COALESCE(SUM(n.card),     0) AS card,
               COALESCE(SUM(n.virament_bancar),  0) AS virament_bancar
          FROM note n
         WHERE n.locatie     = :loc
           AND n.status      = 'F'
           AND n.nr_raport_z = :rz
         GROUP BY n.operator
         ORDER BY n.operator
    ";
    
    // 5 bis. Agregare bacșiș pe ospătari
    $sqlBacsis = "
        SELECT
            n.operator,
            COALESCE(SUM(dn.valoare_vanzare_cu_tva), 0) AS total_bacsis
        FROM det_note dn
        JOIN note n ON dn.nr_bon = n.nrbon
        JOIN produse_servicii ps ON dn.cod_p = ps.cod_produs
        WHERE n.locatie     = :loc
          AND n.status      = 'F'
          AND n.nr_raport_z = :rz
          AND ps.nume       = 'BACSIS'
        GROUP BY n.operator
    ";
    $stmtBacsis = $pdo->prepare($sqlBacsis);
    $stmtBacsis->execute(['loc' => $cod_locatie, 'rz' => $cur_z]);
    $bacsisRows = $stmtBacsis->fetchAll(PDO::FETCH_KEY_PAIR);


    $stmtBacsis = $pdo->prepare($sqlBacsis);
    $stmtBacsis->execute(['loc' => $cod_locatie, 'rz' => $cur_z]);
    $bacsisRows = $stmtBacsis->fetchAll(PDO::FETCH_KEY_PAIR);

    // ADĂUGAT: Total ONLINE (Glovo) pe raportul Z curent
    $sqlOnline = "
        SELECT COALESCE(SUM(n.glovo), 0) AS total_online
          FROM note n
         WHERE n.locatie     = :loc
           AND n.status      = 'F'
           AND n.nr_raport_z = :rz
    ";
    $stmtOnline = $pdo->prepare($sqlOnline);
    $stmtOnline->execute(['loc' => $cod_locatie, 'rz' => $cur_z]);
    $totalOnline = (float)$stmtOnline->fetchColumn();



    $stmtOp = $pdo->prepare($sqlOp);
    $stmtOp->execute(['loc' => $cod_locatie, 'rz' => $cur_z]);
    $opRows = $stmtOp->fetchAll(PDO::FETCH_ASSOC);

    $continut .= "ÎNCASĂRI PE OSPĂTAR\n";
    $continut .= "---\n";
    $grandNum = $grandCard = $grandTich = $grandBacsis = 0;

    // Pregătește interogarea pentru prenume și nume din admins_12
    $stmtName = $pdo->prepare("
        SELECT admin_firstname, admin_lastname
          FROM admins_12
         WHERE admin_id = :id
         LIMIT 1
    ");

    foreach ($opRows as $o) {
        // Obține separat firstname și lastname și concatenează în PHP împreună cu ID-ul
        $stmtName->execute(['id' => (int)$o['operator']]);
        $nameRow = $stmtName->fetch(PDO::FETCH_ASSOC);

        if ($nameRow) {
            $numeOperator = (int)$o['operator']
                          . ' ' . $nameRow['admin_firstname']
                          . ' ' . $nameRow['admin_lastname'];
        } else {
            // Dacă nu există în admins_12, fallback simplu cu ID-ul
            $numeOperator = 'Op ' . (int)$o['operator'];
        }

        // Preluăm bacșișul din array-ul creat anterior
        $bacsisOperator = $bacsisRows[$o['operator']] ?? 0;

        $totalOp = $o['numerar'] + $o['card'] + $o['virament_bancar'];
        $lineOp = $numeOperator . " : "
                . number_format($totalOp, 2) . " LEI"
                . " (Numerar " . number_format($o['numerar'], 2)
                . " | Card "   . number_format($o['card'],    2)
                . " | Virament bancar " . number_format($o['virament_bancar'], 2)
                . " | Bacșiș " . number_format($bacsisOperator, 2) . ")\n";

        $continut .= $lineOp;
        $continut .= "---\n";

        $grandNum    += $o['numerar'];
        $grandCard   += $o['card'];
        $grandTich   += $o['virament_bancar'];
        $grandBacsis += $bacsisOperator;
    }

    $continut .= "TOTAL ÎNCASĂRI:      "
               . number_format($grandNum + $grandCard + $grandTich, 2) . " LEI\n";
    $continut .= "TOTAL NUMERAR:       " . number_format($grandNum, 2) . " LEI\n";
    $continut .= "TOTAL CARD:          " . number_format($grandCard, 2) . " LEI\n";
    $continut .= "TOTAL VIRAMENT BANCAR: " . number_format($grandTich, 2) . " LEI\n";
    $continut .= "TOTAL BACȘIȘ:        " . number_format($grandBacsis, 2) . " LEI\n";
    $continut .= "TOTAL ONLINE:        " . number_format($totalOnline, 2) . " LEI\n";

    $continut .= "====================\n";


        // 6. Construiește structura JSON
        $printData = [[
            'id'                      => 0,
            'data'                    => date('Y-m-d'),
            'ora'                     => date('H:i:s'),
            'de_trimis_la_imprimanta' => 1,
            'nrbon'                   => 0,
            'locatie'                 => (int)$cod_locatie,
            'departament_listare' => 'BAR',
            'continut'                => $continut
        ]];

        $json_array = [
            'status'  => 'success',
            'message' => 'Raport Z pentru imprimantă generat.',
            'data'    => $printData
        ];

        file_put_contents($json_file_path, json_encode($json_array, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    } catch (PDOException $ex) {
        error_log("[".date("Y-m-d H:i:s")."] Eroare raport termic: "
                  . $ex->getMessage()
                  . " în " . __FILE__ . ":" . __LINE__ . "\n",
                  3, $logFile);
    }



// ------------- SFÂRȘIT RAPORT TERMIC -------------

   // error_log("=== DEBUG VARIABLES BEFORE REDIRECT ===\n" . print_r(get_defined_vars(), true));

update_loading_status("Raport procesat! Deconectare...");

// După orice situație, redirecționăm către logout.php
echo "<script>location.href='logout.php'</script>";
?>
