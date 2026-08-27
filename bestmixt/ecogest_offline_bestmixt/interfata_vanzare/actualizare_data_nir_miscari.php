<?php

include('database_connection.php');
  $date_firma = "SELECT id,nr_doc FROM miscari_12 where data='0000-00-00' and fel_doc='NIR'";    
$date_firma_stmt = $pdo->prepare($date_firma);  
$date_firma_stmt->execute(); 


while ($row = $date_firma_stmt->fetch(PDO::FETCH_ASSOC)){ 
$nr_doc=$row['nr_doc'];
$id=$row['id'];

  $date_firma2 = "SELECT data_nir from nir_12 where nr_nir='$nr_doc'";    
$date_firma_stmt2 = $pdo->prepare($date_firma2);  
$date_firma_stmt2->execute(); 
while ($row = $date_firma_stmt2->fetch(PDO::FETCH_ASSOC)){ 

$data_nir=$row['data_nir'];
}
  $date_firma3 = "UPDATE miscari_12 set data='$data_nir' where id='$id'";    
$date_firma_stmt3 = $pdo->prepare($date_firma3);  
$date_firma_stmt3->execute(); 






}

?>