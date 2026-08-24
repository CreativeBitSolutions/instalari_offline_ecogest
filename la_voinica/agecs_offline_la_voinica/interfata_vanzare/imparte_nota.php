<?php  
include('session.php');

	date_default_timezone_set('UTC+2');
		date_default_timezone_set("Europe/Bucharest");
 $adm_id=$_SESSION['admin_id'];
$dsql = "SELECT * FROM $tabel_final_admins where admin_id='$adm_id'";
$dstmt = $pdo->prepare($dsql);
$dstmt->execute(); 
while ($row = $dstmt->fetch(PDO::FETCH_ASSOC)){
$admin_firstname=$row['admin_firstname'];
$admin_lastname=$row['admin_lastname'];
}



					
					if(!isset($_SESSION['nota_noua'])){		
                $sql="insert into $tabel_final_note(operator,locatie) values('$adm_id','1')"; 	 

try{
$pdo->exec($sql) or die(print_r($pdo->errorInfo(), true));   

    // afiseaza un mesaj de succes 
 
}catch(PDOException $e)
    {
    echo $sql . "<br>" . $e->getMessage();
    }
    
    $crccom_sql = "SELECT max(nrbon) as nrb from $tabel_final_note where operator='$adm_id' and locatie='1'";    
$crccom_stmt = $pdo->prepare($crccom_sql);  
$crccom_stmt->execute(); 
while ($row = $crccom_stmt->fetch(PDO::FETCH_ASSOC)){ 
    $_SESSION['nota_noua']=$row['nrb'];
}
    $nr_bon=$_SESSION['nota_noua'];

}

else{
    $nr_bon=$_SESSION['nota_noua'];
}
if(isset($_POST['deconectare'])){

	$dsql="DELETE from $tabel_final_note WHERE nrbon = '$nr_bon';";
$pdo->exec($dsql) or die(print_r($pdo->errorInfo(), true));

	$ddsql="DELETE from $tabel_final_det_note WHERE nr_bon = '$nr_bon';";
$ddsql_stmt = $pdo->prepare($ddsql);  
$ddsql_stmt->execute(); 

unset($_SESSION['nota_noua']);


    			printf("<script>location.href='vanzare_magazin.php'</script>");
							
							} 
							
							
							
							
						$nota_de_impartit=$_SESSION['nr_bon'];
?>
				<html>
				<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="description" content="">
  <meta name="author" content="">
  <title>Restaurant</title>
  <!-- Bootstrap core CSS-->
    <!-- Bootstrap core JavaScript-->
	<script src="javascript.js"></script>

  <!-- Core plugin JavaScript-->
  <!-- Custom fonts for this template-->
  <!-- Custom styles for this template-->
  <link href="css/sb-admin.css" rel="stylesheet">
  <link href="./numpad_files/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="./numpad_files/font-awesome.min.css">


<!-- CSS Page Level -->


<link rel="stylesheet" href="./numpad_files/style.css" type="text/css">





<link rel="stylesheet" href="numpad_files/jquery.numpad.css">




<!-- Yandex.Metrika counter -->
<!-- /Yandex.Metrika counter -->
			


		<!-- JS Global -->

</head>

<body class="bg-dark">
<!-- about -->



 <div>     
    <div class="container-fluid">


        			<div id="Mod_simplu" class="tabcontent" style="display:block;border:0; padding:0;margin:0;" >
  
<div class="wrapper">

    <div id="one">
<table class='table table-bordered' style='margin-bottom:0;'>
  
  <?php
  
  
  $date_firma = "SELECT mod_listare from $tabel_final_date_firma";    
$date_firma_stmt = $pdo->prepare($date_firma);  
$date_firma_stmt->execute(); 
while ($row = $date_firma_stmt->fetch(PDO::FETCH_ASSOC)){ 


$_SESSION['mod_listare']=$row['mod_listare'];
}

//afisare randuri NIR
$f_sql = "SELECT $tabel_final_det_note.pachet,$tabel_final_det_note.discount,$tabel_final_det_note.cod_p,$tabel_final_nomenclator.den_p,cote_tva.cota,$tabel_final_nomenclator.um,$tabel_final_det_note.cantitate,$tabel_final_det_note.tva_col,$tabel_final_det_note.pret_vanzare,$tabel_final_det_note.valoare_vanzare,$tabel_final_det_note.valoare_vanzare_cu_tva,$tabel_final_det_note.id_vanz from $tabel_final_nomenclator inner join $tabel_final_det_note on $tabel_final_det_note.cod_p=$tabel_final_nomenclator.cod_p inner join cote_tva on $tabel_final_nomenclator.cota_tva=cote_tva.id where nr_bon='$nr_bon' order by $tabel_final_nomenclator.den_p;";    
$f_stmt = $pdo->prepare($f_sql);  
$f_stmt->execute(); 
echo "<thead>
<tbody class='tbody' style='height:450px'>
<tr>
<th style='width:100%'>Produs</th>
<th>Cantitate</th>
<th><div>Valoare</div><div style='color:green;'>-Discount</div></th>
<th style='width:20px;'>Șterge</th>



