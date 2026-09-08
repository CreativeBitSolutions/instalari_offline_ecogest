<?php
require_once __DIR__.'/offline_runtime.php';casa_auth();$pdo=casa_db();
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'||basename($_SERVER['SCRIPT_NAME']??'')==='factura.php'){
    $GLOBALS['casa_write_lock']=fopen(casa_config()['api_root_absolute'].'/invoice-write.lock','c+b');
    if(!$GLOBALS['casa_write_lock']||!flock($GLOBALS['casa_write_lock'],LOCK_EX))throw new RuntimeException('Documentul este ocupat.');
}
$casaEditScripts=['update_factura.php','add_product.php','delete_item.php','set_zero_item.php','modifica_cantitate_factura.php','modifica_pret_factura.php','modifica_um_factura.php','stergere_factura.php'];
if(in_array(basename($_SERVER['SCRIPT_NAME']??''),$casaEditScripts,true)){
    $editId=(int)($_POST['id_factura']??$_POST['id_fact_stergere']??0);$lineId=(int)($_POST['id_vanz']??$_POST['id']??0);
    if($lineId){$r=casa_one($pdo,'SELECT id_factura FROM vanzari WHERE id_vanz=?',[$lineId]);$editId=(int)($r['id_factura']??0);}
    $meta=casa_one($pdo,'SELECT state FROM casa_invoices WHERE id_factura=?',[$editId]);
    if(!$meta||$meta['state']!=='draft')casa_json(['success'=>false,'error'=>'Factura este finalizată sau aparține istoricului. Redeschideți factura locală din Sincronizare înainte de modificare.'],409);
}
