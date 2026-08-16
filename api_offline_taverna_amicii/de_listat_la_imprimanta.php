<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/printer_format_helper.php';

function send_response(string $status, string $message, $data = null): void
{
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$clientId = trim((string)($_POST['client_id'] ?? ''));
$locationId = trim((string)($_POST['locatie'] ?? ''));

if ($clientId === '' || !ctype_digit($clientId) || (int)$clientId <= 0) {
    send_response('error', 'Parametrul client_id trebuie să fie un număr pozitiv.');
}

if ($locationId === '' || !ctype_digit($locationId) || (int)$locationId <= 0) {
    send_response('error', 'Parametrul locatie trebuie să fie un număr pozitiv.');
}

$filePath = __DIR__ . DIRECTORY_SEPARATOR . $clientId . DIRECTORY_SEPARATOR . $locationId
    . DIRECTORY_SEPARATOR . 'de_listat_la_imprimanta.json';

if (!is_file($filePath)) {
    send_response('success', 'Nu există document în așteptare pentru imprimantă.');
}

$fileContent = file_get_contents($filePath);
if ($fileContent === false) {
    send_response('error', 'Documentul există, dar nu a putut fi citit.');
}

$payload = json_decode($fileContent, true);
if (!is_array($payload) || json_last_error() !== JSON_ERROR_NONE) {
    send_response('error', 'Conținutul documentului nu este JSON valid.');
}

try {
    $payload = agecs_printer_normalize_scanner_payload($payload);
} catch (InvalidArgumentException $exception) {
    send_response('error', $exception->getMessage());
}

$payload = agecs_printer_format_payload($payload, agecs_printer_load_format_config());
$formattedContent = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($formattedContent === false) {
    send_response('error', 'Documentul nu a putut fi pregătit pentru imprimantă.');
}

if (!unlink($filePath)) {
    send_response('error', 'Documentul a fost citit, dar nu a putut fi eliminat din coadă.');
}

echo $formattedContent;
