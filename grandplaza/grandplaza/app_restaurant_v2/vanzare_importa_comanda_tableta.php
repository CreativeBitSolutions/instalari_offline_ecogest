<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
date_default_timezone_set('Europe/Bucharest');

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/offline_tablet_sync_lib.php';
require_once __DIR__ . '/det_note_departament_listare_schema.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Conexiunea locală nu este disponibilă.');
}

$operatorId = (int)($_SESSION['admin_id'] ?? 0);
$location = (int)($_SESSION['cod_locatie'] ?? 0);
if ($operatorId <= 0 || $location <= 0) {
    header('Location: logout.php');
    exit;
}

if (!isset($_SESSION['tablet_import_csrf'])) {
    $_SESSION['tablet_import_csrf'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['tablet_import_csrf'];
$syncConfig = restaurant_tablet_sync_config($restaurantConfig);
$message = '';
$error = '';

function oti_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function oti_money($value): string
{
    return number_format((float)$value, 2, ',', '.');
}

function oti_create_note(PDO $pdo, int $tableId, int $operatorId, int $location): int
{
    $stmt = $pdo->prepare("
        INSERT INTO note (operator, locatie, cod_masa, data_bon, ora_bon, status, listat_nota_plata, fiscalizat, tableta)
        VALUES (?, ?, ?, ?, ?, 'S', 0, 0, 1)
    ");
    $stmt->execute([$operatorId, $location, $tableId, date('Y-m-d'), date('H:i:s')]);
    return (int)$pdo->lastInsertId();
}

function oti_open_notes_on_table(PDO $pdo, int $tableId, int $location): array
{
    $stmt = $pdo->prepare("SELECT nrbon, operator FROM note WHERE cod_masa=? AND locatie=? AND status='S' ORDER BY nrbon DESC");
    $stmt->execute([$tableId, $location]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function oti_import_details(PDO $pdo, int $noteId, array $details): void
{
    if (!$details) {
        throw new RuntimeException('Comanda nu conține produse și nu poate fi importată.');
    }
    $insert = $pdo->prepare("
        INSERT INTO det_note (
            nr_bon, cod_p, nume_produs, cantitate, cota_tva, tva_col, pret_vanzare,
            valoare_vanzare, valoare_vanzare_cu_tva, data, ora, cod_meniu,
            observatie_produs, t_list, discount, pachet, preparat, preluat_osp,
            prioritate, departament_listare
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($details as $row) {
        $quantity = (float)($row['cantitate'] ?? 0);
        $vatRate = (float)($row['cota_tva'] ?? 0);
        $valueWithoutVat = (float)($row['valoare_vanzare'] ?? 0);
        $valueWithVat = (float)($row['valoare_vanzare_cu_tva'] ?? 0);
        $unitPriceWithVat = (float)($row['pret_vanzare'] ?? 0);

        if ($quantity > 0 && $valueWithVat > 0) {
            $unitPriceWithVat = $valueWithVat / $quantity;
        } elseif ($unitPriceWithVat <= 0 && $quantity > 0 && $valueWithoutVat > 0) {
            $unitPriceWithVat = ($valueWithoutVat * (1 + $vatRate / 100)) / $quantity;
        }
        if ($valueWithVat <= 0 && $quantity > 0 && $unitPriceWithVat > 0) {
            $valueWithVat = $unitPriceWithVat * $quantity;
        }
        if ($valueWithoutVat <= 0 && $valueWithVat > 0) {
            $coefficient = 1 + $vatRate / 100;
            $valueWithoutVat = $coefficient > 0 ? $valueWithVat / $coefficient : $valueWithVat;
        }

        $insert->execute([
            $noteId,
            (int)($row['cod_p'] ?? 0),
            (string)($row['nume_produs'] ?? ''),
            $quantity,
            $vatRate,
            $valueWithVat - $valueWithoutVat,
            $unitPriceWithVat,
            $valueWithoutVat,
            $valueWithVat,
            (string)($row['data'] ?? date('Y-m-d')),
            (string)($row['ora'] ?? date('H:i:s')),
            (int)($row['cod_meniu'] ?? 0),
            mb_substr((string)($row['observatie_produs'] ?? ''), 0, 100),
            (int)($row['t_list'] ?? 0),
            (float)($row['discount'] ?? 0),
            (int)($row['pachet'] ?? 0),
            (int)($row['preparat'] ?? 0),
            (int)($row['preluat_osp'] ?? 0),
            (int)($row['prioritate'] ?? 0),
            trim((string)($row['departament_listare'] ?? '')) ?: null,
        ]);
    }
}

function oti_recalculate_note(PDO $pdo, int $noteId): void
{
    $stmt = $pdo->prepare("
        UPDATE note SET
            valoare_vanzare_cu_tva=(SELECT COALESCE(SUM(valoare_vanzare_cu_tva),0) FROM det_note WHERE nr_bon=?),
            tva_colectata=(SELECT COALESCE(SUM(tva_col),0) FROM det_note WHERE nr_bon=?),
            discount=(SELECT COALESCE(SUM(discount),0) FROM det_note WHERE nr_bon=?)
        WHERE nrbon=?
    ");
    $stmt->execute([$noteId, $noteId, $noteId, $noteId]);
}

function oti_tables(PDO $pdo, int $location, int $operatorId): array
{
    $stmt = $pdo->prepare("
        SELECT m.cod_masa, m.nume_masa, m.stare,
               (SELECT MAX(n.nrbon) FROM note n WHERE n.cod_masa=m.cod_masa AND n.locatie=? AND n.operator=? AND n.status='S') AS own_note
        FROM mese m
        WHERE m.cod_locatie=?
          AND (m.stare=0 OR EXISTS(SELECT 1 FROM note n2 WHERE n2.cod_masa=m.cod_masa AND n2.locatie=? AND n2.operator=? AND n2.status='S'))
        ORDER BY m.cod_masa
    ");
    $stmt->execute([$location, $operatorId, $location, $location, $operatorId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $error = 'Cererea a expirat. Reîncarcă pagina.';
    } elseif ((string)($_POST['action'] ?? '') === 'sync_now') {
        try {
            $result = restaurant_tablet_sync_run($pdo, $restaurantConfig, true);
            $received = (int)($result['pull']['received'] ?? 0);
            $message = 'Sincronizare finalizată. Comenzi online găsite: ' . $received . '.';
            if (!empty($result['errors'])) {
                $error = implode(' ', (array)$result['errors']);
            }
        } catch (Throwable $e) {
            $error = 'Sincronizarea nu a reușit: ' . $e->getMessage();
        }
    } elseif ((string)($_POST['action'] ?? '') === 'import_tablet_order') {
        $sourceOrderId = (int)($_POST['nrbon_src'] ?? 0);
        $tableId = (int)($_POST['cod_masa_target'] ?? 0);
        if ($sourceOrderId <= 0 || $tableId <= 0) {
            $error = 'Comanda sau masa selectată este invalidă.';
        } else {
            try {
                $pdo->beginTransaction();
                $orderStmt = $pdo->prepare("SELECT * FROM com_tableta WHERE nrbon=? AND stare='TRIMISA' LIMIT 1");
                $orderStmt->execute([$sourceOrderId]);
                $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
                if (!$order) {
                    throw new RuntimeException('Comanda a fost deja importată sau nu mai este disponibilă.');
                }
                if ((int)($order['owner_operator_id'] ?? 0) !== $operatorId || (int)($order['locatie'] ?? 0) !== $location) {
                    throw new RuntimeException('Comanda aparține altui ospătar sau altei locații.');
                }

                $noteId = 0;
                $openNotes = oti_open_notes_on_table($pdo, $tableId, $location);
                if ($openNotes) {
                    foreach ($openNotes as $openNote) {
                        if ((int)$openNote['operator'] === $operatorId) {
                            $noteId = (int)$openNote['nrbon'];
                            break;
                        }
                    }
                    if ($noteId <= 0) {
                        throw new RuntimeException('Masa este deschisă la alt ospătar.');
                    }
                } else {
                    $noteId = oti_create_note($pdo, $tableId, $operatorId, $location);
                }

                $detailStmt = $pdo->prepare('SELECT * FROM det_com_tableta WHERE nr_bon=? ORDER BY id_vanz');
                $detailStmt->execute([$sourceOrderId]);
                oti_import_details($pdo, $noteId, $detailStmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
                agecs_snapshot_det_note_departamente($pdo, $noteId);
                oti_recalculate_note($pdo, $noteId);
                $pdo->prepare('UPDATE mese SET stare=1 WHERE cod_masa=? AND cod_locatie=?')->execute([$tableId, $location]);
                restaurantTouchUltimBonConectat($pdo, $location, $noteId, date('Y-m-d H:i:s'));

                $mark = $pdo->prepare("
                    UPDATE com_tableta SET
                        stare='IMPORTATA', status='I', imported_note_nrbon=?, imported_at=?,
                        online_ack_status='pending', online_ack_error=''
                    WHERE nrbon=? AND stare='TRIMISA'
                ");
                $mark->execute([$noteId, date('Y-m-d H:i:s'), $sourceOrderId]);
                if ($mark->rowCount() !== 1) {
                    throw new RuntimeException('Comanda nu mai este disponibilă pentru import.');
                }

                $_SESSION['nr_bon'] = $noteId;
                $_SESSION['masa_curenta'] = $tableId;
                $_SESSION['trimis_comanda'] = 0;
                $pdo->commit();

                try {
                    restaurant_tablet_sync_ack_pending($pdo, $syncConfig, [$sourceOrderId]);
                } catch (Throwable $ackError) {
                    error_log('[tablet-import-ack] ' . $ackError->getMessage());
                }
                header('Location: vanzare_restaurant.php');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Importul nu a reușit: ' . $e->getMessage();
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $result = restaurant_tablet_sync_run($pdo, $restaurantConfig, true);
        if (!empty($result['errors'])) {
            $error = implode(' ', (array)$result['errors']);
        }
    } catch (Throwable $e) {
        $error = 'Comenzile locale pot fi importate, dar verificarea online nu a reușit: ' . $e->getMessage();
    }
}

$orderStmt = $pdo->prepare("
    SELECT * FROM com_tableta
    WHERE stare='TRIMISA' AND owner_operator_id=? AND locatie=?
    ORDER BY nrbon ASC
");
$orderStmt->execute([$operatorId, $location]);
$orders = $orderStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
$details = [];
if ($orders) {
    $ids = array_map(static fn(array $row): int => (int)$row['nrbon'], $orders);
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $detailStmt = $pdo->prepare("SELECT * FROM det_com_tableta WHERE nr_bon IN ({$ph}) ORDER BY nr_bon, id_vanz");
    $detailStmt->execute($ids);
    foreach ($detailStmt->fetchAll(PDO::FETCH_ASSOC) as $detail) {
        $details[(int)$detail['nr_bon']][] = $detail;
    }
}
$tables = oti_tables($pdo, $location, $operatorId);
$runtime = $pdo->query('SELECT * FROM offline_tablet_sync_runtime WHERE id=1')->fetch(PDO::FETCH_ASSOC) ?: [];
?>
<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Comenzi de pe tabletă</title>
    <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="vendor/font-awesome/css/font-awesome.min.css">
    <style>
        :root { --ink:#17222b; --paper:#f3efe4; --line:#c9c0ae; --signal:#e05b34; --fresh:#159a9c; --muted:#65717a; }
        body {
            background-color:var(--paper);
            background-image:linear-gradient(rgba(23,34,43,.035) 1px,transparent 1px),linear-gradient(90deg,rgba(23,34,43,.035) 1px,transparent 1px);
            background-size:22px 22px;
            color:var(--ink);
            font-family:"Trebuchet MS",Tahoma,sans-serif;
            font-size:1.05rem;
        }
        .page-head { position:relative; overflow:hidden; background:var(--ink); color:#fff; padding:20px 0; border-bottom:6px solid var(--signal); }
        .page-head::after { content:""; position:absolute; right:-70px; top:-110px; width:310px; height:260px; border:38px solid rgba(255,255,255,.055); transform:rotate(18deg); pointer-events:none; }
        .page-head h1 { font-family:Rockwell,Georgia,serif; letter-spacing:.02em; }
        .page-kicker { color:#8ed6d3; font-size:.72rem; font-weight:800; letter-spacing:.14em; text-transform:uppercase; }
        .order-card { border:1px solid var(--line); border-left:8px solid var(--signal); border-radius:3px; box-shadow:0 8px 22px rgba(23,34,43,.1); animation:orderReveal .28s ease-out both; }
        .order-card:nth-of-type(2) { animation-delay:.05s; }
        .order-card:nth-of-type(3) { animation-delay:.1s; }
        .order-card .card-header { border-bottom:1px dashed var(--line); }
        .sync-meta { font-size:.92rem; color:var(--muted); border-left:5px solid var(--fresh); }
        .product-table td,.product-table th { vertical-align:middle; }
        .product-table thead th { background:#e7e0d1; border-color:var(--line); color:#33414c; font-size:.78rem; letter-spacing:.04em; text-transform:uppercase; }
        .amount { padding:.35rem .65rem; white-space:nowrap; background:var(--ink); color:#fff; font-family:Rockwell,Georgia,serif; font-size:1.15rem; font-weight:700; }
        .btn-info { background:var(--fresh); border-color:var(--fresh); }
        .btn-primary { background:var(--signal); border-color:var(--signal); font-weight:800; letter-spacing:.02em; }
        .btn-primary:hover,.btn-primary:focus { background:#bd4423; border-color:#bd4423; }
        .form-control { min-height:48px; border-color:#9e9584; font-size:1.05rem; }
        .btn-lg { min-height:50px; }
        .btn:focus,.form-control:focus { box-shadow:0 0 0 3px rgba(21,154,156,.28); }
        @keyframes orderReveal { from { opacity:0; transform:translateY(9px); } to { opacity:1; transform:translateY(0); } }
        @media (prefers-reduced-motion:reduce) { .order-card { animation:none; } }
    </style>
</head>
<body>
<header class="page-head">
    <div class="container-fluid d-flex flex-wrap align-items-center justify-content-between">
        <div>
            <div class="page-kicker">Preluare online în POS offline, locația <?= $location ?></div>
            <h1 class="h3 mb-1">Comenzi de pe tabletă</h1>
            <div>Ospătar: <?= oti_h(trim((string)($_SESSION['admin_firstname'] ?? '') . ' ' . (string)($_SESSION['admin_lastname'] ?? ''))) ?></div>
        </div>
        <div class="d-flex mt-2 mt-md-0">
            <form method="post" class="mr-2">
                <input type="hidden" name="csrf" value="<?= oti_h($csrf) ?>">
                <input type="hidden" name="action" value="sync_now">
                <button class="btn btn-info" type="submit"><i class="fa fa-refresh"></i> Verifică online acum</button>
            </form>
            <a class="btn btn-light" href="vanzare_restaurant.php">Înapoi la vânzare</a>
        </div>
    </div>
</header>

<main class="container-fluid py-4">
    <?php if ($message !== ''): ?><div class="alert alert-success"><?= oti_h($message) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-warning"><?= oti_h($error) ?></div><?php endif; ?>

    <div class="card mb-4">
        <div class="card-body py-2 sync-meta">
            Ultima verificare reușită: <strong><?= oti_h($runtime['last_pull_success_at'] ?? 'niciodată') ?></strong>.
            Comenzi primite la ultima verificare: <strong><?= (int)($runtime['last_orders_received'] ?? 0) ?></strong>.
            Comenzi disponibile pentru ospătarul curent: <strong><?= count($orders) ?></strong>.
        </div>
    </div>

    <?php if (!$orders): ?>
        <div class="alert alert-info">Nu există comenzi trimise de pe tabletele asociate acestui ospătar.</div>
    <?php endif; ?>

    <?php foreach ($orders as $order):
        $orderId = (int)$order['nrbon'];
        $orderDetails = $details[$orderId] ?? [];
    ?>
        <section class="card order-card mb-4">
            <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center">
                <div>
                    <strong>Comanda #<?= $orderId ?></strong>
                    <span class="text-muted ml-2"><?= oti_h(($order['data_bon'] ?? '') . ' ' . ($order['ora_bon'] ?? '')) ?></span>
                    <span class="badge badge-secondary ml-2">Masa transmisă: <?= (int)($order['cod_masa'] ?? 0) ?></span>
                </div>
                <div class="amount"><?= oti_money($order['valoare_vanzare_cu_tva'] ?? 0) ?> lei</div>
            </div>
            <div class="card-body">
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered product-table mb-0">
                        <thead class="thead-light"><tr><th>Produs</th><th class="text-center">Cantitate</th><th class="text-right">Preț</th><th>Observație</th></tr></thead>
                        <tbody>
                        <?php foreach ($orderDetails as $detail): ?>
                            <tr>
                                <td><?= oti_h($detail['nume_produs'] ?? '') ?></td>
                                <td class="text-center"><?= oti_h((float)($detail['cantitate'] ?? 0)) ?></td>
                                <td class="text-right"><?= oti_money($detail['pret_vanzare'] ?? 0) ?></td>
                                <td><?= oti_h($detail['observatie_produs'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <form method="post" class="form-row align-items-end">
                    <input type="hidden" name="csrf" value="<?= oti_h($csrf) ?>">
                    <input type="hidden" name="action" value="import_tablet_order">
                    <input type="hidden" name="nrbon_src" value="<?= $orderId ?>">
                    <div class="form-group col-md-8 mb-md-0">
                        <label for="masa-<?= $orderId ?>"><strong>Masa pe care se importă</strong></label>
                        <select class="form-control" id="masa-<?= $orderId ?>" name="cod_masa_target" required>
                            <option value="">Selectează masa</option>
                            <?php foreach ($tables as $table):
                                $tableId = (int)$table['cod_masa'];
                                $label = trim((string)($table['nume_masa'] ?? '')) ?: ('Masa ' . $tableId);
                                if ((int)($table['own_note'] ?? 0) > 0) {
                                    $label .= ' (nota #' . (int)$table['own_note'] . ' deschisă)';
                                }
                                $selected = $tableId === (int)($order['cod_masa'] ?? 0) ? ' selected' : '';
                            ?>
                                <option value="<?= $tableId ?>"<?= $selected ?>><?= oti_h($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-4 mb-0">
                        <button class="btn btn-primary btn-block btn-lg" type="submit"<?= !$orderDetails ? ' disabled' : '' ?>>Importă în notă</button>
                    </div>
                </form>
            </div>
        </section>
    <?php endforeach; ?>
</main>
</body>
</html>
