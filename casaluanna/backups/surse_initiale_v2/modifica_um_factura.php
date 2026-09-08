<?php 
// modifica_um_factura.php

// Include conexiunea la baza de date
include('database_connection.php');

// Start the session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verifică dacă datele au fost trimise prin POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obține și validează datele
    $id_vanz = isset($_POST['id_vanz']) ? intval($_POST['id_vanz']) : 0;
    $um = isset($_POST['um']) ? trim($_POST['um']) : '';

    if ($id_vanz > 0 && !empty($um)) {
        try {
            // Actualizăm în baza de date
            $update_sql = "UPDATE vanzari 
                           SET um = :um 
                           WHERE id_vanz = :id_vanz";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([
                ':um' => $um,
                ':id_vanz' => $id_vanz
            ]);

            // Redirecționează înapoi la factura curentă
            // Pentru a obține id_factura, putem face un SELECT
            $select_sql = "SELECT id_factura FROM vanzari WHERE id_vanz = :id_vanz";
            $select_stmt = $pdo->prepare($select_sql);
            $select_stmt->execute([':id_vanz' => $id_vanz]);
            $product = $select_stmt->fetch(PDO::FETCH_ASSOC);
            $id_factura = intval($product['id_factura']);

            header("Location: factura.php?id_factura={$id_factura}");
            exit;
        } catch (PDOException $e) {
            // Înregistrează eroarea și afișează un mesaj de eroare
            file_put_contents('error_log.txt', "Update UM Error: " . $e->getMessage(), FILE_APPEND);
            echo "A apărut o eroare la actualizarea unității de măsură. Te rugăm să încerci din nou.";
        }
    } else {
        echo "Date invalide. Te rugăm să verifici unitatea de măsură selectată.";
    }
} else {
    echo "Metodă de cerere invalidă.";
}
?>