</thead>";
while ($row = $f_stmt->fetch(PDO::FETCH_ASSOC)){ 
$codul_produsului=$row['cod_p'];


	 $psql22 = "SELECT $tabel_final_det_note.cantitate from $tabel_final_det_note where $tabel_final_det_note.cod_p='$codul_produsului' and $tabel_final_det_note.nr_bon='$nota_de_impartit';";    
$pstmt22 = $pdo->prepare($psql22);  
$pstmt22->execute(); 

while ($rrrrow = $pstmt22->fetch(PDO::FETCH_ASSOC)){ 
             $cantitate_nota_veche=$rrrrow['cantitate'];
}

$produs=$row['den_p'];
$cantitate=$row['cantitate'];
$tva_col=$row['tva_col'];
$pret_vanzare=$row['pret_vanzare'];
$valoare_vanzare=$row['valoare_vanzare'];
$id_vanz=$row['id_vanz'];
$id_simplu_vanz=$row['id_vanz'];
$id_simplu_vanz=strval($id_simplu_vanz);
$id_simplu_vanz.='D';
$valoare_vanzare_c_tva=floor(($row['valoare_vanzare_cu_tva'])*100)/100;
$cota=$row['cota'];
$d=$row['discount'];
$pch=$row['pachet'];

  echo "
   <tr>
    <td style='width:100%;height:auto; white-space: normal;'><div>$produs</div>";if($pch==0){echo "<div style='color:#ca2222'>Servit la masa";}else{echo "<div style='color:green'>La pachet";} echo"</div></td>
    <td ><button style='display:inline-block;' name='$id_vanz' value='$codul_produsului' data-value='$cantitate_nota_veche' class='btn btn-primary btn-block modif_cant'>X  $cantitate</button></td>
	<td><div>$valoare_vanzare_c_tva</div>";echo "<div style='color:green;'>-$d</div>"; echo"</td>
	<td><form method='post'><input style='background-color:white;color:red;' class='btn btn-primary btn-block' type='submit' value='X' name='$id_simplu_vanz'></form></td>

  </tr>
  
  
  
  " 
  
  ;
  if (isset($_POST[$id_simplu_vanz])) {
					
				
					$sql="DELETE from $tabel_final_det_note WHERE $tabel_final_det_note.id_vanz = '$id_vanz';";
					
$pdo->exec($sql) or die(print_r($pdo->errorInfo(), true));

			printf("<script>location.href='imparte_nota.php'</script>");
				}
  }
  

 
  ?></tbody></table>
  
  

  <table class='table table-bordered'>
<form id='plata' method='POST'>
 <tr style='font-weight:bold;font-size:20px;'><th style='width:20%'><p>Total de încasat</p>C.I.F. CLIENT</p></th>
<?php 
 
 $f_tot_sql = "SELECT sum($tabel_final_det_note.valoare_vanzare_cu_tva) as total_v_vz_ctva from $tabel_final_det_note  where nr_bon='$nr_bon'; ";    
$f_tot_stmt = $pdo->prepare($f_tot_sql);  
$f_tot_stmt->execute();

while ($row = $f_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$valoare_f_vz=$row['total_v_vz_ctva'];
}

   ?>

 <?php 
 
 $f_tot_sql = "SELECT sum($tabel_final_det_note.valoare_vanzare_cu_tva) as t_v_c_tva from $tabel_final_det_note where nr_bon='$nr_bon'; ";    
$f_tot_stmt = $pdo->prepare($f_tot_sql);  
$f_tot_stmt->execute();

while ($row = $f_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_val_vz_cu_tva=round($row['t_v_c_tva'],2);
}
 $disc_tot_sql = "SELECT sum($tabel_final_det_note.discount) as tot_disc from $tabel_final_det_note where nr_bon='$nr_bon'; ";    
$disc_tot_stmt = $pdo->prepare($disc_tot_sql);  
$disc_tot_stmt->execute();

while ($row = $disc_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_discount=$row['tot_disc'];
}

$total_val_vz_cu_tva=floor(($total_val_vz_cu_tva-$total_discount)*100)/100;?>
<th style='width:20%'><div class="input-group"><input  step='0.001' readonly type="number" name="total" value="<?php echo $total_val_vz_cu_tva; ?>" class="form-control" id="total" onchange="updateDue()">  	

	<!-- jQuery (required) & jQuery UI + theme (optional) -->
	<link href="keyboard/docs/css/jquery-ui.min.css" rel="stylesheet">
	<!-- still using jQuery v2.2.4 because Bootstrap doesn't support v3+ -->
	<!-- <script src="keyboard/docs/js/jquery-migrate-3.0.0.min.js"></script> -->

	<!-- keyboard widget css & script (required) -->
	<link href="keyboard/css/keyboard.css" rel="stylesheet">

	<!-- demo only -->
		<input id="cif_client" type="text" maxlength="10" name="cif_client" placeholder="Ex. RO12345678">
	<script>// Autocomplete demo
var availableTags = ["ActionScript", "AppleScript", "Asp", "BASIC", "C", "C++", "Clojure",
	"COBOL", "ColdFusion", "Erlang", "Fortran", "Groovy", "Haskell", "Java", "JavaScript",
	"Lisp", "Perl", "PHP", "Python", "Ruby", "Scala", "Scheme" ];

$('#cif_client')
	.keyboard({ layout: 'qwerty' })
	.autocomplete({
		source: availableTags
	})

	.addTyping();
	</script></div>
</th><th><label for="rest">Suma încasată</label>	<div class="input-group"><input step="0.001" value="<?php echo $total_val_vz_cu_tva; ?>" name='numerarprim' min="<?php echo $total_val_vz_cu_tva;?>" type="number"  id="baniprim" class="form-control" style="width:14.5em;" placeholder="Enter a number" aria-describedby="baniprim-btn" id="baniprim" onchange="updateDue()"> <span style='margin-top:2.5px; float:left;' class="input-group-btn"> <button  class="btn btn-default nmpd-target"  id="baniprim-btn" type="button" readonly="readonly" data-numpad="nmpd3"><i class="fa fa-calculator"></i></button> </span></div>
	</th><th>   <label for="rest">Rest</label>
	<div class="input-group">    <input readonly type="number" step='0.001' value="0" min="0" name="rest_numerar" class="form-control" id="rest"> </div></th></tr></form>
	

    
    


