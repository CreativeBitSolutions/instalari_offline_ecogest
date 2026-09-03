<?php
declare(strict_types=1);

ini_set('display_errors', '0');
date_default_timezone_set('Europe/Bucharest');

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/offline_printer_flow_helper.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function lorand_fiscal_direct_response(bool $success, string $code, string $message, array $extra = []): void
{
    echo json_encode(array_merge([
        'success' => $success,
        'code' => $code,
        'message' => $message,
    ], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function lorand_fiscal_direct_is_local(): bool
{
    $address = strtolower(trim((string)($_SERVER['REMOTE_ADDR'] ?? '')));
    return in_array($address, ['127.0.0.1', '::1', '::ffff:127.0.0.1'], true);
}

function lorand_fiscal_direct_log(string $message, array $context = []): void
{
    $directory = lorand_offline_api_root() . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
        return;
    }

    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    if ($context !== []) {
        $encoded = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($encoded)) {
            $line .= ' ' . $encoded;
        }
    }
    @file_put_contents(
        $directory . DIRECTORY_SEPARATOR . 'direct_fiscal_inp.log',
        $line . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

function lorand_fiscal_direct_load_config(string $path): array
{
    if (!is_file($path)) {
        throw new RuntimeException('Configurarea scannerului casei de marcat nu a fost găsită.');
    }

    $raw = file_get_contents($path);
    if (!is_string($raw)) {
        throw new RuntimeException('Configurarea scannerului casei de marcat nu a putut fi citită.');
    }
    if (strncmp($raw, "\xEF\xBB\xBF", 3) === 0) {
        $raw = substr($raw, 3);
    }

    $config = json_decode($raw, true);
    if (!is_array($config)) {
        throw new RuntimeException('Configurarea scannerului casei de marcat nu este un JSON valid.');
    }
    return $config;
}

function lorand_fiscal_direct_normalize_content($content): string
{
    $content = (string)$content;
    while (strncmp($content, "\xEF\xBB\xBF", 3) === 0) {
        $content = substr($content, 3);
    }

    $content = str_replace(["\r\n", "\r"], "\n", $content);
    $content = str_replace("\0", '', $content);
    $content = str_replace("\n", "\r\n", $content);

    if (!preg_match('/^(?:K|H|S),1,/', $content)) {
        throw new RuntimeException('Conținutul fiscal nu începe cu o comandă K, H sau S validă.');
    }
    if (!preg_match('/(?:^|\r\n)S,1,/', $content) || !preg_match('/(?:^|\r\n)T,1,/', $content)) {
        throw new RuntimeException('Conținutul fiscal nu conține liniile obligatorii S și T.');
    }
    if (substr($content, -2) !== "\r\n") {
        $content .= "\r\n";
    }
    if (strncmp($content, "\xEF\xBB\xBF", 3) === 0) {
        throw new RuntimeException('Conținutul fiscal conține UTF-8 BOM.');
    }

    return $content;
}

function lorand_fiscal_direct_extension(array $config): string
{
    $extension = strtolower(trim((string)($config['Extensie'] ?? 'inp')));
    $extension = (string)preg_replace('/[^a-z0-9]/', '', $extension);
    return $extension !== '' ? $extension : 'inp';
}

function lorand_fiscal_direct_atomic_write(
    string $directory,
    string $baseName,
    string $extension,
    string $content
): string {
    $directory = rtrim(trim($directory), '/\\');
    if ($directory === '') {
        throw new RuntimeException('Folderul casei de marcat nu este configurat.');
    }
    if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Folderul casei de marcat nu a putut fi creat.');
    }

    $safeName = (string)preg_replace('/[^a-zA-Z0-9_-]/', '_', $baseName);
    $finalPath = $directory . DIRECTORY_SEPARATOR . $safeName . '.' . $extension;
    if (is_file($finalPath)) {
        $finalPath = $directory . DIRECTORY_SEPARATOR . $safeName . '_'
            . agecs_printer_queue_token() . '.' . $extension;
    }
    $temporaryPath = $finalPath . '.tmp.' . getmypid() . '.' . agecs_printer_queue_token();

    try {
        $written = file_put_contents($temporaryPath, $content, LOCK_EX);
        if ($written === false || $written !== strlen($content)) {
            throw new RuntimeException('Fișierul fiscal temporar nu a putut fi scris integral.');
        }

        $prefix = @file_get_contents($temporaryPath, false, null, 0, 3);
        if ($prefix === "\xEF\xBB\xBF") {
            throw new RuntimeException('Fișierul fiscal temporar conține UTF-8 BOM.');
        }
        if (!@rename($temporaryPath, $finalPath)) {
            throw new RuntimeException('Fișierul fiscal nu a putut fi publicat în folderul configurat.');
        }
    } finally {
        if (is_file($temporaryPath)) {
            @unlink($temporaryPath);
        }
    }

    return $finalPath;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    lorand_fiscal_direct_response(false, 'method_not_allowed', 'Metoda alternativă acceptă numai cereri locale POST.');
}
if (!lorand_fiscal_direct_is_local()) {
    lorand_fiscal_direct_response(false, 'local_only', 'Trimiterea alternativă este disponibilă numai din aplicația locală.');
}

$clientId = (int)($_SESSION['client_id'] ?? 0);
$locationId = (int)($_SESSION['cod_locatie'] ?? 0);
if ($clientId !== 1019 || $locationId <= 0 || (int)($_SESSION['admin_id'] ?? 0) <= 0) {
    lorand_fiscal_direct_response(false, 'forbidden', 'Sesiunea operatorului Lorand nu este activă.');
}

$expectedToken = (string)($_SESSION['lorand_direct_fiscal_csrf'] ?? '');
$receivedToken = (string)($_POST['csrf_token'] ?? '');
if ($expectedToken === '' || $receivedToken === '' || !hash_equals($expectedToken, $receivedToken)) {
    lorand_fiscal_direct_response(false, 'invalid_request', 'Cererea a expirat. Reîncărcați pagina de așteptare.');
}

$apiRoot = lorand_offline_api_root();
$installationRoot = dirname($apiRoot);
$scannerDirectory = $installationRoot . DIRECTORY_SEPARATOR . 'ecogest_casa_marcat_v3_inp';
$configPath = $scannerDirectory . DIRECTORY_SEPARATOR . 'config.json';
$queuePath = lorand_fiscal_queue_path($clientId, $locationId);
$queueHelper = $apiRoot . DIRECTORY_SEPARATOR . 'printer_queue_atomic_helper.php';
if (!is_file($queueHelper)) {
    lorand_fiscal_direct_response(false, 'missing_helper', 'Lipsește componenta care protejează coada casei de marcat.');
}
require_once $queueHelper;

try {
    $config = lorand_fiscal_direct_load_config($configPath);
    if ((int)($config['client_id'] ?? 0) !== $clientId) {
        throw new RuntimeException('Clientul din configurarea scannerului nu corespunde sesiunii curente.');
    }
    if ((int)($config['LocationId'] ?? 0) !== $locationId) {
        throw new RuntimeException('Locația din configurarea scannerului nu corespunde sesiunii curente.');
    }

    $destinationDirectory = trim((string)($config['CasaMarcatFolder'] ?? ''));
    $backupDirectory = trim((string)($config['BackupFolder'] ?? ''));
    $extension = lorand_fiscal_direct_extension($config);
    if ($destinationDirectory === '') {
        throw new RuntimeException('CasaMarcatFolder nu este configurat în scanner.');
    }
} catch (Throwable $error) {
    lorand_fiscal_direct_log('Configurarea scannerului fiscal nu a putut fi folosită.', [
        'config_path' => $configPath,
        'error' => $error->getMessage(),
    ]);
    lorand_fiscal_direct_response(false, 'settings_error', $error->getMessage());
}

session_write_close();

try {
    $claim = agecs_printer_queue_claim($queuePath);
} catch (Throwable $error) {
    lorand_fiscal_direct_log('Coada fiscală nu a putut fi revendicată.', ['error' => $error->getMessage()]);
    lorand_fiscal_direct_response(false, 'claim_error', 'Coada casei de marcat nu a putut fi accesată în siguranță.');
}

if (($claim['status'] ?? '') === 'empty') {
    lorand_fiscal_direct_response(true, 'already_claimed', 'Bonul a fost deja preluat de scannerul casei de marcat.');
}
if (($claim['status'] ?? '') !== 'claimed') {
    lorand_fiscal_direct_response(false, 'claim_error', 'Bonul nu a putut fi rezervat pentru trimiterea alternativă.');
}

$claimedPath = (string)($claim['path'] ?? '');
$raw = $claimedPath !== '' ? @file_get_contents($claimedPath) : false;
if (is_string($raw) && strncmp($raw, "\xEF\xBB\xBF", 3) === 0) {
    $raw = substr($raw, 3);
}
$payload = is_string($raw) ? json_decode($raw, true) : null;
if (!is_array($payload) || !isset($payload['data']) || !is_array($payload['data'])) {
    $restored = agecs_printer_queue_restore_claim($claimedPath, $queuePath);
    lorand_fiscal_direct_response(false, 'invalid_queue', $restored
        ? 'Fișierul fiscal din coadă nu este valid și a fost restaurat.'
        : 'Fișierul fiscal din coadă nu este valid și necesită verificare manuală.', [
        'restored' => $restored,
    ]);
}

$documents = array_values($payload['data']);
if ($documents === []) {
    @unlink($claimedPath);
    lorand_fiscal_direct_response(true, 'empty', 'Coada fiscală nu conține bonuri.');
}

$prepared = [];
try {
    foreach ($documents as $document) {
        if (!is_array($document)) {
            throw new RuntimeException('Un bon din coadă nu are structură validă.');
        }
        $nrBon = (int)($document['nrbon'] ?? 0);
        if ($nrBon <= 0) {
            throw new RuntimeException('Numărul bonului fiscal lipsește sau nu este valid.');
        }
        $prepared[] = [
            'nrbon' => $nrBon,
            'content' => lorand_fiscal_direct_normalize_content($document['continut_bon'] ?? ''),
            'source' => $document,
        ];
    }
} catch (Throwable $error) {
    $restored = agecs_printer_queue_restore_claim($claimedPath, $queuePath);
    lorand_fiscal_direct_log('Conținutul fiscal nu a trecut validarea.', ['error' => $error->getMessage()]);
    lorand_fiscal_direct_response(false, 'validation_error', $error->getMessage(), [
        'restored' => $restored,
    ]);
}

$writtenCount = 0;
$writtenFiles = [];
$backupWarnings = [];
try {
    foreach ($prepared as $item) {
        $baseName = 'Bon_' . $item['nrbon'] . '_' . date('Ymd_His') . '_'
            . agecs_printer_queue_token();
        $finalPath = lorand_fiscal_direct_atomic_write(
            $destinationDirectory,
            $baseName,
            $extension,
            $item['content']
        );
        $writtenCount++;
        $writtenFiles[] = basename($finalPath);

        if ($backupDirectory !== ''
            && strcasecmp(rtrim($backupDirectory, '/\\'), rtrim($destinationDirectory, '/\\')) !== 0) {
            try {
                lorand_fiscal_direct_atomic_write(
                    $backupDirectory,
                    pathinfo($finalPath, PATHINFO_FILENAME),
                    $extension,
                    $item['content']
                );
            } catch (Throwable $backupError) {
                $backupWarnings[] = $backupError->getMessage();
                lorand_fiscal_direct_log('Fișierul principal a fost trimis, dar backupul a eșuat.', [
                    'nrbon' => $item['nrbon'],
                    'error' => $backupError->getMessage(),
                ]);
            }
        }

        lorand_fiscal_direct_log('Fișier INP publicat direct, fără BOM.', [
            'nrbon' => $item['nrbon'],
            'file' => $finalPath,
        ]);
    }
} catch (Throwable $error) {
    $remainingDocuments = array_map(
        static function (array $item): array {
            return $item['source'];
        },
        array_slice($prepared, $writtenCount)
    );
    $restored = false;
    try {
        if ($remainingDocuments !== []) {
            agecs_printer_queue_append_documents(
                $queuePath,
                $remainingDocuments,
                'Bonuri păstrate după eșecul trimiterii fiscale directe.'
            );
        }
        if ($claimedPath !== '' && is_file($claimedPath)) {
            @unlink($claimedPath);
        }
        $restored = true;
    } catch (Throwable $restoreError) {
        lorand_fiscal_direct_log('Bonurile rămase nu au putut fi restaurate automat.', [
            'claimed_path' => $claimedPath,
            'error' => $restoreError->getMessage(),
        ]);
    }

    lorand_fiscal_direct_response(false, 'write_error', $restored
        ? 'Fișierul INP nu a putut fi scris. Bonurile rămase au revenit în coada normală.'
        : 'Scrierea INP s-a oprit. Bonul rezervat necesită verificare manuală.', [
        'written' => $writtenCount,
        'remaining' => count($remainingDocuments),
        'restored' => $restored,
    ]);
}

$removed = $claimedPath === '' || !is_file($claimedPath) || @unlink($claimedPath);
$message = $writtenCount === 1
    ? 'Fișierul INP a fost pus direct în folderul casei de marcat, fără BOM.'
    : $writtenCount . ' fișiere INP au fost puse direct în folderul casei de marcat, fără BOM.';
if ($backupWarnings !== []) {
    $message .= ' Fișierul principal este trimis, dar backupul necesită verificare.';
}
if (!$removed) {
    $message .= ' Lotul procesat nu a putut fi eliminat. Nu repetați trimiterea.';
}

lorand_fiscal_direct_response(true, $removed ? 'written' : 'written_cleanup_warning', $message, [
    'written' => $writtenCount,
    'files' => $writtenFiles,
    'backup_warning' => $backupWarnings !== [],
    'cleanup_warning' => !$removed,
]);
