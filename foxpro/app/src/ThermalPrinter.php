<?php
declare(strict_types=1);

final class ThermalPrinter
{
    public static function settings(): array
    {
        $defaults = ['printer_name' => 'BAR'];
        $path = self::settingsPath();
        if (!is_file($path)) {
            return $defaults;
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            return $defaults;
        }

        $printer = trim((string) ($decoded['printer_name'] ?? ''));
        return ['printer_name' => $printer !== '' ? $printer : $defaults['printer_name']];
    }

    public static function saveSettings(string $printerName): array
    {
        $printerName = trim($printerName);
        if ($printerName === '') {
            throw new InvalidArgumentException('Completeaza numele imprimantei.');
        }

        $storage = dirname(self::settingsPath());
        if (!is_dir($storage) && !mkdir($storage, 0777, true) && !is_dir($storage)) {
            throw new RuntimeException('Folderul pentru configurare nu poate fi creat.');
        }

        $settings = ['printer_name' => $printerName];
        $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents(self::settingsPath(), $json . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Configurarea imprimantei nu a putut fi salvata.');
        }

        return $settings;
    }

    public static function installedPrinters(): array
    {
        if (!function_exists('shell_exec')) {
            return [];
        }

        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        if (in_array('shell_exec', $disabled, true)) {
            return [];
        }

        $command = 'powershell.exe -NoProfile -NonInteractive -Command '
            . escapeshellarg('Get-Printer | Select-Object -ExpandProperty Name | ConvertTo-Json -Compress');
        $output = @shell_exec($command);
        if (!is_string($output) || trim($output) === '') {
            return [];
        }

        $decoded = json_decode(trim($output), true);
        if (!is_array($decoded)) {
            $decoded = [trim($output)];
        }

        $printers = [];
        foreach ($decoded as $printer) {
            $printer = trim((string) $printer);
            if ($printer !== '') {
                $printers[$printer] = $printer;
            }
        }
        natcasesort($printers);
        return array_values($printers);
    }

    public static function send(string $printerName, string $content): void
    {
        $printerName = trim($printerName);
        if ($printerName === '') {
            throw new InvalidArgumentException('Nu este selectata nicio imprimanta.');
        }

        $scriptPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'printer_bold' . DIRECTORY_SEPARATOR . 'agecs_raw_print_text.ps1';
        if (!is_file($scriptPath)) {
            throw new RuntimeException('Scriptul pentru imprimare termica lipseste.');
        }
        if (!function_exists('shell_exec')) {
            throw new RuntimeException('PHP nu permite pornirea imprimarii directe.');
        }

        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        if (in_array('shell_exec', $disabled, true)) {
            throw new RuntimeException('Functia de imprimare directa este dezactivata in PHP.');
        }

        $temporaryFile = tempnam(sys_get_temp_dir(), 'foxpro_raw_');
        if (!is_string($temporaryFile) || $temporaryFile === '') {
            throw new RuntimeException('Fisierul temporar pentru imprimare nu poate fi creat.');
        }

        try {
            $payload = self::asciiText($content)
                . str_repeat("\r\n", 6)
                . "\x1D\x56\x00";
            if (file_put_contents($temporaryFile, $payload, LOCK_EX) === false) {
                throw new RuntimeException('Raportul nu poate fi scris temporar.');
            }

            $command = 'powershell.exe -NoProfile -NonInteractive -ExecutionPolicy Bypass -File '
                . escapeshellarg($scriptPath)
                . ' -PrinterName ' . escapeshellarg($printerName)
                . ' -InputFile ' . escapeshellarg($temporaryFile)
                . ' 2>&1';
            $output = @shell_exec($command);
            $output = is_string($output) ? trim($output) : '';
            if (strpos($output, 'AGECS_RAW_PRINT_OK') === false) {
                throw new RuntimeException('Windows nu a acceptat lucrarea de imprimare.' . ($output !== '' ? ' ' . $output : ''));
            }
        } finally {
            if (is_file($temporaryFile)) {
                @unlink($temporaryFile);
            }
        }
    }

    public static function sendDocuments(string $printerName, array $documents): void
    {
        $sent = false;
        foreach ($documents as $document) {
            $document = trim((string) $document);
            if ($document === '') {
                continue;
            }
            self::send($printerName, $document);
            $sent = true;
        }

        if (!$sent) {
            throw new InvalidArgumentException('Nu exista niciun document pentru imprimare.');
        }
    }

    private static function settingsPath(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'thermal_printer.json';
    }

    private static function asciiText(string $text): string
    {
        $text = strtr($text, [
            'ă' => 'a', 'Ă' => 'A',
            'â' => 'a', 'Â' => 'A',
            'î' => 'i', 'Î' => 'I',
            'ș' => 's', 'Ș' => 'S',
            'ş' => 's', 'Ş' => 'S',
            'ț' => 't', 'Ț' => 'T',
            'ţ' => 't', 'Ţ' => 'T',
        ]);
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if (is_string($converted)) {
                $text = $converted;
            }
        }
        $text = preg_replace('/[^\x09\x0A\x20-\x7E]/', '', $text) ?? '';
        $lines = array_map(static fn (string $line): string => rtrim(str_replace("\t", '    ', $line)), explode("\n", $text));
        return str_replace("\n", "\r\n", trim(implode("\n", $lines)));
    }
}
