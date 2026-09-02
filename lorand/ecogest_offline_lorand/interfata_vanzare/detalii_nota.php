<?php

include 'database_connection.php';

$title = 'Detalii nota';

include 'header.php';

?>
<!-- Breadcrumbs-->


 

<!-- Example DataTables Card-->
<div class="card mb-3">
<div class="card-header">
<i class="fa fa-tags"></i> Nota <?php  $nr_bon=$_SESSION['nr_bon'];
echo $nr_bon;
   
   
   if(isset($_POST['salv_sume']))
   {
       
       $numerar_nou=$_POST['numerar'];
       $card_nou=$_POST['card'];
       $tichete_nou=$_POST['tichete'];
       $rest_nou=$_POST['rest'];
           $update_stoc_sterg_pf_sql = "update $tabel_final_note set numerar='$numerar_nou',card='$card_nou',tichete='$tichete_nou',rest='$rest_nou' where nrbon='$nr_bon'; "; 
    $update_stoc_sterg_pf_stmt = $pdo->prepare($update_stoc_sterg_pf_sql);  
$update_stoc_sterg_pf_stmt->execute(); 

           echo '<script language="javascript">';
echo 'alert("Sumele au fost modificate")';
echo '</script>';
printf("<script>location.href='detalii_nota.php'</script>");
   }
   
   ?></div>
<div class="card-body">
<div class="table-responsive">
    <table class='table'>
<?php



$dsql = "SELECT * from $tabel_final_date_firma";    
$dstmt = $pdo->prepare($dsql);  
$dstmt->execute(); 
while ($row = $dstmt->fetch(PDO::FETCH_ASSOC)){ 
                      $den_ent=$row['den_ent'];
			             $sediu=$row['sediu']; 
						 $cod_fiscal_ent=$row['cod_fiscal']; 
$serie_c_m=$row['serie_casa_marcat'];
			

}



$cif_client=$_SESSION['cif_client'];
$nr_bon=$_SESSION['nr_bon'];

$bon_sql = "SELECT * from $tabel_final_note where nrbon='$nr_bon'";    
$bon_stmt = $pdo->prepare($bon_sql);  
$bon_stmt->execute(); 
while ($row = $bon_stmt->fetch(PDO::FETCH_ASSOC)){
    $operator=$row['operator'];
    $data_bon=date("d-m-Y", strtotime($row['data_bon']));
    $ora_bon=$row['ora_bon'];
    $tva_colectata=$row['tva_colectata'];
    $numerar=$row['numerar'];
    $card=$row['card'];
        $tichete=$row['tichete'];
$rest=$row['rest'];
}
$f_sql = "SELECT $tabel_final_det_note.pachet,$tabel_final_det_note.discount,$tabel_final_det_note.cod_p,$tabel_final_nomenclator.den_p,$tabel_final_nomenclator.um,$tabel_final_det_note.cantitate,$tabel_final_det_note.tva_col,$tabel_final_det_note.pret_vanzare,$tabel_final_det_note.valoare_vanzare,$tabel_final_det_note.valoare_vanzare_cu_tva,cote_tva.cota,cote_tva.dep_casa,$tabel_final_det_note.id_vanz from $tabel_final_nomenclator inner join $tabel_final_det_note on $tabel_final_det_note.cod_p=$tabel_final_nomenclator.cod_p inner join cote_tva on $tabel_final_nomenclator.cota_tva=cote_tva.id where nr_bon='$nr_bon';";    
$f_stmt = $pdo->prepare($f_sql);  
$f_stmt->execute(); 



echo "
<tr><td>C.I.F. CLIENT: $cif_client</td></tr> 
<tr><td>Data si ora: $data_bon $ora_bon </td></tr>";

$op_sql = "SELECT admin_firstname,admin_lastname FROM $tabel_final_admins where admin_id='$operator'";    
$op_stmt = $pdo->prepare($op_sql);  
$op_stmt->execute(); 
while ($row = $op_stmt->fetch(PDO::FETCH_ASSOC)){ 
$admin_firstname=$row['admin_firstname'];
$admin_lastname=$row['admin_lastname'];
}
echo "<tr><td>OPERATOR:  $admin_firstname $admin_lastname <td></tr>";

//CELL(width,height,text,border,end line,[align])
//end of line


echo "<tr><td><h4>Produse</h4> <td></tr>";

