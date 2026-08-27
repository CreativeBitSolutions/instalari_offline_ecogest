<?php session_start();
	include 'database_connection.php';

	if(!isset($_SESSION['custloggin']))
	{
	    				printf("<script>location.href='home_page.php'</script>");

	    
	}
	include "website_header.php";
				printf("<script>location.href='customer_orders.php#bottomOfPage'</script>");

?>

	
  
<!-- about -->
 <a name="bottomOfPage"></a>
 
		<style>
	h3 {
	margin:2em;
	
}


	</style>
<div class="article">
<h3 style="margin:2em" align="center">Comenzile dumneavoastră</h3><hr>
<div class="container"><?php
$cust_id=$_SESSION['cust_id'];
$dsql = "SELECT * FROM $tabel_final_comenzi where cod_client='$cust_id'";    
$dstmt = $pdo->prepare($dsql);  
$dstmt->execute();  

echo "<table class='table table-bordered' id='dataTable' width='100%' cellspacing='0'>"; 
echo" <thead><tr><th>Număr comandă</th><th>Data comenzii</th><th>Valoare comandă</th><th>Stare</th><th colspan='2'>Opțiuni</th></tr></thead>"; 
while ($row = $dstmt->fetch(PDO::FETCH_ASSOC)){  

$com_det=$row['nr_comanda'];
$cust_type=$row['customer_type'];
$quantity=$row['quantity'];
$availability_id=$row['availability_id'];
$book_status=$row['status'];
              echo"<tr>";    
              echo"<td>$com_det</td><td>".$row['data_comenzii'].' ora '.$row['ora_comenzii']."</td><td>".$row['valoare_comanda']."</td><td>".$row['status']."</td><td><form method='post'><input class='btn btn-primary btn-block' type='submit' value='Detalii Comanda' name='$com_det'></td></form></tr>";

if (isset($_POST[$com_det])) {
								
$_SESSION['com_id']=$com_det;


			printf("<script>location.href='customer_order_details.php'</script>");
				}



}

echo" <tfoot><tr><th>Număr comandă</th><th>Data comenzii</th><th>Valoare comandă</th><th>Stare</th><th colspan='2'>Opțiuni</th></tr></tfoot>"; 

echo "</table>";



?>
<div class="clearfix"></div>
</div>
</div><br>
<!-- /about -->

<?php 
	require "website_footer.php";
?>