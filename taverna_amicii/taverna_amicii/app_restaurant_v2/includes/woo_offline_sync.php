<?php
declare(strict_types=1);

if (!function_exists('wooOfflineEnsureSchema')) {
    function wooOfflineEnsureSchema(PDO $pdo): void
    {
        $sql = [
            "CREATE TABLE IF NOT EXISTS woo_orders_inbox (
                woo_order_id TEXT PRIMARY KEY,
                order_number TEXT DEFAULT '', status TEXT DEFAULT '',
                date_created TEXT DEFAULT NULL, date_modified TEXT DEFAULT NULL,
                customer_name TEXT DEFAULT '', total REAL DEFAULT 0,
                shipping_total_incl_tax REAL DEFAULT 0,
                payload_json TEXT NOT NULL DEFAULT '{}',
                import_state TEXT NOT NULL DEFAULT 'new',
                mapping_error TEXT DEFAULT '', import_error TEXT DEFAULT '',
                imported_note_nrbon INTEGER DEFAULT NULL, imported_at TEXT DEFAULT NULL,
                ack_status TEXT NOT NULL DEFAULT 'not_ready', ack_attempts INTEGER NOT NULL DEFAULT 0,
                ack_error TEXT DEFAULT '', acknowledged_at TEXT DEFAULT NULL,
                fetched_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE INDEX IF NOT EXISTS idx_woo_orders_state ON woo_orders_inbox(import_state,date_created)",
            "CREATE INDEX IF NOT EXISTS idx_woo_orders_ack ON woo_orders_inbox(ack_status,import_state)",
            "CREATE TABLE IF NOT EXISTS woo_product_mapping (
                mapping_key TEXT PRIMARY KEY,
                woo_product_id TEXT DEFAULT '', woo_variation_id TEXT DEFAULT '', woo_type TEXT DEFAULT '',
                external_name TEXT DEFAULT '', cod_produs INTEGER NOT NULL,
                active INTEGER NOT NULL DEFAULT 1, updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE INDEX IF NOT EXISTS idx_woo_mapping_cod_produs ON woo_product_mapping(cod_produs)",
            "CREATE TABLE IF NOT EXISTS woo_sync_state (
                id INTEGER PRIMARY KEY CHECK(id=1), last_modified_cursor TEXT DEFAULT NULL,
                last_sync_at TEXT DEFAULT NULL, last_sync_success_at TEXT DEFAULT NULL,
                last_error TEXT DEFAULT '', updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )",
            "INSERT OR IGNORE INTO woo_sync_state(id) VALUES(1)",
            "CREATE TABLE IF NOT EXISTS woo_sync_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT, action TEXT NOT NULL DEFAULT '', status TEXT NOT NULL DEFAULT '',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP, received_count INTEGER DEFAULT 0,
                inserted_count INTEGER DEFAULT 0, updated_count INTEGER DEFAULT 0,
                acknowledged_count INTEGER DEFAULT 0, http_code INTEGER DEFAULT 0, message TEXT DEFAULT ''
            )",
            "CREATE INDEX IF NOT EXISTS idx_woo_sync_log_created ON woo_sync_log(created_at)"
        ];
        foreach ($sql as $statement) {
            $pdo->exec($statement);
        }
    }
}

if (!function_exists('wooOfflineConfig')) {
    function wooOfflineConfig(): array
    {
        $cfg = require __DIR__ . '/woo_sync_config.php';
        if (!is_array($cfg)) {
            throw new RuntimeException('Configuratia WooCommerce este invalida.');
        }
        return $cfg;
    }
}

