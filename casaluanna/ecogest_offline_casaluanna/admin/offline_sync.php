<?php
require_once __DIR__.'/offline_runtime.php';
function casa_snapshot(PDO $db,int $id): array {
    $invoice=casa_one($db,'SELECT * FROM facturi WHERE id_factura=?',[$id]);
    if(!$invoice)throw new RuntimeException('Factura nu există.');
    $delete=casa_one($db,"SELECT 1 FROM casa_invoices WHERE id_factura=? AND state='delete_requested' AND origin='local'",[$id]);
    if($delete)return ['lifecycle'=>'deleted','factura'=>['id_factura'=>$id,'serie_factura'=>$invoice['serie_factura'],'nr_factura'=>$invoice['nr_factura']]];
    $meta=casa_one($db,'SELECT origin,online_id,finalized FROM casa_invoices WHERE id_factura=?',[$id]);
    if(($meta['origin']??'')==='historical'){
        $receipts=casa_rows($db,'SELECT i.* FROM incasari i JOIN casa_local_receipts l ON l.id_incasare=i.id_incasare WHERE i.id_factura=? ORDER BY i.id_incasare',[$id]);
        if(!$receipts)throw new RuntimeException('Nu există încasări locale noi.');
        return ['receipt_only'=>true,'online_id'=>(int)$meta['online_id'],'factura'=>['id_factura'=>$id,'serie_factura'=>$invoice['serie_factura'],'nr_factura'=>$invoice['nr_factura']],'incasari'=>$receipts];
    }
    $rows=casa_rows($db,'SELECT * FROM vanzari WHERE id_factura=? ORDER BY id_vanz',[$id]);
    if((int)$invoice['nr_factura']<1||trim((string)$invoice['serie_factura'])==='')throw new RuntimeException('Completați seria și numărul facturii.');
    if((int)$meta['finalized']===1){
        if(!$rows)throw new RuntimeException('Completați produsele facturii.');
        if(trim((string)($invoice['denumire']?:($invoice['nume']??'')))==='')throw new RuntimeException('Completați beneficiarul facturii.');
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)$invoice['data_factura'])||substr($invoice['data_factura'],0,4)==='0000')throw new RuntimeException('Data facturii nu este validă.');
    }
    foreach($rows as $row){
        if(!is_numeric($row['cantitate'])||!is_numeric($row['pret_vanzare'])||(float)$row['cota_tva']<0)throw new RuntimeException('O linie a facturii nu este validă.');
        $gross=round((float)$row['cantitate']*(float)$row['pret_vanzare']-(float)$row['discount'],2);
        if(abs($gross-(float)$row['valoare_vanzare_cu_tva'])>0.011)throw new RuntimeException('Totalul unei linii nu corespunde cantității, prețului și discountului.');
    }
    $client=casa_one($db,'SELECT * FROM clienti WHERE id_client=?',[(int)$invoice['id_client']]);
    $fiscal=casa_rows($db,'SELECT * FROM bonuri_casa_marcat WHERE id_factura=? ORDER BY id',[$id]);
    foreach($fiscal as &$bon)$bon['de_trimis_la_casa_marcat']=0;unset($bon);
    $stornari=casa_rows($db,'SELECT * FROM stornari WHERE id_factura=? ORDER BY id_stornare',[$id]);
    foreach($stornari as &$storno){
        $ref=casa_one($db,'SELECT f.serie_factura,f.nr_factura,c.origin,c.online_id FROM facturi f JOIN casa_invoices c ON c.id_factura=f.id_factura WHERE f.id_factura=?',[(int)$storno['id_factura_stornata']]);
        if(!$ref)throw new RuntimeException('Factura stornată nu a fost găsită.');$ref['online_id']=$ref['origin']==='historical'?$ref['online_id']:null;$storno['offline_reference']=$ref;
    }unset($storno);
    return ['lifecycle'=>(int)$meta['finalized']===1?'finalized':'draft','factura'=>$invoice,'vanzari'=>$rows,'client'=>$client,'stornari'=>$stornari,
        'incasari'=>casa_rows($db,'SELECT * FROM incasari WHERE id_factura=? ORDER BY id_incasare',[$id]),
        'miscari'=>casa_rows($db,'SELECT m.* FROM miscari m JOIN vanzari v ON v.id_vanz=m.id_vanz_fact WHERE v.id_factura=? ORDER BY m.id',[$id]),
        'bonuri_casa_marcat'=>$fiscal];
}
function casa_enqueue(PDO $db,int $id): bool {
    $meta=casa_one($db,'SELECT * FROM casa_invoices WHERE id_factura=?',[$id]);
    if(!$meta)throw new RuntimeException('Factura nu este înregistrată local.');
    if(($meta['authority']??'')==='online'&&$meta['state']!=='delete_requested')return false;
    if(casa_one($db,"SELECT 1 FROM casa_outbox WHERE id_factura=? AND status!='acked'",[$id]))return false;
    $body=casa_snapshot($db,$id);
    $json=json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION|JSON_THROW_ON_ERROR);
    $hash=hash('sha256',$json);
    if($hash===$meta['hash'])return false;
    $revision=(int)$meta['revision']+1;
    $event=bin2hex(random_bytes(16));
    $payload=['schema'=>'casa-invoice-v1','client_id'=>19,'cod_locatie'=>1,'installation_uuid'=>casa_installation($db),'event_uuid'=>$event,'source_id'=>$id,'revision'=>$revision,'previous_hash'=>$meta['hash'],'content_hash'=>$hash,'body_json'=>$json];
    $db->prepare('INSERT INTO casa_outbox(event_uuid,id_factura,revision,payload,hash,created_at) VALUES(?,?,?,?,?,?)')->execute([$event,$id,$revision,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$hash,date('Y-m-d H:i:s')]);
    $db->prepare("UPDATE casa_invoices SET state=CASE WHEN state='delete_requested' THEN state WHEN finalized=1 OR origin='historical' THEN 'queued' ELSE 'draft' END,revision=?,hash=?,error='' WHERE id_factura=?")->execute([$revision,$hash,$id]);
    return true;
}
function casa_prepare_outbox(): void {
    $db=casa_db();$write=fopen(casa_config()['api_root_absolute'].'/invoice-write.lock','c+b');
    if(!$write)return;
    if(!flock($write,LOCK_EX|LOCK_NB)){fclose($write);return;}
    try{
        foreach(casa_rows($db,"SELECT c.id_factura FROM casa_invoices c JOIN facturi f ON f.id_factura=c.id_factura WHERE c.origin='local' AND (c.authority='local' OR c.state='delete_requested') AND f.nr_factura>0 AND TRIM(f.serie_factura)<>''") as $row){
            $db->beginTransaction();try{casa_enqueue($db,(int)$row['id_factura']);$db->commit();}catch(Throwable $e){if($db->inTransaction())$db->rollBack();$db->prepare('UPDATE casa_invoices SET error=? WHERE id_factura=?')->execute([mb_substr($e->getMessage(),0,600),(int)$row['id_factura']]);}
        }
    }finally{flock($write,LOCK_UN);fclose($write);}
}
function casa_sync_tick(): array {
    $config=casa_config();$db=casa_db();
    $lock=fopen($config['api_root_absolute'].'/invoice-sync.lock','c+b');
    if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))return ['message'=>'Sincronizare în curs.'];
    try{
        casa_prepare_outbox();
        $event=casa_one($db,"SELECT * FROM casa_outbox o WHERE status='pending' AND next_attempt<=? AND NOT EXISTS(SELECT 1 FROM casa_outbox older WHERE older.id_factura=o.id_factura AND older.revision<o.revision AND older.status!='acked') ORDER BY created_at,id_factura,revision LIMIT 1",[time()]);
        if(!$event){$count=(int)$db->query("SELECT COUNT(*) FROM casa_outbox WHERE status!='acked'")->fetchColumn();$problem=casa_one($db,"SELECT error FROM casa_invoices WHERE error<>'' LIMIT 1");return ['message'=>$problem?'Verificați Sincronizare: '.$problem['error']:($count?$count.' transmiteri în așteptare.':'Facturi sincronizate.'),'pending'=>$count];}
        if(!function_exists('curl_init'))throw new RuntimeException('Extensia cURL trebuie activată în PHP.');
        $ch=curl_init($config['invoice_sync_url']);
        $opts=[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$event['payload'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>12,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-Api-Key: '.$config['api_key']]];
        if(is_file($config['ca_bundle_path']))$opts[CURLOPT_CAINFO]=$config['ca_bundle_path'];
        curl_setopt_array($ch,$opts);$raw=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
        $reply=is_string($raw)?json_decode($raw,true):null;
        if($code>=200&&$code<300&&is_array($reply)&&($reply['status']??'')==='success'&&($reply['event_uuid']??'')===$event['event_uuid']&&($reply['content_hash']??'')===$event['hash']&&(int)($reply['online_id']??0)>0){
            $sent=json_decode($event['payload'],true,512,JSON_THROW_ON_ERROR);
            $sentBody=json_decode($sent['body_json'],true,512,JSON_THROW_ON_ERROR);
            $ackState=($sentBody['lifecycle']??'finalized')==='finalized'?'synced':'queued';
            $db->beginTransaction();
            try{
                $db->prepare("UPDATE casa_outbox SET status='acked',ack_at=?,error='' WHERE event_uuid=?")->execute([date('Y-m-d H:i:s'),$event['event_uuid']]);
                if(($sentBody['lifecycle']??'')==='deleted'){
                    require_once __DIR__.'/offline_delete.php';
                    casa_delete_local($db,(int)$event['id_factura']);
                    $db->commit();return ['message'=>'Ștergerea facturii offline a fost confirmată online.'];
                }
                $db->prepare("UPDATE casa_invoices SET state=CASE WHEN state='delete_requested' THEN state WHEN origin='historical' THEN 'synced' WHEN finalized=1 THEN ? ELSE 'draft' END,online_id=?,error='' WHERE id_factura=? AND revision=?")->execute([$ackState,(int)$reply['online_id'],(int)$event['id_factura'],(int)$event['revision']]);$db->commit();
            }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
            return ['message'=>'Factura a fost confirmată online.'];
        }
        $message=(string)($reply['message']??($err!==''?'Conexiune indisponibilă.':'Răspuns online neconfirmat, HTTP '.$code));
        $attempts=(int)$event['attempts']+1;$delay=min(3600,15*(2**min(8,$attempts)));
        $db->prepare('UPDATE casa_outbox SET attempts=?,next_attempt=?,error=? WHERE event_uuid=?')->execute([$attempts,time()+$delay,mb_substr($message,0,600),$event['event_uuid']]);
        $db->prepare('UPDATE casa_invoices SET error=? WHERE id_factura=?')->execute([mb_substr($message,0,600),$event['id_factura']]);
        return ['message'=>$message.' Factura rămâne în coadă.'];
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}
