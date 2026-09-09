<?php
declare(strict_types=1);

require_once __DIR__ . '/src/Config.php';
require_once __DIR__ . '/src/DbfReader.php';
require_once __DIR__ . '/src/DbfCache.php';
require_once __DIR__ . '/src/RelistareRepository.php';
require_once __DIR__ . '/src/ReportRepository.php';
require_once __DIR__ . '/src/ReceiptPdf.php';
require_once __DIR__ . '/src/ReportPdf.php';

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_money(mixed $value): string
{
    return number_format((float) $value, 2, '.', '');
}

function app_ro_date(?string $dbfDate): string
{
    $dbfDate = trim((string) $dbfDate);
    if (preg_match('/^\d{8}$/', $dbfDate) !== 1) {
        return $dbfDate;
    }

    return substr($dbfDate, 6, 2) . '.' . substr($dbfDate, 4, 2) . '.' . substr($dbfDate, 0, 4);
}

function app_base_url(string $path, array $params = []): string
{
    $query = $params ? '?' . http_build_query($params) : '';
    return $path . $query;
}

function app_page_window(int $page, int $pages): array
{
    if ($pages <= 7) {
        return range(1, $pages);
    }

    $window = [1];
    $start = max(2, $page - 2);
    $end = min($pages - 1, $page + 2);

    if ($start > 2) {
        $window[] = 'gap-left';
    }

    for ($i = $start; $i <= $end; $i++) {
        $window[] = $i;
    }

    if ($end < $pages - 1) {
        $window[] = 'gap-right';
    }

    $window[] = $pages;
    return $window;
}
