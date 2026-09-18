<?php
$q = intval($_GET['q']);
include('database_connection.php');

	 $psql2 = "SELECT cote_tva.cota,$tabel_final_nomenclator.pret,$tabel_final_nomenclator.pret_vanzare from $tabel_final_nomenclator inner join cote_tva on $tabel_final_nomenclator.cota_tva=cote_tva.id where cod_p='$q';";    
$pstmt2 = $pdo->prepare($psql2);  
$pstmt2->execute(); 

while ($row = $pstmt2->fetch(PDO::FETCH_ASSOC)){ 
          $pret_achiz=$row['pret'];
 $pret_vanzare=$row['pret_vanzare'];
 $cota_tva=$row['cota'];
							         $tva_unit=$pret_vanzare*$cota_tva/($cota_tva+100);
 $pret_achiz_cu_adaos=$pret_vanzare-$tva_unit;
 echo " <td><h6>Pret de achizitie (fara tva):</h6></td><td> <input   class='form-control' type='number' name='pret_achiz'  min='0.0000001'  step='0.0000001' value='$pret_achiz' ></td><td><h6>Cota tva de achiziție</h6></td><td><select class='form-control'   name='cota_tva_achiz'>";
 
 $tsql = "SELECT * FROM cote_tva";    
$stmt = $pdo->prepare($tsql);  
$stmt->execute(); 
 while ($row = $stmt->fetch(PDO::FETCH_ASSOC)){ 
                      $den_cota=$row['cota'];
		if($cota_tva==$den_cota){ 
 if($den_cota==5){$den_cota=9;}

              echo "<option selected value='$den_cota'>".$den_cota."%</option>"; 
		    
		}   
		
		else{
		                  echo "<option value='$den_cota'>".$den_cota."%</option>"; 

		}
		
 }
 echo "<option value='5'>5%</option>
 </select></td>";
          
			
}





?>