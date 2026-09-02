<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/session.php';

$departamentSchemaPath = is_file(__DIR__ . '/det_note_departament_listare_schema.php')
    ? __DIR__ . '/det_note_departament_listare_schema.php'
    : dirname(__DIR__) . '/det_note_departament_listare_schema.php';
require_once $departamentSchemaPath;

function productReportResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function productReportUpper(string $value): string
{
    return function_exists('mb_strtoupper')
        ? mb_strtoupper($value, 'UTF-8')
        : strtoupper($value);
}

function productReportDepartmentsFromValue($rawValue, bool $withFallback = true): array
{
    $departments = [];
    foreach (explode(',', (string)$rawValue) as $department) {
        $department = productReportUpper(trim($department));
        if ($department !== '') {
            $departments[$department] = $department;
        }
    }

    if ($withFallback && !$departments) {
        $departments['FĂRĂ DEPARTAMENT'] = 'FĂRĂ DEPARTAMENT';
    }

    return array_values($departments);
}

function productReportAvailableDepartments(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT DISTINCT departament FROM produse_servicii ORDER BY departament");
    $departments = [];
    $hasEmptyDepartment = false;

    while (($rawValue = $stmt->fetchColumn()) !== false) {
        $rawValue = trim((string)$rawValue);
        if ($rawValue === '') {
            $hasEmptyDepartment = true;
            continue;
        }
        foreach (productReportDepartmentsFromValue($rawValue, false) as $department) {
            $departments[$department] = $department;
        }
    }

    if ($hasEmptyDepartment) {
        $departments['FĂRĂ DEPARTAMENT'] = 'FĂRĂ DEPARTAMENT';
    }

    $departments = array_values($departments);
    natcasesort($departments);
    return array_values($departments);
}

function productReportAvailableOperators(PDO $pdo, int $locationId): array
{
    $stmt = $pdo->prepare("
        SELECT DISTINCT
            n.operator AS operator_id,
            a.admin_firstname,
            a.admin_lastname
        FROM note n
        LEFT JOIN admins_12 a ON a.admin_id = n.operator
        WHERE n.locatie = :location
          AND n.status = 'F'
        ORDER BY a.admin_firstname, a.admin_lastname, n.operator
    ");
    $stmt->execute(['location' => $locationId]);

    $operators = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $operatorId = (int)($row['operator_id'] ?? 0);
        $name = trim((string)($row['admin_firstname'] ?? '') . ' ' . (string)($row['admin_lastname'] ?? ''));
        $operators[] = [
            'id' => (string)$operatorId,
            'label' => $name !== '' ? $name . ' (ID ' . $operatorId . ')' : 'Operator ID ' . $operatorId,
        ];
    }
    return $operators;
}

function productReportValidateManager(PDO $pdo): void
{
    $operatorId = (int)($_SESSION['admin_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT rank FROM admins_12 WHERE admin_id = ? LIMIT 1');
    $stmt->execute([$operatorId]);
    $rank = strtolower(trim((string)$stmt->fetchColumn()));

    if ($rank !== 'sefsala') {
        productReportResponse([
            'status' => 'error',
            'message' => 'Raportul poate fi generat numai de șeful de sală.',
        ], 403);
    }
}

function productReportValidateCsrf(): void
{
    $expected = (string)($_SESSION['product_report_csrf'] ?? '');
    $received = (string)($_POST['csrf'] ?? '');
    if ($expected === '' || $received === '' || !hash_equals($expected, $received)) {
        productReportResponse([
            'status' => 'error',
            'message' => 'Sesiunea formularului a expirat. Reîncarcă pagina și încearcă din nou.',
        ], 400);
    }
}

function productReportParseDateTime(string $date, string $time, DateTimeZone $timezone): ?DateTimeImmutable
{
    $value = $date . ' ' . $time;
    $result = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $value, $timezone);
    if (!$result || $result->format('Y-m-d H:i') !== $value) {
        return null;
    }
    return $result;
}

