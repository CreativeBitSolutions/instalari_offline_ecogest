<!-- Modal: Mută Masa (toate produsele pe altă notă) -->
<div class="modal fade" id="MutaMasaModal" tabindex="-1" role="dialog" aria-labelledby="MutaMasaModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document" style="max-width:420px;">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="MutaMasaModalLabel">Mută toate produsele pe altă notă</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Închide"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <form id="mutaMasaForm" method="POST" action="vanzare_update_muta_masa.php">
          <div class="form-group">
            <label for="muta_masa_new_nrbon">Selectează nota/masa destinație:</label>
            <select class="form-control" name="new_nrbon" id="muta_masa_new_nrbon" required>
              <option value="">-- Alege Nota --</option>
              <?php 
                $sql_muta_masa = "
                  SELECT n.nrbon, m.nume_masa, a.admin_lastname, a.admin_firstname
                  FROM $tabel_final_note n
                  JOIN mese m ON m.cod_masa = n.cod_masa
                  JOIN $tabel_final_admins a ON a.admin_id = n.operator
                  WHERE n.status='S' AND n.locatie = :loc AND n.nrbon != :current_bon and n.operator=$adm_id
                  ORDER BY m.nume_masa ASC";
                $st_muta_masa = $pdo->prepare($sql_muta_masa);
                $st_muta_masa->execute([':loc' => $cod_locatie, ':current_bon' => $_SESSION['nr_bon'] ?? 0]);
                while($nota_row = $st_muta_masa->fetch(PDO::FETCH_ASSOC)){
                  echo '<option value="'.(int)$nota_row['nrbon'].'">'.
                        htmlspecialchars($nota_row['nume_masa']).' | Nota: '.(int)$nota_row['nrbon'].' | '.
                        htmlspecialchars($nota_row['admin_firstname'].' '.$nota_row['admin_lastname']).'</option>';
                }
              ?>
            </select>
          </div>
          <button type="submit" class="btn btn-primary btn-block mt-3">
            <i class="fas fa-exchange-alt mr-1"></i> Mută Toate Produsele
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
