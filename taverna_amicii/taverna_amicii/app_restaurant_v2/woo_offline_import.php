<?php
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('log_errors','1');
date_default_timezone_set('Europe/Bucharest');
require_once __DIR__.'/session.php';
require_once __DIR__.'/includes/woo_offline_sync.php';

if(!isset($pdo)||!($pdo instanceof PDO)){http_response_code(500);exit('Conexiunea locala nu este disponibila.');}
if(!function_exists('restaurantIsOfflineSqlite')||!restaurantIsOfflineSqlite()){header('Location: vanzare_importa_comanda_woo.php');exit;}
if(!isset($_SESSION['admin_id'],$_SESSION['cod_locatie'])){header('Location: logout.php');exit;}

function wooOfflineH($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}

$operator=(int)$_SESSION['admin_id'];
$location=(int)$_SESSION['cod_locatie'];
$currentNote=(int)($_SESSION['nr_bon']??0);
wooOfflineEnsureSchema($pdo);
$cfg=wooOfflineConfig();
$installationId=trim((string)($restaurantConfig['installation_uuid']??''));
$message=(string)($_SESSION['woo_offline_flash']??'');unset($_SESSION['woo_offline_flash']);
$error='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    $action=(string)($_POST['action']??'');
    try{
        if($action==='sync'){
            $counts=wooOfflineSync($pdo,$cfg);$acks=wooOfflineRetryAcks($pdo,$cfg,$installationId);
            $message='Sincronizare finalizata: '.$counts['received'].' primite, '.$counts['inserted'].' noi, '.$counts['updated'].' actualizate, '.$acks.' confirmari retrimise.';
        }elseif($action==='save_mapping'){
            $key=trim((string)($_POST['mapping_key']??''));$cod=(int)($_POST['cod_produs']??0);
            if($key===''||$cod<=0)throw new RuntimeException('Maparea este invalida.');
            wooOfflinePosProduct($pdo,$cod);
            $stmt=$pdo->prepare('INSERT OR REPLACE INTO woo_product_mapping(mapping_key,woo_product_id,woo_variation_id,woo_type,external_name,cod_produs,active,updated_at) VALUES(?,?,?,?,?,?,1,?)');
            $stmt->execute([$key,trim((string)($_POST['woo_product_id']??'')),trim((string)($_POST['woo_variation_id']??'')),trim((string)($_POST['woo_type']??'')),trim((string)($_POST['external_name']??'')),$cod,date('Y-m-d H:i:s')]);
            $rows=$pdo->query("SELECT woo_order_id,payload_json FROM woo_orders_inbox WHERE import_state IN ('new','ready','mapping_error')")->fetchAll(PDO::FETCH_ASSOC);
            foreach($rows as $row){$order=json_decode((string)$row['payload_json'],true);if(is_array($order)&&!wooOfflineMissingMappings($pdo,$order))$pdo->prepare("UPDATE woo_orders_inbox SET import_state='ready',mapping_error='',updated_at=? WHERE woo_order_id=? AND import_state<>'imported'")->execute([date('Y-m-d H:i:s'),(string)$row['woo_order_id']]);}
            $_SESSION['woo_offline_flash']='Maparea a fost salvata.';header('Location: woo_offline_import.php');exit;
        }elseif($action==='import'){
            $wooId=trim((string)($_POST['woo_order_id']??''));$mode=(string)($_POST['target_mode']??'table');if(!in_array($mode,['table','current_note'],true))$mode='table';$table=(int)($_POST['cod_masa_target']??0);
            $note=wooOfflineImport($pdo,$wooId,$operator,$location,$mode,$currentNote,$table);wooOfflineAck($pdo,$cfg,$wooId,$note,$installationId);header('Location: vanzare_restaurant.php');exit;
        }elseif($action==='retry_ack'){
            if(!wooOfflineAck($pdo,$cfg,trim((string)($_POST['woo_order_id']??'')),(int)($_POST['note_id']??0),$installationId))throw new RuntimeException('Confirmarea Woo nu a reusit. Comanda ramane importata local si va fi retrimisa.');
            $message='Confirmarea Woo a fost retrimisa.';
        }
    }catch(Throwable $e){$error=$e->getMessage();}
}

if($_SERVER['REQUEST_METHOD']==='GET'&&!isset($_GET['nosync'])){
    try{$last=$pdo->query('SELECT last_sync_success_at FROM woo_sync_state WHERE id=1')->fetchColumn();$interval=max(15,(int)($cfg['automatic_interval_seconds']??30));if(!$last||strtotime((string)$last)<=time()-$interval){wooOfflineSync($pdo,$cfg);wooOfflineRetryAcks($pdo,$cfg,$installationId);}}
    catch(Throwable $e){$error='Comenzile deja salvate local raman disponibile, dar sincronizarea online nu a reusit: '.$e->getMessage();}
}

