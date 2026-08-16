<?php
require_once __DIR__ . '/det_note_departament_listare_schema.php';
include('session.php'); // Asigură conexiunea la baza de date și sesiunea

if (isset($_POST['idvanz']) && isset($_POST['new_nrbon'])) {
    agecs_ensure_det_note_departament_listare($pdo, $tabel_final_det_note);
    $idvanz = $_POST['idvanz'];
    $new_nrbon = $_POST['new_nrbon'];

    $stmtSource = $pdo->prepare("SELECT nr_bon FROM $tabel_final_det_note WHERE id_vanz = :idvanz");
    $stmtSource->execute([':idvanz' => $idvanz]);
    agecs_snapshot_det_note_departamente(
        $pdo,
        (int)$stmtSource->fetchColumn(),
        $tabel_final_det_note,
        $tabel_final_nomenclator
    );
    
    $sql = "UPDATE $tabel_final_det_note SET nr_bon = :new_nrbon WHERE id_vanz = :idvanz";
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute([':new_nrbon' => $new_nrbon, ':idvanz' => $idvanz])) {
        // Redirecționează către pagina principală după actualizare
        printf("<script>location.href='vanzare_restaurant.php'</script>");
        exit();
    } else {
        echo "Eroare la actualizarea notei.";
    }
} else {
    echo "Parametri lipsă.";
}
?>
