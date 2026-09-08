<?php
session_start();
include('database_connection.php');

if (isset($_POST['exec_stergere'])) {

    $id_fact_stergere = filter_input(INPUT_POST, 'id_fact_stergere', FILTER_VALIDATE_INT);
    if ($id_fact_stergere === false || $id_fact_stergere === null || $id_fact_stergere <= 0) {
        $_SESSION['message'] = "Factura nu a putut fi identificată. Selectați din nou factura din listă.";
        $_SESSION['message_type'] = "danger";
        header("Location: facturi.php");
        exit;
    }

    try {
        // 1) Verificare dacă factura a fost deja încărcată la ANAF
        $f_sql_check_incarcari = "SELECT COUNT(*) as count FROM istoric_incarcari WHERE id_factura = :id_fact_stergere";
        $f_stmt_check_incarcari = $pdo->prepare($f_sql_check_incarcari);
        $f_stmt_check_incarcari->bindParam(':id_fact_stergere', $id_fact_stergere, PDO::PARAM_INT);
        $f_stmt_check_incarcari->execute();
        $result_incarcari = $f_stmt_check_incarcari->fetch(PDO::FETCH_ASSOC);

        if ($result_incarcari['count'] > 0) {
            $_SESSION['message'] = "Factura nu poate fi ștearsă deoarece a fost trimisă deja la ANAF. Stornați-o în schimb.";
            $_SESSION['message_type'] = "danger";
            header("Location: facturi.php");
            exit;
        }

        // 2) Luăm nr/serie înainte de ștergere
        $f_sql_select = "SELECT nr_factura, serie_factura FROM facturi WHERE id_factura = :id_fact_stergere";
        $f_stmt_select = $pdo->prepare($f_sql_select);
        $f_stmt_select->bindParam(':id_fact_stergere', $id_fact_stergere, PDO::PARAM_INT);
        $f_stmt_select->execute();
        $factura = $f_stmt_select->fetch(PDO::FETCH_ASSOC);

        if (!$factura) {
            $_SESSION['message'] = "Factura cu ID-ul $id_fact_stergere nu a fost găsită.";
            $_SESSION['message_type'] = "danger";
            header("Location: facturi.php");
            exit;
        }

        $nr_factura = $factura['nr_factura'];
        $serie_factura = $factura['serie_factura'];


        // Verificare: nu permitem ștergerea unei facturi dacă există facturi mai recente în aceeași serie.
        // Ștergerea trebuie făcută descrescător: mai întâi cea mai recentă.
        $nr_factura_int = intval($nr_factura);
        if ($nr_factura_int > 0 && trim((string)$serie_factura) !== '') {
            $sql_facturi_mai_recente = "SELECT MAX(CAST(nr_factura AS UNSIGNED)) AS max_nr
                                        FROM facturi
                                        WHERE serie_factura = :serie_factura
                                          AND id_factura != :id_fact_stergere
                                          AND nr_factura REGEXP '^[0-9]+$'
                                          AND CAST(nr_factura AS UNSIGNED) > :nr_factura";
            $stmt_facturi_mai_recente = $pdo->prepare($sql_facturi_mai_recente);
            $stmt_facturi_mai_recente->execute([
                ':serie_factura' => $serie_factura,
                ':id_fact_stergere' => $id_fact_stergere,
                ':nr_factura' => $nr_factura_int
            ]);
            $factura_mai_recenta = $stmt_facturi_mai_recente->fetch(PDO::FETCH_ASSOC);
            $max_nr_mai_recent = isset($factura_mai_recenta['max_nr']) ? intval($factura_mai_recenta['max_nr']) : 0;

            if ($max_nr_mai_recent > 0) {
                $_SESSION['message'] = "Factura $nr_factura din seria $serie_factura nu poate fi ștearsă deoarece există facturi mai recente în aceeași serie. Trebuie să ștergeți facturile descrescător, de la cea mai recentă la cea mai veche. Exemplu concret: dacă există facturile 1037, 1038 și 1039, trebuie să ștergeți 1039 înainte de 1038.";
                $_SESSION['message_type'] = "danger";
                header("Location: facturi.php");
                exit;
            }
        }
        // === ÎNCEPEM TRANZACȚIA ===
        $pdo->beginTransaction();

        // 3) Colectăm TOATE id_vanz pentru factura curentă
        $sql_get_vanz = "SELECT id_vanz FROM vanzari WHERE id_factura = :id_fact_stergere";
        $stmt_get_vanz = $pdo->prepare($sql_get_vanz);
        $stmt_get_vanz->bindParam(':id_fact_stergere', $id_fact_stergere, PDO::PARAM_INT);
        $stmt_get_vanz->execute();
        $idVanzList = $stmt_get_vanz->fetchAll(PDO::FETCH_COLUMN, 0);

        // 4) Ștergem din 'miscari' pe baza id_vanz_fact IN (...)
        if (!empty($idVanzList)) {
            // pregătim placeholder-ele: ?, ?, ?, ...
            $placeholders = implode(',', array_fill(0, count($idVanzList), '?'));
            $sql_miscari_delete = "DELETE FROM miscari WHERE id_vanz_fact IN ($placeholders)";
            $stmt_miscari_delete = $pdo->prepare($sql_miscari_delete);
            $stmt_miscari_delete->execute($idVanzList);
        }

        // 5) Ștergem înregistrările dependente
        $f_sql_v_delete = "DELETE FROM vanzari WHERE id_factura = :id_fact_stergere";
        $f_stmt_v_delete = $pdo->prepare($f_sql_v_delete);
        $f_stmt_v_delete->bindParam(':id_fact_stergere', $id_fact_stergere, PDO::PARAM_INT);
        $f_stmt_v_delete->execute();

        $f_sql_validari_delete = "DELETE FROM istoric_validari WHERE id_factura = :id_fact_stergere";
        $f_stmt_validari_delete = $pdo->prepare($f_sql_validari_delete);
        $f_stmt_validari_delete->bindParam(':id_fact_stergere', $id_fact_stergere, PDO::PARAM_INT);
        $f_stmt_validari_delete->execute();

        $sql_stornari_delete = "DELETE FROM stornari WHERE id_factura = :id_fact_stergere";
        $stmt_stornari_delete = $pdo->prepare($sql_stornari_delete);
        $stmt_stornari_delete->bindParam(':id_fact_stergere', $id_fact_stergere, PDO::PARAM_INT);
        $stmt_stornari_delete->execute();

        // 6) Ștergem factura propriu-zisă
        $f_sql_f_delete = "DELETE FROM facturi WHERE id_factura = :id_fact_stergere";
        $f_stmt_f_delete = $pdo->prepare($f_sql_f_delete);
        $f_stmt_f_delete->bindParam(':id_fact_stergere', $id_fact_stergere, PDO::PARAM_INT);
        $f_stmt_f_delete->execute();

        // === COMMIT ===
        $pdo->commit();

        $_SESSION['message'] = "Factura cu nr: $nr_factura și seria: $serie_factura a fost ștearsă cu succes.";
        $_SESSION['message_type'] = "success";

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['message'] = "Eroare la ștergere: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
    }

    header("Location: facturi.php");
    exit;
}
