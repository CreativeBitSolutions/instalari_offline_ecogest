<!-- Modal discount pe produs -->
<div class="modal fade" id="Discount" tabindex="-1" role="dialog" aria-labelledby="DiscountLabel" aria-hidden="true">
  <div class="modal-dialog" role="document" style="max-width:520px;">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="DiscountLabel">Aplică discount asupra prețului unitar</h4>
        <button type="button" class="close" data-dismiss="modal" aria-label="Închide"><span aria-hidden="true">&times;</span></button>
      </div>

      <div class="modal-body">
        <form method="POST">
          <input type="hidden" name="prod_discount">
          <input type="hidden" id="cota_calc_tva" name="cota_calc_tva">
          <input type="hidden" name="idvanzare">

          <div class="form-group">
            <label>Procent discount (%)</label>
            <div class="input-group">
              <input class="form-control" max="100" step="0.01" min="0" value="0" type="number" id="val_procent" name="val_procent">
            </div>
            <button class="btn btn-primary btn-block mt-2" type="submit" name="apl_disc_proc" value="1">Aplică discount</button>
          </div>

          <div class="form-group">
            <label>Sau valoare discount (RON)</label>
            <div class="input-group">
              <input type="number" step="0.001" class="form-control" id="val_fix" name="valoare_fixa">
            </div>
            <button class="btn btn-primary btn-block mt-2" type="submit" name="apl_disc_fix" value="1">Aplică discount</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php
// === LOGICĂ DISCOUNT PE RÂND + LOGARE ===
if (isset($_POST['apl_disc_fix']) || isset($_POST['apl_disc_proc'])) {
  $id_vz = (int)($_POST['idvanzare'] ?? 0);
  if ($id_vz <= 0) { echo "<script>location.href='vanzare_restaurant.php'</script>"; exit; }

  $stmt = $pdo->prepare("SELECT pret_vanzare, cantitate, cota_tva FROM $tabel_final_det_note WHERE id_vanz = :id");
  $stmt->execute([':id'=>$id_vz]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row) { echo "<script>alert('Linie inexistentă');location.href='vanzare_restaurant.php'</script>"; exit; }

  $pi = (float)$row['pret_vanzare']; $q=(float)$row['cantitate']; $t=(float)$row['cota_tva'];
  $id_operator = (int)($_SESSION['admin_id'] ?? 0);

  if (isset($_POST['apl_disc_fix'])) {
    $disc_unit = max(0, (float)($_POST['valoare_fixa'] ?? 0));
  } else {
    $proc = max(0, (float)($_POST['val_procent'] ?? 0));
    $disc_unit = round($pi * $proc / 100, 4);
  }

  $pnew = round($pi - $disc_unit, 4); if ($pnew < 0) $pnew = 0;
  $d_total = round($disc_unit * $q, 2);
  $vcu = round($pnew * $q, 2);
  $tva_col = round($vcu * $t / (100 + $t), 2);
  $vf = round($vcu - $tva_col, 2);

  try{
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE $tabel_final_det_note
                   SET pret_vanzare=:p, discount=:d, valoare_vanzare_cu_tva=:vcu, tva_col=:tva, valoare_vanzare=:vf
                   WHERE id_vanz=:id")
        ->execute([':p'=>$pnew, ':d'=>$d_total, ':vcu'=>$vcu, ':tva'=>$tva_col, ':vf'=>$vf, ':id'=>$id_vz]);

    $tip = isset($_POST['apl_disc_fix']) ? 'valoric_fix' : 'procentual';
    $pdo->prepare("INSERT INTO discounturi_acordate
                    (id_vanz,id_operator,tip_discount,valoare_procent,valoare_discount_ron,pret_unitar_initial,pret_unitar_final,id_operatiune_globala)
                   VALUES (:id,:op,:tip,:proc,:ron,:pi,:pf,NULL)")
        ->execute([
          ':id'=>$id_vz, ':op'=>$id_operator, ':tip'=>$tip,
          ':proc'=> isset($_POST['apl_disc_proc']) ? (float)($_POST['val_procent'] ?? 0) : null,
          ':ron'=>$d_total, ':pi'=>$pi, ':pf'=>$pnew
        ]);

    $pdo->commit();
  } catch(PDOException $e){
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "<script>alert('Eroare: ".htmlspecialchars($e->getMessage())."');</script>";
  }
  echo "<script>location.href='vanzare_restaurant.php'</script>";
  exit;
}
?>
