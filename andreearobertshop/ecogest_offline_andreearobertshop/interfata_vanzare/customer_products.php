<?php session_start();
include 'database_connection.php';
include "website_header.php";
if (!isset($_GET['categoryId']))
{
printf("<script>location.href='customer_bookings.php'</script>");
}
$_SESSION['cat_id']=$_GET['categoryId'];
$c_id=$_SESSION['cat_id'];
$catsql = "SELECT * from $tabel_final_categorii where id_categorie='$c_id'";    
$catstmt = $pdo->prepare($catsql);  
$catstmt->execute(); while ($row = $catstmt->fetch()) {
$cat_name=$row['den_categ'];
$cat_desc=$row['desc_categ'];
}
?>



  <link href="card.css" rel="stylesheet">

<!-- about -->  

 <div class="article">
     

     <div style='text-align:center;margin:0 auto; margin-top:3em;color:black'><h1><?php echo $cat_name ; ?><br><h3 style='font-size:1.2em;margin-top:1em;'><?php echo $cat_desc ; ?></h3></h1><hr style='border-top: 3px double #8c8b8b;'></div>

<style>.form-control {margin-right:20em}</style>

<h5 align="center"><form method="post" class="form-inline my-2 my-lg-0 mr-lg-2">Cauta produs:
          <div class="input-group">
            <input class="form-control" type="text" name="search" placeholder="Search for...">
            <span class="input-group-btn">
              <button class="btn btn-primary" type="submit">
                <i class="fa fa-search"></i>
              </button>
            </span>
          </div>
        </form>
</h5>

     </div>
 <div class="card-columns">

<?php
require_once 'database_connection.php';



if (isset($_POST['search'])) {
$searchq = $_POST['search'];
$searchq = preg_replace("#[^0-9a-z]#i","",$searchq);
 
$search_query="SELECT * from $tabel_final_nomenclator WHERE den_p LIKE '%$searchq%' or desc_prod LiKE '%$searchq%'";
$query=$pdo->prepare($search_query);
$query->execute();
$count=$query->rowCount();
   

 if($count == 0) {
         
         
         
     
  echo "<br><h3 align='center'>Nu s-a gasit niciun produs!</h3>";
 } else {
  while($row = $query->fetch(PDO::FETCH_ASSOC)) {
   $cod_p=$row['cod_p'];
$prod_det=$row['cod_p'];
$prod_det=strval($prod_det);
$prod_det.='DT';
  
   echo '            

<div class="card mb-3">

                <img width="300px" height="250px" class="card-img-top img-fluid w-100" src="images/' . $row['imagine'] . '" alt="">
              
              <div class="card-body">
                <h6 class="card-title mb-1">'.$row['den_p'].'</h6>
                <p class="card-text small">'.$row['desc_prod'].'
                </p>';echo "<div class='card-footer small text-muted'><form method='post'><input class='btn btn-primary btn-block' type='submit' value='Detalii' name='$prod_det'></form></div></div>";
echo '
              </div>
'; 


      if(isset($_POST[$prod_det])){


$_SESSION['cod_p']=$cod_p;
printf("<script>location.href='customer_product_details.php#bottomOfPage'</script>");



}
      
  }
  
   
  
 }
}



?>
</div>

<a name="bottomOfPage"></a>


<?php

if (!isset($_POST['search'])) {

 echo "<div class='card-columns' style='display:block'>";
}

else {
 echo "<div class='card-columns' style='display:none'>";

}
$_SESSION['cat_id']=$_GET['categoryId'];
$categ_id=$_SESSION['cat_id'];

$sql = "SELECT * from $tabel_final_nomenclator where cod_categ='$categ_id'";    
$stmt = $pdo->prepare($sql);  
$stmt->execute(); 



while ($row = $stmt->fetch()) {
$cod_p=$row['cod_p'];
$prod_det=$row['cod_p'];
$prod_det=strval($prod_det);
$prod_det.='D';
echo '            

<div class="card mb-3">

                <img width="300px" height="250px" class="card-img-top img-fluid w-100" src="images/' . $row['imagine'] . '" alt="">
              
              <div class="card-body">
                <h6 class="card-title mb-1">'.$row['den_p'].'</h6>
                <p class="card-text small">'.$row['desc_prod'].'
                </p>';echo "<div class='card-footer small text-muted'><form method='post'><input class='btn btn-primary btn-block' type='submit' value='Detalii' name='$prod_det'></form></div></div>";
echo '
              </div>
';




if(isset($_POST[$prod_det])){


$_SESSION['cod_p']=$cod_p;
printf("<script>location.href='customer_product_details.php'</script>");



}
}


?>
                     </div>
            </div>
</div><br>

<!-- /about -->



 

<?php 
require "website_footer.php";
?>