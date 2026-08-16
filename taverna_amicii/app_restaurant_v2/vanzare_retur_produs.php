<?php
include('session.php'); // Asigură conexiunea la baza de date și sesiunea

$id_operator = $_SESSION['admin_id'];

if (isset($_POST['idvanz'])) {
    $idvanz = $_POST['idvanz'];

    try {
        // Începem tranzacția
        $pdo->beginTransaction();

        // Copierea datelor din det_note în tabela retururi + setare operator din sesiune
        $sqlCopy = "INSERT INTO retururi (
                        id_vanz, nr_bon, cod_p, nume_produs, cantitate, cota_tva,
                        tva_col, pret_vanzare, valoare_vanzare, valoare_vanzare_cu_tva,
                        discount, pachet, preparat, t_list, data, ora, cod_meniu,
                        observatie_produs, preluat_osp, operator
                    )
                    SELECT
                        id_vanz, nr_bon, cod_p, nume_produs, cantitate, cota_tva,
                        tva_col, pret_vanzare, valoare_vanzare, valoare_vanzare_cu_tva,
                        discount, pachet, preparat, t_list, data, ora, cod_meniu,
                        observatie_produs, preluat_osp, :id_operator
                    FROM $tabel_final_det_note
                    WHERE id_vanz = :idvanz";
        $stmtCopy = $pdo->prepare($sqlCopy);
        if (!$stmtCopy->execute([':idvanz' => $idvanz, ':id_operator' => $id_operator])) {
            throw new Exception("Eroare la copierea în retururi");
        }

        // Ștergerea înregistrării din det_note
        $sqlDelete = "DELETE FROM $tabel_final_det_note WHERE id_vanz = :idvanz";
        $stmtDelete = $pdo->prepare($sqlDelete);
        if (!$stmtDelete->execute([':idvanz' => $idvanz])) {
            throw new Exception("Eroare la ștergerea înregistrării");
        }

        // Finalizăm tranzacția
        $pdo->commit();
        printf("<script>location.href='vanzare_restaurant.php'</script>");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        echo $e->getMessage();
    }

} else {
    echo "Parametri lipsă.";
}
?>
