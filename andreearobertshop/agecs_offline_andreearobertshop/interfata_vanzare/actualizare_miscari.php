<?php

include('database_connection.php');
  $date_firma = "SELECT id,cod_p from miscari_12 where fel_doc='BF'";    
$date_firma_stmt = $pdo->prepare($date_firma);  
$date_firma_stmt->execute(); 



while ($row = $date_firma_stmt->fetch(PDO::FETCH_ASSOC)){ 
$cod_p=$row['cod_p'];
$id=$row['id'];
  $date_firma2 = "SELECT pret_vanzare from nomenclator_12 where cod_p='$cod_p'";    
$date_firma_stmt2 = $pdo->prepare($date_firma2);  
$date_firma_stmt2->execute(); 
while ($row = $date_firma_stmt2->fetch(PDO::FETCH_ASSOC)){ 

$pret_vanz=$row['pret_vanzare'];

  $date_firma3 = "UPDATE miscari_12 set pu='$pret_vanz' where id='$id'";    
$date_firma_stmt3 = $pdo->prepare($date_firma3);  
$date_firma_stmt3->execute(); 
}





}

?>