<?php //ajax_get_garnituri.php
session_start();
require_once 'database_connection.php';

header('Content-Type: application/json; charset=utf-8');

$id_vanz = isset($_GET['id_vanz']) ? (int)$_GET['id_vanz'] : 0;
$cod_produs = 0;

if ($id_vanz > 0) {
    // Aflăm codul produsului de pe nota de plată
    $stmt_prod = $pdo->prepare("SELECT cod_p FROM det_note WHERE id_vanz = :id_vanz LIMIT 1");
    $stmt_prod->execute([':id_vanz' => $id_vanz]);
    $cod_produs = (int)$stmt_prod->fetchColumn();
}

// Selectăm observațiile: fie sunt globale, fie aparțin produsului curent
$sql = "
    SELECT DISTINCT o.id, o.text_observatie as nume
    FROM observatii_predefinite o
    LEFT JOIN atribuiri_observatii_produse a ON o.id = a.id_observatie
    WHERE o.activ = 1 
      AND (o.toate_produsele = 1 OR a.cod_produs = :cod_produs)
    ORDER BY o.ordine ASC, o.id ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':cod_produs' => $cod_produs]);
$rezultate = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($rezultate);