<?php
declare(strict_types=1);

include('session.php');
require_once __DIR__ . '/raport_z_recuperare_lib.php';
require_once __DIR__ . '/offline_sync_queue_lib.php';

date_default_timezone_set('Europe/Bucharest');

$adminId = (int)($_SESSION['admin_id'] ?? 0);
$clientId = (int)($_SESSION['client_id'] ?? 0);
$adminLocation = (int)($_SESSION['cod_locatie'] ?? 0);
$stmtAdmin = $pdo->prepare('SELECT admin_firstname, admin_lastname, locatie FROM admins_12 WHERE admin_id = ? LIMIT 1');
$stmtAdmin->execute([$adminId]);
$admin = $stmtAdmin->fetch(PDO::FETCH_ASSOC) ?: [];
if ($adminLocation <= 0) {
    $adminLocation = (int)($admin['locatie'] ?? 0);
}
if ($adminLocation <= 0) {
    http_response_code(403);
    exit('Locația nu este disponibilă în sesiune.');
}

restaurant_offline_z_recovery_ensure_schema($pdo);
restaurant_offline_z_recovery_sync_ensure_schema($pdo);
$currentIdentity = restaurant_offline_z_recovery_current_identity($pdo, $adminLocation);
$defaultDate = date('Y-m-d', strtotime('-1 day'));
$date = trim((string)($_POST['data_raport'] ?? $_GET['data_raport'] ?? $defaultDate));
$cashSeries = trim((string)($_POST['serie_casa_marcat'] ?? $_GET['serie_casa_marcat'] ?? $currentIdentity['serie_casa_marcat']));
$nui = max(0, (int)($_POST['nui'] ?? $_GET['nui'] ?? $currentIdentity['nui']));
$memory = restaurant_offline_z_recovery_normalize_memory((string)($_POST['serie_memorie_fiscala'] ?? $_GET['serie_memorie_fiscala'] ?? $currentIdentity['serie_memorie_fiscala']));
$reportNumber = max(0, (int)($_POST['nr_raport_z'] ?? $_GET['nr_raport_z'] ?? 0));
$reportTime = trim((string)($_POST['ora_raport_z'] ?? date('H:i:s')));
$printMode = (string)($_POST['mod_tiparire'] ?? 'all');
$action = (string)($_POST['action'] ?? '');
$preview = null;
$success = '';
$warning = '';
$error = '';
$syncResult = null;
$syncEventUuid = '';
$reportNumberSuggested = false;
$availableDays = [];

if (!isset($_SESSION['csrf_sefsala_recuperare_z'])) {
    $_SESSION['csrf_sefsala_recuperare_z'] = bin2hex(random_bytes(24));
}
$csrf = (string)$_SESSION['csrf_sefsala_recuperare_z'];

