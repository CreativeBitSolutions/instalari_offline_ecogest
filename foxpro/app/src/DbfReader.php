<?php
declare(strict_types=1);

final class DbfReader
{
    private string $path;
    private string $encoding;
    private int $recordCount = 0;
    private int $headerLength = 0;
    private int $recordLength = 0;
    /** @var array<int,array{name:string,type:string,length:int,decimals:int}> */
    private array $fields = [];

    public function __construct(string $path, string $encoding = 'CP1250')
    {
        $this->path = $path;
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
            throw new RuntimeException('DBF lipsa sau inaccesibil: ' . $this->path);
        }

        $handle = fopen($this->path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Nu pot deschide DBF: ' . $this->path);
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
