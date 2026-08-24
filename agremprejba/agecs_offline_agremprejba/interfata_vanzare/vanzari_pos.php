<?php
include 'session.php';

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function money_ro($value)
{
    return number_format((float)$value, 2, '.', '');
}

function valid_date_or_default($value, $default)
{
    $value = trim((string)$value);
    if ($value === '') {
        return $default;
    }

    $dt = DateTime::createFromFormat('Y-m-d', $value);
    return $dt && $dt->format('Y-m-d') === $value ? $value : $default;
}

function status_label($status)
{
    $status = (string)$status;
    if ($status === 'F') {
        return 'Finalizat';
    }
    if ($status === 'S') {
        return 'Deschis';
    }
    return $status !== '' ? $status : 'Nedefinit';
}

$today = date('Y-m-d');
$monthStart = date('Y-m-01');

$filters = [
    'data_start' => valid_date_or_default($_GET['data_start'] ?? '', $monthStart),
    'data_end' => valid_date_or_default($_GET['data_end'] ?? '', $today),
    'status' => trim((string)($_GET['status'] ?? 'F')),
    'q' => trim((string)($_GET['q'] ?? '')),
];

if ($filters['data_start'] > $filters['data_end']) {
    $tmp = $filters['data_start'];
    $filters['data_start'] = $filters['data_end'];
    $filters['data_end'] = $tmp;
}

$where = ['date(n.data_bon) BETWEEN :data_start AND :data_end'];
$params = [
    ':data_start' => $filters['data_start'],
    ':data_end' => $filters['data_end'],
];

if ($filters['status'] !== '') {
    $where[] = 'n.status = :status';
    $params[':status'] = $filters['status'];
}

if ($filters['q'] !== '') {
    $where[] = "(
        CAST(n.nrbon AS TEXT) = :q_exact
        OR EXISTS (
            SELECT 1
            FROM det_note dq
            LEFT JOIN produse_servicii pq ON pq.cod_produs = dq.cod_p
            WHERE dq.nr_bon = n.nrbon
              AND (dq.nume_produs LIKE :q_like OR pq.nume LIKE :q_like)
        )
    )";
    $params[':q_exact'] = $filters['q'];
    $params[':q_like'] = '%' . $filters['q'] . '%';
}

$whereSql = implode(' AND ', $where);

$summaryStmt = $pdo->prepare(
    "SELECT COUNT(*) AS nr_bonuri,
            COALESCE(SUM(n.valoare_vanzare_cu_tva), 0) AS total_vanzari,
            COALESCE(SUM(n.discount), 0) AS total_discount,
            COALESCE(SUM(n.numerar), 0) AS total_numerar,
            COALESCE(SUM(n.card), 0) AS total_card,
            COALESCE(SUM(n.tichete), 0) AS total_tichete,
            COALESCE(SUM(n.protocol), 0) AS total_protocol,
            COALESCE(SUM(n.glovo), 0) AS total_glovo
     FROM note n
     WHERE $whereSql"
);
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$salesStmt = $pdo->prepare(
    "SELECT n.nrbon,
            n.data_bon,
            n.ora_bon,
            n.status,
            n.locatie,
            n.operator,
            n.valoare_vanzare_cu_tva,
            n.discount,
            n.tva_colectata,
            n.numerar,
            n.card,
            n.tichete,
            n.protocol,
            n.glovo,
            n.cod_inchidere,
            n.nr_raport_z,
            COUNT(d.id_vanz) AS nr_linii,
            COALESCE(SUM(d.cantitate), 0) AS total_cantitate
     FROM note n
     LEFT JOIN det_note d ON d.nr_bon = n.nrbon
     WHERE $whereSql
     GROUP BY n.nrbon
     ORDER BY date(n.data_bon) DESC, time(n.ora_bon) DESC, n.nrbon DESC
     LIMIT 500"
);
$salesStmt->execute($params);
$sales = $salesStmt->fetchAll(PDO::FETCH_ASSOC);

$selectedBon = isset($_GET['bon']) ? (int)$_GET['bon'] : 0;
$selectedSale = null;
$saleDetails = [];

