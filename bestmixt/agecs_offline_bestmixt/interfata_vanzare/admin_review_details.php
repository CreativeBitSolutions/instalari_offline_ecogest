<?php
include 'database_connection.php';
 	// unset($_SESSION['shoppingbasket']);
	  //unset($_SESSION['custloggin']);
		  $title = 'Detaliile recenziei';

	include "header.php";

	
	
	
	   if (!isset($_SESSION['review_id']))
	   {
		   
		   	printf("<script>location.href='admin_$tabel_final_recenzii.php#bottomOfPage'</script>");

	   }

	   
	   
?>
<style>
.table-responsive tr{font-size:15px;
}
th{width:20em;}
</style>
<ol class="breadcrumb">
      <li class="breadcrumb-item">
          <a href="admin_reviews.php">Recenzii</a>
        </li>
        <li class="breadcrumb-item active">Detaliile Recenziei</li>
      </ol></h3>
      <!-- Breadcrumbs-->
      

	 <div class="card mb-3">
        <div class="card-header">
          <i class="fa fa-table"></i> Recenzie</div>
        <div class="card-body">
          <div class="table-responsive">
<?php 
	 $rev_id=$_SESSION['review_id'];  

$revsql = "SELECT $tabel_final_nomenclator.den_p,$tabel_final_customers.customer_title,$tabel_final_customers.customer_firstname,$tabel_final_customers.customer_lastname,$tabel_final_recenzii.review,$tabel_final_recenzii.rating,$tabel_final_recenzii.review_date from $tabel_final_recenzii INNER JOIN $tabel_final_customers on $tabel_final_recenzii.customer_id=$tabel_final_customers.customer_id INNER JOIN $tabel_final_nomenclator on $tabel_final_recenzii.cod_p=$tabel_final_nomenclator.cod_p where $tabel_final_recenzii.review_id='$rev_id'";    
$revstmt = $pdo->prepare($revsql);  
$revstmt->execute(); 


while ($row = $revstmt->fetch(PDO::FETCH_ASSOC)){  
      
      $customer_title=$row['customer_title'];
      $customer_firstname=$row['customer_firstname'];
       $customer_lastname=$row['customer_lastname'];
$given_rating=$row['rating'];
$given_review=$row['review'];
$experience=$row['den_p'];
$review_date=$row['review_date'];

echo "<table style='font-size:20px' class='table table-borderless'> 
				<tr><th>Produs:</th>
				<td>$experience</td></tr>
				
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
				     
				 }echo "</td></tr>
					<tr><th>Data recenziei</th><td>
			 $review_date</td></tr>
	  </table><hr class='style6'>";

}


?> 
		 </div>
        </div>
      </div>

   

   <?php include 'footer.php'; ?>
