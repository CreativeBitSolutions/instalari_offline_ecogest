<?php
// vanzare_adaug_meniu_pe_nota.php
include('session.php');
if (!in_array((int)($_SESSION['client_id'] ?? 0), [8, 17])) {
    http_response_code(403);
    echo json_encode(['error' => 'Funcționalitate indisponibilă pentru acest client.']);
    exit;
}

header('Content-Type: application/json');
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.log');

try {
  $nr_bon    = (int)($_POST['nr_bon'] ?? 0);
  $cod_masa  = $_POST['cod_masa'] ?? null;
  $cod_meniu = (int)($_POST['cod_meniu'] ?? 0);
  $qty_m     = max(1, (int)($_POST['qty_meniuri'] ?? 1));
  $cod_locatie = (int)($_SESSION['cod_locatie'] ?? 2);
  if (!$nr_bon || !$cod_masa || !$cod_meniu) {
    throw new Exception('Parametri lipsă.');
  }

  // citim conținut + detalii din nomenclator pentru TVA, UM, SGR, gestiune
  $sql = "
    SELECT mc.cod_produs, mc.cantitate, mc.pret_vanzare AS pret_unitar_meniu,
           n.nume, n.cota_tva, n.um,
           n.sgr, n.sgr_pet, n.sgr_alumin, n.sgr_sticla,
           g.denumire_gestiune
    FROM meniuri_continut mc
    JOIN produse_servicii n ON n.cod_produs = mc.cod_produs
    LEFT JOIN gestiuni g ON g.id_gestiune = n.id_gestiune
    WHERE mc.cod_meniu = :cod_meniu
    ORDER BY mc.id_continut_meniu ASC
  ";
  $st = $pdo->prepare($sql);
  $st->execute([':cod_meniu' => $cod_meniu]);
  $items = $st->fetchAll(PDO::FETCH_ASSOC);

  if (!$items) {
    echo json_encode(['error' => 'Meniu gol sau inexistent.']); exit;
  }

  // funcție locală SGR (identică semantic cu cea din vanzare_adaug_prod_pe_nota.php)
  function adaugaGarantieSGR($cod_garantie, $cant, $nr_bon, $pachet, $cod_locatie, $pdo, $tabel_final_nomenclator, $tabel_final_det_note, array &$resp) {
    $stmt = $pdo->prepare("SELECT nume, pret_cu_tva, cota_tva, um FROM {$tabel_final_nomenclator} WHERE cod_produs = :c LIMIT 1");
    $stmt->execute([':c'=>$cod_garantie]);
    if ($g = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $pret = (float)$g['pret_cu_tva']; $cota = (float)$g['cota_tva'];
      $val_cu = round($pret * $cant, 2);
      $tva    = round($val_cu * $cota / (100 + $cota), 2);
      $val    = $val_cu - $tva;

      $ins = $pdo->prepare("
        INSERT INTO {$tabel_final_det_note}
          (nr_bon,cod_p,nume_produs,cantitate,cota_tva,tva_col,pret_vanzare,valoare_vanzare,valoare_vanzare_cu_tva,pachet,data,ora,cod_locatie)
        VALUES (:nr,:cod,:nume,:cant,:cota,:tva,:pret,:val,:val_cu,:pachet,date('now','localtime'),time('now','localtime'),:cod_locatie)
      ");
      $ins->execute([
        ':nr'=>$nr_bon, ':cod'=>$cod_garantie, ':nume'=>$g['nume'], ':cant'=>$cant,
        ':cota'=>$cota, ':tva'=>$tva, ':pret'=>$pret, ':val'=>$val, ':val_cu'=>$val_cu, ':pachet'=>$pachet,
        ':cod_locatie'=>$cod_locatie
      ]);
      $resp['added'][] = [
        'id_vanz' => (int)$pdo->lastInsertId(),
        'cod_p' => $cod_garantie,
        'nume'  => $g['nume'],
        'cantitate' => $cant,
        'pret_vanzare' => $pret,
        'valoare_vanzare_cu_tva' => $val_cu,
        'um' => $g['um'],
        'cota_tva' => $cota
      ];
    }
  }

  // setări tabele din mediul tău
  $tabel_final_nomenclator = $tabel_final_nomenclator ?? 'produse_servicii';
  $tabel_final_det_note    = $tabel_final_det_note    ?? 'det_note';

  $pachet = ($cod_masa == 9999) ? 1 : 0;
  $resp = ['added'=>[], 'updated'=>[]];

  $pdo->beginTransaction();

  foreach ($items as $it) {
    $cod_p      = (int)$it['cod_produs'];
    $nume       = $it['nume'];
    $um         = $it['um'];
    $cota_tva   = (float)$it['cota_tva'];
    $gestiune   = $it['denumire_gestiune'];

    $sgr = (int)($it['sgr'] ?? 0);
    $sgr_pet = (int)($it['sgr_pet'] ?? 0);
    $sgr_al  = (int)($it['sgr_alumin'] ?? 0);
    $sgr_st  = (int)($it['sgr_sticla'] ?? 0);

    // cantitatea finală per linie = cantitatea din meniu * numărul de meniuri
    $cant = round((float)$it['cantitate'] * $qty_m, 5);
    if ($cant <= 0) continue;

    $pret = (float)$it['pret_unitar_meniu'];

    // calcule 1:1 cu vanzare_adaug_prod_pe_nota.php
    $val_cu = round($pret * $cant, 2);
    $tva    = round($val_cu * $cota_tva / (100 + $cota_tva), 2);
    $val    = $val_cu - $tva;

    // dacă există linie identică (același cod + preț + pachet), o agregăm
    $ck = $pdo->prepare("
      SELECT id_vanz, cantitate
      FROM {$tabel_final_det_note}
      WHERE nr_bon=:nr AND cod_p=:cod AND pret_vanzare=:pret AND pachet=:p LIMIT 1
    ");
    $ck->execute([':nr'=>$nr_bon, ':cod'=>$cod_p, ':pret'=>$pret, ':p'=>$pachet]);
    if ($ex = $ck->fetch(PDO::FETCH_ASSOC)) {
      $upd = $pdo->prepare("
        UPDATE {$tabel_final_det_note}
           SET cantitate = cantitate + :cant,
               tva_col   = tva_col   + :tva,
               valoare_vanzare        = valoare_vanzare        + :val,
               valoare_vanzare_cu_tva = valoare_vanzare_cu_tva + :val_cu,
               t_list='0',
               cod_locatie=:cod_locatie
         WHERE id_vanz=:id
      ");
      $upd->execute([':cant'=>$cant, ':tva'=>$tva, ':val'=>$val, ':val_cu'=>$val_cu, ':cod_locatie'=>$cod_locatie, ':id'=>$ex['id_vanz']]);

      $cant_total = (float)$ex['cantitate'] + $cant;
      $resp['updated'][] = [
        'id_vanz' => (int)$ex['id_vanz'],
        'cantitate' => round($cant_total, 2),
        'valoare_vanzare_cu_tva' => round($cant_total * $pret, 2)
      ];
    } else {
      $ins = $pdo->prepare("
        INSERT INTO {$tabel_final_det_note}
          (nr_bon,cod_p,nume_produs,cantitate,cota_tva,tva_col,pret_vanzare,valoare_vanzare,valoare_vanzare_cu_tva,pachet,data,ora,cod_locatie)
        VALUES
          (:nr,:cod,:nume,:cant,:cota,:tva,:pret,:val,:val_cu,:pachet,date('now','localtime'),time('now','localtime'),:cod_locatie)
      ");
      $ins->execute([
        ':nr'=>$nr_bon, ':cod'=>$cod_p, ':nume'=>$nume, ':cant'=>$cant,
        ':cota'=>$cota_tva, ':tva'=>$tva, ':pret'=>$pret, ':val'=>$val, ':val_cu'=>$val_cu, ':pachet'=>$pachet,
        ':cod_locatie'=>$cod_locatie
      ]);
      $resp['added'][] = [
        'id_vanz' => (int)$pdo->lastInsertId(),
        'cod_p' => $cod_p,
        'nume'  => $nume,
        'cantitate' => $cant,
        'pret_vanzare' => $pret,
        'valoare_vanzare_cu_tva' => $val_cu,
        'um' => $um,
        'cota_tva' => $cota_tva
      ];
    }

    // SGR – identic cu produsul simplu (nu adăugăm pentru PRODUSE FINITE)
    if ($gestiune !== 'PRODUSE FINITE') {
      if ($sgr      === 1) adaugaGarantieSGR(-2, $cant, $nr_bon, $pachet, $cod_locatie, $pdo, $tabel_final_nomenclator, $tabel_final_det_note, $resp);
      if ($sgr_pet  === 1) adaugaGarantieSGR(-3, $cant, $nr_bon, $pachet, $cod_locatie, $pdo, $tabel_final_nomenclator, $tabel_final_det_note, $resp);
      if ($sgr_al   === 1) adaugaGarantieSGR(-4, $cant, $nr_bon, $pachet, $cod_locatie, $pdo, $tabel_final_nomenclator, $tabel_final_det_note, $resp);
      if ($sgr_st   === 1) adaugaGarantieSGR(-5, $cant, $nr_bon, $pachet, $cod_locatie, $pdo, $tabel_final_nomenclator, $tabel_final_det_note, $resp);
    }
  }

  $pdo->commit();
  echo json_encode($resp);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['error'=>$e->getMessage()]);
}
