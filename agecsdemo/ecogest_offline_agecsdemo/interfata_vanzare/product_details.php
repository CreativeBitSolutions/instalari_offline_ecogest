<?php

include 'database_connection.php';
$title = 'Detalii produs';
include 'header.php';

?>

<!-- Breadcrumbs-->
<ol class="breadcrumb">
<li class="breadcrumb-item">
<a href="index.php">Panou de control</a>
</li>
<li class="breadcrumb-item">
<a href="nomenclator.php">Nomenclator</a>
</li>
<li class="breadcrumb-item active">Detalii produs</li>
</ol>


<style>
.outer {
    width: 15em;
    height: 5.5em;
    white-space: nowrap;
    position: relative;
    overflow-x: scroll;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
}

.outer div {
    width: 35%;
    background-color: #eee;
    float: none;
    height: 90%;
    margin: 0 0.25%;
    display: inline-block;
    zoom: 1;
    border: 1px solid #ccc;
   
}
.outer div:hover {
       border: 1px solid #777;

   
}



/* Style the tab */
.tab {
overflow: hidden;
border: 1px solid #ccc;
background-color: #343a40;
margin-top:2em;
}

/* Style the buttons inside the tab */
.tab button {
background-color: #343a40;
float: left;
border: none;
outline: none;
cursor: pointer;
padding: 14px 16px;
transition: 0.3s;
font-size: 17px;
color:white;
}

/* Change background color of buttons on hover */
.tab button:hover {
background-color: #007bff;
}

/* Create an active/current tablink class */
.tab button.active {
background-color: #007bff;
}

/* Style the tab content */
.tabcontent {
display: none;
padding: 6px 12px;
border: 1px solid #ccc;
border-top: none;
}


</style>

<div class="container">

<div class="tab">
<button class="tablinks" onclick="openCity(event, 'Inf_prod')" id="defaultOpen">Detalii produs</button>
<button class="tablinks" onclick="openCity(event, 'recenzii')">Recenzii</button>
<button class="tablinks" onclick="openCity(event, 'Galerie')">Galerie</button>




</div>

</div>
<script>
function openCity(evt, cityName) {
var i, tabcontent, tablinks;
tabcontent = document.getElementsByClassName("tabcontent");
for (i = 0; i < tabcontent.length; i++) {
tabcontent[i].style.display = "none";
}
tablinks = document.getElementsByClassName("tablinks");
for (i = 0; i < tablinks.length; i++) {
tablinks[i].className = tablinks[i].className.replace(" active", "");
}
document.getElementById(cityName).style.display = "block";
evt.currentTarget.className += " active";
}


</script>

<?php

$cod_p=$_SESSION['cod_p'];  
$sql = "SELECT * from $tabel_final_nomenclator where cod_p='$cod_p'";    
$dstmt = $pdo->prepare($sql);  
$dstmt->execute(); 

while ($row = $dstmt->fetch(PDO::FETCH_ASSOC)){  
$curr_title=$row['den_p'];
$curr_image=$row['imagine'];
$curr_info=$row['desc_prod'];
$curr_price=$row['pret'];
$cota_tva=$row['cota_tva'];
$proc_ad=$row['proc_adaos'];
$adaos_unitar=$curr_price*$proc_ad/100;
	 $pret_vanzare=$curr_price+$adaos_unitar;
	 $pret_vanzare_cu_tva=$pret_vanzare+$pret_vanzare*$cota_tva/100;


}
?>
		
		
		<div id="Inf_prod" class="tabcontent" style="display:block">
	 <div class="container">
			<h1 align="right"> </h1>
<a id='bottomOfPage'></a>

<style>.form-control{margin:0 auto; margin-top:10px; width:60px}</style>
<table style="font-size:20px" class='table table-borderless'> 
<tr>
<td> <?php echo '<img width = "270px" height="180px" alt="Experience Photo" src="images/' . $curr_image . '"/>'; ?><?php 
$exp_id=$_SESSION['exp_id'];  
$dsql = "SELECT * from $tabel_final_extra_images where cod_p='$cod_p'";
$dstmt = $pdo->prepare($dsql);  
$dstmt->execute(); 

echo '
 
 <div class="outer">';
while ($row = $dstmt->fetch(PDO::FETCH_ASSOC)){  
$image_src=$row['image_source'];
$image_id=$row['image_id'];
$image_del=$row['image_id'];
	$image_del=strval($image_del);
$image_del.='DE';

 echo '
 
 <div>
  <a target="_blank" href="images/' . $image_src . '">
    <img width = "100px" height="100px" alt="Experience Photo" src="images/' . $image_src . '"/>
  </a>
</div>'; 


}
echo '
 
 </div>';
  ?></td> <td align="right"><h3><?php echo $curr_title; ?></h3></tr>
				<tr><th>Specificatii produs:</th><td>
					<?php echo $curr_info; ?></td>
			</tr>
					<tr><th>Pret(TVA inclus):</th><td>
			<?php echo $pret_vanzare_cu_tva; ?> RON</td></tr>
			
  
					
 </table>
</div>
<div class="clearfix"></div>

