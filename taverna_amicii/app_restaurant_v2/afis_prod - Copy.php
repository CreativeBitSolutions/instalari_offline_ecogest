<?php // afis_prod.php
include('session.php');
$hide_discount_buttons = in_array((int)($_SESSION['client_id'] ?? 0), [25, 26], true);

// Parametri
$nr_bon = $_GET['bonul'] ?? $_SESSION['nr_bon'] ?? null;
$m_n    = $_GET['cod_masa'] ?? $_SESSION['masa_curenta'] ?? null;

if (!$nr_bon) {
    echo "<div class='p-3 text-center'>Bon invalid sau nealocat.</div>";
    exit;
}

// Interogare produse pe bon
$f_sql = "
    SELECT
        n.pret_cu_tva, n.nume,
        dn.observatie_produs, dn.discount, dn.cod_p, dn.cantitate,
        dn.pret_vanzare, dn.valoare_vanzare_cu_tva, dn.id_vanz,
        dn.preluat_osp, dn.t_list, dn.prioritate
    FROM $tabel_final_det_note dn
    JOIN $tabel_final_nomenclator n ON dn.cod_p = n.cod_produs
    WHERE dn.nr_bon = :nr_bon
    ORDER BY dn.id_vanz DESC
";
$f_stmt = $pdo->prepare($f_sql);
$f_stmt->execute([':nr_bon' => $nr_bon]);
$rows = $f_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- WRAPPER cu scroll buttons mici (sticky) -->
<div class="receipt-list-wrapper">
  <div class="receipt-items">
    <?php if (empty($rows)): ?>
      <div class="text-center p-5">Bonul este gol. Adăugați produse.</div>
    <?php else: ?>
      <?php foreach ($rows as $row): ?>
        <?php
          $id_vanz   = $row['id_vanz'];
          $cod_prod  = $row['cod_p'];
          $produs    = htmlspecialchars($row['nume']);
          $observatie= !empty($row['observatie_produs']) ? htmlspecialchars($row['observatie_produs']) : '';

          $cantitate_afisat = (float)$row['cantitate'];
          if ($cantitate_afisat == (int)$cantitate_afisat) $cantitate_afisat = (int)$cantitate_afisat;

          $pret_initial = (float)$row['pret_cu_tva'];
          $pret_vanzare = (float)$row['pret_vanzare'];
          $valoare_totala = number_format((float)$row['valoare_vanzare_cu_tva'], 2, '.', '');

          $status_class = '';
          if ($row['t_list'] == 0) $status_class = 'status-new';
          elseif ($row['t_list'] == 1 && $row['preluat_osp'] == 0) $status_class = 'status-sent';
          elseif ($row['t_list'] == 1 && $row['preluat_osp'] == 1) $status_class = 'status-collected';

          $prioritate = (int)$row['prioritate'];
          $prioritate_text = "Fel";
          $prioritate_class = "btn-secondary";
          if ($prioritate >= 1 && $prioritate <= 3) {
            $prioritate_text  = "FEL " . $prioritate;
            $prioritate_class = ["btn-info", "btn-primary", "btn-success"][$prioritate-1];
          }
        ?>
        <div class="receipt-item <?= $status_class ?>">
          <div class="product-info">
            <p class="name"><?= $produs ?></p>
            <?php if ($observatie): ?><p class="obs">Obs: <?= $observatie ?></p><?php endif; ?>
            <p class="price-details">
              <?= $cantitate_afisat ?> x <?= number_format($pret_vanzare, 2) ?> =
              <strong><?= $valoare_totala ?> RON</strong>
                          <?php if (!$hide_discount_buttons): ?>

              <?php if ($pret_vanzare != $pret_initial): ?>
                <span class="price-original"><?= number_format($pret_initial, 2) ?></span>
              <?php endif; ?>
              <?php endif; ?>

            </p>
          </div>

          <div class="item-actions">
            <?php if ($row['t_list'] == 0): ?>
              <button class="btn btn-danger sterge_prod" value="<?= $id_vanz ?>" data-value="<?= $nr_bon ?>" type="button">
                <i class="fas fa-trash"></i> Șterge
              </button>
            <?php else: ?>
              <div class="btn btn-light disabled" style="cursor:not-allowed;"><i class="fas fa-check"></i> Trimis</div>
            <?php endif; ?>

            <button class="btn btn-secondary obs" name="<?= $id_vanz ?>" title="Adaugă observație">
              <i class="fas fa-comment-alt"></i> Obs.
            </button>

            <button class="btn btn-info muta-nota" data-idvanz="<?= $id_vanz ?>" title="Mută pe altă notă">
              <i class="fas fa-random"></i> Mută
            </button>

            <?php if (!$hide_discount_buttons): ?>
<button class="btn btn-warning discount" name="<?= $id_vanz ?>" value="<?= $cod_prod ?>" title="Aplică discount">
  <i class="fas fa-percentage"></i> %
</button>
<?php endif; ?>

            <button type="button" class="btn <?= $prioritate_class ?> btn-prioritate" data-idvanz="<?= $id_vanz ?>" data-current-priority="<?= $prioritate ?>">
              <i class="fas fa-stream"></i> <?= $prioritate_text ?>
            </button>
            <?php if (!$hide_discount_buttons): ?>

            <?php if ($pret_vanzare != $pret_initial): ?>
              <button class='btn btn-dark reset-pret' data-id-vanz='<?= $id_vanz ?>' data-pret-initial='<?= $pret_initial ?>'>
                <i class="fas fa-undo"></i> Pret
              </button>
            <?php endif; ?>
            <?php endif; ?>

          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- butoanele micuțe de scroll pentru listă -->
  <div class="receipt-scroll-buttons">
    <button id="r-scroll-up"   type="button" class="r-scroll" title="Sus"><i class="fas fa-chevron-up"></i></button>
    <button id="r-scroll-down" type="button" class="r-scroll" title="Jos"><i class="fas fa-chevron-down"></i></button>
  </div>
