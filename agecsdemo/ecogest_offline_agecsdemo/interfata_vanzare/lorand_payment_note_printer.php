<?php
declare(strict_types=1);

require_once __DIR__ . '/printer_format_helper.php';
require_once __DIR__ . '/printer_queue_helper.php';
require_once __DIR__ . '/setari_lorand_schema.php';

if (!function_exists('lorand_payment_note_number')) {
    function lorand_payment_note_number($value, int $decimals = 2): string
    {
        return number_format((float)$value, $decimals, ',', '.');
    }
}

if (!function_exists('lorand_payment_note_wrap')) {
    function lorand_payment_note_wrap(string $text, int $width): array
    {
        $text = trim((string)preg_replace('/\s+/', ' ', $text));
        if ($text === '') {
            return [''];
        }
        return explode("\n", wordwrap($text, max(24, $width), "\n", true));
    }
}

if (!function_exists('lorand_payment_note_build')) {
    function lorand_payment_note_build(PDO $pdo, string $detTable, string $productsTable, string $adminsTable, int $nrBon, int $clientId): string
    {
        $config = agecs_printer_client_format_config($clientId);
        $width = max(24, min(80, (int)($config['width_chars'] ?? 42)));
        $separator = str_repeat('-', $width);

        $noteStmt = $pdo->prepare(
            "SELECT n.data_bon, n.ora_bon, n.operator, n.numerar, n.card, n.tichete, n.glovo, n.protocol, n.rest,\n"
            . "       a.admin_firstname, a.admin_lastname\n"
            . "FROM note n\n"
            . "LEFT JOIN {$adminsTable} a ON a.admin_id = n.operator\n"
            . "WHERE n.nrbon = :nrbon LIMIT 1"
        );
        $noteStmt->execute([':nrbon' => $nrBon]);
        $note = $noteStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $productStmt = $pdo->prepare(
            "SELECT COALESCE(NULLIF(TRIM(dn.nume_produs), ''), ps.nume) AS nume,\n"
            . "       COALESCE(dn.cantitate, 0) AS cantitate,\n"
            . "       COALESCE(ps.um, '') AS um,\n"
            . "       COALESCE(dn.pret_vanzare, 0) AS pret_vanzare,\n"
            . "       COALESCE(dn.valoare_vanzare_cu_tva, 0) AS valoare,\n"
            . "       COALESCE(dn.discount, 0) AS discount\n"
            . "FROM {$detTable} dn\n"
            . "LEFT JOIN {$productsTable} ps ON ps.cod_produs = dn.cod_p\n"
            . "WHERE dn.nr_bon = :nrbon\n"
            . "ORDER BY dn.id_vanz ASC"
        );
        $productStmt->execute([':nrbon' => $nrBon]);
        $products = $productStmt->fetchAll(PDO::FETCH_ASSOC);

        $operator = trim((string)($note['admin_firstname'] ?? '') . ' ' . (string)($note['admin_lastname'] ?? ''));
        if ($operator === '') {
            $operator = 'ID ' . (int)($note['operator'] ?? 0);
        }

        $lines = [
            'AGECSDEMO',
            'NOTA DE PLATA',
            $separator,
            'Nr. nota: ' . $nrBon,
            'Data: ' . trim((string)($note['data_bon'] ?? '') . ' ' . (string)($note['ora_bon'] ?? '')),
            'Operator: ' . $operator,
            $separator,
        ];

        $total = 0.0;
        foreach ($products as $product) {
            $quantity = (float)($product['cantitate'] ?? 0);
            $price = (float)($product['pret_vanzare'] ?? 0);
            $value = (float)($product['valoare'] ?? ($quantity * $price));
            $discount = (float)($product['discount'] ?? 0);
            $net = $value - $discount;
            $total += $net;

            foreach (lorand_payment_note_wrap((string)($product['nume'] ?? 'Produs'), $width) as $nameLine) {
                $lines[] = $nameLine;
            }
            $unit = trim((string)($product['um'] ?? ''));
            if (strcasecmp($unit, 'H87') === 0) {
                $unit = 'BUC';
            }
            $detail = lorand_payment_note_number($quantity, abs($quantity - round($quantity)) < 0.000001 ? 0 : 3)
                . ($unit !== '' ? ' ' . $unit : '')
                . ' x ' . lorand_payment_note_number($price)
                . ' = ' . lorand_payment_note_number($net) . ' LEI';
            $lines[] = $detail;
            if ($discount > 0.00001) {
                $lines[] = 'Discount: ' . lorand_payment_note_number($discount) . ' LEI';
            }
        }

        $lines[] = $separator;
        $lines[] = 'TOTAL: ' . lorand_payment_note_number($total) . ' LEI';

        $paymentLabels = [
            'numerar' => 'Numerar',
            'card' => 'Card',
            'tichete' => 'Tichete',
            'glovo' => 'Online',
            'protocol' => 'Protocol',
        ];
        foreach ($paymentLabels as $field => $label) {
            $value = (float)($note[$field] ?? 0);
            if (abs($value) > 0.00001) {
                $lines[] = $label . ': ' . lorand_payment_note_number($value) . ' LEI';
            }
        }
        $rest = (float)($note['rest'] ?? 0);
        if (abs($rest) > 0.00001) {
            $lines[] = 'Rest: ' . lorand_payment_note_number($rest) . ' LEI';
        }
        $lines[] = $separator;

        return agecs_printer_bold_content(implode("\n", $lines), $clientId);
    }
}

