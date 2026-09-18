<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode([]);
    exit;
}

include 'db.php';

$searchTerm = trim((string)($_GET['q'] ?? ''));
$gestiuniNeexclude = ['PRODUSE FINITE', 'ALTELE'];
$placeholders = implode(',', array_fill(0, count($gestiuniNeexclude), '?'));

$sql = "
    SELECT
        ps.cod_produs,
        ps.nume,
        ps.cod_bare
    FROM produse_servicii ps
    INNER JOIN gestiuni g ON ps.id_gestiune = g.id_gestiune
    WHERE (ps.nume LIKE ? OR ps.cod_bare LIKE ?)
      AND g.denumire_gestiune NOT IN ($placeholders)
    LIMIT 30
";

$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge(
    ['%' . $searchTerm . '%', '%' . $searchTerm . '%'],
    $gestiuniNeexclude
));

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>