if (!function_exists('wooOfflineLog')) {
    function wooOfflineLog(PDO $pdo, string $action, string $status, array $counts = [], int $http = 0, string $message = ''): void
    {
        $stmt = $pdo->prepare('INSERT INTO woo_sync_log(action,status,created_at,received_count,inserted_count,updated_count,acknowledged_count,http_code,message) VALUES(?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$action,$status,date('Y-m-d H:i:s'),(int)($counts['received']??0),(int)($counts['inserted']??0),(int)($counts['updated']??0),(int)($counts['acknowledged']??0),$http,substr($message,0,1000)]);
    }
}

if (!function_exists('wooOfflineHttp')) {
    function wooOfflineHttp(array $cfg, string $method, string $path, array $query = [], ?array $payload = null): array
    {
        $base = rtrim(trim((string)($cfg['base_url'] ?? '')), '/');
        $key = trim((string)($cfg['api_key'] ?? ''));
        $secret = trim((string)($cfg['api_secret'] ?? ''));
        if ($base === '') {
            throw new RuntimeException('Lipseste base_url WooCommerce.');
        }
        if ($key === '' || $secret === '') {
            throw new RuntimeException('Lipsesc API key / API secret. Copiaza includes/woo_sync_config.local.example.php ca woo_sync_config.local.php si completeaza cheile pluginului WordPress.');
        }
        $url = $base . '/' . ltrim($path, '/');
        if ($query) {
            $url .= '?' . http_build_query($query);
        }
        $headers = ['Accept: application/json','X-RS-KEY: '.$key,'X-RS-SECRET: '.$secret];
        if (!empty($cfg['use_hmac'])) {
            $timestamp = (string)time();
            $routePath = (string)(parse_url($url, PHP_URL_PATH) ?: '');
            $canonicalQuery = [];
            foreach ($query as $k => $v) {
                $canonicalQuery[$k] = is_scalar($v) ? (string)$v : $v;
            }
            $canonical = strtoupper($method)."\n".$routePath."\n".$timestamp."\n".json_encode($canonicalQuery,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            $headers[] = 'X-RS-TIMESTAMP: '.$timestamp;
            $headers[] = 'X-RS-SIGNATURE: '.hash_hmac('sha256',$canonical,$secret);
        }
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json; charset=utf-8';
        }
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Nu se poate initializa cURL.');
        }
        curl_setopt_array($ch,[
            CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,
            CURLOPT_TIMEOUT=>max(10,(int)($cfg['timeout']??20)),
            CURLOPT_SSL_VERIFYPEER=>!empty($cfg['verify_ssl']),
            CURLOPT_SSL_VERIFYHOST=>!empty($cfg['verify_ssl'])?2:0,
            CURLOPT_CUSTOMREQUEST=>strtoupper($method),CURLOPT_HTTPHEADER=>$headers,
        ]);
        if ($payload !== null) {
            $json = json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                curl_close($ch);
                throw new RuntimeException('Payload Woo invalid.');
            }
            curl_setopt($ch,CURLOPT_POSTFIELDS,$json);
        }
        $raw = curl_exec($ch);
        $curlError = curl_error($ch);
        $http = (int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false) {
            throw new RuntimeException('Conectarea la WooCommerce a esuat: '.$curlError);
        }
        $body = json_decode((string)$raw,true);
        if (!is_array($body)) {
            throw new RuntimeException('WooCommerce a returnat JSON invalid (HTTP '.$http.').');
        }
        if ($http < 200 || $http >= 300 || (array_key_exists('success',$body) && empty($body['success']))) {
            throw new RuntimeException('WooCommerce HTTP '.$http.': '.(string)($body['message']??$raw));
        }
        return ['http_code'=>$http,'body'=>$body];
    }
}

if (!function_exists('wooOfflineMappingKey')) {
    function wooOfflineMappingKey(array $product): string
    {
        $variation = trim((string)($product['variation_id']??''));
        if ($variation !== '' && $variation !== '0') {
            return 'variation:'.$variation;
        }
        return 'product:'.trim((string)($product['product_id']??$product['id']??'0'));
    }
}

if (!function_exists('wooOfflineShippingKey')) {
    function wooOfflineShippingKey(float $amount): string
    {
        return 'shipping:'.number_format($amount,2,'.','');
    }
}

