<?php //vanzare_javascript.php ?>
<script> 


    // ── Funcție încărcare garnituri în modalul de observație ──
    function loadGarnituri(id_vanz) {
      var $container = $('#garnituri_container');
      var $buttons = $('#garnituri_buttons');
      $container.hide();
      $buttons.empty();

      $.getJSON('ajax_get_garnituri.php', { id_vanz: id_vanz }, function(data) {
        if (data && data.length > 0) {
          data.forEach(function(g) {
            var btn = $('<button type="button" class="btn btn-outline-secondary btn-garnitura"></button>')
              .text(g.nume)
              .css({
                'padding': '12px 8px',
                'font-size': '14px',
                'font-weight': '600',
                'border-radius': '8px',
                'text-transform': 'uppercase',
                'letter-spacing': '0.5px',
                'word-break': 'break-word'
              })
              .attr('data-denumire', g.nume)
              .attr('title', g.nume);
            $buttons.append(btn);
          });
          $container.show();
        }
      });
    }
  // ===== Reîncărcare centralizată panou stâng din afis_prod.php =====
  function loadAfisProd(nrBon, codMasa) {
    try {
      nrBon   = nrBon   || <?php echo json_encode($_SESSION['nr_bon'] ?? 0); ?>;
      codMasa = codMasa || <?php echo json_encode($_SESSION['masa_curenta'] ?? 0); ?>;
      $("#one").load("afis_prod.php?" + $.param({ bonul: nrBon, cod_masa: codMasa }), function () {
        // după ce s-a reîncărcat panoul stâng, sincronizează și butoanele de plată
        $("#metode_plata").load("vanzare_metode_plata.php?" + $.param({ nr_bon: nrBon }));
      });
    } catch (e) {
      console.error('loadAfisProd error:', e);
    }
  }
  // Încarcă inițial panoul la intrarea în pagină
  $(function(){ loadAfisProd(); });
</script>
<script>
  // Auto-logout după 15 minute de inactivitate — neschimbat
  (function(){
    function keepAlive(){ $.post('keep_alive.php'); }
    keepAlive();
    setInterval(keepAlive, 300000);
  })();
  // Siguranță pentru elemente opționale + pornire modale
  $(function(){
    var afiseaza_modal = <?php echo json_encode($afiseaza_modal); ?>;
    if (afiseaza_modal && $.fn.modal) { $('#setare_masa').modal('show'); }
    var searchInput = document.getElementById('searchMasa');
    if (searchInput) {
      searchInput.addEventListener('keyup', function(){ /* extensibil */ });
    }
  });
  // Camere/nota
  $(document).on('click', '#camera_nota_plata', function () { $('#modalCameraNota').modal('show'); });
  // Diverse modale + discount/obs/prioritate etc. — TOT codul tău original, nemodificat
  $(function(){
    $(document).on('click','.discount',function(){
      $('#Discount').modal('show');
      $('[name=prod_discount]').val($(this).val());
      $('[name=cota_calc_tva]').val(this.getAttribute('data-value'));
      $('[name=idvanzare]').val(this.getAttribute('name'));
    });
   $(document).on('click','.obs',function(){
      var idVanzare = $(this).attr('name');
      $('#idvanzare_obs').val(idVanzare);
      $('#observatie_produsInput').val('');
      // Încarcă garniturile specifice acestui produs
      loadGarnituri(idVanzare);
      $('#observatie_produsModal').modal('show');
    });
    $('#plata_tichete').on('click', ()=>$('#Plata_tichete').modal('show'));
    $('#bacsis').on('click', ()=>$('#Bacsis').modal('show'));
    $('#inchide_bratara').on('click', ()=>$('#inchide_bratara_modal').modal('show'));
    $('#relistare').on('click', ()=>$('#Relistare').modal('show'));
function togglePayButtons() {
    // citim valoarea din atributul data-total-cu-tva
    var val = parseFloat($('#total_cu_tva').data('total-cu-tva') || '0');
    var disabled = !(val > 0);
    $('#plata_numerar,#plata_card,#plata_numerar_si_card,#plata_tichete,#plata_protocol,#plata_glovo,#trimite_comanda_bar_buc,#nota_informativa_de_plata')
        .prop('disabled', disabled);
}
// rulează imediat la încărcare
togglePayButtons();

    $('.masa_bon').on('focusout', function(){
      if ((this.value||'') == '0') { $('#setare_masa').modal('show'); }
    }).trigger('focusout');
$('#setare_masa').modal({ backdrop:'static', keyboard: false, show:false });    
  });
  // Confirm preluat ospătar
  $(document).on('click','.confirm-preluat-osp',function(){
    var idvanz = $(this).attr('name');
    if(confirm("Esti sigur ca vrei sa setezi produsul ca preluat de către ospătar?")){
      $.post('vanzare_update_preluat_osp.php',{idvanz:idvanz})
       .done(()=>location.reload())
       .fail(()=>alert("A apărut o eroare la actualizare."));
    }
  });
  // Mută nota
  $(document).on('click','.muta-nota',function(){
    var idvanz = $(this).data('idvanz');
    $('#mutaNotaIdVanz, #mutaNotaIdVanzRetur').val(idvanz);
    $.getJSON('vanzare_check_t_list.php',{idvanz:idvanz})
      .done(function(r){
        if(r.t_list == 1){
          $("#buton_retur").html('<button type="submit" class="btn btn-primary btn-block">Trimite-l în retur</button>');
        } else {
          $("#buton_retur").empty();
        }
      })
      .always(()=>$('#MutaNotaModal').modal('show'));
  });
  // Dubla confirmare pe client 1
  $(document).on('click','.confirmable',function(e){
    var $btn = $(this);
    if (!$btn.data('confirmed')) {
      e.preventDefault();
      $btn.data('confirmed', true);
      var $overlay = $('<div class="confirm-overlay">Confirma</div>');
      $btn.css('position','relative').append($overlay);
      setTimeout(function(){
        $overlay.fadeOut(300,function(){ $(this).remove(); $btn.removeData('confirmed'); });
      }, 3000);
    }
  });
  // Căutare masă în modal setare masă (fallback)
  document.addEventListener('DOMContentLoaded', function() {
    var searchInput = document.getElementById('searchMasa');
    if (searchInput) {
      searchInput.addEventListener('keyup', function() {
        var query = this.value.trim().toLowerCase();
        var items = document.querySelectorAll('#masaContainer .masa-item');
        items.forEach(function(item) {
          var itemText = item.textContent.toLowerCase();
          item.style.display = (itemText.indexOf(query) !== -1) ? "" : "none";
        });
      });
    }
  });
  // Ștergere produs
  $(document).on('click','.sterge_prod',function(){
    var idvanz = $(this).val();
    var bon    = $(this).data('value');
var masa = <?php echo isset($m_n) ? json_encode($m_n) : 'null'; ?>;
    $("#test2").load("sterge_prod.php?"+$.param({id_vanz:idvanz,nr_bon:bon}), function(){
      loadAfisProd(bon, masa);
    });
  });
  // Culori Trimite comanda dacă există nelistate
