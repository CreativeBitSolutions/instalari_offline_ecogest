<?php
// Include fișierul de conexiune la baza de date
include('database_connection.php');
require_once __DIR__ . '/det_note_departament_listare_schema.php';
agecs_ensure_det_note_departament_listare($pdo, $tabel_final_det_note);
$departamentListareSql = agecs_departament_listare_sql('dn', 'ps');

// Funcție utilitară pentru formatarea cantității
function formatQuantity($qty) {
    $cant = floatval($qty);
    return ($cant == floor($cant))
        ? number_format($cant, 0, '.', '')
        : rtrim(rtrim(number_format($cant, 2, '.', ''), '0'), '.');
}

// Preluăm bonurile unde TOATE produsele sunt terminate
$sqlAllNotes = "SELECT n.nrbon, n.data_deschidere, a.admin_firstname, a.admin_lastname, m.nume_masa
                FROM note AS n
                JOIN admins_12 AS a ON n.operator = a.admin_id
                LEFT JOIN mese AS m ON n.cod_masa = m.cod_masa
                WHERE n.status = 'S'
                ORDER BY n.data_deschidere DESC";
$stmtAllNotes = $pdo->prepare($sqlAllNotes);
$stmtAllNotes->execute();
$allNotes = $stmtAllNotes->fetchAll(PDO::FETCH_ASSOC);

$finishedNotesHtml = "<div class='row'>";
$foundFinished = false;

foreach ($allNotes as $note) {
    // Verificăm dacă există vreun produs în așteptare pentru acest bon
  $sqlCheckPending = "SELECT COUNT(dn.id_vanz) as pending_count 
                    FROM det_note AS dn
                    LEFT JOIN produse_servicii AS ps ON dn.cod_p = ps.cod_produs
                    WHERE dn.nr_bon = :nrbon 
                      AND dn.t_list = 1 
                      AND dn.preluat_osp = 0 
                      AND {$departamentListareSql} = 'BUCATARIE'";
    $stmtCheck = $pdo->prepare($sqlCheckPending);
    $stmtCheck->execute(['nrbon' => $note['nrbon']]);
    $result = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    // Dacă nu există produse în așteptare, bonul este terminat
    if ($result['pending_count'] == 0) {
        // Verificam daca bonul are cel putin un produs listabil
$sqlCheckHasProducts = "SELECT COUNT(dn.id_vanz) as count 
                        FROM det_note AS dn
                        LEFT JOIN produse_servicii AS ps ON dn.cod_p = ps.cod_produs
                        WHERE dn.nr_bon = :nrbon 
                          AND dn.t_list = 1
                          AND {$departamentListareSql} = 'BUCATARIE'";
                                  $stmtHasProducts = $pdo->prepare($sqlCheckHasProducts);
        $stmtHasProducts->execute(['nrbon' => $note['nrbon']]);
        if ($stmtHasProducts->fetchColumn() == 0) continue;

        $foundFinished = true;
        
        $ospatar = htmlspecialchars($note['admin_firstname'] . ' ' . $note['admin_lastname']);
        $numeMasa = htmlspecialchars($note['nume_masa'] ?? 'N/A');
        $formattedDateTime = date("d.m.Y H:i", strtotime($note['data_deschidere']));

        // Preluăm produsele pentru afișare detaliată
      $sqlProducts = "SELECT dn.nume_produs, dn.cantitate, dn.observatie_produs 
                FROM det_note AS dn
                LEFT JOIN produse_servicii AS ps ON dn.cod_p = ps.cod_produs
                WHERE dn.nr_bon = :nrbon 
                  AND dn.t_list = 1
                  AND {$departamentListareSql} = 'BUCATARIE'
                ORDER BY dn.data ASC, dn.ora ASC";
        $stmtProducts = $pdo->prepare($sqlProducts);
        $stmtProducts->execute(['nrbon' => $note['nrbon']]);
        $products = $stmtProducts->fetchAll(PDO::FETCH_ASSOC);

        $productsHtml = "<ul class='list-unstyled mb-0'>";
        foreach ($products as $prod) {
            $productsHtml .= "<li class='mb-1 p-2 border rounded' style='background-color: #f0f9f0;'>";
            $productsHtml .= "<strong>" . htmlspecialchars($prod['nume_produs']) . " x" . formatQuantity($prod['cantitate']) . "</strong>";
            if (!empty($prod['observatie_produs'])) {
                $productsHtml .= "<br><small>Obs: " . htmlspecialchars($prod['observatie_produs']) . "</small>";
            }
            $productsHtml .= "</li>";
        }
        $productsHtml .= "</ul>";

        $finishedNotesHtml .= "
        <div class='col-md-6 col-lg-4 mb-3'>
            <div class='card h-100'>
                <div class='card-header bg-success text-white'>
                    <strong>Bon #" . $note['nrbon'] . "</strong> | " . $ospatar . " | " . $numeMasa . "
                    <br><small>" . $formattedDateTime . "</small>
                </div>
                <div class='card-body' style='font-size: 0.9rem; max-height: 250px; overflow-y: auto;'>
                    " . $productsHtml . "
                </div>
            </div>
        </div>";
    }
}

if (!$foundFinished) {
    $finishedNotesHtml .= "<div class='col-12'><p class='text-center'>Nu există bonuri terminate.</p></div>";
}

$finishedNotesHtml .= "</div>";

echo $finishedNotesHtml;
?>
