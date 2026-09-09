<?php
declare(strict_types=1);
date_default_timezone_set('Europe/Bucharest');
ini_set('display_errors','0');ini_set('log_errors','1');
function casa_config(): array {
    static $config;
    if ($config !== null) return $config;
    foreach ([dirname(__DIR__).'/config_offline_casaluanna.json',__DIR__.'/config_offline_casaluanna.json','C:/xampp/htdocs/github/instalari_offline_ecogest/casaluanna/config_offline_casaluanna.json'] as $path) {
        if (!is_file($path)) continue;
        $config=json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
        if ((int)($config['client_id']??0)!==19) throw new RuntimeException('Configurare client incorectă.');
        return $config;
    }
    throw new RuntimeException('Lipsește config_offline_casaluanna.json.');
}
function casa_db(): PDO {
    static $db;
    if ($db instanceof PDO) return $db;
    $path=casa_config()['db_runtime_file'];
    if (!is_file($path)) throw new RuntimeException('Baza locală lipsește. Nu se creează o bază goală.');
    $db=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $db->exec('PRAGMA busy_timeout=10000');$db->exec('PRAGMA foreign_keys=ON');
    $db->sqliteCreateFunction('REGEXP',function($pattern,$value){return preg_match('~'.str_replace('~','\\~',(string)$pattern).'~u',(string)$value)===1?1:0;},2);
    $db->sqliteCreateFunction('CONCAT',function(...$args){return implode('',$args);});
    $db->sqliteCreateFunction('NOW',function(){return date('Y-m-d H:i:s');},0);
    $db->sqliteCreateFunction('CURDATE',function(){return date('Y-m-d');},0);
    require_once __DIR__.'/offline_schema.php';
    $schemaLock=fopen(casa_config()['api_root_absolute'].'/schema.lock','c+b');
    if(!$schemaLock||!flock($schemaLock,LOCK_EX))throw new RuntimeException('Schema locală este ocupată.');
    try{casa_schema($db);}finally{flock($schemaLock,LOCK_UN);fclose($schemaLock);}
    return $db;
}
function casa_session(): void {
    if(session_status()!==PHP_SESSION_ACTIVE)session_start();
    if(empty($_SESSION['casa_csrf']))$_SESSION['casa_csrf']=bin2hex(random_bytes(32));
    $_SESSION['client_id']=19;$_SESSION['cod_locatie']=1;
}
function casa_json(array $data,int $code=200): void {
    http_response_code($code);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
    echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);exit;
}
function casa_csrf(): void {
    $token=(string)($_SERVER['HTTP_X_CASA_CSRF']??$_POST['casa_csrf']??'');
    if($token===''||!hash_equals((string)($_SESSION['casa_csrf']??''),$token))casa_json(['success'=>false,'error'=>'Sesiunea formularului a expirat. Reîncărcați pagina.'],403);
}
function casa_auth(): void {
    casa_session();
    $prefix=basename(dirname($_SERVER['SCRIPT_NAME']??''))==='proc'?'../':'';
    if(empty($_SESSION['admin_id'])||empty($_SESSION['adminloggedin'])){header('Location: '.$prefix.'login.php');exit;}
    $q=casa_db()->prepare('SELECT 1 FROM utilizatori WHERE id_utilizator=? AND dezactivat=0');$q->execute([(int)$_SESSION['admin_id']]);
    if(!$q->fetchColumn()){unset($_SESSION['admin_id'],$_SESSION['adminloggedin']);header('Location: '.$prefix.'login.php');exit;}
    if(($_SERVER['REQUEST_METHOD']??'GET')==='POST')casa_csrf();
}
function casa_rows(PDO $db,string $sql,array $args=[]): array {$q=$db->prepare($sql);$q->execute($args);return $q->fetchAll(PDO::FETCH_ASSOC);}
function casa_one(PDO $db,string $sql,array $args=[]): ?array {$rows=casa_rows($db,$sql,$args);return $rows[0]??null;}
function casa_h($value): string {return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
function casa_installation(PDO $db): string {return (string)$db->query("SELECT value FROM casa_meta WHERE name='installation_uuid'")->fetchColumn();}
function casa_invoice_readonly(PDO $db,int $id): bool {
    $meta=casa_one($db,'SELECT origin,authority FROM casa_invoices WHERE id_factura=?',[$id]);
    return !$meta || $meta['origin']!=='local' || $meta['authority']==='online';
}
function casa_invoice_anaf_locked(PDO $db,int $id): bool {
    $remote=casa_one($db,'SELECT payload FROM casa_remote_status WHERE id_factura=?',[$id]);
    $status=$remote?json_decode($remote['payload'],true):[];
    return !empty($status['anaf_sent']) || !empty($status['anaf']['index_incarcare']) || (bool)casa_one($db,"SELECT 1 FROM facturi WHERE id_factura=? AND COALESCE(data_incarcare,'') NOT IN ('','0000-00-00 00:00:00')",[$id]);
}

function casa_copy_line_movements(PDO $db,int $oldLine,int $newLine,int $newInvoice,int $number,string $date): void {
    foreach(casa_rows($db,'SELECT * FROM miscari WHERE id_vanz_fact=?',[$oldLine]) as $row){
        unset($row['id']);$row['id_vanz_fact']=$newLine;$row['id_doc']=$newInvoice;$row['data']=$date;
        if(strtoupper((string)$row['fel_doc'])==='FAC')$row['nr_doc']=$number;
        $db->prepare('INSERT INTO miscari (`'.implode('`,`',array_keys($row)).'`) VALUES('.implode(',',array_fill(0,count($row),'?')).')')->execute(array_values($row));
    }
}
