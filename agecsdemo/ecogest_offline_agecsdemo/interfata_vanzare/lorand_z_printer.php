<?php
declare(strict_types=1);

require_once __DIR__ . '/printer_format_helper.php';
require_once __DIR__ . '/printer_queue_helper.php';
require_once __DIR__ . '/setari_lorand_schema.php';
require_once __DIR__ . '/det_note_departament_listare_schema.php';

function lorand_z_number($value, int $decimals = 2): string
{
    return number_format((float)$value, $decimals, ',', '.');
}

function lorand_z_wrap(string $text, int $width): array
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
        $candidateWidth = function_exists('mb_strwidth')
            ? mb_strwidth($candidate, 'UTF-8')
            : strlen($candidate);
        if ($current === '' || $candidateWidth <= $width) {
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

function lorand_z_operator_label(array $row): string
{
    $operatorId = (int)($row['operator'] ?? 0);
    $name = trim((string)($row['admin_firstname'] ?? '') . ' ' . (string)($row['admin_lastname'] ?? ''));
    return $name !== '' ? $name . ' (ID ' . $operatorId . ')' : 'Operator ID ' . $operatorId;
}

function lorand_z_interval(PDO $pdo, int $locationId, int $reportNumber): array
{
    $params = [':locatie' => $locationId, ':raport' => $reportNumber];
    $first = $pdo->prepare(
        "SELECT data_bon, ora_bon
           FROM note
          WHERE locatie = :locatie AND status = 'F' AND nr_raport_z = :raport
          ORDER BY data_bon ASC, ora_bon ASC, nrbon ASC
          LIMIT 1"
    );
    $first->execute($params);
    $firstRow = $first->fetch(PDO::FETCH_ASSOC) ?: [];

    $last = $pdo->prepare(
        "SELECT data_bon, ora_bon
           FROM note
          WHERE locatie = :locatie AND status = 'F' AND nr_raport_z = :raport
          ORDER BY data_bon DESC, ora_bon DESC, nrbon DESC
          LIMIT 1"
    );
    $last->execute($params);
    $lastRow = $last->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'first_date' => (string)($firstRow['data_bon'] ?? '-'),
        'first_time' => (string)($firstRow['ora_bon'] ?? '-'),
        'last_date' => (string)($lastRow['data_bon'] ?? '-'),
        'last_time' => (string)($lastRow['ora_bon'] ?? '-'),
    ];
}

function lorand_z_closure_content(PDO $pdo, int $locationId, int $reportNumber, int $width): string
{
    $params = [':locatie' => $locationId, ':raport' => $reportNumber];
    $totalsStmt = $pdo->prepare(
        "SELECT COALESCE(SUM(numerar), 0) AS numerar,
                COALESCE(SUM(card), 0) AS card,
                COALESCE(SUM(tichete), 0) AS tichete,
                COALESCE(SUM(protocol), 0) AS protocol,
                COALESCE(SUM(glovo), 0) AS glovo,
                COALESCE(SUM(virament_bancar), 0) AS virament_bancar
           FROM note
          WHERE locatie = :locatie AND status = 'F' AND nr_raport_z = :raport"
    );
    $totalsStmt->execute($params);
    $totals = $totalsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $operatorsStmt = $pdo->prepare(
        "SELECT DISTINCT n.operator, a.admin_firstname, a.admin_lastname
           FROM note n
           LEFT JOIN admins_12 a ON a.admin_id = n.operator
          WHERE n.locatie = :locatie AND n.status = 'F' AND n.nr_raport_z = :raport
          ORDER BY a.admin_firstname, a.admin_lastname, n.operator"
    );
    $operatorsStmt->execute($params);
    $operators = $operatorsStmt->fetchAll(PDO::FETCH_ASSOC);

    $tipStmt = $pdo->prepare(
        "SELECT COALESCE(SUM(dn.valoare_vanzare_cu_tva), 0)
           FROM det_note dn
           INNER JOIN note n ON n.nrbon = dn.nr_bon
           LEFT JOIN produse_servicii ps ON ps.cod_produs = dn.cod_p
          WHERE n.locatie = :locatie
            AND n.status = 'F'
            AND n.nr_raport_z = :raport
            AND (dn.cod_p = -1 OR UPPER(TRIM(COALESCE(NULLIF(dn.nume_produs, ''), ps.nume, ''))) = 'BACSIS')"
    );
    $tipStmt->execute($params);
    $tip = (float)$tipStmt->fetchColumn();

    $lines = [
        'NOTĂ ÎNCHIDERE TURĂ',
        'Data: ' . date('Y-m-d H:i:s'),
        'Raport Z: ' . $reportNumber,
        str_repeat('-', $width),
    ];
    $labels = [
        'numerar' => 'Numerar',
        'card' => 'Card',
        'tichete' => 'Tichete',
        'protocol' => 'Protocol',
        'glovo' => 'Online',
        'virament_bancar' => 'Virament Bancar',
    ];
    foreach ($labels as $field => $label) {
        $amount = (float)($totals[$field] ?? 0);
        if (abs($amount) >= 0.005) {
            $lines[] = $label . ' total: ' . lorand_z_number($amount) . ' LEI';
        }
    }
    $lines[] = str_repeat('=', $width);
    if (abs($tip) >= 0.005) {
        $lines[] = 'BACSIS total: ' . lorand_z_number($tip) . ' LEI';
    }
    foreach ($operators as $operator) {
        foreach (lorand_z_wrap('OPERATOR: ' . lorand_z_operator_label($operator), $width) as $wrapped) {
            $lines[] = $wrapped;
        }
    }

    return agecs_printer_bold_content(implode("\n", $lines), 8);
}

