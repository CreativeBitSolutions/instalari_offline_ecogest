
<?php session_start();

    include 'database_connection.php';

if (isset($_SESSION['admin_id'])) {
    $updateSql = "UPDATE $tabel_final_admins SET conectat = 0 WHERE admin_id = :admin_id";
    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->execute(['admin_id' => $_SESSION['admin_id']]);
}

	$ul=$_SESSION['live'];

    $d=$_SESSION['d'];
unset($_SESSION['adminloggedin']);
    unset($_SESSION['admin_id']);
unset($_SESSION['masa_curenta']);
unset($_SESSION['vanzare_sub_stoc']);
unset($_SESSION['mod_listare']);
unset($_SESSION['ajustare_adaos']);
unset($_SESSION['camera_nota']);
  
    printf("<script>location.href='agecs_login.php'</script>");
?>