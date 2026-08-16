<?php
include('session.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nrbon'])) {
    $nrbon = $_POST['nrbon'];

    //  Preluăm codul mesei din bonul respectiv
    $masa_sql = "SELECT cod_masa FROM $tabel_final_note WHERE nrbon = :nrbon AND status = 'S'";
    $masa_stmt = $pdo->prepare($masa_sql);
    $masa_stmt->execute([':nrbon' => $nrbon]);
    $masa = $masa_stmt->fetch(PDO::FETCH_ASSOC);

    if ($masa) {
        $cod_masa = $masa['cod_masa'];

        $update_masa_sql = "UPDATE mese SET vandut_intrare = 1 WHERE cod_masa = :cod_masa";
        $update_masa_stmt = $pdo->prepare($update_masa_sql);
        $update_masa_stmt->execute([':cod_masa' => $cod_masa]);

        //Redirecționăm
        echo "<script>location.href='vanzare_restaurant.php'</script>";
        exit;
    }
}
?>
