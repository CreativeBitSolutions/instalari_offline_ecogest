<?php
declare(strict_types=1);

if (!function_exists('agecs_z_number')) {
    function agecs_z_number(float $value, int $decimals = 2): string
    {
        return number_format($value, $decimals, ',', '.');
    }
}

if (!function_exists('agecs_z_wrap')) {
    function agecs_z_wrap(string $text, int $width = 38): array
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
}

if (!function_exists('agecs_z_operator_label')) {
    function agecs_z_operator_label(array $row): string
    {
        $operatorId = (int)($row['operator'] ?? 0);
        $name = trim((string)($row['admin_firstname'] ?? '') . ' ' . (string)($row['admin_lastname'] ?? ''));
        return $name !== '' ? $name . ' (ID ' . $operatorId . ')' : 'Operator ID ' . $operatorId;
    }
}

if (!function_exists('agecs_z_interval')) {
    function agecs_z_interval(PDO $pdo, int $locationId, int $reportNumber): array
    {
        $params = ['loc' => $locationId, 'rz' => $reportNumber];
        $first = $pdo->prepare("\n            SELECT data_bon, ora_bon\n            FROM note\n            WHERE locatie = :loc AND status = 'F' AND nr_raport_z = :rz\n            ORDER BY data_bon ASC, ora_bon ASC, nrbon ASC\n            LIMIT 1\n        ");
        $first->execute($params);
        $firstRow = $first->fetch(PDO::FETCH_ASSOC) ?: [];

        $last = $pdo->prepare("\n            SELECT data_bon, ora_bon\n            FROM note\n            WHERE locatie = :loc AND status = 'F' AND nr_raport_z = :rz\n            ORDER BY data_bon DESC, ora_bon DESC, nrbon DESC\n            LIMIT 1\n        ");
        $last->execute($params);
        $lastRow = $last->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'first_date' => (string)($firstRow['data_bon'] ?? '-'),
            'first_time' => (string)($firstRow['ora_bon'] ?? '-'),
            'last_date' => (string)($lastRow['data_bon'] ?? '-'),
            'last_time' => (string)($lastRow['ora_bon'] ?? '-'),
        ];
    }
}

