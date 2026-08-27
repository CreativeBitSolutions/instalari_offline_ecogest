<?php
include 'database_connection.php';
$title = 'Creare administrator';
  include 'header.php';
    include 'check_priv.php';
?>

      <!-- Breadcrumbs-->
      <ol class="breadcrumb">
        <li class="breadcrumb-item">
          <a href="tablete.php">Lista Tabletelor</a>
        </li>
        <li class="breadcrumb-item active">Adauga Tableta</li>
      </ol>
      <div class="container"><style>label{font-weight:bold;}</style>
<?php
/*if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] == true) {*/

if (isset($_POST['add_admin'])) 
{
    
    $pass = md5($_POST['admin_password']);
    
    $sql2 ="SELECT max(nr_tableta) as ultim_tab from $tabel_final_admins";
$stmt2 = $pdo->prepare($sql2);
$stmt2->execute();
						while ($row = $stmt2->fetch()) {
$ultim_tab=$row['ultim_tab']+1;

}
$sql ="INSERT INTO $tabel_final_admins(rank,admin_password,nr_tableta) values('client','$pass','$ultim_tab')";
$stmt = $pdo->prepare($sql);
$stmt->execute();

printf("<script>location.href='tablete.php#bottomOfPage'</script>");


}

echo '<section class="right">
<h4 align="center">Adauga Tableta</h4><hr>
<div class="card-body">
<form action="adauga_tableta.php" method="POST">
<div class="form-group">
<label for="exampleInputPassword1">Parola:</label>
<input class="form-control" id="exampleInputPassword1"  maxlength="10" type="password" name="admin_password" placeholder="Parola">
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
