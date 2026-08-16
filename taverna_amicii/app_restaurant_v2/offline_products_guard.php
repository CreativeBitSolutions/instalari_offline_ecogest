<?php
declare(strict_types=1);

function opg_bool($value, bool $default = false): bool
{
    if ($value === null || $value === '') {
        return $default;
    }
    if (is_bool($value)) {
        return $value;
    }

    $normalized = strtolower((string)$value);
    if (in_array($normalized, ['1', 'true', 'yes', 'da', 'on'], true)) {
        return true;
    }
    if (in_array($normalized, ['0', 'false', 'no', 'nu', 'off'], true)) {
        return false;
    }

    return $default;
}

function opg_json_flags(): int
{
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }

    return $flags;
}

function opg_quote_identifier(string $identifier): string
{
    return '"' . str_replace('"', '""', $identifier) . '"';
}

function opg_sync_table_columns(string $table): array
{
    $columns = [
        'produse_servicii' => [
            'cod_produs',
            'cod_bare',
            'nume',
            'nume_site',
            'nume_en',
            'descriere',
            'descriere_site',
            'descriere_en',
            'um',
            'pret_cu_tva',
            'pret_achizitie',
            'pret_site',
            'cota_tva',
            'id_categorie',
            'id_gestiune',
            'activ',
            'produs_activ_site',
            'stoc_status_site',
            'woo_product_id',
            'se_vinde',
            'departament',
            'dep_casa_marcat',
            'tip',
            'fel_mancare',
            'ask_obs',
            'imagine',
            'imagine_site',
            'stoc_critic',
            'nc8',
            'infopret_kg',
            'consumabil_de_personal',
            'sgr',
            'sgr_pet',
            'sgr_alumin',
            'sgr_sticla',
        ],
        'categorii' => [
            'id_categorie',
            'den_categ',
            'se_vinde',
        ],
        'categorii_locatii' => [
            'id',
            'id_categorie',
            'cod_locatie',
        ],
        'gestiuni' => [
            'id_gestiune',
            'denumire_gestiune',
        ],
        'cote_tva' => [
            'cota',
            'dep_casa',
        ],
    ];

    return $columns[$table] ?? [];
}

function opg_hash_column_type(string $column): string
{
    static $integerColumns = [
        'cod_produs',
        'id_categorie',
        'id_gestiune',
        'activ',
        'produs_activ_site',
        'woo_product_id',
        'se_vinde',
        'dep_casa_marcat',
        'fel_mancare',
        'ask_obs',
        'consumabil_de_personal',
        'sgr',
        'sgr_pet',
        'sgr_alumin',
        'sgr_sticla',
        'id',
        'cod_locatie',
        'dep_casa',
    ];
    static $numericColumns = [
        'pret_cu_tva',
        'pret_achizitie',
        'pret_site',
        'cota_tva',
        'cota',
        'stoc_critic',
        'infopret_kg',
    ];

    if (in_array($column, $integerColumns, true)) {
        return 'int';
    }
    if (in_array($column, $numericColumns, true)) {
        return 'float';
    }

    return 'text';
}

function opg_hash_value($value, string $column)
{
    if ($value === null) {
        return null;
    }

    $type = opg_hash_column_type($column);
    if ($type === 'int') {
        return $value === '' ? 0 : (int)$value;
    }
    if ($type === 'float') {
        return $value === '' ? 0.0 : round((float)$value, 6);
    }

    return (string)$value;
}

function opg_table_columns(PDO $pdo, string $table): array
{
    $stmt = $pdo->query('PRAGMA table_info(' . opg_quote_identifier($table) . ')');
    $columns = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $columns[(string)$row['name']] = true;
    }

    return $columns;
}

function opg_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = :table_name");
    $stmt->execute([':table_name' => $table]);

    return ((int)$stmt->fetchColumn()) > 0;
}

function opg_fetch_table(PDO $pdo, string $table, string $orderColumn): array
{
    if (!opg_table_exists($pdo, $table)) {
        return [];
    }

    $existingColumns = opg_table_columns($pdo, $table);
    $columns = array_values(array_filter(
        opg_sync_table_columns($table),
        static fn(string $column): bool => isset($existingColumns[$column])
    ));
    if (!$columns) {
        return [];
    }

    $sql = 'SELECT ' . implode(', ', array_map('opg_quote_identifier', $columns)) . ' FROM ' . opg_quote_identifier($table);
    if (isset($existingColumns[$orderColumn])) {
        $sql .= ' ORDER BY ' . opg_quote_identifier($orderColumn);
    }

    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function opg_normalize_for_hash(array $payload): array
{
    foreach ($payload as $key => $rows) {
        if (!is_array($rows)) {
            continue;
        }

        $normalizedRows = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            foreach ($row as $column => $value) {
                $row[$column] = opg_hash_value($value, (string)$column);
            }
            ksort($row);
            $normalizedRows[] = $row;
        }
        $payload[$key] = $normalizedRows;
        usort($payload[$key], static function (array $left, array $right): int {
            return strcmp(
                (string)json_encode($left, opg_json_flags() & ~JSON_PRETTY_PRINT),
                (string)json_encode($right, opg_json_flags() & ~JSON_PRETTY_PRINT)
            );
        });
    }
    ksort($payload);

    return $payload;
}

