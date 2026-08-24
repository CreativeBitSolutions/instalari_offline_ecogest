<?php
// vanzare_update_product_qty.php
include('session.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_vanz'], $_POST['new_qty'])) {
    
    $id_vanz = (int)$_POST['id_vanz'];
    $new_qty = (float)$_POST['new_qty'];

    if ($new_qty <= 0) {
        http_response_code(400);
        echo "Cantitatea trebuie să fie mai mare decât 0.";
        exit;
    }

    try {
        // 1. Preluăm prețul unitar și cota TVA existente pe rândul respectiv
        // IMPORTANT: Luăm pret_vanzare din det_note pentru a păstra eventualele modificări de preț deja făcute
        $sql_select = "SELECT pret_vanzare, cota_tva FROM {$tabel_final_det_note} WHERE id_vanz = :id_vanz";
        $stmt_select = $pdo->prepare($sql_select);
        $stmt_select->execute(['id_vanz' => $id_vanz]);
        $row = $stmt_select->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            http_response_code(404);
            echo "Produsul nu a fost găsit pe bon.";
            exit;
        }

        $pret_unitar = (float)$row['pret_vanzare'];
        $cota_tva = (int)$row['cota_tva'];

        // 2. Recalculăm valorile totale
        // Formula: Valoare cu TVA = Preț unitar * Cantitate nouă
        $new_valoare_vanzare_cu_tva = round($pret_unitar * $new_qty, 2);
        
        // Formula extragere TVA: Valoare * Cota / (100 + Cota)
        $new_tva_col = round($new_valoare_vanzare_cu_tva * $cota_tva / (100 + $cota_tva), 2);
        
        // Formula Valoare fără TVA
        $new_valoare_vanzare = round($new_valoare_vanzare_cu_tva - $new_tva_col, 2);

        // 3. Facem Update în baza de date
        $sql_update = "UPDATE {$tabel_final_det_note} SET 
                            cantitate = :new_qty,
                            valoare_vanzare_cu_tva = :val_cu_tva,
                            tva_col = :tva_col,
                            valoare_vanzare = :val_fara_tva
                        WHERE id_vanz = :id_vanz";
        
        $stmt_update = $pdo->prepare($sql_update);
        
        $stmt_update->execute([
            'new_qty'       => $new_qty,
            'val_cu_tva'    => $new_valoare_vanzare_cu_tva,
            'tva_col'       => $new_tva_col,
            'val_fara_tva'  => $new_valoare_vanzare,
            'id_vanz'       => $id_vanz
        ]);
        
        echo "Success";

    } catch (PDOException $e) {
        http_response_code(500);
        error_log("Eroare la modificare cantitate: " . $e->getMessage());
        echo "A apărut o eroare la salvarea datelor.";
    }
} else {
    http_response_code(400);
    echo "Cerere invalidă.";
}
?>