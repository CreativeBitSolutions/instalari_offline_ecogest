<?php
declare(strict_types=1);

if (!function_exists('agecs_printer_client_format_config')) {
    function agecs_printer_client_format_config($clientId): array
    {
        $defaults = [
            'bold' => true,
            'size' => '11',
            'align' => 'left',
            'width_chars' => 42,
            'copies' => 2,
            'destination' => 'BAR',
        ];

        $apiRoot = '';
        if (function_exists('offline_api_root_path')) {
            $apiRoot = rtrim((string)offline_api_root_path(), '/\\');
        } elseif (defined('RESTAURANT_OFFLINE_API_DIR')) {
            $apiRoot = rtrim((string)constant('RESTAURANT_OFFLINE_API_DIR'), '/\\');
        }
        if ($apiRoot === '') {
            return $defaults;
        }

        $configPath = $apiRoot . DIRECTORY_SEPARATOR . 'printer_format.json';
        if (!is_file($configPath)) {
            return $defaults;
        }

        $decoded = json_decode((string)file_get_contents($configPath), true);
        if (!is_array($decoded)) {
            return $defaults;
        }

        $size = trim((string)($decoded['size'] ?? $defaults['size']));
        $align = strtolower(trim((string)($decoded['align'] ?? $defaults['align'])));
        if ($size !== '' && (!ctype_digit($size) || (int)$size < 6 || (int)$size > 48)) {
            $size = $defaults['size'];
        }
        if (!in_array($align, ['', 'left', 'center', 'right', 'justified'], true)) {
            $align = $defaults['align'];
        }

        $width = (int)($decoded['width_chars'] ?? $defaults['width_chars']);
        if ($width < 24 || $width > 80) {
            $width = $defaults['width_chars'];
        }
        $copies = (int)($decoded['copies'] ?? $defaults['copies']);
        if ($copies < 1 || $copies > 5) {
            $copies = $defaults['copies'];
        }

        return [
            'bold' => filter_var($decoded['bold'] ?? $defaults['bold'], FILTER_VALIDATE_BOOL),
            'size' => $size,
            'align' => $align,
            'width_chars' => $width,
            'copies' => $copies,
            'destination' => 'BAR',
        ];
    }
}

if (!function_exists('agecs_printer_clean_format_tags')) {
    function agecs_printer_clean_format_tags(string $line): string
    {
        $line = (string)preg_replace('/<\/?b>/i', '', $line);
        $line = (string)preg_replace('/<size\s*=\s*"[^"]*"\s*>|<\/size>/i', '', $line);
        return (string)preg_replace('/<align\s*=\s*"[^"]*"\s*>|<\/align>/i', '', $line);
    }
}

if (!function_exists('agecs_printer_format_line')) {
    function agecs_printer_format_line(string $line, $clientId): string
    {
        $config = agecs_printer_client_format_config($clientId);
        $line = agecs_printer_clean_format_tags($line);

        if ($config['size'] !== '') {
            $line = '<size="' . $config['size'] . '">' . $line . '</size>';
        }
        if ($config['bold']) {
            $line = '<b>' . $line . '</b>';
        }
        if ($config['align'] !== '') {
            $line = '<align="' . $config['align'] . '">' . $line . '</align>';
        }

        return $line;
    }
}

if (!function_exists('agecs_printer_bold_content')) {
    function agecs_printer_bold_content($content, $clientId): string
    {
        $content = (string)$content;
        if ($content === '') {
            return '';
        }

        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        foreach ($lines as &$line) {
            if ($line !== '') {
                $line = agecs_printer_format_line($line, $clientId);
            }
        }
        unset($line);

        return implode("\n", $lines);
    }
}

if (!function_exists('agecs_printer_bold_jobs')) {
    function agecs_printer_bold_jobs(array $jobs, $clientId): array
    {
        foreach ($jobs as &$job) {
            if (is_array($job) && array_key_exists('continut', $job)) {
                $job['continut'] = agecs_printer_bold_content($job['continut'], $clientId);
            }
            if (is_array($job) && array_key_exists('mesaj', $job)) {
                $job['mesaj'] = agecs_printer_bold_content($job['mesaj'], $clientId);
            }
        }
        unset($job);

        return $jobs;
    }
}

if (!function_exists('agecs_printer_bold_payload')) {
    function agecs_printer_bold_payload(array $payload, $clientId): array
    {
        if (isset($payload['data']) && is_array($payload['data'])) {
            $payload['data'] = agecs_printer_bold_jobs($payload['data'], $clientId);
        }
        return $payload;
    }
}
