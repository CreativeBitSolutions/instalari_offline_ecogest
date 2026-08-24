<?php

	include 'database_connection.php';
  
	$title = 'Delete category';
  
  include 'header.php';
?>

      <!-- Breadcrumbs-->
      <ol class="breadcrumb">
        
                <li class="breadcrumb-item">
          <a href="admin_categories.php">Categorii</a>
        </li>
        <li class="breadcrumb-item active">Șterge categoria</li>
      </ol>
      <div class="container">
		<style>label{font-weight:bold;}</style>	
	
	<h4 align="center">Sigur ștergeți categoria?</h4><hr>

     <form method="POST">
         
    <input type="submit" name="yes" value="Da" class='btn btn-primary btn-block'>
     <input type="submit" name="no" value="Nu" class='btn btn-primary btn-block'>
         
     </form>

	  </div>
	  
	 <?php 
	 
	 	if(isset($_POST['yes'])){

    $cat_id=$_SESSION['cat_id'];

   $sql="DELETE from $tabel_final_categorii WHERE $tabel_final_categorii.id_categorie ='$cat_id';";
$pdo->exec($sql) or die(print_r($pdo->errorInfo(), true));

echo '<script language="javascript">';
echo 'alert("Categorie stearsa!")';
echo '</script>';


printf("<script>location.href='admin_categories.php'</script>");

}

	 	if(isset($_POST['no'])){

       
 printf("<script>location.href='admin_categories.php'</script>");
   
    
}

?>
   <?php include 'footer.php'; ?>
