<?php session_start();
	include 'database_connection.php';

	
	include "website_header.php";
				

?>

	
	   
<!-- about -->
	  <a name="bottomOfPage"></a>
	  
	<style>
	td{width:75%;}
	hr.style6 {
	background-color: #fff;
	border-top: 2px dotted #8c8b8b;
	
}.table-responsive tr{font-size:15px;
}
th{width:20em;}
	</style>
	
	
	 <div class="article">
<h3 style="margin:2em" align="center">Recenziile clientilor</h3>
<div class="container">
<?php 

$revsql = "SELECT $tabel_final_customers.customer_title,$tabel_final_recenzii.review_date,$tabel_final_customers.customer_firstname,$tabel_final_nomenclator.den_p,$tabel_final_customers.customer_lastname,$tabel_final_recenzii.review,$tabel_final_recenzii.rating from $tabel_final_recenzii INNER JOIN $tabel_final_customers on $tabel_final_recenzii.customer_id=$tabel_final_customers.customer_id INNER JOIN $tabel_final_nomenclator on $tabel_final_recenzii.cod_p=$tabel_final_nomenclator.cod_p ORDER BY $tabel_final_recenzii.review_date";    
$revstmt = $pdo->prepare($revsql);  
$revstmt->execute(); 


while ($row = $revstmt->fetch(PDO::FETCH_ASSOC)){  

$produs=$row['den_p'];
$customer_title=$row['customer_title'];
$customer_firstname=$row['customer_firstname'];
$customer_lastname=$row['customer_lastname'];
$given_rating=$row['rating'];
$given_review=$row['review'];
$rev_date=$row['review_date'];

echo "<table style='font-size:20px' class='table table-responsive'> 
	<tr><th>Experience reviewed:</th><td>
					$produs</td>
			</tr>
				<tr><th>Customer:</th><td>
					 $customer_title $customer_firstname $customer_lastname</td>
			</tr>
					<tr><th>Review:</th><td>
			 $given_review</td></tr>
				<tr><th>Rating:</th><td style='color:#FFD700'>";
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
					<tr><th>Review date:</th><td>
			 $rev_date</td></tr>
	  </table><hr class='style6'>";

}


?> 		<div class="clearfix"></div>
</div>
</div><br>
<!-- /about -->

<?php 
	require "website_footer.php";
?>






