<?php
/**
 * Actualizează NUMELE+PREȚUL pe linia din bon și PREȚUL în nomenclator.
 * După succes, invalidează cache-ul de coduri de bare pentru client+locație.
 */
include('session.php');

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.log');
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id_vanz'], $_POST['cod_p'], $_POST['new_name'], $_POST['new_price'])) {
    http_response_code(400);
    echo "Cerere invalidă. Toate câmpurile sunt obligatorii.";
    exit;
}

$id_vanz   = (int)$_POST['id_vanz'];
$cod_p     = $_POST['cod_p'];
$new_name  = trim((string)$_POST['new_name']);
$new_price = (float)$_POST['new_price'];

if ($id_vanz <= 0 || $cod_p === '' || $new_name === '' || !is_numeric($_POST['new_price'])) {
    http_response_code(400);
    echo "Date invalide. Numele, prețul și codul produsului sunt obligatorii.";
    exit;
}

try {
    $pdo->beginTransaction();

    // 1) Select pentru calcul TVA/valori
    $sql_select = "SELECT cantitate, cota_tva
                   FROM {$tabel_final_det_note}
                   WHERE id_vanz = :id_vanz
                   LIMIT 1";
    $stmt_select = $pdo->prepare($sql_select);
    $stmt_select->execute(['id_vanz' => $id_vanz]);
    $row = $stmt_select->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new Exception("Produsul nu a fost găsit pe bon.");
    }

    $cantitate = (float)$row['cantitate'];
    $cota_tva  = (float)$row['cota_tva'];

    $new_valoare_vanzare_cu_tva = round($new_price * $cantitate, 2);
    $new_tva_col                = round($new_valoare_vanzare_cu_tva * $cota_tva / (100 + $cota_tva), 2);
    $new_valoare_vanzare        = round($new_valoare_vanzare_cu_tva - $new_tva_col, 2);

    // 2) Update linie bon
    $sql_update_bon = "UPDATE {$tabel_final_det_note}
                       SET nume_produs = :new_name,
                           pret_vanzare = :new_price,
                           valoare_vanzare_cu_tva = :val_cu_tva,
                           tva_col = :tva_col,
                           valoare_vanzare = :val_fara_tva
                       WHERE id_vanz = :id_vanz";
    $stmt_update_bon = $pdo->prepare($sql_update_bon);
    $stmt_update_bon->execute([
        'new_name'     => $new_name,
        'new_price'    => $new_price,
        'val_cu_tva'   => $new_valoare_vanzare_cu_tva,
        'tva_col'      => $new_tva_col,
        'val_fara_tva' => $new_valoare_vanzare,
        'id_vanz'      => $id_vanz
    ]);

    // 3) Update preț în nomenclator
    $sql_update_produs = "UPDATE {$tabel_final_nomenclator}
                          SET pret_cu_tva = :new_price
                          WHERE cod_produs = :cod_p";
    $stmt_update_produs = $pdo->prepare($sql_update_produs);
    $stmt_update_produs->execute([
        'new_price' => $new_price,
        'cod_p'     => $cod_p
    ]);

    $pdo->commit();

    // 4) Invalidare cache coduri de bare pentru client+locație (simplu: șterge tot folderul barcodes)
    $client_id   = $_SESSION['client_id']   ?? 'anon';
    $cod_locatie = $_SESSION['cod_locatie'] ?? '0';
    $barcodes_dir = __DIR__ . "/cache/c{$client_id}_l{$cod_locatie}/barcodes";

    if (is_dir($barcodes_dir)) {
        foreach (glob($barcodes_dir . '/*.json') as $f) {
            @unlink($f);
        }
        // @rmdir($barcodes_dir); // optional: golește și directorul
    }
require_once __DIR__ . '/cache_tools.php';

$client_id   = $_SESSION['client_id']   ?? null;
$cod_locatie = $_SESSION['cod_locatie'] ?? null;

if ($client_id && $cod_locatie) {
    invalidate_prodlists_for_client_location($client_id, $cod_locatie);
    // dacă ai/vei avea cache de barcoduri pe disc:
    invalidate_barcodes_for_client_location($client_id, $cod_locatie);
}

    echo "Success";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    error_log("Eroare la actualizare permanentă produs: " . $e->getMessage());
    echo "A apărut o eroare la salvarea datelor: " . $e->getMessage();
}
