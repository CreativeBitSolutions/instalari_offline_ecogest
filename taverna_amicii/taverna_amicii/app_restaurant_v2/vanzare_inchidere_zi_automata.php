<?php //vanzare_inchidere_zi_automata.php
// Include fișierul de sesiune (conține conexiunea la BD și obiectul $pdo)
include('session.php');
require_once __DIR__ . '/offline_printer_flow_helper.php';

date_default_timezone_set('Europe/Bucharest');

// Definește calea către fișierul log
$logFile = 'error.log';

// Preluare variabile din sesiune
$cod_locatie = isset($_SESSION['cod_locatie']) ? intval($_SESSION['cod_locatie']) : 0;
$adm_id      = isset($_SESSION['admin_id'])   ? $_SESSION['admin_id']   : 0;

$raportZIdentity = restaurant_sqlite_raport_z_current_identification($pdo, $cod_locatie);
$nui = $raportZIdentity['nui'];
$serie_memorie_fiscala = $raportZIdentity['serie_memorie_fiscala'];
$idRaportZ = 0;

// Verific dacă există bonuri cu status 'S' și nr_raport_z = 0
$sql_s = "SELECT COUNT(*) FROM note
            WHERE locatie     = :cod_locatie
              AND status      = 'S'
              AND nr_raport_z = 0
              AND (COALESCE(nui, 0) = :nui OR COALESCE(nui, 0) = 0)
              AND (COALESCE(serie_memorie_fiscala, '') = :memory OR COALESCE(serie_memorie_fiscala, '') = '')";
$stmt_s = $pdo->prepare($sql_s);
$stmt_s->execute(['cod_locatie' => $cod_locatie, 'nui' => $nui, 'memory' => $serie_memorie_fiscala]);
$has_S = (int)$stmt_s->fetchColumn();