<?php
if(isset($_POST['finaliz_bon'])){
    
    
    

 $ora_bon = date("H:i:s", strtotime('+0 hours'));
 $data_bon = date("Y-m-d", strtotime('+0 hours'));
 $calc_tva_col_sql = "SELECT $tabel_final_det_note.pachet,$tabel_final_det_note.discount,$tabel_final_det_note.valoare_vanzare_cu_tva,cote_tva.cota,$tabel_final_det_note.id_vanz from $tabel_final_nomenclator inner join $tabel_final_det_note on $tabel_final_det_note.cod_p=$tabel_final_nomenclator.cod_p inner join cote_tva on $tabel_final_nomenclator.cota_tva=cote_tva.id where nr_bon='$nr_bon'; ";    
$calc_tva_col_stmt = $pdo->prepare($calc_tva_col_sql);  
$calc_tva_col_stmt->execute();

while ($row = $calc_tva_col_stmt->fetch(PDO::FETCH_ASSOC)){
$val_vz_c_tva=$row['valoare_vanzare_cu_tva'];
$cota=$row['cota'];
$pachet=$row['pachet'];
 if($cota==9 && $pachet!=1 ){
							       $cota=5;
							   }
$id_v=$row['id_vanz'];
$dis=$row['discount'];
$tva_col=($val_vz_c_tva-$dis)*$cota/(100+$cota);
$tva_col=round($tva_col,2);
 $update_tva_col_sql = "update $tabel_final_det_note set tva_col='$tva_col' where id_vanz='$id_v'";    
$update_tva_col_stmt = $pdo->prepare($update_tva_col_sql);  
$update_tva_col_stmt->execute();
    
}
 $f_tot_sql = "SELECT sum($tabel_final_det_note.tva_col) as total_tva_col from $tabel_final_det_note  where nr_bon='$nr_bon'; ";    
$f_tot_stmt = $pdo->prepare($f_tot_sql);  
$f_tot_stmt->execute();

while ($row = $f_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_tva_col=$row['total_tva_col'];
}
 
	if($_POST['finaliz_bon']=='numerar'){
	    $cif_client=$_POST['cif_client'];
	    $_SESSION['cif_client']=$_POST['cif_client'];

$rest=$_POST['rest_numerar'];
$numerar=$_POST['numerarprim'];
$_SESSION['numerarprim']=$_POST['numerarprim'];

$fin_sql = "update $tabel_final_note SET operator='$adm_id',rest='$rest',tva_colectata='$total_tva_col',valoare_vanzare_cu_tva='$valoare_f_vz',data_bon='$data_bon',ora_bon='$ora_bon',status='F',numerar='$numerar',discount='$total_discount',cif_client='$cif_client' WHERE nrbon='$nr_bon';";    
	}
		elseif($_POST['finaliz_bon']=='card'){
		    	    $cif_client=$_POST['cif_client'];
		    	    	    $_SESSION['cif_client']=$_POST['cif_client'];

		  $card=$total_val_vz_cu_tva;
$_SESSION['cardprim']=$total_val_vz_cu_tva;
$fin_sql = "update $tabel_final_note SET operator='$adm_id',tva_colectata='$total_tva_col',valoare_vanzare_cu_tva='$valoare_f_vz',data_bon='$data_bon',ora_bon='$ora_bon',status='F',card='$card',discount='$total_discount',cif_client='$cif_client' WHERE nrbon='$nr_bon';";    

		}
		
			elseif($_POST['finaliz_bon']=='numerar_si_card'){
			    	    $cif_client_m=$_POST['cif_client_m'];
			    	    $_SESSION['cif_client']=$_POST['cif_client_m'];

$card=$_POST['card'];
$numerar=$_POST['numerar'];
$_SESSION['numerarprim']=$_POST['numerar'];
$_SESSION['cardprim']=$_POST['card'];

$fin_sql = "update $tabel_final_note SET operator='$adm_id',tva_colectata='$total_tva_col',valoare_vanzare_cu_tva='$valoare_f_vz',data_bon='$data_bon',ora_bon='$ora_bon',status='F',numerar='$numerar',card='$card',discount='$total_discount',cif_client='$cif_client_m' WHERE nrbon='$nr_bon';";    

		}
		
				elseif($_POST['finaliz_bon']=='tichete_de_masa'){
			    	    $cif_client_t=$_POST['cif_client_t'];
			    	    $_SESSION['cif_client']=$_POST['cif_client_t'];
$tichete=$_POST['total_tichete'];
$numerar=$_POST['rest_de_incasat'];
$rest=$_POST['rest_de_returnat'];
$_SESSION['rest_tichete']=$_POST['rest_de_incasat'];
$_SESSION['total_tichete']=$_POST['total_tichete'];

$fin_sql = "update $tabel_final_note SET operator='$adm_id',tva_colectata='$total_tva_col',valoare_vanzare_cu_tva='$valoare_f_vz',data_bon='$data_bon',ora_bon='$ora_bon',status='F',numerar='$numerar',tichete='$tichete',rest='$rest',discount='$total_discount',cif_client='$cif_client_t' WHERE nrbon='$nr_bon';";    

		}
	 
	try{
$pdo->exec($fin_sql) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 
		  
}catch(PDOException $e)
    {
    echo $fin_sql . "<br>" . $e->getMessage();
    } 
 
	
	 

$misc_sql = "SELECT $tabel_final_nomenclator.gestiune,$tabel_final_det_note.cod_p,$tabel_final_det_note.cantitate from $tabel_final_det_note INNER JOIN $tabel_final_nomenclator on $tabel_final_det_note.cod_p=$tabel_final_nomenclator.cod_p where nr_bon='$nr_bon';";     
$misc_stmt = $pdo->prepare($misc_sql);  
$misc_stmt->execute(); 

while ($row = $misc_stmt->fetch(PDO::FETCH_ASSOC)){ 
    
         $prod=$row[cod_p];
     $qt=$row[cantitate];
     $gest=$row['gestiune'];
     	date_default_timezone_set('UTC');
   if($gest=='PF'){
       
       
       $b_c_sql = "SELECT max(nr_doc) as ultim_bt from $tabel_final_miscari where miscari.fel_doc='BT'";    
$b_c_stmt = $pdo->prepare($b_c_sql);  
$b_c_stmt->execute(); 
       
       while ($row = $b_c_stmt->fetch(PDO::FETCH_ASSOC)){ 
           $ultim_bt=$row['ultim_bt']+1;
       }
       
            $bt_sql = "insert into $tabel_final_miscari(data,cod_p,cantitate_misc,tip_miscare,fel_doc,nr_doc) values('$data_bon','$prod','$qt','I','BT','$ultim_bt');";   

	 
	try{
$pdo->exec($bt_sql) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 

}catch(PDOException $e)
    {
    echo $bt_sql . "<br>" . $e->getMessage();
    } 
           $iessql = "insert into $tabel_final_miscari(data,cod_p,cantitate_misc,tip_miscare,fel_doc,nr_doc) values('$data_bon','$prod','$qt','O','BF','$nr_bon');";   

	 
	try{
$pdo->exec($iessql) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 

}catch(PDOException $e)
    {
    echo $iessql . "<br>" . $e->getMessage();
    }
    
     
    
    
         $reteta_sql = "SELECT $tabel_final_retete.cod_mat,$tabel_final_retete.cant_folos from $tabel_final_retete where $tabel_final_retete.cod_p='$prod'";    
$reteta_stmt = $pdo->prepare($reteta_sql);  
$reteta_stmt->execute(); 
        $b_c_sql = "SELECT max(nr_doc) as ultim_bc from $tabel_final_miscari where miscari.fel_doc='BC'";    
$b_c_stmt = $pdo->prepare($b_c_sql);  
$b_c_stmt->execute(); 
       
       while ($row = $b_c_stmt->fetch(PDO::FETCH_ASSOC)){ 
           $ultim_bc=$row['ultim_bc']+1;
       }
       while ($row = $reteta_stmt->fetch(PDO::FETCH_ASSOC)){ 

$c_m=$row['cod_mat'];
$qt_m=$row['cant_folos']*$qt;

 
        $iessqll = "insert into $tabel_final_miscari(data,cod_p,cantitate_misc,tip_miscare,fel_doc,nr_doc) values('$data_bon','$c_m','$qt_m','O','BC','$ultim_bc');";   

	 
	try{
$pdo->exec($iessqll) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 

}catch(PDOException $e)
    {
    echo $iessqll . "<br>" . $e->getMessage();
    } 
}
       
       
     
   }
   
   else{
   
   
   
    $iessql = "insert into $tabel_final_miscari(data,cod_p,cantitate_misc,tip_miscare,fel_doc,nr_doc) values('$data_bon','$prod','$qt','O','BF','$nr_bon');";   

	 
	try{
$pdo->exec($iessql) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 

}catch(PDOException $e)
    {
    echo $iessql . "<br>" . $e->getMessage();
    } 
    
}
    
}
    $_SESSION['nr_bon']=$nr_bon;
   
     $imp_n_sql = "SELECT * from $tabel_final_det_note where nr_bon='$nr_bon';";    
$imp_n_stmt = $pdo->prepare($imp_n_sql);  
$imp_n_stmt->execute(); 
       
       while ($row = $imp_n_stmt->fetch(PDO::FETCH_ASSOC)){ 
$cod_p=$row['cod_p'];
$cantitate=$row['cantitate'];
$tva_col=$row['tva_col'];
$valoare_vanzare=$row['valoare_vanzare'];
$valoare_vanzare_cu_tva=$row['valoare_vanzare_cu_tva'];
$discount=$row['discount'];
$pach=$row['pachet'];
   $upt_imp_n_sql = "update $tabel_final_det_note set cantitate=cantitate-$cantitate,tva_col=tva_col-$tva_col,valoare_vanzare=valoare_vanzare-$valoare_vanzare,valoare_vanzare_cu_tva=valoare_vanzare_cu_tva-$valoare_vanzare_cu_tva,discount=discount-$discount where nr_bon='$nota_de_impartit' and cod_p='$cod_p' and pachet='$pach'";    
$upt_imp_n_stmt = $pdo->prepare($upt_imp_n_sql);  
$upt_imp_n_stmt->execute();

       }
       
    $imp_n_sql = "DELETE from $tabel_final_det_note where cantitate='0';";    
$imp_n_stmt = $pdo->prepare($imp_n_sql);  
$imp_n_stmt->execute(); 
    
    if($_SESSION['mod_listare']=='complex'){
printf("<script>location.href='dwred_restaurant_cu_listare.php'</script>");
}
else{
    printf("<script>location.href='dwred_vanzare_magazin.php'</script>");

}





	
}





  ?>
  </th>
