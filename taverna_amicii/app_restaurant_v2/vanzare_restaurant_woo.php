<?php
declare(strict_types=1);

// Wrapper foarte mic pentru instalarea Taverna Amicii.
// Nu modifica logica vanzarii: afiseaza exact vanzare_restaurant.php si adauga
// doar shortcut-ul + badge-ul WooCommerce care sunt ascunse de codul vechi in SQLite.
ob_start();
require __DIR__.'/vanzare_restaurant.php';
$html=(string)ob_get_clean();

if(strpos($html,'id="wooImportTile"')===false){
    $logout='<form method="POST" action="logout.php" class="quick-action-form m-0">';
    $tile='<a href="vanzare_importa_comanda_woo.php" id="wooImportTile" class="quick-action-tile btn btn-outline-secondary fit-grid-text woo-import-tile" title="Import Comenzi Site"><span>Import Comenzi Site</span><span id="wooImportBadge" class="woo-import-badge" aria-label="Comenzi Woo noi" style="display:none">0</span></a>';
    $html=preg_replace('/<form\s+method="POST"\s+action="logout\.php"\s+class="quick-action-form m-0">/',$tile.$logout,$html,1)??$html;
}

$script=<<<'HTML'
<script>
(function($){
  if(!$) return;
  var lastIds=[];
  function renderWooOfflineBadge(){
    $.ajax({url:'woo_check_comenzi_noi.php',method:'GET',dataType:'json',cache:false})
      .done(function(r){
        if(!r||r.success!==true) return;
        var count=parseInt(r.count,10)||0;
        var ids=Array.isArray(r.ids)?r.ids:[];
        var $badge=$('#wooImportBadge');
        if(!$badge.length) return;
        $badge.text(count);
        if(count>0){$badge.show();$('#wooImportTile').addClass('woo-has-new-orders');}else{$badge.hide();$('#wooImportTile').removeClass('woo-has-new-orders');}
        lastIds=ids;
      });
  }
  $(function(){renderWooOfflineBadge();setInterval(renderWooOfflineBadge,20000);});
})(window.jQuery);
</script>
HTML;

if(stripos($html,'</body>')!==false){$html=preg_replace('/<\/body>/i',$script.'</body>',$html,1)??($html.$script);}else{$html.=$script;}
echo $html;
