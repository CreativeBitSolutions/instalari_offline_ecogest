<?php
// api/de_listat_la_imprimanta.php

header('Content-Type: application/json');

/**
 * Trimite un răspuns JSON și termină execuția scriptului.
 *
 * @param string $status  'success' sau 'error'
 * @param string $message Mesajul de răspuns
 * @param mixed  $data    Datele suplimentare (opțional)
 */
function send_response($status, $message, $data = null) {
    echo json_encode([
        'status'  => $status,
        'message' => $message,
        'data'    => $data
    ]);
    exit;
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

// (Opțional) Filtrare/sanitizare pentru a permite doar caractere alfanumerice, underscore sau cratime
$client_id = preg_replace('/[^a-zA-Z0-9_-]/', '', $client_id);
$locatie   = preg_replace('/[^a-zA-Z0-9_-]/', '', $locatie);

// Definim calea către fișierul de la imprimantă
// Presupunând că structura folderelor este: 
// api/{client_id}/{locatie}/de_listat_la_imprimanta.json
$file_path = __DIR__ . '/' . $client_id . '/' . $locatie . '/de_listat_la_imprimanta.json';

// Verificăm dacă fișierul există
if (!file_exists($file_path)) {
    send_response('success', 'Fișierul de_listat_la_imprimanta.json nu a fost găsit în directorul: ' . $client_id . '/' . $locatie);
}

// Citim conținutul fișierului
$file_content = file_get_contents($file_path);

// Verificăm dacă conținutul este un JSON valid
$json_data = json_decode($file_content, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    send_response('error', 'Conținutul fișierului nu este un JSON valid.');
}

// Ștergem fișierul după ce am preluat conținutul
if (!unlink($file_path)) {
    send_response('error', 'Fișierul a fost citit, dar nu s-a putut șterge.');
}

// Returnăm conținutul citit (care deja are structura JSON dorită)
echo $file_content;
exit;
?>