<?php
    // Adaugă o clasă specială pe butonul de trimitere dacă există produse nelistate
    $check_stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM det_note WHERE nr_bon = :nr_bon AND t_list = 0 AND cod_p != -1");
    $check_stmt->execute([':nr_bon' => $nr_bon_curent]);
    if ((int)$check_stmt->fetchColumn() > 0) {
        echo "$(function(){ $('#trimite_comanda_bar_buc').addClass('has-pending-items'); });";
    }
?>
  // Reset preț la prețul inițial
  $(document).on('click','.reset-pret',function(){
    const idVanz = $(this).data('id-vanz');
    const pretInitial = $(this).data('pret-initial');
    fetch('vanzare_reset_pret.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: 'id_vanz='+encodeURIComponent(idVanz)+'&pret_vanzare='+encodeURIComponent(pretInitial)
    })
    .then(r=>r.json())
    .then(d=>{
      if(d.success){
        loadAfisProd();
      } else {
        alert('Eroare la resetarea prețului: '+(d.message||''));
      }
    })
    .catch(()=>alert('Eroare de rețea.'));
  });
  // Poll pentru status bon (F) clienți 14/8
  <?php if (isset($_SESSION['nr_bon']) && in_array($_SESSION['client_id'] ?? 0, [14,8])): ?>
  setInterval(function(){
    $.getJSON('vanzare_check_status.php',{nr_bon: <?php echo json_encode($_SESSION['nr_bon']); ?>})
      .done(function(r){
        if (r.status === 'F') {
          $.post('vanzare_unset_session.php').always(function(){ location.reload(); });
        }
      });
  }, 3000);
  <?php endif; ?>

    // Scanner automat pentru BonANSWER → verifică și mută fișierele în bonuri_fisco_verificate
  (function(){
    function autoScanBonFisco(){
      $.ajax({
        url: 'auto_scan_bon_fiscalizare.php',
        method: 'POST',
        dataType: 'json'
      }).done(function(r){
        // îl lăsăm discret, doar log în consolă pentru debug
        if (!r || r.ok === false) {
          if (window.console && console.warn) {
            console.warn('auto_scan_bon_fiscalizare: ', r && r.msg ? r.msg : 'eroare generică');
          }
        } else {
          if (window.console && console.log) {
            console.log(
              '[scanner BonANSWER] scanned=', r.scanned,
              ' processed=', (r.processed ? r.processed.length : 0),
              ' updated=', r.updatedCount,
              ' moved=', r.movedCount
            );
          }
          // Dacă vrei, poți forța refresh pe panel stânga când s-a actualizat ceva:
          // if (r.updatedCount > 0) { loadAfisProd(); }
        }
      }).fail(function(){
        if (window.console && console.error) {
          console.error('auto_scan_bon_fiscalizare: AJAX fail');
        }
      });
    }

    // prima pornire, puțin după încărcarea paginii
    setTimeout(autoScanBonFisco, 3000);
    // apoi la fiecare 10 secunde
    setInterval(autoScanBonFisco, 10000);
  })();

  // Ajutoare listare detalii bon
  $(function(){
    $('#notaSelectF').on('change', function(){
      var nrbon = $(this).val();
      if (!nrbon) { $('#detNoteDetailsF').html(''); return; }
      $.getJSON('get_det_note.php',{nrbon:nrbon, tip:'F'}).done(function(data){
        renderDetalii('#detNoteDetailsF', data);
      }).fail(function(_, __, e){ $('#detNoteDetailsF').html('<p>Eroare: '+e+'</p>'); });
    });
    $('#notaSelectS').on('change', function(){
      var nrbon = $(this).val();
      if (!nrbon) { $('#detNoteDetailsS').html(''); return; }
      $.getJSON('get_det_note.php',{nrbon:nrbon, tip:'S'}).done(function(data){
        renderDetalii('#detNoteDetailsS', data);
      }).fail(function(_, __, e){ $('#detNoteDetailsS').html('<p>Eroare: '+e+'</p>'); });
    });
    function renderDetalii(sel, data){
      var html='';
      if (data.error) html = '<p>Eroare: '+data.error+'</p>';
      else if (!data.length) html = '<p>Nu există detalii pentru acest bon.</p>';
      else {
        html += '<table class="table table-bordered"><thead><tr><th>Produs</th><th>Cantitate</th><th>Valoare</th><th>Ora</th></tr></thead><tbody>';
        $.each(data,function(_,row){
          html += '<tr><td>'+row.nume_produs+'</td><td>'+row.cantitate+'</td><td>'+row.valoare_vanzare_cu_tva+'</td><td>'+row.ora+'</td></tr>';
        });
        html += '</tbody></table>';
      }
      $(sel).html(html);
    }
  });
  // Proxy către butoanele din modale
  $(function(){
    $('#trimite_comanda_bar_buc').on('click', function(){ $('#trimite_produsele_noi').trigger('click'); });
    $('#nota_informativa_de_plata').on('click', function(){ $('#nota_de_plata_client').trigger('click'); });
    $('#amanare_bon').on('click', function(e){ e.preventDefault(); $('#setare_masa').modal('show'); });
  });
  // Focus când se deschide setare masă
  $('#setare_masa').on('shown.bs.modal', function(){ $('#searchInput').trigger('focus'); });
  // Sesiune validă pe ultim_bon_conectat — logică ta păstrată
  <?php
  if (isset($_SESSION['nr_bon']) && (!isset($_SESSION['no_session_validation']) || $_SESSION['no_session_validation'] != 1)) {
    echo "
    (function(){
      $.post('keep_alive.php'); // keep alive inițial
    })();";
    $bonRow = restaurantFetchUltimBonConectat($pdo, (int)$_SESSION['cod_locatie']);
    if ($bonRow && $bonRow['nr_bon'] != $_SESSION['nr_bon']) {
      $_SESSION['error'] = 'Te rugăm să te conectezi din nou.';
      header('Location: logout.php');
      exit;
    }
  }
  ?>
  // Ascunderi pe brățări — neschimbat
  <?php
  if (isset($_SESSION['masa_curenta'], $_SESSION['client_id'], $_SESSION['cod_locatie'])
      && $_SESSION['client_id'] == 9 && $_SESSION['cod_locatie'] == 2) {
    $stmt = $pdo->prepare("SELECT tip_masa, vandut_intrare FROM mese WHERE cod_masa = ?");
    $stmt->execute([$_SESSION['masa_curenta']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && $row['tip_masa'] === 'bratara') {
      $vandutIntrare = (int)$row['vandut_intrare'];
      $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM det_note WHERE nr_bon = ? AND (nume_produs LIKE '%INTRAR%' OR nume_produs LIKE '%ABONAM%')");
      $stmt2->execute([$_SESSION['nr_bon']]);
      $areIntrare = (int)$stmt2->fetchColumn();
      if ($areIntrare === 0 && $vandutIntrare === 0) {
        echo "$('.despre_prod').filter(function(){return $(this).data('den_categ') !== 'ABONAM INTRARI';}).closest('.col-md-6').hide();";
      }
    }
  }
  if (isset($_SESSION['masa_curenta'], $_SESSION['client_id'], $_SESSION['cod_locatie'])
      && $_SESSION['client_id'] == 9 && $_SESSION['cod_locatie'] == 1) {
    $stmt = $pdo->prepare("SELECT tip_masa FROM mese WHERE cod_masa = ?");
    $stmt->execute([$_SESSION['masa_curenta']]);
    $tm = strtolower($stmt->fetchColumn() ?: '');
    if ($tm === 'simpla') {
      echo "$('.despre_prod').filter(function(){const c=($(this).data('den_categ')||'').toUpperCase();return c.includes('ABONAM')||c.includes('INTRAR');}).closest('.col-md-6').hide();";
    }
  }
  ?>
  // Tabs bootstrap fallback & keep alive
  $(function(){
    var tabs = document.querySelectorAll('.nav-tabs .nav-link');
    tabs.forEach(function(tab){
      tab.addEventListener('click', function(e){
        e.preventDefault();
        tabs.forEach(function(t){ t.classList.remove('active'); });
        this.classList.add('active');
        var panes = document.querySelectorAll('.tab-pane');
        panes.forEach(function(p){ p.classList.remove('show','active'); });
        var target = document.querySelector(this.getAttribute('href'));
        if (target) target.classList.add('show','active');
      });
    });
    function keepSessionAlive(){ $.post('keep_alive.php').fail(function(){ console.error('Eroare keep-alive'); }); }
    setInterval(keepSessionAlive, 300000);
  });
  // Filtru produse (fără caching, fără barcode) — neschimbat
  function normalize(str){
    return (str||'').toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g,"").replace(/\s+/g,' ').trim();
  }
  $('#prod_filter').on('input',function(){
    const q = normalize(this.value);
    $('#product-list-container .product-card').each(function(){
      const txt = normalize($(this).data('search') || $(this).text());
      $(this).toggle(txt.indexOf(q) > -1);
    });
  });
  // UI listă produse + încărcare (FĂRĂ cache JS)
  $(function(){
    const nrBon   = '<?php echo $_SESSION['nr_bon']        ?? 0; ?>';
    const codMasa = '<?php echo $_SESSION['masa_curenta']   ?? 0; ?>';
    const loadFile= "load_prod_restaurant.php";
    function updateScrollButtons(){
      const $ct = $('#category-tabs');
      $('#scroll-cat-left').prop('disabled', $ct.scrollLeft() <= 0);
      $('#scroll-cat-right').prop('disabled', $ct.scrollLeft() >= $ct[0].scrollWidth - $ct[0].clientWidth - 1);
      const $pl = $('#product-list-container');
      $('#scroll-prod-up').prop('disabled', $pl.scrollTop() <= 0);
      $('#scroll-prod-down').prop('disabled', $pl.scrollTop() >= $pl[0].scrollHeight - $pl[0].clientHeight - 1);
    }
    $('#scroll-cat-left').on('click', ()=>$('#category-tabs').animate({scrollLeft:'-=350'},300,updateScrollButtons));
    $('#scroll-cat-right').on('click',()=>$('#category-tabs').animate({scrollLeft:'+=350'},300,updateScrollButtons));
    $('#scroll-prod-up').on('click',  ()=>$('#product-list-container').animate({scrollTop:'-=400'},300,updateScrollButtons));
    $('#scroll-prod-down').on('click',()=>$('#product-list-container').animate({scrollTop:'+=400'},300,updateScrollButtons));
    $('#category-tabs').on('scroll', updateScrollButtons);
    $('#product-list-container').on('scroll', updateScrollButtons);
    function loadProducts(cat){
      $('#product-list-container').html('<div class="text-center p-4"><i class="fas fa-spinner fa-spin fa-3x"></i></div>');
      $('#prod_filter').val('');
      $.get(loadFile, {categ:cat}, function(res){
        $('#product-list-container').html(res);
        updateScrollButtons();
      });
    }
    $(document).on('click','.category-tab-btn', function(){
      $('.category-tab-btn').removeClass('active');
      $(this).addClass('active');
      loadProducts($(this).data('value'));
    });

    // ——— adăugare produs → reîncarcă panoul prin loadAfisProd ———
    // AICI ESTE MODIFICAREA PENTRU OBSERVATII (fără regex, parsing DOM)
  $(document).on('click', '.adaug_prod:not(.disabled)', function () {
      const codP = $(this).attr('value');
      const cant = $('#cantitate_de_adaugat_prod').val() || 1;
      
      // Definim askObs exact aici, la click pe produs
      const askObs = $(this).data('ask-obs'); 

      $.get('vanzare_adaug_prod_pe_nota.php',
        { prod: codP, bonul: nrBon, cod_masa: codMasa, cantitate_de_adaugat_prod: cant },
        function (data) {
          loadAfisProd(nrBon, codMasa);
          $('#cantitate_de_adaugat_prod').val(1);

          // Verificăm dacă produsul cere observații
          if (askObs == 1) {
            var $response = $('<div>').html(data);
            var idVanzare = $response.find('#last_inserted_id_server').val();
            
            if (idVanzare) {
               $('#idvanzare_obs').val(idVanzare);
               $('#observatie_produsInput').val('');
               
               // Trimitem ID-ul către funcție
               loadGarnituri(idVanzare);
               
               $('#observatie_produsModal').modal('show');
               setTimeout(function(){ $('#observatie_produsInput').trigger('focus'); }, 500);
            }
          }
        });
    });

    // Prevent text selection/drag on desktop browsers that ignore CSS in some cases
    $(document)
      .on('selectstart dragstart', '.product-card', function (e) { e.preventDefault(); });

    // If you kept <div role="button"> instead of <button>, also handle keyboard:
    $(document).on('keydown', '.product-card[role="button"]', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        $(this).trigger('click');
      }
    });


    // ── Click pe buton garnitură → completează câmpul observație ──
    $(document).on('click', '.btn-garnitura', function() {
      var denumire = $(this).data('denumire');
      $('#observatie_produsInput').val(denumire);
      // Evidențiază butonul selectat
      $('.btn-garnitura').removeClass('btn-primary').addClass('btn-outline-secondary');
      $(this).removeClass('btn-outline-secondary').addClass('btn-primary');
    });

    loadProducts('all');
    setTimeout(updateScrollButtons,500);
  });
  // Reîncărcare panou stânga + update prioritate
  $(function(){
    const bon  = <?php echo json_encode($_SESSION['nr_bon'] ?? 0); ?>;
    const masa = <?php echo json_encode($_SESSION['masa_curenta'] ?? 0); ?>;
    // încărcarea inițială a panoului se face deja în loadAfisProd() global
    $(document).on('click','.btn-prioritate', function(e){
      e.preventDefault();
      const button = $(this);
      $.ajax({
        url: 'vanzare_update_prioritate.php',
        type: 'POST',
        data: { id_vanz: button.data('idvanz'), current_priority: button.data('current-priority') },
        dataType: 'json'
      }).done(function(r){
        if (r.success){
          loadAfisProd(bon, masa);
        } else {
          alert('Eroare: '+(r.message||''));
        }
      }).fail(function(){ alert('A apărut o eroare de comunicare.'); });
    });
  });
  // Trimiterea centralizată a plății — păstrat
  (function(){
  if (window.__finalizBonHandlerAttached) return;
  window.__finalizBonHandlerAttached = true;

  function postToProcesare(methodValue){
    try{
      var form = document.createElement('form');
      form.method = 'POST';
      form.action = 'vanzare_procesare_finalizare_bon.php';

      function add(name, value){
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = (value == null) ? '' : String(value);
        form.appendChild(input);
      }

      add('finaliz_bon', methodValue);

      // === caz special: PLATĂ MIXTĂ — citește STRICT din modal ===
      if (methodValue === 'numerar_si_card') {
        var num = function(v){ v=(v||'').toString().replace(/[^\d.,-]/g,'').replace(',', '.'); var n=parseFloat(v); return isNaN(n)?'':(Math.round(n*100)/100).toFixed(2); };

        var numerarEl = document.getElementById('mixt_numerar');
        var cardEl    = document.getElementById('mixt_card');
        var cifMEl    = document.getElementById('cif_client_m') || document.getElementById('cif_client');

        // valori normalizate cu punct
        var numerarVal = num(numerarEl ? numerarEl.value : '');
        var cardVal    = num(cardEl    ? cardEl.value    : '');
        var cifVal     = (cifMEl && cifMEl.value) ? cifMEl.value.trim() : '';

        add('numerar', numerarVal);
        add('card',    cardVal);
        add('cif_client_m', cifVal);

        // trimitem și masa (dacă există în pagină)
        var masaEl = document.querySelector('[name="masa_curenta"]');
        if (masaEl) add('masa_curenta', masaEl.value);

      } else {
        // === comportamentul tău existent pentru restul metodelor ===
        ['masa_curenta','cif_client','cif_client_m','cif_client_t',
         'card','numerar','numerarprim','rest_numerar',
         'total_tichete','rest_de_incasat','rest_de_returnat'
        ].forEach(function(n){
          var el = document.querySelector('[name="'+n+'"]');
          if (el) add(n, el.value);
        });

        var speciale = ['protocol','virament_bancar_separat_fara_casa_marcat','glovo'];
        if (speciale.indexOf(methodValue) !== -1 && !form.querySelector('[name="numerarprim"]')) {
          var totalEl = document.querySelector('#total_cu_tva,[data-total-cu-tva],[data-total]');
          if (totalEl) {
            var raw = (totalEl.value || totalEl.textContent || '').replace(/[^\d.,-]/g,'');
            if (raw) add('numerarprim', raw);
          }
        }
      }

      document.body.appendChild(form);
      form.submit();
    } catch(err){
      console.error('Eroare trimitere finalizare bon:', err);
      alert('A apărut o eroare la trimiterea plății. Reîncearcă.');
    }
  }

    document.addEventListener('click', function(e){
    var t = e.target;
    var trigger = null;
    var method = null;

    if (t && t.closest) {
      trigger = t.closest('button[name="finaliz_bon"],input[name="finaliz_bon"],[data-finaliz-bon],[data-metoda]');
    }

    // ❗ NU tratăm butonul "Plată Mixtă" (doar deschide modalul)
    if (trigger && trigger.id === 'plata_numerar_si_card') {
      return; // lăsăm jQuery să facă treaba (deschiderea modalului)
    }

    // 1) caz clasic: <button name="finaliz_bon" value="numerar">
    if (trigger && trigger.getAttribute('name') === 'finaliz_bon') {
      method = trigger.value || trigger.getAttribute('value');
    }

    // 2) caz: <button data-finaliz-bon="numerar_si_card"> (ex: #btn_finalizare_mixta)
    if (!method && trigger && trigger.hasAttribute('data-finaliz-bon')) {
      method = trigger.getAttribute('data-finaliz-bon');
    }

    // 3) fallback: <element data-metoda="...">
    if (!method && trigger && trigger.dataset && trigger.dataset.metoda) {
      method = trigger.dataset.metoda;
    }

    // 4) mapare după ID, DAR **fără** plata_numerar_si_card
    if (!method && t && t.id) {
      var mapIds = {
        // butoanele principale
        'plata_numerar'         : 'numerar',
        'plata_card'            : 'card',
        'plata_protocol'        : 'protocol',
        'plata_glovo'           : 'glovo',
        'plata_virament_bancar' : 'virament_bancar_separat_fara_casa_marcat',
        'platit_din_sold'       : 'platit_din_sold',

        // păstrăm și vechile ID-uri, dacă mai există prin alte modale
        'btn_plata_numerar'      : 'numerar',
        'btn_plata_card'         : 'card',
        'btn_plata_mixta'        : 'numerar_si_card',
        'btn_plata_tichete'      : 'tichete_de_masa',
        'btn_plata_protocol'     : 'protocol',
        'btn_plata_virament'     : 'virament_bancar_separat_fara_casa_marcat',
        'btn_plata_glovo'        : 'glovo',
        'btn_plata_platit_din_sold' : 'platit_din_sold'
      };
      if (mapIds[t.id]) {
        method = mapIds[t.id];
      }
    }

    if (method) {
      e.preventDefault();
      e.stopPropagation();
      postToProcesare(method);
    }
  }, true);


  
})();

