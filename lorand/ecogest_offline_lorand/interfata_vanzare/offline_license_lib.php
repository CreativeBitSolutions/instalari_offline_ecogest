<?php

const OFFLINE_LICENSE_PUBLIC_KEY = <<<'PEM'
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAsB5FdaWjWgbvo0QhgEf9
E8SbNJVaxIXQEIgS9wHAlo07EFS8tyTx4O4Az0/2k3UQps9Vc/nKs1sF1WXVSghB
NEYl2afmSaEPJUQ9b/8yQFKa1CboFp2iY6NUVZx8v9Z7VZiC6OsGWMlBb1GlzHPc
+qXhQ2ZZEGQdhVuYnHsRyRufgRE3YoI7UCNsoOjRk4ooRNIjDJ1BEqAmhCp3XLD5
93FYWH1XVj3jFfcgeC6GVkAGSUxD3m9NH4X5MYrueXo7OKcgmW3MLqsQRJFmhggx
DZOSK2FF/mmNOoLr2EW3cP/LiAtLPd4cNK8BpZqOCn5Hjly9XjoSDy4f7AtJwjVU
5QIDAQAB
-----END PUBLIC KEY-----
PEM;

function offline_license_config()
{
    static $cachedConfig = null;
    if (is_array($cachedConfig)) {
        return $cachedConfig;
    }

    if (function_exists('offline_config_all')) {
        $config = offline_config_all();
    } else {
        global $restaurantConfig;
        $config = isset($restaurantConfig) && is_array($restaurantConfig) ? $restaurantConfig : array();
        $localConfigPath = __DIR__ . '/offline_config.local.php';
        if (!$config && is_file($localConfigPath)) {
            $loadedConfig = require $localConfigPath;
            $config = is_array($loadedConfig) ? $loadedConfig : array();
        }
    }
    $licenseConfig = isset($config['offline_license']) && is_array($config['offline_license'])
        ? $config['offline_license']
        : array();
    $salesSync = isset($config['offline_sales_sync']) && is_array($config['offline_sales_sync'])
        ? $config['offline_sales_sync']
        : array();
    $productsSync = isset($config['online_products_sync']) && is_array($config['online_products_sync'])
        ? $config['online_products_sync']
        : array();
    $apiKey = isset($licenseConfig['api_key']) ? $licenseConfig['api_key'] : (
        isset($config['sync_api_key']) ? $config['sync_api_key'] : (
            isset($salesSync['api_key']) ? $salesSync['api_key'] : (isset($productsSync['api_key']) ? $productsSync['api_key'] : '')
        )
    );
    $apiRoot = isset($config['api_root_absolute']) ? $config['api_root_absolute'] : (
        isset($config['offline_api_path']) ? $config['offline_api_path'] : ''
    );
    $caBundlePath = trim((string)(isset($licenseConfig['ca_bundle_path'])
        ? $licenseConfig['ca_bundle_path']
        : (isset($config['ca_bundle_path']) ? $config['ca_bundle_path'] : '')));
    if ($caBundlePath === '' && $apiRoot !== '') {
        $caBundlePath = rtrim((string)$apiRoot, "\\/") . DIRECTORY_SEPARATOR . 'certificates' . DIRECTORY_SEPARATOR . 'cacert.pem';
    }
    $cachedConfig = array(
        'enabled' => true,
        'url' => trim((string)(isset($licenseConfig['api_url']) ? $licenseConfig['api_url'] : (isset($config['license_check_url']) ? $config['license_check_url'] : ''))),
        'valid_days' => max(1, (int)(isset($licenseConfig['valid_days']) ? $licenseConfig['valid_days'] : (isset($config['license_valid_days']) ? $config['license_valid_days'] : 30))),
        'client_id' => (int)(isset($config['sync_client_id']) ? $config['sync_client_id'] : (isset($config['client_id']) ? $config['client_id'] : 0)),
        'api_key' => trim((string)$apiKey),
        'installation_uuid' => trim((string)(isset($config['installation_uuid']) ? $config['installation_uuid'] : '')),
        'app_name' => trim((string)(isset($config['app_name']) ? $config['app_name'] : 'Aplicatie vanzare offline')),
        'api_root' => rtrim((string)$apiRoot, "\\/"),
        'ca_bundle_path' => $caBundlePath,
        'db_path' => trim((string)(isset($config['db_runtime_file']) ? $config['db_runtime_file'] : (isset($config['sqlite_path']) ? $config['sqlite_path'] : ''))),
        'renew_before_days' => max(1, (int)(isset($licenseConfig['renew_before_days']) ? $licenseConfig['renew_before_days'] : (isset($config['license_renew_before_days']) ? $config['license_renew_before_days'] : 7))),
        'retry_seconds' => max(900, (int)(isset($licenseConfig['retry_seconds']) ? $licenseConfig['retry_seconds'] : (isset($config['license_retry_seconds']) ? $config['license_retry_seconds'] : 21600))),
        'clock_tolerance_seconds' => max(60, (int)(isset($licenseConfig['clock_tolerance_seconds']) ? $licenseConfig['clock_tolerance_seconds'] : (isset($config['license_clock_tolerance_seconds']) ? $config['license_clock_tolerance_seconds'] : 300))),
    );
    return $cachedConfig;
}

