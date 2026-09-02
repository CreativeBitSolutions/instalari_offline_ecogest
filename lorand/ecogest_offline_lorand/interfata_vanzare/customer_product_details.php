<?php
	include 'database_connection.php';
 	// unset($_SESSION['shoppingbasket']);
 //unset($_SESSION['custloggin']);
	
	include "website_header.php";
		printf("<script>location.href='customer_product_details.php#bottomOfPage'</script>");
if (!isset($_SESSION['cod_p']))

  {
	  
	  	printf("<script>location.href='customer_products.php#bottomOfPage'</script>");

  }
?>

  <!-- Breadcrumbs-->




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
    width: 24.5%;
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





</style>






<style>


/* Style the tab */
.tab {
overflow: hidden;
border: 1px solid #ccc;
background-color: black;
margin-top:2em;
}

/* Style the buttons inside the tab */
.tab button {
background-color: black;
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
background-color: #ff4500;
}

/* Create an active/current tablink class */
.tab button.active {
background-color: #ff4500;
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
  <button class="tablinks" onclick="openCity(event, 'Inf_prod')" id="defaultOpen">Informatii produs</button>
  <button class="tablinks" onclick="openCity(event, 'recenzii')">Recenzii</button>
  

  


</div>
<br>
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

echo "<div class='outer'>";
while ($row = $dstmt->fetch(PDO::FETCH_ASSOC)){  
$image_src=$row['image_source'];
$image_id=$row['image_id'];
$image_del=$row['image_id'];
$image_del=strval($image_del);
$image_del.='D';

 echo '
 
 <div >
  <a target="_blank" href="images/' . $image_src . '">
    <img width = "100px" height="100px" alt="Experience Photo" src="images/' . $image_src . '"/>
  </a>
</div>'; 


}
echo "</div>"
  ?></td> <td align="right"><h1><?php echo $curr_title; ?><br/><br/><?php if (!isset($_SESSION['cust_id']))
	   {
		   
		   	echo "<h3>Conectați-vă pentru a adauga produsul în coș!</h3>";


	   } else{$produs=$_SESSION['cod_p'];  

	       $psql22 = "SELECT $tabel_final_nomenclator.cod_p,$tabel_final_nomenclator.um,$tabel_final_nomenclator.den_p,$tabel_final_nomenclator.pret,$tabel_final_nomenclator.cota_tva,$tabel_final_nomenclator.proc_adaos,$tabel_final_stoc.cantitate from $tabel_final_nomenclator inner join $tabel_final_stoc on $tabel_final_stoc.cod_p=$tabel_final_nomenclator.cod_p where $tabel_final_nomenclator.cod_p='$produs';";    
$pstmt22 = $pdo->prepare($psql22);  
$pstmt22->execute(); 

while ($row = $pstmt22->fetch(PDO::FETCH_ASSOC)){ 
                      
							   $stoc=$row['cantitate'];             
			
}
if($stoc==0) {
		 echo "Nu exista niciun produs in stoc!";}
		 else{
		 echo "<p style='float:left;color:green'>$stoc de produse in stoc</p><form method='POST'><input class='btn btn-primary btn-block' type='submit' name='add_to_cart' style='width:150px;margin:0 auto' value='Adauga in cos'><input style='width:4em;margin-left:37em;color:green' class='form-control' type='number' name='cantitate' min='1' value='1'></form>";}}?>
		 
		  
<?php if (isset($_POST['add_to_cart']))

  {
      
	   
	  
      
      
	  $produs=$_SESSION['cod_p'];  

		$cust_id=$_SESSION['cust_id'];

	 $psql2 = "SELECT $tabel_final_nomenclator.cod_p,$tabel_final_nomenclator.um,$tabel_final_nomenclator.den_p,$tabel_final_nomenclator.pret,$tabel_final_nomenclator.cota_tva,$tabel_final_nomenclator.proc_adaos,$tabel_final_stoc.cantitate from $tabel_final_nomenclator inner join $tabel_final_stoc on $tabel_final_stoc.cod_p=$tabel_final_nomenclator.cod_p where $tabel_final_nomenclator.cod_p='$produs';";    
$pstmt2 = $pdo->prepare($psql2);  
$pstmt2->execute(); 

while ($row = $pstmt2->fetch(PDO::FETCH_ASSOC)){ 
                      $den_prod=$row['den_p'];
			                   $pret_achiz=$row['pret'];
							   $cota_tva=$row['cota_tva'];
							   $proc_ad=$row['proc_adaos'];
							   $stoc=$row['cantitate'];             
			$um=$row['um'];
}
	 
	 $cantitate=$_POST['cantitate'];
	 $v_ftva=$pret_achiz*$cantitate;
	 $adaos_unitar=$pret_achiz*$proc_ad/100;
	 $v_adaos=$adaos_unitar*$cantitate;
	 $pret_vanzare=$pret_achiz+$adaos_unitar;
	 $pret_vanzare_cu_tva=($pret_achiz+$adaos_unitar)*$cota_tva/100;

	 $valoare_vanzare=$pret_vanzare*$cantitate;
	 $tva_col=$valoare_vanzare*$cota_tva/100;
	 $valoare_vanzare_cu_tva=$valoare_vanzare+$tva_col;
	 
	 if($cantitate>$stoc) {
	      
		 echo "Stoc insuficient!";
		 echo " Stocul disponibil pentru ".$den_prod." este de ".$$tabel_final_stoc." ".$um;
		 }
		 
		 else{
$psql = "insert into $tabel_final_cosuri(cod_client,cod_p,cantitate,tva_col,pret_vanzare,valoare_vanzare,valoare_vanzare_cu_tva) values('$cust_id','$produs','$cantitate','$tva_col','$pret_vanzare','$valoare_vanzare','$valoare_vanzare_cu_tva');";   

	 
	try{
$pdo->exec($psql) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 
	  	printf("<script>location.href='cos.php#bottomOfPage'</script>");
		  
}catch(PDOException $e)
    {
    echo $sql . "<br>" . $e->getMessage();
    } 
 }
 }
 
 

 ?>
		 </h1></td></tr>
				<tr><th>Specificatii produs:</th><td>
					<?php echo $curr_info; ?></td>
			</tr>
					<tr><th>Pret(TVA inclus):</th><td>
			<?php echo $pret_vanzare_cu_tva; ?> RON</td></tr>
			
  
					
 </table>

 </div>
 		<div class="clearfix"></div><br>	

<?php 
	require "website_footer.php";
?>
 </div>
 



<div id="recenzii" class="tabcontent">
  <div class="container">

<style>hr.style6 {
	background-color: #fff;
	border-top: 2px dotted #8c8b8b;}
	.table-responsive tr{font-size:15px;
}
th{width:20em;}
}</style>
<?php 
$cod_p=$_SESSION['cod_p'];  

$revsql = "SELECT $tabel_final_customers.customer_title,$tabel_final_recenzii.review_date,$tabel_final_customers.customer_firstname,$tabel_final_customers.customer_lastname,$tabel_final_recenzii.review,$tabel_final_recenzii.rating from $tabel_final_recenzii INNER JOIN $tabel_final_customers on $tabel_final_recenzii.customer_id=$tabel_final_customers.customer_id INNER JOIN $tabel_final_nomenclator on $tabel_final_recenzii.cod_p=$tabel_final_nomenclator.cod_p where $tabel_final_nomenclator.cod_p='$cod_p'";
$revstmt = $pdo->prepare($revsql);  
$revstmt->execute(); 


while ($row = $revstmt->fetch(PDO::FETCH_ASSOC)){  
  
  $customer_title=$row['customer_title'];
  $customer_firstname=$row['customer_firstname'];
$customer_lastname=$row['customer_lastname'];
$given_rating=$row['rating'];
$given_review=$row['review'];
$rev_date=$row['review_date'];

echo "<table style='font-size:20px' class='table table-responsive'> 
				<tr><th>Customer:</th><td>
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
			
			}echo "</td></tr>
					<tr><th>Data recenziei:</th><td>
		$rev_date</td></tr>
 </table><hr class='style6'>";

}


