<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

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
        send_response('error', 'Câmpul ' . $field . ' nu este un număr întreg valid.');
    }

    if ($number < -2147483648 || $number > 2147483647) {
        send_response('error', 'Câmpul ' . $field . ' depășește limita acceptată de scaner.');
    }

    return $number;
}

function normalize_scanner_payload(array $payload): array
{
    if (!isset($payload['data']) || !is_array($payload['data'])) {
        send_response('error', 'Lista de bonuri lipsește din răspuns.');
    }

    $integerFields = ['id', 'de_trimis_la_casa_marcat', 'nrbon', 'locatie'];
    foreach ($payload['data'] as $index => &$bon) {
        if (!is_array($bon)) {
            send_response('error', 'Bonul de la poziția ' . $index . ' nu este valid.');
        }

        foreach ($integerFields as $field) {
            if (!array_key_exists($field, $bon)) {
                send_response('error', 'Câmpul ' . $field . ' lipsește din bon.');
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

if (!is_file($filePath)) {
    send_response('success', 'Nu există bon în așteptare pentru clientul și locația solicitate.');
}

$fileContent = file_get_contents($filePath);
if ($fileContent === false) {
    send_response('error', 'Bonul există, dar nu a putut fi citit.');
}

$payload = json_decode($fileContent, true);
if (!is_array($payload) || json_last_error() !== JSON_ERROR_NONE) {
    send_response('error', 'Conținutul bonului nu este JSON valid.');
}

$payload = normalize_scanner_payload($payload);
$scannerContent = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($scannerContent === false) {
    send_response('error', 'Bonul nu a putut fi pregătit pentru scaner.');
}

if (!unlink($filePath)) {
    send_response('error', 'Bonul a fost citit, dar nu a putut fi eliminat din coadă.');
}

echo $scannerContent;
