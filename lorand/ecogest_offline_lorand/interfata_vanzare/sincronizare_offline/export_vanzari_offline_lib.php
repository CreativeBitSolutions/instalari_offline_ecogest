<?php
// export_vanzari_offline_lib.php
require_once dirname(__DIR__) . '/offline_external_config.php';

function offline_export_app_config_value($key, $default)
{
    return offline_config_value((string)$key, $default);
}

function offline_export_escape_sql_value($value)
{
    if ($value === null || $value === '') {
        return $value === null ? 'NULL' : "''";
    }

    if (is_numeric($value) && !preg_match('/^0[0-9]+$/', (string)$value)) {
        return (string)$value;
    }

    $value = str_replace("'", "''", (string)$value);
    $value = str_replace(["\r", "\n"], ['\r', '\n'], $value);

    return "'" . $value . "'";
}

function offline_export_escape_xml_value($value)
{
    if ($value === null) {
        return '';
    }

    return htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function offline_export_get_table_columns(PDO $pdo, $table)
{
    $stmt = $pdo->query("PRAGMA table_info($table)");
    $cols = [];
    foreach ($stmt as $row) {
        $cols[] = $row['name'];
    }

    return $cols;
}

function offline_export_placeholders(array $values)
{
    return implode(',', array_fill(0, count($values), '?'));
}

function offline_export_fetch_rows(PDO $pdo, $table, $where, array $params, $order)
{
    $columns = offline_export_get_table_columns($pdo, $table);
    if (empty($columns)) {
        return [[], []];
    }

    $sql = "SELECT * FROM $table WHERE $where";
    if ($order !== '') {
        $sql .= " ORDER BY $order";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return [$columns, $stmt->fetchAll(PDO::FETCH_ASSOC)];
}

function offline_export_id_for_row($prefix, $table, array $row)
{
    $pkMap = [
        'note' => 'nrbon',
        'det_note' => 'id_vanz',
        'discounturi_acordate' => 'id_discount',
        'bonuri_casa_marcat' => 'id',
        'inchideri_r_12' => 'id_inch',
        'rapoarte_z' => 'id',
        'miscari' => 'id',
        'log_reglari_casa_marcat' => 'id',
    ];

    $pk = isset($pkMap[$table]) ? $pkMap[$table] : 'id';
    $value = isset($row[$pk]) ? $row[$pk] : md5(json_encode($row));

    return $prefix . '_' . $table . '_' . $value;
}

function offline_export_normalize_dates($dataStartRaw, $dataEndRaw)
{
    if (!$dataStartRaw || !$dataEndRaw) {
        throw new InvalidArgumentException('Trebuie selectate ambele date pentru interval.');
    }

    $dataStart = date('Y-m-d', strtotime($dataStartRaw));
    $dataEnd = date('Y-m-d', strtotime($dataEndRaw));

    if ($dataStart > $dataEnd) {
        $tmp = $dataStart;
        $dataStart = $dataEnd;
        $dataEnd = $tmp;
    }

    return [$dataStart, $dataEnd];
}

function offline_export_build(PDO $pdo, $dataStartRaw, $dataEndRaw, $formatRaw = 'xml')
{
    list($data_start, $data_end) = offline_export_normalize_dates($dataStartRaw, $dataEndRaw);

    $format = strtolower((string)$formatRaw);
    if ($format !== 'xml' && $format !== 'sql') {
        $format = 'xml';
    }

    $clientId = (int)offline_export_app_config_value('client_id', 1019);
    $codLocatie = isset($_SESSION['cod_locatie'])
        ? (int)$_SESSION['cod_locatie']
        : (int)offline_export_app_config_value('cod_locatie_default', 1);
    $installationUuid = preg_replace('/[^A-Za-z0-9_-]/', '_', (string)offline_export_app_config_value('transaction_uuid', offline_export_app_config_value('installation_uuid', 'client' . $clientId . '_loc' . $codLocatie . '_pos1')));
    $offlinePrefix = $installationUuid;

    $export = [];

    list($noteCols, $noteRows) = offline_export_fetch_rows(
        $pdo,
        'note',
        "status = 'F' AND date(data_bon) BETWEEN ? AND ?",
        [$data_start, $data_end],
        'date(data_bon), nrbon'
    );
    $export['note'] = ['columns' => $noteCols, 'rows' => $noteRows];

    $noteIds = array_values(array_filter(array_map(function ($row) {
        return isset($row['nrbon']) ? (int)$row['nrbon'] : null;
    }, $noteRows)));

    $detIds = [];
    $inchideriIds = [];
    $rapoarteZIds = [];

    if (!empty($noteIds)) {
        list($detCols, $detRows) = offline_export_fetch_rows(
            $pdo,
            'det_note',
            'nr_bon IN (' . offline_export_placeholders($noteIds) . ')',
            $noteIds,
            'date(data), nr_bon, id_vanz'
        );
        $export['det_note'] = ['columns' => $detCols, 'rows' => $detRows];

        $detIds = array_values(array_filter(array_map(function ($row) {
            return isset($row['id_vanz']) ? (int)$row['id_vanz'] : null;
        }, $detRows)));

        $inchideriIds = array_values(array_unique(array_filter(array_map(function ($row) {
            return isset($row['cod_inchidere']) ? (int)$row['cod_inchidere'] : null;
        }, $noteRows))));

        $rapoarteZIds = array_values(array_unique(array_filter(array_map(function ($row) {
            return isset($row['nr_raport_z']) ? (int)$row['nr_raport_z'] : null;
        }, $noteRows))));
    } else {
        foreach (['det_note', 'discounturi_acordate', 'bonuri_casa_marcat', 'inchideri_r_12', 'rapoarte_z', 'miscari'] as $table) {
            $export[$table] = ['columns' => offline_export_get_table_columns($pdo, $table), 'rows' => []];
        }
    }

    if (!isset($export['discounturi_acordate'])) {
        if (!empty($detIds)) {
            list($discCols, $discRows) = offline_export_fetch_rows(
                $pdo,
                'discounturi_acordate',
                'id_vanz IN (' . offline_export_placeholders($detIds) . ')',
                $detIds,
                'date(data_acordare), id_discount'
            );
        } else {
            $discCols = offline_export_get_table_columns($pdo, 'discounturi_acordate');
            $discRows = [];
        }
        $export['discounturi_acordate'] = ['columns' => $discCols, 'rows' => $discRows];
    }

    if (!isset($export['bonuri_casa_marcat'])) {
        list($bonCols, $bonRows) = offline_export_fetch_rows(
            $pdo,
            'bonuri_casa_marcat',
            'nrbon IN (' . offline_export_placeholders($noteIds) . ')',
            $noteIds,
            'date(data), nrbon, id'
        );
        $export['bonuri_casa_marcat'] = ['columns' => $bonCols, 'rows' => $bonRows];
    }

    if (!isset($export['inchideri_r_12'])) {
        if (!empty($inchideriIds)) {
            list($inchCols, $inchRows) = offline_export_fetch_rows(
                $pdo,
                'inchideri_r_12',
                'cod_inchidere IN (' . offline_export_placeholders($inchideriIds) . ')',
                $inchideriIds,
                'date(data_inchiderii), cod_inchidere, id_inch'
            );
            foreach ($inchRows as $row) {
                if (!empty($row['nr_raport_z'])) {
                    $rapoarteZIds[] = (int)$row['nr_raport_z'];
                }
            }
            $rapoarteZIds = array_values(array_unique(array_filter($rapoarteZIds)));
        } else {
            $inchCols = offline_export_get_table_columns($pdo, 'inchideri_r_12');
            $inchRows = [];
        }
        $export['inchideri_r_12'] = ['columns' => $inchCols, 'rows' => $inchRows];
    }

    if (!isset($export['rapoarte_z'])) {
        if (!empty($rapoarteZIds)) {
            list($zCols, $zRows) = offline_export_fetch_rows(
                $pdo,
                'rapoarte_z',
                'nr_raport_z IN (' . offline_export_placeholders($rapoarteZIds) . ')',
                $rapoarteZIds,
                'date(data_ora_raport_z), nr_raport_z, id'
            );
        } else {
            $zCols = offline_export_get_table_columns($pdo, 'rapoarte_z');
            $zRows = [];
        }
        $export['rapoarte_z'] = ['columns' => $zCols, 'rows' => $zRows];
    }

    if (!isset($export['miscari'])) {
        $miscCols = offline_export_get_table_columns($pdo, 'miscari');
        $miscRows = [];

        if (!empty($noteIds) || !empty($detIds)) {
            $whereParts = [];
            $params = [];

            if (!empty($noteIds)) {
                $whereParts[] = "(fel_doc = 'BF' AND nr_doc IN (" . offline_export_placeholders($noteIds) . '))';
                $params = array_merge($params, $noteIds);
                $whereParts[] = 'nr_nota IN (' . offline_export_placeholders($noteIds) . ')';
                $params = array_merge($params, $noteIds);
            }

            if (!empty($detIds)) {
                $whereParts[] = 'id_vanz_fact IN (' . offline_export_placeholders($detIds) . ')';
                $params = array_merge($params, $detIds);
            }

            list($miscCols, $miscRows) = offline_export_fetch_rows(
                $pdo,
                'miscari',
                '(' . implode(' OR ', $whereParts) . ')',
                $params,
                'date(data), id'
            );
        }

        $export['miscari'] = ['columns' => $miscCols, 'rows' => $miscRows];
    }

    list($logCols, $logRows) = offline_export_fetch_rows(
        $pdo,
        'log_reglari_casa_marcat',
        'date(data_operatiune) BETWEEN ? AND ?',
        [$data_start, $data_end],
        'date(data_operatiune), id'
    );
    $export['log_reglari_casa_marcat'] = ['columns' => $logCols, 'rows' => $logRows];

    $order = [
        'rapoarte_z',
        'inchideri_r_12',
        'note',
        'det_note',
        'discounturi_acordate',
        'bonuri_casa_marcat',
        'miscari',
        'log_reglari_casa_marcat',
    ];

    $now = date('Y-m-d H:i:s');
    $output = '';

    if ($format === 'sql') {
        $output .= "-- Export offline vanzari Bestmixt\n";
        $output .= "-- Client: {$clientId}\n";
        $output .= "-- Locatie: {$codLocatie}\n";
        $output .= "-- Perioada: {$data_start} - {$data_end}\n";
        $output .= "-- Generat la: {$now}\n\n";
        $output .= "BEGIN TRANSACTION;\n\n";
    } else {
        $output .= "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $output .= "<export client_id=\"" . offline_export_escape_xml_value($clientId) . "\" cod_locatie=\"" . offline_export_escape_xml_value($codLocatie) . "\" offline_instance=\"" . offline_export_escape_xml_value($offlinePrefix) . "\" perioada_start=\"" . offline_export_escape_xml_value($data_start) . "\" perioada_end=\"" . offline_export_escape_xml_value($data_end) . "\" generated_at=\"" . offline_export_escape_xml_value($now) . "\">\n";
    }

    foreach ($order as $table) {
        $columns = $export[$table]['columns'] ?? [];
        $rows = $export[$table]['rows'] ?? [];

        if (empty($columns)) {
            if ($format === 'sql') {
                $output .= "-- ATENTIE: Tabelul $table nu exista in baza de date curenta.\n\n";
            } else {
                $output .= "  <table name=\"" . offline_export_escape_xml_value($table) . "\" missing=\"1\" />\n";
            }
            continue;
        }

        $columnsWithIdentifier = $columns;
        if (!in_array('identificator_offline', $columnsWithIdentifier, true)) {
            $columnsWithIdentifier[] = 'identificator_offline';
        }

        if ($format === 'sql') {
            $output .= "-- Tabel: $table\n";
            if (!$rows) {
                $output .= "-- (Nicio inregistrare pentru perioada selectata)\n\n";
                continue;
            }

            $colsList = implode(', ', $columnsWithIdentifier);
            foreach ($rows as $row) {
                $row['identificator_offline'] = offline_export_id_for_row($offlinePrefix, $table, $row);
                $values = [];
                foreach ($columnsWithIdentifier as $col) {
                    $values[] = offline_export_escape_sql_value($row[$col] ?? null);
                }
                $output .= "INSERT INTO $table ($colsList) VALUES (" . implode(', ', $values) . ");\n";
            }
            $output .= "\n";
        } else {
            $output .= "  <table name=\"" . offline_export_escape_xml_value($table) . "\">\n";
            foreach ($rows as $row) {
                $row['identificator_offline'] = offline_export_id_for_row($offlinePrefix, $table, $row);
                $output .= "    <row>\n";
                foreach ($columnsWithIdentifier as $col) {
                    $val = offline_export_escape_xml_value($row[$col] ?? null);
                    $output .= "      <{$col}>{$val}</{$col}>\n";
                }
                $output .= "    </row>\n";
            }
            $output .= "  </table>\n";
        }
    }

    if ($format === 'sql') {
        $output .= "COMMIT;\n";
    } else {
        $output .= "</export>\n";
    }

    $ext = $format;
    $filename = "export_offline_lorand_{$data_start}_{$data_end}." . $ext;
    $contentType = $format === 'sql' ? 'application/sql' : 'application/xml';

    return [
        'content' => $output,
        'filename' => $filename,
        'content_type' => $contentType,
        'format' => $format,
        'data_start' => $data_start,
        'data_end' => $data_end,
        'client_id' => $clientId,
        'cod_locatie' => $codLocatie,
        'installation_uuid' => $installationUuid,
    ];
}
