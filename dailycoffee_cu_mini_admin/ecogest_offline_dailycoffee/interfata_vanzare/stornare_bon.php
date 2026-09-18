<?php  
include('session.php');
 $adm_id=$_SESSION['admin_id'];
$dsql = "SELECT * FROM $tabel_final_admins where admin_id='$adm_id'";
$dstmt = $pdo->prepare($dsql);
$dstmt->execute(); 
while ($row = $dstmt->fetch(PDO::FETCH_ASSOC)){
$admin_firstname=$row['admin_firstname'];
$admin_lastname=$row['admin_lastname'];
}
    $nr_bon=$_SESSION['nr_bon'];
    $id_storn=$_SESSION['id_storn'];


?>
				<html>
				<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="description" content="">
  <meta name="author" content="">
  <title>Admin Login</title>
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


 


<link rel="stylesheet" href="./numpad_files/jquery.numpad.css">

				
		



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
      
      
        <h2>Produse</h2>

<table class='table table-bordered'>
 
 

  
  <?php
//afisare randuri NIR

$f_sql = "SELECT $tabel_final_det_bonuri.cod_p,$tabel_final_nomenclator.den_p,$tabel_final_nomenclator.um,$tabel_final_det_bonuri.cantitate,$tabel_final_det_bonuri.tva_col,$tabel_final_det_bonuri.pret_vanzare,$tabel_final_det_bonuri.valoare_vanzare,$tabel_final_det_bonuri.valoare_vanzare_cu_tva,$tabel_final_det_bonuri.id_vanz from $tabel_final_nomenclator inner join $tabel_final_det_bonuri on $tabel_final_det_bonuri.cod_p=$tabel_final_nomenclator.cod_p where nr_bon='$nr_bon';";    
$f_stmt = $pdo->prepare($f_sql);  
$f_stmt->execute(); 

while ($row = $f_stmt->fetch(PDO::FETCH_ASSOC)){ 

$produs=$row['den_p'];
$cantitate=$row['cantitate'];
$_SESSION['cant']=$cantitate;
$tva_col=$row['tva_col'];
$pret_vanzare=$row['pret_vanzare'];
$valoare_vanzare=$row['valoare_vanzare'];
$id_vanz=$row['id_vanz'];
$id_simplu_vanz=$row['id_vanz'];
$id_simplu_vanz=strval($id_simplu_vanz);
$id_simplu_vanz.='SV';
$valoare_vanzare_c_tva=$row['valoare_vanzare_cu_tva'];


  echo "
   <tr>
    <td>$produs</td>
    <td>X $cantitate</td>
	<td>$valoare_vanzare_c_tva</td>

  </tr>
  
  
  
  " 
  
  ;

  }

  ?>
 <th colspan="2">Total</th>  
<?php 
 
 $f_tot_sql = "SELECT sum($tabel_final_det_bonuri.valoare_vanzare) as a from $tabel_final_det_bonuri  where nr_bon='$nr_bon'; ";    
$f_tot_stmt = $pdo->prepare($f_tot_sql);  
$f_tot_stmt->execute();

while ($row = $f_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$valoare_f_vz=$row['a'];
}

   ?>


 



 
  
  
  
 <?php 
 
 $f_tot_sql = "SELECT sum($tabel_final_det_bonuri.tva_col) as b from $tabel_final_det_bonuri  where nr_bon='$nr_bon'; ";    
$f_tot_stmt = $pdo->prepare($f_tot_sql);  
$f_tot_stmt->execute();

while ($row = $f_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_tva_col=$row['b'];
}
   ?>

 
  
  <th>
 <?php 
 
 $f_tot_sql = "SELECT sum($tabel_final_det_bonuri.valoare_vanzare_cu_tva) as c from $tabel_final_det_bonuri where nr_bon='$nr_bon'; ";    
$f_tot_stmt = $pdo->prepare($f_tot_sql);  
$f_tot_stmt->execute();

while ($row = $f_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_val_vz_cu_tva=$row['c'];
}
  echo $total_val_vz_cu_tva;
  



  ?>
  </th>

  
  
