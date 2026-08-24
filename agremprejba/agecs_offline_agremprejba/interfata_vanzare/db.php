<?php
// db.php
require_once __DIR__ . '/offline_api_path.php';
require_once __DIR__ . '/offline_license_lib.php';
offline_license_enforce();

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error_log.log');
error_reporting(E_ALL);

$DB_PATH = offline_db_runtime_path();
$DB_DIR = dirname($DB_PATH);

if (!is_dir($DB_DIR)) {
    mkdir($DB_DIR, 0777, true);
}

try {
    $pdo = new PDO('sqlite:' . $DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // Pentru SQLite e util să fie ON
    $pdo->exec('PRAGMA foreign_keys = ON;');
    require_once __DIR__ . '/offline_sequence_state.php';
    offline_sequence_ensure_schema($pdo);
} catch (PDOException $e) {
    die("Nu se poate deschide baza locală: " . $e->getMessage());
}

/**
 * Table name aliases — păstrezi ce ai în cod
 */
$tabel_final_note                    = 'note';
$tabel_final_det_note                = 'det_note';
$tabel_final_nomenclator             = 'produse_servicii';
$tabel_final_categorii               = 'categorii';
$tabel_final_date_firma              = 'date_firma';
$tabel_final_admins                  = 'admins_12';

// noi
$tabel_final_reguli_vanzare          = 'reguli_vanzare';
$tabel_final_discounturi_acordate    = 'discounturi_acordate';
$tabel_final_gestiuni = 'gestiuni';
$tabel_final_miscari              = 'miscari';

$tabel_final_consumuri           = 'consumuri';
$tabel_final_bonuri_consum       = 'bonuri_consum';
$tabel_final_abonati             = 'abonati';
$tabel_final_achizitii           = 'achizitii';
$tabel_final_bonuri              = 'bonuri';
$tabel_final_chitante            = 'chitante';
$tabel_final_comenzi             = 'comenzi';
$tabel_final_comenzi_detalii     = 'det_comenzi';
$tabel_final_cosuri              = 'cosuri';
$tabel_final_customers           = 'customers';
$tabel_final_det_bonuri          = 'det_bonuri';
$tabel_final_det_monetar         = 'det_monetar';
$tabel_final_det_procese_comp    = 'det_procese_comp';
$tabel_final_det_stornari        = 'det_stornari';
$tabel_final_det_stornari_fact   = 'det_stornari_fact';
$tabel_final_det_stornari_rest   = 'det_stornari_rest';
$tabel_final_de_listat_bar       = 'de_listat_bar';
$tabel_final_de_listat_buc       = 'de_listat_buc';
$tabel_final_dispozitii          = 'dispozitii';
$tabel_final_extra_images        = 'extra_images';
$tabel_final_facturi             = 'facturi';
$tabel_final_inchideri_m         = 'inchideri_m';
$tabel_final_inchideri_r         = 'inchideri_r_12';
$tabel_final_loc_mese            = 'loc_mese_12';
$tabel_final_mese                = 'mese';
$tabel_final_monetar             = 'monetar';
$tabel_final_nir                 = 'nir';
$tabel_final_nomenclator         = 'produse_servicii';
$tabel_final_procese_comp        = 'procese_comp';
$tabel_final_recenzii            = 'recenzii';
$tabel_final_retete              = 'retete';
$tabel_final_stoc                = 'stoc_produse';
$tabel_final_stornari            = 'stornari';
$tabel_final_stornari_fact       = 'stornari_fact';
$tabel_final_stornari_rest       = 'stornari_rest';
$tabel_final_terti               = 'clienti';
$tabel_final_vanzari             = 'vanzari';

$tabel_final_reguli_vanzare      = 'reguli_vanzare';