</script>

<script>
(function ($) {
  if (!$) return;

  var CHECK_URL = 'woo_check_comenzi_noi.php';
  var POLL_MS = 60000;

  var TILE_SELECTOR = '#wooImportTile';
  var BADGE_SELECTOR = '#wooImportBadge';
  var AUDIO_SELECTOR = '#wooNewOrderAudio';

  var storagePrefix = 'woo_notif_' + <?= json_encode((string)($_SESSION['client_id'] ?? '0') . '_' . (string)($_SESSION['cod_locatie'] ?? '0')) ?>;
  var STORAGE_NOTIFIED_IDS = storagePrefix + '_notified_ids';
  var STORAGE_AUDIO_ARMED = storagePrefix + '_audio_armed';

  var inFlight = false;
  var audioArmed = false;

  function readStoredArray(key) {
    try {
      var raw = localStorage.getItem(key);
      var parsed = raw ? JSON.parse(raw) : [];
      return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
      return [];
    }
  }

  function writeStoredArray(key, arr) {
    try {
      var seen = {};
      var clean = [];

      (arr || []).forEach(function (value) {
        value = String(value || '').trim();
        if (!value || seen[value]) return;
        seen[value] = true;
        clean.push(value);
      });

      if (clean.length > 300) {
        clean = clean.slice(clean.length - 300);
      }

      localStorage.setItem(key, JSON.stringify(clean));
    } catch (e) {}
  }

  function setAudioArmed(value) {
    audioArmed = !!value;
    try {
      localStorage.setItem(STORAGE_AUDIO_ARMED, audioArmed ? '1' : '0');
    } catch (e) {}
  }

  function restoreAudioArmed() {
    try {
      audioArmed = localStorage.getItem(STORAGE_AUDIO_ARMED) === '1';
    } catch (e) {
      audioArmed = false;
    }
  }

  function updateWooTile(count) {
    var $tile = $(TILE_SELECTOR);
    var $badge = $(BADGE_SELECTOR);

    if (!$tile.length || !$badge.length) return;

    if (count > 0) {
      $tile.addClass('has-woo-new is-pulsing');
      $badge.text(count > 99 ? '99+' : String(count)).show();
    } else {
      $tile.removeClass('has-woo-new is-pulsing');
      $badge.text('0').hide();
    }
  }

  var repeatAudioTimer = null;
  var hasPendingWooOrders = false;

  function playWooAudioOnce() {
    var audio = document.querySelector(AUDIO_SELECTOR);
    if (!audio || !audioArmed) return;

    try {
      audio.pause();
      audio.currentTime = 0;
      var playPromise = audio.play();
      if (playPromise && typeof playPromise.catch === 'function') {
        playPromise.catch(function () {});
      }
    } catch (e) {}
  }

  function startWooAudioRepeater() {
    if (!audioArmed) return;
    if (repeatAudioTimer) return;

    playWooAudioOnce();

    repeatAudioTimer = setInterval(function () {
      if (!hasPendingWooOrders) {
        stopWooAudioRepeater();
        return;
      }
      playWooAudioOnce();
    }, 30000);
  }

  function stopWooAudioRepeater() {
    var audio = document.querySelector(AUDIO_SELECTOR);

    if (repeatAudioTimer) {
      clearInterval(repeatAudioTimer);
      repeatAudioTimer = null;
    }

    if (audio) {
      try {
        audio.pause();
        audio.currentTime = 0;
      } catch (e) {}
    }
  }

  function markIdsAsSeen(ids) {
    var existing = readStoredArray(STORAGE_NOTIFIED_IDS);
    writeStoredArray(STORAGE_NOTIFIED_IDS, existing.concat((ids || []).map(String)));
  }

   function handleIncomingIds(ids, hasNewOrders) {
    ids = Array.isArray(ids) ? ids.map(function (v) {
      return String(parseInt(v, 10) || '');
    }).filter(Boolean) : [];

    if (ids.length) {
      var seen = readStoredArray(STORAGE_NOTIFIED_IDS);
      var seenMap = {};
      seen.forEach(function (id) {
        seenMap[String(id)] = true;
      });

      var newIds = ids.filter(function (id) {
        return !seenMap[id];
      });

      if (newIds.length > 0) {
        markIdsAsSeen(newIds);
      }
    }

    hasPendingWooOrders = !!hasNewOrders;

    if (hasPendingWooOrders) {
      startWooAudioRepeater();
    } else {
      stopWooAudioRepeater();
    }
  }

  function pollWooOrders() {
    if (inFlight) return;
    inFlight = true;

    $.ajax({
      url: CHECK_URL,
      method: 'GET',
      dataType: 'json',
      cache: false
        }).done(function (response) {
      if (!response || response.success !== true) return;

      var count = parseInt(response.count, 10) || 0;
      var ids = Array.isArray(response.ids) ? response.ids : [];

      updateWooTile(count);
      handleIncomingIds(ids, count > 0);
    }).fail(function () {
      // păstrăm ultima stare cunoscută; nu stingem notificarea la o eroare temporară
      if (window.console && console.warn) {
        console.warn('woo_check_comenzi_noi.php - eroare la polling');
      }
    }).always(function () {
      inFlight = false;
    });
  }

  function armWooAudioSilently() {
    if (audioArmed) return;

    var audio = document.querySelector(AUDIO_SELECTOR);
    if (!audio) {
      setAudioArmed(true);
      if (hasPendingWooOrders) {
        startWooAudioRepeater();
      }
      return;
    }

    try {
      audio.muted = true;
      var p = audio.play();

      if (p && typeof p.then === 'function') {
        p.then(function () {
          try {
            audio.pause();
            audio.currentTime = 0;
            audio.muted = false;
          } catch (e) {}

          setAudioArmed(true);

          if (hasPendingWooOrders) {
            startWooAudioRepeater();
          }
        }).catch(function () {
          try { audio.muted = false; } catch (e) {}
        });

        return;
      }
    } catch (e) {}

    try { audio.muted = false; } catch (e) {}
  }

  // Armare audio pe interacțiuni din fluxul de lucru cu mesele
  $(document).on('click', '#amanare_bon', armWooAudioSilently);

  // butoanele de mese deja deschise din header
  $(document).on('click', '.tablinks', armWooAudioSilently);

  // fallback pentru elemente din modalul de setare masă:
  // acoperă majoritatea implementărilor unde masa e un buton/card/link
  $(document).on('click', '#setare_masa .masaCard, #setare_masa .masa-item, #setare_masa button, #setare_masa a', function () {
    armWooAudioSilently();
  });

    $(function () {
    restoreAudioArmed();
    updateWooTile(0);
    pollWooOrders();
    setInterval(pollWooOrders, POLL_MS);
  });

  window.addEventListener('beforeunload', function () {
    hasPendingWooOrders = false;
    stopWooAudioRepeater();
  });
})(window.jQuery);
</script>

