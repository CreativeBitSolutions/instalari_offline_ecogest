<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.log');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once 'session.php'; // trebuie să furnizeze $pdo

$term = isset($_GET['term']) ? trim($_GET['term']) : '';
$results = [];

if (mb_strlen($term) < 3) {
    echo json_encode(['results' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Căutăm în nume (prod active)
    $sql = "
        SELECT cod_produs, nume, pret_cu_tva, um, cota_tva
        FROM produse_servicii
        WHERE activ = 1
          AND nume LIKE :term
        ORDER BY nume ASC
        LIMIT 30
    ";
    $stmt = $pdo->prepare($sql);
    $like = '%' . $term . '%';
    $stmt->bindParam(':term', $like, PDO::PARAM_STR);
    $stmt->execute();

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results[] = [
            'id'          => $row['cod_produs'],
            'text'        => $row['nume'],
            'pret_cu_tva' => (float)$row['pret_cu_tva'],
            'um'          => $row['um'],
            'cota_tva'    => (int)$row['cota_tva'],
        ];
    }

    echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log('Eroare ajax_search_produse: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['results' => [], 'error' => 'Eroare la căutare.']);
}