</table>

<h2>Stornări</h2>
<table class='table table-bordered'>
 
 

  
  <?php
//afisare randuri NIR

$f_sql = "SELECT $tabel_final_det_stornari.id_vanzare,$tabel_final_det_stornari.cod_p,$tabel_final_nomenclator.den_p,$tabel_final_nomenclator.um,$tabel_final_det_stornari.cantitate,$tabel_final_det_stornari.tva_col,$tabel_final_det_stornari.pret_vanzare,$tabel_final_det_stornari.valoare_vanzare,$tabel_final_det_stornari.valoare_vanzare_cu_tva,$tabel_final_det_stornari.id from $tabel_final_nomenclator inner join $tabel_final_det_stornari on $tabel_final_det_stornari.cod_p=$tabel_final_nomenclator.cod_p where id_stornare='$id_storn';";    
$f_stmt = $pdo->prepare($f_sql);  
$f_stmt->execute(); 

while ($row = $f_stmt->fetch(PDO::FETCH_ASSOC)){ 
    $cd_p=$row['cod_p'];

    $df_sql = "SELECT cantitate from $tabel_final_det_bonuri where nr_bon='$nr_bon' AND cod_p='$cd_p';";    
$df_stmt = $pdo->prepare($df_sql);  
$df_stmt->execute(); 

while ($cdrow = $df_stmt->fetch(PDO::FETCH_ASSOC)){ 
    $cantitate_max=$cdrow['cantitate'];

}
$produs=$row['den_p'];
$cantitate_stornata=$row['cantitate'];
$cantitate_maxim=$cantitate_max-$cantitate_stornata;
$tva_col=$row['tva_col'];
$pret_vanzare=$row['pret_vanzare'];
$valoare_vanzare=$row['valoare_vanzare'];
$id=$row['id'];
$id_simplu_vanz=$row['id'];
$id_simplu_vanz=strval($id_simplu_vanz);
$id_simplu_vanz.='VS';
$id_vanzare=$row['id_vanzare'];

$valoare_vanzare_c_tva=$row['valoare_vanzare_cu_tva'];
  echo "
   <tr>
    <td>$produs</td>";
    if($cantitate_stornata==0){
    echo" <td ><button disabled style='display:inline-block;' name='$id' value='$id_vanzare' data-value='$cantitate_max' class='btn btn-primary btn-block modif_cant'>X  $cantitate_stornata</button></td>";}
     else{
         echo "<td ><button style='display:inline-block;' name='$id' value='$id_vanzare' data-value='$cantitate_max' class='btn btn-primary btn-block modif_cant'>X  $cantitate_stornata</button></td>";
     }
echo"<td>".$valoare_vanzare_c_tva."</td>";
    if($cantitate_stornata==0){
  echo"	<td><form method='post'><input disabled class='btn btn-primary btn-block' type='submit' value='Sterge' name='$id_simplu_vanz'></form></td>";}
	else{
	    
	     echo"	<td><form method='post'><input class='btn btn-primary btn-block' type='submit' value='Sterge' name='$id_simplu_vanz'></form></td>";
	}

 echo " </tr>";
  
  if (isset($_POST[$id_simplu_vanz])) {
					
					$sterg_sql="SELECT * from $tabel_final_det_stornari where $tabel_final_det_stornari.id = '$id' ";
					$sterg_f_stmt = $pdo->prepare($sterg_sql);  
$sterg_f_stmt->execute(); 

while ($row = $sterg_f_stmt->fetch(PDO::FETCH_ASSOC)){ 

$c_p=$row['cod_p'];
$cant_vand=$row['cantitate'];
}
					
					$sql="update $tabel_final_det_stornari set tva_col=0,valoare_vanzare_cu_tva=0,discount=0,pret_vanzare=0,valoare_vanzare=0,cantitate=0 WHERE $tabel_final_det_stornari.id = '$id';";
$pdo->exec($sql) or die(print_r($pdo->errorInfo(), true));
 $_SESSION['tab_curent']='simplu';

			printf("<script>location.href='stornare_bon.php'</script>");
				}
  }
  
  
 
  ?>
 <th colspan="2">Total</th>  
