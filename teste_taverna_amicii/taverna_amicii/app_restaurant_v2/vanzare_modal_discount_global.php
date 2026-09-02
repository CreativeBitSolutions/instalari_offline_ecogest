<!-- Modal Discount Global -->
<div class="modal fade" id="DiscountGlobal" tabindex="-1" role="dialog" aria-labelledby="DiscountGlobalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document" style="max-width:520px;">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="DiscountGlobalLabel">Aplică Discount Global</h4>
        <button type="button" class="close" data-dismiss="modal" aria-label="Închide"><span aria-hidden="true">&times;</span></button>
      </div>

      <div class="modal-body">
        <form method="POST">
          <input type="hidden" name="global_discount" value="1">

          <div class="form-group">
            <label>Discount procentual (%)</label>
            <input type="number" step="0.01" min="0" max="100" class="form-control" name="val_procent_global" value="0">
            <button class="btn btn-primary btn-block mt-2" type="submit" name="apl_disc_proc_global" value="1">Aplică discount procentual</button>
          </div>

          <div class="form-group">
            <label>Discount fix (RON)</label>
            <input type="number" step="0.001" min="0" class="form-control" name="valoare_fixa_global">
            <button class="btn btn-primary btn-block mt-2" type="submit" name="apl_disc_fix_global" value="1">Aplică discount fix</button>
          </div>

          <div class="form-group">
            <label>Resetare la prețuri inițiale</label>
            <button class="btn btn-secondary btn-block mt-2" type="submit" name="resetare_preturi_initiale" value="1">Resetare la prețuri inițiale</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php
// === DISCOUNT GLOBAL — LOG + UPDATE ===

// FIX VALORIC GLOBAL
if (isset($_POST['apl_disc_fix_global'])) {
    $nr_bon     = (int)($_SESSION['nr_bon'] ?? 0);
    $id_operator= (int)($_SESSION['admin_id'] ?? 0);
    $global_discount_total_input = round((float)($_POST['valoare_fixa_global'] ?? 0), 2);

    if ($nr_bon <= 0 || $global_discount_total_input <= 0) { echo "<script>location.href='vanzare_restaurant.php'</script>"; exit; }

    try {
        $pdo->beginTransaction();
        $sql_fetch_all = "SELECT id_vanz, pret_vanzare, cantitate, cota_tva, nume_produs
                          FROM $tabel_final_det_note
                          WHERE nr_bon = :nr_bon
                          ORDER BY id_vanz ASC";
        $stmt_fetch_all = $pdo->prepare($sql_fetch_all);
        $stmt_fetch_all->execute([':nr_bon'=>$nr_bon]);
        $all_products = $stmt_fetch_all->fetchAll(PDO::FETCH_ASSOC);
        if (!$all_products) { $pdo->rollBack(); echo "<script>location.href='vanzare_restaurant.php'</script>"; exit; }

        $non_sgr = [];
        $capacity = 0;
        foreach ($all_products as $p) {
          if (strpos($p['nume_produs'],'SGR') !== false) continue;
          $non_sgr[] = $p;
          $price = (float)$p['pret_vanzare']; $qty=(float)$p['cantitate'];
          if ($price > 1 && $qty > 0) $capacity += ($price - 1) * $qty;
        }
        $capacity = round($capacity,2);

        if (empty($non_sgr) || $global_discount_total_input > $capacity) {
          $pdo->rollBack();
          echo "<script>alert('Discountul fix depășește capacitatea sau nu există produse eligibile.');location.href='vanzare_restaurant.php'</script>";
          exit;
        }

        $stmt_update = $pdo->prepare(
          "UPDATE $tabel_final_det_note
             SET pret_vanzare=:new_pret, discount=:discount, valoare_vanzare_cu_tva=:v_cu_tva, tva_col=:tva, valoare_vanzare=:v_fara
           WHERE id_vanz=:id"
        );
        $stmt_log = $pdo->prepare(
          "INSERT INTO discounturi_acordate
             (id_vanz, id_operator, tip_discount, valoare_procent, valoare_discount_ron, pret_unitar_initial, pret_unitar_final, id_operatiune_globala)
           VALUES
             (:id_vanz, :op, 'global_valoric', NULL, :val_ron, :p_init, :p_final, :op_id)"
        );
        $op_id = uniqid('GVAL-', true);
        $ramas = $global_discount_total_input;

        foreach ($non_sgr as $r) {
          if ($ramas <= 0.001) break;
          $id = (int)$r['id_vanz']; $pinit=(float)$r['pret_vanzare']; $qty=(float)$r['cantitate']; $tva=(float)$r['cota_tva'];
          if ($qty<=0) continue;
          $rowcap = ($pinit>1) ? round(($pinit-1)*$qty,2) : 0;
          if ($rowcap<=0) continue;

          if ($ramas >= $rowcap) { $rowdisc = $rowcap; $pnew = 1.00; }
          else { $rowdisc = $ramas; $pnew = round($pinit - ($rowdisc/$qty), 4); if ($pnew<1) $pnew=1.00; }

          $ramas = round($ramas - $rowdisc, 2);
          $vcu = round($pnew*$qty,2);
          $tva_col = round($vcu*$tva/(100+$tva),2);
          $vfara = round($vcu - $tva_col, 2);

          $stmt_update->execute([
            ':new_pret'=>$pnew, ':discount'=>$rowdisc, ':v_cu_tva'=>$vcu, ':tva'=>$tva_col, ':v_fara'=>$vfara, ':id'=>$id
          ]);
          $stmt_log->execute([
            ':id_vanz'=>$id, ':op'=>$id_operator, ':val_ron'=>$rowdisc,
            ':p_init'=>$pinit, ':p_final'=>$pnew, ':op_id'=>$op_id
          ]);
        }
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo "<script>alert('Eroare: ".htmlspecialchars($e->getMessage())."');</script>";
    }
    echo "<script>location.href='vanzare_restaurant.php'</script>";
    exit;
}

