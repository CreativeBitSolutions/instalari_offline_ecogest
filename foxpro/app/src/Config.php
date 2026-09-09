<?php
declare(strict_types=1);

final class Config
{
    public static function load(): array
    {
        self::ensureStorage();

        $defaults = self::defaults();
        $path = self::storagePath();

        if (!is_file($path)) {
            self::write($defaults);
            return $defaults;
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            return $defaults;
        }

        return array_merge($defaults, $decoded);
    }

    public static function saveFromPost(array $post): array
    {
        $current = self::load();
        $next = $current;

        foreach ([
            'restaurant_name',
            'note_path',
            'compnote_path',
            'bonuri_path',
            'totaluri_path',
            'comp_total_path',
            'disponibilitati_path',
            'prod_mat_path',
            'dbf_encoding',
        ] as $key) {
            if (array_key_exists($key, $post)) {
                $next[$key] = trim((string) $post[$key]);
            }
        }

        $next['include_deleted_bonuri'] = isset($post['include_deleted_bonuri']);

        self::write($next);
        return $next;
    }

    public static function defaults(): array
    {
        $root = dirname(__DIR__, 2);

        return [
            'restaurant_name' => "TOAN'S",
            'note_path' => $root . DIRECTORY_SEPARATOR . 'note.dbf',
            'compnote_path' => $root . DIRECTORY_SEPARATOR . 'COMPNOTE.DBF',
            'bonuri_path' => $root . DIRECTORY_SEPARATOR . 'temp_bonuri.dbf',
            'totaluri_path' => $root . DIRECTORY_SEPARATOR . 'totaluri.dbf',
            'comp_total_path' => $root . DIRECTORY_SEPARATOR . 'comp_total.dbf',
            'disponibilitati_path' => $root . DIRECTORY_SEPARATOR . 'disponibilitati.dbf',
            'prod_mat_path' => $root . DIRECTORY_SEPARATOR . 'prod_mat.dbf',
            'dbf_encoding' => 'CP1250',
            'include_deleted_bonuri' => true,
        ];
    }

    public static function pathStatus(array $config): array
    {
        $paths = [
            'note_path' => 'note.dbf',
            'compnote_path' => 'COMPNOTE.DBF',
            'bonuri_path' => 'temp_bonuri.dbf',
            'totaluri_path' => 'totaluri.dbf',
            'comp_total_path' => 'comp_total.dbf',
            'disponibilitati_path' => 'disponibilitati.dbf',
            'prod_mat_path' => 'prod_mat.dbf',
        ];

        $status = [];
        foreach ($paths as $key => $label) {
            $path = (string) ($config[$key] ?? '');
            $exists = is_file($path);
            $readable = $exists && is_readable($path);
            $status[$key] = [
                'label' => $label,
                'path' => $path,
                'exists' => $exists,
                'readable' => $readable,
                'size' => $exists ? (int) filesize($path) : 0,
                'message' => !$exists ? 'Fisier lipsa' : (!$readable ? 'Fisier inaccesibil' : 'OK'),
            ];
        }

        return $status;
    }

    private static function storagePath(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'config.json';
    }

    private static function ensureStorage(): void
    {
        $storage = dirname(self::storagePath());
        if (!is_dir($storage)) {
            mkdir($storage, 0777, true);
        }
    }

    private static function write(array $config): void
    {
        self::ensureStorage();
        file_put_contents(
            self::storagePath(),
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }
}
