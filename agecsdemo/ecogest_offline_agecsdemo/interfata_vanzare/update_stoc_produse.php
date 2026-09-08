<?php
// update_stoc_produse.php
include('session.php'); // Asigură-te că ai conexiunea PDO

// Obținem toate codurile de produs din tabelul produse_servicii
$sql_prod = "SELECT cod_produs FROM produse_servicii";
$stmt_prod = $pdo->query($sql_prod);

while ($row = $stmt_prod->fetch(PDO::FETCH_ASSOC)) {
    $cod_produs = intval($row['cod_produs']);

    // Calculăm stocul pentru produsul curent
    $sql = "SELECT 
              SUM(CASE WHEN tip_miscare = 'I' THEN cantitate_misc ELSE 0 END) AS total_in,
              SUM(CASE WHEN tip_miscare = 'O' THEN cantitate_misc ELSE 0 END) AS total_out
            FROM miscari
            WHERE cod_p = :cod_produs";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['cod_produs' => $cod_produs]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $total_in = isset($result['total_in']) ? $result['total_in'] : 0;
    $total_out = isset($result['total_out']) ? $result['total_out'] : 0;
    $final_stock = $total_in - $total_out;

    // Inserăm sau actualizăm în tabelul stoc_produse
    // Se presupune că coloana cod_p este cheie primară în stoc_produse
    $sql_insert = "INSERT INTO stoc_produse (cod_p, cantitate_stoc)
                   VALUES (:cod_p, :cantitate_stoc)
                   ON DUPLICATE KEY UPDATE cantitate_stoc = :cantitate_stoc";
    $stmt_insert = $pdo->prepare($sql_insert);
    $stmt_insert->execute([
        'cod_p'          => $cod_produs,
        'cantitate_stoc' => $final_stock
    ]);
}

echo json_encode(['status' => 'success']);
?>
