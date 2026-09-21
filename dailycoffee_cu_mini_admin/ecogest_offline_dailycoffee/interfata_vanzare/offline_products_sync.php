<?php
declare(strict_types=1);

require_once __DIR__ . '/database_connection.php';
require_once __DIR__ . '/cache_tools.php';

function dps_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function dps_quote(string $identifier): string
{
    return '"' . str_replace('"', '""', $identifier) . '"';
}

function dps_columns(PDO $pdo, string $table): array
{
    $rows = $pdo->query('PRAGMA table_info(' . dps_quote($table) . ')')->fetchAll(PDO::FETCH_ASSOC);
    return array_values(array_map(static fn(array $row): string => (string)$row['name'], $rows));
}

function dps_ensure_sync_state(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS offline_products_sync_state (
        id INTEGER PRIMARY KEY CHECK (id = 1),
        remote_hash TEXT NOT NULL DEFAULT '',
        last_check_at TEXT NULL,
        last_sync_at TEXT NULL,
        last_status TEXT NOT NULL DEFAULT 'never',
        last_error TEXT NULL,
        products_count INTEGER NOT NULL DEFAULT 0
    )");
    $pdo->exec('INSERT OR IGNORE INTO offline_products_sync_state (id) VALUES (1)');
}

function dps_state(PDO $pdo): array
{
    dps_ensure_sync_state($pdo);
    $row = $pdo->query('SELECT * FROM offline_products_sync_state WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : [];
}

function dps_state_update(PDO $pdo, array $values): void
{
    dps_ensure_sync_state($pdo);
    $allowed = ['remote_hash', 'last_check_at', 'last_sync_at', 'last_status', 'last_error', 'products_count'];
    $set = [];
    $params = [];
    foreach ($values as $key => $value) {
        if (!in_array($key, $allowed, true)) {
            continue;
        }
        $set[] = dps_quote($key) . ' = :' . $key;
        $params[':' . $key] = $value;
    }
    if (!$set) {
        return;
    }
    $stmt = $pdo->prepare('UPDATE offline_products_sync_state SET ' . implode(', ', $set) . ' WHERE id = 1');
    $stmt->execute($params);
}

function dps_http_get(string $url, string $apiKey, array $query, int $timeout, bool $verifySsl, string $caBundle): array
{
    $separator = strpos($url, '?') === false ? '?' : '&';
    $requestUrl = $url . $separator . http_build_query($query);
    $ch = curl_init($requestUrl);
    if ($ch === false) {
        throw new RuntimeException('Nu se poate initializa conexiunea pentru sincronizarea catalogului.');
    }

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => min(8, $timeout),
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'X-Api-Key: ' . $apiKey,
        ],
        CURLOPT_SSL_VERIFYPEER => $verifySsl,
        CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
    ];
    if ($verifySsl && $caBundle !== '' && is_file($caBundle)) {
        $options[CURLOPT_CAINFO] = $caBundle;
    }
    curl_setopt_array($ch, $options);
    $body = curl_exec($ch);
    $error = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $error !== '') {
        throw new RuntimeException('Conexiunea pentru catalog a esuat: ' . ($error !== '' ? $error : 'raspuns gol'));
    }
    if ($code < 200 || $code >= 300) {
        throw new RuntimeException('Serverul catalogului a raspuns cu HTTP ' . $code . '.');
    }

    $decoded = json_decode((string)$body, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Raspunsul catalogului nu este JSON valid.');
    }
    if (($decoded['status'] ?? '') !== 'success') {
        throw new RuntimeException((string)($decoded['message'] ?? 'Sincronizarea catalogului a fost respinsa.'));
    }

    return $decoded;
}

