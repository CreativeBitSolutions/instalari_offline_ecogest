<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Bucharest');
ini_set('display_errors', '0');
ini_set('log_errors', '1');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/database_connection.php';

function ous_redirect(string $status, array $extra = []): void
{
    $params = array_merge(['users_sync' => $status], $extra);
    header('Location: agecs_login.php?' . http_build_query($params));
    exit;
}

function ous_bool($value, bool $default): bool
{
    if ($value === null || $value === '') {
        return $default;
    }
    return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
}

function ous_derive_users_api_url(array $restaurantConfig): string
{
    $tablet = is_array($restaurantConfig['online_tablet_sync'] ?? null)
        ? $restaurantConfig['online_tablet_sync']
        : [];
    $sales = is_array($restaurantConfig['offline_sales_sync'] ?? null)
        ? $restaurantConfig['offline_sales_sync']
        : [];

    $explicit = trim((string)($tablet['users_api_url'] ?? ($sales['users_api_url'] ?? '')));
    if ($explicit !== '') {
        return $explicit;
    }

    $candidates = [
        trim((string)($tablet['twofa_api_url'] ?? '')),
        trim((string)($tablet['api_url'] ?? '')),
        trim((string)($sales['api_url'] ?? '')),
    ];

    foreach ($candidates as $candidate) {
        if ($candidate === '') {
            continue;
        }
        $replaced = str_replace(
            ['offline-tablet-2fa.php', 'offline-tablet-orders.php'],
            'offline-users.php',
            $candidate
        );
        if ($replaced !== $candidate) {
            return $replaced;
        }

        $parts = parse_url($candidate);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            continue;
        }
        $path = (string)($parts['path'] ?? '/api/');
        $dir = rtrim(str_replace('\\', '/', dirname($path)), '/');
        $url = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $url .= ':' . (int)$parts['port'];
        }
        $url .= ($dir !== '' && $dir !== '.' ? $dir : '') . '/offline-users.php';
        return $url;
    }

    return '';
}

function ous_sqlite_columns(PDO $pdo, string $table): array
{
    $stmt = $pdo->query("PRAGMA table_info('" . str_replace("'", "''", $table) . "')");
    $columns = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $name = trim((string)($row['name'] ?? ''));
        if ($name !== '') {
            $columns[$name] = true;
        }
    }
    return $columns;
}

function ous_mirror_vat_tables(PDO $pdo, array $coteTva, array $coduriCasaTva): array
{
    $pdo->exec('DELETE FROM cote_tva');
    $insertCota = $pdo->prepare('INSERT INTO cote_tva (id, cota, dep_casa) VALUES (?, ?, ?)');
    foreach ($coteTva as $row) {
        if (!is_array($row) || !array_key_exists('id', $row) || !array_key_exists('cota', $row) || !array_key_exists('dep_casa', $row)) {
            throw new RuntimeException('Răspunsul online pentru cote_tva este incomplet.');
        }
        $insertCota->execute([(int)$row['id'], (float)$row['cota'], (int)$row['dep_casa']]);
    }

    $pdo->exec('DELETE FROM coduri_casa_tva');
    $insertCod = $pdo->prepare('INSERT INTO coduri_casa_tva (id, cota_tva, cod_listare_cota_casa) VALUES (?, ?, ?)');
    foreach ($coduriCasaTva as $row) {
        if (!is_array($row) || !array_key_exists('id', $row) || !array_key_exists('cota_tva', $row) || !array_key_exists('cod_listare_cota_casa', $row)) {
            throw new RuntimeException('Răspunsul online pentru coduri_casa_tva este incomplet.');
        }
        $insertCod->execute([(int)$row['id'], (int)$row['cota_tva'], (int)$row['cod_listare_cota_casa']]);
    }

    $runtime = $pdo->prepare('UPDATE offline_reference_sync_runtime SET vat_mirrored = 1, last_sync_at = ? WHERE id = 1');
    $runtime->execute([date('Y-m-d H:i:s')]);

    return ['cote_tva' => count($coteTva), 'coduri_casa_tva' => count($coduriCasaTva)];
}

