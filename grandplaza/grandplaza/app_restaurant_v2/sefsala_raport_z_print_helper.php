<?php
declare(strict_types=1);

if (!function_exists('sefsala_offline_z_jobs')) {
    function sefsala_offline_z_jobs(
        PDO $pdo,
        int $clientId,
        int $locationId,
        int $reportNumber,
        int $nui,
        string $memorySeries
    ): array {
        $excludeProtocol = in_array($clientId, [25, 26], true);
        $protocolFilter = $excludeProtocol ? ' AND COALESCE(n.protocol, 0) <= 0 ' : '';
        $params = [
            'loc' => $locationId,
            'rz' => $reportNumber,
            'nui' => $nui,
            'memory' => $memorySeries,
        ];

        $productStmt = $pdo->prepare("
            SELECT COALESCE(NULLIF(TRIM(dn.nume_produs), ''), NULLIF(TRIM(ps.nume), ''), 'Produs fără denumire') AS produs,
                   COALESCE(SUM(dn.cantitate), 0) AS cantitate,
                   COALESCE(SUM(dn.valoare_vanzare_cu_tva), 0) AS valoare
            FROM det_note dn
            JOIN note n ON n.nrbon = dn.nr_bon
            LEFT JOIN produse_servicii ps ON ps.cod_produs = dn.cod_p
            WHERE n.locatie = :loc
              AND n.status = 'F'
              AND n.nr_raport_z = :rz
              AND COALESCE(n.nui, 0) = :nui
              AND COALESCE(n.serie_memorie_fiscala, '') = :memory
              {$protocolFilter}
            GROUP BY COALESCE(NULLIF(TRIM(dn.nume_produs), ''), NULLIF(TRIM(ps.nume), ''), 'Produs fără denumire')
            ORDER BY produs
        ");
        $productStmt->execute($params);
        $products = $productStmt->fetchAll(PDO::FETCH_ASSOC);

        $paymentStmt = $pdo->prepare("
            SELECT n.operator,
                   COALESCE(SUM(n.numerar), 0) AS numerar,
                   COALESCE(SUM(n.card), 0) AS card,
                   COALESCE(SUM(n.tichete), 0) AS tichete,
                   COALESCE(SUM(n.protocol), 0) AS protocol,
                   COALESCE(SUM(n.glovo), 0) AS glovo,
                   COALESCE(SUM(n.virament_bancar), 0) AS virament_bancar
            FROM note n
            WHERE n.locatie = :loc
              AND n.status = 'F'
              AND n.nr_raport_z = :rz
              AND COALESCE(n.nui, 0) = :nui
              AND COALESCE(n.serie_memorie_fiscala, '') = :memory
              {$protocolFilter}
            GROUP BY n.operator
            ORDER BY n.operator
        ");
        $paymentStmt->execute($params);
        $payments = $paymentStmt->fetchAll(PDO::FETCH_ASSOC);

        $content = "RAPORT Z PRODUSE VÂNDUTE\n";
        $content .= "Nr. raport Z: {$reportNumber}\n";
        $content .= "Locația: {$locationId}\n";
        $content .= "Generat: " . date('d.m.Y H:i:s') . "\n";
        $content .= "====================\n";

        $totalProducts = 0.0;
        foreach ($products as $product) {
            $value = (float)$product['valoare'];
            $content .= number_format((float)$product['cantitate'], 3, ',', '.')
                . ' X ' . (string)$product['produs']
                . ' - ' . number_format($value, 2, ',', '.') . " LEI\n";
            $totalProducts += $value;
        }
        if (!$products) {
            $content .= "Fără produse de listat.\n";
        }
        $content .= "--------------------\n";
        $content .= 'TOTAL PRODUSE: ' . number_format($totalProducts, 2, ',', '.') . " LEI\n";
        $content .= "====================\n";
        $content .= "ÎNCASĂRI PE OSPĂTAR\n";

        $grand = ['numerar' => 0.0, 'card' => 0.0, 'tichete' => 0.0, 'protocol' => 0.0, 'glovo' => 0.0, 'virament_bancar' => 0.0];
        foreach ($payments as $payment) {
            $content .= 'OSPĂTAR ID ' . (int)$payment['operator'] . "\n";
            foreach ([
                'numerar' => 'NUMERAR',
                'card' => 'CARD',
                'tichete' => 'TICHETE',
                'protocol' => 'PROTOCOL',
                'glovo' => 'PLATĂ MODERNĂ (GLOVO)',
                'virament_bancar' => 'VIRAMENT',
            ] as $key => $label) {
                $amount = (float)$payment[$key];
                $grand[$key] += $amount;
                if (abs($amount) >= 0.005 && !($excludeProtocol && $key === 'protocol')) {
                    $content .= $label . ': ' . number_format($amount, 2, ',', '.') . " LEI\n";
                }
            }
            $content .= "--------------------\n";
        }

        $totalIncome = $grand['numerar'] + $grand['card'] + $grand['tichete']
            + $grand['protocol'] + $grand['glovo'] + $grand['virament_bancar'];
        $content .= 'TOTAL ÎNCASĂRI: ' . number_format($totalIncome, 2, ',', '.') . " LEI\n";
        $content .= 'TOTAL NUMERAR: ' . number_format($grand['numerar'], 2, ',', '.') . " LEI\n";
        $content .= 'TOTAL CARD: ' . number_format($grand['card'], 2, ',', '.') . " LEI\n";
        $content .= 'TOTAL TICHETE: ' . number_format($grand['tichete'], 2, ',', '.') . " LEI\n";
        if (!$excludeProtocol) {
            $content .= 'TOTAL PROTOCOL: ' . number_format($grand['protocol'], 2, ',', '.') . " LEI\n";
        }
        $content .= 'TOTAL PLATĂ MODERNĂ (GLOVO): ' . number_format($grand['glovo'], 2, ',', '.') . " LEI\n";
        $content .= 'TOTAL VIRAMENT: ' . number_format($grand['virament_bancar'], 2, ',', '.') . " LEI\n";

        return [[
            'id' => 0,
            'data' => date('Y-m-d'),
            'ora' => date('H:i:s'),
            'de_trimis_la_imprimanta' => 1,
            'nrbon' => -($reportNumber * 10 + 2),
            'locatie' => $locationId,
            'departament_listare' => 'BAR',
            'continut' => $content,
        ]];
    }
}

if (!function_exists('sefsala_offline_append_jobs')) {
    function sefsala_offline_append_jobs(string $queuePath, array $jobs): bool
    {
        $handle = @fopen($queuePath, 'c+');
        if ($handle === false) {
            return false;
        }
        try {
            if (!flock($handle, LOCK_EX)) {
                return false;
            }
            rewind($handle);
            $raw = stream_get_contents($handle);
            $payload = is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : [];
            $existing = is_array($payload) && isset($payload['data']) && is_array($payload['data'])
                ? $payload['data']
                : [];
            $encoded = json_encode([
                'status' => 'success',
                'message' => 'Nota de închidere a turei și raportul Z au fost trimise la imprimantă.',
                'data' => array_values(array_merge($existing, $jobs)),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded === false) {
                return false;
            }
            rewind($handle);
            if (!ftruncate($handle, 0) || fwrite($handle, $encoded) === false) {
                return false;
            }
            fflush($handle);
            return true;
        } finally {
            @flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}

