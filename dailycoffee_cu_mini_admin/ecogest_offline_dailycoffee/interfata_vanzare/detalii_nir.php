<?php  include('session.php');
	$title = 'Detalii Nir';
  include 'header.php';
	
	if(isset($_POST['modif_nir'])){

printf("<script>location.href='nir.php'</script>");
	
}
 if(isset($_POST['list_nir'])){

printf("<script>location.href='listeaza_nir.php'</script>");
	
}
?>

	
	  
				
<!-- about -->

<style>.table-bordered{
text-align:center;
}
th,td{font-size:0.8em;}

</style>
			<ol class="breadcrumb">
<li class="breadcrumb-item active">Nota de intrare receptie nr. <?php	if(!isset($_SESSION['nr_nir'])){$_SESSION['nr_nir']=$_GET['n'];} echo $_SESSION['nr_nir']; ?></li>
</ol>	
	
	
	 <div class="card mb-3">
        <div class="card-header">
          <i class="fa fa-table"></i> Detaliile notei de receptie</div>
        <div class="card-body">
          <div class="table-responsive">
     <table class='table table-bordered'>   
  <tr>
  <td><h4>Datele furnizorului:</h4></td><td>
  <?php
  $nr_nir=$_SESSION['nr_nir'];
   $nsql = "SELECT * from $tabel_final_nir where nr_nir='$nr_nir'";    
$nstmt = $pdo->prepare($nsql);  
$nstmt->execute(); 
while ($row = $nstmt->fetch(PDO::FETCH_ASSOC)){ 
    $_SESSION['cod_furnizor']=$row['cod_tert'];
     $_SESSION['serie_d']=$row['serie_doc_int']; 
     $_SESSION['nr_d']=$row['nr_doc_int']; 
    $_SESSION['data_doc']=$row['data_doc_int']; 
    $_SESSION['status']=$row['status'];
}
  $cfurnizor=$_SESSION['cod_furnizor'];
  
 $fsql = "SELECT * from $tabel_final_terti where cod_tert='$cfurnizor'";    
$fstmt = $pdo->prepare($fsql);  
$fstmt->execute(); 
while ($row = $fstmt->fetch(PDO::FETCH_ASSOC)){ 
                     $den_tert=$row['denumire'];
			             $adresa=$row['adresa']; 
						 $cui_cif=$row['cui_cif']; 
						 $cont_banca=$row['cont_banca']; 
						 $judet=$row['judet'];
                        $nr_reg_com=$row['nr_reg_com'];
                        $banca=$row['banca'];

              echo "<b>Denumire</b>:  $den_tert 
			  <br><b>Adresa</b>:  $adresa
			   <br>
			  <b>Județ</b>:$judet
			  <br>
			  <b>CUI/CIF</b>:  $cui_cif
			  <br>
			  <b>Nr.ord.reg.com</b>:$nr_reg_com
			  <br>
			  <b>Cont IBAN</b>:  $cont_banca
			  <br>
			  <b>Banca</b>:$banca"; 
             
			

}


echo "</td></tr>"; 
?><tr> 
 <td><h4>Seria facturii:</h4></td>
 <td> <?php
echo $_SESSION['serie_d']; 
 

?>
 </td></tr>
  <tr> 	 
  <td><h4>Numărul facturii:</h4></td>
   <td> <?php
echo $_SESSION['nr_d']; 
 

?></td></tr> 
     <td><h4>Data facturii:</h4></td>
   <td><?php
   $data_doc=date( 'd-m-Y', strtotime( $_SESSION['data_doc'] ) );
echo  $data_doc; 
 

?></td>
  <tr><td> </td>

</tr> 
    
    
    </table>
 
 
 

