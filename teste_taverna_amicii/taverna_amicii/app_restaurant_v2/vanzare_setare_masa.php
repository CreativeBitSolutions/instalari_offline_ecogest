<?php //vanzare_setare_masa.php
include 'session.php'; // Asigură-te că acest fișier definește $pdo, $tabel_final_note, $tabel_final_mese și $_SESSION['cod_locatie']
ini_set('display_errors', 0); // Nu afișează erorile utilizatorului
ini_set('log_errors', 1); // Activează logarea erorilor
ini_set('error_log', 'error_log.log'); // Specifică calea către fișierul de log
error_reporting(E_ALL); // Raportează toate tipurile de erori
// Verificăm dacă operatorul este autentificat
if (!isset($_SESSION['admin_id'])) {
    header("Location: agecs_login.php");
    exit;
}

// Verificăm existența parametrului "masa" și a locației în sesiune
if (isset($_GET['masa']) && isset($_SESSION['cod_locatie'])) {
    
    $masa = $_GET['masa'];
   
    $operatorId = $_SESSION['admin_id'];  // Preluăm operatorul din sesiune
    $cod_locatie = $_SESSION['cod_locatie'];

    // Dacă nu a fost selectat un operator ocupant, se inserează o nouă notă
    if (!isset($_SESSION['occupied_operator']) || $_SESSION['occupied_operator'] !== true) {
       // 1) Preluăm ultimul nrbon pentru locație și calculăm următorul
$sqlMax = "SELECT COALESCE(MAX(nrbon), 0) AS lastBon 
           FROM $tabel_final_note";
$stmtMax = $pdo->prepare($sqlMax);
$stmtMax->execute();

$row = $stmtMax->fetch(PDO::FETCH_ASSOC);
$nextBon = $row['lastBon'] + 1;

// 2) Inserăm nota cu nrbon = lastBon + 1
$sqlIns = "INSERT INTO $tabel_final_note (nrbon, operator, locatie, cod_masa) 
       VALUES (:nrbon, :operator, :locatie, :cod_masa)";
$stmtIns = $pdo->prepare($sqlIns);
$stmtIns->execute([
'nrbon'    => $nextBon,
'operator' => $operatorId,
'locatie'  => $cod_locatie,
'cod_masa' => $masa
]);

$_SESSION['nr_bon'] = $nextBon;
    } else {
      
            // Dacă cumva nu este setată, extragem ultima notă pentru locația dată
            $cccom_sql = "SELECT nrbon FROM $tabel_final_note WHERE locatie = :locatie AND operator = :operator and cod_masa=:cod_masa and status='S'";
            $cccom_stmt = $pdo->prepare($cccom_sql);
            $cccom_stmt->execute([
                'locatie' => $cod_locatie,
                'operator' => $operatorId,
                'cod_masa'  => $masa


            ]);
            
            $row = $cccom_stmt->fetch(PDO::FETCH_ASSOC);
            $nr_bon = $row['nrbon'];
            $_SESSION['nr_bon'] = $nr_bon;
        
        // În această situație se presupune că variabila $_SESSION['admin_id'] a fost deja actualizată la logare
        // (decât se dorește doar actualizarea variabilelor din sesiune, fără a face un INSERT nou)
    }
            if (!isset($_SESSION['no_session_validation']) || $_SESSION['no_session_validation'] != 1) {

   $dateTime    = new DateTime('now', new DateTimeZone('Europe/Bucharest'));
$updateTime  = $dateTime->format('Y-m-d H:i:s');

// UPDATE în ultim_bon_conectat
restaurantTouchUltimBonConectat($pdo, (int)$_SESSION['cod_locatie'], (int)$_SESSION['nr_bon'], $updateTime);
            }
    // Actualizăm starea mesei - se marchează ca ocupată
    $updm_sq = "UPDATE $tabel_final_mese 
                SET stare = 1 
                WHERE cod_masa = :masa";
    $updmstmt = $pdo->prepare($updm_sq);
    $updmstmt->execute(['masa' => $masa]);

    // Actualizăm variabilele de sesiune pentru masa curentă și alte flag-uri
    $_SESSION['masa_curenta'] = $masa;
    $_SESSION['trimis_comanda'] = 0;

    header("Location: vanzare_restaurant.php");
    exit;
} else {
    header("Location: vanzare_restaurant.php");
    exit;
}
?>