if (!function_exists('wooOfflineLegacyMapping')) {
    function wooOfflineLegacyMapping(PDO $pdo, array $product): ?int
    {
        $ids = [];
        $variation = trim((string)($product['variation_id']??''));
        $parent = trim((string)($product['product_id']??''));
        if ($variation !== '' && $variation !== '0') $ids[] = $variation;
        if ($parent !== '' && $parent !== '0') $ids[] = $parent;
        $stmt = $pdo->prepare('SELECT cod_produs FROM produse_servicii WHERE CAST(woo_product_id AS TEXT)=? AND activ=1 LIMIT 1');
        foreach ($ids as $id) {
            if (!ctype_digit($id)) continue;
            $stmt->execute([$id]);
            $cod = $stmt->fetchColumn();
            if ($cod !== false && (int)$cod > 0) return (int)$cod;
        }
        return null;
    }
}

if (!function_exists('wooOfflineMappedProduct')) {
    function wooOfflineMappedProduct(PDO $pdo, string $key, ?array $product = null): ?int
    {
        $stmt = $pdo->prepare('SELECT cod_produs FROM woo_product_mapping WHERE mapping_key=? AND active=1 LIMIT 1');
        $stmt->execute([$key]);
        $cod = $stmt->fetchColumn();
        if ($cod !== false && (int)$cod > 0) return (int)$cod;
        if ($product === null || strpos($key,'shipping:') === 0) return null;
        $cod = wooOfflineLegacyMapping($pdo,$product);
        if ($cod === null) return null;
        $variation = trim((string)($product['variation_id']??''));
        $parent = trim((string)($product['product_id']??''));
        $type = trim((string)($product['woo_tip']??($variation!==''&&$variation!=='0'?'variation':'simple')));
        $save = $pdo->prepare('INSERT OR REPLACE INTO woo_product_mapping(mapping_key,woo_product_id,woo_variation_id,woo_type,external_name,cod_produs,active,updated_at) VALUES(?,?,?,?,?,?,1,?)');
        $save->execute([$key,$parent,$variation,$type,(string)($product['name']??''),$cod,date('Y-m-d H:i:s')]);
        return $cod;
    }
}

if (!function_exists('wooOfflineMissingMappings')) {
    function wooOfflineMissingMappings(PDO $pdo, array $order): array
    {
        $missing = [];
        foreach ((array)($order['products']??[]) as $product) {
            if (!is_array($product)) continue;
            $key = wooOfflineMappingKey($product);
            if (wooOfflineMappedProduct($pdo,$key,$product) === null) {
                $missing[$key] = [
                    'mapping_key'=>$key,'woo_product_id'=>(string)($product['product_id']??''),
                    'woo_variation_id'=>(string)($product['variation_id']??''),'woo_type'=>(string)($product['woo_tip']??''),
                    'external_name'=>(string)($product['name']??$key),
                ];
            }
        }
        $pickup = !empty($order['is_pickup']) || !empty($order['shipping_is_pickup']) || (string)($order['shipping']['type']??'') === 'pickup';
        $shipping = (float)($order['shipping_total_incl_tax']??($order['shipping']['total_incl_tax']??0));
        if (!$pickup && $shipping > 0.0001) {
            $key = wooOfflineShippingKey($shipping);
            if (wooOfflineMappedProduct($pdo,$key) === null) {
                $title = trim((string)($order['shipping_method_title']??$order['shipping']['method_title']??'Livrare'));
                $missing[$key] = [
                    'mapping_key'=>$key,'woo_product_id'=>'','woo_variation_id'=>'','woo_type'=>'shipping',
                    'external_name'=>($title!==''?$title:'Livrare').' - '.number_format($shipping,2,',','.').' lei',
                ];
            }
        }
        return array_values($missing);
    }
}

