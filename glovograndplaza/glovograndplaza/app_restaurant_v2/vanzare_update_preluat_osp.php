<?php
include('session.php');

if (isset($_POST['idvanz'])) {
    $idvanz = $_POST['idvanz'];

    $stmtNb = $pdo->prepare("SELECT nr_bon FROM $tabel_final_det_note WHERE id_vanz = :idvanz LIMIT 1");
    $stmtNb->execute([':idvanz' => $idvanz]);
    $nr_bon = $stmtNb->fetchColumn();

    $sql = "UPDATE $tabel_final_det_note SET preluat_osp = 1 WHERE id_vanz = :idvanz";
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute([':idvanz' => $idvanz])) {
        $cacheDir = __DIR__ . '/cache';
        if (is_dir($cacheDir) && $nr_bon !== false && $nr_bon !== null && $nr_bon !== '') {
            $pfxRows  = 'bon_rows_'  . preg_replace('/[^0-9]/', '', $nr_bon);
            $pfxTotal = 'bon_total_' . preg_replace('/[^0-9]/', '', $nr_bon);
            foreach (glob($cacheDir . '/' . $pfxRows  . '*.cache') as $f) { @unlink($f); }
            foreach (glob($cacheDir . '/' . $pfxTotal . '*.cache') as $f) { @unlink($f); }
        }
        echo "Success";
    } else {
        echo "Error updating";
    }
} else {
    echo "No id provided";
}
?>