function dps_upsert_rows(PDO $pdo, string $table, string $primaryKey, array $rows): int
{
    $columns = dps_columns($pdo, $table);
    if (!in_array($primaryKey, $columns, true)) {
        return 0;
    }
    $changed = 0;
    foreach ($rows as $row) {
        if (!is_array($row) || !array_key_exists($primaryKey, $row)) {
            continue;
        }
        $payload = [];
        foreach ($columns as $column) {
            if (array_key_exists($column, $row)) {
                $payload[$column] = $row[$column];
            }
        }
        if (!array_key_exists($primaryKey, $payload)) {
            continue;
        }

        $insertColumns = array_keys($payload);
        $insertNames = [];
        $insertParams = [];
        $insertValues = [];
        foreach ($insertColumns as $column) {
            $name = ':i_' . $column;
            $insertNames[] = dps_quote($column);
            $insertValues[] = $name;
            $insertParams[$name] = $payload[$column];
        }
        $insert = $pdo->prepare('INSERT OR IGNORE INTO ' . dps_quote($table)
            . ' (' . implode(', ', $insertNames) . ') VALUES (' . implode(', ', $insertValues) . ')');
        $insert->execute($insertParams);

        $updates = [];
        $updateParams = [];
        foreach ($insertColumns as $column) {
            if ($column === $primaryKey) {
                continue;
            }
            $name = ':u_' . $column;
            $updates[] = dps_quote($column) . ' = ' . $name;
            $updateParams[$name] = $payload[$column];
        }
        if ($updates) {
            $updateParams[':pk'] = $payload[$primaryKey];
            $update = $pdo->prepare('UPDATE ' . dps_quote($table)
                . ' SET ' . implode(', ', $updates)
                . ' WHERE ' . dps_quote($primaryKey) . ' = :pk');
            $update->execute($updateParams);
        }
        $changed++;
    }
    return $changed;
}

function dps_sync_category_locations(PDO $pdo, array $rows, ?int $location = null): int
{
    $table = 'categorii_locatii';
    $columns = dps_columns($pdo, $table);
    if (!in_array('id_categorie', $columns, true) || !in_array('cod_locatie', $columns, true)) {
        return 0;
    }

    if (!$rows) {
        return 0;
    }

    $incoming = [];
    $changed = 0;
    $validRows = 0;
    foreach ($rows as $row) {
        $category = (int)($row['id_categorie'] ?? 0);
        $rowLocation = (int)($row['cod_locatie'] ?? 0);
        if ($category <= 0 || $rowLocation <= 0 || ($location !== null && $rowLocation !== $location)) {
            continue;
        }
        $validRows++;
        $key = $category . ':' . $rowLocation;
        $incoming[$key] = true;
        $payload = [];
        foreach ($columns as $column) {
            if ($column === 'id') {
                continue;
            }
            if (array_key_exists($column, $row)) {
                $payload[$column] = $row[$column];
            }
        }
        $find = $pdo->prepare('SELECT id FROM ' . dps_quote($table)
            . ' WHERE id_categorie = :id_categorie AND cod_locatie = :cod_locatie LIMIT 1');
        $find->execute([':id_categorie' => $category, ':cod_locatie' => $rowLocation]);
        $existingId = $find->fetchColumn();
        if ($existingId !== false) {
            $sets = [];
            $params = [':id' => $existingId];
            foreach ($payload as $column => $value) {
                $sets[] = dps_quote($column) . ' = :v_' . $column;
                $params[':v_' . $column] = $value;
            }
            if ($sets) {
                $stmt = $pdo->prepare('UPDATE ' . dps_quote($table) . ' SET ' . implode(', ', $sets) . ' WHERE id = :id');
                $stmt->execute($params);
            }
        } else {
            $names = array_keys($payload);
            $params = [];
            $placeholders = [];
            foreach ($names as $column) {
                $placeholder = ':v_' . $column;
                $placeholders[] = $placeholder;
                $params[$placeholder] = $payload[$column];
            }
            $stmt = $pdo->prepare('INSERT INTO ' . dps_quote($table)
                . ' (' . implode(', ', array_map('dps_quote', $names)) . ') VALUES (' . implode(', ', $placeholders) . ')');
            $stmt->execute($params);
        }
        $changed++;
    }

    if ($validRows === 0) {
        return 0;
    }

    $locationWhere = $location === null ? '' : ' WHERE cod_locatie = ' . (int)$location;
    $existing = $pdo->query('SELECT id, id_categorie, cod_locatie FROM ' . dps_quote($table)
        . $locationWhere)->fetchAll(PDO::FETCH_ASSOC);
    $delete = $pdo->prepare('DELETE FROM ' . dps_quote($table) . ' WHERE id = :id');
    foreach ($existing as $row) {
        $key = (int)$row['id_categorie'] . ':' . (int)$row['cod_locatie'];
        if (!isset($incoming[$key])) {
            $delete->execute([':id' => $row['id']]);
        }
    }
    return $changed;
}

