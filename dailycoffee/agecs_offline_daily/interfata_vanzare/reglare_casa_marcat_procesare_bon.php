<?php
// reglare_casa_marcat_procesare_bon.php (versiune actualizată)

header('Content-Type: application/json');

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.log');
error_reporting(E_ALL);

require_once __DIR__ . '/offline_api_path.php';

include('session.php'); // Include sesiunea și conexiunea $pdo

function send_json_error($message) {
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit();
}

// Preluare și validare date sesiune
$client_id = $_SESSION['client_id'] ?? null;
$cod_locatie = isset($_SESSION['cod_locatie']) ? intval($_SESSION['cod_locatie']) : 0;
$admin_id = $_SESSION['admin_id'] ?? null; 

if (!$client_id || !$cod_locatie) {
    send_json_error("Sesiunea a expirat sau este invalidă. Reîncărcați pagina.");
}

// Preluare date trimise de la client (JavaScript)
$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['items']) || !isset($input['metoda_plata'])) {
    send_json_error("Datele primite sunt incomplete. Bonul nu poate fi procesat.");
}

$bonItems = $input['items'];
$metodaPlataCod = $input['metoda_plata'];
$totalGeneral = (float)$input['total_general'];

// NOU: Mapare coduri plată -> nume plată pentru o logare mai clară
$metode_plata_map = [
    '0' => 'NUMERAR', '1' => 'CARD', '6' => 'PLATA MODERNA',
    '3' => 'TICHETE MASA', '4' => 'TICHETE VALORICE', '5' => 'VOUCHER', '2' => 'CREDIT'
];
$metodaPlataNume = $metode_plata_map[$metodaPlataCod] ?? 'NECUNOSCUT';


// Preluare mapare TVA -> Departament Casa
$cote_tva_map = [];
try {
    $stmt_tva = $pdo->query("SELECT cota, dep_casa FROM cote_tva");
    foreach ($stmt_tva->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $cote_tva_map[$row['cota']] = $row['dep_casa'];
    }
} catch (PDOException $e) {
    error_log("Eroare la preluarea cotelor TVA în procesare: " . $e->getMessage());
    send_json_error("Eroare internă a serverului (TVA Map).");
}

// Construirea string-ului `continut_bon`
$cr = chr(13) . chr(10);
$myBuffer = "H,1,______,_,__;" . $cr;

foreach ($bonItems as $item) {
    $cota_tva = (int)$item['cota_tva'];
    $dep_casa = $cote_tva_map[$cota_tva] ?? 0;
    $nume_produs_formatat = substr($item['nume'], 0, 22);
    
    $myBuffer .= "S,1,______,_,__;" .
                 $nume_produs_formatat . ";" .
                 number_format((float)$item['pret_unitar'], 2, '.', '') . ";" .
                 (float)$item['cantitate'] . ";" .
                 "1;1;" .
                 $dep_casa . ";" .
                 "0;0;" .
                 $item['um'] . $cr;
}

// Adăugare linie de plată
$T = "T,1,______,_,__;";
$myBuffer .= $T . $metodaPlataCod . ";" . number_format($totalGeneral, 2, '.', '') . ";;;;" . $cr;

if ($client_id == 4) {
    $myBuffer .= "T,1,______,_,__;" . $cr;
}

// Construirea obiectului JSON final
$now = new DateTime("now", new DateTimeZone("Europe/Bucharest"));
$timestamp = $now->getTimestamp();

$finalJsonData = [
    "status"  => "success",
    "message" => "Bonuri preluate cu succes.",
    "data"    => [[
        "id" => $timestamp,
        "data" => $now->format('Y-m-d'),
        "ora" => $now->format('H:i:s'),
        "continut_bon" => $myBuffer,
        "de_trimis_la_casa_marcat" => 1,
        "nrbon" => $timestamp,
        "locatie" => $cod_locatie,
        "id_factura" => 0
    ]]
];

$json_string_to_save = json_encode($finalJsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

// Logica de salvare a fișierului pe server
try {
    $folder_path = offline_api_path($client_id, $cod_locatie);

    if (!is_dir($folder_path)) {
        if (!mkdir($folder_path, 0777, true)) {
            send_json_error("Nu s-a putut crea directorul de destinație pe server.");
        }
    }

    $json_file_path = $folder_path . "/bon_casa_marcat.json";
    
    if (file_put_contents($json_file_path, $json_string_to_save) === false) {
        send_json_error("Nu s-a putut scrie fișierul JSON pe server.");
    }

    // --- START BLOC DE LOGARE ---
    // După ce fișierul a fost salvat cu succes, inserăm înregistrarea în log.
    try {
        $sql_log = "INSERT INTO log_reglari_casa_marcat 
                        (client_id, cod_locatie, admin_id, total_general, metoda_plata, detalii_bon_json, continut_fisier_bon) 
                    VALUES 
                        (:client_id, :cod_locatie, :admin_id, :total_general, :metoda_plata, :detalii_bon_json, :continut_fisier_bon)";
        
        $stmt_log = $pdo->prepare($sql_log);
        
        $stmt_log->execute([
            ':client_id' => $client_id,
            ':cod_locatie' => $cod_locatie,
            ':admin_id' => $admin_id,
            ':total_general' => $totalGeneral,
            ':metoda_plata' => $metodaPlataNume,
            ':detalii_bon_json' => json_encode($bonItems),
            ':continut_fisier_bon' => $myBuffer
        ]);

    } catch (PDOException $e) {
        // Dacă logarea eșuează, nu oprim procesul principal.
        // Doar înregistrăm eroarea în fișierul de log al serverului.
        error_log("EROARE CRITICĂ LA LOGAREA REGLĂRII: " . $e->getMessage());
    }
    // --- END BLOC DE LOGARE ---

    // Trimite răspuns de succes
    echo json_encode([
        'status' => 'success',
        'message' => 'Fișierul a fost generat și operațiunea a fost înregistrată. Verificați dacă a ieșit la casa de marcat, în caz contrar reporniți PC-ul.'
    ]);

} catch (Exception $e) {
    error_log("Eroare la salvarea fisierului: " . $e->getMessage());
    send_json_error("A apărut o eroare neașteptată la salvarea fișierului pe server.");
}

?>