<?php 
 
 $f_tot_sql = "SELECT sum($tabel_final_det_stornari.valoare_vanzare) as d from $tabel_final_det_stornari  where id_stornare='$id_storn'; ";    
$f_tot_stmt = $pdo->prepare($f_tot_sql);  
$f_tot_stmt->execute();

while ($row = $f_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$storn_valoare_f_vz=$row['d'];
}

   ?>


 



 
  
  
  
 <?php 
 
 $f_tot_sql = "SELECT sum($tabel_final_det_stornari.tva_col) as e from $tabel_final_det_stornari  where id_stornare='$id_storn'; ";    
$f_tot_stmt = $pdo->prepare($f_tot_sql);  
$f_tot_stmt->execute();

while ($row = $f_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$storn_total_tva_col=$row['e'];
}
   ?>

 
  
  <th>
 <?php 
 
 $f_tot_sql = "SELECT sum($tabel_final_det_stornari.valoare_vanzare_cu_tva) as f from $tabel_final_det_stornari where id_stornare='$id_storn'; ";    
$f_tot_stmt = $pdo->prepare($f_tot_sql);  
$f_tot_stmt->execute();

while ($row = $f_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_val_vz_cu_tva=$row['f'];
}
  echo $total_val_vz_cu_tva;?>
  </table>


</div>
<div id="two">




<style>
#myInput {
    background-image: url('/css/searchicon.png'); /* Add a search icon to input */
    background-position: 10px 12px; /* Position the search icon */
    background-repeat: no-repeat; /* Do not repeat the icon image */
    width: 100%;
    height: auto; /* Full-width */
    font-size: 16px; /* Increase font-size */
    padding: 12px 20px 12px 40px; /* Add some padding */
    border: 1px solid #ddd; /* Add a grey border */
    margin-bottom: 12px; /* Add some space below the input */
}

#myTable {
    border-collapse: collapse; /* Collapse borders */
    width: 100%; /* Full-width */
    border: 1px solid #ddd; /* Add a grey border */
    font-size: 18px; /* Increase font-size */
}

#myTable th, #myTable td {
    text-align: left; /* Left-align text */
    padding-top: 12px; /* Add padding */
}

#myTable tr {
    /* Add a bottom border to all table rows */
    border-bottom: 1px solid #ddd;
}

#myTable tr.header, #myTable tr:hover {
    /* Add a grey background color to the table header and on hover */
    background-color: #f1f1f1;
}
</style>

<script>
function myFunctions() {
  // Declare variables
  var input, filter, table, tr, td, i;
  input = document.getElementById("myInput");
  filter = input.value.toUpperCase();
  table = document.getElementById("myTable");
  tr = table.getElementsByTagName("tr");

  // Loop through all table rows, and hide those who don't match the search query
  for (i = 0; i < tr.length; i++) {
    td = tr[i].getElementsByTagName("td")[0];
    if (td) {
      if (td.innerHTML.toUpperCase().indexOf(filter) > -1) {
        tr[i].style.display = "";
      } else {
        tr[i].style.display = "none";
      }
    }
  }
}

</script>

 <input type="text" id="myInput"  style="width:100%;" onkeyup="myFunctions()" placeholder="Cauta dupa denumirea produsului...">

<table id="myTable">
  <tr class="header">
    <th >Denumire</th>
  </tr>
        <?php 
     
