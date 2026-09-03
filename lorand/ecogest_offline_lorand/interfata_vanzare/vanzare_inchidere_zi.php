<?php

include 'session.php';
require_once __DIR__ . '/lorand_z_printer.php';
require_once __DIR__ . '/offline_printer_flow_helper.php';

$payments = [
    'numerar' => (float)($_POST['numerar'] ?? 0),
    'card' => (float)($_POST['card'] ?? 0),
    'credit' => (float)($_POST['credit'] ?? 0),
    'tichete_masa' => (float)($_POST['tichete_masa'] ?? 0),
    'tichete_valorice' => (float)($_POST['tichete_valorice'] ?? 0),
    'plata_moderna' => (float)($_POST['plata_moderna'] ?? 0),
    'avans_in_numerar' => (float)($_POST['avans_in_numerar'] ?? 0),
    'alte_metode' => (float)($_POST['alte_metode'] ?? 0),
];
$reportNumber = (int)($_POST['nr_raport_z'] ?? 0);
$closureCodes = isset($_POST['coduri_inchidere']) && is_array($_POST['coduri_inchidere'])
    ? $_POST['coduri_inchidere']
    : [];
$location = (int)($_SESSION['cod_locatie'] ?? 0);
$now = new DateTime('now', new DateTimeZone('Europe/Bucharest'));

try {
    if ($location <= 0) {
        throw new RuntimeException('Locatia curenta este invalida.');
    }
    offline_close_z_report($pdo, $location, $reportNumber, $closureCodes, $payments, $now->format('Y-m-d H:i:s'));
    $_SESSION['offline_z_closed'] = $reportNumber;
    try {
        lorand_z_enqueue_documents($pdo, (int)($_SESSION['client_id'] ?? 0), $location, $reportNumber);
    } catch (Throwable $printerError) {
        error_log('Listarea închiderii Lorand: ' . $printerError->getMessage());
        $_SESSION['offline_printer_error'] = $printerError->getMessage();
    }
    header('Location: ' . lorand_printer_wait_url('logout.php', 'inchidere_z'));
    exit;
} catch (Throwable $e) {
    $_SESSION['offline_z_error'] = $e->getMessage();
    header('Location: vanzare_magazin.php?raport_z_eroare=1');
    exit;
}
