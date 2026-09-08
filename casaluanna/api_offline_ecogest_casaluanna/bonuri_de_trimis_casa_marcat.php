<?php
require_once __DIR__.'/offline_runtime.php';require_once __DIR__.'/offline_fiscal.php';
if(!in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true))casa_json(['status'=>'error','message'=>'Acces local obligatoriu.'],403);
if((string)($_POST['client_id']??'')!=='19'||(string)($_POST['locatie']??'')!=='1')casa_json(['status'=>'error','message'=>'Client sau locație invalidă.'],400);
$directory=__DIR__.'/19/1';$lock=fopen($directory.'/scanner.lock','c+b');
if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))casa_json(['status'=>'success','message'=>'Preluare în curs.','data'=>[]]);
try{
    $db=casa_db();casa_fiscal_schema($db);
    $job=casa_one($db,"SELECT * FROM casa_fiscal_jobs WHERE state='queued' ORDER BY id LIMIT 1");
    if(!$job)casa_json(['status'=>'success','message'=>'Nu există bonuri în așteptare.','data'=>[]]);
    $path=$directory.'/bon_casa_marcat_'.$job['id'].'.json';$sent=$path.'.preluat';
    if(is_file($sent)){
        $db->prepare("UPDATE casa_fiscal_jobs SET state='dispatched' WHERE id=?")->execute([$job['id']]);
        casa_json(['status'=>'success','message'=>'Starea preluării precedente a fost recuperată.','data'=>[]]);
    }
    if(!is_file($path)){
        $tmp=$path.'.tmp';
        if(file_put_contents($tmp,$job['payload'],LOCK_EX)!==strlen($job['payload'])||!rename($tmp,$path))throw new RuntimeException('Fișierul JSON nu a putut fi publicat.');
    }
    $content=file_get_contents($path);$payload=json_decode((string)$content,true,512,JSON_THROW_ON_ERROR);
    if(!is_array($payload)||!isset($payload['data']))throw new RuntimeException('Bon JSON invalid.');
    // The unmodified scanner has no fiscal acknowledgment API. Keep the delivered payload for reconciliation.
    if(!rename($path,$sent))throw new RuntimeException('Bonul nu a putut fi marcat ca preluat.');
    $db->prepare("UPDATE casa_fiscal_jobs SET state='dispatched' WHERE id=?")->execute([$job['id']]);
    casa_json($payload);
}catch(Throwable $e){error_log('Casa scanner: '.$e->getMessage());casa_json(['status'=>'error','message'=>'Preluarea bonului nu a fost confirmată.'],500);}