//$test_sql = "SELECT $tabel_final_det_bonuri.cod_p,$tabel_final_nomenclator.den_p,$tabel_final_nomenclator.um,$tabel_final_det_bonuri.cantitate as cant_bon,sum($tabel_final_det_stornari.cantitate),$tabel_final_det_bonuri.tva_col,$tabel_final_det_bonuri.pret_vanzare,$tabel_final_det_bonuri.valoare_vanzare,$tabel_final_det_bonuri.valoare_vanzare_cu_tva,$tabel_final_det_bonuri.id_vanz from $tabel_final_nomenclator inner join $tabel_final_det_bonuri on $tabel_final_det_bonuri.cod_p=$tabel_final_nomenclator.cod_p inner join $tabel_final_det_stornari on $tabel_final_det_stornari.id_vanzare=$tabel_final_det_bonuri.id_vanz where $tabel_final_det_bonuri.nr_bon='$nr_bon' and $tabel_final_det_bonuri.cantitate>sum($tabel_final_det_stornari.cantitate) ;";    
$test_sql = "SELECT $tabel_final_det_stornari.id_vanzare,$tabel_final_det_bonuri.cantitate as cant_bon,sum($tabel_final_det_stornari.cantitate) as cantitate_total_stornata,$tabel_final_nomenclator.den_p,$tabel_final_det_bonuri.id_vanz from $tabel_final_nomenclator inner join $tabel_final_det_bonuri on $tabel_final_det_bonuri.cod_p=$tabel_final_nomenclator.cod_p inner join $tabel_final_det_stornari on $tabel_final_det_stornari.id_vanzare=$tabel_final_det_bonuri.id_vanz where $tabel_final_det_bonuri.nr_bon='$nr_bon' group by $tabel_final_det_stornari.id_vanzare";
$test_stm = $pdo->prepare($test_sql);  
$test_stm->execute(); 
while ($row = $test_stm->fetch(PDO::FETCH_ASSOC)){ 
    $den_p=$row['den_p'];
    $id_vanz=$row['id_vanz'];
    $cantitate_bon=$row['cant_bon'];

 $cant_total_stornata=$row['cantitate_total_stornata'];
 if($cant_total_stornata<$cantitate_bon){
 echo"<tr>
   <td><form method='POST'><button style='width:100%; font-size:1em; height:auto; white-space: normal;
' class='btn btn-primary btn-block' type='submit' name='$id_vanz'>$den_p</button></form></td>
  </tr> ";
     
     
 }
 
 if(isset($_POST[$id_vanz])){
 $produs=$cod_p;
	 $psql2 = "SELECT * from $tabel_final_det_bonuri where id_vanz='$id_vanz';";    
$pstmt2 = $pdo->prepare($psql2);  
$pstmt2->execute(); 

while ($row = $pstmt2->fetch(PDO::FETCH_ASSOC)){ 
                      $pret_vanzare=$row['pret_vanzare'];
$cantitate=$row['cantitate'];
                      	 $tva_col=$row['tva_col']/$cantitate;
			                   $valoare_vanzare=$row['valoare_vanzare']/$cantitate;
							   $valoare_vanzare_cu_tva=$row['valoare_vanzare_cu_tva']/$cantitate;
							   $discount=$row['discount']/$cantitate;
}
	 
	 $cantitate=1;
	

    $psql = "update $tabel_final_det_stornari set cantitate=cantitate+'$cantitate',pret_vanzare='$pret_vanzare',tva_col=tva_col+'$tva_col',valoare_vanzare=valoare_vanzare+'$valoare_vanzare',valoare_vanzare_cu_tva=valoare_vanzare_cu_tva+'$valoare_vanzare_cu_tva',discount=discount+'$discount' where id_stornare='$id_storn' and id_vanzare='$id_vanz'"; 
    

	 
	try{
$pdo->exec($psql) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 

}catch(PDOException $e)
    {
    echo $sql . "<br>" . $e->getMessage();
    } 
    
    			printf("<script>location.href='stornare_bon.php'</script>");


 
 }
 
    
}

      ?>
 

</table> 

   </br>
 

<form method='POST'> <input type='submit' class='btn btn-primary btn-block' name='stornare_bon' value='Stornare'/></form>
    </br><form method='POST'> <input type='submit' class='btn btn-primary btn-block' name='anuleaza_storn' value='Anuleaza stornarea'/></form>
<?php 

