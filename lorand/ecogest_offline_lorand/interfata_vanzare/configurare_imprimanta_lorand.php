<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/database_connection.php';
require_once __DIR__ . '/printer_format_helper.php';
require_once __DIR__ . '/setari_lorand_schema.php';

$clientId = (int)($_SESSION['client_id'] ?? 0);
if ($clientId !== 1019) {
    http_response_code(403);
    echo 'Configurarea imprimantei este disponibilă numai pentru Lorand.';
    exit;
}

$apiRoot = rtrim((string)offline_api_root_path(), '/\\');
if ($apiRoot === '') {
    http_response_code(500);
    echo 'Folderul API offline Lorand nu este configurat.';
    exit;
}
$configPath = $apiRoot . DIRECTORY_SEPARATOR . 'printer_format.json';
$message = '';
$messageType = 'success';
$config = agecs_printer_client_format_config($clientId);
$lorandSettings = vanzare_v2_lorand_settings($pdo);
if (!isset($_SESSION['lorand_printer_csrf']) || !is_string($_SESSION['lorand_printer_csrf'])) {
    $_SESSION['lorand_printer_csrf'] = bin2hex(random_bytes(24));
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $submittedCsrf = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals((string)$_SESSION['lorand_printer_csrf'], $submittedCsrf)) {
        http_response_code(400);
        $message = 'Cererea nu mai este validă. Reîncarcă pagina și încearcă din nou.';
        $messageType = 'danger';
    } else {
        $bold = isset($_POST['bold']);
        $size = trim((string)($_POST['size'] ?? '11'));
        $align = strtolower(trim((string)($_POST['align'] ?? 'left')));
        $width = (int)($_POST['width_chars'] ?? 42);
        $copies = (int)($_POST['copies'] ?? 2);
        $reportsEnabled = isset($_POST['operator_acces_rapoarte']);
        $printAfterFiscal = isset($_POST['listare_nota_dupa_fiscalizare']);

        if (!ctype_digit($size) || (int)$size < 6 || (int)$size > 48) {
            $message = 'Dimensiunea textului trebuie să fie între 6 și 48.';
            $messageType = 'danger';
        } elseif (!in_array($align, ['left', 'center', 'right'], true)) {
            $message = 'Alinierea selectată nu este validă.';
            $messageType = 'danger';
        } elseif ($width < 24 || $width > 80) {
            $message = 'Lățimea notei trebuie să fie între 24 și 80 de caractere.';
            $messageType = 'danger';
        } elseif ($copies < 1 || $copies > 5) {
            $message = 'Numărul de exemplare trebuie să fie între 1 și 5.';
            $messageType = 'danger';
        } else {
            $newConfig = [
                'bold' => $bold,
                'size' => $size,
                'align' => $align,
                'width_chars' => $width,
                'copies' => $copies,
                'destination' => 'BAR',
            ];
            $json = json_encode($newConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false || @file_put_contents($configPath, $json, LOCK_EX) === false) {
                $message = 'Configurarea nu a putut fi salvată în folderul API offline.';
                $messageType = 'danger';
            } elseif (!vanzare_v2_save_lorand_settings($pdo, $reportsEnabled, $printAfterFiscal)) {
                $message = 'Setările de acces și listare nu au putut fi salvate în baza locală.';
                $messageType = 'danger';
            } else {
                $config = $newConfig;
                $lorandSettings = [
                    'operator_acces_rapoarte' => $reportsEnabled,
                    'listare_nota_dupa_fiscalizare' => $printAfterFiscal,
                ];
                $message = $printAfterFiscal
                    ? 'Configurarea a fost salvată. Nota de plată va fi trimisă la BAR în ' . $copies . ' exemplar(e).'
                    : 'Configurarea a fost salvată. Listarea automată după fiscalizare este dezactivată.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurare imprimantă Lorand</title>
    <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
    <style>
        body { margin:0; min-height:100vh; background:#172026; color:#172026; font-family:Arial,sans-serif; }
        .shell { max-width:820px; margin:0 auto; padding:42px 18px; }
        .card-local { background:#fffdf5; border-radius:14px; padding:26px; box-shadow:0 18px 55px rgba(0,0,0,.35); }
        .kicker { color:#6c757d; font-size:12px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; }
        .preview { margin-top:18px; padding:18px; border:1px dashed #9aa4ad; border-radius:8px; background:#fff; }
        .preview pre { margin:0; white-space:pre-wrap; }
        .destination { padding:10px 12px; border-radius:6px; background:#edf7ef; color:#245b31; font-weight:700; }
        .actions { display:flex; gap:10px; flex-wrap:wrap; }
    </style>
</head>
<body>
<div class="shell">
    <div class="card-local">
        <div class="kicker">ECOGEST MAGAZIN LORAND · client 1019</div>
        <h1 class="h3 mt-1">Configurare notă de plată</h1>
        <p class="text-muted">Setările se păstrează local. Destinația notei după fiscalizare este fixă: imprimanta BAR.</p>

        <?php if ($message !== ''): ?>
            <div class="alert alert-<?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)$_SESSION['lorand_printer_csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="size">Dimensiune text</label>
                    <input class="form-control" type="number" id="size" name="size" min="6" max="48" value="<?= htmlspecialchars((string)$config['size'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label for="width_chars">Lățime notă, caractere</label>
                    <input class="form-control" type="number" id="width_chars" name="width_chars" min="24" max="80" value="<?= (int)$config['width_chars'] ?>">
                </div>
                <div class="form-group col-md-4">
                    <label for="copies">Exemplare după fiscalizare</label>
                    <input class="form-control" type="number" id="copies" name="copies" min="1" max="5" value="<?= (int)$config['copies'] ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="align">Aliniere</label>
                    <select class="form-control" id="align" name="align">
                        <?php foreach (['left' => 'Stânga', 'center' => 'Centru', 'right' => 'Dreapta'] as $value => $label): ?>
                            <option value="<?= $value ?>" <?= $config['align'] === $value ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-6">
                    <label>Destinație</label>
                    <div class="destination">BAR</div>
                </div>
            </div>
            <div class="form-group form-check">
                <input type="checkbox" class="form-check-input" id="bold" name="bold" value="1" <?= !empty($config['bold']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="bold">Text îngroșat</label>
            </div>
            <div class="form-group form-check">
                <input type="checkbox" class="form-check-input" id="operator_acces_rapoarte" name="operator_acces_rapoarte" value="1" <?= !empty($lorandSettings['operator_acces_rapoarte']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="operator_acces_rapoarte">Operatorii au acces la rapoarte și istoricul PROTOCOL</label>
            </div>
            <div class="form-group form-check">
                <input type="checkbox" class="form-check-input" id="listare_nota_dupa_fiscalizare" name="listare_nota_dupa_fiscalizare" value="1" <?= !empty($lorandSettings['listare_nota_dupa_fiscalizare']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="listare_nota_dupa_fiscalizare">Listează automat nota de plată după fiscalizare</label>
            </div>
            <div class="actions">
                <button type="submit" class="btn btn-warning">Salvează configurarea</button>
                <a href="rapoarte_produse_lorand.php" class="btn btn-outline-secondary">Rapoarte și PROTOCOL</a>
                <a href="vanzare_magazin.php" class="btn btn-outline-dark">Înapoi la vânzare</a>
            </div>
        </form>

        <div class="preview">
            <strong>Previzualizare logică</strong>
            <pre>NOTA DE PLATA
Nr. nota: 123456
2 BUC x PRODUS
TOTAL: 50,00 LEI
Card: 50,00 LEI
Destinație: BAR · <?= (int)$config['copies'] ?> exemplar(e)</pre>
        </div>
    </div>
</div>
</body>
</html>
