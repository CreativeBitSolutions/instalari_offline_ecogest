<?php  include('session.php');
	$title = 'Factura noua';
  include 'header.php';
	if(!isset($_SESSION['nrbon'])){
	  printf("<script>location.href='index.php'</script>");

	}
	
	$nrbon=$_SESSION['nrbon'];
?> 

<!-- about -->
<style>.table-bordered{
text-align:center;
}</style>
		

	 <div class="card mb-3">
        <div class="card-header">
          <i class="fa fa-table"></i> Completati Factura</div>
        <div class="card-body">
          <div class="table-responsive">
     <table class='table table-bordered'>   
<tr>
  <td><h4>Datele Clientului:</h4></td><td>
  <?php

 $csql = "SELECT * from $tabel_final_facturi_bonuri where nrbon='$nrbon'";    
$cstmt = $pdo->prepare($csql);  
$cstmt->execute(); 
while ($row = $cstmt->fetch(PDO::FETCH_ASSOC)){ 
               $den_tert=$row['denumire'];
			             $adresa=$row['adresa']; 
						 $cui_cif=$row['cui']; 
						 $cont_banca=$row['ct_banca']; 
						 $judet=$row['judet'];
                        $nr_reg_com=$row['nr_reg_com'];
                        $banca=$row['banca'];
                        $serie_factura=$row['serie_factura'];
                        $data_factura=$row['data_factura'];
                        $data_scadenta=$row['data_scadenta'];

              echo "<b>Denumire</b>  $den_tert 
			  <br><b>Adresa</b>  $adresa
			   <br>
			  <b>Judet</b>$judet
			  <br>
			  <b>CUI/CIF</b>  $cui_cif
			  <br>
			  <b>Nr.ord.reg.com</b>:$nr_reg_com
			  <br>
			  <b>Cont IBAN</b>  $cont_banca
			  <br>
			  <b>Banca</b>$banca";
             
			

}


echo "</td></tr>"; 
?><tr> 
 <td colspan='2'><h4>Seria <?php echo $serie_factura; ?>  Numar <?php
echo $nrbon; 
 

?></h4></td>
 
 </td></tr>

    <tr> <td><h4>Data Facturii</h4></td>
   <td><?php
   $data_fact=date( 'd-m-Y', strtotime($data_factura ) );
echo  $data_fact; 
 

?></td></tr> 
    

    
   </table>

<table class='table table-bordered'>
  <tr>
    <th rowspan="2">Nr.Crt.</th>
    <th width="15%" rowspan="2">Denumire<br>Produs</th>
    <th rowspan="2">U.M.</th>
    <th rowspan="2">Cant.</th>
    <th colspan="2">Vanzare</th>
     <th rowspan="2">TVA</th>
     <th rowspan="2">Valoare<br>(cu TVA)</th>
  </tr>
  <tr>
    <td><b>P.U.<br>(fara TVA)</b></td>
    <td><b>Valoare<br>(fara TVA)</b></td>
  </tr>
  <tr>
    <td><b>0</b></td>
    <td width="15%"><b>1</b></td>
    <td><b>2</b></td>
    <td><b>3</b></td>
    <td><b>4</b></td>
    <td><b>5(=3x4)</b></td>
    <td><b>6</b></td>
    <td><b>7(=5+6)</b></td>
  </tr>
  
  <?php
//afisare randuri NIR
$nrbon=$_SESSION['nrbon'];
$nr_crt1 = 1;

$f_sql = "SELECT * from $tabel_final_det_note inner join $tabel_final_nomenclator on $tabel_final_det_note.cod_p=$tabel_final_nomenclator.cod_p where nr_bon='$nrbon';";    
$f_stmt = $pdo->prepare($f_sql);  
$f_stmt->execute(); 

