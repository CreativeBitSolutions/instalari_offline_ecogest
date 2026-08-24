<?php session_start();
date_default_timezone_set("Europe/Bucharest");

$session_login_redirect = isset($session_login_redirect) ? $session_login_redirect : 'agecs_login.php';

if (!isset($_SESSION['admin_id'])) {
    printf("<script>location.href='%s'</script>", $session_login_redirect);
    exit();
}

include('db.php');
$live_id = 12;

$user_check = $_SESSION['admin_id'];

$zsql = "SELECT admin_id FROM $tabel_final_admins WHERE admin_id = :id";
$zstmt = $pdo->prepare($zsql);
$zstmt->execute([':id' => $user_check]);

$row = $zstmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    printf("<script>location.href='%s'</script>", $session_login_redirect);
    exit();
}

$live_id = 12;
?>
