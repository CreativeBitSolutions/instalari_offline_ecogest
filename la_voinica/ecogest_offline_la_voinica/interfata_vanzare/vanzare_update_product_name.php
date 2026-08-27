<?php //vanzare_update_product_name.php
include('session.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_vanz'], $_POST['new_name'], $_POST['new_price'])) {
    
    $id_vanz = (int)$_POST['id_vanz'];
    $new_name = trim($_POST['new_name']);
    $new_price = (float)$_POST['new_price'];

    if (empty($new_name) || !is_numeric($_POST['new_price'])) {
        http_response_code(400);
        echo "Date invalide. Numele nu poate fi gol și prețul trebuie să fie numeric.";
        exit;
    }

    try {
        // Pasul 1: Preluăm datele necesare (cantitate, cota tva) de pe rândul existent
        $sql_select = "SELECT cantitate, cota_tva FROM {$tabel_final_det_note} WHERE id_vanz = :id_vanz";
        $stmt_select = $pdo->prepare($sql_select);
        $stmt_select->execute(['id_vanz' => $id_vanz]);
        $row = $stmt_select->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            http_response_code(404);
            echo "Produsul nu a fost găsit pe bon.";
            exit;
        }

        $cantitate = (float)$row['cantitate'];
        $cota_tva = (int)$row['cota_tva'];

        // Pasul 2: Refacem calculele exact ca în scriptul de adăugare
        $new_valoare_vanzare_cu_tva = round($new_price * $cantitate, 2);
        $new_tva_col = round($new_valoare_vanzare_cu_tva * $cota_tva / (100 + $cota_tva), 2);
        $new_valoare_vanzare = round($new_valoare_vanzare_cu_tva - $new_tva_col, 2);

        // Pasul 3: Construim și executăm query-ul de UPDATE
        $sql_update = "UPDATE {$tabel_final_det_note} SET 
                            nume_produs = :new_name,
                            pret_vanzare = :new_price,
                            valoare_vanzare_cu_tva = :val_cu_tva,
                            tva_col = :tva_col,
                            valoare_vanzare = :val_fara_tva
                        WHERE id_vanz = :id_vanz";
        
        $stmt_update = $pdo->prepare($sql_update);
        
        $stmt_update->execute([
            'new_name'      => $new_name,
            'new_price'     => $new_price,
            'val_cu_tva'    => $new_valoare_vanzare_cu_tva,
            'tva_col'       => $new_tva_col,
            'val_fara_tva'  => $new_valoare_vanzare,
            'id_vanz'       => $id_vanz
        ]);
        
        echo "Success";

    } catch (PDOException $e) {
        http_response_code(500);
        error_log("Eroare la actualizare produs pe bon: " . $e->getMessage());
        echo "A apărut o eroare la salvarea datelor.";
    }
} else {
    http_response_code(400);
    echo "Cerere invalidă.";
}
?>