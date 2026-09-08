<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__.'/database_connection.php';

function redirectBack($idFactura, $message) {
    $url = 'preview_factura_stornare.php?id_factura=' . urlencode($idFactura) . '&error=' . urlencode($message);
    header('Location: ' . $url);
    exit;
}

function getNextNrFactura(PDO $pdo, string $serie_factura): int {
    $sql = "SELECT MAX(nr_factura) AS max_nr FROM facturi WHERE serie_factura = :serie_factura";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':serie_factura' => $serie_factura]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result && $result['max_nr'] !== null) {
        return (int)$result['max_nr'] + 1;
    }

    $sqlStart = "SELECT nr_inceput FROM casa_online_series WHERE serie = :serie_factura LIMIT 1";
    $stmtStart = $pdo->prepare($sqlStart);
    $stmtStart->execute([':serie_factura' => $serie_factura]);
    $rowStart = $stmtStart->fetch(PDO::FETCH_ASSOC);

    if (!$rowStart) {
        throw new Exception("Seria facturii nu există în serii_documente.");
    }

    return (int)$rowStart['nr_inceput'];
}

function insertDynamic(PDO $pdo, string $table, array $data): void {
    $columns = array_keys($data);

    $escapedColumns = array_map(function ($col) {
        return "`" . str_replace("`", "``", $col) . "`";
    }, $columns);

    $placeholders = array_map(function ($col) {
        return ":" . $col;
    }, $columns);

    $sql = "INSERT INTO `$table` (" . implode(', ', $escapedColumns) . ")
            VALUES (" . implode(', ', $placeholders) . ")";

    $stmt = $pdo->prepare($sql);

    foreach ($data as $column => $value) {
        $stmt->bindValue(':' . $column, $value);
    }

    $stmt->execute();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: facturi.php');
    exit;
}

$id_factura = isset($_POST['id_factura']) ? (int)$_POST['id_factura'] : 0;

if ($id_factura <= 0) {
    header('Location: facturi.php');
    exit;
}

