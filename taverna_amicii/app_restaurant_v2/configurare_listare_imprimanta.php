<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';

$stmtOperator = $pdo->prepare('SELECT rank FROM admins_12 WHERE admin_id = ? LIMIT 1');
$stmtOperator->execute([(int)($_SESSION['admin_id'] ?? 0)]);
$operatorRank = strtolower(trim((string)$stmtOperator->fetchColumn()));

if ($operatorRank !== 'sefsala') {
    http_response_code(403);
    echo 'Configurarea imprimantei este disponibilă numai pentru șeful de sală.';
    exit;
}

$configPath = RESTAURANT_OFFLINE_API_DIR . DIRECTORY_SEPARATOR . 'printer_format.json';
$defaults = [
    'bold' => true,
    'size' => '11',
    'align' => 'left',
];

$loadConfig = static function () use ($configPath, $defaults): array {
    if (!is_file($configPath)) {
        return $defaults;
    }

    $decoded = json_decode((string)file_get_contents($configPath), true);
    if (!is_array($decoded)) {
        return $defaults;
    }

    $size = trim((string)($decoded['size'] ?? $defaults['size']));
    $align = strtolower(trim((string)($decoded['align'] ?? $defaults['align'])));

    if ($size !== '' && (!ctype_digit($size) || (int)$size < 6 || (int)$size > 48)) {
        $size = $defaults['size'];
    }
    if (!in_array($align, ['', 'left', 'center', 'right', 'justified'], true)) {
        $align = $defaults['align'];
    }

    return [
        'bold' => filter_var($decoded['bold'] ?? $defaults['bold'], FILTER_VALIDATE_BOOL),
        'size' => $size,
        'align' => $align,
    ];
};

if (!isset($_SESSION['printer_format_csrf'])) {
    $_SESSION['printer_format_csrf'] = bin2hex(random_bytes(24));
}

$message = '';
$messageType = 'success';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $csrf = (string)($_POST['csrf'] ?? '');
    if (!hash_equals((string)$_SESSION['printer_format_csrf'], $csrf)) {
        http_response_code(400);
        $message = 'Cererea nu a putut fi validată. Reîncarcă pagina și încearcă din nou.';
        $messageType = 'danger';
    } else {
        $action = (string)($_POST['action'] ?? 'save');
        $config = $defaults;

        if ($action !== 'standard') {
            $sizeEnabled = isset($_POST['size_enabled']);
            $alignEnabled = isset($_POST['align_enabled']);
            $size = trim((string)($_POST['size'] ?? '11'));
            $align = strtolower(trim((string)($_POST['align'] ?? 'left')));

            if ($sizeEnabled && (!ctype_digit($size) || (int)$size < 6 || (int)$size > 48)) {
                $message = 'Mărimea textului trebuie să fie între 6 și 48.';
                $messageType = 'danger';
            } elseif ($alignEnabled && !in_array($align, ['left', 'center', 'right', 'justified'], true)) {
                $message = 'Alinierea selectată nu este validă.';
                $messageType = 'danger';
            } else {
                $config = [
                    'bold' => isset($_POST['bold']),
                    'size' => $sizeEnabled ? $size : '',
                    'align' => $alignEnabled ? $align : '',
                ];
            }
        }

        if ($message === '') {
            $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false || file_put_contents($configPath, $json . PHP_EOL, LOCK_EX) === false) {
                $message = 'Configurația nu a putut fi salvată.';
                $messageType = 'danger';
            } else {
                $message = $action === 'standard'
                    ? 'Modelul Standard a fost aplicat.'
                    : 'Configurația de listare a fost salvată.';
            }
        }
    }
}

