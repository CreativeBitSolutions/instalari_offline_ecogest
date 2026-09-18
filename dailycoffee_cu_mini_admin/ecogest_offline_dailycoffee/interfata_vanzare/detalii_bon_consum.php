<?php  include('session.php');
	$title = 'Detalii Bon Consum Personalizat';
  include 'header.php';
	

 if(isset($_POST['list_nir'])){

printf("<script>location.href='listeaza_bon_personalizat.php'</script>");
	
}
?>

	
	  
				
<!-- about -->

<style>.table-bordered{
text-align:center;
}</style>
			<ol class="breadcrumb">
<li class="breadcrumb-item active">Bonul de consum nr. <?php	if(!isset($_SESSION['nr_bon_c'])){$_SESSION['nr_bon_c']=$_GET['n'];} echo $_SESSION['nr_bon_c']; ?></li>
</ol>	
	
	
	 <div class="card mb-3">
        <div class="card-header">
          <i class="fa fa-table"></i> Detaliile bonului de consum</div>
        <div class="card-body">
          <div class="table-responsive">
     <table style='float:right;width:100%;' class='table table-bordered'>   

 <td><h4>Numar Bon:</h4></td>
 <td> <?php
 $nr_bon=$_SESSION['nr_bon_c'];

echo $nr_bon; 
 		 
		  $fsql3 = "SELECT * from $tabel_final_bonuri_consum where nr_bon='$nr_bon'";    
$fstmt3 = $pdo->prepare($fsql3);  
$fstmt3->execute(); 
while ($row = $fstmt3->fetch(PDO::FETCH_ASSOC)){
	  	$data_bon=$row['data_bon']; 
$produs=$row['produs'];
$gestiune_pred=$row['gestiune_pred'];
$nr_com=$row['nr_comanda'];
$finalizat=$row['finalizat'];

 
    
}

?>
 </td></tr>
  <tr> 	 
  <td><h4>Produs:</h4></td>
   <td> <?php
echo $produs;
 

?></td></tr> 
    <tr> <td><h4>Data Bonului:</h4></td>
   <td><?php
   $data_bon=date( 'd-m-Y', strtotime($data_bon ) );
echo  $data_bon; 
 

?></td></tr>
  
      <tr> <td><h4>Gestiune predatoare:</h4></td>
   <td><?php
echo  $gestiune_pred; 
 

?></td></tr>
         <tr> <td><h4>Nr comanda:</h4></td>
   <td><?php
echo  $nr_com; 
 

?></td></tr>
    </table>

 
 



<table class='table table-bordered'>
  <tr>
    <th>Nr.Crt.</th>
    <th width="15%"  >Denumire material</th>
    <th>U.M.</th>
    <th  >Cant.<br>neces.</th>
    <th  >Cant.<br>elib.</th>
     <th  >P.U.</th>
     <th  >Valoare</th>



  </tr>
  <tr>
    <td ><b>0</b></td>
    <td width="15%" ><b>1</b></td>
    <td ><b>2</b></td>
    <td ><b>3</b></td>
    <td ><b>4</b></td>
    <td ><b>5</b></td>
    <td ><b>6(=4x5)</b></td>




  </tr>
  
  <?php
//afisare randuri NIR
$nr_crt1 = 1;

$nir_sql = "SELECT $tabel_final_consumuri.cod_p,$tabel_final_nomenclator.den_p,$tabel_final_nomenclator.um,$tabel_final_consumuri.cantitate_nec,$tabel_final_consumuri.cantitate_elib,$tabel_final_consumuri.pret_unitar,$tabel_final_consumuri.id_consum from $tabel_final_nomenclator inner join $tabel_final_consumuri on $tabel_final_consumuri.cod_p=$tabel_final_nomenclator.cod_p where $tabel_final_consumuri.nr_bon='$nr_bon';";    
$nir_stmt = $pdo->prepare($nir_sql);  
$nir_stmt->execute(); 

while ($row = $nir_stmt->fetch(PDO::FETCH_ASSOC)){ 
$codul_produsului=$row['cod_p'];
$produs=$row['den_p'];
$um=$row['um'];
$proc_adaos=$row['proc_adaos'];
$cantitate_doc=$row['cantitate_nec'];
$cantitate_prim=$row['cantitate_elib'];
$pret_achiz=$row['pret_unitar'];
$valoare_fara_tva=$pret_achiz*$cantitate_prim;
$total_val=$total_val+$valoare_fara_tva;
$id_achz=$row['id_consum'];

  echo "
   <tr>
    <td width='10%' class='tabel'>$nr_crt1</td>
    <td class='tabel'>$produs</td>
    <td class='tabel'>$um</td>
<td>$cantitate_doc</td>     
<td>$cantitate_prim</td>    
    <td class='tabel'>$pret_achiz</td>
    <td class='tabel'>$valoare_fara_tva</td></tr>
  " 
  
  ;
  
  $nr_crt1++;}
  
  
 
  ?>
 <th  colspan="5">Total</th>  
 <th  >
 <?php 

  echo $total_val ?>
  </th>  


  </th>
  <th  >-</th>  

  
  
</table>
 

<?php 


if ($finalizat==0){

echo '
 <a class="btn btn-primary btn-block" href="bon_de_consum.php">Modifică Bon de Consum</a>
';
 }?>
 <br>
 <?php 





 if ($finalizat==1){

echo '<form method="post">
 <input type="submit" class="btn btn-primary btn-block" name="list_nir" value="Listează Bon de Consum">
 </form>';

     
 }
?>
 

 
 
 <!-- InstanceEndEditable -->




  </div>
        </div>
        
      </div>



<?php 
	require "footer.php";
?>