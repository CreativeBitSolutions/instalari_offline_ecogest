<?php
include 'database_connection.php';
$title = 'Modificare categorie';
include 'header.php';

?>

<!-- Breadcrumbs-->
<ol class="breadcrumb">

<li class="breadcrumb-item">
<a href="admin_categories.php">Categorii</a>
</li>
<li class="breadcrumb-item active">Modificare Categorie</li>
</ol>
<div class="container">
<section class="right">

<?php
 $cat_id=$_SESSION['cat_id'];

if (isset($_POST['edit_category'])) {



$stmt = $pdo->prepare("UPDATE $tabel_final_categorii SET den_categ = :den_categ,desc_categ = :desc_categ WHERE id_categorie = '$cat_id'");

$criteria = [
'den_categ' => $_POST['den_categ'],
'desc_categ' => $_POST['desc_categ'],
];

$stmt->execute($criteria);


echo 'Categorie modificata';
}



$dsql = "SELECT * from $tabel_final_categorii where id_categorie='$cat_id'";
$dstmt = $pdo->prepare($dsql);
$dstmt->execute(); 

while ($row = $dstmt->fetch(PDO::FETCH_ASSOC)){
$den_categ=$row['den_categ'];
$desc_categ=$row['desc_categ'];

}
?>

<h4 align="center">Modificati categoria </h4><hr>
<style>label{font-weight:bold;}</style><form action="edit_category.php" method="POST" enctype="multipart/form-data">

<div class="form-group">
<label for="exampleInputEmail1">Category Name:</label>
<input class="form-control" id="exampleInputEmail1" type="text" name="den_categ" value="<?php echo $den_categ; ?>" />
</div>
<div class="form-group">
<label for="exampleInputPassword1">Category Description:</label>
<textarea input class="form-control" id="exampleInputPassword1" type="text" name="desc_categ"><?php echo $desc_categ; ?></textarea>
</div>


<input class="btn btn-primary btn-block" type="submit" name="edit_category" value="Salvati Categoria">
</form>



</div>
 </section>

 <?php include 'footer.php'; ?>
