<?php
// export_vanzari_offline.php

session_start();
include('db.php');
require_once __DIR__ . '/sincronizare_offline/export_vanzari_offline_lib.php';

ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.log');
error_reporting(E_ALL);

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
header("Pragma: no-cache");

date_default_timezone_set("Europe/Bucharest");

if (!isset($_SESSION['adminloggedin'])) {
    printf("<script>location.href='agecs_login.php'</script>");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $azi = date('Y-m-d');
    $luna_start = date('Y-m-01');
    echo '<!DOCTYPE html><html lang="ro"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Export Offline</title>
<link href="vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="css/sb-admin.css" rel="stylesheet">
</head><body class="bg-dark">
<div class="container mt-5">
  <div class="card card-login mx-auto" style="max-width:440px">
    <div class="card-header"><b>Export vanzari offline</b></div>
    <div class="card-body">
      <form method="POST" action="export_vanzari_offline.php">
        <div class="form-group mb-3">
          <label><b>Data inceput</b></label>
          <input type="date" name="data_start" class="form-control" value="' . $luna_start . '" required>
        </div>
        <div class="form-group mb-3">
          <label><b>Data sfarsit</b></label>
          <input type="date" name="data_end" class="form-control" value="' . $azi . '" required>
        </div>
        <div class="form-group mb-4">
          <label><b>Format</b></label>
          <select name="format" class="form-control">
            <option value="xml">XML</option>
            <option value="sql">SQL</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary btn-block w-100" style="cursor:pointer">Descarca export</button>
      </form>
      <div class="mt-3 text-center">
        <a href="vanzare_magazin.php" style="color:#aaa">&#8592; Inapoi la vanzare</a>
      </div>
    </div>
  </div>
</div>
</body></html>';
    exit();
}

try {
    $exportFile = offline_export_build(
        $pdo,
        $_POST['data_start'] ?? '',
        $_POST['data_end'] ?? '',
        $_POST['format'] ?? 'xml'
    );
} catch (Throwable $e) {
    echo $e->getMessage();
    exit();
}

header('Content-Type: ' . $exportFile['content_type'] . '; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $exportFile['filename'] . '"');

echo $exportFile['content'];
exit;
