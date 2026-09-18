<?php
// nir_cu_discount_aplica_discount.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesiune invalidă.']);
    exit;
}

include 'db.php';

// Funcție pentru recalcularea și actualizarea totalurilor în tabela NIR
function actualizeaza_nir($pdo, $nr_nir) {
    $sql_sum = "
        SELECT 
            SUM(valoare_achizitie) AS total_valoare_fara_tva,
            SUM(valoare_tva_achizitie) AS total_tva_ded,
            SUM(valoare_adaos) AS total_ad_unit,
            SUM(tva_adaos_comercial) AS total_tva_neex_ad_com
        FROM achizitii
        WHERE nr_nir = :nr_nir
    ";
    $stmt_sum = $pdo->prepare($sql_sum);
    $stmt_sum->execute(['nr_nir' => $nr_nir]);
    $sum_data = $stmt_sum->fetch(PDO::FETCH_ASSOC);

    $total_valoare_fara_tva = $sum_data['total_valoare_fara_tva'] ?? 0;
    $total_tva_ded = $sum_data['total_tva_ded'] ?? 0;
    $total_ad_unit = $sum_data['total_ad_unit'] ?? 0;
    $total_tva_neex_ad_com = $sum_data['total_tva_neex_ad_com'] ?? 0;
    $valoare_totala = $total_valoare_fara_tva + $total_ad_unit;
    $valoare_totala_cu_tva = $valoare_totala + $total_tva_ded + $total_tva_neex_ad_com;

    $sql_update_nir = "
        UPDATE nir SET
            ad_com_total = :ad_com_total, tva_neex_ad_com = :tva_neex_ad_com,
            tva_ded = :tva_ded, val_nir_ftva = :val_nir_ftva,
            valoare_totala = :valoare_totala, valoare_totala_cu_tva = :valoare_totala_cu_tva
        WHERE nr_nir = :nr_nir
    ";
    $stmt_update_nir = $pdo->prepare($sql_update_nir);
    $stmt_update_nir->execute([
        'ad_com_total' => $total_ad_unit, 'tva_neex_ad_com' => $total_tva_neex_ad_com,
        'tva_ded' => $total_tva_ded, 'val_nir_ftva' => $total_valoare_fara_tva,
        'valoare_totala' => $valoare_totala, 'valoare_totala_cu_tva' => $valoare_totala_cu_tva,
        'nr_nir' => $nr_nir
    ]);
}

header('Content-Type: application/json');

$nr_nir = isset($_POST['nr_nir']) ? $_POST['nr_nir'] : null;
$discount_total = isset($_POST['discount_value']) ? (float)$_POST['discount_value'] : 0;
// Preluarea noului parametru care indică dacă se actualizează prețul în catalog
$update_preturi_catalog = isset($_POST['update_preturi_catalog']) && $_POST['update_preturi_catalog'] == '1';

