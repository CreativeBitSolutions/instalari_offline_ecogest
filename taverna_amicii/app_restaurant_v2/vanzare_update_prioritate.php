<?php
include('session.php');

header('Content-Type: application/json; charset=utf-8');

// Verificăm dacă suntem logați și primim datele necesare
if (!isset($_SESSION['admin_id']) || !isset($_POST['id_vanz'])) {
    echo json_encode(['success' => false, 'message' => 'Date invalide sau sesiune expirată.']);
    exit;
}

$id_vanz = intval($_POST['id_vanz']);

if ($id_vanz <= 0) {
    echo json_encode(['success' => false, 'message' => 'Produs invalid.']);
    exit;
}

// Dacă vine new_priority din JS, îl folosim direct.
// Astfel funcționează și valori negative: -1, -2 etc.
// Dacă nu vine new_priority, păstrăm logica veche de ciclare.
if (isset($_POST['new_priority']) && preg_match('/^-?\d+$/', (string)$_POST['new_priority'])) {
    $next_priority = intval($_POST['new_priority']);
} else {
    if (!isset($_POST['current_priority'])) {
        echo json_encode(['success' => false, 'message' => 'Prioritate lipsă.']);
        exit;
    }

    $current_priority = intval($_POST['current_priority']);

    // Logica veche de ciclare a priorității: 0 -> 1 -> 2 -> 3 -> 1
    if ($current_priority == 0) {
        $next_priority = 1;
    } elseif ($current_priority == 1) {
        $next_priority = 2;
    } elseif ($current_priority == 2) {
        $next_priority = 3;
    } else { // Ciclează de la 3 (sau orice altă valoare) înapoi la 1
        $next_priority = 1;
    }
}

// Pregătim și executăm actualizarea în baza de date
try {
    $sql = "UPDATE $tabel_final_det_note SET prioritate = :next_priority WHERE id_vanz = :id_vanz";
    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':next_priority' => $next_priority,
        ':id_vanz' => $id_vanz
    ]);

    // rowCount poate fi 0 dacă valoarea era deja aceeași, dar operația este validă
    echo json_encode([
        'success' => true,
        'prioritate' => $next_priority
    ]);

} catch (PDOException $e) {
    // Logăm eroarea și trimitem un răspuns de eroare
    error_log("Eroare la actualizarea prioritatii: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Eroare de server.']);
}

?>
