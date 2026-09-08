<?php
// Deploy beside sincronizare_date_offline.php containing the library-only guard.
define('CASA_INVOICE_LIBRARY_ONLY',true);
require_once __DIR__.'/sincronizare_date_offline.php';
function casa_online_reply(array $data,int $code=200): void {http_response_code($code);header('Content-Type: application/json; charset=utf-8');echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);exit;}
function casa_online_rows(PDO $db,string $sql,array $params=[]): array {$q=$db->prepare($sql);$q->execute($params);return $q->fetchAll(PDO::FETCH_ASSOC);}
function casa_online_one(PDO $db,string $sql,array $params=[]): ?array {$r=casa_online_rows($db,$sql,$params);return $r[0]??null;}
function casa_online_columns(PDO $db,string $table): array {
    static $cache=[];
    if(!isset($cache[$table])){$cache[$table]=[];foreach(casa_online_rows($db,'SHOW COLUMNS FROM `'.$table.'`') as $col)$cache[$table][$col['Field']]=$col;}
    return $cache[$table];
}
function casa_online_save(PDO $db,string $table,string $pk,array $row,?int $id=null): int {
    $cols=casa_online_columns($db,$table);unset($row[$pk]);
    foreach($row as $name=>$value){
        if(!isset($cols[$name]))throw new RuntimeException('Schema online nu conține '.$table.'.'.$name.'.');
        if(is_array($value)||is_object($value))throw new RuntimeException('Valoare invalidă în document.');
        if($value===null&&$cols[$name]['Null']==='NO')$row[$name]=$cols[$name]['Default']??(preg_match('/int|decimal|double|float/',$cols[$name]['Type'])?0:'');
    }
    if($id){
        $sql='UPDATE `'.$table.'` SET '.implode(',',array_map(function($c){return '`'.$c.'`=?';},array_keys($row))).' WHERE `'.$pk.'`=?';
        $values=array_values($row);$values[]=$id;$db->prepare($sql)->execute($values);return $id;
    }
    foreach($cols as $name=>$col){
        if($name===$pk||isset($row[$name])||array_key_exists($name,$row)||strpos($col['Extra'],'auto_increment')!==false)continue;
        if($col['Null']==='NO'&&$col['Default']===null)$row[$name]=preg_match('/int|decimal|double|float/',$col['Type'])?0:'';
    }
    $db->prepare('INSERT INTO `'.$table.'` (`'.implode('`,`',array_keys($row)).'`) VALUES('.implode(',',array_fill(0,count($row),'?')).')')->execute(array_values($row));
    return (int)$db->lastInsertId();
}
function casa_online_map(PDO $db,string $installation,int $source,string $table,int $localId): ?array {
    return casa_online_one($db,'SELECT * FROM casa_invoice_rows WHERE installation_uuid=? AND source_id=? AND entity=? AND local_id=?',[$installation,$source,$table,$localId]);
}
function casa_online_children(PDO $db,string $installation,int $source,int $online,string $table,string $pk,array $rows,array $lineMap=[]): array {
    $seen=[];$mapped=[];
    foreach($rows as $row){
        $local=(int)($row[$pk]??0);if($local<1||isset($seen[$local]))throw new RuntimeException('Identificator de linie invalid sau duplicat.');$seen[$local]=true;
        if($table==='miscari'){
            $localLine=(int)($row['id_vanz_fact']??0);if(!isset($lineMap[$localLine]))throw new RuntimeException('Mișcare fără linie de factură.');
            $row['id_vanz_fact']=$lineMap[$localLine];$row['id_doc']=$online;$row['cod_locatie']=1;
        }else{$row['id_factura']=$online;}
        if($table==='bonuri_casa_marcat'){$row['de_trimis_la_casa_marcat']=0;$row['locatie']=1;}
        $map=casa_online_map($db,$installation,$source,$table,$local);
        $target=$map?(int)$map['target_id']:null;
        if($target&&!casa_online_one($db,'SELECT `'.$pk.'` FROM `'.$table.'` WHERE `'.$pk.'`=?',[$target]))throw new RuntimeException('O înregistrare sincronizată a fost eliminată online.');
        $target=casa_online_save($db,$table,$pk,$row,$target);$mapped[$local]=$target;
        if(!$map)$db->prepare('INSERT INTO casa_invoice_rows(installation_uuid,source_id,entity,local_id,target_id) VALUES(?,?,?,?,?)')->execute([$installation,$source,$table,$local,$target]);
    }
    foreach(casa_online_rows($db,'SELECT local_id,target_id FROM casa_invoice_rows WHERE installation_uuid=? AND source_id=? AND entity=?',[$installation,$source,$table]) as $old){
        if(isset($seen[(int)$old['local_id']]))continue;
        $db->prepare('DELETE FROM `'.$table.'` WHERE `'.$pk.'`=?')->execute([(int)$old['target_id']]);
        $db->prepare('DELETE FROM casa_invoice_rows WHERE installation_uuid=? AND source_id=? AND entity=? AND local_id=?')->execute([$installation,$source,$table,(int)$old['local_id']]);
    }
    return $mapped;
}
$db=null;$locked=false;
try{
    if(($_SERVER['REQUEST_METHOD']??'')!=='POST')throw new OfflineSyncHttpException(405,'Metodă nepermisă.');
    if(offline_sync_extract_api_key()==='')throw new OfflineSyncHttpException(401,'Cheie API obligatorie.');
    $raw=offline_sync_read_body();if(strlen($raw)>10*1024*1024)throw new OfflineSyncHttpException(413,'Document prea mare.');
    $payload=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
    if(($payload['schema']??'')!=='casa-invoice-v1'||(int)($payload['client_id']??0)!==19||(int)($payload['cod_locatie']??0)!==1)throw new OfflineSyncHttpException(400,'Client, locație sau schemă invalidă.');
    foreach(['installation_uuid','event_uuid'] as $key)if(!preg_match('/^[a-f0-9]{32}$/',(string)($payload[$key]??'')))throw new OfflineSyncHttpException(400,'Identificator invalid.');
    $bodyRaw=(string)($payload['body_json']??'');$hash=hash('sha256',$bodyRaw);
    if(!hash_equals($hash,(string)($payload['content_hash']??'')))throw new OfflineSyncHttpException(400,'Hash invalid.');
    $body=json_decode($bodyRaw,true,512,JSON_THROW_ON_ERROR);$invoice=$body['factura']??[];
    $source=(int)($payload['source_id']??0);$revision=(int)($payload['revision']??0);$installation=$payload['installation_uuid'];$event=$payload['event_uuid'];
    $receiptOnly=($body['receipt_only']??false)===true;
    if($source<1||$revision<1||(int)($invoice['id_factura']??0)!==$source||(int)($invoice['nr_factura']??0)<1||trim((string)($invoice['serie_factura']??''))===''||(!$receiptOnly&&empty($body['vanzari'])))throw new OfflineSyncHttpException(400,'Factura este incompletă.');
    foreach(($body['vanzari']??[]) as $line){
        if((int)($line['id_factura']??0)!==$source)throw new OfflineSyncHttpException(400,'Linie asociată altei facturi.');
        $gross=round((float)$line['pret_vanzare']*(float)$line['cantitate']-(float)$line['discount'],2);
        if(abs($gross-(float)$line['valoare_vanzare_cu_tva'])>0.011||abs((float)$line['valoare_vanzare']+(float)$line['tva_col']-$gross)>0.011)throw new OfflineSyncHttpException(400,'Totaluri de factură inconsistente.');
    }
    $central=offline_sync_connect_central();list($db,$client)=offline_sync_connect_client($central);
    if((int)$client['id_client']!==19)throw new OfflineSyncHttpException(403,'Cheia nu aparține clientului Casa Luanna.');
    $db->exec('CREATE TABLE IF NOT EXISTS casa_invoice_inbox(event_uuid CHAR(32) PRIMARY KEY,installation_uuid CHAR(32) NOT NULL,source_id BIGINT NOT NULL,revision INT NOT NULL,content_hash CHAR(64) NOT NULL,online_id BIGINT NOT NULL,response LONGTEXT NOT NULL,UNIQUE KEY source_revision(installation_uuid,source_id,revision)) ENGINE=InnoDB');
    $db->exec('CREATE TABLE IF NOT EXISTS casa_invoice_sources(installation_uuid CHAR(32) NOT NULL,source_id BIGINT NOT NULL,online_id BIGINT NOT NULL,revision INT NOT NULL,content_hash CHAR(64) NOT NULL,body_json LONGTEXT NOT NULL,PRIMARY KEY(installation_uuid,source_id)) ENGINE=InnoDB');
    $db->exec('CREATE TABLE IF NOT EXISTS casa_invoice_rows(installation_uuid CHAR(32) NOT NULL,source_id BIGINT NOT NULL,entity VARCHAR(40) NOT NULL,local_id BIGINT NOT NULL,target_id BIGINT NOT NULL,PRIMARY KEY(installation_uuid,source_id,entity,local_id)) ENGINE=InnoDB');
    $q=$db->query("SELECT GET_LOCK('casa_invoice_import_19',5)");$locked=(int)$q->fetchColumn()===1;
    if(!$locked)throw new OfflineSyncHttpException(503,'Import ocupat. Se va reîncerca.');
    $db->beginTransaction();
    $prior=casa_online_one($db,'SELECT * FROM casa_invoice_inbox WHERE event_uuid=?',[$event]);
    if($prior){
        if($prior['content_hash']!==$hash||$prior['installation_uuid']!==$installation||(int)$prior['source_id']!==$source)throw new OfflineSyncHttpException(409,'Eveniment reutilizat cu alt conținut.');
        $reply=json_decode($prior['response'],true,512,JSON_THROW_ON_ERROR);$db->commit();$db->query("SELECT RELEASE_LOCK('casa_invoice_import_19')");casa_online_reply($reply);
    }
    $state=casa_online_one($db,'SELECT * FROM casa_invoice_sources WHERE installation_uuid=? AND source_id=? FOR UPDATE',[$installation,$source]);
    if($revision!==($state?(int)$state['revision']+1:1)||(string)($payload['previous_hash']??'')!==($state?$state['content_hash']:''))throw new OfflineSyncHttpException(409,'Revizie lipsă sau conflict de sincronizare.');
    $online=$state?(int)$state['online_id']:null;
    if($receiptOnly){
        $target=casa_online_one($db,'SELECT id_factura FROM facturi WHERE id_factura=? AND serie_factura=? AND nr_factura=? FOR UPDATE',[(int)($body['online_id']??0),$invoice['serie_factura'],$invoice['nr_factura']]);
        if(!$target||($online&&$online!==(int)$target['id_factura']))throw new OfflineSyncHttpException(409,'Factura istorică nu corespunde documentului online.');
        $online=(int)$target['id_factura'];
        casa_online_children($db,$installation,$source,$online,'incasari','id_incasare',$body['incasari']??[]);
        $reply=['status'=>'success','event_uuid'=>$event,'content_hash'=>$hash,'online_id'=>$online];$replyJson=json_encode($reply,JSON_THROW_ON_ERROR);
        $db->prepare('INSERT INTO casa_invoice_sources(installation_uuid,source_id,online_id,revision,content_hash,body_json) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE revision=VALUES(revision),content_hash=VALUES(content_hash),body_json=VALUES(body_json)')->execute([$installation,$source,$online,$revision,$hash,$bodyRaw]);
        $db->prepare('INSERT INTO casa_invoice_inbox(event_uuid,installation_uuid,source_id,revision,content_hash,online_id,response) VALUES(?,?,?,?,?,?,?)')->execute([$event,$installation,$source,$revision,$hash,$online,$replyJson]);
        $db->commit();$db->query("SELECT RELEASE_LOCK('casa_invoice_import_19')");$locked=false;casa_online_reply($reply);
    }
    $duplicate=casa_online_one($db,'SELECT id_factura FROM facturi WHERE serie_factura=? AND nr_factura=? AND id_factura<>? LIMIT 1',[$invoice['serie_factura'],$invoice['nr_factura'],$online??0]);
    if($duplicate)throw new OfflineSyncHttpException(409,'Seria și numărul există deja online. Factura existentă nu a fost modificată.');
    $old=$online?casa_online_one($db,'SELECT * FROM facturi WHERE id_factura=? FOR UPDATE',[$online]):null;
    if($online&&!$old)throw new OfflineSyncHttpException(409,'Factura sincronizată a fost eliminată online.');
    if($old){
        $previous=json_decode($state['body_json'],true,512,JSON_THROW_ON_ERROR);
        $financialChanged=$previous['factura']!=$invoice||$previous['vanzari']!=$body['vanzari'];
        if($financialChanged&&!empty($old['data_incarcare'])&&$old['data_incarcare']!=='0000-00-00 00:00:00')throw new OfflineSyncHttpException(409,'Factura încărcată la ANAF nu poate fi modificată.');
        foreach($previous['factura'] as $key=>$value){
            if(in_array($key,['id_factura','id_client','data_validare','data_incarcare','data_corectare','data_stornare','id_factura_stornare','motiv_stornare'],true))continue;
            if(array_key_exists($key,$old)&&(string)$old[$key]!== (string)$value)throw new OfflineSyncHttpException(409,'Factura a fost modificată online. Este necesară reconcilierea.');
        }
    }
    $invoice['id_client']=0;
    if(!empty($body['client'])){
        $customer=$body['client'];$customerRow=casa_online_one($db,'SELECT id_client FROM clienti WHERE cod_fiscal=? LIMIT 1',[$customer['cod_fiscal']]);
        $invoice['id_client']=$customerRow?(int)$customerRow['id_client']:casa_online_save($db,'clienti','id_client',$customer);
    }
    foreach(['data_validare','data_incarcare','data_corectare'] as $key)$invoice[$key]=$old[$key]??'0000-00-00 00:00:00';
    $invoice['id_deviz']=0;$invoice['nr_nota']=0;$invoice['nrbon']=0;$invoice['factura_restaurant']=0;
    $invoice['id_factura_stornare']=$old['id_factura_stornare']??null;
    $online=casa_online_save($db,'facturi','id_factura',$invoice,$online);
    $lines=casa_online_children($db,$installation,$source,$online,'vanzari','id_vanz',$body['vanzari']);
    casa_online_children($db,$installation,$source,$online,'miscari','id',$body['miscari']??[],$lines);
    casa_online_children($db,$installation,$source,$online,'incasari','id_incasare',$body['incasari']??[]);
    casa_online_children($db,$installation,$source,$online,'bonuri_casa_marcat','id',$body['bonuri_casa_marcat']??[]);
    $stornari=[];
    foreach(($body['stornari']??[]) as $storno){
        $ref=$storno['offline_reference']??[];unset($storno['offline_reference']);
        if(($ref['origin']??'')==='historical')$originalId=(int)($ref['online_id']??0);
        else{$originalMap=casa_online_one($db,'SELECT online_id FROM casa_invoice_sources WHERE installation_uuid=? AND source_id=?',[$installation,(int)$storno['id_factura_stornata']]);$originalId=(int)($originalMap['online_id']??0);}
        $original=casa_online_one($db,'SELECT id_factura FROM facturi WHERE id_factura=? AND serie_factura=? AND nr_factura=?',[$originalId,$ref['serie_factura']??'',(int)($ref['nr_factura']??0)]);
        if(!$original)throw new OfflineSyncHttpException(409,'Factura stornată nu a fost încă sincronizată sau nu corespunde documentului online.');
        $storno['id_factura_stornata']=$originalId;$stornari[]=$storno;
    }
    casa_online_children($db,$installation,$source,$online,'stornari','id_stornare',$stornari);
    $reply=['status'=>'success','event_uuid'=>$event,'content_hash'=>$hash,'online_id'=>$online];
    $replyJson=json_encode($reply,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    $db->prepare('INSERT INTO casa_invoice_sources(installation_uuid,source_id,online_id,revision,content_hash,body_json) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE revision=VALUES(revision),content_hash=VALUES(content_hash),body_json=VALUES(body_json)')->execute([$installation,$source,$online,$revision,$hash,$bodyRaw]);
    $db->prepare('INSERT INTO casa_invoice_inbox(event_uuid,installation_uuid,source_id,revision,content_hash,online_id,response) VALUES(?,?,?,?,?,?,?)')->execute([$event,$installation,$source,$revision,$hash,$online,$replyJson]);
    $db->commit();$db->query("SELECT RELEASE_LOCK('casa_invoice_import_19')");$locked=false;casa_online_reply($reply);
}catch(Throwable $e){
    if($db instanceof PDO&&$db->inTransaction())$db->rollBack();
    if($locked)$db->query("SELECT RELEASE_LOCK('casa_invoice_import_19')");
    $code=$e instanceof OfflineSyncHttpException?$e->getHttpCode():500;
    error_log('Casa invoice import: '.$e->getMessage());
    casa_online_reply(['status'=>'error','message'=>$e instanceof OfflineSyncHttpException?$e->getMessage():'Importul nu a fost confirmat. Verificați jurnalul serverului.'],$code);
}
