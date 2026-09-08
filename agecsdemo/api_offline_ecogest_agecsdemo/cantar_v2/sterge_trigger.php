<?php
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['id_client'], $data['cod_locatie'])) {
    
    $client_id = preg_replace('/[^0-9]/', '', $data['id_client']);
    $locatie_val = preg_replace('/[^0-9]/', '', $data['cod_locatie']);

    $folder_path = __DIR__ . "/" . $client_id . "/" . $locatie_val;
    $trigger_file = $folder_path . "/trigger_cantar.json";

    if (file_exists($trigger_file)) {
        if (unlink($trigger_file)) {
            echo json_encode(["status" => "success", "message" => "Fișierul trigger a fost șters."]);
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Nu s-a putut șterge fișierul trigger."]);
        }
    } else {
        echo json_encode(["status" => "success", "message" => "Fișierul trigger nu a fost găsit (posibil deja șters)."]);
    }
    
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Datele trimise (id_client, cod_locatie) sunt incomplete."]);
}
?>