<?php
require_once __DIR__.'/offline_pull.php';casa_auth();casa_csrf();
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')casa_json(['success'=>false,'error'=>'Metodă nepermisă.'],405);
$action=(string)($_POST['action']??'');
if(!in_array($action,['company','users','products','invoices'],true))casa_json(['success'=>false,'error'=>'Acțiune invalidă.'],400);
$gate=(string)($_POST['gate']??'');
if($action==='invoices'&&(!isset($_SESSION['casa_gates'][$gate])||time()-$_SESSION['casa_gates'][$gate]['started']>1800))casa_json(['success'=>false,'error'=>'Verificarea a expirat. Reîncărcați pagina.'],409);
$progress=$action==='invoices'?$_SESSION['casa_gates'][$gate]:[];
session_write_close();
$lock=null;
try {
    $params=['installation_uuid'=>casa_installation(casa_db())];
    if($action==='invoices') {$params['after']=(int)($progress['after']??0);if(isset($progress['upper']))$params['upper']=$progress['upper'];}
    $reply=casa_remote_request($action,$params);
    $lock=fopen(casa_config()['api_root_absolute'].'/invoice-write.lock','c+b');
    if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('O factură se salvează. Reîncercați preluarea.');
    $db=casa_db();
    if($action==='invoices'){
        $count=casa_pull_invoices($db,$reply);
        if((int)$reply['next']<(int)($progress['after']??0)||(!$reply['done']&&(int)$reply['next']===(int)($progress['after']??0)))throw new RuntimeException('Paginarea online nu a avansat.');
        session_start();
        if(!isset($_SESSION['casa_gates'][$gate]))throw new RuntimeException('Verificarea nu mai este activă.');
        $_SESSION['casa_gates'][$gate]['after']=(int)$reply['next'];
        $_SESSION['casa_gates'][$gate]['upper']=(int)$reply['upper'];
        $_SESSION['casa_gates'][$gate]['verified']=(bool)$reply['done'];
        session_write_close();
    } else $count=casa_pull_catalog($db,$action,$reply);
    flock($lock,LOCK_UN);fclose($lock);$lock=null;
    casa_json(['success'=>true,'count'=>$count,'done'=>$reply['done']??true,'message'=>'Datele au fost preluate din online.']);
} catch(Throwable $e) {
    if(is_resource($lock)){flock($lock,LOCK_UN);fclose($lock);}
    casa_json(['success'=>false,'error'=>$e->getMessage()],503);
}
