<?php
/**
 * Actualizeaza NUMELE+PRETUL pe linia din bon si PRETUL in nomenclator.
 * Dupa succes, invalideaza cache-ul de coduri de bare pentru client+locatie.
 */
include('session.php');

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.log');
error_reporting(E_ALL);

if (!function_exists('isClientTaxaHotelieraVanzare')) {
    function isClientTaxaHotelieraVanzare($clientId): bool {
    return in_array((string)$clientId, ['1005'], true);
    }
}

if (!function_exists('esteProdusCazareTaxaHoteliera')) {
    function esteProdusCazareTaxaHoteliera(string $numeProdus): bool {
        $upper = strtoupper($numeProdus);

        return (
            strpos($upper, 'CAZARE') !== false &&
            strpos($upper, 'TAXA HOTELIERA') === false &&
            strpos($upper, 'TAXA DE ORAS') === false &&
            strpos($upper, 'TAXA ORAS') === false
        );
    }
}

if (!function_exists('stergeTaxaHotelieraDinBon')) {
    function stergeTaxaHotelieraDinBon(PDO $pdo, string $tabelFinalDetNote, int $nrBon, int $pachet): void {
        $sqlDelete = "DELETE FROM {$tabelFinalDetNote}
                      WHERE nr_bon = :nr_bon AND pachet = :pachet AND nume_produs = 'TAXA HOTELIERA'";
        $stmtDelete = $pdo->prepare($sqlDelete);
        $stmtDelete->execute([
            ':nr_bon' => $nrBon,
            ':pachet' => $pachet
        ]);
    }
}

