<?php
// ajax_meniuri_list.php
// Returnează carduri .product-card (identice vizual cu load_prod.php) pentru MENIURI,
// cu paginare + filtrare după nume/descriere.

include('session.php'); // expune $pdo

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.log');
error_reporting(E_ALL);

header('Content-Type: text/html; charset=utf-8');

$limit      = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 40;
$page       = isset($_GET['page'])  ? max(1, (int)$_GET['page'])  : 1;
$searchTerm = trim($_GET['search'] ?? '');
$offset     = ($page - 1) * $limit;

// 1) Meniuri active + total (sumă cantitate*preț din conținut)
$sql = "
  SELECT m.cod_meniu, m.nume_meniu, m.descriere_meniu,
         COALESCE((
            SELECT SUM(mc.cantitate * mc.pret_vanzare)
            FROM meniuri_continut mc
            WHERE mc.cod_meniu = m.cod_meniu
         ), 0) AS total
  FROM meniuri m
  WHERE m.activ = 1
";
$params = [];
if ($searchTerm !== '') {
  $sql .= " AND (m.nume_meniu LIKE :q OR m.descriere_meniu LIKE :q) ";
  $params[':q'] = "%{$searchTerm}%";
}
$sql .= " ORDER BY m.cod_meniu DESC LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
  $stmt->bindValue($k, $v, PDO::PARAM_STR);
}
$stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$rows) {
  echo ''; // nimic -> front-end va afișa "Nu sunt produse." când e cazul
  exit;
}

// 2) Conținut pentru meniurile din această pagină (tooltips)
$ids = array_column($rows, 'cod_meniu');
$byMenu = [];
if ($ids) {
  $ph = implode(',', array_fill(0, count($ids), '?'));
  $q2 = "
    SELECT mc.cod_meniu, mc.cod_produs, mc.cantitate, mc.pret_vanzare,
           ps.nume, ps.um
    FROM meniuri_continut mc
    JOIN produse_servicii ps ON ps.cod_produs = mc.cod_produs
    WHERE mc.cod_meniu IN ($ph)
    ORDER BY mc.id_continut_meniu ASC
  ";
  $st2 = $pdo->prepare($q2);
  $st2->execute($ids);
  while ($r = $st2->fetch(PDO::FETCH_ASSOC)) {
    $byMenu[$r['cod_meniu']][] = $r;
  }
}

// 3) Randare carduri exact ca în load_prod.php (class .product-card)
foreach ($rows as $m) {
  $lista = $byMenu[$m['cod_meniu']] ?? [];
  $total_fmt = number_format((float)$m['total'], 2, '.', '');

  // Tooltip cu conținutul
  $tooltipItems = [];
  foreach ($lista as $it) {
    $um = ($it['um'] === 'H87' || !$it['um']) ? 'buc' : $it['um'];
    $cant = rtrim(rtrim(number_format((float)$it['cantitate'], 4, '.', ''), '0'), '.');
    $tooltipItems[] =
      $it['nume'] .
      " — x {$cant} {$um} @ " .
      number_format((float)$it['pret_vanzare'], 2, '.', '') .
      " RON";
  }
  $tooltip = htmlspecialchars(implode("\n", $tooltipItems), ENT_QUOTES);

  echo "
    <div class='product-card adaug_meniu'
         role='button'
         tabindex='0'
         style='cursor:pointer'
         data-cod-meniu='{$m['cod_meniu']}'
         title='{$tooltip}'>
      <div class='product-name'>".htmlspecialchars($m['nume_meniu'])."</div>
      <div class='product-price'>{$total_fmt} RON / meniu</div>
    </div>
  ";
}
