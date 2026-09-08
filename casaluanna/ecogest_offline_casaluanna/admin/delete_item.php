<?php
// Start the session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// delete_item.php
require_once __DIR__.'/database_connection.php';

// Obținem id_factura și id_vanz din datele POST
$id_factura = isset($_POST['id_factura']) ? intval($_POST['id_factura']) : 0;
$id_vanz = isset($_POST['id_vanz']) ? intval($_POST['id_vanz']) : 0;

try {
    $pdo->beginTransaction();
    // Selectăm cod_p și den_p din tabela vanzari
    $select_sql = "SELECT cod_p, den_p FROM vanzari WHERE id_vanz = :id_vanz";
    $select_stmt = $pdo->prepare($select_sql);
    $select_stmt->bindParam(':id_vanz', $id_vanz, PDO::PARAM_INT);
    $select_stmt->execute();
    $result = $select_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        echo json_encode(['success' => false, 'error' => 'Datele vanzării nu au fost găsite.']);
        exit;
    }

    $cod_p = $result['cod_p'];
    $den_p = $result['den_p'];

    // Ștergem din tabela vanzari
    $delete_vanzari_sql = "DELETE FROM vanzari WHERE id_vanz = :id_vanz AND id_factura = :id_factura";
    $delete_vanzari_stmt = $pdo->prepare($delete_vanzari_sql);
    $delete_vanzari_stmt->bindParam(':id_vanz', $id_vanz, PDO::PARAM_INT);
    $delete_vanzari_stmt->bindParam(':id_factura', $id_factura, PDO::PARAM_INT);

  // Ștergem din tabela miscari doar pe baza id_vanz_fact
$delete_miscari_sql = "
DELETE FROM miscari 
WHERE id_vanz_fact = :id_vanz_fact";
$delete_miscari_stmt = $pdo->prepare($delete_miscari_sql);
$delete_miscari_stmt->bindParam(':id_vanz_fact', $id_vanz, PDO::PARAM_INT);

    // Executăm interogările
    $delete_vanzari_stmt->execute();
    $delete_miscari_stmt->execute();

    // Returnăm răspunsul de succes
    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    if($pdo->inTransaction())$pdo->rollBack();
    file_put_contents('error_log.txt', "Delete Item Error: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'error' => 'A apărut o eroare la ștergerea datelor. Verifică logul pentru detalii.']);
}
?>
