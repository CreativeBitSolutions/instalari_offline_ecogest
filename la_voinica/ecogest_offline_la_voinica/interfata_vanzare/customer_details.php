<?php

include 'database_connection.php';
  
$title = 'Customer details';
  
  include 'header.php';

?>

  <!-- Breadcrumbs-->
  <ol class="breadcrumb">

<li class="breadcrumb-item">
  <a href="customer_list.php">Customers</a>
</li>
<li class="breadcrumb-item active">Customer Details</li>
  </ol>
  <div class="container">
<section class="right">

<?php



if (isset($_SESSION['adminloggedin']) && $_SESSION['adminloggedin'] == true) {
?>
   

   <style>th{width:20em;}</style>
<h4 align='center'> Customer Details</h4><br>
 <?php
 
$cust_id=$_SESSION['cust_det'];  
$dsql = "SELECT * from $tabel_final_customers where customer_id='$cust_id'";
$dstmt = $pdo->prepare($dsql);  
$dstmt->execute(); 


while ($row = $dstmt->fetch(PDO::FETCH_ASSOC)){  
$customer_title=$row['customer_title'];
$customer_firstname=$row['customer_firstname'];
$customer_lastname=$row['customer_lastname'];
$customer_email_address=$row['customer_email_address'];
$customer_telephone_no=$row['customer_telephone_no'];
$customer_postcode=$row['customer_postcode'];
$customer_address_line1=$row['customer_address_line1'];
 $customer_address_line2=$row['customer_address_line2'];
$customer_address_line3=$row['customer_address_line3'];
$customer_preferred_contact_method=$row['customer_preferred_contact_method'];

echo "<table style='font-size:1em' class='table table-borderless'> 
<tr><th>Title:</th>
<td>$customer_title</td></tr>

<tr><th>First Name:</th><td>
 $customer_firstname</td>
</tr>
<tr><th>Last Name:</th><td>
 $customer_lastname</td></tr>
 <tr><th>Address Line 1:</th><td>
 $customer_address_line1</td></tr>
 <tr><th>Address Line 2:</th><td>
 $customer_address_line2</td></tr>
 <tr><th>Address Line 3:</th><td>
 $customer_address_line3</td></tr>
 <tr><th>Postcode:</th><td>
 $customer_postcode</td></tr>
 <tr><th>Email Address:</th><td>
 $customer_email_address</td></tr>
 <tr><th>Telephone Number:</th><td>
 $customer_telephone_no</td></tr>
 <tr><th>Preffered Contact Method:</th><td>
 $customer_preferred_contact_method</td></tr>

  </table><hr>";


}


}
?>



<br>

   </section>

   <?php include 'footer.php'; ?>
