<?php  include('session.php');
	$title = 'Creare bon de consum';
  include 'header.php';

	if(!isset($_SESSION['nr_bon_c'] )){
	  printf("<script>location.href='note_de_receptie.php'</script>");

	}
	  $date_firma = "SELECT vanzare_sub_stoc from $tabel_final_date_firma";    
$date_firma_stmt = $pdo->prepare($date_firma);  
$date_firma_stmt->execute(); 
while ($row = $date_firma_stmt->fetch(PDO::FETCH_ASSOC)){ 

$_SESSION['vanzare_sub_stoc']=$row['vanzare_sub_stoc'];

}
		 $nr_bon=$_SESSION['nr_bon_c'] ;
		 
		  $fsql3 = "SELECT * from $tabel_final_bonuri_consum where nr_bon='$nr_bon' ";    
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


<!-- about -->

<style>.table-bordered{
text-align:center;
}</style>
		

	 <div class="card mb-3">
        <div class="card-header">
          <i class="fa fa-table"></i> Completati bonul de consum</div>
        <div class="card-body">
          <div class="table-responsive">
 
     <table style='float:right;width:100%;' class='table table-bordered'>   

 <td><h4>Numar Bon:</h4></td>
 <td> <?php
echo $nr_bon; 
 

?>
 </td></tr>
  <tr> 	 
  <td><h4>Produs:</h4></td>
   <td> <?php
echo $produs;
 

?></td></tr> 
     <tr><td><h4>Data Bonului:</h4></td>
   <td><?php
   $data_bon=date( 'd-m-Y', strtotime($data_bon ) );
echo  $data_bon; 
 

?></td></tr>
  
    
    
    </table>
 
 

 <form method="post">
<table class='table table-bordered'>
         
        <tr> 	 
  <td><h4>Denumire Produs:</h4></td>
  <td> <?php
