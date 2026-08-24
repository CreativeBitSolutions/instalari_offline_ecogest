
<?php ini_set('display_errors', 0); // Nu afișează erorile utilizatorului
ini_set('log_errors', 1); // Activează logarea erorilor
ini_set('error_log', 'error_log.log'); // Specifică calea către fișierul de log
error_reporting(E_ALL); // Raportează toate tipurile de erori
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    exit('Sesiune invalidă.');
}

include 'db.php';

// Funcție pentru recalcularea și actualizarea totalurilor în tabela NIR
 function actualizeaza_nir($pdo, $nr_nir) {
    // Sumați valorile din tabela achizitii pentru acest nr_nir
    $sql_sum = "
        SELECT 
            SUM(valoare_achizitie) AS total_valoare_fara_tva,
            SUM(tva_adaos_comercial) AS total_tva_neex,
            SUM(valoare_tva_achizitie) AS total_tva_ded,
            SUM(valoare_adaos) AS total_ad_unit,
            SUM(valoare_adaos) AS total_valoare_adaos,
            SUM(tva_adaos_comercial) AS total_tva_neex_ad_com
        FROM achizitii
        WHERE nr_nir = :nr_nir
    ";
    $stmt_sum = $pdo->prepare($sql_sum);
    $stmt_sum->execute(['nr_nir' => $nr_nir]);
    $sum_data = $stmt_sum->fetch(PDO::FETCH_ASSOC);

    // Calculați valorile totale
    $total_valoare_fara_tva = $sum_data['total_valoare_fara_tva'] ?? 0;
    $total_tva_neex = $sum_data['total_tva_neex'] ?? 0;
    $total_tva_ded = $sum_data['total_tva_ded'] ?? 0;
    $total_ad_unit = $sum_data['total_ad_unit'] ?? 0;
    $total_valoare_adaos = $sum_data['total_valoare_adaos'] ?? 0;
    $total_tva_neex_ad_com = $sum_data['total_tva_neex_ad_com'] ?? 0;

    // Calculați valoarea totală fără TVA
    $valoare_totala = $total_valoare_fara_tva + $total_ad_unit;

    // Calculați valoarea totală cu TVA
    $valoare_totala_cu_tva = $valoare_totala + $total_tva_neex_ad_com;

    // Actualizați tabela NIR
    $sql_update_nir = "
        UPDATE nir SET
            ad_com_total = :ad_com_total,
            tva_neex_ad_com = :tva_neex_ad_com,
            tva_ded = :tva_ded,
            val_nir_ftva = :val_nir_ftva,
            valoare_totala = :valoare_totala,
            valoare_totala_cu_tva = :valoare_totala_cu_tva
        WHERE nr_nir = :nr_nir
    ";
    $stmt_update_nir = $pdo->prepare($sql_update_nir);
    $stmt_update_nir->execute([
        'ad_com_total' => $total_ad_unit,
        'tva_neex_ad_com' => $total_tva_neex_ad_com,
        'tva_ded' => $total_tva_ded,
        'val_nir_ftva' => $total_valoare_fara_tva,
        'valoare_totala' => $valoare_totala,
        'valoare_totala_cu_tva' => $valoare_totala_cu_tva,
        'nr_nir' => $nr_nir
    ]);
}
 // Handle product deletion
if (isset($_POST['action']) && $_POST['action'] == 'sterge_produs') {
    $id_achiz = $_POST['id_achiz'];

    if (!isset($_SESSION['nr_nir'])) {
        http_response_code(400);
        exit('Lipsește numărul NIR din sesiune.');
    }

    $nr_nir = $_SESSION['nr_nir'];

    // Începeți o tranzacție
    $pdo->beginTransaction();

    try {
 
            // Ștergem din miscari
            $sql_delete_miscare = "DELETE FROM miscari 
                                      where id_achiz = :id_achiz                                     
                                    ";
            $stmt_delete_miscare = $pdo->prepare($sql_delete_miscare);
            $stmt_delete_miscare->execute([
                'id_achiz' => $id_achiz
            ]);

            // Ștergem din achizitii
            $sql_delete = "DELETE FROM achizitii WHERE id_achiz = :id_achiz";
            $stmt_delete = $pdo->prepare($sql_delete);
            $stmt_delete->execute(['id_achiz' => $id_achiz]);

            // Actualizează tabela NIR
            actualizeaza_nir($pdo, $nr_nir);
        

        // Confirmați tranzacția
        $pdo->commit();

        echo "<script>location.href='nir.php';</script>";
    } catch (Exception $e) {
        // Anulați tranzacția în caz de eroare
        $pdo->rollBack();
        echo "Eroare la ștergerea produsului: " . htmlspecialchars($e->getMessage());
    }
}
?>