function productReportFilters(PDO $pdo, int $locationId): array
{
    $type = trim((string)($_POST['report_type'] ?? 'protocol'));
    if (!in_array($type, ['protocol', 'department'], true)) {
        productReportResponse(['status' => 'error', 'message' => 'Tipul raportului nu este valid.'], 422);
    }

    $timezone = new DateTimeZone('Europe/Bucharest');
    $dateStart = trim((string)($_POST['date_start'] ?? ''));
    $timeStart = trim((string)($_POST['time_start'] ?? ''));
    $dateEnd = trim((string)($_POST['date_end'] ?? ''));
    $timeEnd = trim((string)($_POST['time_end'] ?? ''));
    $start = productReportParseDateTime($dateStart, $timeStart, $timezone);
    $end = productReportParseDateTime($dateEnd, $timeEnd, $timezone);

    if (!$start || !$end) {
        productReportResponse(['status' => 'error', 'message' => 'Completează corect data și ora intervalului.'], 422);
    }

    $end = $end->setTime((int)$end->format('H'), (int)$end->format('i'), 59);
    if ($end < $start) {
        productReportResponse(['status' => 'error', 'message' => 'Sfârșitul intervalului trebuie să fie după început.'], 422);
    }

    if (($end->getTimestamp() - $start->getTimestamp()) > 2678400) {
        productReportResponse(['status' => 'error', 'message' => 'Intervalul maxim permis este de 31 de zile.'], 422);
    }

    $department = productReportUpper(trim((string)($_POST['department'] ?? 'all')));
    $department = $department === '' ? 'ALL' : $department;
    $availableDepartments = productReportAvailableDepartments($pdo);
    if ($department !== 'ALL' && !in_array($department, $availableDepartments, true)) {
        productReportResponse(['status' => 'error', 'message' => 'Alege un departament de listare valid.'], 422);
    }
    $operator = trim((string)($_POST['operator'] ?? 'all'));
    $operator = $operator === '' ? 'all' : $operator;
    $operatorLabel = 'Toți operatorii';
    if ($operator !== 'all') {
        if (!ctype_digit($operator)) {
            productReportResponse(['status' => 'error', 'message' => 'Alege un operator valid.'], 422);
        }
        $operatorFound = false;
        foreach (productReportAvailableOperators($pdo, $locationId) as $operatorOption) {
            if ($operatorOption['id'] === $operator) {
                $operatorLabel = $operatorOption['label'];
                $operatorFound = true;
                break;
            }
        }
        if (!$operatorFound) {
            productReportResponse(['status' => 'error', 'message' => 'Operatorul ales nu are note finalizate în locația curentă.'], 422);
        }
    }

    return [
        'type' => $type,
        'department' => $department,
        'operator' => $operator,
        'operator_label' => $operatorLabel,
        'start' => $start,
        'end' => $end,
        'date_start' => $start->format('Y-m-d'),
        'time_start' => $start->format('H:i:s'),
        'date_end' => $end->format('Y-m-d'),
        'time_end' => $end->format('H:i:s'),
    ];
}

function productReportNumber(float $value, int $decimals): string
{
    return number_format($value, $decimals, ',', '.');
}

function productReportWrap(string $text, int $width = 38): array
{
    $text = trim((string)preg_replace('/\s+/u', ' ', $text));
    if ($text === '') {
        return [''];
    }

    $words = preg_split('/\s+/u', $text) ?: [$text];
    $lines = [];
    $current = '';
    foreach ($words as $word) {
        $candidate = $current === '' ? $word : $current . ' ' . $word;
        $candidateWidth = function_exists('mb_strwidth') ? mb_strwidth($candidate, 'UTF-8') : strlen($candidate);
        if ($candidateWidth <= $width || $current === '') {
            $current = $candidate;
            continue;
        }
        $lines[] = $current;
        $current = $word;
    }
    if ($current !== '') {
        $lines[] = $current;
    }
    return $lines;
}

