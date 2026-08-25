<?php
define('DEBUG_MODE', true);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/eroare.log');

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
date_default_timezone_set('Europe/Bucharest');

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/det_note_departament_listare_schema.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
  http_response_code(500);
  die('<h1>Lipsa conexiune POS ($pdo).</h1>');
}

if (!isset($_SESSION['cod_locatie'], $_SESSION['admin_id'])) {
  header('Location: logout.php');
  exit;
}

$adm_id = (int)$_SESSION['admin_id'];
$cod_locatie = (int)$_SESSION['cod_locatie'];
agecs_ensure_det_note_departament_listare($pdo);

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money_ro($v): string { return number_format((float)$v, 2, ',', '.'); }

function copyDetaliiProtocolInNota(PDO $pdoPOS, int $nrBonSursa, int $nrBonDest): int {
  agecs_snapshot_det_note_departamente($pdoPOS, $nrBonSursa);

  $sql = "
    INSERT INTO det_note (
      nr_bon, cod_p, nume_produs, cantitate, cota_tva, tva_col, pret_vanzare,
      valoare_vanzare, valoare_vanzare_cu_tva, discount, pachet, preparat, t_list,
      data, ora, cod_meniu, observatie_produs, preluat_osp, prioritate,
      cod_meniu_pers, meniu_instance_id, meniu_instance_qty, departament_listare
    )
    SELECT
      :nrBonDest, d.cod_p, d.nume_produs, d.cantitate, d.cota_tva, d.tva_col, d.pret_vanzare,
      d.valoare_vanzare, d.valoare_vanzare_cu_tva, d.discount, d.pachet, d.preparat, d.t_list,
      d.data, d.ora, d.cod_meniu, d.observatie_produs, d.preluat_osp, d.prioritate,
      d.cod_meniu_pers, d.meniu_instance_id, d.meniu_instance_qty, d.departament_listare
    FROM det_note d
    WHERE d.nr_bon = :nrBonSursa
    ORDER BY d.id_vanz ASC
  ";
  $stmt = $pdoPOS->prepare($sql);
  $stmt->execute([
    ':nrBonDest' => $nrBonDest,
    ':nrBonSursa' => $nrBonSursa,
  ]);
  return (int)$stmt->rowCount();
}

function recalcNota(PDO $pdoPOS, int $nrBon): void {
  $sql = "
    UPDATE note n
       SET n.valoare_vanzare_cu_tva = (SELECT COALESCE(SUM(d.valoare_vanzare_cu_tva),0) FROM det_note d WHERE d.nr_bon=n.nrbon),
           n.tva_colectata = (SELECT COALESCE(SUM(d.tva_col),0) FROM det_note d WHERE d.nr_bon=n.nrbon),
           n.discount = (SELECT COALESCE(SUM(d.discount),0) FROM det_note d WHERE d.nr_bon=n.nrbon)
     WHERE n.nrbon=?
  ";
  $stmt = $pdoPOS->prepare($sql);
  $stmt->execute([$nrBon]);
}

function upsertUltimBonConectat(PDO $pdoPOS, int $locatie, int $nrBon): void {
  $ts = date('Y-m-d H:i:s');
  try {
    restaurantTouchUltimBonConectat($pdoPOS, $locatie, $nrBon, $ts);
  } catch (Throwable $e) {
    error_log("[ultim_bon_conectat] " . $e->getMessage());
  }
}

