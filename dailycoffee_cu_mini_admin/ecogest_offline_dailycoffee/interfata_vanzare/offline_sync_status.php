<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/database_connection.php';
require_once __DIR__ . '/offline_sync_queue_lib.php';

function offline_sync_status_response(int $code, array $payload): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function offline_sync_status_local_time($value): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    try {
        $date = new DateTime($value, new DateTimeZone('UTC'));
        $date->setTimezone(new DateTimeZone('Europe/Bucharest'));
        return $date->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return $value;
    }
}

function offline_sync_status_pk(string $table): string
{
    $map = [
        'note' => 'nrbon',
        'det_note' => 'id_vanz',
        'discounturi_acordate' => 'id_discount',
        'bonuri_casa_marcat' => 'id',
        'inchideri_r_12' => 'id_inch',
        'rapoarte_z' => 'id',
        'nir' => 'id_nir',
        'achizitii' => 'id_achiz',
        'miscari' => 'id',
        'log_reglari_casa_marcat' => 'id',
    ];
    return $map[$table] ?? 'id';
}

function offline_sync_status_allowed_fields(string $table): array
{
    $common = ['identificator_offline', 'cod_locatie', 'locatie'];
    $fields = [
        'note' => ['nrbon', 'data_bon', 'ora_bon', 'operator', 'valoare_vanzare_cu_tva', 'tva_colectata', 'discount', 'numerar', 'card', 'cod_inchidere', 'nr_raport_z', 'nui', 'serie_memorie_fiscala'],
        'det_note' => ['id_vanz', 'nr_bon', 'cod_p', 'nume_produs', 'cantitate', 'pret_vanzare', 'valoare_vanzare_cu_tva', 'discount', 'cota_tva'],
        'discounturi_acordate' => ['id_discount', 'id_vanz', 'id_operator', 'valoare_discount', 'procent_discount', 'data', 'ora'],
        'bonuri_casa_marcat' => ['id', 'nrbon', 'data', 'ora', 'de_trimis_la_casa_marcat'],
        'inchideri_r_12' => ['id_inch', 'cod_inchidere', 'operator', 'data_inchiderii', 'ora_inchiderii', 'valoare_cu_tva', 'tva_colectata', 'nr_raport_z', 'nui', 'serie_memorie_fiscala'],
        'rapoarte_z' => ['id', 'nr_raport_z', 'data_ora_raport_z', 'serie_casa_marcat', 'nui', 'serie_memorie_fiscala', 'numerar', 'card', 'credit', 'tichete_masa', 'tichete_valorice', 'plata_moderna', 'alte_metode'],
        'nir' => ['id_nir', 'nr_nir', 'data_nir', 'ora_nir', 'furnizor', 'nr_factura', 'valoare_cu_tva', 'tva', 'status'],
        'achizitii' => ['id_achiz', 'nr_nir', 'cod_produs', 'nume_produs', 'cantitate', 'pret_achizitie', 'valoare_cu_tva', 'cota_tva'],
        'miscari' => ['id', 'fel_doc', 'nr_doc', 'nr_nota', 'nr_raport_z', 'nui', 'serie_memorie_fiscala', 'cod_p', 'denumire_produs', 'nume_produs', 'tip_miscare', 'cantitate_misc', 'cantitate', 'pu', 'pret_achizitie', 'pret_vanzare', 'gestiune', 'data'],
        'log_reglari_casa_marcat' => ['id', 'admin_id', 'tip_reglare', 'suma', 'motiv', 'data', 'ora'],
    ];
    return array_values(array_unique(array_merge($fields[$table] ?? [], $common)));
}

function offline_sync_status_sanitize_row(string $table, array $row): array
{
    $result = [];
    foreach (offline_sync_status_allowed_fields($table) as $field) {
        if (array_key_exists($field, $row)) {
            $result[$field] = trim((string)$row[$field]);
        }
    }
    if ($table === 'miscari') {
        if (trim((string)($result['denumire_produs'] ?? '')) === '') {
            $result['denumire_produs'] = trim((string)($result['nume_produs'] ?? ''));
        }
        if (trim((string)($result['cantitate_misc'] ?? '')) === '') {
            $result['cantitate_misc'] = trim((string)($result['cantitate'] ?? ''));
        }
    }
    return $result;
}

function offline_sync_status_operator_names(PDO $pdo): array
{
    $names = [];
    if (!offline_sync_queue_table_exists($pdo, 'admins_12')) {
        return $names;
    }

    $rows = $pdo->query('SELECT admin_id, admin_firstname, admin_lastname FROM admins_12')
        ->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $id = trim((string)($row['admin_id'] ?? ''));
        if ($id === '') {
            continue;
        }
        $name = trim(trim((string)($row['admin_firstname'] ?? '')) . ' ' . trim((string)($row['admin_lastname'] ?? '')));
        $names[$id] = $name !== '' ? $name : 'Operator #' . $id;
    }
    return $names;
}

