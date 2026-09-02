<?php

	include 'database_connection.php';
  
	$title = 'Sterge produs';
  
  include 'header.php';
  
  $det_nir=$_SESSION['nr_bon_c'];

?>

      <!-- Breadcrumbs-->
      <ol class="breadcrumb">
        
                <li class="breadcrumb-item">
          <a href="note_de_receptie.php">Bonuri de consum</a>
        </li>
        <li class="breadcrumb-item active">Sterge bonul de consum</li>
      </ol>
      <div class="container">
		<style>label{font-weight:bold;}</style>	
	
	<h4 align="center">Sigur doriti sa stergeti bonul de consum <?php echo $det_nir; ?>?</h4><hr>
     <form method="POST">
         
    <input type="submit" name="yes" value="Da" class='btn btn-primary btn-block'>
     <input type="submit" name="no" value="Nu" class='btn btn-primary btn-block'>
         
     </form>

	  </div>
	  
	 <?php 
	 	if(isset($_POST['yes'])){

$det_nir=$_SESSION['nr_bon_c'];

$finalizat=$_SESSION['status'];


        	if($finalizat==1){	
$delsql="SELECT $tabel_final_consumuri.cod_p,$tabel_final_consumuri.cantitate_elib from $tabel_final_consumuri where nr_bon='$det_nir'";
$delstmt = $pdo->prepare($delsql);  
$delstmt->execute(); 
while ($row = $delstmt->fetch(PDO::FETCH_ASSOC)){
    
    $produs=$row['cod_p'];
        $cantitate=$row['cantitate_elib'];
        $stocsql="update $tabel_final_stoc set cantitate=cantitate+'$cantitate' where cod_p='$produs';";

$stocstmt = $pdo->prepare($stocsql);  
$stocstmt->execute(); 
        
				}		
        	}
        	        	if($finalizat==1){	

        	
				$delsql2="DELETE FROM $tabel_final_consumuri where nr_bon='$det_nir';DELETE from $tabel_final_bonuri_consum where nr_bon='$det_nir';DELETE from $tabel_final_miscari where fel_doc='BC' and nr_doc='$det_nir';";
				
        	        	}
        	        	else{
        	        	    				$delsql2="DELETE FROM $tabel_final_consumuri where nr_bon='$det_nir';DELETE from $tabel_final_bonuri_consum where nr_bon='$det_nir';";

        	        	}
$delstmt2 = $pdo->prepare($delsql2);  
$delstmt2->execute(); 


    echo '<script language="javascript">';
echo 'alert("Bonul de consum a fost sters !")';
echo '</script>';
printf("<script>location.href='bonuri_de_consum_personalizate.php'</script>");

}

	 	if(isset($_POST['no'])){

       
 printf("<script>location.href='bonuri_de_consum_personalizate.php'</script>");
   
    
}

?>
   <?php include 'footer.php'; ?>

