<?php
declare(strict_types=1);

// Wrapper foarte mic pentru instalarea Taverna Amicii.
// Nu modifica logica vanzarii: afiseaza exact vanzare_restaurant.php si adauga
// doar shortcut-urile/notificarile care sunt necesare in instalarea SQLite offline.
ob_start();
require __DIR__.'/vanzare_restaurant.php';
$html=(string)ob_get_clean();

if(strpos($html,'id="wooImportTile"')===false){
    $logout='<form method="POST" action="logout.php" class="quick-action-form m-0">';
    $tile='<a href="vanzare_importa_comanda_woo.php" id="wooImportTile" class="quick-action-tile btn btn-outline-secondary fit-grid-text woo-import-tile" title="Import Comenzi Site"><span>Import Comenzi Site</span><span id="wooImportBadge" class="woo-import-badge" aria-label="Comenzi Woo noi" style="display:none">0</span></a>';
    $html=preg_replace('/<form\s+method="POST"\s+action="logout\.php"\s+class="quick-action-form m-0">/',$tile.$logout,$html,1)??$html;
}

// In offline, codul 2FA este citit/generat online prin endpointul dedicat.
// Interfata online AGECS ramane neschimbata si continua sa foloseasca propriul vanzare_tableta_2fa_api.php.
if(strpos($html,'id="operator2faCodeMain"')===false){
    $twoFaBlock='<div><strong>2FA:</strong> <span id="operator2faCodeMain">N/A</span> <button type="button" id="btnRegenerate2FAHeader" class="btn btn-sm btn-outline-primary ml-2">Genereaza cod nou</button></div>';
    $html=preg_replace('/(<div><strong>Nota:<\/strong>.*?<\/div>)/s','$1'.$twoFaBlock,$html,1)??$html;
}

$script=<<<'HTML'
<script>
(function($){
  if(!$) return;

  function renderWooOfflineBadge(){
    $.ajax({url:'woo_check_comenzi_noi.php',method:'GET',dataType:'json',cache:false})
      .done(function(r){
        if(!r||r.success!==true) return;
        var count=parseInt(r.count,10)||0;
        var $badge=$('#wooImportBadge');
        if(!$badge.length) return;
        $badge.text(count);
        if(count>0){$badge.show();$('#wooImportTile').addClass('woo-has-new-orders');}else{$badge.hide();$('#wooImportTile').removeClass('woo-has-new-orders');}
      });
  }

  function setTwoFaBusy(busy){
    var $btn=$('#btnRegenerate2FAHeader');
    if(!$btn.length) return;
    if(busy){
      $btn.data('original-text',$btn.text()).prop('disabled',true).text('Se genereaza...');
    }else{
      $btn.prop('disabled',false).text($btn.data('original-text')||'Genereaza cod nou');
    }
  }

  function requestTwoFa(action,showError){
    if(!$('#operator2faCodeMain').length) return;
    if(action==='regenerate') setTwoFaBusy(true);

    $.ajax({
      url:'vanzare_tableta_2fa_api.php',
      type:'POST',
      dataType:'json',
      cache:false,
      data:{action:action}
    }).done(function(res){
      if(res&&res.status==='success'){
        $('#operator2faCodeMain').text(res.active===false||!res.code?'N/A':res.code);
        return;
      }
      if(showError){alert(res&&res.message?res.message:'Nu s-a putut genera codul 2FA.');}
    }).fail(function(xhr){
      var msg='Eroare de comunicare la generarea codului 2FA.';
      if(xhr&&xhr.responseJSON&&xhr.responseJSON.message) msg=xhr.responseJSON.message;
      if(showError) alert(msg);
    }).always(function(){
      if(action==='regenerate') setTwoFaBusy(false);
    });
  }

  $(document).on('click','#btnRegenerate2FAHeader',function(){requestTwoFa('regenerate',true);});
  $(function(){
    renderWooOfflineBadge();
    setInterval(renderWooOfflineBadge,20000);
    requestTwoFa('status',false);
  });
})(window.jQuery);
</script>
HTML;

if(stripos($html,'</body>')!==false){$html=preg_replace('/<\/body>/i',$script.'</body>',$html,1)??($html.$script);}else{$html.=$script;}
echo $html;