function productReportBuildContent(array $report, int $locationId): string
{
    $filters = $report['filters'];
    $summary = $report['summary'];
    $line = str_repeat('=', 42) . "\n";
    $out = $line;
    $out .= $report['title'] . "\n";
    $out .= $line;
    $out .= 'Generat: ' . date('d.m.Y H:i:s') . "\n";
    $out .= 'Locația: ' . $locationId . "\n";
    $out .= 'De la: ' . $filters['start']->format('d.m.Y H:i') . "\n";
    $out .= 'Până la: ' . $filters['end']->format('d.m.Y H:i') . "\n";
    $out .= 'Imprimantă: ' . $report['destination'] . "\n";
    foreach (productReportWrap('Operator: ' . $filters['operator_label']) as $operatorLine) {
        $out .= $operatorLine . "\n";
    }
    foreach (productReportWrap('Departament: ' . ($filters['department'] === 'ALL' ? 'TOATE' : $filters['department'])) as $departmentLine) {
        $out .= $departmentLine . "\n";
    }
    $out .= $line;

    foreach ($report['groups'] as $departmentIndex => $group) {
        foreach (productReportWrap(($departmentIndex + 1) . '. ' . $group['department']) as $departmentLine) {
            $out .= $departmentLine . "\n";
        }
        $out .= str_repeat('-', 42) . "\n";
        foreach ($group['products'] as $product) {
            $productLine = productReportNumber($product['quantity'], 3)
                . ' X ' . $product['product']
                . ' - ' . productReportNumber($product['value'], 2) . ' LEI';
            foreach (productReportWrap($productLine) as $wrappedLine) {
                $out .= $wrappedLine . "\n";
            }
        }
        $out .= 'TOTAL ' . $group['department'] . ': '
            . productReportNumber($group['quantity_total'], 3) . ' | '
            . productReportNumber($group['value_total'], 2) . " LEI\n";
        $out .= $line;
    }

    if (count($report['groups']) > 1) {
        $out .= 'TOTAL GENERAL: ' . productReportNumber($summary['quantity_total'], 3)
            . ' | ' . productReportNumber($summary['value_total'], 2) . " LEI\n";
        $out .= $line;
    }

    if ($filters['department'] === 'ALL' && !empty($report['payment_totals'])) {
        $paymentLabels = [
            'cash' => 'NUMERAR',
            'card' => 'CARD',
            'tickets' => 'TICHETE',
            'bank' => 'VIRAMENT',
            'protocol' => 'PROTOCOL',
            'glovo' => 'GLOVO',
        ];
        $out .= "TOTAL METODE DE PLATĂ PE OSPĂTAR\n";
        $out .= $line;
        foreach ($report['payment_totals'] as $operatorTotal) {
            foreach (productReportWrap('OSPĂTAR: ' . $operatorTotal['operator_label']) as $operatorLine) {
                $out .= $operatorLine . "\n";
            }
            foreach ($paymentLabels as $paymentKey => $paymentLabel) {
                $amount = (float)($operatorTotal['payments'][$paymentKey] ?? 0);
                if (abs($amount) < 0.005) {
                    continue;
                }
                $out .= $paymentLabel . ': ' . productReportNumber($amount, 2) . " LEI\n";
            }
            $out .= 'TOTAL OSPĂTAR: ' . productReportNumber((float)$operatorTotal['total'], 2) . " LEI\n";
            $out .= str_repeat('-', 42) . "\n";
        }
        $out .= $line;
    }
    return $out;
}

function productReportOperatorLabel(array $row): string
{
    $operatorId = (int)($row['operator_id'] ?? 0);
    $name = trim((string)($row['admin_firstname'] ?? '') . ' ' . (string)($row['admin_lastname'] ?? ''));
    return $name !== '' ? $name . ' (ID ' . $operatorId . ')' : 'Operator ID ' . $operatorId;
}

function productReportDepartmentLabel(array $departments, string $selectedDepartment): string
{
    if ($selectedDepartment !== 'ALL') {
        return $selectedDepartment;
    }
    natcasesort($departments);
    return implode(' + ', array_values($departments));
}