try {
    if (!function_exists('restaurantIsOfflineSqlite') || !restaurantIsOfflineSqlite()) {
        ous_redirect('error', ['message' => 'Disponibil doar in instalarea offline.']);
    }
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
        ous_redirect('error', ['message' => 'Sincronizarea utilizatorilor necesita POST.']);
    }

    $tablet = is_array($restaurantConfig['online_tablet_sync'] ?? null)
        ? $restaurantConfig['online_tablet_sync']
        : [];
    $sales = is_array($restaurantConfig['offline_sales_sync'] ?? null)
        ? $restaurantConfig['offline_sales_sync']
        : [];

    $apiUrl = ous_derive_users_api_url($restaurantConfig);
    $apiKey = trim((string)($tablet['api_key'] ?? ($sales['api_key'] ?? '')));
    $clientId = (int)($tablet['client_id'] ?? ($restaurantConfig['client_id'] ?? 0));
    $codLocatie = (int)($_SESSION['cod_locatie'] ?? ($tablet['cod_locatie'] ?? ($restaurantConfig['cod_locatie'] ?? 0)));
    $timeout = max(5, (int)($tablet['timeout_seconds'] ?? ($sales['timeout_seconds'] ?? 30)));
    $verifySsl = ous_bool($tablet['verify_ssl'] ?? ($sales['verify_ssl'] ?? true), true);
    $sendKeyInQuery = ous_bool($tablet['send_api_key_in_query'] ?? ($sales['send_api_key_in_query'] ?? true), true);
    $installationUuid = trim((string)($tablet['installation_uuid'] ?? ($restaurantConfig['installation_uuid'] ?? '')));

    if ($apiUrl === '' || $apiKey === '' || $clientId <= 0 || $codLocatie <= 0) {
        ous_redirect('error', ['message' => 'Configurația pentru preluarea utilizatorilor și TVA este incompletă.']);
    }

    $query = ['cod_client' => $clientId];
    if ($sendKeyInQuery) {
        $query['api_key'] = $apiKey;
    }
    $separator = strpos($apiUrl, '?') === false ? '?' : '&';
    $url = $apiUrl . $separator . http_build_query($query);

    $payload = [
        'cod_client' => $clientId,
        'cod_locatie' => $codLocatie,
        'installation_uuid' => $installationUuid,
    ];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('Payload utilizatori invalid.');
    }

    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('Clientul HTTP nu a putut fi initializat.');
    }
    $curlOptions = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => $verifySsl,
        CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json; charset=utf-8',
            'X-Api-Key: ' . $apiKey,
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => $json,
    ];
    $caBundlePath = trim((string)($restaurantConfig['ca_bundle_path'] ?? ''));
    if ($verifySsl && $caBundlePath !== '' && is_file($caBundlePath)) {
        $curlOptions[CURLOPT_CAINFO] = $caBundlePath;
    }
    curl_setopt_array($ch, $curlOptions);

    $raw = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        throw new RuntimeException('Conectarea la ECOGEST online a esuat: ' . $curlError);
    }

    $response = json_decode((string)$raw, true);
    if (!is_array($response)) {
        throw new RuntimeException('ECOGEST online a returnat JSON invalid.');
    }
    if ($httpCode < 200 || $httpCode >= 300 || (string)($response['status'] ?? '') !== 'success') {
        $message = trim((string)($response['message'] ?? ''));
        throw new RuntimeException($message !== '' ? $message : ('ECOGEST online HTTP ' . $httpCode));
    }

    $users = is_array($response['users'] ?? null) ? $response['users'] : [];
    if (!array_key_exists('cote_tva', $response) || !is_array($response['cote_tva'])) {
        throw new RuntimeException('Răspunsul online nu conține cote_tva. Datele locale au fost păstrate.');
    }
    if (!array_key_exists('coduri_casa_tva', $response) || !is_array($response['coduri_casa_tva'])) {
        throw new RuntimeException('Răspunsul online nu conține coduri_casa_tva. Datele locale au fost păstrate.');
    }
    $columns = ous_sqlite_columns($pdo, 'admins_12');
    if (!isset($columns['admin_id'])) {
        throw new RuntimeException('Tabela locala admins_12 nu este disponibila.');
    }

    $allowed = [
        'admin_id', 'admin_firstname', 'admin_lastname', 'admin_email_address', 'admin_password',
        'rank', 'locatie', 'cod_locatie', 'lucreaza_la', 'nr_tableta', 'cod_tableta',
        'cod_2fa_tableta', 'data_generare_cod_2fa_tableta', 'active',
    ];

    $received = 0;
    $inserted = 0;
    $updated = 0;

    $pdo->beginTransaction();
    foreach ($users as $user) {
        if (!is_array($user)) {
            continue;
        }
        $adminId = (int)($user['admin_id'] ?? 0);
        if ($adminId <= 0) {
            continue;
        }
        $received++;

        $data = [];
        foreach ($allowed as $column) {
            if ($column === 'admin_id') {
                $data[$column] = $adminId;
                continue;
            }
            if (isset($columns[$column]) && array_key_exists($column, $user)) {
                $data[$column] = $user[$column];
            }
        }

        if (isset($columns['locatie'])) {
            $data['locatie'] = (int)($user['locatie'] ?? $codLocatie);
        }
        if (isset($columns['cod_locatie'])) {
            $data['cod_locatie'] = (int)($user['cod_locatie'] ?? $codLocatie);
        }
        if (isset($columns['conectat'])) {
            $data['conectat'] = 0;
        }
        if (isset($columns['active']) && !array_key_exists('active', $data)) {
            $data['active'] = 1;
        }

        $rank = strtolower(trim((string)($data['rank'] ?? $user['rank'] ?? '')));
        if ($rank === 'tableta') {
            $owner = (int)($user['nr_tableta'] ?? $user['cod_tableta'] ?? 0);
            if (isset($columns['nr_tableta'])) {
                $data['nr_tableta'] = (int)($user['nr_tableta'] ?? $owner);
            }
            if (isset($columns['cod_tableta'])) {
                $codTableta = (int)($user['cod_tableta'] ?? 0);
                $data['cod_tableta'] = $codTableta > 0 ? $codTableta : $owner;
            }
        }

        $existsStmt = $pdo->prepare('SELECT 1 FROM admins_12 WHERE admin_id = ? LIMIT 1');
        $existsStmt->execute([$adminId]);
        $exists = (bool)$existsStmt->fetchColumn();

        if ($exists) {
            $sets = [];
            $params = [];
            foreach ($data as $column => $value) {
                if ($column === 'admin_id' || !isset($columns[$column])) {
                    continue;
                }
                $sets[] = '"' . str_replace('"', '""', $column) . '" = ?';
                $params[] = $value;
            }
            if ($sets) {
                $params[] = $adminId;
                $stmt = $pdo->prepare('UPDATE admins_12 SET ' . implode(', ', $sets) . ' WHERE admin_id = ?');
                $stmt->execute($params);
            }
            $updated++;
        } else {
            $insertData = [];
            foreach ($data as $column => $value) {
                if (isset($columns[$column])) {
                    $insertData[$column] = $value;
                }
            }
            $names = array_keys($insertData);
            $quoted = array_map(static fn(string $name): string => '"' . str_replace('"', '""', $name) . '"', $names);
            $placeholders = array_fill(0, count($names), '?');
            $stmt = $pdo->prepare(
                'INSERT INTO admins_12 (' . implode(', ', $quoted) . ') VALUES (' . implode(', ', $placeholders) . ')'
            );
            $stmt->execute(array_values($insertData));
            $inserted++;
        }
    }
    $vatCounts = ous_mirror_vat_tables($pdo, $response['cote_tva'], $response['coduri_casa_tva']);
    $pdo->commit();

    ous_redirect('success', [
        'received' => $received,
        'inserted' => $inserted,
        'updated' => $updated,
        'cote_tva_count' => $vatCounts['cote_tva'],
        'coduri_casa_tva_count' => $vatCounts['coduri_casa_tva'],
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[offline-users-sync] ' . $e->getMessage());
    ous_redirect('error', ['message' => mb_substr($e->getMessage(), 0, 160)]);
}
