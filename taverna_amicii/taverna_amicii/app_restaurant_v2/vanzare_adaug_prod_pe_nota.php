<?php //vanzare_adaug_prod_pe_nota.php
include('session.php');
require_once __DIR__ . '/det_note_departament_listare_schema.php';

// verific dacă există cod_locatie și admin_id în sesiune
if (!isset($_SESSION['cod_locatie'], $_SESSION['admin_id'])) {
    // lipsește ceva esențial în sesiune: scapă imediat
 
    header('Location: logout.php'); 
    exit;
}
if (!isset($_SESSION['no_session_validation']) || $_SESSION['no_session_validation'] != 1) {

// 1) iau ultima intrare pentru locația asta
$row = restaurantFetchUltimaConexiune($pdo, (int)$_SESSION['cod_locatie']);

// 2) dacă s-a găsit intrarea și adminul nu e același, deconectez
if ($row && $row['admin_id'] != $_SESSION['admin_id']) {
    // opțional: setezi un mesaj de eroare
    $_SESSION['error'] = 'Sesiunea ta a fost invalidată, te rog să te loghezi din nou.';
   
    header('Location: logout.php');
    exit;
}
}
ini_set('display_errors', 0); // Nu afișează erorile utilizatorului
ini_set('log_errors', 1); // Activează logarea erorilor
ini_set('error_log', 'error_log.log'); // Specifică calea către fișierul de log
error_reporting(E_ALL); // Raportează toate tipurile de erori
$data_bon = date("Y-m-d"); // Formatul datei: YYYY-MM-DD
$ora_bon = date("H:i:s");  // Formatul orei: HH:MM:SS

if (isset($_GET['prod_cod_bare'])) {
    $cod_bare = $_GET['prod_cod_bare'];
  
    $query = "SELECT cod_produs FROM produse_servicii WHERE cod_bare = :cod_bare LIMIT 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['cod_bare' => $cod_bare]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cod_p = $row['cod_produs'];
    } else {
        // Codul de bare nu a fost găsit, tratează eroarea după cum e necesar
        echo '<script>
        alert("Cod de bare negăsit");
        parent.window.location.reload(true);
      </script>';
        exit(); // oprește execuția scriptului
    }
} else {
    // $_GET['prod'] nu este setat, tratează această situație după cum e necesar
    $cod_p = $_GET['prod'];
}

$nr_bon = $_GET['bonul'];


             if (!isset($_SESSION['no_session_validation']) || $_SESSION['no_session_validation'] != 1) {

$bonRow = restaurantFetchUltimBonConectat($pdo, (int)$_SESSION['cod_locatie']);

if ($bonRow && $bonRow['nr_bon'] != $nr_bon) {
    // Bonul din sesiune nu mai e cel „activ” în baza de date → sesiune invalidă
    $_SESSION['error'] = 'Te rugăm să te conectezi din nou.';
  
    header('Location: logout.php');
    exit;
}
             }
$m_n = $_GET['cod_masa'];



// Marchează nota ca nelistată în tabela note
$update_sql = "UPDATE note SET listat_nota_plata = 0 WHERE nrbon = :nrbon";
$update_stmt = $pdo->prepare($update_sql);
$update_stmt->execute([':nrbon' => $nr_bon]);


//pentru masa bratara resetam listat_nota_plata la 1

$client_agecs = isset($_SESSION['client_id']) ? $_SESSION['client_id'] : null;
if ($client_agecs == 9) {
$sqltipmasa = "SELECT tip_masa FROM mese WHERE cod_masa = ?";
$stmttipmasa = $pdo->prepare($sqltipmasa);
$stmttipmasa->execute([$m_n]);
$tip_masa = $stmttipmasa->fetchColumn();
if ($tip_masa === "bratara") {
    $update_sql = "UPDATE note SET listat_nota_plata = 1 WHERE nrbon = :nrbon";
$update_stmt = $pdo->prepare($update_sql);
$update_stmt->execute([':nrbon' => $nr_bon]);
  }
}



