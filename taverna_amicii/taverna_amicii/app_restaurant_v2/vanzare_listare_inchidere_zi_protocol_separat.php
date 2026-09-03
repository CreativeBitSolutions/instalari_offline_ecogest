<?php
declare(strict_types=1);

require_once __DIR__ . '/det_note_departament_listare_schema.php';
require_once __DIR__ . '/raport_z_imprimanta_helper.php';
require_once __DIR__ . '/offline_printer_flow_helper.php';
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

    agecs_ensure_det_note_departament_listare($pdo, 'det_note');
    $documents = agecs_z_print_documents($pdo, $clientId, $locationId, $reportNumber);

    $pending = $_SESSION['restaurant_pending_closure_print'] ?? null;
    if (
        is_array($pending)
        && (int)($pending['client_id'] ?? 0) === $clientId
        && (int)($pending['location_id'] ?? 0) === $locationId
        && isset($pending['jobs'])
        && is_array($pending['jobs'])
    ) {
        $documents = array_values(array_merge($pending['jobs'], $documents));
    }

    if (function_exists('agecs_printer_bold_content')) {
        foreach ($documents as &$document) {
            $document['continut'] = agecs_printer_bold_content((string)$document['continut'], $clientId);
        }
        unset($document);
    }

    $queueHelper = rtrim((string)$baseDirectory, '/\\')
        . DIRECTORY_SEPARATOR . 'printer_queue_atomic_helper.php';
    if (!is_file($queueHelper)) {
        throw new RuntimeException('Helperul pentru coada atomică a imprimantei lipsește.');
    }
    require_once $queueHelper;
    if (!agecs_printer_queue_append_documents(
        $queuePath,
        $documents,
        'Închiderea turei, raportul Z și raportul PROTOCOL au fost grupate pentru imprimare.'
    )) {
        throw new RuntimeException('Coada combinată de imprimare nu a putut fi scrisă.');
    }
    unset($_SESSION['restaurant_pending_closure_print']);
    agecs_special_z_update_loading_status('Închiderea, raportul Z și foaia PROTOCOL au fost trimise împreună la imprimantă.');
} catch (Throwable $error) {
    error_log('vanzare_listare_inchidere_zi: ' . $error->getMessage());
    $_SESSION['offline_printer_error'] = 'Închiderea și raportul Z sunt salvate, dar documentele nu au putut fi puse în coada imprimantei. Folosiți relistarea după verificarea scannerului.';
    agecs_special_z_update_loading_status('Raportul nu a putut fi generat. Verifică jurnalul aplicației.');
}

echo agecs_offline_printer_redirect_script('logout.php', 'inchidere_z', 300);
?>
