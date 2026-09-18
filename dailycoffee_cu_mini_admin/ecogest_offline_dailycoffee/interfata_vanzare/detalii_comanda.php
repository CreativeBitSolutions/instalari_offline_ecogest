<?php
include 'database_connection.php';
 
  $title = 'Detalii comanda';

include "header.php";




if (!isset($_SESSION['com_id']))
{

printf("<script>location.href='$tabel_final_comenzi.php'</script>");

}



?>

<!-- Breadcrumbs-->
  <ol class="breadcrumb">

 <li class="breadcrumb-item">
  <a href="$tabel_final_comenzi.php">Comenzi</a>
</li>
<li class="breadcrumb-item active">Detalii comanda nr.  <?php echo $_SESSION['com_id']; ?></li>
</ol>
<div class="container">
<section class="right">




<table class='table table-bordered'>
  <tr>
    <th width="45%" colspan="2">Produs</th>

    <th>Cant.</th>
     <th >Pret</th>
     <th >Pret total</th>



  </tr>

 <?php
//afisare randuri cos
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
$valoare_vanzare_c_tva=$row['valoare_vanzare_cu_tva'];
$imagine_prod=$row['imagine'];
$tip_client=$row['tip_client'];
$com_status=$row['status'];
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

		echo"	</table>";
			
