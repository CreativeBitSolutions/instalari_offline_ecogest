<?php
// Simplul fapt că pornim sesiunea îi resetează timpul de expirare pe server.
include('session.php'); 

// Poți trimite un răspuns pentru a confirma
echo json_encode(['status' => 'success', 'message' => 'Session extended.']);
?>