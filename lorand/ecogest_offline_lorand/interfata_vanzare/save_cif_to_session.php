<?php
// save_cif_to_session.php
include('session.php'); // Asigură-te că sesiunea este pornită

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cif_client'])) {
    $_SESSION['cif_client'] = trim($_POST['cif_client']);
    echo json_encode(['status' => 'success', 'message' => 'CIF salvat in sesiune.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Date invalide.']);
}
?>