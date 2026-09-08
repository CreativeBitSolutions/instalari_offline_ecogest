<?php
require_once __DIR__.'/offline_runtime.php';
function casa_remote_request(string $action,array $params=[]): array {
    $config=casa_config();
    $request=$params+['client_id'=>19,'action'=>$action];
    $ch=curl_init($config['invoice_sync_url']);
    $opts=[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($request,JSON_THROW_ON_ERROR),CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>6,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-Api-Key: '.$config['api_key']]];
    if(is_file($config['ca_bundle_path']))$opts[CURLOPT_CAINFO]=$config['ca_bundle_path'];
    curl_setopt_array($ch,$opts);$raw=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    $reply=is_string($raw)?json_decode($raw,true):null;
    if($code!==200||!is_array($reply)||($reply['status']??'')!=='success'||(int)($reply['client_id']??0)!==19||($reply['action']??'')!==$action)
        throw new RuntimeException((string)($reply['message']??'Verificarea online nu a putut fi confirmată. Verificați conexiunea și publicarea API-ului.'));
    return $reply;
}
function casa_pull_save(PDO $db,string $table,string $pk,array $row,?int $id=null,bool $keepId=false): int {
    $columns=array_column(casa_rows($db,'PRAGMA table_info("'.$table.'")'),'name');
    $row=array_intersect_key($row,array_flip($columns));
    if(!$keepId)unset($row[$pk]);
    if(!$row)throw new RuntimeException('Schema locală nu corespunde tabelei '.$table.'.');
    if($id!==null&&casa_one($db,'SELECT 1 FROM "'.$table.'" WHERE "'.$pk.'"=?',[$id])) {
        unset($row[$pk]);
        $db->prepare('UPDATE "'.$table.'" SET '.implode(',',array_map(fn($key)=>'"'.$key.'"=?',array_keys($row))).' WHERE "'.$pk.'"=?')->execute(array_merge(array_values($row),[$id]));
        return $id;
    }
    $db->prepare('INSERT INTO "'.$table.'" ("'.implode('","',array_keys($row)).'") VALUES ('.implode(',',array_fill(0,count($row),'?')).')')->execute(array_values($row));
    return $keepId?(int)$row[$pk]:(int)$db->lastInsertId();
}
function casa_pull_catalog(PDO $db,string $action,array $reply): int {
    $keys=['date_firma'=>'id','serii_documente'=>'id_serie','utilizatori'=>'id_utilizator','produse_servicii'=>'cod_produs','categorii'=>'id_categorie','categorii_locatii'=>'id','gestiuni'=>'id_gestiune','cote_tva'=>'id','coduri_casa_tva'=>'id','observatii_predefinite'=>'id','atribuiri_observatii_produse'=>'id'];
    $required=['company'=>['date_firma','serii_documente'],'users'=>['utilizatori'],'products'=>['produse_servicii']];
    foreach($required[$action] as $table) if(!isset($reply['tables'][$table])||!is_array($reply['tables'][$table]))throw new RuntimeException('Export online incomplet.');
    if($action==='users'&&!$reply['tables']['utilizatori'])throw new RuntimeException('Lista online de utilizatori este goală. Conturile locale au fost păstrate.');
    $db->beginTransaction();
    try {
        $count=0;
        foreach($reply['tables'] as $table=>$rows) {
            if(!isset($keys[$table]))throw new RuntimeException('Tabelă neașteptată în export.');
            if(!casa_rows($db,'PRAGMA table_info("'.$table.'")'))continue;
            if($table==='utilizatori')$db->exec('UPDATE utilizatori SET dezactivat=1');
            if($table==='produse_servicii')$db->exec('UPDATE produse_servicii SET activ=0');
            foreach($rows as $row) {
                if($table==='utilizatori'){unset($row['reset_token'],$row['reset_expires']);}
                if($table==='date_firma')unset($row['token_anaf']);
                casa_pull_save($db,$table,$keys[$table],$row,(int)$row[$keys[$table]],true);$count++;
            }
            if(in_array($table,['date_firma','serii_documente'],true))$db->prepare('INSERT OR REPLACE INTO casa_meta(name,value) VALUES(?,?)')->execute(['online_members_'.$table,json_encode(array_column($rows,$keys[$table]),JSON_THROW_ON_ERROR)]);
        }
        $db->prepare('INSERT OR REPLACE INTO casa_meta(name,value) VALUES(?,?)')->execute(['pull_'.$action,date('Y-m-d H:i:s')]);
        $db->commit();return $count;
    } catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function casa_pull_header(PDO $db,array $row): int {
    $online=(int)$row['id_factura'];
    $meta=casa_one($db,'SELECT * FROM casa_invoices WHERE online_id=?',[$online]);
    if($meta)return (int)$meta['id_factura'];
    $collision=casa_one($db,"SELECT f.id_factura FROM facturi f JOIN casa_invoices c ON c.id_factura=f.id_factura WHERE f.serie_factura=? AND f.nr_factura=? AND f.nr_factura>0 AND c.origin='local'",[$row['serie_factura'],$row['nr_factura']]);
    // Keep both documents with distinct local IDs. Expose the conflict, never merge by number.
    if($collision)$db->prepare('UPDATE casa_invoices SET error=? WHERE id_factura=?')->execute(['Conflict: seria și numărul sunt ocupate și de o factură online distinctă.',(int)$collision['id_factura']]);
    $row['id_client']=0;$row['id_deviz']=0;$row['nr_nota']=0;$row['nrbon']=0;$row['id_factura_stornare']=null;
    $local=casa_pull_save($db,'facturi','id_factura',$row);
    $db->prepare("UPDATE casa_invoices SET origin='historical',state='historical',online_id=? WHERE id_factura=?")->execute([$online,$local]);
    return $local;
}
function casa_pull_child(PDO $db,string $table,string $pk,array $row,int $invoice): int {
    $online=(int)$row[$pk];$map=casa_one($db,'SELECT local_id FROM casa_remote_rows WHERE entity=? AND online_id=?',[$table,$online]);
    $local=casa_pull_save($db,$table,$pk,$row,$map?(int)$map['local_id']:null);
    $db->prepare('INSERT OR REPLACE INTO casa_remote_rows(entity,online_id,local_id,id_factura) VALUES(?,?,?,?)')->execute([$table,$online,$local,$invoice]);
    return $local;
}
function casa_pull_invoices(PDO $db,array $reply): int {
    if(!isset($reply['documents'],$reply['next'],$reply['upper'],$reply['done'])||!is_array($reply['documents']))throw new RuntimeException('Exportul facturilor este incomplet.');
    $db->beginTransaction();
    try {
        foreach($reply['documents'] as $doc) {
            $row=$doc['factura'];$online=(int)$row['id_factura'];$owner=$doc['owner']??[];
            $ours=($owner['installation_uuid']??'')===casa_installation($db);
            if($ours) {
                $local=(int)$owner['source_id'];
                $meta=casa_one($db,"SELECT * FROM casa_invoices WHERE id_factura=? AND origin='local'",[$local]);
                if(!$meta)throw new RuntimeException('Identitatea instalării corespunde unui document local lipsă. Import oprit.');
                $db->prepare('UPDATE casa_invoices SET online_id=? WHERE id_factura=?')->execute([$online,$local]);
            } else $local=casa_pull_header($db,$row);
            $db->prepare('INSERT OR REPLACE INTO casa_remote_status(id_factura,payload,checked_at) VALUES(?,?,?)')->execute([$local,json_encode(['anaf'=>$doc['anaf'],'anaf_sent'=>$doc['anaf_sent'],'data_validare'=>$row['data_validare'],'data_incarcare'=>$row['data_incarcare'],'lifecycle'=>$doc['lifecycle']['state']??'online'],JSON_THROW_ON_ERROR),date('Y-m-d H:i:s')]);
            if($ours)continue; // Never overwrite unacknowledged local content or its outbox.
            $row['id_client']=0;
            if(!empty($doc['client']))$row['id_client']=casa_pull_child($db,'clienti','id_client',$doc['client'],$local);
            $row['id_deviz']=0;$row['nr_nota']=0;$row['nrbon']=0;$row['id_factura_stornare']=null;
            casa_pull_save($db,'facturi','id_factura',$row,$local);
            $lines=[];
            foreach($doc['vanzari'] as $line){$oid=(int)$line['id_vanz'];$line['id_factura']=$local;$line['nr_nota']=0;$line['id_deviz']=0;$lines[$oid]=casa_pull_child($db,'vanzari','id_vanz',$line,$local);}
            foreach($doc['miscari'] as $movement){if(!isset($lines[(int)$movement['id_vanz_fact']]))throw new RuntimeException('Mișcare fără linie de factură.');$movement['id_vanz_fact']=$lines[(int)$movement['id_vanz_fact']];$movement['id_doc']=$local;casa_pull_child($db,'miscari','id',$movement,$local);}
            foreach(($doc['local_receipts']??[]) as $map){
                if(casa_one($db,'SELECT 1 FROM incasari WHERE id_incasare=? AND id_factura=?',[(int)$map['local_id'],$local]))$db->prepare("INSERT OR REPLACE INTO casa_remote_rows(entity,online_id,local_id,id_factura) VALUES('incasari',?,?,?)")->execute([(int)$map['target_id'],(int)$map['local_id'],$local]);
            }
            foreach($doc['incasari'] as $receipt){$receipt['id_factura']=$local;casa_pull_child($db,'incasari','id_incasare',$receipt,$local);}
            foreach($doc['stornari'] as $storno){$reference=$storno['reference']??null;unset($storno['reference']);if(!$reference)throw new RuntimeException('Referința stornării lipsește online.');$storno['id_factura_stornata']=casa_pull_header($db,$reference);$storno['id_factura']=$local;casa_pull_child($db,'stornari','id_stornare',$storno,$local);}
            // Only previously imported rows are reconciled. Local receipts and user data are preserved.
            foreach(['vanzari'=>'id_vanz','miscari'=>'id','incasari'=>'id_incasare','stornari'=>'id_stornare'] as $table=>$pk){
                $ids=array_map('intval',array_column($doc[$table],$pk));
                foreach(casa_rows($db,'SELECT * FROM casa_remote_rows WHERE entity=? AND id_factura=?',[$table,$local]) as $map){
                    if(in_array((int)$map['online_id'],$ids,true))continue;
                    $prior=casa_one($db,'SELECT * FROM "'.$table.'" WHERE "'.$pk.'"=?',[(int)$map['local_id']]);
                    if($prior)$db->prepare('INSERT INTO casa_remote_archive(entity,local_id,payload,archived_at) VALUES(?,?,?,?)')->execute([$table,(int)$map['local_id'],json_encode($prior,JSON_THROW_ON_ERROR),date('Y-m-d H:i:s')]);
                    $db->prepare('DELETE FROM "'.$table.'" WHERE "'.$pk.'"=?')->execute([(int)$map['local_id']]);
                    $db->prepare('DELETE FROM casa_remote_rows WHERE entity=? AND online_id=?')->execute([$table,(int)$map['online_id']]);
                }
            }
        }
        if($reply['done'])$db->prepare("INSERT OR REPLACE INTO casa_meta(name,value) VALUES('pull_invoices',?)")->execute([date('Y-m-d H:i:s')]);
        $db->commit();return count($reply['documents']);
    } catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
