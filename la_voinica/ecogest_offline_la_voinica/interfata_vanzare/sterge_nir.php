<?php

	include 'database_connection.php';
  
	$title = 'Sterge produs';
  
  include 'header.php';
  
  $det_nir=$_SESSION['nr_nir'];

?>

      <!-- Breadcrumbs-->
      <ol class="breadcrumb">
        
                <li class="breadcrumb-item">
          <a href="note_de_receptie.php">Note de receptie</a>
        </li>
        <li class="breadcrumb-item active">Sterge nota de receptie</li>
      </ol>
      <div class="container">
		<style>label{font-weight:bold;}</style>	
	
	<h4 align="center">Sigur doriti sa stergeti nota de receptie <?php echo $det_nir; ?>?</h4><hr>
     <form method="POST">
         
    <input type="submit" name="yes" value="Da" class='btn btn-primary btn-block'>
     <input type="submit" name="no" value="Nu" class='btn btn-primary btn-block'>
         
     </form>

	  </div>
	  
	 <?php 
	 	if(isset($_POST['yes'])){

$det_nir=$_SESSION['nr_nir'];
$status=$_SESSION['status'];


        	if($status=='F')		
{
$delsql="SELECT $tabel_final_achizitii.cod_p,$tabel_final_achizitii.cantitate_prim from $tabel_final_achizitii where nr_nir='$det_nir'";
$delstmt = $pdo->prepare($delsql);  
$delstmt->execute(); 
while ($row = $delstmt->fetch(PDO::FETCH_ASSOC)){
    
    $produs=$row['cod_p'];
        $cantitate=$row['cantitate_prim'];
        

        $stocsql="update $tabel_final_stoc set cantitate=cantitate-'$cantitate' where cod_p='$produs';";

$stocstmt = $pdo->prepare($stocsql);  
$stocstmt->execute(); 
        
				}
}
				        	if($status=='F')		
{
				$delsql2="DELETE FROM $tabel_final_achizitii where nr_nir='$det_nir';DELETE from $tabel_final_nir where nr_nir='$det_nir';DELETE from $tabel_final_miscari where fel_doc='NIR' and nr_doc='$det_nir';";
}
else{
 				$delsql2="DELETE FROM $tabel_final_achizitii where nr_nir='$det_nir';DELETE from $tabel_final_nir where nr_nir='$det_nir';";
   
}
$delstmt2 = $pdo->prepare($delsql2);  
$delstmt2->execute(); 


    echo '<script language="javascript">';
echo 'alert("Nota de receptie a fost stearsa!")';
echo '</script>';
printf("<script>location.href='note_de_receptie.php'</script>");

}

	 	if(isset($_POST['no'])){

       
 printf("<script>location.href='note_de_receptie.php'</script>");
   
    
}

?>
   <?php include 'footer.php'; ?>

