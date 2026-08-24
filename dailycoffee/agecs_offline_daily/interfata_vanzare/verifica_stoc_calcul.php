<?php
// verifica_stoc_calcul.php
include('session.php'); // asigură-te că incluzi conexiunea la baza de date (PDO)

header('Content-Type: application/json');

$cod_produs = isset($_GET['cod_produs']) ? intval($_GET['cod_produs']) : 0;

if (!$cod_produs) {
    echo json_encode(['final_stock' => 0]);
    exit;
}

$sql = "SELECT 
          SUM(CASE WHEN tip_miscare = 'I' THEN cantitate_misc ELSE 0 END) AS total_in,
          SUM(CASE WHEN tip_miscare = 'O' THEN cantitate_misc ELSE 0 END) AS total_out
        FROM miscari
        WHERE cod_p = :cod_produs";
$stmt = $pdo->prepare($sql);
$stmt->execute(['cod_produs' => $cod_produs]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

$total_in  = isset($result['total_in']) ? $result['total_in'] : 0;
$total_out = isset($result['total_out']) ? $result['total_out'] : 0;
$final_stock = $total_in - $total_out;

echo json_encode(['final_stock' => $final_stock]);
?>