<table class='table table-bordered'>
  <tr>
    <th width="15%"  rowspan="2">Denumire</th>
    <th  rowspan="2">U.M.</th>
    <th  rowspan="2">Cant.<br>doc.</th>
    <th  rowspan="2">Cant.<br>prim.</th>
    <th  colspan="4">Achizitie</th>
    <th  colspan="3">Adaos</th>
        <th width="15%"  rowspan="2">Pret vanzare fara TVA</th>
    <th  colspan="3">TVA vanzare</th>
    <th  colspan="2">Valoare vanzare cu TVA</th>
    <th  rowspan="2">Elimina</th>
  </tr>
  <tr>
    <td ><b>P.U.<br>(fara TVA)</b></td>
    <td ><b>Valoare<br>(fara TVA)</b></td>
    <td ><b>TVA</b></td>
    <td ><b>Total</b></td>

    <td ><b>%</b></td>
    <td ><b>Unitar</b></td>
    <td ><b>Total</b></td>
        <td ><b>Tva adaos</b></td>

    <td ><b>Unitar</b></td>
    <td ><b>Total</b></td>
    <td ><b>Unitar</b></td>
    <td ><b>Total</b></td>

  </tr>
  <tr>
      
      <style>  b{font-size:1em;}</style>
    <td width="15%" ><b>1</b></td>
    <td ><b>2</b></td>
    <td ><b>3</b></td>
    <td ><b>4</b></td>
    <td ><b>5</b></td>
        <td ><b>6</b></td>
    <td ><b>7</b></td>
    <td ><b>8 = 7+6</b></td>
    <td ><b>9</b></td>
    <td ><b>10</b></td>
    <td ><b>11 = 10 x 4</b></td>
    <td ><b>12 = 10 + 5</b></td>
     <td ><b>13 = 11 x cota tva</b></td>
    <td ><b>14 = 12 x cota tva</b></td>
     <td ><b>15 = 14 x 4</b></td>
     <td ><b>16 = 12 + 14</b></td>
<td ><b>17 = 16 x 4</b></td>
  </tr>
  
  <?php
//afisare randuri NIR
$nr_nir=$_SESSION['nr_nir'];

$nir_sql = "SELECT cote_tva.cota,$tabel_final_achizitii.pret_vanzare_fara_tva,$tabel_final_achizitii.cod_p,$tabel_final_nomenclator.den_p,$tabel_final_nomenclator.um,$tabel_final_nomenclator.proc_adaos,$tabel_final_achizitii.cantitate_doc,$tabel_final_nomenclator.pret_vanzare,$tabel_final_achizitii.cantitate_prim,$tabel_final_achizitii.pret_achiz,$tabel_final_achizitii.valoare_fara_tva,$tabel_final_achizitii.tva_ded,$tabel_final_achizitii.proc_adaos,$tabel_final_achizitii.ad_unit,$tabel_final_achizitii.valoare_adaos,$tabel_final_achizitii.tva_neex,$tabel_final_achizitii.pret_vanzare,$tabel_final_achizitii.valoare_vanzare,$tabel_final_achizitii.valoare_vanzare_cu_tva,$tabel_final_achizitii.id_achiz,$tabel_final_achizitii.tva_colect_unit from $tabel_final_nomenclator inner join $tabel_final_achizitii on $tabel_final_achizitii.cod_p=$tabel_final_nomenclator.cod_p INNER JOIN cote_tva on $tabel_final_nomenclator.cota_tva=cote_tva.id where nr_nir='$nr_nir';";    
$nir_stmt = $pdo->prepare($nir_sql);  
$nir_stmt->execute(); 

while ($row = $nir_stmt->fetch(PDO::FETCH_ASSOC)){ 
    $pret_vanzare=$row['pret_vanzare'];
$codul_produsului=$row['cod_p'];
$produs=$row['den_p'];
$um=$row['um'];
$cantitate_doc=$row['cantitate_doc'];
$cantitate_prim=$row['cantitate_prim'];
$pret_achiz=$row['pret_achiz'];
$valoare_fara_tva=$row['valoare_fara_tva'];
$c_tva=$row['cota'];
$tva_ded=$row['tva_ded'];
$total=$valoare_fara_tva+$tva_ded;
$pret_fara_tva=$row['pret_vanzare_fara_tva'];
$ad_unit=$row['ad_unit'];
$valoare_adaos=$row['valoare_adaos'];
$proc_adaos=$row['proc_adaos'];
$tva_neex=$row['tva_neex'];
$tva_col_unitar=$row['tva_colect_unit'];
$tva_col_total=$tva_col_unitar*$cantitate_prim;
$valoare_vanzare=$row['valoare_vanzare'];
$id_achz=$row['id_achiz'];
$valoare_vanzare_c_tva=$row['valoare_vanzare_cu_tva'];
$pret_vanzare_final=$pret_fara_tva+$tva_col_unitar;
$total_vanzare_final=$pret_vanzare_final*$cantitate_prim;
  echo "
   <tr>
    <td class='tabel'>$produs</td>
    <td class='tabel'>$um</td>
<td>$cantitate_doc</td>     
<td >$cantitate_prim</td>    
    <td class='tabel'>$pret_achiz</td>
    <td class='tabel'>$valoare_fara_tva</td>
    	 <td class='tabel'>$tva_ded</td>
    	     	 <td class='tabel'>$total</td>
    <td class='tabel'>$proc_adaos</td>
    <td class='tabel'>$ad_unit</td>
    <td class='tabel'>$valoare_adaos</td>
        <td class='tabel'>$pret_fara_tva</td>
    <td class='tabel'>$tva_neex</td>
    <td class='tabel'>$tva_col_unitar</td>
	<td class='tabel'>$tva_col_total</td>
		<td class='tabel'>$pret_vanzare_final</td>
	<td class='tabel'>$total_vanzare_final</td>

	
	<td><form method='post'><input class='btn btn-primary btn-block' type='submit' value='Sterge' name='$id_achz'></form></td>

  </tr>
  
  
  
  " 
  
  ;
  if (isset($_POST[$id_achz])) {
					
					$sterg_sql="SELECT * FROM $tabel_final_achizitii where $tabel_final_achizitii.id_achiz = '$id_achz' ";
					$sterg_nir_stmt = $pdo->prepare($sterg_sql);  
$sterg_nir_stmt->execute(); 

while ($row = $sterg_nir_stmt->fetch(PDO::FETCH_ASSOC)){ 

$c_p=$row['cod_p'];
$cant_achz=$row['cantitate_prim'];
}
					
					$sql="DELETE FROM $tabel_final_achizitii WHERE $tabel_final_achizitii.id_achiz = '$id_achz';";
$pdo->exec($sql) or die(print_r($pdo->errorInfo(), true));

			printf("<script>location.href='nir.php'</script>");
				}
  $nr_crt1++;}
  
  
 
  ?>
 <th  colspan="5">Total</th>  
 <th  >
 <?php 
 $nr_nir=$_SESSION['nr_nir'];
 $nir_tot_sql = "SELECT sum($tabel_final_achizitii.valoare_fara_tva) as a from $tabel_final_achizitii  where nr_nir='$nr_nir'; ";    
