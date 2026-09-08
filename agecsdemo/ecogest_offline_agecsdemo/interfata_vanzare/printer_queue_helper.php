<?php
declare(strict_types=1);

if (!function_exists('agecs_printer_offline_api_root')) {
    function agecs_printer_offline_api_root(): string
    {
        $root = '';
        if (function_exists('offline_api_root_path')) {
            $root = rtrim((string)offline_api_root_path(), '/\\');
        } elseif (defined('RESTAURANT_OFFLINE_API_DIR')) {
            $root = rtrim((string)constant('RESTAURANT_OFFLINE_API_DIR'), '/\\');
        }

        if ($root === '') {
            throw new RuntimeException('Folderul API offline nu este configurat.');
        }
        return $root;
    }
}

if (!function_exists('agecs_printer_queue_directory')) {
    function agecs_printer_queue_directory(int $clientId, int $locationId): string
    {
        if ($clientId <= 0 || $locationId <= 0) {
            throw new InvalidArgumentException('Clientul și locația sunt obligatorii pentru coada imprimantei.');
        }

        $directory = agecs_printer_offline_api_root()
            . DIRECTORY_SEPARATOR . $clientId
            . DIRECTORY_SEPARATOR . $locationId
            . DIRECTORY_SEPARATOR . 'print_queue';

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException('Folderul cozii de imprimare nu a putut fi creat.');
        }
        return $directory;
    }
}

if (!function_exists('agecs_printer_enqueue_payload')) {
    function agecs_printer_enqueue_payload(int $clientId, int $locationId, array $payload, string $prefix = 'print', ?string $stableKey = null): string
    {
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Documentul nu a putut fi convertit în JSON pentru imprimare.');
        }

        $directory = agecs_printer_queue_directory($clientId, $locationId);
        $safePrefix = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $prefix) ?: 'print';
        if ($stableKey !== null && trim($stableKey) !== '') {
            $safeStableKey = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $stableKey) ?: $safePrefix;
            $finalPath = $directory . DIRECTORY_SEPARATOR . $safeStableKey . '.json';
            if (is_file($finalPath)) {
                return $finalPath;
            }
        } else {
            $stamp = date('Ymd_His') . '_' . sprintf('%06d', (int)(microtime(true) * 1000000) % 1000000);
            try {
                $random = bin2hex(random_bytes(5));
            } catch (Throwable $e) {
                $random = str_replace('.', '', uniqid('', true));
            }
            $finalPath = $directory . DIRECTORY_SEPARATOR . $stamp . '_' . $safePrefix . '_' . $random . '.json';
        }
        $tempPath = $finalPath . '.' . str_replace('.', '', uniqid('tmp_', true));
        $written = @file_put_contents($tempPath, $json, LOCK_EX);
        if ($written === false || $written !== strlen($json)) {
            @unlink($tempPath);
            throw new RuntimeException('Documentul nu a putut fi scris în coada imprimantei.');
        }
        if (!@rename($tempPath, $finalPath)) {
            @unlink($tempPath);
            if ($stableKey !== null && is_file($finalPath)) {
                return $finalPath;
            }
            throw new RuntimeException('Documentul nu a putut fi finalizat în coada imprimantei.');
        }

        return $finalPath;
    }
}
