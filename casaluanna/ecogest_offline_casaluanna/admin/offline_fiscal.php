<?php
require_once __DIR__.'/offline_runtime.php';
function casa_fiscal_schema(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS casa_fiscal_jobs (id INTEGER PRIMARY KEY AUTOINCREMENT,id_factura INTEGER NOT NULL UNIQUE,bon_id INTEGER NOT NULL,payload TEXT NOT NULL,state TEXT NOT NULL DEFAULT 'queued',created_at TEXT NOT NULL)");
    $db->exec("CREATE TABLE IF NOT EXISTS casa_fiscal_attempts (id INTEGER PRIMARY KEY AUTOINCREMENT,id_factura INTEGER NOT NULL,bon_id INTEGER NOT NULL,payload TEXT NOT NULL,state TEXT NOT NULL DEFAULT 'queued',created_at TEXT NOT NULL)");
}
function casa_fiscal_queue_path(): string {
    $config=casa_config();
    $root=rtrim((string)($config['api_root_absolute']??''),"\\/");
    if($root==='')throw new RuntimeException('Directorul API local pentru casa de marcat nu este configurat.');
    return $root.DIRECTORY_SEPARATOR.'19'.DIRECTORY_SEPARATOR.'1'.DIRECTORY_SEPARATOR.'bon_casa_marcat.json';
}
function casa_fiscal_publish_queue(string $payload): bool {
    $queuePath=casa_fiscal_queue_path();$directory=dirname($queuePath);
    if(!is_dir($directory)&&!mkdir($directory,0777,true)&&!is_dir($directory))throw new RuntimeException('Directorul cozii casei de marcat nu a putut fi creat.');
    $lock=fopen($queuePath.'.lock','c+b');
    if($lock===false||!flock($lock,LOCK_EX))throw new RuntimeException('Coada casei de marcat nu a putut fi blocată.');
    $temporaryPath=$queuePath.'.tmp.'.getmypid().'.'.bin2hex(random_bytes(6));
    try{
        if(is_file($queuePath)){
            $current=json_decode((string)file_get_contents($queuePath),true);
            $incoming=json_decode($payload,true);
            $currentId=(int)($current['data'][0]['id']??0);$incomingId=(int)($incoming['data'][0]['id']??0);
            if($currentId>0&&$currentId===$incomingId)return true;
            throw new RuntimeException('Coada casei de marcat conține deja un bon nepreluat.');
        }
        if(file_put_contents($temporaryPath,$payload,LOCK_EX)!==strlen($payload)||!rename($temporaryPath,$queuePath))throw new RuntimeException('Fișierul JSON al bonului nu a putut fi publicat.');
        return true;
    }finally{
        if(is_file($temporaryPath))@unlink($temporaryPath);
        flock($lock,LOCK_UN);fclose($lock);
    }
}
function casa_fiscal_buffer(PDO $db,int $id,array $payments,string $cui): string {
    $invoice=casa_one($db,'SELECT nr_factura,serie_factura FROM facturi WHERE id_factura=?',[$id]);
    if(!$invoice||(int)$invoice['nr_factura']<1||trim((string)$invoice['serie_factura'])==='')throw new RuntimeException('Salvați seria și numărul facturii înainte de fiscalizare.');
    $rows=casa_rows($db,'SELECT * FROM vanzari WHERE id_factura=? ORDER BY id_vanz',[$id]);
    if(!$rows)throw new RuntimeException('Factura nu conține produse.');
    $rates=[];foreach(casa_rows($db,'SELECT cota FROM cote_tva') as $r)$rates[(string)(float)$r['cota']]=true;
    if(!$rates)throw new RuntimeException('Nomenclatorul cotelor TVA lipsește. Preluați produsele și nomenclatoarele din online.');
    $map=[];foreach(casa_rows($db,'SELECT * FROM coduri_casa_tva') as $r)$map[(string)(float)$r['cota_tva']]=$r['cod_listare_cota_casa'];
    $cr=chr(13).chr(10);$header='H,1,______,_,__;'.$cr;$buffer=$header;
    if($cui!==''){if(!preg_match('/^(?:RO)?[0-9]{2,13}$/i',$cui))throw new RuntimeException('CUI invalid.');$buffer='K,1,______,_,__;'.$cui.$cr.$header;}
    $gross=0;$discount=0;
    foreach($rows as $r){
        if((float)$r['cantitate']<=0||(float)$r['pret_vanzare']<0)throw new RuntimeException('Bonul fiscal de vânzare nu acceptă cantități negative sau nule.');
        $rate=(string)(float)$r['cota_tva'];
        $vat=trim($r['den_p'])==='MASA SERVITA 11%'?4:($map[$rate]??null);
        if($vat===null&&!isset($rates[$rate]))throw new RuntimeException('Cota TVA '.$r['cota_tva'].' nu există în nomenclatorul local. Preluați produsele și nomenclatoarele din online.');
        if($vat===null||(int)$vat<1)throw new RuntimeException('Lipsește codul fiscal pentru cota TVA '.$r['cota_tva'].'.');
        $name=mb_substr(str_replace([';',"\r","\n"],' ',trim($r['den_p'])),0,72);
        $um=strtoupper(trim($r['um']))==='H87'?'BUC':mb_substr(str_replace([';',"\r","\n"],'',trim($r['um'])),0,6);
        $price=number_format((float)$r['pret_vanzare'],2,'.','');$qty=number_format((float)$r['cantitate'],3,'.','');
        if(abs(round((float)$price*(float)$qty-(float)$r['discount'],2)-(float)$r['valoare_vanzare_cu_tva'])>0.011)throw new RuntimeException('Precizia cantității sau prețului depășește formatul casei de marcat. Ajustați linia înainte de trimitere.');
        $buffer.="S,1,______,_,__;$name;$price;$qty;1;1;$vat;0;0;$um".$cr;
        $gross+=round((float)$r['valoare_vanzare_cu_tva'],2);$discount+=(float)$r['discount'];
    }
    if($discount>0)$buffer.='C,1,______,_,__;1;'.number_format($discount,2,'.','').';;;;'.$cr;
    $paid=0;
    foreach($payments as $payment){
        $type=(string)($payment['tip']??'');$amount=$payment['valoare']??null;
        if(!preg_match('/^[0-9]$/',$type)||!is_numeric($amount)||(float)$amount<0)throw new RuntimeException('Metodă sau sumă de plată invalidă.');
        if((float)$amount==0)continue;
        $paid+=round((float)$amount,2);$buffer.='T,1,______,_,__;'.$type.';'.number_format((float)$amount,2,'.','').';;;;'.$cr;
    }
    // Line totals already include discounts. Never subtract the discount a second time.
    if(abs(round($paid-$gross,2))>0.009)throw new RuntimeException('Suma metodelor de plată trebuie să fie egală cu totalul facturii.');
    return $buffer;
}
function casa_create_fiscal(PDO $db,int $id,string $buffer): int {
    casa_fiscal_schema($db);
    $db->beginTransaction();
    try{
        $db->prepare('INSERT INTO bonuri_casa_marcat(data,ora,continut_bon,de_trimis_la_casa_marcat,id_factura,locatie) VALUES(?,?,?,?,?,1)')->execute([date('Y-m-d'),date('H:i:s'),$buffer,0,$id]);
        $bonId=(int)$db->lastInsertId();$bon=casa_one($db,'SELECT * FROM bonuri_casa_marcat WHERE id=?',[$bonId]);
        $bon['de_trimis_la_casa_marcat']=1;
        foreach(['id','nrbon','locatie','id_factura','de_trimis_la_casa_marcat'] as $key)if(isset($bon[$key]))$bon[$key]=(int)$bon[$key];
        $payload=json_encode(['status'=>'success','message'=>'Bon pregătit pentru scanner.','data'=>[$bon]],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $created=date('Y-m-d H:i:s');
        $db->prepare('INSERT INTO casa_fiscal_attempts(id_factura,bon_id,payload,created_at) VALUES(?,?,?,?)')->execute([$id,$bonId,$payload,$created]);$jobId=(int)$db->lastInsertId();
        $db->prepare("INSERT INTO casa_fiscal_jobs(id_factura,bon_id,payload,state,created_at) VALUES(?,?,?,'queued',?) ON CONFLICT(id_factura) DO UPDATE SET bon_id=excluded.bon_id,payload=excluded.payload,state='queued',created_at=excluded.created_at")->execute([$id,$bonId,$payload,$created]);
        $db->prepare('UPDATE facturi SET tip_factura=751 WHERE id_factura=?')->execute([$id]);$db->commit();
        try{casa_fiscal_publish_queue($payload);}catch(Throwable $e){error_log('Casa scanner queue: '.$e->getMessage());}
        return $jobId;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