function lorand_z_regular_report(PDO $pdo, int $locationId, int $reportNumber, int $width): string
{
    $params = [':locatie' => $locationId, ':raport' => $reportNumber];
    $interval = lorand_z_interval($pdo, $locationId, $reportNumber);
    $productStmt = $pdo->prepare(
        "SELECT COALESCE(NULLIF(TRIM(dn.nume_produs), ''), NULLIF(TRIM(ps.nume), ''), 'Produs fără denumire') AS produs,
                COALESCE(SUM(dn.cantitate), 0) AS cantitate,
                COALESCE(SUM(dn.valoare_vanzare_cu_tva), 0) AS valoare
           FROM det_note dn
           INNER JOIN note n ON n.nrbon = dn.nr_bon
           LEFT JOIN produse_servicii ps ON ps.cod_produs = dn.cod_p
          WHERE n.locatie = :locatie
            AND n.status = 'F'
            AND n.nr_raport_z = :raport
            AND COALESCE(n.protocol, 0) <= 0
          GROUP BY COALESCE(NULLIF(TRIM(dn.nume_produs), ''), NULLIF(TRIM(ps.nume), ''), 'Produs fără denumire')
          ORDER BY produs"
    );
    $productStmt->execute($params);
    $products = $productStmt->fetchAll(PDO::FETCH_ASSOC);

    $paymentStmt = $pdo->prepare(
        "SELECT n.operator, a.admin_firstname, a.admin_lastname,
                COALESCE(SUM(n.numerar), 0) AS numerar,
                COALESCE(SUM(n.card), 0) AS card,
                COALESCE(SUM(n.tichete), 0) AS tichete,
                COALESCE(SUM(n.glovo), 0) AS glovo,
                COALESCE(SUM(n.virament_bancar), 0) AS virament_bancar
           FROM note n
           LEFT JOIN admins_12 a ON a.admin_id = n.operator
          WHERE n.locatie = :locatie
            AND n.status = 'F'
            AND n.nr_raport_z = :raport
            AND COALESCE(n.protocol, 0) <= 0
          GROUP BY n.operator, a.admin_firstname, a.admin_lastname
          ORDER BY a.admin_firstname, a.admin_lastname, n.operator"
    );
    $paymentStmt->execute($params);
    $payments = $paymentStmt->fetchAll(PDO::FETCH_ASSOC);

    $tipStmt = $pdo->prepare(
        "SELECT n.operator, COALESCE(SUM(dn.valoare_vanzare_cu_tva), 0) AS total_bacsis
           FROM det_note dn
           INNER JOIN note n ON n.nrbon = dn.nr_bon
           LEFT JOIN produse_servicii ps ON ps.cod_produs = dn.cod_p
          WHERE n.locatie = :locatie
            AND n.status = 'F'
            AND n.nr_raport_z = :raport
            AND COALESCE(n.protocol, 0) <= 0
            AND (dn.cod_p = -1 OR UPPER(TRIM(COALESCE(NULLIF(dn.nume_produs, ''), ps.nume, ''))) = 'BACSIS')
          GROUP BY n.operator"
    );
    $tipStmt->execute($params);
    $tips = [];
    foreach ($tipStmt->fetchAll(PDO::FETCH_ASSOC) as $tipRow) {
        $tips[(int)$tipRow['operator']] = (float)$tipRow['total_bacsis'];
    }

    $separator = str_repeat('=', $width);
    $lines = [
        $separator,
        'RAPORT Z PRODUSE VÂNDUTE',
        $separator,
        'Nr. raport Z: ' . $reportNumber,
        'Generat: ' . date('d.m.Y H:i:s'),
        'Locația: ' . $locationId,
        'De la: ' . $interval['first_date'] . ' ' . $interval['first_time'],
        'Până la: ' . $interval['last_date'] . ' ' . $interval['last_time'],
        'Notele PROTOCOL sunt pe foaia separată.',
        $separator,
    ];

    $totalProducts = 0.0;
    foreach ($products as $product) {
        $productLine = lorand_z_number($product['cantitate'] ?? 0, 3)
            . ' X ' . (string)$product['produs']
            . ' - ' . lorand_z_number($product['valoare'] ?? 0) . ' LEI';
        foreach (lorand_z_wrap($productLine, $width) as $wrapped) {
            $lines[] = $wrapped;
        }
        $totalProducts += (float)($product['valoare'] ?? 0);
    }
    if (!$products) {
        $lines[] = 'Fără produse în afara notelor PROTOCOL.';
    }
    $lines[] = str_repeat('-', $width);
    $lines[] = 'TOTAL PRODUSE: ' . lorand_z_number($totalProducts) . ' LEI';
    $lines[] = $separator;
    $lines[] = 'ÎNCASĂRI PE OPERATOR';
    $lines[] = $separator;

    $grand = ['numerar' => 0.0, 'card' => 0.0, 'tichete' => 0.0, 'glovo' => 0.0, 'virament_bancar' => 0.0, 'bacsis' => 0.0];
    foreach ($payments as $payment) {
        $operatorId = (int)$payment['operator'];
        foreach (lorand_z_wrap('OPERATOR: ' . lorand_z_operator_label($payment), $width) as $wrapped) {
            $lines[] = $wrapped;
        }
        $operatorTotal = 0.0;
        foreach (['numerar' => 'NUMERAR', 'card' => 'CARD', 'tichete' => 'TICHETE', 'glovo' => 'ONLINE', 'virament_bancar' => 'VIRAMENT'] as $field => $label) {
            $amount = (float)($payment[$field] ?? 0);
            $grand[$field] += $amount;
            $operatorTotal += $amount;
            if (abs($amount) >= 0.005) {
                $lines[] = $label . ': ' . lorand_z_number($amount) . ' LEI';
            }
        }
        $tip = (float)($tips[$operatorId] ?? 0);
        $grand['bacsis'] += $tip;
        if (abs($tip) >= 0.005) {
            $lines[] = 'BACȘIȘ: ' . lorand_z_number($tip) . ' LEI';
        }
        $lines[] = 'TOTAL OPERATOR: ' . lorand_z_number($operatorTotal) . ' LEI';
        $lines[] = str_repeat('-', $width);
    }

    $grandIncome = $grand['numerar'] + $grand['card'] + $grand['tichete'] + $grand['glovo'] + $grand['virament_bancar'];
    $lines[] = 'TOTAL ÎNCASĂRI: ' . lorand_z_number($grandIncome) . ' LEI';
    $lines[] = 'TOTAL NUMERAR: ' . lorand_z_number($grand['numerar']) . ' LEI';
    $lines[] = 'TOTAL CARD: ' . lorand_z_number($grand['card']) . ' LEI';
    $lines[] = 'TOTAL TICHETE: ' . lorand_z_number($grand['tichete']) . ' LEI';
    $lines[] = 'TOTAL ONLINE: ' . lorand_z_number($grand['glovo']) . ' LEI';
    $lines[] = 'TOTAL VIRAMENT: ' . lorand_z_number($grand['virament_bancar']) . ' LEI';
    $lines[] = 'TOTAL BACȘIȘ: ' . lorand_z_number($grand['bacsis']) . ' LEI';
    $lines[] = $separator;

    return agecs_printer_bold_content(implode("\n", $lines), 8);
}