<?php 
require "footer.php";
?>
</div>




<div id="recenzii" class="tabcontent">
<div class="container">
<br>
<?php 
 $cod_p=$_SESSION['cod_p'];

$revsql = "SELECT $tabel_final_customers.customer_title,$tabel_final_customers.customer_firstname,$tabel_final_customers.customer_lastname,$tabel_final_recenzii.review,$tabel_final_recenzii.rating from $tabel_final_recenzii INNER JOIN $tabel_final_customers on $tabel_final_recenzii.customer_id=$tabel_final_customers.customer_id INNER JOIN $tabel_final_nomenclator on $tabel_final_recenzii.cod_p=$tabel_final_nomenclator.cod_p where $tabel_final_nomenclator.cod_p='$cod_p'";
$revstmt = $pdo->prepare($revsql);
$revstmt->execute(); 


while ($row = $revstmt->fetch(PDO::FETCH_ASSOC)){

$customer_title=$row['customer_title'];
$customer_firstname=$row['customer_firstname'];
 $customer_lastname=$row['customer_lastname'];
$given_rating=$row['rating'];
$given_review=$row['review'];

echo "<table class='table table-borderless'> 
<tr><th>Client:</th><td>
$customer_title $customer_firstname $customer_lastname</td>
</tr>
<tr><th>Recenzie:</th><td>
 $given_review</td></tr>
<tr><th>Scor:</th><td style='color:#FFD700'>";
 if($given_rating==1){
 echo "&#9733";
 
 }
 if($given_rating==2){
 echo "&#9733&#9733";
 
 }
 if($given_rating==3){
 echo "&#9733&#9733&#9733";
 
 }
 if($given_rating==4){
 echo "&#9733&#9733&#9733&#9733";
 
 }
 if($given_rating==5){
 echo "&#9733&#9733&#9733&#9733&#9733";
 
 }echo "</td></tr></table><hr class='style6'>";

}


?>

 <div class="clearfix"></div>

 </div>
 
<?php 
require "footer.php";
?>


 



<div id="Galerie" class="tabcontent">
<div class="container">
<br>
<style>figure{
float:left;
display:inline-block;
margin-left:0.5em;
}</style><div style="display:block;">
    <style>label{font-weight:bold;}</style>

<label for='exampleInputEmail1'>Incarcati o imagine:</label>
 <object style='overflow:hidden;'width='100%' height='40px' data='upload.php'></object>
<hr>
<?php 
 $cod_p=$_SESSION['cod_p'];
$dsql = "SELECT * from $tabel_final_extra_images where cod_p='$cod_p'";
$dstmt = $pdo->prepare($dsql);
$dstmt->execute(); 


while ($row = $dstmt->fetch(PDO::FETCH_ASSOC)){
$image_src=$row['image_source'];
$image_id=$row['image_id'];
$image_del=$row['image_id'];
	$image_del=strval($image_del);
$image_del.='D';

 echo '
<figure><img width = "266px" height="150px" alt="Poza produsului" src="images/' . $image_src . '"/><figcaption>'; if($count == 1) { echo "<form method='post'><input class='btn btn-primary btn-block' type='submit' value='Remove Image' name='$image_del'></form>";} echo "</figcaption></figure>"; 
 
if (isset($_POST[$image_del])) {

$sql="DELETE FROM $tabel_final_extra_images WHERE image_id ='$image_id';";
$pdo->exec($sql) or die(print_r($pdo->errorInfo(), true));

printf("<script>window.top.location.reload();</script>");


}
}
?>
</div>
<style>.radio{width:50em;}</style>
<form method="POST">

<?php if($count == 1) {echo "
<div class='form-group'>";
$dir="images/";
$dirPath = dir('images');
$imgArray = array();
while (($file = $dirPath->read()) !== false)
{
if ((substr($file, -3)=="gif") || (substr($file, -3)=="jpg") || (substr($file, -3)=="png"))
{
 $imgArray[ ] = trim($file);
}
}
echo "<table class='table table-borderless'>"; 

$dirPath->close();
sort($imgArray);
$c = count($imgArray);
for($i=0; $i<$c; $i++)
{
 echo '<tr><td><img width = "100px" height="50px" alt="Experience Photo" src="images/' . $imgArray[$i] . '"/></td>';
 echo "<td class='radio'><input type='radio' name='image' value=\"" . $imgArray[$i] . "\">". "\n</td></tr>
";

}
echo "</table></div></details>";


 if (isset($_POST['add_img'])) 
{ 
 $cod_p=$_SESSION['cod_p'];
 $img_source=$_POST['image'];


$galsql = "INSERT INTO $tabel_final_extra_images(image_source,cod_p) values('$img_source','$cod_p')";
$galstmt = $pdo->prepare($galsql);
$galstmt->execute(); 

echo "Imagine adaugata in galerie";



} }
?>
<?php if($count == 1) { echo "<input class='btn btn-primary btn-block' type='submit' name='add_img' value='Adauga in galerie'>
</form>
<br>

";} ?>


 <div class="clearfix"></div>

 </div>
 
<?php 
require "footer.php";
?>