function opg_payload_hash(array $payload): string
{
    $json = json_encode(opg_normalize_for_hash($payload), opg_json_flags() & ~JSON_PRETTY_PRINT);
    if ($json === false) {
        throw new RuntimeException('Hash-ul local nu a putut fi calculat.');
    }

    return hash('sha256', $json);
}

function opg_filter_rows_for_hash(string $table, array $rows): array
{
    $allowed = array_flip(opg_sync_table_columns($table));
    if (!$allowed) {
        return $rows;
    }

    $filtered = [];
    foreach ($rows as $row) {
        if (is_array($row)) {
            $filtered[] = array_intersect_key($row, $allowed);
        }
    }

    return $filtered;
}

function opg_present_columns(array $rows): array
{
    $columns = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        foreach (array_keys($row) as $column) {
            $columns[$column] = true;
        }
    }

    return $columns;
}

function opg_filter_rows_to_present_columns(array $rows, array $presentColumns): array
{
    if (!$presentColumns) {
        return [];
    }

    $filtered = [];
    foreach ($rows as $row) {
        if (is_array($row)) {
            $filtered[] = array_intersect_key($row, $presentColumns);
        }
    }

    return $filtered;
}

function opg_filter_cote_tva_for_products(array $coteTvaRows, array $products): array
{
    $used = [];
    foreach ($products as $product) {
        if (is_array($product) && array_key_exists('cota_tva', $product)) {
            $key = opg_lookup_key($product['cota_tva'], 'REAL');
            if ($key !== '') {
                $used[$key] = true;
            }
        }
    }

    return array_values(array_filter(
        $coteTvaRows,
        static fn(array $row): bool => isset($used[opg_lookup_key($row['cota'] ?? null, 'REAL')])
    ));
}

function opg_online_compatible_hash(array $online, array $products): string
{
    $onlineCoteTva = opg_filter_rows_for_hash('cote_tva', isset($online['cote_tva']) && is_array($online['cote_tva']) ? $online['cote_tva'] : []);

    return opg_payload_hash([
        'produse_servicii' => opg_filter_rows_for_hash('produse_servicii', $products),
        'categorii' => opg_filter_rows_for_hash('categorii', isset($online['categorii']) && is_array($online['categorii']) ? $online['categorii'] : []),
        'categorii_locatii' => opg_filter_rows_for_hash('categorii_locatii', isset($online['categorii_locatii']) && is_array($online['categorii_locatii']) ? $online['categorii_locatii'] : []),
        'gestiuni' => opg_filter_rows_for_hash('gestiuni', isset($online['gestiuni']) && is_array($online['gestiuni']) ? $online['gestiuni'] : []),
        'cote_tva' => opg_filter_cote_tva_for_products($onlineCoteTva, $products),
    ]);
}

function opg_local_rows_for_online(PDO $pdo, string $table, string $orderColumn, array $onlineRows): array
{
    $presentColumns = opg_present_columns($onlineRows);
    if (!$presentColumns) {
        return [];
    }

    $onlineKeys = [];
    foreach ($onlineRows as $row) {
        if (is_array($row) && array_key_exists($orderColumn, $row)) {
            $key = opg_lookup_key($row[$orderColumn], opg_hash_column_type($orderColumn));
            if ($key !== '') {
                $onlineKeys[$key] = true;
            }
        }
    }
    if (!$onlineKeys) {
        return [];
    }

    $localRows = [];
    foreach (opg_fetch_table($pdo, $table, $orderColumn) as $row) {
        if (!is_array($row) || !array_key_exists($orderColumn, $row)) {
            continue;
        }
        $key = opg_lookup_key($row[$orderColumn], opg_hash_column_type($orderColumn));
        if (isset($onlineKeys[$key])) {
            $localRows[] = $row;
        }
    }

    return opg_filter_rows_to_present_columns($localRows, $presentColumns);
}

