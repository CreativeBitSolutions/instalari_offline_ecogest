<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Bucharest');

function restaurant_tablet_sync_config(array $restaurantConfig): array
{
    $base = is_array($restaurantConfig['offline_sales_sync'] ?? null)
        ? $restaurantConfig['offline_sales_sync']
        : [];
    $cfg = is_array($restaurantConfig['online_tablet_sync'] ?? null)
        ? $restaurantConfig['online_tablet_sync']
        : [];

    return [
        'enabled' => filter_var($cfg['enabled'] ?? true, FILTER_VALIDATE_BOOL),
        'automatic' => filter_var($cfg['automatic'] ?? true, FILTER_VALIDATE_BOOL),
        'api_url' => trim((string)($cfg['api_url'] ?? '')),
        'api_key' => trim((string)($cfg['api_key'] ?? ($base['api_key'] ?? ''))),
        'client_id' => (int)($cfg['client_id'] ?? ($restaurantConfig['client_id'] ?? 0)),
        'cod_locatie' => (int)($cfg['cod_locatie'] ?? ($restaurantConfig['cod_locatie'] ?? 0)),
        'installation_uuid' => trim((string)($cfg['installation_uuid'] ?? ($restaurantConfig['installation_uuid'] ?? ''))),
        'timeout_seconds' => max(5, (int)($cfg['timeout_seconds'] ?? 30)),
        'automatic_interval_seconds' => max(15, (int)($cfg['automatic_interval_seconds'] ?? 30)),
        'limit' => max(1, min(500, (int)($cfg['limit'] ?? 200))),
        'send_api_key_in_query' => filter_var($cfg['send_api_key_in_query'] ?? ($base['send_api_key_in_query'] ?? true), FILTER_VALIDATE_BOOL),
        'verify_ssl' => filter_var($cfg['verify_ssl'] ?? ($base['verify_ssl'] ?? true), FILTER_VALIDATE_BOOL),
    ];
}

function restaurant_tablet_sync_assert_config(array $cfg): void
{
    if (!$cfg['enabled']) {
        throw new RuntimeException('Sincronizarea comenzilor de tabletă este dezactivată.');
    }
    if ($cfg['api_url'] === '' || $cfg['api_key'] === '') {
        throw new RuntimeException('Lipsesc api_url sau api_key pentru comenzile de tabletă.');
    }
    if ($cfg['client_id'] <= 0 || $cfg['cod_locatie'] <= 0) {
        throw new RuntimeException('Clientul sau locația pentru comenzile de tabletă este invalidă.');
    }
}

function restaurant_tablet_sync_log(PDO $pdo, string $action, string $status, array $counts = [], int $httpCode = 0, string $message = ''): void
{
    $stmt = $pdo->prepare("
        INSERT INTO offline_tablet_sync_logs
            (action, status, data_ora, received_count, inserted_count, updated_count, acknowledged_count, http_code, message)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $action,
        $status,
        date('Y-m-d H:i:s'),
        (int)($counts['received'] ?? 0),
        (int)($counts['inserted'] ?? 0),
        (int)($counts['updated'] ?? 0),
        (int)($counts['acknowledged'] ?? 0),
        $httpCode,
        mb_substr($message, 0, 1000),
    ]);
}

function restaurant_tablet_sync_runtime(PDO $pdo, array $values): void
{
    $allowed = [
        'last_pull_at', 'last_pull_success_at', 'last_ack_at', 'last_ack_success_at',
        'last_error', 'last_orders_received', 'last_orders_inserted', 'last_orders_updated',
    ];
    $sets = [];
    $params = [];
    foreach ($allowed as $column) {
        if (!array_key_exists($column, $values)) {
            continue;
        }
        $sets[] = $column . ' = ?';
        $params[] = $values[$column];
    }
    if (!$sets) {
        return;
    }
    $sets[] = 'updated_at = ?';
    $params[] = date('Y-m-d H:i:s');
    $params[] = 1;
    $stmt = $pdo->prepare('UPDATE offline_tablet_sync_runtime SET ' . implode(', ', $sets) . ' WHERE id = ?');
    $stmt->execute($params);
}

