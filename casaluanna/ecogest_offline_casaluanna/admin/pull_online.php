<?php
require_once __DIR__.'/offline_pull.php';casa_session();casa_csrf();
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')casa_json(['success'=>false,'error'=>'Metodă nepermisă.'],405);
$action=(string)($_POST['action']??'');
// Refresh the authoritative users directory from the login page as well.
if($action!=='users')casa_auth();
if(!in_array($action,['company','users','products','invoices'],true))casa_json(['success'=>false,'error'=>'Acțiune invalidă.'],400);
$gate=(string)($_POST['gate']??'');
if($action==='invoices'&&$gate===''){
    foreach(($_SESSION['casa_gates']??[]) as $key=>$entry)if(time()-(int)$entry['started']>1800)unset($_SESSION['casa_gates'][$key]);
    $gate=bin2hex(random_bytes(16));
    $_SESSION['casa_gates'][$gate]=['started'=>time(),'after'=>0,'invoice_id'=>max(0,(int)($_POST['invoice_id']??0))];
}
if($action==='invoices'&&(!isset($_SESSION['casa_gates'][$gate])||time()-$_SESSION['casa_gates'][$gate]['started']>1800))casa_json(['success'=>false,'error'=>'Verificarea a expirat. Reîncărcați pagina.'],409);
$progress=$action==='invoices'?$_SESSION['casa_gates'][$gate]:[];
session_write_close();
$lock=null;$syncLock=null;
try {
    $params=['installation_uuid'=>casa_installation(casa_db())];
    if($action==='invoices') {
        $syncLock=fopen(casa_config()['api_root_absolute'].'/invoice-sync.lock','c+b');
        if(!$syncLock||!flock($syncLock,LOCK_EX|LOCK_NB))throw new RuntimeException('Sincronizarea trimite o factură. Preluarea va reîncerca automat.');
        $params['after']=(int)($progress['after']??0);if(isset($progress['upper']))$params['upper']=$progress['upper'];
        $params['invoice_id']=(int)($progress['invoice_id']??0);
        $params['known_hashes']=[];
        $hashes=$params['invoice_id']?casa_rows(casa_db(),'SELECT * FROM casa_remote_fingerprints WHERE online_id=?',[$params['invoice_id']]):casa_rows(casa_db(),'SELECT * FROM casa_remote_fingerprints WHERE online_id>? ORDER BY online_id LIMIT 80',[$params['after']]);
        foreach($hashes as $hash)$params['known_hashes'][(string)$hash['online_id']]=$hash['hash'];
    }
    $reply=casa_remote_request($action,$params);
    $lock=fopen(casa_config()['api_root_absolute'].'/invoice-write.lock','c+b');
    if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('O factură se salvează. Reîncercați preluarea.');
    $db=casa_db();
    if($action==='invoices'){
        // Do not acquire the session while holding the write lock: a page request
        // can hold the session and wait for this same lock.
        if((int)$reply['next']<(int)($progress['after']??0)||(!$reply['done']&&(int)$reply['next']===(int)($progress['after']??0)))throw new RuntimeException('Paginarea online nu a avansat.');
        $count=casa_pull_invoices($db,$reply);
        flock($lock,LOCK_UN);fclose($lock);$lock=null;
        session_start();
        if(!isset($_SESSION['casa_gates'][$gate]))throw new RuntimeException('Verificarea nu mai este activă.');
        $_SESSION['casa_gates'][$gate]['after']=(int)$reply['next'];
        $_SESSION['casa_gates'][$gate]['upper']=(int)$reply['upper'];
        $_SESSION['casa_gates'][$gate]['verified']=(bool)$reply['done'];
        session_write_close();
    } else $count=casa_pull_catalog($db,$action,$reply);
    if(is_resource($lock)){flock($lock,LOCK_UN);fclose($lock);$lock=null;}
    if(is_resource($syncLock)){flock($syncLock,LOCK_UN);fclose($syncLock);}
    $readonly=false;
    if($action==='invoices'&&!empty($params['invoice_id'])){
        $invoiceMeta=casa_one($db,'SELECT id_factura FROM casa_invoices WHERE online_id=?',[(int)$params['invoice_id']]);
        $readonly=$invoiceMeta&&casa_invoice_readonly($db,(int)$invoiceMeta['id_factura']);
    }
    casa_json(['success'=>true,'gate'=>$gate,'count'=>$count,'done'=>$reply['done']??true,'readonly'=>$readonly,'message'=>'Datele au fost preluate din online.']);
} catch(Throwable $e) {
    if(is_resource($lock)){flock($lock,LOCK_UN);fclose($lock);}
    if(is_resource($syncLock)){flock($syncLock,LOCK_UN);fclose($syncLock);}
    casa_json(['success'=>false,'error'=>$e->getMessage()],503);
}
