<?php
include 'database_connection.php';
$title = 'Creare administrator';
  
  include 'header.php';
    include 'check_priv.php';

?>

      <!-- Breadcrumbs-->
      <ol class="breadcrumb">
        <li class="breadcrumb-item">
          <a href="admin_list.php">Lista Administratorilor</a>
        </li>
        <li class="breadcrumb-item active">Adauga Administrator</li>
      </ol>
      <div class="container">          <style>label{font-weight:bold;}</style>

<?php
/*if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] == true) {*/

if (isset($_POST['add_admin'])) 
{
$sql ="INSERT INTO $tabel_final_admins(admin_email_address, admin_password, admin_firstname, admin_lastname,rank) 
VALUES (:admin_email_address, :admin_password,:admin_firstname, :admin_lastname,:admin_rank)";

$stmt = $pdo->prepare($sql);
              $pass = md5($_POST['admin_password']);
$criteria = 
[
'admin_email_address' => $_POST['admin_email_address'],
'admin_rank' => $_POST['rank'],

'admin_password' => $pass,
'admin_firstname' => $_POST['admin_firstname'],
'admin_lastname' => $_POST['admin_lastname'],
];

$stmt->execute($criteria);



printf("<script>location.href='admin_list.php#bottomOfPage'</script>");


}

echo '<section class="right">
<h4 align="center">Adauga Administrator</h4><hr>
<div class="card-body">
<form action="add_admin.php" method="POST">
<div class="form-group">
<label for="exampleInputEmail1">Adresa de Email:</label>
<input class="form-control" id="exampleInputEmail1" type="email" name="admin_email_address" aria-describedby="emailHelp" placeholder="Introduceti adresa de email">
</div>
<div class="form-group">
<label for="exampleInputPassword1">Parola:</label>
<input class="form-control" id="exampleInputPassword1"  maxlength="10" type="password" name="admin_password" placeholder="Parola">
</div>
<div class="form-group">
<label for="exampleInputPassword1">Prenume:</label>
<input class="form-control" id="exampleInputPassword1" type="text" name="admin_firstname" placeholder="Prenume">
</div>
<div class="form-group">
<label for="exampleInputPassword1">Nume :</label>
<input class="form-control" id="exampleInputPassword1" type="text" name="admin_lastname" placeholder="Nume">
</div>
<div class="form-group">

<label for="color">Functie:</label>
  <select name="rank" class="form-control" id="color">
  <option  value="operator">Operator</option>
    <option  value="ospatar">Ospatar</option>
        <option  value="bucatar">Bucatar</option>
        <option  value="barman">Barman</option>
  <option  value="administrator">Administrator</option>
  
</select>
  </div>

<input class="btn btn-primary btn-block" type="submit" name="add_admin" value="Creaza">
</form>
</div><br>
</section>';

?>
  </div>
    <!-- /.container-fluid-->
    <!-- /.content-wrapper-->
    		<?php include 'footer.php'; ?>
    <!-- Scroll to Top Button-->
    <a class="scroll-to-top rounded" href="#page-top">
      <i class="fa fa-angle-up"></i>
    </a>
    <!-- Logout Modal-->

 
  </div>
</body>

</html>
