<?php

include('database_connection.php');


  $date_firma2 = "SELECT note_12.cod_masa from note_12 inner join mese_12 on mese_12.cod_masa=note_12.cod_masa where note_12.status='F' and mese_12.stare=1 group by mese_12.cod_masa ";    
$date_firma_stmt2 = $pdo->prepare($date_firma2);  
$date_firma_stmt2->execute(); 
while ($row = $date_firma_stmt2->fetch(PDO::FETCH_ASSOC)){ 
echo $cod_masa."<br>";
$cod_masa=$row['cod_masa'];

  $date_firma3 = "UPDATE mese_12 set stare=0 where cod_masa='$cod_masa'";    
$date_firma_stmt3 = $pdo->prepare($date_firma3);  
$date_firma_stmt3->execute(); 
}






?>