function productReportBuild(PDO $pdo, array $filters, int $locationId): array
{
    agecs_ensure_det_note_departament_listare($pdo, 'det_note');
    $departmentSql = agecs_departament_listare_sql('dn', 'ps');
    $where = "
        n.locatie = :location
        AND n.status = 'F'
        AND (
            n.data_bon > :date_start
            OR (n.data_bon = :date_start AND n.ora_bon >= :time_start)
        )
        AND (
            n.data_bon < :date_end
            OR (n.data_bon = :date_end AND n.ora_bon <= :time_end)
        )
    ";
    if ($filters['type'] === 'protocol') {
        $where .= " AND COALESCE(n.protocol, 0) > 0 ";
    }
    if ($filters['operator'] !== 'all') {
        $where .= " AND n.operator = :operator ";
    }

    $params = [
        'location' => $locationId,
        'date_start' => $filters['date_start'],
        'time_start' => $filters['time_start'],
        'date_end' => $filters['date_end'],
        'time_end' => $filters['time_end'],
    ];
    if ($filters['operator'] !== 'all') {
        $params['operator'] = (int)$filters['operator'];
    }

    $sql = "
        SELECT
            n.nrbon AS note_id,
            n.data_bon AS note_date,
            n.ora_bon AS note_time,
            n.operator AS operator_id,
            a.admin_firstname,
            a.admin_lastname,
            COALESCE(n.numerar, 0) AS payment_cash,
            COALESCE(n.card, 0) AS payment_card,
            COALESCE(n.tichete, 0) AS payment_tickets,
            COALESCE(n.virament_bancar, 0) AS payment_bank,
            COALESCE(n.protocol, 0) AS payment_protocol,
            COALESCE(n.glovo, 0) AS payment_glovo,
            COALESCE(NULLIF(TRIM(dn.data), ''), n.data_bon) AS line_date,
            COALESCE(NULLIF(TRIM(dn.ora), ''), n.ora_bon) AS line_time,
            {$departmentSql} AS department_raw,
            COALESCE(NULLIF(TRIM(dn.nume_produs), ''), NULLIF(TRIM(ps.nume), ''), 'Produs fără denumire') AS product_name,
            COALESCE(dn.cantitate, 0) AS quantity,
            COALESCE(dn.valoare_vanzare_cu_tva, 0) AS value_total
        FROM det_note dn
        INNER JOIN note n ON n.nrbon = dn.nr_bon
        LEFT JOIN produse_servicii ps ON ps.cod_produs = dn.cod_p
        LEFT JOIN admins_12 a ON a.admin_id = n.operator
        WHERE {$where}
        ORDER BY n.data_bon DESC, n.ora_bon DESC, n.nrbon DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $sourceRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $groups = [];
    $notes = [];
    $lines = [];
    $allProducts = [];
    foreach ($sourceRows as $sourceRow) {
        $departments = productReportDepartmentsFromValue($sourceRow['department_raw'] ?? '');
        if ($filters['department'] !== 'ALL' && !in_array($filters['department'], $departments, true)) {
            continue;
        }

        $product = trim((string)($sourceRow['product_name'] ?? 'Produs fără denumire'));
        if ($product === '') {
            $product = 'Produs fără denumire';
        }
        $departmentLabel = productReportDepartmentLabel($departments, $filters['department']);
        $departmentKey = productReportUpper($departmentLabel);
        $productKey = productReportUpper($product);
        if (!isset($groups[$departmentKey])) {
            $groups[$departmentKey] = [
                'department' => $departmentLabel,
                'quantity_total' => 0.0,
                'value_total' => 0.0,
                'products' => [],
            ];
        }
        if (!isset($groups[$departmentKey]['products'][$productKey])) {
            $groups[$departmentKey]['products'][$productKey] = [
                'product' => $product,
                'quantity' => 0.0,
                'value' => 0.0,
                'note_ids' => [],
            ];
        }

        $quantity = (float)($sourceRow['quantity'] ?? 0);
        $value = (float)($sourceRow['value_total'] ?? 0);
        $noteId = (string)($sourceRow['note_id'] ?? '');
        $operatorLabel = productReportOperatorLabel($sourceRow);
        $groups[$departmentKey]['quantity_total'] += $quantity;
        $groups[$departmentKey]['value_total'] += $value;
        $groups[$departmentKey]['products'][$productKey]['quantity'] += $quantity;
        $groups[$departmentKey]['products'][$productKey]['value'] += $value;
        $groups[$departmentKey]['products'][$productKey]['note_ids'][$noteId] = true;
        $allProducts[$productKey] = true;

        $line = [
            'note_id' => $noteId,
            'date' => (string)($sourceRow['line_date'] ?? $sourceRow['note_date'] ?? ''),
            'time' => (string)($sourceRow['line_time'] ?? $sourceRow['note_time'] ?? ''),
            'operator_label' => $operatorLabel,
            'department' => $departmentLabel,
            'product' => $product,
            'quantity' => round($quantity, 3),
            'value' => round($value, 2),
        ];
        $lines[] = $line;

        if (!isset($notes[$noteId])) {
            $payments = [
                'cash' => (float)($sourceRow['payment_cash'] ?? 0),
                'card' => (float)($sourceRow['payment_card'] ?? 0),
                'tickets' => (float)($sourceRow['payment_tickets'] ?? 0),
                'bank' => (float)($sourceRow['payment_bank'] ?? 0),
                'protocol' => (float)($sourceRow['payment_protocol'] ?? 0),
                'glovo' => (float)($sourceRow['payment_glovo'] ?? 0),
            ];
            $notes[$noteId] = [
                'note_id' => $noteId,
                'date' => (string)($sourceRow['note_date'] ?? ''),
                'time' => (string)($sourceRow['note_time'] ?? ''),
                'operator_id' => (string)((int)($sourceRow['operator_id'] ?? 0)),
                'operator_label' => $operatorLabel,
                'product_value' => 0.0,
                'payments' => $payments,
                'payment_total' => array_sum($payments),
                'products' => [],
            ];
        }
        $notes[$noteId]['product_value'] += $value;
        $notes[$noteId]['products'][] = [
            'product' => $product,
            'department' => $departmentLabel,
            'quantity' => round($quantity, 3),
            'value' => round($value, 2),
            'date' => $line['date'],
            'time' => $line['time'],
        ];
    }

    uasort($groups, static function (array $left, array $right): int {
        return strnatcasecmp($left['department'], $right['department']);
    });

    $publicGroups = [];
    $quantityTotal = 0.0;
    $valueTotal = 0.0;
    foreach ($groups as $group) {
        uasort($group['products'], static function (array $left, array $right): int {
            return strnatcasecmp($left['product'], $right['product']);
        });
        $products = [];
        foreach ($group['products'] as $product) {
            $products[] = [
                'product' => $product['product'],
                'quantity' => round($product['quantity'], 3),
                'value' => round($product['value'], 2),
                'note_count' => count($product['note_ids']),
            ];
        }
        $quantityTotal += $group['quantity_total'];
        $valueTotal += $group['value_total'];
        $publicGroups[] = [
            'department' => $group['department'],
            'quantity_total' => round($group['quantity_total'], 3),
            'value_total' => round($group['value_total'], 2),
            'products' => $products,
        ];
    }

    $paymentTotals = [];
    if ($filters['department'] === 'ALL') {
        foreach ($notes as $note) {
            $operatorKey = (string)$note['operator_id'];
            if (!isset($paymentTotals[$operatorKey])) {
                $paymentTotals[$operatorKey] = [
                    'operator_id' => $operatorKey,
                    'operator_label' => $note['operator_label'],
                    'payments' => ['cash' => 0.0, 'card' => 0.0, 'tickets' => 0.0, 'bank' => 0.0, 'protocol' => 0.0, 'glovo' => 0.0],
                    'total' => 0.0,
                ];
            }
            foreach ($note['payments'] as $key => $amount) {
                $paymentTotals[$operatorKey]['payments'][$key] += (float)$amount;
            }
            $paymentTotals[$operatorKey]['total'] += (float)$note['payment_total'];
        }
        uasort($paymentTotals, static function (array $left, array $right): int {
            return strnatcasecmp($left['operator_label'], $right['operator_label']);
        });
        foreach ($paymentTotals as &$operatorTotal) {
            foreach ($operatorTotal['payments'] as $key => $amount) {
                $operatorTotal['payments'][$key] = round($amount, 2);
            }
            $operatorTotal['total'] = round($operatorTotal['total'], 2);
        }
        unset($operatorTotal);
    }

    foreach ($notes as &$note) {
        $note['product_value'] = round($note['product_value'], 2);
        $note['payment_total'] = round($note['payment_total'], 2);
        foreach ($note['payments'] as $key => $amount) {
            $note['payments'][$key] = round($amount, 2);
        }
    }
    unset($note);

    $protocolTotal = 0.0;
    if ($filters['type'] === 'protocol') {
        $protocolStmt = $pdo->prepare("SELECT COALESCE(SUM(n.protocol), 0) FROM note n WHERE {$where}");
        $protocolStmt->execute($params);
        $protocolTotal = (float)$protocolStmt->fetchColumn();
    }

    $title = $filters['type'] === 'protocol'
        ? 'RAPORT PRODUSE PROTOCOL'
        : 'RAPORT PRODUSE DEPARTAMENT';
    $destination = ($filters['type'] === 'protocol' || $filters['department'] === 'ALL')
        ? 'BAR'
        : $filters['department'];
    $summary = [
        'note_count' => count($notes),
        'product_count' => count($allProducts),
        'quantity_total' => round($quantityTotal, 3),
        'value_total' => round($valueTotal, 2),
        'protocol_total' => round($protocolTotal, 2),
    ];

    $report = [
        'title' => $title,
        'destination' => $destination,
        'filters' => $filters,
        'summary' => $summary,
        'groups' => $publicGroups,
        'lines' => $lines,
        'notes' => array_values($notes),
        'payment_totals' => array_values($paymentTotals),
    ];
    $report['content'] = productReportBuildContent($report, $locationId);
    return $report;
}