$nir_tot_stmt = $pdo->prepare($nir_tot_sql);  
$nir_tot_stmt->execute();

while ($row = $nir_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$valoare_nir_f_tva=$row['a'];
}
  echo $valoare_nir_f_tva ?>
  </th>  
  <th  >
 <?php 
 $nr_nir=$_SESSION['nr_nir'];
 $nir_tot_sql = "SELECT sum($tabel_final_achizitii.tva_ded) as d from $tabel_final_achizitii  where nr_nir='$nr_nir'; ";    
$nir_tot_stmt = $pdo->prepare($nir_tot_sql);  
$nir_tot_stmt->execute();

while ($row = $nir_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_tva_ded=$row['d'];
}
  echo $total_tva_ded ?>
  </th> 
     <th  ><?php $t=$total_tva_ded+$valoare_nir_f_tva;
     echo $t;?></th>  
   <th  >-</th>  
   <th  >-</th>  

  <th  >
 <?php 
 $nr_nir=$_SESSION['nr_nir'];
 $nir_tot_sql = "SELECT sum($tabel_final_achizitii.valoare_adaos) as b from $tabel_final_achizitii  where nr_nir='$nr_nir'; ";    
$nir_tot_stmt = $pdo->prepare($nir_tot_sql);  
$nir_tot_stmt->execute();

while ($row = $nir_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$valoare_adaos_nir=$row['b'];
}
  echo round($valoare_adaos_nir,2); ?>
  </th> 
   <th  >-</th>  

  <th  >
 <?php 
 $nr_nir=$_SESSION['nr_nir'];
 $nir_tot_sql = "SELECT sum($tabel_final_achizitii.tva_neex) as e from $tabel_final_achizitii  where nr_nir='$nr_nir'; ";    
$nir_tot_stmt = $pdo->prepare($nir_tot_sql);  
$nir_tot_stmt->execute();

while ($row = $nir_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_tva_neex=$row['e'];
}
  echo round($total_tva_neex,2);
  


  ?>
  </th> 
  
  <th>
   -
      
  </th>
  
   <th  >
           <?php 
 $nr_nir=$_SESSION['nr_nir'];
 $nir_tot_sql = "SELECT sum($tabel_final_achizitii.tva_colect_unit*$tabel_final_achizitii.cantitate_prim) as gg from $tabel_final_achizitii  where nr_nir='$nr_nir'; ";    
$nir_tot_stmt = $pdo->prepare($nir_tot_sql);  
$nir_tot_stmt->execute();

while ($row = $nir_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$tva_colect_unit=$row['gg'];
}
  echo round($tva_colect_unit,2);
  


  ?>
       </th>  


  <th  >
 -
  </th> 
  
  

 
  
  <th  >
 <?php 
 $nr_nir=$_SESSION['nr_nir'];
 $nir_tot_sql = "SELECT sum($tabel_final_achizitii.valoare_vanzare_cu_tva) as f from $tabel_final_achizitii  where nr_nir='$nr_nir'; ";    
$nir_tot_stmt = $pdo->prepare($nir_tot_sql);  
$nir_tot_stmt->execute();

