<?php
require_once __DIR__ . '/det_note_departament_listare_schema.php';
include('session.php'); // Conectarea la baza de date și inițierea sesiunii

if (isset($_POST['imparteNotaSubmit']) && isset($_POST['produs_selectat']) && is_array($_POST['produs_selectat']) && !empty($_POST['masa_select'])) {
    // Preluăm codul mesei selectate din formular
    $masaSelectata = $_POST['masa_select'];
    $adm_id=$_SESSION['admin_id'];
    $cod_locatie=$_SESSION['cod_locatie'];
    agecs_ensure_det_note_departament_listare($pdo, $tabel_final_det_note);
    agecs_snapshot_det_note_departamente(
        $pdo,
        (int)($_SESSION['nr_bon'] ?? 0),
        $tabel_final_det_note,
        $tabel_final_nomenclator
    );
    // 1. Inserare nouă notă în tabelul de note, incluzând și codul mesei
    $sqlNewNota = "INSERT INTO $tabel_final_note(operator, locatie, cod_masa) 
                   VALUES ('$adm_id', '$cod_locatie', '$masaSelectata')";
    try {
        $pdo->exec($sqlNewNota) or die(print_r($pdo->errorInfo(), true));
    } catch(PDOException $e) {
        echo $sqlNewNota . "<br>" . $e->getMessage();
        exit();
    }
    
    // 2. Obține noul nr_bon – folosind lastInsertId (presupunând auto-increment)
    $new_nr_bon = $pdo->lastInsertId();
    
    // 3. Actualizează în det_note, pentru rândurile selectate – setând noul nr_bon
    $selectedProducts = $_POST['produs_selectat'];
    $inClause = rtrim(str_repeat('?,', count($selectedProducts)), ',');
    $sqlUpdateDet = "UPDATE $tabel_final_det_note SET nr_bon = ? WHERE id_vanz IN ($inClause)";
    $params = array_merge([$new_nr_bon], $selectedProducts);
    $stmtUpdateDet = $pdo->prepare($sqlUpdateDet);
    $stmtUpdateDet->execute($params);
    
    // 4. Actualizează variabila de sesiune, dacă este necesar
    $_SESSION['nr_bon'] = $new_nr_bon;
    // 5. Marchează masa ca activă (stare = 1)
$sqlUpdateMasa = "UPDATE mese SET stare = 1 WHERE cod_masa = ?";
$stmtUpdateMasa = $pdo->prepare($sqlUpdateMasa);
$stmtUpdateMasa->execute([$masaSelectata]);

    // 6. Redirecționează la pagina principală
    printf("<script>location.href='vanzare_restaurant.php'</script>");
} else {
    // Dacă nu se selectează niciun produs sau nu s-a ales masa
    echo "<script>alert('Selectează cel puțin un produs și alege masa de destinație.');</script>";
    printf("<script>location.href='vanzare_restaurant.php'</script>");
}
?>
