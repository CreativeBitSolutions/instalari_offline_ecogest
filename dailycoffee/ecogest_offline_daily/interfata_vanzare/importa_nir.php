<?php

if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

if (!isset($_SESSION['admin_id'])) {
	http_response_code(401);
	exit('Sesiune invalida.');
}

include 'db.php';

$fStmt = $pdo->prepare("SELECT cod_p, cantitate FROM $tabel_final_achizitii");
$fStmt->execute();

$updateStmt = $pdo->prepare(
	"UPDATE $tabel_final_stoc SET cantitate = cantitate + :cantitate WHERE cod_p = :cod_p"
);

while ($row = $fStmt->fetch(PDO::FETCH_ASSOC)) {
	$updateStmt->execute([
		':cantitate' => $row['cantitate'],
		':cod_p' => $row['cod_p'],
	]);
}

?>