function productReportPublicPayload(array $report): array
{
    return [
        'title' => $report['title'],
        'destination' => $report['destination'],
        'interval' => $report['filters']['start']->format('d.m.Y H:i')
            . ' - ' . $report['filters']['end']->format('d.m.Y H:i'),
        'report_type' => $report['filters']['type'],
        'department' => $report['filters']['department'],
        'operator' => $report['filters']['operator'],
        'operator_label' => $report['filters']['operator_label'],
        'summary' => $report['summary'],
        'groups' => $report['groups'],
        'lines' => $report['lines'],
        'notes' => $report['notes'],
        'payment_totals' => $report['payment_totals'],
        'content' => $report['content'],
        'can_print' => count($report['groups']) > 0,
    ];
}

function productReportStorePreview(array $report, int $clientId, int $locationId): string
{
    $now = time();
    $stored = is_array($_SESSION['product_report_previews'] ?? null)
        ? $_SESSION['product_report_previews']
        : [];
    foreach ($stored as $key => $item) {
        if (!is_array($item) || (int)($item['expires'] ?? 0) < $now) {
            unset($stored[$key]);
        }
    }
    while (count($stored) >= 10) {
        array_shift($stored);
    }

    $token = bin2hex(random_bytes(24));
    $stored[$token] = [
        'expires' => $now + 600,
        'client_id' => $clientId,
        'location_id' => $locationId,
        'title' => $report['title'],
        'destination' => $report['destination'],
        'content' => $report['content'],
    ];
    $_SESSION['product_report_previews'] = $stored;
    return $token;
}

