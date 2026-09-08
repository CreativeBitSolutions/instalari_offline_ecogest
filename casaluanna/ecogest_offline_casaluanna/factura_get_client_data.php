<?php
// get_client_data.php
require_once __DIR__.'/database_connection.php';

if (isset($_GET['id_client'])) {
    $id_client = intval($_GET['id_client']);
    
    $sql = "SELECT cod_fiscal, nume, cod_inmatriculare, banca, iban, adresa, adresa_localitate, adresa_judet 
            FROM clienti WHERE id_client = :id_client";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_client', $id_client, PDO::PARAM_INT);
    $stmt->execute();
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($client) {
        echo json_encode($client);
    } else {
        echo json_encode(['error' => 'Clientul nu a fost găsit']);
    }
} else {
    echo json_encode(['error' => 'ID client lipsă']);
}
?>
