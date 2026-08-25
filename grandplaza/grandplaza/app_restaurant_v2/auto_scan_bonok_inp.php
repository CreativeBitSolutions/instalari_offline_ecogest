<?php
// auto_scan_bonok_inp.php
// Scanner pentru api/bonuri_procesate_fisco/{client}/{loc}/BonOK/inp
// → mută fișierele JSON în api/bonuri_fisco_verificate/{client}/{loc}/BonOK/inp

declare(strict_types=1);
session_start();
require_once __DIR__ . '/session.php';
if (function_exists('restaurantIsOfflineSqlite') && restaurantIsOfflineSqlite()) {
  header('Content-Type: application/json; charset=utf-8');
  http_response_code(403);
  echo json_encode(['ok' => false, 'msg' => 'Scanarea FISCO este dezactivata in modul SQLite offline.']);
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

// Sursă: api/bonuri_procesate_fisco/{client}/{loc}/BonOK/inp
$srcBase = RESTAURANT_OFFLINE_API_DIR . '/bonuri_procesate_fisco/' .
           basename((string)$clientId) . '/' .
           basename((string)$locationId) . '/BonOK/inp';

// Destinație: api/bonuri_fisco_verificate/{client}/{loc}/BonOK/inp
$dstBase = RESTAURANT_OFFLINE_API_DIR . '/bonuri_fisco_verificate/' .
           basename((string)$clientId) . '/' .
           basename((string)$locationId) . '/BonOK/inp';

if (!is_dir($srcBase)) {
  echo json_encode([
    'ok'      => true,
    'msg'     => 'Folder sursă BonOK/inp nu există (încă).',
    'scanned' => 0,
    'movedCount' => 0,
  ]);
  exit;
}

if (!is_dir($dstBase)) {
  @mkdir($dstBase, 0775, true);
}

/**
 * Încarcă JSON în siguranță (max ~1MB).
 */
function safe_json_bonok(string $file, int $max = 1048576): ?array {
  $sz = @filesize($file);
  if ($sz === false || $sz > $max) return null;
  $raw = @file_get_contents($file);
  if ($raw === false) return null;
  $j = @json_decode($raw, true);
  return is_array($j) ? $j : null;
}

// Luăm toate JSON-urile din BonOK/inp
$files = glob(rtrim($srcBase, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.json') ?: [];
if (!$files) {
  echo json_encode([
    'ok'      => true,
    'msg'     => 'Nu sunt fișiere JSON în BonOK/inp.',
    'scanned' => 0,
    'movedCount' => 0,
  ]);
  exit;
}

// Cel mai recent la început
usort($files, static function (string $a, string $b): int {
  return (@filemtime($b) ?: 0) <=> (@filemtime($a) ?: 0);
});

// Limită pentru un singur run (să nu încărcăm serverul)
$files = array_slice($files, 0, 100);

$processed  = [];
$movedCount = 0;

foreach ($files as $file) {
  $basename = basename($file);

  // Dorim doar fișiere de forma Bon_<numar>_...json
  if (!preg_match('/^Bon_(\d+)_/i', $basename, $m)) {
    continue;
  }

  $j = safe_json_bonok($file);
  if (!$j) {
    // JSON corupt / prea mare → nu îl mutăm, rămâne pentru analiză manuală
    continue;
  }

  $meta    = $j['meta']    ?? [];
  $payload = $j['payload'] ?? [];

  $category   = (string)($meta['category']  ?? '');
  $fileExt    = (string)($meta['file_ext']  ?? '');
  $numeFisier = (string)($payload['nume_fisier'] ?? '');
  $dataFisier = (string)($payload['data']   ?? '');

  // Opțional: filtrăm încă o dată doar cele BonOK + ext inp
  if ($category !== 'BonOK' || $fileExt !== 'inp') {
    // dacă vrei să le muți pe TOATE oricum, comentezi acest continue
    continue;
  }

  // Construim destinația (avem grijă de coliziuni)
  $dest = $dstBase . DIRECTORY_SEPARATOR . $basename;
  if (file_exists($dest)) {
    $info = pathinfo($basename);
    $name = $info['filename'] ?? ('file_' . uniqid());
    $dest = $dstBase . DIRECTORY_SEPARATOR . $name . '_' . uniqid('', true) . '.json';
  }

  $moved = @rename($file, $dest);
  if ($moved) {
    $movedCount++;
  }

  $processed[] = [
    'file'        => $basename,
    'nume_fisier' => $numeFisier,
    'category'    => $category,
    'file_ext'    => $fileExt,
    'data'        => $dataFisier,
    'moved'       => (bool)$moved,
  ];
}

echo json_encode([
  'ok'         => true,
  'scanned'    => count($files),
  'movedCount' => $movedCount,
  'processed'  => $processed,
  'msg'        => 'Scanare BonOK/inp finalizată.',
]);
