<?php
/**
 * vanzare_adaug_prod_pe_nota.php
 * VERSIUNE SUPER-OPTIMIZATÄ‚ (cu suport cantitate negativÄƒ -> scÄƒdere fÄƒrÄƒ rÃ¢nduri negative)
 * - AdaugÄƒ/actualizeazÄƒ produsul Ã®n baza de date (cantitÄƒÈ›i pozitive).
 * - La cantitÄƒÈ›i negative: scade din det_note (pÃ¢nÄƒ la 0) È™i È™terge liniile ajunse la 0; scade È™i SGR.
 * - FoloseÈ™te cache per client+locaÈ›ie pentru lookup dupÄƒ cod de bare.
 * - ReturneazÄƒ JSON cu liniile adÄƒugate/actualizate/È™terse (inclusiv SGR).
 * * MODIFICARE: ClienÈ›ii 6 È™i 8 pot adÄƒuga linii cu cantitate negativÄƒ (INSERT/UPDATE cu minus).
 */

include('session.php');
require_once __DIR__ . '/cache_tools.php';
if (!empty($_SESSION['client_id'])) {
    probabilistic_gc_for_client($_SESSION['client_id'], 1800, 0.02); // 2% / request
}

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.log');
error_reporting(E_ALL);

// === Helpers rotunjire condiÈ›ionatÄƒ (strict cÃ¢nd cantitatea are 3 zecimale) ===
if (!function_exists('has_three_decimals')) {
    function has_three_decimals(string $qtyRaw): bool {
        $s = str_replace(',', '.', trim($qtyRaw));
        if (strpos($s, '.') === false) return false;
        $dec = substr($s, strpos($s, '.') + 1);
        // 0.15000 -> considerÄƒm 3 zecimale (0.150)
        $dec = rtrim($dec, '0');
        return strlen($dec) === 3;
    }
    // HALF-UP deterministic, pe string (fallback dacÄƒ nu ai BCMath)
    function money_round($val, $scale = 2) {
        if (function_exists('bcadd')) {
            $s = is_string($val) ? $val : sprintf('%.10F', (float)$val);
            $factor = str_pad('1', $scale + 1, '0'); // ex: 100 pentru 2 zec.
            $shift  = bcmul($s, $factor, $scale + 4);
            $shift_plus = bcadd($shift, '0.5', 1);
            $trunc = strstr($shift_plus, '.', true);
            if ($trunc === false) $trunc = $shift_plus;
            $res = bcdiv($trunc, $factor, $scale);
            if ($res === '-0.' . str_repeat('0', $scale)) $res = '0.' . str_repeat('0', $scale);
            return $res;
        }
        return number_format(round((float)$val, $scale, PHP_ROUND_HALF_UP), $scale, '.', '');
    }
    function dec_mul($a, $b, $scale = 6) {
        if (function_exists('bcmul')) {
            $as = is_string($a) ? $a : sprintf('%.10F', (float)$a);
            $bs = is_string($b) ? $b : sprintf('%.10F', (float)$b);
            return bcmul($as, $bs, $scale);
        }
        return sprintf('%.' . $scale . 'F', ((float)$a) * ((float)$b));
    }
    function dec_add($a, $b, $scale = 6) {
        if (function_exists('bcadd')) {
            $as = is_string($a) ? $a : sprintf('%.10F', (float)$a);
            $bs = is_string($b) ? $b : sprintf('%.10F', (float)$b);
            return bcadd($as, $bs, $scale);
        }
        return sprintf('%.' . $scale . 'F', ((float)$a) + ((float)$b));
    }
    function dec_sub($a, $b, $scale = 6) {
        if (function_exists('bcsub')) {
            $as = is_string($a) ? $a : sprintf('%.10F', (float)$a);
            $bs = is_string($b) ? $b : sprintf('%.10F', (float)$b);
            return bcsub($as, $bs, $scale);
        }
        return sprintf('%.' . $scale . 'F', ((float)$a) - ((float)$b));
    }
    function tva_split_from_cu_tva($valoare_cu_tva, $cota_tva) {
        // TVA = cu_tva * cota / (100 + cota)
        if (function_exists('bcdiv')) {
            $num  = dec_mul($valoare_cu_tva, $cota_tva, 8);
            $den  = dec_add('100', $cota_tva, 4);
            $tva  = money_round(bcdiv($num, $den, 8), 2);
            $fara = money_round(dec_sub($valoare_cu_tva, $tva, 6), 2);
            return [$fara, $tva];
        } else {
            $num  = (float)dec_mul($valoare_cu_tva, $cota_tva, 8);
            $den  = (float)dec_add('100', $cota_tva, 4);
            $tva  = money_round($num / $den, 2);
            $fara = money_round(((float)$valoare_cu_tva - (float)$tva), 2);
            return [$fara, $tva];
        }
    }
}

