<?php
// vanzare_importa_comanda_qr.php — listare + import comenzi QR -> notă nouă în POS
// Necesită: session.php ($pdo POS) + databaseconnection_qr.php ($pdo_qr QR)

define('DEBUG_MODE', true);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/eroare.log');

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
date_default_timezone_set('Europe/Bucharest');

// === Conexiuni necesare ===
require_once __DIR__ . '/session.php'; // definește $pdo (POS)

if (function_exists('restaurantIsOfflineSqlite') && restaurantIsOfflineSqlite()) {
  http_response_code(403);
  die('<h1>Importul QR este dezactivat in modul SQLite offline.</h1>');
}

$__pdo_pos_backup = isset($pdo) && $pdo instanceof PDO ? $pdo : null;
require_once __DIR__ . '/databaseconnection_qr.php'; // trebuie să definească $pdo_qr
if (!isset($pdo_qr) || !($pdo_qr instanceof PDO)) {
  // fallback aliasuri, dacă fișierul folosește alt nume
  foreach (['pdoQR','pdo_client','pdo_client_qr','pdo2','db_qr','cnx_qr'] as $alt) {
    if (isset($GLOBALS[$alt]) && $GLOBALS[$alt] instanceof PDO) { $pdo_qr = $GLOBALS[$alt]; break; }
  }
}
if ($__pdo_pos_backup instanceof PDO) { $pdo = $__pdo_pos_backup; unset($__pdo_pos_backup); }

if (!isset($pdo) || !($pdo instanceof PDO))    { http_response_code(500); die('<h1>Lipsă conexiune POS ($pdo).</h1>'); }
if (!isset($pdo_qr) || !($pdo_qr instanceof PDO)) { http_response_code(500); die('<h1>Lipsă conexiune QR ($pdo_qr).</h1>'); }

// === Sesiune minimă ===
if (!isset($_SESSION['cod_locatie'], $_SESSION['admin_id'])) {
  header('Location: logout.php');
  exit;
}
$adm_id      = (int)$_SESSION['admin_id'];
$cod_locatie = (string)$_SESSION['cod_locatie'];

// === CSRF & utils ===
if (empty($_SESSION['csrf_admin'])) { $_SESSION['csrf_admin'] = bin2hex(random_bytes(32)); }
$csrf = $_SESSION['csrf_admin'];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money_ro($v){ return number_format((float)$v, 2, ',', '.'); }
function status_badge_class($st){
  $st = strtolower((string)$st);
  return match($st){
    'cos' => 'bg-light text-dark',
    'plasata' => 'bg-primary',
    'aprobata' => 'bg-info',
    'in_preparare' => 'bg-warning text-dark',
    'pregatita' => 'bg-info text-dark',
    'servita' => 'bg-secondary',
    'platita','finalizata' => 'bg-success',
    'anulata' => 'bg-dark',
    default => 'bg-light text-dark'
  };
}

