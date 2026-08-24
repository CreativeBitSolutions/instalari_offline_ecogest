<?php

	include 'database_connection.php';
  
	$title = 'Modifica admin';
  
  include 'header.php';
  include 'check_priv.php';

?>

      <!-- Breadcrumbs-->
      <ol class="breadcrumb">
        
                <li class="breadcrumb-item">
          <a href="admin_list.php">Administrators</a>
        </li>
        <li class="breadcrumb-item active">Modificare Cont</li>
      </ol>
      <div class="container">
        <section class="right">          <style>label{font-weight:bold;}</style>	


	<?php
	
	 $adm_id=$_SESSION['admin_id'];  

	if (isset($_POST['edit_admin'])) {

	
	
$stmt = $pdo->prepare("UPDATE $tabel_final_admins SET admin_email_address = :admin_email_address,admin_firstname = :admin_firstname,admin_lastname = :admin_lastname,rank=:admin_rank  WHERE admin_email_address= '$adm_id'");

$criteria = [
	'admin_email_address' => $_POST['admin_email_address'],
	'admin_firstname' => $_POST['admin_firstname'],
	'admin_lastname' => $_POST['admin_lastname'],
    'admin_rank' => $_POST['rank'],


];

$stmt->execute($criteria);


printf("<script>location.href='admin_list.php#bottomOfPage'</script>");	}
	else {
if (isset($_SESSION['adminloggedin']) && $_SESSION['adminloggedin'] == true) {
            ?>
            

            <?php
$dsql = "SELECT * FROM $tabel_final_admins where admin_email_address='$adm_id'";
$dstmt = $pdo->prepare($dsql);  
$dstmt->execute(); 

while ($row = $dstmt->fetch(PDO::FETCH_ASSOC)){  
        $admin_email_address=$row['admin_email_address'];
        $admin_firstname=$row['admin_firstname'];
        $admin_lastname=$row['admin_lastname'];
        $admin_rank=$row['rank'];
}
?>

	<h4 align="center">Modificare cont</h4><hr>

	<section class="right">
<div class="card-body">
<form action="edit_admin.php" method="POST">
	<div class="form-group">
<label for="exampleInputEmail1">Adresa de email:</label>
<input class="form-control" id="exampleInputEmail1" type="email" name="admin_email_address" aria-describedby="emailHelp" placeholder="Enter email" value="<?php echo $admin_email_address; ?>"/>
	</div>

	<div class="form-group">
<label for="exampleInputPassword1">Prenume:</label>
<input class="form-control" id="exampleInputPassword1" type="text" name="admin_firstname" placeholder="Firstname" value="<?php echo $admin_firstname; ?>">
	</div>
	<div class="form-group">
<label for="exampleInputPassword1">Nume:</label>
<input class="form-control" id="exampleInputPassword1" type="text" name="admin_lastname" placeholder="Lastname" value="<?php echo $admin_lastname; ?>">
	</div>
	<div class="form-group">

	<label for="color">Rank:</label>
	  <select name="rank" class="form-control" id="color">
  <option  value="operator">Operator</option>
    <option  value="ospatar">Ospatar</option>
        <option  value="bucatar">Bucatar</option>
        <option  value="barman">Barman</option>
  <option  value="administrator">Administrator</option>
  
  
</select>
  </div>
	<input class="btn btn-primary btn-block" type="submit" name="edit_admin" value="Save Changes">
</form>
</div>

<hr>
<h4 align="center"> Schimbare parola</h4>
<hr>
	<form method="POST" >

           <div class="form-group">
    <label for="exampleInputEmail1">Parola:</label>
	        <input class="form-control" id="exampleInputEmail1" type="password" name="admin_pass"/>
</div>
<div class="form-group">
    <label for="exampleInputEmail1">Confirma parola:</label>
	        <input class="form-control" id="exampleInputEmail1" type="password" name="admin_password_confirm" />
</div>
<br>
   
	  	  <input class="btn btn-primary btn-block" type="submit" name="change_pass_admin" value="Change Password">
</form>

	<?php	 if (isset($_POST['change_pass_admin']))
{
	 $adm_em=$_SESSION['adminloggedin'];  

$admin_pass=md5($_POST['admin_pass']);	
$admin_password_confirm=md5($_POST['admin_password_confirm']);	
	if($_POST['admin_pass']==$_POST['admin_password_confirm']){

	$psql="UPDATE $tabel_final_admins set admin_password='$admin_pass' where admin_id='$adm_id'";
	
	try{
$pdo->exec($psql) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 
$alerta="Parola a fost schimbata!";
 echo '<script language="javascript">';
  echo 'var myvar = ';echo json_encode($alerta).';';

echo 'alert';echo'('.myvar.');';
echo '</script>';
}catch(PDOException $e)
    {
    echo $psql . "<br>" . $e->getMessage();
    }
	
	
	}
	
	else{
echo "Passwords do not match";

	}
	 

	

}
 ?>


	</section>

	
	
	
	
	
        <?php
        }
        
        else {
	
printf("<script>location.href='admin_list.php#bottomOfPage'</script>");

         ?>

        
 ?>
        
        <?php
	  }

      }
  ?>

	  </div>
   </section>

   <?php include 'footer.php'; ?>
