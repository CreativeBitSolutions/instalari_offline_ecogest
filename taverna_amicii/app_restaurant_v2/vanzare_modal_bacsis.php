<!-- Modal bacșis -->
<div class="modal fade" id="Bacsis" tabindex="-1" role="dialog" aria-labelledby="BacsisLabel" aria-hidden="true">
  <div class="modal-dialog" role="document" style="max-width:420px;">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="BacsisLabel">Adaugă Bacșiș</h4>
        <button type="button" class="close" data-dismiss="modal" aria-label="Închide"><span aria-hidden="true">&times;</span></button>
      </div>

      <div class="modal-body">
        <form method="POST">
          <div class="form-group">
            <label>Procent (%)</label>
            <div class="input-group">
              <input class="form-control" type="number" max="100" step="0.01" min="0" value="0" id="val_procent_bacsis" name="val_procent_bacsis">
            </div>
            <button class="btn btn-primary btn-block mt-2" type="submit" name="adauga_bacsis_proc" value="1">Adaugă bacșiș procentual</button>
          </div>

          <div class="form-group">
            <label>Sau valoare fixă (RON)</label>
            <div class="input-group">
              <input class="form-control" type="number" step="0.001" min="0.001" id="val_fix_bacsis" name="valoare_fixa">
            </div>
            <button class="btn btn-primary btn-block mt-2" type="submit" name="adauga_bacsis_fix" value="1">Adaugă bacșiș fix</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php
// Handlere bacșis
if (isset($_POST['adauga_bacsis_fix']) || isset($_POST['adauga_bacsis_proc'])) {
  $nr_bon  = (int)($_SESSION['nr_bon'] ?? 0);
  if ($nr_bon <= 0) { echo "<script>location.href='vanzare_restaurant.php'</script>"; exit; }

  $data_bon = date("Y-m-d");
  $ora_bon  = date("H:i:s");

  if (isset($_POST['adauga_bacsis_fix'])) {
    $bacsis = (float)($_POST['valoare_fixa'] ?? 0);
  } else {
    // procente: luăm totalul curent de pe bon
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(valoare_vanzare_cu_tva),0) AS total FROM $tabel_final_det_note WHERE nr_bon = :nr");
    $stmt->execute([':nr'=>$nr_bon]);
    $total_pe_nota = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    $proc_bacsis   = (float)($_POST['val_procent_bacsis'] ?? 0);
    $bacsis        = round($total_pe_nota * $proc_bacsis / 100, 2);
  }

  if ($bacsis > 0) {
    $sql = "INSERT INTO $tabel_final_det_note
              (cod_p, nume_produs, cantitate, pret_vanzare, valoare_vanzare, valoare_vanzare_cu_tva, nr_bon, data, ora)
            VALUES
              (-1, 'BACSIS', 1, :suma, :suma, :suma, :nr, :d, :o)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      ':suma'=>$bacsis, ':nr'=>$nr_bon, ':d'=>$data_bon, ':o'=>$ora_bon
    ]);
  }
  echo "<script>location.href='vanzare_restaurant.php'</script>";
  exit;
}
?>
