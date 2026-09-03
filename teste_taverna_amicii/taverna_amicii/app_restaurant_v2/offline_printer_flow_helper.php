<?php
declare(strict_types=1);

function agecs_offline_printer_queue_path(int $clientId, int $locationId): string
{
    if (!defined('RESTAURANT_OFFLINE_API_DIR')) {
        throw new RuntimeException('Directorul API offline nu este configurat.');
    }
    if ($clientId <= 0 || $locationId <= 0) {
        throw new RuntimeException('Clientul sau locația imprimantei nu sunt valide.');
    }

    return rtrim((string)RESTAURANT_OFFLINE_API_DIR, '/\\')
        . DIRECTORY_SEPARATOR . $clientId
        . DIRECTORY_SEPARATOR . $locationId
        . DIRECTORY_SEPARATOR . 'de_listat_la_imprimanta.json';
}

function agecs_offline_printer_enqueue(array $documents, string $message): bool
{
    if ($documents === []) {
        return true;
    }

    $clientId = (int)($_SESSION['client_id'] ?? 0);
    $locationId = (int)($_SESSION['cod_locatie'] ?? 0);
    $helperPath = rtrim((string)RESTAURANT_OFFLINE_API_DIR, '/\\')
        . DIRECTORY_SEPARATOR . 'printer_queue_atomic_helper.php';

    if (!is_file($helperPath)) {
        throw new RuntimeException('Lipsește helperul cozii atomice pentru imprimantă.');
    }

    require_once $helperPath;
    return agecs_printer_queue_append_documents(
        agecs_offline_printer_queue_path($clientId, $locationId),
        array_values($documents),
        $message
    );
}

function agecs_offline_printer_safe_return(string $returnPage): string
{
    $allowed = [
        'vanzare_restaurant.php',
        'vanzare_rapoarte_produse.php',
        'vanzare_importa_comanda_woo.php',
        'sefsala.php',
        'agecs_login.php',
        'logout.php',
    ];

    return in_array($returnPage, $allowed, true) ? $returnPage : 'vanzare_restaurant.php';
}

function agecs_offline_printer_wait_url(
    string $returnPage = 'vanzare_restaurant.php',
    string $context = 'document'
): string {
    return 'asteapta_imprimanta.php?return='
        . rawurlencode(agecs_offline_printer_safe_return($returnPage))
        . '&context=' . rawurlencode($context);
}

function agecs_offline_printer_redirect_script(
    string $returnPage = 'vanzare_restaurant.php',
    string $context = 'document',
    int $delayMs = 0
): string {
    $url = agecs_offline_printer_wait_url($returnPage, $context);
    return '<script>setTimeout(function(){window.location.href='
        . json_encode($url, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        . ';},' . max(0, $delayMs) . ');</script>';
}

function agecs_offline_fiscal_publish_json(string $queuePath, string $json): bool
{
    $directory = dirname($queuePath);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('Directorul cozii casei de marcat nu a putut fi creat.');
    }

    $helperPath = rtrim((string)RESTAURANT_OFFLINE_API_DIR, '/\\')
        . DIRECTORY_SEPARATOR . 'printer_queue_atomic_helper.php';
    if (!is_file($helperPath)) {
        throw new RuntimeException('Lipsește helperul pentru coada casei de marcat.');
    }
    require_once $helperPath;

    $lock = agecs_printer_queue_lock($queuePath);
    $temporaryPath = $queuePath . '.tmp.' . getmypid() . '.' . agecs_printer_queue_token();
    try {
        if (is_file($queuePath)) {
            return false;
        }
        if (file_put_contents($temporaryPath, $json, LOCK_EX) === false) {
            throw new RuntimeException('Fișierul temporar al bonului fiscal nu a putut fi scris.');
        }
        if (!@rename($temporaryPath, $queuePath)) {
            throw new RuntimeException('Bonul fiscal nu a putut fi publicat în coadă.');
        }
        return true;
    } finally {
        if (is_file($temporaryPath)) {
            @unlink($temporaryPath);
        }
        agecs_printer_queue_unlock($lock);
    }
}
