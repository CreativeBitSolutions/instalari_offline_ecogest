<?php
require_once __DIR__.'/database_connection.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "Metodă de cerere invalidă.";
    exit;
}

$id_vanz = isset($_POST['id_vanz']) ? (int)$_POST['id_vanz'] : 0;
$pret_input = isset($_POST['pret_vanzare']) ? str_replace(',', '.', (string)$_POST['pret_vanzare']) : '';
$pret_vanzare_nou = is_numeric($pret_input) ? (float)$pret_input : -1;

if ($id_vanz <= 0 || $pret_vanzare_nou < 0) {
    echo "Date invalide. Verifică prețul introdus.";
    exit;
}

try {
    $sqlSelect = "SELECT id_factura, cod_p, cantitate, cota_tva, discount
                  FROM vanzari
                  WHERE id_vanz = :id_vanz
                  LIMIT 1";
    $stmtSelect = $pdo->prepare($sqlSelect);
    $stmtSelect->execute([':id_vanz' => $id_vanz]);
    $row = $stmtSelect->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo "Linia de vânzare nu a fost găsită.";
        exit;
    }

    $id_factura = (int)$row['id_factura'];
    $cod_p = $row['cod_p'];
    $cantitate = (float)$row['cantitate'];
    $cota_tva = (float)$row['cota_tva'];

    $valoare_vanzare_cu_tva = round($pret_vanzare_nou * $cantitate, 2);
    $tva_col = round($valoare_vanzare_cu_tva * $cota_tva / (100 + $cota_tva), 2);
    $valoare_vanzare = round($valoare_vanzare_cu_tva - $tva_col, 2);

    $pdo->beginTransaction();

    $sqlUpdateVanzari = "UPDATE vanzari
                         SET pret_vanzare = :pret_vanzare,
                             valoare_vanzare = :valoare_vanzare,
                             tva_col = :tva_col,
                             valoare_vanzare_cu_tva = :valoare_vanzare_cu_tva
                         WHERE id_vanz = :id_vanz";
    $stmtUpdateVanzari = $pdo->prepare($sqlUpdateVanzari);
    $stmtUpdateVanzari->execute([
        ':pret_vanzare' => $pret_vanzare_nou,
        ':valoare_vanzare' => $valoare_vanzare,
        ':tva_col' => $tva_col,
        ':valoare_vanzare_cu_tva' => $valoare_vanzare_cu_tva,
        ':id_vanz' => $id_vanz
    ]);

    $sqlUpdateMiscari = "UPDATE miscari
                         SET pu = :pret_vanzare,
                             pret_vanzare = :pret_vanzare
                         WHERE id_vanz_fact = :id_vanz_fact
                           AND cod_p = :cod_p";
    $stmtUpdateMiscari = $pdo->prepare($sqlUpdateMiscari);
    $stmtUpdateMiscari->execute([
        ':pret_vanzare' => $pret_vanzare_nou,
        ':id_vanz_fact' => $id_vanz,
        ':cod_p' => $cod_p
    ]);

    $pdo->commit();

    header("Location: factura.php?id_factura=" . $id_factura);
    exit;
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    file_put_contents('error_log.txt', "Update Pret Factura Error: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
    echo "A apărut o eroare la actualizarea prețului. Verifică logul pentru detalii.";
}

