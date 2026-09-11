<?php
require_once __DIR__.'/database_connection.php';
include __DIR__.'/header.php';
$company=casa_one($pdo,'SELECT * FROM casa_online_company ORDER BY id LIMIT 1');
$fields=['den_ent'=>'Denumire firmă','cod_fiscal'=>'Cod fiscal','nr_reg_com'=>'Registrul Comerțului','sediu'=>'Sediu','judet'=>'Județ','localitate'=>'Localitate','banca'=>'Banca','cont_banca'=>'IBAN','serie_casa_marcat'=>'Serie casă de marcat','nui'=>'NUI memorie fiscală','serie_memorie_fiscala'=>'Seria memoriei fiscale','serie_factura_implicita'=>'Serie implicită','cota_tva_predefinita'=>'TVA implicit','email'=>'Email','telefon'=>'Telefon'];
?><main class="container-fluid"><h1 class="h3">Date preluate din online</h1><p>Datele firmei, seriile și utilizatorii se administrează în aplicația online. Produsele se actualizează și automat prin AutoScanner.</p>
<div class="card p-3"><div><button class="btn btn-primary mb-2" data-casa-pull="company">Preia datele firmei și seriile</button> <button class="btn btn-primary mb-2" data-casa-pull="users">Preia utilizatorii și TVA</button> <button class="btn btn-primary mb-2" data-casa-pull="products">Preia produsele</button></div><p id="catalog-message" role="status"></p></div>
<div class="card p-3"><h2 class="h4">Date firmă</h2><dl class="row"><?php foreach($fields as $field=>$label):?><dt class="col-md-4"><?=casa_h($label)?></dt><dd class="col-md-8"><?=casa_h($company[$field]??'')?></dd><?php endforeach;?></dl></div>
<h2 class="h4">Serii documente</h2><table class="table bg-white"><tr><th>Tip</th><th>Serie</th><th>Număr de început</th></tr><?php foreach(casa_rows($pdo,'SELECT * FROM casa_online_series ORDER BY serie') as $series):?><tr><td><?=casa_h($series['tip_registru'])?></td><td><?=casa_h($series['serie'])?></td><td><?=(int)$series['nr_inceput']?></td></tr><?php endforeach;?></table>
<h2 class="h4">Utilizatori</h2><table class="table bg-white"><tr><th>Nume</th><th>Email</th><th>Stare</th></tr><?php foreach(casa_rows($pdo,'SELECT prenume,nume,adresa_de_email,dezactivat FROM utilizatori ORDER BY prenume,nume') as $user):?><tr><td><?=casa_h($user['prenume'].' '.$user['nume'])?></td><td><?=casa_h($user['adresa_de_email'])?></td><td><?=$user['dezactivat']?'Dezactivat':'Activ'?></td></tr><?php endforeach;?></table>
</main><script>
document.querySelectorAll('[data-casa-pull]').forEach(function(button){button.onclick=async function(){
 const buttons=document.querySelectorAll('[data-casa-pull]'),message=document.getElementById('catalog-message');
 buttons.forEach(function(b){b.disabled=true;});message.textContent='Se preiau datele din online...';
 try{const response=await fetch('pull_online.php',{method:'POST',body:new URLSearchParams({action:button.dataset.casaPull})});const data=await response.json();if(!data.success)throw new Error(data.error);location.reload();}
 catch(e){message.textContent=e.message||'Preluarea nu a fost confirmată.';buttons.forEach(function(b){b.disabled=false;});}
};});
</script><?php include __DIR__.'/footer.php';?>
