<?php  include('session.php');
	$title = 'Lista note';
  include 'header.php';
	
?>
		<ol class="breadcrumb">
<li class="breadcrumb-item active">Documente</li>
</ol>
	 <div class="card mb-3">
        <div class="card-header">
          <i class="fa fa-table"></i> Lista note</div>
        <div class="card-body">
          <div class="table-responsive">
<?php
$sql = "SELECT $tabel_final_note.operator,$tabel_final_note.nrbon,$tabel_final_note.data_bon,$tabel_final_note.ora_bon,$tabel_final_admins.admin_firstname,$tabel_final_admins.admin_lastname,$tabel_final_note.status from $tabel_final_note inner join $tabel_final_admins on $tabel_final_note.operator = $tabel_final_admins.admin_id where status = 'F'";    
$stmt = $pdo->prepare($sql);  
$stmt->execute(); 
echo "<table class='table table-bordered'>"; 
echo" <thead><tr><th> Numar Nota</th><th>Data/Ora</th><th>Operator</th></tr></thead>"; 
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)){  
$storn_bon=$row['nrbon'];
//$cautbonsql = "SELECT * from $tabel_final_miscari where nr_nota = '$storn_bon' and fel_doc='BC'";    
//$cautbonstmt = $pdo->prepare($cautbonsql);  
//$cautbonstmt->execute(); 
//$nr_bonuri=$cautbonstmt->rowCount();

$list_storn=$row['nrbon'];
$list_storn=strval($list_storn);
$list_storn.='LS';
$mod_sume=$row['nrbon'];
$mod_sume=strval($mod_sume);
$mod_sume.='MS';
$firstname=$row["admin_firstname"];
$lastname=$row["admin_lastname"];
echo"<tr>";    
echo"<td>".$row['nrbon']."</td><td>".$row['data_bon']."    ".$row['ora_bon']."</td><td>".$firstname." ".$lastname. "</td>";

//if($nr_bonuri>0){while ($row = $cautbonstmt->fetch(PDO::FETCH_ASSOC)){  }echo "<form method='post'><input class='btn btn-primary btn-block' type='submit' value='Vezi Bon de Consum' name='$list_storn'>";}	

echo "<td>";
              
$ttest_sql = "SELECT sum($tabel_final_det_note.cantitate) as total_cant_bon from $tabel_final_det_note where $tabel_final_det_note.nr_bon='$storn_bon'";
$ttest_stm = $pdo->prepare($ttest_sql);  
$ttest_stm->execute(); 
while ($row = $ttest_stm->fetch(PDO::FETCH_ASSOC)){
      $cantitate_totala_bon=$row['total_cant_bon'];
}
$tttest_sql = "SELECT sum($tabel_final_det_stornari_rest.cantitate) as cantitate_total_stornata from $tabel_final_det_stornari_rest where $tabel_final_det_stornari_rest.nr_bon='$storn_bon'";
$tttest_stm = $pdo->prepare($tttest_sql);  
$tttest_stm->execute(); 
while ($row = $tttest_stm->fetch(PDO::FETCH_ASSOC)){
 $canti_total_stornata=$row['cantitate_total_stornata'];      
}
if($canti_total_stornata<$cantitate_totala_bon){
              echo "<form method='post'><input class='btn btn-primary btn-block' type='submit' value='Stornare Nota' name='$storn_bon'>";
}

else{
    
    echo "Nota stornata în totalitate";

}

echo "</td><td><form method='post'><input class='btn btn-primary btn-block' type='submit' value='Document Stornare' name='$list_storn'></td>";
  echo "<td><form method='post'><input class='btn btn-primary btn-block' type='submit' value='Detalii /Modifica sume' name='$mod_sume'></td>";


if (isset($_POST[$list_storn])) {

$_SESSION['nr_bon']=$storn_bon;

printf("<script>location.href='listeaza_stornare_nota.php'</script>");

}
if (isset($_POST[$mod_sume])) {

$_SESSION['nr_bon']=$storn_bon;

printf("<script>location.href='detalii_nota.php'</script>");

}
if (isset($_POST[$storn_bon])) {
							
							
							
$rrsql = "SELECT id_stornare from $tabel_final_stornari_rest where status='S' and nr_bon='$storn_bon' and operator='$adm_id' ";    
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
    
    
    
$sssql = "insert into $tabel_final_stornari_rest(operator,nr_bon) values('$adm_id','$storn_bon')";    
$ssstmt = $pdo->prepare($sssql);  
$ssstmt->execute(); 
  $crccom_sql = "SELECT max(id_stornare) as idstorn from $tabel_final_stornari_rest";    
$crccom_stmt = $pdo->prepare($crccom_sql);  
$crccom_stmt->execute(); 
while ($row = $crccom_stmt->fetch(PDO::FETCH_ASSOC)){ 
    $id_storn=$row['idstorn'];
}   
    
$rsql = "SELECT id_vanz,cod_p from $tabel_final_det_note where nr_bon='$storn_bon'";    
$rstmt = $pdo->prepare($rsql);  
$rstmt->execute(); 

while ($row = $rstmt->fetch(PDO::FETCH_ASSOC)){
    $c_p=$row['cod_p'];
    $id=$row['id_vanz'];
    	$ssql = "insert into $tabel_final_det_stornari_rest(id_vanzare,id_stornare,nr_bon,cod_p,cantitate) values('$id','$id_storn','$storn_bon','$c_p',0)";    
$sstmt = $pdo->prepare($ssql);  
$sstmt->execute(); 
    
}
   $_SESSION['nr_bon']=$storn_bon;
 $_SESSION['id_storn']=$id_storn;;  
}
			printf("<script>location.href='stornare_nota.php'</script>");
				}
				

}

echo" <thead><tr><th> Numar Nota</th><th>Data</th><th>Operator</th></tr></thead>"; 
echo "</table>";



?>
  </div>
        </div>
      
      </div>



   


<?php 
	require "footer.php";
?>