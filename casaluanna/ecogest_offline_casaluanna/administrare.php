<?php
require_once __DIR__.'/database_connection.php';$error='';
$fields=['den_ent'=>'Denumire firmă','cod_fiscal'=>'Cod fiscal','nr_reg_com'=>'Registrul Comerțului','sediu'=>'Sediu','judet'=>'Județ','localitate'=>'Localitate','banca'=>'Banca','cont_banca'=>'IBAN','cap_soc'=>'Capital social','numar_zile_scadenta'=>'Zile până la scadență','serie_factura_implicita'=>'Serie implicită','serie_stornare'=>'Serie stornare','cota_tva_predefinita'=>'TVA implicit','email'=>'Email','telefon'=>'Telefon'];
$columns=array_column(casa_rows($pdo,"PRAGMA table_info('date_firma')"),'name');$fields=array_intersect_key($fields,array_flip($columns));
$company=casa_one($pdo,'SELECT * FROM date_firma ORDER BY id LIMIT 1');
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
    try{
        if(($_POST['action']??'')==='company'){
            if(!$company)throw new RuntimeException('Datele firmei lipsesc din baza furnizată.');
            $data=[];foreach($fields as $key=>$label)$data[$key]=trim((string)($_POST[$key]??''));
            $params=array_values($data);$params[]=$company['id'];
            $pdo->prepare('UPDATE date_firma SET '.implode(',',array_map(function($k){return '"'.$k.'"=?';},array_keys($data))).' WHERE id=?')->execute($params);
        }elseif(($_POST['action']??'')==='series'){
            $series=trim((string)($_POST['serie']??''));$start=(int)($_POST['nr_inceput']??0);
            if($series===''||mb_strlen($series)>50||$start<1)throw new RuntimeException('Seria și numărul de început nu sunt valide.');
            if(casa_one($pdo,'SELECT 1 FROM serii_documente WHERE serie=?',[$series]))throw new RuntimeException('Seria există deja.');
            $pdo->prepare("INSERT INTO serii_documente(tip_registru,serie,nr_inceput,id_utilizator,tip) VALUES('factura',?,?,?,'factura')")->execute([$series,$start,(int)$_SESSION['admin_id']]);
        }
        header('Location: administrare.php');exit;
    }catch(Throwable $e){$error=$e->getMessage();}
}
include __DIR__.'/header.php';
?><main class="container-fluid"><h1 class="h3">Date firmă și serii</h1><p class="text-danger"><?=casa_h($error)?></p><form method="post" class="card p-3"><input type="hidden" name="action" value="company"><div class="row"><?php foreach($fields as $key=>$label):?><label class="col-md-6"><?=casa_h($label)?><input class="form-control mb-2" name="<?=casa_h($key)?>" value="<?=casa_h($company[$key]??'')?>"></label><?php endforeach;?></div><button class="btn btn-primary align-self-start">Salvează datele locale</button></form><h2 class="h4">Serii facturi</h2><table class="table bg-white"><tr><th>Serie</th><th>Număr de început</th></tr><?php foreach(casa_rows($pdo,"SELECT * FROM serii_documente WHERE tip_registru LIKE '%factura%' ORDER BY serie") as $series):?><tr><td><?=casa_h($series['serie'])?></td><td><?=(int)$series['nr_inceput']?></td></tr><?php endforeach;?></table><form method="post" class="card p-3"><input type="hidden" name="action" value="series"><label>Serie nouă<input class="form-control" name="serie" required maxlength="50"></label><label>Număr de început<input class="form-control" name="nr_inceput" type="number" min="1" value="1" required></label><button class="btn btn-primary align-self-start">Adaugă seria</button></form><p>Numerotarea offline continuă din baza importată. O serie folosită simultan online și offline poate produce conflicte de număr. Importul raportează conflictul și păstrează factura online existentă.</p></main><?php include __DIR__.'/footer.php';?>