</table>

</div>
 <div id="two">



<table id="myTable" >
    
    <?php
$psql = "SELECT id_categorie,den_categ from $tabel_final_categorii; ";    
$pstmt = $pdo->prepare($psql);  
$pstmt->execute(); 
$prod_count=$pstmt->rowCount(); ?>






	<div class="block" id="autocomplete">
		<input id="text"  type="text" onchange="myFunctions()" placeholder="Caută după denumirea produsului...">
		<br>
	</div>

  <tr class="header">
    <th >Denumire</th>
  </tr>
  <tbody  class='tbody'>
        <?php 
     
$test_sql = "SELECT $tabel_final_det_note.id_vanz,$tabel_final_nomenclator.cod_p,$tabel_final_nomenclator.den_p,$tabel_final_det_note.cantitate,$tabel_final_det_note.tva_col,$tabel_final_det_note.pret_vanzare,$tabel_final_det_note.valoare_vanzare,$tabel_final_det_note.valoare_vanzare_cu_tva,$tabel_final_det_note.discount,$tabel_final_det_note.pachet from $tabel_final_nomenclator inner join $tabel_final_det_note on $tabel_final_nomenclator.cod_p=$tabel_final_det_note.cod_p  where $tabel_final_det_note.nr_bon='$nota_de_impartit'";    
$test_stm = $pdo->prepare($test_sql);  
$test_stm->execute();
$cant_nota_noua=0;

