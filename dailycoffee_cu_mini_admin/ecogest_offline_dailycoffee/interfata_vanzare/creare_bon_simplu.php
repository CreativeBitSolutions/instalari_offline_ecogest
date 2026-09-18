<?php  
include('session.php');

	date_default_timezone_set('UTC+2');
		date_default_timezone_set("Europe/Bucharest");
		
$ora_bon = date("H:i:s", strtotime('+0 hours'));
 $data_bon = date("Y-m-d", strtotime('+0 hours'));
 $adm_id=$_SESSION['admin_id'];
$dsql = "SELECT * FROM $tabel_final_admins where admin_id='$adm_id'";
$dstmt = $pdo->prepare($dsql);
$dstmt->execute(); 
while ($row = $dstmt->fetch(PDO::FETCH_ASSOC)){
$admin_firstname=$row['admin_firstname'];
$admin_lastname=$row['admin_lastname'];
}
    $ccom_sql = "SELECT nrbon FROM $tabel_final_bonuri where status='S' and operator='$adm_id'";    
$ccom_stmt = $pdo->prepare($ccom_sql);  
$ccom_stmt->execute(); 
 $count=$ccom_stmt->rowCount();
         if($count == 0) {
                $sql="insert into $tabel_final_bonuri(operator) values('$adm_id')"; 	 

try{
$pdo->exec($sql) or die(print_r($pdo->errorInfo(), true));   

    // afiseaza un mesaj de succes 
 
}catch(PDOException $e)
    {
    echo $sql . "<br>" . $e->getMessage();
    }
    
    $crccom_sql = "SELECT max(nrbon) as nrb FROM $tabel_final_bonuri where operator='$adm_id'";    
$crccom_stmt = $pdo->prepare($crccom_sql);  
$crccom_stmt->execute(); 
while ($row = $crccom_stmt->fetch(PDO::FETCH_ASSOC)){ 
    $nr_bon=$row['nrb'];
}
         }

        // If result matched $myusername and $mypassword, the result table's number of rows must be 1 row
	// if the table has 1 rows redirect the user to admin_index.php
	
	if(!isset($_SESSION['nr_bon'])){
	    $ccom_sql = "SELECT nrbon FROM $tabel_final_bonuri where status='S' and operator='$adm_id'";    
$ccom_stmt = $pdo->prepare($ccom_sql);  
$ccom_stmt->execute(); 
 $count=$ccom_stmt->rowCount();
        if($count >= 1) {
while ($row = $ccom_stmt->fetch(PDO::FETCH_ASSOC)){ 
    $nr_bon=$row['nrbon'];

}
}
else{
    $sql="insert into $tabel_final_bonuri(operator) values('$adm_id')"; 	 

try{
$pdo->exec($sql) or die(print_r($pdo->errorInfo(), true));   

    // afiseaza un mesaj de succes 

}catch(PDOException $e)
    {
    echo $sql . "<br>" . $e->getMessage();
    }
    
}

}
else{
   
    $nr_bon=$_SESSION['nr_bon'];
}

?>
				<html>
				<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="description" content="">
  <meta name="author" content="">
  <title>Magazin</title>
  <!-- Bootstrap core CSS-->
    <!-- Bootstrap core JavaScript-->

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
<style>html {
  overflow:   scroll;
}
::-webkit-scrollbar {
    width: 0px;
    background: transparent; /* make scrollbar transparent */
}</style>
</head>

<body class="bg-dark">
<!-- about -->

    <div class="wrapper" style="margin:0">








<?php 


if(isset($_POST['deconectare'])){

unset($_SESSION['admin_id']);
printf("<script>location.href='logout.php'</script>");
							
							} 
							
							?> 


    <div class="tab" style='margin:0'>
             <button disabled class="tablinks">Bonul nr:<?php echo $nr_bon; ?></button>


   <!--          <button onclick="location.href = 'list_prod_bar_fin.php?nr_bon=<?php // echo $nr_bon;?>';" class="tablinks"  type="submit">Listare produse la BAR</button>
                          <button onclick="location.href = 'list_prod_buc_fin.php?nr_bon=<?php // echo  $nr_bon;?>';" class="tablinks"  type="submit">Listare produse la BUCATARIE</button> -->
             <button data-toggle="modal" data-target="#sume_sertar" class="tablinks"  type="submit">Sume sertar</button>
<button disabled style="float:right" class="tablinks" >Operator: <?php echo $admin_firstname.' '.$admin_lastname;?></button>
<button disabled style="float:right" class="tablinks" id="timestamp" ></button>


</div>

    <div id="one" style="height:43em;">
<table class="table table-dark" style='margin:0;width:90.5%;'>
  <div class='row'>
  <?php
  
  
  $date_firma = "SELECT mod_listare,vanzare_sub_stoc,ajustare_adaos from $tabel_final_date_firma";    
$date_firma_stmt = $pdo->prepare($date_firma);  
$date_firma_stmt->execute(); 
while ($row = $date_firma_stmt->fetch(PDO::FETCH_ASSOC)){ 

$_SESSION['vanzare_sub_stoc']=$row['vanzare_sub_stoc'];
$_SESSION['mod_listare']=$row['mod_listare'];
$_SESSION['ajustare_adaos']=$row['ajustare_adaos'];
}

//afisare randuri NIR
$f_sql = "SELECT $tabel_final_nomenclator.departament,$tabel_final_det_bonuri.discount,$tabel_final_det_bonuri.cod_p,$tabel_final_nomenclator.den_p,cote_tva.cota,$tabel_final_nomenclator.um,$tabel_final_det_bonuri.cantitate,$tabel_final_det_bonuri.tva_col,$tabel_final_det_bonuri.pret_vanzare,$tabel_final_det_bonuri.valoare_vanzare,$tabel_final_det_bonuri.valoare_vanzare_cu_tva,$tabel_final_det_bonuri.id_vanz from $tabel_final_nomenclator inner join $tabel_final_det_bonuri on $tabel_final_det_bonuri.cod_p=$tabel_final_nomenclator.cod_p inner join cote_tva on $tabel_final_nomenclator.cota_tva=cote_tva.id where nr_bon='$nr_bon' order by $tabel_final_det_bonuri.id_vanz,$tabel_final_nomenclator.den_p;";    
$f_stmt = $pdo->prepare($f_sql);  
$f_stmt->execute(); 
echo "  
  <div class='input-row'><thead>
<tbody class='tbody lista_prod'  style='height:20em;'>
<tr>
<th style='width:100%'>Produs</th>
<th>Cantitate</th>
<th><div>Valoare</div><div style='color:green;'>-Discount</div></th>
<th style='width:20px;'>Actiuni</th>



</thead>";
while ($row = $f_stmt->fetch(PDO::FETCH_ASSOC)){
    $departament=$row['departament'];
$codul_produsului=$row['cod_p'];
$produs=$row['den_p'];
$cantitate=$row['cantitate'];
$tva_col=$row['tva_col'];
$pret_vanzare=$row['pret_vanzare'];
$valoare_vanzare=$row['valoare_vanzare'];
$id_vanz=$row['id_vanz'];
$id_simplu_vanz=$row['id_vanz'];
$id_simplu_vanz=strval($id_simplu_vanz);
$id_simplu_vanz.='D';
$valoare_vanzare_c_tva=round(($row['valoare_vanzare_cu_tva'])*100)/100;
$cota=$row['cota'];
$d=$row['discount'];

  echo "
   <tr>
    <td style='width:100%;height:auto; white-space: normal;'><div>$produs</div>";echo"</div></td>
    <td ><button style='display:inline-block;' name='$id_vanz' value='$codul_produsului' data-value='$cantitate' class='btn btn-primary btn-block modif_cant' title='Click pentru modificarea cantitatii'>X  $cantitate</button></td>
	<td><div>$valoare_vanzare_c_tva</div>";echo "<div style='color:green;'>-$d</div>"; echo"</td>
	<td><button name='$id_vanz' value='$codul_produsului' data-value='$cota' class='btn btn-primary btn-block discount' title='Click pentru aplicarea unui discount'>%</button><form method='post'></br><input style='background-color:white;color:red;' class='btn btn-primary btn-block' title='Click pentru a sterge' type='submit' value='X' name='$id_simplu_vanz'></form></td>
  </tr>
  
  
  
  " 
  
  ;
  if (isset($_POST[$id_simplu_vanz])) {
					
					$sterg_sql="SELECT $tabel_final_nomenclator.departament,$tabel_final_nomenclator.gestiune,$tabel_final_det_bonuri.cod_p,$tabel_final_det_bonuri.cantitate FROM $tabel_final_det_bonuri INNER JOIN $tabel_final_nomenclator on $tabel_final_det_bonuri.cod_p=$tabel_final_nomenclator.cod_p where $tabel_final_det_bonuri.id_vanz = '$id_vanz' ";
					$sterg_f_stmt = $pdo->prepare($sterg_sql);  
$sterg_f_stmt->execute(); 

while ($row = $sterg_f_stmt->fetch(PDO::FETCH_ASSOC)){ 

$c_p=$row['cod_p'];
$cant_vand=$row['cantitate'];
$gst=$row['gestiune'];
$dep=$row['departament'];
}

if($gst=="PF"){
					$sql="DELETE FROM $tabel_final_det_bonuri WHERE $tabel_final_det_bonuri.id_vanz = '$id_vanz';";
					
$pdo->exec($sql) or die(print_r($pdo->errorInfo(), true));

     	 $psql8 = "SELECT $tabel_final_retete.cod_mat,$tabel_final_retete.cant_folos from $tabel_final_retete where $tabel_final_retete.cod_p='$c_p';";    
$pstmt8 = $pdo->prepare($psql8);  
$pstmt8->execute(); 

while ($row = $pstmt8->fetch(PDO::FETCH_ASSOC)){ 
    
    $materie=$row['cod_mat'];
    $cant_f=$row['cant_folos']*$cant_vand;
    $update_stoc_sterg_pf_sql = "update $tabel_final_stoc set cantitate=cantitate+'$cant_f' where cod_p='$materie'; "; 
    $update_stoc_sterg_pf_stmt = $pdo->prepare($update_stoc_sterg_pf_sql);  
$update_stoc_sterg_pf_stmt->execute(); 
    
    
}
}

elseif($gst!="PF"){
    	$sql="DELETE FROM $tabel_final_det_bonuri WHERE $tabel_final_det_bonuri.id_vanz = '$id_vanz';update $tabel_final_stoc set cantitate=cantitate+'$cant_vand' where cod_p='$c_p';";
				
$pdo->exec($sql) or die(print_r($pdo->errorInfo(), true));

    
}
if($dep=='BUC'){
    	$sql3="DELETE FROM $tabel_final_de_listat_buc WHERE $tabel_final_de_listat_buc.id_vanz = '$id_vanz';";
					
   $stmtsql3 = $pdo->prepare($sql3);  
$stmtsql3->execute(); 
    
    
}
elseif($dep=='BAR'){
        	$sql3="DELETE FROM $tabel_final_de_listat_bar WHERE $tabel_final_de_listat_bar.id_vanz = '$id_vanz';";
					
   $stmtsql3 = $pdo->prepare($sql3);  
$stmtsql3->execute(); 
}
			printf("<script>location.href='creare_bon_simplu.php'</script>");
				}
  }
  

 
  ?></tbody></div>   <div class="col-6 buttons">  <button class='btn-sm scrol hidden-xs' title='Mentineti apasat pentru a derula in sus' id ="scrollup"><i class="fa fa-chevron-up"></i></button>
</br>
<button title='Mentineti apasat pentru a derula in jos' class='btn-sm scrol hidden-xs' id ="scrolldown"><i class="fa fa-chevron-down"></i></button>   
</div></div></table>
  <style>.row div{
  float: left;
}
.input-row{
  line-height: 30px;
  height: 30px;
}
.scrol {
  background-color: #4f4f4f;
  border-radius: 0;
  color: white;
  padding: 0 4px;
  height:16em;
}
.scrol2 {
  background-color: #4f4f4f;
  border-radius: 0;
  color: white;
  padding: 0 4px;
  height:10em;
}
.scrol3 {
  background-color: #4f4f4f;
  border-radius: 0;
  color: white;
  padding: 0 4px;
  height:15em;
}
.buttons{
  float:right;
  margin-right:0.5em;
}

</style>
  

  <table class='table table-bordered test'>
<form id='plata' method='POST'>
    
 <tr class='tr' style='font-weight:bold;font-size:20px;'>
<?php 
 
 $f_tot_sql = "SELECT sum($tabel_final_det_bonuri.valoare_vanzare_cu_tva) as tot_v_vz_c_tva from $tabel_final_det_bonuri  where nr_bon='$nr_bon'; ";    
$f_tot_stmt = $pdo->prepare($f_tot_sql);  
$f_tot_stmt->execute();

while ($row = $f_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$valoare_f_vz=$row['tot_v_vz_c_tva'];
}

   ?>

 <?php 
 
 $f_tot_sql = "SELECT sum($tabel_final_det_bonuri.valoare_vanzare_cu_tva) as tot_vz_c_tva from $tabel_final_det_bonuri where nr_bon='$nr_bon'; ";    
$f_tot_stmt = $pdo->prepare($f_tot_sql);  
$f_tot_stmt->execute();

while ($row = $f_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_val_vz_cu_tva=round($row['tot_vz_c_tva'],2);
}
 $disc_tot_sql = "SELECT sum($tabel_final_det_bonuri.discount) as total_disc from $tabel_final_det_bonuri where nr_bon='$nr_bon'; ";    
$disc_tot_stmt = $pdo->prepare($disc_tot_sql);  
$disc_tot_stmt->execute();

while ($row = $disc_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_discount=$row['total_disc'];
}

