<?php // meniu_vanzare_widget.php - doar carduri, fără inputuri locale ?>

<div id="meniu-panel" style="display:none;">
  <div id="meniu-grid" class="product-grid"></div>
  <div class="text-center my-3" id="meniu-loading" style="display:none;">
    <i class="fas fa-spinner fa-spin fa-2x"></i>
  </div>
</div>

<script>
// Așteaptă până există jQuery (evită "$ is not defined")
(function onJQReady(fn){ if (window.jQuery) fn(window.jQuery); else setTimeout(()=>onJQReady(fn), 30); })(function($){

  const WRAPPER    = $('.product-grid-wrapper');       // container cu scroll
  const SCROLLER   = $('.product-scroll-buttons');     // bara verticală cu săgeți
  const PROD_WRAP  = $('#product-list-container');     // gridul clasic
  const PANEL      = $('#meniu-panel');
  const GRID       = $('#meniu-grid');
  const LOADING    = $('#meniu-loading');
  const NAME_INPUT = $('#prod_filter');                // căutarea globală
  const QTY_INPUT  = $('#cantitate_de_adaugat_prod');  // cantitatea globală

  const clientId = '<?php echo $_SESSION["client_id"] ?? ""; ?>';
  const nrBon    = '<?php echo isset($nr_bon)?$nr_bon:""; ?>';
  const codMasa  = '<?php echo isset($cod_masa)?$cod_masa:""; ?>';

  // Asigură poziția corectă: înainte de bara de scroll verticală
  if (!PANEL.parent().is(WRAPPER)) {
    SCROLLER.before(PANEL);
  }
  // Panoul se va întinde ca gridul standard
  PANEL.css({ flex: '1 1 auto' });

  let MENIU_ACTIVE = false;
  let isLoading    = false;
  let noMore       = false;
  let page         = 1;
  const limit      = 40;
  let searchTerm   = '';

  function currentSearch() { return (NAME_INPUT.val() || '').trim(); }

  function showPanel() {
    MENIU_ACTIVE = true;
    PROD_WRAP.hide();
    PANEL.css('display','flex'); // IMPORTANT: flex, nu block
    resetAndLoad();
  }

  function hidePanel() {
    MENIU_ACTIVE = false;
    PANEL.hide();
    GRID.empty();
    PROD_WRAP.show();
  }

  function resetAndLoad() {
    page = 1; noMore = false;
    GRID.empty();
    loadMeniuri(1, false, searchTerm || currentSearch());
  }

  function loadMeniuri(p = 1, append = false, q = '') {
    if (isLoading || (noMore && append)) return;
    isLoading = true;
    LOADING.show();

    $.get('ajax_meniuri_list.php', { page: p, limit: limit, search: q }, function(html) {
      if (!append) GRID.empty();
      if (html && html.trim()) {
        append ? GRID.append(html) : GRID.html(html);
        page = p;
      } else {
        if (append) noMore = true;
        if (!append) GRID.html('<p class="text-muted p-4">Nu sunt produse.</p>');
      }
    })
    .always(function(){ isLoading = false; LOADING.hide(); });
  }

  // Adăugare meniu => actualizează UI + (opțional) refresh bonul
  function addMeniu(cod_meniu) {
    const raw = (QTY_INPUT.val() || '').trim();
    const qty = raw === '' ? 1 : Math.max(0.00001, parseFloat(raw) || 1);

    $.ajax({
      url: 'vanzare_adaug_meniu_pe_nota.php',
      type: 'POST',
      dataType: 'json',
      data: { cod_meniu: cod_meniu, nr_bon: nrBon, cod_masa: codMasa, qty_meniuri: qty },
      success: function(resp) {
        if (resp && (resp.added || resp.updated)) {
          if (typeof processAddProductResponse === 'function') { processAddProductResponse(resp); }
          if (typeof reloadBonPanel === 'function') { reloadBonPanel(); } // <- refresh complet
          // reset cantitate + focus ca la produse
          if (String(clientId) === '6') { QTY_INPUT.val(''); } else { QTY_INPUT.val(1); }
          if (['18','21','22'].includes(String(clientId))) {
            $('#prod_filter_cod_bare').focus().select();
          } else {
            NAME_INPUT.focus();
          }
        } else if (resp && resp.error) {
          alert(resp.error);
        }
      },
      error: function(xhr) { alert('Eroare la adăugarea meniului: ' + (xhr.responseText || '')); }
    });
  }

  // Curăță orice legări vechi (în caz că a mai fost inclus o dată)
  $(document).off('.meniuwidget');

  // Delegări cu namespace (evită dublări)
  $(document)
    .on('click.meniuwidget',  '.adaug_meniu', function(){ addMeniu($(this).data('cod-meniu')); })
    .on('keydown.meniuwidget','.adaug_meniu', function(e){ if (e.key === 'Enter') $(this).click(); })
    .on('click.meniuwidget',  '.category-tab-btn', function() {
      const val = $(this).data('value');
      if (val === '__MENIURI__') showPanel(); else hidePanel();
    });

  // Căutare globală când MENIURI e activ
  let SEARCH_TIMER = null;
  NAME_INPUT.off('keyup.meniu').on('keyup.meniu', function(e) {
    if (!MENIU_ACTIVE) return;
    if (['Control','Shift','Enter','CapsLock','/'].includes(e.key)) return;
    clearTimeout(SEARCH_TIMER);
    SEARCH_TIMER = setTimeout(function(){
      searchTerm = currentSearch();
      resetAndLoad();
    }, 300);
  });

  // Infinite scroll pe același wrapper
  WRAPPER.off('scroll.meniu').on('scroll.meniu', function() {
    if (!MENIU_ACTIVE || isLoading || noMore) return;
    const el = this;
    if (el.scrollTop + el.clientHeight >= el.scrollHeight - 250) {
      loadMeniuri(page + 1, true, currentSearch());
    }
  });

  // API expus
  window.__MENIU_WIDGET__ = {
    show: showPanel,
    hide: hidePanel,
    reload: function(q){ searchTerm = (q || '').trim(); resetAndLoad(); }
  };
});
</script>
