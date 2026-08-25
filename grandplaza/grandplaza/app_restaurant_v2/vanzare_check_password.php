<?php
include 'session.php'; // Include conexiunea PDO și definițiile necesare

if (isset($_POST['password'])) {
    $password = $_POST['password'];
    // Folosim md5 pentru a cripta parola (reține: md5 nu este recomandat pentru aplicații moderne)
    $hash = md5($password);
    
    // Presupunem că $_SESSION['admin_id'] este setat la login
    $adm_id = $_SESSION['admin_id'];
    
    $stmt = $pdo->prepare("SELECT 1 FROM $tabel_final_admins WHERE admin_id = :adm_id AND admin_password = :hash LIMIT 1");
    $stmt->execute([':adm_id' => $adm_id, ':hash' => $hash]);
    
    if ($stmt->fetchColumn()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Parola nu a fost trimisă']);
}
?>