while ($row = $test_stm->fetch(PDO::FETCH_ASSOC)){
    
    $den_p=$row['den_p'];
    $cod_p=$row['cod_p'];
 $cant_nota_veche=$row['cantitate'];
 $tva_col=$row['tva_col']/$row['cantitate'];
  $pret_vanzare=$row['pret_vanzare'];
$valoare_vanzare=$row['valoare_vanzare']/$row['cantitate'];
$valoare_vanzare_cu_tva=$row['valoare_vanzare_cu_tva']/$row['cantitate'];
	 $discount=$row['discount']/$row['cantitate'];
$pachet=$row['pachet'];
$id_vanz=$row['id_vanz'];
 $ttest_sql = "select $tabel_final_det_note.cantitate as cant_nota_noua from $tabel_final_det_note where $tabel_final_det_note.nr_bon='$nr_bon' and cod_p='$cod_p' and pachet='$pachet';";    
$ttest_stm = $pdo->prepare($ttest_sql);  
$ttest_stm->execute(); 
while ($row = $ttest_stm->fetch(PDO::FETCH_ASSOC)){
$cant_nota_noua=$row['cant_nota_noua'];

}

if($cant_nota_noua<$cant_nota_veche){
 					    echo"<tr>
   <td  width='700'><form method='POST'><button style='width:100%; font-size:1em; height:auto; white-space: normal;
' class='btn btn-primary btn-block' type='submit' name='$id_vanz'>$den_p"; if($pachet==1){echo " La pachet";} echo"</button></form></td>
  </tr> "; 
  
}
$cant_nota_noua=0;

 if(isset($_POST[$id_vanz])){
	 $produs=$cod_p;

	
$psql3 = "SELECT id_vanz from $tabel_final_det_note where nr_bon='$nr_bon' and cod_p='$produs' and pachet='$pachet';";    
$pstmt3 = $pdo->prepare($psql3);  
$pstmt3->execute(); 
$prodcount=$pstmt3->rowCount();

if($prodcount==0){

$psql = "insert into $tabel_final_det_note(nr_bon,cod_p,cantitate,tva_col,pret_vanzare,valoare_vanzare,valoare_vanzare_cu_tva,discount,pachet) values('$nr_bon','$produs',1,'$tva_col','$pret_vanzare','$valoare_vanzare','$valoare_vanzare_cu_tva','$discount','$pachet');";   }

elseif($prodcount>0){
    $psql = "update $tabel_final_det_note set cantitate=cantitate+1,tva_col=tva_col+'$tva_col',valoare_vanzare=valoare_vanzare+'$valoare_vanzare',valoare_vanzare_cu_tva=valoare_vanzare_cu_tva+'$valoare_vanzare_cu_tva',discount=discount+'$discount' where nr_bon='$nr_bon' and cod_p='$produs';"; 
    
}
	 
	try{
$pdo->exec($psql) or die(print_r($pdo->errorInfo(), true));   
    			printf("<script>location.href='imparte_nota.php'</script>");

}catch(PDOException $e)
    {
    echo $psql . "<br>" . $e->getMessage();
    } 
}


 
    

}


      ?>
 
</tbody>
</table> 


 
</div>    





<!-- 3rd row responsive images in background with centered content -->




</div>			  
			</div>	
<footer class="sticky-footer" style="width:100%;height:96px;">
  <div class="container" style='margin:0;width:100%;' >
    <table>
    <tr>
<!-- 1st row verticaly centered text in the square columns -->

<div style="background-color:#BCC6CC;float: left;"><button id='plata_numerar' class="square img_1-1" form='plata' type="submit" name="finaliz_bon" value="numerar" ></button></form>
<button form='plata' type='submit' name="finaliz_bon" value="card" id='plata_card' class="square img_1-2" id="plata_card" ></button>
<button class="square img_1-3" id="plata_numerar_si_card"></button>
<button class="square img_1-7" id="plata_tichete"></button></div>
<div style="background-color:#98AFC7;float: right;">
   
<form method="POST" style="margin:0;float:right;"><button name="deconectare" id='deconectare' class='square img_1-11' type="submit" class="tablinks"></button></form></div>
<div style="background-color:#E5E4E2;float: none;overflow: hidden;">       <button disabled id='white_button' class="square banner1" style='width:100%;font-size:1.5em;'><p style="margin:0;line-height:2">Nota nr: <?php echo $nr_bon; ?> <br>  Operator: <?php echo $admin_firstname.' '.$admin_lastname;?></p></button></div>
      
    
    

</tr>
</table>

  </div>
</footer>

							
								<!--NUMERAR BEGIN//-->







<!-- CSS Global -->


		<!-- Custom JS -->
		<script src="./numpad_files/jquery.numpad.js.download" type="text/javascript"></script>
<script type="text/javascript">
	$(function() {
		// Set NumPad defaults for jQuery mobile. 
		// These defaults will be applied to all NumPads within this document!
		$.fn.numpad.defaults.gridTpl = '<table class="table modal-content"></table>';
		$.fn.numpad.defaults.backgroundTpl = '<div class="modal-backdrop in"></div>';
		$.fn.numpad.defaults.displayTpl = '<input type="text" class="form-control  input-lg" />';
		$.fn.numpad.defaults.buttonNumberTpl =  '<button type="button" class="btn btn-default btn-lg"></button>';
		$.fn.numpad.defaults.buttonFunctionTpl = '<button type="button" class="btn btn-lg" style="width: 100%;"></button>';
		$.fn.numpad.defaults.onKeypadCreate = function(){$(this).find('.done').addClass('btn-primary');};
		
		// Instantiate NumPad once the page is ready to be shown
		$(document).ready(function(){
			$('#text-basic').numpad();
			$('#password').numpad({
				displayTpl: '<input class="form-control" type="password" />'		
			});
			$('#baniprim-btn').numpad({
				target: $('#baniprim'),
                                decimalSeparator: '.'
			});
				$('#numerar-btn').numpad({
				target: $('#numerar'),
                                decimalSeparator: '.'
			});
				$('#proc_disc-btn').numpad({
				target: $('#val_procent'),
                                decimalSeparator: '.'
			});
				$('#val_disc-btn').numpad({
				target: $('#val_fix'),
                                decimalSeparator: '.'
			});
				$('#cant-btn').numpad({
				target: $('#cant_noua'),
                                decimalSeparator: '.'
			});
				$('#card-btn').numpad({
				target: $('#card'),
                                decimalSeparator: '.'
			});
				$('#nr_tichete-btn').numpad({
				target: $('#nr_tichete'),
                                decimalSeparator: '.'
			});
				$('#val_tichet-btn').numpad({
				target: $('#val_tichet'),
                                decimalSeparator: '.'
			});
			$('#rest_de_incasat-btn').numpad({
				target: $('#rest_de_incasat'),
                                decimalSeparator: '.'
			});
			$('#numpad4div').numpad();
			$('#numpad4column .qtyInput').numpad();
		});
	});
