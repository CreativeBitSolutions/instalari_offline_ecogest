<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

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

function normalize_bon_numeric_fields(array $payload): array
{
    if (!isset($payload['data']) || !is_array($payload['data'])) {
        return $payload;
    }

    $integerFields = [
        'id',
        'de_trimis_la_casa_marcat',
        'nrbon',
        'locatie',
        'id_factura',
    ];

    foreach ($payload['data'] as &$bon) {
        if (!is_array($bon)) {
            continue;
        }
        foreach ($integerFields as $field) {
            if (array_key_exists($field, $bon) && $bon[$field] !== null && $bon[$field] !== '') {
                $bon[$field] = (int)$bon[$field];
            }
        }
    }
    unset($bon);

    return $payload;
}

$clientId = trim((string)($_POST['client_id'] ?? ''));
$locationId = trim((string)($_POST['locatie'] ?? ''));
if ($clientId === '') {
    send_response('error', 'Parametrul "client_id" lipsește.');
}
if ($locationId === '') {
    send_response('error', 'Parametrul "locatie" lipsește.');
}

$clientId = (string)preg_replace('/[^a-zA-Z0-9_-]/', '', $clientId);
$locationId = (string)preg_replace('/[^a-zA-Z0-9_-]/', '', $locationId);
if ($clientId === '' || $locationId === '') {
    send_response('error', 'Clientul sau locația nu sunt valide.');
}

$queuePath = __DIR__ . DIRECTORY_SEPARATOR . $clientId . DIRECTORY_SEPARATOR . $locationId
    . DIRECTORY_SEPARATOR . 'bon_casa_marcat.json';

try {
    $claim = agecs_printer_queue_claim($queuePath);
} catch (Throwable $error) {
    send_response('error', 'Coada casei de marcat nu a putut fi accesată în siguranță.');
}

if (($claim['status'] ?? '') === 'empty') {
    send_response('success', 'Nu există bon în așteptare pentru clientul și locația solicitate.');
}
if (($claim['status'] ?? '') !== 'claimed') {
    send_response('error', 'Bonul există, dar nu a putut fi preluat în siguranță.');
}

$claimedPath = (string)($claim['path'] ?? '');
$fileContent = $claimedPath !== '' ? @file_get_contents($claimedPath) : false;
if (!is_string($fileContent)) {
    agecs_printer_queue_restore_claim($claimedPath, $queuePath);
    send_response('error', 'Bonul există, dar nu a putut fi citit.');
}

$payload = json_decode($fileContent, true);
if (!is_array($payload) || json_last_error() !== JSON_ERROR_NONE) {
    agecs_printer_queue_restore_claim($claimedPath, $queuePath);
    send_response('error', 'Conținutul bonului nu este JSON valid.');
}

$payload = normalize_bon_numeric_fields($payload);
$scannerContent = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($scannerContent)) {
    agecs_printer_queue_restore_claim($claimedPath, $queuePath);
    send_response('error', 'Bonul nu a putut fi pregătit pentru scanner.');
}

if (!@unlink($claimedPath)) {
    agecs_printer_queue_restore_claim($claimedPath, $queuePath);
    send_response('error', 'Bonul a fost citit, dar nu a putut fi eliminat în siguranță din coadă.');
}

echo $scannerContent;
