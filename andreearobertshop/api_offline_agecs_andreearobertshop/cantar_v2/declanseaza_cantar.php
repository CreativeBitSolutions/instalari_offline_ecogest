<?php
session_start();
header('Content-Type: application/json');

if (isset($_SESSION['client_id']) && isset($_SESSION['cod_locatie'])) {
    $client_id = $_SESSION['client_id'];
    $locatie_val = $_SESSION['cod_locatie'];
    
    $data = json_decode(file_get_contents('php://input'), true);
    if (isset($data['requestId'])) {
        $request_id = $data['requestId'];

        $folder_path = __DIR__ . "/" . $client_id . "/" . $locatie_val;
        if (!is_dir($folder_path)) {
            mkdir($folder_path, 0777, true);
        }

        $trigger_file = $folder_path . "/trigger_cantar.json";
        $trigger_data = ["requestId" => $request_id]; 
        file_put_contents($trigger_file, json_encode($trigger_data, JSON_PRETTY_PRINT));

        echo json_encode(["status" => "success", "message" => "Trigger V2 activat."]);
    } else {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "ID-ul cererii (requestId) lipsește."]);
    }
} else {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Sesiune invalidă."]);
}
?>