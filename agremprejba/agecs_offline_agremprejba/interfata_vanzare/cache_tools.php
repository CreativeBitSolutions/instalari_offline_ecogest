<?php
// cache_tools.php
declare(strict_types=1);

/** Directorul de bază pentru cache. */
function cache_base_dir(): string {
    return __DIR__ . '/cache';
}

/** Ștergere recursivă sigură (constrânsă în interiorul /cache). */
function rrmdir_safe(string $dir): void {
    $base = realpath(cache_base_dir());
    if (!is_dir($dir)) return;
    $real = realpath($dir);
    if ($real === false || $base === false || strpos($real, $base) !== 0) return; // protecție
    $it = new RecursiveDirectoryIterator($real, FilesystemIterator::SKIP_DOTS);
    $ri = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($ri as $file) {
        $path = $file->getPathname();
        if ($file->isDir()) @rmdir($path); else @unlink($path);
    }
    @rmdir($real);
}

/** Șterge TOT cache-ul pentru un client (toate locațiile). */
function clear_cache_for_client($client_id): int {
    $client_id = preg_replace('/\D+/', '', (string)$client_id);
    if ($client_id === '') return 0;

    $base = cache_base_dir();
    $dirs = glob($base . "/c{$client_id}_l*", GLOB_ONLYDIR) ?: [];
    $count = 0;
    foreach ($dirs as $dir) { rrmdir_safe($dir); $count++; }
    return $count;
}

/** Șterge cache-ul doar pentru client + locație. */
function clear_cache_for_client_location($client_id, $cod_locatie): void {
    $client_id   = preg_replace('/\D+/', '', (string)$client_id);
    $cod_locatie = preg_replace('/\D+/', '', (string)$cod_locatie);
    if ($client_id === '' || $cod_locatie === '') return;

    $dir = cache_base_dir() . "/c{$client_id}_l{$cod_locatie}";
    rrmdir_safe($dir);
}

/** Șterge fișierele mai vechi decât $ttl sec. dintr-un director. */
function gc_expired_in_dir(string $dir, int $ttl): void {
    if (!is_dir($dir)) return;
    $now = time();
    $it = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);
    foreach (new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST) as $file) {
        if ($file->isFile() && $now - $file->getMTime() > $ttl) @unlink($file->getPathname());
    }
    // încearcă să elimini subfolderele rămase goale
    $it2 = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);
    $ri2 = new RecursiveIteratorIterator($it2, RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($ri2 as $node) if ($node->isDir()) @rmdir($node->getPathname());
}

/** GC probabilistic pentru cache-ul unui client (ex: 2% șansă / request). */
function probabilistic_gc_for_client($client_id, int $ttl = 1800, float $chance = 0.02): void {
    if (mt_rand() / mt_getrandmax() > $chance) return;
    $client_id = preg_replace('/\D+/', '', (string)$client_id);
    if ($client_id === '') return;

    $base = cache_base_dir();
    foreach (glob($base . "/c{$client_id}_l*", GLOB_ONLYDIR) ?: [] as $dir) {
        gc_expired_in_dir($dir, $ttl);
    }
}

/** Invalidează listările (grid) pentru client + locație. */
function invalidate_prodlists_for_client_location($client_id, $cod_locatie): void {
    $client_id   = preg_replace('/\D+/', '', (string)$client_id);
    $cod_locatie = preg_replace('/\D+/', '', (string)$cod_locatie);
    if ($client_id === '' || $cod_locatie === '') return;

    $dir = cache_base_dir() . "/c{$client_id}_l{$cod_locatie}";
    if (!is_dir($dir)) return;
    foreach (glob($dir . "/prodlist_*.html") ?: [] as $f) @unlink($f);
    foreach (glob($dir . "/prodlist_stoc_*.html") ?: [] as $f) @unlink($f);
}

/** (opțional) Invalidează cache-ul de coduri de bare pentru client + locație. */
function invalidate_barcodes_for_client_location($client_id, $cod_locatie): void {
    $client_id   = preg_replace('/\D+/', '', (string)$client_id);
    $cod_locatie = preg_replace('/\D+/', '', (string)$cod_locatie);
    if ($client_id === '' || $cod_locatie === '') return;

    $dir = cache_base_dir() . "/c{$client_id}_l{$cod_locatie}/barcodes";
    if (!is_dir($dir)) return;
    foreach (glob($dir . "/*.json") ?: [] as $f) @unlink($f);
}
