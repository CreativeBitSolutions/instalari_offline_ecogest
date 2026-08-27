<?php
session_start();
include('database_connection.php'); // Asigură-te că acest fișier există și e corect

// Nu mai setăm header-ul ca JSON, deoarece vom returna HTML
// header('Content-Type: application/json');

if (isset($_GET['nrbon']) && !empty($_GET['nrbon'])) {
    $nrbon = $_GET['nrbon'];
    
    // Numele tabelului din schema ta este 'det_note', nu '$tabel_final_det_note'
    // Am ajustat și numele coloanei la 'nr_bon' conform schemei tale
    $sql = "SELECT nume_produs, cantitate, pret_vanzare, valoare_vanzare_cu_tva FROM det_note WHERE nr_bon = :nrbon";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['nrbon' => $nrbon]);
    
    if ($stmt->rowCount() > 0) {
        $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Începem să construim tabelul HTML ca un string
        $output = '<table class="table table-striped table-sm">';
        $output .= '<thead><tr><th>Produs</th><th>Cantitate</th><th>Preț Unitar</th><th>Valoare Totală</th></tr></thead>';
        $output .= '<tbody>';
        
        foreach ($details as $row) {
            $output .= '<tr>';
            // Folosim htmlspecialchars pentru a preveni probleme de securitate (XSS)
            $output .= '<td>' . htmlspecialchars($row['nume_produs']) . '</td>';
            // Formatăm numerele pentru un afișaj mai curat
            $output .= '<td>' . number_format($row['cantitate'], 2) . '</td>';
            $output .= '<td>' . number_format($row['pret_vanzare'], 2) . ' RON</td>';
            $output .= '<td>' . number_format($row['valoare_vanzare_cu_tva'], 2) . ' RON</td>';
            $output .= '</tr>';
        }
        
        $output .= '</tbody></table>';
        
        // Trimitem string-ul HTML final
        echo $output;

    } else {
        // Dacă nu se găsesc produse, afișăm un mesaj prietenos
        echo '<div class="alert alert-warning">Nu s-au găsit produse pentru acest bon.</div>';
    }

} else {
    // Mesaj de eroare dacă parametrul lipsește
    echo '<div class="alert alert-danger">Eroare: Nu a fost specificat un număr de bon.</div>';
}
?>