function opg_local_cote_tva_for_hash(PDO $pdo, array $products = []): array
{
    if (!$products) {
        $products = opg_fetch_table($pdo, 'produse_servicii', 'cod_produs');
    }
    $coteTva = opg_fetch_table($pdo, 'cote_tva', 'cota');

    return opg_filter_cote_tva_for_products($coteTva, $products);
}

function opg_local_compatible_hash(PDO $pdo, array $online, array $products): string
{
    $onlineProducts = opg_filter_rows_for_hash('produse_servicii', $products);
    $onlineCategorii = opg_filter_rows_for_hash('categorii', isset($online['categorii']) && is_array($online['categorii']) ? $online['categorii'] : []);
    $onlineCategoriiLocatii = opg_filter_rows_for_hash('categorii_locatii', isset($online['categorii_locatii']) && is_array($online['categorii_locatii']) ? $online['categorii_locatii'] : []);
    $onlineGestiuni = opg_filter_rows_for_hash('gestiuni', isset($online['gestiuni']) && is_array($online['gestiuni']) ? $online['gestiuni'] : []);
    $onlineCoteTva = opg_filter_cote_tva_for_products(
        opg_filter_rows_for_hash('cote_tva', isset($online['cote_tva']) && is_array($online['cote_tva']) ? $online['cote_tva'] : []),
        $products
    );

    return opg_payload_hash([
        'produse_servicii' => opg_local_rows_for_online($pdo, 'produse_servicii', 'cod_produs', $onlineProducts),
        'categorii' => opg_local_rows_for_online($pdo, 'categorii', 'id_categorie', $onlineCategorii),
        'categorii_locatii' => opg_local_rows_for_online($pdo, 'categorii_locatii', 'id', $onlineCategoriiLocatii),
        'gestiuni' => opg_local_rows_for_online($pdo, 'gestiuni', 'id_gestiune', $onlineGestiuni),
        'cote_tva' => opg_filter_rows_to_present_columns(opg_local_cote_tva_for_hash($pdo, $products), opg_present_columns($onlineCoteTva)),
    ]);
}

function opg_local_hash(PDO $pdo): string
{
    return opg_payload_hash([
        'produse_servicii' => opg_fetch_table($pdo, 'produse_servicii', 'cod_produs'),
        'categorii' => opg_fetch_table($pdo, 'categorii', 'id_categorie'),
        'categorii_locatii' => opg_fetch_table($pdo, 'categorii_locatii', 'id'),
        'gestiuni' => opg_fetch_table($pdo, 'gestiuni', 'id_gestiune'),
        'cote_tva' => opg_local_cote_tva_for_hash($pdo),
    ]);
}

function opg_append_query(string $url, array $params): string
{
    $filtered = [];
    foreach ($params as $key => $value) {
        if ($value !== null && $value !== '') {
            $filtered[$key] = $value;
        }
    }
    if (!$filtered) {
        return $url;
    }

    return $url . (strpos($url, '?') === false ? '?' : '&') . http_build_query($filtered);
}

function opg_fetch_online_hash(array $config, string $localHash): array
{
    $query = [
        'hash_only' => 1,
        'local_hash' => $localHash,
        'cod_client' => $config['cod_client'] > 0 ? $config['cod_client'] : null,
    ];
    if ($config['send_api_key_in_query']) {
        $query['api_key'] = $config['api_key'];
    }

    $url = opg_append_query($config['api_url'], $query);
    $headers = [
        'Accept: application/json',
        'X-Api-Key: ' . $config['api_key'],
    ];

    if (extension_loaded('curl')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => $config['timeout_seconds'],
            CURLOPT_TIMEOUT => $config['timeout_seconds'],
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => $config['verify_ssl'],
            CURLOPT_SSL_VERIFYHOST => $config['verify_ssl'] ? 2 : 0,
        ]);
        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('Endpointul de produse nu a putut fi apelat: ' . $error);
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $config['timeout_seconds'],
                'header' => implode("\r\n", $headers),
            ],
            'ssl' => [
                'verify_peer' => $config['verify_ssl'],
                'verify_peer_name' => $config['verify_ssl'],
            ],
        ]);
        $raw = @file_get_contents($url, false, $context);
        $httpCode = 0;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $line) {
                if (preg_match('/^HTTP\/\S+\s+(\d+)/', (string)$line, $matches)) {
                    $httpCode = (int)$matches[1];
                    break;
                }
            }
        }
        if ($raw === false) {
            throw new RuntimeException('Endpointul de produse nu a putut fi apelat.');
        }
    }

    $raw = preg_replace('/^\xEF\xBB\xBF/', '', (string)$raw);
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Endpointul de produse nu a răspuns cu JSON valid.');
    }
    if ($httpCode >= 400 || ($decoded['status'] ?? '') !== 'success') {
        $message = is_string($decoded['message'] ?? null) ? (string)$decoded['message'] : 'Endpointul de produse a returnat eroare.';
        throw new RuntimeException($message);
    }

    return $decoded;
}