$psql = "SELECT * from $tabel_final_nomenclator  where gestiune!='PF' and cod_categ!=14";    
$pstmt = $pdo->prepare($psql);  
$pstmt->execute(); 
echo "<select onchange='showUser(this.value)'  style='width:100%;height:auto;' class='alege_prod form-control js-example-basic-single' name='produs'>"; 
while ($row = $pstmt->fetch(PDO::FETCH_ASSOC)){  
                      $den_prod=$row['den_p'];
             $cod_p=$row['cod_p'];
			                   $p_a=$row['pret'];
              echo "<option value='$cod_p'>$den_prod | $p_a LEI</option>";    
             
			

}
echo "</select>";
 if(isset($_POST['adaug_produs'])){
     	 $produs=$_POST['produs'];
 
   
   // ramas
   
  
   
   
   // ramas
   
   
    
	$cant_necesara=$_POST['cant_necesara'];
	$cant_eliberata=$_POST['cant_eliberata'];
	if($_SESSION['vanzare_sub_stoc']==0){
	    
$verif_stoc_sql = "SELECT $tabel_final_nomenclator.den_p,$tabel_final_nomenclator.um,$tabel_final_nomenclator.pret,$tabel_final_stoc.cantitate from $tabel_final_nomenclator inner join $tabel_final_stoc on $tabel_final_nomenclator.cod_p=$tabel_final_stoc.cod_p where $tabel_final_nomenclator.cod_p='$produs'";    
$verif_stoc_stmt = $pdo->prepare($verif_stoc_sql);  
$verif_stoc_stmt->execute(); 
while ($row = $verif_stoc_stmt->fetch(PDO::FETCH_ASSOC)){ 
$stoc=$row['cantitate'];
$d_p=$row['den_p'];
$um=$row['um'];
$pret_achiz=$row['pret'];
}

$verif_stoc2_sql = "SELECT cantitate_elib from $tabel_final_consumuri where nr_bon='$nr_bon' and cod_p='$produs';";    
$verif_stoc2_stmt = $pdo->prepare($verif_stoc2_sql);  
$verif_stoc2_stmt->execute(); 
while ($row = $verif_stoc2_stmt->fetch(PDO::FETCH_ASSOC)){ 
    $qt_existenta=$row['cantitate_elib'];
}
$stoc=$stoc-$qt_existenta;
if($cant_eliberata>$stoc) {
$alerta="Stoc insuficient!Stocul disponibil pentru ".$d_p. "  este de ".$stoc." ".$um;
 echo '<script language="javascript">';
  echo 'var myvar = ';echo json_encode($alerta).';';
echo 'alert';echo'('.myvar.');';
echo '</script>';
		 }
		 else{
		     
	     
	     
$psql3 = "SELECT id_consum from $tabel_final_consumuri where nr_bon='$nr_bon' and cod_p='$produs' and pret_unitar='$pret_achiz';";    
$pstmt3 = $pdo->prepare($psql3);  
$pstmt3->execute(); 
$prodcount=$pstmt3->rowCount();
if($prodcount==0){
$psql = "insert into $tabel_final_consumuri(nr_bon,cod_p,cantitate_nec,cantitate_elib,pret_unitar) values('$nr_bon','$produs','$cant_necesara','$cant_eliberata','$pret_achiz');";    
}

else{
    
    $psql = "update $tabel_final_consumuri set cantitate_nec=cantitate_nec+'$cant_necesara',cantitate_elib=cantitate_elib+'$cant_eliberata' where nr_bon='$nr_bon' and cod_p='$produs';
 "; 
}
	 
	try{
$pdo->exec($psql) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 
        echo '<center><div>Produs adaugat</div></center>';
		  
}catch(PDOException $e)
    {
    echo $sql . "<br>" . $e->getMessage();
    } 
    
		 }
		 
		 
	
 }
 
 
 else{
     	
$psql3 = "SELECT id_consum from $tabel_final_consumuri where nr_bon='$nr_bon' and cod_p='$produs' and pret_unitar='$pret_achiz';";    
$pstmt3 = $pdo->prepare($psql3);  
$pstmt3->execute(); 
$prodcount=$pstmt3->rowCount();

if($prodcount==0){
	 
	 
$psql = "insert into $tabel_final_consumuri(nr_bon,cod_p,cantitate_nec,cantitate_elib,pret_unitar) values('$nr_bon','$produs','$cant_necesara','$cant_eliberata','$pret_achiz');";    
}

else{
    
    $psql = "update $tabel_final_consumuri set cantitate_nec=cantitate_nec+'$cant_necesara',cantitate_elib=cantitate_elib+'$cant_eliberata' where nr_bon='$nr_bon' and cod_p='$produs';
 "; 
}
	 
	try{
$pdo->exec($psql) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 
        echo '<center><div>Produs adaugat</div></center>';
		  
}catch(PDOException $e)
    {
    echo $sql . "<br>" . $e->getMessage();
    } 
 }
}
?>   </td><td><input class='btn btn-primary btn-block' type="submit" name="adaug_produs" value="Adauga produs"></td>
 </tr><tr>
    <td><h4>Cantitate Necesara:</h4></td>
<td><input  class='form-control' type="number"  name="cant_necesara" min="0.00001" step="0.00001" value="1" ></td>
<td><h4>Cantitate Eliberata:</h4></td>
<td><input class='form-control' type="number" name="cant_eliberata" min="0.00000" step="0.00001" value="1" ></td></tr>

 </table>

 </form>
 
<table class='table table-bordered'>
  <tr>
    <th>Nr.Crt.</th>
    <th width="15%"  >Denumire material</th>
    <th>U.M.</th>
    <th  >Cant.<br>neces.</th>
    <th  >Cant.<br>elib.</th>

    <th  >Elimina</th>
	

  </tr>
  <tr>
    <td ><b>0</b></td>
    <td width="15%" ><b>1</b></td>
    <td ><b>2</b></td>
    <td ><b>3</b></td>
    <td ><b>4</b></td>
    <td ><b>5</b></td>

  



  </tr>
  
  <?php