while ($row = $f_stmt->fetch(PDO::FETCH_ASSOC)){ 
$pachet=$row['pachet'];
$pprodus=$row['den_p'];
$produs=substr($pprodus, 0, 20);

$um=$row['um'];
$cantitate=$row['cantitate'];
$tva_col=$row['tva_col'];
$pret_vanzare=$row['pret_vanzare'];
$valoare_vanzare=$row['valoare_vanzare'];
$cota_tva=$row['cota'];
$dep_casa=$row['dep_casa'];
if($cota_tva==9 && $pachet!=1 ){
							       $cota_tva=5;
								   $dep_casa=3;
							   }
							   			   if($cota_tva==9 && $pachet==1 ){
$produs.=" -P";

}
$discount=$row['discount'];
$discount_unitar=$discount/$cantitate;
$pret_vanzare_cu_tva=$pret_vanzare-$discount_unitar;
$pret_vanzare_cu_tva=round(($pret_vanzare_cu_tva),2);
$valoare_vanzare_c_tva=$pret_vanzare_cu_tva*$cantitate;

echo "<tr><td>$produs   $cantitate  X  $um   | $pret_vanzare";


if($dep_casa==1){
    
    $cat_tva='A';
}
elseif($dep_casa==2){
    
    $cat_tva='B';
}
elseif($dep_casa==3){
    
    $cat_tva='C';
}
elseif($cota_tva==7){
    
    $cat_tva='';
}
echo " Total: $valoare_vanzare_c_tva  $cat_tva</td></tr> ";
}




  
 $nr_bon=$_SESSION['nr_bon'];
 $f_tot_sql = "SELECT sum($tabel_final_det_note.valoare_vanzare_cu_tva) as a from $tabel_final_det_note  where nr_bon='$nr_bon'; ";    
$f_tot_stmt = $pdo->prepare($f_tot_sql);  
$f_tot_stmt->execute();

while ($row = $f_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_val_vz_cu_tva=$row['a'];
}
 $ds_tot_sql = "SELECT sum($tabel_final_det_note.discount) as b from $tabel_final_det_note  where nr_bon='$nr_bon'; ";    
$ds_tot_stmt = $pdo->prepare($ds_tot_sql);  
$ds_tot_stmt->execute();

while ($row = $ds_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_disc=$row['b'];
}

$total_val_vz_cu_tva=$total_val_vz_cu_tva-$total_disc;
echo "<tr><td><h4>TOTAL  $total_val_vz_cu_tva   LEI</h4></td></tr>";



$tva_a=0;
$tva_b=0;
$tva_c=0;
$faratva=0;

 $cote_tva_sql = "SELECT $tabel_final_det_note.pachet,$tabel_final_det_note.discount,cote_tva.cota,cote_tva.dep_casa,$tabel_final_det_note.tva_col from $tabel_final_nomenclator inner join $tabel_final_det_note on $tabel_final_det_note.cod_p=$tabel_final_nomenclator.cod_p inner join cote_tva on $tabel_final_nomenclator.cota_tva=cote_tva.id where nr_bon='$nr_bon';";    
$cote_tva_stmt = $pdo->prepare($cote_tva_sql);  
$cote_tva_stmt->execute();
while ($row = $cote_tva_stmt->fetch(PDO::FETCH_ASSOC)){
    
    $dep_casa=$row['dep_casa'];
$pachet=$row['pachet'];
	$tva_col=round($row['tva_col'],2);

							   $cota_tva=$row['cota'];
							   if($cota_tva==9 && $pachet!=1 ){
								   $dep_casa=3;
							   }
	//$total_discount=$row['total_discount']*$row['cota']/(100+$row['cota']);
	$total_dep=round($total_dep,2);
	if($dep_casa==1){
	    $den_dep_casa='A: TVA A (19%) ';
	    $tva_a=$tva_a+$tva_col;
	}
	elseif($dep_casa==2){
	    	    $den_dep_casa='B: TVA B (9%) ';
	    	    $tva_b=$tva_b+$tva_col;

	}
	elseif($dep_casa==3){
	    	    $den_dep_casa='C: TVA C (5%) ';
	    	    $tva_c=$tva_c+$tva_col;

	}
    elseif($dep_casa==7){
	    	    $den_dep_casa='FARA TVA ';
	    	    $faratva=$faratva+$tva_col;

	}


    
    
}
if($tva_a>0){
echo "	<tr><td>A: TVA A (19%) $tva_a </td></tr>";
	
}
if($tva_b>0){
echo "	<tr><td>B: TVA B (9%) $tva_b </td></tr>";

    
}
	if($tva_c>0){
	    
	    
echo "	    <tr><td>C: TVA C (5%) $tva_c </td></tr>";

	    
	}
echo "	<tr><td>FARA TVA $faratva </td></tr>";
echo "	<tr><td>TOTAL TVA $tva_colectata</td></tr>";

echo "<tr><td><h4>Modificare sume incasate </h4></td></tr>";


echo "<form method='POST'>
<tr><td>Numerar <input class='form-control' type='number' step='0.00000001' value='$numerar' name='numerar'/></td></tr>";


echo "<tr><td>Tichete <input class='form-control' type='number' step='0.00000001' value='$tichete' name='tichete'/></td></tr>";


echo "<tr><td>Card <input class='form-control' type='number' step='0.00000001' value='$card' name='card'/></td></tr>";


echo "<tr><td>Rest <input class='form-control' type='number' step='0.00000001' value='$rest' name='rest'/></td></tr>";

echo "
<tr><td><button style='width:100%; height:6vh;' class='btn btn-success' type='submit' name='salv_sume'><h4>Salveaza sume noi</h4></button></td></tr>
</form>";



//Do not open the print dialog



?>
</table>

</div>
</div>
</div>


<?php include 'footer.php'; ?>