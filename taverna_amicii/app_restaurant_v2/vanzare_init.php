<?php //vanzare_init.php === START: Salvare CIF/CUI client din modal personalizat (ADĂUGAT) ===
require_once __DIR__ . '/det_note_import_schema.php';
require_once __DIR__ . '/det_note_departament_listare_schema.php';
restaurant_v2_ensure_det_note_site_import_column(
    $pdo,
    isset($tabel_final_det_note) ? $tabel_final_det_note : 'det_note'
);
agecs_ensure_det_note_departament_listare(
    $pdo,
    isset($tabel_final_det_note) ? $tabel_final_det_note : 'det_note'
);

if (isset($_POST['save_cif_client'])) {
    $cif_from_modal = isset($_POST['cif_client_modal']) ? $_POST['cif_client_modal'] : '';
    if (!is_string($cif_from_modal)) { $cif_from_modal = ''; }
    // Normalizez: uppercase și fără spații
    $cif_from_modal = strtoupper(trim(preg_replace('/\s+/', '', $cif_from_modal)));
    $_SESSION['cif_client'] = $cif_from_modal;
    // opțional: după salvare, reîncarc pagina ca să se vadă valoarea
    echo "<script>location.href='vanzare_restaurant.php'</script>";
    exit;
}
// === END: Salvare CIF/CUI client din modal personalizat (ADĂUGAT) ===
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
if (isset($_SESSION['nextbon'])) {
    unset($_SESSION['nextbon']);
}
		date_default_timezone_set("Europe/Bucharest");
$ora_bon = date("H:i:s", strtotime('+0 hours'));
 $data_bon = date("Y-m-d", strtotime('+0 hours'));
 $adm_id=$_SESSION['admin_id'];
 $cod_locatie=$_SESSION['cod_locatie'];
 $dsql = "SELECT * FROM $tabel_final_admins where admin_id='$adm_id'";
$dstmt = $pdo->prepare($dsql);
$dstmt->execute(); 
while ($row = $dstmt->fetch(PDO::FETCH_ASSOC)){
$admin_firstname=$row['admin_firstname'];
$admin_lastname=$row['admin_lastname'];
$_SESSION['admin_firstname']=$row['admin_firstname'];
$_SESSION['admin_lastname']=$row['admin_lastname'];
}
// inițializează variabilele pentru client și locație
$client_id   = isset($_SESSION['client_id'])   ? intval($_SESSION['client_id'])   : 0;
$cod_locatie = $_SESSION['cod_locatie'];
// 1. Citește din tabela setari_platforma
$sql  = "SELECT cu_imprimanta, autologin_restaurant FROM setari_platforma LIMIT 1";
$stmt = $pdo->query($sql);
$setare = $stmt->fetch(PDO::FETCH_ASSOC);

// 2. Ținem autologin_restaurant persistent: sesiune + cookie
if ($setare) {
    $cookieName = 'autologin_restaurant';

    // Dacă există cookie valid, acesta devine sursa principală
    if (isset($_COOKIE[$cookieName])) {
        $cookieVal = (int)$_COOKIE[$cookieName];
        $_SESSION['autologin_restaurant'] = ($cookieVal === 1) ? 1 : 0;
    } else {
        // Prima încărcare: luăm din DB
        $_SESSION['autologin_restaurant'] = (int)$setare['autologin_restaurant'];

        // Salvăm și în cookie pe termen lung (5 ani)
        setcookie(
            $cookieName,
            (string)$_SESSION['autologin_restaurant'],
            [
                'expires'  => time() + (86400 * 365 * 5),
                'path'     => '/',
                'secure'   => !empty($_SERVER['HTTPS']),
                'httponly' => false,
                'samesite' => 'Lax'
            ]
        );
    }

    // Dacă vrei să forțezi sincronizarea cookie-ului la fiecare request:
    setcookie(
        $cookieName,
        (string)$_SESSION['autologin_restaurant'],
        [
            'expires'  => time() + (86400 * 365 * 5),
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']),
            'httponly' => false,
            'samesite' => 'Lax'
        ]
    );
}

// 3. Dacă setarea cu_imprimanta e 0, șterge fișierul JSON din folderul specific
if ($setare && (int)$setare['cu_imprimanta'] === 0) {
    $jsonFile = RESTAURANT_OFFLINE_API_DIR . "/{$client_id}/{$cod_locatie}/de_listat_la_imprimanta.json";
    if (file_exists($jsonFile)) {
        unlink($jsonFile);
    }
}

/* ──────────────────────────────────────────────
   1)  PHP: salvează camera în sesiune
   ────────────────────────────────────────────── */
if (isset($_POST['salveaza_camera'])) {
    // forțează numeric și elimină camera 13
    $cam = intval($_POST['camera_select']);
        $_SESSION['camera_nota'] = $cam;
    // opțional: redirect ca să dispară POST-ul
                echo "<script>location.href='vanzare_restaurant.php'</script>";
}

$afiseaza_modal = false;
$nr_bon = null;
if (!isset($_SESSION['nr_bon'])) {
    $afiseaza_modal = true;
} else {
    $nr_bon = $_SESSION['nr_bon'];
            if (!isset($_SESSION['no_session_validation']) || $_SESSION['no_session_validation'] != 1) {
    $bonRow = restaurantFetchUltimBonConectat($pdo, (int)$_SESSION['cod_locatie']);
if ($bonRow && $bonRow['nr_bon'] != $_SESSION['nr_bon']) {
    // Bonul din sesiune nu mai e cel „activ” în baza de date → sesiune invalidă
    $_SESSION['error'] = 'Te rugăm să te conectezi din nou.';
    header('Location: logout.php');
    exit;
}
            }
}
?>
