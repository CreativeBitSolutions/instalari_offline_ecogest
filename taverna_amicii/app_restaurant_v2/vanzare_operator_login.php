<?php
include 'session.php';

header('Content-Type: application/json');
date_default_timezone_set('Europe/Bucharest');

$codLocatie = isset($_SESSION['cod_locatie']) ? $_SESSION['cod_locatie'] : null;
if (!$codLocatie) {
    echo json_encode(['success' => false, 'message' => 'Locatie nevalida!']);
    exit;
}

$loginDateTime = new DateTime('now');
$loginTime = $loginDateTime->format('Y-m-d H:i:s');
$action = $_POST['action'] ?? '';

if ($action === 'set_occupied_flag') {
    $_SESSION['occupied_operator'] = ((int)($_POST['occupied'] ?? 0) === 1);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'login') {
    $operatorPassword = md5($_POST['operatorPassword'] ?? '');

    if (isset($_POST['occupiedOperator']) && trim($_POST['occupiedOperator']) !== '') {
        $occupiedOperator = trim($_POST['occupiedOperator']);
        $sql = "SELECT * FROM $tabel_final_admins
                WHERE admin_id = :occupiedOperator
                  AND admin_password = :operatorPassword
                  AND locatie = :cod_locatie
                  AND rank = :rank
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'occupiedOperator' => $occupiedOperator,
            'operatorPassword' => $operatorPassword,
            'cod_locatie' => $codLocatie,
            'rank' => 'ospatar'
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $_SESSION['occupied_operator'] = true;
            $_SESSION['admin_id'] = $row['admin_id'];
            $_SESSION['adminloggedin'] = $row['admin_id'];

            $insertSql = "INSERT INTO conectari_operatori (id_operator, nume_operator, login_time)
                          VALUES (:id_operator, :nume_operator, :login_time)";
            $insertStmt = $pdo->prepare($insertSql);
            $operatorName = $row['admin_firstname'] . ' ' . $row['admin_lastname'];
            $insertStmt->execute([
                'id_operator' => $row['admin_id'],
                'nume_operator' => $operatorName,
                'login_time' => $loginTime
            ]);

            if (!isset($_SESSION['no_session_validation']) || $_SESSION['no_session_validation'] != 1) {
                restaurantTouchUltimaConexiune($pdo, (int)$_SESSION['cod_locatie'], (int)$occupiedOperator, $loginTime);
            }

            echo json_encode(['success' => true]);
            exit;
        }

        echo json_encode(['success' => false, 'message' => 'Date de conectare incorecte!']);
        exit;
    }

    $_SESSION['occupied_operator'] = false;
    $sql = "SELECT * FROM $tabel_final_admins
            WHERE admin_password = :operatorPassword
              AND locatie = :cod_locatie
              AND rank = :rank
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'operatorPassword' => $operatorPassword,
        'cod_locatie' => $codLocatie,
        'rank' => 'ospatar'
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $_SESSION['admin_id'] = $row['admin_id'];
        $_SESSION['adminloggedin'] = $row['admin_id'];

        $insertSql = "INSERT INTO conectari_operatori (id_operator, nume_operator, login_time)
                      VALUES (:id_operator, :nume_operator, :login_time)";
        $insertStmt = $pdo->prepare($insertSql);
        $operatorName = $row['admin_firstname'] . ' ' . $row['admin_lastname'];
        $insertStmt->execute([
            'id_operator' => $row['admin_id'],
            'nume_operator' => $operatorName,
            'login_time' => $loginTime
        ]);

        if (!isset($_SESSION['no_session_validation']) || $_SESSION['no_session_validation'] != 1) {
            restaurantTouchUltimaConexiune($pdo, (int)$_SESSION['cod_locatie'], (int)$row['admin_id'], $loginTime);
        }

        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Date de conectare incorecte!']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Cerere invalida']);
exit;
?>
