<!-- Modal Relistare Note -->
<div class="modal fade" id="Relistare" tabindex="-1" role="dialog" aria-labelledby="RelistareLabel" aria-hidden="true">
  <div class="modal-dialog" role="document" style="max-width:900px;">
    <div class="modal-content">
      <div class="modal-header align-items-start">
        <div class="mr-3" style="display:flex;flex-direction:column;align-items:center;">
          <button class="btn btn-outline-secondary btn-sm mb-2" id="scrollup4" title="Derulează în sus"><i class="fa fa-chevron-up"></i></button>
          <button class="btn btn-outline-secondary btn-sm" id="scrolldown4" title="Derulează în jos"><i class="fa fa-chevron-down"></i></button>
        </div>
        <div class="flex-grow-1">
          <h4 class="modal-title" id="RelistareLabel">Notele din ultimele 48h</h4>
          <div>Alege nota ce va fi retrimisă la casa de marcat</div>
          <div class="mt-2">
            <button class="btn btn-light leftArrowRelistare">&larr;</button>
            <button class="btn btn-light rightArrowRelistare">&rarr;</button>
          </div>
        </div>
        <button type="button" class="close ml-2" data-dismiss="modal" aria-label="Închide"><span aria-hidden="true">&times;</span></button>
      </div>

      <div class="modal-body brelistare" style="height:31.25em;max-width:100%;overflow-x:auto;">
        <div class="scoll-tree2 d-flex flex-wrap" style="gap:10px;">
          <?php
            $acum2_zile = date("Y-m-d", strtotime('-48 hours'));
            $sql = "SELECT cif_client, numerar, card, tichete, nrbon, data_bon
                    FROM $tabel_final_note
                    WHERE status='F' AND data_bon >= :d AND operator=:op AND locatie=:loc
                    ORDER BY data_bon";
            $st = $pdo->prepare($sql);
            $st->execute([':d'=>$acum2_zile, ':op'=>$adm_id, ':loc'=>$cod_locatie]);

            while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
              $data_fmt = date('d-m-Y', strtotime($r['data_bon']));
              $bon = (int)$r['nrbon'];
              $btnName = $bon.'NDR';

              echo "<figure class='masa' style='min-width:220px;max-width:260px;'>
                      <form method='POST'>
                        <button class='btn btn-outline-secondary list-group2' type='submit' name='$btnName' style='width:100%;text-align:left;'>
                          <div style='font-weight:600;'>Nota nr. $bon</div>
                          <div>Data: $data_fmt</div>
                          <div style='margin-top:6px;font-weight:600;'>Produse:</div>";

              $st2 = $pdo->prepare("SELECT n.nume FROM $tabel_final_det_note d JOIN $tabel_final_nomenclator n ON d.cod_p=n.cod_produs WHERE d.nr_bon=:b");
              $st2->execute([':b'=>$bon]);
              while ($p = $st2->fetch(PDO::FETCH_ASSOC)) {
                echo "<div>".htmlspecialchars($p['nume'])."</div>";
              }

              echo "  </button>
                      </form>
                    </figure>";

              if (isset($_POST[$btnName])) {
                $_SESSION['nr_bon'] = $bon;
                if (!empty($r['cif_client']))   $_SESSION['cif_client'] = $r['cif_client'];
                if ((float)$r['numerar'] > 0)   $_SESSION['numerarprim'] = $r['numerar'];
                if ((float)$r['card'] > 0)      $_SESSION['cardprim']    = $r['card'];
                if ((float)$r['tichete'] > 0)   $_SESSION['total_tichete'] = $r['tichete'];

                if ($_SESSION['mod_listare'] == 'complex') echo "<script>location.href='dwred_restaurant_cu_listare.php'</script>";
                else echo "<script>location.href='dwred_vanzare_restaurant.php'</script>";
                exit;
              }
            }
          ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Scroller simplu pe listă (opțional)
$('.leftArrowRelistare').on('mousedown', function(){ $('.brelistare').animate({scrollLeft:'-=350'},300); });
$('.rightArrowRelistare').on('mousedown', function(){ $('.brelistare').animate({scrollLeft:'+=350'},300); });
$('#scrollup4').on('mousedown', function(){ $('.brelistare').animate({scrollTop:'-=300'},300); });
$('#scrolldown4').on('mousedown', function(){ $('.brelistare').animate({scrollTop:'+=300'},300); });
</script>
