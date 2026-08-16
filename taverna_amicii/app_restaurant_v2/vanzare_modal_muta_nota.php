<!-- Modal: Mută Nota -->
<div class="modal fade" id="MutaNotaModal" tabindex="-1" role="dialog" aria-labelledby="MutaNotaModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document" style="max-width:420px;">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="MutaNotaModalLabel">Mută produsul pe o altă notă</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Închide"><span aria-hidden="true">&times;</span></button>
      </div>

      <div class="modal-body">
        <form id="mutaNotaForm" method="POST" action="vanzare_update_muta_nota.php">
          <input type="hidden" name="idvanz" id="mutaNotaIdVanz" value="">
          <div class="form-group">
            <label for="new_nrbon">Selectează nota/masa:</label>
            <select class="form-control" name="new_nrbon" id="new_nrbon" required>
              <option value="">-- Alege Nota --</option>
              <?php
                $sql = "
                  SELECT n.nrbon, m.nume_masa, a.admin_lastname, a.admin_firstname
                  FROM $tabel_final_note n
                  JOIN mese m ON m.cod_masa = n.cod_masa
                  JOIN $tabel_final_admins a ON a.admin_id = n.operator
                  WHERE n.status='S' AND n.locatie = :loc and n.operator=$adm_id
                  ORDER BY n.nrbon ASC";
                $st = $pdo->prepare($sql);
                $st->execute([':loc'=>$cod_locatie]);
                while($note=$st->fetch(PDO::FETCH_ASSOC)){
                  echo '<option value="'.(int)$note['nrbon'].'">'.
                        (int)$note['nrbon'].' | Masa: '.htmlspecialchars($note['nume_masa']).' | Operator: '.
                        htmlspecialchars($note['admin_firstname'].' '.$note['admin_lastname']).'</option>';
                }
              ?>
            </select>
          </div>
          <button type="submit" class="btn btn-primary btn-block">Mută Produsul</button>
        </form>

        <form method="POST" action="vanzare_retur_produs.php" class="mt-2">
          <input type="hidden" name="idvanz" id="mutaNotaIdVanzRetur" value="">
          <div id="buton_retur">
            <!-- completat din JS (vezi vanzare_restaurant.php) -->
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
