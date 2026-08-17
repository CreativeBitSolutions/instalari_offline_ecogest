<?php //vanzare_restaurant_listare_nota.php
session_start();
ini_set('display_errors', 0); // Nu afișează erorile utilizatorului
ini_set('log_errors', 1); // Activează logarea erorilor
ini_set('error_log', 'error_log.log'); // Specifică calea către fișierul de log
error_reporting(E_ALL); // Raportează toate tipurile de erori
include('database_connection.php');
require_once __DIR__ . '/det_note_import_schema.php';
require_once __DIR__ . '/det_note_departament_listare_schema.php';

restaurant_v2_ensure_det_note_site_import_column(
    $pdo,
    isset($tabel_final_det_note) ? $tabel_final_det_note : 'det_note'
);
$detNoteTable = isset($tabel_final_det_note) ? $tabel_final_det_note : 'det_note';
agecs_ensure_det_note_departament_listare($pdo, $detNoteTable);
$departamentListareSql = agecs_departament_listare_sql('dn', 'ps');
$printedProductNameSql = "CASE
        WHEN TRIM(COALESCE(dn.nume_produs, '')) <> '' THEN dn.nume_produs
        ELSE ps.nume
    END";

// --- FUNCȚII PENTRU ECRANUL DE AȘTEPTARE ---
function init_loading_screen() {
    while (ob_get_level()) { ob_end_clean(); }
    echo '<!DOCTYPE html><html><head><meta charset="utf-8">';
    echo '<style>body{background:#f4f7f6;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;font-family:sans-serif}.card{background:#fff;padding:30px 40px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.1);text-align:center}.spinner{margin-bottom:20px;color:#007bff;font-size:40px;display:inline-block;animation:spin 1.5s linear infinite;} @keyframes spin { 100% { transform: rotate(360deg); } } .text-muted{color:#6c757d;font-size:16px;margin-top:15px;}</style>';
    echo '</head><body>';
    echo '<div class="card"><div class="spinner">⏳</div>';
    echo '<h3>Vă rugăm așteptați...</h3>';
    echo '<p class="text-muted" id="loading-status">Se pregătesc datele...</p>';
    echo '</div>';
    echo '<script>function updateStatus(msg) { document.getElementById("loading-status").innerHTML = msg; }</script>';
    echo '</body></html>';
    flush();
}

function update_loading_status($msg) {
    echo '<script>updateStatus(' . json_encode($msg, JSON_UNESCAPED_UNICODE) . ');</script>';
    flush();
}

function redirect_to_vanzare_restaurant($delayMs = 0) {
    echo '<script>setTimeout(function() { location.href = "vanzare_restaurant.php"; }, ' . (int)$delayMs . ');</script>';
    flush();
}