if (!function_exists('isClientTaxaHotelieraVanzare')) {
    function isClientTaxaHotelieraVanzare($clientId): bool {
    return in_array((string)$clientId, ['1005', '8','1006'], true);
    }
}

if (!function_exists('esteProdusCazareTaxaHoteliera')) {
    function esteProdusCazareTaxaHoteliera(string $numeProdus): bool {
        $upper = strtoupper($numeProdus);

        return (
            strpos($upper, 'CAZARE') !== false &&
            strpos($upper, 'TAXA HOTELIERA') === false &&
            strpos($upper, 'TAXA DE ORAS') === false &&
            strpos($upper, 'TAXA ORAS') === false
        );
    }
}
if (!function_exists('sincronizeazaTaxaHotelieraPeBon')) {
    function sincronizeazaTaxaHotelieraPeBon(
        PDO $pdo,
        string $tabelFinalNomenclator,
        string $tabelFinalDetNote,
        int $nrBon,
        int $pachet,
        float $cantitateCazare,
        float $valoareNetaCazare,
        array &$responseArray
    ): void {
        // È˜tergem orice taxÄƒ existentÄƒ, indiferent cum este denumitÄƒ
        $selectTaxaExistentaSql = "SELECT id_vanz
                                   FROM {$tabelFinalDetNote}
                                   WHERE nr_bon = :nr_bon
                                     AND pachet = :pachet
                                     AND UPPER(nume_produs) IN ('TAXA HOTELIERA', 'TAXA DE ORAS', 'TAXA ORAS')";
        $stmtTaxaExistenta = $pdo->prepare($selectTaxaExistentaSql);
        $stmtTaxaExistenta->execute([
            ':nr_bon' => $nrBon,
            ':pachet' => $pachet
        ]);
        $taxeExistente = $stmtTaxaExistenta->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($taxeExistente)) {
            $placeholders = implode(',', array_fill(0, count($taxeExistente), '?'));
            $deleteTaxaSql = "DELETE FROM {$tabelFinalDetNote} WHERE id_vanz IN ({$placeholders})";
            $stmtDeleteTaxa = $pdo->prepare($deleteTaxaSql);
            $stmtDeleteTaxa->execute(array_values($taxeExistente));

            foreach ($taxeExistente as $idTaxaSters) {
                $responseArray['removed'][] = (int)$idTaxaSters;
            }
        }

        $valoareTaxa = round($valoareNetaCazare * 0.02, 2);
        if (abs($valoareTaxa) < 0.00001) {
            return;
        }

        // CÄƒutÄƒm taxa Ã®n nomenclator È™i luÄƒm exact numele gÄƒsit
        $stmtProdTaxa = $pdo->prepare("
            SELECT cod_produs, um, nume
            FROM {$tabelFinalNomenclator}
            WHERE UPPER(nume) IN ('TAXA HOTELIERA', 'TAXA DE ORAS', 'TAXA ORAS')
            ORDER BY CASE
                WHEN UPPER(nume) = 'TAXA ORAS' THEN 1
                WHEN UPPER(nume) = 'TAXA DE ORAS' THEN 2
                WHEN UPPER(nume) = 'TAXA HOTELIERA' THEN 3
                ELSE 4
            END
            LIMIT 1
        ");
        $stmtProdTaxa->execute();
        $prodTaxa = $stmtProdTaxa->fetch(PDO::FETCH_ASSOC);

        if ($prodTaxa) {
            $codPTaxa = $prodTaxa['cod_produs'];
            $umTaxa   = $prodTaxa['um'];
            $numeTaxa = $prodTaxa['nume'];
        } else {
            $codPTaxa = 'TAXA_AUTO';
            $umTaxa   = 'BUC';
            $numeTaxa = 'TAXA HOTELIERA'; // fallback
        }

        $cantitateTaxa = ($cantitateCazare < 0) ? -1 : 1;
        $pretTaxa = abs($valoareTaxa);
        $valoareTaxaFinala = round($pretTaxa * $cantitateTaxa, 2);

        $insertTaxaSql = "INSERT INTO {$tabelFinalDetNote}
                            (nr_bon, cod_p, nume_produs, cantitate, cota_tva, tva_col, pret_vanzare,
                             valoare_vanzare, valoare_vanzare_cu_tva, pachet, data, ora)
                          VALUES
                            (:nr_bon, :cod_p, :nume, :cantitate, :cota_tva, :tva_col, :pret_vanzare,
                             :valoare_vanzare, :valoare_vanzare_cu_tva, :pachet, date('now','localtime'), time('now','localtime'))";
        $stmtInsertTaxa = $pdo->prepare($insertTaxaSql);
        $stmtInsertTaxa->execute([
            ':nr_bon' => $nrBon,
            ':cod_p' => $codPTaxa,
            ':nume' => $numeTaxa,
            ':cantitate' => $cantitateTaxa,
            ':cota_tva' => 0,
            ':tva_col' => 0,
            ':pret_vanzare' => $pretTaxa,
            ':valoare_vanzare' => $valoareTaxaFinala,
            ':valoare_vanzare_cu_tva' => $valoareTaxaFinala,
            ':pachet' => $pachet
        ]);

        $idTaxaNou = (int)$pdo->lastInsertId();
        $responseArray['added'][] = [
            'id_vanz' => $idTaxaNou,
            'cod_p' => $codPTaxa,
            'nume' => $numeTaxa,
            'cantitate' => $cantitateTaxa,
            'pret_vanzare' => $pretTaxa,
            'valoare_vanzare_cu_tva' => $valoareTaxaFinala,
            'um' => $umTaxa,
            'cota_tva' => 0
        ];
    }
}

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

