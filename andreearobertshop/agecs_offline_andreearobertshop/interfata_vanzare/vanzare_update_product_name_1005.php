<?php
include('session.php');

if (!function_exists('isClientTaxaHotelieraVanzare')) {
    function isClientTaxaHotelieraVanzare($clientId): bool {
    return in_array((string)$clientId, ['1005', '8','1006'], true);
    }
}

if (!function_exists('esteProdusCazareTaxaHoteliera')) {
    function esteProdusCazareTaxaHoteliera(string $numeProdus): bool {
        $upper = strtoupper($numeProdus);
        return (strpos($upper, 'CAZARE') !== false) && (strpos($upper, 'TAXA HOTELIERA') === false);
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id_vanz'], $_POST['new_name'], $_POST['new_price'])) {
    http_response_code(400);
    echo 'Cerere invalida.';
    exit;
}

$id_vanz = (int)$_POST['id_vanz'];
$new_name = trim((string)$_POST['new_name']);
$new_price = (float)$_POST['new_price'];

if ($id_vanz <= 0 || $new_name === '' || !is_numeric($_POST['new_price'])) {
    http_response_code(400);
    echo 'Date invalide. Numele nu poate fi gol si pretul trebuie sa fie numeric.';
    exit;
}

try {
    $pdo->beginTransaction();

    $sqlSelect = "SELECT nr_bon, pachet, cantitate, cota_tva, nume_produs
                  FROM {$tabel_final_det_note}
                  WHERE id_vanz = :id_vanz
                  LIMIT 1";
    $stmtSelect = $pdo->prepare($sqlSelect);
    $stmtSelect->execute([':id_vanz' => $id_vanz]);
    $row = $stmtSelect->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new Exception('Produsul nu a fost gasit pe bon.');
    }

    $nrBon = (int)$row['nr_bon'];
    $pachet = (int)$row['pachet'];
    $cantitate = (float)$row['cantitate'];
    $cotaTva = (float)$row['cota_tva'];
    $oldName = (string)$row['nume_produs'];

    $newValoareCuTva = round($new_price * $cantitate, 2);
    $newTvaCol = round($newValoareCuTva * $cotaTva / (100 + $cotaTva), 2);
    $newValoareFaraTva = round($newValoareCuTva - $newTvaCol, 2);

    $sqlUpdate = "UPDATE {$tabel_final_det_note}
                  SET nume_produs = :new_name,
                      pret_vanzare = :new_price,
                      valoare_vanzare_cu_tva = :val_cu_tva,
                      tva_col = :tva_col,
                      valoare_vanzare = :val_fara_tva
                  WHERE id_vanz = :id_vanz";
    $stmtUpdate = $pdo->prepare($sqlUpdate);
    $stmtUpdate->execute([
        ':new_name' => $new_name,
        ':new_price' => $new_price,
        ':val_cu_tva' => $newValoareCuTva,
        ':tva_col' => $newTvaCol,
        ':val_fara_tva' => $newValoareFaraTva,
        ':id_vanz' => $id_vanz
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
                $newValoareFaraTva
            );
        } elseif ($wasCazare) {
            stergeTaxaHotelieraDinBon($pdo, $tabel_final_det_note, $nrBon, $pachet);
        }
    }

    $pdo->commit();
    echo 'Success';
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    error_log('Eroare la actualizare produs pe bon: ' . $e->getMessage());
    echo 'A aparut o eroare la salvarea datelor.';
}
?>