if (!function_exists('lorand_payment_note_enqueue')) {
    function lorand_payment_note_enqueue(PDO $pdo, string $detTable, string $productsTable, string $adminsTable, int $nrBon, int $clientId, int $locationId): string
    {
        if ($clientId !== 8) {
            throw new InvalidArgumentException('Listarea automată AGECSDEMO este disponibilă numai pentru clientul 8.');
        }

        if (!vanzare_v2_ensure_lorand_offline_schema($pdo)) {
            throw new RuntimeException('Schema locală pentru coada imprimantei nu a putut fi pregătită.');
        }

        $documentKey = 'nota_plata:' . $locationId . ':' . $nrBon;
        $insert = $pdo->prepare(
            'INSERT OR IGNORE INTO lorand_printer_queue_history
                (document_key, nrbon, locatie, status, updated_at)
             VALUES (:document_key, :nrbon, :locatie, \'preparing\', CURRENT_TIMESTAMP)'
        );
        $insert->execute([
            ':document_key' => $documentKey,
            ':nrbon' => $nrBon,
            ':locatie' => $locationId,
        ]);

        if ($insert->rowCount() === 0) {
            $existing = $pdo->prepare('SELECT status, queue_file FROM lorand_printer_queue_history WHERE document_key = :document_key LIMIT 1');
            $existing->execute([':document_key' => $documentKey]);
            $row = $existing->fetch(PDO::FETCH_ASSOC) ?: [];
            if ((string)($row['status'] ?? '') === 'queued') {
                return (string)($row['queue_file'] ?? '');
            }
        }

        try {
            $config = agecs_printer_client_format_config($clientId);
            $copies = max(1, min(5, (int)($config['copies'] ?? 2)));
            $destination = 'BAR';
            $content = lorand_payment_note_build($pdo, $detTable, $productsTable, $adminsTable, $nrBon, $clientId);

            $jobs = [];
            for ($copy = 1; $copy <= $copies; $copy++) {
                $jobs[] = [
                    'id' => 0,
                    'data' => date('Y-m-d'),
                    'ora' => date('H:i:s'),
                    'de_trimis_la_imprimanta' => 1,
                    'nrbon' => $nrBon,
                    'locatie' => $locationId,
                    'departament_listare' => $destination,
                    'continut' => $content,
                ];
            }

            $queuePath = agecs_printer_enqueue_payload($clientId, $locationId, [
                'status' => 'success',
                'message' => 'Nota de plată AGECSDEMO a fost pregătită pentru imprimanta BAR.',
                'data' => $jobs,
            ], 'nota_plata_' . $nrBon, 'nota_plata_' . $locationId . '_' . $nrBon);

            $update = $pdo->prepare(
                'UPDATE lorand_printer_queue_history
                    SET status = \'queued\', queue_file = :queue_file, updated_at = CURRENT_TIMESTAMP
                  WHERE document_key = :document_key'
            );
            $update->execute([':queue_file' => $queuePath, ':document_key' => $documentKey]);
            return $queuePath;
        } catch (Throwable $e) {
            $delete = $pdo->prepare(
                'DELETE FROM lorand_printer_queue_history
                  WHERE document_key = :document_key AND status = \'preparing\''
            );
            $delete->execute([':document_key' => $documentKey]);
            throw $e;
        }
    }
}