function offline_sync_status_add_operator_names(array &$items, array $operatorNames): void
{
    $operatorFields = [
        'note' => 'operator',
        'inchideri_r_12' => 'operator',
        'discounturi_acordate' => 'id_operator',
        'log_reglari_casa_marcat' => 'admin_id',
    ];
    foreach ($operatorFields as $table => $field) {
        if (empty($items[$table])) {
            continue;
        }
        foreach ($items[$table] as &$item) {
            $id = trim((string)($item['data'][$field] ?? ''));
            $item['data']['operator_nume'] = $id !== ''
                ? ($operatorNames[$id] ?? 'Operator #' . $id)
                : '';
        }
        unset($item);
    }
}

function offline_sync_status_compare_desc($leftValue, $rightValue, bool $numeric = false): int
{
    if ($numeric) {
        return (int)$rightValue <=> (int)$leftValue;
    }

    return strcmp(trim((string)$rightValue), trim((string)$leftValue));
}

function offline_sync_status_compare_rows(string $table, array $left, array $right): int
{
    $leftData = (array)($left['data'] ?? []);
    $rightData = (array)($right['data'] ?? []);
    $sortFields = [
        'note' => [
            ['nrbon', true],
            ['ora_bon', false],
            ['data_bon', false],
        ],
        'det_note' => [
            ['nr_bon', true],
            ['id_vanz', true],
        ],
        'discounturi_acordate' => [
            ['data', false],
            ['ora', false],
            ['id_discount', true],
        ],
        'bonuri_casa_marcat' => [
            ['nrbon', true],
            ['ora', false],
            ['data', false],
            ['id', true],
        ],
        'inchideri_r_12' => [
            ['cod_inchidere', true],
            ['ora_inchiderii', false],
            ['data_inchiderii', false],
            ['id_inch', true],
        ],
        'rapoarte_z' => [
            ['nr_raport_z', true],
            ['data_ora_raport_z', false],
            ['id', true],
        ],
        'nir' => [
            ['id_nir', true],
            ['data_nir', false],
            ['ora_nir', false],
        ],
        'achizitii' => [
            ['nr_nir', true],
            ['id_achiz', true],
        ],
        'miscari' => [
            ['id', true],
        ],
        'log_reglari_casa_marcat' => [
            ['id', true],
        ],
    ];

    foreach ($sortFields[$table] ?? [] as [$field, $numeric]) {
        $comparison = offline_sync_status_compare_desc(
            $leftData[$field] ?? '',
            $rightData[$field] ?? '',
            $numeric
        );
        if ($comparison !== 0) {
            return $comparison;
        }
    }

    return (int)($right['transmission']['event_id'] ?? 0)
        <=> (int)($left['transmission']['event_id'] ?? 0);
}

function offline_sync_status_extract_rows(array $events): array
{
    $items = [];
    foreach ($events as $event) {
        $xmlText = trim((string)($event['payload_xml'] ?? ''));
        if ($xmlText === '') {
            continue;
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlText, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$xml) {
            continue;
        }

        foreach ($xml->table as $tableNode) {
            $table = trim((string)$tableNode['name']);
            if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
                continue;
            }

            foreach ($tableNode->row as $rowNode) {
                $row = [];
                foreach ($rowNode->children() as $column => $value) {
                    $row[(string)$column] = (string)$value;
                }
                $row = offline_sync_status_sanitize_row($table, $row);
                if (!$row) {
                    continue;
                }

                $pk = offline_sync_status_pk($table);
                $identifier = trim((string)($row['identificator_offline'] ?? ''));
                $key = $identifier !== '' ? $identifier : trim((string)($row[$pk] ?? ''));
                if ($key === '') {
                    $key = sha1(json_encode($row));
                }

                $items[$table][$key] = [
                    'data' => $row,
                    'transmission' => [
                        'event_id' => (int)$event['id'],
                        'status' => (string)$event['status'],
                        'attempts' => (int)$event['attempts'],
                        'last_http_code' => $event['last_http_code'] !== null ? (int)$event['last_http_code'] : null,
                        'last_error' => trim((string)($event['last_error'] ?? '')),
                        'created_at' => offline_sync_status_local_time($event['created_at'] ?? null),
                        'sent_at' => offline_sync_status_local_time($event['sent_at'] ?? null),
                    ],
                ];
            }
        }
    }

    return $items;
}

