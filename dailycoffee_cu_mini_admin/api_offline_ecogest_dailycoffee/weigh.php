<?php
// Verifică dacă parametrii GET necesari sunt prezenți
if (isset($_GET['weight'], $_GET['id_client'], $_GET['cod_locatie'])) {

    // Preluarea și curățarea variabilelor
    $weight = $_GET['weight'];
    $id_client = preg_replace('/[^0-9]/', '', $_GET['id_client']);
    $cod_locatie = preg_replace('/[^0-9]/', '', $_GET['cod_locatie']);

    // Calculează calea absolută a folderului
    $absoluteFolderPath = __DIR__ . "/$id_client/$cod_locatie";

    // Creează folderul dacă nu există
    if (!is_dir($absoluteFolderPath)) {
        mkdir($absoluteFolderPath, 0775, true);
    }

    // Definește calea fișierului pentru cantitate și scrie datele
    $jsonFile = $absoluteFolderPath . '/cantitate_cantarita.json';
    $jsonData = ['weight' => $weight, 'timestamp' => date("Y-m-d H:i:s")];
    file_put_contents($jsonFile, json_encode($jsonData, JSON_PRETTY_PRINT));

    // Definește calea fișierului trigger și actualizează-l la `false`
    $triggerFile = $absoluteFolderPath . '/trigger_cantar.json';

    if (file_exists($triggerFile)) {
        $triggerData = ["trigger" => false];
        file_put_contents($triggerFile, json_encode($triggerData, JSON_PRETTY_PRINT));
    }
    
    // Răspunsul final trimis către client
    echo "Valoare înregistrată.";

} else {
    // Răspuns în caz de eroare (parametri lipsă)
    echo "Nicio valoare furnizată.";
}
?>