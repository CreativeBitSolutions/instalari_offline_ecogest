<?php
declare(strict_types=1);

final class TransactionXlsx
{
    public static function stream(array $report, string $filename, array $filters = []): void
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('Extensia PHP zip este necesara pentru exportul Excel.');
        }

        $temporary = tempnam(sys_get_temp_dir(), 'foxpro-xlsx-');
        if ($temporary === false) {
            throw new RuntimeException('Nu pot crea fisierul temporar pentru exportul Excel.');
        }

        try {
            $zip = new ZipArchive();
            if ($zip->open($temporary, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Nu pot construi fisierul Excel.');
            }
            $zip->addFromString('[Content_Types].xml', self::contentTypes());
            $zip->addFromString('_rels/.rels', self::rootRelationships());
            $zip->addFromString('docProps/core.xml', self::coreProperties());
            $zip->addFromString('docProps/app.xml', self::appProperties());
            $zip->addFromString('xl/workbook.xml', self::workbook());
            $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRelationships());
            $zip->addFromString('xl/styles.xml', self::styles());
            $zip->addFromString('xl/worksheets/sheet1.xml', self::worksheet($report, $filters));
            $zip->close();

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . self::headerFilename($filename) . '"');
            header('Content-Length: ' . (string) filesize($temporary));
            readfile($temporary);
        } finally {
            @unlink($temporary);
        }
    }

    private static function worksheet(array $report, array $filters): string
    {
        $rows = [];
        $rows[] = [self::textCell('Raport tranzactii si note', 1)];
        $rows[] = [self::textCell('Data: ' . self::dateLabel((string) ($report['date'] ?? '')), 0)];
        $rows[] = [self::textCell(self::filterText($filters), 0)];
        $rows[] = [];

        $totals = $report['totals'] ?? [];
        $rows[] = [
            self::textCell('Note', 2),
            self::textCell('Total', 2),
            self::textCell('Numerar', 2),
            self::textCell('Card', 2),
            self::textCell('Protocol', 2),
            self::textCell('Alte plati', 2),
        ];
        $rows[] = [
            self::numberCell(count($report['transactions'] ?? [])),
            self::numberCell($totals['total'] ?? 0, 3),
            self::numberCell($totals['cash'] ?? 0, 3),
            self::numberCell($totals['card'] ?? 0, 3),
            self::numberCell($totals['protocol'] ?? 0, 3),
            self::numberCell($totals['other'] ?? 0, 3),
        ];
        $rows[] = [];
        $rows[] = [self::textCell('Note filtrate', 1)];
        $rows[] = array_map(static fn (string $header): array => self::textCell($header, 2), [
            'Nota', 'Data si ora', 'Operator', 'Masa', 'Total RON', 'Numerar RON', 'Card RON', 'Protocol RON', 'Alte plati RON', 'Nr produse',
        ]);

        foreach (($report['transactions'] ?? []) as $transaction) {
            $rows[] = [
                self::textCell('#' . (string) $transaction['note_number']),
                self::textCell((string) $transaction['date'] . ' ' . (string) $transaction['time']),
                self::textCell((string) $transaction['operator']),
                self::textCell((string) ($transaction['table'] !== '' ? $transaction['table'] : '-')),
                self::numberCell($transaction['total'], 3),
                self::numberCell($transaction['cash'], 3),
                self::numberCell($transaction['card'], 3),
                self::numberCell($transaction['protocol'], 3),
                self::numberCell($transaction['other'], 3),
                self::numberCell(count($transaction['products'] ?? [])),
            ];
        }

        $rows[] = [];
        $rows[] = [self::textCell('Produse vandute pe note', 1)];
        $rows[] = array_map(static fn (string $header): array => self::textCell($header, 2), [
            'Nota', 'Operator', 'Produs', 'Cantitate', 'Pret RON', 'Valoare RON', 'Observatie',
        ]);
        foreach (($report['transactions'] ?? []) as $transaction) {
            $products = $transaction['products'] ?? [];
            if ($products === []) {
                $rows[] = [
                    self::textCell('#' . (string) $transaction['note_number']),
                    self::textCell((string) $transaction['operator']),
                    self::textCell('Nota fara produse in compnote.'),
                ];
                continue;
            }
            foreach ($products as $product) {
                $rows[] = [
                    self::textCell('#' . (string) $transaction['note_number']),
                    self::textCell((string) $transaction['operator']),
                    self::textCell((string) $product['name']),
                    self::numberCell($product['quantity']),
                    self::numberCell($product['price'], 3),
                    self::numberCell($product['value'], 3),
                    self::textCell((string) $product['observation']),
                ];
            }
        }

        $xmlRows = self::rowsXml($rows);
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<cols><col min="1" max="1" width="14" customWidth="1"/><col min="2" max="2" width="18" customWidth="1"/><col min="3" max="3" width="30" customWidth="1"/><col min="4" max="4" width="14" customWidth="1"/><col min="5" max="10" width="14" customWidth="1"/><col min="11" max="11" width="18" customWidth="1"/></cols>'
            . '<sheetData>' . $xmlRows . '</sheetData>'
            . '</worksheet>';
    }

    private static function rowsXml(array $rows): string
    {
        $xml = '';
        foreach ($rows as $rowIndex => $row) {
            $xml .= '<row r="' . ($rowIndex + 1) . '">';
            foreach (array_values($row) as $columnIndex => $cell) {
                $reference = self::columnName($columnIndex + 1) . ($rowIndex + 1);
                $xml .= '<c r="' . $reference . '"' . ($cell['style'] !== 0 ? ' s="' . $cell['style'] . '"' : '') . ' t="' . $cell['type'] . '">';
                if ($cell['type'] === 'inlineStr') {
                    $xml .= '<is><t xml:space="preserve">' . $cell['value'] . '</t></is>';
                } else {
                    $xml .= '<v>' . $cell['value'] . '</v>';
                }
                $xml .= '</c>';
            }
            $xml .= '</row>';
        }
        return $xml;
    }

    private static function textCell(string $value, int $style = 0): array
    {
        return ['type' => 'inlineStr', 'value' => htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8'), 'style' => $style];
    }

    private static function numberCell(mixed $value, int $style = 0): array
    {
        return ['type' => 'n', 'value' => number_format((float) $value, 4, '.', ''), 'style' => $style];
    }

    private static function filterText(array $filters): string
    {
        $parts = [];
        if (($filters['operator'] ?? '') !== '') {
            $parts[] = 'Operator: ' . $filters['operator'];
        }
        if (($filters['payment'] ?? '') !== '') {
            $parts[] = 'Plata: ' . self::paymentLabel((string) $filters['payment']);
        }
        if (($filters['search'] ?? '') !== '') {
            $parts[] = 'Cautare: ' . $filters['search'];
        }
        return $parts === [] ? 'Filtre: toate' : implode(', ', $parts);
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

    private static function columnName(int $column): string
    {
        $name = '';
        while ($column > 0) {
            $remainder = ($column - 1) % 26;
            $name = chr(65 + $remainder) . $name;
            $column = intdiv($column - 1, 26);
        }
        return $name;
    }

    private static function headerFilename(string $filename): string
    {
        return str_replace(["\r", "\n", '"'], '', $filename);
    }

    private static function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '</Types>';
    }

    private static function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    private static function coreProperties(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>Raport tranzactii</dc:title><dc:creator></dc:creator><cp:lastModifiedBy></cp:lastModifiedBy><dcterms:created xsi:type="dcterms:W3CDTF">2026-09-09T00:00:00Z</dcterms:created></cp:coreProperties>';
    }

    private static function appProperties(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>Microsoft Excel</Application></Properties>';
    }

    private static function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Tranzactii" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private static function workbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    }

    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="1"><numFmt numFmtId="164" formatCode="0.00"/></numFmts><fonts count="2"><font><sz val="10"/><color rgb="FF1F2D2A"/><name val="Calibri"/></font><font><b/><sz val="10"/><color rgb="FF175B46"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE7F2ED"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0"/><xf numFmtId="164" fontId="0" fillId="0" borderId="0" applyNumberFormat="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }
}