if($com_status=='NECONFIRMATA'){

echo "<form method='POST'>
 <tr><td align='center' colspan='2'><input class='btn btn-primary btn-block' type='submit' name='confirma' value='Confirma'></td></tr>";
}
    if (isset($_POST['confirma'])) {

$confirmsql="SELECT $tabel_final_comenzi_detalii.cod_p,$tabel_final_comenzi_detalii.cantitate FROM $tabel_final_comenzi_detalii where nr_comanda='$com_id'";
$confirmstmt = $pdo->prepare($confirmsql);  
$confirmstmt->execute(); 
while ($row = $confirmstmt->fetch(PDO::FETCH_ASSOC)){
    
    $produs=$row['cod_p'];
        $cantitate=$row['cantitate'];
        $stocsql="update $tabel_final_stoc set cantitate=cantitate-'$cantitate' where cod_p='$produs';";

$stocstmt = $pdo->prepare($stocsql);  
$stocstmt->execute(); 
        







}


$misc_sql = "SELECT $tabel_final_comenzi_detalii.cod_p,$tabel_final_comenzi_detalii.cantitate FROM $tabel_final_comenzi_detalii where nr_comanda='$com_id';";    
$misc_stmt = $pdo->prepare($misc_sql);  
$misc_stmt->execute(); 

while ($row = $misc_stmt->fetch(PDO::FETCH_ASSOC)){
    	date_default_timezone_set('UTC');

         $prod=$row['cod_p'];
     $qt=$row['cantitate'];

    $iessql = "insert into $tabel_final_miscari(data,cod_p,cantitate_misc,tip_miscare,fel_doc,nr_doc) values('$data_comenzii','$prod','$qt','O','FF','$com_id');";   

	 
	try{
$pdo->exec($iessql) or die(print_r($pdo->errorInfo(), true));   

}catch(PDOException $e)
    {
    echo $iessql . "<br>" . $e->getMessage();
    } 
    
}

$operator=$_SESSION['adminloggedin'];

$sql="update $tabel_final_comenzi SET status='CONFIRMATA',operator='$operator' where $tabel_final_comenzi.nr_comanda=$com_id;";
$pdo->exec($sql) or die(print_r($pdo->errorInfo(), true));


	//trimitere email 

	$subject = "Comanda efectuata, va multumim!";
$message = "
<html>
<head>
</head>
<body>
<h4>Comanda dumneavoastra la M&B COMPUTERS SHOP</h4>
<p>Buna ziua,</p>
<p>Comanda dvs. inregistrata cu numărul $com_id  a fost predata firmei de curierat.

va multumim.</p>
<h3>Produsele comenzii:</h3>
<hr><table style='background: #f5f5f5;
	border-collapse: separate;
	box-shadow: inset 0 1px 0 #fff;
	font-size: 12px;
	line-height: 24px;
	text-align: left;
	width: 100%;
	float:left;'>
  <tr>
    <th width='45%'>Produs</th>

    <th>Cant.</th>
     <th >Pret</th>
     <th >Pret total</th>



  </tr>";


$mail_sql = "SELECT $tabel_final_comenzi.ora_comenzii,$tabel_final_comenzi.den_pj,$tabel_final_comenzi.cif_pj,$tabel_final_comenzi.nr_reg_com_pj,$tabel_final_comenzi.banca_pj,$tabel_final_comenzi.cont_banca_pj,$tabel_final_comenzi.adresa_pj,$tabel_final_comenzi.judet_pj,$tabel_final_comenzi.email_pj,$tabel_final_customers.customer_preferred_contact_method,$tabel_final_customers.customer_firstname,$tabel_final_customers.customer_lastname,$tabel_final_customers.customer_address_line1,$tabel_final_customers.customer_address_line2,$tabel_final_customers.customer_address_line3,$tabel_final_customers.customer_postcode,$tabel_final_customers.customer_telephone_no,$tabel_final_customers.customer_email_address,$tabel_final_comenzi.tip_client,$tabel_final_comenzi.valoare_comanda,$tabel_final_nomenclator.imagine,$tabel_final_nomenclator.cota_tva,$tabel_final_comenzi.status,$tabel_final_comenzi.data_comenzii,$tabel_final_nomenclator.den_p,$tabel_final_nomenclator.um,$tabel_final_comenzi_detalii.cantitate,$tabel_final_comenzi_detalii.tva_col,$tabel_final_comenzi_detalii.pret_vanzare,$tabel_final_comenzi_detalii.valoare_vanzare,$tabel_final_comenzi_detalii.valoare_vanzare_cu_tva,$tabel_final_comenzi.cod_client FROM $tabel_final_comenzi_detalii inner join $tabel_final_comenzi on $tabel_final_comenzi.nr_comanda=$tabel_final_comenzi_detalii.nr_comanda INNER JOIN $tabel_final_nomenclator on $tabel_final_comenzi_detalii.cod_p=$tabel_final_nomenclator.cod_p INNER JOIN $tabel_final_customers on $tabel_final_comenzi.cod_client=$tabel_final_customers.customer_id where $tabel_final_comenzi.nr_comanda='$com_id';";    
$mail_stmt = $pdo->prepare($mail_sql);  
$mail_stmt->execute(); 

while ($row = $mail_stmt->fetch(PDO::FETCH_ASSOC)){ 
$c_tva=$row['cota_tva'];
$tip_client=$row['tip_client'];
$produs=$row['den_p'];
$um=$row['um'];
$cantitate=$row['cantitate'];
$tva_col=$row['tva_col'];
$pret_vanzare=$row['pret_vanzare'];
$pret_vanzare_c_tva=$pret_vanzare+($c_tva*$pret_vanzare/100);

$valoare_vanzare=$row['valoare_vanzare'];
$id=$row['id'];
$valoare_vanzare_c_tva=$row['valoare_vanzare_cu_tva'];
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

    $message.="<tr><td style='border-left-style:none'>$produs</td>
    <td>$cantitate</td>
    <td>$pret_vanzare_c_tva</td>
	<td>$valoare_vanzare_c_tva</td></tr> " ;
 
  }
  
  

 $cust_id=$_SESSION['cust_id'];
 $f_tot_sql = "SELECT sum($tabel_final_comenzi_detalii.valoare_vanzare_cu_tva) as a FROM $tabel_final_comenzi_detalii where nr_comanda='$com_id'; ";    
$f_tot_stmt = $pdo->prepare($f_tot_sql);  
$f_tot_stmt->execute();

while ($row = $f_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_val_vz_cu_tva=$row['a'];
}


  
 
$message.="<th style='text-align:right'colspan='3'>Total coș</th>";

 

  $message.="<th>$total_val_vz_cu_tva</th></table>";


if($tip_client=='persoana_fizica'){

$message.="<h3>Datele de facturare:</h3>
<hr>
<table>
<tr><td>Cumpărător:</td><td>$customer_firstname $customer_lastname</td></tr>
<tr><td>Adresa de email :</td><td>$customer_email_address</td></tr>
<tr><td>Număr de telefon:</td>$customer_telephone_no<td></td></tr>
<tr><td>Cod postal:</td><td>$customer_postcode</td></tr>
<tr><td>Linie adresă 1:</td><td>$customer_address_line1</td></tr>
<tr><td>Linie adresă 2:</td><td>$customer_address_line2</td></tr>
<tr><td>Linie adresă 3:</td><td>$customer_address_line3</td></tr>
</table>";

}

if($tip_client=='persoana_juridica'){

$message.="<br/><h3>Datele de facturare:</h3>
<hr>
<table>
<tr><td>Comanda efectuata de către:</td><td>$customer_firstname $customer_lastname</td></tr>
<tr><td>Denumire entitate :</td><td>$den_pj</td></tr>
<tr><td>Cod de identificare fiscala:</td>$cui_pj<td></td></tr>
<tr><td>Nr. ordine la Registrul Comertului:</td><td>$nr_reg_com_pj</td></tr>
<tr><td>Banca:</td><td>$banca_pj</td></tr>
<tr><td>Cont bancar:</td><td>$cont_pj</td></tr>
<tr><td>Judet:</td><td>$judet_pj</td></tr>
<tr><td>Sediu:</td><td>$sediu_pj</td></tr>
<tr><td>Email departament contabil:</td><td>$email_pj</td></tr>

</table>";

}
$message.="<p>In continuare, va oferim informatii detaliate:</p>


<p>Numarul de AWB va fi vizibil in sistemul de urmarire al curierului rapid in ziua urmatoare.</p>

<p>Pentru siguranta unor produse este posibil ca livrarea sa fie efectuata in ambalaje diferite, suplimentar fata de cel original.</p>

<p>Pentru plata “Ramburs” (numerar la curier) eliberarea chitantei se face exclusiv de catre firma de curierat.</p>

<p>Mentiunile cu privire la eventualele probleme privind integritatea coletelor se fac in momentul livrarii, pe AWB sau se intocmeste un proces verbal de constatare, se refuza primirea si plata coletului. Orice reclamatii ulterioare, cu privire la aceste aspecte, sunt nule.</p>

<p>Factura fiscala poate fi descarcata oricand si imprimata de pe site la sectiunea 'Comenzile mele'. Certificatul de garantie il veti gasi in colet.</p>
<h3><p>
Toate cele bune,
</p>
<p>Echipa M&B COMPUTERS SHOP

</p>
<p><a href='http://css.ecosoft-sibiu.ro/home_page.php'>http://css.ecosoft-sibiu.ro/home_page.php</a></p></h3>
</body>
</html>
";

// Always set content-type when sending HTML email
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

// More headers
$headers .= 'From: <m&bcomputers@gmail.com>' . "\r\n";

mail($customer_email_address,$subject,$message,$headers);

	
	
	
    				printf("<script>location.href='detalii_comanda.php'</script>");







}




    
    