if (!function_exists('sincronizeazaTaxaHotelieraDinBon')) {
    function sincronizeazaTaxaHotelieraDinBon(
        PDO $pdo,
        string $tabelFinalNomenclator,
        string $tabelFinalDetNote,
        int $nrBon,
        int $pachet,
        float $cantitateCazare,
        float $valoareNetaCazare
    ): void {
        stergeTaxaHotelieraDinBon($pdo, $tabelFinalDetNote, $nrBon, $pachet);

        $valoareTaxa = round($valoareNetaCazare * 0.02, 2);
        if (abs($valoareTaxa) < 0.00001) {
            return;
        }

        $stmtProdTaxa = $pdo->prepare("SELECT cod_produs, um
                                       FROM {$tabelFinalNomenclator}
                                       WHERE UPPER(nume) = 'TAXA HOTELIERA'
                                       LIMIT 1");
        $stmtProdTaxa->execute();
        $prodTaxa = $stmtProdTaxa->fetch(PDO::FETCH_ASSOC);

        $codPTaxa = $prodTaxa['cod_produs'] ?? 'TAXA_AUTO';
        $umTaxa = $prodTaxa['um'] ?? 'BUC';
        $cantitateTaxa = ($cantitateCazare < 0) ? -1 : 1;
        $pretTaxa = abs($valoareTaxa);
        $valoareTaxaFinala = round($pretTaxa * $cantitateTaxa, 2);

        $insertSql = "INSERT INTO {$tabelFinalDetNote}
                        (nr_bon, cod_p, nume_produs, cantitate, cota_tva, tva_col, pret_vanzare,
                         valoare_vanzare, valoare_vanzare_cu_tva, pachet, data, ora)
                      VALUES
                        (:nr_bon, :cod_p, :nume, :cantitate, :cota_tva, :tva_col, :pret_vanzare,
                         :valoare_vanzare, :valoare_vanzare_cu_tva, :pachet, date('now','localtime'), time('now','localtime'))";
        $stmtInsert = $pdo->prepare($insertSql);
        $stmtInsert->execute([
            ':nr_bon' => $nrBon,
            ':cod_p' => $codPTaxa,
            ':nume' => 'TAXA HOTELIERA',
            ':cantitate' => $cantitateTaxa,
            ':cota_tva' => 0,
            ':tva_col' => 0,
            ':pret_vanzare' => $pretTaxa,
            ':valoare_vanzare' => $valoareTaxaFinala,
            ':valoare_vanzare_cu_tva' => $valoareTaxaFinala,
            ':pachet' => $pachet
        ]);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id_vanz'], $_POST['cod_p'], $_POST['new_name'], $_POST['new_price'])) {
    http_response_code(400);
    echo 'Cerere invalida. Toate campurile sunt obligatorii.';
    exit;
}

$id_vanz   = (int)$_POST['id_vanz'];
$cod_p     = $_POST['cod_p'];
$new_name  = trim((string)$_POST['new_name']);
$new_price = (float)$_POST['new_price'];

if ($id_vanz <= 0 || $cod_p === '' || $new_name === '' || !is_numeric($_POST['new_price'])) {
    http_response_code(400);
    echo 'Date invalide. Numele, pretul si codul produsului sunt obligatorii.';
    exit;
}

try {
    $pdo->beginTransaction();

    $sql_select = "SELECT nr_bon, pachet, cantitate, cota_tva, nume_produs
                   FROM {$tabel_final_det_note}
                   WHERE id_vanz = :id_vanz
                   LIMIT 1";
    $stmt_select = $pdo->prepare($sql_select);
    $stmt_select->execute([':id_vanz' => $id_vanz]);
    $row = $stmt_select->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new Exception('Produsul nu a fost gasit pe bon.');
    }

    $nrBon = (int)$row['nr_bon'];
    $pachet = (int)$row['pachet'];
    $cantitate = (float)$row['cantitate'];
    $cota_tva = (float)$row['cota_tva'];
    $oldName = (string)$row['nume_produs'];

    $new_valoare_vanzare_cu_tva = round($new_price * $cantitate, 2);
    $new_tva_col                = round($new_valoare_vanzare_cu_tva * $cota_tva / (100 + $cota_tva), 2);
    $new_valoare_vanzare        = round($new_valoare_vanzare_cu_tva - $new_tva_col, 2);

    $sql_update_bon = "UPDATE {$tabel_final_det_note}
                       SET nume_produs = :new_name,
                           pret_vanzare = :new_price,
                           valoare_vanzare_cu_tva = :val_cu_tva,
                           tva_col = :tva_col,
                           valoare_vanzare = :val_fara_tva
                       WHERE id_vanz = :id_vanz";
    $stmt_update_bon = $pdo->prepare($sql_update_bon);
    $stmt_update_bon->execute([
        ':new_name'     => $new_name,
        ':new_price'    => $new_price,
        ':val_cu_tva'   => $new_valoare_vanzare_cu_tva,
        ':tva_col'      => $new_tva_col,
        ':val_fara_tva' => $new_valoare_vanzare,
        ':id_vanz'      => $id_vanz
    ]);

    if (isClientTaxaHotelieraVanzare($_SESSION['client_id'] ?? null)) {
        $wasCazare = esteProdusCazareTaxaHoteliera($oldName);
        $isCazareNow = esteProdusCazareTaxaHoteliera($new_name);

        if ($isCazareNow) {
            sincronizeazaTaxaHotelieraDinBon(
                $pdo,
                $tabel_final_nomenclator,
                $tabel_final_det_note,
                $nrBon,
                $pachet,
                $cantitate,
                $new_valoare_vanzare
            );
        } elseif ($wasCazare) {
            stergeTaxaHotelieraDinBon($pdo, $tabel_final_det_note, $nrBon, $pachet);
        }
    }

    $sql_update_produs = "UPDATE {$tabel_final_nomenclator}
                          SET pret_cu_tva = :new_price
                          WHERE cod_produs = :cod_p";
    $stmt_update_produs = $pdo->prepare($sql_update_produs);
    $stmt_update_produs->execute([
        ':new_price' => $new_price,
        ':cod_p'     => $cod_p
    ]);

    $pdo->commit();

    $client_id   = $_SESSION['client_id']   ?? 'anon';
    $cod_locatie = $_SESSION['cod_locatie'] ?? '0';
    $barcodes_dir = __DIR__ . "/cache/c{$client_id}_l{$cod_locatie}/barcodes";

    if (is_dir($barcodes_dir)) {
        foreach (glob($barcodes_dir . '/*.json') as $f) {
            @unlink($f);
        }
    }

    require_once __DIR__ . '/cache_tools.php';

    $client_id   = $_SESSION['client_id']   ?? null;
    $cod_locatie = $_SESSION['cod_locatie'] ?? null;

    if ($client_id && $cod_locatie) {
        invalidate_prodlists_for_client_location($client_id, $cod_locatie);
        invalidate_barcodes_for_client_location($client_id, $cod_locatie);
    }

    echo 'Success';
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    error_log('Eroare la actualizare permanenta produs: ' . $e->getMessage());
    echo 'A aparut o eroare la salvarea datelor: ' . $e->getMessage();
}