<?php include('vanzare_modal_setare_masa.php'); ?>
<?php include('vanzare_modal_autentificare_operator.php'); ?>
<script>
  // Tastatură numerică parolă
  $(function(){
    $('.keypad-btn').on('click', function(){
      var d = $(this).data('digit');
      $('#confirmPassword').val($('#confirmPassword').val()+d);
    });
    $('#keypad-backspace').on('click', function(){
      var cur = $('#confirmPassword').val();
      $('#confirmPassword').val(cur.slice(0,-1));
    });
    $('#submitPassword').on('click', function(){
      var pass = $('#confirmPassword').val();
      $.ajax({ url:'vanzare_check_password.php', type:'POST', data:{password:pass}, dataType:'json' })
      .done(function(r){
        if (r.success) $("#turaContainer").load("vanzare_inchide_tura_section.php");
        else alert("Parola incorectă! Vă rugăm să încercați din nou.");
      }).fail(function(){ alert("Eroare la verificarea parolei. Încercați din nou."); });
    });
  });
  // Tabs simple în modal setare masă
  function openTab(evt, tabName){
    var i, tabcontent = document.getElementsByClassName("tabcontent"),
            tablinks  = document.getElementsByClassName("tablink");
    for (i=0;i<tabcontent.length;i++){ tabcontent[i].style.display="none"; }
    for (i=0;i<tablinks.length;i++){ tablinks[i].classList.remove('btn-secondary'); tablinks[i].classList.add('btn-light'); }
    var el = document.getElementById(tabName);
    if (el) el.style.display="block";
    if (evt && evt.currentTarget) { evt.currentTarget.classList.remove('btn-light'); evt.currentTarget.classList.add('btn-secondary'); }
    document.getElementById('searchInput').value="";
    filterTables();
  }
  function filterTables(){
    var input = document.getElementById('searchInput');
    var filter = (input.value || '').toUpperCase();
    var activeTab = document.querySelector('.tabcontent[style*="display: block"]');
    if (!activeTab) return;
    var cards = activeTab.getElementsByClassName('masaCard');
    for (var i=0;i<cards.length;i++){
      var txt = cards[i].textContent || cards[i].innerText;
      cards[i].style.display = (txt.toUpperCase().indexOf(filter) > -1) ? "" : "none";
    }
  }
