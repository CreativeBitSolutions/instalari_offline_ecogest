<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save_obs'])) {
  if (!isset($pdo, $tabel_final_det_note)) {
    include 'session.php';
  }

  header('Content-Type: application/json; charset=UTF-8');

  $idv = (int)($_POST['idvanzare_obs'] ?? 0);
  $obs = trim((string)($_POST['observatie_produs'] ?? ''));

  if ($idv <= 0 || $obs === '') {
    echo json_encode(['success' => false, 'message' => 'Date invalide'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  try {
    $stmt = $pdo->prepare("UPDATE $tabel_final_det_note SET observatie_produs = :o WHERE id_vanz = :id");
    $stmt->execute([':o' => $obs, ':id' => $idv]);
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
  } catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Eroare la salvare'], JSON_UNESCAPED_UNICODE);
  }
  exit;
}
?>
<!-- Modal Observatie pe produs -->
<div class="modal fade" id="observatie_produsModal" tabindex="-1" role="dialog" aria-labelledby="observatie_produsModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document" style="max-width:600px;">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="observatie_produsModalLabel">Adauga Observatie</h4>
        <button type="button" class="close" data-dismiss="modal" aria-label="Inchide"><span aria-hidden="true">&times;</span></button>
      </div>

      <div class="modal-body">
        <div id="garnituri_container" style="display:none; margin-bottom:15px;">
          <label class="font-weight-bold mb-2" style="font-size:16px;">Alege garnitura:</label>
          <div id="garnituri_buttons" style="display:grid; grid-template-columns:repeat(3, 1fr); gap:8px;"></div>
          <hr style="margin-top:12px;">
        </div>

        <form id="observatieProdusForm" method="POST" action="javascript:void(0);">
          <input type="hidden" name="idvanzare_obs" id="idvanzare_obs" value="">
          <input type="hidden" name="oldname" id="oldname" value="">
          <div class="form-group">
            <label for="observatie_produsInput">Observatie</label>
            <input type="text" class="form-control" name="observatie_produs" id="observatie_produsInput" placeholder="Introdu observatia">
          </div>
          <button type="submit" class="btn btn-primary">Salveaza Observatie</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  var nrBon = <?php echo json_encode($_SESSION['nr_bon'] ?? 0); ?>;
  var codMasa = <?php echo json_encode($_SESSION['masa_curenta'] ?? 0); ?>;

  $(document).off('submit.observatieProd', '#observatieProdusForm').on('submit.observatieProd', '#observatieProdusForm', function(e){
    e.preventDefault();

    var idv = $('#idvanzare_obs').val();
    var obs = ($('#observatie_produsInput').val() || '').trim();

    if (!idv || !obs.length) {
      alert('Completeaza observatia.');
      return;
    }

    $.ajax({
      url: 'vanzare_modal_observatie.php',
      type: 'POST',
      dataType: 'json',
      data: {
        ajax_save_obs: 1,
        idvanzare_obs: idv,
        observatie_produs: obs
      }
    }).done(function(r){
      if (!r || !r.success) {
        alert('Eroare la salvare observatie.');
        return;
      }

      $('#observatie_produsModal').modal('hide');
      if (typeof loadAfisProd === 'function') {
        loadAfisProd(nrBon, codMasa);
      }
    }).fail(function(){
      alert('Eroare de retea.');
    });
  });
})();
</script>
