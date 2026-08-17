<?php
declare(strict_types=1);

function agecs_printer_format_defaults(): array
{
    return [
        'bold' => true,
        'size' => '11',
        'align' => 'left',
    ];
}

function agecs_printer_format_config_path(): string
{
    return __DIR__ . DIRECTORY_SEPARATOR . 'printer_format.json';
}

function agecs_printer_normalize_format_config(array $config): array
{
    $defaults = agecs_printer_format_defaults();
    $size = trim((string)($config['size'] ?? $defaults['size']));
    $align = strtolower(trim((string)($config['align'] ?? $defaults['align'])));

    if ($size !== '' && (!ctype_digit($size) || (int)$size < 6 || (int)$size > 48)) {
        $size = $defaults['size'];
    }

    if (!in_array($align, ['', 'left', 'center', 'right', 'justified'], true)) {
        $align = $defaults['align'];
    }

    return [
        'bold' => filter_var($config['bold'] ?? $defaults['bold'], FILTER_VALIDATE_BOOL),
        'size' => $size,
        'align' => $align,
    ];
}

function agecs_printer_load_format_config(): array
{
    $path = agecs_printer_format_config_path();
    if (!is_file($path)) {
        return agecs_printer_format_defaults();
    }

    $content = file_get_contents($path);
    if ($content === false) {
        return agecs_printer_format_defaults();
    }

    $decoded = json_decode($content, true);
    if (!is_array($decoded)) {
        return agecs_printer_format_defaults();
    }

    return agecs_printer_normalize_format_config($decoded);
}

function agecs_printer_clean_format_tags(string $line): string
{
    $line = (string)preg_replace('/<\/?b>/i', '', $line);
    $line = (string)preg_replace('/<size\s*=\s*["\'][^"\']*["\']\s*>|<\/size>/i', '', $line);
    return (string)preg_replace('/<align\s*=\s*["\'][^"\']*["\']\s*>|<\/align>/i', '', $line);
}

function agecs_printer_format_line(string $line, array $config): string
{
    $line = agecs_printer_clean_format_tags($line);

    if ($config['size'] !== '') {
        $line = '<size="' . $config['size'] . '">' . $line . '</size>';
    }

    if ($config['bold']) {
        $line = '<b>' . $line . '</b>';
    }

    if ($config['align'] !== '' && $config['align'] !== 'left') {
        $line = '<align="' . $config['align'] . '">' . $line . '</align>';
    }

    return $line;
}

function agecs_printer_format_content(string $content, array $config): string
{
    if ($content === '') {
        return $content;
    }

    $lines = preg_split('/\r\n|\r|\n/', $content);
    if (!is_array($lines)) {
        return $content;
    }

    foreach ($lines as &$line) {
        if ($line !== '') {
            $line = agecs_printer_format_line($line, $config);
        }
    }
    unset($line);

    return implode("\n", $lines);
}

function agecs_printer_format_payload(array $payload, array $config): array
{
    foreach ($payload as $key => &$value) {
        if (is_array($value)) {
            $value = agecs_printer_format_payload($value, $config);
            continue;
        }

        if (is_string($value) && in_array((string)$key, ['continut', 'mesaj'], true)) {
            $value = agecs_printer_format_content($value, $config);
        }
    }
    unset($value);

    return $payload;
}

function agecs_printer_normalize_scanner_integer($value, string $field): int
{
    if (is_int($value)) {
        $number = $value;
    } elseif (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
        $number = (int)$value;
    } else {
        throw new InvalidArgumentException('Câmpul ' . $field . ' nu este un număr întreg valid.');
    }

    if ($number < -2147483648 || $number > 2147483647) {
        throw new InvalidArgumentException('Câmpul ' . $field . ' depășește limita acceptată de scanner.');
    }

    return $number;
}

function agecs_printer_normalize_scanner_payload(array $payload): array
{
    if (!isset($payload['data']) || !is_array($payload['data'])) {
        throw new InvalidArgumentException('Lista documentelor pentru imprimantă lipsește din răspuns.');
    }

    foreach ($payload['data'] as $index => &$document) {
        if (!is_array($document)) {
            throw new InvalidArgumentException('Documentul de la poziția ' . $index . ' nu este valid.');
        }

        if (!array_key_exists('id', $document)) {
            $document['id'] = 0;
        }

        foreach (['id', 'de_trimis_la_imprimanta', 'nrbon', 'locatie'] as $field) {
            if (!array_key_exists($field, $document)) {
                throw new InvalidArgumentException('Câmpul ' . $field . ' lipsește din document.');
            }
            $document[$field] = agecs_printer_normalize_scanner_integer($document[$field], $field);
        }

        if (!array_key_exists('departament_listare', $document)) {
            throw new InvalidArgumentException('Câmpul departament_listare lipsește din document.');
        }

        $department = strtoupper(trim((string)$document['departament_listare']));
        if (!in_array($department, ['BAR', 'BUCATARIE', 'IMPRIMANTA3'], true)) {
            throw new InvalidArgumentException(
                'Departamentul ' . ($department !== '' ? $department : '(gol)') . ' nu este acceptat de scanner.'
            );
        }
        $document['departament_listare'] = $department;
    }
    unset($document);

    return $payload;
}
