<?php
declare(strict_types=1);

final class ReportRepository
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function build(string $date, string $operator = ''): array
    {
        $ymd = $this->normalizeDate($date);
        $operator = trim($operator);

        $notes = [];
        $operators = [];
        foreach ($this->reader('note_path')->rows(false) as $row) {
            if ((string) ($row['DATA'] ?? '') !== $ymd) {
                continue;
            }

            $name = trim((string) ($row['OSPATAR'] ?? ''));
            $name = $name !== '' ? $name : 'NECUNOSCUT';
            $number = (string) ($row['NR_NOTA'] ?? '');
            $notes[$number] = $row;

            if (!isset($operators[$name])) {
                $operators[$name] = $this->emptyOperator($name);
            }

            $this->addNoteToOperator($operators[$name], $row);
        }

        if ($operator !== '') {
            $operators = array_filter(
                $operators,
                static fn (array $row): bool => strcasecmp((string) $row['name'], $operator) === 0
            );
        }

        $availability = $this->readAvailability();
        foreach ($this->reader('compnote_path')->rows(false) as $row) {
            $number = (string) ($row['NR_NOTA'] ?? '');
            if (!isset($notes[$number])) {
                continue;
            }

            $note = $notes[$number];
            $name = trim((string) ($note['OSPATAR'] ?? ''));
            $name = $name !== '' ? $name : 'NECUNOSCUT';
            if (!isset($operators[$name])) {
                continue;
            }

            $productId = (string) ($row['PRODUS_ID'] ?? '');
            $gest = (string) ($note['GESTIUNEA'] ?? '');
            $depList = $availability[$gest][$productId] ?? $availability['*'][$productId] ?? '';
            $category = $depList === '2' ? 'bar' : ($depList === '1' ? 'buc' : 'unknown');
            $productName = trim((string) ($row['PRODUS'] ?? ''));
            $productKey = $productId . '|' . $productName;
            $quantity = $this->number($row['CANT'] ?? 0);
            $value = $this->number($row['VALOARE'] ?? 0);

            $operators[$name]['product_lines']++;
            $operators[$name]['quantity'] += $quantity;
            $operators[$name]['products'][$productKey] ??= [
                'id' => $productId,
                'name' => $productName,
                'quantity' => 0.0,
                'value' => 0.0,
                'category' => $category,
            ];
            $operators[$name]['products'][$productKey]['quantity'] += $quantity;
            $operators[$name]['products'][$productKey]['value'] += $value;
            $operators[$name][$category] += $value;
        }

        foreach ($operators as &$row) {
            $row['products'] = array_values($row['products']);
            usort($row['products'], static function (array $left, array $right): int {
                return $right['value'] <=> $left['value'];
            });
            $row['products'] = array_slice($row['products'], 0, 12);
            $row['other'] = max(
                0.0,
                $row['total'] - $row['card'] - $row['cash']
            );
        }
        unset($row);

        usort($operators, static function (array $left, array $right): int {
            return $right['total'] <=> $left['total'];
        });

        $totals = $this->emptyTotals();
        foreach ($operators as $row) {
            foreach (['notes', 'total', 'card', 'cash', 'other', 'bar', 'buc', 'unknown', 'product_lines', 'quantity'] as $field) {
                $totals[$field] += (float) $row[$field];
            }
        }

        $closing = $this->readClosingTotals($ymd);
        return [
            'date' => $date,
            'date_dbf' => $ymd,
            'operator_filter' => $operator,
            'operators' => array_values($operators),
            'totals' => $totals,
            'closing' => $closing,
        ];
    }

    private function emptyOperator(string $name): array
    {
        return [
            'name' => $name,
            'notes' => 0,
            'total' => 0.0,
            'card' => 0.0,
            'cash' => 0.0,
            'other' => 0.0,
            'bar' => 0.0,
            'buc' => 0.0,
            'unknown' => 0.0,
            'product_lines' => 0,
            'quantity' => 0.0,
            'products' => [],
        ];
    }

    private function emptyTotals(): array
    {
        return [
            'notes' => 0.0,
            'total' => 0.0,
            'card' => 0.0,
            'cash' => 0.0,
            'other' => 0.0,
            'bar' => 0.0,
            'buc' => 0.0,
            'unknown' => 0.0,
            'product_lines' => 0.0,
            'quantity' => 0.0,
        ];
    }

    private function addNoteToOperator(array &$operator, array $row): void
    {
        $operator['notes']++;
        $operator['total'] += $this->number($row['TOTAL'] ?? 0);
        $operator['card'] += $this->number($row['CARD'] ?? 0);
        $operator['cash'] += $this->number($row['NUMERAR'] ?? 0);
        foreach (['BON_MASA', 'ONLINE', 'BON_VAL', 'VIRAMENT', 'ANGAJATI'] as $field) {
            $operator['other'] += $this->number($row[$field] ?? 0);
        }
    }

    private function readAvailability(): array
    {
        $map = ['*' => []];
        foreach ($this->reader('disponibilitati_path')->rows(false) as $row) {
            $code = (string) ($row['CODP'] ?? '');
            $gest = (string) ($row['GEST'] ?? '');
            if ($code === '') {
                continue;
            }
            $map[$gest][$code] = (string) ($row['DEP_LIST'] ?? '');
            $map['*'][$code] ??= (string) ($row['DEP_LIST'] ?? '');
        }

        return $map;
    }

    private function readClosingTotals(string $ymd): array
    {
        $result = [
            'rows' => 0,
            'operator' => '',
            'total' => 0.0,
            'card' => 0.0,
            'cash' => 0.0,
            'bar' => 0.0,
            'buc' => 0.0,
        ];

        $path = (string) ($this->config['totaluri_path'] ?? '');
        if (trim($path) === '') {
            return $result;
        }

        foreach ($this->reader('totaluri_path')->rows(false) as $row) {
            if ((string) ($row['DATA'] ?? '') !== $ymd) {
                continue;
            }
            $result['rows']++;
            $result['operator'] = trim((string) ($row['OPERATOR'] ?? ''));
            $result['total'] += $this->number($row['VALOARE'] ?? 0);
            $result['card'] += $this->number($row['CARD'] ?? 0);
            $result['cash'] += $this->number($row['NUMERAR'] ?? 0);
            $result['bar'] += $this->number($row['BAR'] ?? 0);
            $result['buc'] += $this->number($row['BUC'] ?? 0);
        }

        return $result;
    }

    private function reader(string $key): DbfReader
    {
        $path = trim((string) ($this->config[$key] ?? ''));
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Path invalid pentru ' . $key . ': ' . ($path !== '' ? $path : 'valoare vida'));
        }

        return new DbfReader($path, (string) ($this->config['dbf_encoding'] ?? 'CP1250'));
    }

    private function normalizeDate(string $date): string
    {
        $date = trim($date);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = DateTimeImmutable::getLastErrors();
        if ($parsed === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new InvalidArgumentException('Data raportului nu este valida.');
        }

        return $parsed->format('Ymd');
    }

    private function number(mixed $value): float
    {
        $text = str_replace(',', '.', trim((string) $value));
        return is_numeric($text) ? (float) $text : 0.0;
    }
}
