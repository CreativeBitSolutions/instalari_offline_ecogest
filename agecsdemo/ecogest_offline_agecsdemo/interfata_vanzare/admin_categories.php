<?php

include 'database_connection.php';

$title = 'Categorii';

include 'header.php';

?>
<!-- Breadcrumbs-->
<ol class="breadcrumb">

<li class="breadcrumb-item active">Categorii</li>
</ol>

 

<!-- Example DataTables Card-->
<div class="card mb-3">
<div class="card-header">
<i class="fa fa-tags"></i> Lista categoriilor</div>
<div class="card-body">
<div class="table-responsive">
<?php

echo '<section class="right">';
$sql="SELECT * from $tabel_final_categorii";
$stmt = $pdo->prepare($sql);

$stmt->execute();

echo '<table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">';
echo '<thead>
<tr>
<th>Denumire categorie</th>
<th>Descriere categorie</th>
<th colspan="2">Actiune</th>
</tr>
</thead>';
echo '<tfoot>
<tr>
<th>Denumire categorie</th>
<th>Descriere categorie</th>
<th colspan="2">Actiune</th>
</tr>
</tfoot>';
echo '<tbody>';

while ($row = $stmt->fetch()) {
$cat_id=$row['id_categorie'];
$cat_del=$row['id_categorie'];
$cat_del=strval($cat_del);
$cat_del.='D';

echo '<tr>
<td>' . $row['den_categ'] . '</td>
<td>' . $row['desc_categ'] . '</td>';
echo "<td><form method='post'>";

$administrator="administrator";
   include('database_connection.php');
$admin_em=$_SESSION['admin_id'];

				
$sql="SELECT * FROM $tabel_final_admins WHERE admin_id='$admin_em' and rank='$administrator'";	  

$zsql=$pdo->prepare($sql);

	  $zsql->execute();
	  //count the returned number of rows

	  $count=$zsql->rowCount();
	 // if 1 row is found show the connected user a full navigation bar with the option to add categories,experiences and add administrators
	 if($count == 1) {echo "<input class='btn btn-primary btn-block' type='submit' value='Modifica' name='$cat_id'></td><td><input class='btn btn-primary btn-block' type='submit' value='Sterge' name='$cat_del'></form></td><tr>"; }

if(isset($_POST[$cat_id])){


$_SESSION['cat_id']=$cat_id;
printf("<script>location.href='edit_category.php'</script>");



}
if (isset($_POST[$cat_del])) {

$_SESSION['cat_id']=$cat_id;



printf("<script>location.href='delete_category.php'</script>");


}





}
echo '</tbody>';
echo '</table>';

echo '</section>';

?>
</div>
</div>
</div>


<?php include 'footer.php'; ?>