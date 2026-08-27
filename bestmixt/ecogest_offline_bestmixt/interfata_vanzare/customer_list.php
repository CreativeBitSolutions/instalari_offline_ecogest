<?php
include 'database_connection.php';
 // unset($_SESSION['shoppingbasket']);
//unset($_SESSION['custloggin']);
$title = 'Customers';

include "header.php";

?>




<!-- about -->

 <ol class="breadcrumb">

<li class="breadcrumb-item active">Clienti</li>
</ol>

 <div class="card mb-3">
<div class="card-header">
<i class="fa fa-users"></i> Lista Clientilor</div>
<div class="card-body">

<form method="post" style="float:right;" class="form-inline my-2 my-lg-0 mr-lg-2">
<div style=" width:23em;" class="input-group">
<input class="form-control" type="text" name="search" placeholder="Cauta client...">
<span class="input-group-btn">
<button class="btn btn-primary" type="submit">
<i class="fa fa-search"></i>
</button>
</span>
</div>
</form>
<style>td{width:20em;}</style>
<div class="table-responsive">
<?php


if (isset($_POST['search'])) {
$searchq = $_POST['search'];
$searchq = preg_replace("#[^0-9a-z]#i","",$searchq);
 
$search_query="SELECT * from $tabel_final_customers WHERE customer_firstname LIKE '%$searchq%' or customer_lastname LiKE '%$searchq%' or customer_preferred_contact_method LIKE '%$searchq%' or customer_email_address LIKE '%$searchq%'";
$query=$pdo->prepare($search_query);
$query->execute();
$count=$query->rowCount();
 

 if($count == 0) {
 
 
 
 
echo "<br><h3 align='center'>No results found!</h3>";
 } else { 
 


echo "<table class='table table-bordered' id='dataTable' width='100%' cellspacing='0'>"; 
echo" <thead><tr><th>Prenume</th><th>Nume</th><th>Adresa de Email</th><th>Număr de Telefon</th><th colspan='2'>Acțiune</th></tr></thead>"; 
 while($row = $query->fetch(PDO::FETCH_ASSOC)) {

$cust_det=$row['customer_id'];
$cust_del=$row['customer_id'];
$cust_del=strval($cust_del);
$cust_del.='D';

echo"<tr>";
echo"<td>".$row['customer_firstname']."</td><td>".$row['customer_lastname']."</td><td>".$row['customer_email_address']."</td><td>".$row['customer_telephone_no']."</td><td><form method='post'><input class='btn btn-primary btn-block' type='submit' value='Detalii' name='$cust_det'></td>";

$administrator="administrator";
   include('database_connection.php');
$admin_em=$_SESSION['adminloggedin'];

				
$ssql="SELECT * FROM $tabel_final_admins WHERE admin_email_address='$admin_em' and rank='$administrator'";

$zsql=$pdo->prepare($ssql);


	  $zsql->execute();
	  //count the returned number of rows

	  $ccount=$zsql->rowCount();
	 
	 if($ccount == 1) {echo"
<td><input class='btn btn-primary btn-block' type='submit' value='Sterge' name='$cust_del'></td>";}
echo "</form></tr>";
if (isset($_POST[$cust_det])) {

 $_SESSION['cust_det']=$cust_det;

 
printf("<script>location.href='customer_details.php'</script>");
}

if (isset($_POST[$cust_del])) {



$sql="DELETE from $tabel_final_customers WHERE $tabel_final_customers.customer_id ='$cust_det';";
$pdo->exec($sql) or die(print_r($pdo->errorInfo(), true));



printf("<script>location.href='customer_list.php'</script>");


}




}
echo" <tfoot><tr><th> Prenume</th><th>Nume</th><th>Adresă de Email</th><th>Număr de Telefon</th><th colspan='2'>Acțiune</th></tr></tfoot>"; 


echo "</table>";

}}

if (!isset($_POST['search'])) {

echo "<table style='display:block' class='table table-bordered' id='dataTable' width='100%' cellspacing='0'>"; 
 
 
}

else {
echo "<table style='display:none' class='table table-bordered' id='dataTable' width='100%' cellspacing='0'>"; 

}
echo" <thead><tr><th> Prenume</th><th>Nume</th><th>Adresă de Email</th><th>Număr de Telefon</th><th colspan='2'>Acțiune</th></tr></thead>"; 

$dsql = "SELECT * from $tabel_final_customers";
$dstmt = $pdo->prepare($dsql);
$dstmt->execute();
while($row = $dstmt->fetch(PDO::FETCH_ASSOC)) {

$cust_det=$row['customer_id'];
$cust_del=$row['customer_id'];
$cust_del=strval($cust_del);
$cust_del.='DE';

echo"<tr>";
echo"<td>".$row['customer_firstname']."</td><td>".$row['customer_lastname']."</td><td>".$row['customer_email_address']."</td><td>".$row['customer_telephone_no']."</td><td><form method='post'><input class='btn btn-primary btn-block' type='submit' value='Details' name='$cust_det'></td>";

$administrator="administrator";
   include('database_connection.php');
$admin_em=$_SESSION['adminloggedin'];

				
	  // search in admins table for the record with admin_email_address=email address of logged user and rank=administrator
				
$ssql="SELECT * FROM $tabel_final_admins WHERE admin_email_address='$admin_em' and rank='$administrator'";

$zsql=$pdo->prepare($ssql);

	  $zsql->execute();
	  //count the returned number of rows

	  $ccount=$zsql->rowCount();
	 
	 if($ccount == 1) {echo "<td><input class='btn btn-primary btn-block' type='submit' value='Delete' name='$cust_del'></td>";} echo "</form></tr>";

if (isset($_POST[$cust_det])) {

 $_SESSION['cust_det']=$cust_det;

 
printf("<script>location.href='customer_details.php'</script>");
}

if (isset($_POST[$cust_del])) {



$sql="DELETE from $tabel_final_customers WHERE $tabel_final_customers.customer_id ='$cust_det';";
$pdo->exec($sql) or die(print_r($pdo->errorInfo(), true));
$message = "Dear";



printf("<script>location.href='customer_list.php'</script>");


}




}
echo" <tfoot><tr><th> Prenume</th><th>Nume</th><th>Adresă de email</th><th>Număr de Telefon</th><th colspan='2'>Acțiune</th></tr></tfoot>"; 


echo "</table>";
?> 
</div>
</div>
</div>



<?php 
require "footer.php";
?>