$pending=$pdo->query("SELECT * FROM woo_orders_inbox WHERE import_state<>'imported' ORDER BY COALESCE(date_created,fetched_at) DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC)?:[];
$imported=$pdo->query("SELECT * FROM woo_orders_inbox WHERE import_state='imported' ORDER BY imported_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC)?:[];
$missing=[];
foreach($pending as $row){$order=json_decode((string)$row['payload_json'],true);if(!is_array($order))continue;foreach(wooOfflineMissingMappings($pdo,$order) as $m)$missing[$m['mapping_key']]=$m;}
$products=$pdo->query('SELECT cod_produs,nume,pret_cu_tva,woo_product_id FROM produse_servicii WHERE activ=1 AND se_vinde=1 ORDER BY nume,cod_produs')->fetchAll(PDO::FETCH_ASSOC)?:[];
$tableStmt=$pdo->prepare("SELECT m.cod_masa,m.nume_masa,(SELECT operator FROM note n WHERE n.cod_masa=m.cod_masa AND n.locatie=? AND n.status='S' ORDER BY nrbon DESC LIMIT 1) open_operator FROM mese m WHERE m.cod_locatie=? ORDER BY m.cod_masa");$tableStmt->execute([$location,$location]);$tables=$tableStmt->fetchAll(PDO::FETCH_ASSOC)?:[];
$currentValid=false;if($currentNote>0){$stmt=$pdo->prepare("SELECT COUNT(*) FROM note WHERE nrbon=? AND operator=? AND locatie=? AND status='S'");$stmt->execute([$currentNote,$operator,$location]);$currentValid=(int)$stmt->fetchColumn()>0;}
$sync=$pdo->query('SELECT * FROM woo_sync_state WHERE id=1')->fetch(PDO::FETCH_ASSOC)?:[];
?>
<!doctype html><html lang="ro"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Comenzi WooCommerce - Taverna Amicii</title><link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css"><style>body{background:#f5f6f8}.head{background:#1f2937;color:#fff}.card{border:0;box-shadow:0 2px 8px #0001}.map{border-left:5px solid #f0ad4e}.order{border-left:5px solid #0d6efd}.done{border-left:5px solid #198754}.muted{font-size:.85rem;color:#6c757d}.items{font-size:.92rem}</style></head><body>
<div class="head p-3"><div class="container-fluid d-flex justify-content-between align-items-center"><div><h3 class="mb-1">Comenzi WooCommerce - Taverna Amicii</h3><small><?=wooOfflineH($cfg['base_url']??'')?> · client <?=wooOfflineH($cfg['client_id']??'')?> / locatie <?=wooOfflineH($cfg['location_id']??'')?></small></div><a href="vanzare_restaurant.php" class="btn btn-light">Inapoi la vanzare</a></div></div>
<div class="container-fluid p-3">
<?php if($message!==''):?><div class="alert alert-success"><?=wooOfflineH($message)?></div><?php endif;?><?php if($error!==''):?><div class="alert alert-warning"><?=wooOfflineH($error)?></div><?php endif;?>
<div class="card mb-3"><div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2"><div><strong>Sincronizare REST</strong><div class="muted">Ultima reusita: <?=wooOfflineH($sync['last_sync_success_at']??'-')?> · cursor modificare: <?=wooOfflineH($sync['last_modified_cursor']??'-')?></div></div><form method="post"><input type="hidden" name="action" value="sync"><button class="btn btn-primary">Sincronizeaza acum</button></form></div></div>

<?php if($missing):?><div class="card map mb-3"><div class="card-header bg-white"><strong>Mapari necesare (<?=count($missing)?>)</strong><div class="muted">Produsele si transportul se mapeaza explicit spre nomenclatorul POS. Transportul de 10/15 lei nu are cod POS hardcodat.</div></div><div class="card-body">
<?php foreach($missing as $m):?><form method="post" class="row g-2 align-items-center border-bottom pb-2 mb-2"><input type="hidden" name="action" value="save_mapping"><input type="hidden" name="mapping_key" value="<?=wooOfflineH($m['mapping_key'])?>"><input type="hidden" name="woo_product_id" value="<?=wooOfflineH($m['woo_product_id'])?>"><input type="hidden" name="woo_variation_id" value="<?=wooOfflineH($m['woo_variation_id'])?>"><input type="hidden" name="woo_type" value="<?=wooOfflineH($m['woo_type'])?>"><input type="hidden" name="external_name" value="<?=wooOfflineH($m['external_name'])?>"><div class="col-lg-4"><strong><?=wooOfflineH($m['external_name'])?></strong><div class="muted"><?=wooOfflineH($m['mapping_key'])?></div></div><div class="col-lg-6"><select class="form-select" name="cod_produs" required><option value="">-- produs POS --</option><?php foreach($products as $p):?><option value="<?=(int)$p['cod_produs']?>"><?=wooOfflineH($p['nume'])?> [<?=(int)$p['cod_produs']?>] - <?=number_format((float)$p['pret_cu_tva'],2,',','.')?> lei</option><?php endforeach;?></select></div><div class="col-lg-2"><button class="btn btn-warning w-100">Salveaza</button></div></form><?php endforeach;?>
</div></div><?php endif;?>

<h5>Comenzi de importat (<?=count($pending)?>)</h5><?php if(!$pending):?><div class="alert alert-light border">Nu exista comenzi Woo neimportate in inboxul local.</div><?php endif;?><div class="row g-3 mb-4">
<?php foreach($pending as $row):$order=json_decode((string)$row['payload_json'],true);if(!is_array($order))continue;$miss=wooOfflineMissingMappings($pdo,$order);?><div class="col-xl-6"><div class="card order h-100"><div class="card-body"><div class="d-flex justify-content-between"><div><h5 class="mb-0">#<?=wooOfflineH($row['order_number']?:$row['woo_order_id'])?></h5><div class="muted"><?=wooOfflineH($row['date_created'])?> · <?=wooOfflineH($row['customer_name'])?></div></div><div class="text-end"><strong><?=number_format((float)$row['total'],2,',','.')?> lei</strong><div class="<?= $miss?'text-warning':'text-success' ?>"><?= $miss?'MAPARE NECESARA':'GATA DE IMPORT' ?></div></div></div><ul class="items mt-3"><?php foreach((array)($order['products']??[]) as $p):?><li><?=wooOfflineH($p['quantity']??0)?> x <?=wooOfflineH($p['name']??'')?><?php if(trim((string)($p['notes']??''))!==''):?> — <em><?=wooOfflineH($p['notes'])?></em><?php endif;?></li><?php endforeach;?></ul><div class="muted mb-3">Livrare: <?=wooOfflineH($order['shipping_method_title']??$order['shipping']['method_title']??'-')?> · <?=number_format((float)($order['shipping_total_incl_tax']??0),2,',','.')?> lei · Plata: <?=wooOfflineH($order['payment_method_title']??$order['payment_method']??'-')?></div>
<?php if(!$miss):?><form method="post" class="row g-2"><input type="hidden" name="action" value="import"><input type="hidden" name="woo_order_id" value="<?=wooOfflineH($row['woo_order_id'])?>"><div class="col-md-4"><select class="form-select" name="target_mode" onchange="this.form.querySelector('[name=cod_masa_target]').disabled=(this.value==='current_note')"><option value="table">La masa</option><?php if($currentValid):?><option value="current_note">In nota curenta #<?=$currentNote?></option><?php endif;?></select></div><div class="col-md-5"><select class="form-select" name="cod_masa_target"><option value="">-- alege masa --</option><?php foreach($tables as $t):if((int)($t['open_operator']??0)>0&&(int)$t['open_operator']!==$operator)continue;?><option value="<?=(int)$t['cod_masa']?>"><?=wooOfflineH($t['nume_masa']?:('Masa '.$t['cod_masa']))?><?=((int)($t['open_operator']??0)===$operator)?' (nota ta deschisa)':''?></option><?php endforeach;?></select></div><div class="col-md-3"><button class="btn btn-success w-100">Importa</button></div></form><?php endif;?></div></div></div><?php endforeach;?></div>

<h5>Importate local</h5><div class="card done"><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Woo</th><th>Nota POS</th><th>Importat</th><th>ACK site</th><th></th></tr></thead><tbody><?php foreach($imported as $row):?><tr><td>#<?=wooOfflineH($row['order_number']?:$row['woo_order_id'])?></td><td><?=(int)$row['imported_note_nrbon']?></td><td><?=wooOfflineH($row['imported_at'])?></td><td><?=wooOfflineH($row['ack_status'])?><?php if(trim((string)$row['ack_error'])!==''):?><div class="small text-danger"><?=wooOfflineH($row['ack_error'])?></div><?php endif;?></td><td><?php if($row['ack_status']!=='acknowledged'):?><form method="post"><input type="hidden" name="action" value="retry_ack"><input type="hidden" name="woo_order_id" value="<?=wooOfflineH($row['woo_order_id'])?>"><input type="hidden" name="note_id" value="<?=(int)$row['imported_note_nrbon']?>"><button class="btn btn-sm btn-outline-primary">Retrimite ACK</button></form><?php endif;?></td></tr><?php endforeach;?></tbody></table></div></div>
</div></body></html>
