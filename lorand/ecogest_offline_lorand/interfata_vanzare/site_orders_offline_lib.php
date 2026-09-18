<?php
// AGECS Storefront -> Lorand offline POS.
// Sincronizeaza numai tabelele site_* si confirma importul prin API-ul AGECS.

date_default_timezone_set('Europe/Bucharest');
require_once __DIR__ . '/offline_external_config.php';

function site_orders_offline_json_flags()
{
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    return $flags;
}

function site_orders_offline_config()
{
    $all = offline_config_all();
    $sales = isset($all['offline_sales_sync']) && is_array($all['offline_sales_sync']) ? $all['offline_sales_sync'] : array();
    $clientId = (int)(isset($all['sync_client_id']) ? $all['sync_client_id'] : (isset($all['client_id']) ? $all['client_id'] : 0));
    $location = (int)(isset($all['cod_locatie_default']) ? $all['cod_locatie_default'] : 1);
    $fallback = 'client' . $clientId . '_loc' . $location . '_pos1';
    $installation = (string)(isset($all['transaction_uuid']) ? $all['transaction_uuid'] : (isset($all['installation_uuid']) ? $all['installation_uuid'] : $fallback));
    $installation = preg_replace('/[^A-Za-z0-9_-]/', '_', $installation);

    $apiKey = '';
    if (isset($all['site_orders_api_key'])) {
        $apiKey = trim((string)$all['site_orders_api_key']);
    }
    if ($apiKey === '' && isset($sales['api_key'])) {
        $apiKey = trim((string)$sales['api_key']);
    }
    if ($apiKey === '' && isset($all['sync_api_key'])) {
        $apiKey = trim((string)$all['sync_api_key']);
    }

    $timeout = isset($all['site_orders_timeout']) ? (int)$all['site_orders_timeout'] : (isset($sales['timeout']) ? (int)$sales['timeout'] : 20);
    $timeout = max(5, min(60, $timeout));
    $verifySsl = array_key_exists('verify_ssl', $sales) ? (bool)$sales['verify_ssl'] : true;
    if (array_key_exists('site_orders_verify_ssl', $all)) {
        $verifySsl = (bool)$all['site_orders_verify_ssl'];
    }

    return array(
        'api_url' => trim((string)(isset($all['site_orders_api_url']) ? $all['site_orders_api_url'] : 'https://agecs.agecs.in/api/site-orders-pos.php')),
        'api_key' => $apiKey,
        'client_id' => $clientId,
        'cod_locatie' => $location > 0 ? $location : 1,
        'installation_uuid' => $installation,
        'timeout' => $timeout,
        'verify_ssl' => $verifySsl,
        'ca_bundle_path' => trim((string)(isset($all['ca_bundle_path']) ? $all['ca_bundle_path'] : '')),
    );
}

