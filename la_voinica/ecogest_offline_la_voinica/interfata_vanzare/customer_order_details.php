<?php session_start();
	include 'database_connection.php';
	date_default_timezone_set('Europe/Bucharest');

	
	include "website_header.php";
				printf("<script>location.href='customer_order_details.php#bottomOfPage'</script>");

  if (!isset($_SESSION['com_id']))
	   {
		   
		   	printf("<script>location.href='customer_orders.php#bottomOfPage'</script>");

	   }

?>

	
  
<!-- about -->
 <a name="bottomOfPage"></a>
 
		<style>
	h3 {
	margin:2em;
	
}


	</style>
<div class="article">
<h3 style="margin:2em" align="center">Comanda dumneavoastră</h3>
<div class="container">

<table class='table table-bordered'>
  <tr>
    <th width="45%" colspan="2">Produs</th>

    <th>Cant.</th>
     <th >Pret</th>
     <th >Pret total</th>



  </tr>
 

  
  <?php
//afisare randuri cos
		$cust_id=$_SESSION['cust_id'];
$com_id=$_SESSION['com_id'];
$f_sql = "SELECT $tabel_final_comenzi.ora_comenzii,$tabel_final_comenzi.den_pj,$tabel_final_comenzi.cif_pj,$tabel_final_comenzi.nr_reg_com_pj,$tabel_final_comenzi.banca_pj,$tabel_final_comenzi.cont_banca_pj,$tabel_final_comenzi.adresa_pj,$tabel_final_comenzi.judet_pj,$tabel_final_comenzi.email_pj,$tabel_final_customers.customer_preferred_contact_method,$tabel_final_customers.customer_firstname,$tabel_final_customers.customer_lastname,$tabel_final_customers.customer_address_line1,$tabel_final_customers.customer_address_line2,$tabel_final_customers.customer_address_line3,$tabel_final_customers.customer_postcode,$tabel_final_customers.customer_telephone_no,$tabel_final_customers.customer_email_address,$tabel_final_comenzi.tip_client,$tabel_final_comenzi.valoare_comanda,$tabel_final_nomenclator.imagine,$tabel_final_nomenclator.cota_tva,$tabel_final_comenzi.status,$tabel_final_comenzi.data_comenzii,$tabel_final_nomenclator.den_p,$tabel_final_nomenclator.um,$tabel_final_comenzi_detalii.cantitate,$tabel_final_comenzi_detalii.tva_col,$tabel_final_comenzi_detalii.pret_vanzare,$tabel_final_comenzi_detalii.valoare_vanzare,$tabel_final_comenzi_detalii.valoare_vanzare_cu_tva,$tabel_final_comenzi.cod_client FROM $tabel_final_comenzi_detalii inner join $tabel_final_comenzi on $tabel_final_comenzi.nr_comanda=$tabel_final_comenzi_detalii.nr_comanda INNER JOIN $tabel_final_nomenclator on $tabel_final_comenzi_detalii.cod_p=$tabel_final_nomenclator.cod_p INNER JOIN $tabel_final_customers on $tabel_final_comenzi.cod_client=$tabel_final_customers.customer_id where $tabel_final_comenzi.nr_comanda='$com_id';"; 


$f_stmt = $pdo->prepare($f_sql);  
$f_stmt->execute(); 

while ($row = $f_stmt->fetch(PDO::FETCH_ASSOC)){ 
    
$_SESSION['cust_id']=$row['cod_client'];
$c_tva=$row['cota_tva'];
$produs=$row['den_p'];
$um=$row['um'];
$cantitate=$row['cantitate'];
$tva_col=$row['tva_col'];
$pret_vanzare=$row['pret_vanzare'];
$pret_vanzare_c_tva=$pret_vanzare+($c_tva*$pret_vanzare/100);
$valoare_vanzare=$row['valoare_vanzare'];
$id=$row['id'];
$com_status=$row['status'];
$valoare_vanzare_c_tva=$row['valoare_vanzare_cu_tva'];
$imagine_prod=$row['imagine'];
$tip_client=$row['tip_client'];
$den_pj=$row['den_pj'];
$cui_pj=$row['cif_pj'];
$nr_reg_com_pj=$row['nr_reg_com_pj'];
$banca_pj=$row['banca_pj'];
$cont_pj=$row['cont_banca_pj'];
$email_pj=$row['email_pj'];
$judet_pj=$row['judet_pj'];
$sediu_pj=$row['adresa_pj'];
$data_comenzii=$row['data_comenzii'];
$ora_comenzii=$row['ora_comenzii'];
$customer_firstname=$row['customer_firstname'];
$customer_lastname=$row['customer_lastname'];
$customer_address_line1=$row['customer_address_line1'];
$customer_address_line2=$row['customer_address_line2'];
$customer_address_line3=$row['customer_address_line3'];
$customer_postcode=$row['customer_postcode'];
$customer_telephone_no=$row['customer_telephone_no'];
$customer_email_address=$row['customer_email_address'];
	$customer_preferred_contact_method=$row['customer_preferred_contact_method'];
	$total_val_vz_cu_tva=$row['valoare_comanda'];
  
    echo '<tr><td style="border-right-style:none" width="400px">' . '<img width = "100%" height="180px" alt="Experience Photo" src="images/' . $imagine_prod . '"/>' . '</td>';
    echo"<td style='border-left-style:none'>$produs</td>
    <td>$cantitate</td>
    <td>$pret_vanzare_c_tva</td>
	<td>$valoare_vanzare_c_tva</td>


  </tr>
  
  
  
  " 
  
  ;
 
  }
  
  
 
  ?>
  
  <th nowrap style="border-right-style:none">Data comenzii: <?php echo $data_comenzii ." " .$ora_comenzii; ?></th>
 <th style='text-align:right; border-left-style:none' colspan="3">Total coș</th>  

