<?php

	include 'database_connection.php';
  
	$title = 'Delete customer';
  
  include 'header.php';
?>

      <!-- Breadcrumbs-->
      <ol class="breadcrumb">
        
                <li class="breadcrumb-item">
          <a href="customer_list.php">Customers</a>
        </li>
        <li class="breadcrumb-item active">Delete customer</li>
      </ol>
      <div class="container">
		<style>label{font-weight:bold;}</style>	
	
	<h4 align="center">Are you sure you want to delete the customer?</h4><hr>

     <form method="POST">
         
    <input type="submit" name="yes" value="Yes" class='btn btn-primary btn-block'>
     <input type="submit" name="no" value="No" class='btn btn-primary btn-block'>
         
     </form>

	  </div>
	  
	 <?php 
	 
	 	if(isset($_POST['yes'])){

    $cust_det=$_SESSION['cust_det'];

    $sql="DELETE from $tabel_final_customers WHERE $tabel_final_customers.customer_id ='$cust_det';";
$pdo->exec($sql) or die(print_r($pdo->errorInfo(), true));

echo '<script language="javascript">';
echo 'alert("The customer has been deleted!")';
echo '</script>';


printf("<script>location.href='customer_list.php'</script>");

}

	 	if(isset($_POST['no'])){

       
 printf("<script>location.href='customer_list.php'</script>");
   
    
}

?>
   <?php include 'footer.php'; ?>