if (!function_exists('agecs_z_regular_report')) {
    function agecs_z_regular_report(PDO $pdo, int $clientId, int $locationId, int $reportNumber): string
    {
        // Pentru 1008 și 1021, notele PROTOCOL apar exclusiv pe al doilea document.
        $separateProtocol = in_array($clientId, [1008, 1021], true);
        $protocolFilter = $separateProtocol ? ' AND COALESCE(n.protocol, 0) <= 0 ' : '';
        $params = ['loc' => $locationId, 'rz' => $reportNumber];
        $interval = agecs_z_interval($pdo, $locationId, $reportNumber);

        $productStmt = $pdo->prepare("\n            SELECT\n                COALESCE(NULLIF(TRIM(dn.nume_produs), ''), NULLIF(TRIM(ps.nume), ''), 'Produs fără denumire') AS produs,\n                COALESCE(SUM(dn.cantitate), 0) AS cantitate,\n                COALESCE(SUM(dn.valoare_vanzare_cu_tva), 0) AS valoare\n            FROM det_note dn\n            INNER JOIN note n ON n.nrbon = dn.nr_bon\n            LEFT JOIN produse_servicii ps ON ps.cod_produs = dn.cod_p\n            WHERE n.locatie = :loc\n              AND n.status = 'F'\n              AND n.nr_raport_z = :rz\n              {$protocolFilter}\n            GROUP BY COALESCE(NULLIF(TRIM(dn.nume_produs), ''), NULLIF(TRIM(ps.nume), ''), 'Produs fără denumire')\n            ORDER BY produs\n        ");
        $productStmt->execute($params);
        $products = $productStmt->fetchAll(PDO::FETCH_ASSOC);

        $paymentStmt = $pdo->prepare("\n            SELECT\n                n.operator,\n                a.admin_firstname,\n                a.admin_lastname,\n                COALESCE(SUM(n.numerar), 0) AS numerar,\n                COALESCE(SUM(n.card), 0) AS card,\n                COALESCE(SUM(n.tichete), 0) AS tichete,\n                COALESCE(SUM(n.protocol), 0) AS protocol,\n                COALESCE(SUM(n.glovo), 0) AS glovo,\n                COALESCE(SUM(n.virament_bancar), 0) AS virament_bancar\n            FROM note n\n            LEFT JOIN admins_12 a ON a.admin_id = n.operator\n            WHERE n.locatie = :loc\n              AND n.status = 'F'\n              AND n.nr_raport_z = :rz\n              {$protocolFilter}\n            GROUP BY n.operator, a.admin_firstname, a.admin_lastname\n            ORDER BY a.admin_firstname, a.admin_lastname, n.operator\n        ");
        $paymentStmt->execute($params);
        $payments = $paymentStmt->fetchAll(PDO::FETCH_ASSOC);

        $tipStmt = $pdo->prepare("\n            SELECT n.operator, COALESCE(SUM(dn.valoare_vanzare_cu_tva), 0) AS total_bacsis\n            FROM det_note dn\n            INNER JOIN note n ON n.nrbon = dn.nr_bon\n            LEFT JOIN produse_servicii ps ON ps.cod_produs = dn.cod_p\n            WHERE n.locatie = :loc\n              AND n.status = 'F'\n              AND n.nr_raport_z = :rz\n              AND UPPER(TRIM(COALESCE(NULLIF(dn.nume_produs, ''), ps.nume, ''))) = 'BACSIS'\n              {$protocolFilter}\n            GROUP BY n.operator\n        ");
        $tipStmt->execute($params);
        $tips = [];
        foreach ($tipStmt->fetchAll(PDO::FETCH_ASSOC) as $tipRow) {
            $tips[(int)$tipRow['operator']] = (float)$tipRow['total_bacsis'];
        }

        $line = str_repeat('=', 42) . "\n";
        $out = $line;
        $out .= "RAPORT Z PRODUSE VÂNDUTE\n";
        $out .= $line;
        $out .= 'Nr. raport Z: ' . $reportNumber . "\n";
        $out .= 'Generat: ' . date('d.m.Y H:i:s') . "\n";
        $out .= 'Locația: ' . $locationId . "\n";
        $out .= 'De la: ' . $interval['first_date'] . ' ' . $interval['first_time'] . "\n";
        $out .= 'Până la: ' . $interval['last_date'] . ' ' . $interval['last_time'] . "\n";
        if ($separateProtocol) {
            $out .= "Notele PROTOCOL sunt pe foaia separată.\n";
        }
        $out .= $line;

        $totalProducts = 0.0;
        foreach ($products as $product) {
            $productLine = agecs_z_number((float)$product['cantitate'], 3)
                . ' X ' . (string)$product['produs']
                . ' - ' . agecs_z_number((float)$product['valoare']) . ' LEI';
            foreach (agecs_z_wrap($productLine) as $wrapped) {
                $out .= $wrapped . "\n";
            }
            $totalProducts += (float)$product['valoare'];
        }
        if (!$products) {
            $out .= "Fără produse în afara notelor PROTOCOL.\n";
        }
        $out .= str_repeat('-', 42) . "\n";
        $out .= 'TOTAL PRODUSE: ' . agecs_z_number($totalProducts) . " LEI\n";
        $out .= $line;

        $out .= "ÎNCASĂRI PE OSPĂTAR\n";
        $out .= $line;
        $grand = ['numerar' => 0.0, 'card' => 0.0, 'tichete' => 0.0, 'protocol' => 0.0, 'glovo' => 0.0, 'virament_bancar' => 0.0, 'bacsis' => 0.0];
        foreach ($payments as $payment) {
            $operatorId = (int)$payment['operator'];
            foreach (agecs_z_wrap('OSPĂTAR: ' . agecs_z_operator_label($payment)) as $wrapped) {
                $out .= $wrapped . "\n";
            }

            $labels = [
                'numerar' => 'NUMERAR',
                'card' => 'CARD',
                'tichete' => 'TICHETE',
                'protocol' => 'PROTOCOL',
                'glovo' => 'ONLINE',
                'virament_bancar' => 'VIRAMENT',
            ];
            $operatorTotal = 0.0;
            foreach ($labels as $key => $label) {
                $amount = (float)$payment[$key];
                $grand[$key] += $amount;
                $operatorTotal += $amount;
                if (abs($amount) >= 0.005) {
                    $out .= $label . ': ' . agecs_z_number($amount) . " LEI\n";
                }
            }
            $tip = (float)($tips[$operatorId] ?? 0);
            $grand['bacsis'] += $tip;
            if (abs($tip) >= 0.005) {
                $out .= 'BACȘIȘ: ' . agecs_z_number($tip) . " LEI\n";
            }
            $out .= 'TOTAL OSPĂTAR: ' . agecs_z_number($operatorTotal) . " LEI\n";
            $out .= str_repeat('-', 42) . "\n";
        }

        $grandIncome = $grand['numerar'] + $grand['card'] + $grand['tichete']
            + $grand['protocol'] + $grand['glovo'] + $grand['virament_bancar'];
        $out .= 'TOTAL ÎNCASĂRI: ' . agecs_z_number($grandIncome) . " LEI\n";
        $out .= 'TOTAL NUMERAR: ' . agecs_z_number($grand['numerar']) . " LEI\n";
        $out .= 'TOTAL CARD: ' . agecs_z_number($grand['card']) . " LEI\n";
        $out .= 'TOTAL TICHETE: ' . agecs_z_number($grand['tichete']) . " LEI\n";
        if (!$separateProtocol) {
            $out .= 'TOTAL PROTOCOL: ' . agecs_z_number($grand['protocol']) . " LEI\n";
        }
        $out .= 'TOTAL ONLINE: ' . agecs_z_number($grand['glovo']) . " LEI\n";
        $out .= 'TOTAL VIRAMENT: ' . agecs_z_number($grand['virament_bancar']) . " LEI\n";
        $out .= 'TOTAL BACȘIȘ: ' . agecs_z_number($grand['bacsis']) . " LEI\n";
        $out .= $line;

        return $out;
    }
}