function opg_fetch_online_products_full(array $config, string $localHash): array
{
    $query = [
        'local_hash' => $localHash,
        'cod_client' => $config['cod_client'] > 0 ? $config['cod_client'] : null,
    ];
    if ($config['send_api_key_in_query']) {
        $query['api_key'] = $config['api_key'];
    }

    $url = opg_append_query($config['api_url'], $query);
    $headers = [
        'Accept: application/json',
        'X-Api-Key: ' . $config['api_key'],
    ];

    if (extension_loaded('curl')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => $config['timeout_seconds'],
            CURLOPT_TIMEOUT => $config['timeout_seconds'],
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => $config['verify_ssl'],
            CURLOPT_SSL_VERIFYHOST => $config['verify_ssl'] ? 2 : 0,
        ]);
        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('Endpointul de produse nu a putut fi apelat: ' . $error);
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $config['timeout_seconds'],
                'header' => implode("\r\n", $headers),
            ],
            'ssl' => [
                'verify_peer' => $config['verify_ssl'],
                'verify_peer_name' => $config['verify_ssl'],
            ],
        ]);
        $raw = @file_get_contents($url, false, $context);
        $httpCode = 0;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $line) {
                if (preg_match('/^HTTP\/\S+\s+(\d+)/', (string)$line, $matches)) {
                    $httpCode = (int)$matches[1];
                    break;
                }
            }
        }
        if ($raw === false) {
            throw new RuntimeException('Endpointul de produse nu a putut fi apelat.');
        }
    }

    $raw = preg_replace('/^\xEF\xBB\xBF/', '', (string)$raw);
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Endpointul de produse nu a răspuns cu JSON valid.');
    }
    if ($httpCode >= 400 || ($decoded['status'] ?? '') !== 'success') {
        $message = is_string($decoded['message'] ?? null) ? (string)$decoded['message'] : 'Endpointul de produse a returnat eroare.';
        throw new RuntimeException($message);
    }

    return $decoded;
}

function opg_column_meta(PDO $pdo, string $table): array
{
    $stmt = $pdo->query('PRAGMA table_info(' . opg_quote_identifier($table) . ')');
    $columns = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $columns[(string)$row['name']] = [
            'type' => strtoupper((string)($row['type'] ?? '')),
            'pk' => (int)($row['pk'] ?? 0),
        ];
    }

    return $columns;
}

function opg_value_for_db($value, string $type)
{
    if ($value === null) {
        return null;
    }

    $type = strtoupper($type);
    if (strpos($type, 'INT') !== false) {
        return $value === '' ? 0 : (int)$value;
    }
    if (strpos($type, 'REAL') !== false || strpos($type, 'FLOA') !== false || strpos($type, 'DOUB') !== false || strpos($type, 'NUM') !== false || strpos($type, 'DEC') !== false) {
        return $value === '' ? 0.0 : (float)$value;
    }

    return (string)$value;
}

function opg_values_equal($left, $right, string $type): bool
{
    if ($left === null && $right === null) {
        return true;
    }

    $type = strtoupper($type);
    if (strpos($type, 'INT') !== false) {
        return (int)$left === (int)$right;
    }
    if (strpos($type, 'REAL') !== false || strpos($type, 'FLOA') !== false || strpos($type, 'DOUB') !== false || strpos($type, 'NUM') !== false || strpos($type, 'DEC') !== false) {
        return abs((float)$left - (float)$right) < 0.000001;
    }

    return (string)$left === (string)$right;
}

function opg_lookup_key($value, string $type): string
{
    if ($value === null || $value === '') {
        return '';
    }

    $type = strtoupper($type);
    if (strpos($type, 'INT') !== false) {
        return (string)(int)$value;
    }
    if (strpos($type, 'REAL') !== false || strpos($type, 'FLOA') !== false || strpos($type, 'DOUB') !== false || strpos($type, 'NUM') !== false || strpos($type, 'DEC') !== false) {
        $normalized = rtrim(rtrim(sprintf('%.6F', (float)$value), '0'), '.');
        return $normalized === '-0' ? '0' : $normalized;
    }

    return (string)$value;
}

