<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Bucharest');
ini_set('display_errors', '0');
ini_set('log_errors', '1');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/database_connection.php';
require_once __DIR__ . '/setari_lorand_schema.php';

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

    $explicit = trim((string)($sales['users_api_url'] ?? ($tablet['users_api_url'] ?? '')));
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

function ous_mirror_date_firma(PDO $pdo, array $dateFirma): bool
{
    if (!$dateFirma) {
        return false;
    }

    $columns = ous_sqlite_columns($pdo, 'date_firma');
    if (!$columns) {
        return false;
    }

    $allowed = [
        'den_ent', 'cod_fiscal', 'nr_reg_com', 'sediu', 'judet', 'localitate', 'banca',
        'cont_banca', 'conducator_entitate', 'serie_casa_marcat', 'nui',
        'serie_memorie_fiscala', 'mod_listare', 'vanzare_sub_stoc', 'ajustare_adaos',
    ];
    $data = [];
    foreach ($allowed as $column) {
        if (!isset($columns[$column]) || !array_key_exists($column, $dateFirma)) {
            continue;
        }
        $value = $dateFirma[$column];
        if ($column === 'nui') {
            $value = max(0, (int)$value);
        } elseif ($column === 'serie_memorie_fiscala') {
            $value = trim((string)$value);
            if ($value === '0') {
                $value = '';
            }
        }
        $data[$column] = $value;
    }

    if (!$data) {
        return false;
    }

    $count = (int)$pdo->query('SELECT COUNT(*) FROM date_firma')->fetchColumn();
    $quoted = array_map(static fn(string $column): string => '"' . str_replace('"', '""', $column) . '"', array_keys($data));
    if ($count > 0) {
        $sets = [];
        foreach ($quoted as $index => $column) {
            $sets[] = $column . ' = ?';
        }
        $stmt = $pdo->prepare('UPDATE date_firma SET ' . implode(', ', $sets));
        $stmt->execute(array_values($data));
        return true;
    }

    $placeholders = array_fill(0, count($data), '?');
    $stmt = $pdo->prepare('INSERT INTO date_firma (' . implode(', ', $quoted) . ') VALUES (' . implode(', ', $placeholders) . ')');
    $stmt->execute(array_values($data));
    return true;
}

function ous_has_pending_receipt(PDO $pdo): bool
{
    $conditions = [];
    $detailColumns = ous_sqlite_columns($pdo, 'det_note');
    if (isset($detailColumns['nr_bon'])) {
        $conditions[] = 'EXISTS (SELECT 1 FROM det_note d WHERE d.nr_bon = n.nrbon)';
    }

    $noteColumns = ous_sqlite_columns($pdo, 'note');
    foreach (['fiscalizat', 'valoare_vanzare_cu_tva', 'tva_colectata'] as $column) {
        if (isset($noteColumns[$column])) {
            $conditions[] = 'COALESCE(n.' . $column . ', 0) <> 0';
        }
    }

    if (!$conditions) {
        return false;
    }

    $stmt = $pdo->query(
        "SELECT 1 FROM note n WHERE n.status = 'S' AND (" . implode(' OR ', $conditions) . ') LIMIT 1'
    );
    return (bool)$stmt->fetchColumn();
}