while ($row = $f_stmt->fetch(PDO::FETCH_ASSOC)){ 

$produs=$row['den_p'];
$um=$row['um'];
$cantitate=$row['cantitate'];
$tva_col=$row['tva_col'];
$pret_vanzare=$row['pret_vanzare'];
$valoare_vanzare=$row['valoare_vanzare'];
$id_vanz=$row['id_vanz'];
$d=$row['discount'];

$valoare_vanzare_c_tva=$row['valoare_vanzare_cu_tva']-$d;
$codul_produsului=$row['cod_p'];
$cota=$row['cota'];
 $pret_fara_tva=round(($valoare_vanzare/$cantitate),2);
  echo "
   <tr>
    <td width='10%'>$nr_crt1</td>
    <td>$produs</td>
    <td>$um</td>
 
 <td ><button disabled style='display:inline-block;' name='$id_vanz' value='$codul_produsului' data-value='$cantitate' class='btn btn-primary btn-block modif_cant' data-toggle='modal' data-target='#Cantitate' >X  $cantitate</button></td>    
    
    <td>$pret_fara_tva</td>
    <td>$valoare_vanzare</td>
	 <td>$tva_col</td>
   
	<td><div>$valoare_vanzare_c_tva</div>"; echo"</td>

  </tr>" ;
  
  if (isset($_POST[$id_vanz])) {
					$sterg_sql="SELECT * FROM $tabel_final_det_note where $tabel_final_det_note.id_vanz = '$id_vanz' ";
					$sterg_f_stmt = $pdo->prepare($sterg_sql);  
$sterg_f_stmt->execute(); 

while ($row = $sterg_f_stmt->fetch(PDO::FETCH_ASSOC)){ 

$c_p=$row['cod_p'];
$cant_vand=$row['cantitate'];
}
					
					$sql="DELETE FROM $tabel_final_det_note WHERE $tabel_final_det_note.id_vanz = '$id_vanz';";
$pdo->exec($sql) or die(print_r($pdo->errorInfo(), true));

			printf("<script>location.href='factura.php'</script>");
				}
  $nr_crt1++;}
  
  
 
  ?>
 <th colspan="5">Total</th>  
 <th>
<?php 
 $nrbon=$_SESSION['nrbon'];
 $f_tot_sql = "SELECT sum($tabel_final_det_note.valoare_vanzare) as a from $tabel_final_det_note  where nrbon='$nrbon'; ";    
$f_tot_stmt = $pdo->prepare($f_tot_sql);  
$f_tot_stmt->execute();

while ($row = $f_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$valoare_f_vz=$row['a'];
}
  echo $valoare_f_vz ?>
  </th> 
   

 




 
  
  
  <th >
 <?php 
 
 


 $nrbon=$_SESSION['nrbon'];
 $f_tot_sql = "SELECT sum($tabel_final_det_note.tva_col) as b from $tabel_final_det_note  where nr_bon='$nrbon'; ";    
$f_tot_stmt = $pdo->prepare($f_tot_sql);  
$f_tot_stmt->execute();

while ($row = $f_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_tva_col=$row['b'];
}

	 $disc_tot_sql = "SELECT sum($tabel_final_det_note.discount) as c from $tabel_final_det_note where nr_bon='$nrbon'; ";    
$disc_tot_stmt = $pdo->prepare($disc_tot_sql);  
$disc_tot_stmt->execute();

while ($row = $disc_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_discount=$row['c'];
}
  echo $total_tva_col?>
  </th> 
  
 
  
  <th >
 <?php 
 $nrbon=$_SESSION['nrbon'];
 $f_tot_sql = "SELECT sum($tabel_final_det_note.valoare_vanzare_cu_tva-$tabel_final_det_note.discount) as d from $tabel_final_det_note where nr_bon='$nrbon'; ";    
$f_tot_stmt = $pdo->prepare($f_tot_sql);  
$f_tot_stmt->execute();

while ($row = $f_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_val_vz_cu_tva=$row['d'];
}
  echo $total_val_vz_cu_tva. '<p style="color:green"></p>';
  
if(isset($_POST['finaliz_fact'])){
	

 $nrbon=$_SESSION['nrbon'];

$fin_sql = "UPDATE $tabel_final_facturi_bonuri SET status='F' WHERE nrbon='$nrbon';";    
			
			
	 
	try{
$pdo->exec($fin_sql) or die(print_r($pdo->errorInfo(), true));   
				printf("<script>location.href='facturi_bonuri.php'</script>");

}catch(PDOException $e)
    {
    echo $fin_sql . "<br>" . $e->getMessage();
    } 

    

	
	

}

  ?>
  </th>

  
  
</table>
 
 

 

<form method='POST'>

<?php

	  	     echo " <input class='btn btn-primary btn-block' type='submit' name='finaliz_fact' value='Finalizare Factura (Listabil Factura Fiscala)'>";

?>


 </form>
 </br>




      </div>      </div>
      </div>



<?php 
	require "footer.php";
?>

