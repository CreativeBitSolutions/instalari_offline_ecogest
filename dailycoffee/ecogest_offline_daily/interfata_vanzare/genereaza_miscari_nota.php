<?php
// genereaza_miscari_nota.php
include('session.php');
require_once('logica_generare_miscari.php'); // Vom plasa funcția într-un fișier separat

if (!isset($_POST['nr_bon'])) {
    header('Location: verifica_note.php?status=eroare&mesaj=' . urlencode('Eroare: Nr. bon invalid.'));
    exit();
}

$nr_bon = (int)$_POST['nr_bon'];

// Verificare suplimentară pentru a evita duplicatele
$check_sql = "SELECT COUNT(*) FROM miscari WHERE fel_doc = 'BF' AND nr_doc = :nr_bon";
$check_stmt = $pdo->prepare($check_sql);
$check_stmt->execute(['nr_bon' => $nr_bon]);
if ($check_stmt->fetchColumn() > 0) {
    header('Location: verifica_note.php?status=eroare&mesaj=' . urlencode("Mișcările pentru bonul {$nr_bon} există deja."));
    exit();
}

try {
    // Generăm mișcarea folosind funcția centralizată
    generareMiscarePentruNota($pdo, $nr_bon);
    header('Location: verifica_note.php?status=succes&mesaj=' . urlencode("Mișcările pentru bonul {$nr_bon} au fost generate cu succes!"));
    exit();
} catch (Exception $e) {
    header('Location: verifica_note.php?status=eroare&mesaj=' . urlencode("Eroare la generarea bonului {$nr_bon}: " . $e->getMessage()));
    exit();
}