$config = $loadConfig();
$sizeEnabled = $config['size'] !== '';
$alignEnabled = $config['align'] !== '';
$previewWeight = $config['bold'] ? '700' : '400';
$previewSize = $sizeEnabled ? max(12, min(32, (int)$config['size'] + 5)) . 'px' : '16px';
$previewAlign = $alignEnabled && $config['align'] === 'justified' ? 'justify' : ($alignEnabled ? $config['align'] : 'left');
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurare listare imprimantă</title>
    <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
    <style>
        :root {
            --ink: #171717;
            --paper: #fffaf0;
            --counter: #292824;
            --accent: #e0a21a;
            --muted: #716c61;
        }
        body {
            min-height: 100vh;
            background-color: var(--counter);
            background-image: linear-gradient(135deg, rgba(255,255,255,.025) 25%, transparent 25%), linear-gradient(315deg, rgba(255,255,255,.025) 25%, transparent 25%);
            background-size: 18px 18px;
            color: var(--ink);
            font-family: Georgia, 'Times New Roman', serif;
        }
        .settings-card {
            max-width: 920px;
            margin: 36px auto;
            overflow: hidden;
            border: 2px solid var(--ink);
            border-radius: 2px;
            background: var(--paper);
            box-shadow: 14px 14px 0 rgba(0, 0, 0, .32);
        }
        .settings-card .card-header {
            background: var(--ink) !important;
            border-bottom: 5px solid var(--accent);
            font-family: Consolas, 'Courier New', monospace;
            letter-spacing: .04em;
        }
        .settings-card .card-body { background: var(--paper); }
        .setting-block {
            height: 100%;
            padding: 18px;
            border: 1px solid #d6cebd;
            background: rgba(255,255,255,.48);
        }
        .preview-label {
            margin-bottom: 8px;
            color: var(--muted);
            font-family: Consolas, 'Courier New', monospace;
            font-size: .8rem;
            letter-spacing: .12em;
            text-transform: uppercase;
        }
        .preview-paper {
            position: relative;
            min-height: 210px;
            overflow: hidden;
            background: #fff;
            border-top: 6px solid var(--ink);
            border-bottom: 6px solid var(--ink);
            padding: 28px;
            font-family: Consolas, 'Courier New', monospace;
            line-height: 1.45;
            white-space: pre-line;
            box-shadow: inset 0 0 28px rgba(115, 91, 43, .08);
            transition: font-size .18s ease, font-weight .18s ease, text-align .18s ease;
        }
        .preview-paper::after {
            content: 'PREVIZUALIZARE';
            position: absolute;
            right: -34px;
            top: 18px;
            padding: 4px 42px;
            transform: rotate(35deg);
            background: var(--accent);
            color: var(--ink);
            font-size: 9px;
            font-weight: 700;
            letter-spacing: .08em;
        }
        .custom-control-label { cursor: pointer; }
        .btn-primary { background: var(--ink); border-color: var(--ink); }
        .btn-primary:hover { background: #383632; border-color: #383632; }
        .btn-outline-secondary { color: var(--ink); border-color: var(--ink); }
        .btn-outline-secondary:hover { background: var(--accent); border-color: var(--ink); color: var(--ink); }
        @media (max-width: 767.98px) {
            .settings-card { margin: 14px auto; box-shadow: 6px 6px 0 rgba(0,0,0,.32); }
        }
    </style>
</head>
<body>
<main class="container-fluid px-3">
    <div class="card settings-card">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h1 class="h5 mb-0">Configurare listare imprimantă</h1>
            <a href="sefsala.php" class="btn btn-outline-light btn-sm">Înapoi</a>
        </div>
        <div class="card-body p-4">
            <?php if ($message !== ''): ?>
                <div class="alert alert-<?php echo htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string)$_SESSION['printer_format_csrf'], ENT_QUOTES, 'UTF-8'); ?>">

                <div class="setting-block mb-4">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="bold" name="bold" <?php echo $config['bold'] ? 'checked' : ''; ?>>
                        <label class="custom-control-label font-weight-bold" for="bold">Îngroașă tot textul listat</label>
                        <small class="form-text text-muted">Setarea se aplică fiecărei linii trimise la imprimante.</small>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-4"><div class="setting-block">
                        <div class="custom-control custom-switch mb-2">
                            <input type="checkbox" class="custom-control-input" id="size_enabled" name="size_enabled" <?php echo $sizeEnabled ? 'checked' : ''; ?>>
                            <label class="custom-control-label" for="size_enabled">Aplică mărimea textului</label>
                        </div>
                        <label for="size">Mărime</label>
                        <input type="number" class="form-control" id="size" name="size" min="6" max="48" value="<?php echo htmlspecialchars($sizeEnabled ? $config['size'] : '11', ENT_QUOTES, 'UTF-8'); ?>">
                    </div></div>

                    <div class="col-md-6 mb-4"><div class="setting-block">
                        <div class="custom-control custom-switch mb-2">
                            <input type="checkbox" class="custom-control-input" id="align_enabled" name="align_enabled" <?php echo $alignEnabled ? 'checked' : ''; ?>>
                            <label class="custom-control-label" for="align_enabled">Aplică alinierea</label>
                        </div>
                        <label for="align">Aliniere</label>
                        <select class="form-control" id="align" name="align">
                            <?php foreach (['left' => 'Stânga', 'center' => 'Centru', 'right' => 'Dreapta', 'justified' => 'Justificat'] as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo $config['align'] === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div></div>
                </div>

                <div class="preview-label">Rezultat estimat pe hârtie</div>
                <div id="printerPreview" class="preview-paper mb-4" style="font-weight: <?php echo $previewWeight; ?>; font-size: <?php echo $previewSize; ?>; text-align: <?php echo $previewAlign; ?>;">TAVERNA AMICII
Masa 1
2 x Produs exemplu
TOTAL 50,00 LEI</div>

                <div class="d-flex flex-wrap justify-content-between">
                    <button type="submit" name="action" value="standard" class="btn btn-outline-secondary mb-2">Aplică modelul Standard</button>
                    <button type="submit" name="action" value="save" class="btn btn-primary mb-2">Salvează configurația</button>
                </div>
            </form>
        </div>
    </div>
</main>
<script>
(function () {
    var bold = document.getElementById('bold');
    var sizeEnabled = document.getElementById('size_enabled');
    var size = document.getElementById('size');
    var alignEnabled = document.getElementById('align_enabled');
    var align = document.getElementById('align');
    var preview = document.getElementById('printerPreview');

    function refreshPreview() {
        preview.style.fontWeight = bold.checked ? '700' : '400';
        preview.style.fontSize = sizeEnabled.checked ? Math.max(12, Math.min(32, Number(size.value || 11) + 5)) + 'px' : '16px';
        preview.style.textAlign = alignEnabled.checked && align.value === 'justified' ? 'justify' : (alignEnabled.checked ? align.value : 'left');
        size.disabled = !sizeEnabled.checked;
        align.disabled = !alignEnabled.checked;
    }

    [bold, sizeEnabled, size, alignEnabled, align].forEach(function (control) {
        control.addEventListener('input', refreshPreview);
        control.addEventListener('change', refreshPreview);
    });
    refreshPreview();
}());
</script>
</body>
</html>
