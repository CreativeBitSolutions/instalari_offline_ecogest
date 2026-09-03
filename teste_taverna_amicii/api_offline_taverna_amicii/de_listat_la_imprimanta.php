<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/printer_format_helper.php';
require_once __DIR__ . '/printer_queue_atomic_helper.php';

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

try {
    $claim = agecs_printer_queue_claim($filePath);
} catch (Throwable $exception) {
    send_response('error', 'Coada imprimantei nu a putut fi accesată în siguranță.');
}
if (($claim['status'] ?? '') === 'empty') {
    send_response('success', 'Nu există document în așteptare pentru imprimantă.');
}
if (($claim['status'] ?? '') === 'error') {
    send_response('error', 'Documentul există, dar nu a putut fi preluat în siguranță.');
}
$claimedPath = (string)($claim['path'] ?? '');

$fileContent = $claimedPath !== '' ? file_get_contents($claimedPath) : false;
if ($fileContent === false) {
    agecs_printer_queue_restore_claim($claimedPath, $filePath);
    send_response('error', 'Documentul există, dar nu a putut fi citit.');
}

$payload = json_decode($fileContent, true);
if (!is_array($payload) || json_last_error() !== JSON_ERROR_NONE) {
    agecs_printer_queue_restore_claim($claimedPath, $filePath);
    send_response('error', 'Conținutul documentului nu este JSON valid.');
}

try {
    $payload = agecs_printer_normalize_scanner_payload($payload);
} catch (InvalidArgumentException $exception) {
    agecs_printer_queue_restore_claim($claimedPath, $filePath);
    send_response('error', $exception->getMessage());
}

$payload = agecs_printer_format_payload($payload, agecs_printer_load_format_config());
$formattedContent = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($formattedContent === false) {
    agecs_printer_queue_restore_claim($claimedPath, $filePath);
    send_response('error', 'Documentul nu a putut fi pregătit pentru imprimantă.');
}

if (!unlink($claimedPath)) {
    send_response('error', 'Documentul a fost citit, dar nu a putut fi eliminat din coadă.');
}

echo $formattedContent;
