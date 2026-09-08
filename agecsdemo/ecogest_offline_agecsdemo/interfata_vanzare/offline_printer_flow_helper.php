<?php
declare(strict_types=1);

require_once __DIR__ . '/printer_queue_helper.php';

if (!function_exists('lorand_offline_api_root')) {
    function lorand_offline_api_root(): string
    {
        $root = '';
        if (function_exists('offline_api_root_path')) {
            $root = rtrim((string)offline_api_root_path(), '/\\');
        } elseif (defined('RESTAURANT_OFFLINE_API_DIR')) {
            $root = rtrim((string)constant('RESTAURANT_OFFLINE_API_DIR'), '/\\');
        }

        if ($root === '') {
            throw new RuntimeException('Folderul API offline AGECSDEMO nu este configurat.');
        }
        return $root;
    }
}

function lorand_printer_safe_return(string $page): string
{
    $allowed = [
        'vanzare_magazin.php',
        'rapoarte_produse_lorand.php',
        'configurare_imprimanta_lorand.php',
        'agecs_login.php',
        'logout.php',
    ];
    return in_array($page, $allowed, true) ? $page : 'vanzare_magazin.php';
}

function lorand_printer_wait_url(string $returnPage, string $context): string
{
    return 'asteapta_imprimanta.php?return=' . rawurlencode(lorand_printer_safe_return($returnPage))
        . '&context=' . rawurlencode(preg_replace('/[^a-z0-9_]/', '', strtolower($context)) ?: 'document');
}

function lorand_printer_pending(int $clientId, int $locationId): array
{
    $base = lorand_offline_api_root()
        . DIRECTORY_SEPARATOR . $clientId . DIRECTORY_SEPARATOR . $locationId;
    $files = [];
    $legacy = $base . DIRECTORY_SEPARATOR . 'de_listat_la_imprimanta.json';
    if (is_file($legacy)) {
        $files[] = $legacy;
    }
    $queueDir = $base . DIRECTORY_SEPARATOR . 'print_queue';
    if (is_dir($queueDir)) {
        $files = array_merge($files, glob($queueDir . DIRECTORY_SEPARATOR . '*.json') ?: []);
    }

    $oldest = time();
    foreach ($files as $file) {
        $modified = @filemtime($file);
        if (is_int($modified)) {
            $oldest = min($oldest, $modified);
        }
    }
    return [
        'pending' => count($files) > 0,
        'documents' => count($files),
        'age' => $files ? max(0, time() - $oldest) : 0,
    ];
}

function lorand_fiscal_queue_path(int $clientId, int $locationId): string
{
    return lorand_offline_api_root()
        . DIRECTORY_SEPARATOR . $clientId . DIRECTORY_SEPARATOR . $locationId
        . DIRECTORY_SEPARATOR . 'bon_casa_marcat.json';
}

function lorand_fiscal_publish_json(string $queuePath, string $json): bool
{
    $directory = dirname($queuePath);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('Directorul cozii casei de marcat nu a putut fi creat.');
    }

    $lock = fopen($queuePath . '.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX)) {
        if (is_resource($lock)) {
            fclose($lock);
        }
        throw new RuntimeException('Coada casei de marcat nu a putut fi blocată.');
    }

    $temporary = $queuePath . '.tmp.' . getmypid() . '.' . str_replace('.', '', uniqid('', true));
    try {
        clearstatcache(true, $queuePath);
        if (is_file($queuePath)) {
            return false;
        }
        $written = file_put_contents($temporary, $json, LOCK_EX);
        if ($written === false || $written !== strlen($json)) {
            throw new RuntimeException('Fișierul fiscal temporar nu a putut fi scris integral.');
        }
        if (!@rename($temporary, $queuePath)) {
            throw new RuntimeException('Bonul fiscal nu a putut fi publicat în coadă.');
        }
        return true;
    } finally {
        if (is_file($temporary)) {
            @unlink($temporary);
        }
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
