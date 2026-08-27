<?php

	include 'database_connection.php';
  
	$title = 'Delete admin';
  
  include 'header.php';
?>

      <!-- Breadcrumbs-->
      <ol class="breadcrumb">
        
                <li class="breadcrumb-item">
          <a href="admin_list.php">Administrators</a>
        </li>
        <li class="breadcrumb-item active">Delete admin</li>
      </ol>
      <div class="container">
		<style>label{font-weight:bold;}</style>	
	
	<h4 align="center">Are you sure you want to delete the administrator?</h4><hr>

     <form method="POST">
         
    <input type="submit" name="yes" value="Yes" class='btn btn-primary btn-block'>
     <input type="submit" name="no" value="No" class='btn btn-primary btn-block'>
         
     </form>

	  </div>
	  
	 <?php 
	 
	 	if(isset($_POST['yes'])){

    $adm_id=$_SESSION['adm_id'];

 			$sql="DELETE FROM $tabel_final_admins WHERE admin_id ='$adm_id';";
$pdo->exec($sql) or die(print_r($pdo->errorInfo(), true));


echo '<script language="javascript">';
echo 'alert("The admin has been deleted!")';
echo '</script>';


printf("<script>location.href='admin_list.php'</script>");

}

	 	if(isset($_POST['no'])){

       
 printf("<script>location.href='admin_list.php'</script>");
   
    
}

?>
   <?php include 'footer.php'; ?>