</script>

		
			<!--- Modal lista note amanate-->
			

						<!------ Modal lista note amanate end -->
						
							<!--- Modal setare masa-->
			

						<!------ Modal Modal setare masa end -->
												<!------ Modal produse vandute begin -->

								<!------ Modal produse vandute end -->
						<!------ Modal modif cantitate begin -->

							<div class="modal fade"  id="Cantitate" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
		  <div class="modal-dialog" role="document" style='width:20%;'>
			<div class="modal-content">
			    
			  <div class="modal-header">
				<h4 class="modal-title" id="myModalLabel">Modificare cantitate</h4>
								<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>

			  </div>
			  <div class="modal-body">
 <form method='POST'><input hidden type="number"  name='produs_modif_cant'/>
 				    <input hidden type="number"  name='idvz'/>
 				      <input  hidden type="number"  name='cantitate_veche'/>

				    		<div class="form-group">
				    <label for="exampleInputEmail1">Cantitatea nouă</label></br>
<div class="input-group"><input type="number"  name="cantitate_noua" min="1" class="form-control" id="cant_noua" ><span class="input-group-btn"><button class="btn btn-default nmpd-target" id="cant-btn" type="button" readonly="readonly" data-numpad="nmpd3"><i class="fa fa-calculator"></i></button> </span> </div>	<input class='btn btn-primary btn-block' type="submit" name="modific_cant" value="Salvează"/>	</div>

</form>

<?php if(isset($_POST['modific_cant'])){
    
    
    $id_vz=$_POST['idvz'];
        		 $cant_nota_veche=$_POST['cantitate_veche'];
    		 $cantitate_de_adaug=$_POST['cantitate_noua'];
    	 $produs=$_POST['produs_modif_cant'];
	 $psql3 = "SELECT $tabel_final_det_note.cantitate,$tabel_final_det_note.tva_col,$tabel_final_det_note.valoare_vanzare,$tabel_final_det_note.valoare_vanzare_cu_tva,$tabel_final_det_note.discount from $tabel_final_det_note where $tabel_final_det_note.id_vanz='$id_vz';";    
$pstmt3 = $pdo->prepare($psql3);  
$pstmt3->execute(); 
while ($row = $pstmt3->fetch(PDO::FETCH_ASSOC)){ 

 $tva_col=$row['tva_col']/$row['cantitate'];
$valoare_vanzare=$row['valoare_vanzare']/$row['cantitate'];
$valoare_vanzare_cu_tva=$row['valoare_vanzare_cu_tva']/$row['cantitate'];
	 $discount=$row['discount']/$row['cantitate'];
}

        	     $psql12 = "update $tabel_final_det_note set cantitate=0,tva_col=0,valoare_vanzare=0,valoare_vanzare_cu_tva=0,discount=0 where id_vanz='$id_vz';"; 
    

	 
	try{
$pdo->exec($psql12) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 




}catch(PDOException $e)
    {
    echo $psql12 . "<br>" . $e->getMessage();
    } 
    
    for ($i = 1; $i <= $cantitate_de_adaug; $i++) {
    $c=1;

    $psql = "update $tabel_final_det_note set cantitate=cantitate+'$c',tva_col=tva_col+'$tva_col',valoare_vanzare=valoare_vanzare+'$valoare_vanzare',valoare_vanzare_cu_tva=valoare_vanzare_cu_tva+'$valoare_vanzare_cu_tva',discount=discount+'$discount' where id_vanz='$id_vz';"; 
    	try{
$pdo->exec($psql) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 




}catch(PDOException $e)
    {
    echo $psql . "<br>" . $e->getMessage();
    } 


}
    			printf("<script>location.href='imparte_nota.php'</script>");


    
    
    


}


?>
 </div>

				  </div>
		

							  </div>			  
	  
							  </div>
								<!------ Modal modif cantitate end -->
	  						<!------ Modal discount-->
							  <div class="modal fade" id="Discount" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
		  <div class="modal-dialog" role="document">
			<div class="modal-content">
			    
			  <div class="modal-header">
				<h4 class="modal-title" id="myModalLabel">Aplică Discount</h4>
								<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>

			  </div>
			  <div class="modal-body">
			      

				    <form method='POST'><input type="number" hidden name='prod_discount'/>
				    <input type="number" id='cota_calc_tva' hidden name='cota_calc_tva'/>
				    <input type="number" hidden name='idvanzare'/>

				    		<div class="form-group">
				    <label for="exampleInputEmail1">Tip discount</label></br>
<td><input type='radio' name='tip_discount' checked value='procent'> Procent <input  type='radio' name='tip_discount' value='val_fixa'/> Valoare fixă </br> </td>				</div>
	
	<div id="procent" class="form-group" style="display:block">
				    <label for="exampleInputEmail1">Procent discount (%)</label></br>
	<div class="input-group"><input class="form-control" max='100' step='0.01'min='0' value='0' type='number' id='val_procent' name='val_procent' style='width:14.5em;'/><span class="input-group-btn" style='margin-top:2.5px; float:left;'> <button class="btn btn-default nmpd-target" id="proc_disc-btn" type="button" readonly="readonly" data-numpad="nmpd3"><i class="fa fa-calculator"></i></button> </span></div>
</br>
 <input style='margin-top:5px;' class='btn btn-primary btn-block' type="submit" name="apl_disc_proc" value="Aplica discount"/>

</div>
<div class="form-group" style="display:none" id="val_fixa">
		  <label for="exampleInputEmail1">Valoare discount (RON)</label></br>
		  	<div class="input-group"><input type='number' step='0.001' id='val_fix' style='width:14.5em;' class='form-control' name='valoare_fixa' onchange="calc_tva_disc()"/>RON