function productReportQueuePath(int $clientId, int $locationId): string
{
    $baseDirectory = defined('RESTAURANT_OFFLINE_API_DIR')
        ? RESTAURANT_OFFLINE_API_DIR
        : dirname(__DIR__) . DIRECTORY_SEPARATOR . 'api';
    $queueDirectory = rtrim((string)$baseDirectory, '/\\')
        . DIRECTORY_SEPARATOR . $clientId
        . DIRECTORY_SEPARATOR . $locationId;

    if (!is_dir($queueDirectory) && !mkdir($queueDirectory, 0777, true) && !is_dir($queueDirectory)) {
        throw new RuntimeException('Folderul cozii de imprimare nu a putut fi creat.');
    }
    return $queueDirectory . DIRECTORY_SEPARATOR . 'de_listat_la_imprimanta.json';
}

function productReportWriteQueue(string $queuePath, array $payload): bool
{
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('Raportul nu a putut fi convertit în formatul imprimantei.');
    }

    $handle = @fopen($queuePath, 'x');
    if ($handle === false) {
        return false;
    }

    $written = false;
    try {
        if (!flock($handle, LOCK_EX)) {
            return false;
        }
        $length = strlen($json);
        $offset = 0;
        while ($offset < $length) {
            $chunk = fwrite($handle, substr($json, $offset));
            if ($chunk === false || $chunk === 0) {
                return false;
            }
            $offset += $chunk;
        }
        fflush($handle);
        $written = true;
        return true;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
        if (!$written && is_file($queuePath)) {
            @unlink($queuePath);
        }
    }
}