?> 



<?php
 $cust_id=$_SESSION['cust_id'];
$csql = "SELECT * from $tabel_final_customers WHERE customer_id='$cust_id'";
$cstmt = $pdo->prepare($csql);  
$cstmt->execute(); 


while ($row = $cstmt->fetch(PDO::FETCH_ASSOC)){  
$customer_firstname=$row['customer_firstname'];
$customer_lastname=$row['customer_lastname'];
$customer_email_address=$row['customer_email_address'];


}


if(isset($_SESSION['cust_id'])){
echo " <h3 align='center'>Adaugati o recenzie</h3><hr>
 <form method='POST'>
  <div class='form-group'>
				<label for='exampleInputEmail1'>First Name:</label>
			<input style='width:100%' readonly class='form-control' id='exampleInputEmail1' type='text' value='$customer_firstname' name='customer_firstname'>
				</div>
			<div class='form-group'>
				<label for='exampleInputEmail1'>Last Name:</label>
			<input style='width:100%' readonly class='form-control' id='exampleInputEmail1' type='text' value='$customer_lastname' name='customer_firstname'>
				</div>
				<div class='form-group'>
						<label for='exampleInputPassword1'>Your Review:</label>
						<textarea class='form-control' id='exampleInputPassword1' type='text' name='review' placeholder='Scrieti aici recenzia dumneavoastra...'></textarea>
						</div>
<div class='form-group'>
					<label for='exampleInputPassword1'>Scor:</label>
				 <select style='width:100%' name='rating' class='form-control' id='color'>
					 <option value=''>Alegeti numarul de stele acordat</option>
					 <option style='color:#0071c5;' value='1'>&#9733;</option>
					 <option style='color:#40E0D0;' value='2'>&#9733&#9733; </option>
					 <option style='color:#008000;' value='3'>&#9733&#9733&#9733</option>					 
					 <option style='color:#FFD700;' value='4'>&#9733&#9733&#9733&#9733;</option>
					 <option style='color:#FF8C00;' value='5'>&#9733&#9733&#9733&#9733&#9733;</option>

						</select>
			 </div>
						<br>
		<input class='btn btn-primary btn-block' type='submit' name='post_review' value='Posteaza Recenzie'>
			</form>
";


if (isset($_POST['post_review'])) 
					{ 		
					$cust_id=$_SESSION['cust_id'];
					$cod_p=$_SESSION['cod_p'];  
					$review=$_POST['review'];
					$review_date=date("Y-m-d");
					$rating=$_POST['rating'];
$rsql = "INSERT INTO $tabel_final_recenzii(customer_id,cod_p,review_date,review,rating) values('$cust_id','$cod_p','$review_date','$review','$rating')";
$rstmt = $pdo->prepare($rsql);  
$rstmt->execute(); 

							
							echo 'Recenzie postata!';
							
							

						}



}else{echo "Va rugam conectati-va pentru a lasa o recenzie acestui produs.";}


	


?>

  		<div class="clearfix"></div>	

 </div><br>
 
<?php 
	require "website_footer.php";
?>
</div>









 
  




</div>
