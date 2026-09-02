<?php
session_start();
include('session.php'); // conexiunea la baza de date

// Preluăm nr_bon din request
$nr_bon = isset($_GET['nr_bon']) ? $_GET['nr_bon'] : '';

// Interogăm baza de date pentru a obține statusul notei
$sql = "SELECT status FROM $tabel_final_note WHERE nrbon = :nr_bon";
$stmt = $pdo->prepare($sql);
$stmt->execute([':nr_bon' => $nr_bon]);
$status = $stmt->fetchColumn();

// Returnăm rezultatul ca JSON
header('Content-Type: application/json');
echo json_encode(['status' => $status]);
?>
