<?php
include('session.php');

header('Content-Type: application/json; charset=utf-8');

$cod_locatie = (int)($_SESSION['cod_locatie'] ?? 2);

$sql_prod = "SELECT cod_produs FROM produse_servicii";
$stmt_prod = $pdo->query($sql_prod);

while ($row = $stmt_prod->fetch(PDO::FETCH_ASSOC)) {
    $cod_produs = (int)$row['cod_produs'];

    $sql = "SELECT
              SUM(CASE WHEN tip_miscare = 'I' THEN cantitate_misc ELSE 0 END) AS total_in,
              SUM(CASE WHEN tip_miscare = 'O' THEN cantitate_misc ELSE 0 END) AS total_out
            FROM miscari
            WHERE cod_p = :cod_produs
              AND cod_locatie = :cod_locatie";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'cod_produs' => $cod_produs,
        'cod_locatie' => $cod_locatie,
    ]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $total_in = (float)($result['total_in'] ?? 0);
    $total_out = (float)($result['total_out'] ?? 0);
    $final_stock = $total_in - $total_out;

    $sql_update = "UPDATE stoc_produse
                   SET cantitate_stoc = :cantitate_stoc
                   WHERE cod_p = :cod_p
                     AND cod_locatie = :cod_locatie";
    $stmt_update = $pdo->prepare($sql_update);
    $stmt_update->execute([
        'cod_p' => $cod_produs,
        'cod_locatie' => $cod_locatie,
        'cantitate_stoc' => $final_stock,
    ]);

    if ($stmt_update->rowCount() === 0) {
        $sql_insert = "INSERT INTO stoc_produse (cod_p, cod_locatie, cantitate_stoc)
                       VALUES (:cod_p, :cod_locatie, :cantitate_stoc)";
        $stmt_insert = $pdo->prepare($sql_insert);
        $stmt_insert->execute([
            'cod_p' => $cod_produs,
            'cod_locatie' => $cod_locatie,
            'cantitate_stoc' => $final_stock,
        ]);
    }
}

echo json_encode(['status' => 'success']);
