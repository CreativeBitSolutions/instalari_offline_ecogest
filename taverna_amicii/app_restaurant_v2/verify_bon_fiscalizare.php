<?php
// verify_bon_fiscalizare.php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/session.php';
if (function_exists('restaurantIsOfflineSqlite') && restaurantIsOfflineSqlite()) {
  header('Content-Type: application/json; charset=utf-8');
  http_response_code(403);
  echo json_encode(['ok' => false, 'msg' => 'BonANSWER este dezactivat in modul SQLite offline.']);
  exit;
}
require_once __DIR__ . '/vanzare_init.php';

header('Content-Type: application/json; charset=utf-8');

$clientId   = $_SESSION['client_id']   ?? null;
$locationId = $_SESSION['cod_locatie'] ?? null;
if (!$clientId || !$locationId) {
  http_response_code(403);
  echo json_encode(['ok'=>false, 'msg'=>'Sesiune invalidă.']);
  exit;
}

$nrbon = isset($_POST['nrbon']) ? (int)$_POST['nrbon'] : 0;
if ($nrbon <= 0) {
  echo json_encode(['ok'=>false, 'msg'=>'Parametru nrbon lipsă/invalid.']);
  exit;
}

$base = RESTAURANT_OFFLINE_API_DIR . '/bonuri_procesate_fisco/' . basename((string)$clientId) . '/' . basename((string)$locationId) . '/BonANSWER';

function safe_json(string $file, int $max=1048576): ?array {
  $sz=@filesize($file); if($sz===false || $sz>$max) return null;
  $raw=@file_get_contents($file); if($raw===false) return null;
  $j=@json_decode($raw, true); return is_array($j)?$j:null;
}
function parse_kv(string $s): array {
  $out=[];
  foreach (preg_split('/\R+/', $s) as $line) {
    $line=trim($line);
    if ($line==='' || $line[0]==='[') continue;
    $p=strpos($line,'=');
    if ($p!==false) {
      $k=trim(substr($line,0,$p)); $v=trim(substr($line,$p+1));
      if ($k!=='') $out[$k]=$v;
    }
  }
  return $out;
}
function find_json_for_bon(string $base, int $nrbon): ?string {
  if (!is_dir($base)) return null;

  // 1) caută direct după nume
  $pattern = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'Bon_' . $nrbon . '*.json';
  $files = glob($pattern) ?: [];
  if ($files) {
    usort($files, fn($a,$b)=>(@filemtime($b)?:0) <=> (@filemtime($a)?:0));
    return $files[0];
  }

  // 2) fallback: scanează câteva recente și compară payload.nume_fisier
  $all = glob(rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.json') ?: [];
  if (!$all) return null;
  usort($all, fn($a,$b)=>(@filemtime($b)?:0) <=> (@filemtime($a)?:0));
  $all = array_slice($all, 0, 800); // limită rezonabilă

  $best = null; $bestTime = -1;
  foreach ($all as $f) {
    $j = safe_json($f);
    if (!$j) continue;
    $name = (string)($j['payload']['nume_fisier'] ?? '');
    if ($name !== '' && stripos($name, 'Bon_'.$nrbon.'_') === 0) {
      $mt = @filemtime($f) ?: 0;
      if ($mt > $bestTime) { $best = $f; $bestTime = $mt; }
    }
  }
  return $best;
}

$file = find_json_for_bon($base, $nrbon);
if (!$file) {
  echo json_encode(['ok'=>false, 'code'=>'NOT_FOUND', 'msg'=>"Nu am găsit fișier JSON pentru Bon_{$nrbon}."]);
  exit;
}
$j = safe_json($file);
if (!$j) {
  echo json_encode(['ok'=>false, 'code'=>'READ_ERR', 'msg'=>"Nu se poate citi fișierul pentru Bon_{$nrbon}."]);
  exit;
}

$content = (string)($j['payload']['continutul_fisierului'] ?? '');
if ($content === '') {
  echo json_encode(['ok'=>false, 'code'=>'EMPTY', 'msg'=>'Fișier JSON nu conține payload.continutul_fisierului.']);
  exit;
}
$kv = parse_kv($content);
if (!array_key_exists('ErrorCode', $kv)) {
  echo json_encode(['ok'=>false, 'code'=>'PARSE_ERR', 'msg'=>'Nu pot interpreta ErrorCode din fișier.']);
  exit;
}
$err = (int)$kv['ErrorCode'];

if ($err === 0) {
  // fiscalizare reușită -> setăm fiscalizat=1 (doar pe bonurile fără Z)
  $st = $pdo->prepare("UPDATE note SET fiscalizat=1 WHERE nrbon=:n AND locatie=:l AND nr_raport_z=0 AND fiscalizat=0");
  $st->execute([':n'=>$nrbon, ':l'=>$locationId]);
  $changed = $st->rowCount() > 0;

  echo json_encode([
    'ok'=>true,
    'errorCode'=>0,
    'updated'=>$changed,
    'msg'=>$changed ? 'Actualizat: fiscalizat=1.' : 'Era deja fiscalizat=1 sau nu se califică.'
  ]);
  exit;
}

// err > 0  => nu actualizăm DB, doar raportăm codul
echo json_encode([
  'ok'=>true,
  'errorCode'=>$err,
  'updated'=>false,
  'msg'=>"Eroare la fiscalizare (ErrorCode={$err})."
]);