try {
    // ===================================================================
    // 1. PRELUARE È˜I VALIDARE DATE DE INTRARE
    // ===================================================================
    $nr_bon   = filter_input(INPUT_GET, 'bonul', FILTER_VALIDATE_INT);
    $cod_masa = $_GET['cod_masa'] ?? null;

    // AcceptÄƒ È™i virgulÄƒ ca separator zecimal; permite cantitate negativÄƒ
    $raw_qty   = $_GET['cantitate_de_adaugat_prod'] ?? '1';
    $raw_qty   = str_replace(',', '.', $raw_qty);
    $cantitate = round((float)$raw_qty, 5);
    // doar dacÄƒ e exact 0, punem fallback 1 (valorile negative sunt permise)
    if ($cantitate == 0.0) $cantitate = 1.0;

    // Flag: aplicÄƒm rotunjirea specialÄƒ doar dacÄƒ cantitatea are exact 3 zecimale
    $qty_has3 = has_three_decimals($raw_qty);

    if (!$nr_bon || $cod_masa === null) {
        throw new Exception("Date de intrare invalide (bon sau masa).");
    }

    $sgr_flags = [];
    $isBarcodeLookup = !empty($_GET['prod_cod_bare']);
    $skipPopupEdit = (isset($_GET['skip_popup_edit']) && (string)$_GET['skip_popup_edit'] === '1');

    // Context pentru cache per client+locaÈ›ie
    $client_id   = $_SESSION['client_id']   ?? 'anon';
    $cod_locatie = $_SESSION['cod_locatie'] ?? '0';

    // ===================================================================
    // 1.a Lookup produs (cod bare cu cache) SAU direct din querystring
    // ===================================================================
    if ($isBarcodeLookup) {
        // ---- Cache pentru cÄƒutare dupÄƒ cod de bare ----
        $BARCODE_TTL = 900; // 15 minute
        $nocache = (isset($_GET['nocache']) && $_GET['nocache'] == '1');

        $barcodeRaw = $_GET['prod_cod_bare'];
        $barcodeSan = preg_replace('/[^0-9A-Za-z\.\-\_]/', '_', $barcodeRaw);

        $barcodes_dir = __DIR__ . "/cache/c{$client_id}_l{$cod_locatie}/barcodes";
        $barcode_cache_file = $barcodes_dir . "/{$barcodeSan}.json";

        $produs_info = null;

        if (!$nocache && is_file($barcode_cache_file) && (time() - filemtime($barcode_cache_file) < $BARCODE_TTL)) {
            $json = @file_get_contents($barcode_cache_file);
            $tmp  = json_decode($json, true);
            if (is_array($tmp) && isset($tmp['cod_produs'])) {
                $produs_info = $tmp;
                header('X-Barcode-Cache: HIT');
            }
        } elseif ($nocache) {
            header('X-Barcode-Cache: BYPASS');
        }

        // DacÄƒ nu avem Ã®n cache â†’ db
        if (!$produs_info) {
            // Mapare cod_bare -> cod_produs (tabel ajutÄƒtor rapid)
            $stmt = $pdo->prepare("SELECT cod_produs FROM produse_servicii WHERE cod_bare = :cod_bare LIMIT 1");
            $stmt->execute([':cod_bare' => $barcodeRaw]);
            $cod_p = $stmt->fetchColumn();

            if (!$cod_p) {
                http_response_code(404);
                echo json_encode(['error' => 'Produs negasit.']);
                exit();
            }

            // Detalii complete din nomenclator
            $sql_produs = "SELECT 
                               n.cod_produs, n.nume, n.pret_cu_tva, n.cota_tva, n.um,
                               n.sgr, n.sgr_pet, n.sgr_alumin, n.sgr_sticla,
                               g.denumire_gestiune
                           FROM {$tabel_final_nomenclator} n
                           LEFT JOIN gestiuni g ON n.id_gestiune = g.id_gestiune
                           WHERE n.cod_produs = :cod_p
                           LIMIT 1";
            $stmt_produs = $pdo->prepare($sql_produs);
            $stmt_produs->execute([':cod_p' => $cod_p]);
            $produs_info = $stmt_produs->fetch(PDO::FETCH_ASSOC);

            if (!$produs_info) {
                http_response_code(404);
                echo json_encode(['error' => 'Detalii produs negasite.']);
                exit();
            }

            // Scriere Ã®n cache (atomic)
            if (!is_dir($barcodes_dir)) {
                @mkdir($barcodes_dir, 0755, true);
            }
            $tmpf = $barcode_cache_file . '.' . getmypid() . '.tmp';
            @file_put_contents($tmpf, json_encode($produs_info, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
            @rename($tmpf, $barcode_cache_file);

            if (!$nocache) {
                header('X-Barcode-Cache: MISS');
            }
        }

        // Populate variabile necesare logicii de vÃ¢nzare
        $cod_p        = $produs_info['cod_produs'];
        $nume_produs  = $produs_info['nume'];
        $pret_vanzare = (float)$produs_info['pret_cu_tva'];
        $cota_tva     = (float)$produs_info['cota_tva'];
        $um           = $produs_info['um'];
        $gestiune     = $produs_info['denumire_gestiune'];

        $sgr_flags = [
            'sgr'        => $produs_info['sgr']        ?? 0,
            'sgr_pet'    => $produs_info['sgr_pet']    ?? 0,
            'sgr_alumin' => $produs_info['sgr_alumin'] ?? 0,
            'sgr_sticla' => $produs_info['sgr_sticla'] ?? 0,
        ];
    } else {
        // Date transmise direct din UI (din card-ul produsului)
        $cod_p = $_GET['prod'] ?? null;
        if (!$cod_p) throw new Exception("Cod produs lipsa.");

        $nume_produs  = $_GET['nume_produs'] ?? '';
        $pret_vanzare = (float)($_GET['pret_vanzare'] ?? 0);
        $cota_tva     = (float)($_GET['cota_tva'] ?? 0);
        $um           = $_GET['um'] ?? 'buc';
        $gestiune     = $_GET['gestiune'] ?? '';

        $sgr_flags = [
            'sgr'        => $_GET['sgr']        ?? 0,
            'sgr_pet'    => $_GET['sgr_pet']    ?? 0,
            'sgr_alumin' => $_GET['sgr_alumin'] ?? 0,
            'sgr_sticla' => $_GET['sgr_sticla'] ?? 0,
        ];
    }

    $pachet = ($cod_masa == 9999) ? 1 : 0;

    // ===================================================================
    // 2. LOGICA DE BUSINESS (reguli client 12)
    // ===================================================================
    if (($_SESSION['client_id'] ?? null) == 12) {
        $sql_regula = "SELECT modificator_cantitate, modificator_pret, tip_modificator_pret
                       FROM reguli_vanzare
                       WHERE cod_produs = :cod_produs
                         AND activ = 1
                         AND (data_inceput IS NULL OR data_inceput <= date('now','localtime'))
                         AND (data_sfarsit  IS NULL OR data_sfarsit  >= date('now','localtime'))
                       LIMIT 1";
        $stmt_regula = $pdo->prepare($sql_regula);
        $stmt_regula->execute([':cod_produs' => $cod_p]);
        if ($regula = $stmt_regula->fetch(PDO::FETCH_ASSOC)) {
            if ($regula['modificator_cantitate'] !== null) {
                $cantitate += (float)$regula['modificator_cantitate'];
            }
            if ($regula['modificator_pret'] !== null) {
                $modificator = (float)$regula['modificator_pret'];
                if (($regula['tip_modificator_pret'] ?? '') === 'procent') {
                    $pret_vanzare *= (1 + ($modificator / 100));
                } else {
                    $pret_vanzare += $modificator;
                }
            }
        }
    }

    // ===================================================================
    // 3. OPERAÈšIUNI BAZÄ‚ DE DATE (ADÄ‚UGARE sau SCÄ‚DERE)
    // ===================================================================
    $pdo->beginTransaction();

    $response = ['updated' => [], 'added' => [], 'removed' => []];

    // MODIFICARE: Am adÄƒugat verificarea pentru client_id 6 È™i 8
    // Astfel, aceÈ™ti clienÈ›i intrÄƒ pe ramura de INSERT/UPDATE chiar dacÄƒ cantitatea e negativÄƒ
    if ($cantitate >= 0 || in_array($client_id, [6, 8])) {
        // --- LOGICA DE ADÄ‚UGARE / MÄ‚RIRE CANTITATE (Sau adÄƒugare negativÄƒ pentru 6,8) ---
        if ($qty_has3) {
            $valoare_vanzare_cu_tva = money_round(dec_mul($pret_vanzare, $cantitate), 2);
            list($valoare_vanzare, $tva_col) = tva_split_from_cu_tva($valoare_vanzare_cu_tva, $cota_tva);
        } else {
            $valoare_vanzare_cu_tva = number_format(round($pret_vanzare * $cantitate, 2, PHP_ROUND_HALF_UP), 2, '.', '');
            $tva_col                = number_format(round(($valoare_vanzare_cu_tva * $cota_tva) / (100 + $cota_tva), 2, PHP_ROUND_HALF_UP), 2, '.', '');
            $valoare_vanzare        = number_format($valoare_vanzare_cu_tva - $tva_col, 2, '.', '');
        }

        // DacÄƒ existÄƒ deja aceeaÈ™i linie (acelaÈ™i cod, acelaÈ™i preÈ›, acelaÈ™i pachet), cumulÄƒm cantitatea
        $sql_check = "SELECT id_vanz, cantitate
                      FROM {$tabel_final_det_note}
                      WHERE nr_bon = :nr_bon AND cod_p = :cod_p AND pret_vanzare = :pret_vanzare AND pachet = :pachet
                      LIMIT 1";
        $stmt_check = $pdo->prepare($sql_check);
        $stmt_check->execute([
            ':nr_bon'        => $nr_bon,
            ':cod_p'         => $cod_p,
            ':pret_vanzare'  => $pret_vanzare,
            ':pachet'        => $pachet
        ]);
        $existing_item = $stmt_check->fetch(PDO::FETCH_ASSOC);

        $id_vanz_target = null;
        if ($existing_item) {
            $id_vanz_existent = (int)$existing_item['id_vanz'];
            $id_vanz_target = $id_vanz_existent;

            $sql_update = "UPDATE {$tabel_final_det_note}
                           SET cantitate = cantitate + :cantitate,
                               tva_col = tva_col + :tva_col,
                               valoare_vanzare = valoare_vanzare + :valoare_vanzare,
                               valoare_vanzare_cu_tva = valoare_vanzare_cu_tva + :valoare_cu_tva,
                               t_list = '0'
                           WHERE id_vanz = :id_vanz";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([
                ':cantitate'       => $cantitate,
                ':tva_col'         => $tva_col,
                ':valoare_vanzare' => $valoare_vanzare,
                ':valoare_cu_tva'  => $valoare_vanzare_cu_tva,
                ':id_vanz'         => $id_vanz_existent
            ]);

            $cant_total = (float)$existing_item['cantitate'] + $cantitate;

            $response['updated'][] = [
                'id_vanz'                => $id_vanz_existent,
                'cantitate'              => round($cant_total, 2),
                'valoare_vanzare_cu_tva' => $qty_has3
                    ? (float) money_round(dec_mul($cant_total, $pret_vanzare), 2)
                    : (float) number_format(round($cant_total * $pret_vanzare, 2, PHP_ROUND_HALF_UP), 2, '.', '')
            ];
        } else {
            $sql_insert = "INSERT INTO {$tabel_final_det_note}
                               (nr_bon, cod_p, nume_produs, cantitate, cota_tva, tva_col, pret_vanzare,
                                valoare_vanzare, valoare_vanzare_cu_tva, pachet, data, ora)
                           VALUES
                               (:nr_bon, :cod_p, :nume, :cantitate, :cota_tva, :tva_col, :pret,
                                :val_vanzare, :val_vanzare_tva, :pachet, date('now','localtime'), time('now','localtime'))";
            $stmt_insert = $pdo->prepare($sql_insert);
            $stmt_insert->execute([
                ':nr_bon'          => $nr_bon,
                ':cod_p'           => $cod_p,
                ':nume'            => $nume_produs,
                ':cantitate'       => $cantitate,
                ':cota_tva'        => $cota_tva,
                ':tva_col'         => $tva_col,
                ':pret'            => $pret_vanzare,
                ':val_vanzare'     => $valoare_vanzare,
                ':val_vanzare_tva' => $valoare_vanzare_cu_tva,
                ':pachet'          => $pachet
            ]);
            $last_id = (int)$pdo->lastInsertId();
            $id_vanz_target = $last_id;

            $response['added'][] = [
                'id_vanz'                => $last_id,
                'cod_p'                  => $cod_p,
                'nume'                   => $nume_produs,
                'cantitate'              => $cantitate,
                'pret_vanzare'           => $pret_vanzare,
                'valoare_vanzare_cu_tva' => $valoare_vanzare_cu_tva,
                'um'                     => $um,
                'cota_tva'               => $cota_tva
            ];
        }

        if ($id_vanz_target) {
            $sqlLinieCurenta = "SELECT id_vanz, cod_p, nume_produs, cantitate, pret_vanzare, cota_tva, valoare_vanzare
                                FROM {$tabel_final_det_note}
                                WHERE id_vanz = :id_vanz
                                LIMIT 1";
            $stmtLinieCurenta = $pdo->prepare($sqlLinieCurenta);
            $stmtLinieCurenta->execute([':id_vanz' => $id_vanz_target]);
            $linieCurenta = $stmtLinieCurenta->fetch(PDO::FETCH_ASSOC);

            if (
                $linieCurenta &&
                isClientTaxaHotelieraVanzare($_SESSION['client_id'] ?? null) &&
                esteProdusCazareTaxaHoteliera((string)$linieCurenta['nume_produs'])
            ) {
                sincronizeazaTaxaHotelieraPeBon(
                    $pdo,
                    $tabel_final_nomenclator,
                    $tabel_final_det_note,
                    (int)$nr_bon,
                    (int)$pachet,
                    (float)$linieCurenta['cantitate'],
                    (float)$linieCurenta['valoare_vanzare'],
                    $response
                );

                if (!$skipPopupEdit) {
                    $response['popup_edit_item'] = [
                        'id_vanz' => (int)$linieCurenta['id_vanz'],
                        'cod_p' => $linieCurenta['cod_p'],
                        'nume' => $linieCurenta['nume_produs'],
                        'cantitate' => (float)$linieCurenta['cantitate'],
                        'pret_vanzare' => (float)$linieCurenta['pret_vanzare'],
                        'cota_tva' => (float)$linieCurenta['cota_tva']
                    ];
                }
            }
        }

        // ===================================================================
        // 4. GaranÈ›ii SGR (adÄƒugate ca linii distincte pe bon) â€” DOAR LA ADÄ‚UGARE
        // ===================================================================
        function adaugaGarantieSGR($cod_garantie, $cantitate, $nr_bon, $pachet, $pdo, $tabel_final_nomenclator, $tabel_final_det_note, &$responseArray) {
            $stmt = $pdo->prepare("SELECT nume, pret_cu_tva, cota_tva, um
                                   FROM {$tabel_final_nomenclator}
                                   WHERE cod_produs = :cod_garantie
                                   LIMIT 1");
            $stmt->execute([':cod_garantie' => $cod_garantie]);
            if ($garantie = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $pret       = (float)$garantie['pret_cu_tva'];
                $cota_tva_g = (float)$garantie['cota_tva'];

                // AplicÄƒ aceeaÈ™i regulÄƒ de rotunjire dacÄƒ cantitatea are 3 zecimale
                global $qty_has3;
                if ($qty_has3) {
                    $val_tva_g = money_round(dec_mul($pret, $cantitate), 2);
                    list($val_g, $tva_col_g) = tva_split_from_cu_tva($val_tva_g, $cota_tva_g);
                } else {
                    $val_tva_g = number_format(round($pret * $cantitate, 2, PHP_ROUND_HALF_UP), 2, '.', '');
                    $tva_col_g = number_format(round(($val_tva_g * $cota_tva_g) / (100 + $cota_tva_g), 2, PHP_ROUND_HALF_UP), 2, '.', '');
                    $val_g     = number_format($val_tva_g - $tva_col_g, 2, '.', '');
                }

                $sql = "INSERT INTO {$tabel_final_det_note}
                            (nr_bon, cod_p, nume_produs, cantitate, cota_tva, tva_col, pret_vanzare,
                             valoare_vanzare, valoare_vanzare_cu_tva, pachet, data, ora)
                        VALUES
                            (:nr_bon, :cod_p, :nume, :cant, :cota_tva, :tva_col, :pret, :val, :val_tva, :pachet, date('now','localtime'), time('now','localtime'))";
                $stmt_insert = $pdo->prepare($sql);
                $stmt_insert->execute([
                    ':nr_bon'   => $nr_bon,
                    ':cod_p'    => $cod_garantie,
                    ':nume'     => $garantie['nume'],
                    ':cant'     => $cantitate,
                    ':cota_tva' => $cota_tva_g,
                    ':tva_col'  => $tva_col_g,
                    ':pret'     => $pret,
                    ':val'      => $val_g,
                    ':val_tva'  => $val_tva_g,
                    ':pachet'   => $pachet
                ]);
                $last_id_garantie = (int)$pdo->lastInsertId();

                $responseArray['added'][] = [
                    'id_vanz'                => $last_id_garantie,
                    'cod_p'                  => $cod_garantie,
                    'nume'                   => $garantie['nume'],
                    'cantitate'              => $cantitate,
                    'pret_vanzare'           => $pret,
                    'valoare_vanzare_cu_tva' => $val_tva_g,
                    'um'                     => $garantie['um'],
                    'cota_tva'               => $cota_tva_g
                ];
            }
        }

        if ($gestiune !== "PRODUSE FINITE") {
            if (!empty($sgr_flags['sgr'])        && (int)$sgr_flags['sgr']        === 1) adaugaGarantieSGR(-2, $cantitate, $nr_bon, $pachet, $pdo, $tabel_final_nomenclator, $tabel_final_det_note, $response);
            if (!empty($sgr_flags['sgr_pet'])    && (int)$sgr_flags['sgr_pet']    === 1) adaugaGarantieSGR(-3, $cantitate, $nr_bon, $pachet, $pdo, $tabel_final_nomenclator, $tabel_final_det_note, $response);
            if (!empty($sgr_flags['sgr_alumin']) && (int)$sgr_flags['sgr_alumin'] === 1) adaugaGarantieSGR(-4, $cantitate, $nr_bon, $pachet, $pdo, $tabel_final_nomenclator, $tabel_final_det_note, $response);
            if (!empty($sgr_flags['sgr_sticla']) && (int)$sgr_flags['sgr_sticla'] === 1) adaugaGarantieSGR(-5, $cantitate, $nr_bon, $pachet, $pdo, $tabel_final_nomenclator, $tabel_final_det_note, $response);
        }

    } else {
        // --- CANTITATE NEGATIVÄ‚ â†’ SCADERE FÄ‚RÄ‚ RÃ‚NDURI NEGATIVE (Comportament Standard) ---
        $de_scazut = abs($cantitate);

        // 1) ia liniile produsului de pe bon (acelaÈ™i pachet), Ã®ncepÃ¢nd cu cele mai recente
        $sql_rows = "SELECT id_vanz, cantitate, pret_vanzare, cota_tva
                     FROM {$tabel_final_det_note}
                     WHERE nr_bon = :nr_bon AND cod_p = :cod_p AND pachet = :pachet
                     ORDER BY id_vanz DESC";
        $stmt_rows = $pdo->prepare($sql_rows);
        $stmt_rows->execute([':nr_bon' => $nr_bon, ':cod_p' => $cod_p, ':pachet' => $pachet]);
        $rows = $stmt_rows->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $r) {
            if ($de_scazut <= 0) break;

            $idv  = (int)$r['id_vanz'];
            $cant = (float)$r['cantitate'];
            $pu   = (float)$r['pret_vanzare'];
            $ctva = (float)$r['cota_tva'];

            $take = min($cant, $de_scazut);

            // delta pe valori (formule originale, cu rotunjire condiÈ›ionatÄƒ dacÄƒ e cazul)
            if ($qty_has3) {
                $delta_cu_tva = money_round(dec_mul($pu, $take), 2);
                list($delta_fara, $delta_tva) = tva_split_from_cu_tva($delta_cu_tva, $ctva);
            } else {
                $delta_cu_tva = number_format(round($pu * $take, 2, PHP_ROUND_HALF_UP), 2, '.', '');
                $delta_tva    = number_format(round(($delta_cu_tva * $ctva) / (100 + $ctva), 2, PHP_ROUND_HALF_UP), 2, '.', '');
                $delta_fara   = number_format($delta_cu_tva - $delta_tva, 2, '.', '');
            }

            if ($cant > $take) {
                // scade parÈ›ial
                $sql_up = "UPDATE {$tabel_final_det_note}
                           SET cantitate = cantitate - :q,
                               tva_col = tva_col - :dtva,
                               valoare_vanzare = valoare_vanzare - :dfara,
                               valoare_vanzare_cu_tva = valoare_vanzare_cu_tva - :dcu,
                               t_list = '0'
                           WHERE id_vanz = :idv";
                $pdo->prepare($sql_up)->execute([
                    ':q' => $take, ':dtva' => $delta_tva, ':dfara' => $delta_fara, ':dcu' => $delta_cu_tva, ':idv' => $idv
                ]);

                $response['updated'][] = [
                    'id_vanz'                => $idv,
                    'cantitate'              => round($cant - $take, 2),
                    'valoare_vanzare_cu_tva' => $qty_has3
                        ? (float) money_round(dec_mul(($cant - $take), $pu), 2)
                        : (float) number_format(round(($cant - $take) * $pu, 2, PHP_ROUND_HALF_UP), 2, '.', '')
                ];
            } else {
                // È˜TERGEREA LINIEI: Ã®ntÃ¢i discount, apoi det_note  <-- FIX
                $pdo->prepare("DELETE FROM discounturi_acordate WHERE id_vanz = :idv")->execute([':idv' => $idv]);
                $pdo->prepare("DELETE FROM {$tabel_final_det_note} WHERE id_vanz = :idv")->execute([':idv' => $idv]);
                $response['removed'][] = $idv;
            }

            $de_scazut -= $take;
        }

        // 2) Scade È™i garanÈ›iile SGR aferente (dacÄƒ e cazul)
        $reduceSGR = function($cod_garantie, $q) use ($nr_bon, $pachet, $pdo, $tabel_final_det_note, &$response, $qty_has3) {
            if ($q <= 0) return;
            $sql = "SELECT id_vanz, cantitate, pret_vanzare, cota_tva
                    FROM {$tabel_final_det_note}
                    WHERE nr_bon = :nr AND cod_p = :cp AND pachet = :p
                    ORDER BY id_vanz DESC";
            $st = $pdo->prepare($sql);
            $st->execute([':nr' => $nr_bon, ':cp' => $cod_garantie, ':p' => $pachet]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);

            $de_s = $q;
            foreach ($rows as $r) {
                if ($de_s <= 0) break;

                $idv  = (int)$r['id_vanz'];
                $cant = (float)$r['cantitate'];
                $pu   = (float)$r['pret_vanzare'];
                $ctva = (float)$r['cota_tva'];

                $take = min($cant, $de_s);

                if ($qty_has3) {
                    $delta_cu_tva = money_round(dec_mul($pu, $take), 2);
                    list($delta_fara, $delta_tva) = tva_split_from_cu_tva($delta_cu_tva, $ctva);
                } else {
                    $delta_cu_tva = number_format(round($pu * $take, 2, PHP_ROUND_HALF_UP), 2, '.', '');
                    $delta_tva    = number_format(round(($delta_cu_tva * $ctva) / (100 + $ctva), 2, PHP_ROUND_HALF_UP), 2, '.', '');
                    $delta_fara   = number_format($delta_cu_tva - $delta_tva, 2, '.', '');
                }

                if ($cant > $take) {
                    $pdo->prepare("UPDATE {$tabel_final_det_note}
                                   SET cantitate = cantitate - :q,
                                       tva_col = tva_col - :dtva,
                                       valoare_vanzare = valoare_vanzare - :df,
                                       valoare_vanzare_cu_tva = valoare_vanzare_cu_tva - :dcu
                                   WHERE id_vanz = :idv")
                        ->execute([':q'=>$take, ':dtva'=>$delta_tva, ':df'=>$delta_fara, ':dcu'=>$delta_cu_tva, ':idv'=>$idv]);

                    $response['updated'][] = [
                        'id_vanz'                => $idv,
                        'cantitate'              => round($cant - $take, 2),
                        'valoare_vanzare_cu_tva' => $qty_has3
                            ? (float) money_round(dec_mul(($cant - $take), $pu), 2)
                            : (float) number_format(round(($cant - $take) * $pu, 2, PHP_ROUND_HALF_UP), 2, '.', '')
                    ];
                } else {
                    // È˜TERGEREA LINIEI SGR: Ã®ntÃ¢i discount (dacÄƒ ar exista), apoi det_note  <-- FIX
                    $pdo->prepare("DELETE FROM discounturi_acordate WHERE id_vanz = :idv")->execute([':idv' => $idv]);
                    $pdo->prepare("DELETE FROM {$tabel_final_det_note} WHERE id_vanz = :idv")->execute([':idv' => $idv]);
                    $response['removed'][] = $idv;
                }
                $de_s -= $take;
            }
        };

        if ($gestiune !== "PRODUSE FINITE") {
            if (!empty($sgr_flags['sgr'])        && (int)$sgr_flags['sgr']        === 1) $reduceSGR(-2, abs($cantitate));
            if (!empty($sgr_flags['sgr_pet'])    && (int)$sgr_flags['sgr_pet']    === 1) $reduceSGR(-3, abs($cantitate));
            if (!empty($sgr_flags['sgr_alumin']) && (int)$sgr_flags['sgr_alumin'] === 1) $reduceSGR(-4, abs($cantitate));
            if (!empty($sgr_flags['sgr_sticla']) && (int)$sgr_flags['sgr_sticla'] === 1) $reduceSGR(-5, abs($cantitate));
        }
    }

    $pdo->commit();

    if ($isBarcodeLookup) {
        // Trimite È™i detaliile complete pentru a popula cache-ul din browser
        $response['product_details_for_cache'] = $produs_info;
    }

    echo json_encode($response);

} catch (PDOException | Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
exit;