echo  "<form method='POST'><tr><td align='center' colspan='2'><input class='btn btn-primary btn-block' type='submit' name='cancel' value='Anuleaza comanda'></td></tr></form>";

if (isset($_POST['cancel'])) {

				
$delsql="SELECT $tabel_final_comenzi_detalii.cod_p,$tabel_final_comenzi_detalii.cantitate FROM $tabel_final_comenzi_detalii where nr_comanda='$com_id'";
$delstmt = $pdo->prepare($delsql);  
$delstmt->execute(); 
while ($row = $delstmt->fetch(PDO::FETCH_ASSOC)){
    
    $produs=$row['cod_p'];
        $cantitate=$row['cantitate'];
        $stocsql="update $tabel_final_stoc set cantitate=cantitate+'$cantitate' where cod_p='$produs';";

$stocstmt = $pdo->prepare($stocsql);  
$stocstmt->execute(); 
        
        



				}		
				
				



	//trimitere email 

	$subject = "Comanda anulata!";
$message = "
<html>
<head>
</head>
<body>
<h4>Comanda dumneavoastra la M&B COMPUTERS SHOP</h4>
<p>Buna ziua,</p>
<p>Comanda dvs. inregistrata cu numarul $com_id  a fost anulata datorita neprocesarii corespunzatoare a informatiilor furnizate de catre dumneavoastra.

va multumim.</p>
<h3>Produsele comenzii:</h3>
<hr><table style='background: #f5f5f5;
	border-collapse: separate;
	box-shadow: inset 0 1px 0 #fff;
	font-size: 12px;
	line-height: 24px;
	text-align: left;
	width: 100%;
	float:left;'>
  <tr>
    <th width='45%'>Produs</th>

    <th>Cant.</th>
     <th >Pret</th>
     <th >Pret total</th>



  </tr>";


$mail_sql = "SELECT $tabel_final_comenzi.ora_comenzii,$tabel_final_comenzi.den_pj,$tabel_final_comenzi.cif_pj,$tabel_final_comenzi.nr_reg_com_pj,$tabel_final_comenzi.banca_pj,$tabel_final_comenzi.cont_banca_pj,$tabel_final_comenzi.adresa_pj,$tabel_final_comenzi.judet_pj,$tabel_final_comenzi.email_pj,$tabel_final_customers.customer_preferred_contact_method,$tabel_final_customers.customer_firstname,$tabel_final_customers.customer_lastname,$tabel_final_customers.customer_address_line1,$tabel_final_customers.customer_address_line2,$tabel_final_customers.customer_address_line3,$tabel_final_customers.customer_postcode,$tabel_final_customers.customer_telephone_no,$tabel_final_customers.customer_email_address,$tabel_final_comenzi.tip_client,$tabel_final_comenzi.valoare_comanda,$tabel_final_nomenclator.imagine,$tabel_final_nomenclator.cota_tva,$tabel_final_comenzi.status,$tabel_final_comenzi.data_comenzii,$tabel_final_nomenclator.den_p,$tabel_final_nomenclator.um,$tabel_final_comenzi_detalii.cantitate,$tabel_final_comenzi_detalii.tva_col,$tabel_final_comenzi_detalii.pret_vanzare,$tabel_final_comenzi_detalii.valoare_vanzare,$tabel_final_comenzi_detalii.valoare_vanzare_cu_tva,$tabel_final_comenzi.cod_client FROM $tabel_final_comenzi_detalii inner join $tabel_final_comenzi on $tabel_final_comenzi.nr_comanda=$tabel_final_comenzi_detalii.nr_comanda INNER JOIN $tabel_final_nomenclator on $tabel_final_comenzi_detalii.cod_p=$tabel_final_nomenclator.cod_p INNER JOIN $tabel_final_customers on $tabel_final_comenzi.cod_client=$tabel_final_customers.customer_id where $tabel_final_comenzi.nr_comanda='$com_id';";    
$mail_stmt = $pdo->prepare($mail_sql);  
$mail_stmt->execute(); 

while ($row = $mail_stmt->fetch(PDO::FETCH_ASSOC)){ 
$c_tva=$row['cota_tva'];
$tip_client=$row['tip_client'];
$produs=$row['den_p'];
$um=$row['um'];
$cantitate=$row['cantitate'];
$tva_col=$row['tva_col'];
$pret_vanzare=$row['pret_vanzare'];
$pret_vanzare_c_tva=$pret_vanzare+($c_tva*$pret_vanzare/100);
$valoare_vanzare=$row['valoare_vanzare'];
$id=$row['id'];
$valoare_vanzare_c_tva=$row['valoare_vanzare_cu_tva'];
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

    $message.="<tr><td style='border-left-style:none'>$produs</td>
    <td>$cantitate</td>
    <td>$pret_vanzare_c_tva</td>
	<td>$valoare_vanzare_c_tva</td></tr> " ;
 
  }
  
  

 $cust_id=$_SESSION['cust_id'];
 $f_tot_sql = "SELECT sum($tabel_final_comenzi_detalii.valoare_vanzare_cu_tva) as b FROM $tabel_final_comenzi_detalii where nr_comanda='$com_id'; ";    
$f_tot_stmt = $pdo->prepare($f_tot_sql);  
$f_tot_stmt->execute();

while ($row = $f_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_val_vz_cu_tva=$row['b'];
}


  
 
$message.="<th style='text-align:right'colspan='3'>Total coș</th>";

 

  $message.="<th>$total_val_vz_cu_tva</th></table>";


if($tip_client=='persoana_fizica'){

$message.="<h3>Datele de facturare:</h3>
<hr>
<table>
<tr><td>Cumpărător:</td><td>$customer_firstname $customer_lastname</td></tr>
<tr><td>Adresa de email :</td><td>$customer_email_address</td></tr>
<tr><td>Număr de telefon:</td>$customer_telephone_no<td></td></tr>
<tr><td>Cod postal:</td><td>$customer_postcode</td></tr>
<tr><td>Linie adresă 1:</td><td>$customer_address_line1</td></tr>
<tr><td>Linie adresă 2:</td><td>$customer_address_line2</td></tr>
<tr><td>Linie adresă 3:</td><td>$customer_address_line3</td></tr>
</table>";

}

if($tip_client=='persoana_juridica'){

$message.="<br/><h3>Datele de facturare:</h3>
<hr>
<table>
<tr><td>Comanda efectuata de către:</td><td>$customer_firstname $customer_lastname</td></tr>
<tr><td>Denumire entitate :</td><td>$den_pj</td></tr>
<tr><td>Cod de identificare fiscala:</td>$cui_pj<td></td></tr>
<tr><td>Nr. ordine la Registrul Comertului:</td><td>$nr_reg_com_pj</td></tr>
<tr><td>Banca:</td><td>$banca_pj</td></tr>
<tr><td>Cont bancar:</td><td>$cont_pj</td></tr>
<tr><td>Judet:</td><td>$judet_pj</td></tr>
<tr><td>Sediu:</td><td>$sediu_pj</td></tr>
<tr><td>Email departament contabil:</td><td>$email_pj</td></tr>

</table>";

}
$message.="<p>Pentru mai multe detalii nu ezitati sa ne contactati.</p>
<h3><p>
Toate cele bune,
</p>
<p>Echipa M&B COMPUTERS SHOP

</p>
<p><a href='http://css.ecosoft-sibiu.ro/home_page.php'>http://css.ecosoft-sibiu.ro/home_page.php</a></p></h3>
</body>
</html>
";

// Always set content-type when sending HTML email
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

// More headers
$headers .= 'From: <m&bcomputers@gmail.com>' . "\r\n";

mail($customer_email_address,$subject,$message,$headers);

	
	
	$delsql="DELETE FROM $tabel_final_comenzi_detalii where nr_comanda='$com_id';DELETE FROM $tabel_final_comenzi where nr_comanda='$com_id'";
$delstmt = $pdo->prepare($delsql);  
$delstmt->execute(); 
	
			printf("<script>location.href='$tabel_final_comenzi.php'</script>");



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
 

</section>



<?php include 'footer.php'; ?>
