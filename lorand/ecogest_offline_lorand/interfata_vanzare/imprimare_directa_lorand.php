<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/offline_api_path.php';
require_once __DIR__ . '/offline_printer_flow_helper.php';

function lorand_direct_print_response(string $status, string $code, string $message, array $extra = []): void
{
    echo json_encode(array_merge([
        'status' => $status,
        'code' => $code,
        'message' => $message,
    ], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function lorand_direct_print_is_local_request(): bool
{
    $address = strtolower(trim((string)($_SERVER['REMOTE_ADDR'] ?? '')));
    return in_array($address, ['127.0.0.1', '::1', '::ffff:127.0.0.1'], true);
}

function lorand_direct_print_configured_printer(): string
{
    $settingsPath = dirname(lorand_offline_api_root())
        . DIRECTORY_SEPARATOR . 'printer_bold'
        . DIRECTORY_SEPARATOR . 'settings.json';
    if (!is_file($settingsPath)) {
        return '';
    }

    $settingsJson = (string)file_get_contents($settingsPath);
    if (strncmp($settingsJson, "\xEF\xBB\xBF", 3) === 0) {
        $settingsJson = substr($settingsJson, 3);
    }
    $decoded = json_decode($settingsJson, true);
    return is_array($decoded) ? trim((string)($decoded['BarPrinter'] ?? '')) : '';
}

function lorand_direct_print_claim(int $clientId, int $locationId): ?array
{
    $base = lorand_offline_api_root()
        . DIRECTORY_SEPARATOR . $clientId
        . DIRECTORY_SEPARATOR . $locationId;
    $candidates = [$base . DIRECTORY_SEPARATOR . 'de_listat_la_imprimanta.json'];
    $queueDirectory = $base . DIRECTORY_SEPARATOR . 'print_queue';

    if (is_dir($queueDirectory)) {
        $queuedFiles = glob($queueDirectory . DIRECTORY_SEPARATOR . '*.json') ?: [];
        sort($queuedFiles, SORT_STRING);
        $candidates = array_merge($candidates, $queuedFiles);
    }

    foreach ($candidates as $originalPath) {
        if (!is_file($originalPath)) {
            continue;
        }
        $claimedPath = $originalPath . '.processing.direct.' . getmypid() . '.' . str_replace('.', '', uniqid('', true));
        if (@rename($originalPath, $claimedPath)) {
            return ['original' => $originalPath, 'claimed' => $claimedPath];
        }
    }

    return null;
}

function lorand_direct_print_restore(string $claimedPath, string $originalPath): string
{
    if (!is_file($claimedPath)) {
        return '';
    }
    if (!is_file($originalPath) && @rename($claimedPath, $originalPath)) {
        return $originalPath;
    }

    $baseDirectory = dirname($originalPath);
    $restoreDirectory = basename($originalPath) === 'de_listat_la_imprimanta.json'
        ? $baseDirectory . DIRECTORY_SEPARATOR . 'print_queue'
        : $baseDirectory;
    if (!is_dir($restoreDirectory) && !@mkdir($restoreDirectory, 0777, true) && !is_dir($restoreDirectory)) {
        return '';
    }

    $restoredPath = $restoreDirectory . DIRECTORY_SEPARATOR
        . 'restaurat_direct_' . date('Ymd_His') . '_' . str_replace('.', '', uniqid('', true)) . '.json';
    return @rename($claimedPath, $restoredPath) ? $restoredPath : '';
}

function lorand_direct_print_plain_text($content): string
{
    $text = (string)$content;
    $text = (string)preg_replace('/<br\s*\/?\s*>/i', "\n", $text);
    $text = (string)preg_replace('/<\/(?:p|div|li|tr|h[1-6])\s*>/i', "\n", $text);
    $text = strip_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = strtr($text, [
        'ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't',
        'Ă' => 'A', 'Â' => 'A', 'Î' => 'I', 'Ș' => 'S', 'Ş' => 'S', 'Ț' => 'T', 'Ţ' => 'T',
        '€' => 'EUR', '£' => 'GBP',
    ]);
    $text = str_replace(["\xC2\xA0", "\xE2\x80\x8B"], [' ', ''], $text);
    if (function_exists('iconv')) {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if (is_string($ascii)) {
            $text = $ascii;
        }
    }
    $text = (string)preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '', $text);
    $text = (string)preg_replace("/\r\n?|\n/", "\n", $text);
    $text = (string)preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
    $text = (string)preg_replace("/\n{4,}/", "\n\n\n", $text);
    $text = rtrim($text, " \t\n");

    if ($text === '') {
        return '';
    }

    $paperFeed = str_repeat("\r\n", 6);
    $fullCutCommand = "\x1D\x56\x00";
    return str_replace("\n", "\r\n", $text) . $paperFeed . $fullCutCommand;
}

function lorand_direct_print_powershell_path(): string
{
    $windows = rtrim((string)(getenv('SystemRoot') ?: 'C:\\Windows'), '/\\');
    foreach ([
        $windows . '\\System32\\WindowsPowerShell\\v1.0\\powershell.exe',
        $windows . '\\Sysnative\\WindowsPowerShell\\v1.0\\powershell.exe',
    ] as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }
    return '';
}

function lorand_direct_print_utf16le(string $value): string
{
    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($value, 'UTF-16LE', 'UTF-8');
    }
    if (function_exists('iconv')) {
        $converted = iconv('UTF-8', 'UTF-16LE', $value);
        if (is_string($converted)) {
            return $converted;
        }
    }
    throw new RuntimeException('Conversia necesară pentru PowerShell nu este disponibilă.');
}

function lorand_direct_print_windows_raw(string $printerName, string $text, string $jobName): array
{
    if (!function_exists('proc_open')) {
        throw new RuntimeException('Pornirea procesului local de imprimare este dezactivată în PHP.');
    }
    $powershell = lorand_direct_print_powershell_path();
    if ($powershell === '') {
        throw new RuntimeException('Windows PowerShell nu a fost găsit.');
    }

    $temporaryPath = tempnam(sys_get_temp_dir(), 'ecogest_print_');
    if ($temporaryPath === false) {
        throw new RuntimeException('Fișierul temporar pentru imprimare nu a putut fi creat.');
    }
    if (file_put_contents($temporaryPath, $text, LOCK_EX) !== strlen($text)) {
        @unlink($temporaryPath);
        throw new RuntimeException('Textul pentru imprimare nu a putut fi pregătit integral.');
    }

    $script = <<<'POWERSHELL'
$ErrorActionPreference = 'Stop'
$printerName = [Text.Encoding]::UTF8.GetString([Convert]::FromBase64String('__PRINTER__'))
$filePath = [Text.Encoding]::UTF8.GetString([Convert]::FromBase64String('__FILE__'))
$jobName = [Text.Encoding]::UTF8.GetString([Convert]::FromBase64String('__JOB__'))
$source = @'
using System;
using System.Runtime.InteropServices;

public static class EcogestRawPrinter
{
    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
    public class DOC_INFO_1
    {
        [MarshalAs(UnmanagedType.LPWStr)] public string pDocName;
        [MarshalAs(UnmanagedType.LPWStr)] public string pOutputFile;
        [MarshalAs(UnmanagedType.LPWStr)] public string pDataType;
    }

    [DllImport("winspool.drv", EntryPoint = "OpenPrinterW", SetLastError = true, CharSet = CharSet.Unicode)]
    public static extern bool OpenPrinter(string printerName, out IntPtr printerHandle, IntPtr defaults);

    [DllImport("winspool.drv", SetLastError = true)]
    public static extern bool ClosePrinter(IntPtr printerHandle);

    [DllImport("winspool.drv", EntryPoint = "StartDocPrinterW", SetLastError = true, CharSet = CharSet.Unicode)]
    public static extern int StartDocPrinter(IntPtr printerHandle, int level, [In] DOC_INFO_1 documentInfo);

    [DllImport("winspool.drv", SetLastError = true)]
    public static extern bool EndDocPrinter(IntPtr printerHandle);

    [DllImport("winspool.drv", SetLastError = true)]
    public static extern bool StartPagePrinter(IntPtr printerHandle);

    [DllImport("winspool.drv", SetLastError = true)]
    public static extern bool EndPagePrinter(IntPtr printerHandle);

    [DllImport("winspool.drv", SetLastError = true)]
    public static extern bool WritePrinter(IntPtr printerHandle, byte[] bytes, int count, out int written);
}
'@

try {
    Add-Type -TypeDefinition $source -ErrorAction Stop
    $bytes = [IO.File]::ReadAllBytes($filePath)
    $handle = [IntPtr]::Zero
    $documentStarted = $false
    $pageStarted = $false
    try {
        if (-not [EcogestRawPrinter]::OpenPrinter($printerName, [ref]$handle, [IntPtr]::Zero)) {
            throw [ComponentModel.Win32Exception]::new([Runtime.InteropServices.Marshal]::GetLastWin32Error())
        }
        $info = [EcogestRawPrinter+DOC_INFO_1]::new()
        $info.pDocName = $jobName
        $info.pOutputFile = $null
        $info.pDataType = 'RAW'
        $jobId = [EcogestRawPrinter]::StartDocPrinter($handle, 1, $info)
        if ($jobId -le 0) {
            throw [ComponentModel.Win32Exception]::new([Runtime.InteropServices.Marshal]::GetLastWin32Error())
        }
        $documentStarted = $true
        if (-not [EcogestRawPrinter]::StartPagePrinter($handle)) {
            throw [ComponentModel.Win32Exception]::new([Runtime.InteropServices.Marshal]::GetLastWin32Error())
        }
        $pageStarted = $true
        $written = 0
        if (-not [EcogestRawPrinter]::WritePrinter($handle, $bytes, $bytes.Length, [ref]$written) -or $written -ne $bytes.Length) {
            throw [ComponentModel.Win32Exception]::new([Runtime.InteropServices.Marshal]::GetLastWin32Error())
        }
        if (-not [EcogestRawPrinter]::EndPagePrinter($handle)) {
            throw [ComponentModel.Win32Exception]::new([Runtime.InteropServices.Marshal]::GetLastWin32Error())
        }
        $pageStarted = $false
        if (-not [EcogestRawPrinter]::EndDocPrinter($handle)) {
            throw [ComponentModel.Win32Exception]::new([Runtime.InteropServices.Marshal]::GetLastWin32Error())
        }
        $documentStarted = $false
        [Console]::Out.WriteLine('JOB_ID=' + $jobId)
    }
    finally {
        if ($pageStarted) { [void][EcogestRawPrinter]::EndPagePrinter($handle) }
        if ($documentStarted) { [void][EcogestRawPrinter]::EndDocPrinter($handle) }
        if ($handle -ne [IntPtr]::Zero) { [void][EcogestRawPrinter]::ClosePrinter($handle) }
    }
    exit 0
}
catch {
    [Console]::Error.WriteLine($_.Exception.Message)
    exit 1
}
POWERSHELL;
    $script = str_replace(
        ['__PRINTER__', '__FILE__', '__JOB__'],
        [base64_encode($printerName), base64_encode($temporaryPath), base64_encode($jobName)],
        $script
    );
    $encodedCommand = base64_encode(lorand_direct_print_utf16le($script));
    $command = '"' . $powershell . '" -NoLogo -NoProfile -NonInteractive -ExecutionPolicy Bypass -EncodedCommand ' . $encodedCommand;
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptors, $pipes, null, null, ['bypass_shell' => true]);
    if (!is_resource($process)) {
        @unlink($temporaryPath);
        throw new RuntimeException('Procesul Windows de imprimare nu a putut fi pornit.');
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $stdout = '';
    $stderr = '';
    $exitCode = -1;
    $startedAt = microtime(true);
    try {
        do {
            $stdout .= (string)stream_get_contents($pipes[1]);
            $stderr .= (string)stream_get_contents($pipes[2]);
            $state = proc_get_status($process);
            if (!$state['running']) {
                $exitCode = (int)$state['exitcode'];
                break;
            }
            if (microtime(true) - $startedAt > 30) {
                proc_terminate($process);
                throw new RuntimeException('Windows nu a confirmat lucrarea în 30 de secunde.');
            }
            usleep(50000);
        } while (true);
        $stdout .= (string)stream_get_contents($pipes[1]);
        $stderr .= (string)stream_get_contents($pipes[2]);
    } finally {
        fclose($pipes[1]);
        fclose($pipes[2]);
        $closedCode = proc_close($process);
        if ($exitCode < 0 && is_int($closedCode)) {
            $exitCode = $closedCode;
        }
        @unlink($temporaryPath);
    }

    if ($exitCode !== 0) {
        $detail = trim($stderr !== '' ? $stderr : $stdout);
        throw new RuntimeException($detail !== '' ? $detail : 'Windows nu a acceptat lucrarea de imprimare.');
    }

    preg_match('/JOB_ID=(\d+)/', $stdout, $matches);
    return ['job_id' => isset($matches[1]) ? (int)$matches[1] : 0];
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    lorand_direct_print_response('error', 'method_not_allowed', 'Metoda cererii nu este permisă.');
}
if (!lorand_direct_print_is_local_request()) {
    lorand_direct_print_response('error', 'local_only', 'Listarea directă este disponibilă numai din aplicația locală.');
}
if ((int)offline_config_value('client_id', 0) !== 1019
    || (int)($_SESSION['client_id'] ?? 0) !== 1019
    || (int)($_SESSION['admin_id'] ?? 0) <= 0) {
    lorand_direct_print_response('error', 'access_denied', 'Sesiunea operatorului Lorand nu este activă.');
}

$expectedToken = (string)($_SESSION['lorand_direct_print_csrf'] ?? '');
$submittedToken = (string)($_POST['csrf_token'] ?? '');
if ($expectedToken === '' || $submittedToken === '' || !hash_equals($expectedToken, $submittedToken)) {
    lorand_direct_print_response('error', 'invalid_request', 'Cererea de listare directă nu mai este validă. Reîncărcați pagina.');
}

$printerName = lorand_direct_print_configured_printer();
if ($printerName === '') {
    lorand_direct_print_response('error', 'printer_not_configured', 'Imprimanta BAR nu este configurată în aplicația de scanare.');
}
if (!function_exists('proc_open')) {
    lorand_direct_print_response('error', 'process_disabled', 'Metoda alternativă nu poate porni procesul Windows de imprimare.');
}

$locationId = (int)($_SESSION['cod_locatie'] ?? 0);
if ($locationId <= 0) {
    lorand_direct_print_response('error', 'invalid_location', 'Locația curentă nu este validă.');
}
session_write_close();

$claim = lorand_direct_print_claim(1019, $locationId);
if ($claim === null) {
    lorand_direct_print_response(
        'success',
        'already_claimed',
        'Lotul a fost deja preluat de scanner sau nu mai există documente în așteptare.'
    );
}

$claimedPath = (string)$claim['claimed'];
$originalPath = (string)$claim['original'];
$rawPayload = @file_get_contents($claimedPath);
if ($rawPayload === false) {
    $restored = lorand_direct_print_restore($claimedPath, $originalPath);
    $message = $restored !== ''
        ? 'Lotul nu a putut fi citit și a fost păstrat în coada normală.'
        : 'Lotul nu a putut fi citit sau restaurat automat. Este necesară verificarea cozii locale.';
    lorand_direct_print_response('error', 'read_failed', $message, [
        'restored' => $restored !== '',
    ]);
}

$payload = json_decode($rawPayload, true);
$documents = is_array($payload) && isset($payload['data']) && is_array($payload['data'])
    ? array_values($payload['data'])
    : [];
if (!$documents) {
    $restored = lorand_direct_print_restore($claimedPath, $originalPath);
    $message = $restored !== ''
        ? 'Lotul nu conține documente valide și a fost restituit în coada normală.'
        : 'Lotul nu conține documente valide și nu a putut fi restaurat automat.';
    lorand_direct_print_response('error', 'invalid_batch', $message, [
        'restored' => $restored !== '',
    ]);
}

$printed = 0;
$jobIds = [];
foreach ($documents as $index => $document) {
    $plainText = lorand_direct_print_plain_text(is_array($document) ? ($document['continut'] ?? '') : '');
    try {
        if ($plainText === '') {
            throw new RuntimeException('Documentul nu conține text care poate fi imprimat.');
        }
        if (strlen($plainText) > 2 * 1024 * 1024) {
            throw new RuntimeException('Documentul depășește dimensiunea permisă pentru listarea directă.');
        }

        $noteNumber = is_array($document) ? (int)($document['nrbon'] ?? 0) : 0;
        $result = lorand_direct_print_windows_raw(
            $printerName,
            $plainText,
            'ECOGEST Lorand' . ($noteNumber > 0 ? ' bon ' . $noteNumber : '')
        );
        $printed++;
        $jobIds[] = (int)($result['job_id'] ?? 0);
    } catch (Throwable $error) {
        if ($printed > 0) {
            $payload['data'] = array_slice($documents, $index);
            $remainingJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            if (!is_string($remainingJson) || file_put_contents($claimedPath, $remainingJson, LOCK_EX) !== strlen($remainingJson)) {
                lorand_direct_print_response(
                    'error',
                    'restore_failed',
                    'O parte a lotului a fost acceptată de Windows, dar documentele rămase nu au putut fi restaurate automat.',
                    ['printed' => $printed]
                );
            }
        }
        $restored = lorand_direct_print_restore($claimedPath, $originalPath);
        $message = $restored !== ''
            ? 'Windows nu a acceptat toate documentele. Cele netrimise au fost restaurate în coada normală. '
            : 'Windows nu a acceptat toate documentele, iar cele netrimise nu au putut fi restaurate automat. ';
        lorand_direct_print_response(
            'error',
            'print_failed',
            $message . $error->getMessage(),
            [
                'printed' => $printed,
                'remaining' => count($documents) - $printed,
                'restored' => $restored !== '',
                'printer' => $printerName,
            ]
        );
    }
}

if (!@unlink($claimedPath)) {
    lorand_direct_print_response(
        'error',
        'cleanup_failed',
        'Windows a acceptat documentele, dar lotul revendicat nu a putut fi eliminat. Nu repetați listarea.',
        ['printed' => $printed, 'printer' => $printerName, 'job_ids' => $jobIds]
    );
}

lorand_direct_print_response(
    'success',
    'printed',
    $printed === 1
        ? 'Documentul a fost acceptat de Windows pentru imprimanta ' . $printerName . '.'
        : $printed . ' documente au fost acceptate de Windows pentru imprimanta ' . $printerName . '.',
    ['printed' => $printed, 'printer' => $printerName, 'job_ids' => $jobIds]
);
