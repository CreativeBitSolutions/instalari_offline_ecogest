<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/fpdf/fpdf.php';

final class ReportPdf
{
    public static function stream(array $report, string $filename): void
    {
        $pdf = new FPDF('L', 'mm', 'A4');
        $pdf->SetTitle(self::pdfText('Raport vanzari'));
        $pdf->SetAuthor('');
        $pdf->SetCreator('');
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 10);
        $pdf->AddPage();

        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 8, self::pdfText('Raport vanzari pe operator'), 0, 1, 'L');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 6, self::pdfText('Data: ' . self::dateLabel((string) $report['date'])), 0, 1, 'L');
        if ((string) ($report['operator_filter'] ?? '') !== '') {
            $pdf->Cell(0, 6, self::pdfText('Operator: ' . (string) $report['operator_filter']), 0, 1, 'L');
        }
        $pdf->Ln(3);

        self::summary($pdf, $report['totals']);
        $pdf->Ln(4);
        self::operatorTable($pdf, $report['operators']);

        foreach ($report['operators'] as $operator) {
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 13);
            $pdf->Cell(0, 8, self::pdfText('Produse, ' . $operator['name']), 0, 1, 'L');
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(0, 6, self::pdfText(
                'Total ' . self::money($operator['total'])
                . ' RON, card ' . self::money($operator['card'])
                . ' RON, numerar ' . self::money($operator['cash']) . ' RON'
            ), 0, 1, 'L');
            $pdf->Ln(3);
            self::productTable($pdf, $operator['products']);
        }

        $pdf->Output('I', $filename);
    }

    private static function summary(FPDF $pdf, array $totals): void
    {
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(42, 8, self::pdfText('Total'), 1, 0, 'L');
        $pdf->Cell(42, 8, self::pdfText('Numerar'), 1, 0, 'L');
        $pdf->Cell(42, 8, self::pdfText('Card'), 1, 0, 'L');
        $pdf->Cell(42, 8, self::pdfText('Alte plati'), 1, 1, 'L');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(42, 8, self::money($totals['total']) . ' RON', 1, 0, 'R');
        $pdf->Cell(42, 8, self::money($totals['cash']) . ' RON', 1, 0, 'R');
        $pdf->Cell(42, 8, self::money($totals['card']) . ' RON', 1, 0, 'R');
        $pdf->Cell(42, 8, self::money($totals['other']) . ' RON', 1, 1, 'R');
    }

    private static function operatorTable(FPDF $pdf, array $operators): void
    {
        $widths = [54, 20, 38, 38, 38, 38, 38];
        $headers = ['Operator', 'Note', 'Total', 'Numerar', 'Card', 'Alte plati', 'Linii produse'];
        self::headerRow($pdf, $headers, $widths);
        $pdf->SetFont('Arial', '', 9);
        foreach ($operators as $row) {
            $values = [
                self::pdfText((string) $row['name']),
                (string) $row['notes'],
                self::money($row['total']),
                self::money($row['cash']),
                self::money($row['card']),
                self::money($row['other']),
                (string) $row['product_lines'],
            ];
            foreach ($values as $index => $value) {
                $align = $index < 2 ? 'L' : 'R';
                $pdf->Cell($widths[$index], 7, $value, 1, $index === count($values) - 1 ? 1 : 0, $align);
            }
        }
    }

    private static function productTable(FPDF $pdf, array $products): void
    {
        $widths = [18, 140, 38, 28];
        self::headerRow($pdf, ['Cod', 'Produs', 'Cantitate', 'Valoare'], $widths);
        $pdf->SetFont('Arial', '', 9);
        foreach ($products as $row) {
            $values = [
                (string) $row['id'],
                self::pdfText((string) $row['name']),
                self::quantity($row['quantity']),
                self::money($row['value']),
            ];
            foreach ($values as $index => $value) {
                $align = $index === 1 ? 'L' : 'R';
                $pdf->Cell($widths[$index], 7, $value, 1, $index === count($values) - 1 ? 1 : 0, $align);
            }
        }
    }

    private static function headerRow(FPDF $pdf, array $headers, array $widths): void
    {
        $pdf->SetFont('Arial', 'B', 9);
        foreach ($headers as $index => $header) {
            $pdf->Cell($widths[$index], 8, self::pdfText($header), 1, $index === count($headers) - 1 ? 1 : 0, 'L');
        }
    }

    private static function dateLabel(string $date): string
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed ? $parsed->format('d.m.Y') : $date;
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
