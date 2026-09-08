<?php
require_once 'database_connection.php';
if (session_status() != PHP_SESSION_ACTIVE) { 
    session_start(); 
}

// Verificăm dacă avem un ID de factură prin GET
if (isset($_GET['id_factura'])) {
    $id_factura = intval($_GET['id_factura']); // Preluăm și validăm ID-ul facturii

    try {
        // Începem o tranzacție pentru a asigura integritatea datelor
        $pdo->beginTransaction();

        // Preluăm factura originală din baza de date
        $stmt = $pdo->prepare("SELECT * FROM facturi WHERE id_factura = :id_factura");
        $stmt->execute(['id_factura' => $id_factura]);
        $old_factura = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$old_factura) {
            // Factura originală nu a fost găsită
            echo "<script>alert('Factura originală nu a fost găsită.'); window.history.back();</script>";
            exit;
        }

       // Verificăm tipul facturii originale
       $original_tip_factura = intval($old_factura['tip_factura']);

       // Setăm tip_factura pentru noua factură
       $new_tip_factura = 384; // Presupunând că 384 indică o factură corectată

       // Obținem seria facturii originale
       $serie_factura = $old_factura['serie_factura'];

       // Generăm un nou număr de factură în funcție de seria facturii
       $stmt_max = $pdo->prepare("SELECT MAX(nr_factura) AS max_nr FROM facturi WHERE serie_factura = :serie_factura");
       $stmt_max->execute(['serie_factura' => $serie_factura]);
       $result = $stmt_max->fetch(PDO::FETCH_ASSOC);
       $max_nr_factura = $result['max_nr'] !== null ? intval($result['max_nr']) : 0;
       $new_nr_factura = $max_nr_factura + 1;

       // Pregătim datele pentru noua factură
       $new_factura = $old_factura;
$new_factura['data_incarcare']='0000-00-00 00:00:00';
$new_factura['data_validare']='0000-00-00 00:00:00';
$new_factura['nrbon']=0;
$new_factura['nr_nota']=0;
$new_factura['factura_restaurant']=0;
$new_factura['id_deviz']=0;
$new_factura['data_stornare']=null;
$new_factura['id_factura_stornare']=null;
       unset($new_factura['id_factura']); // Eliminăm 'id_factura' pentru a permite DB să aloce unul nou
       $new_factura['nr_factura'] = $new_nr_factura;
       $new_factura['data_factura'] = date('Y-m-d'); // Setăm data emiterii la data curentă sau ajustați după necesități
       $new_factura['data_scadenta'] = date('Y-m-d', strtotime('+30 days')); // Exemplu: scadență la 30 de zile
       $new_factura['tip_factura'] = $new_tip_factura;
       $new_factura['data_validare'] = '0000-00-00 00:00:00'; // Setăm data_validare la valoarea specificată
       $new_factura['data_incarcare'] = '0000-00-00 00:00:00'; // Setăm data_validare la valoarea specificată

        // Dacă factura originală are tip_factura = 381, păstrăm anumite câmpuri nealterate
        if ($original_tip_factura === 381) {
            // Lista câmpurilor care nu trebuie modificate
            $campe_protectate = ['nume', 'adresa', 'cod_fiscal', 'cod_inmatriculare', 'banca', 'iban', 'adresa_judet'];
            foreach ($campe_protectate as $camp) {
                if (isset($old_factura[$camp])) {
                    $new_factura[$camp] = $old_factura[$camp];
                }
            }
        }

        // Construim interogarea de inserare pentru 'facturi'
        $columns = array_keys($new_factura);
        $placeholders = ':' . implode(', :', $columns);
        $insert_factura_sql = "INSERT INTO facturi (" . implode(',', $columns) . ") VALUES ($placeholders)";
        $stmt_insert = $pdo->prepare($insert_factura_sql);
        $stmt_insert->execute($new_factura);

        // Obținem noul 'id_factura' al facturii duplicate
        $new_id_factura = $pdo->lastInsertId();

        // Preluăm înregistrările 'vanzari' asociate cu factura originală
        $stmt = $pdo->prepare("SELECT * FROM vanzari WHERE id_factura = :id_factura");
        $stmt->execute(['id_factura' => $id_factura]);
        $vanzari_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Duplicăm fiecare 'vanzari' pentru noua factură
        foreach ($vanzari_records as $vanzare) {
            unset($vanzare['id_vanz']); // Eliminăm 'id_vanz' pentru a permite DB să aloce unul nou
            $vanzare['id_factura'] = $new_id_factura;
            $vanzare['nr_factura'] = $new_nr_factura;

            // Dacă factura originală are tip_factura = 381, păstrăm anumite câmpuri nealterate în 'vanzari'
            if ($original_tip_factura === 381) {
                // Lista câmpurilor care nu trebuie modificate în 'vanzari'
                $campe_protectate_vanzari = ['den_p', 'um']; // Exemplu: Denumirea produsului și unitatea de măsură
                foreach ($campe_protectate_vanzari as $camp) {
                    if (isset($vanzare[$camp])) {
                        $vanzare[$camp] = $vanzare[$camp]; // Păstrăm valoarea originală
                    }
                }
            }

            // Construim interogarea de inserare pentru 'vanzari'
            $columns_vanzare = array_keys($vanzare);
            $placeholders_vanzare = ':' . implode(', :', $columns_vanzare);
            $insert_vanzare_sql = "INSERT INTO vanzari (" . implode(',', $columns_vanzare) . ") VALUES ($placeholders_vanzare)";
            $stmt_vanzare = $pdo->prepare($insert_vanzare_sql);
            $stmt_vanzare->execute($vanzare);
        }

        // Commit tranzacției
        $pdo->commit();

        // Redirecționăm utilizatorul către noua factură
        echo "<script>alert('Factura a fost duplicată cu succes.'); window.location.href='factura.php?id_factura=$new_id_factura';</script>";
        exit();

    } catch (Exception $e) {
        // Rollback în caz de eroare
        $pdo->rollBack();
        echo "<script>alert('A apărut o eroare: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
        exit();
    }

} else {
    // Dacă nu este specificat 'id_factura' în URL, redirecționăm utilizatorul înapoi
    echo "<script>alert('ID-ul facturii nu este specificat.'); window.history.back();</script>";
    exit();
}
?>