<span class="input-group-btn" style='margin-top:2.5px; float:left;'> <button class="btn btn-default nmpd-target" id="val_disc-btn" type="button" readonly="readonly" data-numpad="nmpd3"><i class="fa fa-calculator"></i></button> </span></div>
<label for="exampleInputEmail1">Din care TVA</label></br>
<input type='number' step='0.001' readonly id='tva_discount' name='tva_discount' /> RON

 <input style='margin-top:5px;' class='btn btn-primary btn-block' type="submit" name="apl_disc_fix" value="Aplica discount"/>

</div>



</div></div>
				    
				    </form>
<?php 
if(isset($_POST['apl_disc_fix'])){
    
    
    $id_vz=$_POST['idvanzare'];
    
    $p_disc_sql = "SELECT valoare_vanzare_cu_tva from $tabel_final_det_note where id_vanz='$id_vz'";
$p_disc_stmt = $pdo->prepare($p_disc_sql);
$p_disc_stmt->execute(); 
while ($row = $p_disc_stmt->fetch(PDO::FETCH_ASSOC)){

     $valoare_cu_tva=$row['valoare_vanzare_cu_tva'];   

    
}

    $discount=$_POST['valoare_fixa'];

if($discount<=$valoare_cu_tva){
    
    
    $disc_sql = "update $tabel_final_det_note set discount='$discount' where id_vanz='$id_vz';";    
	try{
$pdo->exec($disc_sql) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 
		  
}catch(PDOException $e)
    {
    echo $disc_sql . "<br>" . $e->getMessage();
    } 
    
        printf("<script>location.href='vanzare_magazin.php'</script>");

}
    else{
        
        	 echo '<script language="javascript">';
echo 'alert("Valoarea discountului depaseste valoarea de vanzare!")';
echo '</script>';
    }
}
elseif(isset($_POST['apl_disc_proc'])){
    
    
    $procent=$_POST['val_procent'];
    $id_vz=$_POST['idvanzare'];
    
    $p_disc_sql = "SELECT valoare_vanzare_cu_tva from $tabel_final_det_note where id_vanz='$id_vz'";
$p_disc_stmt = $pdo->prepare($p_disc_sql);
$p_disc_stmt->execute(); 
while ($row = $p_disc_stmt->fetch(PDO::FETCH_ASSOC)){
 $valoare_cu_tva=$row['valoare_vanzare_cu_tva'];   
}
$discount=$valoare_cu_tva*$procent/100;

    $disc_sql = "update $tabel_final_det_note set discount='$discount' where id_vanz='$id_vz';";    
	try{
$pdo->exec($disc_sql) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 
		  
}catch(PDOException $e)
    {
    echo $disc_sql . "<br>" . $e->getMessage();
    } 
    
    
        printf("<script>location.href='vanzare_magazin.php'</script>");

}
?>
					<script>

$('input[type="radio"]').click(function(){

        if($(this).attr("value")=="val_fixa"){
            $("#val_fixa").show('slow');
            $("#procent").hide('slow');

        }
           if($(this).attr("value")=="procent"){
           $("#procent").show('slow');
            $("#val_fixa").hide('slow')
        }
    });

			</script>
  </div>

				  </div>

	  						<!------ Modal discount-->

								<!--NUMERAR END//-->
		
	

		<!--NUMERAR SI CARD-->
		<div class="modal fade" id="Plata_numerar_si_card" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
		  <div style="width:22em" class="modal-dialog" role="document">
			<div class="modal-content">
			    
			  <div class="modal-header">
				<h4 class="modal-title"  id="myModalLabel">Datele incasarii</h4>
								<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>

			  </div>
			  <div class="modal-body" >
			<form class="form-horizontal" method="POST">
		<!-- Custom JS -->
		


   <label for="totalmixt">Total de plata</label>
	<div class="input-group"><input readonly type="number"  step='0.001' name="total" value="<?php echo $total_val_vz_cu_tva; ?>" class="form-control" id="totalmixt"> </div>
        <label for="numerar">Numerar</label>
	<div class="input-group"><input type="number"  step='0.001' name="numerar"  max="<?php echo $total_val_vz_cu_tva;?>" value="<?php echo $total_val_vz_cu_tva; ?>"  step="0.01" min="0"  class="form-control" style="width:14.5em;" id="numerar" onchange="updateCard()"><span class="input-group-btn" style='margin-top:2.5px; float:left;'> <button class="btn btn-default nmpd-target" id="numerar-btn" type="button" readonly="readonly" data-numpad="nmpd3"><i class="fa fa-calculator"></i></button> </span></div>
	   <label for="card">Card</label>
	<div class="input-group"><input  type="number"  step='0.001' min="0" value="0"  name="card" class="form-control" style="width:14.5em;"  step="0.01"  max="<?php echo $total_val_vz_cu_tva;?>" onchange="updateNumerar()" id="card" ><span class="input-group-btn" style='margin-top:2.5px; float:left;'> <button class="btn btn-default nmpd-target" id="card-btn" type="button" readonly="readonly" data-numpad="nmpd3"><i class="fa fa-calculator"></i></button> </span></div>
				 <label for="cif_client">CIF CLIENT</label>
	<div class="input-group">    <input  type="text"   maxlength='10' name="cif_client_m" class="form-control" id="cif_client_m" placeholder="Ex. RO12345678">
	<script>// Autocomplete demo
var availableTags = ["ActionScript", "AppleScript", "Asp", "BASIC", "C", "C++", "Clojure",
	"COBOL", "ColdFusion", "Erlang", "Fortran", "Groovy", "Haskell", "Java", "JavaScript",
	"Lisp", "Perl", "PHP", "Python", "Ruby", "Scala", "Scheme" ];