try {
    productReportValidateManager($pdo);
    $action = trim((string)($_REQUEST['action'] ?? 'options'));

    if ($action === 'options') {
        $locationId = (int)($_SESSION['cod_locatie'] ?? 0);
        productReportResponse([
            'status' => 'success',
            'departments' => productReportAvailableDepartments($pdo),
            'operators' => productReportAvailableOperators($pdo, $locationId),
        ]);
    }

    if (!in_array($action, ['preview', 'print'], true)) {
        productReportResponse(['status' => 'error', 'message' => 'Acțiunea cerută nu este validă.'], 400);
    }

    productReportValidateCsrf();
    $clientId = (int)($_SESSION['client_id'] ?? 0);
    $locationId = (int)($_SESSION['cod_locatie'] ?? 0);
    if ($clientId <= 0 || $locationId <= 0) {
        productReportResponse(['status' => 'error', 'message' => 'Clientul sau locația nu sunt stabilite în sesiune.'], 400);
    }

    if ($action === 'preview') {
        $filters = productReportFilters($pdo, $locationId);
        $report = productReportBuild($pdo, $filters, $locationId);
        $publicReport = productReportPublicPayload($report);
        $token = '';
        if ($publicReport['can_print']) {
            $token = productReportStorePreview($report, $clientId, $locationId);
        }
        productReportResponse([
            'status' => 'success',
            'message' => $publicReport['can_print']
                ? 'Previzualizarea este pregătită. Verifică raportul înainte de trimitere.'
                : 'Nu există produse pentru criteriile selectate.',
            'preview_token' => $token,
            'report' => $publicReport,
        ]);
    }

    $token = trim((string)($_POST['preview_token'] ?? ''));
    $stored = $_SESSION['product_report_previews'][$token] ?? null;
    if (!preg_match('/^[a-f0-9]{48}$/D', $token) || !is_array($stored)) {
        productReportResponse(['status' => 'error', 'message' => 'Previzualizarea nu mai este validă. Generează raportul din nou.'], 409);
    }
    if ((int)($stored['expires'] ?? 0) < time()) {
        unset($_SESSION['product_report_previews'][$token]);
        productReportResponse(['status' => 'error', 'message' => 'Previzualizarea a expirat. Generează raportul din nou.'], 409);
    }
    if ((int)($stored['client_id'] ?? 0) !== $clientId || (int)($stored['location_id'] ?? 0) !== $locationId) {
        productReportResponse(['status' => 'error', 'message' => 'Previzualizarea aparține altei sesiuni de lucru.'], 409);
    }

    $content = (string)($stored['content'] ?? '');
    $printerHelper = __DIR__ . '/printer_bold_helper.php';
    if (is_file($printerHelper)) {
        require_once $printerHelper;
        if (function_exists('agecs_printer_bold_content')) {
            $content = agecs_printer_bold_content($content, $clientId);
        }
    }

    $queuePath = productReportQueuePath($clientId, $locationId);
    $payload = [
        'status' => 'success',
        'message' => 'Raport produse pregătit pentru imprimare.',
        'data' => [[
            'id' => 0,
            'data' => date('Y-m-d'),
            'ora' => date('H:i:s'),
            'de_trimis_la_imprimanta' => 1,
            'nrbon' => 0,
            'locatie' => $locationId,
            'departament_listare' => (string)$stored['destination'],
            'continut' => $content,
        ]],
    ];

    if (!productReportWriteQueue($queuePath, $payload)) {
        productReportResponse([
            'status' => 'error',
            'message' => 'Imprimanta are deja un document în așteptare. Reîncearcă după procesarea lui.',
        ], 409);
    }

    unset($_SESSION['product_report_previews'][$token]);
    productReportResponse([
        'status' => 'success',
        'message' => 'Raportul a fost trimis către imprimanta ' . (string)$stored['destination'] . '.',
    ]);
} catch (Throwable $error) {
    error_log('sefsala_raport_produse_api: ' . $error->getMessage());
    productReportResponse([
        'status' => 'error',
        'message' => 'Raportul nu a putut fi pregătit. Verifică jurnalul aplicației.',
    ], 500);
}
