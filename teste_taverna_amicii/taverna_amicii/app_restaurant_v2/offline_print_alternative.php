<?php
declare(strict_types=1);

ini_set('display_errors', '0');
date_default_timezone_set('Europe/Bucharest');

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/offline_printer_flow_helper.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function agecs_direct_print_response(bool $success, string $code, string $message, array $extra = []): void
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

function agecs_direct_print_log(string $message, array $context = []): void
{
    if (!defined('RESTAURANT_OFFLINE_API_DIR')) {
        return;
    }

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
        $directory . DIRECTORY_SEPARATOR . 'direct_print.log',
        $line . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

function agecs_direct_print_load_settings(string $settingsPath): array
{
    if (!is_file($settingsPath)) {
        throw new RuntimeException('Configurarea aplicației scanner nu a fost găsită.');
    }

    $raw = file_get_contents($settingsPath);
    if (is_string($raw) && strncmp($raw, "\xEF\xBB\xBF", 3) === 0) {
        // Configuratorul AGECS poate salva settings.json cu UTF-8 BOM.
        $raw = substr($raw, 3);
    }
    $settings = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($settings)) {
        throw new RuntimeException('Configurarea imprimantelor nu este un JSON valid.');
    }

    return $settings;
}

function agecs_direct_print_plain_text($content): string
{
    $text = (string)$content;
    $text = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $text);
    $text = preg_replace('/<\s*\/\s*(?:p|div|li|tr|h[1-6])\s*>/i', "\n", (string)$text);
    $text = strip_tags((string)$text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = str_replace(["\xEF\xBB\xBF", "\xC2\xA0"], ['', ' '], $text);
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace('/[^\P{C}\n\t]/u', '', $text);
    if (!is_string($text)) {
        throw new RuntimeException('Textul documentului nu a putut fi normalizat.');
    }

    // Listarea RAW alternativă folosește numai ASCII, pentru imprimantele termice
    // care nu interpretează sigur diacriticele UTF-8.
    $text = strtr($text, [
        'ă' => 'a', 'Ă' => 'A',
        'â' => 'a', 'Â' => 'A',
        'î' => 'i', 'Î' => 'I',
        'ș' => 's', 'Ș' => 'S',
        'ş' => 's', 'Ş' => 'S',
        'ț' => 't', 'Ț' => 'T',
        'ţ' => 't', 'Ţ' => 'T',
    ]);
    if (function_exists('iconv')) {
        $asciiText = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if (is_string($asciiText)) {
            $text = $asciiText;
        }
    }
    $text = preg_replace('/[^\x09\x0A\x20-\x7E]/', '', $text);
    if (!is_string($text)) {
        throw new RuntimeException('Textul documentului nu a putut fi convertit fără diacritice.');
    }

    $lines = array_map(
        static function (string $line): string {
            return rtrim(str_replace("\t", '    ', $line));
        },
        explode("\n", $text)
    );
    $text = trim(implode("\n", $lines));
    if ($text === '') {
        throw new RuntimeException('Documentul nu conține text care poate fi tipărit.');
    }

    // Metoda alternativă trimite text ASCII simplu, fără diacritice, taguri sau bold.
    // Avansul scoate complet hârtia, iar GS V 0 taie pe imprimantele ESC/POS compatibile.
    $paperFeed = str_repeat("\r\n", 6);
    $fullCutCommand = "\x1D\x56\x00";
    return str_replace("\n", "\r\n", $text) . $paperFeed . $fullCutCommand;
}

function agecs_direct_print_department(array $document): string
{
    $department = (string)($document['departament_listare'] ?? $document['departament'] ?? 'BAR');
    return strtoupper(trim($department));
}

function agecs_direct_print_printer_name(array $settings, array $document): string
{
    $department = agecs_direct_print_department($document);
    if (strpos($department, 'BUC') !== false) {
        $key = 'BucatariePrinter';
    } elseif (strpos($department, 'IMPRIMANTA3') !== false || strpos($department, 'SALAT') !== false) {
        $key = 'Imprimanta3Printer';
    } else {
        $key = 'BarPrinter';
    }

    $printer = trim((string)($settings[$key] ?? ''));
    if ($printer === '') {
        foreach (['BarPrinter', 'BucatariePrinter', 'Imprimanta3Printer'] as $fallbackKey) {
            $printer = trim((string)($settings[$fallbackKey] ?? ''));
            if ($printer !== '') {
                break;
            }
        }
    }
    if ($printer === '') {
        throw new RuntimeException('Nu este selectată nicio imprimantă în configuratorul scannerului.');
    }

    return $printer;
}

