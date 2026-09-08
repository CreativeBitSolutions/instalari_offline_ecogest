<?php
declare(strict_types=1);



if (!function_exists('bestmixt_offline_api_root')) {
    function bestmixt_offline_api_root(): string
    {
        $root = '';
        if (function_exists('offline_api_root_path')) {
            $root = rtrim((string)offline_api_root_path(), '/\\');
        } elseif (defined('RESTAURANT_OFFLINE_API_DIR')) {
            $root = rtrim((string)constant('RESTAURANT_OFFLINE_API_DIR'), '/\\');
        }

        if ($root === '') {
            throw new RuntimeException('Folderul API offline Bestmixt nu este configurat.');
        }
        return $root;
    }
}

function bestmixt_fiscal_queue_path(int $clientId, int $locationId): string
{
    return bestmixt_offline_api_root()
        . DIRECTORY_SEPARATOR . $clientId . DIRECTORY_SEPARATOR . $locationId
        . DIRECTORY_SEPARATOR . 'bon_casa_marcat.json';
}

function bestmixt_fiscal_publish_json(string $queuePath, string $json): bool
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