function dps_sync_product_locations(PDO $pdo, array $rows, ?int $location = null): int
{
    $table = 'produse_servicii_locatii';
    $columns = dps_columns($pdo, $table);
    if (!in_array('cod_produs', $columns, true) || !in_array('cod_locatie', $columns, true)) {
        return 0;
    }

    if (!$rows) {
        return 0;
    }

    $incoming = [];
    $changed = 0;
    $validRows = 0;
    foreach ($rows as $row) {
        $product = (int)($row['cod_produs'] ?? 0);
        $rowLocation = (int)($row['cod_locatie'] ?? 0);
        if ($product <= 0 || $rowLocation <= 0 || ($location !== null && $rowLocation !== $location)) {
            continue;
        }
        $validRows++;
        $incoming[$product . ':' . $rowLocation] = true;
        $active = !empty($row['activ']) ? 1 : 0;
        $stmt = $pdo->prepare('INSERT INTO ' . dps_quote($table)
            . ' (cod_produs, cod_locatie, activ, updated_at) VALUES (:product, :location, :active, CURRENT_TIMESTAMP)'
            . ' ON CONFLICT(cod_produs, cod_locatie) DO UPDATE SET activ = excluded.activ, updated_at = CURRENT_TIMESTAMP');
        $stmt->execute([
            ':product' => $product,
            ':location' => $rowLocation,
            ':active' => $active,
        ]);
        $changed++;
    }

    if ($validRows === 0) {
        return 0;
    }

    $locationWhere = $location === null ? '' : ' WHERE cod_locatie = ' . (int)$location;
    $existing = $pdo->query('SELECT cod_produs, cod_locatie FROM ' . dps_quote($table) . $locationWhere)
        ->fetchAll(PDO::FETCH_ASSOC);
    $delete = $pdo->prepare('DELETE FROM ' . dps_quote($table)
        . ' WHERE cod_locatie = :location AND cod_produs = :product');
    foreach ($existing as $product) {
        $key = (int)$product['cod_produs'] . ':' . (int)$product['cod_locatie'];
        if (!isset($incoming[$key])) {
            $delete->execute([
                ':location' => (int)$product['cod_locatie'],
                ':product' => (int)$product['cod_produs'],
            ]);
        }
    }
    return $changed;
}

