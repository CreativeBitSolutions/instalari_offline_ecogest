<!-- Modal Raport Z -->
<div class="modal fade" id="raportZModal" tabindex="-1" role="dialog" aria-labelledby="raportZModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document" style="max-width:520px;">
    <div class="modal-content">
      <div class="modal-header">
         <h5 class="modal-title" id="raportZModalLabel">Completează valorile Raport Z</h5>
         <button type="button" class="close" data-dismiss="modal" aria-label="Închide"><span aria-hidden="true">&times;</span></button>
      </div>

      <?php
        $sql = "SELECT COALESCE(SUM(numerar),0) AS total_numerar,
                       COALESCE(SUM(card),0)    AS total_card,
                       COALESCE(SUM(tichete),0) AS total_tichete
                FROM $tabel_final_note
                WHERE status='F' AND locatie=:loc AND nr_raport_z=0 AND cod_inchidere!=0";
        $st = $pdo->prepare($sql);
        $st->execute([':loc'=>$cod_locatie]);
        $sum_data = $st->fetch(PDO::FETCH_ASSOC) ?: ['total_numerar'=>0,'total_card'=>0,'total_tichete'=>0];
        $total_numerar_z = number_format((float)$sum_data['total_numerar'], 2, '.', '');
        $total_card_z    = number_format((float)$sum_data['total_card'], 2, '.', '');
        $total_tichete_z = number_format((float)$sum_data['total_tichete'], 2, '.', '');

        $st2 = $pdo->prepare("SELECT MAX(nr_raport_z) AS last_report FROM rapoarte_z WHERE cod_locatie = :loc");
        $st2->execute([':loc'=>$cod_locatie]);
        $last = (int)($st2->fetch(PDO::FETCH_ASSOC)['last_report'] ?? 0);
        $nr_raport_z = $last + 1;
      ?>

      <div class="modal-body">
        <form method="POST" id="raportZForm" action="vanzare_inchidere_zi.php">
          <div class="form-group"><label>Numerar</label><input type="number" step="0.01" min="0" class="form-control" name="numerar" id="numerarInput" value="<?php echo $total_numerar_z; ?>" required></div>
          <div class="form-group"><label>Card</label><input type="number" step="0.01" min="0" class="form-control" name="card" id="cardInput" value="<?php echo $total_card_z; ?>" required></div>
          <div class="form-group"><label>Credit</label><input type="number" step="0.01" min="0" class="form-control" name="credit" id="creditInput" value="0" required></div>
          <div class="form-group"><label>Tichete masă</label><input type="number" step="0.01" min="0" class="form-control" name="tichete_masa" id="ticheteMasaInput" value="<?php echo $total_tichete_z; ?>" required></div>
          <div class="form-group"><label>Tichete valorice</label><input type="number" step="0.01" min="0" class="form-control" name="tichete_valorice" id="ticheteValoriceInput" value="0" required></div>
          <div class="form-group"><label>Voucher</label><input type="number" step="0.01" min="0" class="form-control" name="voucher" id="voucherInput" value="0" required></div>
          <div class="form-group"><label>Plată modernă</label><input type="number" step="0.01" min="0" class="form-control" name="plata_moderna" id="plataModernaInput" value="0" required></div>
          <div class="form-group"><label>Avans în numerar</label><input type="number" step="0.01" min="0" class="form-control" name="avans_in_numerar" id="avansNumerarInput" value="0" required></div>
          <div class="form-group"><label>Alte metode</label><input type="number" step="0.01" min="0" class="form-control" name="alte_metode" id="alteMetodeInput" value="0" required></div>
          <div class="form-group"><label>Nr. raport Z scos la casa de marcat</label><input type="number" step="1" min="1" class="form-control" name="nr_raport_z" id="nr_raport_z" value="<?php echo $nr_raport_z; ?>" required></div>
        </form>
      </div>

      <div class="modal-footer">
         <button type="button" class="btn btn-secondary" data-dismiss="modal">Închide</button>
         <button type="submit" form="raportZForm" class="btn btn-primary" name="submit_raportz" value="1">Închide ziua complet</button>
      </div>
    </div>
  </div>
</div>
