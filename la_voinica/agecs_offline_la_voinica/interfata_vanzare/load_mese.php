

<?php
include('session.php');
        $l = $_GET['locatie'];
        $adm_id=$_SESSION['admin_id'];
$mese_desch_sql = "SELECT nrbon,cod_masa from $tabel_final_note where status='S' and tableta='$l' and $tabel_final_note.locatie=1";    
$mese_desch_stmt = $pdo->prepare($mese_desch_sql);  
$mese_desch_stmt->execute(); 
while ($rrow = $mese_desch_stmt->fetch(PDO::FETCH_ASSOC)){
$masa=$rrow['cod_masa'];
$bon_amanat=$rrow['nrbon'];





echo "
<figure class='masa'><a href='schimb_masa.php?nota=$bon_amanat'<button class='btn btn-default list-group' type='button' style='display:block;float:left;'  >Nota nr. $bon_amanat</br>"; if($masa==9999){echo " La Pachet";}else{echo "Masa nr. ".$masa; } echo"</br> Produse:</br>";$mese_desch_sql2 = "SELECT $tabel_final_nomenclator.den_p from $tabel_final_det_note INNER JOIN $tabel_final_nomenclator on $tabel_final_det_note.cod_p=$tabel_final_nomenclator.cod_p where $tabel_final_det_note.nr_bon='$bon_amanat'";    
$mese_desch_stmt2 = $pdo->prepare($mese_desch_sql2);  
$mese_desch_stmt2->execute(); 

					while ($row = $mese_desch_stmt2->fetch()) {
					    
					   echo "<p style='font-weight:bold'>".$row['den_p']."</p>"; 
					} echo " 
</button></figure></a>";
}


if($locatii == ''){
            echo '';}
        else {
            echo $locatii;}

?>