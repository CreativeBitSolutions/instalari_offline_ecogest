<?php
declare(strict_types=1);

include __DIR__ . '/session.php';
require_once __DIR__ . '/setari_lorand_schema.php';

$clientId = (int)($_SESSION['client_id'] ?? 0);
$locationId = (int)($_SESSION['cod_locatie'] ?? 0);
$lorandSettings = vanzare_v2_lorand_settings($pdo);
if ($clientId !== 1019 || $locationId <= 0 || empty($lorandSettings['operator_acces_rapoarte'])) {
    header('Location: vanzare_magazin.php');
    exit;
}

date_default_timezone_set('Europe/Bucharest');

function protocolHistoryH($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function protocolHistoryMoney($value): string
{
    return number_format((float)$value, 2, ',', '.');
}

function protocolHistoryQuantity($value): string
{
    $formatted = number_format((float)$value, 3, ',', '.');
    return rtrim(rtrim($formatted, '0'), ',');
}

$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));
if ($dateFrom !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $dateFrom)) {
    $dateFrom = '';
}
if ($dateTo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $dateTo)) {
    $dateTo = '';
}
if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
    [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
}

$where = "n.locatie = :location AND n.status = 'F' AND COALESCE(n.protocol, 0) > 0";
$params = ['location' => $locationId];
if ($dateFrom !== '') {
    $where .= ' AND n.data_bon >= :date_from';
    $params['date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $where .= ' AND n.data_bon <= :date_to';
    $params['date_to'] = $dateTo;
}

$stmt = $pdo->prepare("\n    SELECT\n        n.nrbon,\n        n.data_bon,\n        n.ora_bon,\n        n.operator,\n        n.protocol,\n        n.valoare_vanzare_cu_tva AS total_nota,\n        n.nr_raport_z,\n        a.admin_firstname,\n        a.admin_lastname,\n        dn.id_vanz,\n        COALESCE(NULLIF(TRIM(dn.nume_produs), ''), 'Produs fără denumire') AS produs,\n        COALESCE(dn.cantitate, 0) AS cantitate,\n        COALESCE(dn.valoare_vanzare_cu_tva, 0) AS valoare_produs,\n        COALESCE(dn.observatie_produs, '') AS observatie_produs\n    FROM note n\n    LEFT JOIN admins_12 a ON a.admin_id = n.operator\n    LEFT JOIN det_note dn ON dn.nr_bon = n.nrbon\n    WHERE {$where}\n    ORDER BY n.data_bon DESC, n.ora_bon DESC, n.nrbon DESC, dn.id_vanz ASC\n");
$stmt->execute($params);

$notes = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $noteId = (int)$row['nrbon'];
    if (!isset($notes[$noteId])) {
        $operatorName = trim((string)($row['admin_firstname'] ?? '') . ' ' . (string)($row['admin_lastname'] ?? ''));
        $notes[$noteId] = [
            'id' => $noteId,
            'date' => (string)($row['data_bon'] ?? ''),
            'time' => (string)($row['ora_bon'] ?? ''),
            'operator' => $operatorName !== '' ? $operatorName : 'Operator ID ' . (int)$row['operator'],
            'protocol' => (float)($row['protocol'] ?? 0),
            'note_total' => (float)($row['total_nota'] ?? 0),
            'z' => (int)($row['nr_raport_z'] ?? 0),
            'products' => [],
        ];
    }
    if ($row['id_vanz'] !== null) {
        $notes[$noteId]['products'][] = [
            'name' => (string)$row['produs'],
            'quantity' => (float)$row['cantitate'],
            'value' => (float)$row['valoare_produs'],
            'observation' => trim((string)$row['observatie_produs']),
        ];
    }
}