<th >


<?php 
  echo $total_val_vz_cu_tva;?>
  </th>

  
  
</table>

	
		 	<table style="font-size:15px" class='table table-borderless'> 


<?php
	if($tip_client=="persoana_fizica") {
								
							
		   
echo "<tr><td align='center' colspan='2'><h3> Detalii facturare<td></h3></tr>

    <tr>
				   <th nowrap>Prenume:</th>
			 <td>   $customer_firstname
				</td></tr>
				
				   <th nowrap>Nume:</th>
			   <td> $customer_lastname
				</td></tr>
				<tr>
				   <th nowrap>Linie de adresa 1:</th>
			 <td> $customer_address_line1
				</td></tr>
				<tr>
				   <th nowrap>Linie de adresa 2:</th>
			 <td> $customer_address_line2 
				</td></tr>
				<tr>
				   <th nowrap>Linie de adresa 3:</th>
			<td>  $customer_address_line3
				</td></tr>
				<tr>
				   <th nowrap>Cod postal:</th>
			 <td> $customer_postcode 
				</td></tr>
					<tr>
				   <th nowrap>Numar de telefon:</th>
			  <td> $customer_telephone_no 
				</td></tr>
					<tr>
				   <th nowrap>Adresa de email:</th>
			  <td>  $customer_email_address
				</td></tr>
				
					<tr>
				   <th nowrap>Metoda de contactare preferata:</th>
			  <td>  $customer_preferred_contact_method
				</td></tr>
	  

			
							";}
							
											else{
			echo "<tr><td align='center' colspan='2'><h3> Detalii facturare<td></h3></tr>


    <tr>
				   <th nowrap>Denumirea entitatii :</TH>
				<td>	$den_pj

				</td></tr>
				<tr>
				   <th nowrap>Cod fiscal(C.I.F.):</th>
					<td>$cui_pj
				</td></tr>
				<tr>
				   <th nowrap>Nr. de ordine la Registrul Comertului:</th>
				<td>	$nr_reg_com_pj
				</td></tr>
				<tr>
				   <th nowrap>Banca:</th>
				<td>	$banca_pj
				</td></tr>
				<tr>
				   <th nowrap>IBAN cont bancar:</th>
				<td>	$cont_pj
				</td></tr>
				
					<tr>
				   <th nowrap>Email departament contabil:</th>
				<td>	$email_pj
				</td></tr>
					<tr>
				   <th nowrap>Judet:</th>
				<td>	$judet_pj
				</td></tr>
				<tr>
				   <th nowrap>Sediu:</th>
				<td>	$sediu_pj
				</td></tr>
				<tr>
				   <th nowrap>Booked by:</th>
				<td>	$customer_email_address
				</td></tr>
	   

			

												
												
												
												";
												
												
											}
											
											
			echo "</br>";
if($com_status=='CONFIRMATA'){

echo "<form method='POST'>
 <tr><td align='center' colspan='2'><input class='btn btn-primary btn-block' type='submit' name='list_com' value='Listeaza factura'></td></tr></form></table>";
}

if(isset($_POST['list_com'])){
    
    $_SESSION['tip_client']=$tip_client;
    $_SESSION['com_id']=$com_id;

    
printf("<script>location.href='listeaza_com.php'</script>");

}								
											
											
											
?>
			</table>
			
				</div>
				
<div class="clearfix"></div>
</div>
</div><br>
<!-- /about -->

<?php 
	require "website_footer.php";
?>