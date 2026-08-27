<?php
// lookup_prod_by_barcode.php
include('session.php');
require_once __DIR__ . '/cache_tools.php';
header('Content-Type: application/json');

try {
    $client_id   = $_SESSION['client_id']   ?? 'anon';
    $cod_locatie = $_SESSION['cod_locatie'] ?? '0';
    $barcodeRaw  = $_GET['prod_cod_bare']   ?? '';

    if ($barcodeRaw === '') { http_response_code(400); echo json_encode(['error'=>'Lipsă cod de bare.']); exit; }

    $BARCODE_TTL = 900;
    $barcodeSan  = preg_replace('/[^0-9A-Za-z\.\-\_]/', '_', $barcodeRaw);
    $barcodes_dir = __DIR__ . "/cache/c{$client_id}_l{$cod_locatie}/barcodes";
    $barcode_cache_file = $barcodes_dir . "/{$barcodeSan}.json";

    $produs_info = null;
    if (is_file($barcode_cache_file) && (time() - filemtime($barcode_cache_file) < $BARCODE_TTL)) {
        $json = @file_get_contents($barcode_cache_file);
        $tmp  = json_decode($json, true);
        if (is_array($tmp) && isset($tmp['cod_produs'])) $produs_info = $tmp;
    }
    if (!$produs_info) {
        $stmt = $pdo->prepare("SELECT cod_produs FROM produse_servicii WHERE cod_bare = :cod_bare LIMIT 1");
        $stmt->execute([':cod_bare' => $barcodeRaw]);
        $cod_p = $stmt->fetchColumn();
        if (!$cod_p) { http_response_code(404); echo json_encode(['error'=>'Produs negăsit.']); exit; }

        $sql = "SELECT n.cod_produs, n.nume, n.pret_cu_tva, n.cota_tva, n.um,
                       n.sgr, n.sgr_pet, n.sgr_alumin, n.sgr_sticla,
                       g.denumire_gestiune
                FROM {$tabel_final_nomenclator} n
                LEFT JOIN gestiuni g ON n.id_gestiune = g.id_gestiune
                WHERE n.cod_produs = :cod_p LIMIT 1";
        $st = $pdo->prepare($sql);
        $st->execute([':cod_p' => $cod_p]);
        $produs_info = $st->fetch(PDO::FETCH_ASSOC);
        if (!$produs_info) { http_response_code(404); echo json_encode(['error'=>'Detalii produs negăsite.']); exit; }

        if (!is_dir($barcodes_dir)) @mkdir($barcodes_dir, 0755, true);
        $tmpf = $barcode_cache_file . '.' . getmypid() . '.tmp';
        @file_put_contents($tmpf, json_encode($produs_info, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        @rename($tmpf, $barcode_cache_file);
    }

    echo json_encode($produs_info, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
