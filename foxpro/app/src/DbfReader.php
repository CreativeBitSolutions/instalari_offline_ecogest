<?php
declare(strict_types=1);

final class DbfReader
{
    private string $path;
    private string $sourcePath;
    private string $encoding;
    private int $recordCount = 0;
    private int $headerLength = 0;
    private int $recordLength = 0;
    /** @var array<int,string> */
    private static array $snapshots = [];
    private static bool $cleanupRegistered = false;
    /** @var array<int,array{name:string,type:string,length:int,decimals:int}> */
    private array $fields = [];

    public function __construct(string $path, string $encoding = 'CP1250')
    {
        $this->sourcePath = trim($path);
        $this->path = self::createSnapshot($this->sourcePath);
        $this->encoding = $encoding !== '' ? $encoding : 'CP1250';
        $this->readHeader();
    }

    public function recordCount(): int
    {
        return $this->recordCount;
    }

    public function fields(): array
    {
        return $this->fields;
    }

    public static function snapshotPath(string $sourcePath): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'dbf_snapshots'
            . DIRECTORY_SEPARATOR . sha1(trim($sourcePath)) . '.dbf';
    }

    public static function findLocalCandidate(string $sourcePath): ?string
    {
        $sourcePath = trim($sourcePath);
        if (preg_match('/^\\\\[^\\]+\\(.+)$/', $sourcePath, $matches) !== 1) {
            return null;
        }

        $relativePath = $matches[1];
        foreach (range('C', 'Z') as $drive) {
            $candidate = $drive . ':\\' . $relativePath;
            if (is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    public function rows(bool $includeDeleted = false): Generator
    {
        $handle = fopen($this->path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Nu pot deschide DBF: ' . $this->path);
        }

        try {
            fseek($handle, $this->headerLength);

            for ($i = 0; $i < $this->recordCount; $i++) {
                $record = fread($handle, $this->recordLength);
                if ($record === false || strlen($record) < $this->recordLength) {
                    break;
                }

                $deleted = isset($record[0]) && $record[0] === '*';
                if ($deleted && !$includeDeleted) {
                    continue;
                }

                $row = [];
                $offset = 1;
                $hasValue = false;

                foreach ($this->fields as $field) {
                    $raw = substr($record, $offset, $field['length']);
                    $offset += $field['length'];
                    $value = $this->decodeField($raw, $field['type']);
                    $row[$field['name']] = $value;
                    if ($value !== '') {
                        $hasValue = true;
                    }
                }

                if (!$hasValue) {
                    continue;
                }

                $row['__deleted'] = $deleted;
                $row['__record'] = $i + 1;
                yield $row;
            }
        } finally {
            fclose($handle);
        }
    }

    private function readHeader(): void
    {
        if (!is_file($this->path) || !is_readable($this->path)) {
            throw new RuntimeException('Copia temporara DBF lipseste sau este inaccesibila pentru: ' . $this->sourcePath);
        }

        $handle = fopen($this->path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Nu pot deschide copia temporara DBF pentru: ' . $this->sourcePath);
        }

        try {
            $header = fread($handle, 32);
            if ($header === false || strlen($header) < 32) {
                throw new RuntimeException('Header DBF invalid: ' . $this->path);
            }

            $this->recordCount = unpack('V', substr($header, 4, 4))[1];
            $this->headerLength = unpack('v', substr($header, 8, 2))[1];
            $this->recordLength = unpack('v', substr($header, 10, 2))[1];

            $fields = [];
            while (!feof($handle)) {
                $descriptor = fread($handle, 32);
                if ($descriptor === false || $descriptor === '' || ord($descriptor[0]) === 0x0D) {
                    break;
                }

                $name = rtrim(strstr(substr($descriptor, 0, 11), "\0", true) ?: substr($descriptor, 0, 11), "\0 ");
                $fields[] = [
                    'name' => $name,
                    'type' => $descriptor[11],
                    'length' => ord($descriptor[16]),
                    'decimals' => ord($descriptor[17]),
                ];
            }

            $this->fields = $fields;
        } finally {
            fclose($handle);
        }
    }

    private static function createSnapshot(string $sourcePath): string
    {
        $directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'dbf_snapshots';
        if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException('Nu pot crea folderul pentru copiile temporare DBF: ' . $directory);
        }

        if (!is_writable($directory)) {
            throw new RuntimeException('Folderul copiilor temporare DBF nu este accesibil la scriere: ' . $directory);
        }

        $mirror = self::snapshotPath($sourcePath);
        $copySource = $sourcePath;
        if ($sourcePath === '' || !is_file($sourcePath) || !is_readable($sourcePath)) {
            $copySource = self::findLocalCandidate($sourcePath) ?? '';
        }

        if ($copySource === '') {
            if (is_file($mirror) && is_readable($mirror) && (int) @filesize($mirror) >= 32) {
                return $mirror;
            }

            throw new RuntimeException('DBF lipsa sau inaccesibil: ' . ($sourcePath !== '' ? $sourcePath : 'path gol'));
        }

        $sourceSize = (int) @filesize($copySource);
        $lastError = '';
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $snapshot = tempnam($directory, 'foxpro-dbf-');
            if ($snapshot === false) {
                throw new RuntimeException('Nu pot crea fisierul pentru copia temporara DBF.');
            }

            $copyError = '';
            set_error_handler(static function (int $severity, string $message) use (&$copyError): bool {
                $copyError = $message;
                return true;
            });
            $copied = copy($copySource, $snapshot);
            restore_error_handler();

            $size = $copied && is_file($snapshot) ? (int) @filesize($snapshot) : 0;
            if ($copied && $size >= 32 && ($sourceSize < 32 || $size === $sourceSize)) {
                if (self::replaceMirror($snapshot, $mirror)) {
                    return $mirror;
                }

                self::$snapshots[] = $snapshot;
                self::registerCleanup();
                return $snapshot;
            }

            @unlink($snapshot);
            $lastError = $copyError !== '' ? $copyError : 'copiere esuata';
            if ($attempt < 5) {
                usleep(250000);
            }
        }

        if (is_file($mirror) && is_readable($mirror) && (int) @filesize($mirror) >= 32) {
            return $mirror;
        }

        throw new RuntimeException(
            'Nu pot copia DBF-ul pentru citire fara lock: ' . $sourcePath
            . '. Verifica accesul la folderul FoxPro sau daca fisierul este blocat exclusiv. Detalii: ' . $lastError
        );
    }

    private static function replaceMirror(string $snapshot, string $mirror): bool
    {
        if (@rename($snapshot, $mirror)) {
            return true;
        }

        @unlink($mirror);
        if (@rename($snapshot, $mirror)) {
            return true;
        }

        if (@copy($snapshot, $mirror)) {
            @unlink($snapshot);
            return true;
        }

        return false;
    }

    private static function registerCleanup(): void
    {
        if (self::$cleanupRegistered) {
            return;
        }

        self::$cleanupRegistered = true;
        register_shutdown_function(static function (): void {
            foreach (self::$snapshots as $snapshot) {
                @unlink($snapshot);
            }
            self::$snapshots = [];
        });
    }

    private function decodeField(string $raw, string $type): string
    {
        $value = trim($raw, "\0 ");

        if ($value === '') {
            return '';
        }

        if ($type === 'C' || $type === 'M') {
            $converted = @iconv($this->encoding, 'UTF-8//TRANSLIT//IGNORE', $value);
            if ($converted !== false) {
                return trim($converted);
            }
        }

        return trim($value);
    }
}
