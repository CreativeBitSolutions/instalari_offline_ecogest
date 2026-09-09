<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$config = Config::load();
$repo = new RelistareRepository($config);
$type = (string) ($_GET['type'] ?? 'nota');

try {
    $document = $type === 'bon'
        ? $repo->getBonDocument((string) ($_GET['key'] ?? $_GET['id'] ?? ''))
        : $repo->getNotaDocument((string) ($_GET['id'] ?? ''));
} catch (Throwable $exception) {
    $document = null;
    $error = $exception->getMessage();
}

if (!$document) {
    http_response_code(404);
}
?>
<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $document ? h(($document['type'] === 'bon' ? 'Bon comanda ' : 'Nota ') . ($document['number'] ?? '')) : 'Document negasit' ?></title>
    <link rel="stylesheet" href="assets/print.css">
</head>
<body>
<?php if (!$document): ?>
    <main class="missing">
        <h1>Document negasit</h1>
        <p><?= h($error ?? 'Nu exista date pentru identificatorul cerut.') ?></p>
        <a href="index.php">Inapoi</a>
    </main>
<?php else: ?>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Printeaza</button>
        <a href="<?= h(app_base_url('pdf.php', $document['type'] === 'bon' ? ['type' => 'bon', 'key' => $document['key']] : ['type' => 'nota', 'id' => $document['contor'] ?: $document['number']])) ?>">Deschide PDF</a>
        <a href="index.php">Inapoi</a>
    </div>

    <?php if ($document['type'] === 'bon'): ?>
        <article class="receipt receipt-bon">
            <header class="bon-head">
                <strong><?= h($document['restaurant']) ?></strong>
                <dl>
                    <div><dt>Data:</dt><dd><?= h(app_ro_date($document['date'])) ?></dd></div>
                    <div><dt>Ora:</dt><dd><?= h($document['time']) ?></dd></div>
                    <div><dt>Nr.Bon:</dt><dd><?= h($document['number']) ?></dd></div>
                    <div><dt>Nr.Crt:</dt><dd><?= h($document['contor'] ?: $document['contor_bon']) ?></dd></div>
                </dl>
            </header>

            <table class="bon-items">
                <tbody>
                <?php foreach ($document['items'] as $item): ?>
                    <tr>
                        <td><?= h(rtrim(rtrim(app_money($item['quantity']), '0'), '.')) ?></td>
                        <td><?= h($item['name']) ?></td>
                        <td><?= h(app_money($item['value'])) ?></td>
                    </tr>
                    <?php if ($item['obs'] !== ''): ?>
                        <tr class="obs"><td></td><td colspan="2"><?= h($item['obs']) ?></td></tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                </tbody>
            </table>

            <footer class="bon-foot">
                <strong>Total <?= h(app_money($document['total'])) ?></strong>
                <span>Ospatar:<?= h($document['waiter']) ?></span>
            </footer>
        </article>
    <?php else: ?>
        <article class="receipt receipt-nota">
            <header>
                <h1><?= h($document['restaurant']) ?></h1>
                <p><strong>Data</strong> <?= h(app_ro_date($document['date'])) ?> <strong>Ora</strong> <?= h($document['time']) ?></p>
                <h2>Nota de plata nr.<?= h($document['number']) ?></h2>
                <h3>Masa: <?= h($document['table']) ?></h3>
            </header>

            <table class="nota-items">
                <thead>
                <tr>
                    <th colspan="3">Denumire produs</th>
                </tr>
                <tr>
                    <th>Cantitate</th>
                    <th>Pret</th>
                    <th>Valoare</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($document['items'] as $item): ?>
                    <tr class="product"><td colspan="3"><?= h($item['name']) ?></td></tr>
                    <tr class="values">
                        <td><?= h(app_money($item['quantity'])) ?>x</td>
                        <td><?= h(app_money($item['price'])) ?>=</td>
                        <td><?= h(app_money($item['value'])) ?></td>
                    </tr>
                    <?php if ($item['obs'] !== ''): ?>
                        <tr class="obs"><td colspan="3"><?= h($item['obs']) ?></td></tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                </tbody>
            </table>

            <section class="total-line">
                <strong>TOTAL DE PLATA:</strong>
                <span><?= h(app_money($document['total'])) ?> RON</span>
            </section>

            <section class="payments">
                <?php foreach ($document['payments'] as $payment): ?>
                    <p>Plata <?= h($payment['label']) ?>: <strong><?= h(app_money($payment['amount'])) ?> RON</strong></p>
                <?php endforeach; ?>
            </section>

            <footer>
                <p>Ospatar:<?= h($document['waiter']) ?></p>
                <strong>Va multumim si va mai asteptam</strong>
                <b>!</b>
                <small>(c) ECOSOFT S.R.L. Sibiu<br>Tel. 0744.299843</small>
            </footer>
        </article>
    <?php endif; ?>
<?php endif; ?>
</body>
</html>
