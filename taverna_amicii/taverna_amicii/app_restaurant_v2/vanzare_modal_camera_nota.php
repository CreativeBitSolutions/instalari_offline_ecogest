<!-- Modal „Selectează camera” -->
<div class="modal fade" id="modalCameraNota" tabindex="-1" role="dialog" aria-labelledby="modalCameraNotaLabel" aria-hidden="true">
  <div class="modal-dialog" role="document" style="max-width:420px;">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalCameraNotaLabel">Selectează camera</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Închide"><span aria-hidden="true">&times;</span></button>
      </div>

      <form method="POST">
        <div class="modal-body">
          <select class="form-control" name="camera_select" required>
            <?php
              $camera_curenta = $_SESSION['camera_nota'] ?? null;
              for ($i = 1; $i <= 48; $i++) {
                if ($i == 13) continue; // fără camera 13
                $sel = ($camera_curenta == $i) ? 'selected' : '';
                echo "<option value='$i' $sel>Camera $i</option>";
              }
            ?>
          </select>
        </div>

        <div class="modal-footer">
          <button type="submit" name="salveaza_camera" value="1" class="btn btn-primary">Salvează</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
if (isset($_POST['salveaza_camera'])) {
  $_SESSION['camera_nota'] = (int)($_POST['camera_select'] ?? 0);
  echo "<script>location.href='vanzare_restaurant.php'</script>";
  exit;
}
?>
