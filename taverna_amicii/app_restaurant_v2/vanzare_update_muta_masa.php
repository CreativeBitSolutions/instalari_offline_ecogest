<?php
/**
 * vanzare_update_muta_masa.php
 * Mută TOATE produsele de pe nota curentă pe altă notă (altă masă).
 */
include('session.php');
require_once __DIR__ . '/det_note_departament_listare_schema.php';
agecs_ensure_det_note_departament_listare($pdo, $tabel_final_det_note);

if (isset($_POST['new_nrbon'])) {
    $new_nrbon = (int)$_POST['new_nrbon'];
    $old_nrbon = (int)($_SESSION['nr_bon'] ?? 0);

    if ($new_nrbon <= 0 || $old_nrbon <= 0 || $new_nrbon === $old_nrbon) {
        echo "Parametri invalizi.";
        exit;
    }

    try {
        agecs_snapshot_det_note_departamente(
            $pdo,
            $old_nrbon,
            $tabel_final_det_note,
            $tabel_final_nomenclator
        );
        $pdo->beginTransaction();

        // Mută toate produsele de pe nota veche pe nota nouă
        $sql = "UPDATE $tabel_final_det_note SET nr_bon = :new_nrbon WHERE nr_bon = :old_nrbon";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':new_nrbon' => $new_nrbon, ':old_nrbon' => $old_nrbon]);
        $pdo->commit();
       // NU SCHIMBAM NOTA SI SESIUNEA VECHE, CA SA VADA CA I-A RAMAS GOALA
        printf("<script>location.href='vanzare_restaurant.php'</script>");

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo "Eroare: " . htmlspecialchars($e->getMessage());
    }
} else {
    echo "Parametri lipsă.";
}
?>
