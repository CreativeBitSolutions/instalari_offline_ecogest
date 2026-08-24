<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(null);
    exit;
}

include 'db.php';

$id = (int)($_GET['id'] ?? 0);

$sql = "SELECT id_furnizor, nume FROM furnizori WHERE id_furnizor = :id LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $id]);

echo json_encode($stmt->fetch(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>