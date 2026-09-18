<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/daily_export_xml_lib.php';

if (!isset($_SESSION['adminloggedin'])) {
    header('Location: agecs_login.php');
    exit;
}

$message = '';
$isError = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Extensia cURL nu este disponibila pentru trimiterea XML-ului.');
        }
        $export = daily_export_xml_build($pdo, (string)($_POST['data_start'] ?? ''), (string)($_POST['data_end'] ?? ''));
        $tmp = tempnam(sys_get_temp_dir(), 'daily_xml_');
        if ($tmp === false || file_put_contents($tmp, $export['content'], LOCK_EX) === false) {
            throw new RuntimeException('Fisierul XML temporar nu a putut fi creat.');
        }

        $ch = curl_init($export['upload_url']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => ['X-Offline-Export-Key: ' . $export['upload_key'], 'Accept: application/json'],
            CURLOPT_POSTFIELDS => [
                'client_id' => $export['client_id'],
                'cod_locatie' => $export['cod_locatie'],
                'data_start' => $export['data_start'],
                'data_end' => $export['data_end'],
                'fisier' => new CURLFile($tmp, 'application/xml', $export['filename']),
            ],
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        @unlink($tmp);

        $payload = json_decode((string)$response, true);
        if ($response === false || $httpCode < 200 || $httpCode >= 300 || !is_array($payload) || empty($payload['success'])) {
            throw new RuntimeException(is_array($payload) && !empty($payload['message']) ? (string)$payload['message'] : ($error !== '' ? $error : 'Serverul online nu a confirmat incarcarea.'));
        }
        $message = 'XML incarcat online: ' . (string)($payload['filename'] ?? $export['filename']);
    } catch (Throwable $e) {
        $isError = true;
        $message = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ro"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Trimite XML online</title><link href="vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-dark"><main class="container mt-5"><section class="card mx-auto" style="max-width:480px"><div class="card-header"><b>Trimite export XML catre online</b></div><div class="card-body">
<?php if ($message !== ''): ?><div class="alert <?= $isError ? 'alert-danger' : 'alert-success' ?>"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<form method="post"><div class="form-group"><label>Data inceput</label><input class="form-control" type="date" name="data_start" value="<?= htmlspecialchars((string)($_POST['data_start'] ?? date('Y-m-01')), ENT_QUOTES, 'UTF-8') ?>" required></div><div class="form-group"><label>Data sfarsit</label><input class="form-control" type="date" name="data_end" value="<?= htmlspecialchars((string)($_POST['data_end'] ?? date('Y-m-d')), ENT_QUOTES, 'UTF-8') ?>" required></div><button class="btn btn-success btn-block" type="submit">Trimite XML catre online</button></form><div class="mt-3 text-center"><a href="export_vanzari_offline.php">Inapoi la export</a></div>
</div></section></main></body></html>
