<?php
declare(strict_types=1);

require_once __DIR__ . '/det_note_departament_listare_schema.php';
require_once __DIR__ . '/raport_z_imprimanta_helper.php';
$printerFormatHelper = __DIR__ . '/printer_bold_helper.php';
if (is_file($printerFormatHelper)) {
    require_once $printerFormatHelper;
}

function agecs_special_z_init_loading_screen(): void
{
    while (ob_get_level() > 0) {
        if (!@ob_end_clean()) {
            break;
        }
    }
    echo '<!DOCTYPE html><html lang="ro"><head><meta charset="utf-8">';
    echo '<style>body{background:#f4f7f6;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;font-family:sans-serif}.card{background:#fff;padding:30px 40px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.1);text-align:center}.spinner{margin-bottom:20px;color:#007bff;font-size:40px;display:inline-block;animation:spin 1.5s linear infinite}@keyframes spin{100%{transform:rotate(360deg)}}.text-muted{color:#6c757d;font-size:16px;margin-top:15px}</style>';
    echo '</head><body><div class="card"><div class="spinner">⏳</div>';
    echo '<h3>Vă rugăm așteptați...</h3>';
    echo '<p class="text-muted" id="loading-status">Se pregătesc datele...</p>';
    echo '</div><script>function updateStatus(msg){document.getElementById("loading-status").textContent=msg;}</script></body></html>';
    flush();
}

function agecs_special_z_update_loading_status(string $message): void
{
    echo '<script>updateStatus(' . json_encode($message, JSON_UNESCAPED_UNICODE) . ');</script>';
    flush();
}

agecs_special_z_init_loading_screen();
agecs_special_z_update_loading_status('Se generează raportul Z din sistem...');

try {
    $requestedReport = isset($_GET['nr_raport_z']) ? (int)$_GET['nr_raport_z'] : 0;
    $locationId = (int)($_SESSION['cod_locatie'] ?? 0);
    $clientId = (int)($_SESSION['client_id'] ?? 0);
    if ($locationId <= 0 || $clientId <= 0) {
        throw new RuntimeException('Clientul sau locația nu sunt stabilite în sesiune.');
    }

    if ($requestedReport > 0) {
        $reportNumber = $requestedReport;
    } else {
        $stmt = $pdo->prepare('SELECT MAX(nr_raport_z) FROM rapoarte_z WHERE cod_locatie = ?');
        $stmt->execute([$locationId]);
        $reportNumber = (int)$stmt->fetchColumn();
    }
    if ($reportNumber <= 0) {
        throw new RuntimeException('Nu există un raport Z valid pentru listare.');
    }

    $baseDirectory = defined('RESTAURANT_OFFLINE_API_DIR')
        ? RESTAURANT_OFFLINE_API_DIR
        : dirname(__DIR__) . DIRECTORY_SEPARATOR . 'api';
    $queueDirectory = rtrim((string)$baseDirectory, '/\\')
        . DIRECTORY_SEPARATOR . $clientId
        . DIRECTORY_SEPARATOR . $locationId;
    if (!is_dir($queueDirectory) && !mkdir($queueDirectory, 0777, true) && !is_dir($queueDirectory)) {
        throw new RuntimeException('Folderul cozii de imprimare nu a putut fi creat.');
    }
    $queuePath = $queueDirectory . DIRECTORY_SEPARATOR . 'de_listat_la_imprimanta.json';

    agecs_special_z_update_loading_status('Se așteaptă eliberarea imprimantei...');
    $waited = 0;
    while (is_file($queuePath) && $waited < 60) {
        sleep(5);
        $waited += 5;
    }

    agecs_ensure_det_note_departament_listare($pdo, 'det_note');
    $documents = agecs_z_print_documents($pdo, $clientId, $locationId, $reportNumber);
    if (function_exists('agecs_printer_bold_content')) {
        foreach ($documents as &$document) {
            $document['continut'] = agecs_printer_bold_content((string)$document['continut'], $clientId);
        }
        unset($document);
    }

    $payload = [
        'status' => 'success',
        'message' => count($documents) > 1
            ? 'Raportul Z și raportul PROTOCOL au fost generate separat.'
            : 'Raportul Z pentru imprimantă a fost generat.',
        'data' => $documents,
    ];
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($queuePath, $json, LOCK_EX) === false) {
        throw new RuntimeException('Coada de imprimare nu a putut fi scrisă.');
    }
    agecs_special_z_update_loading_status(count($documents) > 1
        ? 'Raportul Z și foaia PROTOCOL au fost trimise la imprimantă.'
        : 'Raportul Z a fost trimis la imprimantă.');
} catch (Throwable $error) {
    error_log('vanzare_listare_inchidere_zi: ' . $error->getMessage());
    agecs_special_z_update_loading_status('Raportul nu a putut fi generat. Verifică jurnalul aplicației.');
}

echo '<script>setTimeout(function(){location.href="logout.php";},700);</script>';
?>
