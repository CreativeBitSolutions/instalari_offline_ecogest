<?php
include('../database_connection.php');

if (isset($_POST['exec_incasare'])) {

    // Preluăm valorile din formular
    $suma_incasata      = $_POST['suma_incasata'];
    $nr_doc_incasare    = $_POST['nr_doc_incasare'];
    $tip_doc_incasare   = $_POST['tip_doc_incasare'];
    $data_incasare      = $_POST['data_incasare'];
    $nr_factura_incasata = $_POST['nr_factura_incasata']; 
    // Notă: din formular, câmpul "nr_factura_incasata" este folosit pentru a transmite ID-ul facturii.
    
    // Pentru coloana `serie_doc_incasare` nu avem un câmp din formular, așa că o vom seta ca șir gol.
    $serie_doc_incasare = '';

    // Construim interogarea cu numele de coloane corecte:
    // Coloanele sunt: id_factura, suma, tip_doc, data_incasarii, nr_doc_incasare, serie_doc_incasare
    $incasare_fact_sql = "INSERT INTO incasari (id_factura, suma, tip_doc, data_incasarii, nr_doc_incasare, serie_doc_incasare)
                           VALUES (:id_factura, :suma, :tip_doc, :data_incasarii, :nr_doc_incasare, :serie_doc_incasare)";
    
    $incasare_fact_stmt = $pdo->prepare($incasare_fact_sql);
    $incasare_fact_stmt->bindParam(':id_factura', $nr_factura_incasata, PDO::PARAM_INT);
    $incasare_fact_stmt->bindParam(':suma', $suma_incasata);
    $incasare_fact_stmt->bindParam(':tip_doc', $tip_doc_incasare);
    $incasare_fact_stmt->bindParam(':data_incasarii', $data_incasare);
    $incasare_fact_stmt->bindParam(':nr_doc_incasare', $nr_doc_incasare);
    $incasare_fact_stmt->bindParam(':serie_doc_incasare', $serie_doc_incasare);
    
    $incasare_fact_stmt->execute();
    
    echo '<script language="javascript">';
    echo 'alert("Factura ' . $nr_factura_incasata . ' a fost încasată")';
    echo '</script>';
    printf("<script>location.href='../facturi.php'</script>");
}
?>