function dps_deactivate_missing_products(PDO $pdo, array $rows): int
{
    $remoteProducts = [];
    foreach ($rows as $row) {
        $product = (int)($row['cod_produs'] ?? 0);
        if ($product > 0) {
            $remoteProducts[$product] = true;
        }
    }
    if (!$remoteProducts) {
        return 0;
    }

    $localProducts = $pdo->query('SELECT cod_produs FROM produse_servicii WHERE cod_produs > 0')
        ->fetchAll(PDO::FETCH_COLUMN);
    $deactivate = $pdo->prepare('UPDATE produse_servicii SET activ = 0 WHERE cod_produs = :product');
    $deactivateLocations = $pdo->prepare('UPDATE produse_servicii_locatii SET activ = 0, updated_at = CURRENT_TIMESTAMP
        WHERE cod_produs = :product');
    $count = 0;
    foreach ($localProducts as $product) {
        $product = (int)$product;
        if (!isset($remoteProducts[$product])) {
            $deactivate->execute([':product' => $product]);
            $deactivateLocations->execute([':product' => $product]);
            $count++;
        }
    }
    return $count;
}

function dps_preview_value($value, string $column): string
{
    if ($value === null) {
        return '__NULL__';
    }

    $integerColumns = [
        'cod_produs', 'id_categorie', 'id_gestiune', 'activ', 'produs_activ_site',
        'woo_product_id', 'woo_category_id', 'se_vinde', 'dep_casa_marcat',
        'fel_mancare', 'ask_obs', 'consumabil_de_personal', 'sgr', 'sgr_pet',
        'sgr_alumin', 'sgr_sticla', 'categorie_activa_site', 'id', 'cod_locatie',
        'dep_casa',
    ];
    $numericColumns = [
        'pret_cu_tva', 'pret_achizitie', 'pret_site', 'cota_tva', 'stoc_critic',
        'infopret_kg',
    ];

    if (in_array($column, $integerColumns, true)) {
        return (string)(int)$value;
    }
    if (in_array($column, $numericColumns, true)) {
        $normalized = number_format((float)$value, 6, '.', '');
        return rtrim(rtrim($normalized, '0'), '.');
    }

    return (string)$value;
}

function dps_preview_row_changed(array $local, array $remote, array $columns, string $primaryKey): bool
{
    foreach ($columns as $column) {
        if ($column === $primaryKey || $column === 'id' || !array_key_exists($column, $remote)) {
            continue;
        }
        if (dps_preview_value($local[$column] ?? null, $column) !== dps_preview_value($remote[$column], $column)) {
            return true;
        }
    }

    return false;
}

function dps_preview_label(array $row, string $primaryKey, string $nameColumn = ''): string
{
    $name = $nameColumn !== '' ? trim((string)($row[$nameColumn] ?? '')) : '';
    $key = (string)($row[$primaryKey] ?? '');
    if ($name === '') {
        return $primaryKey . ' ' . $key;
    }

    return $name . ' (' . $primaryKey . ' ' . $key . ')';
}

function dps_preview_table(PDO $pdo, string $table, string $primaryKey, string $nameColumn, array $remoteRows): array
{
    $columns = dps_columns($pdo, $table);
    $empty = [
        'online' => 0,
        'local' => 0,
        'new' => 0,
        'updated' => 0,
        'unchanged' => 0,
        'new_items' => [],
        'updated_items' => [],
    ];
    if (!in_array($primaryKey, $columns, true)) {
        return $empty;
    }

    $localRows = $pdo->query('SELECT * FROM ' . dps_quote($table))->fetchAll(PDO::FETCH_ASSOC);
    $localByKey = [];
    foreach ($localRows as $row) {
        $key = dps_preview_value($row[$primaryKey] ?? null, $primaryKey);
        if ($key !== '__NULL__') {
            $localByKey[$key] = $row;
        }
    }

    $remoteByKey = [];
    foreach ($remoteRows as $row) {
        if (!is_array($row) || !array_key_exists($primaryKey, $row)) {
            continue;
        }
        $key = dps_preview_value($row[$primaryKey], $primaryKey);
        if ($key !== '__NULL__') {
            $remoteByKey[$key] = $row;
        }
    }

    $previewLimit = 100;
    $result = $empty;
    $result['online'] = count($remoteByKey);
    $result['local'] = count($localByKey);
    foreach ($remoteByKey as $key => $remote) {
        if (!isset($localByKey[$key])) {
            $result['new']++;
            if (count($result['new_items']) < $previewLimit) {
                $result['new_items'][] = [
                    'key' => $key,
                    'label' => dps_preview_label($remote, $primaryKey, $nameColumn),
                ];
            }
            continue;
        }
        if (dps_preview_row_changed($localByKey[$key], $remote, $columns, $primaryKey)) {
            $result['updated']++;
            if (count($result['updated_items']) < $previewLimit) {
                $result['updated_items'][] = [
                    'key' => $key,
                    'label' => dps_preview_label($remote, $primaryKey, $nameColumn),
                ];
            }
            continue;
        }
        $result['unchanged']++;
    }

    return $result;
}

function dps_preview_product_locations_by_product(array $remoteRows): array
{
    $result = [];
    foreach ($remoteRows as $row) {
        $product = (int)($row['cod_produs'] ?? 0);
        $location = (int)($row['cod_locatie'] ?? 0);
        if ($product <= 0 || $location <= 0) {
            continue;
        }
        if (!isset($result[$product])) {
            $result[$product] = [];
        }
        $result[$product][$location] = [
            'location' => $location,
            'active' => !empty($row['activ']) ? 1 : 0,
        ];
    }
    foreach ($result as $product => $locations) {
        ksort($locations, SORT_NUMERIC);
        $result[$product] = array_values($locations);
    }

    return $result;
}

function dps_preview_products(PDO $pdo, array $remoteRows, array $remoteProductLocations = []): array
{
    $result = dps_preview_table($pdo, 'produse_servicii', 'cod_produs', 'nume', $remoteRows);
    $locationsByProduct = dps_preview_product_locations_by_product($remoteProductLocations);
    foreach ($result['new_items'] as &$item) {
        $item['locations'] = $locationsByProduct[(int)($item['key'] ?? 0)] ?? [];
    }
    unset($item);
    $columns = dps_columns($pdo, 'produse_servicii');
    if (!in_array('cod_produs', $columns, true)) {
        $result['deactivate'] = 0;
        $result['already_inactive'] = 0;
        $result['deactivate_items'] = [];
        return $result;
    }

    $remoteKeys = [];
    foreach ($remoteRows as $row) {
        if (is_array($row) && array_key_exists('cod_produs', $row)) {
            $remoteKeys[dps_preview_value($row['cod_produs'], 'cod_produs')] = true;
        }
    }

    $localRows = $pdo->query('SELECT * FROM ' . dps_quote('produse_servicii'))->fetchAll(PDO::FETCH_ASSOC);
    $result['deactivate'] = 0;
    $result['already_inactive'] = 0;
    $result['deactivate_items'] = [];
    foreach ($localRows as $row) {
        $key = dps_preview_value($row['cod_produs'] ?? null, 'cod_produs');
        if ($key === '__NULL__' || isset($remoteKeys[$key])) {
            continue;
        }
        $isActive = !array_key_exists('activ', $row) || (int)$row['activ'] !== 0;
        if ($isActive) {
            $result['deactivate']++;
            if (count($result['deactivate_items']) < 100) {
                $result['deactivate_items'][] = [
                    'key' => $key,
                    'label' => dps_preview_label($row, 'cod_produs', 'nume'),
                ];
            }
        } else {
            $result['already_inactive']++;
        }
    }

    return $result;
}

function dps_preview_category_locations(PDO $pdo, array $remoteRows, ?int $location, bool $enabled): array
{
    $result = [
        'enabled' => $enabled,
        'scope' => $location === null ? 'toate locatiile' : 'locatia ' . $location,
        'online' => 0,
        'local' => 0,
        'new' => 0,
        'updated' => 0,
        'unchanged' => 0,
        'removed' => 0,
        'new_items' => [],
        'removed_items' => [],
    ];
    if (!$enabled) {
        return $result;
    }

    $incoming = [];
    foreach ($remoteRows as $row) {
        $category = (int)($row['id_categorie'] ?? 0);
        $rowLocation = (int)($row['cod_locatie'] ?? 0);
        if ($category <= 0 || $rowLocation <= 0 || ($location !== null && $rowLocation !== $location)) {
            continue;
        }
        $incoming[$category . ':' . $rowLocation] = [
            'category' => $category,
            'location' => $rowLocation,
        ];
    }

    $where = $location === null ? '' : ' WHERE cod_locatie = ' . (int)$location;
    $localRows = $pdo->query('SELECT id_categorie, cod_locatie FROM ' . dps_quote('categorii_locatii') . $where)
        ->fetchAll(PDO::FETCH_ASSOC);
    $local = [];
    foreach ($localRows as $row) {
        $key = (int)$row['id_categorie'] . ':' . (int)$row['cod_locatie'];
        $local[$key] = $row;
    }

    $result['online'] = count($incoming);
    $result['local'] = count($local);
    if (!$incoming) {
        return $result;
    }

    foreach ($incoming as $key => $item) {
        if (isset($local[$key])) {
            $result['unchanged']++;
            continue;
        }
        $result['new']++;
        if (count($result['new_items']) < 100) {
            $result['new_items'][] = ['key' => $key, 'label' => 'Categoria ' . $item['category'] . ', locatia ' . $item['location']];
        }
    }
    foreach ($local as $key => $row) {
        if (isset($incoming[$key])) {
            continue;
        }
        $result['removed']++;
        if (count($result['removed_items']) < 100) {
            $result['removed_items'][] = ['key' => $key, 'label' => 'Categoria ' . $row['id_categorie'] . ', locatia ' . $row['cod_locatie']];
        }
    }

    return $result;
}

function dps_preview_record_location(array &$buckets, int $location, int $active, ?int $previousActive = null): void
{
    if (!isset($buckets[$location])) {
        $buckets[$location] = [
            'location' => $location,
            'count' => 0,
            'active' => 0,
            'inactive' => 0,
            'became_active' => 0,
            'became_inactive' => 0,
        ];
    }
    $buckets[$location]['count']++;
    if ($active === 1) {
        $buckets[$location]['active']++;
    } else {
        $buckets[$location]['inactive']++;
    }
    if ($previousActive !== null && $previousActive !== $active) {
        if ($active === 1) {
            $buckets[$location]['became_active']++;
        } else {
            $buckets[$location]['became_inactive']++;
        }
    }
}

function dps_preview_sorted_location_buckets(array $buckets): array
{
    ksort($buckets, SORT_NUMERIC);
    return array_values($buckets);
}

function dps_preview_product_locations(PDO $pdo, array $remoteRows, ?int $location, bool $enabled): array
{
    $result = [
        'enabled' => $enabled,
        'scope' => $location === null ? 'toate locatiile' : 'locatia ' . $location,
        'online' => 0,
        'local' => 0,
        'new' => 0,
        'updated' => 0,
        'unchanged' => 0,
        'removed' => 0,
        'new_items' => [],
        'updated_items' => [],
        'removed_items' => [],
        'by_location' => [
            'new' => [],
            'updated' => [],
            'removed' => [],
        ],
    ];
    if (!$enabled) {
        return $result;
    }

    $incoming = [];
    foreach ($remoteRows as $row) {
        $product = (int)($row['cod_produs'] ?? 0);
        $rowLocation = (int)($row['cod_locatie'] ?? 0);
        if ($product <= 0 || $rowLocation <= 0 || ($location !== null && $rowLocation !== $location)) {
            continue;
        }
        $key = $product . ':' . $rowLocation;
        $incoming[$key] = [
            'product' => $product,
            'location' => $rowLocation,
            'active' => !empty($row['activ']) ? 1 : 0,
        ];
    }

    $where = $location === null ? '' : ' WHERE cod_locatie = ' . (int)$location;
    $localRows = $pdo->query('SELECT cod_produs, cod_locatie, activ FROM ' . dps_quote('produse_servicii_locatii') . $where)
        ->fetchAll(PDO::FETCH_ASSOC);
    $local = [];
    foreach ($localRows as $row) {
        $key = (int)$row['cod_produs'] . ':' . (int)$row['cod_locatie'];
        $local[$key] = [
            'product' => (int)$row['cod_produs'],
            'location' => (int)$row['cod_locatie'],
            'active' => !empty($row['activ']) ? 1 : 0,
        ];
    }

    $result['online'] = count($incoming);
    $result['local'] = count($local);
    if (!$incoming) {
        return $result;
    }

    foreach ($incoming as $key => $item) {
        if (!isset($local[$key])) {
            $result['new']++;
            dps_preview_record_location($result['by_location']['new'], $item['location'], $item['active']);
            if (count($result['new_items']) < 100) {
                $result['new_items'][] = [
                    'key' => $key,
                    'label' => 'Produs ' . $item['product'] . ', locatia ' . $item['location'] . ', ' . ($item['active'] === 1 ? 'activ' : 'inactiv'),
                ];
            }
            continue;
        }
        if ($local[$key]['active'] !== $item['active']) {
            $result['updated']++;
            dps_preview_record_location(
                $result['by_location']['updated'],
                $item['location'],
                $item['active'],
                $local[$key]['active']
            );
            if (count($result['updated_items']) < 100) {
                $result['updated_items'][] = [
                    'key' => $key,
                    'label' => 'Produs ' . $item['product'] . ', locatia ' . $item['location'] . ', disponibilitate '
                        . ($local[$key]['active'] === 1 ? 'activ -> inactiv' : 'inactiv -> activ'),
                ];
            }
            continue;
        }
        $result['unchanged']++;
    }
    foreach ($local as $key => $row) {
        if (isset($incoming[$key])) {
            continue;
        }
        $result['removed']++;
        dps_preview_record_location($result['by_location']['removed'], $row['location'], $row['active']);
        if (count($result['removed_items']) < 100) {
            $result['removed_items'][] = [
                'key' => $key,
                'label' => 'Produs ' . $row['product'] . ', locatia ' . $row['location'] . ', '
                    . ($row['active'] === 1 ? 'activ in lista locala' : 'inactiv in lista locala'),
            ];
        }
    }

    $result['by_location'] = [
        'new' => dps_preview_sorted_location_buckets($result['by_location']['new']),
        'updated' => dps_preview_sorted_location_buckets($result['by_location']['updated']),
        'removed' => dps_preview_sorted_location_buckets($result['by_location']['removed']),
    ];

    return $result;
}

function dps_preview_build(PDO $pdo, array $response, array $products, array $categories, array $categoryLocations, array $productLocations, array $gestiuni, array $vatRates, int $location): array
{
    $allLocationsIncluded = ($response['include_all_locations'] ?? false) === true;
    $mappingScope = $allLocationsIncluded ? null : $location;
    $remoteProductCount = (int)($response['products_count'] ?? 0);
    $productsComplete = $remoteProductCount > 0 && count($products) === $remoteProductCount;
    $productLocationsAvailable = ($response['product_locations_available'] ?? false) === true;
    $categoryColumns = dps_columns($pdo, 'categorii');
    $categoryLocationsEnabled = in_array('id_categorie', $categoryColumns, true)
        && in_array('cod_locatie', dps_columns($pdo, 'categorii_locatii'), true);

    return [
        'scope' => [
            'client_id' => 2,
            'location' => $location,
            'all_locations_included' => $allLocationsIncluded,
            'products_complete' => $productsComplete,
            'product_locations_available' => $productLocationsAvailable,
            'mapping_scope' => $mappingScope === null ? 'toate locatiile' : 'locatia ' . $mappingScope,
        ],
        'products' => dps_preview_products($pdo, $products, $productLocations),
        'categories' => dps_preview_table($pdo, 'categorii', 'id_categorie', 'den_categ', $categories),
        'gestiuni' => dps_preview_table($pdo, 'gestiuni', 'id_gestiune', 'denumire_gestiune', $gestiuni),
        'vat_rates' => dps_preview_table($pdo, 'cote_tva', 'id', 'cota', $vatRates),
        'category_locations' => dps_preview_category_locations($pdo, $categoryLocations, $mappingScope, $categoryLocationsEnabled),
        'product_locations' => dps_preview_product_locations($pdo, $productLocations, $mappingScope, $productLocationsAvailable),
    ];
}

try {
    dps_ensure_sync_state($pdo);
    $config = offline_config_all();
    $syncConfig = isset($config['online_products_sync']) && is_array($config['online_products_sync'])
        ? $config['online_products_sync'] : [];
    $apiUrl = trim((string)($syncConfig['api_url'] ?? ''));
    $apiKey = trim((string)($syncConfig['api_key'] ?? $config['sync_api_key'] ?? $config['upload_key'] ?? ''));
    $clientId = (int)($syncConfig['client_id'] ?? $config['sync_client_id'] ?? $config['client_id'] ?? 0);
    $location = (int)($syncConfig['cod_locatie'] ?? $config['cod_locatie_default'] ?? 0);
    $timeout = max(5, (int)($syncConfig['timeout_seconds'] ?? 12));
    $verifySsl = !array_key_exists('verify_ssl', $syncConfig) || (bool)$syncConfig['verify_ssl'];
    $caBundle = trim((string)($syncConfig['ca_bundle_path'] ?? $config['ca_bundle_path'] ?? ''));
    $force = (string)($_GET['force'] ?? $_POST['force'] ?? '') === '1';
    $preview = (string)($_GET['preview'] ?? $_POST['preview'] ?? '') === '1';
    $checkOnly = (string)($_GET['check_only'] ?? $_POST['check_only'] ?? '') === '1';
    $requestTimeout = $checkOnly ? min($timeout, 5) : $timeout;

    if ($apiUrl === '' || $apiKey === '' || $clientId !== 2 || $location !== 2) {
        dps_json(['status' => 'disabled']);
    }

    $state = dps_state($pdo);
    $remoteHash = trim((string)($state['remote_hash'] ?? ''));
    $baseQuery = [
        'api_key' => $apiKey,
        'cod_client' => $clientId,
        'cod_locatie' => $location,
        'include_all_locations' => 1,
    ];

    if ($checkOnly) {
        $hashQuery = $baseQuery + ['hash_only' => 1];
        if ($remoteHash !== '') {
            $hashQuery['local_hash'] = $remoteHash;
        }
        $hashResponse = dps_http_get($apiUrl, $apiKey, $hashQuery, $requestTimeout, $verifySsl, $caBundle);
        $onlineHash = trim((string)($hashResponse['products_hash'] ?? ''));
        $needsInitialSync = $remoteHash === '';
        $changed = $needsInitialSync || (($hashResponse['changed'] ?? null) !== false);
        dps_state_update($pdo, [
            'last_check_at' => date('Y-m-d H:i:s'),
            'last_status' => $changed ? 'changed' : 'unchanged',
            'last_error' => null,
        ]);
        dps_json([
            'status' => $changed ? 'changed' : 'unchanged',
            'changed' => $changed,
            'needs_initial_sync' => $needsInitialSync,
            'products_hash' => $onlineHash,
            'products_count' => (int)($hashResponse['products_count'] ?? 0),
            'local_products_count' => (int)($state['products_count'] ?? 0),
        ]);
    }

    if (!$preview && !$force && $remoteHash !== '') {
        $hashResponse = dps_http_get($apiUrl, $apiKey, $baseQuery + [
            'hash_only' => 1,
            'local_hash' => $remoteHash,
        ], $requestTimeout, $verifySsl, $caBundle);
        dps_state_update($pdo, [
            'last_check_at' => date('Y-m-d H:i:s'),
            'last_status' => 'checked',
            'last_error' => null,
        ]);
        if (($hashResponse['changed'] ?? null) === false) {
            dps_json([
                'status' => 'unchanged',
                'products_hash' => $remoteHash,
                'products_count' => (int)($state['products_count'] ?? 0),
            ]);
        }
    }

    $requestQuery = $baseQuery;
    if (!$preview && !$force) {
        $requestQuery['local_hash'] = $remoteHash;
    }
    $response = dps_http_get($apiUrl, $apiKey, $requestQuery, $timeout, $verifySsl, $caBundle);
    $products = is_array($response['data'] ?? null) ? $response['data'] : [];
    $categories = is_array($response['categorii'] ?? null) ? $response['categorii'] : [];
    $categoryLocations = is_array($response['categorii_locatii'] ?? null) ? $response['categorii_locatii'] : [];
    $productLocations = is_array($response['produse_servicii_locatii'] ?? null) ? $response['produse_servicii_locatii'] : [];
    $gestiuni = is_array($response['gestiuni'] ?? null) ? $response['gestiuni'] : [];
    $vatRates = is_array($response['cote_tva'] ?? null) ? $response['cote_tva'] : [];
    $productLocationsAvailable = ($response['product_locations_available'] ?? false) === true;

    if ($preview) {
        dps_json([
            'status' => 'preview',
            'products_hash' => trim((string)($response['products_hash'] ?? '')),
            'products_count' => count($products),
            'product_locations_available' => $productLocationsAvailable,
            'preview' => dps_preview_build(
                $pdo,
                $response,
                $products,
                $categories,
                $categoryLocations,
                $productLocations,
                $gestiuni,
                $vatRates,
                $location
            ),
        ]);
    }

    $pdo->beginTransaction();
    $updatedProducts = dps_upsert_rows($pdo, 'produse_servicii', 'cod_produs', $products);
    $deactivatedMissingProducts = 0;
    $remoteProductCount = (int)($response['products_count'] ?? 0);
    if ($products && $remoteProductCount > 0 && count($products) === $remoteProductCount) {
        $deactivatedMissingProducts = dps_deactivate_missing_products($pdo, $products);
    }
    $updatedCategories = dps_upsert_rows($pdo, 'categorii', 'id_categorie', $categories);
    $updatedGestiuni = dps_upsert_rows($pdo, 'gestiuni', 'id_gestiune', $gestiuni);
    $updatedVatRates = dps_upsert_rows($pdo, 'cote_tva', 'id', $vatRates);
    $allLocationsIncluded = ($response['include_all_locations'] ?? false) === true;
    $mappingScope = $allLocationsIncluded ? null : $location;
    $updatedCategoryLocations = dps_sync_category_locations($pdo, $categoryLocations, $mappingScope);
    $updatedProductLocations = $productLocationsAvailable
        ? dps_sync_product_locations($pdo, $productLocations, $mappingScope)
        : 0;
    $pdo->commit();

    // Catalogul local s-a schimbat. Golim doar cache-ul instalarii curente,
    // astfel incat urmatoarea scanare sa reconstruiasca datele produsului cu
    // pretul, cota TVA si disponibilitatea actualizate.
    invalidate_prodlists_for_client_location($clientId, $location);
    invalidate_barcodes_for_client_location($clientId, $location);

    $newHash = trim((string)($response['products_hash'] ?? ''));
    dps_state_update($pdo, [
        'remote_hash' => $newHash,
        'last_check_at' => date('Y-m-d H:i:s'),
        'last_sync_at' => date('Y-m-d H:i:s'),
        'last_status' => 'synced',
        'last_error' => null,
        'products_count' => count($products),
    ]);
    dps_json([
        'status' => 'synced',
        'products_hash' => $newHash,
        'products_count' => count($products),
        'product_locations_available' => $productLocationsAvailable,
        'updated' => [
            'products' => $updatedProducts,
            'deactivated_missing_products' => $deactivatedMissingProducts,
            'categories' => $updatedCategories,
            'category_locations' => $updatedCategoryLocations,
            'product_locations' => $updatedProductLocations,
            'gestiuni' => $updatedGestiuni,
            'vat_rates' => $updatedVatRates,
        ],
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (isset($pdo) && $pdo instanceof PDO) {
        dps_state_update($pdo, [
            'last_check_at' => date('Y-m-d H:i:s'),
            'last_status' => 'error',
            'last_error' => substr($e->getMessage(), 0, 500),
        ]);
    }
    error_log('Daily Coffee product sync: ' . $e->getMessage());
    dps_json(['status' => 'error', 'message' => 'Sincronizarea catalogului nu a reusit.'], 502);
}