function opg_fetch_existing_by_pk(PDO $pdo, string $table, string $pkColumn, array $rows, string $pkType = ''): array
{
    $ids = [];
    foreach ($rows as $row) {
        if (is_array($row) && array_key_exists($pkColumn, $row)) {
            $key = opg_lookup_key($row[$pkColumn], $pkType);
            if ($key !== '') {
                $ids[$key] = opg_value_for_db($row[$pkColumn], $pkType);
            }
        }
    }
    if (!$ids) {
        return [];
    }

    $existing = [];
    foreach (array_chunk(array_values($ids), 500) as $chunk) {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $stmt = $pdo->prepare('SELECT * FROM ' . opg_quote_identifier($table) . ' WHERE ' . opg_quote_identifier($pkColumn) . " IN ({$placeholders})");
        $stmt->execute($chunk);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $existing[opg_lookup_key($row[$pkColumn], $pkType)] = $row;
        }
    }

    return $existing;
}

function opg_compare_rows(PDO $pdo, string $table, string $pkColumn, array $rows): array
{
    $stats = [
        'received' => count($rows),
        'missing' => 0,
        'different' => 0,
        'unchanged' => 0,
        'skipped' => 0,
    ];

    if (!$rows) {
        return $stats;
    }

    if (!opg_table_exists($pdo, $table)) {
        foreach ($rows as $row) {
            if (!is_array($row) || !array_key_exists($pkColumn, $row) || (string)$row[$pkColumn] === '') {
                $stats['skipped']++;
            } else {
                $stats['missing']++;
            }
        }

        return $stats;
    }

    $columnMeta = opg_column_meta($pdo, $table);
    if (!isset($columnMeta[$pkColumn])) {
        throw new RuntimeException("Tabela {$table} nu contine cheia {$pkColumn}.");
    }

    $pkType = (string)($columnMeta[$pkColumn]['type'] ?? '');
    $existing = opg_fetch_existing_by_pk($pdo, $table, $pkColumn, $rows, $pkType);
    $allowedColumns = array_flip(opg_sync_table_columns($table));

    foreach ($rows as $row) {
        if (!is_array($row) || !array_key_exists($pkColumn, $row) || (string)$row[$pkColumn] === '') {
            $stats['skipped']++;
            continue;
        }

        $commonColumns = [];
        foreach (array_keys($row) as $column) {
            if (isset($columnMeta[$column]) && (!$allowedColumns || isset($allowedColumns[$column]))) {
                $commonColumns[] = $column;
            }
        }
        if (!in_array($pkColumn, $commonColumns, true)) {
            $commonColumns[] = $pkColumn;
        }

        $existingRow = $existing[opg_lookup_key($row[$pkColumn], $pkType)] ?? null;
        if (!$existingRow) {
            $stats['missing']++;
            continue;
        }

        $hasDifferences = false;
        foreach ($commonColumns as $column) {
            if ($column === $pkColumn) {
                continue;
            }
            $type = (string)($columnMeta[$column]['type'] ?? '');
            $onlineValue = opg_value_for_db($row[$column] ?? null, $type);
            $localValue = $existingRow[$column] ?? null;
            if (!opg_values_equal($localValue, $onlineValue, $type)) {
                $hasDifferences = true;
                break;
            }
        }

        if ($hasDifferences) {
            $stats['different']++;
        } else {
            $stats['unchanged']++;
        }
    }

    return $stats;
}

function opg_difference_stats(PDO $pdo, array $online, array $products): array
{
    $onlineProducts = opg_filter_rows_for_hash('produse_servicii', $products);
    $onlineCategorii = opg_filter_rows_for_hash('categorii', isset($online['categorii']) && is_array($online['categorii']) ? $online['categorii'] : []);
    $onlineCategoriiLocatii = opg_filter_rows_for_hash('categorii_locatii', isset($online['categorii_locatii']) && is_array($online['categorii_locatii']) ? $online['categorii_locatii'] : []);
    $onlineGestiuni = opg_filter_rows_for_hash('gestiuni', isset($online['gestiuni']) && is_array($online['gestiuni']) ? $online['gestiuni'] : []);
    $onlineCoteTva = opg_filter_cote_tva_for_products(
        opg_filter_rows_for_hash('cote_tva', isset($online['cote_tva']) && is_array($online['cote_tva']) ? $online['cote_tva'] : []),
        $products
    );

    $lookups = [
        'categorii' => opg_compare_rows($pdo, 'categorii', 'id_categorie', $onlineCategorii),
        'categorii_locatii' => opg_compare_rows($pdo, 'categorii_locatii', 'id', $onlineCategoriiLocatii),
        'gestiuni' => opg_compare_rows($pdo, 'gestiuni', 'id_gestiune', $onlineGestiuni),
        'cote_tva' => opg_compare_rows($pdo, 'cote_tva', 'cota', $onlineCoteTva),
    ];

    $lookupMissing = 0;
    $lookupDifferent = 0;
    foreach ($lookups as $stats) {
        $lookupMissing += (int)($stats['missing'] ?? 0);
        $lookupDifferent += (int)($stats['different'] ?? 0);
    }

    return [
        'products' => opg_compare_rows($pdo, 'produse_servicii', 'cod_produs', $onlineProducts),
        'lookups' => $lookups,
        'lookup_missing' => $lookupMissing,
        'lookup_different' => $lookupDifferent,
        'lookup_changed' => $lookupMissing + $lookupDifferent,
    ];
}

