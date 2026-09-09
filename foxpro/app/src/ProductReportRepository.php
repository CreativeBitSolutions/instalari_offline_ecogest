<?php
declare(strict_types=1);

final class ProductReportRepository
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
            $name = trim((string) ($row['OSPATAR'] ?? '')) ?: 'NECUNOSCUT';
            $notes[(string) ($row['NR_NOTA'] ?? '')] = $row;
            if (!isset($operators[$name])) {
                $operators[$name] = [
                    'name' => $name,
                    'notes' => 0,
                    'total' => 0.0,
                    'cash' => 0.0,
                    'card' => 0.0,
                    'other' => 0.0,
                    'products' => [],
                    'product_lines' => 0,
                    'quantity' => 0.0,
                ];
            }
            $operators[$name]['notes']++;
            $operators[$name]['total'] += $this->number($row['TOTAL'] ?? 0);
            $operators[$name]['cash'] += $this->number($row['NUMERAR'] ?? 0);
            $operators[$name]['card'] += $this->number($row['CARD'] ?? 0);
            foreach (['BON_MASA', 'ONLINE', 'BON_VAL', 'VIRAMENT', 'ANGAJATI'] as $field) {
                $operators[$name]['other'] += $this->number($row[$field] ?? 0);
            }
        }

        if ($operator !== '') {
            $operators = array_filter(
                $operators,
                static fn (array $row): bool => strcasecmp((string) $row['name'], $operator) === 0
            );
        }

        foreach ($this->reader('compnote_path')->rows(false) as $row) {
            $noteNumber = (string) ($row['NR_NOTA'] ?? '');
            if (!isset($notes[$noteNumber])) {
                continue;
            }
            $name = trim((string) ($notes[$noteNumber]['OSPATAR'] ?? '')) ?: 'NECUNOSCUT';
            if (!isset($operators[$name])) {
                continue;
            }
            $productId = (string) ($row['PRODUS_ID'] ?? '');
            $productName = trim((string) ($row['PRODUS'] ?? '')) ?: 'PRODUS FARA DENUMIRE';
            $key = $productId . '|' . $productName;
            $operators[$name]['products'][$key] ??= [
                'id' => $productId,
                'name' => $productName,
                'quantity' => 0.0,
                'value' => 0.0,
            ];
            $operators[$name]['products'][$key]['quantity'] += $this->number($row['CANT'] ?? 0);
            $operators[$name]['products'][$key]['value'] += $this->number($row['VALOARE'] ?? 0);
            $operators[$name]['product_lines']++;
            $operators[$name]['quantity'] += $this->number($row['CANT'] ?? 0);
        }

        foreach ($operators as &$row) {
            $row['products'] = array_values($row['products']);
            usort($row['products'], static fn (array $left, array $right): int => $right['value'] <=> $left['value']);
        }
        unset($row);
        usort($operators, static fn (array $left, array $right): int => $right['total'] <=> $left['total']);

        $protocolOperators = $this->readProtocolByOperator($notes, $operators);
        $protocol = $this->summarizeProtocol($protocolOperators);
        return [
            'date' => $date,
            'operators' => array_values($operators),
            'protocol' => $protocol,
            'protocol_operators' => $protocolOperators,
            'totals' => $this->totals($operators),
        ];
    }

    private function totals(array $operators): array
    {
        $totals = ['notes' => 0, 'total' => 0.0, 'cash' => 0.0, 'card' => 0.0, 'other' => 0.0, 'quantity' => 0.0, 'product_lines' => 0];
        foreach ($operators as $row) {
            foreach (array_keys($totals) as $field) {
                $totals[$field] += $row[$field] ?? 0;
            }
        }
        return $totals;
    }

    private function readProtocolByOperator(array $notes, array $operators): array
    {
        $protocolNotes = [];
        foreach ($notes as $noteNumber => $note) {
            if ($this->isProtocolValue($note['PROTOCOL'] ?? '')) {
                $operatorName = trim((string) ($note['OSPATAR'] ?? '')) ?: 'NECUNOSCUT';
                if (isset($operators[$operatorName])) {
                    $protocolNotes[(string) $noteNumber] = $operatorName;
                }
            }
        }

        $protocolOperators = [];
        foreach ($protocolNotes as $operatorName) {
            $protocolOperators[$operatorName] ??= [
                'name' => $operatorName,
                'notes' => 0,
                'total' => 0.0,
                'quantity' => 0.0,
                'value' => 0.0,
                'products' => [],
            ];
        }
        foreach ($protocolNotes as $noteNumber => $operatorName) {
            $protocolOperators[$operatorName]['notes']++;
            $protocolOperators[$operatorName]['total'] += $this->number($notes[$noteNumber]['PROTOCOL'] ?? 0);
        }

        foreach ($this->reader('compnote_path')->rows(false) as $row) {
            $noteNumber = (string) ($row['NR_NOTA'] ?? '');
            if (!isset($protocolNotes[$noteNumber])) {
                continue;
            }
            $operatorName = $protocolNotes[$noteNumber];
            $id = (string) ($row['PRODUS_ID'] ?? '');
            $name = trim((string) ($row['PRODUS'] ?? '')) ?: 'PRODUS FARA DENUMIRE';
            $key = $id . '|' . $name;
            $protocolOperators[$operatorName]['products'][$key] ??= [
                'id' => $id,
                'name' => $name,
                'quantity' => 0.0,
                'value' => 0.0,
            ];
            $quantity = $this->number($row['CANT'] ?? 0);
            $value = $this->number($row['VALOARE'] ?? 0);
            $protocolOperators[$operatorName]['products'][$key]['quantity'] += $quantity;
            $protocolOperators[$operatorName]['products'][$key]['value'] += $value;
            $protocolOperators[$operatorName]['quantity'] += $quantity;
            $protocolOperators[$operatorName]['value'] += $value;
        }

        foreach ($protocolOperators as &$operator) {
            $operator['products'] = array_values($operator['products']);
            usort($operator['products'], static fn (array $left, array $right): int => $right['value'] <=> $left['value']);
            if ((float) $operator['total'] <= 0.009) {
                $operator['total'] = (float) $operator['value'];
            }
        }
        unset($operator);
        uasort($protocolOperators, static fn (array $left, array $right): int => $right['total'] <=> $left['total']);
        return array_values($protocolOperators);
    }

    private function summarizeProtocol(array $operators): array
    {
        $products = [];
        $quantity = 0.0;
        $value = 0.0;
        $total = 0.0;
        $notes = 0;
        foreach ($operators as $operator) {
            $notes += (int) ($operator['notes'] ?? 0);
            $total += (float) ($operator['total'] ?? 0);
            $quantity += (float) ($operator['quantity'] ?? 0);
            $value += (float) ($operator['value'] ?? 0);
            foreach ($operator['products'] ?? [] as $product) {
                $key = (string) ($product['id'] ?? '') . '|' . (string) ($product['name'] ?? '');
                $products[$key] ??= ['id' => $product['id'], 'name' => $product['name'], 'quantity' => 0.0, 'value' => 0.0];
                $products[$key]['quantity'] += (float) $product['quantity'];
                $products[$key]['value'] += (float) $product['value'];
            }
        }
        $total = $total > 0.009 ? $total : $value;
        usort($products, static fn (array $left, array $right): int => $right['value'] <=> $left['value']);
        return [
            'notes' => $notes,
            'products' => array_values($products),
            'quantity' => $quantity,
            'value' => $value,
            'total' => $total,
        ];
    }

    private function reader(string $key): DbfReader
    {
        $path = trim((string) ($this->config[$key] ?? ''));
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Path invalid pentru ' . $key . ': ' . $path);
        }
        return new DbfReader($path, (string) ($this->config['dbf_encoding'] ?? 'CP1250'));
    }

    private function normalizeDate(string $date): string
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', trim($date));
        $errors = DateTimeImmutable::getLastErrors();
        if (!$parsed || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new InvalidArgumentException('Data raportului nu este valida.');
        }
        return $parsed->format('Ymd');
    }

    private function isTrue(mixed $value): bool
    {
        return in_array(strtoupper(trim((string) $value)), ['T', 'Y', '1', 'TRUE'], true);
    }

    private function isProtocolValue(mixed $value): bool
    {
        $text = strtoupper(trim((string) $value));
        if ($this->isTrue($text)) {
            return true;
        }
        $numeric = str_replace(',', '.', $text);
        return is_numeric($numeric) && (float) $numeric > 0.009;
    }

    private function number(mixed $value): float
    {
        $text = str_replace(',', '.', trim((string) $value));
        return is_numeric($text) ? (float) $text : 0.0;
    }
}