try {
    $pdo->beginTransaction();

    // 1) Factura sursă
    $stmtFactura = $pdo->prepare("
        SELECT * 
        FROM facturi 
        WHERE id_factura = :id_factura 
        LIMIT 1
    ");
    $stmtFactura->execute([':id_factura' => $id_factura]);
    $facturaOriginala = $stmtFactura->fetch(PDO::FETCH_ASSOC);

    if (!$facturaOriginala) {
        throw new Exception("Factura sursă nu a fost găsită.");
    }

    $serieFactura = (string)$facturaOriginala['serie_factura'];
    if(trim($serieFactura)==='')throw new RuntimeException('Factura sursă nu are o serie validă.');
    $nrNouFactura = getNextNrFactura($pdo, $serieFactura);
    if(casa_one($pdo,'SELECT id_factura FROM facturi WHERE serie_factura=? AND nr_factura=?',[$serieFactura,$nrNouFactura]))throw new RuntimeException('Numărul facturii nu mai este disponibil. Reîncercați stornarea.');

    // 2) Linii vanzari sursă
    $stmtVanzari = $pdo->prepare("
        SELECT * 
        FROM vanzari 
        WHERE id_factura = :id_factura 
        ORDER BY id_vanz ASC
    ");
    $stmtVanzari->execute([':id_factura' => $id_factura]);
    $vanzariOriginale = $stmtVanzari->fetchAll(PDO::FETCH_ASSOC);

    if (empty($vanzariOriginale)) {
        throw new Exception("Factura nu are linii în vanzari.");
    }

    // 3) Creez factura nouă
    $facturaNoua = $facturaOriginala;

    unset($facturaNoua['id_factura']);

    // nu copiem validarea / încărcarea
    unset($facturaNoua['data_validare']);
    unset($facturaNoua['data_incarcare']);

    $facturaNoua['nr_factura'] = $nrNouFactura;
    $facturaNoua['serie_factura'] = $serieFactura;

    // tipul facturii rămâne același ca factura stornată
    $facturaNoua['tip_factura'] = 380;
    foreach(['nrbon','nr_nota','factura_restaurant','id_deviz'] as $field)$facturaNoua[$field]=0;
    $facturaNoua['data_factura']=date('Y-m-d');$facturaNoua['data_scadenta']=date('Y-m-d');
    $facturaNoua['data_corectare']='0000-00-00 00:00:00';
    $facturaNoua['data_stornare']=null;$facturaNoua['id_factura_stornare']=null;

    insertDynamic($pdo, 'facturi', $facturaNoua);
    $idFacturaNoua = (int)$pdo->lastInsertId();

    // 4) Copiez vanzari cu semn inversat
    $mapVanzari = []; // [id_vanz_vechi => id_vanz_nou]

    foreach ($vanzariOriginale as $vanzare) {
        $idVanzVechi = (int)$vanzare['id_vanz'];

        $vanzareNoua = $vanzare;
        unset($vanzareNoua['id_vanz']);

        $vanzareNoua['id_factura'] = $idFacturaNoua;$vanzareNoua['id_deviz']=0;$vanzareNoua['nr_nota']=0;

        if (array_key_exists('nr_factura', $vanzareNoua)) {
            $vanzareNoua['nr_factura'] = $nrNouFactura;
        }

        if (array_key_exists('serie_factura', $vanzareNoua)) {
            $vanzareNoua['serie_factura'] = $serieFactura;
        }

        if (isset($vanzareNoua['cantitate']) && is_numeric($vanzareNoua['cantitate'])) {
            $vanzareNoua['cantitate'] = (float)$vanzareNoua['cantitate'] * -1;
        }

        if (isset($vanzareNoua['discount']) && is_numeric($vanzareNoua['discount'])) {
            $vanzareNoua['discount'] = (float)$vanzareNoua['discount'] * -1;
        }

        if (isset($vanzareNoua['tva_col']) && is_numeric($vanzareNoua['tva_col'])) {
            $vanzareNoua['tva_col'] = (float)$vanzareNoua['tva_col'] * -1;
        }

        if (isset($vanzareNoua['valoare_vanzare']) && is_numeric($vanzareNoua['valoare_vanzare'])) {
            $vanzareNoua['valoare_vanzare'] = (float)$vanzareNoua['valoare_vanzare'] * -1;
        }

        if (isset($vanzareNoua['valoare_vanzare_cu_tva']) && is_numeric($vanzareNoua['valoare_vanzare_cu_tva'])) {
            $vanzareNoua['valoare_vanzare_cu_tva'] = (float)$vanzareNoua['valoare_vanzare_cu_tva'] * -1;
        }

        // prețul unitar rămâne identic
        insertDynamic($pdo, 'vanzari', $vanzareNoua);
        $idVanzNou = (int)$pdo->lastInsertId();

        $mapVanzari[$idVanzVechi] = $idVanzNou;
    }

    // 5) Copiez miscari aferente
    $idsVanzariVechi = array_keys($mapVanzari);

    if (!empty($idsVanzariVechi)) {
        $placeholders = implode(',', array_fill(0, count($idsVanzariVechi), '?'));

        $sqlMiscari = "SELECT * FROM miscari WHERE id_vanz_fact IN ($placeholders) ORDER BY id ASC";
        $stmtMiscari = $pdo->prepare($sqlMiscari);
        $stmtMiscari->execute($idsVanzariVechi);
        $miscariOriginale = $stmtMiscari->fetchAll(PDO::FETCH_ASSOC);

        foreach ($miscariOriginale as $miscare) {
            $miscareNoua = $miscare;

            unset($miscareNoua['id']);

            $idVanzFactVechi = (int)$miscare['id_vanz_fact'];
            $miscareNoua['id_vanz_fact'] = $mapVanzari[$idVanzFactVechi] ?? null;

            if (isset($miscareNoua['cantitate_misc']) && is_numeric($miscareNoua['cantitate_misc'])) {
                $miscareNoua['cantitate_misc'] = (float)$miscareNoua['cantitate_misc'] * -1;
            }

            if (array_key_exists('nr_nota', $miscareNoua)) {
                $miscareNoua['nr_nota'] = $nrNouFactura;
            }

            // pentru liniile FAC, nr_doc devine noul număr de factură
            if (
                array_key_exists('fel_doc', $miscareNoua) &&
                array_key_exists('nr_doc', $miscareNoua) &&
                strtoupper((string)$miscareNoua['fel_doc']) === 'FAC'
            ) {
                $miscareNoua['nr_doc'] = $nrNouFactura;
            }

            insertDynamic($pdo, 'miscari', $miscareNoua);
        }
    }

    $pdo->prepare('INSERT INTO stornari(id_factura,id_factura_stornata,data_stornare,motiv_stornare,user_created,created_at,updated_at) VALUES(?,?,?,?,?,?,?)')->execute([$idFacturaNoua,$id_factura,date('Y-m-d H:i:s'),trim((string)($_POST['motiv_stornare']??'Stornare integrală')),(int)$_SESSION['admin_id'],date('Y-m-d H:i:s'),date('Y-m-d H:i:s')]);
    $pdo->commit();

    header('Location: preview_factura_stornare.php?id_factura=' . urlencode($idFacturaNoua) . '&created=1&source_id=' . urlencode($id_factura));
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    redirectBack($id_factura, $e->getMessage());
}
?>