//afisare randuri NIR
$nr_bon=$_SESSION['nr_bon_c'] ;
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
<td >$cantitate_prim</td>    

	<td><form method='post'><input class='btn btn-primary btn-block' type='submit' value='Sterge' name='$id_achz'></form></td>

  </tr>
  
  
  
  " 
  
  ;
  if (isset($_POST[$id_achz])) {
					
			
					
					$sql="DELETE FROM $tabel_final_consumuri WHERE $tabel_final_consumuri.id_consum = '$id_achz';";
$pdo->exec($sql) or die(print_r($pdo->errorInfo(), true));

			printf("<script>location.href='bon_de_consum.php'</script>");
				}
  $nr_crt1++;}
  
  
 
  ?>
 


  
 <?php 

  
if(isset($_POST['salvare_nir'])){


			printf("<script>location.href='bonuri_de_consum.php'</script>");

 
	
}

if(isset($_POST['finaliz_nir'])){
	
	
	
$ultim_fact_sql = "SELECT max(nr_doc) as ultim_bon from $tabel_final_miscari where fel_doc='BCF'";    
$ultim_fact_stmt = $pdo->prepare($ultim_fact_sql);  
$ultim_fact_stmt->execute(); 

while ($row = $ultim_fact_stmt->fetch(PDO::FETCH_ASSOC)){ 
                      $ultim_bon=$row['ultim_bon']+1;
}


$ultim_fact_sql2 = "SELECT max(nr_nota) as ultim_com from $tabel_final_miscari where fel_doc='BCF'";    
$ultim_fact_stmt2 = $pdo->prepare($ultim_fact_sql2);  
$ultim_fact_stmt2->execute(); 

while ($row = $ultim_fact_stmt2->fetch(PDO::FETCH_ASSOC)){ 
                      $ultim_com=$row['ultim_com']+1;
} 

	
	 
$fin_sql = "update $tabel_final_bonuri_consum SET finalizat=1,nr_bon='$ultim_bon',nr_comanda='$ultim_com' WHERE nr_bon='$nr_bon';";    
			
			
	 
	try{
$pdo->exec($fin_sql) or die(print_r($pdo->errorInfo(), true));   



}catch(PDOException $e)
    {
    echo $fin_sql . "<br>" . $e->getMessage();
    } 
 
	 $nr_bon=$_SESSION['nr_bon_c'] ;
	 $data_bonului=$_SESSION['data_bon'];

$misc_sql = "SELECT $tabel_final_consumuri.cod_p,$tabel_final_consumuri.id_consum,$tabel_final_consumuri.pret_unitar,$tabel_final_consumuri.cantitate_elib from $tabel_final_consumuri where nr_bon='$nr_bon';";    
$misc_stmt = $pdo->prepare($misc_sql);  
$misc_stmt->execute(); 

while ($row = $misc_stmt->fetch(PDO::FETCH_ASSOC)){
    	date_default_timezone_set('UTC');
$id_consum=$row['id_consum'];
         $prod=$row['cod_p'];
     $qt=$row['cantitate_elib'];




//rms
$cant_scazuta=0;

 

    // aici se incepe si mai jos se termina actualizarea pe ramas
    $cant_ramasa=$qt;
    
    while($cant_ramasa>0 )
             {
      $ramas_sql = "SELECT id,pu,ramas,pret_vanzare FROM $tabel_final_miscari where fel_doc='NIR' and cod_p='$prod' and ramas>0 order by data,id ASC LIMIT 1;";    
$ramas_stmt = $pdo->prepare($ramas_sql);  
$ramas_stmt->execute(); 
       while ($row = $ramas_stmt->fetch(PDO::FETCH_ASSOC)){ 
$id_ramas=$row['id'];
$pretul_unitar_al_miscarii=$row['pu'];
$pretul_unitar_al_vanzarii=$row['pret_vanzare'];

$ramas=$row['ramas'];
       } 
   if($cant_ramasa<$ramas){
           $update_ramas = "update $tabel_final_miscari set ramas=ramas-'$cant_ramasa' where id='$id_ramas'";   
         $cant_scazuta=$cant_ramasa;
   }
   else{
    $update_ramas = "update $tabel_final_miscari set ramas=ramas-'$ramas' where id='$id_ramas'";  
             $cant_scazuta=$ramas;
   }
	try{
$pdo->exec($update_ramas) or die(print_r($pdo->errorInfo(), true));   
}catch(PDOException $e)
    {
    echo $update_ramas . "<br>" . $e->getMessage();
    } 
           $iessql = "insert into $tabel_final_miscari(data,cod_p,cantitate_misc,tip_miscare,fel_doc,nr_doc,pu,pret_vanzare) values('$data_bonului','$prod','$cant_scazuta','O','BCF','$ultim_bon','$pretul_unitar_al_miscarii','$pretul_unitar_al_vanzarii');";   
	try{
$pdo->exec($iessql) or die(print_r($pdo->errorInfo(), true));   
}catch(PDOException $e)
    {
    echo $iessql . "<br>" . $e->getMessage();
    }
    // copiere in consumuri   
     $iessql = "insert into $tabel_final_consumuri(nr_bon,cod_p,cantitate_nec,cantitate_elib,pret_unitar) values('$ultim_bon','$prod','$cant_scazuta','$cant_scazuta','$pretul_unitar_al_miscarii');";   
	try{
$pdo->exec($iessql) or die(print_r($pdo->errorInfo(), true));   
}catch(PDOException $e)
    {
    echo $iessql . "<br>" . $e->getMessage();
    }
    
    
//     copiere in consumuri

    $cant_ramasa=$cant_ramasa-$cant_scazuta;
    
             }
             
            //aici se termina actualizarea pe ramas 


	     
		   
    
        $stoc_sql = "update $tabel_final_stoc set cantitate=cantitate-'$qt' where cod_p=$prod;";   

	 
	try{
$pdo->exec($stoc_sql) or die(print_r($pdo->errorInfo(), true));   
$confirmare="Bonul a fost finalizat!Numarul acestuia este :".$ultim_bon;
 echo '<script language="javascript">';
  echo 'var myvar = ';echo json_encode($confirmare).';';

echo 'alert';echo'('.myvar.');';
echo '</script>';
			printf("<script>location.href='bonuri_de_consum_personalizate.php'</script>");
}catch(PDOException $e)
    {
    echo $stoc_sql . "<br>" . $e->getMessage();
    } 
    
    
        $del_sql = "DELETE from $tabel_final_consumuri where id_consum='$id_consum';";    
$del_stmt = $pdo->prepare($del_sql);  
$del_stmt->execute(); 
    
    
    
}
	
	
}

  ?>
  </th>

  
  