function restaurant_tablet_sync_http(array $cfg, string $method, array $query = [], ?array $payload = null): array
{
    if ($cfg['send_api_key_in_query']) {
        $query['api_key'] = $cfg['api_key'];
    }
    $query['cod_client'] = $cfg['client_id'];
    $separator = strpos($cfg['api_url'], '?') === false ? '?' : '&';
    $url = $cfg['api_url'] . ($query ? $separator . http_build_query($query) : '');

    $headers = [
        'Accept: application/json',
        'X-Api-Key: ' . $cfg['api_key'],
        'Authorization: Bearer ' . $cfg['api_key'],
    ];
    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('Clientul HTTP nu a putut fi inițializat.');
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => min(10, $cfg['timeout_seconds']),
        CURLOPT_TIMEOUT => $cfg['timeout_seconds'],
        CURLOPT_SSL_VERIFYPEER => $cfg['verify_ssl'],
        CURLOPT_SSL_VERIFYHOST => $cfg['verify_ssl'] ? 2 : 0,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($payload !== null) {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            curl_close($ch);
            throw new RuntimeException('Payload-ul comenzilor de tabletă nu a putut fi serializat.');
        }
        $headers[] = 'Content-Type: application/json; charset=utf-8';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    }

    $raw = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false) {
        throw new RuntimeException('Conectarea la API-ul comenzilor de tabletă a eșuat: ' . $curlError);
    }
    $decoded = json_decode((string)$raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('API-ul comenzilor de tabletă a returnat un răspuns JSON invalid.');
    }
    if ($httpCode < 200 || $httpCode >= 300 || (string)($decoded['status'] ?? '') !== 'success') {
        throw new RuntimeException((string)($decoded['message'] ?? ('API tabletă HTTP ' . $httpCode)));
    }
    return ['http_code' => $httpCode, 'body' => $decoded];
}

