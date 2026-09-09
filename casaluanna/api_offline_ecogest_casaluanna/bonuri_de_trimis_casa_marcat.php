<?php
require_once __DIR__.'/offline_runtime.php';
require_once __DIR__.'/offline_fiscal.php';
require_once __DIR__.'/printer_queue_atomic_helper.php';
if(!in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true))casa_json(['status'=>'error','message'=>'Acces local obligatoriu.'],403);
if((string)($_POST['client_id']??'')!=='19'||(string)($_POST['locatie']??'')!=='1')casa_json(['status'=>'error','message'=>'Client sau locație invalidă.'],400);
$queuePath=casa_fiscal_queue_path();$directory=dirname($queuePath);
if(!is_dir($directory)&&!mkdir($directory,0777,true)&&!is_dir($directory))casa_json(['status'=>'error','message'=>'Directorul cozii casei de marcat nu poate fi creat.'],500);
$scannerLock=fopen($directory.'/scanner.lock','c+b');
if(!$scannerLock||!flock($scannerLock,LOCK_EX|LOCK_NB))casa_json(['status'=>'success','message'=>'Preluare în curs.','data'=>[]]);
$claimedPath='';
try{
    $claim=agecs_printer_queue_claim($queuePath);
    if(($claim['status']??'')==='empty'){
        $processing=glob($queuePath.'.processing.*');
        if(is_array($processing)&&$processing!==[])casa_json(['status'=>'success','message'=>'Preluare în curs.','data'=>[]]);
        $db=casa_db();casa_fiscal_schema($db);
        $job=casa_one($db,"SELECT * FROM casa_fiscal_attempts WHERE state='queued' ORDER BY id LIMIT 1");
        if(!$job)$job=casa_one($db,"SELECT * FROM casa_fiscal_jobs WHERE state='queued' ORDER BY id LIMIT 1");
        if(!$job)casa_json(['status'=>'success','message'=>'Nu există bonuri în așteptare.','data'=>[]]);
        casa_fiscal_publish_queue((string)$job['payload']);
        $claim=agecs_printer_queue_claim($queuePath);
    }
    if(($claim['status']??'')!=='claimed'||!is_file($claim['path']??''))throw new RuntimeException('Bonul JSON nu a putut fi preluat atomic.');
    $claimedPath=(string)$claim['path'];
    $raw=(string)file_get_contents($claimedPath);
    if(strncmp($raw,"\xEF\xBB\xBF",3)===0)$raw=substr($raw,3);
    $payload=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
    if(!is_array($payload)||!isset($payload['data'])||!is_array($payload['data']))throw new RuntimeException('Bon JSON invalid.');
    $bonId=(int)($payload['data'][0]['id']??0);$db=$db??casa_db();casa_fiscal_schema($db);
    if($bonId>0){
        $job=casa_one($db,'SELECT id FROM casa_fiscal_attempts WHERE bon_id=?',[$bonId]);
        if($job)$db->prepare("UPDATE casa_fiscal_attempts SET state='dispatched' WHERE id=?")->execute([(int)$job['id']]);
        $db->prepare("UPDATE casa_fiscal_jobs SET state='dispatched' WHERE bon_id=?")->execute([$bonId]);
    }
    if(!@unlink($claimedPath))throw new RuntimeException('Bonul nu a putut fi eliminat după preluare.');
    $claimedPath='';casa_json($payload);
}catch(Throwable $e){
    if($claimedPath!==''&&is_file($claimedPath))agecs_printer_queue_restore_claim($claimedPath,$queuePath);
    error_log('Casa scanner: '.$e->getMessage());
    casa_json(['status'=>'error','message'=>'Preluarea bonului nu a fost confirmată.'],500);
}finally{
    flock($scannerLock,LOCK_UN);fclose($scannerLock);
}