if (!function_exists('wooOfflineStoreOrder')) {
    function wooOfflineStoreOrder(PDO $pdo, array $order): string
    {
        $id = trim((string)($order['id']??''));
        if ($id === '') throw new RuntimeException('Comanda Woo fara ID.');
        $q = $pdo->prepare('SELECT import_state,payload_json FROM woo_orders_inbox WHERE woo_order_id=?');
        $q->execute([$id]);
        $old = $q->fetch(PDO::FETCH_ASSOC);
        if ($old && (string)$old['import_state'] === 'imported') return 'preserved';
        $json = json_encode($order,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if ($json === false) throw new RuntimeException('Payload Woo neserializabil pentru #'.$id.'.');
        $state = wooOfflineMissingMappings($pdo,$order) ? 'mapping_error' : 'ready';
        $mappingError = $state === 'mapping_error' ? 'Exista produse sau transport fara mapare POS.' : '';
        $values = [
            (string)($order['number']??$id),(string)($order['status']??''),$order['date_created']??null,$order['date_modified']??null,
            trim((string)($order['customer']['name']??'')),(float)($order['total']??0),(float)($order['shipping_total_incl_tax']??0),
            $json,$state,$mappingError,date('Y-m-d H:i:s'),date('Y-m-d H:i:s'),$id
        ];
        if ($old) {
            $stmt=$pdo->prepare("UPDATE woo_orders_inbox SET order_number=?,status=?,date_created=?,date_modified=?,customer_name=?,total=?,shipping_total_incl_tax=?,payload_json=?,import_state=?,mapping_error=?,fetched_at=?,updated_at=? WHERE woo_order_id=? AND import_state<>'imported'");
            $stmt->execute($values);
            return hash_equals((string)$old['payload_json'],$json)?'unchanged':'updated';
        }
        $stmt=$pdo->prepare('INSERT INTO woo_orders_inbox(order_number,status,date_created,date_modified,customer_name,total,shipping_total_incl_tax,payload_json,import_state,mapping_error,fetched_at,updated_at,woo_order_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute($values);
        return 'inserted';
    }
}

if (!function_exists('wooOfflineSync')) {
    function wooOfflineSync(PDO $pdo, array $cfg): array
    {
        $state=$pdo->query('SELECT * FROM woo_sync_state WHERE id=1')->fetch(PDO::FETCH_ASSOC)?:[];
        $cursor=trim((string)($state['last_modified_cursor']??''));
        $base=['per_page'=>100,'unacknowledged'=>1];
        if (!empty($cfg['statuses'])) $base['status']=is_array($cfg['statuses'])?implode(',',$cfg['statuses']):(string)$cfg['statuses'];
        if ($cursor!=='') {
            $ts=strtotime($cursor);
            if ($ts!==false) $base['modified_after']=date('Y-m-d H:i:s',$ts-2);
        } else {
            $days=max(1,(int)($cfg['initial_lookback_days']??7));
            $base['date_from']=date('Y-m-d',strtotime('-'.$days.' days'));
        }
        $counts=['received'=>0,'inserted'=>0,'updated'=>0,'acknowledged'=>0];
        $maxModified=$cursor;$lastHttp=0;
        try {
            for($page=1;$page<=100;$page++) {
                $query=$base;$query['page']=$page;
                $response=wooOfflineHttp($cfg,'GET','orders',$query);$lastHttp=(int)$response['http_code'];$body=$response['body'];
                if ((int)($cfg['client_id']??0)>0 && isset($body['client_id']) && (int)$body['client_id']!==(int)$cfg['client_id']) throw new RuntimeException('API Woo configurat pentru alt client_id.');
                if ((int)($cfg['location_id']??0)>0 && isset($body['location_id']) && (int)$body['location_id']!==(int)$cfg['location_id']) throw new RuntimeException('API Woo configurat pentru alta locatie.');
                $orders=is_array($body['data']??null)?$body['data']:[];$counts['received']+=count($orders);
                $pdo->beginTransaction();
                try {
                    foreach($orders as $order) {
                        if(!is_array($order)) continue;
                        $change=wooOfflineStoreOrder($pdo,$order);
                        if($change==='inserted')$counts['inserted']++; elseif($change==='updated')$counts['updated']++;
                        $modified=trim((string)($order['date_modified']??''));
                        if($modified!==''&&($maxModified===''||strtotime($modified)>strtotime($maxModified)))$maxModified=$modified;
                    }
                    $pdo->commit();
                } catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); throw $e; }
                $pagination=is_array($body['pagination']??null)?$body['pagination']:[];
                if($page>=max(1,(int)($pagination['total_pages']??1))||!$orders)break;
            }
            $now=date('Y-m-d H:i:s');
            $stmt=$pdo->prepare("UPDATE woo_sync_state SET last_modified_cursor=?,last_sync_at=?,last_sync_success_at=?,last_error='',updated_at=? WHERE id=1");
            $stmt->execute([$maxModified!==''?$maxModified:$cursor,$now,$now,$now]);
            wooOfflineLog($pdo,'pull','success',$counts,$lastHttp,'Sincronizare Woo finalizata.');
            return $counts;
        } catch(Throwable $e) {
            $now=date('Y-m-d H:i:s');$stmt=$pdo->prepare('UPDATE woo_sync_state SET last_sync_at=?,last_error=?,updated_at=? WHERE id=1');$stmt->execute([$now,substr($e->getMessage(),0,1000),$now]);
            wooOfflineLog($pdo,'pull','error',$counts,$lastHttp,$e->getMessage());throw $e;
        }
    }
}

