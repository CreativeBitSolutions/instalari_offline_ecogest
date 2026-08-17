<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors','0');
require_once __DIR__.'/session.php';
require_once __DIR__.'/includes/woo_offline_sync.php';

$out=['success'=>true,'count'=>0,'ids'=>[]];
try {
    if(!isset($pdo)||!($pdo instanceof PDO)) throw new RuntimeException('Conexiunea locala nu este disponibila.');
    if(!function_exists('restaurantIsOfflineSqlite')||!restaurantIsOfflineSqlite()) throw new RuntimeException('Endpoint disponibil doar in modul SQLite offline.');
    wooOfflineEnsureSchema($pdo);
    $cfg=wooOfflineConfig();
    $state=$pdo->query('SELECT last_sync_success_at FROM woo_sync_state WHERE id=1')->fetchColumn();
    $interval=max(15,(int)($cfg['automatic_interval_seconds']??30));
    if(!$state||strtotime((string)$state)<=time()-$interval){
        try { wooOfflineSync($pdo,$cfg); }
        catch(Throwable $syncError) { $out['warning']=$syncError->getMessage(); }
    }
    $rows=$pdo->query("SELECT woo_order_id FROM woo_orders_inbox WHERE import_state<>'imported' ORDER BY COALESCE(date_created,fetched_at) ASC LIMIT 200")->fetchAll(PDO::FETCH_COLUMN)?:[];
    $out['ids']=array_values(array_map('strval',$rows));
    $out['count']=count($out['ids']);
} catch(Throwable $e) {
    $out=['success'=>false,'count'=>0,'ids'=>[],'message'=>$e->getMessage()];
}
echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
