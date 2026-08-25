<?php //vanzare_reset_pret.php
include 'database_connection.php'; // Fișierul tău de configurare cu conexiunea PDO

header('Content-Type: application/json');

try {
    $id_vanz = $_POST['id_vanz'];
    $pret_vanzare = $_POST['pret_vanzare'];

    // Obținem datele necesare direct din tabelul det_note
    $sql_select = "SELECT cantitate, cota_tva 
                  FROM $tabel_final_det_note 
                  WHERE id_vanz = :id_vanz";
    $stmt_select = $pdo->prepare($sql_select);
    $stmt_select->execute([':id_vanz' => $id_vanz]);
    $row = $stmt_select->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $cantitate = $row['cantitate'];
        $cota_tva = $row['cota_tva'];

        // Calculăm valorile conform formulelor
        $valoare_vanzare_cu_tva = round($pret_vanzare * $cantitate, 2);
        $tva_col = round($valoare_vanzare_cu_tva * $cota_tva / (100 + $cota_tva), 2);
        $valoare_vanzare = round($valoare_vanzare_cu_tva - $tva_col, 2);

        // === Ștergem logurile de discount pentru această linie (resetare preț) ===
        $sql_del_disc = "DELETE FROM discounturi_acordate WHERE id_vanz = :id_vanz";
        $stmt_del_disc = $pdo->prepare($sql_del_disc);
        $stmt_del_disc->execute([':id_vanz' => $id_vanz]);
        // ======================================================================

        // Actualizăm toate coloanele
        $sql_update = "UPDATE $tabel_final_det_note 
                      SET pret_vanzare = :pret_vanzare,
                          valoare_vanzare_cu_tva = :valoare_vanzare_cu_tva,
                          tva_col = :tva_col,discount = 0,
                          valoare_vanzare = :valoare_vanzare
                      WHERE id_vanz = :id_vanz";
        
        $stmt_update = $pdo->prepare($sql_update);
        $stmt_update->execute([
            ':pret_vanzare' => $pret_vanzare,
            ':valoare_vanzare_cu_tva' => $valoare_vanzare_cu_tva,
            ':tva_col' => $tva_col,
            ':valoare_vanzare' => $valoare_vanzare,
            ':id_vanz' => $id_vanz
        ]);

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Înregistrarea nu a fost găsită']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
