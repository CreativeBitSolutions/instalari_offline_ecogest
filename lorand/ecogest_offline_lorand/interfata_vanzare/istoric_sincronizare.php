<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
?>
<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ECOGEST Lorand, istoric sincronizare</title>
    <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="vendor/font-awesome/css/font-awesome.min.css">
    <style>
        body { margin:0; min-height:100vh; background:#172026; font-family:Arial,sans-serif; }
        .page { width:min(1240px,calc(100% - 28px)); margin:22px auto; }
        .top { display:flex; justify-content:space-between; align-items:center; gap:14px; margin-bottom:12px; padding:16px 18px; border-left:5px solid #23845f; border-radius:7px; background:#fff; }
        .top h1 { margin:0; font-size:24px; }
        .top p { margin:3px 0 0; color:#64748b; }
        #offlineSyncStatusModal { display:block; position:static; }
        #offlineSyncStatusModal .modal-dialog { margin:0; max-width:none; }
        #offlineSyncStatusModal .modal-content { border:0; }
        #offlineSyncStatusModal .modal-header .close, #offlineSyncStatusModal .modal-footer .btn-secondary { display:none; }
        @media (max-width:700px) { .top { align-items:flex-start; flex-direction:column; } }
    </style>
</head>
<body>
<main class="page">
    <header class="top">
        <div><h1>Istoric sincronizare</h1><p>Operațiuni locale și confirmările primite din aplicația online.</p></div>
        <a class="btn btn-outline-dark" href="agecs_login.php">Înapoi la conectare</a>
    </header>
    <?php include __DIR__ . '/modal_situatie_sincronizare.php'; ?>
</main>
<script src="vendor/jquery/jquery.min.js"></script>
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    $('#offlineSyncStatusModal').modal({backdrop:false, keyboard:false, show:true});
});
</script>
</body>
</html>
