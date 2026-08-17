<!-- în vanzare_modal_plata_mixta.php (sau unde e definit modalul) -->
<div class="modal fade" id="PlataNumerarCard" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-sm" role="document" style="max-width:22em">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Datele încasării</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Închide"><span>&times;</span></button>
      </div>

      <div class="modal-body">
        <label for="mixt_total">Total de plată</label>
        <input id="mixt_total" type="number" class="form-control mb-2" step="0.01" readonly>

        <label for="mixt_numerar">Numerar</label>
        <input id="mixt_numerar" name="numerar" type="number" class="form-control mb-2" step="0.01" min="0" inputmode="decimal">

        <label for="mixt_card">Card</label>
        <input id="mixt_card" name="card" type="number" class="form-control mb-2" step="0.01" min="0" inputmode="decimal">

        <input id="cif_client" type="text" hidden maxlength="10" name="cif_client"
               class="form-control"
               value="<?= htmlspecialchars($_SESSION['cif_client'] ?? '') ?>"
               placeholder="CIF Client">

        <!-- mirror hidden pt. ce citește PHP în branch-ul numerar_si_card -->
        <input type="hidden" id="cif_client_m" name="cif_client_m"
               value="<?= htmlspecialchars($_SESSION['cif_client'] ?? '') ?>">

        <!-- nu avem nevoie de form aici -->
        <input type="hidden" name="masa_curenta" value="<?= htmlspecialchars($_SESSION['masa_curenta'] ?? '', ENT_QUOTES) ?>">
      </div>

      <div class="modal-footer">
        <!-- IMPORTANT: trimitere prin handlerul global -->
        <button type="button"
                class="btn btn-primary btn-block"
                id="btn_finalizare_mixta"
                data-finaliz-bon="numerar_si_card">
          Finalizare Bon
        </button>
      </div>
    </div>
  </div>
</div>
