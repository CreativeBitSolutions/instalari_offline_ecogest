<?php
// modifica_cantitate_factura.php

// Include conexiunea la baza de date
require_once __DIR__.'/database_connection.php';

// Start the session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verifică dacă datele au fost trimise prin POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obține și validează datele
    $id_vanz = isset($_POST['id_vanz']) ? intval($_POST['id_vanz']) : 0;
    $cantitate = isset($_POST['cantitate']) ? floatval($_POST['cantitate']) : 0;

    if ($id_vanz > 0 && $cantitate > 0) {
        // Obține detaliile produsului
        $select_sql = "SELECT pret_vanzare, cota_tva, id_factura FROM vanzari WHERE id_vanz = :id_vanz";
        $select_stmt = $pdo->prepare($select_sql);
        $select_stmt->bindParam(':id_vanz', $id_vanz, PDO::PARAM_INT);
        $select_stmt->execute();

        if ($product = $select_stmt->fetch(PDO::FETCH_ASSOC)) {
            $pret_vanzare = floatval($product['pret_vanzare']);
            $cota_tva = floatval($product['cota_tva']);
            $id_factura = intval($product['id_factura']);

            // Calculăm noile valori
            $valoare_vanzare_cu_tva = $pret_vanzare * $cantitate;
            $tva_col = $valoare_vanzare_cu_tva * $cota_tva / (100 + $cota_tva);
            $valoare_vanzare = $valoare_vanzare_cu_tva - $tva_col;

            // Actualizăm în baza de date
            $update_sql = "UPDATE vanzari 
                           SET cantitate = :cantitate, 
                               valoare_vanzare = :valoare_vanzare, 
                               tva_col = :tva_col, 
                               valoare_vanzare_cu_tva = :valoare_vanzare_cu_tva 
                           WHERE id_vanz = :id_vanz";
            $update_stmt = $pdo->prepare($update_sql);
            try {
                $update_stmt->execute([
                    ':cantitate' => $cantitate,
                    ':valoare_vanzare' => $valoare_vanzare,
                    ':tva_col' => $tva_col,
                    ':valoare_vanzare_cu_tva' => $valoare_vanzare_cu_tva,
                    ':id_vanz' => $id_vanz
                ]);
     // Actualizăm și în tabela miscari: adăugăm cantitatea nouă la cantitate_misc
     $psql_miscari_update = "UPDATE miscari 
     SET cantitate_misc = :cantitate_misc 
     WHERE id_vanz_fact = :id_vanz";
try {
$stmt_miscari_update = $pdo->prepare($psql_miscari_update);
$stmt_miscari_update->execute([
':cantitate_misc' => $cantitate,
':id_vanz'        => $id_vanz
]);
echo json_encode(['success' => true]);
} catch (PDOException $e) {
file_put_contents('error_log.txt', "Update Miscari Error: " . $e->getMessage(), FILE_APPEND);
echo json_encode(['success' => false, 'error' => 'A apărut o eroare la actualizarea mișcării. Verifică logul pentru detalii.']);
}
                // Redirecționează înapoi la factura curentă
                header("Location: factura.php?id_factura={$id_factura}");
                exit;
            } catch (PDOException $e) {
                // Înregistrează eroarea și afișează un mesaj de eroare
                file_put_contents('error_log.txt', "Update Quantity Error: " . $e->getMessage(), FILE_APPEND);
                echo "A apărut o eroare la actualizarea cantității. Te rugăm să încerci din nou.";
            }


        
        } else {
            echo "Produs inexistent.";
        }
    } else {
        echo "Date invalide. Te rugăm să verifici cantitatea introdusă.";
    }
} else {
    echo "Metodă de cerere invalidă.";
}
?>