// === ADĂUGARE: Obținerea numelui produsului, cota TVA și fel_mancare din tabela produse_servicii ===
$prod_servicii_sql = "SELECT nume, cota_tva, fel_mancare FROM produse_servicii WHERE cod_produs = :cod_p";
$prod_servicii_stmt = $pdo->prepare($prod_servicii_sql);
$prod_servicii_stmt->execute([':cod_p' => $cod_p]);
if ($row = $prod_servicii_stmt->fetch(PDO::FETCH_ASSOC)) {
    $nume_produs = $row['nume'];
    $cota_tva = $row['cota_tva'];
    $fel_mancare = (int)$row['fel_mancare']; // Preluăm felul de mâncare (prioritatea)
} else {
    $nume_produs = '';
    $fel_mancare = 0; // Fallback
}

$masaa_fin = $m_n;
if ($masaa_fin == 9999) {
    $pach = 1;
} else {
    $pach = 0;
}
$produs = $cod_p;

$psql2 = "SELECT 
            gestiuni.denumire_gestiune,
            $tabel_final_nomenclator.sgr,
            $tabel_final_nomenclator.sgr_pet,
            $tabel_final_nomenclator.sgr_alumin,
            $tabel_final_nomenclator.sgr_sticla,
            $tabel_final_nomenclator.cod_produs,
            $tabel_final_nomenclator.um,
            $tabel_final_nomenclator.nume,
            $tabel_final_nomenclator.pret_cu_tva,
            $tabel_final_nomenclator.cota_tva 
          FROM 
            $tabel_final_nomenclator 
          INNER JOIN 
            gestiuni ON $tabel_final_nomenclator.id_gestiune = gestiuni.id_gestiune 
          WHERE 
            $tabel_final_nomenclator.cod_produs = :produs";

$pstmt2 = $pdo->prepare($psql2);  
$pstmt2->execute([':produs' => $produs]); 

while ($row = $pstmt2->fetch(PDO::FETCH_ASSOC)) { 
    $den_prod = $row['nume'];
    $pret_vanzare = $row['pret_cu_tva'];
    $cota_tva = $row['cota_tva'];
    $sgr = $row['sgr'];
    $sgr_pet = $row['sgr_pet'];
    $sgr_alumin = $row['sgr_alumin'];
    $sgr_sticla = $row['sgr_sticla'];
    $um = $row['um'];
    $gestiune = $row['denumire_gestiune'];
}


// --- LOGICĂ MODIFICATĂ PENTRU CANTITATE ---
$cantitate = 1.0; // Valoare implicită (predefinită)
if (isset($_GET['cantitate_de_adaugat_prod'])) {
    $cantitate_trimisa = (float)$_GET['cantitate_de_adaugat_prod'];
    if ($cantitate_trimisa > 0) {
        // Dacă a trimis ceva valid > 0 (inclusiv 0.5), folosim acea valoare
        $cantitate = round($cantitate_trimisa, 2);
    }
}
// ------------------------------------------

$valoare_vanzare_cu_tva = round($pret_vanzare * $cantitate, 2);
$tva_col = round($valoare_vanzare_cu_tva * $cota_tva / (100 + $cota_tva), 2);
$valoare_vanzare = round($valoare_vanzare_cu_tva - $tva_col, 2);

