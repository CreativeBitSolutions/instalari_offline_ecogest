<?php session_start();
	include 'database_connection.php';
	date_default_timezone_set('Europe/Bucharest');

	
	include "website_header.php";
				printf("<script>location.href='cos.php#bottomOfPage'</script>");



?>

	
  
<!-- about -->
 <a name="bottomOfPage"></a>
 
		<style>
	h3 {
	margin:2em;
	
}


	</style>
<div class="article">
<h3 style="margin:2em" align="center">Coșul dumneavoastră</h3><hr>
<div class="container">

<table class='table table-bordered'>
  <tr>
    <th width="45%" colspan="2">Produs</th>

    <th>Cant.</th>
     <th >Pret</th>
     <th >Pret total</th>

    <th>Optiuni</th>
	

  </tr>
 

  
  <?php
//afisare randuri cos
		$cust_id=$_SESSION['cust_id'];

$f_sql = "SELECT $tabel_final_nomenclator.imagine,$tabel_final_cosuri.cod_p,$tabel_final_nomenclator.cota_tva,$tabel_final_nomenclator.den_p,$tabel_final_nomenclator.um,$tabel_final_cosuri.cantitate,$tabel_final_cosuri.tva_col,$tabel_final_cosuri.pret_vanzare,$tabel_final_cosuri.valoare_vanzare,$tabel_final_cosuri.valoare_vanzare_cu_tva,$tabel_final_cosuri.id from $tabel_final_cosuri INNER JOIN $tabel_final_nomenclator on $tabel_final_cosuri.cod_p=$tabel_final_nomenclator.cod_p where cod_client='$cust_id';";    
$f_stmt = $pdo->prepare($f_sql);  
$f_stmt->execute(); 

while ($row = $f_stmt->fetch(PDO::FETCH_ASSOC)){ 
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

  
    echo '<tr><td style="border-right-style:none" width="400px">' . '<img width = "100%" height="180px" alt="Experience Photo" src="images/' . $imagine_prod . '"/>' . '</td>';
    echo"<td style='border-left-style:none'>$produs</td>
    <td>$cantitate</td>
    <td>$pret_vanzare_c_tva</td>
	<td>$valoare_vanzare_c_tva</td>
	<td><form method='post'><input class='btn btn-primary btn-block' type='submit' value='Sterge' name='$id'></form></td>

  </tr>
  
  
  
  " 
  
  ;
  if (isset($_POST[$id])) {
					
					$sterg_sql="SELECT * from $tabel_final_cosuri where $tabel_final_cosuri.id = '$id' ";
					$sterg_f_stmt = $pdo->prepare($sterg_sql);  
$sterg_f_stmt->execute(); 

while ($row = $sterg_f_stmt->fetch(PDO::FETCH_ASSOC)){ 

$c_p=$row['cod_p'];
$cant_vand=$row['cantitate'];
}
					
					$sql="DELETE from $tabel_final_cosuri WHERE $tabel_final_cosuri.id = '$id';";
$pdo->exec($sql) or die(print_r($pdo->errorInfo(), true));
      
			printf("<script>location.href='cos.php'</script>");
				}
  }
  
  
 
  ?>
 <th style='text-align:right'colspan="4">Total coș</th>  

<?php 
 $cust_id=$_SESSION['cust_id'];
 $f_tot_sql = "SELECT sum($tabel_final_cosuri.valoare_vanzare) as a from $tabel_final_cosuri  where cod_client='$cust_id'; ";    
$f_tot_stmt = $pdo->prepare($f_tot_sql);  
$f_tot_stmt->execute();

while ($row = $f_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$valoare_f_vz=$row['a'];
}
   ?>
 
   

 




 
  
  
  
 <?php 
 $cust_id=$_SESSION['cust_id'];
 $f_tot_sql = "SELECT sum($tabel_final_cosuri.tva_col) as b from $tabel_final_cosuri  where cod_client='$cust_id'; ";    
