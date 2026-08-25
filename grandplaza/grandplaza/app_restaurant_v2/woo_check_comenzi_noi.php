<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors','0');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$isLoginCheck = isset($_GET['source']) && $_GET['source'] === 'login' && (int)($_SESSION['client_id'] ?? 25) === 25;
if ($isLoginCheck) require_once __DIR__.'/database_connection.php';
else require_once __DIR__.'/session.php';
require_once __DIR__.'/includes/woo_offline_sync.php';
require_once __DIR__.'/includes/woo_sync_helpers.php';

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
    $autoPrintEnabled = false;
    if (!$isLoginCheck) {
        $setting = $pdo->query('SELECT listare_automata_comenzi_site FROM setari_platforma ORDER BY id LIMIT 1')->fetchColumn();
        $autoPrintEnabled = $setting !== false && (int)$setting === 1;
    }
    $out['auto_site_order_print_enabled'] = $autoPrintEnabled ? 1 : 0;
    $out['auto_bar_only_results'] = [];
    $out['auto_bar_only_errors'] = [];
    foreach ($autoPrintEnabled ? $out['ids'] : [] as $wooOrderId) {
        $numericId = (int)$wooOrderId;
        if ($numericId <= 0 || woo_sync_auto_bar_was_listed($numericId, (int)($_SESSION['cod_locatie'] ?? 1))) continue;
        try {
            $out['auto_bar_only_results'][] = woo_sync_auto_print_site_order_to_bar_only($pdo,$numericId,(int)($_SESSION['cod_locatie'] ?? 1),'offline_scanner_new_order_no_pos_import');
        } catch (Throwable $printError) {
            $out['auto_bar_only_errors'][] = ['woo_order_id'=>$numericId,'message'=>$printError->getMessage()];
        }
        break;
    }
} catch(Throwable $e) {
    $out=['success'=>false,'count'=>0,'ids'=>[],'message'=>$e->getMessage()];
}
echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