function opg_insert_row(PDO $pdo, string $table, array $columns, array $row, array $columnMeta): void
{
    $quotedColumns = implode(', ', array_map('opg_quote_identifier', $columns));
    $placeholders = implode(', ', array_map(static fn(string $column): string => ':' . $column, $columns));
    $stmt = $pdo->prepare('INSERT INTO ' . opg_quote_identifier($table) . " ({$quotedColumns}) VALUES ({$placeholders})");
    $params = [];
    foreach ($columns as $column) {
        $params[':' . $column] = opg_value_for_db($row[$column] ?? null, (string)($columnMeta[$column]['type'] ?? ''));
    }
    $stmt->execute($params);
}

function opg_update_row(PDO $pdo, string $table, string $pkColumn, $pkValue, array $changedFields, array $row, array $columnMeta): void
{
    $sets = [];
    $params = [':__pk' => $pkValue];
    foreach ($changedFields as $column) {
        $sets[] = opg_quote_identifier($column) . ' = :' . $column;
        $params[':' . $column] = opg_value_for_db($row[$column] ?? null, (string)($columnMeta[$column]['type'] ?? ''));
    }

    $stmt = $pdo->prepare('UPDATE ' . opg_quote_identifier($table) . ' SET ' . implode(', ', $sets) . ' WHERE ' . opg_quote_identifier($pkColumn) . ' = :__pk');
    $stmt->execute($params);
}

function opg_sync_rows(PDO $pdo, string $table, string $pkColumn, array $rows): array
{
    if (!$rows || !opg_table_exists($pdo, $table)) {
        return [
            'received' => count($rows),
            'inserted' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'skipped' => count($rows),
        ];
    }

    $columnMeta = opg_column_meta($pdo, $table);
    if (!isset($columnMeta[$pkColumn])) {
        throw new RuntimeException("Tabela {$table} nu contine cheia {$pkColumn}.");
    }

    $pkType = (string)($columnMeta[$pkColumn]['type'] ?? '');
    $existing = opg_fetch_existing_by_pk($pdo, $table, $pkColumn, $rows, $pkType);
    $stats = [
        'received' => count($rows),
        'inserted' => 0,
        'updated' => 0,
        'unchanged' => 0,
        'skipped' => 0,
    ];
    $allowedColumns = array_flip(opg_sync_table_columns($table));

    foreach ($rows as $row) {
        if (!is_array($row) || !array_key_exists($pkColumn, $row) || (string)$row[$pkColumn] === '') {
            $stats['skipped']++;
            continue;
        }

        $commonColumns = [];
        foreach (array_keys($row) as $column) {
            if (isset($columnMeta[$column]) && (!$allowedColumns || isset($allowedColumns[$column]))) {
                $commonColumns[] = $column;
            }
        }
        if (!in_array($pkColumn, $commonColumns, true)) {
            $commonColumns[] = $pkColumn;
        }

        $pkValue = opg_value_for_db($row[$pkColumn], $pkType);
        $existingRow = $existing[opg_lookup_key($row[$pkColumn], $pkType)] ?? null;

        if (!$existingRow) {
            opg_insert_row($pdo, $table, $commonColumns, $row, $columnMeta);
            $stats['inserted']++;
            continue;
        }

        $changedFields = [];
        foreach ($commonColumns as $column) {
            if ($column === $pkColumn) {
                continue;
            }
            $type = (string)($columnMeta[$column]['type'] ?? '');
            $onlineValue = opg_value_for_db($row[$column] ?? null, $type);
            $localValue = $existingRow[$column] ?? null;
            if (!opg_values_equal($localValue, $onlineValue, $type)) {
                $changedFields[] = $column;
            }
        }

        if ($changedFields) {
            opg_update_row($pdo, $table, $pkColumn, $pkValue, $changedFields, $row, $columnMeta);
            $stats['updated']++;
        } else {
            $stats['unchanged']++;
        }
    }

    return $stats;
}