</table>

 <form method="post">
   
<input  class='btn btn-primary btn-block' type='submit' name='salvare_nir' value='Salvare Bon de Consum'> <input  class='btn btn-primary btn-block' type="submit" name="finaliz_nir" value="Finalizare Bon de Consum">

 </form>
 <br>
<?php 
$nr_bon=$_SESSION['nr_bon_c'] ;   
$anul_nir_sql = $pdo->prepare("SELECT id_consum from $tabel_final_consumuri where nr_bon='$nr_bon'; ");  
$anul_nir_sql->execute();
	  $count=$anul_nir_sql->rowCount();
	 
	
	 if($count == 0) {
         
         
         
        echo "<form method='post'>
 <input class='btn btn-primary btn-block' type='submit' name='anulare_nir' value='Anulare Bon de Consum'>
 </form>" ;
      } else{
		  echo "<form method='post'>
 <input class='btn btn-primary btn-block' type='submit' name='anulare_nir' value='Pentru a anula bonul de consum stergeti toate materialele' disabled>
 </form>" ;
		  }
	  
	   if(isset($_POST['anulare_nir'])){
$sql="DELETE from $tabel_final_bonuri_consum WHERE $tabel_final_bonuri_consum.nr_bon = '$nr_bon';
					";
$pdo->exec($sql) or die(print_r($pdo->errorInfo(), true));

			printf("<script>location.href='bonuri_de_consum_personalizate.php'</script>");

	   }


