<?php
/**
 * sterge_prod.php
 * VERSIUNE ACTUALIZATĂ
 * - Adaugă ștergerea discounturilor asociate într-o tranzacție.
 */

include('session.php');
require_once __DIR__ . '/offline_api_path.php';
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.log');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

try {
    $id_vanz = filter_input(INPUT_GET, 'id_vanz', FILTER_VALIDATE_INT);
    $nr_bon = filter_input(INPUT_GET, 'nr_bon', FILTER_VALIDATE_INT);

    if (!$id_vanz || !$nr_bon) {
        throw new Exception("ID vanzare invalid sau lipsa.");
    }

    // Începem o tranzacție pentru a ne asigura că ambele operațiuni reușesc sau eșuează împreună
    $pdo->beginTransaction();

    // PASUL 1 (NOU): Ștergem mai întâi înregistrările de discount asociate
    $sql_delete_discount = "DELETE FROM discounturi_acordate WHERE id_vanz = :id_vanz";
    $stmt_delete_discount = $pdo->prepare($sql_delete_discount);
    $stmt_delete_discount->execute([':id_vanz' => $id_vanz]);

    // PASUL 2: Ștergem linia de produs de pe bon
    $sql_delete_prod = "DELETE FROM {$tabel_final_det_note} WHERE id_vanz = :id_vanz AND nr_bon = :nr_bon";
    $stmt_delete_prod = $pdo->prepare($sql_delete_prod);
    $stmt_delete_prod->execute([
        ':id_vanz' => $id_vanz,
        ':nr_bon' => $nr_bon,
    ]);

    if ($stmt_delete_prod->rowCount() !== 1) {
        throw new RuntimeException('Pozitia nu mai exista pe bonul curent.');
    }
    
    // Dacă am ajuns aici fără erori, confirmăm modificările
    $pdo->commit();

    // Logica specială pentru clientul 16 rămâne neschimbată
    if (($_SESSION['client_id'] ?? null) == 16 && $nr_bon) {
        $folder_path = offline_api_path($_SESSION['client_id'], $_SESSION["cod_locatie"]);
        if (!offline_api_ensure_dir($folder_path)) {
            throw new Exception("Nu s-a putut crea directorul API offline.");
        }
        $json_file_path = $folder_path . "/refresh_pos_client.json";
        $json_data = json_encode(['nr_bon' => $nr_bon, 'timestamp' => time()]);
        file_put_contents($json_file_path, $json_data, LOCK_EX);
    }

    echo json_encode(['success' => true, 'id_vanz' => $id_vanz]);

} catch (Throwable $e) {
    // Dacă apare o eroare, anulăm toate modificările din tranzacție
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("EROARE in sterge_prod.php: " . $e->getMessage());
    http_response_code($e instanceof RuntimeException ? 404 : 500);
    echo json_encode(['success' => false, 'message' => 'Pozitia nu a putut fi stearsa.']);
}

// Oprim execuția.
exit();
?>