function offline_license_storage_dir()
{
    $config = offline_license_config();
    $base = $config['api_root'] !== '' ? $config['api_root'] : __DIR__;
    return $base . DIRECTORY_SEPARATOR . 'licenta';
}

function offline_license_storage_path($file)
{
    return offline_license_storage_dir() . DIRECTORY_SEPARATOR . basename((string)$file);
}

function offline_license_ensure_storage()
{
    $dir = offline_license_storage_dir();
    return is_dir($dir) || @mkdir($dir, 0777, true);
}

function offline_license_json_flags()
{
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    return $flags;
}

function offline_license_read_json($path)
{
    if (!is_file($path)) {
        return array();
    }
    $raw = @file_get_contents($path);
    if ($raw === false) {
        return array();
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : array();
}

function offline_license_write_json($path, array $data)
{
    if (!offline_license_ensure_storage()) {
        return false;
    }
    $json = json_encode($data, offline_license_json_flags() | JSON_PRETTY_PRINT);
    if (!is_string($json)) {
        return false;
    }
    $temporary = $path . '.tmp';
    if (@file_put_contents($temporary, $json, LOCK_EX) === false) {
        return false;
    }
    if (@rename($temporary, $path)) {
        return true;
    }
    $written = @file_put_contents($path, $json, LOCK_EX) !== false;
    @unlink($temporary);
    return $written;
}

function offline_license_runtime_defaults()
{
    return array(
        'last_seen_epoch' => 0,
        'last_server_epoch' => 0,
        'last_attempt_epoch' => 0,
        'last_success_epoch' => 0,
        'last_error' => '',
    );
}

function offline_license_runtime_read()
{
    $runtime = array_merge(
        offline_license_runtime_defaults(),
        offline_license_read_json(offline_license_storage_path('license_runtime.json'))
    );
    $config = offline_license_config();
    if ($config['db_path'] !== '' && is_file($config['db_path']) && class_exists('PDO')) {
        try {
            $pdo = new PDO('sqlite:' . $config['db_path']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $row = $pdo->query('SELECT * FROM offline_license_runtime WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                foreach (array('last_seen_epoch', 'last_server_epoch', 'last_attempt_epoch', 'last_success_epoch') as $field) {
                    $runtime[$field] = max((int)$runtime[$field], (int)(isset($row[$field]) ? $row[$field] : 0));
                }
                if ($runtime['last_error'] === '' && !empty($row['last_error'])) {
                    $runtime['last_error'] = (string)$row['last_error'];
                }
            }
        } catch (Throwable $e) {
        }
    }
    return $runtime;
}

function offline_license_runtime_write(array $changes)
{
    $current = offline_license_runtime_read();
    $resetClock = !empty($changes['_reset_clock']);
    unset($changes['_reset_clock']);
    $runtime = array_merge($current, $changes);
    foreach (array('last_seen_epoch', 'last_server_epoch', 'last_attempt_epoch', 'last_success_epoch') as $field) {
        $runtime[$field] = max(0, (int)(isset($runtime[$field]) ? $runtime[$field] : 0));
    }
    if (!$resetClock) {
        $runtime['last_seen_epoch'] = max((int)$current['last_seen_epoch'], (int)$runtime['last_seen_epoch']);
        $runtime['last_server_epoch'] = max((int)$current['last_server_epoch'], (int)$runtime['last_server_epoch']);
    }
    $runtime['last_error'] = substr((string)(isset($runtime['last_error']) ? $runtime['last_error'] : ''), 0, 1000);
    $jsonWritten = offline_license_write_json(offline_license_storage_path('license_runtime.json'), $runtime);

    $config = offline_license_config();
    if ($config['db_path'] !== '' && is_file($config['db_path']) && class_exists('PDO')) {
        try {
            $pdo = new PDO('sqlite:' . $config['db_path']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $writeRuntime = static function (PDO $connection) use ($runtime) {
                $stmt = $connection->prepare("INSERT OR REPLACE INTO offline_license_runtime
                    (id, last_seen_epoch, last_server_epoch, last_attempt_epoch, last_success_epoch, last_error, updated_at)
                    VALUES (1, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
                $stmt->execute(array(
                    $runtime['last_seen_epoch'],
                    $runtime['last_server_epoch'],
                    $runtime['last_attempt_epoch'],
                    $runtime['last_success_epoch'],
                    $runtime['last_error'],
                ));
            };

            try {
                $writeRuntime($pdo);
            } catch (Throwable $missingRuntimeTable) {
                $pdo->exec("CREATE TABLE IF NOT EXISTS offline_license_runtime (
                    id INTEGER PRIMARY KEY CHECK (id = 1),
                    last_seen_epoch INTEGER NOT NULL DEFAULT 0,
                    last_server_epoch INTEGER NOT NULL DEFAULT 0,
                    last_attempt_epoch INTEGER NOT NULL DEFAULT 0,
                    last_success_epoch INTEGER NOT NULL DEFAULT 0,
                    last_error TEXT NOT NULL DEFAULT '',
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
                )");
                $writeRuntime($pdo);
            }
        } catch (Throwable $e) {
        }
    }
    return $jsonWritten;
}

function offline_license_normalize_serial($serial)
{
    $serial = strtoupper(trim((string)$serial));
    $serial = preg_replace('/\s+/', '', $serial);
    return is_string($serial) ? substr($serial, 0, 191) : '';
}

function offline_license_shell_exec_available()
{
    if (!function_exists('shell_exec')) {
        return false;
    }
    $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
    return !in_array('shell_exec', $disabled, true);
}

function offline_license_run_command($command)
{
    if (!offline_license_shell_exec_available()) {
        return '';
    }
    try {
        $result = @shell_exec((string)$command);
        return is_string($result) ? trim($result) : '';
    } catch (Throwable $e) {
        return '';
    }
}

function offline_license_detect_identity_uncached()
{
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $powerShell = 'powershell.exe -NoProfile -NonInteractive -ExecutionPolicy Bypass '
            . '-Command "$s=Get-CimInstance Win32_DiskDrive -ErrorAction SilentlyContinue '
            . '| Where-Object {$_.SerialNumber} | Select-Object -First 1 -ExpandProperty SerialNumber; '
            . 'if($s){[Console]::Write($s.Trim())}"';
        $serial = offline_license_normalize_serial(offline_license_run_command($powerShell));
        if ($serial !== '') {
            return array('serial' => $serial, 'source' => 'HDD fizic');
        }

        $wmic = offline_license_run_command('wmic diskdrive get SerialNumber /value 2>NUL');
        if (preg_match('/SerialNumber\s*=\s*([^\r\n]+)/i', $wmic, $matches)) {
            $serial = offline_license_normalize_serial($matches[1]);
            if ($serial !== '') {
                return array('serial' => $serial, 'source' => 'HDD fizic');
            }
        }

        $volume = offline_license_run_command('cmd.exe /D /C vol %SystemDrive% 2>NUL');
        if (preg_match('/([A-F0-9]{4}-[A-F0-9]{4})/i', $volume, $matches)) {
            return array('serial' => offline_license_normalize_serial($matches[1]), 'source' => 'Volum sistem');
        }
    }

    $fingerprintParts = array(
        getenv('COMPUTERNAME'),
        getenv('PROCESSOR_IDENTIFIER'),
        getenv('SystemDrive'),
        php_uname('n'),
        php_uname('m'),
    );
    $fingerprint = implode('|', array_filter(array_map('strval', $fingerprintParts)));
    if ($fingerprint === '') {
        $fingerprint = __DIR__;
    }
    return array(
        'serial' => 'FALLBACK-' . strtoupper(substr(hash('sha256', $fingerprint), 0, 24)),
        'source' => 'Identificator hardware de rezerva',
    );
}

function offline_license_hardware_identity($forceRefresh = false)
{
    static $requestIdentity = null;
    if (!$forceRefresh && is_array($requestIdentity)) {
        return $requestIdentity;
    }

    $path = offline_license_storage_path('hardware_identity.json');
    if (!$forceRefresh) {
        $cached = offline_license_read_json($path);
        $detectedAt = isset($cached['detected_at']) ? strtotime((string)$cached['detected_at']) : false;
        if (!empty($cached['serial']) && $detectedAt !== false && $detectedAt >= time() - 86400) {
            $requestIdentity = array(
                'serial' => offline_license_normalize_serial($cached['serial']),
                'source' => isset($cached['source']) ? (string)$cached['source'] : 'HDD fizic',
                'detected_at' => (string)$cached['detected_at'],
            );
            return $requestIdentity;
        }
    }

    $identity = offline_license_detect_identity_uncached();
    $identity['detected_at'] = date(DATE_ATOM);
    offline_license_write_json($path, $identity);
    $requestIdentity = $identity;
    return $identity;
}

function offline_license_signature_valid(array $license, $signature)
{
    if (!function_exists('openssl_verify')) {
        return false;
    }
    $decodedSignature = base64_decode((string)$signature, true);
    if ($decodedSignature === false) {
        return false;
    }
    $canonical = json_encode($license, offline_license_json_flags());
    if (!is_string($canonical)) {
        return false;
    }
    $publicKey = openssl_pkey_get_public(OFFLINE_LICENSE_PUBLIC_KEY);
    if ($publicKey === false) {
        return false;
    }
    return openssl_verify($canonical, $decodedSignature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
}

function offline_license_validate_state(array $state, $refreshHardware = false, $trustedNow = null)
{
    $config = offline_license_config();
    $identity = offline_license_hardware_identity($refreshHardware);
    $result = array(
        'valid' => false,
        'code' => 'license_missing',
        'message' => 'Licenta nu a fost verificata pe acest dispozitiv.',
        'hardware_serial' => $identity['serial'],
        'hardware_source' => $identity['source'],
        'license' => array(),
    );

    if (empty($state['license']) || !is_array($state['license']) || empty($state['signature'])) {
        return $result;
    }
    $license = $state['license'];
    $result['license'] = $license;
    if (!offline_license_signature_valid($license, $state['signature'])) {
        $result['code'] = 'signature_invalid';
        $result['message'] = 'Fisierul local al licentei nu are o semnatura valida.';
        return $result;
    }
    if ((int)(isset($license['client_id']) ? $license['client_id'] : 0) !== $config['client_id']) {
        $result['code'] = 'client_mismatch';
        $result['message'] = 'Licenta locala apartine altui client.';
        return $result;
    }
    if (!hash_equals((string)(isset($license['installation_uuid']) ? $license['installation_uuid'] : ''), $config['installation_uuid'])) {
        $result['code'] = 'installation_mismatch';
        $result['message'] = 'Licenta locala apartine altei instalari.';
        return $result;
    }
    $licensedSerial = offline_license_normalize_serial(isset($license['hardware_serial']) ? $license['hardware_serial'] : '');
    if ($licensedSerial === '' || !hash_equals($licensedSerial, $identity['serial'])) {
        $result['code'] = 'hardware_mismatch';
        $result['message'] = 'Seria HDD curenta nu corespunde cu licenta salvata.';
        return $result;
    }

    $localNow = time();
    $runtime = offline_license_runtime_read();
    $maximumSeen = max((int)$runtime['last_seen_epoch'], (int)$runtime['last_server_epoch']);
    $effectiveNow = $trustedNow === null ? $localNow : (int)$trustedNow;
    if ($trustedNow === null && $maximumSeen > 0 && $localNow + $config['clock_tolerance_seconds'] < $maximumSeen) {
        $result['code'] = 'clock_rollback_detected';
        $result['message'] = 'Data calculatorului a fost mutata inapoi. Este necesara verificarea online.';
        return $result;
    }

    $issuedAt = isset($license['issued_at']) ? strtotime((string)$license['issued_at']) : false;
    $expiresAt = isset($license['expires_at']) ? strtotime((string)$license['expires_at']) : false;
    $maximumSeconds = ($config['valid_days'] + 1) * 86400;
    if ($issuedAt === false || $expiresAt === false || $expiresAt <= $issuedAt || ($expiresAt - $issuedAt) > $maximumSeconds) {
        $result['code'] = 'period_invalid';
        $result['message'] = 'Perioada licentei locale este invalida.';
        return $result;
    }
    if ($issuedAt > $effectiveNow + 3600) {
        $result['code'] = 'clock_invalid';
        $result['message'] = 'Data calculatorului nu permite validarea licentei.';
        return $result;
    }
    if ($expiresAt < $effectiveNow) {
        $result['code'] = 'license_expired';
        $result['message'] = 'Au trecut 30 de zile de la ultima verificare a licentei.';
        $result['expires_at'] = (string)$license['expires_at'];
        return $result;
    }

    $result['valid'] = true;
    $result['code'] = 'license_valid';
    $result['message'] = 'Licenta offline este valida.';
    $result['issued_at'] = (string)$license['issued_at'];
    $result['expires_at'] = (string)$license['expires_at'];
    $result['days_remaining'] = max(0, (int)ceil(($expiresAt - $effectiveNow) / 86400));
    if ($trustedNow !== null) {
        offline_license_runtime_write(array(
            '_reset_clock' => true,
            'last_seen_epoch' => $effectiveNow,
            'last_server_epoch' => $effectiveNow,
            'last_attempt_epoch' => $effectiveNow,
            'last_success_epoch' => $effectiveNow,
            'last_error' => '',
        ));
    } elseif ($localNow >= $maximumSeen + 60) {
        offline_license_runtime_write(array('last_seen_epoch' => $localNow));
    }
    return $result;
}

function offline_license_status($refreshHardware = false)
{
    $config = offline_license_config();
    if (!$config['enabled']) {
        return array('valid' => true, 'code' => 'license_disabled', 'message' => 'Verificarea licentei este dezactivata.');
    }
    return offline_license_validate_state(
        offline_license_read_json(offline_license_storage_path('license_state.json')),
        $refreshHardware
    );
}

function offline_license_http_post($url, $apiKey, $body, $timeout)
{
    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        $curlOptions = array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json',
                'Content-Type: application/json; charset=utf-8',
                'X-Api-Key: ' . $apiKey,
            ),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        );
        $licenseConfig = offline_license_config();
        if ($licenseConfig['ca_bundle_path'] !== '' && is_file($licenseConfig['ca_bundle_path'])) {
            $curlOptions[CURLOPT_CAINFO] = $licenseConfig['ca_bundle_path'];
        }
        curl_setopt_array($curl, $curlOptions);
        $response = curl_exec($curl);
        $error = curl_error($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        return array('body' => is_string($response) ? $response : '', 'status' => $status, 'error' => $error);
    }

    $contextOptions = array('http' => array(
        'method' => 'POST',
        'timeout' => $timeout,
        'ignore_errors' => true,
        'header' => "Accept: application/json\r\nContent-Type: application/json; charset=utf-8\r\nX-Api-Key: {$apiKey}\r\n",
        'content' => $body,
    ));
    $licenseConfig = offline_license_config();
    if ($licenseConfig['ca_bundle_path'] !== '' && is_file($licenseConfig['ca_bundle_path'])) {
        $contextOptions['ssl'] = array(
            'verify_peer' => true,
            'verify_peer_name' => true,
            'cafile' => $licenseConfig['ca_bundle_path'],
        );
    }
    $context = stream_context_create($contextOptions);
    $response = @file_get_contents($url, false, $context);
    $status = 0;
    if (!empty($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches)) {
        $status = (int)$matches[1];
    }
    return array(
        'body' => is_string($response) ? $response : '',
        'status' => $status,
        'error' => $response === false ? 'Endpointul online nu a putut fi accesat.' : '',
    );
}

function offline_license_check_online($timeout = 20)
{
    $config = offline_license_config();
    $identity = offline_license_hardware_identity(true);
    $baseResult = array(
        'ok' => false,
        'hardware_serial' => $identity['serial'],
        'hardware_source' => $identity['source'],
    );
    if ($config['url'] === '' || $config['api_key'] === '' || $config['client_id'] <= 0 || $config['installation_uuid'] === '') {
        return $baseResult + array(
            'code' => 'configuration_invalid',
            'message' => 'Configurarea verificarii licentei este incompleta.',
        );
    }

    $payload = array(
        'client_id' => $config['client_id'],
        'hardware_serial' => $identity['serial'],
        'hardware_source' => $identity['source'],
        'installation_uuid' => $config['installation_uuid'],
        'app_name' => $config['app_name'],
    );
    $body = json_encode($payload, offline_license_json_flags());
    $http = offline_license_http_post($config['url'], $config['api_key'], $body, max(5, (int)$timeout));
    if ($http['body'] === '') {
        return $baseResult + array(
            'code' => 'connection_failed',
            'message' => $http['error'] !== '' ? $http['error'] : 'Endpointul online nu a raspuns.',
            'http_status' => $http['status'],
        );
    }
    $response = json_decode($http['body'], true);
    if (!is_array($response)) {
        return $baseResult + array(
            'code' => 'invalid_response',
            'message' => 'Endpointul online a trimis un raspuns invalid.',
            'http_status' => $http['status'],
        );
    }
    $response['hardware_serial'] = isset($response['hardware_serial']) ? $response['hardware_serial'] : $identity['serial'];
    $response['hardware_source'] = $identity['source'];
    $response['http_status'] = $http['status'];
    if (empty($response['ok'])) {
        return $response;
    }
    if (empty($response['license']) || !is_array($response['license']) || empty($response['signature'])) {
        return $baseResult + array('code' => 'invalid_license', 'message' => 'Raspunsul online nu contine licenta completa.');
    }
    $state = array(
        'license' => $response['license'],
        'signature' => (string)$response['signature'],
        'signature_algorithm' => isset($response['signature_algorithm']) ? (string)$response['signature_algorithm'] : '',
        'saved_at' => date(DATE_ATOM),
    );
    $serverTime = isset($response['license']['issued_at']) ? strtotime((string)$response['license']['issued_at']) : false;
    $validation = offline_license_validate_state($state, false, $serverTime === false ? null : $serverTime);
    if (empty($validation['valid'])) {
        return $baseResult + array(
            'code' => $validation['code'],
            'message' => $validation['message'],
        );
    }
    if (!offline_license_write_json(offline_license_storage_path('license_state.json'), $state)) {
        return $baseResult + array(
            'code' => 'storage_failed',
            'message' => 'Licenta este valida, dar nu a putut fi salvata local.',
        );
    }
    return array_merge($response, $validation, array('ok' => true));
}

function offline_license_background_refresh()
{
    $statePath = offline_license_storage_path('license_state.json');
    $state = offline_license_read_json($statePath);
    if (empty($state['license']) || !is_array($state['license']) || empty($state['signature'])) {
        return array(
            'ok' => true,
            'status' => 'manual_activation_required',
            'checked_online' => false,
            'valid' => false,
        );
    }

    $config = offline_license_config();
    $status = offline_license_validate_state($state, false);
    $expiresAt = isset($state['license']['expires_at']) ? strtotime((string)$state['license']['expires_at']) : false;
    $refreshThreshold = time() + ($config['renew_before_days'] * 86400);
    $needsRefresh = empty($status['valid']) || $expiresAt === false || $expiresAt <= $refreshThreshold;
    if (!$needsRefresh) {
        return array(
            'ok' => true,
            'status' => 'not_due',
            'checked_online' => false,
            'valid' => true,
            'days_remaining' => isset($status['days_remaining']) ? (int)$status['days_remaining'] : null,
        );
    }

    $runtime = offline_license_runtime_read();
    $now = time();
    $clockRollback = isset($status['code']) && $status['code'] === 'clock_rollback_detected';
    if (!$clockRollback && (int)$runtime['last_attempt_epoch'] > 0
        && $now - (int)$runtime['last_attempt_epoch'] < $config['retry_seconds']) {
        return array(
            'ok' => true,
            'status' => 'retry_scheduled',
            'checked_online' => false,
            'valid' => !empty($status['valid']),
            'next_attempt_epoch' => (int)$runtime['last_attempt_epoch'] + $config['retry_seconds'],
        );
    }

    offline_license_runtime_write(array('last_attempt_epoch' => $now, 'last_error' => ''));
    $result = offline_license_check_online(8);
    if (!empty($result['ok'])) {
        return array(
            'ok' => true,
            'status' => 'refreshed',
            'checked_online' => true,
            'valid' => true,
            'expires_at' => isset($result['expires_at']) ? $result['expires_at'] : (isset($result['license']['expires_at']) ? $result['license']['expires_at'] : ''),
        );
    }

    offline_license_runtime_write(array(
        'last_attempt_epoch' => $now,
        'last_error' => isset($result['message']) ? (string)$result['message'] : 'Verificarea online nu a reusit.',
    ));
    return array(
        'ok' => false,
        'status' => 'refresh_failed',
        'checked_online' => true,
        'valid' => !empty($status['valid']),
        'message' => isset($result['message']) ? (string)$result['message'] : 'Verificarea online nu a reusit.',
    );
}

function offline_license_check_url()
{
    $script = str_replace('\\', '/', isset($_SERVER['SCRIPT_NAME']) ? (string)$_SERVER['SCRIPT_NAME'] : '');
    foreach (array('/interfata_vanzare/', '/app_restaurant_v2/') as $marker) {
        $position = strpos($script, $marker);
        if ($position !== false) {
            return substr($script, 0, $position) . $marker . 'offline_license_check.php';
        }
    }
    return 'offline_license_check.php';
}

function offline_license_request_expects_json()
{
    $accept = isset($_SERVER['HTTP_ACCEPT']) ? strtolower((string)$_SERVER['HTTP_ACCEPT']) : '';
    $requestedWith = isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) : '';
    return strpos($accept, 'application/json') !== false || $requestedWith === 'xmlhttprequest';
}

function offline_license_enforce()
{
    $config = offline_license_config();
    if (!$config['enabled'] || PHP_SAPI === 'cli') {
        return;
    }
    $script = basename(isset($_SERVER['SCRIPT_FILENAME']) ? (string)$_SERVER['SCRIPT_FILENAME'] : '');
    if (in_array($script, array(
        'offline_license_check.php',
        'offline_license_background.php',
        'offline_sync_worker.php',
        'offline_sync_status.php',
    ), true)) {
        return;
    }
    $status = offline_license_status(false);
    if (!empty($status['valid'])) {
        return;
    }
    $verifyUrl = offline_license_check_url();
    if (offline_license_request_expects_json()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array(
            'ok' => false,
            'code' => $status['code'],
            'message' => $status['message'],
            'verify_url' => $verifyUrl,
        ), offline_license_json_flags());
        exit;
    }
    header('Location: ' . $verifyUrl . '?reason=' . rawurlencode((string)$status['code']));
    exit;
}
