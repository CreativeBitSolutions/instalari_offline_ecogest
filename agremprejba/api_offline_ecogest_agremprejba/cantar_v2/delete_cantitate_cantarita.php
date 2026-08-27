<?php
if (isset($_GET['client_id'], $_GET['cod_locatie'], $_GET['requestId'])) {
    $client_id = preg_replace('/[^0-9]/', '', $_GET['client_id']);
    $cod_locatie = preg_replace('/[^0-9]/', '', $_GET['cod_locatie']);
    $request_id = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['requestId']);

    $folder = __DIR__ . "/$client_id/$cod_locatie";
    $jsonFile = $folder . '/cantitate_' . $request_id . '.json';

    if (file_exists($jsonFile)) {
        unlink($jsonFile);
    }
}
?>