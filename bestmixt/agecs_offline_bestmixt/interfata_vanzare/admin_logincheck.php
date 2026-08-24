<?php
session_start();
unset($_SESSION['error']);

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
    include 'database_connection.php';

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
