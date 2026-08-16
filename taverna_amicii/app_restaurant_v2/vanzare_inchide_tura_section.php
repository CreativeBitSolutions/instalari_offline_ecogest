<?php 
         include('session.php');
         $adm_id=$_SESSION['admin_id'];
         $cod_locatie=$_SESSION['cod_locatie'];


         // Verifică dacă există bonuri neînchise (status 'F')
            $bon_sql = "SELECT COUNT(*) 
                        FROM $tabel_final_note 
                        WHERE $tabel_final_note.cod_inchidere = 0 
                          AND $tabel_final_note.status = 'F' 
                          AND $tabel_final_note.locatie = :locatie 
                          AND operator = :operator";
            
            $bon_stmt = $pdo->prepare($bon_sql);  
            $bon_stmt->execute([
                ':locatie' => $cod_locatie,
                ':operator' => $adm_id
            ]);
            
            $bon_neinchis_count = (int)$bon_stmt->fetchColumn();
            
            if ($bon_neinchis_count >= 1) {
                // Verifică dacă există note deschise (status 'S') pentru același operator
                $note_deschise_sql = "SELECT 1 
                                      FROM $tabel_final_note 
                                      WHERE status = 'S' 
                                        AND locatie = :locatie 
                                        AND operator = :operator 
                                      LIMIT 1";
            
                $note_deschise_stmt = $pdo->prepare($note_deschise_sql);
                $note_deschise_stmt->execute([
                    ':locatie' => $cod_locatie,
                    ':operator' => $adm_id
                ]);
            
                if (!$note_deschise_stmt->fetchColumn()) {
                    // Nu există mese deschise, afișează butonul
                    echo "
                    <form style='float:right;' method='POST'>
                        <button type='submit' class='square' name='inchidere_zi' style='width:100%; margin-top:10%;padding:10px; font-size:1em; border:1px solid #ccc; border-radius:5px;background-color:black;color:white;'>InchideTura</button>
                    </form>";
                } else {
                    // Există mese deschise, afișează mesajul
                    echo "<div style='float:right; color:red; font-weight:bold;'>Închideți toate mesele înainte de a închide tura.</div>";
                }
            }
            
            
            
              // mai jos buton inchidere zi raport z 
              

     // Numărul total de note pentru locația respectivă, cu status 'F' și nr_raport_z = 0
$sql_total = "SELECT COUNT(*) FROM note 
WHERE locatie = :cod_locatie 
  AND status = 'F' 
  AND nr_raport_z = 0";
$stmt_total = $pdo->prepare($sql_total);
$stmt_total->execute(['cod_locatie' => $cod_locatie]);
$total = $stmt_total->fetchColumn();

// Numărul de note pentru care cod_inchidere nu este 0
$sql_valid = "SELECT COUNT(*) FROM note 
WHERE locatie = :cod_locatie 
  AND status = 'F' 
  AND nr_raport_z = 0 
  AND cod_inchidere != 0";
$stmt_valid = $pdo->prepare($sql_valid);
$stmt_valid->execute(['cod_locatie' => $cod_locatie]);
$valid = $stmt_valid->fetchColumn();

if ($total == $valid && $total!=0 && $valid!=0) {
echo '
<button type="button" class="square img_1-12" style="float:right;" data-toggle="modal" data-target="#raportZModal">
</button>';
}
              ?>
