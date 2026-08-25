<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
date_default_timezone_set('Europe/Bucharest');

require_once __DIR__ . '/session.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function offline_2fa_out(array $payload, int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function offline_2fa_table_columns(PDO $pdo, string $table): array
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        throw new RuntimeException('Numele tabelului admins este invalid.');
    }

    $columns = [];
    $stmt = $pdo->query('PRAGMA table_info("' . $table . '")');
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $name = (string)($row['name'] ?? '');
        if ($name !== '') {
            $columns[$name] = true;
        }
    }
    return $columns;
}

function offline_2fa_sync_tablet_user(PDO $pdo, string $table, array $tabletUser, int $operatorId, int $codLocatie): array
{
    $tabletAdminId = (int)($tabletUser['admin_id'] ?? 0);
    if ($tabletAdminId <= 0) {
        throw new RuntimeException('AGECS online nu a transmis admin_id pentru utilizatorul tableta.');
    }

    $columns = offline_2fa_table_columns($pdo, $table);
    if (!isset($columns['admin_id'])) {
        throw new RuntimeException('Tabela locala admins nu contine admin_id.');
    }

    $select = $pdo->prepare('SELECT * FROM "' . $table . '" WHERE admin_id = ? LIMIT 1');
    $select->execute([$tabletAdminId]);
    $existing = $select->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($existing) {
        $existingRank = strtolower(trim((string)($existing['rank'] ?? '')));
        if ($existingRank !== '' && $existingRank !== 'tableta') {
            throw new RuntimeException('admin_id ' . $tabletAdminId . ' exista local si nu este utilizator tableta.');
        }
    }

    // Nu sincronizam parola. Tableta se autentifica online; local pastram doar identitatea si asocierea.
    $values = [
        'admin_firstname' => (string)($tabletUser['admin_firstname'] ?? ''),
        'admin_lastname' => (string)($tabletUser['admin_lastname'] ?? ''),
        'admin_email_address' => (string)($tabletUser['admin_email_address'] ?? ''),
        'rank' => 'tableta',
        'nr_tableta' => $operatorId,
        'cod_tableta' => $operatorId,
        'locatie' => $codLocatie,
        'cod_locatie' => $codLocatie,
        'conectat' => 0,
        'lucreaza_la' => 'restaurant_tableta',
        'cod_2fa_tableta' => (int)($tabletUser['cod_2fa_tableta'] ?? 0),
        'data_generare_cod_2fa_tableta' => (string)($tabletUser['data_generare_cod_2fa_tableta'] ?? '0000-00-00 00:00:00'),
    ];

    foreach (array_keys($values) as $column) {
        if (!isset($columns[$column])) {
            unset($values[$column]);
        }
    }

    if ($existing) {
        if ($values) {
            $sets = [];
            $params = [];
            foreach ($values as $column => $value) {
                $sets[] = '"' . $column . '" = ?';
                $params[] = $value;
            }
            $params[] = $tabletAdminId;
            $update = $pdo->prepare('UPDATE "' . $table . '" SET ' . implode(', ', $sets) . ' WHERE admin_id = ?');
            $update->execute($params);
        }
        $change = 'updated';
    } else {
        $insertValues = ['admin_id' => $tabletAdminId] + $values;
        $columnSql = [];
        $placeholders = [];
        $params = [];
        foreach ($insertValues as $column => $value) {
            $columnSql[] = '"' . $column . '"';
            $placeholders[] = '?';
            $params[] = $value;
        }
        $insert = $pdo->prepare(
            'INSERT INTO "' . $table . '" (' . implode(', ', $columnSql) . ') VALUES (' . implode(', ', $placeholders) . ')'
        );
        $insert->execute($params);
        $change = 'inserted';
    }

    return [
        'status' => 'success',
        'change' => $change,
        'tablet_admin_id' => $tabletAdminId,
        'owner_operator_id' => $operatorId,
    ];
}

