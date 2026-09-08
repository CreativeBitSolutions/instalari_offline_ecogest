<?php
// get_produs_factura.php

header('Content-Type: application/json'); // Setarea header-ului pentru răspuns JSON

require_once __DIR__.'/database_connection.php';

if (isset($_GET['q'])) {
    $q = trim((string)$_GET['q']);
    $response = [];

    // Am modificat interogarea SQL pentru a calcula și prețul de achiziție cu TVA
    // Formula: pret_achizitie * (1 + cota_tva / 100.0)
    $sql_produse = "
        SELECT 
            um, 
            pret_cu_tva, 
            cota_tva, 
            nume, 
            pret_achizitie,
            (pret_achizitie * (1 + cota_tva / 100.0)) AS pret_achizitie_calculat 
        FROM produse_servicii 
        WHERE cod_produs = :cod_produs
    ";
    
    $stmt_produse = $pdo->prepare($sql_produse);
    $stmt_produse->execute(['cod_produs' => $q]);
    $product = $stmt_produse->fetch(PDO::FETCH_ASSOC);

    if ($product) {
        // Preia prețul de achiziție calculat și îl formatează
        $pret_achizitie_final = null;
        if (isset($product['pret_achizitie_calculat'])) {
            $pret_achizitie_final = number_format((float)$product['pret_achizitie_calculat'], 2, '.', '');
        }

        $response = [
            'nume_produs' => $product['nume'],
            'um' => $product['um'],
            'pret_cu_tva' => $product['pret_cu_tva'],
            'cota_tva' => $product['cota_tva'],
            // Trimitem prețul de achiziție cu TVA inclus sub cheia 'pret_achizitie'
            // pentru a menține compatibilitatea cu JavaScript-ul din factura.php
            'pret_achizitie' => $pret_achizitie_final
        ];
        
    } else {
        // Dacă produsul nu este găsit în 'produse_servicii', se caută în 'vanzari'
        // Aici nu avem preț de achiziție, deci logica rămâne neschimbată
        $sql_vanzari = "SELECT um, pret_vanzare AS pret_cu_tva, cota_tva FROM vanzari WHERE cod_p = :cod_produs ORDER BY id_vanz DESC LIMIT 1";
        $stmt_vanzari = $pdo->prepare($sql_vanzari);
        $stmt_vanzari->execute(['cod_produs' => $q]);
        $sale_item = $stmt_vanzari->fetch(PDO::FETCH_ASSOC);
        
        if ($sale_item) {
             $response = [
                 'um' => $sale_item['um'],
                 'pret_cu_tva' => $sale_item['pret_cu_tva'],
                 'cota_tva' => $sale_item['cota_tva'],
                 'pret_achizitie' => null // Prețul de achiziție nu este disponibil aici
             ];
        }
    }

    if (!empty($response)) {
        echo json_encode($response);
    } else {
        echo json_encode(['error' => 'Produsul nu a fost găsit.']);
    }

} else {
    echo json_encode(['error' => 'Parametrul "q" este necesar.']);
}
?>