if ($selectedBon > 0) {
    $detailHeaderStmt = $pdo->prepare(
        "SELECT nrbon, data_bon, ora_bon, status, locatie, operator,
                valoare_vanzare_cu_tva, discount, tva_colectata,
                numerar, card, tichete, protocol, glovo,
                cod_inchidere, nr_raport_z
         FROM note
         WHERE nrbon = :nrbon
         LIMIT 1"
    );
    $detailHeaderStmt->execute([':nrbon' => $selectedBon]);
    $selectedSale = $detailHeaderStmt->fetch(PDO::FETCH_ASSOC);

    if ($selectedSale) {
        $detailsStmt = $pdo->prepare(
            "SELECT d.id_vanz,
                    d.cod_p,
                    COALESCE(NULLIF(d.nume_produs, ''), p.nume, '') AS nume_produs,
                    d.cantitate,
                    d.pret_vanzare,
                    d.valoare_vanzare,
                    d.valoare_vanzare_cu_tva,
                    d.discount,
                    d.cota_tva,
                    d.tva_col,
                    d.pachet,
                    d.data,
                    d.ora,
                    p.um
             FROM det_note d
             LEFT JOIN produse_servicii p ON p.cod_produs = d.cod_p
             WHERE d.nr_bon = :nrbon
             ORDER BY d.id_vanz"
        );
        $detailsStmt->execute([':nrbon' => $selectedBon]);
        $saleDetails = $detailsStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$baseQuery = $filters;
?>
<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vanzari POS</title>
    <link rel="stylesheet" href="vendor/offline/bootstrap5/bootstrap.min.css">
    <style>
        body { background:#f3f4f6; }
        .page { padding:18px; }
        .card { border-radius:8px; }
        .table-wrap { overflow:auto; }
        table { min-width:1450px; }
        th { white-space:nowrap; }
        .metric { background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:12px; }
        .metric small { color:#6b7280; display:block; }
        .metric strong { font-size:20px; }
        .badge-soft { background:#eef2ff; color:#3730a3; }
        .details-table { min-width:1050px; }
    </style>
</head>
<body>
<div class="page">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="m-0">Vanzari POS</h3>
        <a class="btn btn-secondary" href="vanzare_magazin.php">Inapoi la vanzare</a>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-md-2">
            <div class="metric"><small>Bonuri</small><strong><?php echo (int)($summary['nr_bonuri'] ?? 0); ?></strong></div>
        </div>
        <div class="col-md-2">
            <div class="metric"><small>Total</small><strong><?php echo money_ro($summary['total_vanzari'] ?? 0); ?></strong></div>
        </div>
        <div class="col-md-2">
            <div class="metric"><small>Numerar</small><strong><?php echo money_ro($summary['total_numerar'] ?? 0); ?></strong></div>
        </div>
        <div class="col-md-2">
            <div class="metric"><small>Card</small><strong><?php echo money_ro($summary['total_card'] ?? 0); ?></strong></div>
        </div>
        <div class="col-md-2">
            <div class="metric"><small>Online</small><strong><?php echo money_ro($summary['total_glovo'] ?? 0); ?></strong></div>
        </div>
        <div class="col-md-2">
            <div class="metric"><small>Discount</small><strong><?php echo money_ro($summary['total_discount'] ?? 0); ?></strong></div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="get" class="row g-2">
                <div class="col-md-2">
                    <label class="form-label">Data inceput</label>
                    <input type="date" class="form-control" name="data_start" value="<?php echo h($filters['data_start']); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Data sfarsit</label>
                    <input type="date" class="form-control" name="data_end" value="<?php echo h($filters['data_end']); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="" <?php echo $filters['status'] === '' ? 'selected' : ''; ?>>Toate</option>
                        <option value="F" <?php echo $filters['status'] === 'F' ? 'selected' : ''; ?>>Finalizate</option>
                        <option value="S" <?php echo $filters['status'] === 'S' ? 'selected' : ''; ?>>Deschise</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Cautare</label>
                    <input class="form-control" name="q" value="<?php echo h($filters['q']); ?>" placeholder="Nr. bon sau produs">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-dark w-100" type="submit">Filtreaza</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($selectedBon > 0): ?>
        <div class="card mb-3">
            <div class="card-body">
                <?php if ($selectedSale): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="m-0">Detalii bon <?php echo (int)$selectedSale['nrbon']; ?></h5>
                        <a class="btn btn-sm btn-outline-secondary" href="vanzari_pos.php?<?php echo h(http_build_query($baseQuery)); ?>">Inchide detalii</a>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-2"><span class="badge badge-soft">Status <?php echo h(status_label($selectedSale['status'])); ?></span></div>
                        <div class="col-md-2">Data <?php echo h($selectedSale['data_bon']); ?></div>
                        <div class="col-md-2">Ora <?php echo h($selectedSale['ora_bon']); ?></div>
                        <div class="col-md-2">Locatie <?php echo h($selectedSale['locatie']); ?></div>
                        <div class="col-md-2">Tura <?php echo h($selectedSale['cod_inchidere']); ?></div>
                        <div class="col-md-2">Raport Z <?php echo h($selectedSale['nr_raport_z']); ?></div>
                    </div>
                    <div class="table-wrap">
                        <table class="table table-sm table-striped table-bordered align-middle details-table">
                            <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Cod</th>
                                <th>Produs</th>
                                <th>Cantitate</th>
                                <th>UM</th>
                                <th>Pret</th>
                                <th>Valoare fara TVA</th>
                                <th>TVA</th>
                                <th>Total TVA</th>
                                <th>Discount</th>
                                <th>Pachet</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($saleDetails as $row): ?>
                                <tr>
                                    <td><?php echo (int)$row['id_vanz']; ?></td>
                                    <td><?php echo h($row['cod_p']); ?></td>
                                    <td><?php echo h($row['nume_produs']); ?></td>
                                    <td><?php echo h($row['cantitate']); ?></td>
                                    <td><?php echo h($row['um'] ?? ''); ?></td>
                                    <td><?php echo money_ro($row['pret_vanzare']); ?></td>
                                    <td><?php echo money_ro($row['valoare_vanzare']); ?></td>
                                    <td><?php echo h($row['cota_tva']); ?>%</td>
                                    <td><?php echo money_ro($row['valoare_vanzare_cu_tva']); ?></td>
                                    <td><?php echo money_ro($row['discount']); ?></td>
                                    <td><?php echo h($row['pachet']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning mb-0">Bonul selectat nu a fost gasit.</div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body table-wrap">
            <table class="table table-sm table-striped table-bordered align-middle">
                <thead class="table-dark">
                <tr>
                    <th>Actiuni</th>
                    <th>Bon</th>
                    <th>Data</th>
                    <th>Ora</th>
                    <th>Status</th>
                    <th>Locatie</th>
                    <th>Operator</th>
                    <th>Total</th>
                    <th>Discount</th>
                    <th>TVA</th>
                    <th>Numerar</th>
                    <th>Card</th>
                    <th>Tichete</th>
                    <th>Protocol</th>
                    <th>Online</th>
                    <th>Linii</th>
                    <th>Cantitate</th>
                    <th>Tura</th>
                    <th>Raport Z</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($sales as $row): ?>
                    <?php $detailQuery = array_merge($baseQuery, ['bon' => (int)$row['nrbon']]); ?>
                    <tr>
                        <td><a class="btn btn-sm btn-primary" href="vanzari_pos.php?<?php echo h(http_build_query($detailQuery)); ?>">Detalii</a></td>
                        <td><?php echo (int)$row['nrbon']; ?></td>
                        <td><?php echo h($row['data_bon']); ?></td>
                        <td><?php echo h($row['ora_bon']); ?></td>
                        <td><?php echo h(status_label($row['status'])); ?></td>
                        <td><?php echo h($row['locatie']); ?></td>
                        <td><?php echo h($row['operator']); ?></td>
                        <td><?php echo money_ro($row['valoare_vanzare_cu_tva']); ?></td>
                        <td><?php echo money_ro($row['discount']); ?></td>
                        <td><?php echo money_ro($row['tva_colectata']); ?></td>
                        <td><?php echo money_ro($row['numerar']); ?></td>
                        <td><?php echo money_ro($row['card']); ?></td>
                        <td><?php echo money_ro($row['tichete']); ?></td>
                        <td><?php echo money_ro($row['protocol']); ?></td>
                        <td><?php echo money_ro($row['glovo']); ?></td>
                        <td><?php echo (int)$row['nr_linii']; ?></td>
                        <td><?php echo h($row['total_cantitate']); ?></td>
                        <td><?php echo h($row['cod_inchidere']); ?></td>
                        <td><?php echo h($row['nr_raport_z']); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$sales): ?>
                    <tr><td colspan="19" class="text-center text-muted">Nu exista vanzari pentru filtrele selectate.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
