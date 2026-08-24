<?php

include('database_connection.php');

$f_sql = "SELECT cod_p,cantitate_prim from $tabel_final_achizitii;";    
$f_stmt = $pdo->prepare($f_sql);  
$f_stmt->execute(); 

while ($row = $f_stmt->fetch(PDO::FETCH_ASSOC)){ 
$codul_produsului=$row['cod_p'];
$cant_prim=$row['cantitate_prim'];


	 $psql22 = "UPDATE $tabel_final_stoc set cantitate=cantitate+$cant_prim where cod_p='$codul_produsului'";    
$pstmt22 = $pdo->prepare($psql22);  
$pstmt22->execute(); 
}

?>