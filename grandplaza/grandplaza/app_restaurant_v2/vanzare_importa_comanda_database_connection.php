<?php  		date_default_timezone_set("Europe/Bucharest");

if (session_status() != PHP_SESSION_ACTIVE) {
    session_start();
}

// Datele de conexiune pentru baza de date centrală
$driver = 'mysql';
$host = 'localhost';
$central_database = 'u27429868_cl_agecs_qr'; // Numele bazei de date centrale
$dsn_central = "$driver:host=$host;dbname=$central_database;charset=utf8mb4";
$central_username = 'u27429868_cl_agecs_qr';
$central_password = 'D#41L*pHc';

// Creează o instanță PDO pentru baza de date centrală
try {
    $central_pdo_qr = new PDO($dsn_central, $central_username, $central_password);
    $central_pdo_qr->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "<h1>Conexiunea la baza de date centrală a eșuat: " . htmlspecialchars($e->getMessage()) . "</h1>";
    exit;
}

// Verifică dacă clientul este autentificat
if (isset($_SESSION['client_id'])) {
    $client_id = $_SESSION['client_id'];

    // Pregătește și execută interogarea pentru a obține detaliile clientului
    try {
        $stmt = $central_pdo_qr->prepare("SELECT nume_bd, utilizator_bd_enc, parola_bd_enc FROM clienti WHERE id = :client_id");
        $stmt->bindParam(':client_id', $client_id, PDO::PARAM_INT);
        $stmt->execute();
        $client = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$client) {
            echo "<h1>Clientul nu a fost găsit.</h1>".$client_id;
            exit;
        }

        // Extrage detaliile de conexiune ale clientului
        $client_db = trim($client['nume_bd']);    // Numele bazei de date
        $client_user= trim($client['utilizator_bd_enc']); // Utilizatorul bazei de date
        $client_pass=trim($client['parola_bd_enc']); // Parola bazei de date

        // Configurează DSN-ul pentru baza de date a clientului
        $dsn_client = "$driver:host=$host;dbname=$client_db;charset=utf8mb4";

        // Creează o instanță PDO pentru baza de date a clientului și setează $pdo_qr
        try {
            $pdo_qr = new PDO($dsn_client, $client_user, $client_pass);
            $pdo_qr->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            echo "<h1>Eroare la conectarea la baza de date a clientului: " . htmlspecialchars($e->getMessage()) . "</h1>";
            exit;
        }

    } catch (PDOException $e) {
        echo "<h1>Eroare la interogarea bazei de date centrale: " . htmlspecialchars($e->getMessage()) . "</h1>";
        exit;
    }
}
?>

<?php if(session_status()!=PHP_SESSION_ACTIVE) {session_start();}
$live_id=12;
	$tabel_final_consumuri='consumuri'.'_'.$live_id;
	$tabel_final_bonuri_consum='bonuri_consum'.'_'.$live_id;
	$tabel_final_abonati='abonati'.'_'.$live_id;
$tabel_final_achizitii='achizitii'.'_'.$live_id;
$tabel_final_admins='admins'.'_'.$live_id;
$tabel_final_bonuri='bonuri'.'_'.$live_id;
$tabel_final_categorii='categorii';
$tabel_final_chitante='chitante'.'_'.$live_id;
$tabel_final_comenzi='comenzi'.'_'.$live_id;
$tabel_final_comenzi_detalii='comenzi_detalii'.'_'.$live_id;
$tabel_final_cosuri='cosuri'.'_'.$live_id;
$tabel_final_customers='customers'.'_'.$live_id;
$tabel_final_date_firma='date_firma';
$tabel_final_det_bonuri='det_bonuri'.'_'.$live_id;
$tabel_final_det_monetar='det_monetar'.'_'.$live_id;
$tabel_final_det_note='det_note';
$tabel_final_det_procese_comp='det_procese_comp'.'_'.$live_id;
$tabel_final_det_stornari='det_stornari'.'_'.$live_id;
$tabel_final_det_stornari_fact='det_stornari_fact'.'_'.$live_id;
$tabel_final_det_stornari_rest='det_stornari_rest'.'_'.$live_id;
$tabel_final_de_listat_bar='de_listat_bar'.'_'.$live_id;
$tabel_final_de_listat_buc='de_listat_buc'.'_'.$live_id;
$tabel_final_dispozitii='dispozitii'.'_'.$live_id;
$tabel_final_extra_images='extra_images'.'_'.$live_id;
$tabel_final_facturi='facturi'.'_'.$live_id;
$tabel_final_inchideri_m='inchideri_m'.'_'.$live_id;
$tabel_final_inchideri_r='inchideri_r'.'_'.$live_id;
$tabel_final_loc_mese='loc_mese'.'_'.$live_id;
$tabel_final_mese='mese';
$tabel_final_miscari='miscari';
$tabel_final_monetar='monetar'.'_'.$live_id;
$tabel_final_nir='nir'.'_'.$live_id;
$tabel_final_nomenclator='produse_servicii';
$tabel_final_note='note';
$tabel_final_procese_comp='procese_comp'.'_'.$live_id;
$tabel_final_recenzii='recenzii'.'_'.$live_id;
$tabel_final_retete='retete';
$tabel_final_stoc='stoc'.'_'.$live_id;
$tabel_final_stornari='stornari'.'_'.$live_id;
$tabel_final_stornari_fact='stornari_fact'.'_'.$live_id;
$tabel_final_stornari_rest='stornari_rest'.'_'.$live_id;
$tabel_final_terti='clienti';
$tabel_final_vanzari='vanzari'.'_'.$live_id;
?>
