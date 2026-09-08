<?php 

 if(isset($_POST[$cod_p])){
     $cantitate=$_POST['cantitate_de_adaugat'];
 	 $confirmare="Anuntul a fost postat! Id-ul anuntului este:".$cantitate;
 echo '<script language="javascript">';
  echo 'var myvar = ';echo json_encode($confirmare).';';

echo 'alert';echo'('.myvar.');';
echo '</script>';
 
 }

?>