</div>

<!-- FOOTER TOTAL -->
<div class="receipt-footer">
  <?php
    $total_sql = "SELECT SUM(valoare_vanzare_cu_tva) AS total FROM $tabel_final_det_note WHERE nr_bon = :nr_bon";
    $total_stmt = $pdo->prepare($total_sql);
    $total_stmt->execute([':nr_bon' => $nr_bon]);
    $total_val_vz_cu_tva = $total_stmt->fetchColumn() ?: 0;
  ?>
  <form id='plata' method='POST' onsubmit="document.getElementById('loading').style.display='flex'">
    <div class="total-container">
      <div>
<?php if (!$hide_discount_buttons): ?>
<button type="button" class="btn btn-sm btn-warning" data-toggle="modal" data-target="#DiscountGlobal">
  Discount Global
</button>
<?php endif; ?>
        <input id="cif_client" form="plata" value="<?= htmlspecialchars($_SESSION['cif_client'] ?? '') ?>" type="text" maxlength="10" name="cif_client" placeholder="CIF Client">
      </div>
      <h3 class="total-text" id="total_cu_tva" data-total-cu-tva="<?= number_format($total_val_vz_cu_tva, 2, '.', '') ?>">
        <?= number_format($total_val_vz_cu_tva, 2, '.', '') ?> RON
      </h3>
    </div>
    <input type="hidden" name="total" id="total" value="<?= number_format($total_val_vz_cu_tva, 2, '.', '') ?>">
    <input type="hidden" name="masa_curenta" value="<?= htmlspecialchars($m_n ?? '') ?>">
  </form>
</div>

<?php
// semnal pentru „produse nelistate”
$check_sql = "SELECT COUNT(*) AS cnt FROM det_note WHERE nr_bon = :nr_bon AND t_list = 0";
$check_stmt = $pdo->prepare($check_sql);
$check_stmt->execute([':nr_bon' => $nr_bon]);
$row = $check_stmt->fetch(PDO::FETCH_ASSOC);
if ($row['cnt'] > 0) {
  echo "<script>$(function(){ $('#butontrimitecomanda').css('background-color','green'); });</script>";
}
?>

<script>
// butoanele micuțe de scroll (sus/jos) pentru lista din stânga
(function(){
  var $list = $('.receipt-items');
  function smooth(delta) {
    $list.stop(true).animate({scrollTop: $list.scrollTop() + delta}, 250);
  }
  $(document).on('click', '#r-scroll-up',   function(){ smooth(-300); });
  $(document).on('click', '#r-scroll-down', function(){ smooth( 300); });

  // mic tuning 4:3 – font icon puțin mai mic
  $('#r-scroll-up i, #r-scroll-down i').css('font-size','1rem');
})();
</script>

<script>
// (păstrat restul comportamentelor tale — doar cleanup min.)
$(document).ready(function() {
  $('.discount').on('click', function() {
    $('#Discount').modal('show');
    $('[name=prod_discount]').val($(this).val());
    $('[name=cota_calc_tva]').val(this.getAttribute("data-value"));
    $('[name=idvanzare]').val(this.getAttribute("name"));
  });

  $('.modif_cant').on('click', function() {
    $('#Cantitate').modal('show');
    $('[name=produs_modif_cant]').val($(this).val());
    $('[name=cantitate_noua],[name=cantitate_veche]').val(this.getAttribute("data-value"));
    $('[name=idvz]').val(this.getAttribute("name"));
  });

  $('#plata_numerar_si_card').on('click', function(){ $('#Plata_numerar_si_card').modal('show'); });
  $('#plata_tichete').on('click', function(){ $('#Plata_tichete').modal('show'); });
  $('#produse_vandute').on('click', function(){ $('#Prod_vandute').modal('show'); });
  $('#amanate').on('click', function(){ $('#Amanate').modal('show'); });
  $('#relistare').on('click', function(){ $('#Relistare').modal('show'); });

  $('.masa_bon').on('focusout', function(){
    if ((this.value||'') == '0') { $('#setare_masa').modal('show'); }
  }).trigger('focusout');

  $('#setare_masa').modal({ backdrop: 'static', keyboard: true, show: false });

  $('.confirm-preluat-osp').on('click', function(){
    var idvanz = $(this).attr('name');
    if (confirm("Esti sigur ca vrei sa starea produsului în preluat de către ospătar?")) {
      $.post('vanzare_update_preluat_osp.php',{ idvanz:idvanz })
       .done(function(){ $("#one").load("afis_prod.php?" + $.param({ bonul: "<?= $nr_bon ?>", cod_masa: "<?= $m_n ?>"})); })
       .fail(function(){ alert("A aparut o eroare la actualizare."); });
    }
  });

  $('.muta-nota').on('click', function(){
    var idvanz = $(this).data('idvanz');
    $('#mutaNotaIdVanz,#mutaNotaIdVanzRetur').val(idvanz);
    $.getJSON('vanzare_check_t_list.php',{ idvanz:idvanz }).done(function(r){
      if(r.t_list == 1) $("#buton_retur").html('<button type="submit" class="btn btn-primary btn-block">Trimite-l în retur</button>');
      else $("#buton_retur").empty();
    });
    $('#MutaNotaModal').modal('show');
  });
});
</script>