if (!function_exists('agecs_z_protocol_report')) {
    function agecs_z_protocol_report(PDO $pdo, int $locationId, int $reportNumber): ?string
    {
        $params = ['loc' => $locationId, 'rz' => $reportNumber];
        $stmt = $pdo->prepare("\n            SELECT\n                n.nrbon,\n                n.operator,\n                n.protocol,\n                a.admin_firstname,\n                a.admin_lastname,\n                COALESCE(NULLIF(TRIM(dn.departament_listare), ''), NULLIF(TRIM(ps.departament), ''), 'FĂRĂ DEPARTAMENT') AS departament,\n                COALESCE(NULLIF(TRIM(dn.nume_produs), ''), NULLIF(TRIM(ps.nume), ''), 'Produs fără denumire') AS produs,\n                COALESCE(dn.cantitate, 0) AS cantitate,\n                COALESCE(dn.valoare_vanzare_cu_tva, 0) AS valoare\n            FROM note n\n            INNER JOIN det_note dn ON dn.nr_bon = n.nrbon\n            LEFT JOIN produse_servicii ps ON ps.cod_produs = dn.cod_p\n            LEFT JOIN admins_12 a ON a.admin_id = n.operator\n            WHERE n.locatie = :loc\n              AND n.status = 'F'\n              AND n.nr_raport_z = :rz\n              AND COALESCE(n.protocol, 0) > 0\n            ORDER BY departament, produs, n.nrbon\n        ");
        $stmt->execute($params);
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
                $department = trim($department);
                if ($department !== '') {
                    $departments[strtoupper($department)] = strtoupper($department);
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
                    $operators[$operatorId] = [
                        'label' => agecs_z_operator_label($row),
                        'total' => 0.0,
                        'notes' => 0,
                    ];
                }
                $operators[$operatorId]['total'] += (float)$row['protocol'];
                $operators[$operatorId]['notes']++;
            }
        }

        uasort($groups, static fn(array $left, array $right): int => strnatcasecmp($left['label'], $right['label']));
        uasort($operators, static fn(array $left, array $right): int => strnatcasecmp($left['label'], $right['label']));
        $interval = agecs_z_interval($pdo, $locationId, $reportNumber);
        $line = str_repeat('=', 42) . "\n";
        $out = $line;
        $out .= "RAPORT PRODUSE PROTOCOL\n";
        $out .= $line;
        $out .= 'Nr. raport Z: ' . $reportNumber . "\n";
        $out .= 'Generat: ' . date('d.m.Y H:i:s') . "\n";
        $out .= 'Locația: ' . $locationId . "\n";
        $out .= 'De la: ' . $interval['first_date'] . ' ' . $interval['first_time'] . "\n";
        $out .= 'Până la: ' . $interval['last_date'] . ' ' . $interval['last_time'] . "\n";
        $out .= 'Note PROTOCOL: ' . count($notes) . "\n";
        $out .= $line;

        $generalQuantity = 0.0;
        $generalValue = 0.0;
        $departmentIndex = 0;
        foreach ($groups as $group) {
            $departmentIndex++;
            foreach (agecs_z_wrap($departmentIndex . '. ' . $group['label']) as $wrapped) {
                $out .= $wrapped . "\n";
            }
            $out .= str_repeat('-', 42) . "\n";
            uasort($group['products'], static fn(array $left, array $right): int => strnatcasecmp($left['label'], $right['label']));
            foreach ($group['products'] as $product) {
                $productLine = agecs_z_number((float)$product['quantity'], 3)
                    . ' X ' . $product['label']
                    . ' - ' . agecs_z_number((float)$product['value']) . ' LEI';
                foreach (agecs_z_wrap($productLine) as $wrapped) {
                    $out .= $wrapped . "\n";
                }
            }
            $out .= 'TOTAL ' . $group['label'] . ': '
                . agecs_z_number((float)$group['quantity'], 3) . ' | '
                . agecs_z_number((float)$group['value']) . " LEI\n";
            $out .= $line;
            $generalQuantity += (float)$group['quantity'];
            $generalValue += (float)$group['value'];
        }

        $out .= 'TOTAL PRODUSE PROTOCOL: ' . agecs_z_number($generalQuantity, 3)
            . ' | ' . agecs_z_number($generalValue) . " LEI\n";
        $out .= $line;
        $out .= "PROTOCOL PE OSPĂTAR\n";
        $out .= $line;
        foreach ($operators as $operator) {
            foreach (agecs_z_wrap('OSPĂTAR: ' . $operator['label']) as $wrapped) {
                $out .= $wrapped . "\n";
            }
            $out .= 'NOTE: ' . (int)$operator['notes'] . "\n";
            $out .= 'PROTOCOL: ' . agecs_z_number((float)$operator['total']) . " LEI\n";
            $out .= str_repeat('-', 42) . "\n";
        }
        $out .= 'TOTAL PROTOCOL: ' . agecs_z_number(array_sum($notes)) . " LEI\n";
        $out .= $line;

        return $out;
    }
}

if (!function_exists('agecs_z_print_documents')) {
    function agecs_z_print_documents(PDO $pdo, int $clientId, int $locationId, int $reportNumber): array
    {
        $documents = [[
            'id' => 0,
            'data' => date('Y-m-d'),
            'ora' => date('H:i:s'),
            'de_trimis_la_imprimanta' => 1,
            'nrbon' => 0,
            'locatie' => $locationId,
            'departament_listare' => 'BAR',
            'continut' => agecs_z_regular_report($pdo, $clientId, $locationId, $reportNumber),
        ]];

        if (in_array($clientId, [1008, 1021], true)) {
            $protocolContent = agecs_z_protocol_report($pdo, $locationId, $reportNumber);
            if ($protocolContent !== null) {
                $documents[] = [
                    'id' => 0,
                    'data' => date('Y-m-d'),
                    'ora' => date('H:i:s'),
                    'de_trimis_la_imprimanta' => 1,
                    'nrbon' => 0,
                    'locatie' => $locationId,
                    'departament_listare' => 'BAR',
                    'continut' => $protocolContent,
                ];
            }
        }

        return $documents;
    }
}
