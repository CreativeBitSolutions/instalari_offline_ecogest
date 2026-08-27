<?php

$q = intval($_GET['q']);
include('database_connection.php');

	 $psql2 = "SELECT pret_vanzare from $tabel_final_nomenclator where cod_p='$q';";    
$pstmt2 = $pdo->prepare($psql2);  
$pstmt2->execute(); 

while ($row = $pstmt2->fetch(PDO::FETCH_ASSOC)){ 
          $pret_vanzare=$row['pret_vanzare'];

 echo " Pret vanzare <input  form='ad_prod' class='form-control' type='number' name='pret_vanzare'  min='0.0000001'  step='0.0000001' value='$pret_vanzare' > lei";
          
			
}





?>