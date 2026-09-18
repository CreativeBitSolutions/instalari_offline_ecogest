<?php
// vanzare_get_pret_achizitie.php
include('session.php');
header('Content-Type: application/json');

// Verificăm dacă a fost trimis un cod de produs
if (!isset($_POST['cod_p']) || empty($_POST['cod_p'])) {
    echo json_encode(['error' => 'Codul produsului lipsește.']);
    exit;
}

$cod_produs = $_POST['cod_p'];
$response = ['pret_achizitie' => null]; // Răspuns implicit

try {
    // Am modificat interogarea SQL pentru a calcula direct prețul de achiziție cu TVA inclus
    // Formula aplicată: pret_achizitie * (1 + cota_tva / 100)
    $sql = "SELECT (pret_achizitie * (1 + cota_tva / 100)) AS pret_achizitie_cu_tva FROM produse_servicii WHERE cod_produs = :cod_produs";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['cod_produs' => $cod_produs]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result && isset($result['pret_achizitie_cu_tva'])) {
        // Dacă produsul este găsit, trimitem prețul calculat
        // Folosim cheia 'pret_achizitie' în răspuns pentru a nu modifica structura JSON
        // și formatăm valoarea la 2 zecimale, specific pentru prețuri.
        $response['pret_achizitie'] = number_format((float)$result['pret_achizitie_cu_tva'], 2, '.', '');
    }

} catch (PDOException $e) {
    // Gestionarea erorilor de bază de date
    $response['error'] = 'Eroare la interogarea bazei de date.';
    // Pentru debugging, poți înregistra eroarea reală: error_log($e->getMessage());
}

echo json_encode($response);
?>