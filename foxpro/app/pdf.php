<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$config = Config::load();
$repo = new RelistareRepository($config);
$type = (string) ($_GET['type'] ?? 'nota');

$document = $type === 'bon'
    ? $repo->getBonDocument((string) ($_GET['key'] ?? $_GET['id'] ?? ''))
    : $repo->getNotaDocument((string) ($_GET['id'] ?? ''));

if (!$document) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Document negasit.';
    exit;
}

$prefix = $document['type'] === 'bon' ? 'bon-comanda' : 'nota';
$number = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($document['number'] ?? 'document'));
ReceiptPdf::stream($document, $prefix . '-' . $number . '.pdf');