if (!function_exists('wooOfflineOrder')) {
    function wooOfflineOrder(PDO $pdo, string $id): array
    {
        $stmt=$pdo->prepare('SELECT payload_json FROM woo_orders_inbox WHERE woo_order_id=? LIMIT 1');$stmt->execute([$id]);$json=$stmt->fetchColumn();
        if($json===false)throw new RuntimeException('Comanda Woo nu exista in inboxul local.');
        $order=json_decode((string)$json,true);if(!is_array($order))throw new RuntimeException('Payload local Woo invalid.');return $order;
    }
}

if (!function_exists('wooOfflinePosProduct')) {
    function wooOfflinePosProduct(PDO $pdo, int $cod): array
    {
        $stmt=$pdo->prepare('SELECT cod_produs,nume,pret_cu_tva,cota_tva,departament,dep_casa_marcat,fel_mancare FROM produse_servicii WHERE cod_produs=? AND activ=1 LIMIT 1');$stmt->execute([$cod]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$row)throw new RuntimeException('Produsul POS '.$cod.' nu mai este activ/disponibil.');return $row;
    }
}

if (!function_exists('wooOfflineObservation')) {
    function wooOfflineObservation(array $product): string
    {
        $parts=[];$note=trim((string)($product['notes']??''));if($note!=='')$parts[]=$note;
        foreach((array)($product['options']??[]) as $k=>$v){if(is_array($v)||is_object($v))$v=json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$v=trim((string)$v);if($v!=='')$parts[]=trim((string)$k).': '.$v;}
        return substr(implode(' | ',array_unique($parts)),0,100);
    }
}

