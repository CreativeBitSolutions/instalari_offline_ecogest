<?php
// vanzare_restaurant.php (stil modernizat, FĂRĂ caching ca în magazin)
include('session.php');
include('vanzare_init.php');

$offlineMode = function_exists('restaurantIsOfflineSqlite') && restaurantIsOfflineSqlite();
$wooNotifAudioSrc = $offlineMode ? '' : 'woo_comanda_noua.mpeg';
$tabletPendingCount = 0;


if ($offlineMode) {
  try {
    $tabletCountStmt = $pdo->prepare("SELECT COUNT(*) FROM com_tableta WHERE stare='TRIMISA' AND owner_operator_id=? AND locatie=?");
    $tabletCountStmt->execute([(int)($_SESSION['admin_id'] ?? 0), (int)($_SESSION['cod_locatie'] ?? 0)]);
    $tabletPendingCount = (int)$tabletCountStmt->fetchColumn();
  } catch (Throwable $tabletCountError) {
    $tabletPendingCount = 0;
  }
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>Vânzare Restaurant</title>

<!-- CSS de bază -->
<link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="vendor/font-awesome/css/font-awesome.min.css">
<link rel="stylesheet" href="vanzare_css.css">
<style>.fas,.far{display:inline-block;font:normal normal normal 14px/1 FontAwesome;text-rendering:auto;-webkit-font-smoothing:antialiased}</style>
<?php if ((int)($_SESSION['client_id'] ?? 0) === 26): ?>
<style>
  .page-header .flex-grow-1 {
    background-color: #ffc107 !important;

  }
</style>
<?php endif; ?>

<script>
// Deschide “Setare Masă” sigur, după ce s-au încărcat toate scripturile
window.addEventListener('load', function () {
  var afiseaza_modal = <?php echo json_encode($afiseaza_modal || isset($_GET['deschide_mese'])); ?>;
  if (!afiseaza_modal) return;
  if (window.jQuery && $.fn && $.fn.modal) {
    $('#setare_masa').modal({ backdrop: 'static', keyboard: false }).modal('show');
  }
});
</script>
<script src="js/offline-persistent-zoom.js"></script>
    <?php include __DIR__ . '/i18n/i18n_bootstrap.php'; ?>
</head>
<body>
<div id="loading"></div>
<div id="test2"></div>

<!-- JS de bază -->
<script src="js/jquery-3.6.0.min.js"></script>
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

<script>
(function($){
  if (!$ || !$.fn || !$.fn.modal) return;

  // 1) Stivuire corectă (poți păstra codul tău existent care setează z-index)
  $(document).on('show.bs.modal', '.modal', function () {
    var $open = $('.modal.show');
    var z = 2050 + ($open.length * 20);
    $(this).css('z-index', z);
    setTimeout(function(){
      $('.modal-backdrop').not('.modal-stack').css('z-index', z - 5).addClass('modal-stack');
    }, 0);
  });

  // 2) NU închide modalele deja deschise când intră tastatura
  $(document).on('show.bs.modal', '.modal', function () {
    var isTK = $(this).is('#TouchKeyboardNumeric, #TouchKeyboardAlpha');
    if (isTK) return; // nu închide nimic când deschizi tastatura

    // pentru restul modalelor, comportamentul tău: închide ce nu e necesar
    $('.modal.show').not(this).each(function(){
      if (this.id === 'setare_masa') return; // excepție ta existentă
      if ($(this).is('#TouchKeyboardNumeric, #TouchKeyboardAlpha')) return; // păstrează tastaturile dacă sunt deja deschise
      $(this).modal('hide');
    });
  });

  // 3) Dacă un alt script totuși îți închide părintele, îl redeschidem când se închide tastatura
  var __tkParentId = null;
  $(document).on('show.bs.modal', '#TouchKeyboardNumeric, #TouchKeyboardAlpha', function(){
    __tkParentId = $('.modal.show').not('#TouchKeyboardNumeric, #TouchKeyboardAlpha').last().attr('id') || null;
    $('body').addClass('modal-open'); // păstrează scroll-ul blocat
  });

  $(document).on('hidden.bs.modal', '#TouchKeyboardNumeric, #TouchKeyboardAlpha', function(){
    if (__tkParentId && !$('#'+__tkParentId).hasClass('show')) {
      setTimeout(function(){ $('#'+__tkParentId).modal('show'); __tkParentId=null; }, 50);
    } else {
      __tkParentId = null;
    }
  });

  // 4) Dacă se închide părintele, închide și tastatura (ca să nu rămână orfană)
  $(document).on('hide.bs.modal', '.modal', function(){
    if ($(this).is('#TouchKeyboardNumeric, #TouchKeyboardAlpha')) return;
    if ($('.modal.show').filter('#TouchKeyboardNumeric, #TouchKeyboardAlpha').length){
      $('#TouchKeyboardNumeric,#TouchKeyboardAlpha').modal('hide');
    }
  });

  // 5) Păstrează body .modal-open dacă mai e vreun modal deschis
  $(document).on('hidden.bs.modal', '.modal', function () {
    if ($('.modal.show').length) $('body').addClass('modal-open');
  });
})(jQuery);
</script>

<!-- Scriptul UNIC pentru "Verifica Erori Bonuri" (modal + verificare pe rând) -->
<script>
$(function(){
  // Deschide modalul și încarcă lista
  $('#btn_verifica_erori_bonuri').off('click.vb').on('click.vb', function(){
    $('#modalVerificaBonuri').modal('show');
    $('#modalVerificaBonuriBody')
      .html('<div class="py-5 text-center"><i class="fas fa-spinner fa-spin fa-2x"></i></div>')
      .load('note_fara_z_modal_body.php?limit=300&ts='+Date.now());
  });

  // Delegare: buton "Verifică" pe fiecare rând
  $(document).off('click.vb', '#modalVerificaBonuri .btn-verifica-bon')
             .on('click.vb',  '#modalVerificaBonuri .btn-verifica-bon', function(){
    var $btn = $(this);
    var nr   = parseInt($btn.data('nrbon'), 10) || 0;
    if (!nr) return;

    $btn.prop('disabled', true).text('Verific...');
    var $msg = $('#msg-'+nr);
    $msg.removeClass('text-danger text-success').text('');

    $.ajax({
      url: 'verify_bon_fiscalizare.php',
      method: 'POST',
      dataType: 'json',
      data: { nrbon: nr }
    }).done(function(r){
      if (!r || r.ok === false) {
        $msg.addClass('text-danger').text(r && r.msg ? r.msg : 'Eroare la verificare.');
        return;
      }
      if (r.errorCode === 0) {
        // success => marchează fiscalizat=1 în UI
        $('#pill-'+nr)
          .removeClass('status-0').addClass('status-1')
          .text('fiscalizat = 1');
        $msg.addClass('text-success').text(r.msg || 'Actualizat.');
      } else if (typeof r.errorCode === 'number') {
        $msg.addClass('text-danger').text('Eroare (ErrorCode='+r.errorCode+').');
      } else {
        $msg.addClass('text-danger').text(r.msg || 'Eroare necunoscută.');
      }
    }).fail(function(){
      $msg.addClass('text-danger').text('Eroare de rețea.');
    }).always(function(){
      $btn.prop('disabled', false).text('Verifică');
    });
  });
});
</script>


<!-- Scripturile tale existente -->
<script src="javascript.js"></script>
<script src="javascript_scroll.js"></script>

<div class="page-container">
  <!-- HEADER -->
  <header class="page-header">
    <div class="flex-grow-1">
      <div id="trimite_buc_bar"></div>
      <form method="POST" class="d-inline">
      <?php
      // Mese operator curent (note deschise)
      $mese_desch_sql = "SELECT n.nrbon,
                                n.cod_masa,
                                n.listat_nota_plata,
                                m.nume_masa,
                                EXISTS (
                                    SELECT 1
                                    FROM $tabel_final_det_note dn
                                    WHERE dn.nr_bon = n.nrbon
                                      AND dn.t_list = 0
                                ) AS are_produse_nelistate
                         FROM $tabel_final_note n
                         INNER JOIN mese m ON m.cod_masa = n.cod_masa
                         WHERE n.status='S'
                           AND n.operator=:op
                           AND n.locatie=:loc
                         ORDER BY n.nrbon ASC";
      $mese_desch_stmt = $pdo->prepare($mese_desch_sql);
      $mese_desch_stmt->execute([':op'=>$adm_id, ':loc'=>$cod_locatie]);
      while ($rrow = $mese_desch_stmt->fetch(PDO::FETCH_ASSOC)) {
        if (isset($rrow['cod_masa'], $rrow['nrbon'])) {
          $masa = $rrow['cod_masa'];
          $nume_masa = $rrow['nume_masa'];
          $bon_amanat = $rrow['nrbon'];
          $bon_am = strval($rrow['nrbon']) . 'BAM';
          // Culoarea se decide din acelasi rezultat, fara query-uri suplimentare in bucla.
          if ((int)($rrow['are_produse_nelistate'] ?? 0) > 0) {
            $colorStyle = "background-color:#f8d7da;color:#721c24;";
          } else {
            $listat_nota_plata = (int)($rrow['listat_nota_plata'] ?? 0);
            $colorStyle = ($listat_nota_plata === 1)
              ? "background-color:#d4edda;color:#155724;"
              : "background-color:#fff3cd;color:#856404;";
          }
          $buttonStyle = (isset($_SESSION['masa_curenta']) && $_SESSION['masa_curenta'] == $masa)
              ? "font-size:1rem;background-color:#0578F5;color:#fff;"
              : "font-size:1rem;".$colorStyle;
          echo "<button style='$buttonStyle;padding:.35rem .6rem;border-radius:6px;border:1px solid #999;margin-right:6px;' name='$bon_am' class='tablinks' type='submit'>".htmlspecialchars($nume_masa)."</button>";
          if (isset($_POST[$bon_am])) {
            $_SESSION['nr_bon'] = $bon_amanat;
            $_SESSION['masa_curenta'] = $masa;
            if (!isset($_SESSION['no_session_validation']) || $_SESSION['no_session_validation'] != 1) {
              $dateTime = new DateTime('now', new DateTimeZone('Europe/Bucharest'));
              $updateTime = $dateTime->format('Y-m-d H:i:s');
              restaurantTouchUltimBonConectat($pdo, (int)$_SESSION['cod_locatie'], (int)$_SESSION['nr_bon'], $updateTime);
            }
            echo "<script>location.href='vanzare_restaurant.php'</script>";
          }
        }
      }
      ?>
      </form>
    </div>
  </header>

  <!-- MAIN -->
  <main class="main-content">
    <!-- Stânga -->
    <div class="left-panel" id="one">
      <div class="p-4 text-center">
        <i class="fas fa-spinner fa-spin fa-2x"></i>
        <p>Se încarcă bonul...</p>
      </div>
    </div>

    <!-- Dreapta - Catalog -->
    <div class="right-panel" id="two">
      <div class="category-wrapper">
  <button id="scroll-cat-left" class="scroll-btn"><i class="fas fa-chevron-left"></i></button>

  <div id="category-tabs">
    <div class="category-columns">
      <?php
      $catSql = "SELECT c.id_categorie, c.den_categ
                 FROM categorii c
                 JOIN categorii_locatii cl ON cl.id_categorie = c.id_categorie
                 WHERE c.se_vinde = 1 AND cl.cod_locatie = :loc
                 ORDER BY c.den_categ ASC";
      $catStmt = $pdo->prepare($catSql);
      $catStmt->execute([':loc' => $cod_locatie]);

      $categorii = $catStmt->fetchAll(PDO::FETCH_ASSOC);

      $items = [];
      $items[] = [
          'value'  => 'all',
          'label'  => 'TOATE',
          'active' => true
      ];

      foreach ($categorii as $c) {
          $items[] = [
              'value'  => (int)$c['id_categorie'],
              'label'  => $c['den_categ'],
              'active' => false
          ];
      }

      $totalItems = count($items);
      $categoriiPeColoana = 3; // afișare pe 3 rânduri în fiecare coloană, fără scroll orizontal

      for ($i = 0; $i < $totalItems; $i += $categoriiPeColoana) {
          echo "<div class='category-col'>";

          for ($j = 0; $j < $categoriiPeColoana; $j++) {
              $idx = $i + $j;

              if (isset($items[$idx])) {
                  $item = $items[$idx];
                  $activeClass = !empty($item['active']) ? ' active' : '';

                  echo "<button class='category-tab-btn{$activeClass}' data-value='"
                      . htmlspecialchars((string)$item['value'], ENT_QUOTES, 'UTF-8')
                      . "'>"
                      . htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8')
                      . "</button>";
              } else {
                  echo "<span class='category-tab-btn category-tab-btn-placeholder' aria-hidden='true'></span>";
              }
          }

          echo "</div>";
      }
      ?>
    </div>
  </div>

  <button id="scroll-cat-right" class="scroll-btn"><i class="fas fa-chevron-right"></i></button>
</div>

      <div id="product-controls" class="p-3" style="border-bottom:1px solid #e9ecef;">
        <div class="form-row align-items-end">
          <div class="form-group col-md-8">
            <label class="mb-1 small font-weight-bold">Caută produs</label>
            <input type="text" id="prod_filter" autocomplete="off" class="form-control" placeholder="Tastează denumirea…">
          </div>
          <div class="form-group col-md-4">
            <label class="mb-1 small font-weight-bold">Cantitate</label>
            <input type="number" id="cantitate_de_adaugat_prod" step="0.001" min="0.001" value="1" class="form-control text-center">
          </div>
        </div>
      </div>

      <div class="product-grid-wrapper">
        <div id="product-list-container" class="product-grid"></div>
        <div class="product-scroll-buttons">
          <button id="scroll-prod-up" class="scroll-btn-v"><i class="fas fa-chevron-up"></i></button>
          <button id="scroll-prod-down" class="scroll-btn-v"><i class="fas fa-chevron-down"></i></button>
        </div>
      </div>
    </div>

    <!-- Panou acțiuni (dreapta) -->
    <div class="actions-panel" id="three">
      <!-- ✅ conținut scrollabil cu butoane sticky ↑/↓ -->  <div class="actions-scroll-buttons">
          <button id="scroll-actions-up" class="scroll-btn-v" type="button" title="Sus">
            <i class="fas fa-chevron-up"></i>
          </button>
          <button id="scroll-actions-down" class="scroll-btn-v" type="button" title="Jos">
            <i class="fas fa-chevron-down"></i>
          </button>
        </div>
      <div class="actions-scroll">
      

        <div class="header-actions">
          <button type="button" class="action-btn btn-primary" id="amanare_bon" data-toggle="modal" data-target="#setare_masa">
            <i class="fas fa-th"></i> Grilă mese / Comandă nouă
          </button>
        
          <?php
          if (!$offlineMode && isset($_SESSION['client_id']) && in_array($_SESSION['client_id'], [3, 8])) {
            echo "<a href='vanzare_facturi.php' class='action-btn btn-outline-secondary'>Facturi</a>";
          }
          ?>
        </div>

        <div class="payment-section">
          <div id="metode_plata" class="grup-stanga">
            <div class="text-center"><i class="fas fa-spinner fa-spin"></i></div>
          </div>
          <div class="grup-dreapta">
            <?php
              $client_agecs = $_SESSION['client_id'] ?? null;
              $visible = ($client_agecs == 8 || $client_agecs == 9) ? '' : 'hide';
              $camera_nota_text = 'Camera';
              if (isset($_SESSION['camera_nota'])) {
                  $camera_nota_text .= ' '.htmlspecialchars($_SESSION['camera_nota']);
              }
            ?>
            <button class="action-btn btn-secondary <?php echo $visible; ?>" id="camera_nota_plata">
              <?php echo $camera_nota_text; ?>
            </button>

            <button form="plata" type="submit" name="finaliz_bon" value="virament_bancar_separat_fara_casa_marcat" class="action-btn btn-secondary" id="plata_virament_bancar">🏦 Virament Bancar</button>

            <?php
              $stmt = $pdo->prepare("SELECT tip_masa FROM mese WHERE cod_masa = ?");
              $stmt->execute([$_SESSION['masa_curenta'] ?? null]);
              if ($stmt->fetchColumn() === "bratara") {
                echo '<button form="plata" type="submit" name="finaliz_bon" value="platit_din_sold" class="action-btn btn-primary" id="platit_din_sold">💰 Plată din sold</button>';
              }
            ?>

            <button class="action-btn btn-warning" id="bacsis" style="color:#212529;">💸 Bacșiș</button>
          </div>
        </div>
      </div>

      <!-- Footer fix jos (nu este împins de scrollul de mai sus) -->
     <div class="mt-auto">
    <div class="quick-actions-box mb-2">
        <a href="vanzare_imparte_nota_complex.php"
           class="quick-action-tile btn btn-outline-secondary fit-grid-text">
            <span>Împarte Nota</span>
        </a>

        <?php if ($offlineMode): ?>
        <a href="vanzare_importa_comanda_tableta.php"
           id="tabletImportTile"
           class="quick-action-tile btn btn-outline-secondary fit-grid-text woo-import-tile<?php echo $tabletPendingCount > 0 ? ' has-woo-new' : ''; ?>"
           title="Importă comenzile trimise online de pe tabletă">
            <span>Comenzi Tabletă</span>
            <span id="tabletImportBadge" class="woo-import-badge" aria-label="Comenzi tabletă noi"><?php echo $tabletPendingCount; ?></span>
        </a>
        <?php endif; ?>

        <a href="vanzare_restaurare_protocol.php"
           class="quick-action-tile btn btn-outline-secondary fit-grid-text">
            <span>Restaurare Protocol</span>
        </a>

        <?php if (in_array((int)($_SESSION['client_id'] ?? 0), [1008, 1021], true)): ?>
        <?php if (!empty($_SESSION['nr_bon']) && !empty($_SESSION['masa_curenta'])): ?>
        <button type="button"
                id="openTableSummary"
                class="quick-action-tile btn btn-outline-secondary fit-grid-text">
            <span><i class="fas fa-list-ul mr-1"></i> Total produse pe masă</span>
        </button>
        <?php endif; ?>

        <a href="vanzare_istoric_protocol.php"
           class="quick-action-tile btn btn-outline-secondary fit-grid-text">
            <span>Istoric Protocol</span>
        </a>
        <?php endif; ?>

        <button type="button"
                data-toggle="modal"
                data-target="#sume_sertar"
                class="quick-action-tile btn btn-outline-secondary fit-grid-text">
            <span>Sume încasate și de încasat</span>
        </button>

        <?php if (!$offlineMode): ?>
                <a href="vanzare_importa_comanda_woo.php"
           id="wooImportTile"
           class="quick-action-tile btn btn-outline-secondary fit-grid-text woo-import-tile"
           data-import-url="vanzare_importa_comanda_woo.php"
           title="Import Comenzi Site">
            <span>Import Comenzi Site</span>
            <span id="wooImportBadge" class="woo-import-badge" aria-label="Comenzi Woo noi">0</span>
        </a>
        <?php endif; ?>

        <form method="POST" action="logout.php" class="quick-action-form m-0">
            <button name="deconectare"
                    id="deconectare"
                    class="quick-action-tile btn btn-danger fit-grid-text"
                    type="submit">
                <span><i class="fas fa-sign-out-alt mr-1"></i> Deconectare</span>
            </button>
        </form>
    </div>

    <?php if (!$offlineMode): ?>
    <!-- Buton verificare erori din BonANSWER -->
    <button type="button"
            class="action-btn btn-outline-primary mb-2 hide"
            id="btn_verifica_erori_bonuri">
      Verifica Erori Bonuri
    </button>

    <!-- Buton deschidere modal Bonuri FISCO -->
    <button type="button" class="action-btn btn-outline-secondary mb-2 hide" data-toggle="modal" data-target="#modalBonuriFisco">
      Bonuri FISCO
    </button>
    <?php endif; ?>

        <div id="white_button" class="banner1 action-btn">
            <div><strong>Operator:</strong> <?php
              $ln = trim($_SESSION['admin_lastname'] ?? '');
              echo htmlspecialchars($_SESSION['admin_firstname'] ?? '');
              if ($ln !== '' && $ln !== '-') echo ' ' . htmlspecialchars($ln);
          ?></div>
          <div><strong>Nota:</strong> <?= $_SESSION['nr_bon'] ?? '' ?> <strong>Masa:</strong> <?php
              $stmt_nume_masa_curenta = $pdo->prepare("SELECT nume_masa FROM mese WHERE cod_masa = ?");
              $stmt_nume_masa_curenta->execute([$_SESSION['masa_curenta'] ?? null]);
              echo htmlspecialchars($stmt_nume_masa_curenta->fetchColumn() ?: 'N/A');
          ?></div>
          </div>
        </div>
      </div>
    </div>
  </main>

  <!-- Include-urile originale (modale) -->
  <?php include('vanzare_inchidere_tura.php'); ?>
  <?php include('vanzare_modal_muta_nota.php'); ?>
    <?php include('vanzare_modal_muta_masa.php'); ?>
  <?php include('vanzare_modal_camera_nota.php'); ?>
  <?php include('vanzare_modal_note_relistare.php'); ?>
  <?php include('vanzare_modal_bacsis.php'); ?>
  <?php include('vanzare_modal_inchide_bratara.php'); ?>
  <?php include('vanzare_modal_sume_sertar.php'); ?>
  <?php include('vanzare_modal_observatie.php'); ?>
  <?php include('vanzare_modal_discount_global.php'); ?>
  <?php include('vanzare_modal_discount.php'); ?>
  <?php include('vanzare_modal_plata_tichete.php'); ?>
  <?php include('vanzare_modal_listare_nota_imprimanta.php'); ?>
  <?php include('vanzare_modal_tastatura_cantitate.php'); ?>
  <?php include('vanzare_modal_raport_z.php'); ?>
  <?php include('vanzare_modal_cif_client_modal.php'); ?>
    <?php include('vanzare_modal_plata_mixta.php'); ?>
<?php if (!$offlineMode) include('vanzare_modal_bonuri_fisco.php'); ?>
<?php if (!$offlineMode) include('vanzare_modal_bonanswer_erori.php'); ?>

<?php if (in_array((int)($_SESSION['client_id'] ?? 0), [1008, 1021], true)): ?>
<div class="modal fade" id="tableSummaryModal" tabindex="-1" role="dialog" aria-labelledby="tableSummaryModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
    <div class="modal-content" style="border-radius:12px;overflow:hidden;">
      <div class="modal-header" style="background:#17232b;color:#fff;border-bottom:4px solid #f0b43c;">
        <div>
          <div class="small text-uppercase" style="color:#f0c766;letter-spacing:.08em;">Verificare comandă</div>
          <h5 class="modal-title mb-0" id="tableSummaryModalTitle">Total produse pe masă</h5>
          <div id="tableSummarySubtitle" class="small" style="color:#d8e0e5;"></div>
        </div>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Închide">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body p-0">
        <div id="tableSummaryLoading" class="text-center py-5">
          <i class="fas fa-spinner fa-spin fa-2x"></i>
          <div class="mt-2">Se calculează produsele...</div>
        </div>
        <div id="tableSummaryError" class="alert alert-danger m-3 d-none"></div>
        <div id="tableSummaryContent" class="d-none">
          <div id="tableSummaryProducts" style="max-height:58vh;overflow-y:auto;"></div>
          <div class="d-flex justify-content-between align-items-center px-3 py-3" style="background:#eef3f1;border-top:1px solid #ccd8d3;">
            <strong>Total masă</strong>
            <div class="text-right">
              <div id="tableSummaryQuantity" class="font-weight-bold"></div>
              <div id="tableSummaryValue" class="text-muted"></div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-dark btn-block" data-dismiss="modal">Înapoi la vânzare</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

  <?php if (!empty($wooNotifAudioSrc)): ?>
    <audio id="wooNewOrderAudio" preload="auto">
      <source src="<?= htmlspecialchars($wooNotifAudioSrc, ENT_QUOTES, 'UTF-8'); ?>" type="audio/mpeg">
    </audio>
  <?php endif; ?>

  <?php include('vanzare_javascript.php'); ?>

<?php
// ——— Tastaturi touch ———
// ( fiecare fișier include automat o singură dată tasta_core_touchkbd.php )

include 'tasta_val_procent_global_modal.php';
include 'tasta_valoare_fixa_global_modal.php';

include 'tasta_prod_filter_modal.php';
include 'tasta_cantitate_de_adaugat_prod_modal.php';

include 'tasta_val_procent_modal.php';
include 'tasta_val_fix_modal.php';

include 'tasta_val_procent_bacsis_modal.php';
include 'tasta_val_fix_bacsis_modal.php';

include 'tasta_observatie_produsInput_modal.php';

include 'tasta_mixt_numerar_modal.php';
include 'tasta_mixt_card_modal.php';
?>


</div>
<script>
(function () {
  function fitQuickActionText() {
    var tiles = document.querySelectorAll('.fit-grid-text');

    tiles.forEach(function(tile){
      var span = tile.querySelector('span') || tile;
      var maxFont = 15;
      var minFont = 10;

      span.style.fontSize = maxFont + 'px';

      while (
        maxFont > minFont &&
        (
          span.scrollHeight > span.clientHeight + 2 ||
          span.scrollWidth > span.clientWidth + 2 ||
          tile.scrollHeight > tile.clientHeight + 2
        )
      ) {
        maxFont -= 0.5;
        span.style.fontSize = maxFont + 'px';
      }
    });
  }

  window.addEventListener('load', fitQuickActionText);
  window.addEventListener('resize', fitQuickActionText);
  document.addEventListener('shown.bs.modal', fitQuickActionText);
})();
</script>
<?php if (in_array((int)($_SESSION['client_id'] ?? 0), [1008, 1021], true)): ?>
<script>
(function ($) {
  function escapeTableSummaryText(value) {
    return $('<div>').text(value == null ? '' : String(value)).html();
  }

  function formatTableSummaryQuantity(value) {
    var number = Number(value) || 0;
    return number.toLocaleString('ro-RO', {
      minimumFractionDigits: Number.isInteger(number) ? 0 : 3,
      maximumFractionDigits: 3
    });
  }

  function formatTableSummaryMoney(value) {
    return (Number(value) || 0).toLocaleString('ro-RO', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }) + ' LEI';
  }

  $(document).on('click', '#openTableSummary', function () {
    var $modal = $('#tableSummaryModal');
    $('#tableSummaryLoading').removeClass('d-none');
    $('#tableSummaryError').addClass('d-none').text('');
    $('#tableSummaryContent').addClass('d-none');
    $('#tableSummaryProducts').empty();
    $modal.modal('show');

    $.getJSON('afis_prod.php', { sumar_masa: 1, _: Date.now() })
      .done(function (response) {
        if (!response || response.ok !== true) {
          $('#tableSummaryError').removeClass('d-none').text(response && response.message ? response.message : 'Sumarul nu este disponibil.');
          return;
        }

        $('#tableSummarySubtitle').text(response.table_name + ' | Nota ' + response.note_id);
        var products = Array.isArray(response.products) ? response.products : [];
        if (products.length === 0) {
          $('#tableSummaryProducts').html('<div class="text-center text-muted py-5">Masa nu are produse adăugate.</div>');
        } else {
          products.forEach(function (product, index) {
            $('#tableSummaryProducts').append(
              '<div class="d-flex justify-content-between align-items-center px-3 py-3" style="border-bottom:1px solid #e2e7e5;' + (index % 2 ? 'background:#fafbfb;' : '') + '">' +
                '<div class="pr-3"><strong>' + escapeTableSummaryText(product.name) + '</strong></div>' +
                '<div class="text-right" style="min-width:145px;">' +
                  '<div class="font-weight-bold" style="font-size:1.12rem;">' + formatTableSummaryQuantity(product.quantity) + ' x</div>' +
                  '<div class="small text-muted">' + formatTableSummaryMoney(product.value) + '</div>' +
                '</div>' +
              '</div>'
            );
          });
        }
        $('#tableSummaryQuantity').text(formatTableSummaryQuantity(response.quantity_total) + ' produse');
        $('#tableSummaryValue').text(formatTableSummaryMoney(response.value_total));
        $('#tableSummaryContent').removeClass('d-none');
      })
      .fail(function (xhr) {
        var responseMessage = xhr && xhr.responseJSON && xhr.responseJSON.message
          ? xhr.responseJSON.message
          : 'Sumarul mesei nu a putut fi încărcat.';
        $('#tableSummaryError').removeClass('d-none').text(responseMessage);
      })
      .always(function () {
        $('#tableSummaryLoading').addClass('d-none');
      });
  });
})(jQuery);
</script>
<?php endif; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var qtyInput = document.getElementById('cantitate_de_adaugat_prod');
  if (!qtyInput) return;

  function clearIfDefault() {
    if (qtyInput.value === '1') {
      qtyInput.value = '';
    }
  }

  qtyInput.addEventListener('click', clearIfDefault);
  qtyInput.addEventListener('focus', clearIfDefault);

  qtyInput.addEventListener('blur', function () {
    if (qtyInput.value.trim() === '') {
      qtyInput.value = '1';
    }
  });
});

// Scroll pentru categorii (doar pe desktop, pe mobil se vede 1-2 categorii și se scroll-ează nativ)
$(function () {
  var $tabs = $('#category-tabs');

  function getCategoryScrollStep() {
    return Math.max(320, Math.floor($tabs.innerWidth() * 0.85));
  }

  $('#scroll-cat-left').off('click.catScroll').on('click.catScroll', function () {
    $tabs.stop().animate(
      { scrollLeft: $tabs.scrollLeft() - getCategoryScrollStep() },
      180
    );
  });

  $('#scroll-cat-right').off('click.catScroll').on('click.catScroll', function () {
    $tabs.stop().animate(
      { scrollLeft: $tabs.scrollLeft() + getCategoryScrollStep() },
      180
    );
  });
});
</script>

<script src="offline_sync_heartbeat.js"></script>
</body>
</html>
