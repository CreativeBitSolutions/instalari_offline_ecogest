<?php
declare(strict_types=1);

if (!function_exists('offline_api_root_path')) {
    require_once __DIR__ . '/offline_api_path.php';
}

function andreearobert_offline_api_root(): string
{
    $root = rtrim((string)offline_api_root_path(), '/\\');
    if ($root === '') {
        throw new RuntimeException('Folderul API offline Andreea Robert Shop nu este configurat.');
    }
    return $root;
}

function andreearobert_fiscal_queue_path(int $clientId, int $locationId): string
{
    if ($clientId <= 0 || $locationId <= 0) {
        throw new InvalidArgumentException('Clientul și locația fiscală nu sunt valide.');
    }

    return andreearobert_offline_api_root()
        . DIRECTORY_SEPARATOR . $clientId
        . DIRECTORY_SEPARATOR . $locationId
        . DIRECTORY_SEPARATOR . 'bon_casa_marcat.json';
}

function andreearobert_fiscal_publish_json(string $queuePath, string $json): bool
{
    if ($json === '') {
        throw new RuntimeException('Bonul fiscal nu poate fi publicat fără conținut.');
    }

    $directory = dirname($queuePath);
    if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('Directorul cozii casei de marcat nu a putut fi creat.');
    }

    $lock = @fopen($queuePath . '.lock', 'c+b');
    if ($lock === false || !@flock($lock, LOCK_EX)) {
        if (is_resource($lock)) {
            @fclose($lock);
        }
        throw new RuntimeException('Coada casei de marcat nu a putut fi blocată.');
    }

    $temporary = $queuePath . '.tmp.' . (string)getmypid() . '.' . andreearobert_fiscal_token();
    try {
        clearstatcache(true, $queuePath);
        if (is_file($queuePath)) {
            return false;
        }

        $written = @file_put_contents($temporary, $json, LOCK_EX);
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
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

function andreearobert_fiscal_token(): string
{
    try {
        return bin2hex(random_bytes(8));
    } catch (Throwable $error) {
        return str_replace('.', '', uniqid('', true));
    }
}
