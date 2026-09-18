<?php
// Setăm header-ul pentru a returna JSON
header('Content-Type: application/json');

// Funcție pentru a trimite răspunsuri JSON și a termina execuția scriptului
function send_response($status, $message, $data = null) {
    echo json_encode([
        'status'  => $status,
        'message' => $message,
        'data'    => $data
    ]);
    exit;
}

function normalize_bon_numeric_fields(array $payload) {
    if (!isset($payload['data']) || !is_array($payload['data'])) {
        return $payload;
    }

    $integer_fields = [
        'id',
        'de_trimis_la_casa_marcat',
        'nrbon',
        'locatie',
        'id_factura'
    ];

    foreach ($payload['data'] as &$bon) {
        if (!is_array($bon)) {
            continue;
        }

        foreach ($integer_fields as $field) {
            if (array_key_exists($field, $bon) && $bon[$field] !== null && $bon[$field] !== '') {
                $bon[$field] = (int)$bon[$field];
            }
        }
    }
    unset($bon);

    return $payload;
}

// Verificăm dacă parametrul 'client_id' este prezent în $_POST
if (!isset($_POST['client_id']) || trim($_POST['client_id']) === '') {
    send_response('error', 'Parametrul "client_id" lipsește.');
}

$client_id = trim($_POST['client_id']);

// Verificăm dacă parametrul 'locatie' este prezent în $_POST
if (!isset($_POST['locatie']) || trim($_POST['locatie']) === '') {
    send_response('error', 'Parametrul "locatie" lipsește.');
}

$locatie = trim($_POST['locatie']);

// Pentru siguranță, poți valida sau filtra valorile primite
// De exemplu, dacă te aștepți ca aceste variabile să conțină doar caractere alfanumerice,
// le poți filtra cu: 
// $client_id = preg_replace('/[^a-zA-Z0-9_-]/', '', $client_id);
// $locatie   = preg_replace('/[^a-zA-Z0-9_-]/', '', $locatie);

// Definim calea către fișierul bon_casa_marcat.json, în directorul client_id/locatie
$file_path = __DIR__ . '/' . $client_id . '/' . $locatie . '/bon_casa_marcat.json';

// Verificăm dacă fișierul există
if (!file_exists($file_path)) {
    send_response('success', 'Fișierul bon_casa_marcat.json nu a fost găsit în directorul: ' . $client_id . '/' . $locatie);
}

// Citim conținutul fișierului
$file_content = file_get_contents($file_path);

// Verificăm dacă conținutul este un JSON valid (opțional)
$json_data = json_decode($file_content, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    send_response('error', 'Conținutul fișierului nu este un JSON valid.');
}

// Încercăm să ștergem fișierul după ce am preluat conținutul
if (!unlink($file_path)) {
    send_response('error', 'Fișierul a fost citit, dar nu s-a putut șterge.');
}

// Returnăm conținutul citit (care are deja structura JSON dorită)
$json_data = normalize_bon_numeric_fields($json_data);
echo json_encode($json_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
?>
