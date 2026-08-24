<?php
// verifica_stoc_cauta_produs.php
include('session.php');
header('Content-Type: application/json');

$q = isset($_GET['q']) ? $_GET['q'] : '';

$sql = "SELECT cod_produs, nume FROM produse_servicii WHERE nume LIKE :term LIMIT 50";
$stmt = $pdo->prepare($sql);
$stmt->execute(['term' => '%' . $q . '%']);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($results);
?>