$f_tot_stmt = $pdo->prepare($f_tot_sql);  
$f_tot_stmt->execute();

while ($row = $f_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_tva_col=$row['b'];
}
   ?>
  
  
 		 <?php
	 	  	 $cust_id=$_SESSION['cust_id'];
$csql = "SELECT * from $tabel_final_customers WHERE customer_id='$cust_id'";    
$cstmt = $pdo->prepare($csql);  
$cstmt->execute(); 


while ($row = $cstmt->fetch(PDO::FETCH_ASSOC)){  
       $customer_firstname=$row['customer_firstname'];
       $customer_lastname=$row['customer_lastname'];
       $customer_title=$row['customer_title'];
$customer_address_line1=$row['customer_address_line1'];
$customer_address_line2=$row['customer_address_line2'];
$customer_address_line3=$row['customer_address_line3'];
$customer_postcode=$row['customer_postcode'];
$customer_telephone_no=$row['customer_telephone_no'];
$customer_email_address=$row['customer_email_address'];

}
					

?>
  
  <th ><style>details summary{
	font-family:tahoma;
	font-size: 90%;
    margin-bottom:1em;
	color:#007bff;
}
details summary:hover{
	cursor:pointer;
	font-size:110%;
}</style>
 <?php 
 $cust_id=$_SESSION['cust_id'];
 $f_tot_sql = "SELECT sum($tabel_final_cosuri.valoare_vanzare_cu_tva) as c from $tabel_final_cosuri where cod_client='$cust_id'; ";    
$f_tot_stmt = $pdo->prepare($f_tot_sql);  
$f_tot_stmt->execute();

while ($row = $f_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_val_vz_cu_tva=$row['c'];
}
  echo $total_val_vz_cu_tva;
  
  
  
  
  
