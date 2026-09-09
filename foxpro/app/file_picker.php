<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$config = Config::load();
$allowedTargets = [
    'note_path' => 'note.dbf',
    'compnote_path' => 'COMPNOTE.DBF',
    'bonuri_path' => 'temp_bonuri.dbf',
    'totaluri_path' => 'totaluri.dbf',
    'comp_total_path' => 'comp_total.dbf',
    'disponibilitati_path' => 'disponibilitati.dbf',
    'prod_mat_path' => 'prod_mat.dbf',
];

$target = (string) ($_GET['target'] ?? '');
if (!isset($allowedTargets[$target])) {
    http_response_code(400);
    echo 'Target invalid.';
    exit;
}

$current = trim((string) ($_GET['current'] ?? ''));
$dir = trim((string) ($_GET['dir'] ?? ''));

if ($dir === '') {
    $dir = $current !== '' ? $current : (string) ($config[$target] ?? dirname(__DIR__));
}

if (is_file($dir)) {
    $dir = dirname($dir);
}

$realDir = realpath($dir);
if ($realDir === false || !is_dir($realDir)) {
    $realDir = realpath(dirname((string) ($config[$target] ?? __DIR__))) ?: realpath(dirname(__DIR__)) ?: __DIR__;
}

$entries = @scandir($realDir);
if ($entries === false) {
    $entries = [];
    $readError = 'Folderul nu poate fi citit.';
} else {
    $readError = null;
}

$folders = [];
$files = [];
foreach ($entries as $entry) {
    if ($entry === '.' || $entry === '..') {
        continue;
    }

    $path = $realDir . DIRECTORY_SEPARATOR . $entry;
    if (is_dir($path)) {
        $folders[] = $entry;
        continue;
    }

    if (is_file($path) && strtolower(pathinfo($entry, PATHINFO_EXTENSION)) === 'dbf') {
        $files[] = $entry;
    }
}

natcasesort($folders);
natcasesort($files);

$parent = dirname($realDir);
$drives = [];
if (DIRECTORY_SEPARATOR === '\\') {
    foreach (range('C', 'Z') as $drive) {
        $drivePath = $drive . ':\\';
        if (is_dir($drivePath)) {
            $drives[] = $drivePath;
        }
    }
}

$quickLinks = [];
$localCandidate = class_exists('DbfReader') ? DbfReader::findLocalCandidate($current) : null;
if ($localCandidate !== null) {
    $quickLinks['Sursa locala detectata'] = dirname($localCandidate);
}
$desktopPath = 'C:\\Users\\Me\\Desktop';
if (is_dir($desktopPath)) {
    $quickLinks['Desktop'] = $desktopPath;
}
?>
<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Alege fisier DBF</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body class="picker-body">
<main class="picker-shell">
    <header class="picker-header">
        <div class="picker-heading">
            <div>
                <p class="eyebrow">Alege fisier</p>
                <h1><?= h($allowedTargets[$target]) ?></h1>
            </div>
            <button type="button" class="ghost-button" onclick="window.close()">Inchide</button>
        </div>

        <section class="picker-current">
            <span>Folder curent</span>
            <strong><?= h($realDir) ?></strong>
        </section>
    </header>

    <?php if ($quickLinks || $drives): ?>
        <nav class="drive-list" aria-label="Comenzi rapide">
            <?php foreach ($quickLinks as $label => $path): ?>
                <a class="quick-link" href="<?= h(app_base_url('file_picker.php', ['target' => $target, 'dir' => $path])) ?>"><?= h($label) ?></a>
            <?php endforeach; ?>
            <?php foreach ($drives as $drive): ?>
                <a href="<?= h(app_base_url('file_picker.php', ['target' => $target, 'dir' => $drive])) ?>"><?= h($drive) ?></a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>

    <?php if ($readError): ?>
        <div class="notice is-error"><?= h($readError) ?></div>
    <?php endif; ?>

    <div class="picker-grid">
        <section class="picker-panel">
            <div class="section-title compact">
                <span>Navigare</span>
                <strong>Foldere</strong>
            </div>
            <div class="picker-list">
                <?php if ($parent !== $realDir): ?>
                    <a class="folder-row" href="<?= h(app_base_url('file_picker.php', ['target' => $target, 'dir' => $parent])) ?>">..</a>
                <?php endif; ?>
                <?php foreach ($folders as $folder): ?>
                    <?php $folderPath = $realDir . DIRECTORY_SEPARATOR . $folder; ?>
                    <a class="folder-row" href="<?= h(app_base_url('file_picker.php', ['target' => $target, 'dir' => $folderPath])) ?>"><?= h($folder) ?></a>
                <?php endforeach; ?>
                <?php if (!$folders && $parent === $realDir): ?>
                    <p class="picker-empty">Nu exista foldere aici.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="picker-panel">
            <div class="section-title compact">
                <span>Selectie</span>
                <strong>Fisiere DBF</strong>
            </div>
            <div class="picker-list">
                <?php foreach ($files as $file): ?>
                    <?php $filePath = $realDir . DIRECTORY_SEPARATOR . $file; ?>
                    <button type="button" class="file-row" data-path="<?= h($filePath) ?>">
                        <span><?= h($file) ?></span>
                        <small><?= h(number_format((float) filesize($filePath) / 1024 / 1024, 2)) ?> MB</small>
                    </button>
                <?php endforeach; ?>
                <?php if (!$files): ?>
                    <p class="picker-empty">Nu exista fisiere .dbf in acest folder.</p>
                <?php endif; ?>
            </div>
        </section>
    </div>
</main>

<script>
const target = <?= json_encode($target) ?>;

document.querySelectorAll('.file-row').forEach((button) => {
    button.addEventListener('click', () => {
        const path = button.dataset.path;
        if (window.opener && !window.opener.closed) {
            const input = window.opener.document.querySelector(`[name="${target}"]`);
            if (input) {
                input.value = path;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
            window.close();
            return;
        }

        navigator.clipboard?.writeText(path);
        alert('Path copiat: ' + path);
    });
});
</script>
</body>
</html>