function offline_sync_status_error_rows(array $events): array
{
    $errors = [];

    foreach ($events as $event) {
        $lastError = trim((string)($event['last_error'] ?? ''));
        if ($lastError === '') {
            continue;
        }

        $detailRowsByCode = [];
        $xmlText = trim((string)($event['payload_xml'] ?? ''));
        if ($xmlText !== '') {
            $previous = libxml_use_internal_errors(true);
            $xml = simplexml_load_string($xmlText, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            if ($xml) {
                foreach ($xml->table as $tableNode) {
                    if (trim((string)$tableNode['name']) !== 'det_note') {
                        continue;
                    }
                    foreach ($tableNode->row as $rowNode) {
                        $row = [];
                        foreach ($rowNode->children() as $column => $value) {
                            $row[(string)$column] = trim((string)$value);
                        }
                        $code = trim((string)($row['cod_p'] ?? ''));
                        if ($code !== '') {
                            $detailRowsByCode[$code][] = $row;
                        }
                    }
                }
            }
        }

        $missingCodes = [];
        if (preg_match_all('/\bcod[_\s]?p(?:\s+offline)?\s*(?:=|:)?\s*([0-9]+)/iu', $lastError, $matches)) {
            foreach ((array)($matches[1] ?? []) as $code) {
                $code = trim((string)$code);
                if ($code !== '') {
                    $missingCodes[$code] = true;
                }
            }
        }

        $eventBase = [
            'event_id' => (int)($event['id'] ?? 0),
            'event_uuid' => trim((string)($event['event_uuid'] ?? '')),
            'event_type' => trim((string)($event['event_type'] ?? '')),
            'aggregate_type' => trim((string)($event['aggregate_type'] ?? '')),
            'aggregate_id' => trim((string)($event['aggregate_id'] ?? '')),
            'cod_locatie' => (int)($event['cod_locatie'] ?? 0),
            'status' => trim((string)($event['status'] ?? '')),
            'attempts' => (int)($event['attempts'] ?? 0),
            'created_at' => offline_sync_status_local_time($event['created_at'] ?? null),
            'next_attempt_at' => offline_sync_status_local_time($event['next_attempt_at'] ?? null),
            'last_error' => $lastError,
        ];

        if ($missingCodes) {
            foreach (array_keys($missingCodes) as $code) {
                $detail = $detailRowsByCode[$code][0] ?? [];
                $productName = trim((string)($detail['nume_produs'] ?? ''));
                $bonNumber = trim((string)($detail['nr_bon'] ?? ''));
                $errors[] = array_merge($eventBase, [
                    'type' => 'missing_product',
                    'cod_produs' => $code,
                    'nume_produs' => $productName,
                    'nr_bon' => $bonNumber,
                    'instruction' => 'Creează în aplicația online produsul cu cod produs ' . $code . '. După salvare, păstrează același cod și lasă sincronizarea să reîncerce vânzarea.',
                ]);
            }
            continue;
        }

        $errors[] = array_merge($eventBase, [
            'type' => 'sync_error',
            'cod_produs' => '',
            'nume_produs' => '',
            'nr_bon' => '',
            'instruction' => 'Verifică mesajul de eroare, corectează cauza în aplicația online și lasă sincronizarea să reîncerce operațiunea.',
        ]);
    }

    return array_slice($errors, 0, 200);
}

if (empty($_SESSION['admin_id'])) {
    offline_sync_status_response(401, [
        'status' => 'error',
        'message' => 'Sesiunea operatorului nu mai este activa.'
    ]);
}

if (isset($_GET['indicator'])) {
    try {
        offline_sync_queue_ensure_schema($pdo);
        $transmissionCounts = offline_sync_queue_counts($pdo);
        $runtime = $pdo->query('SELECT last_tick_at, last_success_at, last_error FROM offline_sync_runtime WHERE id = 1')
            ->fetch(PDO::FETCH_ASSOC) ?: [];
        $errorCount = (int)$pdo->query("SELECT COUNT(*) FROM offline_sync_outbox
            WHERE status IN ('retry', 'blocked', 'sending')
              AND TRIM(COALESCE(last_error, '')) <> ''")->fetchColumn();
        $config = offline_sync_queue_config();
        offline_sync_status_response(200, [
            'status' => 'success',
            'client_id' => (int)$config['client_id'],
            'cod_locatie' => (int)$config['cod_locatie'],
            'counts' => $transmissionCounts,
            'transmission_counts' => $transmissionCounts,
            'error_count' => $errorCount,
            'error_event_count' => $errorCount,
            'runtime' => [
                'last_tick_at' => offline_sync_status_local_time($runtime['last_tick_at'] ?? null),
                'last_success_at' => offline_sync_status_local_time($runtime['last_success_at'] ?? null),
                'last_error' => trim((string)($runtime['last_error'] ?? '')),
            ],
            'generated_at' => (new DateTime('now', new DateTimeZone('Europe/Bucharest')))->format('Y-m-d H:i:s'),
        ]);
    } catch (Throwable $e) {
        offline_sync_status_response(500, [
            'status' => 'error',
            'message' => $e->getMessage(),
        ]);
    }
}

try {
    offline_sync_queue_ensure_schema($pdo);
    offline_sync_queue_discover($pdo, 100);

    $transmissionCounts = offline_sync_queue_counts($pdo);
    $runtime = $pdo->query('SELECT last_tick_at, last_success_at, last_error FROM offline_sync_runtime WHERE id = 1')
        ->fetch(PDO::FETCH_ASSOC) ?: [];

    $latestEvents = $pdo->query("SELECT o.*
        FROM offline_sync_outbox o
        INNER JOIN (
            SELECT aggregate_type, aggregate_id, MAX(id) AS latest_id
            FROM offline_sync_outbox
            GROUP BY aggregate_type, aggregate_id
        ) latest ON latest.latest_id = o.id
        ORDER BY o.id")->fetchAll(PDO::FETCH_ASSOC);

    $items = offline_sync_status_extract_rows($latestEvents);
    offline_sync_status_add_operator_names($items, offline_sync_status_operator_names($pdo));
    $elementCounts = ['pending' => 0, 'retry' => 0, 'sending' => 0, 'sent' => 0, 'blocked' => 0];
    $tables = [];
    foreach ($items as $table => $tableItems) {
        $rows = array_values($tableItems);
        usort($rows, static function (array $left, array $right) use ($table): int {
            return offline_sync_status_compare_rows($table, $left, $right);
        });
        foreach ($rows as $item) {
            $status = (string)$item['transmission']['status'];
            if (array_key_exists($status, $elementCounts)) {
                $elementCounts[$status]++;
            }
        }
        $tables[$table] = [
            'total' => count($rows),
            'rows' => array_slice($rows, 0, 500),
        ];
    }

    $events = $pdo->query("SELECT id, event_uuid, event_type, aggregate_type, aggregate_id,
            cod_locatie, status, attempts, next_attempt_at, last_http_code, last_error,
            created_at, sent_at
        FROM offline_sync_outbox
        ORDER BY id DESC
        LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($events as &$event) {
        $event['id'] = (int)$event['id'];
        $event['cod_locatie'] = (int)$event['cod_locatie'];
        $event['attempts'] = (int)$event['attempts'];
        $event['last_http_code'] = $event['last_http_code'] !== null ? (int)$event['last_http_code'] : null;
        $event['last_error'] = trim((string)($event['last_error'] ?? ''));
        $event['next_attempt_at'] = offline_sync_status_local_time($event['next_attempt_at'] ?? null);
        $event['created_at'] = offline_sync_status_local_time($event['created_at'] ?? null);
        $event['sent_at'] = offline_sync_status_local_time($event['sent_at'] ?? null);
    }
    unset($event);

    $errorEvents = $pdo->query("SELECT id, event_uuid, event_type, aggregate_type, aggregate_id,
            cod_locatie, status, attempts, next_attempt_at, last_error, created_at, payload_xml
        FROM offline_sync_outbox
        WHERE status IN ('retry', 'blocked', 'sending')
          AND TRIM(COALESCE(last_error, '')) <> ''
        ORDER BY id DESC
        LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    $errors = offline_sync_status_error_rows($errorEvents);

    $config = offline_sync_queue_config();
    offline_sync_status_response(200, [
        'status' => 'success',
        'client_id' => (int)$config['client_id'],
        'cod_locatie' => (int)$config['cod_locatie'],
        'counts' => $elementCounts,
        'transmission_counts' => $transmissionCounts,
        'tables' => $tables,
        'errors' => $errors,
        'error_count' => count($errors),
        'error_event_count' => count(array_unique(array_map(static function (array $error): int {
            return (int)($error['event_id'] ?? 0);
        }, $errors))),
        'runtime' => [
            'last_tick_at' => offline_sync_status_local_time($runtime['last_tick_at'] ?? null),
            'last_success_at' => offline_sync_status_local_time($runtime['last_success_at'] ?? null),
            'last_error' => trim((string)($runtime['last_error'] ?? '')),
        ],
        'events' => $events,
        'generated_at' => (new DateTime('now', new DateTimeZone('Europe/Bucharest')))->format('Y-m-d H:i:s'),
    ]);
} catch (Throwable $e) {
    offline_sync_status_response(500, [
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