function site_orders_offline_http($method, $action, $params, $codLocatie)
{
    $cfg = site_orders_offline_config();
    if ($cfg['api_url'] === '' || $cfg['api_key'] === '' || $cfg['client_id'] <= 0) {
        return array('ok' => false, 'http_code' => 0, 'data' => null, 'error' => 'Configurarea API pentru comenzile site este incompleta.');
    }
    if (!function_exists('curl_init')) {
        return array('ok' => false, 'http_code' => 0, 'data' => null, 'error' => 'Extensia cURL nu este disponibila in PHP.');
    }

    $base = array(
        'action' => (string)$action,
        'cod_client' => (int)$cfg['client_id'],
        'cod_locatie' => max(1, (int)$codLocatie),
    );
    $method = strtoupper((string)$method);
    $url = $cfg['api_url'];
    $body = null;
    if ($method === 'GET') {
        $query = array_merge($base, is_array($params) ? $params : array());
        $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($query, '', '&');
    } else {
        $payload = array_merge($base, is_array($params) ? $params : array());
        $body = json_encode($payload, site_orders_offline_json_flags());
        if ($body === false) {
            return array('ok' => false, 'http_code' => 0, 'data' => null, 'error' => 'Payload-ul pentru API nu poate fi codificat JSON.');
        }
    }

    $ch = curl_init($url);
    $headers = array(
        'Accept: application/json',
        'X-Api-Key: ' . $cfg['api_key'],
        'Authorization: Bearer ' . $cfg['api_key'],
        'X-Installation-Uuid: ' . $cfg['installation_uuid'],
    );
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json; charset=utf-8';
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(10, (int)$cfg['timeout']));
    curl_setopt($ch, CURLOPT_TIMEOUT, (int)$cfg['timeout']);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, (bool)$cfg['verify_ssl']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $cfg['verify_ssl'] ? 2 : 0);
    if ($cfg['verify_ssl'] && $cfg['ca_bundle_path'] !== '' && is_file($cfg['ca_bundle_path'])) {
        curl_setopt($ch, CURLOPT_CAINFO, $cfg['ca_bundle_path']);
    }
    if ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $raw = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false || $curlError !== '') {
        return array('ok' => false, 'http_code' => $httpCode, 'data' => null, 'error' => 'Conexiunea cu serverul AGECS a esuat: ' . $curlError);
    }
    $decoded = json_decode((string)$raw, true);
    if (!is_array($decoded)) {
        return array('ok' => false, 'http_code' => $httpCode, 'data' => null, 'error' => 'Serverul AGECS a raspuns intr-un format invalid.');
    }
    $ok = $httpCode >= 200 && $httpCode < 300 && isset($decoded['status']) && $decoded['status'] === 'success';
    if (!$ok) {
        $message = isset($decoded['message']) ? trim((string)$decoded['message']) : ('Eroare HTTP ' . $httpCode . '.');
        return array('ok' => false, 'http_code' => $httpCode, 'data' => $decoded, 'error' => $message);
    }
    return array('ok' => true, 'http_code' => $httpCode, 'data' => $decoded, 'error' => '');
}

