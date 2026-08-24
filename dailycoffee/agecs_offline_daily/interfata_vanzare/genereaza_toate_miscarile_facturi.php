<?php
// genereaza_toate_miscarile_facturi.php
include('session.php');
require_once('logica_generare_miscari.php');
set_time_limit(0);

$sql = "
    SELECT DISTINCT f.id_factura, f.nr_factura, f.serie_factura, f.data_factura
    FROM facturi f
    JOIN vanzari v ON f.id_factura = v.id_factura
    WHERE f.tip_factura NOT IN (751, 384)
      AND f.nr_factura <> 0
      AND NOT EXISTS (
          SELECT 1 
          FROM miscari m 
          WHERE m.id_vanz_fact = v.id_vanz 
            AND m.fel_doc = 'FAC'
      )
    ORDER BY f.data_factura, f.nr_factura;
";

$stmt = $pdo->query($sql);
$facturi_de_procesat = $stmt->fetchAll(PDO::FETCH_COLUMN);

$total = count($facturi_de_procesat);
$succes = 0;
$erori = 0;
$mesaje_erori = [];

foreach ($facturi_de_procesat as $id_factura) {
    try {
        generareMiscarePentruFactura($pdo, $id_factura);
        $succes++;
    } catch (Exception $e) {
        $erori++;
        $mesaje_erori[] = "Factura ID {$id_factura}: " . $e->getMessage();
    }
}

$mesaj_final = "Procesare facturi finalizată. Total: {$total}. Succes: {$succes}. Erori: {$erori}.";
if (!empty($mesaje_erori)) {
    $mesaj_final .= " Detalii erori: " . implode('; ', $mesaje_erori);
}

header('Location: verifica_facturi.php?status=' . ($erori > 0 ? 'eroare' : 'succes') . '&mesaj=' . urlencode($mesaj_final));
exit();