function publish_printer_queue_file($queueFile, array $queuePayload) {
    $jsonData = json_encode($queuePayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    if ($jsonData === false) {
        throw new RuntimeException('Nu se poate genera JSON-ul pentru imprimanta.');
    }

    $tmpFile = $queueFile . '.tmp.' . getmypid() . '.' . uniqid('', true);

    if (file_put_contents($tmpFile, $jsonData, LOCK_EX) === false) {
        throw new RuntimeException('Nu se poate scrie fisierul temporar pentru imprimanta: ' . $tmpFile);
    }

    clearstatcache(true, $queueFile);
    if (is_file($queueFile)) {
        $archiveFile = $queueFile . '.vechi.' . date('YmdHis') . '.' . getmypid() . '.' . uniqid('', true);

        if (!@rename($queueFile, $archiveFile) && !@unlink($queueFile)) {
            @unlink($tmpFile);
            throw new RuntimeException('Fisierul cozii de imprimare este blocat: ' . $queueFile);
        }
    }

    if (!@rename($tmpFile, $queueFile)) {
        @unlink($tmpFile);
        throw new RuntimeException('Nu se poate publica fisierul pentru imprimanta: ' . $queueFile);
    }
}
function bold_content_for_client_8($content, $client_id) {
    if ((int)$client_id !== 8) {
        return $content;
    }

    $lines = preg_split('/\\r\\n|\\r|\\n/', (string)$content);
    foreach ($lines as &$line) {
        if ($line === '') {
            continue;
        }
        $line = '<b>' . $line . '</b>';
    }
    unset($line);

    return implode("\n", $lines);
}

// -------------------------------------------

init_loading_screen();
update_loading_status("Colectăm produsele către secțiile de producție...");

$_SESSION['trimis_comanda'] = 0;
// Obținem parametrii din POST și din sesiune
$nr_bon       = isset($_POST['nota_de_relistat']) ? intval($_POST['nota_de_relistat']) : 0;
$cod_locatie  = isset($_SESSION['cod_locatie'])   ? intval($_SESSION['cod_locatie'])   : 0;
$masa_curenta = $_SESSION['masa_curenta'];
$adm_id       = $_SESSION['admin_id'];
$client_id    = $_SESSION['client_id'];
$hide_discount = in_array((int)$client_id, [25, 26], true);
// Filtrul implicit pentru produse noi (care nu au fost trimise la imprimantă)
$filter_t_list = "dn.t_list = 0";

// Dacă se apasă pe "Trimite la imprimantă toată nota" sau "Relistează nota de plată"
if ((isset($_POST['listeaza_tot']) && $_POST['listeaza_tot'] === 'da') ||
    (isset($_POST['relistare_nota_plata']) && $_POST['relistare_nota_plata'] === 'da')) {
    $filter_t_list = "1=1";
}

// Definim calea către folderul unde se va salva fișierul JSON
$folder_path = RESTAURANT_OFFLINE_API_DIR . "/" . $client_id . "/" . $cod_locatie;
if (!is_dir($folder_path)) {
    mkdir($folder_path, 0777, true);
}

$nume_masa = "";
$masa_sql  = "SELECT nume_masa FROM mese WHERE cod_masa = :cod_masa LIMIT 1";
$masa_stmt = $pdo->prepare($masa_sql);
$masa_stmt->execute([':cod_masa' => $masa_curenta]);
$masa_data = $masa_stmt->fetch(PDO::FETCH_ASSOC);

if ($masa_data && isset($masa_data['nume_masa'])) {
    $nume_masa = $masa_data['nume_masa'];
}

$df_sql  = "SELECT * FROM date_firma LIMIT 1";
$df_stmt = $pdo->prepare($df_sql);
$df_stmt->execute();
$date_firma = $df_stmt->fetch(PDO::FETCH_ASSOC);

// Variabile pentru antet - se folosește doar pseudonimul firmei
$pseudonim_firma = $date_firma['pseudonim_firma'] ?? "";
$data_bon = date('Y-m-d');
$ora_bon  = date('H:i:s');

// Preluăm din sesiune numele și prenumele operatorului
$admin_firstname = isset($_SESSION['admin_firstname']) ? $_SESSION['admin_firstname'] : "Operator";
$admin_lastname  = isset($_SESSION['admin_lastname'])  ? $_SESSION['admin_lastname']  : "";

$numerar  = $_SESSION['numerarprim']   ?? 0;
$tichete  = $_SESSION['total_tichete'] ?? 0;
$card     = $_SESSION['cardprim']      ?? 0;
$glovo    = $_SESSION['glovo']         ?? 0;

$protocol = 0;
$rest     = 0;

try {
    $departments_sql = "
        SELECT DISTINCT $departamentListareSql AS departament
        FROM $tabel_final_det_note dn
        LEFT JOIN $tabel_final_nomenclator ps ON dn.cod_p = ps.cod_produs
        WHERE dn.nr_bon = :nrbon
          AND $departamentListareSql IS NOT NULL
          AND $departamentListareSql != ''
    ";
    $departments_stmt = $pdo->prepare($departments_sql);
   $departments_stmt->execute([':nrbon' => $nr_bon]);
    // 1. Preluăm toate înregistrările de departamente așa cum sunt (ex: 'BAR', 'BAR,BUCATARIE')
    $all_departments_raw = $departments_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // 2. Creăm o listă "aplatizată" și unică
    $departments_flat = [];
    foreach ($all_departments_raw as $dept_string) {
        $parts = explode(',', $dept_string); // Spargem string-ul la fiecare virgulă
        foreach ($parts as $part) {
            $trimmed_part = trim($part); // Curățăm spațiile
            if (!empty($trimmed_part) && !in_array($trimmed_part, $departments_flat)) {
                $departments_flat[] = $trimmed_part; // Adăugăm doar departamentele unice
            }
        }
    }
    
    // Acum $departments_flat conține o listă curată, ex: ['BAR', 'BUCATARIE', 'IMPRIMANTA3']

    $printData = [];

    // 3. Modificăm condiția 'if' și bucla 'foreach' de mai jos
    if (!empty($departments_flat)) { // Verificăm noua listă
        $current_date = date('Y-m-d');
        $current_time = date('H:i:s');
        $de_trimis    = 1;

foreach ($departments_flat as $departament_listare) { 
        // ✅ MODIFICARE: Am adăugat `dn.prioritate` în SELECT și am ordonat rezultatele
            $products_sql = "
                SELECT 
                    dn.pachet,
                    dn.discount,
                    dn.cod_p,
                    $printedProductNameSql AS nume,
                    ps.descriere_en,       
                    dn.observatie_produs,
                    ps.um,
                    dn.cantitate,
                    dn.tva_col,
                    dn.pret_vanzare,
                    dn.valoare_vanzare,
                    dn.valoare_vanzare_cu_tva,
                    ps.cota_tva,
                    $departamentListareSql AS departament,
                    dn.prioritate
                FROM $tabel_final_det_note dn
                LEFT JOIN $tabel_final_nomenclator ps ON dn.cod_p = ps.cod_produs
                WHERE dn.nr_bon = :nrbon
                  AND FIND_IN_SET(REPLACE(:departament, ' ', ''), REPLACE($departamentListareSql, ' ', '')) > 0
                  AND $filter_t_list
                ORDER BY CASE WHEN COALESCE(dn.prioritate, 0) = 0 THEN 1 ELSE 0 END, dn.prioritate ASC, dn.id_vanz
            ";
            $products_stmt = $pdo->prepare($products_sql);
            $products_stmt->execute([':nrbon' => $nr_bon, ':departament' => $departament_listare]);
            $products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($products)) {
                continue;
            }

            // Definirea separatorului standard pentru lizibilitate
            $separator = str_repeat('-', 20) . "\n";

            // Construim conținutul pentru fiecare bon - Antet
$continut = "COMANDA:\n";
$continut .= "Nr. nota: " . $nr_bon . "\n";
$continut .= $separator;
$continut .= $pseudonim_firma . "\n";
$continut .= $data_bon . " " . $ora_bon . "\n";
$continut .= "OPERATOR: " . $admin_firstname . " " . $admin_lastname . "\n";
$continut .= "Masa: " . $nume_masa . "\n";
$continut .= $separator;

$total_nota = 0;
            
            // Grupăm produsele: dacă observația este goală, adunăm cantitățile și valorile (LOGICA ORIGINALĂ PĂSTRATĂ)
            // Excepție: pentru BUCATARIE nu grupăm deloc, ca produsele trimise separat să rămână pe rânduri separate.
            $groupedProducts = [];
            $dept_listare_curent = strtoupper(trim((string)$departament_listare));
            $este_listare_bucatarie = (strpos($dept_listare_curent, 'BUCATARIE') !== false);

            if ($client_id == 9 || $client_id == 8 || $este_listare_bucatarie) {
                // Pentru client_id 9/8 și pentru BUCATARIE NU grupăm deloc, păstrăm ordinea existentă de listare.
                foreach ($products as $product) {
                    $groupedProducts[] = $product;
                }
            } else {
                foreach ($products as $product) {
                    $departamente_produs = strtoupper(str_replace(' ', '', (string)($product['departament'] ?? '')));
                    $este_produs_multi_departament_cu_bucatarie = (
                        (intval($client_id) === 25 || intval($client_id) === 26) &&
                        strpos($departamente_produs, ',') !== false &&
                        in_array('BUCATARIE', array_map('trim', explode(',', $departamente_produs)), true)
                    );

                    if ($este_produs_multi_departament_cu_bucatarie) {
                        // Pentru client_id 25/26, produsele care au ps.departament de forma BAR,BUCATARIE rămân individuale
                        $groupedProducts[] = $product;
                        continue;
                    }

                    if (empty(trim($product['observatie_produs']))) {
                        // ✅ MODIFICARE: Adăugăm și prioritatea la cheie pentru a nu combina produse identice din feluri diferite
                        $key = $product['nume'] . '_' . $product['prioritate'];
                    } else {
                        // Dacă există observație, folosim o cheie unică pentru a nu combina înregistrările
                        $key = $product['nume'] . '_' . $product['observatie_produs'] . '_' . $product['prioritate'] . '_' . uniqid();
                    }
                    if (!isset($groupedProducts[$key])) {
                        $groupedProducts[$key] = $product;
                    } else {
                        // Adunăm cantitățile și valorile de vânzare cu TVA pentru produsul grupat
                        $groupedProducts[$key]['cantitate'] += $product['cantitate'];
                        $groupedProducts[$key]['valoare_vanzare_cu_tva'] += $product['valoare_vanzare_cu_tva'];
                    }
                }
            }

            // ✅ MODIFICARE: Distribuim produsele dinamic pe feluri.
            // Felurile negative sunt permise și apar înaintea celor pozitive.
            // FEL 0 este afișat explicit și este mutat mereu ultimul pe foaie.
            $produsePeFeluri = [];

            foreach ($groupedProducts as $product) {
                $prioritate = (int)($product['prioritate'] ?? 0);
                if (!isset($produsePeFeluri[$prioritate])) {
                    $produsePeFeluri[$prioritate] = [];
                }
                $produsePeFeluri[$prioritate][] = $product;
            }

            uksort($produsePeFeluri, function($a, $b) {
                $a = (int)$a;
                $b = (int)$b;

                if ($a === 0 && $b !== 0) return 1;
                if ($b === 0 && $a !== 0) return -1;

                return $a <=> $b;
            });
            
            // ✅ MODIFICARE: Construim conținutul pe secțiuni, păstrând formatarea originală
            
            // Funcție ajutătoare pentru a formata o linie de produs, pentru a nu repeta cod
            // Pasăm $departament_listare cu "use" pentru a verifica departamentul
           $formatProductLine = function($product, $client_id, $separator) use ($departament_listare) {
    $produs                 = $product['nume'];
    $produs_en              = $product['descriere_en'];
    $observatie_produs      = trim((string)($product['observatie_produs'] ?? ''));
    $cantitate              = round($product['cantitate'], 2);
    $valoare_vanzare_cu_tva = $product['valoare_vanzare_cu_tva'];
    $este_meniu_woo_gp25    = (
        (int)$client_id === 25
        && (int)($product['cod_p'] ?? 0) === 447
        && preg_match('/^(MENIUL ZILEI: COMPLET|FEL 2 MEN ZILEI)(?:\s*\+|$)/iu', $produs)
    );

    // Verificăm dacă departamentul este printre cele la care ascundem prețul
    $dept_upper = strtoupper(trim($departament_listare));
    $hide_price = (
        strpos($dept_upper, 'BUCATARIE') !== false ||
        strpos($dept_upper, 'IMPRIMANTA3') !== false ||
        strpos($dept_upper, 'SALATE') !== false
    );

    $line = $cantitate . " x " . $produs;

    // Dacă produsul NU conține "FEL" în nume, și NU suntem la departamentele fără preț, adaugă prețul
    if (stripos($produs, 'FEL') === false && !$hide_price && !$este_meniu_woo_gp25) {
        $line .= " = " . number_format($valoare_vanzare_cu_tva, 2) . " LEI";
    }

    $line .= "\n";

    // Observația apare pe rând nou, sub produs, cu liniuță înainte
    if ($observatie_produs !== '') {
        $obs_lines = preg_split('/\r\n|\r|\n/', $observatie_produs);

        foreach ($obs_lines as $obs_line) {
            $obs_line = trim($obs_line);

            if ($obs_line !== '') {
                $line .= "- " . $obs_line . "\n";
            }
        }
    }

    /* --- Afișăm și varianta ENG sub linia produsului (doar pentru client 3) --- */
    if ($client_id == 3 && !empty(trim($produs_en))) {
        $line .= $produs_en . "\n";
    }

    return $line;
};

            // Adăugăm toate felurile în ordinea stabilită mai sus: negative, pozitive, apoi FEL 0.
            foreach ($produsePeFeluri as $fel => $produse) {
                if (!empty($produse)) {
                    $header_text = " FEL {$fel} ";
                    $continut .= str_pad($header_text, 20, "-", STR_PAD_BOTH) . "\n";
                    foreach ($produse as $product) {
                        $continut .= $formatProductLine($product, $client_id, $separator);
                        $total_nota += $product['valoare_vanzare_cu_tva'];
                    }
                }
            }

            // Adăugăm subsolul
            $continut = rtrim($continut) . "\n" . $separator;
            
            // Ascundem și TOTALUL general dacă e listat la departamentele vizate (la fel, nu este necesar pretul)
            $dept_upper_footer = strtoupper(trim($departament_listare));
            $hide_price_footer = (strpos($dept_upper_footer, 'BUCATARIE') !== false || 
                                  strpos($dept_upper_footer, 'IMPRIMANTA3') !== false || 
                                  strpos($dept_upper_footer, 'SALATE') !== false);
            
            if (!$hide_price_footer) {
                $continut .= "TOTAL: " . number_format($total_nota, 2) . " LEI\n";
            }

            $printData[] = [
                'data'                    => $current_date,
                'ora'                     => $current_time,
                'de_trimis_la_imprimanta' => $de_trimis,
                'nrbon'                   => $nr_bon,
                'locatie'                 => $cod_locatie,
                'departament_listare'     => (isset($_POST['relistare_nota_plata']) && $_POST['relistare_nota_plata'] === 'da') ? "BAR" : $departament_listare,
                'continut'                => bold_content_for_client_8($continut, $client_id)
            ];
        }

        // Actualizam în baza de date pentru a marca că produsele au fost trimise la imprimantă
        $update_sql  = "UPDATE $tabel_final_det_note SET t_list = 1 WHERE nr_bon = :nr_bon";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([':nr_bon' => $nr_bon]);
    }

    // --- Nota de plată pentru client (RĂMÂNE NEMODIFICAT) ---
if (isset($_POST['nota_de_plata_client']) && $_POST['nota_de_plata_client']=='da' && !isset($_POST['listeaza_tot'])) {
        $current_date = date('Y-m-d');
        $current_time = date('H:i:s');

        // 1) Adaugă ps.pret_cu_tva în SELECT
        $products_sql_all = "
            SELECT 
                dn.pachet,
                dn.discount,
                dn.cod_p,
                $printedProductNameSql AS nume,
                ps.descriere,
                dn.observatie_produs,
                ps.um,
                dn.cantitate,
                dn.tva_col,
                dn.pret_vanzare,
                ps.pret_cu_tva,
                dn.valoare_vanzare,
                dn.valoare_vanzare_cu_tva,
                ps.cota_tva,
                $departamentListareSql AS departament
            FROM $tabel_final_det_note dn
            JOIN $tabel_final_nomenclator ps ON dn.cod_p = ps.cod_produs
            WHERE dn.nr_bon = :nrbon AND dn.pret_vanzare > 0
        ";
        $products_all_stmt = $pdo->prepare($products_sql_all);
        $products_all_stmt->execute([':nrbon' => $nr_bon]);
        $all_products = $products_all_stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($all_products)) {
            // 2) Calculează suma totală de discount
            $suma_discount_total = 0;
            foreach ($all_products as $prod) {
                if ($prod['pret_cu_tva'] != $prod['pret_vanzare']) {
                    $suma_discount_total +=
                        ($prod['pret_cu_tva'] - $prod['pret_vanzare'])
                        * $prod['cantitate'];
                }
            }

            $nota_plata_continut = "NOTĂ DE PLATĂ\n";
 // 3) Antetul pentru nota de plată (Modificat: Nr nota și Masa primele)
$nota_plata_continut .= "Nr. nota: " . $nr_bon . "\n";
$nota_plata_continut .= "-----\n";
$nota_plata_continut .= $pseudonim_firma . "\n";
$nota_plata_continut .= $data_bon . " " . $ora_bon . "\n";
$nota_plata_continut .= "OPERATOR: " . $admin_firstname . " " . $admin_lastname . "\n";
$nota_plata_continut .= "Masa: " . $nume_masa . "\n";
$nota_plata_continut .= "-----\n";

            $total_nota = 0;
            // 4) Grupăm produsele pentru nota de plată în mod similar
            $groupedProducts = [];
            foreach ($all_products as $product) {
                if (empty(trim($product['observatie_produs']))) {
                    $key = $product['descriere'];
                } else {
                    $key = $product['descriere'] . '_' . $product['observatie_produs'] . '_' . uniqid();
                }
                if (!isset($groupedProducts[$key])) {
                    $groupedProducts[$key] = $product;
                } else {
                    $groupedProducts[$key]['cantitate'] += $product['cantitate'];
                    $groupedProducts[$key]['valoare_vanzare_cu_tva'] += $product['valoare_vanzare_cu_tva'];
                }
            }

           foreach ($groupedProducts as $product) {
    // ============== ADĂUGARE PENTRU CLIENT_ID 9 =================
    $produs             = $product['descriere'];
    if ($client_id == 9) {
        $produs .= " (" . number_format($product['pret_vanzare'], 2) . ")";
    }
    // ============================================================

    $observatie_produs      = $product['observatie_produs'];
    $cantitate              = round($product['cantitate'], 2);
    $valoare_vanzare_cu_tva = $product['valoare_vanzare_cu_tva'];
    $total_nota            += $valoare_vanzare_cu_tva;

    $line = $cantitate . " x " . $produs;
    // Modificarea este aici: se adaugă condiția "$client_id != 9"
    if (!empty(trim($observatie_produs)) && $client_id != 9) {
        $line .= " " . $observatie_produs;
    }
    $line .= " = " . number_format($valoare_vanzare_cu_tva, 2) . " LEI";
    $nota_plata_continut .= $line . "\n";
}

            // Adăugăm linia cu numărul notei și masa
          
            $nota_plata_continut .= "-----\n";

            // 5) Dacă avem discount, afișăm valoarea fără discount și discount-ul
if ($suma_discount_total > 0 && !$hide_discount) {
                    // calculăm valoarea fără discount
                $valoare_fara_discount = $total_nota + $suma_discount_total;
                // calcul procent discount
                if($valoare_fara_discount > 0) {
                    $procent_discount = ($suma_discount_total / $valoare_fara_discount) * 100;
                } else {
                    $procent_discount = 0;
                }

                // ——— Verificare dacă avem bacșiș ———
                $cu_bacsis = "";  // default
                $bacsis_sql = "
                    SELECT COUNT(*)
                    FROM $tabel_final_det_note dn
                    JOIN $tabel_final_nomenclator ps ON dn.cod_p = ps.cod_produs
                    WHERE dn.nr_bon = :nrbon
                      AND ps.nume LIKE '%BACSIS%'
                ";
                $bacsis_stmt = $pdo->prepare($bacsis_sql);
                $bacsis_stmt->execute([':nrbon' => $nr_bon]);
                if ($bacsis_stmt->fetchColumn() > 0) {
                    $cu_bacsis = " +BACSIS ";
                }

                $nota_plata_continut .= "Val fără discount " . $cu_bacsis . ":"
                    . number_format($valoare_fara_discount, 2)
                    . " LEI\n";
                // afișăm discount-ul acordat
                $nota_plata_continut .= "Discount " . $cu_bacsis . ":"
                    . number_format($suma_discount_total, 2)
                    . " LEI\n";
                $nota_plata_continut .= "Discount procentual " . $cu_bacsis . ":"
                    . number_format($procent_discount, 2)
                    . " %\n";
            }

            // total de plată
            $nota_plata_continut .= "TOTAL: "
                . number_format($total_nota, 2)
                . " LEI\n";

            // 6) Dacă în detaliu nu există produs cu cod_p = -1, afișăm sugestii pentru bacșiș
            $check_sql = "
                SELECT COUNT(*)
                FROM $tabel_final_det_note
                WHERE nr_bon = :nrbon
                  AND cod_p = -1
            ";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([':nrbon' => $nr_bon]);
            $has_minus_one = $check_stmt->fetchColumn() > 0;

            if (!$has_minus_one) {
                $nota_plata_continut .= "\nBacșișul nu este inclus / Tips not included\n\n";
if (!in_array($client_id, [23])) {
    $nota_plata_continut .= "Sugestii bacșiș / Suggested tips:\n\n";
}

                // antet tabel
                $nota_plata_continut .= "Bacșiș\tTotal notă\n";

                // procentele dorite
                $percentages = [10, 12, 15];
                foreach ($percentages as $pct) {
                    // calcul bacșiș și total cu bacșiș
                    $tip       = round($total_nota * $pct / 100, 2);
                    $total_tip = round($total_nota + $tip, 2);
                    $nota_plata_continut .=
                        $pct . "%\t"
                        . number_format($tip, 2) . "\t"
                        . number_format($total_tip, 2)
                        . "\n";
                }

                // spațiu pentru înscriere manuală
                $nota_plata_continut .= "\nAltă valoare: ...\n";
            }

            $printData[] = [
                'data'                    => $current_date,
                'ora'                     => $current_time,
                'de_trimis_la_imprimanta' => 1,
                'nrbon'                   => $nr_bon,
                'locatie'                 => $cod_locatie,
                'departament_listare'     => "BAR",
                'continut'                => bold_content_for_client_8($nota_plata_continut, $client_id)
            ];
        }

        // Marchează nota ca listată în tabela note
        $update_sql  = "UPDATE note SET listat_nota_plata = 1 WHERE nrbon = :nrbon";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([':nrbon' => $nr_bon]);
    }


    // ✅ *** START BLOC MODIFICAT PENTRU SCRIERE JSON COMBINAT ***
    
    if (!empty($printData)) {
        $json_file_path_imprimanta = $folder_path . "/de_listat_la_imprimanta.json";
    
        update_loading_status("Se verifică imprimanta (generare combinată)...");
        $totalWait = 0;
        
        // Asteptare scurta pentru fisierul preluat de serviciul de imprimare.
        while (is_file($json_file_path_imprimanta) && $totalWait < 10) {
            sleep(1);
            $totalWait += 1;
            clearstatcache(true, $json_file_path_imprimanta);
        }
    
        if (is_file($json_file_path_imprimanta)) {
            error_log("Fisier vechi gasit in coada imprimantei dupa " . $totalWait . " secunde: " . $json_file_path_imprimanta);
            update_loading_status("Se publică noua listare...");
        } else {
            update_loading_status("Se trimit listările cumulate...");
        }

        $mesaj_succes = (count($printData) > 1) 
            ? "Date pentru imprimantă generate pentru departamente multiple." 
            : "Date pentru imprimantă generate pentru un singur departament.";

        // Generăm un singur fișier JSON care conține TOT array-ul în interiorul "data"
        $json_array_imprimanta = [
            "status"  => "success",
            "message" => $mesaj_succes,
            "data"    => $printData
        ];
        
        publish_printer_queue_file($json_file_path_imprimanta, $json_array_imprimanta);
    }
    // ✅ *** FINAL BLOC MODIFICAT ***

    if ((isset($_POST['listeaza_tot']) && $_POST['listeaza_tot'] === 'nu')) {
        $_SESSION['trimis_comanda'] = 1;
    }

    // Facem să se afișeze grila mese mereu după listare ca să apuce imprimanta să listeze
    $_SESSION['trimis_comanda'] = 1;

    update_loading_status("Comanda finalizată! Redirecționăm...");

    redirect_to_vanzare_restaurant();
} catch (PDOException $e) {
    error_log("Eroare la generarea datelor pentru imprimantă: " . $e->getMessage());
    update_loading_status("A apărut o eroare la generarea listării. Revenim la vânzare...");
    redirect_to_vanzare_restaurant(1500);
} catch (Exception $e) {
    error_log("Eroare la publicarea datelor pentru imprimantă: " . $e->getMessage());
    update_loading_status("A apărut o eroare la trimiterea către imprimantă. Revenim la vânzare...");
    redirect_to_vanzare_restaurant(1500);
}
?>
