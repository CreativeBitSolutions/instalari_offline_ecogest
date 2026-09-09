<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$config = Config::load();
$date = trim((string) ($_GET['date'] ?? '2026-09-08'));
$operator = trim((string) ($_GET['operator'] ?? ''));
$payment = trim((string) ($_GET['payment'] ?? ''));
$search = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$pageSize = 30;
$error = null;
$report = null;
$allReport = null;

try {
    $repository = new TransactionRepository($config);
    $allReport = $repository->build($date);
    $report = $repository->build($date, $operator, $payment, $search);
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}

$transactions = $report['transactions'] ?? [];
$totalTransactions = count($transactions);
$pages = max(1, (int) ceil($totalTransactions / $pageSize));
$page = min($page, $pages);
$visibleTransactions = array_slice($transactions, ($page - 1) * $pageSize, $pageSize);
$operators = $allReport['operators'] ?? [];
$totals = $report['totals'] ?? ['total' => 0, 'cash' => 0, 'card' => 0, 'protocol' => 0, 'other' => 0];
$dateLabel = $date;
if ($date !== '') {
    $dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    $dateLabel = $dateObject ? $dateObject->format('d.m.Y') : $date;
}

$queryParams = static function (int $nextPage = 1) use ($date, $operator, $payment, $search): string {
    return http_build_query(array_filter([
        'date' => $date,
        'operator' => $operator,
        'payment' => $payment,
        'q' => $search,
        'page' => $nextPage > 1 ? $nextPage : null,
    ], static fn (mixed $value): bool => $value !== null && $value !== ''));
};

$exportParams = array_filter([
    'date' => $date,
    'operator' => $operator,
    'payment' => $payment,
    'q' => $search,
], static fn (mixed $value): bool => $value !== null && $value !== '');

$details = [];
foreach ($visibleTransactions as $transaction) {
    $details[(string) $transaction['id']] = $transaction;
}
?>
<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tranzactii si detalii note</title>
    <link rel="stylesheet" href="assets/styles.css?v=transactions-20260909-2">
