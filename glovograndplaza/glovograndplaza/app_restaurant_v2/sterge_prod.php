<?php //sterge_prod.php
include('session.php');

$nr_bon  = isset($_GET['nr_bon'])  ? $_GET['nr_bon']  : null;
$id_vanz = isset($_GET['id_vanz']) ? $_GET['id_vanz'] : null;

if ($id_vanz) {
    if (!$nr_bon) {
        $stmtNb = $pdo->prepare("SELECT nr_bon FROM $tabel_final_det_note WHERE id_vanz = :id_vanz LIMIT 1");
        $stmtNb->execute([':id_vanz' => $id_vanz]);
        $nr_bon = $stmtNb->fetchColumn();
    }

    $sterg_sql = "SELECT $tabel_final_nomenclator.departament,
                         $tabel_final_det_note.cod_p,
                         $tabel_final_det_note.cantitate
                  FROM $tabel_final_det_note
                  INNER JOIN $tabel_final_nomenclator
                    ON $tabel_final_det_note.cod_p = $tabel_final_nomenclator.cod_produs
                  WHERE $tabel_final_det_note.id_vanz = :id_vanz";
    $sterg_f_stmt = $pdo->prepare($sterg_sql);
    $sterg_f_stmt->execute([':id_vanz' => $id_vanz]);
    while ($row = $sterg_f_stmt->fetch(PDO::FETCH_ASSOC)) {
        $c_p       = $row['cod_p'];
        $cant_vand = $row['cantitate'];
        $dep       = $row['departament'];
    }

    $stmt_del_disc = $pdo->prepare("DELETE FROM discounturi_acordate WHERE id_vanz = :id_vanz");
    $stmt_del_disc->execute([':id_vanz' => $id_vanz]);

    $stmt_del = $pdo->prepare("DELETE FROM $tabel_final_det_note WHERE id_vanz = :id_vanz");
    $stmt_del->execute([':id_vanz' => $id_vanz]);

    $cacheDir = __DIR__ . '/cache';
    if (is_dir($cacheDir) && $nr_bon !== null && $nr_bon !== '') {
        $pfxRows  = 'bon_rows_'  . preg_replace('/[^0-9]/', '', $nr_bon);
        $pfxTotal = 'bon_total_' . preg_replace('/[^0-9]/', '', $nr_bon);
        foreach (glob($cacheDir . '/' . $pfxRows  . '*.cache') as $f) { @unlink($f); }
        foreach (glob($cacheDir . '/' . $pfxTotal . '*.cache') as $f) { @unlink($f); }
    }

    printf("<script>location.href='vanzare_restaurant.php'</script>");
}
?>