try {
    if (!function_exists('restaurantIsOfflineSqlite') || !restaurantIsOfflineSqlite()) {
        offline_2fa_out(['status' => 'error', 'message' => 'Endpoint disponibil doar in instalarea offline.'], 409);
    }
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
        offline_2fa_out(['status' => 'error', 'message' => 'Metoda invalida.'], 405);
    }

    $action = trim((string)($_POST['action'] ?? 'status'));
    if (!in_array($action, ['status', 'regenerate'], true)) {
        offline_2fa_out(['status' => 'error', 'message' => 'Actiune invalida.'], 422);
    }

    $operatorId = (int)($_SESSION['admin_id'] ?? 0);
    $codLocatie = (int)($_SESSION['cod_locatie'] ?? 0);
    if ($operatorId <= 0 || $codLocatie <= 0) {
        offline_2fa_out(['status' => 'error', 'message' => 'Sesiune locala invalida.'], 401);
    }

    $tabletCfg = is_array($restaurantConfig['online_tablet_sync'] ?? null)
        ? $restaurantConfig['online_tablet_sync']
        : [];
    $salesCfg = is_array($restaurantConfig['offline_sales_sync'] ?? null)
        ? $restaurantConfig['offline_sales_sync']
        : [];

    $ordersApiUrl = trim((string)($tabletCfg['api_url'] ?? ''));
    $twoFaApiUrl = trim((string)($tabletCfg['twofa_api_url'] ?? ''));
    if ($twoFaApiUrl === '' && $ordersApiUrl !== '') {
        $twoFaApiUrl = str_replace('offline-tablet-orders.php', 'offline-tablet-2fa.php', $ordersApiUrl);
    }

    $apiKey = trim((string)($tabletCfg['api_key'] ?? ($salesCfg['api_key'] ?? '')));
    $clientId = (int)($tabletCfg['client_id'] ?? ($restaurantConfig['client_id'] ?? 0));
    $timeout = max(5, (int)($tabletCfg['timeout_seconds'] ?? 30));
    $verifySsl = filter_var($tabletCfg['verify_ssl'] ?? true, FILTER_VALIDATE_BOOL);
    $sendKeyInQuery = filter_var($tabletCfg['send_api_key_in_query'] ?? true, FILTER_VALIDATE_BOOL);
    $installationUuid = trim((string)($tabletCfg['installation_uuid'] ?? ''));

    if ($twoFaApiUrl === '' || $twoFaApiUrl === $ordersApiUrl || $apiKey === '' || $clientId <= 0) {
        offline_2fa_out([
            'status' => 'error',
            'message' => 'Configuratia 2FA offline este incompleta. Verifica online_tablet_sync.',
        ], 500);
    }

    $query = ['cod_client' => $clientId];
    if ($sendKeyInQuery) {
        $query['api_key'] = $apiKey;
    }
    $separator = strpos($twoFaApiUrl, '?') === false ? '?' : '&';
    $url = $twoFaApiUrl . $separator . http_build_query($query);

    $payload = [
        'action' => $action,
        'operator_id' => $operatorId,
        'cod_locatie' => $codLocatie,
        'cod_client' => $clientId,
        'installation_uuid' => $installationUuid,
    ];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('Payload-ul 2FA nu a putut fi serializat.');
    }

    $headers = [
        'Accept: application/json',
        'Content-Type: application/json; charset=utf-8',
        'X-Api-Key: ' . $apiKey,
        'Authorization: Bearer ' . $apiKey,
    ];

    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('Clientul HTTP nu a putut fi initializat.');
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => $verifySsl,
        CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $json,
    ]);

    $raw = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        throw new RuntimeException('Conectarea la AGECS online a esuat: ' . $curlError);
    }

    $response = json_decode((string)$raw, true);
    if (!is_array($response)) {
        throw new RuntimeException('AGECS online a returnat un raspuns JSON invalid.');
    }

    if ($httpCode < 200 || $httpCode >= 300 || (string)($response['status'] ?? '') !== 'success') {
        $message = trim((string)($response['message'] ?? ''));
        offline_2fa_out([
            'status' => 'error',
            'message' => $message !== '' ? $message : ('AGECS online HTTP ' . $httpCode),
        ], $httpCode >= 400 && $httpCode <= 599 ? $httpCode : 502);
    }

    if (is_array($response['tablet_user'] ?? null)) {
        try {
            $response['local_tablet_sync'] = offline_2fa_sync_tablet_user(
                $pdo,
                isset($tabel_final_admins) ? (string)$tabel_final_admins : 'admins_12',
                $response['tablet_user'],
                $operatorId,
                $codLocatie
            );
        } catch (Throwable $syncError) {
            error_log('[offline-vanzare-tableta-2fa][sync-user] ' . $syncError->getMessage());
            $response['local_tablet_sync'] = [
                'status' => 'error',
                'message' => $syncError->getMessage(),
            ];
        }
    }

    offline_2fa_out($response, 200);
} catch (Throwable $e) {
    error_log('[offline-vanzare-tableta-2fa] ' . $e->getMessage());
    offline_2fa_out(['status' => 'error', 'message' => 'Nu s-a putut comunica cu AGECS online pentru codul 2FA.'], 500);
}
