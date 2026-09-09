<?php
require_once 'database_connection.php';
if(session_status() != PHP_SESSION_ACTIVE) { session_start(); }

// Check if the form is submitted
if (isset($_POST['exec_duplicare'])) {
    // Get the form data
    $id_factura = $_POST['id_factura']; // Old invoice ID
    $nr_fact_duplicata = $_POST['nr_fact_duplicata']; // New invoice number
    $data_emitere = $_POST['data_emitere']; // Date of issue
    $data_scadenta = $_POST['data_scadenta']; // Due date

    // Validate the data
    if (empty($id_factura) || empty($nr_fact_duplicata) || empty($data_emitere) || empty($data_scadenta)) {
        // Error: Missing data
        echo "<script>alert('Toate câmpurile sunt obligatorii.'); window.history.back();</script>";
        exit;
    }

    try {
        // Begin a transaction
        $pdo->beginTransaction();

        // Fetch the old invoice based on 'id_factura'
        $stmt = $pdo->prepare("SELECT * FROM facturi WHERE id_factura = :id_factura");
        $stmt->execute(['id_factura' => $id_factura]);
        $old_factura = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$old_factura) {
            // Old invoice not found
            echo "<script>alert('Factura veche nu a fost găsită.'); window.history.back();</script>";
            exit;
        }

        // Prepare data for the new invoice
        $new_factura = $old_factura;
$new_factura['data_incarcare']='0000-00-00 00:00:00';
$new_factura['data_validare']='0000-00-00 00:00:00';
$new_factura['nrbon']=0;
$new_factura['nr_nota']=0;
$new_factura['factura_restaurant']=0;
$new_factura['id_deviz']=0;
$new_factura['data_stornare']=null;
$new_factura['id_factura_stornare']=null;
        unset($new_factura['id_factura']); // Remove 'id_factura' to let the database assign a new one
        $expected=(int)$pdo->query('SELECT COALESCE(MAX(nr_factura),0)+1 FROM facturi WHERE serie_factura='.$pdo->quote($old_factura['serie_factura']))->fetchColumn();
        if((int)$nr_fact_duplicata!==$expected)throw new RuntimeException('Numărul consecutiv disponibil este '.$expected.'.');
        $new_factura['tip_factura']=380;$new_factura['data_corectare']='0000-00-00 00:00:00';
        $new_factura['nr_factura'] = $nr_fact_duplicata;
        $new_factura['data_factura'] = $data_emitere;
        $new_factura['data_scadenta'] = $data_scadenta;

        // Build the INSERT statement for 'facturi'
        $columns = array_keys($new_factura);
        $placeholders = ':' . implode(', :', $columns);
        $insert_factura_sql = "INSERT INTO facturi (" . implode(',', $columns) . ") VALUES ($placeholders)";
        $stmt_insert = $pdo->prepare($insert_factura_sql);

        // Execute the insertion
        $stmt_insert->execute($new_factura);

        // Get the new 'id_factura' assigned by the database
        $new_id_factura = $pdo->lastInsertId();

        // Fetch the 'vanzari' records associated with the old invoice
        $stmt = $pdo->prepare("SELECT * FROM vanzari WHERE id_factura = :id_factura");
        $stmt->execute(['id_factura' => $old_factura['id_factura']]);
        $vanzari_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Duplicate each 'vanzari' record with the new 'id_factura' and 'nr_factura'
        foreach ($vanzari_records as $vanzare) {
            $oldLineId=(int)$vanzare['id_vanz'];
            unset($vanzare['id_vanz']); // Remove 'id_vanz' to let the database assign a new one
            $vanzare['id_factura'] = $new_id_factura;
            $vanzare['nr_factura'] = $nr_fact_duplicata;

            // Build the INSERT statement for 'vanzari'
            $columns_vanzare = array_keys($vanzare);
            $placeholders_vanzare = ':' . implode(', :', $columns_vanzare);
            $insert_vanzare_sql = "INSERT INTO vanzari (" . implode(',', $columns_vanzare) . ") VALUES ($placeholders_vanzare)";
            $stmt_vanzare = $pdo->prepare($insert_vanzare_sql);

            // Execute the insertion
            $stmt_vanzare->execute($vanzare);
            casa_copy_line_movements($pdo,$oldLineId,(int)$pdo->lastInsertId(),(int)$new_id_factura,(int)$new_factura['nr_factura'],(string)$new_factura['data_factura']);
        }

        // Commit the transaction
        $pdo->commit();
        $_SESSION['casa_reset_invoice_filters']=true;

        // Display an alert and redirect
        echo "<script>alert('Factura a fost duplicată cu succes.'); window.location.href='factura.php?id_factura=$new_id_factura';</script>";
        exit;

    } catch (Exception $e) {
        // Roll back the transaction if something failed
        $pdo->rollBack();
        echo "<script>alert('A apărut o eroare: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
        exit;
    }

} else {
    // The form was not submitted
    echo "<script>alert('Formularul nu a fost trimis corect.'); window.history.back();</script>";
    exit;
}
?>