if (empty($nr_nir) || $discount_total <= 0) {
    echo json_encode(['success' => false, 'message' => 'Valoarea discountului este invalidă sau NIR-ul nu este identificat.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // MODIFICARE 1: Calculează totalul de achiziție doar pentru produsele care NU sunt din gestiuni SGR.
    $sql_total = "
        SELECT SUM(a.valoare_achizitie) as total 
        FROM achizitii a
        JOIN produse_servicii ps ON a.cod_p = ps.cod_produs
        JOIN gestiuni g ON ps.id_gestiune = g.id_gestiune
        WHERE a.nr_nir = :nr_nir AND g.denumire_gestiune NOT LIKE '%SGR%'
    ";
    $stmt_total = $pdo->prepare($sql_total);
    $stmt_total->execute(['nr_nir' => $nr_nir]);
    $total_achizitie = (float)$stmt_total->fetchColumn();

    if ($total_achizitie == 0) {
        throw new Exception("Nu există produse eligibile pe acest NIR pentru a aplica discountul (produsele SGR sunt excluse).");
    }
    if ($discount_total > $total_achizitie) {
        throw new Exception("Discountul nu poate fi mai mare decât valoarea totală de achiziție a produselor eligibile.");
    }

    // MODIFICARE 2: Selectează pentru actualizare doar produsele care NU sunt din gestiuni SGR.
    $sql_produse = "
        SELECT a.*
        FROM achizitii a
        JOIN produse_servicii ps ON a.cod_p = ps.cod_produs
        JOIN gestiuni g ON ps.id_gestiune = g.id_gestiune
        WHERE a.nr_nir = :nr_nir AND g.denumire_gestiune NOT LIKE '%SGR%'
    ";
    $stmt_produse = $pdo->prepare($sql_produse);
    $stmt_produse->execute(['nr_nir' => $nr_nir]);
    $produse_achizitionate = $stmt_produse->fetchAll(PDO::FETCH_ASSOC);

    foreach ($produse_achizitionate as $produs) {
        $id_achiz = $produs['id_achiz'];
        $cantitate = (float)$produs['cantitate'];
        
        if ($cantitate <= 0) continue;

        $proportie = (float)$produs['valoare_achizitie'] / $total_achizitie;
        $discount_produs = $discount_total * $proportie;
        $discount_unitar = $discount_produs / $cantitate;
        $pret_unitar_nou = (float)$produs['pret_unitar_achizitie'] - $discount_unitar;

        $cota_tva_achiz = (int)$produs['cota_tva'];
        
        $sql_fetch_produs = "SELECT g.denumire_gestiune, ps.pret_cu_tva AS pret_vanzare FROM produse_servicii ps INNER JOIN gestiuni g ON ps.id_gestiune = g.id_gestiune WHERE ps.cod_produs = :cod_p";
        $stmt_fetch_produs = $pdo->prepare($sql_fetch_produs);
        $stmt_fetch_produs->execute(['cod_p' => $produs['cod_p']]);
        $produs_data_db = $stmt_fetch_produs->fetch(PDO::FETCH_ASSOC);
        $den_gestiune = $produs_data_db['denumire_gestiune'];
        $pret_vanzare = (float)($produs_data_db['pret_vanzare'] ?? 0);

        $pret_achiz = $pret_unitar_nou;
        $valoare_achizitie = $cantitate * $pret_achiz;
        $tva_unitar_achizitie = $pret_achiz * ($cota_tva_achiz / 100);
        $valoare_tva_achizitie = $cantitate * $tva_unitar_achizitie;
        $valoare_achizitie_cu_tva = $valoare_achizitie + $valoare_tva_achizitie;
        
        $procent_adaos = 0.00; $adaos_unitar = 0.00;

        if ($den_gestiune == 'MARFURI' && $pret_vanzare > 0) {
            $base = $pret_achiz * (1 + ($cota_tva_achiz / 100));
            if ($base > 0) {
                $procent_adaos = (($pret_vanzare / $base) - 1) * 100;
                if ($procent_adaos < 0) $procent_adaos = 0;
            }
            $adaos_unitar = $pret_achiz * ($procent_adaos / 100);
        }
        
        $valoare_adaos = $cantitate * $adaos_unitar;
        $pret_cu_adaos_unitar_fara_tva = $pret_achiz + $adaos_unitar;
        $tva_adaos_comercial = $adaos_unitar * ($cota_tva_achiz / 100);
        $pret_unitar_cu_amanuntul_cu_tva = $pret_achiz + $tva_unitar_achizitie + $adaos_unitar + $tva_adaos_comercial;
        $valoare_pret_amanunt = $cantitate * $pret_unitar_cu_amanuntul_cu_tva;
        $tva_total_unitar = $tva_unitar_achizitie + $tva_adaos_comercial;
        $valoare_tva_totala = $cantitate * $tva_total_unitar;

        $sql_update_achizitie = "UPDATE achizitii SET
            pret_unitar_achizitie = :pret_unitar_achizitie, valoare_achizitie = :valoare_achizitie,
            tva_unitar_achizitie = :tva_unitar_achizitie, valoare_tva_achizitie = :valoare_tva_achizitie,
            valoare_achizitie_cu_tva = :valoare_achizitie_cu_tva, procent_adaos = :procent_adaos,
            adaos_unitar = :adaos_unitar, valoare_adaos = :valoare_adaos,
            pret_cu_adaos_unitar_fara_tva = :pret_cu_adaos_unitar_fara_tva, tva_adaos_comercial = :tva_adaos_comercial,
            pret_unitar_cu_amanuntul_cu_tva = :pret_unitar_cu_amanuntul_cu_tva, valoare_pret_amanunt = :valoare_pret_amanunt,
            tva_total_unitar = :tva_total_unitar, valoare_tva_totala = :valoare_tva_totala
        WHERE id_achiz = :id_achiz";
        $stmt_update_achizitie = $pdo->prepare($sql_update_achizitie);
        $stmt_update_achizitie->execute([
            'pret_unitar_achizitie' => $pret_achiz, 'valoare_achizitie' => $valoare_achizitie,
            'tva_unitar_achizitie' => $tva_unitar_achizitie, 'valoare_tva_achizitie' => $valoare_tva_achizitie,
            'valoare_achizitie_cu_tva' => $valoare_achizitie_cu_tva, 'procent_adaos' => $procent_adaos,
            'adaos_unitar' => $adaos_unitar, 'valoare_adaos' => $valoare_adaos,
            'pret_cu_adaos_unitar_fara_tva' => $pret_cu_adaos_unitar_fara_tva, 'tva_adaos_comercial' => $tva_adaos_comercial,
            'pret_unitar_cu_amanuntul_cu_tva' => $pret_unitar_cu_amanuntul_cu_tva, 'valoare_pret_amanunt' => $valoare_pret_amanunt,
            'tva_total_unitar' => $tva_total_unitar, 'valoare_tva_totala' => $valoare_tva_totala,
            'id_achiz' => $id_achiz
        ]);

        $sql_update_miscari = "UPDATE miscari SET pu = :pu WHERE id_achiz = :id_achiz AND fel_doc = 'NIR'";
        $stmt_update_miscari = $pdo->prepare($sql_update_miscari);
        $stmt_update_miscari->execute(['pu' => $pret_achiz, 'id_achiz' => $id_achiz]);

        // **BLOC NOU: Actualizează prețul de achiziție în catalogul de produse dacă utilizatorul a fost de acord**
        if ($update_preturi_catalog) {
            $sql_update_catalog = "UPDATE produse_servicii SET pret_achizitie = :pret_achizitie WHERE cod_produs = :cod_produs";
            $stmt_update_catalog = $pdo->prepare($sql_update_catalog);
            $stmt_update_catalog->execute([
                'pret_achizitie' => $pret_achiz,
                'cod_produs'     => $produs['cod_p']
            ]);
        }
    }

    $sql_update_nir_discount = "UPDATE nir SET reducere_comerciala = IFNULL(reducere_comerciala, 0) + :discount_total WHERE nr_nir = :nr_nir";
    $stmt_update_nir_discount = $pdo->prepare($sql_update_nir_discount);
    $stmt_update_nir_discount->execute(['discount_total' => $discount_total, 'nr_nir' => $nr_nir]);

    actualizeaza_nir($pdo, $nr_nir);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Discountul global a fost aplicat cu succes!']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500); 
    echo json_encode(['success' => false, 'message' => 'Eroare la aplicarea discountului: ' . $e->getMessage()]);
}
?>