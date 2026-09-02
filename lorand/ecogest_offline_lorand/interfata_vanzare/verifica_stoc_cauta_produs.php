<?php

include __DIR__ . '/session.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$query = trim((string)($_GET['q'] ?? ''));
$queryLength = function_exists('mb_strlen') ? mb_strlen($query, 'UTF-8') : strlen($query);
if ($queryLength < 2) {
    echo json_encode(array('ok' => true, 'source' => 'local', 'results' => array()), JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $pdo->prepare(
    'SELECT cod_produs, nume, um FROM produse_servicii '
    . 'WHERE nume LIKE :term ORDER BY nume ASC LIMIT 50'
);
$stmt->execute(array(':term' => '%' . $query . '%'));
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($results as &$result) {
    if (strtoupper(trim((string)($result['um'] ?? ''))) === 'H87') {
        $result['um'] = 'BUC';
    }
}
unset($result);

echo json_encode(array(
    'ok' => true,
    'source' => 'local',
    'results' => $results,
), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
