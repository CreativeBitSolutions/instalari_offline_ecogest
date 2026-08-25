<?php
include('session.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nrbon'])) {
    $nrbon = $_POST['nrbon'];

    // 1. Preluăm codul mesei din bonul respectiv
    $masa_sql = "SELECT cod_masa FROM $tabel_final_note WHERE nrbon = :nrbon AND status = 'S'";
    $masa_stmt = $pdo->prepare($masa_sql);
    $masa_stmt->execute([':nrbon' => $nrbon]);
    $masa = $masa_stmt->fetch(PDO::FETCH_ASSOC);

    if ($masa) {
        $cod_masa = $masa['cod_masa'];

        // 2. Resetăm codul mesei în note
        $update_note_sql = "UPDATE $tabel_final_note SET cod_masa = 0 WHERE nrbon = :nrbon AND status = 'S'";
        $update_note_stmt = $pdo->prepare($update_note_sql);
        $update_note_stmt->execute([':nrbon' => $nrbon]);

        // 3. Setăm starea mesei ca liberă
        $update_masa_sql = "UPDATE mese SET stare = 0 WHERE cod_masa = :cod_masa";
        $update_masa_stmt = $pdo->prepare($update_masa_sql);
        $update_masa_stmt->execute([':cod_masa' => $cod_masa]);

        // 4. Resetăm sesiunea
        unset($_SESSION['nr_bon']);
        unset($_SESSION['masa_curenta']);

        $_SESSION['trimis_comanda'] = 0;

        // 5. Redirecționăm
        echo "<script>location.href='vanzare_renunta_alegere_masa.php'</script>";
        exit;
    }
}
?>
