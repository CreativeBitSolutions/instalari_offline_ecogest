<?php
// genereaza_toate_miscarile_note.php
include('session.php');
require_once('logica_generare_miscari.php');

set_time_limit(0); // Anulează limita de timp pentru execuție

// Preluăm intervalul (dacă vine din verifica_note.php)
$data_start = isset($_GET['data_start']) && $_GET['data_start'] !== '' ? $_GET['data_start'] : null;
$data_end   = isset($_GET['data_end'])   && $_GET['data_end']   !== '' ? $_GET['data_end']   : null;

// Preluăm toate bonurile care necesită procesare (eventual filtrate pe interval)
$sql = "
    SELECT n.nrbon
    FROM note n
    LEFT JOIN miscari m ON n.nrbon = m.nr_doc AND m.fel_doc = 'BF'
    WHERE m.id IS NULL AND n.status = 'F'
";

$params = [];

if ($data_start !== null) {
    $sql .= " AND n.data_bon >= :data_start";
    $params['data_start'] = $data_start;
}

if ($data_end !== null) {
    $sql .= " AND n.data_bon <= :data_end";
    $params['data_end'] = $data_end;
}

$sql .= " ORDER BY n.nrbon";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bonuri_de_procesat = $stmt->fetchAll(PDO::FETCH_COLUMN);

$total = count($bonuri_de_procesat);
$succes = 0;
$erori = 0;
$mesaje_erori = [];

foreach ($bonuri_de_procesat as $nr_bon) {
    try {
        // Pentru fiecare bon, rulăm logica de generare
        generareMiscarePentruNota($pdo, $nr_bon);
        $succes++;
    } catch (Exception $e) {
        $erori++;
        $mesaje_erori[] = "Bon {$nr_bon}: " . $e->getMessage();
    }
}

$mesaj_final = "Procesare finalizată. Total bonuri: {$total}. Succes: {$succes}. Erori: {$erori}.";
if (!empty($mesaje_erori)) {
    $mesaj_final .= " Detalii erori: " . implode('; ', $mesaje_erori);
}

// Reconstruim query string astfel încât să păstrăm intervalul când ne întoarcem
$query_string = 'status=' . ($erori > 0 ? 'eroare' : 'succes') . '&mesaj=' . urlencode($mesaj_final);

if ($data_start !== null) {
    $query_string .= '&data_start=' . urlencode($data_start);
}
if ($data_end !== null) {
    $query_string .= '&data_end=' . urlencode($data_end);
}

header('Location: verifica_note.php?' . $query_string);
exit();
