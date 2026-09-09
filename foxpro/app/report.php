<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$config = Config::load();
$error = null;
$date = trim((string) ($_GET['date'] ?? '2026-09-08'));
$operatorFilter = trim((string) ($_GET['operator'] ?? ''));
$report = null;
$allOperators = [];

try {
    $repository = new ReportRepository($config);
    $allReport = $repository->build($date);
    $allOperators = $allReport['operators'];
    $report = $operatorFilter !== '' ? $repository->build($date, $operatorFilter) : $allReport;
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}

$pathStatus = Config::pathStatus($config);
$dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
$dateLabel = $dateObject ? $dateObject->format('d.m.Y') : $date;
$operators = $report['operators'] ?? [];
$totals = $report['totals'] ?? [
    'notes' => 0,
    'total' => 0,
    'card' => 0,
    'cash' => 0,
    'other' => 0,
    'bar' => 0,
    'buc' => 0,
    'unknown' => 0,
    'product_lines' => 0,
    'quantity' => 0,
];
$closing = $report['closing'] ?? ['rows' => 0, 'operator' => '', 'total' => 0, 'card' => 0, 'cash' => 0, 'bar' => 0, 'buc' => 0];
$difference = (float) $totals['total'] - (float) $closing['total'];
?>
<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Raport vanzari</title>
    <link rel="stylesheet" href="assets/styles.css">
    <link rel="stylesheet" href="assets/report-print.css" media="print">
</head>
<body>
<main class="app-shell">
    <header class="topbar report-topbar">
        <div>
            <p class="eyebrow">Raport local</p>
            <h1>Vanzari pe operator</h1>
        </div>
        <div class="toolbar report-toolbar">
            <a class="ghost-button" href="index.php">Lista documente</a>
            <?php if ($report): ?>
                <button class="primary-button" type="button" onclick="window.print()">Printeaza</button>
                <a class="ghost-button" href="<?= h(app_base_url('report_pdf.php', ['date' => $date, 'operator' => $operatorFilter])) ?>">Export PDF</a>
            <?php endif; ?>
        </div>
    </header>

    <?php $activeNav = 'reports'; require __DIR__ . '/partials/navigation.php'; ?>

    <?php if ($error): ?>
        <div class="notice is-error"><?= h($error) ?></div>
    <?php endif; ?>

    <section class="report-controls">
        <form method="get" class="report-filter-form">
            <label>
                <span>Data</span>
                <input type="date" name="date" value="<?= h($date) ?>">
            </label>
            <label>
                <span>Operator</span>
                <select name="operator">
                    <option value="">Toti operatorii</option>
                    <?php foreach ($allOperators as $row): ?>
                        <option value="<?= h($row['name']) ?>" <?= strcasecmp($operatorFilter, (string) $row['name']) === 0 ? 'selected' : '' ?>><?= h($row['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="primary-button" type="submit">Afiseaza raport</button>
        </form>
        <div class="report-path-note">
            <a href="index.php">Configurare path-uri DBF</a>
            <span>Citirea exclude inregistrarile marcate ca sterse.</span>
        </div>
    </section>

    <?php if ($report): ?>
        <section class="report-summary-grid">
            <article class="summary-card"><span>Total vanzari</span><strong><?= h(app_money($totals['total'])) ?> RON</strong></article>
            <article class="summary-card"><span>Numerar</span><strong><?= h(app_money($totals['cash'])) ?> RON</strong></article>
            <article class="summary-card"><span>Card</span><strong><?= h(app_money($totals['card'])) ?> RON</strong></article>
            <article class="summary-card"><span>Note</span><strong><?= h((int) $totals['notes']) ?></strong></article>
        </section>

        <section class="report-panel">
            <div class="section-title">
                <span><?= h($dateLabel) ?></span>
                <strong>Centralizator operatori</strong>
            </div>
            <div class="table-wrap">
                <table class="report-table">
                    <thead>
                    <tr>
                        <th>Operator</th>
                        <th class="num">Note</th>
                        <th class="num">Total</th>
                        <th class="num">Numerar</th>
                        <th class="num">Card</th>
                        <th class="num">Alte plati</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($operators as $row): ?>
                        <tr>
                            <td class="strong"><?= h($row['name']) ?></td>
                            <td class="num"><?= h($row['notes']) ?></td>
                            <td class="num strong"><?= h(app_money($row['total'])) ?></td>
                            <td class="num"><?= h(app_money($row['cash'])) ?></td>
                            <td class="num"><?= h(app_money($row['card'])) ?></td>
                            <td class="num"><?= h(app_money($row['other'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <?php if ($closing['rows'] > 0): ?>
            <section class="report-check <?= abs($difference) < 0.01 ? 'is-ok' : 'is-warning' ?>">
                <strong>Control totaluri</strong>
                <span>Total note <?= h(app_money($totals['total'])) ?> RON, total inchidere <?= h(app_money($closing['total'])) ?> RON, diferenta <?= h(app_money($difference)) ?> RON.</span>
                <?php if ($closing['operator'] !== ''): ?><span>Operator inchidere: <?= h($closing['operator']) ?>.</span><?php endif; ?>
            </section>
        <?php endif; ?>

        <section class="report-products-grid">
            <?php foreach ($operators as $row): ?>
                <article class="report-panel operator-products">
                    <div class="section-title">
                        <span><?= h($row['product_lines']) ?> linii</span>
                        <strong><?= h($row['name']) ?></strong>
                    </div>
                    <div class="table-wrap">
                        <table class="report-table">
                            <thead>
                            <tr>
                                <th>Produs</th>
                                <th class="num">Cant.</th>
                                <th class="num">Valoare</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($row['products'] as $product): ?>
                                <tr>
                                    <td><?= h($product['name']) ?></td>
                                    <td class="num"><?= h(number_format((float) $product['quantity'], 2, '.', '')) ?></td>
                                    <td class="num strong"><?= h(app_money($product['value'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <section class="report-path-status">
        <div class="section-title">
            <span>Surse</span>
            <strong>Status path-uri</strong>
        </div>
        <div class="path-status-grid">
            <?php foreach ($pathStatus as $status): ?>
                <div class="path-status-card <?= $status['available'] ? 'is-ok' : 'is-bad' ?>">
                    <strong><?= h($status['label']) ?></strong>
                    <span><?= h($status['message']) ?></span>
                    <small><?= h($status['path']) ?></small>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="cache-prompt" data-cache-prompt hidden>
        <div class="cache-prompt-backdrop" data-cache-prompt-backdrop></div>
        <section class="cache-prompt-dialog" role="dialog" aria-modal="true" aria-labelledby="cache-prompt-title">
            <button class="cache-prompt-close" type="button" data-cache-prompt-close aria-label="Inchide">X</button>
            <p class="eyebrow">Actualizare necesara</p>
            <h2 id="cache-prompt-title">Cache-ul trebuie reincarcat</h2>
            <p data-cache-prompt-message>Cache-ul nu este incarcat sau nu mai este actual. Apasa butonul pentru reincarcare.</p>
            <div class="cache-prompt-actions">
                <button class="primary-button" type="button" data-cache-prompt-rebuild>Reincarca cache</button>
                <button class="ghost-button" type="button" data-cache-prompt-close>Mai tarziu</button>
            </div>
        </section>
    </div>
</main>
<script src="assets/cache-prompt.js"></script>
</body>
</html>