function opg_ensure_log_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS offline_products_sync_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sync_id TEXT DEFAULT '',
            data_ora TEXT DEFAULT CURRENT_TIMESTAMP,
            endpoint TEXT DEFAULT '',
            cod_client INTEGER DEFAULT 0,
            cod_locatie INTEGER DEFAULT 0,
            products_hash TEXT DEFAULT '',
            received_count INTEGER DEFAULT 0,
            inserted_count INTEGER DEFAULT 0,
            updated_count INTEGER DEFAULT 0,
            unchanged_count INTEGER DEFAULT 0,
            skipped_count INTEGER DEFAULT 0,
            lookup_inserted INTEGER DEFAULT 0,
            lookup_updated INTEGER DEFAULT 0,
            status TEXT DEFAULT '',
            dry_run INTEGER DEFAULT 0,
            erori TEXT DEFAULT ''
        )
    ");

    $columns = $pdo->query("PRAGMA table_info(offline_products_sync_logs)")->fetchAll(PDO::FETCH_ASSOC);
    $hasCodLocatie = false;
    foreach ($columns as $column) {
        if ((string)$column['name'] === 'cod_locatie') {
            $hasCodLocatie = true;
            break;
        }
    }
    if (!$hasCodLocatie) {
        $pdo->exec('ALTER TABLE offline_products_sync_logs ADD COLUMN cod_locatie INTEGER DEFAULT 0');
    }

    if (function_exists('restaurant_sqlite_ensure_cod_locatie_triggers')) {
        restaurant_sqlite_ensure_cod_locatie_triggers($pdo);
    }
}

