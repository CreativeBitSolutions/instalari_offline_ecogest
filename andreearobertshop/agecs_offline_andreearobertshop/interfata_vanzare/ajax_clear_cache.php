<?php
// ajax_clear_cache.php
include('session.php');

// Includem fișierul care conține funcțiile de ștergere cache.
// ATENȚIE: Verifică dacă fișierul tău se numește 'cache_tools.php'
// Bazat pe codul tău, pare că 'cache_tools.php' este cel folosit în POS.
if (file_exists('cache_tools.php')) {
    require_once 'cache_tools.php';
}

header('Content-Type: application/json');

try {
    $client_id = $_SESSION['client_id'] ?? null;
    $cod_locatie = $_SESSION['cod_locatie'] ?? null;

    if ($client_id && $cod_locatie) {
        // 1. Șterge cache-ul de fișiere (grid produse, json barcodes etc)
        if (function_exists('clear_cache_for_client_location')) {
            clear_cache_for_client_location($client_id, $cod_locatie);
        }

        // 2. Invalidează listele specifice (grid HTML)
        if (function_exists('invalidate_prodlists_for_client_location')) {
            invalidate_prodlists_for_client_location($client_id, $cod_locatie);
        }

        // 3. Invalidează codurile de bare
        if (function_exists('invalidate_barcodes_for_client_location')) {
            invalidate_barcodes_for_client_location($client_id, $cod_locatie);
        }

        echo json_encode(['status' => 'success', 'message' => 'Cache server sters cu succes.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Sesiune invalida.']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>