$protocolTotal = 0.0;
foreach ($notes as $note) {
    $protocolTotal += (float)$note['protocol'];
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Istoric note PROTOCOL</title>
    <style>
        :root{--bg:#11181d;--panel:#fff;--ink:#17212a;--muted:#68757f;--accent:#a32626;--accent2:#e7ad32;--line:#d8e0e5;--soft:#f4f7f8}
        *{box-sizing:border-box}
        body{margin:0;background:linear-gradient(135deg,#10171c,#1b262c);color:var(--ink);font-family:Bahnschrift,"Trebuchet MS",sans-serif;min-height:100vh}
        .shell{width:min(1180px,100%);margin:0 auto;padding:14px}
        .topbar{display:flex;align-items:center;gap:12px;justify-content:space-between;background:var(--panel);border-radius:12px;padding:14px 16px;border-top:5px solid var(--accent2);box-shadow:0 10px 32px rgba(0,0,0,.24);position:sticky;top:0;z-index:10}
        .topbar h1{font-size:clamp(19px,3vw,28px);margin:0}.eyebrow{font-size:11px;font-weight:800;letter-spacing:.12em;color:var(--accent);text-transform:uppercase;margin-bottom:4px}
        .back{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:0 18px;border-radius:8px;background:#202b31;color:#fff;text-decoration:none;font-weight:800;white-space:nowrap}
        .summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin:12px 0}
        .stat{background:var(--panel);border-radius:10px;padding:13px 15px;border-left:5px solid var(--accent2)}.stat-label{color:var(--muted);font-size:12px;font-weight:700;text-transform:uppercase}.stat-value{font-size:clamp(20px,3vw,31px);font-weight:900;margin-top:3px}
        .filters{background:var(--panel);border-radius:10px;padding:12px;margin-bottom:12px;display:flex;align-items:end;gap:10px;flex-wrap:wrap}
        .field{display:flex;flex-direction:column;gap:4px;min-width:155px}.field label{font-size:12px;font-weight:800;color:var(--muted)}.field input{min-height:42px;border:1px solid #bac6cd;border-radius:7px;padding:7px 10px;font-size:16px}
        .filter-actions{display:flex;gap:8px}.btn{min-height:42px;border:0;border-radius:7px;padding:0 16px;font-weight:800;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center}.btn-primary{background:#146da0;color:#fff}.btn-light{background:#e9eef1;color:#26343c}
        .empty{background:#fff;border-radius:10px;padding:40px 18px;text-align:center;color:var(--muted);font-weight:700}
        .note{background:var(--panel);border-radius:11px;margin-bottom:12px;overflow:hidden;box-shadow:0 6px 20px rgba(0,0,0,.16)}
        .note-head{display:grid;grid-template-columns:1fr auto;gap:10px;padding:13px 15px;background:linear-gradient(90deg,#f8fafb,#edf2f4);border-bottom:1px solid var(--line)}
        .note-title{font-size:18px;font-weight:900}.meta{color:var(--muted);font-size:13px;margin-top:4px;display:flex;gap:10px;flex-wrap:wrap}.note-total{text-align:right}.note-total strong{display:block;color:var(--accent);font-size:22px}.note-total span{font-size:11px;color:var(--muted);font-weight:800;text-transform:uppercase}
        .products{padding:5px 15px 10px}.product{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;padding:10px 0;border-bottom:1px dashed var(--line)}.product:last-child{border-bottom:0}.product-name{font-weight:800}.product-observation{font-size:12px;color:#8a5a00;margin-top:3px}.product-value{text-align:right;font-weight:900;white-space:nowrap}.product-value small{display:block;color:var(--muted);font-weight:700;margin-bottom:2px}
        @media(max-width:640px){.shell{padding:8px}.topbar{border-radius:8px;padding:11px}.back{padding:0 12px}.summary{grid-template-columns:1fr 1fr}.summary .stat:last-child{grid-column:1/-1}.filters{align-items:stretch}.field{width:calc(50% - 5px);min-width:0}.filter-actions{width:100%}.filter-actions .btn{flex:1;justify-content:center}.note-head{grid-template-columns:1fr}.note-total{text-align:left;display:flex;align-items:baseline;gap:7px}.note-total span{order:-1}}
    </style>
    <?php
    $i18nBootstrap = __DIR__ . '/i18n/i18n_bootstrap.php';
    if (is_file($i18nBootstrap)) {
        include $i18nBootstrap;
    }
    unset($i18nBootstrap);
    ?>
</head>
<body>
<main class="shell">
    <header class="topbar">
        <div>
            <div class="eyebrow">ECOGEST MAGAZIN LORAND</div>
            <h1>Istoric note PROTOCOL</h1>
        </div>
        <a class="back" href="rapoarte_produse_lorand.php">Înapoi la rapoarte</a>
    </header>

    <section class="summary" aria-label="Sumar protocol">
        <div class="stat"><div class="stat-label">Note PROTOCOL</div><div class="stat-value"><?= count($notes) ?></div></div>
        <div class="stat"><div class="stat-label">Total PROTOCOL</div><div class="stat-value"><?= protocolHistoryMoney($protocolTotal) ?> LEI</div></div>
        <div class="stat"><div class="stat-label">Perioadă</div><div class="stat-value" style="font-size:18px"><?= $dateFrom === '' && $dateTo === '' ? 'Toate' : protocolHistoryH(($dateFrom ?: 'început') . ' / ' . ($dateTo ?: 'astăzi')) ?></div></div>
    </section>

    <form class="filters" method="get">
        <div class="field"><label for="date_from">De la data</label><input id="date_from" name="date_from" type="date" value="<?= protocolHistoryH($dateFrom) ?>"></div>
        <div class="field"><label for="date_to">Până la data</label><input id="date_to" name="date_to" type="date" value="<?= protocolHistoryH($dateTo) ?>"></div>
        <div class="filter-actions"><button class="btn btn-primary" type="submit">Aplică filtrul</button><a class="btn btn-light" href="istoric_protocol_lorand.php">Toate notele</a></div>
    </form>

    <?php if (!$notes): ?>
        <div class="empty">Nu există note finalizate pe PROTOCOL pentru perioada aleasă.</div>
    <?php endif; ?>

    <?php foreach ($notes as $note): ?>
        <article class="note">
            <div class="note-head">
                <div>
                    <div class="note-title">Nota #<?= (int)$note['id'] ?></div>
                    <div class="meta">
                        <span><?= protocolHistoryH($note['date']) ?>, <?= protocolHistoryH($note['time']) ?></span>
                        <span><?= protocolHistoryH($note['operator']) ?></span>
                        <?php if ((int)$note['z'] > 0): ?><span>Raport Z <?= (int)$note['z'] ?></span><?php endif; ?>
                    </div>
                </div>
                <div class="note-total"><strong><?= protocolHistoryMoney($note['protocol']) ?> LEI</strong><span>Total PROTOCOL</span></div>
            </div>
            <div class="products">
                <?php if (!$note['products']): ?><div class="product"><div class="product-name">Nota nu are produse în detaliu.</div></div><?php endif; ?>
                <?php foreach ($note['products'] as $product): ?>
                    <div class="product">
                        <div>
                            <div class="product-name"><?= protocolHistoryQuantity($product['quantity']) ?> x <?= protocolHistoryH($product['name']) ?></div>
                            <?php if ($product['observation'] !== ''): ?><div class="product-observation">Obs: <?= protocolHistoryH($product['observation']) ?></div><?php endif; ?>
                        </div>
                        <div class="product-value"><?= protocolHistoryMoney($product['value']) ?> LEI</div>
                    </div>
                <?php endforeach; ?>
            </div>
        </article>
    <?php endforeach; ?>
</main>
</body>
</html>