if(isset($_POST['anuleaza_storn'])){
           	
           	$sql="DELETE FROM $tabel_final_stornari WHERE $tabel_final_stornari.id_stornare ='$id_storn';
					DELETE from $tabel_final_det_stornari WHERE $tabel_final_det_stornari.id_stornare ='$id_storn';";
$pdo->exec($sql) or die(print_r($pdo->errorInfo(), true));
	unset($_SESSION['nr_bon']);

			printf("<script>location.href='lista_bonuri.php'</script>");
           
       }
if(isset($_POST['stornare_bon'])){
    
    
    

 $ora_bon = date("H:i:s", strtotime('+0 hours'));
 $data_bon = date("Y-m-d", strtotime('+0 hours'));

  
$fin_sql = "update $tabel_final_stornari set nr_bon='$nr_bon',operator='$adm_id',tva_colectata='$storn_total_tva_col',valoare_fara_tva='$storn_valoare_f_vz',data_stornare='$data_bon',ora_stornare='$ora_bon',status='F' where id_stornare='$id_storn'"; 
	try{
$pdo->exec($fin_sql) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 
		  
}catch(PDOException $e)
    {
    echo $fin_sql . "<br>" . $e->getMessage();
    } 
 
	
	 

$misc_sql = "SELECT $tabel_final_det_stornari.cod_p,$tabel_final_det_stornari.cantitate,$tabel_final_nomenclator.gestiune from $tabel_final_det_stornari INNER JOIN $tabel_final_nomenclator on $tabel_final_det_stornari.cod_p=$tabel_final_nomenclator.cod_p where $tabel_final_det_stornari.nr_bon='$nr_bon' and $tabel_final_det_stornari.id_stornare='$id_storn' and $tabel_final_det_stornari.cantitate>0;";     
$misc_stmt = $pdo->prepare($misc_sql);  
$misc_stmt->execute(); 

while ($row = $misc_stmt->fetch(PDO::FETCH_ASSOC)){ 
    $gst=$row['gestiune'];
         $prod=$row['cod_p'];
     $qt=$row['cantitate'];
     	date_default_timezone_set('UTC');
     	if($gst!='PF'){
     $stocsql="update $tabel_final_stoc set cantitate=cantitate+'$cantitate' where cod_p='$prod';"; 
     $stocstmt = $pdo->prepare($stocsql);  
$stocstmt->execute(); 
    $iessql = "insert into $tabel_final_miscari(data,cod_p,cantitate_misc,tip_miscare,fel_doc,nr_doc) values('$data_bon','$prod','$qt','I','BS','$id_storn');";   

	 
	try{
$pdo->exec($iessql) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 

}catch(PDOException $e)
    {
    echo $iessql . "<br>" . $e->getMessage();
    } 
     	}   
}

  $max_disp_sql="select max(nr_disp) as max_disp from $tabel_final_dispozitii;"; 
     $max_disp_stmt = $pdo->prepare($max_disp_sql);  
$max_disp_stmt->execute(); 
while ($row = $max_disp_stmt->fetch(PDO::FETCH_ASSOC)){ 
$max_disp=$row['max_disp'];
    
    
}
$nr_disp=$max_disp+1;
$val_disp=$storn_valoare_f_vz+$storn_total_tva_col;
$insert_disp_sql="insert into $tabel_final_dispozitii(nr_disp,tip_disp,suma_disp,data_disp) values('$nr_disp','p','$val_disp','$data_bon') ;"; 
     $insert_disp_stmt = $pdo->prepare($insert_disp_sql);  
$insert_disp_stmt->execute(); 
	unset($_SESSION['nr_bon']);

printf("<script>location.href='lista_bonuri.php'</script>");

	
}
 

        // If result matched $myusername and $mypassword, the result table's number of rows must be 1 row
	// if the table has 1 rows redirect the user to admin_index.php
     
 
 ?>
 
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
 				      <input hidden type="number"  name='cantitate_veche'/>

				    		<div class="form-group">
				    <label for="exampleInputEmail1">Cantitatea nouă</label></br>
