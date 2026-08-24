<?php
// vanzare_importa_comenzi_online.php â€” import comenzi online locale -> bonul curent din vanzare_magazin.php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/eroare_import_comenzi_online.log');
error_reporting(E_ALL);

date_default_timezone_set('Europe/Bucharest');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/session.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    die('<h1>LipsÄƒ conexiune POS ($pdo).</h1>');
}
if (!isset($_SESSION['cod_locatie'], $_SESSION['admin_id'])) {
    header('Location: logout.php');
    exit;
}

$adm_id = (int)$_SESSION['admin_id'];
$cod_locatie = (int)$_SESSION['cod_locatie'];
$cod_masa = $cod_locatie; // Ã®n modul magazin, cod_masa este identic cu locaÈ›ia
$currentNrBon = (int)($_SESSION['nr_bon'] ?? 0);

// Fallback Ã®n caz cÄƒ session.php nu defineÈ™te denumirile tabelare finale.
$tabel_note = $tabel_final_note ?? 'note';
$tabel_det_note = $tabel_final_det_note ?? 'det_note';

$message = $_SESSION['online_flash_message'] ?? null;
$error = null;
unset($_SESSION['online_flash_message']);

if (empty($_SESSION['csrf_import_comenzi_online'])) {
    $_SESSION['csrf_import_comenzi_online'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_import_comenzi_online'];

function online_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function online_money($value): string
{
    return number_format((float)$value, 2, '.', '');
}

function online_qty($value): string
{
    return rtrim(rtrim(number_format((float)$value, 5, '.', ''), '0'), '.');
}

function online_normalize_date_filter($value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    $dt = DateTime::createFromFormat('Y-m-d', $value);
    if ($dt instanceof DateTime && $dt->format('Y-m-d') === $value) {
        return $value;
    }

    return '';
}

function online_date_plus_days(string $date, int $days): string
{
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dt instanceof DateTime) {
        return $date;
    }

    $dt->modify(($days >= 0 ? '+' : '') . $days . ' day');
    return $dt->format('Y-m-d');
}

function online_date_ro_label(string $date): string
{
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $dt instanceof DateTime ? $dt->format('d.m.Y') : $date;
}

function online_status_badge($status): string
{
    $status = strtolower(trim((string)$status));
    $label = $status !== '' ? $status : 'necunoscut';
    $class = 'secondary';

    if ($status === 'completed') {
        $class = 'success';
    } elseif (in_array($status, ['processing', 'pending', 'on-hold'], true)) {
        $class = 'warning text-dark';
    } elseif (in_array($status, ['cancelled', 'failed', 'refunded'], true)) {
        $class = 'danger';
    } elseif (in_array($status, ['mapare_incompleta', 'eroare_import'], true)) {
        $class = 'danger';
    }

    return '<span class="badge bg-' . $class . '">' . online_h($label) . '</span>';
}

function online_payment_badge($method): string
{
    $method = trim((string)$method);
    if ($method === '') {
        return '<span class="badge bg-secondary">nespecificat</span>';
    }

    $lower = strtolower($method);
    $class = 'info text-dark';

    if (str_contains($lower, 'card') || str_contains($lower, 'online')) {
        $class = 'primary';
    } elseif (str_contains($lower, 'ramburs') || str_contains($lower, 'cash') || str_contains($lower, 'numerar')) {
        $class = 'success';
    } elseif (str_contains($lower, 'transfer') || str_contains($lower, 'virament')) {
        $class = 'warning text-dark';
    }

    return '<span class="badge bg-' . $class . '">' . online_h($method) . '</span>';
}

function online_can_import_order(array $comanda, int $produseCount): array
{
    $status = strtolower(trim((string)($comanda['status'] ?? '')));
    $importata = (int)($comanda['importata_pos'] ?? 0);

    if ($importata === 1) {
        return [false, 'Comanda este deja importatÄƒ Ã®n bonul #' . (int)($comanda['nr_bon_pos'] ?? 0) . '.'];
    }

    if ($produseCount <= 0) {
        return [false, 'Comanda nu are produse Ã®n det_comenzi.'];
    }

    if (in_array($status, ['cancelled', 'failed', 'refunded'], true)) {
        return [false, 'Comanda are status neimportabil: ' . $status . '.'];
    }

    if (in_array($status, ['mapare_incompleta', 'eroare_import'], true)) {
        return [false, 'Comanda are status intern blocat: ' . $status . '.'];
    }

    return [true, ''];
}

function online_fetch_current_note(PDO $pdo, string $tabel_note, int $adm_id, int $cod_locatie): ?array
{
    $stmt = $pdo->prepare("
        SELECT nrbon, data_bon, ora_bon, valoare_vanzare_cu_tva
        FROM {$tabel_note}
        WHERE status = 'S'
          AND operator = :operator
          AND locatie = :locatie
        ORDER BY nrbon DESC
        LIMIT 1
    ");
    $stmt->execute([
        ':operator' => $adm_id,
        ':locatie' => $cod_locatie,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function online_get_or_create_current_note(PDO $pdo, string $tabel_note, int $adm_id, int $cod_locatie, int $cod_masa): int
{
    $current = online_fetch_current_note($pdo, $tabel_note, $adm_id, $cod_locatie);
    if ($current && (int)$current['nrbon'] > 0) {
        $_SESSION['nr_bon'] = (int)$current['nrbon'];
        return (int)$current['nrbon'];
    }

    // AceeaÈ™i logicÄƒ minimÄƒ folositÄƒ Ã®n vanzare_magazin.php.
    $stmt = $pdo->prepare("
        INSERT INTO {$tabel_note}
            (operator, locatie, cod_masa, status, cod_inchidere)
        VALUES
            (:operator, :locatie, :cod_masa, 'S', 0)
    ");
    $stmt->execute([
        ':operator' => $adm_id,
        ':locatie' => $cod_locatie,
        ':cod_masa' => $cod_masa,
    ]);

    $nrBon = (int)$pdo->lastInsertId();
    $_SESSION['nr_bon'] = $nrBon;
    return $nrBon;
}

function online_recalc_note(PDO $pdo, string $tabel_note, string $tabel_det_note, int $nrBon): void
{
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(valoare_vanzare_cu_tva - discount), 0) AS total_cu_tva,
            COALESCE(SUM(tva_col), 0) AS total_tva
        FROM {$tabel_det_note}
        WHERE nr_bon = :nr_bon
    ");
    $stmt->execute([':nr_bon' => $nrBon]);
    $totals = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total_cu_tva' => 0, 'total_tva' => 0];

    $stmtUpdate = $pdo->prepare("
        UPDATE {$tabel_note}
        SET valoare_vanzare_cu_tva = :total_cu_tva,
            tva_colectata = :total_tva
        WHERE nrbon = :nr_bon
    ");
    $stmtUpdate->execute([
        ':total_cu_tva' => round((float)$totals['total_cu_tva'], 2),
        ':total_tva' => round((float)$totals['total_tva'], 2),
        ':nr_bon' => $nrBon,
    ]);
}

function online_import_order_into_current_note(
    PDO $pdo,
    string $tabel_note,
    string $tabel_det_note,
    int $idComanda,
    int $adm_id,
    int $cod_locatie,
    int $cod_masa
): int {
    $pdo->beginTransaction();

    try {
        $stmtComanda = $pdo->prepare("
            SELECT *
            FROM comenzi
            WHERE id = :id_comanda
        ");
        $stmtComanda->execute([':id_comanda' => $idComanda]);
        $comanda = $stmtComanda->fetch(PDO::FETCH_ASSOC);

        if (!$comanda) {
            throw new RuntimeException('Comanda nu existÄƒ.');
        }

        $stmtProduse = $pdo->prepare("
            SELECT *
            FROM det_comenzi
            WHERE id_comanda = :id_comanda
            ORDER BY id ASC
        ");
        $stmtProduse->execute([':id_comanda' => $idComanda]);
        $produse = $stmtProduse->fetchAll(PDO::FETCH_ASSOC);

        [$canImport, $reason] = online_can_import_order($comanda, count($produse));
        if (!$canImport) {
            throw new RuntimeException($reason);
        }

        $nrBonTarget = online_get_or_create_current_note($pdo, $tabel_note, $adm_id, $cod_locatie, $cod_masa);

        $stmtInsert = $pdo->prepare("
            INSERT INTO {$tabel_det_note}
                (
                    nr_bon,
                    cod_p,
                    nume_produs,
                    cantitate,
                    cota_tva,
                    tva_col,
                    pret_vanzare,
                    valoare_vanzare,
                    valoare_vanzare_cu_tva,
                    discount,
                    pachet,
                    preparat,
                    t_list,
                    data,
                    ora,
                    cod_meniu,
                    observatie_produs,
                    preluat_osp,
                    prioritate
                )
            VALUES
                (
                    :nr_bon,
                    :cod_p,
                    :nume_produs,
                    :cantitate,
                    :cota_tva,
                    :tva_col,
                    :pret_vanzare,
                    :valoare_vanzare,
                    :valoare_vanzare_cu_tva,
                    :discount,
                    0,
                    0,
                    0,
                    date('now','localtime'),
                    time('now','localtime'),
                    0,
                    :observatie_produs,
                    0,
                    0
                )
        ");

        foreach ($produse as $produs) {
            $codProdusRaw = trim((string)($produs['cod_produs'] ?? ''));
            if ($codProdusRaw === '' || !ctype_digit($codProdusRaw)) {
                throw new RuntimeException('Cod produs invalid pentru linia: ' . (string)($produs['nume_produs'] ?? 'necunoscut'));
            }

            $cantitate = (float)($produs['cantitate'] ?? 0);
            if ($cantitate <= 0) {
                throw new RuntimeException('Cantitate invalidÄƒ pentru produsul: ' . (string)($produs['nume_produs'] ?? 'necunoscut'));
            }

            $stmtInsert->execute([
                ':nr_bon' => $nrBonTarget,
                ':cod_p' => (int)$codProdusRaw,
                ':nume_produs' => (string)($produs['nume_produs'] ?? ''),
                ':cantitate' => $cantitate,
                ':cota_tva' => (int)round((float)($produs['cota_tva'] ?? 0)),
                ':tva_col' => round((float)($produs['tva_col'] ?? 0), 5),
                ':pret_vanzare' => round((float)($produs['pret_unitar'] ?? 0), 5),
                ':valoare_vanzare' => round((float)($produs['valoare'] ?? 0), 5),
                ':valoare_vanzare_cu_tva' => round((float)($produs['valoare_cu_tva'] ?? 0), 5),
                ':discount' => round((float)($produs['discount'] ?? 0), 5),
                ':observatie_produs' => (string)($produs['observatie_produs'] ?? ''),
            ]);
        }

        online_recalc_note($pdo, $tabel_note, $tabel_det_note, $nrBonTarget);

        $stmtUpdate = $pdo->prepare("
            UPDATE comenzi
            SET importata_pos = 1,
                nr_bon_pos = :nr_bon,
                operator_import = :operator_import,
                locatie_import = :locatie_import,
                data_import_pos = datetime('now','localtime')
            WHERE id = :id_comanda
        ");
        $stmtUpdate->execute([
            ':nr_bon' => $nrBonTarget,
            ':operator_import' => $adm_id,
            ':locatie_import' => $cod_locatie,
            ':id_comanda' => $idComanda,
        ]);

        $_SESSION['nr_bon'] = $nrBonTarget;
        $_SESSION['trimis_comanda'] = 0;

        $pdo->commit();
        return $nrBonTarget;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'import_online_order') {
    $idComanda = (int)($_POST['id_comanda'] ?? 0);

    try {
        if (!hash_equals($csrfToken, (string)($_POST['csrf_token'] ?? ''))) {
            throw new RuntimeException('Sesiune invalidÄƒ. ReÃ®ncarcÄƒ pagina È™i Ã®ncearcÄƒ din nou.');
        }
        if ($idComanda <= 0) {
            throw new RuntimeException('ID comandÄƒ invalid.');
        }

        $nrBonTarget = online_import_order_into_current_note(
            $pdo,
            $tabel_note,
            $tabel_det_note,
            $idComanda,
            $adm_id,
            $cod_locatie,
            $cod_masa
        );

        $_SESSION['online_flash_message'] = 'Comanda online #' . $idComanda . ' a fost importatÄƒ Ã®n bonul #' . $nrBonTarget . '.';
        header('Location: vanzare_magazin.php');
        exit;
    } catch (Throwable $e) {
        $error = 'Eroare import comandÄƒ online: ' . $e->getMessage();
    }
}

$currentNote = null;
try {
    $currentNote = online_fetch_current_note($pdo, $tabel_note, $adm_id, $cod_locatie);
    if (!$currentNote) {
        $currentNrBon = online_get_or_create_current_note($pdo, $tabel_note, $adm_id, $cod_locatie, $cod_masa);
        $currentNote = online_fetch_current_note($pdo, $tabel_note, $adm_id, $cod_locatie);
    } else {
        $currentNrBon = (int)$currentNote['nrbon'];
        $_SESSION['nr_bon'] = $currentNrBon;
    }
} catch (Throwable $e) {
    $error = ($error ? $error . ' | ' : '') . 'Nu s-a putut identifica bonul curent: ' . $e->getMessage();
}

$rawDateFromInput = trim((string)($_GET['date_from'] ?? ''));
$rawDateToInput = trim((string)($_GET['date_to'] ?? ''));
$dateFromInput = online_normalize_date_filter($rawDateFromInput);
$dateToInput = online_normalize_date_filter($rawDateToInput);
$dateFilterWarning = '';

if ($rawDateFromInput !== '' && $dateFromInput === '') {
    $dateFilterWarning = 'Data de Ã®nceput nu este validÄƒ È™i a fost ignoratÄƒ.';
}
if ($rawDateToInput !== '' && $dateToInput === '') {
    $dateFilterWarning = trim($dateFilterWarning . ' Data de final nu este validÄƒ È™i a fost ignoratÄƒ.');
}
if ($dateFromInput !== '' && $dateToInput !== '' && strtotime($dateFromInput) > strtotime($dateToInput)) {
    [$dateFromInput, $dateToInput] = [$dateToInput, $dateFromInput];
    $dateFilterWarning = trim($dateFilterWarning . ' Intervalul de datÄƒ a fost inversat automat, deoarece data de Ã®nceput era mai mare decÃ¢t data de final.');
}

$filters = [
    'status' => trim((string)($_GET['status'] ?? '')),
    'search' => trim((string)($_GET['search'] ?? '')),
    'date_from' => $dateFromInput,
    'date_to' => $dateToInput,
];

$activeDateFilter = $filters['date_from'] !== '' || $filters['date_to'] !== '';
if ($filters['date_from'] !== '' && $filters['date_to'] !== '') {
    $dateIntervalLabel = online_date_ro_label($filters['date_from']) . ' - ' . online_date_ro_label($filters['date_to']);
    $dateModeLabel = 'Interval selectat';
} elseif ($filters['date_from'] !== '') {
    $dateIntervalLabel = 'De la ' . online_date_ro_label($filters['date_from']) . ' pÃ¢nÄƒ Ã®n prezent';
    $dateModeLabel = 'De la data selectatÄƒ';
} elseif ($filters['date_to'] !== '') {
    $dateIntervalLabel = 'PÃ¢nÄƒ la ' . online_date_ro_label($filters['date_to']);
    $dateModeLabel = 'PÃ¢nÄƒ la data selectatÄƒ';
} else {
    $dateIntervalLabel = 'Toate comenzile disponibile Ã®n limita ultimelor 300 de Ã®nregistrÄƒri';
    $dateModeLabel = 'FÄƒrÄƒ filtru de datÄƒ';
}

$orders = [];
$pendingOrders = [];
$importedOrders = [];
$detailsByOrder = [];

try {
    $where = [];
    $params = [];

    if ($filters['status'] !== '') {
        $where[] = 'c.status = :status';
        $params[':status'] = $filters['status'];
    }

    if ($filters['date_from'] !== '') {
        $where[] = 'c.data >= :date_from';
        $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
    }

    if ($filters['date_to'] !== '') {
        $where[] = 'c.data < :date_to_next';
        $params[':date_to_next'] = online_date_plus_days($filters['date_to'], 1) . ' 00:00:00';
    }

    if ($filters['search'] !== '') {
        $where[] = '(
            CAST(c.id AS CHAR) LIKE :search
            OR c.nume_client LIKE :search
            OR c.utilizator LIKE :search
            OR c.telefon_client LIKE :search
            OR c.metoda_plata LIKE :search
            OR c.observatii LIKE :search
        )';
        $params[':search'] = '%' . $filters['search'] . '%';
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $stmtOrders = $pdo->prepare("
        SELECT
            c.*,
            COUNT(dc.id) AS nr_produse
        FROM comenzi c
        LEFT JOIN det_comenzi dc ON dc.id_comanda = c.id
        {$whereSql}
        GROUP BY c.id
        ORDER BY c.importata_pos ASC, c.data DESC, c.id DESC
        LIMIT 300
    ");
    $stmtOrders->execute($params);
    $orders = $stmtOrders->fetchAll(PDO::FETCH_ASSOC);

    $ids = array_map(static function ($row) {
        return (int)$row['id'];
    }, $orders);

    if ($ids) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmtDetails = $pdo->prepare("
            SELECT *
            FROM det_comenzi
            WHERE id_comanda IN ({$placeholders})
            ORDER BY id_comanda DESC, id ASC
        ");
        $stmtDetails->execute($ids);
        while ($line = $stmtDetails->fetch(PDO::FETCH_ASSOC)) {
            $detailsByOrder[(int)$line['id_comanda']][] = $line;
        }
    }

    foreach ($orders as $order) {
        if ((int)$order['importata_pos'] === 1) {
            $importedOrders[] = $order;
        } else {
            $pendingOrders[] = $order;
        }
    }
} catch (Throwable $e) {
    $error = ($error ? $error . ' | ' : '') . 'Nu s-au putut Ã®ncÄƒrca comenzile online: ' . $e->getMessage();
}

$statusOptions = [];
try {
    $stmtStatus = $pdo->query("SELECT DISTINCT status FROM comenzi WHERE status IS NOT NULL AND status <> '' ORDER BY status ASC");
    $statusOptions = $stmtStatus->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (Throwable $e) {
    $statusOptions = [];
}

function online_render_orders_table(array $orders, array $detailsByOrder, string $csrfToken, bool $showImportedInfo = false): void
{
    ?>
    <div class="table-responsive">
      <table class="table table-hover table-bordered align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th class="text-center">ComandÄƒ</th>
            <th>Data comandÄƒ</th>
            <th>Client</th>
            <th>Telefon</th>
            <th>Status</th>
            <th>PlatÄƒ</th>
            <th class="text-end">Total</th>
            <th class="text-center">Produse</th>
            <th>AcÈ›iuni</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$orders): ?>
          <tr><td colspan="9" class="text-center text-muted py-4">Nu existÄƒ comenzi Ã®n aceastÄƒ secÈ›iune.</td></tr>
        <?php else: foreach ($orders as $order): ?>
          <?php
            $oid = (int)$order['id'];
            $products = $detailsByOrder[$oid] ?? [];
            $productsCount = (int)($order['nr_produse'] ?? count($products));
            [$canImport, $blockReason] = online_can_import_order($order, $productsCount);
            $warningStatus = in_array(strtolower((string)$order['status']), ['processing', 'pending', 'on-hold'], true);
          ?>
          <tr class="cursor-pointer" data-online-id="<?= $oid ?>">
            <td class="text-center nowrap fw-semibold">
              #<?= online_h($oid) ?>
              <?php if ((int)$order['importata_pos'] === 1): ?>
                <div class="small-muted">bon #<?= online_h($order['nr_bon_pos']) ?></div>
              <?php endif; ?>
            </td>
            <td class="nowrap"><?= online_h($order['data'] ?? '') ?></td>
            <td><?= online_h(($order['nume_client'] ?? '') ?: ($order['utilizator'] ?? '-') ?: '-') ?></td>
            <td class="nowrap"><?= online_h(($order['telefon_client'] ?? '') ?: '-') ?></td>
            <td>
              <?= online_status_badge($order['status'] ?? '') ?>
              <?php if ((int)$order['importata_pos'] === 1): ?>
                <span class="badge bg-info text-dark">importatÄƒ</span>
              <?php elseif ($warningStatus): ?>
                <span class="badge bg-warning text-dark">atenÈ›ie</span>
              <?php endif; ?>
            </td>
            <td><?= online_payment_badge($order['metoda_plata'] ?? '') ?></td>
            <td class="text-end nowrap fw-semibold"><?= online_money($order['valoare_cu_tva'] ?? 0) ?> lei</td>
            <td class="text-center nowrap"><?= online_h($productsCount) ?></td>
            <td class="actions-cell">
              <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-sm btn-outline-primary js-open-online-order" data-online-id="<?= $oid ?>">
                  Vezi detalii
                </button>
                <?php if ($canImport): ?>
                  <button type="button" class="btn btn-sm btn-success js-open-online-order" data-online-id="<?= $oid ?>">
                    ImportÄƒ
                  </button>
                <?php else: ?>
                  <button type="button" class="btn btn-sm btn-secondary" disabled title="<?= online_h($blockReason) ?>">
                    Import blocat
                  </button>
                <?php endif; ?>
              </div>
              <?php if (!$canImport && $blockReason): ?>
                <div class="small-muted mt-1"><?= online_h($blockReason) ?></div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Import comenzi online</title>
  <link href="vendor/offline/bootstrap5/bootstrap.min.css" rel="stylesheet">
  <style>
    body { font-size: 1.05rem; background:#f8f9fa; }
    .page-header { background:linear-gradient(90deg,#0d6efd,#20c997); color:#fff; padding:20px; }
    .cursor-pointer { cursor:pointer; }
    .nowrap { white-space:nowrap; }
    .toolbar-card { border:0; box-shadow:0 2px 10px rgba(0,0,0,.07); }
    .modal-products-scroll { max-height:min(52vh, 460px); overflow:auto; scrollbar-width:auto; }
    .online-scroll-controls {
      position: sticky;
      top: 0;
      z-index: 5;
      background: #fff;
      padding: .35rem .5rem;
      border: 1px solid #dee2e6;
      border-bottom: 0;
      border-radius: .375rem .375rem 0 0;
    }
    .online-scroll-controls .btn { min-width: 120px; }
    .small-muted { color:#6c757d; font-size:.92rem; }
    .pill { display:inline-block; padding:.2rem .55rem; border-radius:999px; background:#eef3ff; }
    .online-modal-template { display:none !important; }
    .section-title { font-size:1.15rem; font-weight:700; margin:0; }
    .table-card { border:0; box-shadow:0 3px 12px rgba(0,0,0,.08); overflow:hidden; }
    .table-card .card-header { padding:.9rem 1rem; }
    .table-card .table th { vertical-align:middle; }
    .table-card .table td { vertical-align:middle; }
    .actions-cell { min-width:230px; }
    .filter-panel-header {
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:1rem;
      padding:1rem 1.15rem;
      background:linear-gradient(90deg, rgba(13,110,253,.10), rgba(32,201,151,.10));
      border-bottom:1px solid rgba(13,110,253,.10);
    }
    .filter-title {
      font-size:1.08rem;
      font-weight:800;
      color:#1f2937;
      margin:0;
      letter-spacing:-.01em;
    }
    .filter-subtitle {
      margin:.2rem 0 0;
      color:#6c757d;
      font-size:.92rem;
    }
    .filter-window-badge {
      display:inline-flex;
      align-items:center;
      gap:.35rem;
      padding:.42rem .7rem;
      border-radius:999px;
      background:#fff;
      color:#0d6efd;
      font-weight:700;
      font-size:.88rem;
      border:1px solid rgba(13,110,253,.16);
      box-shadow:0 1px 4px rgba(0,0,0,.04);
      white-space:nowrap;
    }
    .filter-panel-body { padding:1.15rem; }
    .filter-panel-body .form-label {
      margin-bottom:.35rem;
      color:#495057;
      font-size:.82rem;
      font-weight:700;
      text-transform:uppercase;
      letter-spacing:.035em;
    }
    .filter-panel-body .form-control,
    .filter-panel-body .form-select {
      min-height:44px;
      border-radius:.75rem;
      border-color:#dbe3ef;
      background:#fbfcff;
      box-shadow:none;
    }
    .filter-panel-body .form-control:focus,
    .filter-panel-body .form-select:focus {
      border-color:#86b7fe;
      background:#fff;
      box-shadow:0 0 0 .2rem rgba(13,110,253,.12);
    }
    .filter-actions {
      display:grid;
      grid-template-columns:1fr;
      gap:.55rem;
    }
    .filter-actions .btn {
      min-height:44px;
      border-radius:.75rem;
      font-weight:700;
    }
    .filter-actions .btn-primary {
      box-shadow:0 6px 14px rgba(13,110,253,.18);
    }
    .filter-help {
      margin-top:.85rem;
      padding:.7rem .85rem;
      border-radius:.85rem;
      background:#f8fafc;
      border:1px dashed #cfd8e3;
      color:#6c757d;
      font-size:.9rem;
    }
    .interval-card {
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:1rem;
      padding:.85rem 1rem;
      border-radius:1rem;
      background:#eef7ff;
      border:1px solid rgba(13,110,253,.12);
      color:#24405f;
    }
    .interval-card .interval-title {
      font-weight:800;
      color:#0d6efd;
    }
    .interval-card .interval-text {
      font-weight:700;
      color:#1f2937;
    }
    .interval-card .interval-note {
      display:block;
      margin-top:.12rem;
      color:#6c757d;
      font-size:.88rem;
    }
    @media (max-width: 768px) {
      body { font-size:1rem; }
      .actions-cell { min-width:260px; }
      .filter-panel-header,
      .interval-card { flex-direction:column; align-items:flex-start; }
      .filter-window-badge { white-space:normal; }
    }
  </style>
</head>
<body>
  <div class="page-header mb-4">
    <div class="container-fluid">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
          <h1 class="h3 mb-1">Import comenzi online</h1>
          <div>
            Comenzi salvate local Ã®n <strong>comenzi</strong> È™i <strong>det_comenzi</strong>, importate Ã®n bonul curent din magazin.
          </div>
        </div>
        <div class="text-end">
          <div class="mb-2">
            <span class="pill bg-white text-dark">Bon curent: #<?= online_h($currentNrBon) ?></span>
            <span class="pill bg-white text-dark">LocaÈ›ie: <?= online_h($cod_locatie) ?></span>
            <span class="pill bg-white text-dark">Operator: <?= online_h($adm_id) ?></span>
          </div>
          <a href="vanzare_magazin.php" class="btn btn-light btn-sm">â† ÃŽnapoi la vÃ¢nzare</a>
        </div>
      </div>
    </div>
  </div>

  <div class="container-fluid pb-4">
    <?php if ($message): ?>
      <div class="alert alert-success alert-dismissible fade show">
        <?= online_h($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="ÃŽnchide"></button>
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert-danger alert-dismissible fade show">
        <?= online_h($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="ÃŽnchide"></button>
      </div>
    <?php endif; ?>

    <?php if ($dateFilterWarning !== ''): ?>
      <div class="alert alert-warning alert-dismissible fade show">
        <?= online_h($dateFilterWarning) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="ÃŽnchide"></button>
      </div>
    <?php endif; ?>

    <?php if ($currentNote): ?>
      <div class="alert alert-light border d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
          <strong>Nr. bon Ã®n care se importÄƒ:</strong>
          #<?= online_h($currentNote['nrbon']) ?>,
          deschis la <?= online_h(($currentNote['data_bon'] ?? '') . ' ' . ($currentNote['ora_bon'] ?? '')) ?>,
          total curent <?= online_money($currentNote['valoare_vanzare_cu_tva'] ?? 0) ?> lei.
        </div>
        <div class="small-muted">Operatorul va alege metoda de platÄƒ la finalizarea bonului.</div>
      </div>
    <?php endif; ?>

    <div class="interval-card mb-3">
      <div>
        <span class="interval-title">Interval afiÈ™at</span>
        <span class="text-muted">(<?= online_h($dateModeLabel) ?>)</span>:
        <span class="interval-text"><?= online_h($dateIntervalLabel) ?></span>
        <span class="interval-note">CompleteazÄƒ un interval pentru a vedea comenzi mai vechi sau pentru a restrÃ¢nge lista afiÈ™atÄƒ.</span>
      </div>
    </div>

    <div class="card toolbar-card filters-card mb-4">
      <div class="filter-panel-header">
        <div>
          <h2 class="filter-title">Filtre comenzi online</h2>
          <p class="filter-subtitle">SelecteazÄƒ perioada, statusul sau cautÄƒ rapid dupÄƒ comandÄƒ, client, telefon, platÄƒ ori observaÈ›ii.</p>
        </div>
        <span class="filter-window-badge">ðŸ“… <?= online_h($dateModeLabel) ?></span>
      </div>
      <div class="filter-panel-body">
        <form method="get" class="row g-3 align-items-end">
          <div class="col-12 col-md-2">
            <label class="form-label" for="date_from">De la data</label>
            <input type="date" name="date_from" id="date_from" class="form-control" value="<?= online_h($filters['date_from']) ?>">
          </div>
          <div class="col-12 col-md-2">
            <label class="form-label" for="date_to">PÃ¢nÄƒ la data</label>
            <input type="date" name="date_to" id="date_to" class="form-control" value="<?= online_h($filters['date_to']) ?>">
          </div>
          <div class="col-12 col-md-3 col-xl-2">
            <label class="form-label" for="status">Status</label>
            <select name="status" id="status" class="form-select">
              <option value="">Toate statusurile</option>
              <?php foreach ($statusOptions as $st): ?>
                <option value="<?= online_h($st) ?>" <?= $filters['status'] === $st ? 'selected' : '' ?>><?= online_h($st) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 col-md-5 col-xl-4">
            <label class="form-label" for="search">CautÄƒ</label>
            <input type="text" name="search" id="search" class="form-control" value="<?= online_h($filters['search']) ?>" placeholder="ComandÄƒ, client, telefon, platÄƒ, observaÈ›ii">
          </div>
          <div class="col-12 col-md-12 col-xl-2">
            <div class="filter-actions">
              <button type="submit" class="btn btn-primary">ðŸ”Ž FiltreazÄƒ</button>
              <a href="vanzare_importa_comenzi_online.php" class="btn btn-outline-secondary">ReseteazÄƒ</a>
            </div>
          </div>
        </form>
        <div class="filter-help">
          Se afiÈ™eazÄƒ maximum 300 comenzi, ordonate dupÄƒ import È™i datÄƒ. DacÄƒ laÈ™i datele necompletate, lista nu este restrÃ¢nsÄƒ dupÄƒ perioadÄƒ.
        </div>
      </div>
    </div>

    <div class="card table-card mb-4">
      <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h2 class="section-title">Comenzi neimportate</h2>
        <span class="badge bg-primary"><?= count($pendingOrders) ?> comenzi</span>
      </div>
      <div class="card-body p-0">
        <?php online_render_orders_table($pendingOrders, $detailsByOrder, $csrfToken, false); ?>
      </div>
    </div>

    <div class="card table-card">
      <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h2 class="section-title">Comenzi importate</h2>
        <span class="badge bg-info text-dark"><?= count($importedOrders) ?> comenzi</span>
      </div>
      <div class="card-body p-0">
        <?php online_render_orders_table($importedOrders, $detailsByOrder, $csrfToken, true); ?>
      </div>
    </div>

    <?php foreach ($orders as $order): ?>
      <?php
        $oid = (int)$order['id'];
        $products = $detailsByOrder[$oid] ?? [];
        $productsCount = (int)($order['nr_produse'] ?? count($products));
        [$canImport, $blockReason] = online_can_import_order($order, $productsCount);
        $warningStatus = in_array(strtolower((string)$order['status']), ['processing', 'pending', 'on-hold'], true);
      ?>
      <div id="online-details-<?= $oid ?>" class="online-modal-template"
           data-can-import="<?= $canImport ? '1' : '0' ?>"
           data-block-reason="<?= online_h($blockReason) ?>">
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <div class="alert alert-light border py-2 mb-0">
              <div><strong>Client:</strong> <?= online_h(($order['nume_client'] ?? '') ?: ($order['utilizator'] ?? '-') ?: '-') ?></div>
              <div><strong>Telefon:</strong> <?= online_h(($order['telefon_client'] ?? '') ?: '-') ?></div>
              <div><strong>Data comandÄƒ:</strong> <?= online_h($order['data'] ?? '') ?></div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="alert alert-light border py-2 mb-0">
              <div class="d-flex flex-wrap gap-2 align-items-center mb-1">
                <strong>Status:</strong> <?= online_status_badge($order['status'] ?? '') ?>
                <?php if ($warningStatus && $canImport): ?>
                  <span class="badge bg-warning text-dark">vizibilÄƒ cu avertisment</span>
                <?php endif; ?>
              </div>
              <div class="d-flex flex-wrap gap-2 align-items-center mb-1">
                <strong>PlatÄƒ:</strong> <?= online_payment_badge($order['metoda_plata'] ?? '') ?>
              </div>
              <div><strong>Total:</strong> <?= online_money($order['valoare_cu_tva'] ?? 0) ?> lei</div>
            </div>
          </div>
        </div>

        <?php if (!empty($order['observatii'])): ?>
          <div class="alert alert-info py-2"><strong>ObservaÈ›ii comandÄƒ:</strong> <?= nl2br(online_h($order['observatii'])) ?></div>
        <?php endif; ?>

        <?php if ((int)$order['importata_pos'] === 1): ?>
          <div class="alert alert-secondary py-2">
            <strong>ComandÄƒ importatÄƒ:</strong>
            bon #<?= online_h($order['nr_bon_pos']) ?>,
            operator <?= online_h($order['operator_import']) ?>,
            locaÈ›ia <?= online_h($order['locatie_import']) ?>,
            la <?= online_h($order['data_import_pos']) ?>.
          </div>
        <?php elseif (!$canImport): ?>
          <div class="alert alert-danger py-2">
            <strong>Import blocat:</strong> <?= online_h($blockReason) ?>
          </div>
        <?php elseif ($warningStatus): ?>
          <div class="alert alert-warning py-2">
            <strong>AtenÈ›ie:</strong> comanda are status <?= online_h($order['status']) ?>. Poate fi importatÄƒ, dar operatorul trebuie sÄƒ verifice situaÈ›ia Ã®nainte de finalizarea bonului.
          </div>
        <?php endif; ?>

        <div class="online-scroll-controls d-flex justify-content-end gap-2 align-items-center">
          <button type="button" class="btn btn-outline-secondary btn-sm js-products-scroll-up">â†‘ Scroll sus</button>
          <button type="button" class="btn btn-outline-secondary btn-sm js-products-scroll-down">â†“ Scroll jos</button>
        </div>

        <div class="table-responsive modal-products-scroll border rounded-bottom bg-white js-products-scroll-area">
          <table class="table table-sm table-bordered mb-0">
            <thead class="table-light">
              <tr>
                <th>Cod produs POS</th>
                <th>Produs</th>
                <th class="text-center">Cant.</th>
                <th class="text-end">PreÈ› unitar</th>
                <th class="text-end">Val. fÄƒrÄƒ TVA</th>
                <th class="text-end">TVA</th>
                <th class="text-end">Total</th>
                <th>ObservaÈ›ii</th>
              </tr>
            </thead>
            <tbody>
            <?php if (!$products): ?>
              <tr><td colspan="8" class="text-muted">Comanda nu conÈ›ine produse.</td></tr>
            <?php else: foreach ($products as $p): ?>
              <tr>
                <td class="nowrap"><?= online_h($p['cod_produs'] ?? '') ?></td>
                <td><?= online_h($p['nume_produs'] ?? '') ?></td>
                <td class="text-center nowrap"><?= online_qty($p['cantitate'] ?? 0) ?></td>
                <td class="text-end nowrap"><?= online_money($p['pret_unitar'] ?? 0) ?></td>
                <td class="text-end nowrap"><?= online_money($p['valoare'] ?? 0) ?></td>
                <td class="text-end nowrap"><?= online_money($p['tva_col'] ?? 0) ?> <span class="small-muted">(<?= online_money($p['cota_tva'] ?? 0) ?>%)</span></td>
                <td class="text-end nowrap fw-semibold"><?= online_money($p['valoare_cu_tva'] ?? 0) ?></td>
                <td><?= online_h($p['observatie_produs'] ?? '') ?></td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <script src="vendor/offline/bootstrap5/bootstrap.bundle.min.js"></script>

  <div class="modal fade" id="onlineOrderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <form id="onlineImportForm" method="post" action="vanzare_importa_comenzi_online.php">
          <div class="modal-header align-items-start gap-2">
            <div class="flex-grow-1">
              <h5 class="modal-title mb-1" id="onlineOrderModalTitle">Detalii comandÄƒ online</h5>
              <div class="small text-muted">VerificÄƒ produsele, apoi importÄƒ Ã®n bonul curent din magazin.</div>
            </div>

            <button type="submit" class="btn btn-primary btn-sm mt-1" id="onlineImportSubmitTop">ImportÄƒ</button>
            <button type="button" class="btn-close mt-2" data-bs-dismiss="modal" aria-label="ÃŽnchide"></button>
          </div>

          <div class="modal-body" id="onlineOrderModalBody"></div>

          <div class="modal-footer">
            <input type="hidden" name="csrf_token" value="<?= online_h($csrfToken) ?>">
            <input type="hidden" name="action" value="import_online_order">
            <input type="hidden" name="id_comanda" value="">

            <button type="submit" class="btn btn-primary" id="onlineImportSubmit">ImportÄƒ Ã®n bon</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ÃŽnchide</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    document.addEventListener('click', function (ev) {
      const explicitBtn = ev.target.closest('.js-open-online-order');
      if (explicitBtn) {
        ev.preventDefault();
        openOnlineModal(explicitBtn.dataset.onlineId);
        return;
      }

      const row = ev.target.closest('tr[data-online-id]');
      if (!row) return;
      if (ev.target.closest('button,a,input,label,select,option,form')) return;
      openOnlineModal(row.dataset.onlineId);
    }, { passive: false });

    document.getElementById('onlineImportForm').addEventListener('submit', function (ev) {
      const orderInput = document.querySelector('#onlineImportForm input[name="id_comanda"]');
      const oid = orderInput ? orderInput.value : '';
      const ok = confirm('Importi comanda online #' + oid + ' Ã®n bonul curent?');
      if (!ok) {
        ev.preventDefault();
      }
    });

    function wireOnlineProductScrollButtons() {
      const body = document.getElementById('onlineOrderModalBody');
      const scrollArea = body.querySelector('.js-products-scroll-area');
      const btnUp = body.querySelector('.js-products-scroll-up');
      const btnDown = body.querySelector('.js-products-scroll-down');

      if (!scrollArea || !btnUp || !btnDown) {
        return;
      }

      function getScrollStep() {
        const firstRow = scrollArea.querySelector('tbody tr');
        const rowHeight = firstRow ? firstRow.getBoundingClientRect().height : 42;
        return Math.max(160, rowHeight * 5);
      }

      btnUp.addEventListener('click', function () {
        scrollArea.scrollBy({ top: -getScrollStep(), behavior: 'smooth' });
      });

      btnDown.addEventListener('click', function () {
        scrollArea.scrollBy({ top: getScrollStep(), behavior: 'smooth' });
      });
    }

    function setImportButtonsEnabled(enabled, reason) {
      const submitTop = document.getElementById('onlineImportSubmitTop');
      const submitBottom = document.getElementById('onlineImportSubmit');
      [submitTop, submitBottom].forEach(function (btn) {
        if (!btn) return;
        btn.disabled = !enabled;
        btn.title = enabled ? '' : (reason || 'Import blocat');
        btn.classList.toggle('btn-primary', enabled);
        btn.classList.toggle('btn-secondary', !enabled);
      });
    }

    function openOnlineModal(orderId) {
      const tpl = document.getElementById('online-details-' + orderId);
      if (!tpl) return;

      document.getElementById('onlineOrderModalTitle').textContent = 'ImportÄƒ comanda online #' + orderId;
      document.getElementById('onlineOrderModalBody').innerHTML = tpl.innerHTML;

      const orderInput = document.querySelector('#onlineImportForm input[name="id_comanda"]');
      if (orderInput) {
        orderInput.value = orderId;
      }

      const canImport = tpl.dataset.canImport === '1';
      const reason = tpl.dataset.blockReason || 'Import blocat';
      setImportButtonsEnabled(canImport, reason);

      wireOnlineProductScrollButtons();
      bootstrap.Modal.getOrCreateInstance(document.getElementById('onlineOrderModal')).show();
    }

    document.getElementById('onlineOrderModal').addEventListener('hidden.bs.modal', function () {
      document.getElementById('onlineOrderModalTitle').textContent = 'Detalii comandÄƒ online';
      document.getElementById('onlineOrderModalBody').innerHTML = '';

      const orderInput = document.querySelector('#onlineImportForm input[name="id_comanda"]');
      if (orderInput) {
        orderInput.value = '';
      }

      setImportButtonsEnabled(true, '');
    });
  </script>
</body>
</html>