$total_val_vz_cu_tva=round(($total_val_vz_cu_tva-$total_discount)*100)/100;?>
<td class='td'>Total de încasat<input  step='0.001' readonly type="number" name="total" value="<?php echo $total_val_vz_cu_tva; ?>" class="form-control" id="total" onchange="updateDue()">  </td>	

	<!-- jQuery (required) & jQuery UI + theme (optional) -->
	<link href="keyboard/docs/css/jquery-ui.min.css" rel="stylesheet">
	<!-- still using jQuery v2.2.4 because Bootstrap doesn't support v3+ -->
	<!-- <script src="keyboard/docs/js/jquery-migrate-3.0.0.min.js"></script> -->

	<!-- keyboard widget css & script (required) -->
	<link href="keyboard/css/keyboard.css" rel="stylesheet">

	<!-- demo only -->
		<td class='td'>C.I.F. CLIENT <input  id="cif_client" value="<?php echo $_SESSION['cif_client'];?>" type="text" maxlength="10" name="cif_client" placeholder="Ex. RO12345678"> <button type="button"  class="btn btn-primary" style='width:100%' data-toggle="modal" data-target="#set_client" >ALEGE CLIENT</button>  

</td><td class='td'><label for="rest">Suma încasată</label>	<div class="input-group"><input step="0.001" value="<?php echo $total_val_vz_cu_tva; ?>" name='numerarprim' min="<?php echo $total_val_vz_cu_tva;?>" type="number"  id="baniprim" class="form-control" style="width:10em;"  onfocusout="updateDue()">

	</td><td class='td'>   <label for="rest">Rest</label>
 <input readonly type="number" step='0.001' value="0" min="0" name="rest_numerar" class="form-control" id="rest"></td></tr>
	


	</form>
	


<?php
if(isset($_POST['finaliz_bon'])){
    
    
    
 $ora_bon = date("H:i:s", strtotime('+0 hours'));
 $data_bon = date("Y-m-d", strtotime('+0 hours'));
 $calc_tva_col_sql = "SELECT $tabel_final_det_bonuri.discount,$tabel_final_det_bonuri.valoare_vanzare_cu_tva,cote_tva.cota,$tabel_final_det_bonuri.id_vanz from $tabel_final_nomenclator inner join $tabel_final_det_bonuri on $tabel_final_det_bonuri.cod_p=$tabel_final_nomenclator.cod_p inner join cote_tva on $tabel_final_nomenclator.cota_tva=cote_tva.id where nr_bon='$nr_bon'; ";    
$calc_tva_col_stmt = $pdo->prepare($calc_tva_col_sql);  
$calc_tva_col_stmt->execute();

while ($row = $calc_tva_col_stmt->fetch(PDO::FETCH_ASSOC)){
$val_vz_c_tva=$row['valoare_vanzare_cu_tva'];
$cota=$row['cota'];

$id_v=$row['id_vanz'];
$dis=$row['discount'];
$tva_col=($val_vz_c_tva-$dis)*$cota/(100+$cota);
$tva_col=round($tva_col,2);
 $update_tva_col_sql = "UPDATE $tabel_final_det_bonuri set tva_col='$tva_col' where id_vanz='$id_v'";    
$update_tva_col_stmt = $pdo->prepare($update_tva_col_sql);  
$update_tva_col_stmt->execute();
    
}
 $f_tot_sql = "SELECT sum($tabel_final_det_bonuri.tva_col) as t_tva from $tabel_final_det_bonuri  where nr_bon='$nr_bon'; ";    
$f_tot_stmt = $pdo->prepare($f_tot_sql);  
$f_tot_stmt->execute();

while ($row = $f_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_tva_col=$row['t_tva'];
}
 
	if($_POST['finaliz_bon']=='numerar'){
	    $cif_client=$_POST['cif_client'];
	    $_SESSION['cif_client']=$_POST['cif_client'];

$rest=$_POST['rest_numerar'];
$numerar=$_POST['numerarprim'];
$_SESSION['numerarprim']=$_POST['numerarprim'];

$fin_sql = "UPDATE $tabel_final_bonuri SET operator='$adm_id',rest='$rest',tva_colectata='$total_tva_col',valoare_vanzare_cu_tva='$valoare_f_vz',data_bon='$data_bon',ora_bon='$ora_bon',status='F',numerar='$numerar',discount='$total_discount',cif_client='$cif_client' WHERE nrbon='$nr_bon';";    
	}
		elseif($_POST['finaliz_bon']=='card'){
		    	    $cif_client=$_POST['cif_client'];
		    	    	    $_SESSION['cif_client']=$_POST['cif_client'];

		  $card=$total_val_vz_cu_tva;
$_SESSION['cardprim']=$total_val_vz_cu_tva;
$fin_sql = "UPDATE $tabel_final_bonuri SET operator='$adm_id',tva_colectata='$total_tva_col',valoare_vanzare_cu_tva='$valoare_f_vz',data_bon='$data_bon',ora_bon='$ora_bon',status='F',card='$card',discount='$total_discount',cif_client='$cif_client' WHERE nrbon='$nr_bon';";    

		}
		
			elseif($_POST['finaliz_bon']=='numerar_si_card'){
			    	    $cif_client_m=$_POST['cif_client_m'];
			    	    $_SESSION['cif_client']=$_POST['cif_client_m'];

$card=$_POST['card'];
$numerar=$_POST['numerar'];
$_SESSION['numerarprim']=$_POST['numerar'];
$_SESSION['cardprim']=$_POST['card'];

$fin_sql = "UPDATE $tabel_final_bonuri SET operator='$adm_id',tva_colectata='$total_tva_col',valoare_vanzare_cu_tva='$valoare_f_vz',data_bon='$data_bon',ora_bon='$ora_bon',status='F',numerar='$numerar',card='$card',discount='$total_discount',cif_client='$cif_client_m' WHERE nrbon='$nr_bon';";    

		}
		
				elseif($_POST['finaliz_bon']=='tichete_de_masa'){
			    	    $cif_client_t=$_POST['cif_client_t'];
			    	    $_SESSION['cif_client']=$_POST['cif_client_t'];
$tichete=$_POST['total_tichete'];
$numerar=$_POST['rest_de_incasat'];
$rest=$_POST['rest_de_returnat'];
$_SESSION['rest_tichete']=$_POST['rest_de_incasat'];
$_SESSION['total_tichete']=$_POST['total_tichete'];

$fin_sql = "UPDATE $tabel_final_bonuri SET operator='$adm_id',tva_colectata='$total_tva_col',valoare_vanzare_cu_tva='$valoare_f_vz',data_bon='$data_bon',ora_bon='$ora_bon',status='F',numerar='$numerar',tichete='$tichete',rest='$rest',discount='$total_discount',cif_client='$cif_client_t' WHERE nrbon='$nr_bon';";    

		}
		elseif($_POST['finaliz_bon']=='protocol'){
		    $pe_protocol=1;
	    $cif_client=$_POST['cif_client'];
	    $_SESSION['cif_client']=$_POST['cif_client'];

$rest=$_POST['rest_numerar'];
$numerar=$_POST['numerarprim'];
$_SESSION['numerarprim']=$_POST['numerarprim'];

$fin_sql = "UPDATE $tabel_final_bonuri SET operator='$adm_id',rest='$rest',tva_colectata='$total_tva_col',valoare_vanzare_cu_tva='$valoare_f_vz',data_bon='$data_bon',ora_bon='$ora_bon',status='F',protocol='$numerar',discount='$total_discount',cif_client='$cif_client' WHERE nrbon='$nr_bon';";    
	}
	 
	try{
$pdo->exec($fin_sql) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 
		  
}catch(PDOException $e)
    {
    echo $fin_sql . "<br>" . $e->getMessage();
    } 
 
	
	 

$misc_sql = "SELECT $tabel_final_nomenclator.gestiune,$tabel_final_det_bonuri.cod_p,$tabel_final_det_bonuri.cantitate from $tabel_final_det_bonuri INNER JOIN $tabel_final_nomenclator on $tabel_final_det_bonuri.cod_p=$tabel_final_nomenclator.cod_p where nr_bon='$nr_bon';";     
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
 


if($pe_protocol==1){
printf("<script>location.href='listeaza_bon_fin.php'</script>");

}
    if($_SESSION['mod_listare']=='complex'){
printf("<script>location.href='dwred_cu_listare.php'</script>");
}
else{
    printf("<script>location.href='dwred.php'</script>");

}
	
}

  ?>
  </th>
</table>

    
    
</div>
 <div id="two">



<table id="myTable" class='table' style="margin:0;width:86.5%;height:auto;">
    
    <?php
$psql = "SELECT id_categorie,den_categ from $tabel_final_categorii; ";    
$pstmt = $pdo->prepare($psql);  
$pstmt->execute(); 
$prod_count=$pstmt->rowCount(); ?>

        <form name="get_state">
<div class="block1 content_cat" >
<div class="block2">  
<select id='selectInp' size='<?php echo $prod_count;?>' onclick='window.loadStatesMag()' onchange='window.loadStatesMag()' class='form-control' name='categ'><li><option id='toate' value='999'>Toate produsele</option><li> 

 <?php 
while ($row = $pstmt->fetch(PDO::FETCH_ASSOC)){ 
                      $den_categ=$row['den_categ'];
             $id_categorie=$row['id_categorie'];
			                 
							   
              echo "<option value='$id_categorie'>$den_categ</option>";    
             
			

}

 
 
 
?>   </select></div> </div></form>
<button class='form-control leftArrow'  title='Mentineti apasat pentru a derula spre stanga' style='width:50%;float:left'>
&larr;      </button>
      <button class='form-control rightArrow' title='Mentineti apasat pentru a derula spre dreapta' style='width:50%;float:right'>
&rarr;      </button>

<style>
    *:focus {
    outline: none;
}

#selectInp {
  appearance: none;
  -webkit-appearance: none;
  width: auto !important;
min-width:305px;
 background-color:	#F0FFFF;
  padding:3px;
    margin: 0;
    -webkit-border-radius:4px;
    -moz-border-radius:4px;
    border-radius:4px;
    -webkit-box-shadow: 0 3px 0 #ccc, 0 -1px #fff inset;
    -moz-box-shadow: 0 3px 0 #ccc, 0 -1px #fff inset;
    box-shadow: 0 3px 0 #ccc, 0 -1px #fff inset;
    border:none;
    outline:none;
    display: inline-block;
    -webkit-appearance:none;
    -moz-appearance:none;
    appearance:none;
    cursor:pointer;
    overflow-x:hidden;
overflow-y:hidden;
}

.block2 #selectInp option {
  display:inline-block;
  font-size:24px;
  border-right:2px outset;
 color:green;

}

.block1 {
max-width: 600px;
height:35px;
overflow-x:hidden;
overflow-y:hidden;

display: block;
border: 1px solid #ccc;
-moz-border-radius: 9px 9px 9px 9px;
   -webkit-border-radius: 9px 9px 9px 9px;
   border-radius: 9px 9px 9px 9px;
   box-shadow: 1px 1px 11px #330033;
}

.block2 {
display: inline-block;
vertical-align: top;
overflow-x:hidden;
overflow-y:hidden;
border-right: 1px solid #a4a4a4;

}
</style>





	<div class="block" id="autocomplete">
		<input id="text"  type="text" onchange="myFunctions()" placeholder="Caută după denumirea produsului...">
	</div>

  <div class='row'>
<div class='input-row'><tbody id='states_container' class='tbody lista_nomencl'>
        <?php 
     
$test_sql = "SELECT $tabel_final_nomenclator.gestiune,$tabel_final_nomenclator.cod_p,$tabel_final_nomenclator.um,$tabel_final_nomenclator.den_p,$tabel_final_stoc.cantitate from $tabel_final_nomenclator inner join $tabel_final_stoc on $tabel_final_stoc.cod_p=$tabel_final_nomenclator.cod_p where $tabel_final_nomenclator.gestiune='MR'";    
$test_stm = $pdo->prepare($test_sql);  
$test_stm->execute(); 

