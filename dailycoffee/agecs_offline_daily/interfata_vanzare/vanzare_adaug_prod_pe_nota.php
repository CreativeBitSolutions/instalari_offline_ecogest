<?php
/**
 * vanzare_adaug_prod_pe_nota.php
 * VERSIUNE SUPER-OPTIMIZATĂ (cu suport cantitate negativă -> scădere fără rânduri negative)
 * - Adaugă/actualizează produsul în baza de date (cantități pozitive).
 * - La cantități negative: scade din det_note (până la 0) și șterge liniile ajunse la 0; scade și SGR.
 * - Folosește cache per client+locație pentru lookup după cod de bare.
 * - Returnează JSON cu liniile adăugate/actualizate/șterse (inclusiv SGR).
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

// === Helpers rotunjire condiționată (strict când cantitatea are 3 zecimale) ===
if (!function_exists('has_three_decimals')) {
    function has_three_decimals(string $qtyRaw): bool {
        $s = str_replace(',', '.', trim($qtyRaw));
        if (strpos($s, '.') === false) return false;
        $dec = substr($s, strpos($s, '.') + 1);
        // 0.15000 -> considerăm 3 zecimale (0.150)
        $dec = rtrim($dec, '0');
        return strlen($dec) === 3;
    }
    // HALF-UP deterministic, pe string (fallback dacă nu ai BCMath)
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

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

try {
    // ===================================================================
    // 1. PRELUARE ȘI VALIDARE DATE DE INTRARE
    // ===================================================================
    $nr_bon   = filter_input(INPUT_GET, 'bonul', FILTER_VALIDATE_INT);
    $cod_masa = $_GET['cod_masa'] ?? null;

    // Acceptă și virgulă ca separator zecimal; permite cantitate negativă
    $raw_qty   = $_GET['cantitate_de_adaugat_prod'] ?? '1';
    $raw_qty   = str_replace(',', '.', $raw_qty);
    $cantitate = round((float)$raw_qty, 5);
    // doar dacă e exact 0, punem fallback 1 (valorile negative sunt permise)
    if ($cantitate == 0.0) $cantitate = 1.0;

    // Flag: aplicăm rotunjirea specială doar dacă cantitatea are exact 3 zecimale
    $qty_has3 = has_three_decimals($raw_qty);

    if (!$nr_bon || $cod_masa === null) {
        throw new Exception("Date de intrare invalide (bon sau masa).");
    }

    $sgr_flags = [];
    $isBarcodeLookup = !empty($_GET['prod_cod_bare']);

    // Context pentru cache per client+locație
    $client_id   = $_SESSION['client_id']   ?? 'anon';
    $cod_locatie = (int)($_SESSION['cod_locatie'] ?? 2);

    // ===================================================================
    // 1.a Lookup produs (cod bare cu cache) SAU direct din querystring
    // ===================================================================
    if ($isBarcodeLookup) {
        // ---- Cache pentru căutare după cod de bare ----
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

        // Dacă nu avem în cache → db
        if (!$produs_info) {
            // Mapare cod_bare -> cod_produs (tabel ajutător rapid)
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

            // Scriere în cache (atomic)
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

        // Populate variabile necesare logicii de vânzare
        $cod_p        = $produs_info['cod_produs'];
        $nume_produs  = $produs_info['nume'];
        $pret_vanzare = (float)$produs_info['pret_cu_tva'];
        $cota_tva     = (float)$produs_info['cota_tva'];
        $um           = $produs_info['um'];
        $gestiune     = $produs_info['denumire_gestiune'];

        $sgr_flags = [
            'sgr'         => $produs_info['sgr']         ?? 0,
            'sgr_pet'     => $produs_info['sgr_pet']     ?? 0,
            'sgr_alumin'  => $produs_info['sgr_alumin']  ?? 0,
            'sgr_sticla'  => $produs_info['sgr_sticla']  ?? 0,
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
            'sgr'         => $_GET['sgr']         ?? 0,
            'sgr_pet'     => $_GET['sgr_pet']     ?? 0,
            'sgr_alumin'  => $_GET['sgr_alumin']  ?? 0,
            'sgr_sticla'  => $_GET['sgr_sticla']  ?? 0,
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
                 AND (data_inceput IS NULL OR date(data_inceput) <= date('now','localtime'))
                 AND (data_sfarsit  IS NULL OR date(data_sfarsit)  >= date('now','localtime'))
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
    // 3. OPERAȚIUNI BAZĂ DE DATE (ADĂUGARE sau SCĂDERE)
    // ===================================================================
    $pdo->beginTransaction();

    $response = ['updated' => [], 'added' => [], 'removed' => []];

    if ($cantitate >= 0) {
        // --- LOGICA ORIGINALĂ DE ADĂUGARE / MĂRIRE CANTITATE ---
        if ($qty_has3) {
            $valoare_vanzare_cu_tva = money_round(dec_mul($pret_vanzare, $cantitate), 2);
            list($valoare_vanzare, $tva_col) = tva_split_from_cu_tva($valoare_vanzare_cu_tva, $cota_tva);
        } else {
            $valoare_vanzare_cu_tva = number_format(round($pret_vanzare * $cantitate, 2, PHP_ROUND_HALF_UP), 2, '.', '');
            $tva_col                = number_format(round(($valoare_vanzare_cu_tva * $cota_tva) / (100 + $cota_tva), 2, PHP_ROUND_HALF_UP), 2, '.', '');
            $valoare_vanzare        = number_format($valoare_vanzare_cu_tva - $tva_col, 2, '.', '');
        }

        // Dacă există deja aceeași linie (același cod, același preț, același pachet), creștem cantitatea
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

        if ($existing_item) {
            $id_vanz_existent = (int)$existing_item['id_vanz'];

            $sql_update = "UPDATE {$tabel_final_det_note}
                           SET cantitate = cantitate + :cantitate,
                               tva_col = tva_col + :tva_col,
                               valoare_vanzare = valoare_vanzare + :valoare_vanzare,
                               valoare_vanzare_cu_tva = valoare_vanzare_cu_tva + :valoare_cu_tva,
                               t_list = '0',
                               cod_locatie = :cod_locatie
                           WHERE id_vanz = :id_vanz";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([
                ':cantitate'       => $cantitate,
                ':tva_col'         => $tva_col,
                ':valoare_vanzare' => $valoare_vanzare,
                ':valoare_cu_tva'  => $valoare_vanzare_cu_tva,
                ':cod_locatie'     => $cod_locatie,
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
                                valoare_vanzare, valoare_vanzare_cu_tva, discount, pachet, preparat,
                                t_list, data, ora, cod_meniu, observatie_produs, preluat_osp, prioritate, cod_locatie)
                        VALUES
                            (:nr_bon, :cod_p, :nume, :cantitate, :cota_tva, :tva_col, :pret,
                                :val_vanzare, :val_vanzare_tva, 0, :pachet, 0,
                                0, date('now','localtime'), time('now','localtime'), 0, '', 0, 0, :cod_locatie)";

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
                ':pachet'          => $pachet,
                ':cod_locatie'     => $cod_locatie
            ]);
            $last_id = (int)$pdo->lastInsertId();

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

        // ===================================================================
        // 4. Garanții SGR (adăugate ca linii distincte pe bon) — DOAR LA ADĂUGARE
        // ===================================================================
        function adaugaGarantieSGR($cod_garantie, $cantitate, $nr_bon, $pachet, $cod_locatie, $pdo, $tabel_final_nomenclator, $tabel_final_det_note, &$responseArray) {
            $stmt = $pdo->prepare("SELECT nume, pret_cu_tva, cota_tva, um
                                   FROM {$tabel_final_nomenclator}
                                   WHERE cod_produs = :cod_garantie
                                   LIMIT 1");
            $stmt->execute([':cod_garantie' => $cod_garantie]);
            if ($garantie = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $pret       = (float)$garantie['pret_cu_tva'];
                $cota_tva_g = (float)$garantie['cota_tva'];

                // Aplică aceeași regulă de rotunjire dacă cantitatea are 3 zecimale
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
                             valoare_vanzare, valoare_vanzare_cu_tva, discount, pachet, preparat,
                             t_list, data, ora, cod_meniu, observatie_produs, preluat_osp, prioritate, cod_locatie)
                        VALUES
                            (:nr_bon, :cod_p, :nume, :cant, :cota_tva, :tva_col, :pret, :val, :val_tva, 0, :pachet, 0,
                             0, date('now','localtime'), time('now','localtime'), 0, '', 0, 0, :cod_locatie)";
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
                    ':pachet'   => $pachet,
                    ':cod_locatie' => $cod_locatie
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
            if (!empty($sgr_flags['sgr'])        && (int)$sgr_flags['sgr']        === 1) adaugaGarantieSGR(-2, $cantitate, $nr_bon, $pachet, $cod_locatie, $pdo, $tabel_final_nomenclator, $tabel_final_det_note, $response);
            if (!empty($sgr_flags['sgr_pet'])    && (int)$sgr_flags['sgr_pet']    === 1) adaugaGarantieSGR(-3, $cantitate, $nr_bon, $pachet, $cod_locatie, $pdo, $tabel_final_nomenclator, $tabel_final_det_note, $response);
            if (!empty($sgr_flags['sgr_alumin']) && (int)$sgr_flags['sgr_alumin'] === 1) adaugaGarantieSGR(-4, $cantitate, $nr_bon, $pachet, $cod_locatie, $pdo, $tabel_final_nomenclator, $tabel_final_det_note, $response);
            if (!empty($sgr_flags['sgr_sticla']) && (int)$sgr_flags['sgr_sticla'] === 1) adaugaGarantieSGR(-5, $cantitate, $nr_bon, $pachet, $cod_locatie, $pdo, $tabel_final_nomenclator, $tabel_final_det_note, $response);
        }

    } else {
        // --- CANTITATE NEGATIVĂ → SCADERE FĂRĂ RÂNDURI NEGATIVE ---
        $de_scazut = abs($cantitate);

        // 1) ia liniile produsului de pe bon (același pachet), începând cu cele mai recente
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

            // delta pe valori (formule originale, cu rotunjire condiționată dacă e cazul)
            if ($qty_has3) {
                $delta_cu_tva = money_round(dec_mul($pu, $take), 2);
                list($delta_fara, $delta_tva) = tva_split_from_cu_tva($delta_cu_tva, $ctva);
            } else {
                $delta_cu_tva = number_format(round($pu * $take, 2, PHP_ROUND_HALF_UP), 2, '.', '');
                $delta_tva    = number_format(round(($delta_cu_tva * $ctva) / (100 + $ctva), 2, PHP_ROUND_HALF_UP), 2, '.', '');
                $delta_fara   = number_format($delta_cu_tva - $delta_tva, 2, '.', '');
            }

            if ($cant > $take) {
                // scade parțial
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
                // ȘTERGEREA LINIEI: întâi discount, apoi det_note  <-- FIX
                $pdo->prepare("DELETE FROM discounturi_acordate WHERE id_vanz = :idv")->execute([':idv' => $idv]);
                $pdo->prepare("DELETE FROM {$tabel_final_det_note} WHERE id_vanz = :idv")->execute([':idv' => $idv]);
                $response['removed'][] = $idv;
            }

            $de_scazut -= $take;
        }

        // 2) Scade și garanțiile SGR aferente (dacă e cazul)
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
                    // ȘTERGEREA LINIEI SGR: întâi discount (dacă ar exista), apoi det_note  <-- FIX
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
        // Trimite și detaliile complete pentru a popula cache-ul din browser
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