function agecs_direct_print_shell_available(): bool
{
    if (!function_exists('shell_exec')) {
        return false;
    }

    $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
    return !in_array('shell_exec', $disabled, true);
}

function agecs_direct_print_send(string $scriptPath, string $printerName, string $plainText): void
{
    if (!agecs_direct_print_shell_available()) {
        throw new RuntimeException('PHP nu permite pornirea metodei alternative de listare.');
    }
    if (!is_file($scriptPath)) {
        throw new RuntimeException('Utilitarul pentru listare directă lipsește din folderul printer_bold.');
    }

    $temporaryFile = tempnam(sys_get_temp_dir(), 'agecs_raw_');
    if (!is_string($temporaryFile) || $temporaryFile === '') {
        throw new RuntimeException('Nu a putut fi creat fișierul temporar pentru listare.');
    }

    try {
        if (file_put_contents($temporaryFile, $plainText, LOCK_EX) === false) {
            throw new RuntimeException('Textul temporar pentru listare nu a putut fi scris.');
        }

        $command = 'powershell.exe -NoProfile -NonInteractive -ExecutionPolicy Bypass -File '
            . escapeshellarg($scriptPath)
            . ' -PrinterName ' . escapeshellarg($printerName)
            . ' -InputFile ' . escapeshellarg($temporaryFile)
            . ' 2>&1';
        $output = @shell_exec($command);
        $output = is_string($output) ? trim($output) : '';
        if (strpos($output, 'AGECS_RAW_PRINT_OK') === false) {
            throw new RuntimeException('Windows nu a acceptat lucrarea de imprimare.');
        }
    } finally {
        if (is_file($temporaryFile)) {
            @unlink($temporaryFile);
        }
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    agecs_direct_print_response(false, 'method', 'Metoda alternativă acceptă numai cereri locale POST.');
}

$clientId = (int)($_SESSION['client_id'] ?? 0);
$locationId = (int)($_SESSION['cod_locatie'] ?? 0);
if (!in_array($clientId, [1008, 1021], true) || $locationId <= 0) {
    agecs_direct_print_response(false, 'forbidden', 'Listarea alternativă nu este activă pentru această instalare.');
}

$expectedToken = (string)($_SESSION['offline_direct_print_token'] ?? '');
$receivedToken = (string)($_POST['token'] ?? '');
if ($expectedToken === '' || $receivedToken === '' || !hash_equals($expectedToken, $receivedToken)) {
    agecs_direct_print_response(false, 'token', 'Sesiunea butonului a expirat. Reîncărcați pagina de așteptare.');
}

$installationRoot = dirname(rtrim((string)RESTAURANT_OFFLINE_API_DIR, '/\\'));
$scannerDirectory = $installationRoot . DIRECTORY_SEPARATOR . 'printer_bold';
$settingsPath = $scannerDirectory . DIRECTORY_SEPARATOR . 'settings.json';
$scriptPath = $scannerDirectory . DIRECTORY_SEPARATOR . 'agecs_raw_print_text.ps1';
$queuePath = agecs_offline_printer_queue_path($clientId, $locationId);
$queueHelper = rtrim((string)RESTAURANT_OFFLINE_API_DIR, '/\\')
    . DIRECTORY_SEPARATOR . 'printer_queue_atomic_helper.php';

if (!is_file($queueHelper)) {
    agecs_direct_print_response(false, 'helper', 'Lipsește componenta care protejează coada imprimantei.');
}
require_once $queueHelper;

try {
    $settings = agecs_direct_print_load_settings($settingsPath);
} catch (Throwable $exception) {
    agecs_direct_print_log('Configurarea imprimantelor nu a putut fi citită.', [
        'settings_path' => $settingsPath,
        'error' => $exception->getMessage(),
    ]);
    agecs_direct_print_response(false, 'settings_error', $exception->getMessage());
}

try {
    $claim = agecs_printer_queue_claim($queuePath);
} catch (Throwable $exception) {
    agecs_direct_print_log('Coada nu a putut fi revendicată.', ['error' => $exception->getMessage()]);
    agecs_direct_print_response(false, 'claim_error', 'Coada imprimantei nu a putut fi accesată în siguranță.');
}

if (($claim['status'] ?? '') === 'empty') {
    agecs_direct_print_response(true, 'empty', 'Lotul a fost deja preluat de aplicația scanner.');
}
if (($claim['status'] ?? '') !== 'claimed') {
    agecs_direct_print_response(false, 'claim_error', 'Lotul nu a putut fi rezervat pentru listarea directă.');
}

$claimedPath = (string)($claim['path'] ?? '');
$raw = $claimedPath !== '' ? @file_get_contents($claimedPath) : false;
$payload = is_string($raw) ? json_decode($raw, true) : null;
if (!is_array($payload) || !isset($payload['data']) || !is_array($payload['data'])) {
    agecs_printer_queue_restore_claim($claimedPath, $queuePath);
    agecs_direct_print_log('Lotul revendicat nu este valid.');
    agecs_direct_print_response(false, 'invalid_queue', 'Fișierul din coadă nu conține un lot valid.');
}

$documents = array_values($payload['data']);
if ($documents === []) {
    @unlink($claimedPath);
    agecs_direct_print_response(true, 'empty', 'Coada imprimantei nu conține documente.');
}

$printedCount = 0;
try {
    foreach ($documents as $index => $document) {
        if (!is_array($document)) {
            throw new RuntimeException('Un document din lot nu are structură validă.');
        }

        $plainText = agecs_direct_print_plain_text($document['continut'] ?? $document['mesaj'] ?? '');
        $printerName = agecs_direct_print_printer_name($settings, $document);
        agecs_direct_print_send($scriptPath, $printerName, $plainText);
        $printedCount++;

        agecs_direct_print_log('Document acceptat de Windows Print Spooler.', [
            'index' => $index,
            'nrbon' => (int)($document['nrbon'] ?? 0),
            'department' => agecs_direct_print_department($document),
            'printer' => $printerName,
        ]);
    }
} catch (Throwable $exception) {
    $remainingDocuments = array_slice($documents, $printedCount);
    $restored = false;
    try {
        if ($remainingDocuments !== []) {
            agecs_printer_queue_append_documents(
                $queuePath,
                $remainingDocuments,
                'Documente păstrate după eșecul listării directe.'
            );
        }
        if ($claimedPath !== '' && is_file($claimedPath)) {
            @unlink($claimedPath);
        }
        $restored = true;
    } catch (Throwable $restoreException) {
        agecs_direct_print_log('Lotul revendicat nu a putut fi restaurat automat.', [
            'claimed_path' => $claimedPath,
            'error' => $restoreException->getMessage(),
        ]);
    }

    agecs_direct_print_log('Listarea directă a eșuat.', [
        'printed' => $printedCount,
        'remaining' => count($remainingDocuments),
        'restored' => $restored,
        'error' => $exception->getMessage(),
    ]);
    agecs_direct_print_response(
        false,
        'print_error',
        $restored
            ? 'Imprimanta nu a acceptat toate documentele. Documentele rămase au revenit în coada normală.'
            : 'Listarea s-a oprit. Lotul rezervat a fost păstrat pentru verificare manuală.',
        [
            'printed' => $printedCount,
            'remaining' => count($remainingDocuments),
            'restored' => $restored,
        ]
    );
}

$removed = $claimedPath !== '' && is_file($claimedPath) ? @unlink($claimedPath) : true;
if (!$removed) {
    agecs_direct_print_log('Lotul tipărit a rămas ca fișier processing.', ['claimed_path' => $claimedPath]);
}

agecs_direct_print_response(
    true,
    'printed',
    $printedCount === 1
        ? 'Documentul a fost acceptat de imprimanta Windows.'
        : 'Toate cele ' . $printedCount . ' documente au fost acceptate de imprimantele Windows.',
    ['printed' => $printedCount]
);
