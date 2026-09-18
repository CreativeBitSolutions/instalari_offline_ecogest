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

$sql = "
    SELECT id_furnizor, nume
    FROM furnizori
    WHERE nume LIKE :searchTerm
       OR cod_fiscal LIKE :searchTerm
    ORDER BY nume ASC
    LIMIT 10
";

$stmt = $pdo->prepare($sql);
$stmt->execute(['searchTerm' => '%' . $searchTerm . '%']);

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
