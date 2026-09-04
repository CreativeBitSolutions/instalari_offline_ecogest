<?php
// load_prod.php (VERSIUNE FĂRĂ CACHE)

include('session.php');

// EROARE RAPORTARE (pentru debugging, dacă e necesar)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.log');
error_reporting(E_ALL);

// === ADAUGAT: INSTRUCTIUNI STRICTE PENTRU A PREVENI ORICE FEL DE CACHE ===
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // O dată din trecut
header("Pragma: no-cache");
// === SFARSIT BLOC ADAUGAT ===

// -- Parametrii request-ului --
$categ      = $_GET['categ'] ?? 'all';
$limit      = isset($_GET['limit']) ? (int)$_GET['limit'] : 40;
$page       = isset($_GET['page'])  ? (int)$_GET['page']  : 1;
$searchTerm = $_GET['search'] ?? '';
$offset     = ($page - 1) * $limit;
// -- Pregătire interogare SQL --
$query_conditions = [];
$params = [];

if ($categ !== 'all') {
    $query_conditions[] = "n.id_categorie = :categ";
    $params[':categ'] = $categ;
}

$searchTerm = trim($searchTerm ?? '');
$clientIdSession = (int)($_SESSION['client_id'] ?? 0);
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

if ($searchTerm !== '') {
    if ($clientIdSession === 18 && preg_match('/^\d/', $searchTerm)) {
        // ➜ Client 18 + începe cu cifră: caută DOAR după cod de bare (match exact)
        $params[':search_term'] = $searchTerm;

        // Normalizare (acceptă coduri cu spații/liniuțe)
        $searchDigits = preg_replace('/\D+/', '', $searchTerm);
        if ($searchDigits !== '') {
            $query_conditions[] = "(
                REPLACE(REPLACE(n.cod_bare, ' ', ''), '-', '') = :search_digits
                OR n.cod_bare = :search_term
            )";
            $params[':search_digits'] = $searchDigits;
        } else {
            $query_conditions[] = "n.cod_bare = :search_term";
        }
    } else {
        // ➜ Toți ceilalți (sau nu începe cu cifră): comportament vechi (LIKE)
        $query_conditions[] = "(n.nume LIKE :search_term OR n.cod_bare LIKE :search_term)";
        $params[':search_term'] = '%' . $searchTerm . '%';
    }
}


$where_clause = "WHERE g.denumire_gestiune NOT IN ('MATERII', 'CONSUMABILE', 'MATERIALE AUXILIARE') AND n.activ = 1";
if (!empty($query_conditions)) {
    $where_clause .= " AND " . implode(' AND ', $query_conditions);
}


// -- Interogarea SQL directă --
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
                n.cod_bare
            FROM $tabel_final_nomenclator n
            JOIN $tabel_final_categorii c ON n.id_categorie = c.id_categorie 
            JOIN gestiuni g ON n.id_gestiune = g.id_gestiune 
            {$where_clause}
            ORDER BY n.nume ASC
            LIMIT :limit OFFSET :offset";
            
$ppckstmt = $pdo->prepare($ppcksql);

foreach ($params as $key => &$val) {
    $ppckstmt->bindParam($key, $val, PDO::PARAM_STR);
}
$ppckstmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$ppckstmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$ppckstmt->execute();

// -- Generare și afișare HTML direct, fără buffer --
while ($row = $ppckstmt->fetch(PDO::FETCH_ASSOC)) {
    $den_p   = $row['nume'];
    $cod_p   = $row['cod_produs'];
    $pret    = $row['pret_cu_tva'];
    $gest    = $row['denumire_gestiune'];
    $um_raw  = $row['um'];
    $um_attr = ($um_raw == 'H87' || empty($um_raw)) ? 'buc' : htmlspecialchars($um_raw, ENT_QUOTES);

    echo "
    <div class='product-card adaug_prod' 
         value='{$cod_p}' 
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
    </div>";
}
exit;
?>