// Dacă se dorește ca fiecare produs adăugat să fie inserat ca rând nou,
// nu mai verificăm dacă există deja o intrare în det_note.
if ($gestiune != "PRODUSE FINITE") {
    // === ADĂUGARE: Inserăm $fel_mancare în coloana prioritate ===
    $psql = "INSERT INTO $tabel_final_det_note(
                nr_bon,
                cod_p,
                nume_produs,
                cantitate,
                cota_tva,
                tva_col,
                pret_vanzare,
                valoare_vanzare,
                valoare_vanzare_cu_tva,
                pachet,
                prioritate,
                data,
                ora
             ) VALUES (
                :nr_bon,
                :produs,
                :nume_produs,
                :cantitate,
                :cota_tva,
                :tva_col,
                :pret_vanzare,
                :valoare_vanzare,
                :valoare_vanzare_cu_tva,
                :pach,
                :prioritate,
                :data_bon,
                :ora_bon
             )";
    
    try {
        $stmt_insert = $pdo->prepare($psql);
        $stmt_insert->execute([
            ':nr_bon' => $nr_bon,
            ':produs' => $produs,
            ':nume_produs' => $nume_produs,
            ':cantitate' => $cantitate,
            ':cota_tva' => $cota_tva,
            ':tva_col' => $tva_col,
            ':pret_vanzare' => $pret_vanzare,
            ':valoare_vanzare' => $valoare_vanzare,
            ':valoare_vanzare_cu_tva' => $valoare_vanzare_cu_tva,
            ':pach' => $pach,
            ':prioritate' => $fel_mancare, // Inserăm prioritatea
            ':data_bon' => $data_bon,
            ':ora_bon' => $ora_bon
        ]);
        $last_inserted_id = $pdo->lastInsertId();
        /* * Inserare produse de tip SGR (garanții) – se inserează doar dacă flag-ul este 1 
         * pentru fiecare dintre: sgr (-2), sgr_pet (-3), sgr_alumin (-4), sgr_sticla (-5)
         */
         
        // Pentru SGR (garantie SGR) – cod_produs = -2
        if ($sgr == 1) {
            $warrantyCode = -2;
            $warrantyQuery = "SELECT * FROM $tabel_final_nomenclator WHERE cod_produs = :warrantyCode";
            $warrantyStmt = $pdo->prepare($warrantyQuery);
            $warrantyStmt->execute([':warrantyCode' => $warrantyCode]);
            if ($warrantyData = $warrantyStmt->fetch(PDO::FETCH_ASSOC)) {
                 $warranty_cod_p = $warrantyData['cod_produs'];
                 $warranty_nume_produs = $warrantyData['nume'];
                 $warranty_cota_tva = $warrantyData['cota_tva'];
                 $warranty_cantitate = round($cantitate, 2);
                 $warranty_tva_col = $warrantyData['cota_tva'];
                 $warranty_pret_vanzare = $warrantyData['pret_cu_tva'];
                 $warranty_valoare_vanzare = round($warranty_pret_vanzare * $warranty_cantitate, 2);
                 $warranty_valoare_vanzare_cu_tva = round($warranty_valoare_vanzare * (1 + ($warranty_tva_col / 100)), 2);
                 $warranty_pachet = $pach;
                 $warranty_data = $data_bon;
                 $warranty_ora = $ora_bon;
                 $warrantyInsertSql = "INSERT INTO $tabel_final_det_note(
                                             nr_bon, 
                                             cod_p, 
                                             nume_produs,
                                             cantitate, 
                                             cota_tva,
                                             tva_col, 
                                             pret_vanzare, 
                                             valoare_vanzare, 
                                             valoare_vanzare_cu_tva, 
                                             pachet, 
                                             data, 
                                             ora
                                            ) VALUES (
                                             :nr_bon, 
                                             :warranty_cod_p,
                                             :warranty_nume_produs,
                                             :warranty_cantitate, 
                                             :warranty_cota_tva,
                                             :warranty_tva_col, 
                                             :warranty_pret_vanzare, 
                                             :warranty_valoare_vanzare, 
                                             :warranty_valoare_vanzare_cu_tva, 
                                             :warranty_pachet, 
                                             :warranty_data, 
                                             :warranty_ora
                                            )";
                 $wStmt = $pdo->prepare($warrantyInsertSql);
                 $wStmt->execute([
                     ':nr_bon' => $nr_bon,
                     ':warranty_cod_p' => $warranty_cod_p,
                     ':warranty_nume_produs' => $warranty_nume_produs,
                     ':warranty_cantitate' => $warranty_cantitate,
                     ':warranty_cota_tva' => $warranty_cota_tva,
                     ':warranty_tva_col' => $warranty_tva_col,
                     ':warranty_pret_vanzare' => $warranty_pret_vanzare,
                     ':warranty_valoare_vanzare' => $warranty_valoare_vanzare,
                     ':warranty_valoare_vanzare_cu_tva' => $warranty_valoare_vanzare_cu_tva,
                     ':warranty_pachet' => $warranty_pachet,
                     ':warranty_data' => $warranty_data,
                     ':warranty_ora' => $warranty_ora
                 ]);
            }
        }

        // Pentru SGR PET – cod_produs = -3
        if ($sgr_pet == 1) {
            $warrantyCode = -3;
            $warrantyQuery = "SELECT * FROM $tabel_final_nomenclator WHERE cod_produs = :warrantyCode";
            $warrantyStmt = $pdo->prepare($warrantyQuery);
            $warrantyStmt->execute([':warrantyCode' => $warrantyCode]);
            if ($warrantyData = $warrantyStmt->fetch(PDO::FETCH_ASSOC)) {
                 $warranty_cod_p = $warrantyData['cod_produs'];
                 $warranty_nume_produs = $warrantyData['nume'];
                 $warranty_cota_tva = $warrantyData['cota_tva'];
                 $warranty_cantitate = round($cantitate, 2);
                 $warranty_tva_col = $warrantyData['cota_tva'];
                 $warranty_pret_vanzare = $warrantyData['pret_cu_tva'];
                 $warranty_valoare_vanzare = round($warranty_pret_vanzare * $warranty_cantitate, 2);
                 $warranty_valoare_vanzare_cu_tva = round($warranty_valoare_vanzare * (1 + ($warranty_tva_col / 100)), 2);
                 $warranty_pachet = $pach;
                 $warranty_data = $data_bon;
                 $warranty_ora = $ora_bon;
                 $warrantyInsertSql = "INSERT INTO $tabel_final_det_note(
                                             nr_bon, 
                                             cod_p, 
                                             nume_produs,
                                             cota_tva,
                                             cantitate, 
                                             tva_col, 
                                             pret_vanzare, 
                                             valoare_vanzare, 
                                             valoare_vanzare_cu_tva, 
                                             pachet, 
                                             data, 
                                             ora
                                            ) VALUES (
                                             :nr_bon, 
                                             :warranty_cod_p,
                                             :warranty_nume_produs,
                                             :warranty_cota_tva,
                                             :warranty_cantitate, 
                                             :warranty_tva_col, 
                                             :warranty_pret_vanzare, 
                                             :warranty_valoare_vanzare, 
                                             :warranty_valoare_vanzare_cu_tva, 
                                             :warranty_pachet, 
                                             :warranty_data, 
                                             :warranty_ora
                                            )";
                 $wStmt = $pdo->prepare($warrantyInsertSql);
                 $wStmt->execute([
                     ':nr_bon' => $nr_bon,
                     ':warranty_cod_p' => $warranty_cod_p,
                     ':warranty_nume_produs' => $warranty_nume_produs,
                     ':warranty_cota_tva' => $warranty_cota_tva,
                     ':warranty_cantitate' => $warranty_cantitate,
                     ':warranty_tva_col' => $warranty_tva_col,
                     ':warranty_pret_vanzare' => $warranty_pret_vanzare,
                     ':warranty_valoare_vanzare' => $warranty_valoare_vanzare,
                     ':warranty_valoare_vanzare_cu_tva' => $warranty_valoare_vanzare_cu_tva,
                     ':warranty_pachet' => $warranty_pachet,
                     ':warranty_data' => $warranty_data,
                     ':warranty_ora' => $warranty_ora
                 ]);
            }
        }

        // Pentru SGR ALUMINIU – cod_produs = -4
        if ($sgr_alumin == 1) {
            $warrantyCode = -4;
            $warrantyQuery = "SELECT * FROM $tabel_final_nomenclator WHERE cod_produs = :warrantyCode";
            $warrantyStmt = $pdo->prepare($warrantyQuery);
            $warrantyStmt->execute([':warrantyCode' => $warrantyCode]);
            if ($warrantyData = $warrantyStmt->fetch(PDO::FETCH_ASSOC)) {
                 $warranty_cod_p = $warrantyData['cod_produs'];
                 $warranty_nume_produs = $warrantyData['nume'];
                 $warranty_cota_tva = $warrantyData['cota_tva'];
                 $warranty_cantitate = round($cantitate, 2);
                 $warranty_tva_col = $warrantyData['cota_tva'];
                 $warranty_pret_vanzare = $warrantyData['pret_cu_tva'];
                 $warranty_valoare_vanzare = round($warranty_pret_vanzare * $warranty_cantitate, 2);
                 $warranty_valoare_vanzare_cu_tva = round($warranty_valoare_vanzare * (1 + ($warranty_tva_col / 100)), 2);
                 $warranty_pachet = $pach;
                 $warranty_data = $data_bon;
                 $warranty_ora = $ora_bon;
                 $warrantyInsertSql = "INSERT INTO $tabel_final_det_note(
                                             nr_bon, 
                                             cod_p, 
                                             nume_produs,
                                             cota_tva,
                                             cantitate, 
                                             tva_col, 
                                             pret_vanzare, 
                                             valoare_vanzare, 
                                             valoare_vanzare_cu_tva, 
                                             pachet, 
                                             data, 
                                             ora
                                            ) VALUES (
                                             :nr_bon, 
                                             :warranty_cod_p,
                                             :warranty_nume_produs,
                                             :warranty_cota_tva,
                                             :warranty_cantitate, 
                                             :warranty_tva_col, 
                                             :warranty_pret_vanzare, 
                                             :warranty_valoare_vanzare, 
                                             :warranty_valoare_vanzare_cu_tva, 
                                             :warranty_pachet, 
                                             :warranty_data, 
                                             :warranty_ora
                                            )";
                 $wStmt = $pdo->prepare($warrantyInsertSql);
                 $wStmt->execute([
                     ':nr_bon' => $nr_bon,
                     ':warranty_cod_p' => $warranty_cod_p,
                     ':warranty_nume_produs' => $warranty_nume_produs,
                     ':warranty_cota_tva' => $warranty_cota_tva,
                     ':warranty_cantitate' => $warranty_cantitate,
                     ':warranty_tva_col' => $warranty_tva_col,
                     ':warranty_pret_vanzare' => $warranty_pret_vanzare,
                     ':warranty_valoare_vanzare' => $warranty_valoare_vanzare,
                     ':warranty_valoare_vanzare_cu_tva' => $warranty_valoare_vanzare_cu_tva,
                     ':warranty_pachet' => $warranty_pachet,
                     ':warranty_data' => $warranty_data,
                     ':warranty_ora' => $warranty_ora
                 ]);
            }
        }

        // Pentru SGR STICLA – cod_produs = -5
        if ($sgr_sticla == 1) {
            $warrantyCode = -5;
            $warrantyQuery = "SELECT * FROM $tabel_final_nomenclator WHERE cod_produs = :warrantyCode";
            $warrantyStmt = $pdo->prepare($warrantyQuery);
            $warrantyStmt->execute([':warrantyCode' => $warrantyCode]);
            if ($warrantyData = $warrantyStmt->fetch(PDO::FETCH_ASSOC)) {
                 $warranty_cod_p = $warrantyData['cod_produs'];
                 $warranty_nume_produs = $warrantyData['nume'];
                 $warranty_cota_tva = $warrantyData['cota_tva'];
                 $warranty_cantitate = round($cantitate, 2);
                 $warranty_tva_col = $warrantyData['cota_tva'];
                 $warranty_pret_vanzare = $warrantyData['pret_cu_tva'];
                 $warranty_valoare_vanzare = round($warranty_pret_vanzare * $warranty_cantitate, 2);
                 $warranty_valoare_vanzare_cu_tva = round($warranty_valoare_vanzare * (1 + ($warranty_tva_col / 100)), 2);
                 $warranty_pachet = $pach;
                 $warranty_data = $data_bon;
                 $warranty_ora = $ora_bon;
                 $warrantyInsertSql = "INSERT INTO $tabel_final_det_note(
                                             nr_bon, 
                                             cod_p, 
                                             nume_produs,
                                             cota_tva,
                                             cantitate, 
                                             tva_col, 
                                             pret_vanzare, 
                                             valoare_vanzare, 
                                             valoare_vanzare_cu_tva, 
                                             pachet, 
                                             data, 
                                             ora
                                            ) VALUES (
                                             :nr_bon, 
                                             :warranty_cod_p,
                                             :warranty_nume_produs,
                                             :warranty_cota_tva,
                                             :warranty_cantitate, 
                                             :warranty_tva_col, 
                                             :warranty_pret_vanzare, 
                                             :warranty_valoare_vanzare, 
                                             :warranty_valoare_vanzare_cu_tva, 
                                             :warranty_pachet, 
                                             :warranty_data, 
                                             :warranty_ora
                                            )";
                 $wStmt = $pdo->prepare($warrantyInsertSql);
                 $wStmt->execute([
                     ':nr_bon' => $nr_bon,
                     ':warranty_cod_p' => $warranty_cod_p,
                     ':warranty_nume_produs' => $warranty_nume_produs,
                     ':warranty_cota_tva' => $warranty_cota_tva,
                     ':warranty_cantitate' => $warranty_cantitate,
                     ':warranty_tva_col' => $warranty_tva_col,
                     ':warranty_pret_vanzare' => $warranty_pret_vanzare,
                     ':warranty_valoare_vanzare' => $warranty_valoare_vanzare,
                     ':warranty_valoare_vanzare_cu_tva' => $warranty_valoare_vanzare_cu_tva,
                     ':warranty_pachet' => $warranty_pachet,
                     ':warranty_data' => $warranty_data,
                     ':warranty_ora' => $warranty_ora
                 ]);
            }
        }

    } catch(PDOException $e) {
        echo $psql . "<br>" . $e->getMessage();
    }
} elseif ($gestiune == "PRODUSE FINITE") {
    // === ADĂUGARE: Inserăm $fel_mancare în coloana prioritate ===
    $psql = "INSERT INTO $tabel_final_det_note(
                nr_bon,
                cod_p,
                nume_produs,
                cantitate,
                cota_tva,
                tva_col,
                pret_vanzare,
                valoare_vanzare,
                valoare_vanzare_cu_tva,
                pachet,
                prioritate,
                data,
                ora
             ) VALUES (
                :nr_bon,
                :produs,
                :nume_produs,
                :cantitate,
                :cota_tva,
                :tva_col,
                :pret_vanzare,
                :valoare_vanzare,
                :valoare_vanzare_cu_tva,
                :pach,
                :prioritate,
                :data_bon,
                :ora_bon
             )";
    try {
        $stmt_insert = $pdo->prepare($psql);
        $stmt_insert->execute([
            ':nr_bon' => $nr_bon,
            ':produs' => $produs,
            ':nume_produs' => $nume_produs,
            ':cantitate' => $cantitate,
            ':cota_tva' => $cota_tva,
            ':tva_col' => $tva_col,
            ':pret_vanzare' => $pret_vanzare,
            ':valoare_vanzare' => $valoare_vanzare,
            ':valoare_vanzare_cu_tva' => $valoare_vanzare_cu_tva,
            ':pach' => $pach,
            ':prioritate' => $fel_mancare, // Inserăm prioritatea
            ':data_bon' => $data_bon,
            ':ora_bon' => $ora_bon
        ]);
        $last_inserted_id = $pdo->lastInsertId();
    } catch(PDOException $e) {
        echo $psql . "<br>" . $e->getMessage();
    }
}
agecs_snapshot_det_note_departamente(
    $pdo,
    (int)$nr_bon,
    $tabel_final_det_note,
    $tabel_final_nomenclator
);
?>

<script>
    var timerr = null;

    function goAway3() {
        clearTimeout(timerr);
        timerr = setTimeout(function() {
            var bon = "<?php echo $nr_bon; ?>";
            $("#one").load("afis_prod.php?" + $.param({
                bonul: bon
            }));
        }, 50);
    }

    goAway3();  // start the first timer off
</script>

<style>
    #buttons_categ_modal {
        position: absolute !important;
        right: 0 !important;
    }
</style>

<?php
// === ADĂUGAT PENTRU SOLUȚIE ROBUSTĂ OBSERVATII ===
// Trimitem ID-ul inserat într-un input hidden standard
// pe care JS îl va citi fără REGEX.
if (isset($last_inserted_id)) {
    echo '<input type="hidden" id="last_inserted_id_server" value="' . $last_inserted_id . '">';
}
?>
