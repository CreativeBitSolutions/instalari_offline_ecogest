<?php
declare(strict_types=1);

if (!function_exists('restaurant_sql_identifier')) {
    function restaurant_sql_identifier(string $identifier): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
            throw new InvalidArgumentException('Identificator SQL invalid: ' . $identifier);
        }

        return $identifier;
    }
}

if (!function_exists('restaurant_build_totaluri_plata_payload')) {
    function restaurant_build_totaluri_plata_payload(
        PDO $pdo,
        string $noteTable,
        string $detNoteTable,
        int $codInchidere,
        int $locatie,
        int $operatorId,
        string $generatLa
    ): ?array {
        $noteTable = restaurant_sql_identifier($noteTable);
        $detNoteTable = restaurant_sql_identifier($detNoteTable);

        $operatorWhere = $operatorId > 0 ? ' AND operator = :operator' : '';
        $params = [
            ':cod_inchidere' => $codInchidere,
            ':locatie' => $locatie,
        ];
        if ($operatorId > 0) {
            $params[':operator'] = $operatorId;
        }

        $stmtTotals = $pdo->prepare("
            SELECT
                COUNT(*) AS note_count,
                COALESCE(SUM(numerar), 0) AS numerar,
                COALESCE(SUM(card), 0) AS card,
                COALESCE(SUM(tichete), 0) AS tichete,
                COALESCE(SUM(protocol), 0) AS protocol,
                COALESCE(SUM(glovo), 0) AS glovo,
                COALESCE(SUM(virament_bancar), 0) AS virament_bancar,
                MIN(nrbon) AS nrbon_min,
                MAX(nrbon) AS nrbon_max
            FROM {$noteTable}
            WHERE cod_inchidere = :cod_inchidere
              AND locatie = :locatie{$operatorWhere}
        ");
        $stmtTotals->execute($params);
        $totals = $stmtTotals->fetch(PDO::FETCH_ASSOC) ?: [];

        if ((int)($totals['note_count'] ?? 0) <= 0) {
            return null;
        }

        $stmtTip = $pdo->prepare("
            SELECT
                COALESCE(SUM(d.pret_vanzare), 0) AS total_bacsis,
                COALESCE(SUM(CASE
                    WHEN n.card > 0 AND n.numerar <= 0 THEN d.pret_vanzare
                    WHEN n.card > 0 AND n.numerar > 0 THEN d.pret_vanzare * (n.card / NULLIF(n.card + n.numerar, 0))
                    ELSE 0
                END), 0) AS bacsis_card,
                COALESCE(SUM(CASE
                    WHEN n.numerar > 0 AND n.card <= 0 THEN d.pret_vanzare
                    WHEN n.card > 0 AND n.numerar > 0 THEN d.pret_vanzare * (n.numerar / NULLIF(n.card + n.numerar, 0))
                    ELSE 0
                END), 0) AS bacsis_numerar
            FROM {$detNoteTable} d
            JOIN {$noteTable} n ON d.nr_bon = n.nrbon
            WHERE d.cod_p = -1
              AND n.cod_inchidere = :cod_inchidere
              AND n.locatie = :locatie{$operatorWhere}
        ");
        $stmtTip->execute($params);
        $bacsis = $stmtTip->fetch(PDO::FETCH_ASSOC) ?: [];

        $nrbonMin = isset($totals['nrbon_min']) ? (int)$totals['nrbon_min'] : 0;
        $nrbonMax = isset($totals['nrbon_max']) ? (int)$totals['nrbon_max'] : 0;
        $intervalNote = ($nrbonMin > 0 && $nrbonMax > 0) ? ($nrbonMin . '-' . $nrbonMax) : '-';

        return [
            'numerar' => round((float)($totals['numerar'] ?? 0), 2),
            'card' => round((float)($totals['card'] ?? 0), 2),
            'tichete' => round((float)($totals['tichete'] ?? 0), 2),
            'protocol' => round((float)($totals['protocol'] ?? 0), 2),
            'glovo' => round((float)($totals['glovo'] ?? 0), 2),
            'virament_bancar' => round((float)($totals['virament_bancar'] ?? 0), 2),
            'bacsis_numerar' => round((float)($bacsis['bacsis_numerar'] ?? 0), 2),
            'bacsis_card' => round((float)($bacsis['bacsis_card'] ?? 0), 2),
            'bacsis' => round((float)($bacsis['total_bacsis'] ?? 0), 2),
            'cod_inchidere' => $codInchidere,
            'nrbon_min' => $nrbonMin,
            'nrbon_max' => $nrbonMax,
            'interval_note' => $intervalNote,
            'operator_id' => $operatorId,
            'locatie' => $locatie,
            'generat_la' => $generatLa,
        ];
    }
}

if (!function_exists('restaurant_build_totaluri_plata_json')) {
    function restaurant_build_totaluri_plata_json(
        PDO $pdo,
        string $noteTable,
        string $detNoteTable,
        int $codInchidere,
        int $locatie,
        int $operatorId,
        string $generatLa
    ): ?string {
        $payload = restaurant_build_totaluri_plata_payload(
            $pdo,
            $noteTable,
            $detNoteTable,
            $codInchidere,
            $locatie,
            $operatorId,
            $generatLa
        );

        if ($payload === null) {
            return null;
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json === false ? null : $json;
    }
}
