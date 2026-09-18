<?php  include('session.php');
	$title = 'chitanta noua';
  include 'header.php';
	if(!isset($_SESSION['nr_chitanta'])){
	  printf("<script>location.href='client_chitanta.php'</script>");

	}
	$adm_id=$_SESSION['admin_id'];
?>

	
	   
				
<!-- about -->

<style>.table-bordered{
text-align:center;
}</style>
	 <div class="card mb-3">
        <div class="card-header">
          <i class="fa fa-table"></i> Completati Chitanta</div>
        <div class="card-body">
          <div class="table-responsive">
     <table class='table table-bordered'>   
      <form method="post">
<tr>
  <td><h4>Datele Clientului:</h4></td><td>
  <?php
	$factura= $_SESSION['factura'];

	 $psql3 = "SELECT $tabel_final_terti.denumire,$tabel_final_terti.adresa,$tabel_final_terti.cui_cif,$tabel_final_terti.cont_banca,$tabel_final_terti.judet,$tabel_final_terti.nr_reg_com,$tabel_final_terti.banca,$tabel_final_facturi.valoare_factura from $tabel_final_facturi inner join $tabel_final_terti on $tabel_final_facturi.cod_client=$tabel_final_terti.cod_tert where $tabel_final_facturi.nrfactura='$factura';";    
$pstmt3 = $pdo->prepare($psql3);  
$pstmt3->execute(); 
while ($row = $pstmt3->fetch(PDO::FETCH_ASSOC)){ 
                      $val_fact=$row['valoare_factura'];
			                  $den_tert=$row['denumire'];
			             $adresa=$row['adresa']; 
						 $cui_cif=$row['cui_cif']; 
						 $cont_banca=$row['cont_banca']; 
						 $judet=$row['judet'];
                        $nr_reg_com=$row['nr_reg_com'];
                        $banca=$row['banca'];          
}

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
			  <b>Banca</b>:$banca";

echo "</td></tr>"; 



	 $psql2 = "SELECT sum($tabel_final_chitante.suma_numere) as total_chitante from $tabel_final_chitante where nrfact='$factura';";    
$pstmt2 = $pdo->prepare($psql2);  
$pstmt2->execute(); 
while ($row = $pstmt2->fetch(PDO::FETCH_ASSOC)){ 
                      $total_chitante=$row['total_chitante'];
			                          
}
   

if($val_fact<5000){
    
    $max_chit=$val_fact-$total_chitante;

}
else{
$max_chit=5000-$total_chitante;
}

?><tr> 
 <td><h4>Seria Chitantei:</h4></td>
 <td> <?php
echo $_SESSION['serie_chitanta']; 
 

?>
 </td></tr>
  <tr> 	 
  <td><h4>Numarul Chitantei:</h4></td>
   <td> <?php
echo $_SESSION['nr_chitanta']; 
 

?>
</tr>
 <tr> 	 
  <td><h4>Numarul Facturii:</h4></td>
   <td> <?php
echo $_SESSION['factura']; 
 

?>
</tr>
     <tr><td><h4>Data Chitantei:</h4></td>
   <td><?php
   $data_chit=date( 'd-m-Y', strtotime( $_SESSION['data_chitanta'] ) );
echo  $data_chit; 
?>
</td> </tr>

<tr>  <td><h4>Suma:</h4></td>
<td><input class="form-control" type="number" step='0.01' min='0.01' value='<?php echo $max_chit;?>' max='5000' name="suma_num"  ></td></tr>

    

   </table>
 </table>
 	</div>
	</div>

      
      <?php

 if(isset($_POST['anulare_chitanta'])){
     $nr_chitanta=$_SESSION['nr_chitanta'];
$csql="DELETE from $tabel_final_chitante WHERE $tabel_final_chitante.nrchitanta = '$nr_chitanta';
					";
$pdo->exec($csql) or die(print_r($pdo->errorInfo(), true));
    	unset($_SESSION['nr_chitanta']);

			printf("<script>location.href='client_chitanta.php'</script>");

	   }


?>
      
<?php
$dsql = "SELECT * from $tabel_final_date_firma";
$dstmt = $pdo->prepare($dsql);  
$dstmt->execute(); 

while ($row = $dstmt->fetch(PDO::FETCH_ASSOC)){  
        $den_ent=$row['den_ent'];
        $cod_fiscal=$row['cod_fiscal'];
        $nr_reg_com=$row['nr_reg_com'];
        $sediu=$row['sediu'];
        $judet=$row['judet'];
        $banca=$row['banca'];
        $cont_banca=$row['cont_banca'];
   

     
}
?>
	<?php
	

if(isset($_POST['finalizare_chit'])){
	

$suma_num=$_POST['suma_num'];

$nr_chitanta=$_SESSION['nr_chitanta'];

$sfin_sql = "update $tabel_final_chitante SET suma_numere='$suma_num',gestionar='$adm_id' WHERE nrchitanta='$nr_chitanta';";    
			
			
	 
	try{
$pdo->exec($sfin_sql) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 
    	unset($_SESSION['nr_chitanta']);

			printf("<script>location.href='chitante.php'</script>");
		  
}catch(PDOException $e)
    {
    echo $sfin_sql . "<br>" . $e->getMessage();
    } }
           ?>
 <input class='btn btn-primary btn-block' type="submit" name="finalizare_chit" value="Finalizare Chitanță">
 <input class='btn btn-primary btn-block' type="submit" name="anulare_chitanta" value="Anulare Chitanta">
</form>
</div>
</form>
</div>
  </th> 
  <th >
  </th> 
  <th >
<?php 
	require "footer.php";
?>