try {
    $date = restaurant_offline_z_recovery_validate_date($date);
    $historicalIdentities = restaurant_offline_z_recovery_historical_identities($pdo, $adminLocation, $date);
    if (!$historicalIdentities && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        $historicalIdentities = [['serie_casa_marcat' => $cashSeries, 'nui' => $nui, 'serie_memorie_fiscala' => $memory]];
    }
    $availableDays = restaurant_offline_z_recovery_available_days($pdo, $adminLocation, $cashSeries, $nui, $memory);
    if ($reportNumber <= 0) {
        $suggestedReportNumber = restaurant_offline_z_recovery_suggest_report_number(
            $pdo,
            $adminLocation,
            $date,
            $cashSeries,
            $nui,
            $memory
        );
        if ($suggestedReportNumber > 0) {
            $reportNumber = $suggestedReportNumber;
            $reportNumberSuggested = true;
        }
    }
    if ($action !== '') {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
            || !hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('Sesiunea formularului a expirat. Reîncarcă pagina.');
        }
        if ($action === 'preview') {
            $preview = restaurant_offline_z_recovery_preview($pdo, $adminLocation, $date, $cashSeries, $nui, $memory, $reportNumber);
        } elseif ($action === 'execute') {
            if ((string)($_POST['confirmare'] ?? '') !== 'REGENEREAZA RAPORT Z') {
                throw new RuntimeException('Confirmarea exactă este obligatorie pentru executare.');
            }
            $result = restaurant_offline_z_recovery_execute(
                $pdo,
                $clientId,
                $adminId,
                $adminLocation,
                $date,
                $reportTime,
                $cashSeries,
                $nui,
                $memory,
                $reportNumber,
                $printMode
            );

            $queueConfig = restaurant_sync_queue_config($restaurantConfig);
            $syncQueued = 0;
            foreach ((array)$result['closure_ids'] as $closureId) {
                $syncQueued += restaurant_sync_queue_enqueue_safely(static function () use ($pdo, $queueConfig, $closureId, $adminId): bool {
                    return restaurant_sync_queue_enqueue_shift($pdo, $queueConfig, (int)$closureId, $adminId);
                }) ? 1 : 0;
            }
            if ((int)$result['report_id'] > 0) {
                $syncQueued += restaurant_sync_queue_enqueue_safely(static function () use ($pdo, $queueConfig, $result, $adminId): bool {
                    return restaurant_sync_queue_enqueue_z($pdo, $queueConfig, (int)$result['report_id'], $adminId);
                }) ? 1 : 0;
            }

            try {
                $syncPayload = restaurant_offline_z_recovery_sync_payload(
                    $pdo,
                    $restaurantConfig,
                    $result,
                    [
                        'id' => $adminId,
                        'nume' => trim((string)($admin['admin_firstname'] ?? '') . ' ' . (string)($admin['admin_lastname'] ?? '')),
                    ]
                );
                $syncEventUuid = (string)$syncPayload['event_uuid'];
                $syncResult = restaurant_offline_z_recovery_sync_online($pdo, $restaurantConfig, $syncPayload);
            } catch (Throwable $syncError) {
                $warning = $syncEventUuid !== ''
                    ? 'Datele locale au fost reparate, dar actualizarea online nu a fost confirmată. Evenimentul rămâne în jurnalul local și poate fi retrimis din această pagină. Detalii: ' . $syncError->getMessage()
                    : 'Datele locale au fost reparate, dar pachetul pentru actualizarea online nu a putut fi pregătit. Detalii: ' . $syncError->getMessage();
            }

            try {
                $printResult = restaurant_offline_z_recovery_queue_print(
                    $pdo,
                    $clientId,
                    $adminLocation,
                    $result,
                    trim((string)($admin['admin_firstname'] ?? '') . ' ' . (string)($admin['admin_lastname'] ?? ''))
                );
            } catch (Throwable $printError) {
                error_log('Recuperare Z: datele locale au fost salvate, dar tipărirea a eșuat: ' . $printError->getMessage());
                $printResult = ['queued' => false, 'documents' => 0, 'message' => 'Datele au fost reparate, dar documentele nu au putut fi puse în coada imprimantei.'];
            }
            $onlineSyncStatus = (string)($syncResult['local_status'] ?? '');
            if ($onlineSyncStatus === 'sent') {
                $onlineSyncMessage = ' Actualizarea dedicată online a fost confirmată.';
            } elseif ($onlineSyncStatus === 'partial') {
                $onlineSyncMessage = ' Actualizarea online este parțială. Rândurile care nu au fost încă importate pot fi retrimise din jurnalul local.';
                $warning = $warning !== '' ? $warning : 'Actualizarea online este parțială. Retrimite evenimentul după ce sincronizarea obișnuită a importat notele și închiderile.';
            } elseif ($onlineSyncStatus === 'pending') {
                $onlineSyncMessage = ' Actualizarea online a fost păstrată local și așteaptă disponibilitatea sincronizării.';
                $warning = $warning !== '' ? $warning : 'Actualizarea online a rămas în așteptare. Retrimite evenimentul după ce sincronizarea este disponibilă.';
            } else {
                $onlineSyncMessage = '';
            }
            $success = 'Raportul Z intern nr. ' . $reportNumber . ' a fost reparat pentru data ' . $date . '. ' . $printResult['message'] . ' Evenimente de sincronizare obișnuită pregătite: ' . $syncQueued . '.' . $onlineSyncMessage;
            if (!$printResult['queued'] && $printMode !== 'none') {
                $printWarning = 'Verifică imprimanta înainte de reluarea tipăririi. Raportul fiscal din casa de marcat nu a fost modificat.';
                $warning = $warning !== '' ? $warning . ' ' . $printWarning : $printWarning;
            }
            $preview = restaurant_offline_z_recovery_preview($pdo, $adminLocation, $date, $cashSeries, $nui, $memory, $reportNumber);
        } elseif ($action === 'retry_sync') {
            $syncEventUuid = trim((string)($_POST['event_uuid'] ?? ''));
            $syncPayload = restaurant_offline_z_recovery_sync_payload_by_event($pdo, $syncEventUuid);
            if (!$syncPayload) {
                throw new RuntimeException('Evenimentul de sincronizare al recuperării Z nu mai există local.');
            }
            $expectedInstallation = trim((string)($restaurantConfig['transaction_uuid'] ?? $restaurantConfig['installation_uuid'] ?? ''));
            if ((int)($syncPayload['client_id'] ?? 0) !== $clientId
                || (int)($syncPayload['cod_locatie'] ?? 0) !== $adminLocation
                || trim((string)($syncPayload['installation_uuid'] ?? '')) !== $expectedInstallation) {
                throw new RuntimeException('Evenimentul nu aparține aceleiași instalații offline și nu poate fi retrimis de aici.');
            }
            $date = restaurant_offline_z_recovery_validate_date((string)($syncPayload['data_raport'] ?? $date));
            $cashSeries = trim((string)($syncPayload['serie_casa_marcat'] ?? $cashSeries));
            $nui = max(0, (int)($syncPayload['nui'] ?? $nui));
            $memory = restaurant_offline_z_recovery_normalize_memory((string)($syncPayload['serie_memorie_fiscala'] ?? $memory));
            $reportNumber = max(0, (int)($syncPayload['nr_raport_z'] ?? $reportNumber));
            $syncResult = restaurant_offline_z_recovery_sync_online($pdo, $restaurantConfig, $syncPayload);
            $syncStatus = (string)($syncResult['local_status'] ?? '');
            if ($syncStatus === 'sent') {
                $success = 'Actualizarea online pentru recuperarea Z nr. ' . $reportNumber . ' a fost confirmată.';
            } elseif ($syncStatus === 'partial') {
                $warning = 'Actualizarea online rămâne parțială. Unele note sau închideri nu sunt încă importate și pot fi retrimise ulterior.';
            } else {
                $warning = 'Actualizarea online rămâne în așteptare. Evenimentul poate fi retrimis după restabilirea sincronizării.';
            }
        } else {
            throw new RuntimeException('Acțiune necunoscută.');
        }
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$recentSyncs = [];
try {
    $recentSyncs = restaurant_offline_z_recovery_sync_recent($pdo, $adminLocation);
} catch (Throwable $e) {
    if ($error === '') {
        $error = 'Jurnalul sincronizărilor recuperării Z nu poate fi citit: ' . $e->getMessage();
    }
}
$printerAvailable = restaurant_offline_z_recovery_printer_available();

function restaurant_offline_z_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperare raport Z offline</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body { background: #f4f7f6; }
        .page { max-width: 1120px; margin: 24px auto; }
        .card { box-shadow: 0 2px 10px rgba(0,0,0,.06); }
        .metric { min-height: 92px; }
        .metric strong { display: block; font-size: 1.45rem; }
        .warning-list li { margin-bottom: .35rem; }
        code { color: #1f2937; }
    </style>
</head>
<body>
<main class="page">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-1">Recuperare raport Z offline</h2>
            <div class="text-muted">Locația <?php echo restaurant_offline_z_h((string)$adminLocation); ?>, client <?php echo restaurant_offline_z_h((string)$clientId); ?></div>
        </div>
        <a href="sefsala.php" class="btn btn-outline-secondary">Înapoi la Șef sală</a>
    </div>

    <div class="alert alert-warning">
        Această pagină lucrează numai cu datele din SQLite ale instalației offline. Poate regenera numai un raport Z creat în fluxul offline și nu citește sau modifică note ori rapoarte Z create din online. După executare, corecția se pune în coada obișnuită și se trimite printr-un pachet dedicat către online, fără să emită sau să modifice raportul Z fiscal din casa de marcat. Introdu numărul și ora efective citite de pe casa de marcat.
    </div>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?php echo restaurant_offline_z_h($error); ?></div><?php endif; ?>
    <?php if ($success !== ''): ?><div class="alert alert-success"><?php echo restaurant_offline_z_h($success); ?></div><?php endif; ?>
    <?php if ($warning !== ''): ?><div class="alert alert-warning"><?php echo restaurant_offline_z_h($warning); ?></div><?php endif; ?>

    <div class="card mb-3">
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="csrf" value="<?php echo restaurant_offline_z_h($csrf); ?>">
                <div class="form-row">
                    <div class="form-group col-md-3">
                        <label for="data_raport">Data vânzărilor</label>
                        <input type="date" class="form-control" id="data_raport" name="data_raport" value="<?php echo restaurant_offline_z_h($date); ?>" required>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="nr_raport_z">Număr Z efectiv</label>
                        <input type="number" class="form-control" id="nr_raport_z" name="nr_raport_z" min="1" value="<?php echo $reportNumber > 0 ? restaurant_offline_z_h((string)$reportNumber) : ''; ?>" required>
                        <?php if ($reportNumberSuggested): ?>
                            <small class="form-text text-success">A fost identificat numărul Z <?php echo restaurant_offline_z_h((string)$reportNumber); ?> pentru data aleasă. Dacă nu îl schimbi, recuperarea rămâne pe același raport intern.</small>
                        <?php else: ?>
                            <small class="form-text text-muted">Introdu numărul de pe bonul Z fiscal. Numărul este sugerat automat numai când există o identificare unică pentru data aleasă.</small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="ora_raport_z">Ora Z efectivă</label>
                        <input type="time" step="1" class="form-control" id="ora_raport_z" name="ora_raport_z" value="<?php echo restaurant_offline_z_h($reportTime); ?>" required>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="mod_tiparire">Tipărire</label>
                        <select class="form-control" id="mod_tiparire" name="mod_tiparire">
                            <option value="none" <?php echo $printMode === 'none' ? 'selected' : ''; ?>>Fără tipărire</option>
                            <?php if ($printerAvailable): ?>
                                <option value="final" <?php echo $printMode === 'final' ? 'selected' : ''; ?>>Doar raportul final și protocolul clientului</option>
                                <option value="all" <?php echo $printMode === 'all' ? 'selected' : ''; ?>>Toate închiderile de tură și raportul final</option>
                            <?php endif; ?>
                        </select>
                        <?php if (!$printerAvailable): ?>
                            <small class="form-text text-muted">Această instalație nu are coadă locală de imprimare configurată. Recuperarea se poate face fără tipărire.</small>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-3">
                        <label for="serie_casa_marcat">Seria casei de marcat</label>
                        <input type="text" class="form-control" id="serie_casa_marcat" name="serie_casa_marcat" value="<?php echo restaurant_offline_z_h($cashSeries); ?>">
                    </div>
                    <div class="form-group col-md-2">
                        <label for="nui">NUI</label>
                        <input type="number" class="form-control" id="nui" name="nui" min="0" value="<?php echo restaurant_offline_z_h((string)$nui); ?>">
                    </div>
                    <div class="form-group col-md-3">
                        <label for="serie_memorie_fiscala">Seria memoriei fiscale</label>
                        <input type="text" class="form-control" id="serie_memorie_fiscala" name="serie_memorie_fiscala" value="<?php echo restaurant_offline_z_h($memory); ?>">
                    </div>
                    <div class="form-group col-md-4 d-flex align-items-end">
                        <button class="btn btn-primary mr-2" type="submit" name="action" value="preview">Previzualizează</button>
                        <a class="btn btn-outline-secondary" href="sefsala.php">Renunță</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if ($preview !== null): ?>
        <div class="card mb-3">
            <div class="card-header">Previzualizare pentru <?php echo restaurant_offline_z_h($preview['date']); ?></div>
            <div class="card-body">
                <?php if ($preview['conflicts']): ?>
                    <div class="alert alert-danger">
                        <strong>Executarea este blocată.</strong>
                        <ul class="warning-list mb-0 mt-2">
                            <?php foreach ($preview['conflicts'] as $conflict): ?><li><?php echo restaurant_offline_z_h((string)$conflict); ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                <?php else: ?>
                    <div class="row text-center mb-3">
                        <div class="col-md-3"><div class="border rounded p-2 metric"><span class="text-muted">Note finalizate</span><strong><?php echo restaurant_offline_z_h((string)$preview['note_count']); ?></strong></div></div>
                        <div class="col-md-3"><div class="border rounded p-2 metric"><span class="text-muted">Note nelegate la Z</span><strong><?php echo restaurant_offline_z_h((string)$preview['unassigned_count']); ?></strong></div></div>
                        <div class="col-md-3"><div class="border rounded p-2 metric"><span class="text-muted">Închideri găsite</span><strong><?php echo restaurant_offline_z_h((string)count($preview['closures'])); ?></strong></div></div>
                        <div class="col-md-3"><div class="border rounded p-2 metric"><span class="text-muted">Operatori</span><strong><?php echo restaurant_offline_z_h((string)count($preview['operators'])); ?></strong></div></div>
                    </div>
                    <p class="mb-1"><strong>Coduri de închidere:</strong> <?php echo restaurant_offline_z_h($preview['codes'] ? implode(', ', $preview['codes']) : 'vor fi generate automat pentru operatorii fără cod'); ?></p>
                    <p class="mb-1"><strong>Numerar:</strong> <?php echo restaurant_offline_z_h(number_format((float)$preview['totals']['numerar'], 2, ',', '.')); ?> lei, <strong>card:</strong> <?php echo restaurant_offline_z_h(number_format((float)$preview['totals']['card'], 2, ',', '.')); ?> lei, <strong>tichete:</strong> <?php echo restaurant_offline_z_h(number_format((float)$preview['totals']['tichete'], 2, ',', '.')); ?> lei</p>
                    <p class="mb-0"><strong>Raport intern:</strong> <?php echo $preview['target_report'] ? 'există și va fi actualizat pe același rând' : 'nu există și va fi creat cu numărul introdus'; ?></p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($preview['can_execute']): ?>
            <div class="card border-danger">
                <div class="card-body">
                    <h5 class="text-danger">Executare controlată</h5>
                    <div class="alert alert-info">
                        <strong>Sursa este blocată pe OFFLINE.</strong> Vor fi selectate și actualizate exclusiv notele, închiderile și raportul din SQLite local, pentru locația și identitatea fiscală afișate. Online vor fi actualizate numai rândurile care au fost importate din această instalație offline. Notele și raportul Z create din online nu sunt citite, suprascrise sau amestecate cu această regenerare. Dacă unele rânduri offline nu au ajuns încă online, actualizarea rămâne parțială și poate fi retrimisă din jurnalul de mai jos după sincronizarea obișnuită.
                    </div>
                    <form method="post">
                        <input type="hidden" name="csrf" value="<?php echo restaurant_offline_z_h($csrf); ?>">
                        <input type="hidden" name="data_raport" value="<?php echo restaurant_offline_z_h($date); ?>">
                        <input type="hidden" name="nr_raport_z" value="<?php echo restaurant_offline_z_h((string)$reportNumber); ?>">
                        <input type="hidden" name="ora_raport_z" value="<?php echo restaurant_offline_z_h($reportTime); ?>">
                        <input type="hidden" name="serie_casa_marcat" value="<?php echo restaurant_offline_z_h($cashSeries); ?>">
                        <input type="hidden" name="nui" value="<?php echo restaurant_offline_z_h((string)$nui); ?>">
                        <input type="hidden" name="serie_memorie_fiscala" value="<?php echo restaurant_offline_z_h($memory); ?>">
                        <input type="hidden" name="mod_tiparire" value="<?php echo restaurant_offline_z_h($printMode); ?>">
                        <div class="form-row align-items-end">
                            <div class="form-group col-md-5 mb-md-0">
                                <label for="confirmare">Scrie exact <code>REGENEREAZA RAPORT Z</code></label>
                                <input type="text" class="form-control" id="confirmare" name="confirmare" autocomplete="off" required>
                            </div>
                            <div class="form-group col-md-4 mb-md-0">
                                <button class="btn btn-danger" type="submit" name="action" value="execute">Regenerează local și sincronizează</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="card mt-3">
        <div class="card-header">Zile disponibile pentru recuperarea raportului Z</div>
        <div class="card-body">
            <?php if ($availableDays): ?>
                <?php foreach ($availableDays as $availableDay): ?>
                    <div><?php echo restaurant_offline_z_h((string)$availableDay['report_date']); ?>,
                        <?php echo restaurant_offline_z_h((string)$availableDay['unassigned_count']); ?> note nelegate,
                        <?php echo restaurant_offline_z_h((string)$availableDay['operator_count']); ?> operatori,
                        <?php echo restaurant_offline_z_h(number_format((float)$availableDay['total_value'], 2, ',', '.')); ?> lei
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <span class="text-muted">Nu există zile cu note offline finalizate și nelegate la un raport Z pentru locația și identitatea fiscală selectate.</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($recentSyncs): ?>
        <div class="card mt-3">
            <div class="card-header">Jurnalul actualizărilor online pentru recuperări offline</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Data Z</th>
                                <th>Raport local</th>
                                <th>Stare</th>
                                <th>Ultimul mesaj</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentSyncs as $syncRow): ?>
                                <?php
                                $syncStatus = (string)($syncRow['status'] ?? '');
                                $syncMessage = trim((string)($syncRow['last_error'] ?? ''));
                                if ($syncMessage === '' && !empty($syncRow['online_response_json'])) {
                                    $syncResponse = json_decode((string)$syncRow['online_response_json'], true);
                                    if (is_array($syncResponse)) {
                                        $syncMessage = trim((string)($syncResponse['message'] ?? ''));
                                    }
                                }
                                $canRetrySync = in_array($syncStatus, ['failed', 'pending', 'partial'], true);
                                ?>
                                <tr>
                                    <td><?php echo restaurant_offline_z_h((string)($syncRow['data_raport'] ?? '')); ?></td>
                                    <td><?php echo restaurant_offline_z_h((string)($syncRow['raport_id'] ?? '')); ?></td>
                                    <td><?php echo restaurant_offline_z_h($syncStatus); ?></td>
                                    <td><?php echo restaurant_offline_z_h($syncMessage !== '' ? $syncMessage : 'Actualizare confirmată.'); ?></td>
                                    <td class="text-right">
                                        <?php if ($canRetrySync): ?>
                                            <form method="post" class="m-0">
                                                <input type="hidden" name="csrf" value="<?php echo restaurant_offline_z_h($csrf); ?>">
                                                <input type="hidden" name="event_uuid" value="<?php echo restaurant_offline_z_h((string)($syncRow['event_uuid'] ?? '')); ?>">
                                                <button class="btn btn-sm btn-outline-primary" type="submit" name="action" value="retry_sync">Retrimite</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>
<script>
(function () {
    var dateInput = document.getElementById('data_raport');
    var reportInput = document.getElementById('nr_raport_z');
    var suggested = <?php echo $reportNumberSuggested ? 'true' : 'false'; ?>;
    if (!dateInput || !reportInput || !suggested) {
        return;
    }
    var initialDate = dateInput.value;
    dateInput.addEventListener('change', function () {
        if (dateInput.value !== initialDate) {
            reportInput.value = '';
        }
    });
})();
</script>
</body>
</html>
