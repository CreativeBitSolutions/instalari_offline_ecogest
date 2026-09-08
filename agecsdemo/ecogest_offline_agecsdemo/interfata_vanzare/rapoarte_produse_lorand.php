<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/setari_lorand_schema.php';

$clientId = (int)($_SESSION['client_id'] ?? 0);
$lorandSettings = vanzare_v2_lorand_settings($pdo);
if ($clientId !== 8 || empty($lorandSettings['operator_acces_rapoarte'])) {
    http_response_code(403);
    echo 'Pagina este disponibilă numai operatorilor autorizați.';
    exit;
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapoarte produse AGECSDEMO</title>
    <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
    <style>
        body { background:#172026; color:#fff; }
        .report-page-bar { min-height:100vh; padding:18px; }
        .report-page-card { max-width:760px; margin:8vh auto 0; padding:24px; border-radius:12px; background:#fffdf5; color:#172026; box-shadow:0 18px 55px rgba(0,0,0,.35); }
        .report-page-actions { display:flex; flex-wrap:wrap; gap:10px; }
    </style>
</head>
<body>
<main class="report-page-bar">
    <section class="report-page-card">
        <small class="text-uppercase text-muted font-weight-bold">ECOGEST MAGAZIN AGECSDEMO</small>
        <h1 class="h3 mt-1">Rapoarte și istoric PROTOCOL</h1>
        <p class="text-muted">Produse vândute pe departamente și produse încasate pe PROTOCOL, cu filtre, detalii și previzualizare înaintea listării.</p>
        <div class="report-page-actions">
            <a class="btn btn-outline-dark" href="vanzare_magazin.php">Înapoi la vânzare</a>
            <a class="btn btn-outline-primary" href="istoric_protocol_lorand.php">Istoric PROTOCOL</a>
            <a class="btn btn-outline-secondary" href="configurare_imprimanta_lorand.php">Configurare imprimantă</a>
            <button type="button" class="btn btn-warning" id="btnProductReport">Deschide raportul</button>
        </div>
    </section>
</main>

<?php include __DIR__ . '/modal_raport_produse_lorand.php'; ?>

<script src="js/jquery-3.6.0.min.js"></script>
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var button = document.getElementById('btnProductReport');
    if (button) {
        button.click();
    }
});
</script>
</body>
</html>
