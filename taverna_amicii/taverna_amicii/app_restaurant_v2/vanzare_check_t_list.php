<?php
include('session.php'); // Conexiunea la baza de date și inițializarea sesiunii

if (isset($_GET['idvanz'])) {
    $idvanz = $_GET['idvanz'];
    $sql = "SELECT t_list FROM $tabel_final_det_note WHERE id_vanz = :idvanz";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':idvanz' => $idvanz]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode(['t_list' => $row['t_list']]);
    } else {
        echo json_encode(['t_list' => 0]);
    }
} else {
    echo json_encode(['t_list' => 0]);
}
?>
