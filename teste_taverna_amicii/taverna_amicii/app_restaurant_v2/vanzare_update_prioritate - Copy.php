<?php
include('session.php');

// Verificăm dacă suntem logați și primim datele necesare
if (!isset($_SESSION['admin_id']) || !isset($_POST['id_vanz'], $_POST['current_priority'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Date invalide sau sesiune expirată.']);
    exit;
}

$id_vanz = intval($_POST['id_vanz']);
$current_priority = intval($_POST['current_priority']);

// Logica de ciclare a priorității: 0 -> 1 -> 2 -> 3 -> 1
if ($current_priority == 0) {
    $next_priority = 1;
} elseif ($current_priority == 1) {
    $next_priority = 2;
} elseif ($current_priority == 2) {
    $next_priority = 3;
} else { // Ciclează de la 3 (sau orice altă valoare) înapoi la 1
    $next_priority = 1;
}

// Pregătim și executăm actualizarea în baza de date
try {
    $sql = "UPDATE $tabel_final_det_note SET prioritate = :next_priority WHERE id_vanz = :id_vanz";
    $stmt = $pdo->prepare($sql);
    
    $stmt->execute([
        ':next_priority' => $next_priority,
        ':id_vanz' => $id_vanz
    ]);

    // Verificăm dacă actualizarea a avut succes
    if ($stmt->rowCount() > 0) {
        // Trimitem un răspuns JSON de succes
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Produsul nu a fost găsit sau actualizarea a eșuat.']);
    }

} catch (PDOException $e) {
    // Logăm eroarea și trimitem un răspuns de eroare
    error_log("Eroare la actualizarea prioritatii: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Eroare de server.']);
}

?>