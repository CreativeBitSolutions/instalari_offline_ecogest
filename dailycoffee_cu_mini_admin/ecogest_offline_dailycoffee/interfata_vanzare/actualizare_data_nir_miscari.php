<?php

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

if (!isset($_SESSION['admin_id'])) {
  http_response_code(401);
  exit('Sesiune invalida.');
}

include 'db.php';

$selectStmt = $pdo->prepare(
  "SELECT id, nr_doc FROM $tabel_final_miscari WHERE data = :invalid_data AND fel_doc = :fel_doc"
);
$selectStmt->execute([
  ':invalid_data' => '0000-00-00',
  ':fel_doc' => 'NIR',
]);

$dateStmt = $pdo->prepare("SELECT data_nir FROM $tabel_final_nir WHERE nr_nir = :nr_nir LIMIT 1");
$updateStmt = $pdo->prepare("UPDATE $tabel_final_miscari SET data = :data_nir WHERE id = :id");

while ($row = $selectStmt->fetch(PDO::FETCH_ASSOC)) {
  $dateStmt->execute([':nr_nir' => $row['nr_doc']]);
  $dataNir = $dateStmt->fetchColumn();

  if ($dataNir) {
    $updateStmt->execute([
      ':data_nir' => $dataNir,
      ':id' => $row['id'],
    ]);
  }
}

?>