<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/fpdf/fpdf.php';

final class TransactionPdf
{
    private const MARGIN = 10.0;
    private const PAGE_WIDTH = 297.0;
    private const CONTENT_WIDTH = 277.0;

    public static function stream(array $report, string $filename, array $filters = []): void
    {
        $pdf = new FPDF('L', 'mm', 'A4');
        $pdf->SetTitle(self::pdfText('Raport tranzactii'));
        $pdf->SetAuthor('');
        $pdf->SetCreator('');
        $pdf->SetMargins(self::MARGIN, self::MARGIN, self::MARGIN);
        $pdf->SetAutoPageBreak(true, self::MARGIN);
        $pdf->AddPage();

        self::title($pdf, $report, $filters);
        self::summary($pdf, $report['totals'] ?? [], count($report['transactions'] ?? []));
        $pdf->Ln(5);
        self::transactionsTable($pdf, $report['transactions'] ?? []);

        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 13);
        $pdf->Cell(0, 8, self::pdfText('Produse vandute pe note'), 0, 1, 'L');
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(0, 6, self::pdfText('Produsele provin din compnote. Inregistrarile sterse sunt ignorate.'), 0, 1, 'L');
        $pdf->Ln(3);
        self::productsTable($pdf, $report['transactions'] ?? []);

