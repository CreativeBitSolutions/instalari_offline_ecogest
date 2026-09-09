<?php
declare(strict_types=1);

ini_set('display_errors', '0');
date_default_timezone_set('Europe/Bucharest');

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/offline_printer_flow_helper.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function agecs_fiscal_inp_response(bool $success, string $code, string $message, array $extra = []): void
{
    echo json_encode(
        array_merge([
            'success' => $success,
            'code' => $code,
            'message' => $message,
        ], $extra),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function agecs_fiscal_inp_log(string $message, array $context = []): void
{
    $directory = rtrim((string)RESTAURANT_OFFLINE_API_DIR, '/\\')
        . DIRECTORY_SEPARATOR . 'logs';
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

function agecs_fiscal_inp_load_config(string $path): array
{
    if (!is_file($path)) {
        throw new RuntimeException('Configurarea scannerului casei de marcat nu a fost găsită.');
    }

    $raw = file_get_contents($path);
    if (is_string($raw) && strncmp($raw, "\xEF\xBB\xBF", 3) === 0) {
        $raw = substr($raw, 3);
    }
    $config = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($config)) {
        throw new RuntimeException('Configurarea scannerului casei de marcat nu este un JSON valid.');
    }

    return $config;
}

function agecs_fiscal_inp_normalize_content($content): string
{
    $content = (string)$content;
    while (strncmp($content, "\xEF\xBB\xBF", 3) === 0) {
        $content = substr($content, 3);
    }

    $content = str_replace(["\r\n", "\r"], "\n", $content);
    $content = str_replace("\0", '', $content);
    $content = str_replace("\n", "\r\n", $content);

    $startsWithProduct = strncmp($content, 'S,1,', 4) === 0;
    $startsWithCui = strncmp($content, 'K,1,', 4) === 0;
    if (!$startsWithProduct && !$startsWithCui) {
        throw new RuntimeException('Bonul FiscalWire nu începe cu linia S,1 sau K,1 și nu a fost trimis.');
    }
    if ($startsWithCui) {
        $firstLineEnd = strpos($content, "\r\n");
        $lineAfterCui = $firstLineEnd === false ? '' : substr($content, $firstLineEnd + 2);
        if (strncmp($lineAfterCui, 'S,1,', 4) !== 0) {
            throw new RuntimeException('Linia K a bonului FiscalWire nu este urmată de o linie S,1.');
        }
    }
    if ($content === '' || substr($content, -2) !== "\r\n") {
        $content .= "\r\n";
    }
    if (strncmp($content, "\xEF\xBB\xBF", 3) === 0) {
        throw new RuntimeException('Bonul conține UTF-8 BOM și nu a fost trimis.');
    }

    return $content;
}

function agecs_fiscal_inp_safe_extension(array $config): string
{
    $extension = strtolower(trim((string)($config['Extensie'] ?? 'inp')));
    $extension = preg_replace('/[^a-z0-9]/', '', $extension);
    return is_string($extension) && $extension !== '' ? $extension : 'inp';
}

function agecs_fiscal_inp_unique_path(string $directory, string $baseName, string $extension): string
{
    $path = $directory . DIRECTORY_SEPARATOR . $baseName . '.' . $extension;
    $suffix = 2;
    while (is_file($path)) {
        $path = $directory . DIRECTORY_SEPARATOR . $baseName . '_' . $suffix . '.' . $extension;
        $suffix++;
    }
    return $path;
}

function agecs_fiscal_inp_atomic_write(
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

    $finalPath = agecs_fiscal_inp_unique_path($directory, $baseName, $extension);
    $temporaryPath = $finalPath . '.tmp.' . getmypid() . '.' . agecs_printer_queue_token();

    try {
        if (file_put_contents($temporaryPath, $content, LOCK_EX) === false) {
            throw new RuntimeException('Fișierul fiscal temporar nu a putut fi scris.');
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
    agecs_fiscal_inp_response(false, 'method', 'Metoda alternativă acceptă numai cereri locale POST.');
}

$clientId = (int)($_SESSION['client_id'] ?? 0);
$locationId = (int)($_SESSION['cod_locatie'] ?? 0);
if (!in_array($clientId, [1008, 1021], true) || $locationId <= 0) {
    agecs_fiscal_inp_response(false, 'forbidden', 'Trimiterea fiscală alternativă nu este activă pentru această instalare.');
}

$expectedToken = (string)($_SESSION['offline_direct_fiscal_token'] ?? '');
$receivedToken = (string)($_POST['token'] ?? '');
if ($expectedToken === '' || $receivedToken === '' || !hash_equals($expectedToken, $receivedToken)) {
    agecs_fiscal_inp_response(false, 'token', 'Sesiunea butonului a expirat. Reîncărcați pagina de așteptare.');
}

$installationRoot = dirname(rtrim((string)RESTAURANT_OFFLINE_API_DIR, '/\\'));
$scannerDirectory = $installationRoot . DIRECTORY_SEPARATOR . 'scan_casa_marcat_v3_inp';
$configPath = $scannerDirectory . DIRECTORY_SEPARATOR . 'config.json';
$queuePath = rtrim((string)RESTAURANT_OFFLINE_API_DIR, '/\\')
    . DIRECTORY_SEPARATOR . $clientId
    . DIRECTORY_SEPARATOR . $locationId
    . DIRECTORY_SEPARATOR . 'bon_casa_marcat.json';
$queueHelper = rtrim((string)RESTAURANT_OFFLINE_API_DIR, '/\\')
    . DIRECTORY_SEPARATOR . 'printer_queue_atomic_helper.php';

if (!is_file($queueHelper)) {
    agecs_fiscal_inp_response(false, 'helper', 'Lipsește componenta care protejează coada casei de marcat.');
}
require_once $queueHelper;

try {
    $config = agecs_fiscal_inp_load_config($configPath);
    if ((int)($config['client_id'] ?? 0) !== $clientId) {
        throw new RuntimeException('Clientul din configurarea scannerului nu corespunde sesiunii curente.');
    }
    if ((int)($config['LocationId'] ?? 0) !== $locationId) {
        throw new RuntimeException('Locația din configurarea scannerului nu corespunde sesiunii curente.');
    }

    $destinationDirectory = trim((string)($config['CasaMarcatFolder'] ?? ''));
    $backupDirectory = trim((string)($config['BackupFolder'] ?? ''));
    $extension = agecs_fiscal_inp_safe_extension($config);
    if ($destinationDirectory === '') {
        throw new RuntimeException('CasaMarcatFolder nu este configurat în scanner.');
    }
} catch (Throwable $exception) {
    agecs_fiscal_inp_log('Configurarea scannerului fiscal nu a putut fi folosită.', [
        'config_path' => $configPath,
        'error' => $exception->getMessage(),
    ]);
    agecs_fiscal_inp_response(false, 'settings_error', $exception->getMessage());
}

try {
    $claim = agecs_printer_queue_claim($queuePath);
} catch (Throwable $exception) {
    agecs_fiscal_inp_log('Coada fiscală nu a putut fi revendicată.', ['error' => $exception->getMessage()]);
    agecs_fiscal_inp_response(false, 'claim_error', 'Coada casei de marcat nu a putut fi accesată în siguranță.');
}

if (($claim['status'] ?? '') === 'empty') {
    agecs_fiscal_inp_response(true, 'empty', 'Bonul a fost deja preluat de scannerul casei de marcat.');
}
if (($claim['status'] ?? '') !== 'claimed') {
    agecs_fiscal_inp_response(false, 'claim_error', 'Bonul nu a putut fi rezervat pentru trimiterea alternativă.');
}

$claimedPath = (string)($claim['path'] ?? '');
$raw = $claimedPath !== '' ? @file_get_contents($claimedPath) : false;
$payload = is_string($raw) ? json_decode($raw, true) : null;
if (!is_array($payload) || !isset($payload['data']) || !is_array($payload['data'])) {
    agecs_printer_queue_restore_claim($claimedPath, $queuePath);
    agecs_fiscal_inp_log('Fișierul fiscal revendicat nu este JSON valid.');
    agecs_fiscal_inp_response(false, 'invalid_queue', 'Fișierul fiscal din coadă nu este valid.');
}

$documents = array_values($payload['data']);
if ($documents === []) {
    @unlink($claimedPath);
    agecs_fiscal_inp_response(true, 'empty', 'Coada fiscală nu conține bonuri.');
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
            'content' => agecs_fiscal_inp_normalize_content($document['continut_bon'] ?? ''),
            'source' => $document,
        ];
    }
} catch (Throwable $exception) {
    agecs_printer_queue_restore_claim($claimedPath, $queuePath);
    agecs_fiscal_inp_log('Bonul FiscalWire nu a trecut validarea.', ['error' => $exception->getMessage()]);
    agecs_fiscal_inp_response(false, 'validation_error', $exception->getMessage());
}

$writtenCount = 0;
$writtenFiles = [];
$backupWarnings = [];
try {
    foreach ($prepared as $index => $item) {
        $baseName = 'Bon_' . $item['nrbon'] . '_' . date('Ymd_His');
        if ($index > 0) {
            $baseName .= '_' . ($index + 1);
        }

        $finalPath = agecs_fiscal_inp_atomic_write(
            $destinationDirectory,
            $baseName,
            $extension,
            $item['content']
        );
        $writtenCount++;
        $writtenFiles[] = basename($finalPath);

        if (
            $backupDirectory !== ''
            && strcasecmp(
                rtrim($backupDirectory, '/\\'),
                rtrim($destinationDirectory, '/\\')
            ) !== 0
        ) {
            try {
                agecs_fiscal_inp_atomic_write(
                    $backupDirectory,
                    pathinfo($finalPath, PATHINFO_FILENAME),
                    $extension,
                    $item['content']
                );
            } catch (Throwable $backupException) {
                $backupWarnings[] = $backupException->getMessage();
                agecs_fiscal_inp_log('Bonul a fost trimis, dar backupul nu a putut fi scris.', [
                    'nrbon' => $item['nrbon'],
                    'error' => $backupException->getMessage(),
                ]);
            }
        }

        agecs_fiscal_inp_log('Fișier INP publicat direct, fără BOM.', [
            'nrbon' => $item['nrbon'],
            'file' => $finalPath,
        ]);
    }
} catch (Throwable $exception) {
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
    } catch (Throwable $restoreException) {
        agecs_fiscal_inp_log('Bonul revendicat nu a putut fi restaurat automat.', [
            'claimed_path' => $claimedPath,
            'error' => $restoreException->getMessage(),
        ]);
    }

    agecs_fiscal_inp_log('Trimiterea fiscală directă a eșuat.', [
        'written' => $writtenCount,
        'remaining' => count($remainingDocuments),
        'restored' => $restored,
        'error' => $exception->getMessage(),
    ]);
    agecs_fiscal_inp_response(
        false,
        'write_error',
        $restored
            ? 'Fișierul INP nu a putut fi scris. Bonurile rămase au revenit în coada normală.'
            : 'Scrierea INP s-a oprit. Bonul rezervat a fost păstrat pentru verificare manuală.',
        [
            'written' => $writtenCount,
            'remaining' => count($remainingDocuments),
            'restored' => $restored,
        ]
    );
}

$removed = $claimedPath !== '' && is_file($claimedPath) ? @unlink($claimedPath) : true;
if (!$removed) {
    agecs_fiscal_inp_log('Bonul trimis a rămas ca fișier processing.', ['claimed_path' => $claimedPath]);
}

$message = $writtenCount === 1
    ? 'Fișierul INP a fost pus direct în folderul casei de marcat, fără BOM.'
    : 'Cele ' . $writtenCount . ' fișiere INP au fost puse direct în folderul casei de marcat, fără BOM.';
if ($backupWarnings !== []) {
    $message .= ' Fișierul principal este trimis, dar backupul necesită verificare.';
}

agecs_fiscal_inp_response(true, 'written', $message, [
    'written' => $writtenCount,
    'files' => $writtenFiles,
    'backup_warning' => $backupWarnings !== [],
]);