if ($has_S > 0) {
    // Dacă există bonuri S neînchise, nu facem raportul Z (putem loga sau lăsa gol)
    // — aici nu se execută nimic altceva —
} else {
    // ---------------------------------------------------------------
    // BLOCUL ORIGINAL DE GENERARE A RAPORTULUI Z, neschimbat:
    // ---------------------------------------------------------------

    // 1. Numărul total de note F, nr_raport_z=0
    $sql_total = "SELECT COUNT(*) FROM note
                   WHERE locatie     = :cod_locatie
                     AND status      = 'F'
                     AND nr_raport_z = 0
                     AND (COALESCE(nui, 0) = :nui OR COALESCE(nui, 0) = 0)
                     AND (COALESCE(serie_memorie_fiscala, '') = :memory OR COALESCE(serie_memorie_fiscala, '') = '')";
    $stmt_total = $pdo->prepare($sql_total);
    $stmt_total->execute(['cod_locatie' => $cod_locatie, 'nui' => $nui, 'memory' => $serie_memorie_fiscala]);
    $total = $stmt_total->fetchColumn();

    // 2. Numărul de note F, nr_raport_z=0 și cod_inchidere!=0
    $sql_valid = "SELECT COUNT(*) FROM note
                   WHERE locatie         = :cod_locatie
                     AND status          = 'F'
                     AND nr_raport_z     = 0
                     AND cod_inchidere  != 0
                     AND (COALESCE(nui, 0) = :nui OR COALESCE(nui, 0) = 0)
                     AND (COALESCE(serie_memorie_fiscala, '') = :memory OR COALESCE(serie_memorie_fiscala, '') = '')";
    $stmt_valid = $pdo->prepare($sql_valid);
    $stmt_valid->execute(['cod_locatie' => $cod_locatie, 'nui' => $nui, 'memory' => $serie_memorie_fiscala]);
    $valid = $stmt_valid->fetchColumn();

    // 3. Condiția de generare raport Z
    if ($total == $valid && $total != 0) {
        // a) Calcul sume
        $sql = "SELECT
                      COALESCE(SUM(numerar), 0) AS total_numerar,
                      COALESCE(SUM(card),    0) AS total_card,
                      COALESCE(SUM(tichete), 0) AS total_tichete,
                       COALESCE(SUM(glovo),   0) AS total_glovo
                  FROM note
                 WHERE status          = 'F'
                   AND locatie         = :cod_locatie
                   AND nr_raport_z     = 0
                   AND cod_inchidere  != 0
                   AND (COALESCE(nui, 0) = :nui OR COALESCE(nui, 0) = 0)
                   AND (COALESCE(serie_memorie_fiscala, '') = :memory OR COALESCE(serie_memorie_fiscala, '') = '')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['cod_locatie' => $cod_locatie, 'nui' => $nui, 'memory' => $serie_memorie_fiscala]);
        $sum = $stmt->fetch(PDO::FETCH_ASSOC);

        $numerar      = number_format($sum['total_numerar'], 2, '.', '');
        $card         = number_format($sum['total_card'],    2, '.', '');
        $tichete_masa = number_format($sum['total_tichete'], 2, '.', '');
        $plata_moderna  = number_format($sum['total_glovo'],   2, '.', ''); 
        
        // b) Set valori default
        $credit           = 0;
        $tichete_valorice = 0;
        $voucher          = 0;
        $avans_in_numerar = 0;
        $alte_metode      = 0;

        // c) Determinare nr raport Z
        $nr_raport_z = restaurant_sqlite_raport_z_next_number($pdo, $cod_locatie, $nui, $serie_memorie_fiscala);

        // d) Preluare serie casa marcat
        try {
            $sql = "SELECT serie_casa_marcat
                      FROM loc_mese_12
                     WHERE cod_locatie = :cod_locatie
                     LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['cod_locatie' => $cod_locatie]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $serie = $raportZIdentity['serie_casa_marcat'];
        } catch (PDOException $e) {
            error_log("[".date("Y-m-d H:i:s")."] Eroare serie casa: "
                      . $e->getMessage()
                      . " în " . __FILE__ . ":" . __LINE__ . "\n",
                      3, $logFile);
            $serie = '';
        }

        $data_ora_curenta = date('Y-m-d H:i:s');

        // e) INSERT rapoarte_z cu DATETIME
        try {
            $duplicateStmt = $pdo->prepare("SELECT 1 FROM rapoarte_z
                WHERE cod_locatie = ? AND COALESCE(serie_casa_marcat, '') = ?
                  AND COALESCE(nui, 0) = ? AND COALESCE(serie_memorie_fiscala, '') = ?
                  AND nr_raport_z = ? LIMIT 1");
            $duplicateStmt->execute([$cod_locatie, $serie, $nui, $serie_memorie_fiscala, $nr_raport_z]);
            if ($duplicateStmt->fetchColumn()) {
                throw new RuntimeException('Raportul Z exista deja pentru aceasta identitate fiscala.');
            }

            $ins = "INSERT INTO rapoarte_z
                    (nr_raport_z, cod_locatie, serie_casa_marcat, nui, serie_memorie_fiscala,
                     numerar, card, credit, tichete_masa,
                     tichete_valorice, plata_moderna,
                     avans_in_numerar, alte_metode, data_ora_raport_z)
                    VALUES
                    (:nr_raport_z, :cod_locatie, :serie, :nui, :serie_memorie_fiscala,
                     :numerar, :card, :credit, :tichete_masa,
                     :tichete_valorice, :plata_moderna,
                     :avans_in_numerar, :alte_metode, :data_ora_raport_z)";
            $stmt = $pdo->prepare($ins);
            $stmt->execute([
                'nr_raport_z'       => $nr_raport_z,
                'cod_locatie'       => $cod_locatie,
                'serie'             => $serie,
                'nui'               => $nui,
                'serie_memorie_fiscala' => $serie_memorie_fiscala,
                'numerar'           => $numerar,
                'card'              => $card,
                'credit'            => $credit,
                'tichete_masa'      => $tichete_masa,
                'tichete_valorice'  => $tichete_valorice,
                'plata_moderna'     => $plata_moderna,
                'avans_in_numerar'  => $avans_in_numerar,
                'alte_metode'       => $alte_metode,
                'data_ora_raport_z' => $data_ora_curenta
            ]);
            $idRaportZ = (int)$pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("[".date("Y-m-d H:i:s")."] Eroare insert raport Z: "
                      . $e->getMessage()
                      . " în " . __FILE__ . ":" . __LINE__ . "\n",
                      3, $logFile);
        }

        // f) UPDATE note DOAR nr_raport_z (FĂRĂ data_ora)
        try {
            $upd = "UPDATE note
                        SET nr_raport_z = :nr_raport_z, nui = :nui, serie_memorie_fiscala = :serie_memorie_fiscala
                      WHERE status      = 'F'
                        AND locatie     = :cod_locatie
                        AND nr_raport_z = 0
                        AND (COALESCE(nui, 0) = :filter_nui OR COALESCE(nui, 0) = 0)
                        AND (COALESCE(serie_memorie_fiscala, '') = :filter_memory OR COALESCE(serie_memorie_fiscala, '') = '')";
            $stmt = $pdo->prepare($upd);
            $stmt->execute([
                'nr_raport_z'       => $nr_raport_z,
                'cod_locatie'       => $cod_locatie
                ,'nui'              => $nui
                ,'serie_memorie_fiscala' => $serie_memorie_fiscala
                ,'filter_nui' => $nui
                ,'filter_memory' => $serie_memorie_fiscala
            ]);
        } catch (PDOException $e) {
            error_log("[".date("Y-m-d H:i:s")."] Eroare update note: "
                      . $e->getMessage()
                      . " în " . __FILE__ . ":" . __LINE__ . "\n",
                      3, $logFile);
        }

        // g) UPDATE inchideri_r_12
        try {
            $sel = "SELECT DISTINCT cod_inchidere
                      FROM note
                     WHERE status      = 'F'
                       AND locatie     = :cod_locatie
                       AND nr_raport_z = :nr_raport_z
                       AND (COALESCE(nui, 0) = :nui OR COALESCE(nui, 0) = 0)
                       AND (COALESCE(serie_memorie_fiscala, '') = :memory OR COALESCE(serie_memorie_fiscala, '') = '')";
            $stmt = $pdo->prepare($sel);
            $stmt->execute([
                'cod_locatie' => $cod_locatie,
                'nr_raport_z' => $nr_raport_z,
                'nui' => $nui,
                'memory' => $serie_memorie_fiscala
            ]);
            $cods = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($cods)) {
                $in  = implode(',', array_fill(0, count($cods), '?'));
                $upd = "UPDATE inchideri_r_12
                           SET nr_raport_z = ?, nui = ?, serie_memorie_fiscala = ?
                         WHERE cod_inchidere IN ($in)
                           AND locatie = ?
                           AND (COALESCE(nui, 0) = ? OR COALESCE(nui, 0) = 0)
                           AND (COALESCE(serie_memorie_fiscala, '') = ? OR COALESCE(serie_memorie_fiscala, '') = '')";
                $params = array_merge([$nr_raport_z, $nui, $serie_memorie_fiscala], $cods, [$cod_locatie, $nui, $serie_memorie_fiscala]);
                $stmt   = $pdo->prepare($upd);
                $stmt->execute($params);
            }
        } catch (PDOException $e) {
            error_log("[".date("Y-m-d H:i:s")."] Eroare update inchideri: "
                      . $e->getMessage()
                      . " în " . __FILE__ . ":" . __LINE__ . "\n",
                      3, $logFile);
        }

        // ============================================================================
        // h) ACTUALIZARE TABELA MISCARI (Integrat Non-intrusiv)
        // ============================================================================
        try {
            $pdo->beginTransaction();

            $sqlBF = "
                UPDATE miscari
                SET nr_raport_z = (
                    SELECT n.nr_raport_z
                    FROM note n
                    WHERE n.nrbon = miscari.nr_doc
                    LIMIT 1
                ), nui = (SELECT COALESCE(n.nui, 0) FROM note n WHERE n.nrbon = miscari.nr_doc LIMIT 1),
                   serie_memorie_fiscala = (SELECT COALESCE(n.serie_memorie_fiscala, '') FROM note n WHERE n.nrbon = miscari.nr_doc LIMIT 1)
                WHERE tip_miscare = 'O'
                  AND fel_doc = 'BF'
                  AND EXISTS (
                      SELECT 1
                      FROM note n
                      WHERE n.nrbon = miscari.nr_doc
                        AND n.nr_raport_z = :nr_z
                        AND miscari.nr_raport_z <> n.nr_raport_z
                  )
            ";
            $stBF = $pdo->prepare($sqlBF);
            $stBF->execute([':nr_z' => $nr_raport_z]);

            $sqlBC = "
                UPDATE miscari
                SET nr_raport_z = (
                    SELECT n.nr_raport_z
                    FROM note n
                    WHERE n.nrbon = miscari.nr_nota
                    LIMIT 1
                ), nui = (SELECT COALESCE(n.nui, 0) FROM note n WHERE n.nrbon = miscari.nr_nota LIMIT 1),
                   serie_memorie_fiscala = (SELECT COALESCE(n.serie_memorie_fiscala, '') FROM note n WHERE n.nrbon = miscari.nr_nota LIMIT 1)
                WHERE fel_doc = 'BC'
                  AND EXISTS (
                      SELECT 1
                      FROM note n
                      WHERE n.nrbon = miscari.nr_nota
                        AND n.nr_raport_z = :nr_z
                        AND miscari.nr_raport_z <> n.nr_raport_z
                  )
            ";
            $stBC = $pdo->prepare($sqlBC);
            $stBC->execute([':nr_z' => $nr_raport_z]);

            $sqlBT = "
                UPDATE miscari
                SET nr_raport_z = (
                    SELECT n.nr_raport_z
                    FROM note n
                    WHERE n.nrbon = miscari.nr_nota
                    LIMIT 1
                ), nui = (SELECT COALESCE(n.nui, 0) FROM note n WHERE n.nrbon = miscari.nr_nota LIMIT 1),
                   serie_memorie_fiscala = (SELECT COALESCE(n.serie_memorie_fiscala, '') FROM note n WHERE n.nrbon = miscari.nr_nota LIMIT 1)
                WHERE fel_doc = 'BT'
                  AND EXISTS (
                      SELECT 1
                      FROM note n
                      WHERE n.nrbon = miscari.nr_nota
                        AND n.nr_raport_z = :nr_z
                        AND miscari.nr_raport_z <> n.nr_raport_z
                  )
            ";
            $stBT = $pdo->prepare($sqlBT);
            $stBT->execute([':nr_z' => $nr_raport_z]);

            $pdo->commit();

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("[".date("Y-m-d H:i:s")."] WARNING - Eroare update miscari: " . $e->getMessage() . "\n", 3, $logFile);
        }
        // ============================================================================

        // Raportul Z este emis exclusiv offline. Online primește numărul și actualizează
        // închiderile și notele existente prin identificator_offline.
        if ($idRaportZ > 0) {
            require_once __DIR__ . '/offline_sync_queue_lib.php';
            $restaurantQueueConfig = restaurant_sync_queue_config($restaurantConfig);
            restaurant_sync_queue_enqueue_safely(static function () use ($pdo, $restaurantQueueConfig, $idRaportZ, $adm_id): bool {
                return restaurant_sync_queue_enqueue_z($pdo, $restaurantQueueConfig, $idRaportZ, (int)$adm_id);
            });
        }

        // --------------- RAPORT VANZARI TOTALE IMPRIMANTA TERMICA---------------

    $clienti_redirect = [3, 8, 9, 23, 25, 26, 1008, 1021];

if (isset($_SESSION['client_id']) && in_array((int)$_SESSION['client_id'], $clienti_redirect, true)) {
    $stmtZ = $pdo->prepare("SELECT MAX(nr_raport_z) FROM rapoarte_z WHERE cod_locatie = ? AND COALESCE(nui, 0) = ? AND COALESCE(serie_memorie_fiscala, '') = ?");
    $stmtZ->execute([$cod_locatie, $nui, $serie_memorie_fiscala]);
    $cur_z = (int)$stmtZ->fetchColumn();

    $listareQuery = http_build_query([
        'nr_raport_z' => $cur_z,
        'serie_casa_marcat' => $serie,
        'nui' => $nui,
        'serie_memorie_fiscala' => $serie_memorie_fiscala,
    ]);
    header("Location: vanzare_listare_inchidere_zi.php?{$listareQuery}");
    exit;
}

    }
}

