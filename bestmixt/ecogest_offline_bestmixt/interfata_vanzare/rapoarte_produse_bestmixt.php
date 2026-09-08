<?php
declare(strict_types=1);
require_once __DIR__ . '/session.php';
header('Cache-Control: no-store');
if ((int)($_SESSION['client_id'] ?? 0) !== 21
    || (int)$pdo->query('SELECT operator_acces_rapoarte FROM setari_platforma LIMIT 1')->fetchColumn() !== 1) {
    http_response_code(403);
    exit('Accesul la rapoarte nu este permis.');
}
$location = (int)($_SESSION['cod_locatie'] ?? 1);
session_write_close();
function br_h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function br_number($value): string { return number_format((float)$value, 2, ',', '.'); }
$fromText = (string)($_GET['from'] ?? (date('Y-m-d') . 'T00:00'));
$toText = (string)($_GET['to'] ?? (date('Y-m-d') . 'T23:59'));
$operator = max(0, (int)($_GET['operator'] ?? 0));
$product = trim((string)($_GET['product'] ?? ''));
$zone = new DateTimeZone('Europe/Bucharest');
$from = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $fromText, $zone);
$to = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $toText, $zone);
$error = '';
$rows = [];
$totals = ['value' => 0.0, 'vat' => 0.0, 'discount' => 0.0];
if (!$from || !$to || $from->format('Y-m-d\TH:i') !== $fromText || $to->format('Y-m-d\TH:i') !== $toText
    || $to < $from || $to->getTimestamp() - $from->getTimestamp() > 31 * 86400) {
    $error = 'Alege un interval valid de cel mult 31 de zile.';
} else {
    $where = "n.status = 'F' AND n.locatie = :location
        AND (n.data_bon || ' ' || COALESCE(NULLIF(n.ora_bon, ''), '00:00:00')) BETWEEN :start AND :end";
    $params = [':location' => $location, ':start' => $from->format('Y-m-d H:i:s'), ':end' => $to->format('Y-m-d H:i:59')];
    if ($operator > 0) { $where .= ' AND n.operator = :operator'; $params[':operator'] = $operator; }
    if ($product !== '') {
        $where .= " AND (dn.nume_produs LIKE :product OR CAST(dn.cod_p AS TEXT) = :code)";
        $params[':product'] = '%' . $product . '%'; $params[':code'] = $product;
    }
    // Sumele provin din liniile istorice, nu din prețurile catalogului actual.
    $stmt = $pdo->prepare("SELECT dn.cod_p, dn.nume_produs, dn.cota_tva,
        SUM(dn.cantitate) AS quantity, SUM(dn.valoare_vanzare_cu_tva) AS value,
        SUM(dn.tva_col) AS vat, SUM(dn.discount) AS discount, COUNT(DISTINCT n.nrbon) AS receipts
        FROM det_note dn JOIN note n ON n.nrbon = dn.nr_bon
        WHERE {$where} GROUP BY dn.cod_p, dn.nume_produs, dn.cota_tva ORDER BY dn.nume_produs, dn.cod_p");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) { foreach ($totals as $key => $value) { $totals[$key] += (float)$row[$key]; } }
}
$operators = $pdo->prepare("SELECT DISTINCT n.operator AS id, a.admin_firstname, a.admin_lastname
    FROM note n LEFT JOIN admins_12 a ON a.admin_id = n.operator
    WHERE n.status = 'F' AND n.locatie = ? ORDER BY a.admin_firstname, a.admin_lastname, n.operator");
$operators->execute([$location]);
?>
<!doctype html>
<html lang="ro"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Bestmixt, rapoarte produse</title><link rel="stylesheet" href="vendor/offline/bootstrap4/bootstrap.min.css">
<style>body{background:#f1f4f6;color:#20333e}main{max-width:1250px;margin:auto;padding:24px}header,form{display:flex;gap:16px;align-items:end;flex-wrap:wrap;margin-bottom:24px}header{justify-content:space-between;align-items:center}h1{font-size:26px;margin:0}label{display:block;margin:0;font-weight:600}input,select{display:block;margin-top:5px;padding:8px;border:1px solid #aab9c1;border-radius:4px}.summary{padding:16px;background:white;border-left:4px solid #167b66;margin:20px 0}.table{background:white}.number{text-align:right;white-space:nowrap}tfoot{font-weight:bold}</style></head>
<body><main><header><h1>Rapoarte produse</h1><a class="btn btn-secondary" href="vanzare_magazin.php">Înapoi la vânzare</a></header>
<form method="get">
<label>De la<input type="datetime-local" name="from" value="<?= br_h($fromText) ?>" required></label>
<label>Până la<input type="datetime-local" name="to" value="<?= br_h($toText) ?>" required></label>
<label>Operator<select name="operator"><option value="0">Toți operatorii</option>
<?php foreach ($operators as $op): $name = trim($op['admin_firstname'] . ' ' . $op['admin_lastname']); ?>
<option value="<?= (int)$op['id'] ?>" <?= $operator === (int)$op['id'] ? 'selected' : '' ?>><?= br_h($name !== '' ? $name : 'Operator ' . $op['id']) ?></option>
<?php endforeach; ?></select></label>
<label>Produs sau cod<input name="product" value="<?= br_h($product) ?>" maxlength="150"></label>
<button class="btn btn-primary" type="submit">Afișează</button></form>
<?php if ($error !== ''): ?><p class="alert alert-warning"><?= br_h($error) ?></p><?php else: ?>
<p class="summary">Vânzări finalizate în locația <?= $location ?>. Valoare <?= br_number($totals['value']) ?> lei. TVA <?= br_number($totals['vat']) ?> lei.</p>
<div class="table-responsive"><table class="table table-striped"><thead><tr><th>Cod</th><th>Produs</th><th class="number">TVA %</th><th class="number">Cantitate</th><th class="number">Bonuri</th><th class="number">Discount lei</th><th class="number">TVA lei</th><th class="number">Valoare lei</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td><?= (int)$row['cod_p'] ?></td><td><?= br_h($row['nume_produs']) ?></td><td class="number"><?= br_number($row['cota_tva']) ?></td><td class="number"><?= br_number($row['quantity']) ?></td><td class="number"><?= (int)$row['receipts'] ?></td><td class="number"><?= br_number($row['discount']) ?></td><td class="number"><?= br_number($row['vat']) ?></td><td class="number"><?= br_number($row['value']) ?></td></tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="8">Nu există vânzări pentru filtrele alese.</td></tr><?php endif; ?>
</tbody><tfoot><tr><td colspan="5">Total</td><td class="number"><?= br_number($totals['discount']) ?></td><td class="number"><?= br_number($totals['vat']) ?></td><td class="number"><?= br_number($totals['value']) ?></td></tr></tfoot></table></div>
<?php endif; ?></main></body></html>
