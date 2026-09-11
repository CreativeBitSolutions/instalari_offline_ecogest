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

function offline_session_operator_allowed(PDO $pdo, int $adminId): bool
{
    if (strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) !== 'sqlite') {
        return true;
    }
    $runtimeExists = (bool)$pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'offline_reference_sync_runtime'")->fetchColumn();
    if (!$runtimeExists) {
        return true;
    }
    $mirrored = (int)$pdo->query('SELECT vat_mirrored FROM offline_reference_sync_runtime WHERE id = 1')->fetchColumn();
    if ($mirrored !== 1) {
        return true;
    }
    $operatorsExists = (bool)$pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'offline_online_operators'")->fetchColumn();
    if (!$operatorsExists) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT 1 FROM offline_online_operators WHERE admin_id = ? LIMIT 1');
    $stmt->execute([$adminId]);
    return (bool)$stmt->fetchColumn();
}

$zsql = "SELECT admin_id FROM $tabel_final_admins WHERE admin_id = :id";
$zstmt = $pdo->prepare($zsql);
$zstmt->execute([':id' => $user_check]);

$row = $zstmt->fetch(PDO::FETCH_ASSOC);

if (!$row || !offline_session_operator_allowed($pdo, (int)$user_check)) {
    unset($_SESSION['admin_id'], $_SESSION['adminloggedin']);
    printf("<script>location.href='%s'</script>", $session_login_redirect);
    exit();
}

$live_id = 12;
?>
