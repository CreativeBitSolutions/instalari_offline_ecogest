<?php
session_start(); // Asigură-te că sesiunile sunt pornite

// Primește datele trimise prin POST
$data = json_decode(file_get_contents('php://input'), true);

// Verifică dacă trigger-ul este setat true
if (isset($data['trigger']) && $data['trigger'] === true) {

    // Preia variabilele din sesiune
    if (isset($_SESSION['client_id']) && isset($_SESSION['cod_locatie'])) {
        $client_id = $_SESSION['client_id'];
        $locatie_val = $_SESSION['cod_locatie'];

        // Definește calea folderului clientului și locației
        $folder_path = __DIR__ . "/" . $client_id . "/" . $locatie_val;

        // Creează folderul dacă nu există
        if (!is_dir($folder_path)) {
            mkdir($folder_path, 0777, true);
        }

        // Definește calea fișierului trigger_cantar.json
        $trigger_file = $folder_path . "/trigger_cantar.json";

        // Scrie trigger true în fișier
        $trigger_data = array("trigger" => true);
        file_put_contents($trigger_file, json_encode($trigger_data, JSON_PRETTY_PRINT));

        echo "Trigger-ul a fost activat cu succes.";
    } else {
        http_response_code(400);
        echo "Variabilele client_id și cod_locatie nu sunt setate în sesiune.";
    }

} else {
    http_response_code(400);
    echo "Date incorecte transmise.";
}
?>
