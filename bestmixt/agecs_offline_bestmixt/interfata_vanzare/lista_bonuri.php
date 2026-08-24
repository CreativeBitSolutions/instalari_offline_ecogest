<?php  include('session.php');
	$title = 'Lista Bonuri';
  include 'header.php';
	
?>

	
	   
          
			
				

		<ol class="breadcrumb">
<li class="breadcrumb-item active">Documente</li>
</ol>

	 <div class="card mb-3">
        <div class="card-header">
          <i class="fa fa-table"></i> Lista Bonuri</div>
        <div class="card-body">
          <div class="table-responsive">
<?php
$sql = "SELECT  nrbon,data_bon,ora_bon,operator,status FROM $tabel_final_bonuri where status = 'F'";    
$stmt = $pdo->prepare($sql);  
$stmt->execute(); 

echo "<table class='table table-bordered'>"; 
echo" <thead><tr><th> Numar Bon</th><th>Data/Ora</th><th>Operator</th></tr></thead>"; 
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)){  
$storn_bon=$row['nrbon'];
              echo"<tr>";    
              echo"<td>".$row['nrbon']."</td><td>".$row['data_bon']."    ".$row['ora_bon']."</td><td>".$row['operator']."</td><td>";
              
$ttest_sql = "SELECT sum($tabel_final_det_bonuri.cantitate) as total_cant_bon from $tabel_final_det_bonuri where $tabel_final_det_bonuri.nr_bon='$storn_bon'";
$ttest_stm = $pdo->prepare($ttest_sql);  
$ttest_stm->execute(); 
while ($row = $ttest_stm->fetch(PDO::FETCH_ASSOC)){
      $cantitate_totala_bon=$row['total_cant_bon'];

}
$tttest_sql = "SELECT sum($tabel_final_det_stornari.cantitate) as cantitate_total_stornata from $tabel_final_det_stornari where $tabel_final_det_stornari.nr_bon='$storn_bon'";
$tttest_stm = $pdo->prepare($tttest_sql);  
$tttest_stm->execute(); 
while ($row = $tttest_stm->fetch(PDO::FETCH_ASSOC)){
 $canti_total_stornata=$row['cantitate_total_stornata'];  
    
}
if($canti_total_stornata<$cantitate_totala_bon){
              echo "<form method='post'><input class='btn btn-primary btn-block' type='submit' value='Stornare Bon' name='$storn_bon'>";
}

else{
    
    echo "Bon stornat în totalitate";

}

echo "</td>";
  


if (isset($_POST[$storn_bon])) {
							
							
							
$rrsql = "SELECT id_stornare from stornari where status='S' and nr_bon='$storn_bon' and operator='$adm_id' ";    
$rrstmt = $pdo->prepare($rrsql);  
$rrstmt->execute(); 
$count=$rrstmt->rowCount();
while ($row = $rrstmt->fetch(PDO::FETCH_ASSOC)){

    $id_storn=$row['id_stornare'];
    
}
if($count>=1){
$_SESSION['nr_bon']=$storn_bon;
$_SESSION['id_storn']=$id_storn;
    
}
else{
    
    
    
$sssql = "insert into $tabel_final_stornari(operator,nr_bon) values('$adm_id','$storn_bon')";    
$ssstmt = $pdo->prepare($sssql);  
$ssstmt->execute(); 
  $crccom_sql = "SELECT max(id_stornare) as idstorn FROM stornari";    
$crccom_stmt = $pdo->prepare($crccom_sql);  
$crccom_stmt->execute(); 
while ($row = $crccom_stmt->fetch(PDO::FETCH_ASSOC)){ 
    $id_storn=$row['idstorn'];
}   
    
$rsql = "SELECT id_vanz,cod_p from $tabel_final_det_bonuri where nr_bon='$storn_bon'";    
$rstmt = $pdo->prepare($rsql);  
$rstmt->execute(); 

while ($row = $rstmt->fetch(PDO::FETCH_ASSOC)){
    $c_p=$row['cod_p'];
    $id=$row['id_vanz'];
    	$ssql = "insert into $tabel_final_det_stornari(id_vanzare,id_stornare,nr_bon,cod_p,cantitate) values('$id','$id_storn','$storn_bon','$c_p',0)";    
$sstmt = $pdo->prepare($ssql);  
$sstmt->execute(); 
    
}
   $_SESSION['nr_bon']=$storn_bon;
 $_SESSION['id_storn']=$id_storn;;  
}
			printf("<script>location.href='stornare_bon.php'</script>");
				}
				

}

echo" <thead><tr><th> Numar Bon</th><th>Data</th><th>Operator</th></tr></thead>"; 
echo "</table>";



?>
  </div>
        </div>
      
      </div>



   


<?php 
	require "footer.php";
?>