while ($row = $test_stm->fetch(PDO::FETCH_ASSOC)){ 
    $den_p=$row['den_p'];
    $cod_p=$row['cod_p'];
 $stocc=$row['cantitate'];
 $gestiune=$row['gestiune'];
 					if($_SESSION['vanzare_sub_stoc']==0){


if($gestiune=="PF"){
    
       $reteta_sql = "SELECT $tabel_final_retete.cod_mat from $tabel_final_retete where $tabel_final_retete.cod_p='$cod_p'";    
$reteta_stmt = $pdo->prepare($reteta_sql);  
$reteta_stmt->execute(); 
$materii_reteta=$reteta_stmt->rowCount(); 
    $pf_sql = "SELECT $tabel_final_retete.cod_mat,$tabel_final_stoc.cantitate as stoc_mat,$tabel_final_retete.cant_folos from $tabel_final_retete inner join $tabel_final_stoc on $tabel_final_retete.cod_mat=$tabel_final_stoc.cod_p where $tabel_final_retete.cod_p='$cod_p' and $tabel_final_stoc.cantitate>=$tabel_final_retete.cant_folos";    
$pf_stm = $pdo->prepare($pf_sql);  
$pf_stm->execute(); 
$stoc_mat=$pf_stm->rowCount();
if($stoc_mat!=$materii_reteta){

     echo"<tr>
   <td  width='700'><button disabled style='background-color:red;color:black;width:100%; font-size:1em; height:auto; white-space: normal;
' class='btn btn-primary btn-block' type='submit' name='$cod_p'>$den_p</button></td>
  </tr> ";
    
}

elseif($stoc_mat==$materii_reteta){
    
     echo"<tr>
   <td  width='700'><form method='POST'><button style='width:100%; font-size:1em; height:auto; white-space: normal;
' class='btn btn-primary btn-block' type='submit' name='$cod_p'>$den_p</button></form></td>
  </tr> ";  
}

}
else{
 if($stocc<=0){
 echo"<tr>
   <td  width='700'><button disabled style='background-color:red;color:black;width:100%; font-size:1em; height:auto; white-space: normal;
' class='btn btn-primary btn-block' type='submit' name='$cod_p'>$den_p</button></td>
  </tr> ";}
  else{
     echo"<tr>
   <td  width='700'><form method='POST'><button style='width:100%; font-size:1em; height:auto; white-space: normal;
' class='btn btn-primary btn-block' type='submit' name='$cod_p'>$den_p</button></form></td>
  </tr> ";  
      
  }
}
 					}
 					
 					else{
 					    
 					    echo"<tr>
   <td  width='700'><form method='POST'><button style='width:100%; font-size:1em; height:auto; white-space: normal;
' class='btn btn-primary btn-block' type='submit' name='$cod_p'>$den_p</button></form></td>
  </tr> "; 
 					}
 if(isset($_POST[$cod_p])){
    
	 $produs=$cod_p;
	 $psql2 = "SELECT $tabel_final_nomenclator.gestiune,$tabel_final_nomenclator.cod_p,$tabel_final_nomenclator.proc_discount,$tabel_final_nomenclator.um,$tabel_final_nomenclator.den_p,$tabel_final_nomenclator.pret,cote_tva.cota,$tabel_final_nomenclator.proc_adaos,$tabel_final_stoc.cantitate from $tabel_final_nomenclator INNER JOIN cote_tva on $tabel_final_nomenclator.cota_tva=cote_tva.id inner join $tabel_final_stoc on $tabel_final_stoc.cod_p=$tabel_final_nomenclator.cod_p where $tabel_final_nomenclator.cod_p='$produs';";    
$pstmt2 = $pdo->prepare($psql2);  
$pstmt2->execute(); 

while ($row = $pstmt2->fetch(PDO::FETCH_ASSOC)){ 
                      $den_prod=$row['den_p'];
			                   $pret_achiz=$row['pret'];
							   $cota_tva=$row['cota'];
							   $proc_ad=$row['proc_adaos'];
							   
							  
							   $stoc=$row['cantitate'];             
			$um=$row['um'];
			$proc_d=$row['proc_discount'];
			 $gestiune=$row['gestiune'];

}
	 
	 $cantitate=1;
	 $adaos_unitar=$pret_achiz*$proc_ad/100;
	 $v_adaos=$adaos_unitar*$cantitate;
	 $tva_neex=$v_adaos*$cota_tva/100;
	 $pret_vanzare=round(($pret_achiz+$adaos_unitar),2);
	 $pret_vanzare=floor($pret_vanzare*100)/100;
	 $valoare_vanzare=$pret_vanzare*$cantitate;
	 $tva_col=round(($valoare_vanzare*$cota_tva/100),2);
	 $valoare_vanzare_cu_tva=floor(($valoare_vanzare+$tva_col)*100)/100;
	 $discount=$valoare_vanzare_cu_tva*$proc_d/100;
	 // aici stergi daca ai probleme
$discount=round($discount,2);



$psql3 = "SELECT id_vanz from $tabel_final_det_bonuri where nr_bon='$nr_bon' and cod_p='$produs';";    
$pstmt3 = $pdo->prepare($psql3);  
$pstmt3->execute(); 
$prodcount=$pstmt3->rowCount();


if($gestiune!="PF"){
if($prodcount==0){

$psql = "insert into $tabel_final_det_bonuri(nr_bon,cod_p,cantitate,tva_col,pret_vanzare,valoare_vanzare,valoare_vanzare_cu_tva,discount,data,ora) values('$nr_bon','$produs','$cantitate','$tva_col','$pret_vanzare','$valoare_vanzare','$valoare_vanzare_cu_tva','$discount','$data_bon','$ora_bon');
update $tabel_final_stoc set cantitate=cantitate-'$cantitate' where cod_p='$produs'; ";   
}

elseif($prodcount>0){



    $psql = "update $tabel_final_det_bonuri set cantitate=cantitate+'$cantitate',tva_col=tva_col+'$tva_col',valoare_vanzare=valoare_vanzare+'$valoare_vanzare',valoare_vanzare_cu_tva=valoare_vanzare_cu_tva+'$valoare_vanzare_cu_tva',discount=discount+'$discount' where nr_bon='$nr_bon' and cod_p='$produs';
update $tabel_final_stoc set cantitate=cantitate-'$cantitate' where cod_p='$produs'; ";

     
}
	 
	try{
$pdo->exec($psql) or die(print_r($pdo->errorInfo(), true));  

    			printf("<script>location.href='creare_bon_simplu.php'</script>");

}catch(PDOException $e)
    {
    echo $psql . "<br>" . $e->getMessage();
    } 
}

elseif($gestiune="PF"){
    if($prodcount==0){

    $psql = "insert into $tabel_final_det_bonuri(nr_bon,cod_p,cantitate,tva_col,pret_vanzare,valoare_vanzare,valoare_vanzare_cu_tva,discount,data,ora) values('$nr_bon','$produs','$cantitate','$tva_col','$pret_vanzare','$valoare_vanzare','$valoare_vanzare_cu_tva','$discount','$data_bon','$ora_bon');";   }

elseif($prodcount>0){
    


    $psql = "update $tabel_final_det_bonuri set cantitate=cantitate+'$cantitate',tva_col=tva_col+'$tva_col',valoare_vanzare=valoare_vanzare+'$valoare_vanzare',valoare_vanzare_cu_tva=valoare_vanzare_cu_tva+'$valoare_vanzare_cu_tva',discount=discount+'$discount' where nr_bon='$nr_bon' and cod_p='$produs'; "; 
    
}
	 
	try{
$pdo->exec($psql) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 
    
        	 $psql7 = "SELECT $tabel_final_retete.cod_mat,$tabel_final_retete.cant_folos from $tabel_final_retete where $tabel_final_retete.cod_p='$produs';";    
$pstmt7 = $pdo->prepare($psql7);  
$pstmt7->execute(); 

while ($row = $pstmt7->fetch(PDO::FETCH_ASSOC)){ 
    
    $materie=$row['cod_mat'];
    $cant_f=$row['cant_folos'];
    $stoc_upd_mat_sql = "update $tabel_final_stoc set cantitate=cantitate-'$cant_f' where cod_p='$materie'; "; 
    $stoc_upd_mat_stmt = $pdo->prepare($stoc_upd_mat_sql);  
$stoc_upd_mat_stmt->execute(); 
    
    
}

}catch(PDOException $e)
    {
    echo $psql . "<br>" . $e->getMessage();
    }
    


    			printf("<script>location.href='creare_bon_simplu.php'</script>");


 
 }
 
    
}


}
      ?>
 
</tbody>
</div>  <div class='buttons'>  </br><button class='btn-sm scrol2 hidden-xs' title='Mentineti apasat pentru a derula in sus' id ="scrollup2"><i class="fa fa-chevron-up"></i></button>
</br>
<button title='Mentineti apasat pentru a derula in jos' class='btn-sm scrol2 hidden-xs' id ="scrolldown2"><i class="fa fa-chevron-down"></i></button>   
</div></div>

</table> </br></br>
<form method="POST" class='hidden-xs' name="prod"><h4 style='text-align:center'>Adăugare după codul de bare</h4>
<table  style='margin-bottom:2px;width:100%'><tr><td style="padding-left:20px;font-weight:bold;">Cantitate</td><td style='margin:0;padding:0'><input class="form-control" type="number" id='cant_cod_b' name="cantitate" min="0.001" step='0.001' value="1" ></td></tr></table>

 <input type="text" id='cod_bare' onkeyup="checkUserName()" class='form-control' name='cod_bare_prod' style="width:100%;" placeholder="Scanați codul de bare...">
  <input style='display:none' class='btn btn-primary btn-block' type="submit" name="adaug_produs_scanner" value="Adauga"></form>

   <?php

 if(isset($_POST['adaug_produs_scanner'])){
	 $cod_bare_prod=$_POST['cod_bare_prod'];
	       

	 $psql2 = "SELECT $tabel_final_nomenclator.gestiune,$tabel_final_nomenclator.cod_p,$tabel_final_nomenclator.proc_discount,$tabel_final_nomenclator.um,$tabel_final_nomenclator.den_p,$tabel_final_nomenclator.pret,cote_tva.cota,$tabel_final_nomenclator.proc_adaos,$tabel_final_stoc.cantitate from $tabel_final_nomenclator INNER JOIN cote_tva on $tabel_final_nomenclator.cota_tva=cote_tva.id inner join $tabel_final_stoc on $tabel_final_stoc.cod_p=$tabel_final_nomenclator.cod_p where $tabel_final_nomenclator.cod_bare='$cod_bare_prod' AND $tabel_final_nomenclator.gestiune!='MP' AND $tabel_final_nomenclator.gestiune!='MC' AND $tabel_final_nomenclator.gestiune!='MA';";    
$pstmt2 = $pdo->prepare($psql2);  
$pstmt2->execute(); 
$cod_bare_count=$pstmt2->rowCount();
if($cod_bare_count>0){
while ($row = $pstmt2->fetch(PDO::FETCH_ASSOC)){ 
    $produs=$row['cod_p'];
                      $den_prod=$row['den_p'];
			                   $pret_achiz=$row['pret'];
							   $cota_tva=$row['cota'];
							   
							   $proc_ad=$row['proc_adaos'];
							   $stoc=$row['cantitate'];             
			$um=$row['um'];
			$proc_d=$row['proc_discount'];
			$gest=$row['gestiune'];
}
		 $cantitate_de_adaug=$_POST['cantitate'];
	
	
	if($gest=="PF"){


 					if($_SESSION['vanzare_sub_stoc']==0){

       $reteta_sql = "SELECT $tabel_final_retete.cod_mat,$tabel_final_nomenclator.den_p,$tabel_final_nomenclator.um,$tabel_final_retete.cant_folos,$tabel_final_stoc.cantitate as stoc_mat from $tabel_final_retete inner join $tabel_final_stoc on $tabel_final_retete.cod_mat=$tabel_final_stoc.cod_p INNER JOIN $tabel_final_nomenclator on $tabel_final_retete.cod_mat=$tabel_final_nomenclator.cod_p where $tabel_final_retete.cod_p='$produs'";    
$reteta_stmt = $pdo->prepare($reteta_sql);  
$reteta_stmt->execute(); 
$materii_reteta=$reteta_stmt->rowCount(); 
    $pf_sql = "SELECT $tabel_final_retete.cod_mat,$tabel_final_stoc.cantitate as stoc_mat,$tabel_final_retete.cant_folos from $tabel_final_retete inner join $tabel_final_stoc on $tabel_final_retete.cod_mat=$tabel_final_stoc.cod_p where $tabel_final_retete.cod_p='$produs' and $tabel_final_stoc.cantitate>=$tabel_final_retete.cant_folos*'$cantitate_de_adaug'";    
$pf_stm = $pdo->prepare($pf_sql);  
$pf_stm->execute(); 
$stoc_mat=$pf_stm->rowCount();
if($stoc_mat!=$materii_reteta){
        $alerta="Stoc insuficient de materii prime!\nPentru aceasta cantitate aveti nevoie de:\n";
$stoc_curent="Stocul curent de materii pentru acest produs: \n";
while ($row = $reteta_stmt->fetch(PDO::FETCH_ASSOC)){
    $dp=$row['den_p'];
    $canti_f=$row['cant_folos']*$cantitate_de_adaug;
    $canti_f=number_format((float)$canti_f, 4, '.', '');
    $st_mater=$row['stoc_mat'];
    $unit_m=$row['um'];
$alerta.=$dp.' '.' '.$canti_f.' '.$unit_m;
$alerta.="\n";
$stoc_curent.=$dp.' '.' '.$st_mater.' '.$unit_m;
$stoc_curent.="\n";

}
$alerta=$alerta.$stoc_curent;
 echo '<script language="javascript">';
  echo 'var myvar = ';echo json_encode($alerta).';';

echo 'alert';echo'('.myvar.');';
echo '</script>';
    
}

elseif($stoc_mat==$materii_reteta){
    
     		     
	 

for ($i = 1; $i <= $cantitate_de_adaug; $i++) {
    $c=1;
	 $adaos_unitar=$pret_achiz*$proc_ad/100;
	 $v_adaos=$adaos_unitar*$c;
	 $tva_neex=$v_adaos*$cota_tva/100;
	 $pret_vanzare=round(($pret_achiz+$adaos_unitar),2);
	 $pret_vanzare=floor($pret_vanzare*100)/100;
	 $valoare_vanzare=$pret_vanzare*$c;
	 $tva_col=round(($valoare_vanzare*$cota_tva/100),2);
	 $valoare_vanzare_cu_tva=floor(($valoare_vanzare+$tva_col)*100)/100;
	 $discount=$valoare_vanzare_cu_tva*$proc_d/100;
	 // aici stergi daca ai probleme
$discount=round($discount,2);





$psql3 = "select id_vanz from $tabel_final_det_bonuri where nr_bon='$nr_bon' and cod_p='$produs';";    
$pstmt3 = $pdo->prepare($psql3);  
$pstmt3->execute(); 
$prodcount=$pstmt3->rowCount();

if($prodcount==0){

$psql = "insert into $tabel_final_det_bonuri(nr_bon,cod_p,cantitate,tva_col,pret_vanzare,valoare_vanzare,valoare_vanzare_cu_tva,discount,data,ora) values('$nr_bon','$produs','$c','$tva_col','$pret_vanzare','$valoare_vanzare','$valoare_vanzare_cu_tva','$discount','$data_bon','$ora_bon');";   }

elseif($prodcount>0){
    
    

    $psql = "update $tabel_final_det_bonuri set cantitate=cantitate+'$c',tva_col=tva_col+'$tva_col',valoare_vanzare=valoare_vanzare+'$valoare_vanzare',valoare_vanzare_cu_tva=valoare_vanzare_cu_tva+'$valoare_vanzare_cu_tva',discount=discount+'$discount' where nr_bon='$nr_bon' and cod_p='$produs';"; 
    
}
	 
	try{
$pdo->exec($psql) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 
      	 $psql7 = "SELECT $tabel_final_retete.cod_mat,$tabel_final_retete.cant_folos from $tabel_final_retete where $tabel_final_retete.cod_p='$produs';";    
$pstmt7 = $pdo->prepare($psql7);  
$pstmt7->execute(); 

while ($row = $pstmt7->fetch(PDO::FETCH_ASSOC)){ 
    
    $materie=$row['cod_mat'];
    $cant_f=$row['cant_folos'];
    $stoc_upd_mat_sql = "update $tabel_final_stoc set cantitate=cantitate-'$cant_f' where cod_p='$materie'; "; 
    $stoc_upd_mat_stmt = $pdo->prepare($stoc_upd_mat_sql);  
$stoc_upd_mat_stmt->execute(); 
    
    
}
}catch(PDOException $e)
    {
    echo $sql . "<br>" . $e->getMessage();
    } 
}
    			printf("<script>location.href='creare_bon_simplu.php'</script>");


}


}



else{
    
    for ($i = 1; $i <= $cantitate_de_adaug; $i++) {
    $c=1;
	 $adaos_unitar=$pret_achiz*$proc_ad/100;
	 $v_adaos=$adaos_unitar*$c;
	 $tva_neex=$v_adaos*$cota_tva/100;
	 $pret_vanzare=round(($pret_achiz+$adaos_unitar),2);
	 $pret_vanzare=floor($pret_vanzare*100)/100;
	 $valoare_vanzare=$pret_vanzare*$c;
	 $tva_col=round(($valoare_vanzare*$cota_tva/100),2);
	 $valoare_vanzare_cu_tva=floor(($valoare_vanzare+$tva_col)*100)/100;
	 $discount=$valoare_vanzare_cu_tva*$proc_d/100;
	 // aici stergi daca ai probleme
$discount=round($discount,2);

$psql3 = "select id_vanz from $tabel_final_det_bonuri where nr_bon='$nr_bon' and cod_p='$produs';";    
$pstmt3 = $pdo->prepare($psql3);  
$pstmt3->execute(); 
$prodcount=$pstmt3->rowCount();

if($prodcount==0){

$psql = "insert into $tabel_final_det_bonuri(nr_bon,cod_p,cantitate,tva_col,pret_vanzare,valoare_vanzare,valoare_vanzare_cu_tva,discount,data,ora) values('$nr_bon','$produs','$c','$tva_col','$pret_vanzare','$valoare_vanzare','$valoare_vanzare_cu_tva','$discount','$data_bon','$ora_bon');";   }

elseif($prodcount>0){
    
   
    $psql = "update $tabel_final_det_bonuri set cantitate=cantitate+'$c',tva_col=tva_col+'$tva_col',valoare_vanzare=valoare_vanzare+'$valoare_vanzare',valoare_vanzare_cu_tva=valoare_vanzare_cu_tva+'$valoare_vanzare_cu_tva',discount=discount+'$discount' where nr_bon='$nr_bon' and cod_p='$produs';"; 
    
}
	 
	try{
$pdo->exec($psql) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 
      	 $psql7 = "SELECT $tabel_final_retete.cod_mat,$tabel_final_retete.cant_folos from $tabel_final_retete where $tabel_final_retete.cod_p='$produs';";    
$pstmt7 = $pdo->prepare($psql7);  
$pstmt7->execute(); 

while ($row = $pstmt7->fetch(PDO::FETCH_ASSOC)){ 
    
    $materie=$row['cod_mat'];
    $cant_f=$row['cant_folos'];
    $stoc_upd_mat_sql = "update $tabel_final_stoc set cantitate=cantitate-'$cant_f' where cod_p='$materie'; "; 
    $stoc_upd_mat_stmt = $pdo->prepare($stoc_upd_mat_sql);  
$stoc_upd_mat_stmt->execute(); 
    
    
}
}catch(PDOException $e)
    {
    echo $sql . "<br>" . $e->getMessage();
    } 
}
    			printf("<script>location.href='creare_bon_simplu.php'</script>");


    
    
    
}

}
		 
elseif($gest!="PF"){
		 
		 
 					if($_SESSION['vanzare_sub_stoc']==1){
 					    $stoc=$cantitate_de_adaug;
}
if($cantitate_de_adaug>$stoc) {
		   
$alerta="Stoc insuficient!Stocul disponibil pentru ".$den_prod." | ".$pret_achiz. " LEI  este de ".$$tabel_final_stoc." ".$um;
 echo '<script language="javascript">';
  echo 'var myvar = ';echo json_encode($alerta).';';

echo 'alert';echo'('.myvar.');';
echo '</script>';
		 }
		 
		 else{
		     
		     
	 
function is_decimal($val)
{
    return is_numeric( $val ) && floor( $val ) != $val;
}

if(is_decimal($cantitate_de_adaug)){


    $c=$cantitate_de_adaug;
	 $adaos_unitar=$pret_achiz*$proc_ad/100;
	 $v_adaos=$adaos_unitar*$c;
	 $tva_neex=$v_adaos*$cota_tva/100;
	 $pret_vanzare=round(($pret_achiz+$adaos_unitar),2);
	 $pret_vanzare=floor($pret_vanzare*100)/100;
	 $valoare_vanzare=$pret_vanzare*$c;
	 $tva_col=round(($valoare_vanzare*$cota_tva/100),2);
	 $valoare_vanzare_cu_tva=floor(($valoare_vanzare+$tva_col)*100)/100;
	 $discount=$valoare_vanzare_cu_tva*$proc_d/100;
	 // aici stergi daca ai probleme
$discount=round($discount,2);

$psql3 = "select id_vanz from $tabel_final_det_bonuri where nr_bon='$nr_bon' and cod_p='$produs';";    
$pstmt3 = $pdo->prepare($psql3);  
$pstmt3->execute(); 
$prodcount=$pstmt3->rowCount();

if($prodcount==0){

$psql = "insert into $tabel_final_det_bonuri(nr_bon,cod_p,cantitate,tva_col,pret_vanzare,valoare_vanzare,valoare_vanzare_cu_tva,discount,data,ora) values('$nr_bon','$produs','$c','$tva_col','$pret_vanzare','$valoare_vanzare','$valoare_vanzare_cu_tva','$discount','$data_bon','$ora_bon');
update $tabel_final_stoc set cantitate=cantitate-'$c' where cod_p='$produs'; ";   }

elseif($prodcount>0){
    
   
    $psql = "update $tabel_final_det_bonuri set cantitate=cantitate+'$c',tva_col=tva_col+'$tva_col',valoare_vanzare=valoare_vanzare+'$valoare_vanzare',valoare_vanzare_cu_tva=valoare_vanzare_cu_tva+'$valoare_vanzare_cu_tva',discount=discount+'$discount' where nr_bon='$nr_bon' and cod_p='$produs';
update $tabel_final_stoc set cantitate=cantitate-'$c' where cod_p='$produs'; "; 
    
}
	 
	try{
$pdo->exec($psql) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 

}catch(PDOException $e)
    {
    echo $sql . "<br>" . $e->getMessage();
    } 

    			printf("<script>location.href='creare_bon_simplu.php'</script>");


}
else{
for ($i = 1; $i <= $cantitate_de_adaug; $i++) {
    $c=1;
	 $adaos_unitar=$pret_achiz*$proc_ad/100;
	 $v_adaos=$adaos_unitar*$c;
	 $tva_neex=$v_adaos*$cota_tva/100;
	 $pret_vanzare=round(($pret_achiz+$adaos_unitar),2);
	 $pret_vanzare=floor($pret_vanzare*100)/100;
	 $valoare_vanzare=$pret_vanzare*$c;
	 $tva_col=round(($valoare_vanzare*$cota_tva/100),2);
	 $valoare_vanzare_cu_tva=floor(($valoare_vanzare+$tva_col)*100)/100;
	 $discount=$valoare_vanzare_cu_tva*$proc_d/100;
	 // aici stergi daca ai probleme
$discount=round($discount,2);

$psql3 = "select id_vanz from $tabel_final_det_bonuri where nr_bon='$nr_bon' and cod_p='$produs';";    
$pstmt3 = $pdo->prepare($psql3);  
$pstmt3->execute(); 
$prodcount=$pstmt3->rowCount();

if($prodcount==0){

$psql = "insert into $tabel_final_det_bonuri(nr_bon,cod_p,cantitate,tva_col,pret_vanzare,valoare_vanzare,valoare_vanzare_cu_tva,discount,data,ora) values('$nr_bon','$produs','$c','$tva_col','$pret_vanzare','$valoare_vanzare','$valoare_vanzare_cu_tva','$discount','$data_bon','$ora_bon');
update $tabel_final_stoc set cantitate=cantitate-'$c' where cod_p='$produs'; ";   }

elseif($prodcount>0){
    
   
    $psql = "update $tabel_final_det_bonuri set cantitate=cantitate+'$c',tva_col=tva_col+'$tva_col',valoare_vanzare=valoare_vanzare+'$valoare_vanzare',valoare_vanzare_cu_tva=valoare_vanzare_cu_tva+'$valoare_vanzare_cu_tva',discount=discount+'$discount' where nr_bon='$nr_bon' and cod_p='$produs';
update $tabel_final_stoc set cantitate=cantitate-'$c' where cod_p='$produs'; "; 
    
}
	 
	try{
$pdo->exec($psql) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 

}catch(PDOException $e)
    {
    echo $sql . "<br>" . $e->getMessage();
    } 
}
}
    			printf("<script>location.href='creare_bon_simplu.php'</script>");


 
 }
}


}

else{
    
    $alerta="Nu exista produsul cu codul de bare ".$cod_bare_prod." ! ";
 echo '<script language="javascript">';
  echo 'var myvar = ';echo json_encode($alerta).';';

echo 'alert';echo'('.myvar.');';
echo '</script>';
    
}
 }
 ?>

        
 
  <?php 
  
  
  // INCHIDERE ZI

        
        					if(isset($_POST['inchidere_tura'])){

 $inchidere_sql = "SELECT max(cod_inchidere) as ultim_inch FROM $tabel_final_bonuri where bonuri.status='F' and bonuri.cod_inchidere>0";    
$inchidere_stmt = $pdo->prepare($inchidere_sql);  
$inchidere_stmt->execute();
while ($row = $inchidere_stmt->fetch(PDO::FETCH_ASSOC)){ 
    $ultim_inchidere=$row['ultim_inch'];
}
$cod_inchidere_curenta=$ultim_inchidere+1;

  $valoare_inchidere_sql = "SELECT sum(bonuri.valoare_vanzare_cu_tva) FROM $tabel_final_bonuri where bonuri.cod_inchidere=0 and bonuri.status='F'; ";    
$valoare_inchidere_stmt = $pdo->prepare($valoare_inchidere_sql);  
$valoare_inchidere_stmt->execute();

while ($row = $valoare_inchidere_stmt->fetch(PDO::FETCH_ASSOC)){
	$valoare_inchidere=$row['sum(bonuri.valoare_vanzare_cu_tva)'];
}

  $valoare_tva_inchidere_sql = "SELECT sum(bonuri.tva_colectata) FROM $tabel_final_bonuri where bonuri.cod_inchidere=0 and bonuri.status='F'; ";    
$valoare_tva_inchidere_stmt = $pdo->prepare($valoare_tva_inchidere_sql);  
$valoare_tva_inchidere_stmt->execute();
while ($row = $valoare_tva_inchidere_stmt->fetch(PDO::FETCH_ASSOC)){
	$valoare_tva_inchidere=$row['sum(bonuri.tva_colectata)'];
}
 
 $ora_inchiderii = date("H:i:s", strtotime('+0 hours'));
 $data_inchiderii = date("Y-m-d", strtotime('+0 hours'));
$adauga_inchidere = "insert into $tabel_final_inchideri_r(cod_inchidere,operator,valoare_cu_tva,tva_colectata,data_inchiderii,ora_inchiderii) values('$cod_inchidere_curenta','$adm_id','$valoare_inchidere','$valoare_tva_inchidere','$data_inchiderii','$ora_inchiderii');";

	 
	try{
$pdo->exec($adauga_inchidere) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 

}catch(PDOException $e)
    {
    echo $adauga_inchidere . "<br>" . $e->getMessage();
    } 

$bon_sql = "SELECT bonuri.nrbon FROM $tabel_final_bonuri where bonuri.cod_inchidere=0 and bonuri.status='F'";    
$bon_stmt = $pdo->prepare($bon_sql);  
$bon_stmt->execute();
while ($row = $bon_stmt->fetch(PDO::FETCH_ASSOC)){ 
$bon_de_inchis=$row['nrbon'];
    $inchsql="UPDATE $tabel_final_bonuri set cod_inchidere='$cod_inchidere_curenta' where nrbon='$bon_de_inchis'"; 	 
    $inchstmt = $pdo->prepare($inchsql);  
$inchstmt->execute(); 
}

 

$_SESSION['cod_inchidere']=$cod_inchidere_curenta;

			printf("<script>location.href='dwred_inchidere_tura_magazin.php'</script>");

							}	
          // INCHIDERE ZI
          
          
          
          	if(isset($_POST['inchidere_zi'])){
	    // INCHIDERE ZI

        

 $inchidere_sql = "SELECT max(cod_inchidere) as ultim_inch FROM $tabel_final_bonuri where bonuri.status='F' and bonuri.cod_inchidere>0";    
$inchidere_stmt = $pdo->prepare($inchidere_sql);  
$inchidere_stmt->execute();
while ($row = $inchidere_stmt->fetch(PDO::FETCH_ASSOC)){ 
    $ultim_inchidere=$row['ultim_inch'];
}
$cod_inchidere_curenta=$ultim_inchidere+1;

  $valoare_inchidere_sql = "SELECT sum(bonuri.valoare_vanzare_cu_tva) FROM $tabel_final_bonuri where bonuri.cod_inchidere=0 and bonuri.status='F'; ";    
$valoare_inchidere_stmt = $pdo->prepare($valoare_inchidere_sql);  
$valoare_inchidere_stmt->execute();

while ($row = $valoare_inchidere_stmt->fetch(PDO::FETCH_ASSOC)){
	$valoare_inchidere=$row['sum(bonuri.valoare_vanzare_cu_tva)'];
}

  $valoare_tva_inchidere_sql = "SELECT sum(bonuri.tva_colectata) FROM $tabel_final_bonuri where bonuri.cod_inchidere=0 and bonuri.status='F'; ";    
$valoare_tva_inchidere_stmt = $pdo->prepare($valoare_tva_inchidere_sql);  
$valoare_tva_inchidere_stmt->execute();
while ($row = $valoare_tva_inchidere_stmt->fetch(PDO::FETCH_ASSOC)){
	$valoare_tva_inchidere=$row['sum(bonuri.tva_colectata)'];
}
 
 $ora_inchiderii = date("H:i:s", strtotime('+0 hours'));
 $data_inchiderii = date("Y-m-d", strtotime('+0 hours'));
$adauga_inchidere = "insert into $tabel_final_inchideri_r(cod_inchidere,operator,valoare_cu_tva,tva_colectata,data_inchiderii,ora_inchiderii) values('$cod_inchidere_curenta','$adm_id','$valoare_inchidere','$valoare_tva_inchidere','$data_inchiderii','$ora_inchiderii');";

	 
	try{
$pdo->exec($adauga_inchidere) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 

}catch(PDOException $e)
    {
    echo $adauga_inchidere . "<br>" . $e->getMessage();
    } 

$bon_sql = "SELECT bonuri.nrbon FROM $tabel_final_bonuri where bonuri.cod_inchidere=0 and bonuri.status='F'";    
$bon_stmt = $pdo->prepare($bon_sql);  
$bon_stmt->execute();
while ($row = $bon_stmt->fetch(PDO::FETCH_ASSOC)){ 
$bon_de_inchis=$row['nrbon'];
    $inchsql="UPDATE $tabel_final_bonuri set cod_inchidere='$cod_inchidere_curenta' where nrbon='$bon_de_inchis'"; 	 
    $inchstmt = $pdo->prepare($inchsql);  
$inchstmt->execute(); 
}

 

$_SESSION['cod_inchidere']=$cod_inchidere_curenta;

			printf("<script>location.href='dwred_inchidere_zi_magazin.php'</script>");

								
          // INCHIDERE ZI
	}

 ?>
</div>    





<!-- 3rd row responsive images in background with centered content -->



			</div>	

<footer class="sticky-footer" style="width:100%;height:auto;margin:0;">
  <div class="container" style='margin:0;width:100%;' >
    <table>
    <tr>
<!-- 1st row verticaly centered text in the square columns -->

<div style="background-color:#BCC6CC;float: left;"><button id='plata_numerar' class="square img_1-1" form='plata' type="submit" name="finaliz_bon" value="numerar" ></button>
</form>
<button form='plata' type='submit' name="finaliz_bon" value="card"  class="square img_1-2" id="plata_card" ></button>
<button class="square img_1-3" id="plata_numerar_si_card"></button>
<button class="square img_1-7" id="plata_tichete"></button></div>
<button form='plata' type='submit' name="finaliz_bon" value="protocol"  class="square img_1-15" id="plata_protocol" ></button>
<div style="background-color:#98AFC7;float: right;">
    <form style='float:right;' method="POST"><button class="square img_1-10" name='imparte_nota' type="submit" ></button></form>
<button class="square img_1-8" id="amanate"></button>
<button class="square img_1-14" id="relistare"></button>
<button class="square img_1-5" id="produse_vandute"></button>
<form style='float:right;' method='POST'><button type='submit' class='square img_1-13' id='amanare_bon' name='amanare'></button></form>
<?php $bon_sql = "SELECT bonuri.nrbon FROM $tabel_final_bonuri where bonuri.cod_inchidere=0 and bonuri.status='F'";    
$bon_stmt = $pdo->prepare($bon_sql);  
$bon_stmt->execute(); 
 $bon_neinchis_count=$bon_stmt->rowCount();
        if($bon_neinchis_count >= 1) {
echo "<form style='float:right;' method='POST'><button type='submit' class='square img_1-4' name='inchidere_tura'></button></form>
<form style='float:right;' method='POST'><button type='submit' class='square img_1-12' name='inchidere_zi'></button></form>";
        }
?>
<form method="POST" style="margin:0;float:right;"><button name="deconectare" id='deconectare' class='square img_1-9' type="submit"></button></form></div>
<div style="background-color:#E5E4E2;float: none;overflow: hidden;">       <button disabled id='white_button' class="square banner1" style='width:100%;height:4em;font-size:1.5em;'></button></div>
      
    
    

</tr>
</table>

  </div>
</footer>

<?php 
if(isset($_POST['amanare'])){

    $amansql="insert into $tabel_final_bonuri(operator) values('$adm_id')"; 	 
    $aman_stmt = $pdo->prepare($amansql);  
$aman_stmt->execute(); 
 $cccom_sql = "SELECT max(nrbon) as nrb FROM $tabel_final_bonuri ";    
$cccom_stmt = $pdo->prepare($cccom_sql);  
$cccom_stmt->execute(); 
while ($row = $cccom_stmt->fetch(PDO::FETCH_ASSOC)){ 
    $nr_bon=$row['nrb'];
}
$_SESSION['nr_bon']=$nr_bon;

			printf("<script>location.href='creare_bon_simplu.php'</script>");

							}
							
							if(isset($_POST['imparte_nota'])){

  
$_SESSION['nr_bon']=$nr_bon;
			printf("<script>location.href='imparte_nota.php'</script>");

							}
	
							?> 
							
								<!--NUMERAR BEGIN//-->







<!-- CSS Global -->


		<!-- Custom JS -->
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

		
			<!--- Modal lista bonuri amanate-->
			
			<div class="modal fade" id="Amanate" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
		  <div class="modal-dialog" role="document">
			<div class="modal-content">
			    
			  <div class="modal-header">
			       <div class='buttons' style='float:left;margin-top:2em;'>  </br><button class='btn-sm scrol3 hidden-xs' title='Mentineti apasat pentru a derula in sus' id ="scrollup3"><i class="fa fa-chevron-up"></i></button>
</br>
<button title='Mentineti apasat pentru a derula in jos' class='btn-sm scrol3 hidden-xs' id ="scrolldown3"><i class="fa fa-chevron-down"></i></button>   
</div>
				<h4 class="modal-title" id="myModalLabel">bonuri Amânate</h4>
				<button class='form-control leftArrowAmanate'  title='Mentineti apasat pentru a derula spre stanga' style='width:22%;display:inline-block;'>
&larr;      </button>
      <button class='form-control rightArrowAmanate' title='Mentineti apasat pentru a derula spre dreapta' style='width:22%;display:inline-block;'>
&rarr;      </button>
								<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>

			  </div>
			  <div class="modal-body bamanate" style="height:31.25em;max-width:100%;overflow-x: auto;">
			                      <div class="scoll-tree">

<style>
figure{
float:left;
display:inline-block;
margin-left:0.5em;
}
label{font-weight:bold;}
</style>

<?php 
$mese_desch_sql = "SELECT nrbon FROM $tabel_final_bonuri where status='S' and operator='$adm_id'";    
$mese_desch_stmt = $pdo->prepare($mese_desch_sql);  
$mese_desch_stmt->execute(); 
while ($rrow = $mese_desch_stmt->fetch(PDO::FETCH_ASSOC)){
$bon_amanat=$rrow['nrbon'];
$bon_am=$rrow['nrbon'];
$bon_am=strval($bon_am);
$bon_am.='BAM';




echo "
<figure class='masa'><form method='POST'><button class='btn btn-default list-group' type='submit' style='display:block;float:left;' name='$bon_am' >Nota nr. $bon_amanat</br>";echo"</br> Produse:</br>";$mese_desch_sql2 = "SELECT $tabel_final_nomenclator.den_p from $tabel_final_det_bonuri INNER JOIN $tabel_final_nomenclator on $tabel_final_det_bonuri.cod_p=$tabel_final_nomenclator.cod_p where $tabel_final_det_bonuri.nr_bon='$bon_amanat'";    
$mese_desch_stmt2 = $pdo->prepare($mese_desch_sql2);  
$mese_desch_stmt2->execute(); 

					while ($row = $mese_desch_stmt2->fetch()) {
					    
					   echo "<p style='font-weight:bold'>".$row['den_p']."</p>"; 
					} echo " 
</button></figure></form>";
if(isset($_POST[$bon_am])){
      $_SESSION['nr_bon']=$bon_amanat;

      			printf("<script>location.href='creare_bon_simplu.php'</script>");

}
}

 ?>
 </div></div>

				  </div>
							  </div>			  
	  
							  </div>
							  
						<!------ Modal lista bonuri amanate end -->
					
						<!------ Modal set client  -->
			
			<div class="modal fade" id="set_client" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
		  <div class="modal-dialog" role="document">
			<div class="modal-content">
			    
			  <div class="modal-header">
			       <div class='buttons' style='float:left;margin-top:2em;'>  </br><button class='btn-sm scrol3 hidden-xs' title='Mentineti apasat pentru a derula in sus' id ="scrollup5"><i class="fa fa-chevron-up"></i></button>
</br>
<button title='Mentineti apasat pentru a derula in jos' class='btn-sm scrol3 hidden-xs' id ="scrolldown5"><i class="fa fa-chevron-down"></i></button>   
</div>
				<h4 class="modal-title" id="myModalLabel">Alegeți Clientul</h4>
				<button class='form-control leftArrowClienti'  title='Mentineti apasat pentru a derula spre stanga' style='width:22%;display:inline-block;'>
&larr;      </button>
      <button class='form-control rightArrowClienti' title='Mentineti apasat pentru a derula spre dreapta' style='width:22%;display:inline-block;'>
&rarr;      </button>
								<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>

			  </div>
			  <div class="modal-body clienti" style="height:31.25em;max-width:100%;overflow-x: auto;">

   <table class='table table-bordered'>
<?php 
$clienti_sql = "SELECT denumire,cod_tert,cui_cif from $tabel_final_terti where tip_tert='client' order by denumire ASC";    
$clienti_stmt = $pdo->prepare($clienti_sql);  
$clienti_stmt->execute(); 
while ($rrow = $clienti_stmt->fetch(PDO::FETCH_ASSOC)){
$den_client=$rrow['denumire'];
$cui_cif=$rrow['cui_cif'];
$cod_tert=$rrow['cod_tert'];
$cod_tert=strval($cod_tert);
$cod_tert.='CL';
echo"<tr>
  <td><form method='POST'><button style='width:100%; font-size:1em; height:auto; white-space: normal;
' class='btn btn-primary btn-block' type='submit' name='$cod_tert'>$den_client</button></form></td>
  </tr> ";
if(isset($_POST[$cod_tert])){
      $_SESSION['cif_client']=$cui_cif;
      			printf("<script>location.href='creare_bon_simplu.php'</script>");

}
}
  ?>
    

  
  
</table>

 </div>

				  </div>
							  </div>			  
	  
							  </div>
							  
						<!------ Modal set client end -->
					
							
			<!--- Modal lista bonuri pentru relistare-->
			
			<div class="modal fade" id="Relistare" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
		  <div class="modal-dialog" role="document">
			<div class="modal-content">
			    
			  <div class="modal-header">
			       <div class='buttons' style='float:left;margin-top:7em;'>  </br><button class='btn-sm scrol3 hidden-xs' title='Mentineti apasat pentru a derula in sus' id ="scrollup4"><i class="fa fa-chevron-up"></i></button>
</br>
<button title='Mentineti apasat pentru a derula in jos' class='btn-sm scrol3 hidden-xs' id ="scrolldown4"><i class="fa fa-chevron-down"></i></button>   
</div>
				<h4 class="modal-title" id="myModalLabel">bonurile din ultimele 2 zile</h4><h4>Alegeti nota ce va fi retrimisa la casa de marcat</h4>
				<button class='form-control leftArrowRelistare'  title='Mentineti apasat pentru a derula spre stanga' style='width:22%;display:inline-block;'>
&larr;      </button>
      <button class='form-control rightArrowRelistare' title='Mentineti apasat pentru a derula spre dreapta' style='width:22%;display:inline-block;'>
&rarr;      </button>
								<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>

			  </div>
			  <div class="modal-body brelistare" style="height:31.25em;max-width:100%;overflow-x: auto;">
			                      <div class="scoll-tree2">

<style>
figure{
float:left;
display:inline-block;
margin-left:0.5em;
}
label{font-weight:bold;}
</style>

<?php 
 $acum2_zile = date("Y-m-d", strtotime('-48 hours'));

$mese_desch_sql_relist = "SELECT cif_client,numerar,card,tichete,nrbon,data_bon FROM $tabel_final_bonuri where status='F' and data_bon>='$acum2_zile' and operator='$adm_id' order by data_bon";    
$mese_desch_stmt_relist = $pdo->prepare($mese_desch_sql_relist);  
$mese_desch_stmt_relist->execute(); 

while ($rrow = $mese_desch_stmt_relist->fetch(PDO::FETCH_ASSOC)){
    $data_n_r=$rrow['data_bon'];
    $data_n_r=date('d-m-Y',strtotime($data_n_r));
    $cif_client_relist=$rrow['cif_client'];
        $numerar_relist=$rrow['numerar'];
    $card_relist=$rrow['card'];
    $tichete_relist=$rrow['tichete'];

$bon_amanat=$rrow['nrbon'];
$bon_am=$rrow['nrbon'];
$bon_am=strval($bon_am);
$bon_am.='NDR';

echo "
<figure class='masa'><form method='POST'><button class='btn btn-default list-group2' type='submit' style='display:block;float:left;' name='$bon_am' >Nota nr. $bon_amanat</br>Data: $data_n_r </br> Produse:</br>";$mese_desch_sql2_relist = "SELECT $tabel_final_nomenclator.den_p from $tabel_final_det_bonuri INNER JOIN $tabel_final_nomenclator on $tabel_final_det_bonuri.cod_p=$tabel_final_nomenclator.cod_p where $tabel_final_det_bonuri.nr_bon='$bon_amanat'";    
$mese_desch_stmt2_relist = $pdo->prepare($mese_desch_sql2_relist);  
$mese_desch_stmt2_relist->execute(); 

					while ($row = $mese_desch_stmt2_relist->fetch()) {
					    
					   echo "<p style='font-weight:bold'>".$row['den_p']."</p>"; 
					} echo " 
</button></figure></form>";
if(isset($_POST[$bon_am])){
      $_SESSION['nr_bon']=$bon_amanat;
      if($cif_client_relist!=''){
      $_SESSION['cif_client']=$cif_client_relist;}
      if($numerar_relist!=0){
      $_SESSION['numerarprim']=$numerar_relist;}
      if($card_relist!=0){
      $_SESSION['cardprim']=$card_relist;}
      if($numerar_relist!=0 && $tichete_relist!=0){
unset($_SESSION['numerarprim']);
                $_SESSION['total_tichete']=$tichete_relist;
                $_SESSION['rest_tichete']=$numerar_relist;

      }
      elseif($numerar_relist=0 && $tichete_relist!=0){
                $_SESSION['total_tichete']=$tichete_relist;
      }

    if($_SESSION['mod_listare']=='complex'){
printf("<script>location.href='dwred_cu_listare.php'</script>");
}
else{
    printf("<script>location.href='dwred.php'</script>");

}
}
}

 ?>
 </div></div>

				  </div>
							  </div>			  
	  
							  </div>
							  
			<!--- Modal lista bonuri pentru relistare end-->
					

												<!------ Modal produse vandute begin -->

							<div class="modal fade" id="Prod_vandute" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
		  <div class="modal-dialog" role="document">
			<div class="modal-content">
			    
			  <div class="modal-header">
				<h4 class="modal-title" id="myModalLabel">Produse vândute</h4>
								<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>

			  </div>
			  <div class="modal-body">
				    <object width="100%" height="400" data="vizualizare_produse_vandute.php"></object>
 </div>

				  </div>
		

							  </div>			  
	  
							  </div>
							  
								<!------ Modal produse vandute end -->
								
														<!------ Modal sume sertar  begin -->

							<div class="modal fade" id="sume_sertar" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
		  <div class="modal-dialog" role="document">
			<div class="modal-content">
			    
			  <div class="modal-header">
				<h4 class="modal-title" id="myModalLabel">Sume sertar</h4>
								<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>

			  </div>
			  <div class="modal-body">
 <table class='table table-bordered test'>
<form id='plata' method='POST'>
    
     
     <?php 
       $sume_sertar_sql = "SELECT sum(numerar) as total_numerar,sum(card) as total_card,sum(tichete) as total_tichete,sum(protocol) as total_protocol FROM $tabel_final_bonuri where cod_inchidere=0 and operator='$adm_id'
";    
$sume_sertar_stmt = $pdo->prepare($sume_sertar_sql);  
$sume_sertar_stmt->execute(); 
while ($row = $sume_sertar_stmt->fetch(PDO::FETCH_ASSOC)){ 

$total_numerar=$row['total_numerar'];
$total_card=$row['total_card'];
$total_tichete=$row['total_tichete'];
$total_protocol=$row['total_protocol'];

}
     ?>
      <tr>
<td>Numerar</td><td><?php echo $total_numerar;?> LEI</td></tr>
        <tr>  <td>Card</td><td><?php echo $total_card;?> LEI</td></tr>
    <tr> <td>Tichete de masa</td><td><?php echo $total_tichete;?> LEI</td></tr>
    <tr>  <td>Protocol</td><td><?php echo $total_protocol;?> LEI</td></tr>

     </table>
      </div>

				  </div>
		

							  </div>			  
	  
							  </div>
							  
								<!------ Modal  sume sertar end -->
								
								
								
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
 				      <input hidden type="number" step='0.001'  name='cantitate_veche'/>

				    		<div class="form-group">
				    <label for="exampleInputEmail1">Cantitatea nouă</label></br>
<div class="input-group"><input type="number" step='0.001' name="cantitate_noua" min="0.001" class="form-control" id="cant_noua" > </div>	<input class='btn btn-primary btn-block' type="submit" name="modific_cant" value="Salvează"/>	</div>

</form>

<?php if(isset($_POST['modific_cant'])){
    
    
    $id_vz=$_POST['idvz'];
        		 $cantitate_veche=$_POST['cantitate_veche'];
    		 $cantitate_de_adaug=$_POST['cantitate_noua'];
    	 $produs=$_POST['produs_modif_cant'];
    	 
   $psql3 = "select id_vanz from $tabel_final_det_bonuri where id_vanz='$id_vz';";    
$pstmt3 = $pdo->prepare($psql3);  
$pstmt3->execute(); 


 
	 $psql3 = "SELECT $tabel_final_nomenclator.gestiune,$tabel_final_nomenclator.cod_p,$tabel_final_nomenclator.proc_discount,$tabel_final_nomenclator.um,$tabel_final_nomenclator.den_p,$tabel_final_nomenclator.pret,cote_tva.cota,$tabel_final_nomenclator.proc_adaos,$tabel_final_stoc.cantitate from $tabel_final_nomenclator INNER JOIN cote_tva on $tabel_final_nomenclator.cota_tva=cote_tva.id inner join $tabel_final_stoc on $tabel_final_stoc.cod_p=$tabel_final_nomenclator.cod_p where $tabel_final_nomenclator.cod_p='$produs';";    
$pstmt3 = $pdo->prepare($psql3);  
$pstmt3->execute(); 
while ($row = $pstmt3->fetch(PDO::FETCH_ASSOC)){
    
 
                      $den_prod=$row['den_p'];
			                   $pret_achiz=$row['pret'];
							   $cota_tva=$row['cota'];
							   
							   $proc_ad=$row['proc_adaos'];
							   $stoc=$row['cantitate'];             
			$um=$row['um'];
			$proc_d=$row['proc_discount'];
			$gest=$row['gestiune'];
}


	if($gest=="PF"){


 					if($_SESSION['vanzare_sub_stoc']==0){



       $reteta_sql = "SELECT $tabel_final_retete.cod_mat,$tabel_final_nomenclator.den_p,$tabel_final_nomenclator.um,$tabel_final_retete.cant_folos,$tabel_final_stoc.cantitate as stoc_mat from $tabel_final_retete inner join $tabel_final_stoc on $tabel_final_retete.cod_mat=$tabel_final_stoc.cod_p INNER JOIN $tabel_final_nomenclator on $tabel_final_retete.cod_mat=$tabel_final_nomenclator.cod_p where $tabel_final_retete.cod_p='$produs'";    
$reteta_stmt = $pdo->prepare($reteta_sql);  
$reteta_stmt->execute(); 
$materii_reteta=$reteta_stmt->rowCount(); 

    	 $psql13 = "SELECT $tabel_final_retete.cod_mat,$tabel_final_retete.cant_folos from $tabel_final_retete where $tabel_final_retete.cod_p='$produs';";    
$pstmt13 = $pdo->prepare($psql13);  
$pstmt13->execute(); 

while ($row = $pstmt13->fetch(PDO::FETCH_ASSOC)){ 
    
    $materie=$row['cod_mat'];
    $cant_f=$row['cant_folos']*$cantitate_veche;
    $update_stoc_modif_pf_sql = "update $tabel_final_stoc set cantitate=cantitate+'$cant_f' where cod_p='$materie'; "; 
    $update_stoc_modif_pf_stmt = $pdo->prepare($update_stoc_modif_pf_sql);  
$update_stoc_modif_pf_stmt->execute(); 
    
    
}

    $pf_sql = "SELECT $tabel_final_retete.cod_mat,$tabel_final_stoc.cantitate as stoc_mat,$tabel_final_retete.cant_folos from $tabel_final_retete inner join $tabel_final_stoc on $tabel_final_retete.cod_mat=$tabel_final_stoc.cod_p where $tabel_final_retete.cod_p='$produs' and $tabel_final_stoc.cantitate>=$tabel_final_retete.cant_folos*'$cantitate_de_adaug'";    
$pf_stm = $pdo->prepare($pf_sql);  
$pf_stm->execute(); 
$stoc_mat=$pf_stm->rowCount();
if($stoc_mat!=$materii_reteta){
    
      	 $psql13 = "SELECT $tabel_final_retete.cod_mat,$tabel_final_retete.cant_folos from $tabel_final_retete where $tabel_final_retete.cod_p='$produs';";    
$pstmt13 = $pdo->prepare($psql13);  
$pstmt13->execute(); 

while ($row = $pstmt13->fetch(PDO::FETCH_ASSOC)){ 
    
    $materie=$row['cod_mat'];
    $cant_f=$row['cant_folos']*$cantitate_veche;
    $update_stoc_modif_pf_sql = "update $tabel_final_stoc set cantitate=cantitate-'$cant_f' where cod_p='$materie'; "; 
    $update_stoc_modif_pf_stmt = $pdo->prepare($update_stoc_modif_pf_sql);  
$update_stoc_modif_pf_stmt->execute(); 
    
    
}
    
        $alerta="Stoc insuficient de materii prime!\nPentru aceasta cantitate aveti nevoie de:\n";
$stoc_curent="Stocul curent de materii pentru acest produs: \n";
while ($row = $reteta_stmt->fetch(PDO::FETCH_ASSOC)){
    $dp=$row['den_p'];
    $canti_f=$row['cant_folos']*$cantitate_de_adaug;
    $canti_f=number_format((float)$canti_f, 4, '.', '');
    $st_mater=$row['stoc_mat'];
    $unit_m=$row['um'];
$alerta.=$dp.' '.' '.$canti_f.' '.$unit_m;
$alerta.="\n";
$stoc_curent.=$dp.' '.' '.$st_mater.' '.$unit_m;
$stoc_curent.="\n";

}
$alerta=$alerta.$stoc_curent;
 echo '<script language="javascript">';
  echo 'var myvar = ';echo json_encode($alerta).';';

echo 'alert';echo'('.myvar.');';
echo '</script>';
    
}

elseif($stoc_mat==$materii_reteta){
    
     		     
	     	     $psql12 = "update $tabel_final_det_bonuri set cantitate=0,tva_col=0,valoare_vanzare=0,valoare_vanzare_cu_tva=0,discount=0 where id_vanz='$id_vz';"; 
    

	 
	try{
$pdo->exec($psql12) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 




}catch(PDOException $e)
    {
    echo $psql12 . "<br>" . $e->getMessage();
    } 

for ($i = 1; $i <= $cantitate_de_adaug; $i++) {
    $c=1;
	 $adaos_unitar=$pret_achiz*$proc_ad/100;
	 $v_adaos=$adaos_unitar*$c;
	 $tva_neex=$v_adaos*$cota_tva/100;
	 $pret_vanzare=round(($pret_achiz+$adaos_unitar),2);
	 $pret_vanzare=floor($pret_vanzare*100)/100;
	 $valoare_vanzare=$pret_vanzare*$c;
	 $tva_col=round(($valoare_vanzare*$cota_tva/100),2);
	 $valoare_vanzare_cu_tva=floor(($valoare_vanzare+$tva_col)*100)/100;
	 $discount=$valoare_vanzare_cu_tva*$proc_d/100;
	 // aici stergi daca ai probleme
$discount=round($discount,2);

$psql3 = "SELECT id_vanz from $tabel_final_det_bonuri where nr_bon='$nr_bon' and cod_p='$produs';";    
$pstmt3 = $pdo->prepare($psql3);  
$pstmt3->execute(); 
$prodcount=$pstmt3->rowCount();

if($prodcount==0){

$psql = "insert into $tabel_final_det_bonuri(nr_bon,cod_p,cantitate,tva_col,pret_vanzare,valoare_vanzare,valoare_vanzare_cu_tva,discount,data,ora) values('$nr_bon','$produs','$c','$tva_col','$pret_vanzare','$valoare_vanzare','$valoare_vanzare_cu_tva','$discount','$data_bon','$ora_bon');";   }

elseif($prodcount>0){
    $psql = "update $tabel_final_det_bonuri set cantitate=cantitate+'$c',tva_col=tva_col+'$tva_col',valoare_vanzare=valoare_vanzare+'$valoare_vanzare',valoare_vanzare_cu_tva=valoare_vanzare_cu_tva+'$valoare_vanzare_cu_tva',discount=discount+'$discount' where id_vanz='$id_vz';"; 
    
}
	 
	try{
$pdo->exec($psql) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 
      	 $psql7 = "SELECT $tabel_final_retete.cod_mat,$tabel_final_retete.cant_folos from $tabel_final_retete where $tabel_final_retete.cod_p='$produs';";    
$pstmt7 = $pdo->prepare($psql7);  
$pstmt7->execute(); 

while ($row = $pstmt7->fetch(PDO::FETCH_ASSOC)){ 
    
    $materie=$row['cod_mat'];
    $cant_f=$row['cant_folos'];
    $stoc_upd_mat_sql = "update $tabel_final_stoc set cantitate=cantitate-'$cant_f' where cod_p='$materie'; "; 
    $stoc_upd_mat_stmt = $pdo->prepare($stoc_upd_mat_sql);  
$stoc_upd_mat_stmt->execute(); 
    
    
}
}catch(PDOException $e)
    {
    echo $sql . "<br>" . $e->getMessage();
    } 
}
    			printf("<script>location.href='creare_bon_simplu.php'</script>");


}


}



else{
    
        	     $psql12 = "update $tabel_final_det_bonuri set cantitate=0,tva_col=0,valoare_vanzare=0,valoare_vanzare_cu_tva=0,discount=0 where id_vanz='$id_vz';"; 
    

	 
	try{
$pdo->exec($psql12) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 

    	 $psql13 = "SELECT $tabel_final_retete.cod_mat,$tabel_final_retete.cant_folos from $tabel_final_retete where $tabel_final_retete.cod_p='$produs';";    
$pstmt13 = $pdo->prepare($psql13);  
$pstmt13->execute(); 

while ($row = $pstmt13->fetch(PDO::FETCH_ASSOC)){ 
    
    $materie=$row['cod_mat'];
    $cant_f=$row['cant_folos']*$cantitate_veche;
    $update_stoc_modif_pf_sql = "update $tabel_final_stoc set cantitate=cantitate+'$cant_f' where cod_p='$materie'; "; 
    $update_stoc_modif_pf_stmt = $pdo->prepare($update_stoc_modif_pf_sql);  
$update_stoc_modif_pf_stmt->execute(); 
    
    
}


}catch(PDOException $e)
    {
    echo $psql12 . "<br>" . $e->getMessage();
    } 
    
    for ($i = 1; $i <= $cantitate_de_adaug; $i++) {
    $c=1;
	 $adaos_unitar=$pret_achiz*$proc_ad/100;
	 $v_adaos=$adaos_unitar*$c;
	 $tva_neex=$v_adaos*$cota_tva/100;
	 $pret_vanzare=round(($pret_achiz+$adaos_unitar),2);
	 $pret_vanzare=floor($pret_vanzare*100)/100;
	 $valoare_vanzare=$pret_vanzare*$c;
	 $tva_col=round(($valoare_vanzare*$cota_tva/100),2);
	 $valoare_vanzare_cu_tva=floor(($valoare_vanzare+$tva_col)*100)/100;
	 $discount=$valoare_vanzare_cu_tva*$proc_d/100;
	 // aici stergi daca ai probleme
$discount=round($discount,2);

$psql3 = "SELECT id_vanz from $tabel_final_det_bonuri where nr_bon='$nr_bon' and cod_p='$produs';";    
$pstmt3 = $pdo->prepare($psql3);  
$pstmt3->execute(); 
$prodcount=$pstmt3->rowCount();

if($prodcount==0){

$psql = "insert into $tabel_final_det_bonuri(nr_bon,cod_p,cantitate,tva_col,pret_vanzare,valoare_vanzare,valoare_vanzare_cu_tva,discount,data,ora) values('$nr_bon','$produs','$c','$tva_col','$pret_vanzare','$valoare_vanzare','$valoare_vanzare_cu_tva','$discount','$data_bon','$ora_bon');";   }

elseif($prodcount>0){
    $psql = "update $tabel_final_det_bonuri set cantitate=cantitate+'$c',tva_col=tva_col+'$tva_col',valoare_vanzare=valoare_vanzare+'$valoare_vanzare',valoare_vanzare_cu_tva=valoare_vanzare_cu_tva+'$valoare_vanzare_cu_tva',discount=discount+'$discount' where id_vanz='$id_vz';"; 
    
}
	 
	try{
$pdo->exec($psql) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 
      	 $psql7 = "SELECT $tabel_final_retete.cod_mat,$tabel_final_retete.cant_folos from $tabel_final_retete where $tabel_final_retete.cod_p='$produs';";    
$pstmt7 = $pdo->prepare($psql7);  
$pstmt7->execute(); 

while ($row = $pstmt7->fetch(PDO::FETCH_ASSOC)){ 
    
    $materie=$row['cod_mat'];
    $cant_f=$row['cant_folos'];
    $stoc_upd_mat_sql = "update $tabel_final_stoc set cantitate=cantitate-'$cant_f' where cod_p='$materie'; "; 
    $stoc_upd_mat_stmt = $pdo->prepare($stoc_upd_mat_sql);  
$stoc_upd_mat_stmt->execute(); 
    
    
}
}catch(PDOException $e)
    {
    echo $sql . "<br>" . $e->getMessage();
    } 
}
    			printf("<script>location.href='creare_bon_simplu.php'</script>");


    
    
    
}

}

else{
if($_SESSION['vanzare_sub_stoc']==0){
if($cantitate_de_adaug>$stoc+$cantitate_veche) {
    
  
$alerta="Stoc insuficient!Modificarea maximă pentru ".$den_prod." | ".$pret_achiz. " LEI  este de ".($stoc+$cantitate_veche)." ".$um;
 echo '<script language="javascript">';
  echo 'var myvar = ';echo json_encode($alerta).';';

echo 'alert';echo'('.myvar.');';
echo '</script>';
		 }

		 else{
		     
		         	     $psql5 = "update $tabel_final_det_bonuri set cantitate=0,tva_col=0,valoare_vanzare=0,valoare_vanzare_cu_tva=0,discount=0 where id_vanz='$id_vz';
update $tabel_final_stoc set cantitate=cantitate+'$cantitate_veche' where cod_p='$produs'; "; 
    

	 
	try{
$pdo->exec($psql5) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 

}catch(PDOException $e)
    {
    echo $psql5 . "<br>" . $e->getMessage();
    } 
function is_decimal($val)
{
    return is_numeric( $val ) && floor( $val ) != $val;
}

if(is_decimal($cantitate_de_adaug)){


    $c=$cantitate_de_adaug;
	 $adaos_unitar=$pret_achiz*$proc_ad/100;
	 $v_adaos=$adaos_unitar*$c;
	 $tva_neex=$v_adaos*$cota_tva/100;
	 $pret_vanzare=round(($pret_achiz+$adaos_unitar),2);
	 $pret_vanzare=floor($pret_vanzare*100)/100;
	 $valoare_vanzare=$pret_vanzare*$c;
	 $tva_col=round(($valoare_vanzare*$cota_tva/100),2);
	 $valoare_vanzare_cu_tva=floor(($valoare_vanzare+$tva_col)*100)/100;
	 $discount=$valoare_vanzare_cu_tva*$proc_d/100;
	 // aici stergi daca ai probleme
$discount=round($discount,2);


    $psql = "update $tabel_final_det_bonuri set cantitate=cantitate+'$c',tva_col=tva_col+'$tva_col',valoare_vanzare=valoare_vanzare+'$valoare_vanzare',valoare_vanzare_cu_tva=valoare_vanzare_cu_tva+'$valoare_vanzare_cu_tva',discount=discount+'$discount' where id_vanz='$id_vz';
update $tabel_final_stoc set cantitate=cantitate-'$c' where cod_p='$produs'; "; 
    

	 
	try{
$pdo->exec($psql) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 

}catch(PDOException $e)
    {
    echo $sql . "<br>" . $e->getMessage();
    } 

    			printf("<script>location.href='creare_bon_simplu.php'</script>");

}
else{


for ($i = 1; $i <= $cantitate_de_adaug; $i++) {
    $c=1;
	 $adaos_unitar=$pret_achiz*$proc_ad/100;
	 $v_adaos=$adaos_unitar*$c;
	 $tva_neex=$v_adaos*$cota_tva/100;
	 $pret_vanzare=round(($pret_achiz+$adaos_unitar),2);
	 $pret_vanzare=floor($pret_vanzare*100)/100;
	 $valoare_vanzare=$pret_vanzare*$c;
	 $tva_col=round(($valoare_vanzare*$cota_tva/100),2);
	 $valoare_vanzare_cu_tva=floor(($valoare_vanzare+$tva_col)*100)/100;
	 $discount=$valoare_vanzare_cu_tva*$proc_d/100;
	 // aici stergi daca ai probleme
$discount=round($discount,2);


    $psql = "update $tabel_final_det_bonuri set cantitate=cantitate+'$c',tva_col=tva_col+'$tva_col',valoare_vanzare=valoare_vanzare+'$valoare_vanzare',valoare_vanzare_cu_tva=valoare_vanzare_cu_tva+'$valoare_vanzare_cu_tva',discount=discount+'$discount' where id_vanz='$id_vz';
update $tabel_final_stoc set cantitate=cantitate-'$c' where cod_p='$produs'; "; 
    

	 
	try{
$pdo->exec($psql) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 

}catch(PDOException $e)
    {
    echo $sql . "<br>" . $e->getMessage();
    } 
}
}
    			printf("<script>location.href='creare_bon_simplu.php'</script>");


 
 }
 
}

else{
    
    
    	     
		         	     $psql5 = "update $tabel_final_det_bonuri set cantitate=0,tva_col=0,valoare_vanzare=0,valoare_vanzare_cu_tva=0,discount=0 where id_vanz='$id_vz';
update $tabel_final_stoc set cantitate=cantitate+'$cantitate_veche' where cod_p='$produs'; "; 
    

	 
	try{
$pdo->exec($psql5) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 

}catch(PDOException $e)
    {
    echo $psql5 . "<br>" . $e->getMessage();
    } 
	 
function is_decimal($val)
{
    return is_numeric( $val ) && floor( $val ) != $val;
}

if(is_decimal($cantitate_de_adaug)){


    $c=$cantitate_de_adaug;
	 $adaos_unitar=$pret_achiz*$proc_ad/100;
	 $v_adaos=$adaos_unitar*$c;
	 $tva_neex=$v_adaos*$cota_tva/100;
	 $pret_vanzare=round(($pret_achiz+$adaos_unitar),2);
	 $pret_vanzare=floor($pret_vanzare*100)/100;
	 $valoare_vanzare=$pret_vanzare*$c;
	 $tva_col=round(($valoare_vanzare*$cota_tva/100),2);
	 $valoare_vanzare_cu_tva=floor(($valoare_vanzare+$tva_col)*100)/100;
	 $discount=$valoare_vanzare_cu_tva*$proc_d/100;
	 // aici stergi daca ai probleme
$discount=round($discount,2);


    $psql = "update $tabel_final_det_bonuri set cantitate=cantitate+'$c',tva_col=tva_col+'$tva_col',valoare_vanzare=valoare_vanzare+'$valoare_vanzare',valoare_vanzare_cu_tva=valoare_vanzare_cu_tva+'$valoare_vanzare_cu_tva',discount=discount+'$discount' where id_vanz='$id_vz';
update $tabel_final_stoc set cantitate=cantitate-'$c' where cod_p='$produs'; "; 
    

	 
	try{
$pdo->exec($psql) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 

}catch(PDOException $e)
    {
    echo $sql . "<br>" . $e->getMessage();
    } 

    			printf("<script>location.href='creare_bon_simplu.php'</script>");

}
else{
for ($i = 1; $i <= $cantitate_de_adaug; $i++) {
    $c=1;
	 $adaos_unitar=$pret_achiz*$proc_ad/100;
	 $v_adaos=$adaos_unitar*$c;
	 $tva_neex=$v_adaos*$cota_tva/100;
	 $pret_vanzare=round(($pret_achiz+$adaos_unitar),2);
	 $pret_vanzare=floor($pret_vanzare*100)/100;
	 $valoare_vanzare=$pret_vanzare*$c;
	 $tva_col=round(($valoare_vanzare*$cota_tva/100),2);
	 $valoare_vanzare_cu_tva=floor(($valoare_vanzare+$tva_col)*100)/100;
	 $discount=$valoare_vanzare_cu_tva*$proc_d/100;
	 // aici stergi daca ai probleme
$discount=round($discount,2);


    $psql = "update $tabel_final_det_bonuri set cantitate=cantitate+'$c',tva_col=tva_col+'$tva_col',valoare_vanzare=valoare_vanzare+'$valoare_vanzare',valoare_vanzare_cu_tva=valoare_vanzare_cu_tva+'$valoare_vanzare_cu_tva',discount=discount+'$discount' where id_vanz='$id_vz';
update $tabel_final_stoc set cantitate=cantitate-'$c' where cod_p='$produs'; "; 
    

	 
	try{
$pdo->exec($psql) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 

}catch(PDOException $e)
    {
    echo $sql . "<br>" . $e->getMessage();
    } 
}
}
    			printf("<script>location.href='creare_bon_simplu.php'</script>");


    
}
}

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

		

	<div id="procent" class="form-group" style="display:block">
				    <label for="exampleInputEmail1">Procent discount (%)</label></br>
	<div class="input-group"><input class="form-control" max='100' step='0.01' min='0' value='0' type='number' id='val_procent' name='val_procent' style='width:14.5em;'/></div>
</br>
 <input style='margin-top:5px;' class='btn btn-primary btn-block' type="submit" name="apl_disc_proc" value="Aplica discount"/>


</div>
<div class="form-group"  id="val_fixa">
    <label for="exampleInputEmail1">Sau</label></br>

		  <label for="exampleInputEmail1">Valoare discount (RON)</label></br>
		  	<div class="input-group"><input type='number' step='0.001' id='val_fix' style='width:14.5em;' class='form-control' name='valoare_fixa' onchange="calc_tva_disc()"/>RON
</div>
<label for="exampleInputEmail1">Din care TVA</label></br>
<input type='number' step='0.001' readonly id='tva_discount' name='tva_discount' /> RON

 <input style='margin-top:5px;' class='btn btn-primary btn-block' type="submit" name="apl_disc_fix" value="Aplica discount"/>

</div>



</div></div>
				    
				    </form>
<?php 
if(isset($_POST['apl_disc_fix'])){
    
    
    $id_vz=$_POST['idvanzare'];
    
    $p_disc_sql = "SELECT valoare_vanzare_cu_tva from $tabel_final_det_bonuri where id_vanz='$id_vz'";
$p_disc_stmt = $pdo->prepare($p_disc_sql);
$p_disc_stmt->execute(); 
while ($row = $p_disc_stmt->fetch(PDO::FETCH_ASSOC)){

     $valoare_cu_tva=$row['valoare_vanzare_cu_tva'];   

    
}

    $discount=$_POST['valoare_fixa'];

if($discount<=$valoare_cu_tva){
    
    
    $disc_sql = "UPDATE $tabel_final_det_bonuri set discount='$discount' where id_vanz='$id_vz';";    
	try{
$pdo->exec($disc_sql) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 
		  
}catch(PDOException $e)
    {
    echo $disc_sql . "<br>" . $e->getMessage();
    } 
    
        printf("<script>location.href='creare_bon_simplu.php'</script>");

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
    
    $p_disc_sql = "SELECT valoare_vanzare_cu_tva from $tabel_final_det_bonuri where id_vanz='$id_vz'";
$p_disc_stmt = $pdo->prepare($p_disc_sql);
$p_disc_stmt->execute(); 
while ($row = $p_disc_stmt->fetch(PDO::FETCH_ASSOC)){
 $valoare_cu_tva=$row['valoare_vanzare_cu_tva'];   
}
$discount=$valoare_cu_tva*$procent/100;

    $disc_sql = "UPDATE $tabel_final_det_bonuri set discount='$discount' where id_vanz='$id_vz';";    
	try{
$pdo->exec($disc_sql) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 
		  
}catch(PDOException $e)
    {
    echo $disc_sql . "<br>" . $e->getMessage();
    } 
    
    
        printf("<script>location.href='creare_bon_simplu.php'</script>");

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
	<div class="input-group"><input type="number"  step='0.001' name="numerar"  max="<?php echo $total_val_vz_cu_tva;?>" value="<?php echo $total_val_vz_cu_tva; ?>"  step="0.01" min="0"  class="form-control" style="width:14.5em;" id="numerar" onchange="updateCard()"></div>
	   <label for="card">Card</label>
	<div class="input-group"><input  type="number"  step='0.001' min="0" value="0"  name="card" class="form-control" style="width:14.5em;"  step="0.01"  max="<?php echo $total_val_vz_cu_tva;?>" onchange="updateNumerar()" id="card" ></div>
				 <label for="cif_client">CIF CLIENT</label>
	<div class="input-group">    <input  type="text"   maxlength='10' value="<?php echo $_SESSION['cif_client'];?>" name="cif_client_m" class="form-control" id="cif_client_m" placeholder="Ex. RO12345678">
</div>
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
	<div class="input-group"><input type="number"  step='1' name="numerar"  value="1"  min="1"  class="form-control" style="width:14.5em;" id="nr_tichete" onchange="updateTichete()"></div>
	   <label for="card">Valoarea unui tichet</label>
	<div class="input-group"><input  type="number"  step='0.001' min="0"  class="form-control" style="width:14.5em;"  step="0.01" value="<?php echo $total_val_vz_cu_tva;?>" max="<?php echo $total_val_vz_cu_tva;?>" onchange="updateNrTichete()" id="val_tichet" ></div>
	<label for="rest">Valoarea tichetelor</label>
	<div class="input-group">    <input readonly type="number" step='0.001' value="<?php echo $total_val_vz_cu_tva;?>" min="0" max="<?php echo $total_val_vz_cu_tva;?>"  name="total_tichete" class="form-control" id="total_tichete"> </div>
		<label for="rest_de_incasat">Rest de încasat în numerar</label>
	<div class="input-group">   <input  style="width:14.5em;" type="number" step='0.001' value="0" min="0"  onchange="updateRestTichete()"  name="rest_de_incasat" class="form-control" id="rest_de_incasat"></div>
	   <label for="total_de_plata_tichete">Rest de returnat</label>
	<div class="input-group"><input readonly type="number"  step='0.001' name="rest_de_returnat" value='0' class="form-control" id="rest_de_returnat"> </div>
				 <label for="cif_client">CIF CLIENT</label>
	<div class="input-group">    <input  type="text"   maxlength='10' value="<?php echo $_SESSION['cif_client'];?>" name="cif_client_t" class="form-control" id="cif_client_t">
</div>
				 <div class="modal-footer" >

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



					<script  src="javascript.js"></script>
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
	</script>
		<script>// Autocomplete demo
var availableTags = ["ActionScript", "AppleScript", "Asp", "BASIC", "C", "C++", "Clojure",
	"COBOL", "ColdFusion", "Erlang", "Fortran", "Groovy", "Haskell", "Java", "JavaScript",
	"Lisp", "Perl", "PHP", "Python", "Ruby", "Scala", "Scheme" ];

$('#cif_client_t')
	.keyboard({ layout: 'qwerty' })


	.addTyping();
	</script> 
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
	</script>
	<script>
    
    $('#cant_cod_b')
	.keyboard({
		layout : 'num',
		restrictInput : true, // Prevent keys not in the displayed keyboard from being typed in
		preventPaste : true,  // prevent ctrl-v and right click
		autoAccept : true
	})
	.addTyping();
	
</script>
	<script>
    
    $('#baniprim')
	.keyboard({
		layout : 'num',
		restrictInput : true, // Prevent keys not in the displayed keyboard from being typed in
		preventPaste : true,  // prevent ctrl-v and right click
		autoAccept : true
	})
	.addTyping();
	
</script>
	<script>
    
    $('#numerar')
	.keyboard({
		layout : 'num',
		restrictInput : true, // Prevent keys not in the displayed keyboard from being typed in
		preventPaste : true,  // prevent ctrl-v and right click
		autoAccept : true
	})
	.addTyping();
	
</script>
	<script>
    
    $('#card')
	.keyboard({
		layout : 'num',
		restrictInput : true, // Prevent keys not in the displayed keyboard from being typed in
		preventPaste : true,  // prevent ctrl-v and right click
		autoAccept : true
	})
	.addTyping();
	
</script>
	<script>
    
    $('#nr_tichete')
	.keyboard({
		layout : 'num',
		restrictInput : true, // Prevent keys not in the displayed keyboard from being typed in
		preventPaste : true,  // prevent ctrl-v and right click
		autoAccept : true
	})
	.addTyping();
	
</script>
	<script>
    
    $('#rest_de_incasat')
	.keyboard({
		layout : 'num',
		restrictInput : true, // Prevent keys not in the displayed keyboard from being typed in
		preventPaste : true,  // prevent ctrl-v and right click
		autoAccept : true
	})
	.addTyping();
	val_tichet
</script>
	<script>
    $('#val_tichet')
	.keyboard({
		layout : 'num',
		restrictInput : true, // Prevent keys not in the displayed keyboard from being typed in
		preventPaste : true,  // prevent ctrl-v and right click
		autoAccept : true
	})
	.addTyping();
	
</script>
	<script>
    $('#cant_noua')
	.keyboard({
		layout : 'num',
		restrictInput : true, // Prevent keys not in the displayed keyboard from being typed in
		preventPaste : true,  // prevent ctrl-v and right click
		autoAccept : true
	})
	.addTyping();
	
</script>
	<script>
    $('#cant_noua')
	.keyboard({
		layout : 'num',
		restrictInput : true, // Prevent keys not in the displayed keyboard from being typed in
		preventPaste : true,  // prevent ctrl-v and right click
		autoAccept : true
	})
	.addTyping();
	
</script>
	<script>
    $('#val_procent')
	.keyboard({
		layout : 'num',
		restrictInput : true, // Prevent keys not in the displayed keyboard from being typed in
		preventPaste : true,  // prevent ctrl-v and right click
		autoAccept : true
	})
	.addTyping();
	
</script>
	<script>
    $('#val_fix')
	.keyboard({
		layout : 'num',
		restrictInput : true, // Prevent keys not in the displayed keyboard from being typed in
		preventPaste : true,  // prevent ctrl-v and right click
		autoAccept : true
	})
	.addTyping();
	
</script>
		  <script>
		      
		      (function () {

  $('#scrollup').on({
    'mousedown touchstart': function() {
      $(".lista_prod").animate({scrollTop:  0}, 500);
    },
    'mouseup touchend': function() {
      $(".lista_prod").stop(true);
    }
  });

  $('#scrolldown').on({
    'mousedown touchstart': function() {
      $(".lista_prod").animate({
        scrollTop:  $(".lista_prod")[0].scrollHeight
      }, 1000);
    },
    'mouseup touchend': function() {
      $(".lista_prod").stop(true);
    }
});



 $('.rightArrow').on({
    'mousedown touchstart': function() {
      var leftPos = $('.content_cat').scrollLeft();
  $(".content_cat").animate({scrollLeft: leftPos + 200}, 800);
    },
    'mouseup touchend': function() {
      $(".content_cat").stop(true);
    }
});
 $('.leftArrow').on({
    'mousedown touchstart': function() {
      var leftPos = $('.content_cat').scrollLeft();
  $(".content_cat").animate({scrollLeft: leftPos - 200}, 800);
    },
    'mouseup touchend': function() {
      $(".content_cat").stop(true);
    }
});
 $('.rightArrowAmanate').on({
    'mousedown touchstart': function() {
      var leftPos = $('.bamanate').scrollLeft();
  $(".bamanate").animate({scrollLeft: leftPos + 200}, 800);
    },
    'mouseup touchend': function() {
      $(".bamanate").stop(true);
    }
});
 $('.leftArrowAmanate').on({
    'mousedown touchstart': function() {
      var leftPos = $('.bamanate').scrollLeft();
  $(".bamanate").animate({scrollLeft: leftPos - 200}, 800);
    },
    'mouseup touchend': function() {
      $(".bamanate").stop(true);
    }
});
 $('.rightArrowRelistare').on({
    'mousedown touchstart': function() {
      var leftPos = $('.brelistare').scrollLeft();
  $(".brelistare").animate({scrollLeft: leftPos + 200}, 800);
    },
    'mouseup touchend': function() {
      $(".brelistare").stop(true);
    }
});
 $('.leftArrowRelistare').on({
    'mousedown touchstart': function() {
      var leftPos = $('.brelistare').scrollLeft();
  $(".brelistare").animate({scrollLeft: leftPos - 200}, 800);
    },
    'mouseup touchend': function() {
      $(".brelistare").stop(true);
    }
});
 $('.rightArrowClienti').on({
    'mousedown touchstart': function() {
      var leftPos = $('.clienti').scrollLeft();
  $(".clienti").animate({scrollLeft: leftPos + 200}, 800);
    },
    'mouseup touchend': function() {
      $(".clienti").stop(true);
    }
});
 $('.leftArrowClienti').on({
    'mousedown touchstart': function() {
      var leftPos = $('.clienti').scrollLeft();
  $(".clienti").animate({scrollLeft: leftPos - 200}, 800);
    },
    'mouseup touchend': function() {
      $(".clienti").stop(true);
    }
});
    $('#scrollup2').on({
    'mousedown touchstart': function() {
      $(".lista_nomencl").animate({scrollTop:  0}, 500);
    },
    'mouseup touchend': function() {
      $(".lista_nomencl").stop(true);
    }
  });

  $('#scrolldown2').on({
    'mousedown touchstart': function() {
      $(".lista_nomencl").animate({
        scrollTop:  $(".lista_nomencl")[0].scrollHeight
      }, 1000);
    },
    'mouseup touchend': function() {
      $(".lista_nomencl").stop(true);
    }
});
    $('#scrollup3').on({
    'mousedown touchstart': function() {
      $(".bamanate").animate({scrollTop:  0}, 500);
    },
    'mouseup touchend': function() {
      $(".bamanate").stop(true);
    }
  });

  $('#scrolldown3').on({
    'mousedown touchstart': function() {
      $(".bamanate").animate({
        scrollTop:  $(".bamanate")[0].scrollHeight
      }, 1000);
    },
    'mouseup touchend': function() {
      $(".bamanate").stop(true);
    }
});

    $('#scrollup4').on({
    'mousedown touchstart': function() {
      $(".brelistare").animate({scrollTop:  0}, 500);
    },
    'mouseup touchend': function() {
      $(".brelistare").stop(true);
    }
  });

  $('#scrolldown4').on({
    'mousedown touchstart': function() {
      $(".brelistare").animate({
        scrollTop:  $(".brelistare")[0].scrollHeight
      }, 1000);
    },
    'mouseup touchend': function() {
      $(".brelistare").stop(true);
    }
});

    $('#scrollup5').on({
    'mousedown touchstart': function() {
      $(".clienti").animate({scrollTop:  0}, 500);
    },
    'mouseup touchend': function() {
      $(".clienti").stop(true);
    }
  });

  $('#scrolldown5').on({
    'mousedown touchstart': function() {
      $(".clienti").animate({
        scrollTop:  $(".clienti")[0].scrollHeight
      }, 1000);
    },
    'mouseup touchend': function() {
      $(".clienti").stop(true);
    }
});

})();
		  </script>
		<script>
		
		$(document).ready(function() {
		    $(document).ready(function() {
    setInterval(timestamp, 1000);
});

setInterval(function() {
    var date = new Date();
    $('#timestamp').html(
        date.getHours() + ":" + date.getMinutes() + ":" + date.getSeconds()
        );
}, 500);
		    document.getElementById("cod_bare").focus();var $input=$('#cod_bare');var inputTimeout;function checkUserName(){var elms=document.getElementsByTagName('input');for(var i=0;i<elms.length;i++){var sb=elms[i];if(sb.type=="submit"&&sb.value=="Adauga"){sb.click();break}}}
		    // Add your javascript here

  
	

		    

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
$('[name=cantitate_veche]').val(cantitate_curenta);
$('[name=idvz]').val(idvanzare);



});

		$('#plata_numerar_si_card').on('click', function() {
$('#Plata_numerar_si_card').modal('show'); 
});
		$('#plata_tichete').on('click', function() {
$('#Plata_tichete').modal('show'); 
});
		$('#produse_vandute').on('click', function() {
$('#Prod_vandute').modal('show'); 
});
	$('#amanate').on('click', function() {
$('#Amanate').modal('show'); 
});
	$('#relistare').on('click', function() {
$('#Relistare').modal('show'); 
});
    var banideprim = parseFloat(document.getElementById("baniprim").value).toFixed(2);

      if (banideprim <=0) {
        $('#plata_numerar').attr('disabled', 'disabled');
        $('#plata_card').attr('disabled', 'disabled');
        $('#plata_numerar_si_card').attr('disabled', 'disabled');
        $('#plata_tichete').attr('disabled', 'disabled');
        $('#plata_protocol').attr('disabled', 'disabled');


    } else {
        $('#plata_numerar').removeAttr('disabled');
        $('#plata_card').removeAttr('disabled');
        $('#plata_numerar_si_card').removeAttr('disabled');
        $('#plata_tichete').removeAttr('disabled');
                $('#plata_protocol').removeAttr('disabled');
    }
    document.getElementById("cod_bare").focus();
    
var totalwidth = 190 * $('.list-group').length;

$('.scoll-tree').css('width', totalwidth);



	});

</script>
<script>

		    $(document).ready(function() {

    var totalwidth2 = 190 * $('.list-group2').length;

$('.scoll-tree2').css('width', totalwidth2);

	});
</script>

</body>
</html>
