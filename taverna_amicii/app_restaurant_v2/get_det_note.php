<?php session_start();
// get_det_note.php
include('database_connection.php'); // deja ai conexiunea PDO
header('Content-Type: application/json');

if (isset($_GET['nrbon']) && !empty($_GET['nrbon'])) {
    $nrbon = $_GET['nrbon'];
    
    $sql = "SELECT * FROM det_note WHERE nr_bon = :nrbon";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['nrbon' => $nrbon]);
    $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($details);
} else {
    echo json_encode(['error' => 'Parametrul nrbon lipsește sau este invalid']);
}
?>
