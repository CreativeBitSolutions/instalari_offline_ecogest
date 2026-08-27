<?php

	include 'database_connection.php'; 	// unset($_SESSION['shoppingbasket']);
	  //unset($_SESSION['custloggin']);
	  $title = 'Recenzii';

	include "header.php";

?>

	
	 
				
<!-- about -->

		<style>
.table-responsive tr{font-size:15px;
}</style>

 <ol class="breadcrumb">
        
        <li class="breadcrumb-item active">Recenzii</li>
      </ol>


	 <div class="card mb-3">
        <div class="card-header">
          <i class="fa fa-pencil"></i> Lista Recenziilor</div>
        <div class="card-body">
          <div class="table-responsive">
<?php

$dsql = "SELECT $tabel_final_customers.customer_title,$tabel_final_customers.customer_firstname,$tabel_final_customers.customer_lastname,$tabel_final_recenzii.review,$tabel_final_recenzii.rating,$tabel_final_nomenclator.den_p,$tabel_final_recenzii.review_id from $tabel_final_recenzii INNER JOIN $tabel_final_customers on $tabel_final_recenzii.customer_id=$tabel_final_customers.customer_id INNER JOIN $tabel_final_nomenclator on $tabel_final_recenzii.cod_p=$tabel_final_nomenclator.cod_p";    
$dstmt = $pdo->prepare($dsql);  
$dstmt->execute();  

echo "<table class='table table-bordered' id='dataTable' width='100%' cellspacing='0'>"; 
echo" <thead><tr><th> Produs</th><th>Client</th><th>Scor</th><th colspan='3'>Action</th></tr></thead>"; 
while ($row = $dstmt->fetch(PDO::FETCH_ASSOC)){  

$rev_det=$row['review_id'];
$rev_del=$row['review_id'];
$rev_del=strval($rev_del);
$rev_del.='D';
$rev_approv=$row['review_id']; 
$rev_approv=strval($rev_approv);
$rev_approv.='AP';
$given_rating=$row['rating'];
              echo"<tr>";    
              echo"<td>".$row['den_p']."</td><td>".$row['customer_firstname']." ".$row['customer_lastname']."</td><td style='color:#FFD700'>";if($given_rating==1){
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
				     
				 } echo "</td><td><form method='post'><input class='btn btn-primary btn-block' type='submit' value='Detalii' name='$rev_det'></td><td><input class='btn btn-primary btn-block' type='submit' value='Sterge' name='$rev_del'><td></form></td></tr>";
				     
				 
				     
				     
				 }

if (isset($_POST[$rev_det])) {
								
	 $_SESSION['review_id']=$rev_det;

	 
			printf("<script>location.href='admin_review_details.php'</script>");
				}
if (isset($_POST[$rev_del])) {
								
$ddsql = "DELETE from $tabel_final_recenzii where review_id='$rev_det' ";    
$ddstmt = $pdo->prepare($ddsql);  
$ddstmt->execute();  

	 
			printf("<script>location.href='admin_review_details.php'</script>");
				}

				
			
				


echo" <tfoot><tr><th> Produs</th><th>Client</th><th>Scor</th><th colspan='3'>Action</th></tr></tfoot>"; 


echo "</table>";



?>
  </div>
        </div>
                <div class="card-footer small text-muted"> <?php
              $bookings="SELECT * from $tabel_final_recenzii ORDER BY review_date DESC LIMIT 1";
 $revs=$pdo->prepare($bookings);
	  $revs->execute();
	 					while ($row = $revs->fetch()) {
	 					    $latest_rev=$row['review_date'];
	 					}

	 
	  echo "Actualizat la: ".$latest_rev;?></div>

      </div>		   <style>th{width:20em;}</style>



   


<?php 
	require "footer.php";
?>