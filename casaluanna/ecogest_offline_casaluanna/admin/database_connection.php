<?php
require_once __DIR__.'/offline_runtime.php';casa_auth();$pdo=casa_db();
unset($_POST['casa_csrf']);
// Persist and attempt transmission immediately after any successful legacy write.
if(!isset($GLOBALS['casa_after_write'])&&(($_SERVER['REQUEST_METHOD']??'GET')==='POST'||in_array(basename($_SERVER['SCRIPT_NAME']??''),['factura.php','duplicare_factura_corectare.php'],true))){
    $GLOBALS['casa_after_write']=true;
    register_shutdown_function(function(){
        $last=error_get_last();
        if($last&&in_array($last['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR],true))return;
        if(http_response_code()>=400)return;
        try {
            if(casa_db()->inTransaction())return;
            if(isset($GLOBALS['casa_write_lock'])&&is_resource($GLOBALS['casa_write_lock'])){flock($GLOBALS['casa_write_lock'],LOCK_UN);fclose($GLOBALS['casa_write_lock']);}
            if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
            // The page worker sends queued revisions without holding up navigation.
            require_once __DIR__.'/offline_sync.php';casa_prepare_outbox();
        } catch(Throwable $e){error_log('Casa automatic sync: '.$e->getMessage());}
    });
}
if(!isset($GLOBALS['casa_write_lock'])&& (($_SERVER['REQUEST_METHOD']??'GET')==='POST'||in_array(basename($_SERVER['SCRIPT_NAME']??''),['factura.php','duplicare_factura_corectare.php'],true))){
    $GLOBALS['casa_write_lock']=fopen(casa_config()['api_root_absolute'].'/invoice-write.lock','c+b');
    if(!$GLOBALS['casa_write_lock']||!flock($GLOBALS['casa_write_lock'],LOCK_EX))throw new RuntimeException('Documentul este ocupat.');
}
$casaEditScripts=['update_factura.php','add_product.php','delete_item.php','set_zero_item.php','modifica_cantitate_factura.php','modifica_pret_factura.php','modifica_um_factura.php','stergere_factura.php'];
if(($_SERVER['REQUEST_METHOD']??'')==='POST'&&basename($_SERVER['SCRIPT_NAME']??'')!=='stergere_factura.php'){
    $target=(int)($_POST['id_factura']??$_GET['id_factura']??0);
    if($target && !in_array(basename($_SERVER['SCRIPT_NAME']??''),['duplicare_factura.php','genereaza_factura_stornare.php','stornare_factura.php','preview_factura_stornare.php'],true) && casa_invoice_readonly($pdo,$target))casa_json(['success'=>false,'error'=>'Factura se modifică numai în aplicația online. Copia offline este doar pentru consultare.'],409);
    if($target&&casa_one($pdo,"SELECT 1 FROM casa_invoices WHERE id_factura=? AND state IN ('delete_requested','deleted')",[$target]))casa_json(['success'=>false,'error'=>'Factura este blocată pentru ștergere.'],409);
}
if(basename($_SERVER['SCRIPT_NAME']??'')==='factura.php'&&isset($_GET['id_factura'])&&casa_one($pdo,"SELECT 1 FROM casa_invoices WHERE id_factura=? AND state IN ('delete_requested','deleted')",[(int)$_GET['id_factura']])){
    header('Location: facturi.php');exit;
}
if(in_array(basename($_SERVER['SCRIPT_NAME']??''),$casaEditScripts,true)){
    $editId=(int)($_POST['id_factura']??$_POST['id_fact_stergere']??0);$lineId=(int)($_POST['id_vanz']??$_POST['id']??0);
    if($lineId){$r=casa_one($pdo,'SELECT id_factura FROM vanzari WHERE id_vanz=?',[$lineId]);$editId=(int)($r['id_factura']??0);}
    $meta=casa_one($pdo,'SELECT state,origin,revision FROM casa_invoices WHERE id_factura=?',[$editId]);
    if(basename($_SERVER['SCRIPT_NAME']??'')!=='stergere_factura.php' && (casa_invoice_readonly($pdo,$editId)||casa_invoice_anaf_locked($pdo,$editId)))casa_json(['success'=>false,'error'=>'Factura este disponibilă numai pentru consultare offline sau este transmisă la ANAF.'],409);
    if(basename($_SERVER['SCRIPT_NAME']??'')==='update_factura.php'&&(int)($meta['revision']??0)>0){
        $number=casa_one($pdo,'SELECT serie_factura,nr_factura FROM facturi WHERE id_factura=?',[$editId]);
        if(isset($_POST['serie_factura'],$_POST['nr_factura'])&&($number['serie_factura']!==trim((string)$_POST['serie_factura'])||(int)$number['nr_factura']!==(int)$_POST['nr_factura']))casa_json(['success'=>false,'error'=>'Seria și numărul au intrat deja în sincronizare. Numerotarea nu poate fi schimbată cât timp există o rezervare sau o transmitere neconfirmată.'],409);
    }
    if(basename($_SERVER['SCRIPT_NAME']??'')!=='stergere_factura.php'&&(!$meta||$meta['state']!=='draft'||$meta['origin']==='historical'))casa_json(['success'=>false,'error'=>'Factura este blocată, finalizată sau aparține istoricului online.'],409);
}

if(in_array(basename($_SERVER['SCRIPT_NAME']??''),['add_product.php','delete_item.php','set_zero_item.php','modifica_cantitate_factura.php','modifica_pret_factura.php','modifica_um_factura.php','stergere_factura.php'],true)){
    if(casa_one($pdo,"SELECT name FROM sqlite_master WHERE type='table' AND name='casa_fiscal_jobs'")&&casa_one($pdo,'SELECT id FROM casa_fiscal_jobs WHERE id_factura=?',[$editId]))casa_json(['success'=>false,'error'=>'Factura are un bon fiscal pregătit. Conținutul nu mai poate fi modificat.'],409);
}