function site_orders_offline_state(PDO $pdo)
{
    $row = $pdo->query('SELECT * FROM site_comenzi_sync_state WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    return $row ?: array('id' => 1, 'last_event_id' => 0, 'last_sync_at' => null, 'last_success_at' => null, 'last_error' => null);
}

function site_orders_offline_set_state(PDO $pdo, $lastEventId, $success, $error)
{
    if ($success) {
        $stmt = $pdo->prepare("UPDATE site_comenzi_sync_state SET last_event_id=?, last_sync_at=datetime('now','localtime'), last_success_at=datetime('now','localtime'), last_error=NULL, updated_at=datetime('now','localtime') WHERE id=1");
        $stmt->execute(array(max(0, (int)$lastEventId)));
    } else {
        $stmt = $pdo->prepare("UPDATE site_comenzi_sync_state SET last_sync_at=datetime('now','localtime'), last_error=?, updated_at=datetime('now','localtime') WHERE id=1");
        $stmt->execute(array(substr((string)$error, 0, 2000)));
    }
}

function site_orders_offline_store_order(PDO $pdo, $payload)
{
    if (!is_array($payload) || !isset($payload['order']) || !is_array($payload['order'])) {
        throw new RuntimeException('Payload de comanda invalid.');
    }
    $o = $payload['order'];
    $lines = isset($payload['lines']) && is_array($payload['lines']) ? $payload['lines'] : array();
    $onlineId = (int)(isset($o['id_comanda_site']) ? $o['id_comanda_site'] : 0);
    $uuid = trim((string)(isset($o['uuid_comanda']) ? $o['uuid_comanda'] : ''));
    if ($onlineId <= 0 || $uuid === '') {
        throw new RuntimeException('Comanda primita nu are identificatori valizi.');
    }

    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }
    try {
        $existingStmt = $pdo->prepare('SELECT nr_bon_pos, status_sync_pos, claim_token, importata_la, operator_import FROM site_comenzi WHERE uuid_comanda=? LIMIT 1');
        $existingStmt->execute(array($uuid));
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
        $remoteSyncStatus = trim((string)(isset($o['status_sync_pos']) ? $o['status_sync_pos'] : 'nepreluata'));
        $localSyncStatus = $remoteSyncStatus;
        if ($existing && (int)$existing['nr_bon_pos'] > 0 && $remoteSyncStatus !== 'preluata') {
            $localSyncStatus = (string)$existing['status_sync_pos'];
        }

        $fields = array(
            'id_comanda_online' => $onlineId,
            'uuid_comanda' => $uuid,
            'numar_comanda' => (string)(isset($o['numar_comanda']) ? $o['numar_comanda'] : ''),
            'cod_locatie' => (int)(isset($o['cod_locatie']) ? $o['cod_locatie'] : 1),
            'status_comanda' => (string)(isset($o['status_comanda']) ? $o['status_comanda'] : 'noua'),
            'status_sync_pos' => $localSyncStatus,
            'status_plata' => (string)(isset($o['status_plata']) ? $o['status_plata'] : 'neplatita'),
            'metoda_plata' => (string)(isset($o['metoda_plata']) ? $o['metoda_plata'] : 'la_ridicare'),
            'tip_predare' => (string)(isset($o['tip_predare']) ? $o['tip_predare'] : 'ridicare'),
            'data_ridicare' => isset($o['data_ridicare']) ? $o['data_ridicare'] : null,
            'moneda' => (string)(isset($o['moneda']) ? $o['moneda'] : 'RON'),
            'subtotal' => (float)(isset($o['subtotal']) ? $o['subtotal'] : 0),
            'discount_total' => (float)(isset($o['discount_total']) ? $o['discount_total'] : 0),
            'taxa_serviciu' => (float)(isset($o['taxa_serviciu']) ? $o['taxa_serviciu'] : 0),
            'taxa_livrare' => (float)(isset($o['taxa_livrare']) ? $o['taxa_livrare'] : 0),
            'total' => (float)(isset($o['total']) ? $o['total'] : 0),
            'observatii' => isset($o['observatii']) ? $o['observatii'] : null,
            'client_nume_snapshot' => (string)(isset($o['client_nume_snapshot']) ? $o['client_nume_snapshot'] : ''),
            'client_email_snapshot' => (string)(isset($o['client_email_snapshot']) ? $o['client_email_snapshot'] : ''),
            'client_telefon_snapshot' => (string)(isset($o['client_telefon_snapshot']) ? $o['client_telefon_snapshot'] : ''),
            'factura_solicitata' => (int)(isset($o['factura_solicitata']) ? $o['factura_solicitata'] : 0),
            'factura_denumire' => (string)(isset($o['factura_denumire']) ? $o['factura_denumire'] : ''),
            'factura_cui' => (string)(isset($o['factura_cui']) ? $o['factura_cui'] : ''),
            'factura_nr_reg_com' => (string)(isset($o['factura_nr_reg_com']) ? $o['factura_nr_reg_com'] : ''),
            'factura_tara' => (string)(isset($o['factura_tara']) ? $o['factura_tara'] : ''),
            'factura_judet' => (string)(isset($o['factura_judet']) ? $o['factura_judet'] : ''),
            'factura_localitate' => (string)(isset($o['factura_localitate']) ? $o['factura_localitate'] : ''),
            'factura_adresa' => (string)(isset($o['factura_adresa']) ? $o['factura_adresa'] : ''),
            'factura_cod_postal' => (string)(isset($o['factura_cod_postal']) ? $o['factura_cod_postal'] : ''),
            'livrare_tara' => (string)(isset($o['livrare_tara']) ? $o['livrare_tara'] : ''),
            'livrare_judet' => (string)(isset($o['livrare_judet']) ? $o['livrare_judet'] : ''),
            'livrare_localitate' => (string)(isset($o['livrare_localitate']) ? $o['livrare_localitate'] : ''),
            'livrare_adresa' => (string)(isset($o['livrare_adresa']) ? $o['livrare_adresa'] : ''),
            'livrare_cod_postal' => (string)(isset($o['livrare_cod_postal']) ? $o['livrare_cod_postal'] : ''),
            'creata_la' => (string)(isset($o['creata_la']) ? $o['creata_la'] : date('Y-m-d H:i:s')),
            'actualizata_la' => (string)(isset($o['actualizata_la']) ? $o['actualizata_la'] : date('Y-m-d H:i:s')),
        );

        if ($existing) {
            $sets = array();
            $values = array();
            foreach ($fields as $name => $value) {
                if ($name === 'uuid_comanda') {
                    continue;
                }
                $sets[] = $name . '=?';
                $values[] = $value;
            }
            $sets[] = "sincronizata_la=datetime('now','localtime')";
            $values[] = $uuid;
            $stmt = $pdo->prepare('UPDATE site_comenzi SET ' . implode(',', $sets) . ' WHERE uuid_comanda=?');
            $stmt->execute($values);
        } else {
            $names = array_keys($fields);
            $marks = array_fill(0, count($names), '?');
            $stmt = $pdo->prepare('INSERT INTO site_comenzi(' . implode(',', $names) . ') VALUES(' . implode(',', $marks) . ')');
            $stmt->execute(array_values($fields));
        }

        $pdo->prepare('DELETE FROM site_comenzi_produse WHERE id_comanda_online=?')->execute(array($onlineId));
        $ins = $pdo->prepare('INSERT INTO site_comenzi_produse(id_linie_online,id_comanda_online,cod_produs,nume_produs_snapshot,imagine_snapshot,um_snapshot,departament_snapshot,cantitate,cota_tva,pret_unitar_cu_tva,discount_linie,valoare_fara_tva,tva,valoare_cu_tva,observatie_produs,optiuni_json) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        foreach ($lines as $line) {
            $lineId = (int)(isset($line['id_linie_site']) ? $line['id_linie_site'] : 0);
            if ($lineId <= 0) {
                throw new RuntimeException('Linie de comanda fara id_linie_site.');
            }
            $ins->execute(array(
                $lineId, $onlineId,
                (int)(isset($line['cod_produs']) ? $line['cod_produs'] : 0),
                (string)(isset($line['nume_produs_snapshot']) ? $line['nume_produs_snapshot'] : ''),
                (string)(isset($line['imagine_snapshot']) ? $line['imagine_snapshot'] : ''),
                (string)(isset($line['um_snapshot']) ? $line['um_snapshot'] : ''),
                (string)(isset($line['departament_snapshot']) ? $line['departament_snapshot'] : ''),
                (float)(isset($line['cantitate']) ? $line['cantitate'] : 0),
                (float)(isset($line['cota_tva']) ? $line['cota_tva'] : 0),
                (float)(isset($line['pret_unitar_cu_tva']) ? $line['pret_unitar_cu_tva'] : 0),
                (float)(isset($line['discount_linie']) ? $line['discount_linie'] : 0),
                (float)(isset($line['valoare_fara_tva']) ? $line['valoare_fara_tva'] : 0),
                (float)(isset($line['tva']) ? $line['tva'] : 0),
                (float)(isset($line['valoare_cu_tva']) ? $line['valoare_cu_tva'] : 0),
                (string)(isset($line['observatie_produs']) ? $line['observatie_produs'] : ''),
                isset($line['optiuni_json']) ? $line['optiuni_json'] : null,
            ));
        }
        if ($started) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($started && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function site_orders_offline_sync(PDO $pdo, $codLocatie)
{
    $codLocatie = max(1, (int)$codLocatie);
    site_orders_offline_flush_outbox($pdo, $codLocatie, 10);
    $state = site_orders_offline_state($pdo);
    $after = max(0, (int)$state['last_event_id']);
    $changes = site_orders_offline_http('GET', 'changes', array('after_event_id' => $after, 'limit' => 200), $codLocatie);
    if (!$changes['ok']) {
        site_orders_offline_set_state($pdo, $after, false, $changes['error']);
        return array('ok' => false, 'count' => 0, 'last_event_id' => $after, 'error' => $changes['error']);
    }

    $events = isset($changes['data']['events']) && is_array($changes['data']['events']) ? $changes['data']['events'] : array();
    $uuids = array();
    foreach ($events as $event) {
        $uuid = trim((string)(isset($event['uuid_comanda']) ? $event['uuid_comanda'] : ''));
        if ($uuid !== '') {
            $uuids[$uuid] = true;
        }
    }
    try {
        foreach (array_keys($uuids) as $uuid) {
            $order = site_orders_offline_http('GET', 'order', array('uuid_comanda' => $uuid), $codLocatie);
            if (!$order['ok']) {
                throw new RuntimeException($order['error']);
            }
            site_orders_offline_store_order($pdo, $order['data']);
        }
        $last = isset($changes['data']['last_event_id']) ? max($after, (int)$changes['data']['last_event_id']) : $after;
        site_orders_offline_set_state($pdo, $last, true, '');
        return array('ok' => true, 'count' => count($uuids), 'last_event_id' => $last, 'error' => '');
    } catch (Throwable $e) {
        site_orders_offline_set_state($pdo, $after, false, $e->getMessage());
        return array('ok' => false, 'count' => 0, 'last_event_id' => $after, 'error' => $e->getMessage());
    }
}

function site_orders_offline_claim($uuid, $operatorId, $codLocatie)
{
    $cfg = site_orders_offline_config();
    return site_orders_offline_http('POST', 'claim', array(
        'uuid_comanda' => (string)$uuid,
        'operator_id' => (int)$operatorId,
        'installation_uuid' => $cfg['installation_uuid'],
    ), $codLocatie);
}

function site_orders_offline_queue_imported(PDO $pdo, $order, $claimToken, $nrBon, $operatorId, $codLocatie)
{
    $cfg = site_orders_offline_config();
    $payload = array(
        'claim_token' => (string)$claimToken,
        'nr_bon_pos' => (int)$nrBon,
        'operator_id' => (int)$operatorId,
        'installation_uuid' => $cfg['installation_uuid'],
    );
    $eventUuid = 'site-imported:' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string)$order['uuid_comanda']) . ':' . (int)$nrBon;
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO site_comenzi_outbox(event_uuid,id_comanda_online,actiune,payload_json,status,attempts,next_attempt_at) VALUES(?,?, 'imported', ?, 'pending', 0, NULL)");
    $stmt->execute(array($eventUuid, (int)$order['id_comanda_online'], json_encode($payload, site_orders_offline_json_flags())));
}

function site_orders_offline_flush_outbox(PDO $pdo, $codLocatie, $limit)
{
    $limit = max(1, min(50, (int)$limit));
    $rows = $pdo->query("SELECT * FROM site_comenzi_outbox WHERE status='pending' AND (next_attempt_at IS NULL OR next_attempt_at <= datetime('now','localtime')) ORDER BY id ASC LIMIT " . $limit)->fetchAll(PDO::FETCH_ASSOC);
    $sent = 0;
    foreach ($rows as $row) {
        $payload = json_decode((string)$row['payload_json'], true);
        if (!is_array($payload)) {
            $payload = array();
        }
        $result = site_orders_offline_http('POST', (string)$row['actiune'], $payload, $codLocatie);
        if ($result['ok']) {
            $stmt = $pdo->prepare("UPDATE site_comenzi_outbox SET status='sent', attempts=attempts+1, last_http_code=?, last_error=NULL, sent_at=datetime('now','localtime') WHERE id=?");
            $stmt->execute(array((int)$result['http_code'], (int)$row['id']));
            if ((string)$row['actiune'] === 'imported') {
                $up = $pdo->prepare("UPDATE site_comenzi SET status_sync_pos='preluata', claim_token='', sincronizata_la=datetime('now','localtime') WHERE id_comanda_online=?");
                $up->execute(array((int)$row['id_comanda_online']));
            }
            $sent++;
            continue;
        }
        $attempt = (int)$row['attempts'] + 1;
        $delay = min(3600, max(30, (int)pow(2, min(8, $attempt)) * 15));
        $next = date('Y-m-d H:i:s', time() + $delay);
        $stmt = $pdo->prepare("UPDATE site_comenzi_outbox SET status='pending', attempts=?, next_attempt_at=?, last_http_code=?, last_error=? WHERE id=?");
        $stmt->execute(array($attempt, $next, (int)$result['http_code'], substr((string)$result['error'], 0, 2000), (int)$row['id']));
    }
    return $sent;
}
