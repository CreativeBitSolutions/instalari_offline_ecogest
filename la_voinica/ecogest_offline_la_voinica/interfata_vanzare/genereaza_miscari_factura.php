<?php
// genereaza_miscari_factura.php
include('session.php');
require_once('logica_generare_miscari.php'); 

if (!isset($_POST['id_factura'])) {
    header('Location: verifica_facturi.php?status=eroare&mesaj=' . urlencode('Eroare: ID Factură invalid.'));
    exit();
}
$id_factura = (int)$_POST['id_factura'];

try {
    generareMiscarePentruFactura($pdo, $id_factura);
    header('Location: verifica_facturi.php?status=succes&mesaj=' . urlencode("Mișcările pentru factura ID {$id_factura} au fost generate cu succes!"));
    exit();
} catch (Exception $e) {
    header('Location: verifica_facturi.php?status=eroare&mesaj=' . urlencode("Eroare la factura ID {$id_factura}: " . $e->getMessage()));
    exit();
}