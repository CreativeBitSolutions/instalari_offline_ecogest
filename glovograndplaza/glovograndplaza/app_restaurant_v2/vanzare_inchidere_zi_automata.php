<?php //vanzare_inchidere_zi_automata.php
// Include fișierul de sesiune (conține conexiunea la BD și obiectul $pdo)
include('session.php');

date_default_timezone_set('Europe/Bucharest');

// Definește calea către fișierul log
$logFile = 'error.log';

// Preluare variabile din sesiune
$cod_locatie = isset($_SESSION['cod_locatie']) ? intval($_SESSION['cod_locatie']) : 0;
$adm_id      = isset($_SESSION['admin_id'])   ? $_SESSION['admin_id']   : 0;

// Verific dacă există bonuri cu status 'S' și nr_raport_z = 0
$sql_s = "SELECT COUNT(*) FROM note
            WHERE locatie     = :cod_locatie
              AND status      = 'S'
              AND nr_raport_z = 0";
$stmt_s = $pdo->prepare($sql_s);
$stmt_s->execute(['cod_locatie' => $cod_locatie]);
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
                     AND nr_raport_z = 0";
    $stmt_total = $pdo->prepare($sql_total);
    $stmt_total->execute(['cod_locatie' => $cod_locatie]);
    $total = $stmt_total->fetchColumn();

    // 2. Numărul de note F, nr_raport_z=0 și cod_inchidere!=0
    $sql_valid = "SELECT COUNT(*) FROM note
                   WHERE locatie         = :cod_locatie
                     AND status          = 'F'
                     AND nr_raport_z     = 0
                     AND cod_inchidere  != 0";
    $stmt_valid = $pdo->prepare($sql_valid);
    $stmt_valid->execute(['cod_locatie' => $cod_locatie]);
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
                   AND cod_inchidere  != 0";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['cod_locatie' => $cod_locatie]);
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
        $sql_last = "SELECT MAX(nr_raport_z) AS last_report
                        FROM rapoarte_z
                       WHERE cod_locatie = :cod_locatie";
        $stmt_last = $pdo->prepare($sql_last);
        $stmt_last->execute(['cod_locatie' => $cod_locatie]);
        $last = $stmt_last->fetch(PDO::FETCH_ASSOC);
        $nr_raport_z = ($last['last_report'] ? intval($last['last_report']) : 0) + 1;

        // d) Preluare serie casa marcat
        try {
            $sql = "SELECT serie_casa_marcat
                      FROM loc_mese_12
                     WHERE cod_locatie = :cod_locatie
                     LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['cod_locatie' => $cod_locatie]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $serie = $row ? $row['serie_casa_marcat'] : '';
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
            $ins = "INSERT INTO rapoarte_z
                    (nr_raport_z, cod_locatie, serie_casa_marcat,
                     numerar, card, credit, tichete_masa,
                     tichete_valorice, plata_moderna,
                     avans_in_numerar, alte_metode, data_ora_raport_z)
                    VALUES
                    (:nr_raport_z, :cod_locatie, :serie,
                     :numerar, :card, :credit, :tichete_masa,
                     :tichete_valorice, :plata_moderna,
                     :avans_in_numerar, :alte_metode, :data_ora_raport_z)";
            $stmt = $pdo->prepare($ins);
            $stmt->execute([
                'nr_raport_z'       => $nr_raport_z,
                'cod_locatie'       => $cod_locatie,
                'serie'             => $serie,
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
        } catch (PDOException $e) {
            error_log("[".date("Y-m-d H:i:s")."] Eroare insert raport Z: "
                      . $e->getMessage()
                      . " în " . __FILE__ . ":" . __LINE__ . "\n",
                      3, $logFile);
        }

        // f) UPDATE note DOAR nr_raport_z (FĂRĂ data_ora)
        try {
            $upd = "UPDATE note
                        SET nr_raport_z = :nr_raport_z
                      WHERE status      = 'F'
                        AND locatie     = :cod_locatie
                        AND nr_raport_z = 0";
            $stmt = $pdo->prepare($upd);
            $stmt->execute([
                'nr_raport_z'       => $nr_raport_z,
                'cod_locatie'       => $cod_locatie
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
                       AND nr_raport_z = :nr_raport_z";
            $stmt = $pdo->prepare($sel);
            $stmt->execute([
                'cod_locatie' => $cod_locatie,
                'nr_raport_z' => $nr_raport_z
            ]);
            $cods = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($cods)) {
                $in  = implode(',', array_fill(0, count($cods), '?'));
                $upd = "UPDATE inchideri_r_12
                           SET nr_raport_z = ?
                         WHERE cod_inchidere IN ($in)
                           AND locatie = ?";
                $params = array_merge([$nr_raport_z], $cods, [$cod_locatie]);
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
                )
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
                )
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
                )
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

        // --------------- RAPORT VANZARI TOTALE IMPRIMANTA TERMICA---------------

    $clienti_redirect = [3, 8, 9, 23, 25, 26, 1008];

if (isset($_SESSION['client_id']) && in_array((int)$_SESSION['client_id'], $clienti_redirect, true)) {
    $stmtZ = $pdo->prepare("SELECT MAX(nr_raport_z) FROM rapoarte_z WHERE cod_locatie = ?");
    $stmtZ->execute([$cod_locatie]);
    $cur_z = (int)$stmtZ->fetchColumn();

    header("Location: vanzare_listare_inchidere_zi.php?nr_raport_z={$cur_z}");
    exit;
}

    }
}

// După orice situație, redirecționăm către logout.php
echo "<script>location.href='logout.php'</script>";
?>