// PROCENTUAL GLOBAL
if (isset($_POST['apl_disc_proc_global'])) {
    $nr_bon = (int)($_SESSION['nr_bon'] ?? 0);
    $id_operator = (int)($_SESSION['admin_id'] ?? 0);
    $proc = (float)($_POST['val_procent_global'] ?? 0);

    if ($nr_bon <= 0 || $proc <= 0) { echo "<script>location.href='vanzare_restaurant.php'</script>"; exit; }

    try {
      $pdo->beginTransaction();
      $stmt = $pdo->prepare("SELECT id_vanz, pret_vanzare, cantitate, cota_tva, nume_produs FROM $tabel_final_det_note WHERE nr_bon=:nr");
      $stmt->execute([':nr'=>$nr_bon]);
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      if (!$rows) { $pdo->rollBack(); echo "<script>location.href='vanzare_restaurant.php'</script>"; exit; }

      $total_all = 0; $total_non = 0; $non = [];
      foreach ($rows as $r) {
        $val = round($r['pret_vanzare']*$r['cantitate'],2);
        $total_all += $val;
        if (strpos($r['nume_produs'],'SGR') === false) { $non[] = $r + ['valoare_init'=>$val]; $total_non += $val; }
      }
      $disc_total = round($total_all*$proc/100,2);
      if ($total_non <= 0 || $disc_total <= 0) { $pdo->rollBack(); echo "<script>alert('Nu există produse eligibile.');location.href='vanzare_restaurant.php'</script>"; exit; }

      $stmt_update = $pdo->prepare("UPDATE $tabel_final_det_note SET pret_vanzare=:p, discount=:d, valoare_vanzare_cu_tva=:vcu, tva_col=:tva, valoare_vanzare=:vf WHERE id_vanz=:id");
      $stmt_log = $pdo->prepare("INSERT INTO discounturi_acordate (id_vanz,id_operator,tip_discount,valoare_procent,valoare_discount_ron,pret_unitar_initial,pret_unitar_final,id_operatiune_globala)
                                 VALUES (:id,:op,'global_procentual',:pr,:ron,:pi,:pf,:opid)");
      $opid = uniqid('GPROC-', true);
      $ramas = $disc_total;

      for ($i=0; $i<count($non); $i++) {
        $r = $non[$i];
        $id=(int)$r['id_vanz']; $pi=(float)$r['pret_vanzare']; $q=(float)$r['cantitate']; $t=(float)$r['cota_tva']; $vi=(float)$r['valoare_init'];
        if ($q<=0) continue;

        if ($i < count($non)-1) $disc_row = round($disc_total * ($vi / $total_non), 2);
        else $disc_row = round($ramas, 2);

        if ($disc_row > $vi) $disc_row = $vi;
        $ramas = round($ramas - $disc_row, 2);

        $vcu = round($vi - $disc_row, 2);
        $pnew = round($vcu / max($q,1), 4);
        $vcu_final = round($pnew*$q,2);
        $d_final = round($vi - $vcu_final, 2);
        $tva_col = round($vcu_final*$t/(100+$t),2);
        $vf = round($vcu_final - $tva_col,2);

        $stmt_update->execute([':p'=>$pnew, ':d'=>$d_final, ':vcu'=>$vcu_final, ':tva'=>$tva_col, ':vf'=>$vf, ':id'=>$id]);
        $stmt_log->execute([':id'=>$id, ':op'=>$id_operator, ':pr'=>$proc, ':ron'=>$d_final, ':pi'=>$pi, ':pf'=>$pnew, ':opid'=>$opid]);
      }
      $pdo->commit();
    } catch (PDOException $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      echo "<script>alert('Eroare: ".htmlspecialchars($e->getMessage())."');</script>";
    }
    echo "<script>location.href='vanzare_restaurant.php'</script>";
    exit;
}

// RESET PREȚURI INIȚIALE
if (isset($_POST['resetare_preturi_initiale'])) {
  $nr_bon = (int)($_SESSION['nr_bon'] ?? 0);
  if ($nr_bon <= 0) { echo "<script>location.href='vanzare_restaurant.php'</script>"; exit; }

  try{
    $pdo->beginTransaction();
    $sql = "SELECT d.id_vanz, d.cantitate, d.cota_tva, n.pret_cu_tva
            FROM $tabel_final_det_note d
            JOIN $tabel_final_nomenclator n ON d.cod_p = n.cod_produs
            WHERE d.nr_bon = :nr";
    $stmt = $pdo->prepare($sql); $stmt->execute([':nr'=>$nr_bon]);

    $del = $pdo->prepare("DELETE FROM discounturi_acordate WHERE id_vanz = :id");
    $upd = $pdo->prepare("UPDATE $tabel_final_det_note
                          SET pret_vanzare=:p, valoare_vanzare_cu_tva=:vcu, tva_col=:tva, valoare_vanzare=:vf, discount=0
                          WHERE id_vanz=:id");
    while($r=$stmt->fetch(PDO::FETCH_ASSOC)){
      $id=(int)$r['id_vanz']; $q=(float)$r['cantitate']; $t=(float)$r['cota_tva']; $pinit=round((float)$r['pret_cu_tva'],2);
      $vcu=round($pinit*$q,2); $tva_col=round($vcu*$t/(100+$t),2); $vf=round($vcu-$tva_col,2);
      $del->execute([':id'=>$id]);
      $upd->execute([':p'=>$pinit, ':vcu'=>$vcu, ':tva'=>$tva_col, ':vf'=>$vf, ':id'=>$id]);
    }
    $pdo->commit();
  } catch(PDOException $e){
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "<script>alert('Eroare: ".htmlspecialchars($e->getMessage())."');</script>";
  }
  echo "<script>location.href='vanzare_restaurant.php'</script>";
  exit;
}
?>
