<?php //load_prod_restaurant.php
session_start();
include('database_connection.php');
$nr_bon = $_SESSION['nr_bon'];
if (!isset($_GET['categ'])) exit;

/* all / id_categorie */
$categ = $_GET['categ'];
$where = ($categ == 'all') ? "" : "AND c.id_categorie = :cat";

// 1. Am adaugat ps.ask_obs in SELECT
$sql = "SELECT ps.cod_produs,
        ps.nume,
        ps.um,
        ps.pret_cu_tva,
        ps.cod_bare,
        ps.ask_obs 
        FROM produse_servicii ps
        JOIN categorii c ON c.id_categorie = ps.id_categorie
        JOIN categorii_locatii cl ON cl.id_categorie = c.id_categorie
        WHERE c.se_vinde = 1
        AND cl.cod_locatie = :loc
        AND ps.activ = :activ
        $where
        GROUP BY ps.cod_produs
        ORDER BY ps.nume ASC";

$stmt = $pdo->prepare($sql);
$params = [
    ':loc' => $_SESSION['cod_locatie'],
    ':activ' => 1
];
if ($where) {
    $params[':cat'] = $categ;
}
$stmt->execute($params);

while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $cod = $r['cod_produs'];
    $nume = $r['nume'];
    $pret = number_format($r['pret_cu_tva'],2);
    $um = $r['um'];
    // Validam ask_obs sa fie 0 sau 1
    $ask_obs = isset($r['ask_obs']) ? $r['ask_obs'] : 0;

    // 2. Am adaugat atributul data-ask-obs in HTML
    $nume_esc = htmlspecialchars($nume, ENT_QUOTES, 'UTF-8');
    $nume_search = htmlspecialchars(strtolower($nume), ENT_QUOTES, 'UTF-8');
    echo "
    <div class='product-card adaug_prod'
         value='$cod'
         data-value='$nr_bon'
         data-ask-obs='$ask_obs'
         data-search='$nume_search'>
        <div class='product-name'>$nume_esc</div>
        <div class='product-price'>$pret&nbsp;RON</div>
    </div>";
}
echo '<link rel="stylesheet" href="vanzare_css.css">';
?>