// === Helper POS — NOTE ===
function createNotaNoua(PDO $pdoPOS, int $cod_masa, int $operator, string $locatie): int {
  $sql = "INSERT INTO note (operator, locatie, cod_masa, data_bon, ora_bon, status, listat_nota_plata)
          VALUES (?, ?, ?, ?, ?, 'S', 0)";
  $stmt = $pdoPOS->prepare($sql);
  $stmt->execute([$operator, $locatie, $cod_masa, date('Y-m-d'), date('H:i:s')]);
  return (int)$pdoPOS->lastInsertId();
}
function importDetaliiInNota(PDO $pdoPOS, int $nr_bon, array $detalii): void {
  $ins = $pdoPOS->prepare("
    INSERT INTO det_note
      (nr_bon, cod_p, nume_produs, cantitate, cota_tva, tva_col, pret_vanzare,
       valoare_vanzare, valoare_vanzare_cu_tva, data, ora, cod_meniu, observatie_produs, t_list)
    VALUES
      (?,?,?,?,?,?,?,?,?,?,?,0,?,0)
  ");
  $data_bon = date('Y-m-d'); $ora_bon = date('H:i:s');
  foreach ($detalii as $r) {
    $cant = (float)($r['cantitate'] ?? 0);
    $pretTVA = (float)($r['pret_cu_tva'] ?? 0);
    $cota = (float)($r['cota_tva'] ?? 0);
    $val_cu = round($pretTVA * $cant, 2);
    $tva_col = $cota > 0 ? round($val_cu * $cota / (100 + $cota), 2) : 0.0;
    $val_f = round($val_cu - $tva_col, 2);
    $obs = '';
    if (!empty($r['optiuni_json'])) {
      $j = json_decode($r['optiuni_json'], true);
      if (json_last_error() === JSON_ERROR_NONE && $j) {
        $obs = mb_substr("Optiuni: " . json_encode($j, JSON_UNESCAPED_UNICODE), 0, 255);
      }
    }
    $ins->execute([
      $nr_bon,
      $r['cod_produs'] ?? '',
      $r['denumire_snapshot'] ?? '',
      $cant,
      $cota,
      $tva_col,
      $pretTVA,
      $val_f,
      $val_cu,
      $data_bon,
      $ora_bon,
      $obs
    ]);
  }
}
function recalcNota(PDO $pdoPOS, int $nr_bon): void {
  $sql = "
    UPDATE note n
       SET n.valoare_vanzare_cu_tva = (SELECT COALESCE(SUM(d.valoare_vanzare_cu_tva),0) FROM det_note d WHERE d.nr_bon=n.nrbon),
           n.tva_colectata         = (SELECT COALESCE(SUM(d.tva_col),0)            FROM det_note d WHERE d.nr_bon=n.nrbon),
           n.discount               = (SELECT COALESCE(SUM(d.discount),0)          FROM det_note d WHERE d.nr_bon=n.nrbon)
     WHERE n.nrbon=?
  ";
  $pdoPOS->prepare($sql)->execute([$nr_bon]);
}
function setMasaOcupata(PDO $pdoPOS, int $cod_masa): void {
  try { $pdoPOS->prepare("UPDATE mese SET stare=1 WHERE cod_masa=?")->execute([$cod_masa]); }
  catch(Throwable $e){ /* dacă nu există coloana, ignorăm */ }
}
function upsertUltimBonConectat(PDO $pdoPOS, string $locatie, int $nr_bon): void {
  $ts = (new DateTime('now', new DateTimeZone('Europe/Bucharest')))->format('Y-m-d H:i:s');
  try{
    restaurantTouchUltimBonConectat($pdoPOS, (int)$locatie, $nr_bon, $ts);
  }catch(Throwable $e){ error_log("[ultim_bon_conectat] ".$e->getMessage()); }
}

// === Helper QR ===
function fetchComanda(PDO $pdoQR, int $id): ?array {
  $st = $pdoQR->prepare("SELECT * FROM comenzi WHERE id = ?");
  $st->execute([$id]);
  $r = $st->fetch(PDO::FETCH_ASSOC);
  return $r ?: null;
}
function fetchDetaliiComanda(PDO $pdoQR, int $id): array {
  $sd = $pdoQR->prepare("
    SELECT cod_produs, denumire_snapshot, cantitate, cota_tva, pret_cu_tva, optiuni_json
    FROM detalii_comenzi WHERE id_comanda=? ORDER BY id ASC
  ");
  $sd->execute([$id]);
  return $sd->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

// === POST: anulare comandă (doar în QR) ===
$message = $error = null;
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='cancel_order') {
  $token = $_POST['csrf'] ?? '';
  $id    = (int)($_POST['id_comanda'] ?? 0);

  if (!hash_equals($csrf, $token))        { $error = "Sesiune invalidă (CSRF). Reîncarcă pagina."; }
  elseif ($id<=0)                         { $error = "ID comandă invalid."; }
  else {
    try{
      $pdo_qr->beginTransaction();
      $st = $pdo_qr->prepare("SELECT id, stare FROM comenzi WHERE id=? FOR UPDATE");
      $st->execute([$id]); $row = $st->fetch();
      if (!$row) { throw new RuntimeException("Comanda nu există."); }
      $stare = strtolower((string)$row['stare']);
      if (in_array($stare, ['anulata','platita','finalizata','livrata','servita'], true)) {
        throw new RuntimeException("Comanda nu poate fi anulată (stare curentă: {$row['stare']})."); }
      $pdo_qr->prepare("UPDATE comenzi SET stare='anulata' WHERE id=?")->execute([$id]);
      try { $pdo_qr->prepare("UPDATE detalii_comenzi SET stare='respinsa' WHERE id_comanda=?")->execute([$id]); } catch(Throwable $x){}
      $pdo_qr->prepare("INSERT INTO evenimente_comenzi (id_comanda,actor_tip,actor_id,tip_eveniment,payload_json,creat_la)
                        VALUES (?,?,?,'anulare_comanda',?,NOW())")
             ->execute([$id,'ospatar',($adm_id?:null),json_encode(['motiv'=>'anulat din UI import'],JSON_UNESCAPED_UNICODE)]);
      $pdo_qr->commit(); $message = "Comanda #{$id} a fost anulată.";
    }catch(Throwable $e){
      if ($pdo_qr->inTransaction()) $pdo_qr->rollBack();
      $error = "Eroare la anulare: ".$e->getMessage();
    }
  }
}

// === POST: importă comandă QR → NOTĂ NOUĂ în POS ===
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='import_order') {
  $token         = $_POST['csrf'] ?? '';
  $id            = (int)($_POST['id_comanda'] ?? 0);
  $masaSelectata = (int)($_POST['cod_masa_target'] ?? 0);

  if (!hash_equals($csrf, $token))   { $error = "Sesiune invalidă (CSRF). Reîncarcă pagina."; }
  elseif ($id<=0)                    { $error = "ID comandă invalid."; }
  elseif ($masaSelectata<=0)         { $error = "Selectează o masă validă."; }
  else {
    try{
      $pdo->beginTransaction();

      $comanda = fetchComanda($pdo_qr, $id);
      if (!$comanda) { throw new RuntimeException("Comanda nu există."); }

      $nr_bon = createNotaNoua($pdo, $masaSelectata, $adm_id, $cod_locatie);
      $detalii = fetchDetaliiComanda($pdo_qr, $id);
      importDetaliiInNota($pdo, $nr_bon, $detalii);
      recalcNota($pdo, $nr_bon);
      setMasaOcupata($pdo, $masaSelectata);

      upsertUltimBonConectat($pdo, $cod_locatie, $nr_bon);
      $_SESSION['nr_bon']        = $nr_bon;
      $_SESSION['masa_curenta']  = $masaSelectata;
      $_SESSION['trimis_comanda']= 0;

      $stare_veche = strtolower((string)($comanda['stare'] ?? ''));
      $pdo_qr->prepare("UPDATE comenzi SET stare='aprobata' WHERE id=?")->execute([$id]);
      try {
        $pdo_qr->prepare("
          INSERT INTO evenimente_comenzi (id_comanda,actor_tip,actor_id,tip_eveniment,payload_json,creat_la)
          VALUES (?,?,?,'stare_modificata',?,NOW())
        ")->execute([
          $id, 'ospatar', ($adm_id ?: null),
          json_encode(['de_la'=>$stare_veche,'la'=>'aprobata'], JSON_UNESCAPED_UNICODE)
        ]);
      } catch (Throwable $x) { error_log('[import_order][log eveniment] '.$x->getMessage()); }

      $pdo->commit();
      header("Location: vanzare_restaurant.php");
      exit;

    } catch(Throwable $e){
      if ($pdo->inTransaction()) $pdo->rollBack();
      $error = "Eroare la import: ".$e->getMessage();
    }
  }
}

// === Date UI comune (mese libere) ===
$meseFull=[];
try{
  $ms = $pdo->query("
    SELECT cod_masa,
           COALESCE(NULLIF(nume_masa, ''), CONCAT('Masa ', cod_masa)) AS label
    FROM mese
    WHERE stare = 0
    ORDER BY cod_masa
  ");
  $meseFull = $ms->fetchAll(PDO::FETCH_ASSOC);
}catch(Throwable $e){ error_log("[mese dropdown] ".$e->getMessage()); }

// Contor active QR (plasata / in_preparare)
$activeCount=0; try{
  $q=$pdo_qr->query("SELECT COUNT(*) AS c FROM comenzi WHERE stare IN ('plasata','in_preparare')");
  $activeCount=(int)($q->fetch()['c']??0);
}catch(Throwable $e){}

// === Filtre/listare QR ===
$orders = [];$detailsByOrder=[];$stareFilter='toate';$page=1;$totalPages=1;$totalRows=0;$limit=50;$offset=0;

$stareFilter = trim((string)($_GET['stare'] ?? 'toate'));
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 50; $offset = ($page-1)*$limit;

$where=[]; $args=[];
if ($stareFilter!=='' && $stareFilter!=='toate') { $where[]="c.stare = ?"; $args[]=$stareFilter; }
else { $where[]="c.stare <> 'anulata'"; }
$whereSql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

$cntSt = $pdo_qr->prepare("SELECT COUNT(*) AS cnt FROM comenzi c $whereSql");
$cntSt->execute($args);
$totalRows  = (int)($cntSt->fetch()['cnt'] ?? 0);
$totalPages = max(1, (int)ceil($totalRows/$limit));

// Comenzi + masa din sesiuni_qr
$st = $pdo_qr->prepare("
  SELECT c.id,c.id_sesiune,c.stare,c.observatii,c.total_fara_tva,c.total_tva,c.total_cu_tva,c.creat_la,sqr.cod_masa
  FROM comenzi c
  LEFT JOIN sesiuni_qr sqr ON sqr.id=c.id_sesiune
  $whereSql
  ORDER BY c.id DESC
  LIMIT $limit OFFSET $offset
");
$st->execute($args);
$orders = $st->fetchAll(PDO::FETCH_ASSOC);

// Detalii pentru fiecare comandă
if ($orders) {
  $ids = array_column($orders,'id');
  $ph = implode(',', array_fill(0, count($ids), '?'));
  $sd = $pdo_qr->prepare("
    SELECT id_comanda,cod_produs,denumire_snapshot,cantitate,cota_tva,pret_cu_tva,
           valoare_fara_tva,valoare_tva,valoare_cu_tva,optiuni_json
    FROM detalii_comenzi
    WHERE id_comanda IN ($ph)
    ORDER BY id ASC
  ");
  $sd->execute($ids);
  while ($r=$sd->fetch(PDO::FETCH_ASSOC)) { $detailsByOrder[(int)$r['id_comanda']][] = $r; }
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Comenzi QR → Import în POS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body.admin-page{ font-size:1.1rem; }
    .page-header{ background:linear-gradient(90deg,#6f42c1,#0d6efd); color:#fff; padding:18px; }
    .header-badge{ font-weight:700; }
    .badge-status{ font-size:.9rem; }
    .nowrap{ white-space:nowrap; }
    .cursor-pointer{ cursor:pointer; }
    .order-details{ background:#fff; }
  </style>
</head>
<body class="admin-page">
<header class="page-header">
  <div class="container d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <a href="vanzare_restaurant.php" class="btn btn-light btn-sm border">← Înapoi</a>
      <h1 class="m-0 fs-2">Comenzi QR</h1>
      <span class="badge bg-warning text-dark header-badge">Active QR: <?= (int)$activeCount; ?></span>
    </div>
  </div>
</header>

<div class="container my-3 my-md-4">
  <?php if($message): ?><div class="alert alert-success"><?=h($message)?></div><?php endif; ?>
  <?php if($error):   ?><div class="alert alert-danger"><?=h($error)?></div><?php endif; ?>

  <form class="row gy-2 gx-2 align-items-end mb-3" method="get" action="vanzare_importa_comanda_qr.php">
    <div class="col-auto">
      <label class="form-label mb-1" for="stare">Stare</label>
      <select id="stare" name="stare" class="form-select">
        <?php
          $stari=['toate'=>'Toate','cos'=>'Coș','plasata'=>'Plasată','aprobata'=>'Aprobată','in_preparare'=>'În preparare','pregatita'=>'Pregătită','servita'=>'Servită','platita'=>'Plătită','anulata'=>'Anulată'];
          foreach($stari as $val=>$lbl){ $sel=$stareFilter===$val?'selected':''; echo "<option value='".h($val)."' $sel>".h($lbl)."</option>"; }
        ?>
      </select>
    </div>
    <div class="col-auto">
      <button class="btn btn-primary" type="submit">Aplică filtre</button>
      <a class="btn btn-outline-secondary" href="vanzare_importa_comanda_qr.php">Reset</a>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-hover align-middle bg-white">
      <thead class="table-light">
        <tr>
          <th class="text-center">ID</th>
          <th>Data</th>
          <th class="text-center">Masă</th>
          <th class="text-end">Fără TVA</th>
          <th class="text-end">TVA</th>
          <th class="text-end">Total</th>
          <th class="text-center">Stare</th>
          <th>Acțiuni</th>
        </tr>
      </thead>
      <tbody>
        <?php if(!$orders): ?>
          <tr><td colspan="9" class="text-center py-4 text-muted">Nu există comenzi pentru filtrul curent.</td></tr>
        <?php else: foreach($orders as $o):
          $id=(int)$o['id']; $masaCurenta=(int)($o['cod_masa']??0);
        ?>
          <tr class="cursor-pointer" data-order-id="<?=$id?>" title="Vezi detalii">
            <td class="text-center fw-semibold">#<?=$id?></td>
            <td><span class="nowrap"><?=h($o['creat_la'])?></span></td>
            <td class="text-center"><?=$masaCurenta?></td>
            <td class="text-end"><?=money_ro($o['total_fara_tva'])?> RON</td>
            <td class="text-end"><?=money_ro($o['total_tva'])?> RON</td>
            <td class="text-end fw-bold"><?=money_ro($o['total_cu_tva'])?> RON</td>
            <td class="text-center"><span class="badge badge-status <?=status_badge_class($o['stare'])?>"><?=h($o['stare'])?></span></td>        
            <td>
              <button type="button" class="btn btn-outline-primary btn-sm me-2"
                      onclick="event.stopPropagation(); openOrderModal(<?= (int)$id ?>);">
                🔎 Deschide
              </button>
              <form class="d-inline" method="post" action="vanzare_importa_comanda_qr.php"
                    onsubmit="return confirm('Sigur anulezi comanda #<?=$id?> ?');">
                <input type="hidden" name="action" value="cancel_order">
                <input type="hidden" name="id_comanda" value="<?=$id?>">
                <input type="hidden" name="csrf" value="<?=h($csrf)?>">
                <button class="btn btn-outline-danger btn-sm">❌ Șterge</button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <?php if($orders): foreach($orders as $o):
    $id=(int)$o['id']; $items=$detailsByOrder[$id]??[]; $masaCurenta=(int)($o['cod_masa']??0); ?>
    <div id="order-details-<?=$id?>" class="d-none">
      <div class="order-details p-1 p-sm-2">
        <div class="d-flex flex-column flex-md-row align-items-md-start justify-content-between">
          <div class="mb-2 mb-md-0">
            <div class="small text-muted">Data: <span class="nowrap"><?=h($o['creat_la'])?></span></div>
            <?php if(!empty($o['observatii'])): ?><div class="text-muted small mt-1">Observații: <?=nl2br(h($o['observatii']))?></div><?php endif; ?>
          </div>
          <div class="text-md-end">
            <div>Total: <strong><?=money_ro($o['total_cu_tva'])?> RON</strong></div>
            <small class="text-muted">Fără TVA: <?=money_ro($o['total_fara_tva'])?> • TVA: <?=money_ro($o['total_tva'])?></small>
          </div>
        </div>

        <?php if(!$items): ?>
          <div class="mt-3 text-muted">Nu există linii pentru această comandă.</div>
        <?php else: ?>
          <div class="table-responsive mt-3">
            <table class="table table-sm">
              <thead>
                <tr>
                  <th>Produs</th><th class="text-center">Cant</th><th class="text-center">TVA</th>
                  <th class="text-end">Preț (cu TVA)</th><th class="text-end">Valoare fără TVA</th>
                  <th class="text-end">TVA</th><th class="text-end">Valoare cu TVA</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($items as $it):
                  $obs=''; if(!empty($it['optiuni_json'])){ $opt=json_decode((string)$it['optiuni_json'],true); if(is_array($opt)&&!empty($opt['observatie_produs'])){ $obs=(string)$opt['observatie_produs']; } }
                ?>
                  <tr>
                    <td><?=h($it['denumire_snapshot'])?><?php if($obs!==''): ?><div class="small text-muted">Obs: <?=nl2br(h($obs))?></div><?php endif; ?></td>
                    <td class="text-center"><?=(float)$it['cantitate']?></td>
                    <td class="text-center"><?=(int)$it['cota_tva']?>%</td>
                    <td class="text-end"><?=money_ro($it['pret_cu_tva'])?> RON</td>
                    <td class="text-end"><?=money_ro($it['valoare_fara_tva'])?> RON</td>
                    <td class="text-end"><?=money_ro($it['valoare_tva'])?> RON</td>
                    <td class="text-end fw-semibold"><?=money_ro($it['valoare_cu_tva'])?> RON</td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

        <div class="mt-3">
          <label class="form-label small mb-1">Alege Masă</label>
          <select class="form-select" name="cod_masa_target" required>
            <option value="">— alege masa —</option>
            <?php
              $coduriMese = array_map(fn($r)=>(int)$r['cod_masa'], $meseFull);
              $preselect = in_array($masaCurenta, $coduriMese, true) ? $masaCurenta : null;
              if($meseFull){
                foreach($meseFull as $m){
                  $code=(int)$m['cod_masa'];
                  $label=$m['label'] ?? ("Masa ".$code);
                  $sel = ($preselect !== null && $code === $preselect) ? 'selected' : '';
                  echo "<option value='{$code}' {$sel}>".h($label)."</option>";
                }
              } else {
                if($masaCurenta>0){ echo "<option value='{$masaCurenta}' selected>Masa {$masaCurenta}</option>"; }
              }
            ?>
          </select>
        </div>
      </div>
    </div>
  <?php endforeach; endif; ?>

  <?php if($totalPages>1): ?>
    <nav aria-label="Paginare comenzi" class="mt-3">
      <ul class="pagination justify-content-center">
        <?php $base='vanzare_importa_comanda_qr.php?stare='.urlencode($stareFilter); ?>
        <li class="page-item <?=$page<=1?'disabled':''?>"><a class="page-link" href="<?=$page<=1?'#':$base.'&page='.( $page-1) ?>">Anterior</a></li>
        <?php $start=max(1,$page-3); $end=min($totalPages,$page+3); for($p=$start;$p<=$end;$p++): ?>
          <li class="page-item <?=$p===$page?'active':''?>"><a class="page-link" href="<?=$base.'&page='.$p?>"><?=$p?></a></li>
        <?php endfor; ?>
        <li class="page-item <?=$page>=$totalPages?'disabled':''?>"><a class="page-link" href="<?=$base.'&page='.( $page+1) ?>">Următor</a></li>
      </ul>
    </nav>
  <?php endif; ?>
</div>

<footer class="text-center text-muted my-4">&copy; <?=date('Y')?> • Powered by AGE Creative Solutions.</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Modal -->
<div class="modal fade" id="orderModal" tabindex="-1" aria-labelledby="orderModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="orderModalLabel">Detalii comandă</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Închide"></button>
      </div>

      <form id="importForm" method="post" action="vanzare_importa_comanda_qr.php">
        <div class="modal-body" id="orderModalBody"></div>
        <div class="modal-footer">
          <input type="hidden" name="action" value="import_order">
          <input type="hidden" name="id_comanda" value="">
          <input type="hidden" name="csrf" value="<?=h($csrf)?>">
          <button id="importSubmitBtn" class="btn btn-outline-primary" type="submit">⬇️ Importă comanda (creează notă nouă)</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Închide</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  // Auto-refresh (nu reîncarcă dacă modalul e deschis)
  (function(){
    const REFRESH_MS=20000; let timer=null;
    function schedule(){
      clearTimeout(timer);
      timer=setTimeout(()=>{
        const shown=document.querySelector('#orderModal.show');
        if(document.visibilityState==='visible' && !shown){ location.reload(); } else { schedule(); }
      }, REFRESH_MS);
    }
    document.addEventListener('visibilitychange',schedule);
    window.addEventListener('focus',schedule);
    window.addEventListener('pageshow',schedule);
    schedule();
  })();

  // Deschidere modal + setare ID în formular pentru QR
  document.addEventListener('click', (ev)=>{
    const tr=ev.target.closest('tr[data-order-id]'); if(!tr) return;
    if(ev.target.closest('form,button,a,input,label')) return;
    openOrderModal(tr.dataset.orderId);
  }, {passive:true});

  function openOrderModal(orderId){
    const bodyEl=document.getElementById('orderModalBody');
    const tpl=document.getElementById('order-details-'+orderId);
    if(!tpl) return;
    bodyEl.innerHTML=tpl.innerHTML;
    document.getElementById('orderModalLabel').textContent='Importă Comanda QR #'+orderId+' → notă nouă';

    const form=document.getElementById('importForm');
    form.action='vanzare_importa_comanda_qr.php';
    form.querySelector('input[name="action"]').value='import_order';
    form.querySelector('input[name="id_comanda"]').value=orderId;
    document.getElementById('importSubmitBtn').textContent='⬇️ Importă comanda (creează notă nouă)';

    bootstrap.Modal.getOrCreateInstance(document.getElementById('orderModal')).show();
  }
</script>
</body>
</html>