// Dacă nu se poate genera raportul Z deoarece mai există note deschise,
// publică doar închiderea de tură păstrată în sesiune. Fluxul nu se blochează.
$pendingClosurePrint = $_SESSION['restaurant_pending_closure_print'] ?? null;
$currentClientId = (int)($_SESSION['client_id'] ?? 0);
$showClosurePrinterStatus = false;
if (
    $idRaportZ <= 0
    && in_array($currentClientId, [1008, 1021], true)
    && is_array($pendingClosurePrint)
    && (int)($pendingClosurePrint['client_id'] ?? 0) === $currentClientId
    && (int)($pendingClosurePrint['location_id'] ?? 0) === $cod_locatie
    && isset($pendingClosurePrint['jobs'])
    && is_array($pendingClosurePrint['jobs'])
) {
    $showClosurePrinterStatus = true;
    try {
        $queueHelper = rtrim((string)RESTAURANT_OFFLINE_API_DIR, '/\\')
            . DIRECTORY_SEPARATOR . 'printer_queue_atomic_helper.php';
        if (!is_file($queueHelper)) {
            throw new RuntimeException('Helperul pentru coada atomică a imprimantei lipsește.');
        }
        require_once $queueHelper;
        $queuePath = rtrim((string)RESTAURANT_OFFLINE_API_DIR, '/\\')
            . DIRECTORY_SEPARATOR . $currentClientId
            . DIRECTORY_SEPARATOR . $cod_locatie
            . DIRECTORY_SEPARATOR . 'de_listat_la_imprimanta.json';
        agecs_printer_queue_append_documents(
            $queuePath,
            $pendingClosurePrint['jobs'],
            'Închiderea turei a fost adăugată în coada imprimantei.'
        );
        unset($_SESSION['restaurant_pending_closure_print']);
    } catch (Throwable $error) {
        error_log('Închiderea a fost salvată, dar listarea ei nu a putut fi pusă în coadă: ' . $error->getMessage());
        $_SESSION['offline_printer_error'] = 'Închiderea este salvată, dar documentul nu a putut fi pus în coada imprimantei. Verificați scannerul și folosiți relistarea.';
    }
}

// După orice situație, redirecționăm către logout.php
echo $showClosurePrinterStatus
    ? agecs_offline_printer_redirect_script('logout.php', 'inchidere_z')
    : "<script>location.href='logout.php'</script>";
?>