while ($row = $nir_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_val_vz_cu_tva=$row['f'];
}
  echo round($total_val_vz_cu_tva,2);
  
if(isset($_POST['salvare_nir'])){

$fin_sql = "update $tabel_final_nir SET ad_com_total='$valoare_adaos_nir',tva_neex_ad_com='$total_tva_neex',tva_ded='$total_tva_ded',val_nir_ftva='$valoare_nir_f_tva',valoare_totala='$valoare_nir_vz',valoare_totala_cu_tva='$total_val_vz_cu_tva' WHERE nr_nir='$nr_nir';";    
			
			
	 
	try{
$pdo->exec($fin_sql);   
    // afiseaza un mesaj de succes 
			printf("<script>location.href='note_de_receptie.php'</script>");
}catch(PDOException $e)
    {
    echo $fin_sql . "<br>" . $e->getMessage();
    } 
 
	
}

if(isset($_POST['finaliz_nir'])){
$admin_em=$_SESSION['adminloggedin'];

$fin_sql = "update $tabel_final_nir SET gestionar='$admin_em',ad_com_total='$valoare_adaos_nir',tva_neex_ad_com='$total_tva_neex',tva_ded='$total_tva_ded',val_nir_ftva='$valoare_nir_f_tva',valoare_totala='$valoare_nir_vz',valoare_totala_cu_tva='$total_val_vz_cu_tva',status='F' WHERE nr_nir='$nr_nir';";    
			
			
	 
	try{
$pdo->exec($fin_sql) or die(print_r($pdo->errorInfo(), true));   
			printf("<script>location.href='note_de_receptie.php'</script>");

}catch(PDOException $e)
    {
    echo $fin_sql . "<br>" . $e->getMessage();
    } 
 
	 $nr_nir=$_SESSION['nr_nir'];

$misc_sql = "SELECT $tabel_final_achizitii.cod_p,$tabel_final_achizitii.pret_achiz,$tabel_final_achizitii.cantitate_prim from $tabel_final_achizitii where nr_nir='$nr_nir';";    
$misc_stmt = $pdo->prepare($misc_sql);  
$misc_stmt->execute(); 

while ($row = $misc_stmt->fetch(PDO::FETCH_ASSOC)){
    	date_default_timezone_set('UTC');

         $prod=$row['cod_p'];
     $qt=$row['cantitate_prim'];
          $pu=$row['pret_achiz'];


    $iessql = "insert into $tabel_final_miscari(data,cod_p,cantitate_misc,tip_miscare,fel_doc,nr_doc,pu,ramas,cod_locatie) values('$data_nir','$prod','$qt','I','NIR','$nr_nir','$pu','$qt','2');";   

	 
	try{
$pdo->exec($iessql) or die(print_r($pdo->errorInfo(), true));   

}catch(PDOException $e)
    {
    echo $iessql . "<br>" . $e->getMessage();
    } 
    
    
        $stoc_sql = "update $tabel_final_stoc set cantitate=cantitate+'$qt' where cod_p=$prod;";   

	 
	try{
$pdo->exec($stoc_sql) or die(print_r($pdo->errorInfo(), true));   

}catch(PDOException $e)
    {
    echo $stoc_sql . "<br>" . $e->getMessage();
}
}

$syncNirStmt = $pdo->prepare("SELECT id_nir FROM $tabel_final_nir WHERE nr_nir = ? LIMIT 1");
$syncNirStmt->execute([$nr_nir]);
$syncNirId = (int)$syncNirStmt->fetchColumn();
if ($syncNirId > 0) {
    offline_sync_enqueue_nir_safely($pdo, $syncNirId);
}
	
	
}

  ?>
  </th>
  <th  >-</th>  

  
  
</table>
 

<?php 

$status=$_SESSION['status'];

if ($status=='S'){

echo '
<form method="post">
 <input type="submit" class="btn btn-primary btn-block" name="modif_nir" value="Modifică NIR">
 </form>';
 }?>
 <br>
 <?php 





if ($status=='F'){

echo '<div class="row">
 <div class="col-md-6">
  <a class="btn btn-primary btn-block" href="listeaza_nir.php?nr_nir=' . urlencode($nr_nir) . '&spatii=1">LISTEAZA CU SPATII</a>
 </div>
 <div class="col-md-6">
  <a class="btn btn-info btn-block" href="listeaza_nir.php?nr_nir=' . urlencode($nr_nir) . '&spatii=0">LISTEAZA FARA SPATII</a>
 </div>
</div>';

     
 }
?>
 

 
 
 <!-- InstanceEndEditable -->




  </div>
        </div>
        
      </div>



<?php 
	require "footer.php";
?>
