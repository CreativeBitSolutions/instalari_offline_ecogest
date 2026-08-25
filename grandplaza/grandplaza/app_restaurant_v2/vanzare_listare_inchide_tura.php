<?php //vanzare_listare_inchide_tura.php
include('session.php'); // sesiune + conexiune DB

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
update_loading_status("Generăm datele pentru închiderea de tură...");

// date esențiale din sesiune
$client_id   = $_SESSION['client_id'];
$cod_locatie = isset($_SESSION['cod_locatie']) ? intval($_SESSION['cod_locatie']) : 0;
$ultim_inch  = $_SESSION['ultim_inch'];
$adm_id=$_SESSION['admin_id'];

// calea către folderul de output
$folder_path = RESTAURANT_OFFLINE_API_DIR . "/{$client_id}/{$cod_locatie}";
if (!is_dir($folder_path)) {
    mkdir($folder_path, 0777, true);
}

try {
    // 1) Preluăm toate notele de tura
    $stmt = $pdo->prepare("
        SELECT numerar, card, tichete, protocol, glovo, virament_bancar
        FROM note
        WHERE cod_inchidere = :inchidere AND locatie = :locatie and operator=:adm_id
    ");
    $stmt->execute([
        ':inchidere' => $ultim_inch,
        ':locatie'   => $cod_locatie,
        ':adm_id'   => $adm_id        
    ]);
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2) Inițializăm agregatoarele
    $totals = [
        'numerar'          => 0.0,
        'card'             => 0.0,
        'tichete'          => 0.0,
        'protocol'         => 0.0,
        'glovo'            => 0.0,
        'virament_bancar'  => 0.0,
    ];

    // 3) Sumăm pe fiecare metodă de plată
    foreach ($notes as $n) {
        foreach ($totals as $metoda => &$suma) {
            $suma += floatval($n[$metoda]);
        }
    }
    unset($suma); // curățăm referința

    // 4) Construim conținutul de print
    $current_date = date('Y-m-d');
    $current_time = date('H:i:s');

    $continut  = "NOTĂ ÎNCHIDERE TURĂ\n";
    $continut .= "Data: {$current_date} {$current_time}\n";
    $continut .= "------------------\n";
    // Doar metodele cu sumă > 0
    foreach ($totals as $metoda => $suma) {
        if ($suma > 0) {
            // Trecem în formă prietenoasă
            $label = [
                'numerar'         => 'Numerar',
                'card'            => 'Card',
                'tichete'         => 'Tichete',
                'protocol'        => 'Protocol',
                'glovo'           => 'Online',
                'virament_bancar' => 'Virament Bancar',
            ][$metoda];
            $continut .= "{$label} total: " . number_format($suma, 2) . " LEI\n";
        }
    }
    $continut .= "==================\n";
    
// 4.1) Preluare total bacșiș pentru operator ca produs pe bon (cod_p = -1)
$stmt_tip = $pdo->prepare("
    SELECT SUM(d.pret_vanzare) AS total_bacsis
    FROM det_note d
    JOIN note n ON d.nr_bon = n.nrbon
    WHERE d.cod_p = -1
      AND n.cod_inchidere = :inchidere
      AND n.locatie       = :locatie
      AND n.operator      = :adm_id
");
$stmt_tip->execute([
    ':inchidere' => $ultim_inch,
    ':locatie'   => $cod_locatie,
    ':adm_id'    => $adm_id
]);
$total_bacsis = floatval($stmt_tip->fetchColumn());

if ($total_bacsis > 0) {
    $continut .= "BACSIS total: " . number_format($total_bacsis, 2) . " LEI\n";
}

    // 5) Adăugăm operatorul din sesiune
    $admin_firstname = $_SESSION['admin_firstname'] ?? 'Operator';
    $admin_lastname  = $_SESSION['admin_lastname']  ?? '';
    $continut       .= "OPERATOR: {$admin_firstname} {$admin_lastname}\n";

    // 6) Pregătim JSON-ul de export
    $printData = [[
        'id'                     => 0,
        'data'                   => $current_date,
        'ora'                    => $current_time,
        'de_trimis_la_imprimanta'=> 1,
        'nrbon'                  => 0,
        'locatie'                => (int)$cod_locatie,
        'departament_listare'    => "BAR",
        'continut'               => $continut
    ]];

    $json_file_path = "{$folder_path}/de_listat_la_imprimanta.json";

    // 7) Așteaptă eventual și scrie fișierul
    update_loading_status("Așteptăm preluarea datelor de către imprimanta BAR (Închidere Tură)...");
    $wait = 0;
    while (file_exists($json_file_path) && $wait < 60) {
        sleep(10);
        $wait += 10;
    }
    if (file_exists($json_file_path)) {
        echo "<script>alert('Fișierul nu s-a putut genera deoarece există deja unul activ.');location.href='vanzare_restaurant.php';</script>";
        exit();
    }

    $json_array = [
        "status"  => "success",
        "message" => "Date pentru închiderea turei generate cu succes.",
        "data"    => $printData
    ];
    file_put_contents($json_file_path, json_encode($json_array, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    update_loading_status("Gata! Redirecționăm...");
    
    // redirect
    echo "<script>location.href='vanzare_inchidere_zi_automata.php'</script>";
} catch (PDOException $e) {
    error_log("Eroare la generarea datelor pentru închiderea turei: " . $e->getMessage());
}