<div class="input-group"><input type="number"  name="cantitate_noua" min="1" class="form-control" id="cant_noua" ><span class="input-group-btn"><button class="btn btn-default nmpd-target" id="cant-btn" type="button" readonly="readonly" data-numpad="nmpd3"><i class="fa fa-calculator"></i></button> </span> </div>	<input class='btn btn-primary btn-block' type="submit" name="modific_cant" value="Salvează"/>	</div>

</form>

<?php if(isset($_POST['modific_cant'])){
    
    
    $id_vz=$_POST['idvz'];
    
    
		         	     $psql5 = "update $tabel_final_det_stornari set tva_col=0,valoare_vanzare_cu_tva=0,discount=0,pret_vanzare=0,valoare_vanzare=0,cantitate=0 WHERE $tabel_final_det_stornari.id = '$id_vz';"; 
    

	 
	try{
$pdo->exec($psql5) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 

}catch(PDOException $e)
    {
    echo $psql5 . "<br>" . $e->getMessage();
    } 
        		 $cantitate_veche=$_POST['cantitate_veche'];
    		 $cantitate_de_adaug=$_POST['cantitate_noua'];
    	 $id_vanzaree=$_POST['produs_modif_cant'];

 	 $psql2M = "SELECT * from $tabel_final_det_bonuri where id_vanz='$id_vanzaree';";   

$pstmt2M = $pdo->prepare($psql2M);  
$pstmt2M->execute(); 

while ($row = $pstmt2M->fetch(PDO::FETCH_ASSOC)){ 
                      $pret_vanzare=$row['pret_vanzare'];
                      	 $tva_col=$row['tva_col']/$cantitate_veche;
			                   $valoare_vanzare=$row['valoare_vanzare']/$cantitate_veche;
							   $valoare_vanzare_cu_tva=$row['valoare_vanzare_cu_tva']/$cantitate_veche;
							   $discount=$row['discount']/$cantitate_veche;
}
	 for ($i = 1; $i <= $cantitate_de_adaug; $i++) {

	 $cantitate=1;
	


    $psql = "update $tabel_final_det_stornari set cantitate=cantitate+'$cantitate',pret_vanzare='$pret_vanzare',tva_col=tva_col+'$tva_col',valoare_vanzare=valoare_vanzare+'$valoare_vanzare',valoare_vanzare_cu_tva=valoare_vanzare_cu_tva+'$valoare_vanzare_cu_tva',discount=discount+'$discount' where id_stornare='$id_storn' and id_vanzare='$id_vanzaree'"; 
    

	 
	try{
$pdo->exec($psql) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 

}catch(PDOException $e)
    {
    echo $sql . "<br>" . $e->getMessage();
    }
	 }
    
    			printf("<script>location.href='stornare_bon.php'</script>");


 
 
 
    
}




 
 

?>
 </div>

				  </div>
		

							  </div>			  
	  
							  </div>




</div>			  
			</div>	
<footer class="sticky-footer" style="width:100%;height:96px;">
  <div class="container" style='margin:0;width:100%;' >
    <table>
    <tr>
<!-- 1st row verticaly centered text in the square columns -->

<div style="background-color:#E5E4E2;float: none;overflow: hidden;">       <button disabled id='white_button' class="square banner1" style='width:100%;font-size:1.5em;'><p style="margin:0;line-height:2">Nota nr.: <?php echo $nr_bon; ?> <br>  Operator: <?php echo $admin_firstname.' '.$admin_lastname;?></p></button></div>

    

</tr>
</table>

  </div>
</footer>


							
				

			</div>
		  </div>



		
		  
		<script>
		
		$(document).ready(function() {
		    

$('.modif_cant').on('click', function() {
$('#Cantitate').modal('show');
var produs_modif = $(this).val();
var cantitate_curenta = this.getAttribute("data-value");
var idvanzare = this.getAttribute("name");

$('[name=produs_modif_cant]').val(produs_modif);
$('[name=cantitate_noua]').val(cantitate_curenta);

$('[name=cantitate_noua]').attr({
       "max" : cantitate_curenta
    });
$('[name=cantitate_veche]').val(cantitate_curenta);
$('[name=idvz]').val(idvanzare);



});


	});

</script>

</body>
</html>