?>
 <!-- InstanceEndEditable -->
</div>
</div>
</div>





<?php 
	require "footer.php";
?>
		<script>
		function updateDue(){
		    var pret=parseFloat(document.getElementById("pret").value).toFixed(2);
		var cota_tva=parseFloat(document.getElementById("cota_tva").value).toFixed(2);
			var cota_adaos=parseFloat(document.getElementById("cota_adaos").value).toFixed(2);
		var proc_discount=parseFloat(document.getElementById("proc_discount").value).toFixed(2);

		if(!pret){pret=0}
if(!cota_tva){cota_tva=0}
		if(!cota_adaos){cota_adaos=0}
if(!proc_discount){proc_discount=0}
if(cota_tva==1){
    cota_tva=9;
}
if(cota_tva==2){
    cota_tva=19;
}
if(cota_tva==3){
    cota_tva=0;
}
var adaos_unit=pret*cota_adaos/100;
var tva=(Number(adaos_unit)+Number(pret))*Number(cota_tva)/100;
var pret_vanzare=Number(pret)+Number(adaos_unit)+Number(tva);
var pret_vanzare_cu_disc=pret_vanzare-(pret_vanzare*proc_discount/100);

var ansD=document.getElementById("pret_vanzare");
ansD.value=Math.round(pret_vanzare_cu_disc*100)/100
   
		    
		}
	function update_pachiz(){
	    
	    		    var pret_achizitie=parseFloat(document.getElementById("pret").value).toFixed(2);
if(pret_achizitie!=0){

		    var pret_vanzare=parseFloat(document.getElementById("pret_vanzare").value).toFixed(2);
		var cota_tva=parseFloat(document.getElementById("cota_tva").value).toFixed(2);


		if(!pret_vanzare){pret_vanzare=0}
if(!cota_tva){cota_tva=0}
	
if(cota_tva==1){
    cota_tva=9;
}
if(cota_tva==2){
    cota_tva=19;
}
if(cota_tva==3){
    cota_tva=0;
}


var tva=(pret_vanzare*cota_tva)/(Number(cota_tva)+Number(100));
var pret_achiz=pret_vanzare-tva;

var adaos_calc=pret_achiz-pret_achizitie;
var cota_adaos_calc=adaos_calc/pret_achizitie*100;

var ansD=document.getElementById("cota_adaos");
ansD.value=cota_adaos_calc;

}

else{
		    var pret_vanzare=parseFloat(document.getElementById("pret_vanzare").value).toFixed(2);
		var cota_tva=parseFloat(document.getElementById("cota_tva").value).toFixed(2);


		if(!pret_vanzare){pret_vanzare=0}
if(!cota_tva){cota_tva=0}
	
if(cota_tva==1){
    cota_tva=9;
}
if(cota_tva==2){
    cota_tva=19;
}
if(cota_tva==3){
    cota_tva=0;
}


var tva=(pret_vanzare*cota_tva)/(Number(cota_tva)+Number(100));
var pret_achiz=pret_vanzare-tva;
var ansD=document.getElementById("pret");
ansD.value=Math.round(pret_achiz*100)/100
		  updateDue(); 
		  
	}
		}
		
		$(function () {
    $(".alege_prod").change();
});

function showUser(str) {
    if (str == "") {
        document.getElementById("txtHint").innerHTML = "";
        return;
    } else { 
        if (window.XMLHttpRequest) {
            // code for IE7+, Firefox, Chrome, Opera, Safari
            xmlhttp = new XMLHttpRequest();
        } else {
            // code for IE6, IE5
            xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
        }
        xmlhttp.onreadystatechange = function() {
            if (this.readyState == 4 && this.status == 200) {
                document.getElementById("txtHint").innerHTML = this.responseText;
            }
        };
        xmlhttp.open("GET","getuser.php?q="+str,true);
        xmlhttp.send();
    }
}
</script>