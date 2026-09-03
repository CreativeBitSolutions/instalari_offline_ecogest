<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
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

function normalize_scanner_integer($value, string $field): int
{
    if (is_int($value)) {
        $number = $value;
    } elseif (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
        $number = (int)$value;
    } else {
        throw new InvalidArgumentException('Câmpul ' . $field . ' nu este un număr întreg valid.');
    }

    if ($number < -2147483648 || $number > 2147483647) {
        throw new InvalidArgumentException('Câmpul ' . $field . ' depășește limita acceptată de scaner.');
    }

    return $number;
}

function normalize_scanner_payload(array $payload): array
{
    if (!isset($payload['data']) || !is_array($payload['data'])) {
        throw new InvalidArgumentException('Lista de bonuri lipsește din răspuns.');
    }

    $integerFields = ['id', 'de_trimis_la_casa_marcat', 'nrbon', 'locatie'];
    foreach ($payload['data'] as $index => &$bon) {
        if (!is_array($bon)) {
            throw new InvalidArgumentException('Bonul de la poziția ' . $index . ' nu este valid.');
        }

        foreach ($integerFields as $field) {
            if (!array_key_exists($field, $bon)) {
                throw new InvalidArgumentException('Câmpul ' . $field . ' lipsește din bon.');
            }
            $bon[$field] = normalize_scanner_integer($bon[$field], $field);
        }
    }
    unset($bon);

    return $payload;
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
    . DIRECTORY_SEPARATOR . 'bon_casa_marcat.json';

try {
    $claim = agecs_printer_queue_claim($filePath);
} catch (Throwable $exception) {
    send_response('error', 'Coada casei de marcat nu a putut fi accesată în siguranță.');
}

if (($claim['status'] ?? '') === 'empty') {
    send_response('success', 'Nu există bon în așteptare pentru clientul și locația solicitate.');
}
if (($claim['status'] ?? '') === 'error') {
    send_response('error', 'Bonul există, dar nu a putut fi preluat în siguranță.');
}
$claimedPath = (string)($claim['path'] ?? '');

$fileContent = $claimedPath !== '' ? file_get_contents($claimedPath) : false;
if ($fileContent === false) {
    agecs_printer_queue_restore_claim($claimedPath, $filePath);
    send_response('error', 'Bonul există, dar nu a putut fi citit.');
}

$payload = json_decode($fileContent, true);
if (!is_array($payload) || json_last_error() !== JSON_ERROR_NONE) {
    agecs_printer_queue_restore_claim($claimedPath, $filePath);
    send_response('error', 'Conținutul bonului nu este JSON valid.');
}

try {
    $payload = normalize_scanner_payload($payload);
} catch (InvalidArgumentException $exception) {
    agecs_printer_queue_restore_claim($claimedPath, $filePath);
    send_response('error', $exception->getMessage());
}
$scannerContent = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($scannerContent === false) {
    agecs_printer_queue_restore_claim($claimedPath, $filePath);
    send_response('error', 'Bonul nu a putut fi pregătit pentru scaner.');
}

if (!unlink($claimedPath)) {
    send_response('error', 'Bonul a fost citit, dar nu a putut fi eliminat din coadă.');
}

echo $scannerContent;