        $pdf->Output('I', $filename);
    }

    private static function title(FPDF $pdf, array $report, array $filters): void
    {
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 8, self::pdfText('Raport tranzactii si note'), 0, 1, 'L');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 6, self::pdfText('Data: ' . self::dateLabel((string) ($report['date'] ?? ''))), 0, 1, 'L');

        $filterText = [];
        if ((string) ($filters['operator'] ?? '') !== '') {
            $filterText[] = 'Operator: ' . (string) $filters['operator'];
        }
        if ((string) ($filters['payment'] ?? '') !== '') {
            $filterText[] = 'Plata: ' . self::paymentLabel((string) $filters['payment']);
        }
        if ((string) ($filters['search'] ?? '') !== '') {
            $filterText[] = 'Cautare: ' . (string) $filters['search'];
        }
        $pdf->Cell(0, 6, self::pdfText($filterText === [] ? 'Filtre: toate' : implode(', ', $filterText)), 0, 1, 'L');
        $pdf->Ln(3);
    }

    private static function summary(FPDF $pdf, array $totals, int $noteCount): void
    {
        $headers = ['Note', 'Total', 'Numerar', 'Card', 'Protocol', 'Alte plati'];
        $values = [
            (string) $noteCount,
            self::money($totals['total'] ?? 0) . ' RON',
            self::money($totals['cash'] ?? 0) . ' RON',
            self::money($totals['card'] ?? 0) . ' RON',
            self::money($totals['protocol'] ?? 0) . ' RON',
            self::money($totals['other'] ?? 0) . ' RON',
        ];
        $widths = [28, 49, 49, 49, 49, 53];
        $pdf->SetFillColor(231, 242, 237);
        $pdf->SetFont('Arial', 'B', 9);
        foreach ($headers as $index => $header) {
            $pdf->Cell($widths[$index], 7, self::pdfText($header), 1, $index === count($headers) - 1 ? 1 : 0, 'L', true);
        }
        $pdf->SetFont('Arial', '', 10);
        foreach ($values as $index => $value) {
            $pdf->Cell($widths[$index], 8, self::pdfText($value), 1, $index === count($values) - 1 ? 1 : 0, $index === 0 ? 'L' : 'R');
        }
    }

    private static function transactionsTable(FPDF $pdf, array $transactions): void
    {
        $widths = [18, 30, 43, 16, 22, 22, 22, 22, 22, 20];
        $headers = ['Nota', 'Data si ora', 'Operator', 'Masa', 'Total', 'Numerar', 'Card', 'Protocol', 'Alte plati', 'Produse'];
        self::tableHeader($pdf, $headers, $widths);
        $pdf->SetFont('Arial', '', 8);

        foreach ($transactions as $transaction) {
            self::ensureSpace($pdf, 7, $headers, $widths);
            $values = [
                '#' . (string) $transaction['note_number'],
                (string) $transaction['date'] . ' ' . (string) $transaction['time'],
                (string) $transaction['operator'],
                (string) ($transaction['table'] !== '' ? $transaction['table'] : '-'),
                self::money($transaction['total']),
                self::money($transaction['cash']),
                self::money($transaction['card']),
                self::money($transaction['protocol']),
                self::money($transaction['other']),
                (string) count($transaction['products']),
            ];
            foreach ($values as $index => $value) {
                $value = self::fitText(self::pdfText($value), $widths[$index] - 2, $pdf);
                $align = $index >= 4 ? 'R' : 'L';
                $pdf->Cell($widths[$index], 7, $value, 1, $index === count($values) - 1 ? 1 : 0, $align);
            }
        }
    }

    private static function productsTable(FPDF $pdf, array $transactions): void
    {
        $widths = [18, 43, 115, 22, 25, 28, 26];
        $headers = ['Nota', 'Operator', 'Produs', 'Cantitate', 'Pret', 'Valoare', 'Observatie'];
        self::tableHeader($pdf, $headers, $widths);
        $pdf->SetFont('Arial', '', 8);

        foreach ($transactions as $transaction) {
            $products = $transaction['products'] ?? [];
            if ($products === []) {
                self::productRow($pdf, $widths, [
                    '#' . (string) $transaction['note_number'],
                    (string) $transaction['operator'],
                    'Nota fara produse in compnote.',
                    '',
                    '',
                    '',
                    '',
                ]);
                continue;
            }
            foreach ($products as $product) {
                self::productRow($pdf, $widths, [
                    '#' . (string) $transaction['note_number'],
                    (string) $transaction['operator'],
                    (string) $product['name'],
                    self::quantity($product['quantity']),
                    self::money($product['price']),
                    self::money($product['value']),
                    (string) $product['observation'],
                ]);
            }
        }
    }

    private static function productRow(FPDF $pdf, array $widths, array $values): void
    {
        self::ensureSpace($pdf, 7, ['Nota', 'Operator', 'Produs', 'Cantitate', 'Pret', 'Valoare', 'Observatie'], $widths);
        foreach ($values as $index => $value) {
            $value = self::fitText(self::pdfText($value), $widths[$index] - 2, $pdf);
            $pdf->Cell($widths[$index], 7, $value, 1, $index === count($values) - 1 ? 1 : 0, $index >= 3 && $index <= 5 ? 'R' : 'L');
        }
    }

    private static function tableHeader(FPDF $pdf, array $headers, array $widths): void
    {
        $pdf->SetFillColor(23, 91, 70);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Arial', 'B', 8);
        foreach ($headers as $index => $header) {
            $pdf->Cell($widths[$index], 8, self::pdfText($header), 1, $index === count($headers) - 1 ? 1 : 0, 'L', true);
        }
        $pdf->SetTextColor(0, 0, 0);
    }

    private static function ensureSpace(FPDF $pdf, float $height, array $headers, array $widths): void
    {
        if ($pdf->GetY() + $height <= 190) {
            return;
        }
        $pdf->AddPage();
        self::tableHeader($pdf, $headers, $widths);
        $pdf->SetFont('Arial', '', 8);
    }

    private static function fitText(string $text, float $width, FPDF $pdf): string
    {
        if ($pdf->GetStringWidth($text) <= $width) {
            return $text;
        }
        while ($text !== '' && $pdf->GetStringWidth($text . '...') > $width) {
            $text = substr($text, 0, -1);
        }
        return rtrim($text) . '...';
    }

    private static function paymentLabel(string $payment): string
    {
        return match ($payment) {
            'cash' => 'numerar',
            'card' => 'card',
            'protocol' => 'protocol',
            'other' => 'alte plati',
            default => $payment,
        };
    }

    private static function dateLabel(string $date): string
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed ? $parsed->format('d.m.Y') : ($date !== '' ? $date : 'toate datele');
    }

    private static function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private static function quantity(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private static function pdfText(string $value): string
    {
        $converted = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);
        return $converted === false ? $value : $converted;
    }
}
