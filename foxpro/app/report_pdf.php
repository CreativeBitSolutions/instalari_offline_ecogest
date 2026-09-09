<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$config = Config::load();
$date = trim((string) ($_GET['date'] ?? '2026-09-08'));
$operator = trim((string) ($_GET['operator'] ?? ''));

try {
    $report = (new ReportRepository($config))->build($date, $operator);
} catch (Throwable $exception) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo $exception->getMessage();
    exit;
}

$safeDate = preg_replace('/[^0-9-]+/', '-', $date) ?: 'raport';
$safeOperator = preg_replace('/[^A-Za-z0-9_-]+/', '-', $operator);
$filename = 'raport-vanzari-' . $safeDate . ($safeOperator !== '' ? '-' . $safeOperator : '') . '.pdf';
ReportPdf::stream($report, $filename);