function opg_insert_log(PDO $pdo, array $log): void
{
    try {
        opg_ensure_log_table($pdo);
        $stmt = $pdo->prepare("
            INSERT INTO offline_products_sync_logs
                (sync_id, data_ora, endpoint, cod_client, cod_locatie, products_hash, received_count,
                 inserted_count, updated_count, unchanged_count, skipped_count,
                 lookup_inserted, lookup_updated, status, dry_run, erori)
            VALUES
                (:sync_id, :data_ora, :endpoint, :cod_client, :cod_locatie, :products_hash, :received_count,
                 :inserted_count, :updated_count, :unchanged_count, :skipped_count,
                 :lookup_inserted, :lookup_updated, :status, :dry_run, :erori)
        ");
        $stmt->execute([
            ':sync_id' => (string)($log['sync_id'] ?? ''),
            ':data_ora' => (string)($log['data_ora'] ?? date('Y-m-d H:i:s')),
            ':endpoint' => (string)($log['endpoint'] ?? ''),
            ':cod_client' => (int)($log['cod_client'] ?? 0),
            ':cod_locatie' => (int)($log['cod_locatie'] ?? ($_SESSION['cod_locatie'] ?? 0)),
            ':products_hash' => (string)($log['products_hash'] ?? ''),
            ':received_count' => (int)($log['received_count'] ?? 0),
            ':inserted_count' => (int)($log['inserted_count'] ?? 0),
            ':updated_count' => (int)($log['updated_count'] ?? 0),
            ':unchanged_count' => (int)($log['unchanged_count'] ?? 0),
            ':skipped_count' => (int)($log['skipped_count'] ?? 0),
            ':lookup_inserted' => (int)($log['lookup_inserted'] ?? 0),
            ':lookup_updated' => (int)($log['lookup_updated'] ?? 0),
            ':status' => (string)($log['status'] ?? ''),
            ':dry_run' => (int)($log['dry_run'] ?? 0),
            ':erori' => (string)($log['erori'] ?? ''),
        ]);
    } catch (Throwable $e) {
        error_log('offline products guard log error: ' . $e->getMessage());
    }
}

function opg_products_sync_config(array $restaurantConfig): array
{
    $syncConfig = isset($restaurantConfig['online_products_sync']) && is_array($restaurantConfig['online_products_sync'])
        ? $restaurantConfig['online_products_sync']
        : [];

    return [
        'enabled' => opg_bool($syncConfig['enabled'] ?? false, false),
        'auto_check' => opg_bool($syncConfig['auto_check'] ?? true, true),
        'strict' => opg_bool($syncConfig['strict'] ?? true, true),
        'api_url' => trim((string)($syncConfig['api_url'] ?? '')),
        'api_key' => trim((string)($syncConfig['api_key'] ?? '')),
        'cod_client' => (int)($syncConfig['cod_client'] ?? ($restaurantConfig['client_id'] ?? 0)),
        'timeout_seconds' => max(3, (int)($syncConfig['timeout_seconds'] ?? 10)),
        'dry_run' => opg_bool($syncConfig['dry_run'] ?? false, false),
        'send_api_key_in_query' => opg_bool($syncConfig['send_api_key_in_query'] ?? true, true),
        'verify_ssl' => opg_bool($syncConfig['verify_ssl'] ?? true, true),
    ];
}

function opg_check_products_sync(PDO $pdo, array $restaurantConfig): array
{
    if (!function_exists('restaurantIsOfflineSqlite') || !restaurantIsOfflineSqlite()) {
        return [
            'allow' => true,
            'status' => 'not_offline_sqlite',
            'message' => '',
        ];
    }

    $config = opg_products_sync_config($restaurantConfig);
    if (!$config['enabled'] || !$config['auto_check']) {
        return [
            'allow' => true,
            'status' => !$config['enabled'] ? 'disabled' : 'manual_only',
            'message' => '',
        ];
    }

    if ($config['api_url'] === '' || $config['api_key'] === '') {
        $message = 'Verificarea produselor nu este configurată complet.';
        return [
            'allow' => !$config['strict'],
            'status' => 'config_error',
            'message' => $message,
        ];
    }

    try {
        $localHash = opg_local_hash($pdo);
        $online = opg_fetch_online_hash($config, $localHash);
        $onlineHash = (string)($online['products_hash'] ?? '');
        $changed = array_key_exists('changed', $online)
            ? (bool)$online['changed']
            : ($onlineHash !== '' && !hash_equals($localHash, $onlineHash));

        if ($changed) {
            $onlineFull = opg_fetch_online_products_full($config, $localHash);
            $products = [];
            if (isset($onlineFull['data']) && is_array($onlineFull['data'])) {
                $products = $onlineFull['data'];
            } elseif (isset($onlineFull['products']) && is_array($onlineFull['products'])) {
                $products = $onlineFull['products'];
            }

            if (!$products && (int)($onlineFull['products_count'] ?? 0) > 0) {
                return [
                    'allow' => false,
                    'status' => 'check_incomplete',
                    'message' => 'API-ul a raportat diferențe la produse, dar nu a trimis lista de produse pentru verificare.',
                    'local_hash' => $localHash,
                    'products_hash' => $onlineHash,
                    'products_count' => (int)($onlineFull['products_count'] ?? 0),
                ];
            }

            $onlineCompatibleHash = opg_online_compatible_hash($onlineFull, $products);
            $localCompatibleHash = opg_local_compatible_hash($pdo, $onlineFull, $products);
            if (!hash_equals($localCompatibleHash, $onlineCompatibleHash)) {
                $diffStats = opg_difference_stats($pdo, $onlineFull, $products);
                return [
                    'allow' => false,
                    'status' => 'products_changed',
                    'message' => 'Există diferențe între produsele offline și produsele online. Apasă Sincronizare Produse pentru actualizare.',
                    'local_hash' => $localCompatibleHash,
                    'products_hash' => $onlineCompatibleHash,
                    'remote_hash' => (string)($onlineFull['products_hash'] ?? ''),
                    'products_count' => (int)($onlineFull['products_count'] ?? 0),
                    'diff_stats' => $diffStats,
                ];
            }

            return [
                'allow' => true,
                'status' => 'ok',
                'message' => '',
                'local_hash' => $localCompatibleHash,
                'products_hash' => $onlineCompatibleHash,
                'remote_hash' => (string)($onlineFull['products_hash'] ?? ''),
                'products_count' => (int)($onlineFull['products_count'] ?? 0),
            ];

        }

        return [
            'allow' => true,
            'status' => 'ok',
            'message' => '',
            'local_hash' => $localHash,
            'products_hash' => $onlineHash,
            'products_count' => (int)($online['products_count'] ?? 0),
        ];
    } catch (Throwable $e) {
        $message = 'Verificarea produselor online a eșuat: ' . $e->getMessage();
        $isConnectionError = stripos($e->getMessage(), 'nu a putut fi apelat') !== false
            || stripos($e->getMessage(), 'timed out') !== false
            || stripos($e->getMessage(), 'couldn') !== false
            || stripos($e->getMessage(), 'failed to connect') !== false;

        return [
            'allow' => $isConnectionError ? true : !$config['strict'],
            'status' => 'check_error',
            'message' => $message,
        ];
    }
}