/* FIX 1024×768: reducere ușoară a delay-urilor de scroll pentru senzație mai „snappy” pe touch */
$('#scroll-prod-up, #scroll-prod-down').attr('data-speed','300');
// === Lock de închidere pentru setare_masa ===
window.__canCloseSetareMasa = false;

$(function(){
  $('#setare_masa').on('hide.bs.modal', function(e){
    // Blochează *orice* încercare de a închide, dacă nu s-a acordat permisiune explicit
    if (!window.__canCloseSetareMasa) {
      e.preventDefault();
      e.stopImmediatePropagation();
      return false;
    }
  });
});

</script>

<script>
  // Scroll vertical în gridul de mese din modal
  $(function(){
    function updateMasaScrollButtons(){
      var $c = $('#masaGridScroll');
      if (!$c.length) return;
      $('#scroll-mese-up').prop('disabled', $c.scrollTop() <= 0);
      $('#scroll-mese-down').prop('disabled', $c.scrollTop() >= $c[0].scrollHeight - $c[0].clientHeight - 1);
    }
    $(document).on('click', '#scroll-mese-up',  function(){ $('#masaGridScroll').animate({scrollTop: '-=400'}, 300, updateMasaScrollButtons); });
    $(document).on('click', '#scroll-mese-down',function(){ $('#masaGridScroll').animate({scrollTop: '+=400'}, 300, updateMasaScrollButtons); });
    $(document).on('shown.bs.modal', '#setare_masa', updateMasaScrollButtons);
    $(document).on('scroll', '#masaGridScroll', updateMasaScrollButtons);
  });
