<?php
declare(strict_types=1);

final class ReceiptPdf
{
    private const PAGE_WIDTH = 595.28;
    private const PAGE_HEIGHT = 841.89;

    /** @var array<int,string> */
    private array $pages = [];
    private string $content = '';

    public static function stream(array $document, string $filename): void
    {
        $pdf = new self();
        $pdf->drawDocument($document);
        $bytes = $pdf->build();

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . addslashes($filename) . '"');
        header('Content-Length: ' . strlen($bytes));
        echo $bytes;
    }

    private function drawDocument(array $doc): void
    {
        $this->newPage();

        if (($doc['type'] ?? '') === 'bon') {
            $this->drawBon($doc);
            return;
        }

        $this->drawNota($doc);
    }

    private function drawNota(array $doc): void
    {
        $y = 26;
        $this->text(132, $y, (string) $doc['restaurant'], 18, 'B');
        $y += 34;
        $this->text(34, $y, 'Data ' . app_ro_date((string) $doc['date']) . '    Ora ' . (string) $doc['time'], 10, 'B');
        $y += 26;
        $this->text(62, $y, 'Nota de plata nr.' . (string) $doc['number'], 16, 'B');
        $y += 22;
        $this->text(154, $y, 'Masa: ' . (string) $doc['table'], 13, 'B');
        $y += 8;
        $this->line(12, $y, 292, $y, 1);
        $y += 14;
        $this->text(26, $y, 'Denumire produs', 10, 'B');
        $y += 16;
        $this->text(84, $y, 'Cantitate', 10, 'B');
        $this->text(160, $y, 'Pret', 10, 'B');
        $this->text(228, $y, 'Valoare', 10, 'B');
        $y += 9;
        $this->line(12, $y, 292, $y, 1);
        $y += 16;

        foreach ($doc['items'] as $item) {
            $name = $this->clean((string) $item['name']);
            foreach ($this->wrap($name, 30) as $line) {
                $this->text(14, $y, $line, 12, 'B');
                $y += 14;
            }
            $this->text(98, $y, app_money($item['quantity']) . 'x', 12, 'B');
            $this->text(156, $y, app_money($item['price']) . '=', 12, 'B');
            $this->text(236, $y, app_money($item['value']), 12, 'B');
            $y += 16;
            $this->pageBreakIfNeeded($y);
        }

        $this->line(12, $y - 4, 292, $y - 4, 1);
        $y += 14;
        $this->text(34, $y, 'TOTAL DE PLATA:', 16, 'B');
        $this->text(188, $y, app_money($doc['total']) . ' RON', 16, 'B');
        $y += 22;

        foreach ($doc['payments'] as $payment) {
            $this->text(34, $y, 'Plata ' . $payment['label'] . ':', 12, 'C');
            $this->text(178, $y, app_money($payment['amount']) . ' RON', 12, 'C');
            $y += 18;
        }

        $y += 12;
        $this->text(104, $y, 'Ospatar:' . (string) $doc['waiter'], 12, 'C');
        $y += 28;
        $this->text(24, $y, 'Va multumim si va mai asteptam', 12, 'B');
        $y += 18;
        $this->text(168, $y, '!', 12, 'B');
        $y += 28;
        $this->text(70, $y, '(c) ECOSOFT S.R.L. Sibiu', 9, 'C');
        $y += 16;
        $this->text(100, $y, 'Tel. 0744.299843', 9, 'C');
    }

    private function drawBon(array $doc): void
    {
        $y = 16;
        $this->text(14, $y, (string) $doc['restaurant'], 12, 'C');
        $this->text(216, $y, 'Data:   ' . app_ro_date((string) $doc['date']), 10, 'C');
        $y += 22;
        $this->text(216, $y, 'Ora:    ' . (string) $doc['time'], 10, 'C');
        $y += 20;
        $this->text(14, $y, 'Nr.Bon:  ' . (string) $doc['number'], 10, 'C');
        $this->text(216, $y, 'Nr.Crt: ' . (string) ($doc['contor'] ?: $doc['contor_bon']), 10, 'C');
        $y += 8;
        $this->line(4, $y, 292, $y, 1);
        $y += 18;

        foreach ($doc['items'] as $item) {
            $qty = rtrim(rtrim(app_money($item['quantity']), '0'), '.');
            $left = $qty . ' ' . $this->clean((string) $item['name']);
            $lines = $this->wrap($left, 27);
            foreach ($lines as $index => $line) {
                $this->text(4, $y, $line, 13, 'B');
                if ($index === 0) {
                    $this->text(246, $y, app_money($item['value']), 13, 'B');
                }
                $y += 16;
                $this->pageBreakIfNeeded($y);
            }
        }

        $this->line(4, $y - 4, 292, $y - 4, 1);
        $y += 14;
        $this->text(94, $y, 'Total ' . app_money($doc['total']), 13, 'B');
        $y += 24;
        $this->text(78, $y, 'Ospatar:' . (string) $doc['waiter'], 12, 'C');
    }

    private function newPage(): void
    {
        if ($this->content !== '') {
            $this->pages[] = $this->content;
        }
        $this->content = '';
    }

    private function pageBreakIfNeeded(float &$y): void
    {
        if ($y < 780) {
            return;
        }

        $this->newPage();
        $y = 24;
    }

    private function text(float $x, float $y, string $text, int $size = 10, string $font = 'C'): void
    {
        $pdfY = self::PAGE_HEIGHT - $y;
        $fontName = match ($font) {
            'B' => 'F2',
            'C' => 'F3',
            default => 'F1',
        };

        $this->content .= sprintf(
            "BT /%s %d Tf %.2F %.2F Td (%s) Tj ET\n",
            $fontName,
            $size,
            $x,
            $pdfY,
            $this->escape($this->clean($text))
        );
    }

    private function line(float $x1, float $y1, float $x2, float $y2, float $width = 0.5): void
    {
        $this->content .= sprintf(
            "%.2F w %.2F %.2F m %.2F %.2F l S\n",
            $width,
            $x1,
            self::PAGE_HEIGHT - $y1,
            $x2,
            self::PAGE_HEIGHT - $y2
        );
    }

    private function build(): string
    {
        if ($this->content !== '') {
            $this->pages[] = $this->content;
            $this->content = '';
        }

        $objects = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';

        $pageObjectNumbers = [];
        $contentObjectNumbers = [];
        $nextObject = 7;
        foreach ($this->pages as $page) {
            $pageObjectNumbers[] = $nextObject++;
            $contentObjectNumbers[] = $nextObject++;
        }

        $kids = implode(' ', array_map(static fn (int $number): string => $number . ' 0 R', $pageObjectNumbers));
        $objects[] = "<< /Type /Pages /Kids [$kids] /Count " . count($pageObjectNumbers) . ' >>';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Courier-Bold >>';

        foreach ($this->pages as $index => $content) {
            $pageNo = $pageObjectNumbers[$index];
            $contentNo = $contentObjectNumbers[$index];
            $objects[$pageNo - 1] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 " . self::PAGE_WIDTH . ' ' . self::PAGE_HEIGHT . "] /Resources << /Font << /F1 3 0 R /F2 4 0 R /F3 5 0 R /F4 6 0 R >> >> /Contents $contentNo 0 R >>";
            $objects[$contentNo - 1] = "<< /Length " . strlen($content) . " >>\nstream\n$content\nendstream";
        }

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $number => $object) {
            $offsets[$number + 1] = strlen($pdf);
            $pdf .= ($number + 1) . " 0 obj\n$object\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";

        return $pdf;
    }

    private function clean(string $text): string
    {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        return $converted !== false ? $converted : $text;
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function wrap(string $text, int $width): array
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
        if ($text === '') {
            return [''];
        }

        $lines = [];
        while (strlen($text) > $width) {
            $chunk = substr($text, 0, $width + 1);
            $break = strrpos($chunk, ' ');
            if ($break === false || $break < 8) {
                $break = $width;
            }
            $lines[] = trim(substr($text, 0, $break));
            $text = trim(substr($text, $break));
        }

        if ($text !== '') {
            $lines[] = $text;
        }

        return $lines;
    }
}
