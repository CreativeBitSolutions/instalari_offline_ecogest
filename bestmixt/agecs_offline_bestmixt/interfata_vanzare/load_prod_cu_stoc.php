<?php
// load_prod_cu_stoc.php — FĂRĂ CACHE

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.log');
error_reporting(E_ALL);

include('session.php');

// === ADAUGAT: INSTRUCTIUNI STRICTE PENTRU A PREVENI ORICE FEL DE CACHE ===
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // O dată din trecut
header("Pragma: no-cache");
// === SFARSIT BLOC ADAUGAT ===

// ── Parametri request
$categ      = $_GET['categ'] ?? 'all';
$limit      = isset($_GET['limit']) ? (int)$_GET['limit'] : 40;
$page       = isset($_GET['page'])  ? (int)$_GET['page']  : 1;
$searchTerm = $_GET['search'] ?? '';

// ── Context client/locație (isolare cache server-side)
$client_id     = $_SESSION['client_id']        ?? 'anon';
$cod_locatie   = $_SESSION['cod_locatie']      ?? '0';
$vanz_sub_stoc = $_SESSION['vanzare_sub_stoc'] ?? null;


$offset = ($page - 1) * $limit;

$query_conditions = [];
$params = [];

if ($categ !== 'all') {
    $query_conditions[] = "n.id_categorie = :categ";
    $params[':categ'] = $categ;
}

if (!empty($searchTerm)) {
    $query_conditions[] = "(n.nume LIKE :search_term OR n.cod_bare LIKE :search_term)";
    $params[':search_term'] = '%' . $searchTerm . '%';
}

$where_clause = "WHERE g.denumire_gestiune NOT IN ('MATERII', 'CONSUMABILE', 'MATERIALE AUXILIARE') AND n.activ = 1";
if (!empty($query_conditions)) {
    $where_clause .= " AND " . implode(' AND ', $query_conditions);
}

$ppcksql = "SELECT 
                g.denumire_gestiune, 
                n.cod_produs, 
                n.um, 
                n.pret_cu_tva, 
                n.cota_tva,
                n.sgr,
                n.sgr_pet,
                n.sgr_alumin,
                n.sgr_sticla,
                n.nume, 
                n.cod_bare,
                COALESCE(sp.cantitate_stoc, 0) AS cantitate_stoc
            FROM $tabel_final_nomenclator n
            JOIN $tabel_final_categorii c ON n.id_categorie = c.id_categorie 
            JOIN gestiuni g ON n.id_gestiune = g.id_gestiune 
            LEFT JOIN stoc_produse sp ON sp.cod_p = n.cod_produs 
            {$where_clause}
            GROUP BY n.cod_produs 
            ORDER BY n.nume ASC
            LIMIT :limit OFFSET :offset";

$ppckstmt = $pdo->prepare($ppcksql);

foreach ($params as $key => &$val) {
    $ppckstmt->bindParam($key, $val, PDO::PARAM_STR);
}

$ppckstmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$ppckstmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$ppckstmt->execute();

while ($row = $ppckstmt->fetch(PDO::FETCH_ASSOC)) {
    $den_p   = $row['nume'];
    $cod_p   = $row['cod_produs'];
    $pret    = $row['pret_cu_tva'];
    $um_raw  = $row['um'];
    $um_attr = ($um_raw == 'H87' || empty($um_raw)) ? 'buc' : htmlspecialchars($um_raw, ENT_QUOTES);
    $stoc    = $row['cantitate_stoc'];
    $gest    = $row['denumire_gestiune'];

    $card_class    = 'adaug_prod';
    $disabled_attr = '';
    $card_style    = '';
    
    if (($_SESSION['vanzare_sub_stoc'] ?? 0) == 0 && $stoc <= 0) {
        $card_class    = 'disabled';
        $disabled_attr = 'disabled';
        $card_style    = 'background-color: #f8d7da; cursor: not-allowed;';
    }

    echo "
    <div class='product-card {$card_class}' 
         value='{$cod_p}' 
         style='{$card_style}' 
         {$disabled_attr}
         data-nume='" . htmlspecialchars($den_p, ENT_QUOTES) . "'
         data-pret='{$row['pret_cu_tva']}'
         data-tva='{$row['cota_tva']}'
         data-sgr='{$row['sgr']}'
         data-sgr-pet='{$row['sgr_pet']}'
         data-sgr-alumin='{$row['sgr_alumin']}'
         data-sgr-sticla='{$row['sgr_sticla']}'
         data-gestiune='" . htmlspecialchars($gest, ENT_QUOTES) . "'
         data-um='{$um_attr}'
    >
        <div class='product-name'>" . htmlspecialchars($den_p) . "</div>
        <div class='product-price'>{$pret} RON / {$um_attr}</div>
        <div class='product-stock' style='font-weight:bold;margin-top:5px;color:" . ($stoc > 0 ? '#28a745' : '#dc3545') . ";'>
            Stoc: {$stoc}
        </div>
    </div>";
}

exit;
?>