<?php
// Include fișierul de conexiune la baza de date
include('database_connection.php');

// Setează header-ul pentru a indica un răspuns de tip JSON
header('Content-Type: application/json');

// Verifică dacă cererea este de tip POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Metodă nepermisă.']);
    exit;
}

// Verifică dacă parametrul 'ids' a fost trimis
if (!isset($_POST['ids']) || empty($_POST['ids'])) {
    echo json_encode(['success' => false, 'message' => 'ID-urile produselor lipsesc din cerere.']);
    exit;
}

// ID-urile pot fi multiple, separate prin virgulă (pentru produsele grupate)
$ids_string = $_POST['ids'];
$ids_array = explode(',', $ids_string);

// Validăm și curățăm ID-urile
$placeholders = implode(',', array_fill(0, count($ids_array), '?'));
$sanitized_ids = array_map('intval', $ids_array);

if (empty($sanitized_ids)) {
    echo json_encode(['success' => false, 'message' => 'ID-uri invalide.']);
    exit;
}

try {
    // Începem o tranzacție pentru a asigura integritatea datelor
    $pdo->beginTransaction();

    // Actualizăm toate produsele trimise
    $stmt = $pdo->prepare("UPDATE det_note SET preluat_osp = 2 WHERE id_vanz IN ($placeholders)");
    $stmt->execute($sanitized_ids);

    // Commit tranzacția
    $pdo->commit();

    // Returnează un răspuns de succes
    echo json_encode(['success' => true, 'message' => 'Produsele au fost marcate cu succes.']);

} catch (PDOException $e) {
    // Rollback în caz de eroare
    $pdo->rollBack();
    
    // În caz de eroare la baza de date, înregistrează eroarea și returnează un mesaj
    error_log('Eroare DB (mark_item_completed.php): ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A apărut o eroare la actualizarea datelor.']);
}