$('#cif_client_m')
	.keyboard({ layout: 'qwerty' })
	.autocomplete({
		source: availableTags
	})

	.addTyping();
	</script> </div>
				 <div class="modal-footer">

 <button class='btn btn-primary btn-block' type="submit" name="finaliz_bon" value="numerar_si_card">Finalizare Bon</button>
			  </div>

				
				</form>
				
				
				  
				  

				  
				
			  				  				  </div>


							  </div>			  
							  
							  
							  
							  
							  </div> 

			</div>
		
					<!--NUMERAR SI CARD END-->

	<!--TICHETE DE MASA-->
		<div class="modal fade" id="Plata_tichete" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
		  <div style="width:22em" class="modal-dialog" role="document">
			<div class="modal-content">
			    
			  <div class="modal-header">
				<h4 class="modal-title"  id="myModalLabel">Datele incasarii</h4>
								<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>

			  </div>
			  <div class="modal-body" >
			<form class="form-horizontal" method="POST">
		<!-- Custom JS -->
		


   <label for="total_de_plata_tichete">Total de plata</label>
	<div class="input-group"><input readonly type="number"  step='0.001' name="total_de_plata_tichete" value="<?php echo $total_val_vz_cu_tva; ?>" class="form-control" id="total_de_plata_tichete"> </div>
        <label for="numerar">Numărul tichetelor</label>
	<div class="input-group"><input type="number"  step='1' name="numerar"  value="1"  min="1"  class="form-control" style="width:14.5em;" id="nr_tichete" onchange="updateTichete()"><span class="input-group-btn" style='margin-top:2.5px; float:left;'> <button class="btn btn-default nmpd-target" id="nr_tichete-btn" type="button" readonly="readonly" data-numpad="nmpd3"><i class="fa fa-calculator"></i></button> </span></div>
	   <label for="card">Valoarea unui tichet</label>
	<div class="input-group"><input  type="number"  step='0.001' min="0"  class="form-control" style="width:14.5em;"  step="0.01" value="<?php echo $total_val_vz_cu_tva;?>" max="<?php echo $total_val_vz_cu_tva;?>" onchange="updateNrTichete()" id="val_tichet" ><span class="input-group-btn" style='margin-top:2.5px; float:left;'> <button class="btn btn-default nmpd-target" id="val_tichet-btn" type="button" readonly="readonly" data-numpad="nmpd3"><i class="fa fa-calculator"></i></button> </span></div>
	<label for="rest">Valoarea tichetelor</label>
	<div class="input-group">    <input readonly type="number" step='0.001' value="<?php echo $total_val_vz_cu_tva;?>" min="0" max="<?php echo $total_val_vz_cu_tva;?>"  name="total_tichete" class="form-control" id="total_tichete"> </div>
		<label for="rest_de_incasat">Rest de încasat în numerar</label>
	<div class="input-group">   <input  style="width:14.5em;" type="number" step='0.001' value="0" min="0"  onchange="updateRestTichete()"  name="rest_de_incasat" class="form-control" id="rest_de_incasat"><span class="input-group-btn" style='margin-top:2.5px; float:left;'> <button class="btn btn-default nmpd-target" id="rest_de_incasat-btn" type="button" readonly="readonly" data-numpad="nmpd3"><i class="fa fa-calculator"></i></button> </span> </div>
	   <label for="total_de_plata_tichete">Rest de returnat</label>
	<div class="input-group"><input readonly type="number"  step='0.001' name="rest_de_returnat" value='0' class="form-control" id="rest_de_returnat"> </div>
				 <label for="cif_client">CIF CLIENT</label>
	<div class="input-group">    <input  type="text"   maxlength='10' name="cif_client_t" class="form-control" id="cif_client_t">
	<script>// Autocomplete demo
var availableTags = ["ActionScript", "AppleScript", "Asp", "BASIC", "C", "C++", "Clojure",
	"COBOL", "ColdFusion", "Erlang", "Fortran", "Groovy", "Haskell", "Java", "JavaScript",
	"Lisp", "Perl", "PHP", "Python", "Ruby", "Scala", "Scheme" ];

$('#cif_client_t')
	.keyboard({ layout: 'qwerty' })


	.addTyping();
	</script> </div>
				 <div class="modal-footer">

 <button class='btn btn-primary btn-block' type="submit" id='finalizare_tichet' name="finaliz_bon" value="tichete_de_masa">Finalizare Bon</button>
			  </div>

				
				</form>
				
				
				  
				  

				  
				
			  				  				  </div>


							  </div>			  
							  
							  
							  
							  
							  </div> 

			</div>
		
					<!--TICHETE DE MASA END-->


			</div>
		  </div>



		
		  
		<script>
		
		$(document).ready(function() {

$('.discount').on('click', function() {
$('#Discount').modal('show');
var prod_discount = $(this).val();
var dataValue = this.getAttribute("data-value");
var idvanzare = this.getAttribute("name");

$('[name=prod_discount]').val(prod_discount);
$('[name=cota_calc_tva]').val(dataValue);
$('[name=idvanzare]').val(idvanzare);



});
$('.modif_cant').on('click', function() {
$('#Cantitate').modal('show');
var produs_modif = $(this).val();
var cantitate_curenta = this.getAttribute("data-value");
var idvanzare = this.getAttribute("name");

$('[name=produs_modif_cant]').val(produs_modif);
$('[name=cantitate_noua]').val(cantitate_curenta);

$('[name=cantitate_noua]').attr({
       "max" : cantitate_curenta      // substitute your own
                // values (or variables) here
    });
    $('[name=cantitate_veche]').val(cantitate_curenta);

$('[name=idvz]').val(idvanzare);



});

		$('#plata_numerar_si_card').on('click', function() {
$('#Plata_numerar_si_card').modal('show'); 
});
		$('#plata_tichete').on('click', function() {
$('#Plata_tichete').modal('show'); 
});


    var banideprim = parseFloat(document.getElementById("baniprim").value).toFixed(2);

      if (banideprim <=0) {
        $('#plata_numerar').attr('disabled', 'disabled');
        $('#plata_card').attr('disabled', 'disabled');
        $('#plata_numerar_si_card').attr('disabled', 'disabled');
        $('#plata_tichete').attr('disabled', 'disabled');

    } else {
        $('#plata_numerar').removeAttr('disabled');
        $('#plata_card').removeAttr('disabled');
        $('#plata_numerar_si_card').removeAttr('disabled');
        $('#plata_tichete').removeAttr('disabled');

    }
    document.getElementById("cod_bare").focus();


	});

</script>

</body>
</html>
