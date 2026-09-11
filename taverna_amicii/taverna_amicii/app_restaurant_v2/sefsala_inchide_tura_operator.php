<?php //sefsala_inchide_tura_operator.php
declare(strict_types=1);
include('session.php');
require_once __DIR__ . '/totaluri_plata_helper.php';
require_once __DIR__ . '/offline_printer_flow_helper.php';
header('Content-Type: application/json; charset=utf-8');

date_default_timezone_set('Europe/Bucharest');

function json_exit(array $payload, int $httpCode = 200): void {
    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function sefsala_update_miscari_raport_z(PDO $pdo, int $nrRaportZ, int $nui, string $serieMemorieFiscala): void {
    $driver = '';
    try {
        $driver = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    } catch (Throwable $e) {
        $driver = '';
    }

    if ($driver === 'sqlite') {
        $pdo->prepare("
            UPDATE miscari
            SET nr_raport_z = (
                SELECT n.nr_raport_z
                FROM note n
                WHERE n.nrbon = miscari.nr_doc
                LIMIT 1
            ), nui = (
                SELECT COALESCE(n.nui, 0)
                FROM note n
                WHERE n.nrbon = miscari.nr_doc
                LIMIT 1
            ), serie_memorie_fiscala = (
                SELECT COALESCE(n.serie_memorie_fiscala, '')
                FROM note n
                WHERE n.nrbon = miscari.nr_doc
                LIMIT 1
            )
            WHERE tip_miscare = 'O'
              AND fel_doc = 'BF'
              AND EXISTS (
                  SELECT 1
                  FROM note n
                  WHERE n.nrbon = miscari.nr_doc
                    AND n.nr_raport_z = ?
                    AND (COALESCE(n.nui, 0) = ? OR COALESCE(n.nui, 0) = 0)
                    AND (COALESCE(n.serie_memorie_fiscala, '') = ? OR COALESCE(n.serie_memorie_fiscala, '') = '')
                    AND miscari.nr_raport_z <> n.nr_raport_z
              )
        ")->execute([$nrRaportZ, $nui, $serieMemorieFiscala]);

        $pdo->prepare("
            UPDATE miscari
            SET nr_raport_z = (
                SELECT n.nr_raport_z
                FROM note n
                WHERE n.nrbon = miscari.nr_nota
                LIMIT 1
            ), nui = (
                SELECT COALESCE(n.nui, 0)
                FROM note n
                WHERE n.nrbon = miscari.nr_nota
                LIMIT 1
            ), serie_memorie_fiscala = (
                SELECT COALESCE(n.serie_memorie_fiscala, '')
                FROM note n
                WHERE n.nrbon = miscari.nr_nota
                LIMIT 1
            )
            WHERE fel_doc IN ('BC', 'BT')
              AND EXISTS (
                  SELECT 1
                  FROM note n
                  WHERE n.nrbon = miscari.nr_nota
                    AND n.nr_raport_z = ?
                    AND (COALESCE(n.nui, 0) = ? OR COALESCE(n.nui, 0) = 0)
                    AND (COALESCE(n.serie_memorie_fiscala, '') = ? OR COALESCE(n.serie_memorie_fiscala, '') = '')
                    AND miscari.nr_raport_z <> n.nr_raport_z
              )
        ")->execute([$nrRaportZ, $nui, $serieMemorieFiscala]);

        return;
    }

    $pdo->prepare("UPDATE miscari m INNER JOIN note n ON n.nrbon = m.nr_doc SET m.nr_raport_z = n.nr_raport_z WHERE m.tip_miscare='O' AND m.fel_doc='BF' AND m.nr_raport_z<>n.nr_raport_z AND n.nr_raport_z=? AND (COALESCE(n.nui, 0)=? OR COALESCE(n.nui, 0)=0) AND (COALESCE(n.serie_memorie_fiscala, '')=? OR COALESCE(n.serie_memorie_fiscala, '')='')")->execute([$nrRaportZ, $nui, $serieMemorieFiscala]);
    $pdo->prepare("UPDATE miscari m INNER JOIN note n ON n.nrbon = m.nr_nota SET m.nr_raport_z = n.nr_raport_z WHERE m.fel_doc IN ('BC','BT') AND m.nr_raport_z<>n.nr_raport_z AND n.nr_raport_z=? AND (COALESCE(n.nui, 0)=? OR COALESCE(n.nui, 0)=0) AND (COALESCE(n.serie_memorie_fiscala, '')=? OR COALESCE(n.serie_memorie_fiscala, '')='')")->execute([$nrRaportZ, $nui, $serieMemorieFiscala]);
}

try {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_exit(['status' => 'error', 'message' => 'Metodă invalidă.'], 405);
    }

    $targetOperatorId = isset($_POST['operator_id']) ? (int)$_POST['operator_id'] : 0;
    if ($targetOperatorId <= 0) json_exit(['status' => 'error', 'message' => 'operator_id invalid.'], 400);

    $actorId = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : 0;
    $client_id = isset($_SESSION['client_id']) ? (int)$_SESSION['client_id'] : 0;

    $current_date = date('Y-m-d');
    $current_time = date('H:i:s');
    $current_datetime = date('Y-m-d H:i:s');

    $stmtActor = $pdo->prepare("SELECT admin_id, admin_firstname, admin_lastname, locatie FROM admins_12 WHERE admin_id = ? LIMIT 1");
    $stmtActor->execute([$actorId]);
    $actor = $stmtActor->fetch(PDO::FETCH_ASSOC);

    $actorLocation = isset($actor['locatie']) ? (int)$actor['locatie'] : 0;
    if ($actorLocation <= 0) json_exit(['status' => 'error', 'message' => 'Locație nedeterminată.'], 400);
    $actorName = trim(((string)($actor['admin_firstname'] ?? '')) . ' ' . ((string)($actor['admin_lastname'] ?? '')));
    $raportZIdentity = restaurant_sqlite_raport_z_current_identification($pdo, $actorLocation);
    $raportZNui = (int)$raportZIdentity['nui'];
    $raportZMemory = (string)$raportZIdentity['serie_memorie_fiscala'];
    $raportZFilter = "
        AND (COALESCE(nui, 0) = :raport_nui OR COALESCE(nui, 0) = 0)
        AND (COALESCE(serie_memorie_fiscala, '') = :raport_memory OR COALESCE(serie_memorie_fiscala, '') = '')";

    $stmtTarget = $pdo->prepare("SELECT admin_firstname, admin_lastname FROM admins_12 WHERE admin_id = ? LIMIT 1");
    $stmtTarget->execute([$targetOperatorId]);
    $target = $stmtTarget->fetch(PDO::FETCH_ASSOC);
    if (!$target) json_exit(['status' => 'error', 'message' => 'Operatorul selectat nu există.'], 404);
    $targetName = trim(((string)($target['admin_firstname'] ?? '')) . ' ' . ((string)($target['admin_lastname'] ?? '')));

    $pdo->beginTransaction();

    // 1. Verificăm mesele deschise pt operator
    $stmtOpen = $pdo->prepare("SELECT COUNT(*) FROM note WHERE locatie = :loc AND operator = :op AND status = 'S'{$raportZFilter}");
    $stmtOpen->execute([':loc' => $actorLocation, ':op' => $targetOperatorId, ':raport_nui' => $raportZNui, ':raport_memory' => $raportZMemory]);
    if ((int)$stmtOpen->fetchColumn() > 0) {
        $pdo->rollBack();
        json_exit(['status' => 'error', 'message' => 'Operatorul are încă mese deschise.'], 409);
    }

    // 2. Sumar bonuri F
    $stmtSummary = $pdo->prepare("
        SELECT COUNT(*) AS bonuri_F, COALESCE(SUM(valoare_vanzare_cu_tva), 0) AS total_vanzari, COALESCE(SUM(tva_colectata), 0) AS total_tva
        FROM note WHERE locatie = :loc AND operator = :op AND status = 'F' AND cod_inchidere = 0{$raportZFilter}
    ");
    $stmtSummary->execute([':loc' => $actorLocation, ':op' => $targetOperatorId, ':raport_nui' => $raportZNui, ':raport_memory' => $raportZMemory]);
    $summary = $stmtSummary->fetch(PDO::FETCH_ASSOC);

    if ((int)($summary['bonuri_F'] ?? 0) <= 0) {
        $pdo->rollBack();
        json_exit(['status' => 'error', 'message' => 'Operatorul nu are bonuri de închis.'], 409);
    }

    // 3. Generare cod inchidere nou si Update BD
    $stmtMax = $pdo->prepare("SELECT COALESCE(MAX(cod_inchidere), 0) FROM note WHERE locatie = :loc");
    $stmtMax->execute([':loc' => $actorLocation]);
    $codInchidereNou = (int)$stmtMax->fetchColumn() + 1;

    try {
        $idInchidere = 0;
        $stmtInsert = $pdo->prepare("
            INSERT INTO inchideri_r_12 (cod_inchidere, operator, valoare_cu_tva, tva_colectata, data_inchiderii, ora_inchiderii, locatie, nr_raport_z, nui, serie_memorie_fiscala)
            VALUES (:cod_inchidere, :operator, :valoare_cu_tva, :tva_colectata, :data_inchiderii, :ora_inchiderii, :locatie, 0, :nui, :serie_memorie_fiscala)
        ");
        $stmtInsert->execute([
            ':cod_inchidere'   => $codInchidereNou,
            ':operator'        => $targetOperatorId,
            ':valoare_cu_tva'  => $summary['total_vanzari'],
            ':tva_colectata'   => $summary['total_tva'],
            ':data_inchiderii' => $current_date,
            ':ora_inchiderii'  => $current_time,
            ':locatie'         => $actorLocation,
            ':nui'             => $raportZNui,
            ':serie_memorie_fiscala' => $raportZMemory
        ]);
        $idInchidere = (int)$pdo->lastInsertId();
    } catch (Exception $e) {
        error_log("Eroare insert inchideri_r_12: " . $e->getMessage());
    }

    $stmtUpdate = $pdo->prepare("UPDATE note SET cod_inchidere = :nou_cod, nui = :nui, serie_memorie_fiscala = :serie_memorie_fiscala WHERE locatie = :loc AND operator = :op AND status = 'F' AND cod_inchidere = 0{$raportZFilter}");
    $stmtUpdate->execute([':nou_cod' => $codInchidereNou, ':nui' => $raportZNui, ':serie_memorie_fiscala' => $raportZMemory, ':loc' => $actorLocation, ':op' => $targetOperatorId, ':raport_nui' => $raportZNui, ':raport_memory' => $raportZMemory]);
    
    $pdo->commit();

    $totaluriPlataJson = restaurant_build_totaluri_plata_json(
        $pdo,
        $tabel_final_note ?? 'note',
        $tabel_final_det_note ?? 'det_note',
        $codInchidereNou,
        $actorLocation,
        $targetOperatorId,
        $current_datetime
    );
    if ($idInchidere > 0 && $totaluriPlataJson !== null) {
        try {
            $stmtUpdJson = $pdo->prepare("
                UPDATE inchideri_r_12
                SET totaluri_plata_json = :json
                WHERE id_inch = :id
            ");
            $stmtUpdJson->execute([
                ':json' => $totaluriPlataJson,
                ':id' => $idInchidere,
            ]);
        } catch (Throwable $e) {
            error_log('Eroare update totaluri_plata_json: ' . $e->getMessage());
        }
    }

    require_once __DIR__ . '/offline_sync_queue_lib.php';
    $restaurantQueueConfig = restaurant_sync_queue_config($restaurantConfig);
    if ($idInchidere > 0) {
        restaurant_sync_queue_enqueue_safely(static function () use ($pdo, $restaurantQueueConfig, $idInchidere, $actorId): bool {
            return restaurant_sync_queue_enqueue_shift($pdo, $restaurantQueueConfig, (int)$idInchidere, (int)$actorId);
        });
    }

    // ==========================================================
    // 5. GENERARE LISTARE IMPRIMANTA (bon inchidere tura operator)
    // ==========================================================
    if ($client_id > 0) {
        $stmtList = $pdo->prepare("SELECT numerar, card, tichete, protocol, glovo, virament_bancar FROM note WHERE cod_inchidere = ? AND locatie = ?");
        $stmtList->execute([$codInchidereNou, $actorLocation]);
        $notes = $stmtList->fetchAll(PDO::FETCH_ASSOC);

        $totals = ['numerar'=>0, 'card'=>0, 'tichete'=>0, 'protocol'=>0, 'glovo'=>0, 'virament_bancar'=>0];
        foreach ($notes as $n) { foreach ($totals as $metoda => &$suma) { $suma += floatval($n[$metoda]); } }

        $stmtInterval = $pdo->prepare("SELECT MIN(nrbon) AS nrbon_min, MAX(nrbon) AS nrbon_max FROM note WHERE cod_inchidere = ? AND locatie = ?");
        $stmtInterval->execute([$codInchidereNou, $actorLocation]);
        $interval = $stmtInterval->fetch(PDO::FETCH_ASSOC) ?: [];
        $nrbonMin = isset($interval['nrbon_min']) ? (int)$interval['nrbon_min'] : 0;
        $nrbonMax = isset($interval['nrbon_max']) ? (int)$interval['nrbon_max'] : 0;
        $intervalNote = ($nrbonMin > 0 && $nrbonMax > 0) ? ($nrbonMin . '-' . $nrbonMax) : '-';
        
        $continut  = "NOTĂ ÎNCHIDERE TURĂ\nData: " . $current_datetime . "\nCod închidere: " . $codInchidereNou . "\nInterval note: " . $intervalNote . "\n------------------\n";
        foreach ($totals as $metoda => $suma) {
            if ($suma > 0) {
                $lbl = ['numerar'=>'Numerar', 'card'=>'Card', 'tichete'=>'Tichete', 'protocol'=>'Protocol', 'glovo'=>'Online', 'virament_bancar'=>'Virament Bancar'][$metoda];
                $continut .= "{$lbl} total: " . number_format($suma, 2) . " LEI\n";
            }
        }
        $continut .= "==================\n";

        $stmt_tip = $pdo->prepare("
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
            FROM det_note d
            JOIN note n ON d.nr_bon = n.nrbon
            WHERE d.cod_p = -1 AND n.cod_inchidere = ? AND n.locatie = ?
        ");
        $stmt_tip->execute([$codInchidereNou, $actorLocation]);
        $bacsis = $stmt_tip->fetch(PDO::FETCH_ASSOC) ?: [];
        $total_bacsis = floatval($bacsis['total_bacsis'] ?? 0);
        $bacsis_card = floatval($bacsis['bacsis_card'] ?? 0);
        $bacsis_numerar = floatval($bacsis['bacsis_numerar'] ?? 0);
        if ($total_bacsis > 0) {
            if ($bacsis_numerar > 0) $continut .= "BACSIS numerar: " . number_format($bacsis_numerar, 2) . " LEI\n";
            if ($bacsis_card > 0) $continut .= "BACSIS card: " . number_format($bacsis_card, 2) . " LEI\n";
            $continut .= "BACSIS total: " . number_format($total_bacsis, 2) . " LEI\n";
        }

        $continut .= "OPERATOR TURĂ: {$targetName}\n";
        $continut .= "ÎNCHIS DE ȘEF SALĂ: {$actorName}\n";

        try {
            agecs_offline_printer_enqueue(
                [['id' => 0, 'data' => $current_date, 'ora' => $current_time, 'de_trimis_la_imprimanta' => 1, 'nrbon' => 0, 'locatie' => (int)$actorLocation, 'departament_listare' => "BAR", 'continut' => $continut]],
                'Închiderea turei operatorului a fost adăugată în coada imprimantei.'
            );
        } catch (Throwable $printerError) {
            error_log('Tura este închisă, dar listarea nu a intrat în coadă: ' . $printerError->getMessage());
            $_SESSION['offline_printer_error'] = 'Tura este închisă, dar documentul nu a putut fi pus în coada imprimantei. Folosiți relistarea.';
        }
    }

    // ==========================================================
    // 6. VERIFICARE + GENERARE RAPORT Z AUTOMAT
    // ==========================================================
    $trigger_z = false;
    $nr_raport_z_nou = 0;
    $idRaportZNou = 0;

    $stmt_s = $pdo->prepare("SELECT COUNT(*) FROM note WHERE locatie = :loc AND status = 'S' AND nr_raport_z = 0{$raportZFilter}");
    $stmt_s->execute(['loc' => $actorLocation, 'raport_nui' => $raportZNui, 'raport_memory' => $raportZMemory]);
    
    if ((int)$stmt_s->fetchColumn() === 0) {
        $stmt_tot = $pdo->prepare("SELECT COUNT(*) FROM note WHERE locatie = :loc AND status = 'F' AND nr_raport_z = 0{$raportZFilter}");
        $stmt_tot->execute(['loc' => $actorLocation, 'raport_nui' => $raportZNui, 'raport_memory' => $raportZMemory]);
        $total = $stmt_tot->fetchColumn();

        $stmt_val = $pdo->prepare("SELECT COUNT(*) FROM note WHERE locatie = :loc AND status = 'F' AND nr_raport_z = 0 AND cod_inchidere != 0{$raportZFilter}");
        $stmt_val->execute(['loc' => $actorLocation, 'raport_nui' => $raportZNui, 'raport_memory' => $raportZMemory]);
        $valid = $stmt_val->fetchColumn();

        if ($total == $valid && $total != 0) {
            $pdo->beginTransaction();
            try {
                $stmtSum = $pdo->prepare("SELECT COALESCE(SUM(numerar),0) as numerar, COALESCE(SUM(card),0) as card, COALESCE(SUM(tichete),0) as tichete, COALESCE(SUM(glovo),0) as glovo FROM note WHERE status='F' AND locatie=:loc AND nr_raport_z=0 AND cod_inchidere!=0{$raportZFilter}");
                $stmtSum->execute(['loc' => $actorLocation, 'raport_nui' => $raportZNui, 'raport_memory' => $raportZMemory]);
                $sum = $stmtSum->fetch(PDO::FETCH_ASSOC);

                $nr_raport_z_nou = restaurant_sqlite_raport_z_next_number($pdo, $actorLocation, $raportZNui, $raportZMemory);

                $serie = $raportZIdentity['serie_casa_marcat'];

                // Insert rapoarte_z cu DATETIME (singura tabelă unde avem această coloană specifică)
                $insZ = $pdo->prepare("INSERT INTO rapoarte_z (nr_raport_z, cod_locatie, serie_casa_marcat, nui, serie_memorie_fiscala, numerar, card, tichete_masa, plata_moderna, credit, tichete_valorice, avans_in_numerar, alte_metode, data_ora_raport_z) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 0, ?)");
                $insZ->execute([$nr_raport_z_nou, $actorLocation, $serie, $raportZNui, $raportZMemory, $sum['numerar'], $sum['card'], $sum['tichete'], $sum['glovo'], $current_datetime]);
                $idRaportZNou = (int)$pdo->lastInsertId();

                // Update DOAR nr_raport_z în note (fără data_ora)
                $pdo->prepare("UPDATE note SET nr_raport_z = :raport, nui = :nui, serie_memorie_fiscala = :memory WHERE status = 'F' AND locatie = :loc AND nr_raport_z = 0{$raportZFilter}")->execute(['raport' => $nr_raport_z_nou, 'nui' => $raportZNui, 'memory' => $raportZMemory, 'loc' => $actorLocation, 'raport_nui' => $raportZNui, 'raport_memory' => $raportZMemory]);
                
                $stmtCods = $pdo->prepare("SELECT DISTINCT cod_inchidere FROM note WHERE status='F' AND locatie=? AND nr_raport_z=? AND COALESCE(nui, 0)=? AND COALESCE(serie_memorie_fiscala, '')=?");
                $stmtCods->execute([$actorLocation, $nr_raport_z_nou, $raportZNui, $raportZMemory]);
                $cods = $stmtCods->fetchAll(PDO::FETCH_COLUMN);
                if (!empty($cods)) {
                    $in = implode(',', array_fill(0, count($cods), '?'));
                    $params = array_merge([$nr_raport_z_nou, $raportZNui, $raportZMemory], $cods, [$actorLocation, $raportZNui, $raportZMemory]);
                    $pdo->prepare("UPDATE inchideri_r_12 SET nr_raport_z = ?, nui = ?, serie_memorie_fiscala = ? WHERE cod_inchidere IN ($in) AND locatie = ? AND (COALESCE(nui, 0)=? OR COALESCE(nui, 0)=0) AND (COALESCE(serie_memorie_fiscala, '')=? OR COALESCE(serie_memorie_fiscala, '')='')")->execute($params);
                }

                sefsala_update_miscari_raport_z($pdo, $nr_raport_z_nou, $raportZNui, $raportZMemory);

                $pdo->commit();

                if ($idRaportZNou > 0) {
                    restaurant_sync_queue_enqueue_safely(static function () use ($pdo, $restaurantQueueConfig, $idRaportZNou, $actorId): bool {
                        return restaurant_sync_queue_enqueue_z($pdo, $restaurantQueueConfig, (int)$idRaportZNou, (int)$actorId);
                    });
                }
                
                $clienti_redirect = [3, 8, 9, 23, 25, 26, 1008, 1021];
                if (in_array($client_id, $clienti_redirect, true)) {
                    $trigger_z = true; // Dăm flag interfeței să ceară listarea raportului termic Z
                }
            } catch (Exception $e) {
                $pdo->rollBack();
            }
        }
    }

    json_exit([
        'status'        => 'success',
        'message'       => "Tura pentru $targetName a fost închisă cu succes. (Cod Închidere: $codInchidereNou)",
        'trigger_z'     => $trigger_z,
        'nr_raport_z'   => $nr_raport_z_nou,
        'printer_wait_url' => agecs_offline_printer_wait_url('sefsala.php', 'inchidere_z')
    ]);

} catch (Exception $e) {
    try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (Throwable $t) {}
    json_exit(['status' => 'error', 'message' => 'Eroare pe server: ' . $e->getMessage()], 500);
}