function lorand_z_protocol_report(PDO $pdo, int $locationId, int $reportNumber, int $width): ?string
{
    $departmentSql = agecs_departament_listare_sql('dn', 'ps');
    $stmt = $pdo->prepare(
        "SELECT n.nrbon, n.operator, n.protocol, a.admin_firstname, a.admin_lastname,
                COALESCE({$departmentSql}, 'FĂRĂ DEPARTAMENT') AS departament,
                COALESCE(NULLIF(TRIM(dn.nume_produs), ''), NULLIF(TRIM(ps.nume), ''), 'Produs fără denumire') AS produs,
                COALESCE(dn.cantitate, 0) AS cantitate,
                COALESCE(dn.valoare_vanzare_cu_tva, 0) AS valoare
           FROM note n
           INNER JOIN det_note dn ON dn.nr_bon = n.nrbon
           LEFT JOIN produse_servicii ps ON ps.cod_produs = dn.cod_p
           LEFT JOIN admins_12 a ON a.admin_id = n.operator
          WHERE n.locatie = :locatie
            AND n.status = 'F'
            AND n.nr_raport_z = :raport
            AND COALESCE(n.protocol, 0) > 0
          ORDER BY departament, produs, n.nrbon"
    );
    $stmt->execute([':locatie' => $locationId, ':raport' => $reportNumber]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        return null;
    }

    $groups = [];
    $notes = [];
    $operators = [];
    foreach ($rows as $row) {
        $departments = [];
        foreach (explode(',', (string)$row['departament']) as $department) {
            $department = strtoupper(trim($department));
            if ($department !== '') {
                $departments[$department] = $department;
            }
        }
        if (!$departments) {
            $departments['FĂRĂ DEPARTAMENT'] = 'FĂRĂ DEPARTAMENT';
        }
        natcasesort($departments);
        $departmentLabel = implode(' + ', array_values($departments));
        $departmentKey = strtoupper($departmentLabel);
        $product = trim((string)$row['produs']);
        $productKey = strtoupper($product);
        if (!isset($groups[$departmentKey])) {
            $groups[$departmentKey] = ['label' => $departmentLabel, 'quantity' => 0.0, 'value' => 0.0, 'products' => []];
        }
        if (!isset($groups[$departmentKey]['products'][$productKey])) {
            $groups[$departmentKey]['products'][$productKey] = ['label' => $product, 'quantity' => 0.0, 'value' => 0.0];
        }

        $quantity = (float)$row['cantitate'];
        $value = (float)$row['valoare'];
        $groups[$departmentKey]['quantity'] += $quantity;
        $groups[$departmentKey]['value'] += $value;
        $groups[$departmentKey]['products'][$productKey]['quantity'] += $quantity;
        $groups[$departmentKey]['products'][$productKey]['value'] += $value;

        $noteId = (int)$row['nrbon'];
        if (!isset($notes[$noteId])) {
            $notes[$noteId] = (float)$row['protocol'];
            $operatorId = (int)$row['operator'];
            if (!isset($operators[$operatorId])) {
                $operators[$operatorId] = ['label' => lorand_z_operator_label($row), 'total' => 0.0, 'notes' => 0];
            }
            $operators[$operatorId]['total'] += (float)$row['protocol'];
            $operators[$operatorId]['notes']++;
        }
    }

    uasort($groups, static fn(array $left, array $right): int => strnatcasecmp($left['label'], $right['label']));
    uasort($operators, static fn(array $left, array $right): int => strnatcasecmp($left['label'], $right['label']));
    $interval = lorand_z_interval($pdo, $locationId, $reportNumber);
    $separator = str_repeat('=', $width);
    $lines = [
        $separator,
        'RAPORT PRODUSE PROTOCOL',
        $separator,
        'Nr. raport Z: ' . $reportNumber,
        'Generat: ' . date('d.m.Y H:i:s'),
        'Locația: ' . $locationId,
        'De la: ' . $interval['first_date'] . ' ' . $interval['first_time'],
        'Până la: ' . $interval['last_date'] . ' ' . $interval['last_time'],
        'Note PROTOCOL: ' . count($notes),
        $separator,
    ];

    $generalQuantity = 0.0;
    $generalValue = 0.0;
    $departmentIndex = 0;
    foreach ($groups as $group) {
        $departmentIndex++;
        foreach (lorand_z_wrap($departmentIndex . '. ' . $group['label'], $width) as $wrapped) {
            $lines[] = $wrapped;
        }
        $lines[] = str_repeat('-', $width);
        uasort($group['products'], static fn(array $left, array $right): int => strnatcasecmp($left['label'], $right['label']));
        foreach ($group['products'] as $product) {
            $productLine = lorand_z_number($product['quantity'], 3)
                . ' X ' . $product['label']
                . ' - ' . lorand_z_number($product['value']) . ' LEI';
            foreach (lorand_z_wrap($productLine, $width) as $wrapped) {
                $lines[] = $wrapped;
            }
        }
        $lines[] = 'TOTAL ' . $group['label'] . ': '
            . lorand_z_number($group['quantity'], 3) . ' | '
            . lorand_z_number($group['value']) . ' LEI';
        $lines[] = $separator;
        $generalQuantity += (float)$group['quantity'];
        $generalValue += (float)$group['value'];
    }

    $lines[] = 'TOTAL PRODUSE PROTOCOL: ' . lorand_z_number($generalQuantity, 3)
        . ' | ' . lorand_z_number($generalValue) . ' LEI';
    $lines[] = $separator;
    $lines[] = 'PROTOCOL PE OPERATOR';
    $lines[] = $separator;
    foreach ($operators as $operator) {
        foreach (lorand_z_wrap('OPERATOR: ' . $operator['label'], $width) as $wrapped) {
            $lines[] = $wrapped;
        }
        $lines[] = 'NOTE: ' . (int)$operator['notes'];
        $lines[] = 'PROTOCOL: ' . lorand_z_number($operator['total']) . ' LEI';
        $lines[] = str_repeat('-', $width);
    }
    $lines[] = 'TOTAL PROTOCOL: ' . lorand_z_number(array_sum($notes)) . ' LEI';
    $lines[] = $separator;

    return agecs_printer_bold_content(implode("\n", $lines), 8);
}