function ous_realign_empty_drafts(PDO $pdo, array $dateFirma): void
{
    $columns = ous_sqlite_columns($pdo, 'note');
    $sets = [];
    $params = [];
    if (isset($columns['serie_casa_marcat']) && array_key_exists('serie_casa_marcat', $dateFirma)) {
        $sets[] = 'serie_casa_marcat = ?';
        $params[] = trim((string)$dateFirma['serie_casa_marcat']);
    }
    if (isset($columns['nui']) && array_key_exists('nui', $dateFirma)) {
        $sets[] = 'nui = ?';
        $params[] = max(0, (int)$dateFirma['nui']);
    }
    if (isset($columns['serie_memorie_fiscala']) && array_key_exists('serie_memorie_fiscala', $dateFirma)) {
        $memory = trim((string)$dateFirma['serie_memorie_fiscala']);
        $sets[] = 'serie_memorie_fiscala = ?';
        $params[] = $memory === '0' ? '' : $memory;
    }
    if (!$sets) {
        return;
    }

    $detailCondition = '';
    if (isset(ous_sqlite_columns($pdo, 'det_note')['nr_bon'])) {
        $detailCondition = ' AND NOT EXISTS (SELECT 1 FROM det_note d WHERE d.nr_bon = note.nrbon)';
    }
    $stmt = $pdo->prepare(
        "UPDATE note SET " . implode(', ', $sets) . " WHERE status = 'S'" . $detailCondition
    );
    $stmt->execute($params);
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
    $submittedCsrf = (string)($_POST['csrf_token'] ?? '');
    $sessionCsrf = (string)($_SESSION['offline_login_csrf'] ?? '');
    if ($sessionCsrf === '' || !hash_equals($sessionCsrf, $submittedCsrf)) {
        ous_redirect('error', ['message' => 'Cererea de sincronizare nu mai este validă. Reîncarcă pagina de conectare.']);
    }
    if (!vanzare_v2_ensure_lorand_offline_schema($pdo)) {
        throw new RuntimeException('Schema locală pentru sincronizarea utilizatorilor și TVA nu a putut fi pregătită.');
    }

    $tablet = is_array($restaurantConfig['online_tablet_sync'] ?? null)
        ? $restaurantConfig['online_tablet_sync']
        : [];
    $sales = is_array($restaurantConfig['offline_sales_sync'] ?? null)
        ? $restaurantConfig['offline_sales_sync']
        : [];

    $apiUrl = ous_derive_users_api_url($restaurantConfig);
    $apiKey = trim((string)($sales['api_key'] ?? ($tablet['api_key'] ?? '')));
    $clientId = (int)($sales['client_id'] ?? ($restaurantConfig['client_id'] ?? 0));
    $codLocatie = (int)($_SESSION['cod_locatie'] ?? ($sales['cod_locatie'] ?? ($restaurantConfig['cod_locatie'] ?? 0)));
    $timeout = max(5, (int)($sales['timeout_seconds'] ?? ($tablet['timeout_seconds'] ?? 30)));
    $verifySsl = ous_bool($sales['verify_ssl'] ?? ($tablet['verify_ssl'] ?? true), true);
    $sendKeyInQuery = ous_bool($sales['send_api_key_in_query'] ?? ($tablet['send_api_key_in_query'] ?? true), true);
    $installationUuid = trim((string)($sales['installation_uuid'] ?? ($restaurantConfig['installation_uuid'] ?? '')));

    if ($apiUrl === '' || $apiKey === '' || $clientId <= 0 || $codLocatie <= 0) {
        ous_redirect('error', ['message' => 'Configurația pentru preluarea utilizatorilor și TVA este incompletă.']);
    }

    $query = [
        'cod_client' => $clientId,
        'cod_locatie' => $codLocatie,
        'app_mode' => 'magazin',
    ];
    if ($sendKeyInQuery) {
        $query['api_key'] = $apiKey;
    }
    $separator = strpos($apiUrl, '?') === false ? '?' : '&';
    $url = $apiUrl . $separator . http_build_query($query);

    $payload = [
        'cod_client' => $clientId,
        'cod_locatie' => $codLocatie,
        'installation_uuid' => $installationUuid,
        'app_mode' => 'magazin',
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

    if (ous_has_pending_receipt($pdo)) {
        throw new RuntimeException('Finalizeaza bonul curent inainte de actualizarea utilizatorilor si TVA.');
    }

    $pdo->exec('CREATE TABLE IF NOT EXISTS offline_online_operators (
        admin_id INTEGER PRIMARY KEY,
        synced_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');
    $pdo->beginTransaction();
    $pdo->exec('DELETE FROM offline_online_operators');
    $registerOperator = $pdo->prepare('INSERT INTO offline_online_operators(admin_id, synced_at) VALUES (?, CURRENT_TIMESTAMP)');
    foreach ($users as $user) {
        if (!is_array($user)) {
            throw new RuntimeException('Lista online contine un utilizator invalid.');
        }
        $adminId = (int)($user['admin_id'] ?? 0);
        if ($adminId <= 0) {
            throw new RuntimeException('Lista online contine un ID de utilizator invalid.');
        }
        if (isset($user['active']) && (int)$user['active'] !== 1) {
            continue;
        }
        if ((int)($user['locatie'] ?? $codLocatie) !== $codLocatie
            || (string)($user['lucreaza_la'] ?? '') !== 'magazin') {
            throw new RuntimeException('Utilizatorul primit nu apartine magazinului curent.');
        }
        $registerOperator->execute([$adminId]);
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
    $dateFirma = is_array($response['date_firma'] ?? null) ? $response['date_firma'] : [];
    $companySynced = ous_mirror_date_firma($pdo, $dateFirma);
    ous_realign_empty_drafts($pdo, $dateFirma);
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
