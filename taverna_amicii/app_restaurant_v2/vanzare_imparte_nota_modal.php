<?php ini_set('display_errors', 0); // Nu afișează erorile utilizatorului
ini_set('log_errors', 1); // Activează logarea erorilor
ini_set('error_log', 'error_log.log'); // Specifică calea către fișierul de log
include('session.php');
$nr_bon=$_SESSION['nr_bon'];

?>
<!-- Modalul "Imparte Nota" -->
<div class="modal fade" id="imparteNotaModal" tabindex="-1" role="dialog" aria-labelledby="imparteNotaLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <!-- Formularul se va trimite către scriptul vanzare_imparte_nota.php -->
    <form method="POST" action="vanzare_imparte_nota.php">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="imparteNotaLabel">
            Selectează produsele de mutat pe altă notă
          </h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Închide">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <!-- Select pentru alegerea mesei de tip TEMPORARA (stare = 0) -->
          <div class="form-group">
            <label for="masaSelect">Alege masa destinație</label>
            <select name="masa_select" id="masaSelect" class="form-control" required>
              <option value="">-- Alege o masă --</option>
              <?php
              // Interogăm tabela mese pentru a prelua înregistrările cu categorie_masa "TEMPORARA" și stare 0
              $sqlMese = "SELECT cod_masa, nume_masa FROM mese WHERE categorie_masa = 'TEMPORARA' AND stare = 0 ORDER BY nume_masa ASC";
              $stmtMese = $pdo->prepare($sqlMese);
              $stmtMese->execute();
              while ($rowMasa = $stmtMese->fetch(PDO::FETCH_ASSOC)) {
                  echo '<option value="' . $rowMasa['cod_masa'] . '">' . $rowMasa['nume_masa'] . '</option>';
              }
              ?>
            </select>
          </div>

          <!-- Afișează produsele din nota curentă -->
          <div class="form-group">
            <label>Selectează produsele de mutat:</label>
            <?php
            $sqlProd = "SELECT id_vanz, nume_produs, cantitate, pret_vanzare 
                        FROM $tabel_final_det_note 
                        WHERE nr_bon = '$nr_bon'";
            $stmtProd = $pdo->prepare($sqlProd);
            $stmtProd->execute();
            $produseNota = $stmtProd->fetchAll(PDO::FETCH_ASSOC);
            if ($produseNota) {
                foreach ($produseNota as $prodRow) {
                    echo '<div class="form-check" style="margin-bottom:5px;">';
                    echo '<input class="form-check-input" type="checkbox" name="produs_selectat[]" value="' . $prodRow['id_vanz'] . '" id="prod_' . $prodRow['id_vanz'] . '">';
                    echo '<label class="form-check-label" for="prod_' . $prodRow['id_vanz'] . '">';
                    echo $prodRow['nume_produs'] . ' - Cantitate: ' . $prodRow['cantitate'] . ' - Pret: ' . $prodRow['pret_vanzare'] . ' RON';
                    echo '</label>';
                    echo '</div>';
                }
            } else {
                echo '<p>Nu există produse pentru nota curentă.</p>';
            }
            ?>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Închide</button>
          <button type="submit" name="imparteNotaSubmit" class="btn btn-primary">
            Mută Produsele Selectate
          </button>
        </div>
      </div>
    </form>
  </div>
</div>
<!-- end modal imparte nota-->
