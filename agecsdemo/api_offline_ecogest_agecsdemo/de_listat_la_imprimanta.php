<?php
header('Content-Type: application/json; charset=utf-8');

function send_response($status, $message, $data = null) {
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function printer_claim_file(string $path): ?string {
    if (!is_file($path)) {
        return null;
    }
    $claimed = $path . '.processing.' . getmypid() . '.' . str_replace('.', '', uniqid('', true));
    if (@rename($path, $claimed)) {
        return $claimed;
    }
    return null;
}

$client_id = isset($_POST['client_id']) ? trim((string)$_POST['client_id']) : '';
$locatie = isset($_POST['locatie']) ? trim((string)$_POST['locatie']) : '';
if ($client_id === '') {
    send_response('error', 'Parametrul "client_id" lipsește.');
}
if ($locatie === '') {
    send_response('error', 'Parametrul "locatie" lipsește.');
}

$client_id = preg_replace('/[^a-zA-Z0-9_-]/', '', $client_id);
$locatie = preg_replace('/[^a-zA-Z0-9_-]/', '', $locatie);
if ($client_id === '' || $locatie === '') {
    send_response('error', 'Clientul sau locația nu sunt valide.');
}

$base = __DIR__ . DIRECTORY_SEPARATOR . $client_id . DIRECTORY_SEPARATOR . $locatie;
$legacy = $base . DIRECTORY_SEPARATOR . 'de_listat_la_imprimanta.json';
$claimed = printer_claim_file($legacy);

if ($claimed === null) {
    $queueDir = $base . DIRECTORY_SEPARATOR . 'print_queue';
    if (is_dir($queueDir)) {
        $candidates = glob($queueDir . DIRECTORY_SEPARATOR . '*.json') ?: [];
        sort($candidates, SORT_STRING);
        foreach ($candidates as $candidate) {
            $claimed = printer_claim_file($candidate);
            if ($claimed !== null) {
                break;
            }
        }
    }
}

if ($claimed === null) {
    send_response('success', 'Nu există documente în așteptare pentru imprimare.');
}

$file_content = @file_get_contents($claimed);
if ($file_content === false) {
    @unlink($claimed);
    send_response('error', 'Documentul de imprimare nu a putut fi citit.');
}

$json_data = json_decode($file_content, true);
if (!is_array($json_data) || json_last_error() !== JSON_ERROR_NONE) {
    @unlink($claimed);
    send_response('error', 'Documentul din coada imprimantei nu conține JSON valid.');
}

if (!@unlink($claimed)) {
    send_response('error', 'Documentul a fost citit, dar nu s-a putut elimina din coada imprimantei.');
}

echo $file_content;
exit;