if(isset($_POST['trimite_comanda_individ'])){
	
$tip_client="persoana_fizica";
	$data_comenzii=date("Y-m-d");
		$ora_comenzii=date("H:i:s");
echo $ora_comenzii;
	$cust_id=$_SESSION['cust_id'];

$com_sql = "INSERT INTO $tabel_final_comenzi(cod_client,data_comenzii,ora_comenzii,tip_client) values('$cust_id','$data_comenzii','$ora_comenzii','$tip_client');";    
$com_stmt = $pdo->prepare($com_sql);  
$com_stmt->execute(); 

$ccom_sql = "SELECT max(nr_comanda) as nr_c FROM $tabel_final_comenzi ";    
$ccom_stmt = $pdo->prepare($ccom_sql);  
$ccom_stmt->execute(); 
while ($row = $ccom_stmt->fetch(PDO::FETCH_ASSOC)){ 
    $nr_com=$row['nr_c'];
}


$cos_sql = "SELECT $tabel_final_cosuri.cod_p,$tabel_final_cosuri.cantitate,$tabel_final_cosuri.tva_col,$tabel_final_cosuri.pret_vanzare,$tabel_final_cosuri.valoare_vanzare,$tabel_final_cosuri.valoare_vanzare_cu_tva,$tabel_final_cosuri.id from $tabel_final_cosuri where cod_client='$cust_id';";    
$cos_stmt = $pdo->prepare($cos_sql);  
$cos_stmt->execute(); 

while ($row = $cos_stmt->fetch(PDO::FETCH_ASSOC)){ 
    
$cod_p=$row['cod_p'];
$cantitate=$row['cantitate'];
$tva_col=$row['tva_col'];
$pret_vanzare=$row['pret_vanzare'];
$valoare_vanzare=$row['valoare_vanzare'];
$valoare_vanzare_c_tva=$row['valoare_vanzare_cu_tva'];


$com_det_sql = "INSERT INTO $tabel_final_comenzi_detalii(nr_comanda,cod_p,cantitate,tva_col,pret_vanzare,valoare_vanzare,valoare_vanzare_cu_tva) values('$nr_com','$cod_p','$cantitate','$tva_col','$pret_vanzare','$valoare_vanzare','$valoare_vanzare_c_tva');";    

    
try{
$pdo->exec($com_det_sql) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 

}catch(PDOException $e)
    {
    echo $com_det_sql . "<br>" . $e->getMessage();
    } 


    
}

 
	//trimitere email 

	$subject = "Comanda dvs. la M&B COMPUTERS SHOP!";
$message = "
<html>
<head>
</head>
<body>
<h4>Comanda dumneavoastra la M&B COMPUTERS SHOP</h4>
<p>Buna ziua,</p>
<p>Comanda dvs. a fost trimisa si inregistrata cu numarul $nr_com, va multumim.</p>
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

	
	
		$cust_id=$_SESSION['cust_id'];
$mail_sql = "SELECT $tabel_final_cosuri.cod_p,$tabel_final_nomenclator.cota_tva,$tabel_final_nomenclator.den_p,$tabel_final_nomenclator.um,$tabel_final_cosuri.cantitate,$tabel_final_cosuri.tva_col,$tabel_final_cosuri.pret_vanzare,$tabel_final_cosuri.valoare_vanzare,$tabel_final_cosuri.valoare_vanzare_cu_tva,$tabel_final_cosuri.id from $tabel_final_cosuri INNER JOIN $tabel_final_nomenclator on $tabel_final_cosuri.cod_p=$tabel_final_nomenclator.cod_p where cod_client='$cust_id';";    
$mail_stmt = $pdo->prepare($mail_sql);  
$mail_stmt->execute(); 

while ($row = $mail_stmt->fetch(PDO::FETCH_ASSOC)){ 
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

    $message.="<tr><td style='border-left-style:none'>$produs</td>
    <td>$cantitate</td>
    <td>$pret_vanzare_c_tva</td>
	<td>$valoare_vanzare_c_tva</td></tr> " ;
 
  }
  
  
 
  
 
$message.="<th style='text-align:right'colspan='3'>Total coș</th>";

 

  $message.="<th>$total_val_vz_cu_tva</th></table>";




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
</table>

<p>Acesta este un mesaj automat de preluare a comenzii. Veti fi contactat in scurt timp de un operator pentru finalizarea comenzii.

Incheierea contractului are loc in momentul emiterii facturii fiscale si nu la lansarea comenzii sau emiterea confirmarii automate de primire a acestei $tabel_final_comenzi. 

Veti fi contactat in cel mai scurt timp de un consultant de $tabel_final_vanzari pentru a stabili detalii referitoare la finalizarea acesteia. 

Pentru orice detalii suplimentare sau modificari asupra comenzii, nu ezitati sa ne contactati.</p>
<h3><p>
Toate cele bune,
</p>
<p>Echipa M&B COMPUTERS SHOP

</p>
<p><a href='home_page.php'>home_page.php</a></p></h3>
</body>
</html>
";

// Always set content-type when sending HTML email
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

// More headers
$headers .= 'From: <m&bcomputers@gmail.com>' . "\r\n";

mail($customer_email_address,$subject,$message,$headers);

	
	
	
	
	
	
	
	
	
	
	
	
	
	
$fin_sql = "update $tabel_final_comenzi SET tva_colectata='$total_tva_col',valoare_fara_tva='$valoare_f_vz',valoare_comanda='$total_val_vz_cu_tva' WHERE nr_comanda='$nr_com';DELETE from $tabel_final_cosuri where cod_client='$cust_id';";    
			
			
	 
	try{
$pdo->exec($fin_sql) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 
     	printf("<script>alert('Comanda dumneavoastra a fost trimisa spre procesare. De asemenea, ati primit un mesaj de confirmare pe email.');
</script>");
			printf("<script>location.href='customer_orders.php'</script>");
		  
}catch(PDOException $e)
    {
    echo $fin_sql . "<br>" . $e->getMessage();
    } 
	
	
	
	
}


if(isset($_POST['trimite_comanda_companie'])){
	
$tip_client="persoana_juridica";
	$data_comenzii=date("Y-m-d");
		$ora_comenzii=date("H:i:s");
echo $ora_comenzii;
	$cust_id=$_SESSION['cust_id'];
	$den_pj=$_POST['den_pj'];
					$cui_pj=$_POST['cui_pj'];
					$nr_reg_com_pj=$_POST['nr_reg_com_pj'];
					$banca_pj=$_POST['banca_pj'];
					$cont_pj=$_POST['cont_pj'];
					$acc_email=$_POST['email_pj'];
					$judet_pj=$_POST['judet_pj'];
					$adresa_pj=$_POST['adresa_pj'];

$comp_com_sql = "INSERT INTO $tabel_final_comenzi(cod_client,tip_client,data_comenzii,ora_comenzii,den_pj,adresa_pj,judet_pj,cif_pj,nr_reg_com_pj,cont_banca_pj,banca_pj,email_pj) values('$cust_id','$tip_client','$data_comenzii','$ora_comenzii','$den_pj','$adresa_pj','$judet_pj','$cui_pj','$nr_reg_com_pj','$cont_pj','$banca_pj','$acc_email');";  
try{
$pdo->exec($comp_com_sql) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 

}catch(PDOException $e)
    {
    echo $comp_com_sql . "<br>" . $e->getMessage();
    } 


$ccom_sql = "SELECT max(nr_comanda) as nr_c FROM $tabel_final_comenzi ";    
$ccom_stmt = $pdo->prepare($ccom_sql);  
$ccom_stmt->execute(); 
while ($row = $ccom_stmt->fetch(PDO::FETCH_ASSOC)){ 
    $nr_com=$row['nr_c'];
}


$cos_sql = "SELECT $tabel_final_cosuri.cod_p,$tabel_final_cosuri.cantitate,$tabel_final_cosuri.tva_col,$tabel_final_cosuri.pret_vanzare,$tabel_final_cosuri.valoare_vanzare,$tabel_final_cosuri.valoare_vanzare_cu_tva,$tabel_final_cosuri.id from $tabel_final_cosuri where cod_client='$cust_id';";    
$cos_stmt = $pdo->prepare($cos_sql);  
$cos_stmt->execute(); 

while ($row = $cos_stmt->fetch(PDO::FETCH_ASSOC)){ 
    
$cod_p=$row['cod_p'];
$cantitate=$row['cantitate'];
$tva_col=$row['tva_col'];
$pret_vanzare=$row['pret_vanzare'];
$valoare_vanzare=$row['valoare_vanzare'];
$valoare_vanzare_c_tva=$row['valoare_vanzare_cu_tva'];
$com_det_sql = "INSERT INTO $tabel_final_comenzi_detalii(nr_comanda,cod_p,cantitate,tva_col,pret_vanzare,valoare_vanzare,valoare_vanzare_cu_tva) values('$nr_com','$cod_p','$cantitate','$tva_col','$pret_vanzare','$valoare_vanzare','$valoare_vanzare_c_tva');";    

    
try{
$pdo->exec($com_det_sql) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 

}catch(PDOException $e)
    {
    echo $com_det_sql . "<br>" . $e->getMessage();
    } 


    
}



//trimitere email 
	
		$subject = "Comanda dvs. la M&B COMPUTERS SHOP!";
$message = "
<html>
<head>
</head>
<body>
<h4>Comanda dumneavoastra la M&B COMPUTERS SHOP</h4>
<p>Buna ziua,</p>
<p>Comanda dvs. a fost trimisa si inregistrata cu numarul $nr_com, va multumim.</p>
<h3>Produsele comenzii:</h3>
<hr><table style='background: #f5f5f5;
	border-collapse: separate;
	box-shadow: inset 0 1px 0 #fff;
	font-size: 12px;
	line-height: 24px;
	text-align: left;
	width: 100%;
	float:left;
'>
  <tr>
    <th width='45%'>Produs</th>

    <th>Cant.</th>
     <th >Pret</th>
     <th >Pret total</th>



  </tr>";
		$cust_id=$_SESSION['cust_id'];
$mail_sql = "SELECT $tabel_final_cosuri.cod_p,$tabel_final_nomenclator.cota_tva,$tabel_final_nomenclator.den_p,$tabel_final_nomenclator.um,$tabel_final_cosuri.cantitate,$tabel_final_cosuri.tva_col,$tabel_final_cosuri.pret_vanzare,$tabel_final_cosuri.valoare_vanzare,$tabel_final_cosuri.valoare_vanzare_cu_tva,$tabel_final_cosuri.id from $tabel_final_cosuri INNER JOIN $tabel_final_nomenclator on $tabel_final_cosuri.cod_p=$tabel_final_nomenclator.cod_p where cod_client='$cust_id';";    
$mail_stmt = $pdo->prepare($mail_sql);  
$mail_stmt->execute(); 

while ($row = $mail_stmt->fetch(PDO::FETCH_ASSOC)){ 
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

    $message.="<tr><td style='border-left-style:none'>$produs</td>
    <td>$cantitate</td>
    <td>$pret_vanzare_c_tva</td>
	<td>$valoare_vanzare_c_tva</td></tr> " ;
 
  }
  
  
 
  
 
$message.="<th style='text-align:right'colspan='3'>Total coș</th>";

 

  $message.="<th>$total_val_vz_cu_tva</th></table>";




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
<tr><td>Sediu:</td><td>$adresa_pj</td></tr>
<tr><td>Email departament contabil:</td><td>$acc_email</td></tr>

</table>

<p>Acesta este un mesaj automat de preluare a comenzii. Veti fi contactat in scurt timp de un operator pentru finalizarea comenzii.

Incheierea contractului are loc in momentul emiterii facturii fiscale si nu la lansarea comenzii sau emiterea confirmarii automate de primire a acestei $tabel_final_comenzi. 

Veti fi contactat in cel mai scurt timp de un consultant de $tabel_final_vanzari pentru a stabili detalii referitoare la finalizarea acesteia. 

Pentru orice detalii suplimentare sau modificari asupra comenzii, nu ezitati sa ne contactati.</p>
<h3><p>
Toate cele bune,
</p>
<p>Echipa M&B COMPUTERS SHOP

</p>
<p><a href='home_page.php'>home_page.php</a></p></h3>
</body>
</html>
";

// Always set content-type when sending HTML email
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

// More headers
$headers .= 'From: <m&bcomputers@gmail.com>' . "\r\n";

mail($customer_email_address,$subject,$message,$headers);

	
	
	
	
	





















$fin_sql = "update $tabel_final_comenzi SET tva_colectata='$total_tva_col',valoare_fara_tva='$valoare_f_vz',valoare_comanda='$total_val_vz_cu_tva' WHERE nr_comanda='$nr_com';DELETE from $tabel_final_cosuri where cod_client='$cust_id';";    
			
			
	 
	try{
$pdo->exec($fin_sql) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 
     	printf("<script>alert('Comanda dumneavoastra a fost trimisa spre procesare. De asemenea, ati primit un mesaj de confirmare pe email.');
</script>");
			printf("<script>location.href='customer_orders.php'</script>");
		  
}catch(PDOException $e)
    {
    echo $fin_sql . "<br>" . $e->getMessage();
    } 
 
	
}

  ?>
  </th>
  <th >-</th>  

  
  
</table>

	


<h5 style="margin-bottom:10px;">Doresc emiterea facturii pe:</h5>

<details open><summary>Persoana fizica</summary>

<form method="POST" >

                <div class="form-group">
				    <label for="exampleInputEmail1">Prenume:</label>
			        <input readonly class="form-control" id="exampleInputEmail1" type="text" name="customer_firstname" value="<?php echo $customer_firstname; ?>" />
				</div>
				<div class="form-group">
				    <label for="exampleInputEmail1">Nume:</label>
			        <input readonly class="form-control" id="exampleInputEmail1" type="text" name="customer_lastname" value="<?php echo $customer_lastname; ?>" />
				</div>
				<div class="form-group">
				    <label for="exampleInputEmail1">Linie adresa 1:</label>
			        <input readonly class="form-control" id="exampleInputEmail1" type="text" name="customer_address_line1" value="<?php echo $customer_address_line1; ?>" />
				</div>
				<div class="form-group">
				    <label for="exampleInputEmail1">Linie adresa 2:</label>
			        <input readonly class="form-control" id="exampleInputEmail1" type="text" name="customer_address_line2" value="<?php echo $customer_address_line2; ?>" />
				</div>
				<div class="form-group">
				    <label for="exampleInputEmail1">Linie adresa 3:</label>
			        <input readonly class="form-control" id="exampleInputEmail1" type="text" name="customer_address_line3" value="<?php echo $customer_address_line3; ?>" />
				</div>
				<div class="form-group">
				    <label for="exampleInputEmail1">Cod postal:</label>
			        <input readonly class="form-control" id="exampleInputEmail1" type="text" name="customer_postcode" value="<?php echo $customer_postcode; ?>" />
				</div>
					<div class="form-group">
				    <label for="exampleInputEmail1">Numar de telefon:</label>
			        <input readonly class="form-control" id="exampleInputEmail1" type="text" name="customer_telephone_no" value="<?php echo $customer_telephone_no; ?>" />
				</div>
					<div class="form-group">
				    <label for="exampleInputEmail1">Adresa de email:</label>
			        <input readonly class="form-control" id="exampleInputEmail1" type="text" name="customer_email_address" value="<?php echo $customer_email_address; ?>" />
				</div>
				
				<br>
	  <input class="btn btn-primary btn-block" type="submit" name="trimite_comanda_individ" value="Trimite Comanda">
				</form>				</details>
			
<br>

						<details><summary>Persoana juridica</summary>	

			<form method="POST" >

                <div class="form-group">
				    <label for="exampleInputEmail1">Denumire entitate:</label>
			        <input class="form-control" id="exampleInputEmail1" type="text" name="den_pj"/>
				</div>
				<div class="form-group">
				    <label for="exampleInputEmail1">Codf fiscal (C.I.F.):</label>
			        <input class="form-control" id="exampleInputEmail1" type="text" name="cui_pj"/>
				</div>
				<div class="form-group">
				    <label for="exampleInputEmail1">Numarul de ordine de la Registrul Comertului:</label>
			        <input class="form-control" id="exampleInputEmail1" type="text" name="nr_reg_com_pj" />
				</div>
				<div class="form-group">
				    <label for="exampleInputEmail1">Banca :</label>
			        <input class="form-control" id="exampleInputEmail1" type="text" name="banca_pj" />
				</div>
				<div class="form-group">
				    <label for="exampleInputEmail1">IBAN cont bancar:</label>
			        <input class="form-control" id="exampleInputEmail1" type="text" name="cont_pj" />
				</div>
				
					<div class="form-group">
				    <label for="exampleInputEmail1">Adresa de email departament contabil:</label>
			        <input class="form-control" id="exampleInputEmail1" type="text" name="email_pj"  />
				</div>
					<div class="form-group">
				    <label for="exampleInputEmail1">Judet:</label>
			        <input class="form-control" id="exampleInputEmail1" type="text" name="judet_pj"  />
				</div>
				<div class="form-group">
				    <label for="exampleInputEmail1">Sediu:</label>
			        <input class="form-control" id="exampleInputEmail1" type="text" name="adresa_pj" />
				</div>
				
				<br>



	  <input class="btn btn-primary btn-block" type="submit" name="trimite_comanda_companie" value="Trimite comanda">

			
				</form><br>	
				</div>
				
<div class="clearfix"></div>
</div>
</div><br>
<!-- /about -->

<?php 
	require "website_footer.php";
?>
