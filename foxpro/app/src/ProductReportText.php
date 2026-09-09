<?php
declare(strict_types=1);

final class ProductReportText
{
    public static function build(array $report, string $mode, string $restaurantName): string
    {
        $lines = [];
        $lines[] = self::center(self::ascii($restaurantName));
        $lines[] = self::center('RAPORT PRODUSE VANDUTE');
        $lines[] = self::center('DATA: ' . self::dateLabel((string) ($report['date'] ?? '')));
        $lines[] = str_repeat('=', 42);

        $operators = $report['operators'] ?? [];
        if ($operators === []) {
            $lines[] = 'NU EXISTA VANZARI PENTRU DATA SELECTATA.';
        } elseif ($mode === 'summary') {
            $lines[] = 'SUMAR OPERATORI';
            $lines[] = str_repeat('-', 42);
            foreach ($operators as $operator) {
                self::addOperatorPayments($lines, $operator);
            }
        } else {
            $lines[] = 'PRODUSE AGREGATE PE OPERATOR';
            $lines[] = str_repeat('-', 42);
            foreach ($operators as $operator) {
                self::addOperatorProducts($lines, $operator);
            }
        }

        $totals = $report['totals'] ?? [];
        $lines[] = str_repeat('=', 42);
        $lines[] = 'TOTAL GENERAL';
        $lines[] = self::moneyLine('VANZARI', (float) ($totals['total'] ?? 0));
        $lines[] = self::moneyLine('NUMERAR', (float) ($totals['cash'] ?? 0));
        $lines[] = self::moneyLine('CARD', (float) ($totals['card'] ?? 0));
        $lines[] = self::moneyLine('ALTE PLATI', (float) ($totals['other'] ?? 0));

        return implode("\n", $lines) . "\n";
    }

    public static function buildProtocolSheet(array $report, string $restaurantName): string
    {
        $lines = [];
        $lines[] = self::center(self::ascii($restaurantName));
        $lines[] = self::center('RAPORT PRODUSE PE PROTOCOL');
        $lines[] = self::center('DATA: ' . self::dateLabel((string) ($report['date'] ?? '')));
        $lines[] = str_repeat('=', 42);

        $operators = $report['protocol_operators'] ?? [];
        if ($operators === []) {
            $lines[] = 'NU EXISTA VANZARI PE PROTOCOL.';
        } else {
            foreach ($operators as $operator) {
                $lines[] = 'OPERATOR: ' . self::ascii((string) ($operator['name'] ?? 'NECUNOSCUT'));
                $lines[] = 'NOTE PROTOCOL: ' . (int) ($operator['notes'] ?? 0);
                foreach (($operator['products'] ?? []) as $product) {
                    $lines[] = self::productLine($product);
                }
                $lines[] = self::moneyLine('VALOARE PRODUSE', (float) ($operator['value'] ?? 0));
                $lines[] = self::moneyLine('TOTAL PROTOCOL', (float) ($operator['total'] ?? 0));
                $lines[] = str_repeat('-', 42);
            }
            $protocol = $report['protocol'] ?? [];
            $lines[] = self::moneyLine('TOTAL PRODUSE PROTOCOL', (float) ($protocol['value'] ?? 0));
            $lines[] = self::moneyLine('TOTAL PROTOCOL', (float) ($protocol['total'] ?? 0));
        }

        return implode("\n", $lines) . "\n";
    }

    private static function addOperatorPayments(array &$lines, array $operator): void
    {
        $lines[] = self::ascii((string) ($operator['name'] ?? 'NECUNOSCUT'));
        $lines[] = self::moneyLine('TOTAL', (float) ($operator['total'] ?? 0));
        $lines[] = self::moneyLine('NUMERAR', (float) ($operator['cash'] ?? 0));
        $lines[] = self::moneyLine('CARD', (float) ($operator['card'] ?? 0));
        $lines[] = self::moneyLine('ALTE PLATI', (float) ($operator['other'] ?? 0));
        $lines[] = str_repeat('-', 42);
    }

    private static function addOperatorProducts(array &$lines, array $operator): void
    {
        $lines[] = self::ascii((string) ($operator['name'] ?? 'NECUNOSCUT'));
        $lines[] = self::moneyLine('TOTAL', (float) ($operator['total'] ?? 0));
        $lines[] = self::moneyLine('NUMERAR', (float) ($operator['cash'] ?? 0));
        $lines[] = self::moneyLine('CARD', (float) ($operator['card'] ?? 0));
        $lines[] = self::moneyLine('ALTE PLATI', (float) ($operator['other'] ?? 0));
        $lines[] = 'PRODUSE';
        foreach (($operator['products'] ?? []) as $product) {
            $lines[] = self::productLine($product);
        }
        $lines[] = str_repeat('-', 42);
    }

    private static function productLine(array $product): string
    {
        $name = self::ascii((string) ($product['name'] ?? 'PRODUS FARA DENUMIRE'));
        $quantity = number_format((float) ($product['quantity'] ?? 0), 2, '.', '');
        $value = number_format((float) ($product['value'] ?? 0), 2, '.', '');
        $prefix = $quantity . ' X ';
        $suffix = ' ' . $value . ' RON';
        $available = max(12, 42 - strlen($prefix) - strlen($suffix));
        $chunks = explode("\n", wordwrap($name, $available, "\n", true));
        $last = count($chunks) - 1;
        $lines = [];
        foreach ($chunks as $index => $chunk) {
            $lines[] = ($index === 0 ? $prefix : '    ') . $chunk . ($index === $last ? $suffix : '');
        }
        return implode("\n", $lines);
    }

    private static function moneyLine(string $label, float $value): string
    {
        return self::ascii($label) . ': ' . number_format($value, 2, '.', '') . ' RON';
    }

    private static function dateLabel(string $date): string
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed ? $parsed->format('d.m.Y') : $date;
    }

    private static function center(string $value): string
    {
        $length = strlen($value);
        if ($length >= 42) {
            return $value;
        }
        return str_repeat(' ', intdiv(42 - $length, 2)) . $value;
    }

    private static function ascii(string $value): string
    {
        return strtr($value, [
            'ă' => 'a', 'Ă' => 'A',
            'â' => 'a', 'Â' => 'A',
            'î' => 'i', 'Î' => 'I',
            'ș' => 's', 'Ș' => 'S',
            'ş' => 's', 'Ş' => 'S',
            'ț' => 't', 'Ț' => 'T',
            'ţ' => 't', 'Ţ' => 'T',
        ]);
    }
}
