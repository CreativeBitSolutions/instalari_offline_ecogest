<?php  include('session.php');
	$title = 'Detalii factura';
  include 'header.php';
	
?>

	
	   
				
<!-- about -->

<style>.table-bordered{
text-align:center;
}</style>
		
	<h3 align="center">Factura nr. <?php 	if(!isset($_SESSION['nr_factura'])){$_SESSION['nr_factura']=$_GET['f'];} echo $_SESSION['nr_factura'];  ?></h3>
	
	 <div class="card mb-3">
        <div class="card-header">
          <i class="fa fa-table"></i> Detaliile facturii</div>
        <div class="card-body">
          <div class="table-responsive">

         
<table class='table table-bordered'>       
  <tr>
  <td><h4>Datele clientului:</h4></td><td>
  <?php
  $cclient=$_SESSION['cod_client'];

 $csql = "SELECT * from $tabel_final_terti where cod_tert='$cclient'";    
$cstmt = $pdo->prepare($csql);  
$cstmt->execute(); 
while ($row = $cstmt->fetch(PDO::FETCH_ASSOC)){ 
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
			  <b>Judet</b>:$judet
			  <br>
			  <b>CUI/CIF</b>:  $cui_cif
			  <br>
			  <b>Nr.ord.reg.com</b>:$nr_reg_com
			  <br>
			  <b>Cont IBAN</b>:  $cont_banca
			  <br>
			  <b>Banca</b>:$banca
			 
			  
			  
			  ";    
             
			

}


echo "</td></tr>"; 
?><tr> 
 <td><h4>Seria facturii:</h4></td>
 <td> <?php
echo $_SESSION['serie_factura']; 
 

?>
 </td></tr>
  <tr> 	 
  <td><h4>Numarul facturii:</h4></td>
   <td> <?php
echo $_SESSION['nr_factura']; 
 

?></td></tr> 
     <td><h4>Data facturii:</h4></td>
   <td><?php
   $data_fact=date( 'd-m-Y', strtotime( $_SESSION['data_factura'] ) );
echo  $data_fact; 
 

?></td></tr> 
    
    
   </table>

 



<table class='table table-bordered'>
  <tr>
    <th rowspan="2">Nr.Crt.</th>
    <th width="15%" rowspan="2">Denumire<br>produs</th>
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
    <td>2</b></td>
    <td><b>3</b></td>
    <td><b>4</b></td>
    <td><b>5(=3x4)</b></td>
    <td><b>6</b></td>
    <td><b>7(=5+6)</b></td>
   



  </tr>
  
  <?php
//afisare randuri factura
$nr_factura=$_SESSION['nr_factura'];
$nr_crt1 = 1;

$f_sql = "SELECT cote_tva.cota,$tabel_final_vanzari.discount,$tabel_final_vanzari.cod_p,$tabel_final_nomenclator.den_p,$tabel_final_nomenclator.um,$tabel_final_vanzari.cantitate,$tabel_final_vanzari.tva_col,$tabel_final_vanzari.pret_vanzare,$tabel_final_vanzari.valoare_vanzare,$tabel_final_vanzari.valoare_vanzare_cu_tva,$tabel_final_vanzari.id_vanz from $tabel_final_nomenclator inner join $tabel_final_vanzari on $tabel_final_vanzari.cod_p=$tabel_final_nomenclator.cod_p inner join cote_tva on $tabel_final_nomenclator.cota_tva=cote_tva.id where $tabel_final_vanzari.nr_factura='$nr_factura';";    
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
$valoare_vanzare_c_tva=$row['valoare_vanzare_cu_tva'];
$codul_produsului=$row['cod_p'];
$cota=$row['cota'];
$d=$row['discount'];
  echo "
   <tr>
    <td width='10%'>$nr_crt1</td>
    <td>$produs</td>
    <td>$um</td>
 
 <td >X  $cantitate</td>    
    
    <td>$pret_vanzare</td>
    <td>$valoare_vanzare</td>
	 <td>$tva_col</td>
   
	<td><div>$valoare_vanzare_c_tva</div>";echo "<div style='color:green;'>-$d</div>"; echo"</td>


  </tr>
  
  
  
  " 
  
  ;
  
  $nr_crt1++;}
  
  
 
  ?>
 <th colspan="5">Total</th>  
 <th>
<?php 
 $nr_factura=$_SESSION['nr_factura'];
 $f_tot_sql = "SELECT sum($tabel_final_vanzari.valoare_vanzare) as a from $tabel_final_vanzari  where nr_factura='$nr_factura'; ";    
$f_tot_stmt = $pdo->prepare($f_tot_sql);  
$f_tot_stmt->execute();

while ($row = $f_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$valoare_f_vz=$row['a'];
}
  echo $valoare_f_vz ?>
  </th> 
   

 




 
  
  
  <th >
 <?php 
 $nr_factura=$_SESSION['nr_factura'];
 $f_tot_sql = "SELECT sum($tabel_final_vanzari.tva_col) as c from $tabel_final_vanzari  where nr_factura='$nr_factura'; ";    
$f_tot_stmt = $pdo->prepare($f_tot_sql);  
$f_tot_stmt->execute();

	 $disc_tot_sql = "SELECT sum($tabel_final_vanzari.discount) as b from $tabel_final_vanzari where nr_factura='$nr_factura'; ";    
$disc_tot_stmt = $pdo->prepare($disc_tot_sql);  
$disc_tot_stmt->execute();

while ($row = $disc_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_discount=$row['b'];
}
while ($row = $f_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_tva_col=$row['c'];
}
  echo $total_tva_col ?>
  </th> 
  
 
  
  <th >
 <?php 
 $nr_factura=$_SESSION['nr_factura'];
 $f_tot_sql = "SELECT sum($tabel_final_vanzari.valoare_vanzare_cu_tva) as de from $tabel_final_vanzari  where nr_factura='$nr_factura'; ";    
$f_tot_stmt = $pdo->prepare($f_tot_sql);  
$f_tot_stmt->execute();

while ($row = $f_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_val_vz_cu_tva=$row['de'];
}
  echo $total_val_vz_cu_tva. '<p style="color:green">'.'- '.$total_discount.'</p>';
  


  ?>
  </th>

  
  
</table>
 

<?php 

$status=$_SESSION['status'];

if ($status=='S'){
if(isset($_POST['modif_factura'])){

printf("<script>location.href='factura.php'</script>");
	
}
if(isset($_POST['listare_proform'])){

printf("<script>location.href='listeaza_fact_proform.php'</script>");
	
}
echo '
<form method="post">
 <input type="submit" class="btn btn-primary btn-block" name="modif_factura" value="Modifică Factura">
  <input type="submit" class="btn btn-primary btn-block" name="listare_proform" value="Listare Proforma">
 </form>';
 }?>
 <br>
 <?php 





 if ($status=='F'){

echo '<form method="post">
 <input type="submit" class="btn btn-primary btn-block" name="list_fact" value="Listează factura">
 </form>';
 if(isset($_POST['list_fact'])){

printf("<script>location.href='listeaza_fact.php'</script>");
	
}
     
 }
?>
 
 <!-- InstanceEndEditable -->




  </div>
        </div>
        
      </div>


   



<?php 
	require "footer.php";
?>