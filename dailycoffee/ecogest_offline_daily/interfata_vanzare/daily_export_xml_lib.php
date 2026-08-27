<?php
require_once __DIR__ . '/offline_external_config.php';

function daily_export_xml_config(): array
{
    $data = offline_config_all();
    $data['client_id'] = (int)$data['sync_client_id'];
    $data['cod_locatie'] = (int)$data['cod_locatie_default'];
    $data['installation_uuid'] = preg_replace('/[^A-Za-z0-9_-]/', '_', (string)($data['transaction_uuid'] ?? $data['installation_uuid']));
    if (empty($data['upload_url']) || empty($data['upload_key'])) {
        throw new RuntimeException('Configurarea trimiterii XML este invalida.');
    }

    return $data;
}

function daily_export_xml_dates(string $start, string $end): array
{
    $startDate = DateTime::createFromFormat('!Y-m-d', $start);
    $endDate = DateTime::createFromFormat('!Y-m-d', $end);
    if (!$startDate || !$endDate || $startDate->format('Y-m-d') !== $start || $endDate->format('Y-m-d') !== $end) {
        throw new InvalidArgumentException('Intervalul selectat este invalid.');
    }
    if ($startDate > $endDate) {
        [$startDate, $endDate] = [$endDate, $startDate];
    }

    return [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')];
}

function daily_export_xml_columns(PDO $pdo, string $table): array
{
    $stmt = $pdo->query('PRAGMA table_info(' . $table . ')');
    return array_map(static fn(array $row): string => (string)$row['name'], $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function daily_export_xml_escape($value): string
{
    return $value === null ? '' : htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function daily_export_xml_build(PDO $pdo, string $startRaw, string $endRaw): array
{
    [$dataStart, $dataEnd] = daily_export_xml_dates($startRaw, $endRaw);
    $config = daily_export_xml_config();
    $tables = [
        'note' => ["date(data_bon) BETWEEN :data_start AND :data_end", 'date(data_bon), nrbon'],
        'det_note' => ["date(data) BETWEEN :data_start AND :data_end", 'date(data), nr_bon, id_vanz'],
        'discounturi_acordate' => ["date(data_acordare) BETWEEN :data_start AND :data_end", 'date(data_acordare)'],
        'bonuri_casa_marcat' => ["date(data) BETWEEN :data_start AND :data_end", 'date(data), nrbon, id'],
        'inchideri_r_12' => ["date(data_inchiderii) BETWEEN :data_start AND :data_end", 'date(data_inchiderii), cod_inchidere, id_inch'],
        'rapoarte_z' => ["(date(data_ora_raport_z) BETWEEN :data_start AND :data_end OR nr_raport_z IN (SELECT nr_raport_z FROM inchideri_r_12 WHERE date(data_inchiderii) BETWEEN :data_start AND :data_end AND nr_raport_z <> 0))", 'date(data_ora_raport_z), nr_raport_z, id'],
        'nir' => ["date(data_nir) BETWEEN :data_start AND :data_end", 'date(data_nir), nr_nir'],
        'achizitii' => ["nr_nir IN (SELECT nr_nir FROM nir WHERE date(data_nir) BETWEEN :data_start AND :data_end)", 'nr_nir, id_achiz'],
        'miscari' => ["date(data) BETWEEN :data_start AND :data_end", 'date(data), id'],
        'log_reglari_casa_marcat' => ["date(data_operatiune) BETWEEN :data_start AND :data_end", 'date(data_operatiune), id'],
    ];

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<export client_id="' . (int)$config['client_id'] . '" cod_locatie="' . (int)$config['cod_locatie'] . '" offline_instance="' . daily_export_xml_escape($config['installation_uuid']) . '" perioada_start="' . $dataStart . '" perioada_end="' . $dataEnd . '" generated_at="' . date('Y-m-d H:i:s') . '">' . "\n";
    $params = [':data_start' => $dataStart, ':data_end' => $dataEnd];

    foreach ($tables as $table => [$where, $order]) {
        $columns = daily_export_xml_columns($pdo, $table);
        if (!$columns) {
            continue;
        }
        $stmt = $pdo->prepare('SELECT * FROM ' . $table . ' WHERE ' . $where . ' ORDER BY ' . $order);
        $stmt->execute($params);
        $xml .= '  <table name="' . $table . '">' . "\n";
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $xml .= "    <row>\n";
            foreach ($columns as $column) {
                $xml .= '      <' . $column . '>' . daily_export_xml_escape($row[$column] ?? null) . '</' . $column . ">\n";
            }
            $xml .= "    </row>\n";
        }
        $xml .= "  </table>\n";
    }

    $xml .= "</export>\n";
    return [
        'content' => $xml,
        'filename' => 'export_offline_client_2_' . $dataStart . '_' . $dataEnd . '.xml',
        'data_start' => $dataStart,
        'data_end' => $dataEnd,
        'client_id' => (int)$config['client_id'],
        'cod_locatie' => (int)$config['cod_locatie'],
        'upload_url' => (string)$config['upload_url'],
        'upload_key' => (string)$config['upload_key'],
    ];
}