function fetchSourceProtocolNote(PDO $pdoPOS, int $nrBon, int $locatie, int $operator): ?array {
  $stmt = $pdoPOS->prepare("
    SELECT nrbon, cod_masa, operator, locatie, status, protocol
    FROM note
    WHERE nrbon = ? AND locatie = ? AND operator = ? AND status = 'F' AND protocol > 0
    LIMIT 1
    FOR UPDATE
  ");
  $stmt->execute([$nrBon, $locatie, $operator]);
  return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function fetchDestinationOpenNote(PDO $pdoPOS, int $nrBon, int $locatie, int $operator): ?array {
  $stmt = $pdoPOS->prepare("
    SELECT nrbon, cod_masa, operator, locatie, status
    FROM note
    WHERE nrbon = ? AND locatie = ? AND operator = ? AND status = 'S'
    LIMIT 1
    FOR UPDATE
  ");
  $stmt->execute([$nrBon, $locatie, $operator]);
  return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$message = $error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'restore_protocol_note') {
  $nrBonSursa = (int)($_POST['nrbon_src'] ?? 0);
  $nrBonDest = (int)($_SESSION['nr_bon'] ?? 0);

  if ($nrBonSursa <= 0) {
    $error = 'Nr. nota protocol invalid.';
  } elseif ($nrBonDest <= 0) {
    $error = 'Nu exista nota deschisa in sesiune.';
  } elseif ($nrBonSursa === $nrBonDest) {
    $error = 'Nota sursa nu poate fi aceeasi cu nota de destinatie.';
  } else {
    try {
      $pdo->beginTransaction();

      $dest = fetchDestinationOpenNote($pdo, $nrBonDest, $cod_locatie, $adm_id);
      if (!$dest) {
        throw new RuntimeException('Nota de destinatie din sesiune nu este valida.');
      }

      $src = fetchSourceProtocolNote($pdo, $nrBonSursa, $cod_locatie, $adm_id);
      if (!$src) {
        throw new RuntimeException('Nota protocol sursa nu este valida pentru operatorul conectat.');
      }

      $stmtDetCount = $pdo->prepare("SELECT COUNT(*) AS c FROM det_note WHERE nr_bon=?");
      $stmtDetCount->execute([$nrBonSursa]);
      $detCount = (int)($stmtDetCount->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
      if ($detCount <= 0) {
        throw new RuntimeException('Nota protocol sursa nu are produse.');
      }

      $copiate = copyDetaliiProtocolInNota($pdo, $nrBonSursa, $nrBonDest);
      if ($copiate <= 0) {
        throw new RuntimeException('Nu s-au putut copia produsele din det_note.');
      }

      recalcNota($pdo, $nrBonDest);

      $stmtDelMisc = $pdo->prepare("DELETE FROM miscari WHERE fel_doc='BF' AND nr_doc=?");
      $stmtDelMisc->execute([$nrBonSursa]);

      $stmtDelDet = $pdo->prepare("DELETE FROM det_note WHERE nr_bon=?");
      $stmtDelDet->execute([$nrBonSursa]);

      $stmtDelNota = $pdo->prepare("DELETE FROM note WHERE nrbon=? AND locatie=? AND operator=?");
      $stmtDelNota->execute([$nrBonSursa, $cod_locatie, $adm_id]);
      if ($stmtDelNota->rowCount() <= 0) {
        throw new RuntimeException('Nu s-a putut sterge nota protocol sursa.');
      }

      upsertUltimBonConectat($pdo, $cod_locatie, $nrBonDest);
      $_SESSION['nr_bon'] = $nrBonDest;
      $_SESSION['masa_curenta'] = (int)($dest['cod_masa'] ?? 0);
      $_SESSION['trimis_comanda'] = 0;

      $pdo->commit();
      header('Location: vanzare_restaurant.php');
      exit;
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      $error = 'Eroare la restaurare protocol: ' . $e->getMessage();
    }
  }
}

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

$q = trim((string)($_GET['q'] ?? ''));
if (strlen($q) > 120) {
  $q = substr($q, 0, 120);
}

$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));

if ($dateFrom !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
  $dateFrom = '';
}
if ($dateTo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
  $dateTo = '';
}
if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
  $tmp = $dateFrom;
  $dateFrom = $dateTo;
  $dateTo = $tmp;
}

$whereSql = "
  n.locatie = :locatie
  AND n.operator = :operator
  AND n.status = 'F'
  AND COALESCE(n.protocol, 0) > 0
";
$whereParams = [
  ':locatie' => $cod_locatie,
  ':operator' => $adm_id,
];

if ($dateFrom !== '') {
  $whereSql .= " AND n.data_bon >= :date_from";
  $whereParams[':date_from'] = $dateFrom;
}
if ($dateTo !== '') {
  $whereSql .= " AND n.data_bon <= :date_to";
  $whereParams[':date_to'] = $dateTo;
}
if ($q !== '') {
  $whereSql .= "
    AND (
      CAST(n.nrbon AS CHAR) LIKE :q
      OR COALESCE(NULLIF(m.nume_masa, ''), CONCAT('Masa ', n.cod_masa)) LIKE :q
      OR EXISTS (
        SELECT 1
        FROM det_note ds
        WHERE ds.nr_bon = n.nrbon
          AND ds.nume_produs LIKE :q
      )
    )
  ";
  $whereParams[':q'] = '%' . $q . '%';
}

$countStmt = $pdo->prepare("
  SELECT COUNT(*) AS c
  FROM note n
  LEFT JOIN mese m
    ON m.cod_masa = n.cod_masa
    AND m.cod_locatie = :locatie_mese
  WHERE $whereSql
");
$countParams = $whereParams;
$countParams[':locatie_mese'] = $cod_locatie;
$countStmt->execute($countParams);
$totalRows = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
$totalPages = max(1, (int)ceil($totalRows / $limit));
if ($page > $totalPages) {
  $page = $totalPages;
  $offset = ($page - 1) * $limit;
}

$orders = [];
$detailsByNote = [];
$nrBonSesiuneAfisare = (int)($_SESSION['nr_bon'] ?? 0);

if ($totalRows > 0) {
  $sqlList = "
    SELECT
      n.nrbon,
      n.data_bon,
      n.ora_bon,
      n.valoare_vanzare_cu_tva,
      n.tva_colectata,
      n.discount,
      n.protocol,
      n.operator,
      n.cod_masa,
      COALESCE(NULLIF(m.nume_masa, ''), CONCAT('Masa ', n.cod_masa)) AS nume_masa,
      TRIM(CONCAT(COALESCE(a.admin_firstname, ''), ' ', COALESCE(a.admin_lastname, ''))) AS ospatar,
      (SELECT COUNT(*) FROM det_note d WHERE d.nr_bon = n.nrbon) AS linii_det,
      (SELECT COUNT(*) FROM miscari mi WHERE mi.fel_doc = 'BF' AND mi.nr_doc = n.nrbon) AS linii_miscari_bf
    FROM note n
    LEFT JOIN mese m
      ON m.cod_masa = n.cod_masa
      AND m.cod_locatie = :locatie_mese
    LEFT JOIN {$tabel_final_admins} a
      ON a.admin_id = n.operator
    WHERE $whereSql
    ORDER BY n.nrbon DESC
    LIMIT {$limit} OFFSET {$offset}
  ";
  $stmt = $pdo->prepare($sqlList);
  $listParams = $whereParams;
  $listParams[':locatie_mese'] = $cod_locatie;
  $stmt->execute($listParams);
  $orders = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

  if ($orders) {
    $ids = array_map(static fn($r) => (int)$r['nrbon'], $orders);
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $stmtDet = $pdo->prepare("
      SELECT
        id_vanz, nr_bon, cod_p, nume_produs, cantitate, cota_tva, tva_col, pret_vanzare,
        valoare_vanzare, valoare_vanzare_cu_tva, observatie_produs, t_list
      FROM det_note
      WHERE nr_bon IN ($ph)
      ORDER BY id_vanz ASC
    ");
    $stmtDet->execute($ids);
    while ($r = $stmtDet->fetch(PDO::FETCH_ASSOC)) {
      $detailsByNote[(int)$r['nr_bon']][] = $r;
    }
  }
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Restaurare note protocol</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { font-size: 1.06rem; background-color: #f8f9fa; }
    .page-header { background: linear-gradient(90deg, #0f5132, #198754); color: #fff; padding: 20px; }
    .nowrap { white-space: nowrap; }
    .cursor-pointer { cursor: pointer; }
    .table-hover tr:hover { background-color: #f1f3f5; }
    .order-products-panel {
      border: 1px solid #dee2e6;
      border-radius: 10px;
      overflow: hidden;
      background: #fff;
    }
    .order-products-scroll {
      max-height: min(52vh, 460px);
      overflow-y: auto;
      scroll-behavior: smooth;
      background: #fff;
    }
    .scroll-controls {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      padding: 10px 12px;
      background: #f8f9fa;
    }
    .scroll-controls + .order-products-scroll {
      border-top: 1px solid #dee2e6;
      border-bottom: 1px solid #dee2e6;
    }
    .scroll-controls-bottom { border-top: 0; }
    .scroll-controls .btn { min-width: 86px; }
  </style>
</head>
<body>
<header class="page-header">
  <div class="container d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
    <div class="d-flex align-items-center gap-3 flex-wrap">
      <a href="vanzare_restaurant.php" class="btn btn-light btn-sm border">&larr; Inapoi la vanzari</a>
      <h1 class="m-0 fs-3">Restaurare note protocol</h1>
    </div>
  </div>
</header>

<div class="container my-4">
  <?php if ($message): ?><div class="alert alert-success"><?= h($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
  <div class="alert alert-info py-2">
    Nota de destinatie din sesiune: <strong>#<?= $nrBonSesiuneAfisare > 0 ? (int)$nrBonSesiuneAfisare : 0 ?></strong>
  </div>

  <div class="card shadow-sm">
    <div class="card-body border-bottom bg-light">
      <form method="get" action="vanzare_restaurare_protocol.php" class="row g-2 align-items-end">
        <div class="col-12 col-md-5">
          <label class="form-label mb-1">Cautare (nr. nota, masa, produs)</label>
          <input type="text" class="form-control" name="q" value="<?= h($q) ?>" placeholder="Ex: 123, Masa 5, Ciorba">
        </div>
        <div class="col-6 col-md-3">
          <label class="form-label mb-1">Data de la</label>
          <input type="date" class="form-control" name="date_from" value="<?= h($dateFrom) ?>">
        </div>
        <div class="col-6 col-md-3">
          <label class="form-label mb-1">Data pana la</label>
          <input type="date" class="form-control" name="date_to" value="<?= h($dateTo) ?>">
        </div>
        <div class="col-12 col-md-1 d-grid">
          <button type="submit" class="btn btn-primary">Filtreaza</button>
        </div>
        <div class="col-12">
          <a href="vanzare_restaurare_protocol.php" class="btn btn-outline-secondary btn-sm">Reseteaza filtrele</a>
        </div>
      </form>
    </div>

    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover table-striped mb-0">
          <thead class="table-dark">
            <tr>
              <th class="text-center">Nr. Nota</th>
              <th>Data & Ora</th>
              <th class="text-center">Masa</th>
              <th>Ospatar</th>
              <th class="text-end">Total</th>
              <th class="text-end">Protocol</th>
              <th class="text-center">Detalii</th>
              <th class="text-center">Miscari BF</th>
              <th>Actiuni</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$orders): ?>
              <tr>
                <td colspan="9" class="text-center py-4 text-muted">Nu exista note protocol disponibile.</td>
              </tr>
            <?php else: foreach ($orders as $o):
              $nr = (int)$o['nrbon'];
            ?>
              <tr class="cursor-pointer" data-pr-nrbon="<?= $nr ?>" title="Vezi detalii nota protocol">
                <td class="text-center fw-semibold">#<?= $nr ?></td>
                <td><span class="nowrap"><?= h(trim((string)$o['data_bon'] . ' ' . (string)$o['ora_bon'])) ?></span></td>
                <td class="text-center"><?= h($o['nume_masa'] ?: ('Masa ' . (int)$o['cod_masa'])) ?></td>
                <td><?= h($o['ospatar'] !== '' ? $o['ospatar'] : ('Operator #' . (int)$o['operator'])) ?></td>
                <td class="text-end fw-semibold"><?= money_ro($o['valoare_vanzare_cu_tva']) ?> RON</td>
                <td class="text-end"><?= money_ro($o['protocol']) ?> RON</td>
                <td class="text-center"><?= (int)$o['linii_det'] ?></td>
                <td class="text-center"><?= (int)$o['linii_miscari_bf'] ?></td>
                <td>
                  <button type="button" class="btn btn-outline-success btn-sm"
                          onclick="event.stopPropagation(); openProtocolModal(<?= $nr ?>);">
                    Restaurare
                  </button>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <?php if ($totalPages > 1): ?>
    <nav aria-label="Paginare" class="mt-4">
      <ul class="pagination justify-content-center">
        <?php
          $base = 'vanzare_restaurare_protocol.php';
          $queryBase = [];
          if ($q !== '') { $queryBase['q'] = $q; }
          if ($dateFrom !== '') { $queryBase['date_from'] = $dateFrom; }
          if ($dateTo !== '') { $queryBase['date_to'] = $dateTo; }
          $buildPageUrl = static function(int $targetPage) use ($base, $queryBase): string {
            $params = $queryBase;
            $params['page'] = $targetPage;
            return $base . '?' . http_build_query($params);
          };
        ?>
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
          <a class="page-link" href="<?= $page <= 1 ? '#' : h($buildPageUrl($page - 1)) ?>">Anterior</a>
        </li>
        <?php
          $start = max(1, $page - 3);
          $end = min($totalPages, $page + 3);
          for ($p = $start; $p <= $end; $p++):
        ?>
          <li class="page-item <?= $p === $page ? 'active' : '' ?>">
            <a class="page-link" href="<?= h($buildPageUrl($p)) ?>"><?= $p ?></a>
          </li>
        <?php endfor; ?>
        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
          <a class="page-link" href="<?= $page >= $totalPages ? '#' : h($buildPageUrl($page + 1)) ?>">Urmator</a>
        </li>
      </ul>
    </nav>
  <?php endif; ?>

  <?php if ($orders): foreach ($orders as $o):
    $nr = (int)$o['nrbon'];
    $items = $detailsByNote[$nr] ?? [];
  ?>
    <div id="pr-details-<?= $nr ?>" class="d-none">
      <div class="pr-modal-header-info">
        <div class="small text-muted">
          <span class="nowrap">Data & Ora: <?= h(trim((string)$o['data_bon'] . ' ' . (string)$o['ora_bon'])) ?></span>
          • Masa: <?= h($o['nume_masa'] ?: ('Masa ' . (int)$o['cod_masa'])) ?>
          • Protocol: <strong><?= money_ro($o['protocol']) ?> RON</strong>
        </div>
      </div>

      <div class="pr-modal-header-controls">
        <div class="alert alert-warning mb-0 py-2">
          Produsele se vor copia in nota curenta din sesiune
          (<strong>#<?= $nrBonSesiuneAfisare > 0 ? (int)$nrBonSesiuneAfisare : 0 ?></strong>),
          apoi nota protocol sursa va fi stearsa din <code>note</code>, <code>det_note</code> si <code>miscari</code>
          unde <code>fel_doc='BF'</code> si <code>nr_doc=nrbon</code>.
        </div>
      </div>

      <div class="pr-modal-body-content mt-3">
        <?php if (!$items): ?>
          <div class="text-muted">Nu exista produse in det_note pentru aceasta nota.</div>
        <?php else: ?>
          <div class="order-products-panel">
            <div class="scroll-controls">
              <div class="small text-muted fw-semibold">Produse din nota protocol</div>
              <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm js-scroll-products-up">↑ Sus</button>
                <button type="button" class="btn btn-outline-secondary btn-sm js-scroll-products-down">↓ Jos</button>
              </div>
            </div>

            <div class="table-responsive js-products-scroll order-products-scroll">
              <table class="table table-sm table-bordered mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Produs</th>
                    <th class="text-center">Cant.</th>
                    <th class="text-center">TVA %</th>
                    <th class="text-end">Pret (cu TVA)</th>
                    <th class="text-end">Val. fara TVA</th>
                    <th class="text-end">TVA</th>
                    <th class="text-end">Val. cu TVA</th>
                    <th class="text-center">t_list</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($items as $it): ?>
                    <tr>
                      <td>
                        <?= h($it['nume_produs']) ?>
                        <?php if (($it['observatie_produs'] ?? '') !== ''): ?>
                          <div class="small text-muted">Obs: <?= nl2br(h($it['observatie_produs'])) ?></div>
                        <?php endif; ?>
                      </td>
                      <td class="text-center"><?= (float)$it['cantitate'] ?></td>
                      <td class="text-center"><?= (int)$it['cota_tva'] ?></td>
                      <td class="text-end"><?= money_ro($it['pret_vanzare']) ?> RON</td>
                      <td class="text-end"><?= money_ro($it['valoare_vanzare']) ?> RON</td>
                      <td class="text-end"><?= money_ro($it['tva_col']) ?> RON</td>
                      <td class="text-end fw-semibold"><?= money_ro($it['valoare_vanzare_cu_tva']) ?> RON</td>
                      <td class="text-center"><?= (int)$it['t_list'] ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <div class="scroll-controls scroll-controls-bottom">
              <div class="small text-muted">Derulare rapida in lista de produse</div>
              <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm js-scroll-products-up">↑ Sus</button>
                <button type="button" class="btn btn-outline-secondary btn-sm js-scroll-products-down">↓ Jos</button>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<div class="modal fade" id="protocolModal" tabindex="-1" aria-labelledby="protocolModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <form id="restoreForm" method="post" action="vanzare_restaurare_protocol.php">
        <div class="modal-header d-block">
          <div class="d-flex align-items-start justify-content-between gap-2">
            <div class="w-100 pe-2">
              <h5 class="modal-title mb-1" id="protocolModalLabel">Detalii nota protocol</h5>
              <div id="protocolModalHeaderInfo" class="small text-muted"></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Inchide"></button>
          </div>
          <div class="mt-3" id="protocolModalHeaderControls"></div>
        </div>
        <div class="modal-body" id="protocolModalBody"></div>
        <div class="modal-footer">
          <input type="hidden" name="action" value="restore_protocol_note">
          <input type="hidden" name="nrbon_src" value="">
          <button id="restoreSubmitBtn" class="btn btn-success" type="submit">Restaureaza nota protocol</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Inchide</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  (function () {
    const REFRESH_MS = 20000;
    let timer = null;

    function schedule() {
      clearTimeout(timer);
      timer = setTimeout(() => {
        const shown = document.querySelector('#protocolModal.show');
        if (document.visibilityState === 'visible' && !shown) {
          location.reload();
        } else {
          schedule();
        }
      }, REFRESH_MS);
    }

    document.addEventListener('visibilitychange', schedule);
    window.addEventListener('focus', schedule);
    window.addEventListener('pageshow', schedule);
    schedule();
  })();

  document.addEventListener('click', (ev) => {
    const tr = ev.target.closest('tr[data-pr-nrbon]');
    if (!tr) return;
    if (ev.target.closest('form,button,a,input,label,select,option')) return;
    openProtocolModal(tr.dataset.prNrbon);
  }, { passive: true });

  function wireModalProductScroll() {
    const bodyEl = document.getElementById('protocolModalBody');
    const scrollArea = bodyEl.querySelector('.js-products-scroll');
    const upButtons = bodyEl.querySelectorAll('.js-scroll-products-up');
    const downButtons = bodyEl.querySelectorAll('.js-scroll-products-down');

    if (!scrollArea || (!upButtons.length && !downButtons.length)) return;

    const getStep = () => Math.max(180, Math.floor(scrollArea.clientHeight * 0.75));

    const updateButtons = () => {
      const maxScrollTop = Math.max(0, scrollArea.scrollHeight - scrollArea.clientHeight);
      const atTop = scrollArea.scrollTop <= 5;
      const atBottom = scrollArea.scrollTop >= (maxScrollTop - 5);
      upButtons.forEach(btn => { btn.disabled = atTop; });
      downButtons.forEach(btn => { btn.disabled = (maxScrollTop <= 0) || atBottom; });
    };

    upButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        scrollArea.scrollBy({ top: -getStep(), behavior: 'smooth' });
      });
    });

    downButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        scrollArea.scrollBy({ top: getStep(), behavior: 'smooth' });
      });
    });

    scrollArea.addEventListener('scroll', updateButtons, { passive: true });
    requestAnimationFrame(updateButtons);
  }

  function openProtocolModal(nrbon) {
    const modalEl = document.getElementById('protocolModal');
    const bodyEl = document.getElementById('protocolModalBody');
    const headerInfoEl = document.getElementById('protocolModalHeaderInfo');
    const headerControlsEl = document.getElementById('protocolModalHeaderControls');
    const tpl = document.getElementById('pr-details-' + nrbon);
    if (!tpl) return;

    const infoTpl = tpl.querySelector('.pr-modal-header-info');
    const controlsTpl = tpl.querySelector('.pr-modal-header-controls');
    const bodyTpl = tpl.querySelector('.pr-modal-body-content');

    headerInfoEl.innerHTML = infoTpl ? infoTpl.innerHTML : '';
    headerControlsEl.innerHTML = controlsTpl ? controlsTpl.innerHTML : '';
    bodyEl.innerHTML = bodyTpl ? bodyTpl.innerHTML : tpl.innerHTML;

    document.getElementById('protocolModalLabel').textContent = 'Restaurare nota protocol #' + nrbon;
    document.querySelector('#restoreForm input[name="nrbon_src"]').value = nrbon;

    wireModalProductScroll();
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
  }

  document.getElementById('protocolModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('protocolModalLabel').textContent = 'Detalii nota protocol';
    document.getElementById('protocolModalHeaderInfo').innerHTML = '';
    document.getElementById('protocolModalHeaderControls').innerHTML = '';
    document.getElementById('protocolModalBody').innerHTML = '';
    document.querySelector('#restoreForm input[name="nrbon_src"]').value = '';
  });
</script>
</body>
</html>