if (!function_exists('wooOfflineResolveNote')) {
    function wooOfflineResolveNote(PDO $pdo,int $operator,int $location,string $mode,int $currentNote,int $table): array
    {
        if($mode==='current_note'){
            if($currentNote<=0)throw new RuntimeException('Nu exista nota curenta pentru import.');
            $stmt=$pdo->prepare("SELECT nrbon,cod_masa FROM note WHERE nrbon=? AND operator=? AND locatie=? AND status='S' LIMIT 1");$stmt->execute([$currentNote,$operator,$location]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
            if(!$row)throw new RuntimeException('Nota curenta nu mai este deschisa pentru acest operator.');return ['nr_bon'=>(int)$row['nrbon'],'cod_masa'=>(int)$row['cod_masa']];
        }
        if($table<=0)throw new RuntimeException('Selecteaza masa tinta.');
        $stmt=$pdo->prepare("SELECT nrbon,operator FROM note WHERE cod_masa=? AND locatie=? AND status='S' ORDER BY nrbon DESC LIMIT 1");$stmt->execute([$table,$location]);$open=$stmt->fetch(PDO::FETCH_ASSOC);
        if($open){if((int)$open['operator']!==$operator)throw new RuntimeException('Masa este deschisa la alt operator.');return ['nr_bon'=>(int)$open['nrbon'],'cod_masa'=>$table];}
        $next=(int)$pdo->query('SELECT COALESCE(MAX(nrbon),0)+1 FROM note')->fetchColumn();
        $stmt=$pdo->prepare("INSERT INTO note(nrbon,operator,locatie,cod_masa,data_deschidere,data_bon,ora_bon,status,listat_nota_plata,fiscalizat) VALUES(?,?,?,?,?,?,?,'S',0,0)");
        $stmt->execute([$next,$operator,$location,$table,date('Y-m-d H:i:s'),date('Y-m-d'),date('H:i:s')]);$pdo->prepare('UPDATE mese SET stare=1 WHERE cod_masa=? AND cod_locatie=?')->execute([$table,$location]);
        return ['nr_bon'=>$next,'cod_masa'=>$table];
    }
}

if (!function_exists('wooOfflineInsertLine')) {
    function wooOfflineInsertLine(PDO $pdo,int $noteId,int $cod,float $qty,float $lineIncl,string $obs): void
    {
        if($qty<=0)throw new RuntimeException('Cantitate Woo invalida pentru produsul POS '.$cod.'.');$pos=wooOfflinePosProduct($pdo,$cod);$vat=(float)($pos['cota_tva']??0);
        if($lineIncl<=0)$lineIncl=(float)($pos['pret_cu_tva']??0)*$qty;$unit=$lineIncl/$qty;$coef=1+$vat/100;$excl=$coef>0?$lineIncl/$coef:$lineIncl;$vatValue=$lineIncl-$excl;$dep=trim((string)($pos['departament']??''));
        $stmt=$pdo->prepare("INSERT INTO det_note(nr_bon,cod_p,nume_produs,cantitate,cota_tva,tva_col,pret_vanzare,valoare_vanzare,valoare_vanzare_cu_tva,discount,pachet,preparat,t_list,cod_meniu,preluat_osp,prioritate,importat_din_site,departament_listare,observatie_produs,data,ora) VALUES(?,?,?,?,?,?,?,?,?,0,0,0,0,0,0,0,NULL,?,?,?,?)");
        $stmt->execute([$noteId,$cod,(string)$pos['nume'],$qty,$vat,round($vatValue,2),round($unit,4),round($excl,2),round($lineIncl,2),$dep!==''?$dep:null,$obs,date('Y-m-d'),date('H:i:s')]);
    }
}

if (!function_exists('wooOfflineImport')) {
    function wooOfflineImport(PDO $pdo,string $wooId,int $operator,int $location,string $mode,int $currentNote,int $table): int
    {
        $stmt=$pdo->prepare('SELECT import_state FROM woo_orders_inbox WHERE woo_order_id=?');$stmt->execute([$wooId]);if((string)$stmt->fetchColumn()==='imported')throw new RuntimeException('Comanda Woo a fost deja importata local.');
        $order=wooOfflineOrder($pdo,$wooId);$missing=wooOfflineMissingMappings($pdo,$order);
        if($missing){$pdo->prepare("UPDATE woo_orders_inbox SET import_state='mapping_error',mapping_error=?,updated_at=? WHERE woo_order_id=?")->execute(['Exista '.count($missing).' mapari lipsa.',date('Y-m-d H:i:s'),$wooId]);throw new RuntimeException('Comanda are produse/transport nemapate.');}
        $pdo->beginTransaction();
        try{
            $target=wooOfflineResolveNote($pdo,$operator,$location,$mode,$currentNote,$table);$noteId=(int)$target['nr_bon'];
            foreach((array)($order['products']??[]) as $product){if(!is_array($product))continue;$key=wooOfflineMappingKey($product);$cod=wooOfflineMappedProduct($pdo,$key,$product);if($cod===null)throw new RuntimeException('Maparea a disparut pentru '.$key.'.');$qty=(float)($product['quantity']??0);$incl=(float)($product['line_total']??0)+(float)($product['line_total_tax']??0);if(abs($incl)<0.0001)$incl=(float)($product['price']??0)*$qty;wooOfflineInsertLine($pdo,$noteId,$cod,$qty,$incl,wooOfflineObservation($product));}
            $pickup=!empty($order['is_pickup'])||!empty($order['shipping_is_pickup'])||(string)($order['shipping']['type']??'')==='pickup';$shipping=(float)($order['shipping_total_incl_tax']??($order['shipping']['total_incl_tax']??0));
            if(!$pickup&&$shipping>0.0001){$key=wooOfflineShippingKey($shipping);$cod=wooOfflineMappedProduct($pdo,$key);if($cod===null)throw new RuntimeException('Lipseste maparea transportului.');wooOfflineInsertLine($pdo,$noteId,$cod,1.0,$shipping,'Transport WooCommerce');}
            $stmt=$pdo->prepare("UPDATE note SET valoare_vanzare_cu_tva=(SELECT ROUND(COALESCE(SUM(valoare_vanzare_cu_tva),0),2) FROM det_note WHERE nr_bon=?),tva_colectata=(SELECT ROUND(COALESCE(SUM(tva_col),0),2) FROM det_note WHERE nr_bon=?),discount=(SELECT ROUND(COALESCE(SUM(discount),0),2) FROM det_note WHERE nr_bon=?) WHERE nrbon=?");$stmt->execute([$noteId,$noteId,$noteId,$noteId]);
            $now=date('Y-m-d H:i:s');$stmt=$pdo->prepare("UPDATE woo_orders_inbox SET import_state='imported',mapping_error='',import_error='',imported_note_nrbon=?,imported_at=?,ack_status='pending',ack_error='',updated_at=? WHERE woo_order_id=? AND import_state<>'imported'");$stmt->execute([$noteId,$now,$now,$wooId]);if($stmt->rowCount()!==1)throw new RuntimeException('Comanda nu mai este disponibila pentru import.');
            $_SESSION['nr_bon']=$noteId;if((int)$target['cod_masa']>0)$_SESSION['masa_curenta']=(int)$target['cod_masa'];$_SESSION['trimis_comanda']=0;
            if(function_exists('restaurantTouchUltimBonConectat'))restaurantTouchUltimBonConectat($pdo,$location,$noteId,$now);
            $pdo->commit();return $noteId;
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$pdo->prepare("UPDATE woo_orders_inbox SET import_error=?,updated_at=? WHERE woo_order_id=? AND import_state<>'imported'")->execute([substr($e->getMessage(),0,1000),date('Y-m-d H:i:s'),$wooId]);throw $e;}
    }
}

if (!function_exists('wooOfflineAck')) {
    function wooOfflineAck(PDO $pdo,array $cfg,string $wooId,int $noteId,string $installationId): bool
    {
        try{
            wooOfflineHttp($cfg,'POST','orders/'.rawurlencode($wooId).'/acknowledge',[],['installation_id'=>$installationId,'imported_at'=>date('Y-m-d H:i:s'),'local_note_id'=>(string)$noteId,'result'=>'success','message'=>'Import WooCommerce finalizat in AGECS offline.']);
            $now=date('Y-m-d H:i:s');$pdo->prepare("UPDATE woo_orders_inbox SET ack_status='acknowledged',acknowledged_at=?,ack_error='',updated_at=? WHERE woo_order_id=?")->execute([$now,$now,$wooId]);wooOfflineLog($pdo,'ack','success',['acknowledged'=>1],200,'Woo #'.$wooId);return true;
        }catch(Throwable $e){$pdo->prepare("UPDATE woo_orders_inbox SET ack_status='pending',ack_attempts=ack_attempts+1,ack_error=?,updated_at=? WHERE woo_order_id=?")->execute([substr($e->getMessage(),0,1000),date('Y-m-d H:i:s'),$wooId]);wooOfflineLog($pdo,'ack','error',[],0,$e->getMessage());return false;}
    }
}

if (!function_exists('wooOfflineRetryAcks')) {
    function wooOfflineRetryAcks(PDO $pdo,array $cfg,string $installationId): int
    {
        $rows=$pdo->query("SELECT woo_order_id,imported_note_nrbon FROM woo_orders_inbox WHERE import_state='imported' AND ack_status='pending' ORDER BY imported_at LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);$count=0;
        foreach($rows as $row)if(wooOfflineAck($pdo,$cfg,(string)$row['woo_order_id'],(int)$row['imported_note_nrbon'],$installationId))$count++;return $count;
    }
}