</head>
<body>
<main class="app-shell">
    <header class="topbar report-topbar">
        <div>
            <p class="eyebrow">Citire FoxPro</p>
            <h1>Tranzactii si detalii note</h1>
        </div>
        <div class="toolbar report-toolbar">
            <a class="ghost-button" href="report.php">Rapoarte operatori</a>
            <a class="ghost-button" href="product_report_print.php">Raport imprimanta</a>
        </div>
    </header>

    <?php $activeNav = 'transactions'; require __DIR__ . '/partials/navigation.php'; ?>

    <?php if ($error): ?><div class="notice is-error"><?= h($error) ?></div><?php endif; ?>

    <section class="report-controls transactions-controls">
        <form method="get" class="transaction-filter-form">
            <label>
                <span>Data</span>
                <input type="date" name="date" value="<?= h($date) ?>">
            </label>
            <label>
                <span>Operator</span>
                <select name="operator">
                    <option value="">Toti operatorii</option>
                    <?php foreach ($operators as $option): ?>
                        <option value="<?= h($option) ?>" <?= strcasecmp($operator, $option) === 0 ? 'selected' : '' ?>><?= h($option) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Metoda plata</span>
                <select name="payment">
                    <option value="">Toate metodele</option>
                    <option value="cash" <?= $payment === 'cash' ? 'selected' : '' ?>>Numerar</option>
                    <option value="card" <?= $payment === 'card' ? 'selected' : '' ?>>Card</option>
                    <option value="protocol" <?= $payment === 'protocol' ? 'selected' : '' ?>>Protocol</option>
                    <option value="other" <?= $payment === 'other' ? 'selected' : '' ?>>Alte plati</option>
                </select>
            </label>
            <label class="transaction-search-field">
                <span>Cautare nota sau produs</span>
                <input type="search" name="q" value="<?= h($search) ?>" placeholder="Numar nota, operator, produs">
            </label>
            <button class="primary-button" type="submit">Afiseaza tranzactii</button>
            <a class="ghost-button" href="transactions.php">Sterge filtrele</a>
        </form>
        <div class="report-path-note">
            <strong><?= h($dateLabel) ?></strong>
            <span>Inregistrari sterse ignorate.</span>
        </div>
    </section>

    <section class="report-summary-grid transaction-summary-grid">
        <article class="summary-card"><span>Note afisate</span><strong><?= h($totalTransactions) ?></strong></article>
        <article class="summary-card"><span>Total</span><strong><?= h(app_money($totals['total'])) ?> RON</strong></article>
        <article class="summary-card"><span>Numerar</span><strong><?= h(app_money($totals['cash'])) ?> RON</strong></article>
        <article class="summary-card"><span>Card</span><strong><?= h(app_money($totals['card'])) ?> RON</strong></article>
    </section>

    <section class="report-panel transactions-panel">
        <div class="section-title">
            <span><?= h(($page - 1) * $pageSize + 1) ?> - <?= h(min($page * $pageSize, $totalTransactions)) ?> din <?= h($totalTransactions) ?></span>
            <div class="transaction-section-heading">
                <strong>Lista notelor</strong>
                <div class="transaction-export-actions">
                    <span>Exporta toate rezultatele filtrate</span>
                    <a class="ghost-button" href="<?= h(app_base_url('transactions_pdf.php', $exportParams)) ?>">PDF</a>
                    <a class="ghost-button" href="<?= h(app_base_url('transactions_excel.php', $exportParams)) ?>">Excel</a>
                </div>
            </div>
        </div>
        <div class="table-wrap transaction-table-wrap">
            <table class="report-table transaction-table">
                <thead>
                <tr>
                    <th>Nota</th>
                    <th>Data si ora</th>
                    <th>Operator</th>
                    <th>Masa</th>
                    <th class="num">Total</th>
                    <th class="num">Numerar</th>
                    <th class="num">Card</th>
                    <th>Produse</th>
                    <th>Actiune</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($visibleTransactions === []): ?>
                    <tr><td colspan="9" class="transaction-empty">Nu exista note pentru filtrele alese.</td></tr>
                <?php endif; ?>
                <?php foreach ($visibleTransactions as $transaction): ?>
                    <?php $firstProducts = array_slice($transaction['products'], 0, 5); $remainingProducts = count($transaction['products']) - count($firstProducts); ?>
                    <tr>
                        <td data-label="Nota">
                            <div class="transaction-note-cell">
                                <button class="transaction-preview-trigger" type="button" data-transaction-id="<?= h($transaction['id']) ?>" aria-label="Previzualizeaza nota <?= h($transaction['note_number']) ?>">
                                    <span class="transaction-note-number">#<?= h($transaction['note_number']) ?></span>
                                    <span class="transaction-note-hint">hover pentru produse</span>
                                </button>
                                <div class="transaction-hover-card" role="tooltip" aria-hidden="true">
                                    <strong>Produse pe nota</strong>
                                    <?php foreach ($firstProducts as $product): ?>
                                        <div class="transaction-hover-line"><span><?= h($product['name']) ?></span><b><?= h(number_format((float) $product['quantity'], 2, '.', '')) ?> x <?= h(app_money($product['value'])) ?></b></div>
                                    <?php endforeach; ?>
                                    <?php if ($remainingProducts > 0): ?><small>+<?= h($remainingProducts) ?> produse. Apasa Detalii pentru lista completa.</small><?php endif; ?>
                                    <?php if ($firstProducts === []): ?><small>Nota fara produse in compnote.</small><?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td data-label="Data si ora"><span class="transaction-date"><?= h($transaction['date']) ?></span><small><?= h($transaction['time']) ?></small></td>
                        <td data-label="Operator"><strong><?= h($transaction['operator']) ?></strong><?php if ($transaction['operator_code'] !== ''): ?><small>Cod <?= h($transaction['operator_code']) ?></small><?php endif; ?></td>
                        <td data-label="Masa"><?= h($transaction['table'] !== '' ? $transaction['table'] : '-') ?></td>
                        <td data-label="Total" class="num strong"><?= h(app_money($transaction['total'])) ?></td>
                        <td data-label="Numerar" class="num"><?= h(app_money($transaction['cash'])) ?></td>
                        <td data-label="Card" class="num"><?= h(app_money($transaction['card'])) ?></td>
                        <td data-label="Produse"><span class="transaction-product-count"><?= h(count($transaction['products'])) ?> produse</span><?php if ($transaction['protocol'] > 0.009): ?><span class="transaction-badge is-protocol">Protocol</span><?php endif; ?></td>
                        <td data-label="Actiune"><button class="ghost-button transaction-details-button" type="button" data-transaction-id="<?= h($transaction['id']) ?>">Detalii</button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pages > 1): ?>
            <nav class="transaction-pagination" aria-label="Paginare tranzactii">
                <?php if ($page > 1): ?><a class="ghost-button" href="?<?= h($queryParams($page - 1)) ?>">Inapoi</a><?php endif; ?>
                <span>Pagina <?= h($page) ?> din <?= h($pages) ?></span>
                <?php if ($page < $pages): ?><a class="primary-button" href="?<?= h($queryParams($page + 1)) ?>">Urmatoarea</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    </section>
</main>

<div class="transaction-modal" data-transaction-modal hidden>
    <div class="transaction-modal-backdrop" data-transaction-close></div>
    <section class="transaction-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="transaction-modal-title">
        <button class="transaction-modal-close" type="button" data-transaction-close aria-label="Inchide">X</button>
        <p class="eyebrow">Detalii tranzactie</p>
        <h2 id="transaction-modal-title">Nota</h2>
        <div class="transaction-modal-meta" data-transaction-meta></div>
        <div class="transaction-modal-payments" data-transaction-payments></div>
        <div class="transaction-modal-products">
            <div class="section-title compact"><span>Produse</span><strong>Continut nota</strong></div>
            <div class="table-wrap">
                <table class="report-table">
                    <thead><tr><th>Produs</th><th class="num">Cant.</th><th class="num">Pret</th><th class="num">Valoare</th></tr></thead>
                    <tbody data-transaction-products></tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<script>
window.foxproTransactions = <?= json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="assets/transactions.js"></script>
</body>
</html>
