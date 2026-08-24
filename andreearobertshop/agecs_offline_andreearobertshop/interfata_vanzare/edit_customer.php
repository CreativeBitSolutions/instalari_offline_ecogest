<?php
include 'database_connection.php';
 // unset($_SESSION['shoppingbasket']);
  //unset($_SESSION['custloggin']);

include "website_header.php";
printf("<script>location.href='edit_customer.php#centerOfPage'</script>");



?>

  <!-- Breadcrumbs-->

  <div class="container">
<section class="right">












 <?php
$cust_id=$_SESSION['cust_id'];
$csql = "SELECT * from $tabel_final_customers WHERE customer_id='$cust_id'";
$cstmt = $pdo->prepare($csql);  
$cstmt->execute(); 


while ($row = $cstmt->fetch(PDO::FETCH_ASSOC)){  
$customer_firstname=$row['customer_firstname'];
$customer_lastname=$row['customer_lastname'];
$customer_address_line1=$row['customer_address_line1'];
$customer_address_line2=$row['customer_address_line2'];
$customer_address_line3=$row['customer_address_line3'];
$customer_postcode=$row['customer_postcode'];
$customer_telephone_no=$row['customer_telephone_no'];
$customer_email_address=$row['customer_email_address'];
$customer_preferred_contact_method=$row['customer_preferred_contact_method'];
$customer_curr_pass=$row['customer_password'];



}

?>
<a name="centerOfPage"></a>
<br>
<h3 align="center"> Modifica Datele Contului</h3>
<hr>
<form method="POST" >

<div class="form-group">
<label for="exampleInputEmail1">Prenume</label>
<input class="form-control" id="exampleInputEmail1" type="text" name="customer_firstname" value="<?php echo $customer_firstname; ?>" />
</div>
<div class="form-group">
<label for="exampleInputEmail1">Nume</label>
<input class="form-control" id="exampleInputEmail1" type="text" name="customer_lastname" value="<?php echo $customer_lastname; ?>" />
</div>
<div class="form-group">
<label for="exampleInputEmail1">Linie de adresa 1</label>
<input class="form-control" id="exampleInputEmail1" type="text" name="customer_address_line1" value="<?php echo $customer_address_line1; ?>" />
</div>
<div class="form-group">
<label for="exampleInputEmail1">Linie de adresa 2</label>
<input class="form-control" id="exampleInputEmail1" type="text" name="customer_address_line2" value="<?php echo $customer_address_line2; ?>" />
</div>
<div class="form-group">
<label for="exampleInputEmail1">Linie de adresa 3</label>
<input class="form-control" id="exampleInputEmail1" type="text" name="customer_address_line3" value="<?php echo $customer_address_line3; ?>" />
</div>
<div class="form-group">
<label for="exampleInputEmail1">Cod postal</label>
<input class="form-control" id="exampleInputEmail1" type="text" name="customer_postcode" value="<?php echo $customer_postcode; ?>" />
</div>
<div class="form-group">
<label for="exampleInputEmail1">Numar de telefon</label>
<input class="form-control" id="exampleInputEmail1" type="text" name="customer_telephone_no" maxlength="10" value="<?php echo $customer_telephone_no; ?>" />
</div>
<div class="form-group">
<label for="exampleInputEmail1">Adresa de email</label>
<input class="form-control" id="exampleInputEmail1" type="text" name="customer_email_address" value="<?php echo $customer_email_address; ?>" />
</div>
<div class='form-group'>
<label >Metoda de contact preferata</label>
<select input class='form-control' name='customer_preferred_contact_method'>

<option value='Email'</option>Email
<option value='Telefonic'</option>Telefonic


</select>
</div>

  <input class="btn btn-primary btn-block" type="submit" name="edit_cust" value="Salvare modificari">


</form>





  <?php
  
$cust_id=$_SESSION['cust_id'];


if (isset($_POST['edit_cust']))
{
$customer_firstname=$_POST['customer_firstname'];
$customer_lastname=$_POST['customer_lastname'];
$customer_address_line1=$_POST['customer_address_line1'];
$customer_address_line2=$_POST['customer_address_line2'];
$customer_address_line3=$_POST['customer_address_line3'];
$customer_postcode=$_POST['customer_postcode'];
$customer_telephone_no=$_POST['customer_telephone_no'];
$customer_email_address=$_POST['customer_email_address'];
$customer_preferred_contact_method=$_POST['customer_preferred_contact_method'];




$edsql="update $tabel_final_customers set customer_firstname='$customer_firstname',customer_lastname='$customer_lastname',customer_address_line1='$customer_address_line1',customer_address_line2='$customer_address_line2',customer_address_line3='$customer_address_line3',customer_postcode='$customer_postcode',customer_telephone_no='$customer_telephone_no',customer_email_address='$customer_email_address',customer_preferred_contact_method='$customer_preferred_contact_method' where customer_id='$cust_id'";

try{
$pdo->exec($edsql) or die(print_r($pdo->errorInfo(), true));
// afiseaza un mesaj de succes 

echo "<h2 align='center'>Datele dumneavoastră s-au modificat cu succes!</h2>";

}catch(PDOException $e)
{
echo $edsql . "<br>" . $e->getMessage();
} 



}




?>

<br>
<h3 align="center"> Modificare parola</h3>
<hr>
<form method="POST" >

<div class="form-group">
<label for="exampleInputEmail1">Parola curenta</label>
<input class="form-control" id="exampleInputEmail1" type="password" name="curr_pass"/>
</div>
<div class="form-group">
<label for="exampleInputEmail1">Parola noua</label>
<input class="form-control" id="exampleInputEmail1" type="password" name="customer_pass"/>
</div>
<div class="form-group">
<label for="exampleInputEmail1">Confirmare parola noua</label>
<input class="form-control" id="exampleInputEmail1" type="password" name="customer_password_confirm" />
</div>

<input class="btn btn-primary btn-block" type="submit" name="change_pass_cust" value="Change password">
</form>

<?php if (isset($_POST['change_pass_cust']))
{
    
$curr_pass=md5($_POST['curr_pass']);
$customer_pass=md5($_POST['customer_pass']);
$customer_password_confirm=md5($_POST['customer_password_confirm']);

if($customer_curr_pass==$curr_pass)
{

if($_POST['customer_pass']==$_POST['customer_password_confirm']){

$psql="update $tabel_final_customers set customer_password='$customer_pass' where customer_id='$cust_id'";

try{
$pdo->exec($psql) or die(print_r($pdo->errorInfo(), true));
// afiseaza un mesaj de succes 

echo "Parola modificata";
}catch(PDOException $e)
{
echo $psql . "<br>" . $e->getMessage();
}


}

else{
echo "Noua parola nu se potriveste cu parola din campul de confirmare";

}
 


}
else {
    
    echo "Parola curenta gresita";

}
}
 ?>
</div><br>
</section>





<?php include 'website_footer.php'; ?>
