<!-- Modalul "listarenotaimprimanta" -->
<div class="modal fade" id="listarenotaimprimanta" tabindex="-1" role="dialog" aria-labelledby="listarenotaimprimantalabel" aria-hidden="true">
  <div class="modal-dialog" role="document" style="max-width: 500px;">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="listarenotaimprimantalabel">Trimite la imprimantă</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Închide">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <div class="modal-body" style="padding:0;">
        <div class="form-group" style="padding:15px;">
          <label for="notaSelectS">Comanda curentă</label>

<?php
  // Folosim nr_bon din sesiune ca fallback dacă nu există variabila locală
  $nr_bon_curent = isset($nr_bon) ? (int)$nr_bon : (int)($_SESSION['nr_bon'] ?? 0);
  $adm_curent    = isset($adm_id) ? (int)$adm_id : (int)($_SESSION['admin_id'] ?? 0);
?>

          <!-- Trimitere BAR/BUC doar produse noi -->
          <form method="POST" action="vanzare_restaurant_listare_nota.php" id="formNotaS">
            <select class="form-control" name="nota_de_relistat" id="notaSelectS">
              <?php
              if ($nr_bon_curent > 0 && $adm_curent > 0) {
                $sqlS = "SELECT nrbon, data_bon, ora_bon, cod_masa
                         FROM note
                         WHERE nrbon = :nrbon AND status = 'S' AND operator = :adm_id";
                $stmtS = $pdo->prepare($sqlS);
                $stmtS->execute([':nrbon' => $nr_bon_curent, ':adm_id' => $adm_curent]);
                foreach ($stmtS->fetchAll(PDO::FETCH_ASSOC) as $note) {
                  echo '<option value="'.(int)$note['nrbon'].'">Bon '.(int)$note['nrbon'].' - Masa '.htmlspecialchars($note['cod_masa']).'</option>';
                }
              }
              ?>
            </select>

            <div id="detNoteDetailsS" style="margin-top:15px;"></div>

            <button type="submit"
                    class="btn btn-primary"
                    id="trimite_produsele_noi"
                    style="margin-top:10px;"
                    name="listeaza_tot"
                    value="nu">
              Trimite la imprimantă BAR/BUC produsele noi
            </button>

            <?php
            // dacă există deja produse listate pe nota curentă, oferă și opțiunea "toată nota"
            if ($nr_bon_curent > 0) {
              $stmtCount = $pdo->prepare("SELECT COUNT(*) AS cnt FROM det_note WHERE nr_bon = :nrbon AND t_list = 1");
              $stmtCount->execute([':nrbon' => $nr_bon_curent]);
              $are_listate = (int)($stmtCount->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
              if ($are_listate > 0) {
                echo '<button type="submit" class="btn btn-primary" style="margin-top:10px;" name="listeaza_tot" value="da">
                        Trimite la imprimantă BAR/BUC toată nota
                      </button>';
              }
            }
            ?>
          </form>

          <!-- Nota informativă pentru client (proxy din footer pe #nota_de_plata_client) -->
          <form method="POST" action="vanzare_restaurant_listare_nota.php" id="formNotaInformativa">
            <select class="form-control" style="display:none;" name="nota_de_relistat" id="notaSelectS2">
              <?php
              if ($nr_bon_curent > 0 && $adm_curent > 0) {
                $sqlS2 = "SELECT nrbon, cod_masa FROM note WHERE nrbon = :nrbon AND status = 'S' AND operator = :adm_id";
                $stmtS2 = $pdo->prepare($sqlS2);
                $stmtS2->execute([':nrbon' => $nr_bon_curent, ':adm_id' => $adm_curent]);
                foreach ($stmtS2->fetchAll(PDO::FETCH_ASSOC) as $note) {
                  echo '<option value="'.(int)$note['nrbon'].'">Bon '.(int)$note['nrbon'].' - Masa '.htmlspecialchars($note['cod_masa']).'</option>';
                }
              }
              ?>
            </select>
            <button type="submit"
                    class="btn btn-primary"
                    style="margin-top:10px;"
                    id="nota_de_plata_client"
                    name="nota_de_plata_client"
                    value="da">
              Nota informativă pentru client
            </button>
          </form>
        </div>

        <!-- Note încasate (status F) -->
        <div class="form-group" style="padding:15px; border-bottom:1px solid #ddd;">
          <label for="notaSelectF">Note de plată încasate</label>
          <form method="POST" action="vanzare_restaurant_listare_nota_incasata.php" id="formNotaF">
            <select class="form-control" name="nota_de_relistat" id="notaSelectF">
              <option value="">-- Alege o notă --</option>
              <?php
              if ($adm_curent > 0) {
                $sqlF = "SELECT nrbon, data_bon, ora_bon, cod_masa
                         FROM note
                         WHERE cod_inchidere = 0
                           AND status = 'F'
                           AND operator = :adm_id
                         ORDER BY nrbon DESC";
                $stmtF = $pdo->prepare($sqlF);
                $stmtF->execute([':adm_id' => $adm_curent]);
                foreach ($stmtF->fetchAll(PDO::FETCH_ASSOC) as $note) {
                  echo '<option value="'.(int)$note['nrbon'].'">Bon '.(int)$note['nrbon'].' - Masa '.htmlspecialchars($note['cod_masa']).' - '
                       .htmlspecialchars($note['data_bon']).' '.htmlspecialchars($note['ora_bon']).'</option>';
                }
              }
              ?>
            </select>

            <div id="detNoteDetailsF" style="margin-top:15px;"></div>

            <button type="submit"
                    class="btn btn-primary"
                    style="margin-top:10px;"
                    name="relistare_nota_plata"
                    value="da">
              Relistează nota de plată
            </button>
          </form>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Închide</button>
      </div>
    </div>
  </div>
</div>
<!-- Modalul "listarenotaimprimanta" end -->