function restaurant_tablet_sync_store_order(PDO $pdo, array $order): string
{
    $nrbon = (int)($order['nrbon'] ?? 0);
    if ($nrbon <= 0) {
        throw new RuntimeException('Comanda primită nu are nrbon valid.');
    }
    $existingStmt = $pdo->prepare('SELECT stare, payload_hash FROM com_tableta WHERE nrbon = ? LIMIT 1');
    $existingStmt->execute([$nrbon]);
    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
    if ($existing && (string)$existing['stare'] === 'IMPORTATA') {
        return 'preserved';
    }

    $values = [
        (string)($order['serie'] ?? ''),
        (string)($order['data_bon'] ?? ''),
        (string)($order['ora_bon'] ?? ''),
        (float)($order['valoare_vanzare_cu_tva'] ?? 0),
        (float)($order['tva_colectata'] ?? 0),
        (float)($order['discount'] ?? 0),
        (int)($order['operator'] ?? 0),
        (float)($order['numerar'] ?? 0),
        (float)($order['card'] ?? 0),
        (float)($order['tichete'] ?? 0),
        (float)($order['rest'] ?? 0),
        (float)($order['protocol'] ?? 0),
        (float)($order['glovo'] ?? 0),
        (float)($order['virament_bancar'] ?? 0),
        (string)($order['cif_client'] ?? ''),
        (int)($order['cod_masa'] ?? 0),
        (int)($order['cod_inchidere'] ?? 0),
        (int)($order['tableta'] ?? 1),
        (int)($order['locatie'] ?? 0),
        (int)($order['nr_raport_z'] ?? 0),
        (string)($order['data_deschidere'] ?? ''),
        (int)($order['listat_nota_plata'] ?? 0),
        (int)($order['owner_operator_id'] ?? 0),
        (string)($order['owner_operator_name'] ?? ''),
        (string)($order['payload_hash'] ?? ''),
        date('Y-m-d H:i:s'),
    ];

    if (!$existing) {
        $insert = $pdo->prepare("
            INSERT INTO com_tableta (
                nrbon, serie, data_bon, ora_bon, valoare_vanzare_cu_tva, tva_colectata, discount,
                operator, numerar, card, tichete, rest, protocol, glovo, virament_bancar, cif_client,
                cod_masa, stare, status, cod_inchidere, tableta, locatie, nr_raport_z, data_deschidere,
                listat_nota_plata, owner_operator_id, owner_operator_name, payload_hash, fetched_at,
                online_ack_status, online_ack_attempts, online_ack_error
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'TRIMISA', 'S', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'not_ready', 0, '')
        ");
        $insert->execute(array_merge([$nrbon], $values));
        $change = 'inserted';
    } else {
        $update = $pdo->prepare("
            UPDATE com_tableta SET
                serie=?, data_bon=?, ora_bon=?, valoare_vanzare_cu_tva=?, tva_colectata=?, discount=?,
                operator=?, numerar=?, card=?, tichete=?, rest=?, protocol=?, glovo=?, virament_bancar=?,
                cif_client=?, cod_masa=?, cod_inchidere=?, tableta=?, locatie=?, nr_raport_z=?, data_deschidere=?,
                listat_nota_plata=?, owner_operator_id=?, owner_operator_name=?, payload_hash=?, fetched_at=?,
                stare='TRIMISA', status='S'
            WHERE nrbon=? AND stare<>'IMPORTATA'
        ");
        $update->execute(array_merge($values, [$nrbon]));
        $change = hash_equals((string)($existing['payload_hash'] ?? ''), (string)($order['payload_hash'] ?? '')) ? 'unchanged' : 'updated';
    }

    $pdo->prepare('DELETE FROM det_com_tableta WHERE nr_bon = ?')->execute([$nrbon]);
    $detailInsert = $pdo->prepare("
        INSERT INTO det_com_tableta (
            nr_bon, cod_p, nume_produs, cantitate, cota_tva, tva_col, pret_vanzare,
            valoare_vanzare, valoare_vanzare_cu_tva, discount, pachet, preparat, t_list,
            data, ora, cod_meniu, observatie_produs, preluat_osp, prioritate,
            online_id_vanz, departament_listare
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ((array)($order['details'] ?? []) as $detail) {
        $detailInsert->execute([
            $nrbon,
            (int)($detail['cod_p'] ?? 0),
            (string)($detail['nume_produs'] ?? ''),
            (float)($detail['cantitate'] ?? 0),
            (float)($detail['cota_tva'] ?? 0),
            (float)($detail['tva_col'] ?? 0),
            (float)($detail['pret_vanzare'] ?? 0),
            (float)($detail['valoare_vanzare'] ?? 0),
            (float)($detail['valoare_vanzare_cu_tva'] ?? 0),
            (float)($detail['discount'] ?? 0),
            (int)($detail['pachet'] ?? 0),
            (int)($detail['preparat'] ?? 0),
            (int)($detail['t_list'] ?? 0),
            (string)($detail['data'] ?? ''),
            (string)($detail['ora'] ?? ''),
            (int)($detail['cod_meniu'] ?? 0),
            mb_substr((string)($detail['observatie_produs'] ?? ''), 0, 100),
            (int)($detail['preluat_osp'] ?? 0),
            (int)($detail['prioritate'] ?? 0),
            (int)($detail['id_vanz'] ?? 0),
            ($detail['departament_listare'] ?? null) !== null ? (string)$detail['departament_listare'] : null,
        ]);
    }
    return $change;
}

function restaurant_tablet_sync_pull(PDO $pdo, array $cfg): array
{
    $now = date('Y-m-d H:i:s');
    restaurant_tablet_sync_runtime($pdo, ['last_pull_at' => $now]);
    try {
        $orders = [];
        $afterNrbon = 0;
        $lastHttpCode = 0;
        for ($page = 0; $page < 50; $page++) {
            $response = restaurant_tablet_sync_http($cfg, 'GET', [
                'cod_locatie' => $cfg['cod_locatie'],
                'limit' => $cfg['limit'],
                'after_nrbon' => $afterNrbon,
            ]);
            $lastHttpCode = (int)$response['http_code'];
            $body = $response['body'];
            if ((int)($body['client_id'] ?? 0) !== $cfg['client_id'] || (int)($body['cod_locatie'] ?? 0) !== $cfg['cod_locatie']) {
                throw new RuntimeException('Răspunsul API aparține altui client sau altei locații.');
            }
            $pageOrders = is_array($body['orders'] ?? null) ? $body['orders'] : [];
            foreach ($pageOrders as $pageOrder) {
                $orders[] = $pageOrder;
            }
            $nextAfter = (int)($body['next_after_nrbon'] ?? $afterNrbon);
            if (empty($body['has_more']) || !$pageOrders || $nextAfter <= $afterNrbon) {
                break;
            }
            $afterNrbon = $nextAfter;
        }
        $counts = ['received' => count($orders), 'inserted' => 0, 'updated' => 0];
        $pdo->beginTransaction();
        try {
            foreach ($orders as $order) {
                $change = restaurant_tablet_sync_store_order($pdo, is_array($order) ? $order : []);
                if ($change === 'inserted') {
                    $counts['inserted']++;
                } elseif ($change === 'updated') {
                    $counts['updated']++;
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        restaurant_tablet_sync_runtime($pdo, [
            'last_pull_success_at' => $now,
            'last_error' => '',
            'last_orders_received' => $counts['received'],
            'last_orders_inserted' => $counts['inserted'],
            'last_orders_updated' => $counts['updated'],
        ]);
        restaurant_tablet_sync_log($pdo, 'pull', 'success', $counts, $lastHttpCode, 'Preluare finalizată.');
        return $counts;
    } catch (Throwable $e) {
        restaurant_tablet_sync_runtime($pdo, ['last_error' => $e->getMessage()]);
        restaurant_tablet_sync_log($pdo, 'pull', 'error', [], 0, $e->getMessage());
        throw $e;
    }
}

function restaurant_tablet_sync_ack_pending(PDO $pdo, array $cfg, array $onlyOrderIds = []): array
{
    restaurant_tablet_sync_runtime($pdo, ['last_ack_at' => date('Y-m-d H:i:s')]);
    $where = "stare='IMPORTATA' AND imported_note_nrbon>0 AND online_ack_status IN ('pending','retry')";
    $params = [];
    if ($onlyOrderIds) {
        $ids = array_values(array_filter(array_map('intval', $onlyOrderIds), static fn(int $id): bool => $id > 0));
        if ($ids) {
            $where .= ' AND nrbon IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            $params = $ids;
        }
    }
    $stmt = $pdo->prepare("SELECT nrbon, imported_note_nrbon, payload_hash FROM com_tableta WHERE {$where} ORDER BY nrbon ASC LIMIT 100");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (!$rows) {
        return ['acknowledged' => 0];
    }

    $orders = [];
    foreach ($rows as $row) {
        $orders[] = [
            'online_nrbon' => (int)$row['nrbon'],
            'local_note_nrbon' => (int)$row['imported_note_nrbon'],
            'payload_hash' => (string)$row['payload_hash'],
        ];
    }
    $ids = array_column($orders, 'online_nrbon');
    $markAttempt = $pdo->prepare("UPDATE com_tableta SET online_ack_attempts=online_ack_attempts+1, online_ack_status='retry' WHERE nrbon=?");
    foreach ($ids as $id) {
        $markAttempt->execute([(int)$id]);
    }

    try {
        $response = restaurant_tablet_sync_http($cfg, 'POST', [], [
            'action' => 'ack_imported',
            'cod_locatie' => $cfg['cod_locatie'],
            'installation_uuid' => $cfg['installation_uuid'],
            'orders' => $orders,
        ]);
        $acknowledged = 0;
        $sent = $pdo->prepare("UPDATE com_tableta SET online_ack_status='sent', online_ack_error='', online_acknowledged_at=? WHERE nrbon=?");
        $failed = $pdo->prepare("UPDATE com_tableta SET online_ack_status='retry', online_ack_error=? WHERE nrbon=?");
        foreach ((array)($response['body']['results'] ?? []) as $result) {
            $id = (int)($result['online_nrbon'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            if ((string)($result['status'] ?? '') === 'acknowledged') {
                $sent->execute([date('Y-m-d H:i:s'), $id]);
                $acknowledged++;
            } else {
                $failed->execute([(string)($result['status'] ?? 'Confirmare refuzată'), $id]);
            }
        }
        restaurant_tablet_sync_runtime($pdo, ['last_ack_success_at' => date('Y-m-d H:i:s'), 'last_error' => '']);
        restaurant_tablet_sync_log($pdo, 'ack', 'success', ['acknowledged' => $acknowledged], (int)$response['http_code'], 'Confirmare finalizată.');
        return ['acknowledged' => $acknowledged];
    } catch (Throwable $e) {
        $failed = $pdo->prepare("UPDATE com_tableta SET online_ack_status='retry', online_ack_error=? WHERE nrbon=?");
        foreach ($ids as $id) {
            $failed->execute([mb_substr($e->getMessage(), 0, 1000), (int)$id]);
        }
        restaurant_tablet_sync_runtime($pdo, ['last_error' => $e->getMessage()]);
        restaurant_tablet_sync_log($pdo, 'ack', 'error', [], 0, $e->getMessage());
        throw $e;
    }
}

function restaurant_tablet_sync_run(PDO $pdo, array $restaurantConfig, bool $forcePull = false): array
{
    $cfg = restaurant_tablet_sync_config($restaurantConfig);
    if (!$cfg['enabled'] || (!$cfg['automatic'] && !$forcePull)) {
        return ['status' => 'disabled', 'pull' => null, 'ack' => null];
    }
    restaurant_tablet_sync_assert_config($cfg);

    $lockPath = defined('RESTAURANT_OFFLINE_API_DIR')
        ? RESTAURANT_OFFLINE_API_DIR . DIRECTORY_SEPARATOR . 'offline_tablet_sync.lock'
        : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'offline_tablet_sync.lock';
    $lock = fopen($lockPath, 'c');
    if ($lock === false) {
        throw new RuntimeException('Blocarea sincronizării tabletei nu poate fi inițializată.');
    }
    if (!flock($lock, LOCK_EX | LOCK_NB)) {
        fclose($lock);
        return ['status' => 'busy', 'pull' => null, 'ack' => null];
    }

    try {
        $runtime = $pdo->query('SELECT last_pull_at FROM offline_tablet_sync_runtime WHERE id=1')->fetch(PDO::FETCH_ASSOC) ?: [];
        $lastPull = strtotime((string)($runtime['last_pull_at'] ?? '')) ?: 0;
        $pullDue = $forcePull || (time() - $lastPull >= $cfg['automatic_interval_seconds']);
        $pull = null;
        $ack = null;
        $errors = [];
        if ($pullDue) {
            try {
                $pull = restaurant_tablet_sync_pull($pdo, $cfg);
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }
        try {
            $ack = restaurant_tablet_sync_ack_pending($pdo, $cfg);
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
        return [
            'status' => $errors ? 'waiting' : 'success',
            'pull' => $pull,
            'ack' => $ack,
            'errors' => $errors,
        ];
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
