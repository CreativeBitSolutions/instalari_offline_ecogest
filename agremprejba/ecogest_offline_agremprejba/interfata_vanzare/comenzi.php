<?php  include('session.php');
	$title = 'Comenzi';
  include 'header.php';
	
?>

	
	   
          
			
				

		<ol class="breadcrumb">
<li class="breadcrumb-item active">Comenzi</li>
</ol>

	 <div class="card mb-3">
        <div class="card-header">
          <i class="fa fa-table"></i> Lista Comenzilor</div>
        <div class="card-body">
            
                  <form style="float:right;" method="post" class="form-inline my-2 my-lg-0 mr-lg-2">
<div style=" width:23em;" class="input-group">
<label>Cauta dupa data</label><input class="form-control" type="date" name="search">
<span class="input-group-btn">
<button class="btn btn-primary" type="submit">
<i class="fa fa-search"></i>
</button>
</span>
</div>
</form>
            
            <?php
            
            if (isset($_POST['search'])) {
$searchq = $_POST['search'];

$search_query="SELECT * FROM $tabel_final_comenzi WHERE $tabel_final_comenzi.data_comenzii= '$searchq'";
$query=$pdo->prepare($search_query);
$query->execute();
$count=$query->rowCount();
   

 if($count == 0) {
         
         
         
     
  echo "<br><h3 align='center'>Nicio comanda gasita pentru aceasta data!</h3>";
 } else {
     echo "<div style='display:block' class='table-responsive'>";
     echo "<table class='table table-bordered' id='dataTable' width='100%' cellspacing='0'>"; 
echo" <thead><tr><th>Număr Comandă</th><th>Data Comenzii</th><th>Valoare Comandă</th><th>Stare</th><th colspan='3'>Opțiuni</th></tr></thead>"; 
while ($row = $query->fetch(PDO::FETCH_ASSOC)){  
$status=$row['status'];
$com_det=$row['nr_comanda'];
$com_del=$row['nr_comanda'];
$com_del=strval($com_del);
$com_del.='DE';
$com_confirm=$row['nr_comanda'];
$com_confirm=strval($com_confirm);
$com_confirm.='CO';
$cust_type=$row['customer_type'];
$quantity=$row['quantity'];
$availability_id=$row['availability_id'];
$book_status=$row['status'];
$data_comenzii=$row['data_comenzii'];
              echo"<tr>";    
              echo"<td>$com_det</td><td>".$row['data_comenzii'].' ora '.$row['ora_comenzii']."</td><td>".$row['valoare_comanda']."</td><td>".$row['status']."</td><form method='post'><td><input class='btn btn-primary btn-block' type='submit' value='Detalii Comanda' name='$com_det'></td><td><input class='btn btn-primary btn-block' type='submit' value='Sterge Comanda' name='$com_del'></td>";if($status=="NECONFIRMATA"){echo "<td><input class='btn btn-primary btn-block' type='submit' value='Confirma Comanda' name='$com_confirm'></td>";};echo "</form></tr>";

if (isset($_POST[$com_det])) {
								
$_SESSION['com_id']=$com_det;


			printf("<script>location.href='detalii_comanda.php'</script>");
				}

if (isset($_POST[$com_del])) {

				
$delsql="SELECT $tabel_final_comenzi_detalii.cod_p,$tabel_final_comenzi_detalii.cantitate FROM $tabel_final_comenzi_detalii where nr_comanda='$com_det'";
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
<p>Comanda dvs. inregistrata cu numarul $com_det  a fost anulata datorita neprocesarii corespunzatoare a informatiilor furnizate de catre dumneavoastra.

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


$mail_sql = "SELECT $tabel_final_comenzi.ora_comenzii,$tabel_final_comenzi.den_pj,$tabel_final_comenzi.cif_pj,$tabel_final_comenzi.nr_reg_com_pj,$tabel_final_comenzi.banca_pj,$tabel_final_comenzi.cont_banca_pj,$tabel_final_comenzi.adresa_pj,$tabel_final_comenzi.judet_pj,$tabel_final_comenzi.email_pj,$tabel_final_customers.customer_preferred_contact_method,$tabel_final_customers.customer_firstname,$tabel_final_customers.customer_lastname,$tabel_final_customers.customer_address_line1,$tabel_final_customers.customer_address_line2,$tabel_final_customers.customer_address_line3,$tabel_final_customers.customer_postcode,$tabel_final_customers.customer_telephone_no,$tabel_final_customers.customer_email_address,$tabel_final_comenzi.tip_client,$tabel_final_comenzi.valoare_comanda,$tabel_final_nomenclator.imagine,$tabel_final_nomenclator.cota_tva,$tabel_final_comenzi.status,$tabel_final_comenzi.data_comenzii,$tabel_final_nomenclator.den_p,$tabel_final_nomenclator.um,$tabel_final_comenzi_detalii.cantitate,$tabel_final_comenzi_detalii.tva_col,$tabel_final_comenzi_detalii.pret_vanzare,$tabel_final_comenzi_detalii.valoare_vanzare,$tabel_final_comenzi_detalii.valoare_vanzare_cu_tva,$tabel_final_comenzi.cod_client FROM $tabel_final_comenzi_detalii inner join $tabel_final_comenzi on $tabel_final_comenzi.nr_comanda=$tabel_final_comenzi_detalii.nr_comanda INNER JOIN $tabel_final_nomenclator on $tabel_final_comenzi_detalii.cod_p=$tabel_final_nomenclator.cod_p INNER JOIN $tabel_final_customers on $tabel_final_comenzi.cod_client=$tabel_final_customers.customer_id where $tabel_final_comenzi.nr_comanda='$com_det';";    
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
 $f_tot_sql = "SELECT sum($tabel_final_comenzi_detalii.valoare_vanzare_cu_tva) as t_v_vz_c_tva FROM $tabel_final_comenzi_detalii where nr_comanda='$com_det'; ";    
$f_tot_stmt = $pdo->prepare($f_tot_sql);  
$f_tot_stmt->execute();

while ($row = $f_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_val_vz_cu_tva=$row['t_v_vz_c_tva'];
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

	
		$delsql="DELETE FROM $tabel_final_comenzi_detalii where nr_comanda='$com_det';DELETE FROM $tabel_final_comenzi where nr_comanda='$com_det'";
$delstmt = $pdo->prepare($delsql);  
$delstmt->execute(); 
	
	
			printf("<script>location.href='$tabel_final_comenzi.php'</script>");


    
}


if (isset($_POST[$com_confirm])) {
    
    
    $confirmsql="SELECT $tabel_final_comenzi_detalii.cod_p,$tabel_final_comenzi_detalii.cantitate FROM $tabel_final_comenzi_detalii where nr_comanda='$com_det'";
$confirmstmt = $pdo->prepare($confirmsql);  
$confirmstmt->execute(); 
while ($row = $confirmstmt->fetch(PDO::FETCH_ASSOC)){
    
    $produs=$row['cod_p'];
        $cantitate=$row['cantitate'];
        $stocsql="update $tabel_final_stoc set cantitate=cantitate-'$cantitate' where cod_p='$produs';";

$stocstmt = $pdo->prepare($stocsql);  
$stocstmt->execute(); 
        
     





				}
    
    $misc_sql = "SELECT $tabel_final_comenzi_detalii.cod_p,$tabel_final_comenzi_detalii.cantitate FROM $tabel_final_comenzi_detalii where nr_comanda='$com_det';";    
$misc_stmt = $pdo->prepare($misc_sql);  
$misc_stmt->execute(); 

while ($row = $misc_stmt->fetch(PDO::FETCH_ASSOC)){
    	date_default_timezone_set('UTC');

         $prod=$row['cod_p'];
     $qt=$row['cantitate'];

    $iessql = "insert into $tabel_final_miscari(data,cod_p,cantitate_misc,tip_miscare,fel_doc,nr_doc) values('$data_comenzii','$prod','$qt','O','FF','$com_det');";   

	 
	try{
$pdo->exec($iessql) or die(print_r($pdo->errorInfo(), true));   

}catch(PDOException $e)
    {
    echo $iessql . "<br>" . $e->getMessage();
    } 
    
}
    
    $operator=$_SESSION['adminloggedin'];

    $sql="update $tabel_final_comenzi SET status='CONFIRMATA',operator='$operator' where $tabel_final_comenzi.nr_comanda='$com_det';";
$pdo->exec($sql) or die(print_r($pdo->errorInfo(), true));


	//trimitere email 

	$subject = "Comanda efectuata!";
$message = "
<html>
<head>
</head>
<body>
<h4>Comanda dumneavoastra la M&B COMPUTERS SHOP</h4>
<p>Buna ziua,</p>
<p>Comanda dvs. inregistrata cu numarul $com_det  a fost predata firmei de curierat.

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


$mail_sql = "SELECT $tabel_final_comenzi.ora_comenzii,$tabel_final_comenzi.den_pj,$tabel_final_comenzi.cif_pj,$tabel_final_comenzi.nr_reg_com_pj,$tabel_final_comenzi.banca_pj,$tabel_final_comenzi.cont_banca_pj,$tabel_final_comenzi.adresa_pj,$tabel_final_comenzi.judet_pj,$tabel_final_comenzi.email_pj,$tabel_final_customers.customer_preferred_contact_method,$tabel_final_customers.customer_firstname,$tabel_final_customers.customer_lastname,$tabel_final_customers.customer_address_line1,$tabel_final_customers.customer_address_line2,$tabel_final_customers.customer_address_line3,$tabel_final_customers.customer_postcode,$tabel_final_customers.customer_telephone_no,$tabel_final_customers.customer_email_address,$tabel_final_comenzi.tip_client,$tabel_final_comenzi.valoare_comanda,$tabel_final_nomenclator.imagine,$tabel_final_nomenclator.cota_tva,$tabel_final_comenzi.status,$tabel_final_comenzi.data_comenzii,$tabel_final_nomenclator.den_p,$tabel_final_nomenclator.um,$tabel_final_comenzi_detalii.cantitate,$tabel_final_comenzi_detalii.tva_col,$tabel_final_comenzi_detalii.pret_vanzare,$tabel_final_comenzi_detalii.valoare_vanzare,$tabel_final_comenzi_detalii.valoare_vanzare_cu_tva,$tabel_final_comenzi.cod_client FROM $tabel_final_comenzi_detalii inner join $tabel_final_comenzi on $tabel_final_comenzi.nr_comanda=$tabel_final_comenzi_detalii.nr_comanda INNER JOIN $tabel_final_nomenclator on $tabel_final_comenzi_detalii.cod_p=$tabel_final_nomenclator.cod_p INNER JOIN $tabel_final_customers on $tabel_final_comenzi.cod_client=$tabel_final_customers.customer_id where $tabel_final_comenzi.nr_comanda='$com_det';";    
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
 $f_tot_sql = "SELECT sum($tabel_final_comenzi_detalii.valoare_vanzare_cu_tva) as b FROM $tabel_final_comenzi_detalii where nr_comanda='$com_det'; ";    
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

	
	
	
	
			printf("<script>location.href='$tabel_final_comenzi.php'</script>");

}


}

echo" <tfoot><tr><th>Număr Comandă</th><th>Data Comenzii</th><th>Valoare Comandă</th><th>Stare</th><th colspan='3'>Opțiuni</th></tr></tfoot>"; 

echo "</table>";
     
     
     
 }
            
            }
            ?>
            
        <?php
        
          if (!isset($_POST['search'])) {echo "<div style='display:block' class='table-responsive'>"; 
          } else {
              echo "<div style='display:none' class='table-responsive'>"; 
          
          }
          
          
          ?>
<div class="container"><?php
$cust_id=$_SESSION['cust_id'];
$dsql = "SELECT * FROM $tabel_final_comenzi order by status desc";    
$dstmt = $pdo->prepare($dsql);  
$dstmt->execute();  

echo "<table class='table table-bordered' id='dataTable' width='100%' cellspacing='0'>"; 
echo" <thead><tr><th>Număr Comandă</th><th>Data Comenzii</th><th>Valoare Comandă</th><th>Stare</th><th colspan='3'>Opțiuni</th></tr></thead>"; 
while ($row = $dstmt->fetch(PDO::FETCH_ASSOC)){  
$status=$row['status'];
$com_det=$row['nr_comanda'];
$com_del=$row['nr_comanda'];
$com_del=strval($com_del);
$com_del.='D';
$com_confirm=$row['nr_comanda'];
$com_confirm=strval($com_confirm);
$com_confirm.='C';
$cust_type=$row['customer_type'];
$quantity=$row['quantity'];
$availability_id=$row['availability_id'];
$book_status=$row['status'];
$data_comenzii=$row['data_comenzii'];
              echo"<tr>";    
              echo"<td>$com_det</td><td>".$row['data_comenzii'].' ora '.$row['ora_comenzii']."</td><td>".$row['valoare_comanda']."</td><td>".$row['status']."</td><form method='post'><td><input class='btn btn-primary btn-block' type='submit' value='Detalii Comanda' name='$com_det'></td><td><input class='btn btn-primary btn-block' type='submit' value='Sterge Comanda' name='$com_del'></td>";if($status=="NECONFIRMATA"){echo "<td><input class='btn btn-primary btn-block' type='submit' value='Confirma Comanda' name='$com_confirm'></td>";};echo "</form></tr>";

if (isset($_POST[$com_det])) {
								
$_SESSION['com_id']=$com_det;


			printf("<script>location.href='detalii_comanda.php'</script>");
				}

if (isset($_POST[$com_del])) {

				
$delsql="SELECT $tabel_final_comenzi_detalii.cod_p,$tabel_final_comenzi_detalii.cantitate FROM $tabel_final_comenzi_detalii where nr_comanda='$com_det'";
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
<p>Comanda dvs. inregistrata cu numarul $com_det  a fost anulata datorita neprocesarii corespunzatoare a informatiilor furnizate de catre dumneavoastra.

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


$mail_sql = "SELECT $tabel_final_comenzi.ora_comenzii,$tabel_final_comenzi.den_pj,$tabel_final_comenzi.cif_pj,$tabel_final_comenzi.nr_reg_com_pj,$tabel_final_comenzi.banca_pj,$tabel_final_comenzi.cont_banca_pj,$tabel_final_comenzi.adresa_pj,$tabel_final_comenzi.judet_pj,$tabel_final_comenzi.email_pj,$tabel_final_customers.customer_preferred_contact_method,$tabel_final_customers.customer_firstname,$tabel_final_customers.customer_lastname,$tabel_final_customers.customer_address_line1,$tabel_final_customers.customer_address_line2,$tabel_final_customers.customer_address_line3,$tabel_final_customers.customer_postcode,$tabel_final_customers.customer_telephone_no,$tabel_final_customers.customer_email_address,$tabel_final_comenzi.tip_client,$tabel_final_comenzi.valoare_comanda,$tabel_final_nomenclator.imagine,$tabel_final_nomenclator.cota_tva,$tabel_final_comenzi.status,$tabel_final_comenzi.data_comenzii,$tabel_final_nomenclator.den_p,$tabel_final_nomenclator.um,$tabel_final_comenzi_detalii.cantitate,$tabel_final_comenzi_detalii.tva_col,$tabel_final_comenzi_detalii.pret_vanzare,$tabel_final_comenzi_detalii.valoare_vanzare,$tabel_final_comenzi_detalii.valoare_vanzare_cu_tva,$tabel_final_comenzi.cod_client FROM $tabel_final_comenzi_detalii inner join $tabel_final_comenzi on $tabel_final_comenzi.nr_comanda=$tabel_final_comenzi_detalii.nr_comanda INNER JOIN $tabel_final_nomenclator on $tabel_final_comenzi_detalii.cod_p=$tabel_final_nomenclator.cod_p INNER JOIN $tabel_final_customers on $tabel_final_comenzi.cod_client=$tabel_final_customers.customer_id where $tabel_final_comenzi.nr_comanda='$com_det';";    
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
 $f_tot_sql = "SELECT sum($tabel_final_comenzi_detalii.valoare_vanzare_cu_tva) as c FROM $tabel_final_comenzi_detalii where nr_comanda='$com_det'; ";    
$f_tot_stmt = $pdo->prepare($f_tot_sql);  
$f_tot_stmt->execute();

while ($row = $f_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_val_vz_cu_tva=$row['c'];
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

	
		$delsql="DELETE FROM $tabel_final_comenzi_detalii where nr_comanda='$com_det';DELETE FROM $tabel_final_comenzi where nr_comanda='$com_det'";
$delstmt = $pdo->prepare($delsql);  
$delstmt->execute(); 
	
	
			printf("<script>location.href='$tabel_final_comenzi.php'</script>");


    
}


if (isset($_POST[$com_confirm])) {
    
    
    $confirmsql="SELECT $tabel_final_comenzi_detalii.cod_p,$tabel_final_comenzi_detalii.cantitate FROM $tabel_final_comenzi_detalii where nr_comanda='$com_det'";
$confirmstmt = $pdo->prepare($confirmsql);  
$confirmstmt->execute(); 
while ($row = $confirmstmt->fetch(PDO::FETCH_ASSOC)){
    
    $produs=$row['cod_p'];
        $cantitate=$row['cantitate'];
        $stocsql="update $tabel_final_stoc set cantitate=cantitate-'$cantitate' where cod_p='$produs';";

$stocstmt = $pdo->prepare($stocsql);  
$stocstmt->execute(); 
        
     





				}
    
    $misc_sql = "SELECT $tabel_final_comenzi_detalii.cod_p,$tabel_final_comenzi_detalii.cantitate FROM $tabel_final_comenzi_detalii where nr_comanda='$com_det';";    
$misc_stmt = $pdo->prepare($misc_sql);  
$misc_stmt->execute(); 

while ($row = $misc_stmt->fetch(PDO::FETCH_ASSOC)){
    	date_default_timezone_set('UTC');

         $prod=$row['cod_p'];
     $qt=$row['cantitate'];

    $iessql = "insert into $tabel_final_miscari(data,cod_p,cantitate_misc,tip_miscare,fel_doc,nr_doc) values('$data_comenzii','$prod','$qt','O','FF','$com_det');";   

	 
	try{
$pdo->exec($iessql) or die(print_r($pdo->errorInfo(), true));   

}catch(PDOException $e)
    {
    echo $iessql . "<br>" . $e->getMessage();
    } 
    
}
    
    $operator=$_SESSION['adminloggedin'];

    $sql="update $tabel_final_comenzi SET status='CONFIRMATA',operator='$operator' where $tabel_final_comenzi.nr_comanda='$com_det';";
$pdo->exec($sql) or die(print_r($pdo->errorInfo(), true));


	//trimitere email 

	$subject = "Comanda efectuata!";
$message = "
<html>
<head>
</head>
<body>
<h4>Comanda dumneavoastra la M&B COMPUTERS SHOP</h4>
<p>Buna ziua,</p>
<p>Comanda dvs. inregistrata cu numarul $com_det  a fost predata firmei de curierat.

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


$mail_sql = "SELECT $tabel_final_comenzi.ora_comenzii,$tabel_final_comenzi.den_pj,$tabel_final_comenzi.cif_pj,$tabel_final_comenzi.nr_reg_com_pj,$tabel_final_comenzi.banca_pj,$tabel_final_comenzi.cont_banca_pj,$tabel_final_comenzi.adresa_pj,$tabel_final_comenzi.judet_pj,$tabel_final_comenzi.email_pj,$tabel_final_customers.customer_preferred_contact_method,$tabel_final_customers.customer_firstname,$tabel_final_customers.customer_lastname,$tabel_final_customers.customer_address_line1,$tabel_final_customers.customer_address_line2,$tabel_final_customers.customer_address_line3,$tabel_final_customers.customer_postcode,$tabel_final_customers.customer_telephone_no,$tabel_final_customers.customer_email_address,$tabel_final_comenzi.tip_client,$tabel_final_comenzi.valoare_comanda,$tabel_final_nomenclator.imagine,$tabel_final_nomenclator.cota_tva,$tabel_final_comenzi.status,$tabel_final_comenzi.data_comenzii,$tabel_final_nomenclator.den_p,$tabel_final_nomenclator.um,$tabel_final_comenzi_detalii.cantitate,$tabel_final_comenzi_detalii.tva_col,$tabel_final_comenzi_detalii.pret_vanzare,$tabel_final_comenzi_detalii.valoare_vanzare,$tabel_final_comenzi_detalii.valoare_vanzare_cu_tva,$tabel_final_comenzi.cod_client FROM $tabel_final_comenzi_detalii inner join $tabel_final_comenzi on $tabel_final_comenzi.nr_comanda=$tabel_final_comenzi_detalii.nr_comanda INNER JOIN $tabel_final_nomenclator on $tabel_final_comenzi_detalii.cod_p=$tabel_final_nomenclator.cod_p INNER JOIN $tabel_final_customers on $tabel_final_comenzi.cod_client=$tabel_final_customers.customer_id where $tabel_final_comenzi.nr_comanda='$com_det';";    
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
 $f_tot_sql = "SELECT sum($tabel_final_comenzi_detalii.valoare_vanzare_cu_tva) as d FROM $tabel_final_comenzi_detalii where nr_comanda='$com_det'; ";    
$f_tot_stmt = $pdo->prepare($f_tot_sql);  
$f_tot_stmt->execute();

while ($row = $f_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_val_vz_cu_tva=$row['d'];
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

	
	
	
	
			printf("<script>location.href='$tabel_final_comenzi.php'</script>");

}


}

echo" <tfoot><tr><th>Număr Comandă</th><th>Data Comenzii</th><th>Valoare Comandă</th><th>Stare</th><th colspan='3'>Opțiuni</th></tr></tfoot>"; 

echo "</table>";



?>
<div class="clearfix"></div>
</div>
  </div>
        </div>
        
      </div>



   


<?php 
	require "footer.php";
?>
