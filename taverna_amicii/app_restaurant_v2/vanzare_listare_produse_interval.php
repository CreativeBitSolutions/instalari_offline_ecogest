<?php //vanzare_listare_produse_interval.php
include('session.php');
require_once __DIR__ . '/det_note_departament_listare_schema.php';
agecs_ensure_det_note_departament_listare($pdo, $tabel_final_det_note);
$departamentListareSql = agecs_departament_listare_sql('dn', 'ps');

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
update_loading_status("Se colectează vânzările pe interval...");

try {
    // ========= CONTEXT & INPUT =========
    $cod_locatie = (int)($_SESSION['cod_locatie'] ?? 0);
    $client_id   = (int)($_SESSION['client_id']   ?? 0);
    $admin_id    = (int)($_SESSION['admin_id']    ?? 0);

    $data_start = $_GET['data_start'] ?? '';
    $ora_start  = $_GET['ora_start']  ?? '';
    $data_end   = $_GET['data_end']   ?? '';
    $ora_end    = $_GET['ora_end']    ?? '';

    $tz = new DateTimeZone('Europe/Bucharest');
    if (!$data_start || !$ora_start) {
        $s = new DateTime('today', $tz);
        $data_start = $s->format('Y-m-d'); $ora_start = '00:00';
    }
    if (!$data_end || !$ora_end) {
        $e = new DateTime('now', $tz);
        $data_end = $e->format('Y-m-d'); $ora_end = $e->format('H:i');
    }
    $from_ts = $data_start.' '.sprintf('%s:00', $ora_start);
    $to_ts   = $data_end  .' '.sprintf('%s:59', $ora_end);

    // ========= PATH & LOCKS =========
    $folder_path    = RESTAURANT_OFFLINE_API_DIR . "/" . $client_id . "/" . $cod_locatie;
    if (!is_dir($folder_path)) { mkdir($folder_path, 0777, true); }
    $json_file_path = $folder_path . "/de_listat_la_imprimanta.json";

    function wait_until_absent($path, $timeout = 60, $step = 5) {
        $waited = 0;
        while (file_exists($path) && $waited < $timeout) {
            sleep($step);
            $waited += $step;
        }
    }

    // ========= QUERY DATA BRUTĂ =========
    $sql = "
        SELECT 
            UPPER(COALESCE({$departamentListareSql}, 'ALTELE')) AS departament_raw,
            dn.nume_produs AS produs,
            SUM(dn.cantitate) AS cantitate,
            SUM(dn.valoare_vanzare_cu_tva) AS valoare
        FROM det_note dn
        JOIN note n  ON n.nrbon = dn.nr_bon
        LEFT JOIN produse_servicii ps ON ps.cod_produs = dn.cod_p
        WHERE n.locatie = :loc
          AND n.status  = 'F'
          AND n.operator = :op
          AND TIMESTAMP(n.data_bon, n.ora_bon) BETWEEN :from_ts AND :to_ts
        GROUP BY departament_raw, dn.nume_produs
        ORDER BY departament_raw ASC, dn.nume_produs ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':loc'     => $cod_locatie,
        ':op'      => $admin_id,
        ':from_ts' => $from_ts,
        ':to_ts'   => $to_ts
    ]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ========= LOGICA DE SPARGE ȘI AGREGARE (REPARTIZARE) =========
    
    $finalData = [];

    foreach ($rows as $r) {
        $rawDept = $r['departament_raw'];
        $targets = explode(',', $rawDept);
        
        // Verificăm dacă produsul curent este setat pe mai multe departamente
        // (adică dacă split-ul a generat mai mult de 1 element)
        $is_multi_dep = (count($targets) > 1);

        foreach ($targets as $tgt) {
            $tgt = trim($tgt);
            if ($tgt === '') continue;

            if (!isset($finalData[$tgt])) {
                $finalData[$tgt] = [];
            }

            $prod = $r['produs'];

            if (!isset($finalData[$tgt][$prod])) {
                $finalData[$tgt][$prod] = [
                    'produs'    => $prod,
                    'cantitate' => 0.0,
                    'valoare'   => 0.0,
                    'is_multi'  => false // Default e fals
                ];
            }

            // Agregăm sumele
            $finalData[$tgt][$prod]['cantitate'] += (float)$r['cantitate'];
            $finalData[$tgt][$prod]['valoare']   += (float)$r['valoare'];
            
            // Dacă acest rând vine dintr-o configurație multi-departament, marcăm produsul.
            // Folosim operatorul `|=` sau `if` pentru a ne asigura că dacă devine true, rămâne true.
            if ($is_multi_dep) {
                $finalData[$tgt][$prod]['is_multi'] = true;
            }
        }
    }

    ksort($finalData);

    // ========= BUILDER CONȚINUT =========
    $intervalHuman = sprintf(
        '%s %s — %s %s',
        date('d.m.Y', strtotime($data_start)), substr($ora_start,0,5),
        date('d.m.Y', strtotime($data_end)),   substr($ora_end,0,5)
    );
    $nf3 = fn($n) => number_format((float)$n, 3);
    $nf2 = fn($n) => number_format((float)$n, 2);

    $makeContent = function($deptName, $productsMap) use ($cod_locatie, $admin_id, $intervalHuman, $nf2, $nf3) {
        ksort($productsMap);

        $out  = "RAPORT PRODUSE VÂNDUTE\n";
        $out .= date('Y-m-d H:i:s') . "\n";
        $out .= "Locația: {$cod_locatie}\n";
        $out .= "Operator: {$admin_id}\n"; 
        $out .= "Interval: {$intervalHuman}\n";
        $out .= "DEPARTAMENT: {$deptName}\n";
        $out .= "--------------------------------\n";
        $out .= sprintf("%-28s %7s %7s %10s\n", "PRODUS", "CANT", "PRET", "VALOARE");
        $out .= "--------------------------------\n";

        $total = 0.0;
        foreach ($productsMap as $it) {
            $prodName = $it['produs'];
            
            // LOGICA NOUĂ: Adăugăm sufixul dacă produsul este multi-departament
            if (!empty($it['is_multi']) && $it['is_multi'] === true) {
                // Notă: Textul lung s-ar putea să fie tăiat de limita de 28 caractere de mai jos.
                // Putem folosi un text mai scurt gen "(+alte dep)" sau lăsăm textul lung
                // știind că funcția mb_strimwidth va pune "..." dacă e prea lung.
                $prodName .= " (list alte dep)";
            }

            $cant = $it['cantitate'];
            $val  = $it['valoare'];
            $pret = ($cant > 0) ? ($val / $cant) : 0.0;

            // Folosim mb_strimwidth pentru a tăia numele dacă devine prea lung cu tot cu paranteză
            $out .= sprintf("%-28s %7s %7s %10s\n",
                mb_strimwidth($prodName, 0, 28, '..', 'UTF-8'),
                $nf3($cant),
                $nf2($pret),
                $nf2($val)
            );
            $total += $val;
        }
        $out .= "--------------------------------\n";
        $out .= "TOTAL: " . $nf2($total) . " LEI\n";
        $out .= "====================\n";
        return $out;
    };

    $nowDate = date('Y-m-d');
    $nowTime = date('H:i:s');

    // ========= SCRIERE SECVENȚIALĂ DINAMICĂ =========
    
    if (!empty($finalData)) {
        foreach ($finalData as $dept => $items) {
            if (empty($items)) continue;

            update_loading_status("Se așteaptă și se trimite raportul pentru departamentul: <strong>" . htmlspecialchars($dept) . "</strong>...");
            wait_until_absent($json_file_path, 60, 5);

            $content = $makeContent($dept, $items);
            $payload = [
                'status'  => 'success',
                'message' => "Raport ($dept) generat.",
                'data'    => [[
                    'id'                      => 0,
                    'data'                    => $nowDate,
                    'ora'                     => $nowTime,
                    'de_trimis_la_imprimanta' => 1,
                    'nrbon'                   => 0,
                    'locatie'                 => (int)$cod_locatie,
                    'departament_listare'     => $dept,
                    'continut'                => $content
                ]]
            ];
            file_put_contents($json_file_path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            wait_until_absent($json_file_path, 60, 5);
            usleep(200 * 1000); 
        }
    } else {
        update_loading_status("Nicio vânzare în interval. Se listează confirmarea...");
        wait_until_absent($json_file_path, 60, 5);
        $content = "RAPORT PRODUSE VÂNDUTE\n{$nowDate} {$nowTime}\nLocația: {$cod_locatie}\nOperator: {$admin_id}\nInterval: {$intervalHuman}\n---\nNu există vânzări în interval.\n====================\n";
        $payload = [
            'status'  => 'success',
            'message' => 'Nu există vânzări.',
            'data'    => [[
                'id'                      => 0,
                'data'                    => $nowDate,
                'ora'                     => $nowTime,
                'de_trimis_la_imprimanta' => 1,
                'nrbon'                   => 0,
                'locatie'                 => (int)$cod_locatie,
                'departament_listare'     => 'BAR',
                'continut'                => $content
            ]]
        ];
        file_put_contents($json_file_path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

} catch (Exception $ex) {
    error_log("[".date("Y-m-d H:i:s")."] Eroare raport: ".$ex->getMessage()." in ".__FILE__.":".__LINE__."\n");
}

update_loading_status("Listare finalizată! Redirecționăm...");
echo "<script>location.href='vanzare_restaurant.php'</script>";
?>
