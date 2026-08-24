<?php
if (isset($_GET['client_id']) && isset($_GET['cod_locatie'])) {
    // Sanitizează parametrii pentru a preveni eventuale atacuri
    $client_id = preg_replace('/[^0-9]/', '', $_GET['client_id']);
    $cod_locatie = preg_replace('/[^0-9]/', '', $_GET['cod_locatie']);

    // Definește folderul în care se află fișierul JSON
    $folder = "./$client_id/$cod_locatie";
    $jsonFile = $folder . '/cantitate_cantarita.json';

    // Verifică dacă fișierul există și îl șterge
    if (file_exists($jsonFile)) {
        if (unlink($jsonFile)) {
            echo "Fisier sters.";
        } else {
            echo "Eroare la stergerea fisierului.";
        }
    } else {
        echo "Fisierul nu exista.";
    }
} else {
    echo "Parametrii lipsesc.";
}
?>
