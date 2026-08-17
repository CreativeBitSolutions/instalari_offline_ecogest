<!-- Modal: Tichete de masă -->
<div class="modal fade" id="Plata_tichete" tabindex="-1" role="dialog" aria-labelledby="PlataTicheteLabel" aria-hidden="true">
  <div class="modal-dialog" role="document" style="max-width:420px;">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="PlataTicheteLabel">Datele încasării</h4>
        <button type="button" class="close" data-dismiss="modal" aria-label="Închide"><span aria-hidden="true">&times;</span></button>
      </div>

      <div class="modal-body">
        <?php
          // fallback dacă nu există $total_val_vz_cu_tva în scope
          if (!isset($total_val_vz_cu_tva)) {
            $nr_bon_cur = isset($nr_bon) ? (int)$nr_bon : (int)($_SESSION['nr_bon'] ?? 0);
            $st = $pdo->prepare("SELECT COALESCE(SUM(valoare_vanzare_cu_tva),0) AS t FROM $tabel_final_det_note WHERE nr_bon=:n");
            $st->execute([':n'=>$nr_bon_cur]);
            $total_val_vz_cu_tva = (float)($st->fetch(PDO::FETCH_ASSOC)['t'] ?? 0);
          }
        ?>
        <form class="form-horizontal" method="POST" action="javascript:void(0);">
          <div class="form-group">
            <label for="total_de_plata_tichete">Total de plată</label>
            <input readonly type="number" step="0.001" class="form-control" id="total_de_plata_tichete" name="total_de_plata_tichete" value="<?php echo number_format($total_val_vz_cu_tva,3,'.',''); ?>">
          </div>

          <div class="form-group">
            <label for="nr_tichete">Numărul tichetelor</label>
            <input type="number" step="1" min="1" class="form-control" id="nr_tichete" name="numerar" value="1" onchange="updateTichete()">
          </div>

          <div class="form-group">
            <label for="val_tichet">Valoarea unui tichet</label>
            <input type="number" step="0.001" min="0" class="form-control" id="val_tichet" value="<?php echo number_format($total_val_vz_cu_tva,3,'.',''); ?>" onchange="updateNrTichete()">
          </div>

          <div class="form-group">
            <label for="total_tichete">Valoarea tichetelor</label>
            <input readonly type="number" step="0.001" class="form-control" id="total_tichete" name="total_tichete" value="<?php echo number_format($total_val_vz_cu_tva,3,'.',''); ?>">
          </div>

          <div class="form-group">
            <label for="rest_de_incasat">Rest de încasat în numerar</label>
            <input type="number" step="0.001" min="0" class="form-control" id="rest_de_incasat" name="rest_de_incasat" value="0" onchange="updateRestTichete()">
          </div>

          <div class="form-group">
            <label for="rest_de_returnat">Rest de returnat</label>
            <input readonly type="number" step="0.001" class="form-control" id="rest_de_returnat" name="rest_de_returnat" value="0">
          </div>

          <div class="form-group">
            <label for="cif_client_t">CIF Client</label>
            <input type="text" maxlength="10" class="form-control" id="cif_client_t" name="cif_client_t" value="<?php echo isset($_SESSION['cif_client']) ? htmlspecialchars($_SESSION['cif_client'],ENT_QUOTES,'UTF-8') : ''; ?>">
          </div>

          <!-- Butonul este interceptat de handlerul central din pagina principală -->
          <div class="modal-footer p-0 pt-2">
            <button class="btn btn-primary btn-block" type="submit" id="finalizare_tichet" name="finaliz_bon" value="tichete_de_masa">Finalizare Bon</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
function round3(x){ return Math.round((x+Number.EPSILON)*1000)/1000; }
function parseNum(id){ var v = parseFloat(document.getElementById(id).value || '0'); return isNaN(v)?0:v; }

function updateTichete(){
  var n  = parseNum('nr_tichete');
  var vt = parseNum('val_tichet');
  document.getElementById('total_tichete').value = round3(n*vt);
  updateRestTichete();
}
function updateNrTichete(){
  var total = parseNum('total_de_plata_tichete');
  var vt    = parseNum('val_tichet');
  if (vt <= 0){ document.getElementById('nr_tichete').value = 1; vt = 1; }
  document.getElementById('nr_tichete').value = Math.max(1, Math.ceil(total / vt));
  updateTichete();
}
function updateRestTichete(){
  var total = parseNum('total_de_plata_tichete');
  var tichete = parseNum('total_tichete');
  var incasat = parseNum('rest_de_incasat');
  var rest = round3(total - tichete - incasat);
  document.getElementById('rest_de_returnat').value = rest < 0 ? Math.abs(rest) : 0;
}
</script>
