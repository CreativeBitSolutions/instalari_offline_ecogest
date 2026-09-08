<?php
declare(strict_types=1);
date_default_timezone_set('Europe/Bucharest');
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
    require_once __DIR__.'/offline_schema.php';casa_schema($db);return $db;
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