function lorand_z_snapshot_departments(PDO $pdo, int $locationId, int $reportNumber): void
{
    if (!agecs_ensure_det_note_departament_listare($pdo)) {
        throw new RuntimeException('Coloana pentru departamentul istoric nu a putut fi pregătită.');
    }
    $stmt = $pdo->prepare(
        "SELECT nrbon FROM note
          WHERE locatie = :locatie AND status = 'F' AND nr_raport_z = :raport"
    );
    $stmt->execute([':locatie' => $locationId, ':raport' => $reportNumber]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $noteId) {
        agecs_snapshot_det_note_departamente($pdo, (int)$noteId);
    }
}

function lorand_z_enqueue_documents(PDO $pdo, int $clientId, int $locationId, int $reportNumber): int
{
    if ($clientId !== 8 || $reportNumber <= 0) {
        throw new InvalidArgumentException('Raportul de listare AGECSDEMO nu este valid.');
    }
    if (!vanzare_v2_ensure_lorand_offline_schema($pdo)) {
        throw new RuntimeException('Schema cozii imprimantei nu a putut fi pregătită.');
    }

    lorand_z_snapshot_departments($pdo, $locationId, $reportNumber);
    $config = agecs_printer_client_format_config($clientId);
    $width = max(24, min(80, (int)($config['width_chars'] ?? 42)));
    $contents = [
        '01_inchidere' => lorand_z_closure_content($pdo, $locationId, $reportNumber, $width),
        '02_produse' => lorand_z_regular_report($pdo, $locationId, $reportNumber, $width),
    ];
    $protocolContent = lorand_z_protocol_report($pdo, $locationId, $reportNumber, $width);
    if ($protocolContent !== null) {
        $contents['03_protocol'] = $protocolContent;
    }

    $queued = 0;
    foreach ($contents as $type => $content) {
        $documentKey = 'raport_z:' . $locationId . ':' . $reportNumber . ':' . $type;
        $insert = $pdo->prepare(
            "INSERT OR IGNORE INTO lorand_printer_queue_history
                (document_key, nrbon, locatie, status, updated_at)
             VALUES (:document_key, 0, :locatie, 'preparing', CURRENT_TIMESTAMP)"
        );
        $insert->execute([':document_key' => $documentKey, ':locatie' => $locationId]);
        if ($insert->rowCount() === 0) {
            continue;
        }

        try {
            $stableKey = 'z_' . str_pad((string)$reportNumber, 10, '0', STR_PAD_LEFT) . '_' . $type;
            $path = agecs_printer_enqueue_payload($clientId, $locationId, [
                'status' => 'success',
                'message' => 'Document de închidere AGECSDEMO pregătit pentru BAR.',
                'data' => [[
                    'id' => 0,
                    'data' => date('Y-m-d'),
                    'ora' => date('H:i:s'),
                    'de_trimis_la_imprimanta' => 1,
                    'nrbon' => 0,
                    'locatie' => $locationId,
                    'departament_listare' => 'BAR',
                    'continut' => $content,
                ]],
            ], $stableKey, $stableKey);
            $update = $pdo->prepare(
                "UPDATE lorand_printer_queue_history
                    SET status = 'queued', queue_file = :file, updated_at = CURRENT_TIMESTAMP
                  WHERE document_key = :document_key"
            );
            $update->execute([':file' => $path, ':document_key' => $documentKey]);
            $queued++;
        } catch (Throwable $e) {
            $delete = $pdo->prepare(
                "DELETE FROM lorand_printer_queue_history
                  WHERE document_key = :document_key AND status = 'preparing'"
            );
            $delete->execute([':document_key' => $documentKey]);
            throw $e;
        }
    }
    return $queued;
}
