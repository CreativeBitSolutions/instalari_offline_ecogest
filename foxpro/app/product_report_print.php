<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$config = Config::load();
$date = trim((string) ($_POST['date'] ?? $_GET['date'] ?? '2026-09-08'));
$operator = trim((string) ($_POST['operator'] ?? $_GET['operator'] ?? ''));
$mode = trim((string) ($_POST['mode'] ?? $_GET['mode'] ?? 'products')) === 'summary' ? 'summary' : 'products';
$notice = null;
$error = null;
$previewText = '';
$protocolPreviewText = '';
$report = null;
$allOperators = [];
$settings = ThermalPrinter::settings();
$printers = ThermalPrinter::installedPrinters();
$selectedPrinter = trim((string) ($_POST['printer_name'] ?? $settings['printer_name'] ?? ''));

if (!isset($_SESSION['product_print_csrf'])) {
    $_SESSION['product_print_csrf'] = bin2hex(random_bytes(16));
}
$csrf = (string) $_SESSION['product_print_csrf'];

try {
    $repository = new ProductReportRepository($config);
    $allReport = $repository->build($date);
    $allOperators = $allReport['operators'];
    $report = $operator !== '' ? $repository->build($date, $operator) : $allReport;
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $postedCsrf = (string) ($_POST['csrf'] ?? '');
        if (!hash_equals($csrf, $postedCsrf)) {
            throw new RuntimeException('Sesiunea formularului a expirat. Reincarca pagina.');
        }

        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'save_printer') {
            ThermalPrinter::saveSettings($selectedPrinter);
            $settings = ThermalPrinter::settings();
            $notice = 'Imprimanta a fost salvata: ' . $settings['printer_name'];
        } elseif (in_array($action, ['preview', 'print', 'protocol_preview', 'protocol_print'], true)) {
            if (!$report) {
                throw new RuntimeException('Raportul nu poate fi generat pana cand path-urile DBF nu sunt valide.');
            }
            $restaurantName = (string) ($config['restaurant_name'] ?? 'CARU CU FLORI');
            if (in_array($action, ['protocol_preview', 'protocol_print'], true)) {
                $protocolPreviewText = ProductReportText::buildProtocolSheet($report, $restaurantName);
                if (empty($report['protocol_operators'])) {
                    $notice = 'Pentru data si operatorul alese nu exista vanzari pe protocol.';
                } elseif ($action === 'protocol_print') {
                    ThermalPrinter::send($selectedPrinter, $protocolPreviewText);
                    ThermalPrinter::saveSettings($selectedPrinter);
                    $notice = 'Foaia protocol a fost trimisa la imprimanta ' . $selectedPrinter . '.';
                }
            } else {
                $previewText = ProductReportText::build($report, $mode, $restaurantName);
                if ($action === 'print') {
                    ThermalPrinter::send($selectedPrinter, $previewText);
                    ThermalPrinter::saveSettings($selectedPrinter);
                    $notice = 'Raportul a fost trimis la imprimanta ' . $selectedPrinter . '.';
                }
            }
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
$dateLabel = $dateObject ? $dateObject->format('d.m.Y') : $date;
$pathStatus = Config::pathStatus($config);
$operatorName = $operator !== '' ? $operator : 'Toti operatorii';
?>
<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Trimite raport produse la imprimanta termica</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
<main class="app-shell">
    <header class="topbar report-topbar">
        <div>
            <p class="eyebrow">Raport termic local</p>
            <h1>Trimite raport produse la imprimanta termica</h1>
        </div>
        <div class="toolbar report-toolbar">
            <a class="ghost-button" href="report.php">Rapoarte operatori</a>
            <a class="ghost-button" href="index.php">Configurare path-uri</a>
        </div>
    </header>

    <?php $activeNav = 'printer_report'; require __DIR__ . '/partials/navigation.php'; ?>

    <?php if ($notice): ?><div class="notice"><?= h($notice) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="notice is-error"><?= h($error) ?></div><?php endif; ?>

    <section class="product-print-layout">
        <div class="product-print-main">
            <section class="report-controls product-print-controls">
                <div class="section-title">
                    <span>Filtrare</span>
                    <strong><?= h($dateLabel) ?>, <?= h($operatorName) ?></strong>
                </div>
                <form method="post" class="product-report-form">
                    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                    <input type="hidden" name="printer_name" value="<?= h($selectedPrinter) ?>">
                    <label>
                        <span>Data</span>
                        <input type="date" name="date" value="<?= h($date) ?>" required>
                    </label>
                    <label>
                        <span>Operator</span>
                        <select name="operator">
                            <option value="">Toti operatorii</option>
                            <?php foreach ($allOperators as $row): ?>
                                <option value="<?= h($row['name']) ?>" <?= strcasecmp($operator, (string) $row['name']) === 0 ? 'selected' : '' ?>><?= h($row['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <fieldset class="mode-options">
                        <legend>Continut raport</legend>
                        <label class="mode-option">
                            <input type="radio" name="mode" value="products" <?= $mode === 'products' ? 'checked' : '' ?>>
                            <span><strong>Produse agregate</strong><small>Operator, produse, cantitati, valoare si metode de plata</small></span>
                        </label>
                        <label class="mode-option">
                            <input type="radio" name="mode" value="summary" <?= $mode === 'summary' ? 'checked' : '' ?>>
                            <span><strong>Doar operatori si sume</strong><small>Operator, numerar, card, alte plati si total</small></span>
                        </label>
                    </fieldset>
                    <div class="product-report-actions">
                        <button class="primary-button" type="submit" name="action" value="preview">Genereaza previzualizare</button>
                        <button class="ghost-button direct-print-button" type="submit" name="action" value="print">Trimite raport la imprimanta termica</button>
                    </div>
                </form>
                <p class="form-note">Citirea exclude inregistrarile marcate ca sterse in FoxPro. Produsele pe protocol sunt legate de operator prin NR_NOTA din note si compnote.</p>
            </section>

            <section class="report-panel protocol-report-panel">
                <div class="section-title">
                    <span>Optiune separata</span>
                    <strong>Raport produse vandute pe protocol</strong>
                </div>
                <p class="protocol-report-description">Genereaza numai foaia protocol. Produsele sunt grupate dupa operatorul fiecarei note protocol.</p>
                <form method="post" class="product-report-form protocol-report-form">
                    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                    <input type="hidden" name="printer_name" value="<?= h($selectedPrinter) ?>">
                    <label>
                        <span>Data protocol</span>
                        <input type="date" name="date" value="<?= h($date) ?>" required>
                    </label>
                    <label>
                        <span>Operator protocol</span>
                        <select name="operator">
                            <option value="">Toti operatorii</option>
                            <?php foreach ($allOperators as $row): ?>
                                <option value="<?= h($row['name']) ?>" <?= strcasecmp($operator, (string) $row['name']) === 0 ? 'selected' : '' ?>><?= h($row['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <div class="product-report-actions">
                        <button class="primary-button" type="submit" name="action" value="protocol_preview">Genereaza foaia protocol</button>
                        <button class="ghost-button direct-print-button" type="submit" name="action" value="protocol_print">Trimite foaia protocol la imprimanta</button>
                    </div>
                </form>
            </section>

            <?php if ($report): ?>
                <section class="report-summary-grid product-summary-grid">
                    <article class="summary-card"><span>Total vanzari</span><strong><?= h(app_money($report['totals']['total'] ?? 0)) ?> RON</strong></article>
                    <article class="summary-card"><span>Numerar</span><strong><?= h(app_money($report['totals']['cash'] ?? 0)) ?> RON</strong></article>
                    <article class="summary-card"><span>Card</span><strong><?= h(app_money($report['totals']['card'] ?? 0)) ?> RON</strong></article>
                    <article class="summary-card"><span>Operatori</span><strong><?= h(count($report['operators'])) ?></strong></article>
                </section>
            <?php endif; ?>

            <?php if ($previewText !== ''): ?>
                <section class="report-panel thermal-preview-panel">
                    <div class="section-title">
                        <span>Previzualizare</span>
                        <strong>Textul care va fi trimis</strong>
                    </div>
                    <pre class="thermal-preview"><?= h($previewText) ?></pre>
                    <form method="post" class="print-form">
                        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                        <input type="hidden" name="action" value="print">
                        <input type="hidden" name="date" value="<?= h($date) ?>">
                        <input type="hidden" name="operator" value="<?= h($operator) ?>">
                        <input type="hidden" name="mode" value="<?= h($mode) ?>">
                        <input type="hidden" name="printer_name" value="<?= h($selectedPrinter) ?>">
                        <button class="primary-button print-report-button" type="submit">Trimite raport produse la imprimanta termica</button>
                    </form>
                </section>
            <?php endif; ?>

            <?php if ($protocolPreviewText !== ''): ?>
                <section class="report-panel thermal-preview-panel protocol-preview-panel">
                    <div class="section-title">
                        <span>Previzualizare separata</span>
                        <strong>Foaie produse pe protocol</strong>
                    </div>
                    <pre class="thermal-preview <?= empty($report['protocol_operators']) ? 'is-empty' : '' ?>"><?= h($protocolPreviewText) ?></pre>
                    <?php if (!empty($report['protocol_operators'])): ?>
                        <form method="post" class="print-form">
                            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                            <input type="hidden" name="action" value="protocol_print">
                            <input type="hidden" name="date" value="<?= h($date) ?>">
                            <input type="hidden" name="operator" value="<?= h($operator) ?>">
                            <input type="hidden" name="printer_name" value="<?= h($selectedPrinter) ?>">
                            <button class="primary-button print-report-button" type="submit">Trimite foaia protocol la imprimanta</button>
                        </form>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </div>

        <aside class="product-print-side">
            <section class="report-panel printer-settings-panel">
                <div class="section-title">
                    <span>Configurare</span>
                    <strong>Imprimanta termica</strong>
                </div>
                <form method="post" class="printer-settings-form">
                    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                    <input type="hidden" name="action" value="save_printer">
                    <label>
                        <span>Imprimanta aleasa</span>
                        <select name="printer_name">
                            <?php if ($selectedPrinter !== '' && !in_array($selectedPrinter, $printers, true)): ?>
                                <option value="<?= h($selectedPrinter) ?>" selected><?= h($selectedPrinter) ?>, salvata dar negasita acum</option>
                            <?php endif; ?>
                            <?php foreach ($printers as $printer): ?>
                                <option value="<?= h($printer) ?>" <?= strcasecmp($selectedPrinter, $printer) === 0 ? 'selected' : '' ?>><?= h($printer) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span>Nume manual, daca nu apare in lista</span>
                        <input type="text" name="manual_printer_name" value="" placeholder="Exemplu: BAR">
                    </label>
                    <p class="form-note">Lista este citita din Windows. Numele salvat ramane in configurare chiar daca imprimanta nu este disponibila.</p>
                    <button class="ghost-button" type="submit" onclick="this.form.printer_name.value = this.form.manual_printer_name.value.trim() || this.form.printer_name.value">Salveaza imprimanta</button>
                </form>
                <?php if ($printers === []): ?>
                    <div class="notice is-warning printer-warning">Windows nu a returnat imprimante instalate. Poti introduce manual numele imprimantei.</div>
                <?php endif; ?>
            </section>

            <section class="report-path-status compact-path-status">
                <div class="section-title">
                    <span>Surse</span>
                    <strong>Status DBF</strong>
                </div>
                <div class="path-status-grid">
                    <?php foreach ($pathStatus as $status): ?>
                        <div class="path-status-card <?= $status['available'] ? 'is-ok' : 'is-bad' ?>">
                            <strong><?= h($status['label']) ?></strong>
                            <span><?= h($status['message']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </aside>
    </section>
</main>
</body>
</html>
