<?php
// set_zero_item.php

// Includem conexiunea la bază de date
require_once __DIR__.'/database_connection.php';

// Obținem id_vanz din POST
$id_vanz = isset($_POST['id_vanz']) ? intval($_POST['id_vanz']) : 0;

try {
    // Actualizăm valorile la 0, dar lăsăm cota_tva, denumirea și cantitatea neschimbate
    // Se pun pe 0: prețul, valoarea, tva-ul colectat și valoarea totală
    $update_sql = "UPDATE vanzari 
                   SET pret_vanzare = 0, 
                       valoare_vanzare = 0, 
                       tva_col = 0, 
                       valoare_vanzare_cu_tva = 0 
                   WHERE id_vanz = :id_vanz";
                   
    $stmt = $pdo->prepare($update_sql);
    $stmt->bindParam(':id_vanz', $id_vanz, PDO::PARAM_INT);
    $stmt->execute();

    // Returnăm succes
    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    // În caz de eroare
    echo json_encode(['success' => false, 'error' => 'Eroare SQL: ' . $e->getMessage()]);
}
?>