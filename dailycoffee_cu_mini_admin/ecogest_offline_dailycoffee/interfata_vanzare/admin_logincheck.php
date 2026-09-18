<?php
session_start();
unset($_SESSION['error']);

function offline_synced_operator_allowed(PDO $pdo, int $adminId): bool
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

function redirectByRank($rank, $tabletaMode)
{
    if ($rank == "administrator") {
        printf("<script>location.href='index.php'</script>");
    } elseif ($rank == "operator") {
        printf("<script>location.href='vanzare_magazin.php'</script>");
    } elseif ($rank == "ospatar") {
        if ($tabletaMode == 1) {
            printf("<script>location.href='tableta/tableta.php'</script>");
        } else {
            printf("<script>location.href='vanzare_magazin.php'</script>");
        }
    } elseif ($rank == "bucatar") {
        printf("<script>location.href='bucatarie.php'</script>");
    } elseif ($rank == "barman") {
        printf("<script>location.href='bar.php'</script>");
    } elseif ($rank == "client" || $rank == "tableta") {
        printf("<script>location.href='tableta.php'</script>");
    } elseif ($rank == "receptioner") {
        printf("<script>location.href='hotel/index.php'</script>");
    }
}

function logIn()
{
    include 'db.php';

    $row = false;
    $redirectOnError = 'agecs_login.php';
    $tabletaMode = isset($_POST['tableta']) ? (int) $_POST['tableta'] : 0;

    if (!empty($_POST['oper'])) {
        $myusername = $_POST['oper'];
        $mypassword = md5($_POST['calc_result']);

        $psql = "SELECT * FROM $tabel_final_admins WHERE admin_id = :id AND admin_password = :pwd";
        $pstmt = $pdo->prepare($psql);
        $pstmt->execute([
            ':id' => $myusername,
            ':pwd' => $mypassword
        ]);
        $row = $pstmt->fetch(PDO::FETCH_ASSOC);
    } elseif (!empty($_POST['admin_email_address'])) {
        $redirectOnError = 'admin_login.php';
        $adminEmail = trim($_POST['admin_email_address']);
        $adminPassword = md5($_POST['admin_password']);

        $psql = "SELECT * FROM $tabel_final_admins WHERE admin_email_address = :email AND admin_password = :pwd LIMIT 1";
        $pstmt = $pdo->prepare($psql);
        $pstmt->execute([
            ':email' => $adminEmail,
            ':pwd' => $adminPassword
        ]);
        $row = $pstmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($row) {
        if (!offline_synced_operator_allowed($pdo, (int)$row['admin_id'])) {
            $_SESSION['error'] = 'Utilizatorul nu mai are acces la această instalare offline.';
            header('Location: agecs_login.php');
            return;
        }
        $_SESSION['error'] = '';
        $_SESSION['adminloggedin'] = $row['admin_id'];

        $admin_id = $row['admin_id'];
        $rank = $row['rank'];

        $_SESSION['admin_id'] = $admin_id;

        redirectByRank($rank, $tabletaMode);
        return;
    }

    $_SESSION['error'] = "Date de conectare incorecte!";
    printf("<script>location.href='%s'</script>", $redirectOnError);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    logIn();
}

?>
