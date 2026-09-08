<?php
header('Content-Type: application/json');

// Include your database connection
include('database_connection.php'); // Ensure this includes your PDO connection

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['serie_factura'])) {
        $serie_factura = trim($_POST['serie_factura']);
        
        try {
            // Fetch the highest nr_factura for the given serie_factura
            $sql = "SELECT MAX(nr_factura) AS max_nr FROM facturi WHERE serie_factura = :serie_factura";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':serie_factura' => $serie_factura]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // If there are no facturi with this serie, get nr_inceput from serii_documente
            if ($result['max_nr'] === null) {
                $sql_nr_inceput = "SELECT nr_inceput FROM serii_documente WHERE serie = :serie_factura";
                $stmt_inceput = $pdo->prepare($sql_nr_inceput);
                $stmt_inceput->execute([':serie_factura' => $serie_factura]);
                $result_inceput = $stmt_inceput->fetch(PDO::FETCH_ASSOC);

                if ($result_inceput) {
                    $next_nr_factura = max(1, intval($result_inceput['nr_inceput']));
                } else {
                    // Dacă seria nu are configurat un număr de început, pornim de la minim 1.
                    $next_nr_factura = 1;
                }
            } else {
                // Increment the max number by 1 if a factura was found
                $next_nr_factura = $result['max_nr'] + 1;
            }

            echo json_encode(['next_nr_factura' => $next_nr_factura]);
        } catch (PDOException $e) {
            // Log the error
            file_put_contents('error_log.txt', "get_next_nr_factura.php Error: " . $e->getMessage() . "\n", FILE_APPEND);
            echo json_encode(['error' => 'Internal server error.']);
        }
    } else {
        echo json_encode(['error' => 'Missing serie_factura parameter.']);
    }
} else {
    echo json_encode(['error' => 'Invalid request method.']);
}
?>
