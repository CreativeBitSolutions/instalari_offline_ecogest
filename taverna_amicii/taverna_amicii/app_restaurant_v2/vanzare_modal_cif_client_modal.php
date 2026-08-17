<style>
  .cif-input-modern {
    font-size: 1.8em !important; text-align:center; font-weight:700;
    letter-spacing: 2px; border:2px solid #ced4da; padding:15px 10px !important; margin-bottom:20px !important;
  }
  #cifKeyboard { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; width:100%; }
  #cifKeyboard .btn { font-size:1.4em; padding:14px 0; font-weight:700; border-radius:8px; }
  #cifKeyboard .key-bksp { grid-column: span 2; }
</style>

<div class="modal fade" id="CifClientModal" tabindex="-1" role="dialog" aria-labelledby="CifClientModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document" style="max-width:420px;">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="CifClientModalLabel">CIF / CUI client</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Închide"><span aria-hidden="true">&times;</span></button>
      </div>

      <form method="POST">
        <div class="modal-body">
          <div class="form-group">
            <input type="text" class="form-control cif-input-modern" id="cif_client_modal" name="cif_client_modal"
                   maxlength="10" placeholder="Introduceți CIF..."
                   value="<?php echo isset($_SESSION['cif_client']) ? htmlspecialchars($_SESSION['cif_client'],ENT_QUOTES,'UTF-8') : ''; ?>">
          </div>

          <div id="cifKeyboard">
            <button type="button" class="btn btn-light" data-key="1">1</button>
            <button type="button" class="btn btn-light" data-key="2">2</button>
            <button type="button" class="btn btn-light" data-key="3">3</button>
            <button type="button" class="btn btn-info"  data-key="RO">RO</button>

            <button type="button" class="btn btn-light" data-key="4">4</button>
            <button type="button" class="btn btn-light" data-key="5">5</button>
            <button type="button" class="btn btn-light" data-key="6">6</button>
            <button type="button" class="btn btn-warning" data-key="clear">Șterge</button>

            <button type="button" class="btn btn-light" data-key="7">7</button>
            <button type="button" class="btn btn-light" data-key="8">8</button>
            <button type="button" class="btn btn-light" data-key="9">9</button>
            <button type="button" class="btn btn-light" data-key="0">0</button>

            <button type="button" class="btn btn-secondary key-bksp" data-key="bksp">&larr; Backspace</button>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Anulează</button>
          <button type="submit" class="btn btn-primary" name="save_cif_client" value="1">Salvează</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Deschidere modal din butonul de pe pagină sau click pe inputul din footer
$(document).on('click', '#openCifModal, #cif_client', function(e){
  e.preventDefault();
  $('#CifClientModal').modal('show');
  setTimeout(function(){ $('#cif_client_modal').trigger('focus'); }, 200);
});

// Tastatură custom CIF
$(document).on('click', '#cifKeyboard [data-key]', function(){
  var key = String($(this).attr('data-key')); // <-- FIX: attr, nu data
  var $inp = $('#cif_client_modal');
  var v = $inp.val() || '';

  if (key === 'bksp') { $inp.val(v.slice(0, -1)); return; }
  if (key === 'clear') { $inp.val(''); return; }
  if (key.toUpperCase() === 'RO') {
    if (!/^RO/i.test(v)) {
      $inp.val(('RO' + v.replace(/^RO/i, '')).toUpperCase().slice(0,10));
    } else {
      $inp.val(v.toUpperCase().slice(0,10));
    }
    return;
  }
  if (/^[0-9]$/.test(key) && v.length < 10) {
    $inp.val((v + key).toUpperCase());
  }
});

</script>

<?php
if (isset($_POST['save_cif_client'])) {
  $_SESSION['cif_client'] = substr(strtoupper(trim($_POST['cif_client_modal'] ?? '')), 0, 10);
  echo "<script>location.href='vanzare_restaurant.php'</script>";
  exit;
}
?>
