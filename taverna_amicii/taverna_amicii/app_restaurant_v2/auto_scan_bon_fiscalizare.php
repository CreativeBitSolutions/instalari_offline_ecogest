<?php
// auto_scan_bon_fiscalizare.php
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
  echo json_encode(['ok' => false, 'msg' => 'Sesiune invalidă.']);
  exit;
}

$srcBase = RESTAURANT_OFFLINE_API_DIR . '/bonuri_procesate_fisco/' .
           basename((string)$clientId) . '/' .
           basename((string)$locationId) . '/BonANSWER';

// DEST: api/bonuri_fisco_verificate/{client}/{loc}/BonANSWER
$dstBase = RESTAURANT_OFFLINE_API_DIR . '/bonuri_fisco_verificate/' .
           basename((string)$clientId) . '/' .
           basename((string)$locationId) . '/BonANSWER';

if (!is_dir($srcBase)) {
  echo json_encode([
    'ok'      => true,
    'msg'     => 'Folder sursă BonANSWER nu există (încă).',
    'scanned' => 0,
  ]);
  exit;
}
if (!is_dir($dstBase)) {
  @mkdir($dstBase, 0775, true);
}

/**
 * Încarcă JSON în siguranță (max ~1MB).
 */
function safe_json_auto(string $file, int $max = 1048576): ?array {
  $sz = @filesize($file);
  if ($sz === false || $sz > $max) return null;
  $raw = @file_get_contents($file);
  if ($raw === false) return null;
  $j = @json_decode($raw, true);
  return is_array($j) ? $j : null;
}

/**
 * Parsează conținut tip INI:
 *   CommandName=...
 *   ErrorCode=...
 */
function parse_kv_auto(string $s): array {
  $out = [];
  foreach (preg_split('/\R+/', $s) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '[') continue;
    $p = strpos($line, '=');
    if ($p !== false) {
      $k = trim(substr($line, 0, $p));
      $v = trim(substr($line, $p + 1));
      if ($k !== '') $out[$k] = $v;
    }
  }
  return $out;
}

/**
 * Din continutul fisierului (payload.continutul_fisierului) extrage:
 *  - CommandName
 *  - ErrorCode (int)
 *  - toate celelalte câmpuri în $kv
 */
function parse_answer_content_auto(string $content): array {
  $kv  = parse_kv_auto($content);
  $cmd = isset($kv['CommandName']) ? trim((string)$kv['CommandName']) : '';
  $err = array_key_exists('ErrorCode', $kv) ? (int)$kv['ErrorCode'] : null;
  return [$cmd, $err, $kv];
}

$files = glob(rtrim($srcBase, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.json') ?: [];
if (!$files) {
  echo json_encode([
    'ok'      => true,
    'msg'     => 'Nu sunt fișiere JSON în BonANSWER.',
    'scanned' => 0,
  ]);
  exit;
}

// cel mai recent la început
usort($files, static function (string $a, string $b): int {
  return (@filemtime($b) ?: 0) <=> (@filemtime($a) ?: 0);
});

// limită (de ex. max 100 fișiere / apel ca să nu bușim serverul)
$files = array_slice($files, 0, 100);

$processed = [];
$updatedCount = 0;
$errCount     = 0;
$movedCount   = 0;

foreach ($files as $file) {
  $basename = basename($file);

  // doar fișiere de forma Bon_<NR>_...json
  if (!preg_match('/^Bon_(\d+)_/i', $basename, $m)) {
    continue;
  }
  $nrbon = (int)$m[1];

  $j = safe_json_auto($file);
  if (!$j) {
    // nu putem citi → nu mutăm, rămâne pentru analiză manuală
    continue;
  }

  $payload = $j['payload'] ?? [];
  $content = (string)($payload['continutul_fisierului'] ?? '');
  if ($content === '') {
    // gol → salt, nu mutăm
    continue;
  }

  [$cmd, $err, $kv] = parse_answer_content_auto($content);

  // ne interesează DOAR receipt_Fiscal_Close
  if ($cmd !== 'receipt_Fiscal_Close' || $err === null) {
    continue;
  }

  // dacă ErrorCode = 0 → încercăm să marcăm fiscalizat=1
  $updated = false;
  if ($err === 0) {
    $st = $pdo->prepare("
      UPDATE note
         SET fiscalizat = 1
       WHERE nrbon       = :n
         AND locatie     = :l
         AND nr_raport_z = 0
         AND fiscalizat  = 0
    ");
    $st->execute([
      ':n' => $nrbon,
      ':l' => $locationId,
    ]);
    $updated = ($st->rowCount() > 0);
    if ($updated) {
      $updatedCount++;
    }
  } else {
    $errCount++;
  }

  // DUPĂ ce am procesat fișierul (indiferent de err), îl mutăm în arhivă
  $dest = $dstBase . DIRECTORY_SEPARATOR . $basename;
  if (file_exists($dest)) {
    $info = pathinfo($basename);
    $name = $info['filename'] ?? ('file_' . uniqid());
    $dest = $dstBase . DIRECTORY_SEPARATOR . $name . '_' . uniqid('', true) . '.json';
  }

  if (@rename($file, $dest)) {
    $movedCount++;
  }

  $processed[] = [
    'nrbon'      => $nrbon,
    'errorCode'  => $err,
    'updated'    => $updated,
    'file'       => $basename,
    'cmd'        => $cmd,
  ];
}

echo json_encode([
  'ok'           => true,
  'scanned'      => count($files),
  'processed'    => $processed,
  'updatedCount' => $updatedCount,
  'errorCount'   => $errCount,
  'movedCount'   => $movedCount,
  'msg'          => 'Scanare BonANSWER finalizată.',
]);
