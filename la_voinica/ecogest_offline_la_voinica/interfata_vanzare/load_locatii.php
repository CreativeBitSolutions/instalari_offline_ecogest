<?php
include('session.php');
        $l = $_GET['locatie'];
$amanate_sql = "SELECT cod_masa from $tabel_final_mese where stare='0' and cod_locatie='$l'";    
$amanate_stmt = $pdo->prepare($amanate_sql);  
$amanate_stmt->execute(); 
        $locatii ='';

while ($rrrow = $amanate_stmt->fetch(PDO::FETCH_ASSOC)){ 
$masa=$rrrow['cod_masa'];
$m=$rrrow['cod_masa'];
$m=strval($m);
$m.='M';
$locatii.=
"<figure><form method='POST'><button type='submit' name='$m' value='$masa' class='my_button' class='operat'><img width='90px' height='90px' src='images/masa.png' />
</button><figcaption style='text-align:center;'>Masa nr. $masa</figcaption></figure></form>";

} 


if($locatii == ''){
            echo '';}
        else {
            echo $locatii;}

?>