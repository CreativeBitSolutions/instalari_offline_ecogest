<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$config = Config::load();
$date = trim((string) ($_GET['date'] ?? '2026-09-08'));
$operator = trim((string) ($_GET['operator'] ?? ''));
$payment = trim((string) ($_GET['payment'] ?? ''));
$search = trim((string) ($_GET['q'] ?? ''));

try {
    $report = (new TransactionRepository($config))->build($date, $operator, $payment, $search);
} catch (Throwable $exception) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo $exception->getMessage();
    exit;
}

$safeDate = preg_replace('/[^0-9-]+/', '-', $date) ?: 'toate-datele';
$safeOperator = preg_replace('/[^A-Za-z0-9_-]+/', '-', $operator);
$filename = 'tranzactii-' . $safeDate . ($safeOperator !== '' ? '-' . $safeOperator : '') . '.pdf';
TransactionPdf::stream($report, $filename, [
    'operator' => $operator,
    'payment' => $payment,
    'search' => $search,
]);
