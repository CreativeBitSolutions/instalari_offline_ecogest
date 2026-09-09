<?php
declare(strict_types=1);

final class TransactionRepository
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function build(string $date, string $operator = '', string $payment = '', string $search = ''): array
    {
        $ymd = $this->normalizeOptionalDate($date);
        $operator = trim($operator);
        $payment = trim($payment);
        $search = trim($search);
        $transactions = [];
        $noteNumbers = [];
        $operators = [];

        foreach ($this->reader('note_path')->rows(false) as $row) {
            if ($ymd !== '' && (string) ($row['DATA'] ?? '') !== $ymd) {
                continue;
            }

            $name = trim((string) ($row['OSPATAR'] ?? '')) ?: 'NECUNOSCUT';
            $noteNumber = (string) ($row['NR_NOTA'] ?? '');
            if ($noteNumber === '') {
                continue;
            }

            $operators[$name] = $name;
            $transaction = [
                'id' => $noteNumber . '-' . (string) ($row['__record'] ?? count($transactions)),
                'note_number' => $noteNumber,
                'date_dbf' => (string) ($row['DATA'] ?? ''),
                'date' => $this->dateLabel((string) ($row['DATA'] ?? '')),
                'time' => trim((string) ($row['ORA'] ?? '')),
                'operator' => $name,
                'operator_code' => trim((string) ($row['COD_OSP'] ?? '')),
                'table' => trim((string) ($row['MASA'] ?? '')),
                'management' => trim((string) ($row['GESTIUNEA'] ?? '')),
                'total' => $this->number($row['TOTAL'] ?? 0),
                'cash' => $this->number($row['NUMERAR'] ?? 0),
                'card' => $this->number($row['CARD'] ?? 0),
                'protocol' => $this->numberOrFlag($row['PROTOCOL'] ?? 0),
                'other' => 0.0,
                'incasat' => trim((string) ($row['INCASAT'] ?? '')),
                'status' => trim((string) ($row['SIT'] ?? '')),
                'products' => [],
            ];
            foreach (['BON_MASA', 'ONLINE', 'BON_VAL', 'VIRAMENT', 'ANGAJATI'] as $field) {
                $transaction['other'] += $this->number($row[$field] ?? 0);
            }

            if ($operator !== '' && strcasecmp($operator, $name) !== 0) {
                continue;
            }
            if ($payment !== '' && !$this->hasPayment($transaction, $payment)) {
                continue;
            }

            $transactions[$noteNumber] = $transaction;
            $noteNumbers[$noteNumber] = true;
        }

        foreach ($this->reader('compnote_path')->rows(false) as $row) {
            $noteNumber = (string) ($row['NR_NOTA'] ?? '');
            if (!isset($noteNumbers[$noteNumber]) || !isset($transactions[$noteNumber])) {
                continue;
            }

            $transactions[$noteNumber]['products'][] = [
                'id' => trim((string) ($row['PRODUS_ID'] ?? '')),
                'name' => trim((string) ($row['PRODUS'] ?? '')) ?: 'PRODUS FARA DENUMIRE',
                'cashier_name' => trim((string) ($row['NUME_CASA'] ?? '')),
                'quantity' => $this->number($row['CANT'] ?? 0),
                'price' => $this->number($row['PRET'] ?? 0),
                'value' => $this->number($row['VALOARE'] ?? 0),
                'vat' => $this->number($row['TVA'] ?? 0),
                'observation' => trim((string) ($row['OBS'] ?? '')),
                'type' => trim((string) ($row['TIP'] ?? '')),
            ];
        }

        if ($search !== '') {
            $transactions = array_filter($transactions, function (array $transaction) use ($search): bool {
                $haystack = $transaction['note_number'] . ' ' . $transaction['operator'] . ' ' . $transaction['table'];
                foreach ($transaction['products'] as $product) {
                    $haystack .= ' ' . $product['name'];
                }
                return stripos($haystack, $search) !== false;
            });
        }

        $transactions = array_values($transactions);
        usort($transactions, static function (array $left, array $right): int {
            $leftKey = $left['date_dbf'] . ' ' . $left['time'] . ' ' . str_pad($left['note_number'], 12, '0', STR_PAD_LEFT);
            $rightKey = $right['date_dbf'] . ' ' . $right['time'] . ' ' . str_pad($right['note_number'], 12, '0', STR_PAD_LEFT);
            return strcmp($rightKey, $leftKey);
        });
        natcasesort($operators);

        $totals = ['total' => 0.0, 'cash' => 0.0, 'card' => 0.0, 'protocol' => 0.0, 'other' => 0.0];
        foreach ($transactions as $transaction) {
            foreach (array_keys($totals) as $field) {
                $totals[$field] += (float) $transaction[$field];
            }
        }

        return [
            'date' => $date,
            'date_dbf' => $ymd,
            'operators' => array_values($operators),
            'transactions' => $transactions,
            'totals' => $totals,
        ];
    }

    private function hasPayment(array $transaction, string $payment): bool
    {
        $field = match ($payment) {
            'cash' => 'cash',
            'card' => 'card',
            'protocol' => 'protocol',
            'other' => 'other',
            default => '',
        };
        return $field !== '' && abs((float) ($transaction[$field] ?? 0)) > 0.004;
    }

    private function reader(string $key): DbfReader
    {
        $path = trim((string) ($this->config[$key] ?? ''));
        if ($path === '') {
            throw new RuntimeException('Path invalid pentru ' . $key . ': ' . $path);
        }
        return new DbfReader($path, (string) ($this->config['dbf_encoding'] ?? 'CP1250'));
    }

    private function normalizeOptionalDate(string $date): string
    {
        $date = trim($date);
        if ($date === '') {
            return '';
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$parsed || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new InvalidArgumentException('Data tranzactiei nu este valida.');
        }
        return $parsed->format('Ymd');
    }

    private function dateLabel(string $date): string
    {
        return preg_match('/^\d{8}$/', $date) === 1
            ? substr($date, 6, 2) . '.' . substr($date, 4, 2) . '.' . substr($date, 0, 4)
            : $date;
    }

    private function numberOrFlag(mixed $value): float
    {
        $text = strtoupper(trim((string) $value));
        if (in_array($text, ['T', 'Y', 'TRUE'], true)) {
            return 1.0;
        }
        $numeric = str_replace(',', '.', $text);
        return is_numeric($numeric) ? (float) $numeric : 0.0;
    }

    private function number(mixed $value): float
    {
        $text = str_replace(',', '.', trim((string) $value));
        return is_numeric($text) ? (float) $text : 0.0;
    }
}
