<?php
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['requestId'], $data['payload'], $data['id_client'], $data['cod_locatie'])) {
    
    $client_id = preg_replace('/[^0-9]/', '', $data['id_client']);
    $locatie_val = preg_replace('/[^0-9]/', '', $data['cod_locatie']);
    $request_id = preg_replace('/[^a-zA-Z0-9_]/', '', $data['requestId']);

    $folder_path = __DIR__ . "/" . $client_id . "/" . $locatie_val;
    if (!is_dir($folder_path)) {
        mkdir($folder_path, 0777, true);
    }

    $output_file = $folder_path . "/cantitate_" . $request_id . ".json";
    
    file_put_contents($output_file, json_encode($data['payload'], JSON_PRETTY_PRINT));

    echo json_encode(["status" => "success", "message" => "Datele au fost înregistrate."]);
    
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Datele trimise sunt incomplete."]);
}
?>