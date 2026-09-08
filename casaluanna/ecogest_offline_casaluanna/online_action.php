<?php
require_once __DIR__.'/database_connection.php';
$id=(int)($_GET['id_factura']??$_POST['id_factura']??0);
if(!$id&&!empty($_GET['nr_factura'])){
    $f=casa_one($pdo,'SELECT id_factura FROM facturi WHERE nr_factura=? AND serie_factura=?',[$_GET['nr_factura'],$_GET['serie_factura']??'']);$id=(int)($f['id_factura']??0);
}
$meta=casa_one($pdo,'SELECT * FROM casa_invoices WHERE id_factura=?',[$id]);
$url=casa_config()['online_base_url'].'/facturi.php';
if(!empty($meta['online_id']))$url=casa_config()['online_base_url'].'/detalii_factura.php?id_factura='.(int)$meta['online_id'];
if(($_SERVER['REQUEST_METHOD']??'')==='POST')casa_json(['success'=>false,'error'=>'Operația necesită aplicația online. Deschideți factura online din Sincronizare.','message'=>'Operația necesită aplicația online.','online_url'=>$url],409);
include __DIR__.'/header.php';
?><main class="container-fluid"><h1 class="h3">Operație online</h1><?php if(empty($meta['online_id'])):?><p>Factura trebuie finalizată și confirmată online înainte de această operație.</p><a class="btn btn-primary" href="sincronizare.php">Sincronizare</a><?php else:?><p>Validarea ANAF, încărcarea și trimiterea prin email sunt disponibile în contul online.</p><a class="btn btn-primary" href="<?=casa_h($url)?>" target="_blank" rel="noopener">Deschide factura online</a><?php endif;?></main><?php include __DIR__.'/footer.php';?>
