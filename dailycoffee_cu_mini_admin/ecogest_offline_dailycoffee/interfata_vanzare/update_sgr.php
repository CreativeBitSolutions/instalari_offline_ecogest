<?php
include('database_connection.php');

$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['cod_p']) && isset($data['sgr_value'])) {
    $cod_p = $data['cod_p'];
    $sgr_value = $data['sgr_value'];

    $sql = "UPDATE $tabel_final_nomenclator SET sgr = :sgr_value WHERE cod_p = :cod_p";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['sgr_value' => $sgr_value, 'cod_p' => $cod_p]);

    if ($stmt->rowCount()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'fail']);
    }
}
?>