</script>
<script>
(function(){
  var initDone = false;

  function updateMasaScrollButtons(){
    var c = document.getElementById('masaGridScroll');
    if (!c) return;
    var canUp   = c.scrollTop > 0;
    var canDown = (c.scrollTop + c.clientHeight) < (c.scrollHeight - 1);
    $('#scroll-mese-up').prop('disabled', !canUp);
    $('#scroll-mese-down').prop('disabled', !canDown);
  }

  function bindMasaScroll(){
    if (initDone) return;
    initDone = true;

    $(document).on('click', '#scroll-mese-up', function(){
      var $c = $('#masaGridScroll');
      $c.stop(true).animate({ scrollTop: Math.max(0, $c.scrollTop() - 400) }, 250, updateMasaScrollButtons);
    });

    $(document).on('click', '#scroll-mese-down', function(){
      var $c = $('#masaGridScroll');
      var max = $c[0].scrollHeight - $c[0].clientHeight;
      $c.stop(true).animate({ scrollTop: Math.min(max, $c.scrollTop() + 400) }, 250, updateMasaScrollButtons);
    });

    $(document).on('scroll', '#masaGridScroll', updateMasaScrollButtons);

    // când deschizi modalul sau schimbi tab-ul, recalculăm
    $(document).on('shown.bs.modal', '#setare_masa', function(){
      setTimeout(updateMasaScrollButtons, 50);
    });

    // dacă tab-urile se schimbă cu openTab(...), apelează și update:
    window.openTab = (function(orig){
      return function(evt, tabName){
        if (orig) orig(evt, tabName);
        setTimeout(function(){
          var c = document.getElementById('masaGridScroll');
          if (c) c.scrollTop = 0; // optional: urcă sus pe tab nou
          updateMasaScrollButtons();
        }, 0);
      };
    })(window.openTab || null);
  }

  // pornește la ready
  $(function(){
    bindMasaScroll();
    setTimeout(updateMasaScrollButtons, 150);
  });
})();
</script>

