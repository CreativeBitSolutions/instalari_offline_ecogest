<?php
declare(strict_types=1);

require_once __DIR__ . '/database_connection.php';

date_default_timezone_set('Europe/Bucharest');

function ops_is_cli(): bool
{
    return PHP_SAPI === 'cli';
}

function ops_json_flags(): int
{
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }

    return $flags;
}

function ops_wants_json(): bool
{
    if (isset($_GET['format']) && strtolower((string)$_GET['format']) === 'json') {
        return true;
    }

    $requestedWith = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    if ($requestedWith === 'xmlhttprequest') {
        return true;
    }

    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    return $accept !== '' && strpos($accept, 'application/json') !== false && strpos($accept, 'text/html') === false;
}

function ops_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function ops_recent_logs(PDO $pdo, int $limit = 10, int $offset = 0): array
{
    try {
        ops_ensure_log_table($pdo);
        $stmt = $pdo->prepare('SELECT * FROM offline_products_sync_logs ORDER BY id DESC LIMIT :limit_rows OFFSET :offset_rows');
        $stmt->bindValue(':limit_rows', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset_rows', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

function ops_recent_logs_count(PDO $pdo): int
{
    try {
        ops_ensure_log_table($pdo);
        $stmt = $pdo->query('SELECT COUNT(*) FROM offline_products_sync_logs');

        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function ops_logs_page_size(): int
{
    return 10;
}

function ops_current_logs_page(): int
{
    $page = (int)($_GET['logs_page'] ?? 1);

    return max(1, $page);
}

function ops_logs_url(int $page): string
{
    $params = $_GET;
    unset($params['start_sync'], $params['format']);
    $params['logs_page'] = max(1, $page);

    return 'offline_products_sync.php?' . http_build_query($params);
}

function ops_sync_start_requested(): bool
{
    if (ops_is_cli() || ops_wants_json()) {
        return true;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        return ops_bool($_POST['start_sync'] ?? null, false);
    }

    return ops_bool($_GET['start_sync'] ?? null, false);
}

function ops_preserved_sync_inputs(): string
{
    $html = '<input type="hidden" name="start_sync" value="1">';
    foreach (['force', 'rewrite_existing', 'dry_run'] as $name) {
        if (isset($_REQUEST[$name]) && ops_bool($_REQUEST[$name], false)) {
            $html .= '<input type="hidden" name="' . ops_h($name) . '" value="1">';
        }
    }

    return $html;
}

function ops_status_label(string $status): string
{
    $labels = [
        'success' => 'Finalizată',
        'unchanged' => 'Fără modificări',
        'dry_run' => 'Simulare',
        'error' => 'Eroare',
    ];

    return $labels[$status] ?? $status;
}

function ops_status_class(string $status): string
{
    return $status === 'error' ? 'err' : 'ok';
}

function ops_lookup_total(array $lookups, string $key): int
{
    $total = 0;
    foreach ($lookups as $stats) {
        if (is_array($stats)) {
            $total += (int)($stats[$key] ?? 0);
        }
    }

    return $total;
}

function ops_count_value(array $stats, string $key): int
{
    return (int)($stats[$key] ?? 0);
}

function ops_render_sync_summary(array $payload): string
{
    $products = isset($payload['products']) && is_array($payload['products']) ? $payload['products'] : [];
    $lookups = isset($payload['lookups']) && is_array($payload['lookups']) ? $payload['lookups'] : [];
    if (!$products && !$lookups) {
        return '';
    }

    $items = [
        'Produse online găsite' => $products ? ops_count_value($products, 'received') : 0,
        'Produse adăugate' => $products ? ops_count_value($products, 'inserted') : 0,
        'Produse actualizate' => $products ? ops_count_value($products, 'updated') : 0,
        'Neschimbate' => $products ? ops_count_value($products, 'unchanged') : 0,
        'Date auxiliare' => ops_lookup_total($lookups, 'inserted') + ops_lookup_total($lookups, 'updated'),
    ];

    $html = '<section class="summary" aria-label="Rezumat sincronizare">';
    foreach ($items as $label => $value) {
        $html .= '<div class="metric"><span>' . ops_h($label) . '</span><strong>' . ops_h($value) . '</strong></div>';
    }
    $html .= '</section>';

    return $html;
}

function ops_format_log_date($value): string
{
    $raw = trim((string)$value);
    if ($raw === '') {
        return '';
    }

    try {
        $date = new DateTimeImmutable($raw);
        return $date->format('d/m/Y H:i');
    } catch (Throwable $e) {
        return $raw;
    }
}

function ops_render_recent_runs(array $logs, int $page, int $pageSize, int $total): string
{
    if (!$logs) {
        return '';
    }

    $totalPages = max(1, (int)ceil($total / max(1, $pageSize)));
    $html = '<section class="recent"><h2>Ultimele rulări</h2><table><thead><tr><th>Data</th><th>Status</th><th>Produse online găsite</th><th>Produse adăugate</th><th>Produse actualizate</th><th>Neschimbate</th><th>Date auxiliare</th></tr></thead><tbody>';
    foreach ($logs as $log) {
        $status = (string)($log['status'] ?? '');
        $lookupChanged = (int)($log['lookup_inserted'] ?? 0) + (int)($log['lookup_updated'] ?? 0);
        $html .= '<tr>';
        $html .= '<td>' . ops_h(ops_format_log_date($log['data_ora'] ?? '')) . '</td>';
        $html .= '<td class="' . ops_h(ops_status_class($status)) . '">' . ops_h(ops_status_label($status)) . '</td>';
        $html .= '<td>' . ops_h((int)($log['received_count'] ?? 0)) . '</td>';
        $html .= '<td>' . ops_h((int)($log['inserted_count'] ?? 0)) . '</td>';
        $html .= '<td>' . ops_h((int)($log['updated_count'] ?? 0)) . '</td>';
        $html .= '<td>' . ops_h((int)($log['unchanged_count'] ?? 0)) . '</td>';
        $html .= '<td>' . ops_h($lookupChanged) . '</td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';

    if ($totalPages > 1) {
        $html .= '<nav class="pagination" aria-label="Paginare ultimele rulări">';
        if ($page > 1) {
            $html .= '<a href="' . ops_h(ops_logs_url($page - 1)) . '">Anterior</a>';
        } else {
            $html .= '<span class="disabled">Anterior</span>';
        }

        $html .= '<span>Pagina ' . ops_h($page) . ' din ' . ops_h($totalPages) . '</span>';

        if ($page < $totalPages) {
            $html .= '<a href="' . ops_h(ops_logs_url($page + 1)) . '">Următor</a>';
        } else {
            $html .= '<span class="disabled">Următor</span>';
        }
        $html .= '</nav>';
    }

    $html .= '</section>';
    return $html;
}

function ops_render_start_page(): void
{
    global $pdo;

    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');

    $logsPageSize = ops_logs_page_size();
    $logsPage = ops_current_logs_page();
    $logsTotal = (isset($pdo) && $pdo instanceof PDO) ? ops_recent_logs_count($pdo) : 0;
    $logsTotalPages = max(1, (int)ceil($logsTotal / $logsPageSize));
    $logsPage = min($logsPage, $logsTotalPages);
    $logsOffset = ($logsPage - 1) * $logsPageSize;
    $logs = (isset($pdo) && $pdo instanceof PDO) ? ops_recent_logs($pdo, $logsPageSize, $logsOffset) : [];

    echo '<!doctype html><html lang="ro"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Sincronizare produse</title>';
    echo '<style>
        body{font-family:Arial,sans-serif;background:#f3f4f6;margin:0;color:#111827}
        .wrap{max-width:1120px;margin:36px auto;padding:0 16px}
        .panel{background:#fff;border:1px solid #d1d5db;border-radius:8px;padding:22px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
        h1{font-size:24px;margin:0 0 10px}
        h2{font-size:17px;margin:24px 0 10px}
        p{line-height:1.5;margin:0 0 14px}.muted{color:#6b7280}
        .actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}
        button,.btn{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:10px 16px;border-radius:6px;text-decoration:none;border:1px solid #2563eb;background:#2563eb;color:#fff;font-weight:600;cursor:pointer}
        .secondary{background:#fff;color:#2563eb}
        .recent{overflow-x:auto}
        .recent table{min-width:1040px}
        .pagination{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:12px}
        .pagination a,.pagination span{display:inline-flex;align-items:center;min-height:34px;padding:7px 10px;border-radius:6px;border:1px solid #d1d5db;background:#fff;color:#111827;text-decoration:none}
        .pagination a{border-color:#2563eb;color:#2563eb}
        .pagination .disabled{color:#9ca3af;background:#f9fafb}
        table{width:100%;border-collapse:collapse;background:#fff;margin-top:8px}
        th,td{border:1px solid #d1d5db;padding:8px;text-align:left;vertical-align:top}
        th{background:#f9fafb}.ok{color:#166534}.err{color:#991b1b}
    </style></head><body><main class="wrap"><div class="panel">';
    echo '<h1>Sincronizare produse</h1>';
    echo '<p class="muted">Actualizează nomenclatorul local de produse din baza online.</p>';
    echo '<form method="post" action="offline_products_sync.php">';
    echo ops_preserved_sync_inputs();
    echo '<div class="actions">';
    echo '<button type="submit">Start sincronizare</button>';
    echo '<a class="btn secondary" href="agecs_login.php">Înapoi la login</a>';
    echo '</div></form>';
    echo ops_render_recent_runs($logs, $logsPage, $logsPageSize, $logsTotal);
    echo '</div></main></body></html>';
    exit;
}

function ops_render_html_response(array $payload, int $httpCode): void
{
    global $pdo;

    http_response_code($httpCode);
    header('Content-Type: text/html; charset=utf-8');

    $status = (string)($payload['status'] ?? '');
    $success = $status === 'success';
    $title = $success ? 'Sincronizare produse finalizată' : 'Sincronizare produse oprită';
    $message = (string)($payload['message'] ?? ($success ? 'Proces finalizat.' : 'Procesul nu a putut continua.'));
    $logsPageSize = ops_logs_page_size();
    $logsPage = ops_current_logs_page();
    $logsTotal = (isset($pdo) && $pdo instanceof PDO) ? ops_recent_logs_count($pdo) : 0;
    $logsTotalPages = max(1, (int)ceil($logsTotal / $logsPageSize));
    $logsPage = min($logsPage, $logsTotalPages);
    $logsOffset = ($logsPage - 1) * $logsPageSize;
    $logs = (isset($pdo) && $pdo instanceof PDO) ? ops_recent_logs($pdo, $logsPageSize, $logsOffset) : [];

    echo '<!doctype html><html lang="ro"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . ops_h($title) . '</title>';
    echo '<style>
        body{font-family:Arial,sans-serif;background:#f3f4f6;margin:0;color:#111827}
        .wrap{max-width:1120px;margin:36px auto;padding:0 16px}
        .panel{background:#fff;border:1px solid #d1d5db;border-radius:8px;padding:22px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
        h1{font-size:24px;margin:0 0 12px}
        h2{font-size:17px;margin:24px 0 10px}
        p{line-height:1.5;margin:0 0 14px}
        .ok{color:#166534}.err{color:#991b1b}.muted{color:#6b7280}
        .summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin:18px 0 4px}
        .metric{border:1px solid #d1d5db;border-radius:6px;padding:12px;background:#f9fafb}
        .metric span{display:block;font-size:13px;color:#6b7280;margin-bottom:6px}
        .metric strong{display:block;font-size:22px;color:#111827}
        .actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:22px}
        button,.btn{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:10px 16px;border-radius:6px;text-decoration:none;border:1px solid #2563eb;background:#2563eb;color:#fff;font-weight:600;cursor:pointer}
        .secondary{background:#fff;color:#2563eb}
        .recent{overflow-x:auto}
        .recent table{min-width:1040px}
        .pagination{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:12px}
        .pagination a,.pagination span{display:inline-flex;align-items:center;min-height:34px;padding:7px 10px;border-radius:6px;border:1px solid #d1d5db;background:#fff;color:#111827;text-decoration:none}
        .pagination a{border-color:#2563eb;color:#2563eb}
        .pagination .disabled{color:#9ca3af;background:#f9fafb}
        table{width:100%;border-collapse:collapse;background:#fff}
        th,td{border:1px solid #d1d5db;padding:8px;text-align:left;vertical-align:top}
        th{background:#f9fafb}
    </style></head><body><main class="wrap"><div class="panel">';
    echo '<h1 class="' . ($success ? 'ok' : 'err') . '">' . ops_h($title) . '</h1>';
    echo '<p>' . ops_h($message) . '</p>';

    if (isset($payload['dry_run']) && $payload['dry_run']) {
        echo '<p class="err">Modificările au fost simulate și nu au fost salvate.</p>';
    }
    if (isset($payload['changed']) && $payload['changed'] === false) {
        echo '<p class="ok">Nomenclatorul local este deja sincronizat.</p>';
    }

    echo ops_render_sync_summary($payload);
    echo ops_render_recent_runs($logs, $logsPage, $logsPageSize, $logsTotal);

    $repeatQuery = !empty($payload['rewrite_existing']) ? 'force=1&rewrite_existing=1' : 'force=1';
    echo '<div class="actions">';
    echo '<a class="btn secondary" href="agecs_login.php">Înapoi la login</a>';
    echo '<form method="post" action="offline_products_sync.php" style="margin:0">';
    echo '<input type="hidden" name="start_sync" value="1">';
    foreach (explode('&', $repeatQuery) as $pair) {
        [$name, $value] = array_pad(explode('=', $pair, 2), 2, '1');
        echo '<input type="hidden" name="' . ops_h($name) . '" value="' . ops_h($value) . '">';
    }
    echo '<button type="submit">Sincronizează din nou</button>';
    echo '</form>';
    echo '</div>';
    echo '</div></main></body></html>';
    exit;
}

function ops_send_json(array $payload, int $httpCode = 200): void
{
    if (ops_is_cli()) {
        echo json_encode($payload, ops_json_flags()) . PHP_EOL;
        exit($httpCode >= 400 ? 1 : 0);
    }

    if (!ops_wants_json()) {
        ops_render_html_response($payload, $httpCode);
    }

    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, ops_json_flags());
    exit;
}

function ops_bool($value, bool $default = false): bool
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

function ops_cli_options(): array
{
    if (!ops_is_cli()) {
        return [];
    }

    $valueOptions = [
        'api-url' => true,
        'api-key' => true,
        'cod-client' => true,
        'timeout' => true,
    ];
    $flagOptions = [
        'dry-run' => true,
        'force' => true,
        'rewrite-existing' => true,
    ];
    $argv = $_SERVER['argv'] ?? [];
    $options = [];

    for ($i = 1, $count = count($argv); $i < $count; $i++) {
        $arg = (string)$argv[$i];
        if (strpos($arg, '--') !== 0) {
            continue;
        }

        $nameValue = substr($arg, 2);
        $value = true;
        if (strpos($nameValue, '=') !== false) {
            [$name, $value] = explode('=', $nameValue, 2);
        } else {
            $name = $nameValue;
        }

        if (isset($valueOptions[$name])) {
            if ($value === true) {
                $next = $argv[$i + 1] ?? null;
                if ($next !== null && strpos((string)$next, '--') !== 0) {
                    $value = (string)$next;
                    $i++;
                } else {
                    $value = '';
                }
            }
            $options[$name] = $value;
            continue;
        }

        if (isset($flagOptions[$name])) {
            $options[$name] = true;
        }
    }

    return $options;
}

function ops_config(array $restaurantConfig): array
{
    $syncConfig = isset($restaurantConfig['online_products_sync']) && is_array($restaurantConfig['online_products_sync'])
        ? $restaurantConfig['online_products_sync']
        : [];
    $cliOptions = ops_cli_options();

    if (isset($cliOptions['api-url'])) {
        $syncConfig['api_url'] = (string)$cliOptions['api-url'];
        $syncConfig['enabled'] = true;
    }
    if (isset($cliOptions['api-key'])) {
        $syncConfig['api_key'] = (string)$cliOptions['api-key'];
        $syncConfig['enabled'] = true;
    }
    if (isset($cliOptions['cod-client'])) {
        $syncConfig['cod_client'] = (int)$cliOptions['cod-client'];
    }
    if (isset($cliOptions['timeout'])) {
        $syncConfig['timeout_seconds'] = (int)$cliOptions['timeout'];
    }
    if (array_key_exists('dry-run', $cliOptions)) {
        $syncConfig['dry_run'] = true;
    }
    if (array_key_exists('force', $cliOptions)) {
        $syncConfig['force'] = true;
    }
    if (array_key_exists('rewrite-existing', $cliOptions)) {
        $syncConfig['rewrite_existing'] = true;
    }

    if (!ops_is_cli()) {
        if (isset($_REQUEST['dry_run'])) {
            $syncConfig['dry_run'] = ops_bool($_REQUEST['dry_run'], false);
        }
        if (isset($_REQUEST['force'])) {
            $syncConfig['force'] = ops_bool($_REQUEST['force'], false);
        }
        if (isset($_REQUEST['rewrite_existing'])) {
            $syncConfig['rewrite_existing'] = ops_bool($_REQUEST['rewrite_existing'], false);
        }
    }

    return [
        'enabled' => ops_bool($syncConfig['enabled'] ?? false, false),
        'api_url' => trim((string)($syncConfig['api_url'] ?? '')),
        'api_key' => trim((string)($syncConfig['api_key'] ?? '')),
        'cod_client' => (int)($syncConfig['cod_client'] ?? ($restaurantConfig['client_id'] ?? 0)),
        'timeout_seconds' => max(5, (int)($syncConfig['timeout_seconds'] ?? 30)),
        'dry_run' => ops_bool($syncConfig['dry_run'] ?? false, false),
        'force' => ops_bool($syncConfig['force'] ?? false, false),
        'rewrite_existing' => ops_bool($syncConfig['rewrite_existing'] ?? false, false),
        'send_api_key_in_query' => ops_bool($syncConfig['send_api_key_in_query'] ?? true, true),
        'verify_ssl' => ops_bool($syncConfig['verify_ssl'] ?? true, true),
        'allow_http_without_session' => ops_bool($syncConfig['allow_http_without_session'] ?? false, false),
    ];
}

function ops_quote_identifier(string $identifier): string
{
    return '"' . str_replace('"', '""', $identifier) . '"';
}

function ops_sync_table_columns(string $table): array
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

function ops_hash_column_type(string $column): string
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

function ops_hash_value($value, string $column)
{
    if ($value === null) {
        return null;
    }

    $type = ops_hash_column_type($column);
    if ($type === 'int') {
        return $value === '' ? 0 : (int)$value;
    }
    if ($type === 'float') {
        return $value === '' ? 0.0 : round((float)$value, 6);
    }

    return (string)$value;
}

function ops_table_columns(PDO $pdo, string $table): array
{
    $stmt = $pdo->query('PRAGMA table_info(' . ops_quote_identifier($table) . ')');
    $columns = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $columns[(string)$row['name']] = [
            'type' => strtoupper((string)($row['type'] ?? '')),
            'pk' => (int)($row['pk'] ?? 0),
        ];
    }

    return $columns;
}

function ops_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = :table_name");
    $stmt->execute([':table_name' => $table]);

    return ((int)$stmt->fetchColumn()) > 0;
}

function ops_fetch_table(PDO $pdo, string $table, string $orderColumn, array $selectedColumns = []): array
{
    if (!ops_table_exists($pdo, $table)) {
        return [];
    }

    $columnMeta = ops_table_columns($pdo, $table);
    if (!$columnMeta) {
        return [];
    }

    $columns = $selectedColumns
        ? array_values(array_filter($selectedColumns, static fn(string $column): bool => isset($columnMeta[$column])))
        : array_keys($columnMeta);
    if (!$columns) {
        return [];
    }

    $sql = 'SELECT ' . implode(', ', array_map('ops_quote_identifier', $columns)) . ' FROM ' . ops_quote_identifier($table);
    if (isset($columnMeta[$orderColumn])) {
        $sql .= ' ORDER BY ' . ops_quote_identifier($orderColumn);
    }

    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function ops_normalize_for_hash(array $payload): array
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
                $row[$column] = ops_hash_value($value, (string)$column);
            }
            ksort($row);
            $normalizedRows[] = $row;
        }
        $payload[$key] = $normalizedRows;
        usort($payload[$key], static function (array $left, array $right): int {
            return strcmp(
                (string)json_encode($left, ops_json_flags() & ~JSON_PRETTY_PRINT),
                (string)json_encode($right, ops_json_flags() & ~JSON_PRETTY_PRINT)
            );
        });
    }
    ksort($payload);

    return $payload;
}

function ops_payload_hash(array $payload): string
{
    $json = json_encode(ops_normalize_for_hash($payload), ops_json_flags() & ~JSON_PRETTY_PRINT);
    if ($json === false) {
        throw new RuntimeException('Hash-ul local nu a putut fi generat.');
    }

    return hash('sha256', $json);
}

function ops_filter_rows_for_hash(string $table, array $rows): array
{
    $allowed = array_flip(ops_sync_table_columns($table));
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

function ops_filter_cote_tva_for_products(array $coteTvaRows, array $products): array
{
    $used = [];
    foreach ($products as $product) {
        if (is_array($product) && array_key_exists('cota_tva', $product)) {
            $key = ops_lookup_key($product['cota_tva'], 'REAL');
            if ($key !== '') {
                $used[$key] = true;
            }
        }
    }

    return array_values(array_filter(
        $coteTvaRows,
        static fn(array $row): bool => isset($used[ops_lookup_key($row['cota'] ?? null, 'REAL')])
    ));
}

function ops_online_compatible_hash(array $online, array $products): string
{
    $onlineCoteTva = ops_filter_rows_for_hash('cote_tva', isset($online['cote_tva']) && is_array($online['cote_tva']) ? $online['cote_tva'] : []);

    return ops_payload_hash([
        'produse_servicii' => ops_filter_rows_for_hash('produse_servicii', $products),
        'categorii' => ops_filter_rows_for_hash('categorii', isset($online['categorii']) && is_array($online['categorii']) ? $online['categorii'] : []),
        'categorii_locatii' => ops_filter_rows_for_hash('categorii_locatii', isset($online['categorii_locatii']) && is_array($online['categorii_locatii']) ? $online['categorii_locatii'] : []),
        'gestiuni' => ops_filter_rows_for_hash('gestiuni', isset($online['gestiuni']) && is_array($online['gestiuni']) ? $online['gestiuni'] : []),
        'cote_tva' => ops_filter_cote_tva_for_products($onlineCoteTva, $products),
    ]);
}

function ops_local_cote_tva_for_hash(PDO $pdo): array
{
    $products = ops_fetch_table($pdo, 'produse_servicii', 'cod_produs', ['cota_tva']);
    $coteTva = ops_fetch_table($pdo, 'cote_tva', 'cota', ops_sync_table_columns('cote_tva'));

    return ops_filter_cote_tva_for_products($coteTva, $products);
}

function ops_local_hash(PDO $pdo): string
{
    return ops_payload_hash([
        'produse_servicii' => ops_fetch_table($pdo, 'produse_servicii', 'cod_produs', ops_sync_table_columns('produse_servicii')),
        'categorii' => ops_fetch_table($pdo, 'categorii', 'id_categorie', ops_sync_table_columns('categorii')),
        'categorii_locatii' => ops_fetch_table($pdo, 'categorii_locatii', 'id', ops_sync_table_columns('categorii_locatii')),
        'gestiuni' => ops_fetch_table($pdo, 'gestiuni', 'id_gestiune', ops_sync_table_columns('gestiuni')),
        'cote_tva' => ops_local_cote_tva_for_hash($pdo),
    ]);
}

function ops_append_query(string $url, array $params): string
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

function ops_fetch_online_products(array $config, string $localHash): array
{
    $query = [
        'cod_client' => $config['cod_client'] > 0 ? $config['cod_client'] : null,
        'local_hash' => $config['force'] ? null : $localHash,
    ];
    if ($config['send_api_key_in_query']) {
        $query['api_key'] = $config['api_key'];
    }

    $url = ops_append_query($config['api_url'], $query);
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
            throw new RuntimeException('Endpointul online nu a putut fi apelat: ' . $error);
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
            throw new RuntimeException('Endpointul online nu a putut fi apelat.');
        }
    }

    $raw = preg_replace('/^\xEF\xBB\xBF/', '', (string)$raw);
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Raspunsul endpointului online nu este JSON valid.');
    }
    if ($httpCode >= 400 || ($decoded['status'] ?? '') !== 'success') {
        $message = is_string($decoded['message'] ?? null) ? (string)$decoded['message'] : 'Endpointul online a returnat eroare.';
        throw new RuntimeException($message);
    }

    return $decoded;
}

function ops_value_for_db($value, string $type)
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

function ops_values_equal($left, $right, string $type): bool
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

function ops_lookup_key($value, string $type): string
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

function ops_fetch_existing_by_pk(PDO $pdo, string $table, string $pkColumn, array $rows, string $pkType = ''): array
{
    $ids = [];
    foreach ($rows as $row) {
        if (is_array($row) && array_key_exists($pkColumn, $row)) {
            $key = ops_lookup_key($row[$pkColumn], $pkType);
            if ($key !== '') {
                $ids[$key] = ops_value_for_db($row[$pkColumn], $pkType);
            }
        }
    }
    if (!$ids) {
        return [];
    }

    $existing = [];
    foreach (array_chunk(array_values($ids), 500) as $chunk) {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $stmt = $pdo->prepare('SELECT * FROM ' . ops_quote_identifier($table) . ' WHERE ' . ops_quote_identifier($pkColumn) . " IN ({$placeholders})");
        $stmt->execute($chunk);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $existing[ops_lookup_key($row[$pkColumn], $pkType)] = $row;
        }
    }

    return $existing;
}

function ops_insert_row(PDO $pdo, string $table, array $columns, array $row, array $columnMeta): void
{
    $quotedColumns = implode(', ', array_map('ops_quote_identifier', $columns));
    $placeholders = implode(', ', array_map(static fn(string $column): string => ':' . $column, $columns));
    $stmt = $pdo->prepare('INSERT INTO ' . ops_quote_identifier($table) . " ({$quotedColumns}) VALUES ({$placeholders})");
    $params = [];
    foreach ($columns as $column) {
        $params[':' . $column] = ops_value_for_db($row[$column] ?? null, (string)($columnMeta[$column]['type'] ?? ''));
    }
    $stmt->execute($params);
}

function ops_update_row(PDO $pdo, string $table, string $pkColumn, $pkValue, array $changedFields, array $row, array $columnMeta): void
{
    $sets = [];
    $params = [':__pk' => $pkValue];
    foreach ($changedFields as $column) {
        $sets[] = ops_quote_identifier($column) . ' = :' . $column;
        $params[':' . $column] = ops_value_for_db($row[$column] ?? null, (string)($columnMeta[$column]['type'] ?? ''));
    }

    $stmt = $pdo->prepare('UPDATE ' . ops_quote_identifier($table) . ' SET ' . implode(', ', $sets) . ' WHERE ' . ops_quote_identifier($pkColumn) . ' = :__pk');
    $stmt->execute($params);
}

function ops_sync_rows(PDO $pdo, string $table, string $pkColumn, array $rows, int $previewLimit = 50, bool $rewriteExisting = false): array
{
    if (!$rows || !ops_table_exists($pdo, $table)) {
        return [
            'received' => count($rows),
            'inserted' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'skipped' => count($rows),
            'ignored_columns' => [],
            'preview_changes' => [],
        ];
    }

    $columnMeta = ops_table_columns($pdo, $table);
    if (!isset($columnMeta[$pkColumn])) {
        throw new RuntimeException("Tabela {$table} nu contine cheia {$pkColumn}.");
    }

    $pkType = (string)($columnMeta[$pkColumn]['type'] ?? '');
    $existing = ops_fetch_existing_by_pk($pdo, $table, $pkColumn, $rows, $pkType);
    $stats = [
        'received' => count($rows),
        'inserted' => 0,
        'updated' => 0,
        'unchanged' => 0,
        'skipped' => 0,
        'ignored_columns' => [],
        'preview_changes' => [],
    ];
    $ignored = [];
    $allowedColumns = array_flip(ops_sync_table_columns($table));

    foreach ($rows as $row) {
        if (!is_array($row) || !array_key_exists($pkColumn, $row) || (string)$row[$pkColumn] === '') {
            $stats['skipped']++;
            continue;
        }

        $commonColumns = [];
        foreach (array_keys($row) as $column) {
            if (isset($columnMeta[$column]) && (!$allowedColumns || isset($allowedColumns[$column]))) {
                $commonColumns[] = $column;
            } else {
                $ignored[$column] = true;
            }
        }
        if (!in_array($pkColumn, $commonColumns, true)) {
            $commonColumns[] = $pkColumn;
        }

        $pkValue = ops_value_for_db($row[$pkColumn], $pkType);
        $existingRow = $existing[ops_lookup_key($row[$pkColumn], $pkType)] ?? null;

        if (!$existingRow) {
            ops_insert_row($pdo, $table, $commonColumns, $row, $columnMeta);
            $stats['inserted']++;
            if (count($stats['preview_changes']) < $previewLimit) {
                $stats['preview_changes'][] = [
                    'pk' => (string)$row[$pkColumn],
                    'action' => 'insert',
                    'fields' => array_values(array_diff($commonColumns, [$pkColumn])),
                ];
            }
            continue;
        }

        $changedFields = [];
        foreach ($commonColumns as $column) {
            if ($column === $pkColumn) {
                continue;
            }
            $type = (string)($columnMeta[$column]['type'] ?? '');
            $onlineValue = ops_value_for_db($row[$column] ?? null, $type);
            $localValue = $existingRow[$column] ?? null;
            if (!ops_values_equal($localValue, $onlineValue, $type)) {
                $changedFields[] = $column;
            }
        }

        if ($changedFields && $rewriteExisting) {
            $changedFields = array_values(array_filter(
                $commonColumns,
                static fn(string $column): bool => $column !== $pkColumn
            ));
        }

        if ($changedFields) {
            ops_update_row($pdo, $table, $pkColumn, $pkValue, $changedFields, $row, $columnMeta);
            $stats['updated']++;
            if (count($stats['preview_changes']) < $previewLimit) {
                $stats['preview_changes'][] = [
                    'pk' => (string)$row[$pkColumn],
                    'action' => 'update',
                    'fields' => $changedFields,
                ];
            }
        } else {
            $stats['unchanged']++;
        }
    }

    $stats['ignored_columns'] = array_keys($ignored);
    sort($stats['ignored_columns']);

    return $stats;
}

function ops_ensure_log_table(PDO $pdo): void
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

function ops_insert_log(PDO $pdo, array $log): void
{
    try {
        ops_ensure_log_table($pdo);
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
        error_log('offline products sync log error: ' . $e->getMessage());
    }
}

try {
    if (!function_exists('restaurantIsOfflineSqlite') || !restaurantIsOfflineSqlite()) {
        ops_send_json([
            'status' => 'error',
            'message' => 'Sincronizarea produselor se poate rula doar in modul offline SQLite.',
        ], 409);
    }

    $config = ops_config($restaurantConfig ?? []);
    if (!ops_is_cli() && !$config['allow_http_without_session'] && empty($_SESSION['admin_id'])) {
        ops_send_json([
            'status' => 'error',
            'message' => 'Sesiune expirata.',
        ], 401);
    }

    if (!$config['enabled']) {
        ops_send_json([
            'status' => 'error',
            'message' => 'Sincronizarea produselor nu este activata in offline_config.local.php.',
        ], 409);
    }
    if ($config['api_url'] === '' || $config['api_key'] === '') {
        ops_send_json([
            'status' => 'error',
            'message' => 'Lipsesc api_url sau api_key pentru sincronizarea produselor.',
        ], 400);
    }

    if (!ops_sync_start_requested()) {
        ops_render_start_page();
    }

    ops_ensure_log_table($pdo);
    $syncId = 'PRODUCTS-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
    $localHash = ops_local_hash($pdo);
    $online = ops_fetch_online_products($config, $localHash);

    if (array_key_exists('changed', $online) && $online['changed'] === false) {
        ops_insert_log($pdo, [
            'sync_id' => $syncId,
            'endpoint' => $config['api_url'],
            'cod_client' => $config['cod_client'],
            'products_hash' => (string)($online['products_hash'] ?? ''),
            'received_count' => (int)($online['products_count'] ?? 0),
            'status' => 'unchanged',
            'dry_run' => (int)$config['dry_run'],
        ]);

        ops_send_json([
            'status' => 'success',
            'sync_id' => $syncId,
            'message' => 'Nomenclatorul local este deja sincronizat.',
            'products_hash' => (string)($online['products_hash'] ?? ''),
            'changed' => false,
        ]);
    }

    $products = [];
    if (isset($online['data']) && is_array($online['data'])) {
        $products = $online['data'];
    } elseif (isset($online['products']) && is_array($online['products'])) {
        $products = $online['products'];
    }

    if (!$products && (int)($online['products_count'] ?? 0) > 0) {
        throw new RuntimeException('Endpointul a raportat produse, dar nu a trimis lista de produse.');
    }
    $compatibleHash = ops_online_compatible_hash($online, $products);

    $pdo->beginTransaction();
    $rewriteExisting = (bool)$config['rewrite_existing'];
    $lookupStats = [
        'categorii' => ops_sync_rows($pdo, 'categorii', 'id_categorie', isset($online['categorii']) && is_array($online['categorii']) ? $online['categorii'] : [], 10, $rewriteExisting),
        'categorii_locatii' => ops_sync_rows($pdo, 'categorii_locatii', 'id', isset($online['categorii_locatii']) && is_array($online['categorii_locatii']) ? $online['categorii_locatii'] : [], 10, $rewriteExisting),
        'gestiuni' => ops_sync_rows($pdo, 'gestiuni', 'id_gestiune', isset($online['gestiuni']) && is_array($online['gestiuni']) ? $online['gestiuni'] : [], 10, $rewriteExisting),
        'cote_tva' => ops_sync_rows($pdo, 'cote_tva', 'cota', isset($online['cote_tva']) && is_array($online['cote_tva']) ? $online['cote_tva'] : [], 10, $rewriteExisting),
    ];
    $productStats = ops_sync_rows($pdo, 'produse_servicii', 'cod_produs', $products, 80, $rewriteExisting);

    if ($config['dry_run']) {
        $pdo->rollBack();
        $status = 'dry_run';
    } else {
        $pdo->commit();
        $status = 'success';
    }

    $lookupInserted = 0;
    $lookupUpdated = 0;
    foreach ($lookupStats as $stats) {
        $lookupInserted += (int)($stats['inserted'] ?? 0);
        $lookupUpdated += (int)($stats['updated'] ?? 0);
    }

    ops_insert_log($pdo, [
        'sync_id' => $syncId,
        'endpoint' => $config['api_url'],
        'cod_client' => $config['cod_client'],
        'products_hash' => $compatibleHash,
        'received_count' => $productStats['received'],
        'inserted_count' => $productStats['inserted'],
        'updated_count' => $productStats['updated'],
        'unchanged_count' => $productStats['unchanged'],
        'skipped_count' => $productStats['skipped'],
        'lookup_inserted' => $lookupInserted,
        'lookup_updated' => $lookupUpdated,
        'status' => $status,
        'dry_run' => (int)$config['dry_run'],
    ]);

    ops_send_json([
        'status' => 'success',
        'sync_id' => $syncId,
        'dry_run' => $config['dry_run'],
        'rewrite_existing' => $rewriteExisting,
        'message' => $rewriteExisting
            ? 'Produsele au fost importate din online și rescrise în baza offline.'
            : 'Produsele au fost importate din online.',
        'products_hash' => $compatibleHash,
        'remote_products_hash' => (string)($online['products_hash'] ?? ''),
        'products' => $productStats,
        'lookups' => $lookupStats,
    ]);
} catch (Throwable $e) {
    try {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
    } catch (Throwable $rollbackError) {
        error_log('offline products rollback error: ' . $rollbackError->getMessage());
    }

    if (isset($pdo) && $pdo instanceof PDO) {
        ops_insert_log($pdo, [
            'sync_id' => $syncId ?? '',
            'endpoint' => $config['api_url'] ?? '',
            'cod_client' => $config['cod_client'] ?? 0,
            'status' => 'error',
            'dry_run' => (int)($config['dry_run'] ?? 0),
            'erori' => $e->getMessage(),
        ]);
    }

    ops_send_json([
        'status' => 'error',
        'message' => 'Sincronizarea produselor a esuat: ' . $e->getMessage(),
        'dry_run' => (bool)($config['dry_run'] ?? false),
        'rewrite_existing' => (bool)($config['rewrite_existing'] ?? false),
    ], 500);
}
