<?php
	include "database_connection.php";
		include "website_header.php";

?>
<!--Author: W3layouts
Author URL: http://w3layouts.com
License: Creative Commons Attribution 3.0 Unported
License URL: http://creativecommons.org/licenses/by/3.0/
-->
<!DOCTYPE HTML>
	   	     <link href="card.css" rel="stylesheet">

<section class="service-agileinfo" id="service">
	<div class="container">
		<h3 class="text-center" data-aos="zoom-in">Cele mai comandate produse</h3>
		<div class="container">
		<div style="-webkit-column-count:5;column-count:5;" class='card-columns'>
	<?php	$dsql = "SELECT $tabel_final_nomenclator.imagine,$tabel_final_nomenclator.pret,$tabel_final_nomenclator.den_p,$tabel_final_stoc.cantitate,$tabel_final_nomenclator.desc_prod,$tabel_final_nomenclator.cod_p,count($tabel_final_comenzi_detalii.cod_p) AS nr_comenzi FROM $tabel_final_comenzi_detalii INNER JOIN $tabel_final_nomenclator on $tabel_final_comenzi_detalii.cod_p=$tabel_final_nomenclator.cod_p inner join $tabel_final_stoc on $tabel_final_nomenclator.cod_p=$tabel_final_stoc.cod_p GROUP BY $tabel_final_nomenclator.den_p ORDER BY nr_comenzi DESC LIMIT 5";    

$dstmt = $pdo->prepare($dsql);  
$dstmt->execute();  


while ($row = $dstmt->fetch()) {
      	$cod_p=$row['cod_p'];
	$prod_det=$row['cod_p'];
	$prod_det=strval($prod_det);
$prod_det.='D';
    
    echo '<div class="card mb-3" style="height:400px" style="float:left";>

                <img width="300px" height="250px" class="card-img-top img-fluid w-100" src="images/' . $row['imagine'] . '" alt="">
              
              <div class="card-body">
                <h6 class="card-title mb-1">'.$row['den_p'].'</h6>
                <p class="card-text small">'.$row['desc_prod'].'
                </p>';	echo "<div class='card-footer small text-muted'><form method='post'><input class='btn btn-primary btn-block' type='submit' value='Detalii' name='$prod_det'></form></div></div>";
echo '
              </div> 
								';
					   	if(isset($_POST[$prod_det])){

								
							$_SESSION['cod_p']=$cod_p;
							printf("<script>location.href='customer_product_details.php#bottomOfPage'</script>");

							
	
}	

}
	?>
            </div>
		<div class="clearfix"></div>
	</div>
</section>
<!-- /services -->
<?php 
	require "website_footer.php";
?>