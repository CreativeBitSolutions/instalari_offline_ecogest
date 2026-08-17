<?php
include('session.php'); // sesiune + conexiune DB

// date esențiale din sesiune
$client_id    = $_SESSION['client_id'];
$cod_locatie  = isset($_SESSION['cod_locatie']) ? intval($_SESSION['cod_locatie']) : 0;
$admin_id     = $_SESSION['admin_id'];  // nou: filtrăm după operator

// calea către folderul de output
$folder_path = RESTAURANT_OFFLINE_API_DIR . "/{$client_id}/{$cod_locatie}";
if (!is_dir($folder_path)) {
    mkdir($folder_path, 0777, true);
}

try {
    // 1) Preluăm notele “deschise” (neverificate) de operator
    $stmt = $pdo->prepare("
        SELECT numerar, card, tichete, protocol, glovo, virament_bancar
        FROM note
        WHERE cod_inchidere = 0
          AND status       = 'F'
          AND operator     = :admin_id
          AND locatie      = :locatie
    ");
    $stmt->execute([
        ':admin_id' => $admin_id,
        ':locatie'  => $cod_locatie
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
    unset($suma);

    // 4) Construim conținutul de print
    $current_date = date('Y-m-d');
    $current_time = date('H:i:s');

    $continut  = "RAPORT X INFORMATIV -SUME SERTAR\n";
    $continut .= "Data: {$current_date} {$current_time}\n";
    $continut .= "Operator ID: {$admin_id}\n";
    $continut .= "------------------\n";
    foreach ($totals as $metoda => $suma) {
        if ($suma > 0) {
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

    // 5) Adăugăm operatorul (prenume + nume) la final, dacă vrei
    $admin_firstname = $_SESSION['admin_firstname'] ?? 'Operator';
    $admin_lastname  = $_SESSION['admin_lastname']  ?? '';
    $continut       .= "OPERATOR: {$admin_firstname} {$admin_lastname}\n";

    // 6) Pregătim JSON-ul de export
    $printData = [[
        'data'                    => $current_date,
        'ora'                     => $current_time,
        'de_trimis_la_imprimanta' => 1,
        'nrbon'                   => 0,
        'locatie'                 => $cod_locatie,
        'departament_listare'     => "BAR",
        'continut'                => $continut
    ]];

    $json_file_path = "{$folder_path}/de_listat_la_imprimanta.json";

    // 7) Așteptăm eventual și scriem fișierul
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
        "message" => "Raport note deschise generat cu succes.",
        "data"    => $printData
    ];
    file_put_contents($json_file_path, json_encode($json_array, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // redirect (sau poți trimite alt mesaj/JSON)
    echo "<script>location.href='vanzare_restaurant.php'</script>";
} catch (PDOException $e) {
    error_log("Eroare la generarea raportului de note deschise: " . $e->getMessage());
}