<script>
  (function(){
    function scrollActions(by){
      var c = document.querySelector('.actions-scroll');
      if (!c) return;
      c.scrollBy({ top: by, left: 0, behavior: 'smooth' });
    }
    document.addEventListener('click', function(e){
      if (e.target.closest('#scroll-actions-up'))   scrollActions(-350);
      if (e.target.closest('#scroll-actions-down')) scrollActions( 350);
    });
  })();
</script>
<script>
(function(){
  function num(v){ v=(v||'').toString().replace(/[^\d.,-]/g,'').replace(',', '.'); var n=parseFloat(v); return isNaN(n)?0:n; }
  function fix2(n){ return Math.max(0, Math.round(n*100)/100); }
  function clamp(v, min, max){ return Math.min(Math.max(v,min),max); }
  function totalBon(){
    var $h = $('#total_cu_tva');
    var t = num($h.data('total-cu-tva')); if(!t) t = num($h.text());
    return fix2(t);
  }

  // deschidere modal + precompletări
  $(document).on('click','#plata_numerar_si_card',function(e){
    e.preventDefault();
    var total = totalBon();
    if (total<=0){ alert('Totalul este 0.'); return; }

    $('#mixt_total').val(total.toFixed(2));
    $('#mixt_numerar').attr({max:total}).val(total.toFixed(2));
    $('#mixt_card').attr({max:total}).val('0.00');

    // precompletăm CIF din sesiune (dacă există)
    var cifSess = <?= json_encode($_SESSION['cif_client'] ?? '') ?>;
    $('#cif_client').val(cifSess);
    $('#cif_client_m').val(cifSess); // mirror pt. PHP

    $('#PlataNumerarCard').modal('show');
  });

  // numerar -> card
  $(document).on('input change','#mixt_numerar',function(){
    var total = num($('#mixt_total').val());
    var numerar = clamp(num(this.value),0,total);
    this.value = numerar.toFixed(2);
    $('#mixt_card').val(fix2(total-numerar).toFixed(2));
  });

  // card -> numerar
  $(document).on('input change','#mixt_card',function(){
    var total = num($('#mixt_total').val());
    var card = clamp(num(this.value),0,total);
    this.value = card.toFixed(2);
    $('#mixt_numerar').val(fix2(total-card).toFixed(2));
  });

  // CIF vizibil -> mirror hidden (cif_client_m) pe măsură ce tastezi
  $(document).on('input change','#cif_client', function(){
    $('#cif_client_m').val(this.value);
  });

  // înainte de a lăsa handlerul global să trimită, forțăm corectitudinea sumei
  $(document).on('click','#btn_finalizare_mixta', function(){
    var total   = num($('#mixt_total').val());
    var numerar = num($('#mixt_numerar').val());
    var card    = num($('#mixt_card').val());
    var sum     = fix2(numerar + card);
    if (sum !== total) {
      $('#mixt_card').val(fix2(total - numerar).toFixed(2));
    }
    // NU oprim propagarea — document listener va vedea data-finaliz-bon="numerar_si_card"
    // și va apela postToProcesare(...) care citește [name="numerar"], [name="card"], [name="cif_client_m"] etc.
  });
})();
</script>
<script>
$(function () {
  // De fiecare dată când se deschide modalul, încarcă dinamic conținutul.
  $('#sume_sertar').on('show.bs.modal', function () {
    var $target = $('#sume_sertar_content');
    $target.html('<div class="py-3 text-center"><i class="fas fa-spinner fa-spin"></i></div>');
    // cache-buster ca fallback, pe lângă antetele no-store din PHP
    $target.load('sume_sertar_partial.php?ts=' + Date.now(), function (resp, status) {
      if (status !== 'success') {
        $target.html('<div class="alert alert-danger m-0">Nu s-au putut încărca sumele. Încearcă din nou.</div>');
      }
    });
  });

  // (opțional) Curăță la închidere
  $('#sume_sertar').on('hidden.bs.modal', function () {
    $('#sume_sertar_content').empty();
  });
});
</script>
<script>
$(function(){
  function loadBonuriFisco(){
    var limit = $('#bf_limit').val() || 50;
    $('#bonuri_fisco_content').html('<div class="py-5 text-center"><i class="fas fa-spinner fa-spin fa-2x"></i></div>');
    $('#bonuri_fisco_content').load('bonuri_fisco_partial.php?limit='+encodeURIComponent(limit)+'&ts='+Date.now(), function(resp, status){
      if (status !== 'success') {
        $('#bonuri_fisco_content').html('<div class="alert alert-danger m-3">Nu am putut încărca bonurile. Verifică existența folderului <code>api/bonuri_procesate_fisco/<?= htmlspecialchars($_SESSION['client_id'] ?? "", ENT_QUOTES) ?>/<?= htmlspecialchars($_SESSION["cod_locatie"] ?? "", ENT_QUOTES) ?></code>.</div>');
      }
    });
  }
  $('#modalBonuriFisco').on('show.bs.modal', loadBonuriFisco);
  $(document).on('change', '#bf_limit', loadBonuriFisco);
  $(document).on('click',  '#bf_refresh', loadBonuriFisco);
});
</script>
<script>
  // Scanner automat pentru BonOK/inp
  // rulează auto_scan_bonok_inp.php la fiecare 10 secunde
  (function(){
    function autoScanBonOKInp(){
      $.ajax({
        url: 'auto_scan_bonok_inp.php',
        method: 'POST',
        dataType: 'json'
      }).done(function(r){
        if (window.console && console.log) {
          console.log(
            '[scanner BonOK/inp] scanned=',  r && typeof r.scanned !== 'undefined' ? r.scanned : '?',
            ' moved=',  r && typeof r.movedCount !== 'undefined' ? r.movedCount : '?'
          );
          // Dacă vrei să vezi lista detaliată:
          // console.log('[scanner BonOK/inp] processed =', r.processed);
        }
      }).fail(function(){
        if (window.console && console.error) {
          console.error('[scanner BonOK/inp] AJAX fail');
        }
      });
    }

    // Prima rulare după câteva secunde de la încărcarea paginii
    setTimeout(autoScanBonOKInp, 7000);

    // Apoi la fiecare 10 secunde
    setInterval(autoScanBonOKInp, 10000);
  })();
</script>
