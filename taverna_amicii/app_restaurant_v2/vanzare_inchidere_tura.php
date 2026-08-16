<?php //vanzare_inchidere_tura.php
require_once __DIR__ . '/totaluri_plata_helper.php';

  // INCHIDERE ZI
          // INCHIDERE ZI
          	if(isset($_POST['inchidere_zi'])){
	    // INCHIDERE ZI
 $inchidere_sql = "SELECT max(cod_inchidere) as ultim_inch from $tabel_final_note where $tabel_final_note.status='F' and $tabel_final_note.cod_inchidere>0  and $tabel_final_note.locatie='$cod_locatie'";    	
 $inchidere_stmt = $pdo->prepare($inchidere_sql);  
$inchidere_stmt->execute();
while ($row = $inchidere_stmt->fetch(PDO::FETCH_ASSOC)){ 
    $ultim_inchidere=$row['ultim_inch'];
}
$cod_inchidere_curenta=$ultim_inchidere+1;
$_SESSION['ultim_inch']=$cod_inchidere_curenta;
  $valoare_inchidere_sql = "SELECT sum($tabel_final_note.valoare_vanzare_cu_tva) as total_vz_c_tva from $tabel_final_note where $tabel_final_note.cod_inchidere=0 and $tabel_final_note.status='F'  and $tabel_final_note.locatie='$cod_locatie' and operator='$adm_id'; ";    	
  $valoare_inchidere_stmt = $pdo->prepare($valoare_inchidere_sql);  
$valoare_inchidere_stmt->execute();
while ($row = $valoare_inchidere_stmt->fetch(PDO::FETCH_ASSOC)){
	$valoare_inchidere=$row['total_vz_c_tva'];
}
  $valoare_tva_inchidere_sql = "SELECT sum($tabel_final_note.tva_colectata) as total_tva_col from $tabel_final_note where $tabel_final_note.cod_inchidere=0 and $tabel_final_note.status='F'  and $tabel_final_note.locatie='$cod_locatie' and operator='$adm_id'; ";    	
  $valoare_tva_inchidere_stmt = $pdo->prepare($valoare_tva_inchidere_sql);  
$valoare_tva_inchidere_stmt->execute();
while ($row = $valoare_tva_inchidere_stmt->fetch(PDO::FETCH_ASSOC)){
	$valoare_tva_inchidere=$row['total_tva_col'];
}
 $ora_inchiderii = date("H:i:s", strtotime('+0 hours'));
 $data_inchiderii = date("Y-m-d", strtotime('+0 hours'));
$idInchidere = 0;
$adauga_inchidere = "insert into $tabel_final_inchideri_r(cod_inchidere,operator,valoare_cu_tva,tva_colectata,data_inchiderii,ora_inchiderii,locatie) values('$cod_inchidere_curenta','$adm_id','$valoare_inchidere','$valoare_tva_inchidere','$data_inchiderii','$ora_inchiderii','$cod_locatie');";	
	try{
$pdo->exec($adauga_inchidere) or die(print_r($pdo->errorInfo(), true));   
$idInchidere = (int)$pdo->lastInsertId();
}catch(PDOException $e)
    {
    echo $adauga_inchidere . "<br>" . $e->getMessage();
    } 
$bon_sql = "SELECT $tabel_final_note.nrbon from $tabel_final_note where $tabel_final_note.cod_inchidere=0 and $tabel_final_note.status='F'  and $tabel_final_note.locatie='$cod_locatie' and operator='$adm_id'";    	
$bon_stmt = $pdo->prepare($bon_sql);  
$bon_stmt->execute();
while ($row = $bon_stmt->fetch(PDO::FETCH_ASSOC)){ 
$bon_de_inchis=$row['nrbon'];
    $inchsql="update $tabel_final_note set cod_inchidere='$cod_inchidere_curenta' where nrbon='$bon_de_inchis'"; 	 
    $inchstmt = $pdo->prepare($inchsql);  
$inchstmt->execute(); 
}
$totaluriPlataJson = restaurant_build_totaluri_plata_json(
    $pdo,
    $tabel_final_note,
    $tabel_final_det_note,
    (int)$cod_inchidere_curenta,
    (int)$cod_locatie,
    (int)$adm_id,
    $data_inchiderii . ' ' . $ora_inchiderii
);
if ($totaluriPlataJson !== null) {
    try {
        $inchideriTable = restaurant_sql_identifier($tabel_final_inchideri_r);
        if ($idInchidere > 0) {
            $stmtJson = $pdo->prepare("UPDATE {$inchideriTable} SET totaluri_plata_json = :json WHERE id_inch = :id");
            $stmtJson->execute([
                ':json' => $totaluriPlataJson,
                ':id' => $idInchidere,
            ]);
        } else {
            $stmtJson = $pdo->prepare("
                UPDATE {$inchideriTable}
                SET totaluri_plata_json = :json
                WHERE cod_inchidere = :cod_inchidere
                  AND locatie = :locatie
                  AND operator = :operator
            ");
            $stmtJson->execute([
                ':json' => $totaluriPlataJson,
                ':cod_inchidere' => (int)$cod_inchidere_curenta,
                ':locatie' => (int)$cod_locatie,
                ':operator' => (int)$adm_id,
            ]);
        }
    } catch (Throwable $e) {
        error_log('Eroare update totaluri_plata_json in vanzare_inchidere_tura.php: ' . $e->getMessage());
    }
}
$_SESSION['cod_inchidere']=$cod_inchidere_curenta;
			printf("<script>location.href='vanzare_listare_inchide_tura.php'</script>");	
          // INCHIDERE ZI
	}
 ?>
