<?php  include('session.php');
	$title = 'NIR noua';
  include 'header.php';
	if(!isset($_SESSION['nr_nir'])){
	  printf("<script>location.href='note_de_receptie.php'</script>");
	}
		 $nrnir=$_SESSION['nr_nir'];
		  $fsql3 = "SELECT * from $tabel_final_nir where nr_nir='$nrnir'";    
$fstmt3 = $pdo->prepare($fsql3);  
$fstmt3->execute(); 
while ($row = $fstmt3->fetch(PDO::FETCH_ASSOC)){ 
	  	$data_nir=$row['data_nir'];
}

?>


<!-- about -->

<style>.table-bordered{
text-align:center;
}
th,td{font-size:0.8em;}
</style>
		

	 <div class="card mb-3">
        <div class="card-header">
          <i class="fa fa-table"></i> Completati nota de receptie si constatare diferente</div>
        <div class="card-body">
          <div class="table-responsive">
     <table style='float:left;width:50%;' class='table table-bordered'>   
  <tr>
  <td><h6>Datele Furnizorului:</h6>
  <?php
  
  $cfurnizor=$_SESSION['cod_furnizor'];
 $fsql = "SELECT * from $tabel_final_terti where cod_tert='$cfurnizor'";    
$fstmt = $pdo->prepare($fsql);  
$fstmt->execute(); 
while ($row = $fstmt->fetch(PDO::FETCH_ASSOC)){ 
                      $den_tert=$row['denumire'];
			             $adresa=$row['adresa']; 
						 $cui_cif=$row['cui_cif']; 
						 $cont_banca=$row['cont_banca']; 
						 $judet=$row['judet'];
                        $nr_reg_com=$row['nr_reg_com'];
                        $banca=$row['banca'];

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
             
			

}


echo "</td></tr>"; 
?><tr> 
</table>
     <table style='float:right;width:50%;' class='table table-bordered'>   

 <td><h6>Seria Facturii:</h6></td>
 <td> <?php
echo $_SESSION['serie_d']; 
 

?>
 </td></tr>
  <tr> 	 
  <td><h6>Numarul Facturii:</h6></td>
   <td> <?php
echo $_SESSION['nr_d']; 
 

?></td></tr> 
     <td><h6>Data Facturii:</h6></td>
   <td><?php
   $data_doc=date( 'd-m-Y', strtotime( $_SESSION['data_doc'] ) );
echo  $data_doc; 
 

?></td>
  
    
    
    </table>
 
 <script>
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

 <form method="post">
<table class='table table-bordered'>
         
        <tr> 	 
  <td><h6>Denumire Produs:</h6></td>
  <td> <?php
$psql = "SELECT * from $tabel_final_nomenclator where gestiune!='PF' and cod_categ!=14 ";    
$pstmt = $pdo->prepare($psql);  
$pstmt->execute(); 
echo "<select onchange='showUser(this.value)'  style='width:100%;height:auto;' class='alege_prod form-control js-example-basic-single' name='produs'>"; 
while ($row = $pstmt->fetch(PDO::FETCH_ASSOC)){ 
                      $den_prod=$row['den_p'];
             $cod_p=$row['cod_p'];
			                   $p_a=$row['pret'];
							   $cota_ad=$row['proc_adaos'];

              echo "<option value='$cod_p'>$den_prod | $p_a LEI</option>";    
             
			

}
echo "</select>";

 if(isset($_POST['adaug_produs'])){
     $pret_achiz=$_POST['pret_achiz'];
	 $produs=$_POST['produs'];
	 $cota_tva=$_POST['cota_tva_achiz'];
	 
	 $psql2 = "SELECT * from $tabel_final_nomenclator INNER JOIN cote_tva on $tabel_final_nomenclator.cota_tva=cote_tva.id where cod_p='$produs';";    
$pstmt2 = $pdo->prepare($psql2);  
$pstmt2->execute(); 

while ($row = $pstmt2->fetch(PDO::FETCH_ASSOC)){ 
					  $gestiune=$row['gestiune'];
							   $pret_vanzare=$row['pret_vanzare'];
$cota_tva_vz=$row['cota'];


}
	 
	 $nrnir=$_SESSION['nr_nir'];
	 $cant_doc=$_POST['cant_doc'];
	 $cant_prim=$_POST['cant_prim'];
	 $v_ftva=$pret_achiz*$cant_prim;
	 $tva_ded=$v_ftva*$cota_tva/100;
	 if($gestiune=='MR'){

	 $pret_fara_tva=round($pret_vanzare/((100+$cota_tva_vz)/100),2);
$adaos_unitar=$pret_fara_tva-$pret_achiz;
$v_adaos=$adaos_unitar*$cant_prim;
$proc_ad=($pret_fara_tva*100/$pret_achiz)-100;
$tva_neex=round(($v_adaos*$cota_tva_vz/100),2);
$tva_col_unitar=round(($pret_fara_tva*$cota_tva_vz/100),2);
$pret_vanzare_final=$pret_fara_tva+$tva_col_unitar;
$valoare_vanzare=$pret_fara_tva*$cant_prim;
$valoare_vanzare_cu_tva=$pret_vanzare_final*$cant_prim;


	 }
	 else{
	     $proc_ad=0;
		  $adaos_unitar=0;
	 $v_adaos=0;
	 $tva_neex=0;
	 $pret_vanzare=0;
	 $valoare_vanzare=0;
	 $valoare_vanzare_cu_tva=0;
	 }
	 

	 
$psql3 = "SELECT id_achiz from $tabel_final_achizitii where nr_nir='$nrnir' and cod_p='$produs' and pret_achiz='$pret_achiz';";    
$pstmt3 = $pdo->prepare($psql3);  
$pstmt3->execute(); 
$prodcount=$pstmt3->rowCount();

if($prodcount==0){
	 
	 
$psql = "insert into $tabel_final_achizitii(nr_nir,cod_p,cantitate_doc,cantitate_prim,pret_achiz,valoare_fara_tva,cota_tva,tva_ded,proc_adaos,ad_unit,valoare_adaos,tva_neex,pret_vanzare,valoare_vanzare,valoare_vanzare_cu_tva,data,pret_vanzare_fara_tva,tva_colect_unit) values('$nrnir','$produs','$cant_doc','$cant_prim','$pret_achiz','$v_ftva','$cota_tva','$tva_ded','$proc_ad','$adaos_unitar','$v_adaos','$tva_neex','$pret_vanzare','$valoare_vanzare','$valoare_vanzare_cu_tva','$data_nir','$pret_fara_tva','$tva_col_unitar');";    
}

else{
    
    $psql = "update $tabel_final_achizitii set cantitate_doc=cantitate_doc+'$cant_doc',cantitate_prim=cantitate_prim+'$cant_prim',valoare_fara_tva=valoare_fara_tva+'$v_ftva',tva_ded=tva_ded+'$tva_ded',valoare_adaos=valoare_adaos+'$v_adaos',tva_neex=tva_neex+'$tva_neex',valoare_vanzare=valoare_vanzare+'$valoare_vanzare',valoare_vanzare_cu_tva=valoare_vanzare_cu_tva+'$valoare_vanzare_cu_tva' where nr_nir='$nrnir' and cod_p='$produs' and pret_achiz='$pret_achiz';
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
 

?>   </td><td colspan='2'><input class='btn btn-primary btn-block' type="submit" name="adaug_produs" value="Adauga produs"></td>
 </tr><tr>
    <td><h6>Cantitate de Primit:</h6></td>
<td><input  class='form-control' type="number"  name="cant_doc"  step="0.00001" value="1" ></td>
<td><h6>Cantitate Primita:</h6></td>
<td><input class='form-control' type="number" name="cant_prim"  step="0.00001" value="1" ></td></tr>
  <tr id="txtHint">
      

  </tr>
 </table>

 </form>
 <script>
$(document).ready(function(){ $(".js-example-basic-single").select2();});
</script>
<table class='table table-bordered'>
  <tr>
    <th width="15%"  rowspan="2">Denumire</th>
    <th  rowspan="2">U.M.</th>
    <th  rowspan="2">Cant.<br>doc.</th>
    <th  rowspan="2">Cant.<br>prim.</th>
    <th  colspan="4">Achizitie</th>
    <th  colspan="3">Adaos</th>
        <th width="15%"  rowspan="2">Pret vanzare fara TVA</th>
    <th  colspan="3">TVA vanzare</th>
    <th  colspan="2">Valoare vanzare cu TVA</th>
    <th  rowspan="2">Elimina</th>
  </tr>
  <tr>
    <td ><b>P.U.<br>(fara TVA)</b></td>
    <td ><b>Valoare<br>(fara TVA)</b></td>
    <td ><b>TVA</b></td>
    <td ><b>Total</b></td>

    <td ><b>%</b></td>
    <td ><b>Unitar</b></td>
    <td ><b>Total</b></td>
        <td ><b>Tva adaos</b></td>

    <td ><b>Unitar</b></td>
    <td ><b>Total</b></td>
    <td ><b>Unitar</b></td>
    <td ><b>Total</b></td>

  </tr>
  <tr>
      
      <style>  b{font-size:1em;}</style>
    <td width="15%" ><b>1</b></td>
    <td ><b>2</b></td>
    <td ><b>3</b></td>
    <td ><b>4</b></td>
    <td ><b>5</b></td>
        <td ><b>6</b></td>

    <td ><b>7</b></td>
    <td ><b>8 = 7+6</b></td>
    <td ><b>9</b></td>
    <td ><b>10</b></td>
    <td ><b>11 = 10 x 4</b></td>
    <td ><b>12 = 10 + 5</b></td>
     <td ><b>13 = 11 x cota tva</b></td>
    <td ><b>14 = 12 x cota tva</b></td>
     <td ><b>15 = 14 x 4</b></td>
     <td ><b>16 = 12 + 14</b></td>
<td ><b>17 = 16 x 4</b></td>


  </tr>
  
  <?php
//afisare randuri NIR
$nr_nir=$_SESSION['nr_nir'];

$nir_sql = "SELECT cote_tva.cota,$tabel_final_achizitii.pret_vanzare_fara_tva,$tabel_final_achizitii.cod_p,$tabel_final_nomenclator.den_p,$tabel_final_nomenclator.um,$tabel_final_nomenclator.proc_adaos,$tabel_final_achizitii.cantitate_doc,$tabel_final_nomenclator.pret_vanzare,$tabel_final_achizitii.cantitate_prim,$tabel_final_achizitii.pret_achiz,$tabel_final_achizitii.valoare_fara_tva,$tabel_final_achizitii.tva_ded,$tabel_final_achizitii.proc_adaos,$tabel_final_achizitii.ad_unit,$tabel_final_achizitii.valoare_adaos,$tabel_final_achizitii.tva_neex,$tabel_final_achizitii.pret_vanzare,$tabel_final_achizitii.valoare_vanzare,$tabel_final_achizitii.valoare_vanzare_cu_tva,$tabel_final_achizitii.id_achiz,$tabel_final_achizitii.tva_colect_unit from $tabel_final_nomenclator inner join $tabel_final_achizitii on $tabel_final_achizitii.cod_p=$tabel_final_nomenclator.cod_p INNER JOIN cote_tva on $tabel_final_nomenclator.cota_tva=cote_tva.id where nr_nir='$nr_nir';";    
$nir_stmt = $pdo->prepare($nir_sql);  
$nir_stmt->execute(); 

while ($row = $nir_stmt->fetch(PDO::FETCH_ASSOC)){ 
    $pret_vanzare=$row['pret_vanzare'];
$codul_produsului=$row['cod_p'];
$produs=$row['den_p'];
$um=$row['um'];
$cantitate_doc=$row['cantitate_doc'];
$cantitate_prim=$row['cantitate_prim'];
$pret_achiz=$row['pret_achiz'];
$valoare_fara_tva=$row['valoare_fara_tva'];
$c_tva=$row['cota'];
$tva_ded=$row['tva_ded'];

$total=$valoare_fara_tva+$tva_ded;
$pret_fara_tva=$row['pret_vanzare_fara_tva'];
$ad_unit=$row['ad_unit'];
$valoare_adaos=$row['valoare_adaos'];
$proc_adaos=$row['proc_adaos'];
$tva_neex=$row['tva_neex'];
$tva_col_unitar=$row['tva_colect_unit'];
$tva_col_total=$tva_col_unitar*$cantitate_prim;
$valoare_vanzare=$row['valoare_vanzare'];
$id_achz=$row['id_achiz'];
$valoare_vanzare_c_tva=$row['valoare_vanzare_cu_tva'];
$pret_vanzare_final=$pret_fara_tva+$tva_col_unitar;
$total_vanzare_final=$pret_vanzare_final*$cantitate_prim;
  echo "
   <tr>
    <td class='tabel'>$produs</td>
    <td class='tabel'>$um</td>
<td>$cantitate_doc</td>     
<td >$cantitate_prim</td>    
    <td class='tabel'>$pret_achiz</td>
    <td class='tabel'>$valoare_fara_tva</td>
    	 <td class='tabel'>$tva_ded</td>
    	     	 <td class='tabel'>$total</td>
    <td class='tabel'>$proc_adaos</td>
    <td class='tabel'>$ad_unit</td>
    <td class='tabel'>$valoare_adaos</td>
        <td class='tabel'>$pret_fara_tva</td>
    <td class='tabel'>$tva_neex</td>
    <td class='tabel'>$tva_col_unitar</td>
	<td class='tabel'>$tva_col_total</td>
		<td class='tabel'>$pret_vanzare_final</td>
	<td class='tabel'>$total_vanzare_final</td>

	
	<td><form method='post'><input class='btn btn-primary btn-block' type='submit' value='Sterge' name='$id_achz'></form></td>

  </tr>
  
  
  
  " 
  
  ;
  if (isset($_POST[$id_achz])) {
					
					$sterg_sql="SELECT * FROM $tabel_final_achizitii where $tabel_final_achizitii.id_achiz = '$id_achz' ";
					$sterg_nir_stmt = $pdo->prepare($sterg_sql);  
$sterg_nir_stmt->execute(); 

while ($row = $sterg_nir_stmt->fetch(PDO::FETCH_ASSOC)){ 

$c_p=$row['cod_p'];
$cant_achz=$row['cantitate_prim'];
}
					
					$sql="DELETE FROM $tabel_final_achizitii WHERE $tabel_final_achizitii.id_achiz = '$id_achz';";
$pdo->exec($sql) or die(print_r($pdo->errorInfo(), true));

			printf("<script>location.href='nir.php'</script>");
				}
  $nr_crt1++;}
  
  
 
  ?>
 <th  colspan="5">Total</th>  
 <th  >
 <?php 
 $nr_nir=$_SESSION['nr_nir'];
 $nir_tot_sql = "SELECT sum($tabel_final_achizitii.valoare_fara_tva) as a from $tabel_final_achizitii  where nr_nir='$nr_nir'; ";    
$nir_tot_stmt = $pdo->prepare($nir_tot_sql);  
$nir_tot_stmt->execute();

while ($row = $nir_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$valoare_nir_f_tva=$row['a'];
}
  echo $valoare_nir_f_tva ?>
  </th>  
  <th  >
 <?php 
 $nr_nir=$_SESSION['nr_nir'];
 $nir_tot_sql = "SELECT sum($tabel_final_achizitii.tva_ded) as d from $tabel_final_achizitii  where nr_nir='$nr_nir'; ";    
$nir_tot_stmt = $pdo->prepare($nir_tot_sql);  
$nir_tot_stmt->execute();

while ($row = $nir_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_tva_ded=$row['d'];
}
  echo $total_tva_ded ?>
  </th> 
     <th  ><?php $t=$total_tva_ded+$valoare_nir_f_tva;
     echo $t;?></th>  
   <th  >-</th>  
   <th  >-</th>  

  <th  >
 <?php 
 $nr_nir=$_SESSION['nr_nir'];
 $nir_tot_sql = "SELECT sum($tabel_final_achizitii.valoare_adaos) as b from $tabel_final_achizitii  where nr_nir='$nr_nir'; ";    
$nir_tot_stmt = $pdo->prepare($nir_tot_sql);  
$nir_tot_stmt->execute();

while ($row = $nir_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$valoare_adaos_nir=$row['b'];
}
  echo round($valoare_adaos_nir,2); ?>
  </th> 
   <th  >-</th>  

  <th  >
 <?php 
 $nr_nir=$_SESSION['nr_nir'];
 $nir_tot_sql = "SELECT sum($tabel_final_achizitii.tva_neex) as e from $tabel_final_achizitii  where nr_nir='$nr_nir'; ";    
$nir_tot_stmt = $pdo->prepare($nir_tot_sql);  
$nir_tot_stmt->execute();

while ($row = $nir_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_tva_neex=$row['e'];
}
  echo round($total_tva_neex,2);
  


  ?>
  </th> 
  
  <th>
   -
      
  </th>
  
   <th  >
           <?php 
 $nr_nir=$_SESSION['nr_nir'];
 $nir_tot_sql = "SELECT sum($tabel_final_achizitii.tva_colect_unit*$tabel_final_achizitii.cantitate_prim) as gg from $tabel_final_achizitii  where nr_nir='$nr_nir'; ";    
$nir_tot_stmt = $pdo->prepare($nir_tot_sql);  
$nir_tot_stmt->execute();

while ($row = $nir_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$tva_colect_unit=$row['gg'];
}
  echo round($tva_colect_unit,2);
  


  ?>
       </th>  


  <th  >
 -
  </th> 
  
  

 
  
  <th  >
 <?php 
 $nr_nir=$_SESSION['nr_nir'];
 $nir_tot_sql = "SELECT sum($tabel_final_achizitii.valoare_vanzare_cu_tva) as f from $tabel_final_achizitii  where nr_nir='$nr_nir'; ";    
$nir_tot_stmt = $pdo->prepare($nir_tot_sql);  
$nir_tot_stmt->execute();

while ($row = $nir_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_val_vz_cu_tva=$row['f'];
}
  echo round($total_val_vz_cu_tva,2);
  
if(isset($_POST['salvare_nir'])){

$fin_sql = "update $tabel_final_nir SET ad_com_total='$valoare_adaos_nir',tva_neex_ad_com='$total_tva_neex',tva_ded='$total_tva_ded',val_nir_ftva='$valoare_nir_f_tva',valoare_totala='$valoare_nir_vz',valoare_totala_cu_tva='$total_val_vz_cu_tva' WHERE nr_nir='$nr_nir';";    
			
			
	 
	try{
$pdo->exec($fin_sql);   
    // afiseaza un mesaj de succes 
			printf("<script>location.href='note_de_receptie.php'</script>");
}catch(PDOException $e)
    {
    echo $fin_sql . "<br>" . $e->getMessage();
    } 
 
	
}

if(isset($_POST['finaliz_nir'])){
$admin_em=$_SESSION['adminloggedin'];

$fin_sql = "update $tabel_final_nir SET gestionar='$admin_em',ad_com_total='$valoare_adaos_nir',tva_neex_ad_com='$total_tva_neex',tva_ded='$total_tva_ded',val_nir_ftva='$valoare_nir_f_tva',valoare_totala='$valoare_nir_vz',valoare_totala_cu_tva='$total_val_vz_cu_tva',status='F' WHERE nr_nir='$nr_nir';";    
			
			
	 
	try{
$pdo->exec($fin_sql) or die(print_r($pdo->errorInfo(), true));   
			printf("<script>location.href='note_de_receptie.php'</script>");

}catch(PDOException $e)
    {
    echo $fin_sql . "<br>" . $e->getMessage();
    } 
 
	 $nr_nir=$_SESSION['nr_nir'];

$misc_sql = "SELECT $tabel_final_achizitii.cod_p,$tabel_final_achizitii.tva_ded,$tabel_final_achizitii.pret_achiz,$tabel_final_achizitii.pret_vanzare,$tabel_final_achizitii.cantitate_prim from $tabel_final_achizitii where nr_nir='$nr_nir';";    
$misc_stmt = $pdo->prepare($misc_sql);  
$misc_stmt->execute(); 

while ($row = $misc_stmt->fetch(PDO::FETCH_ASSOC)){
    	date_default_timezone_set('UTC');
         $prod=$row['cod_p'];
     $qt=$row['cantitate_prim'];
          $pu=$row['pret_achiz'];
          $pretul_vanzare=$row['pret_vanzare'];
          
$tva_ded=$row['tva_ded'];
$tva_ded_unit=$row['tva_ded']/$qt;
$pretul_vanzare_mp=$row['pret_achiz'];



$afla_gestiune_sql = "SELECT $tabel_final_nomenclator.gestiune from $tabel_final_nomenclator where cod_p='$prod';";    
$afla_gestiune_stmt = $pdo->prepare($afla_gestiune_sql);  
$afla_gestiune_stmt->execute(); 

while ($frow = $afla_gestiune_stmt->fetch(PDO::FETCH_ASSOC)){
    $gestiune=$frow['gestiune'];
}


   

if($gestiune=='MR')
{
    
    $iessql = "insert into $tabel_final_miscari(data,cod_p,cantitate_misc,tip_miscare,fel_doc,nr_doc,pret_vanzare,pu,ramas) values('$data_nir','$prod','$qt','I','NIR','$nr_nir','$pretul_vanzare',$pu,'$qt');"; 
    
}

else{
     $iessql = "insert into $tabel_final_miscari(data,cod_p,cantitate_misc,tip_miscare,fel_doc,nr_doc,pu,pret_vanzare,ramas) values('$data_nir','$prod','$qt','I','NIR','$nr_nir','$pu','$pretul_vanzare_mp','$qt');"; 
}
  

	 
	try{
$pdo->exec($iessql) or die(print_r($pdo->errorInfo(), true));   

}catch(PDOException $e)
    {
    echo $iessql . "<br>" . $e->getMessage();
    } 
    
    
        $stoc_sql = "update $tabel_final_stoc set cantitate=cantitate+'$qt' where cod_p=$prod;";   

	 
	try{
$pdo->exec($stoc_sql) or die(print_r($pdo->errorInfo(), true));   

}catch(PDOException $e)
    {
    echo $stoc_sql . "<br>" . $e->getMessage();
    } 
}
	
	
}

  ?>
  </th>
  <th  >-</th>  

  
  
</table>

 <form method="post">
   
<input  class='btn btn-primary btn-block' type='submit' name='salvare_nir' value='Salvare NIR'> <input  class='btn btn-primary btn-block' type="submit" name="finaliz_nir" value="Finalizare NIR">

 </form>
 <br>
<?php 
$nr_nir=$_SESSION['nr_nir'];   
$anul_nir_sql = $pdo->prepare("SELECT id_achiz from $tabel_final_achizitii where nr_nir='$nr_nir'; ");  
$anul_nir_sql->execute();
	  $count=$anul_nir_sql->rowCount();
	 
	
	 if($count == 0) {
         
         
         
        echo "<form method='post'>
 <input class='btn btn-primary btn-block' type='submit' name='anulare_nir' value='Anulare NIR'>
 </form>" ;
      } else{
		  echo "<form method='post'>
 <input class='btn btn-primary btn-block' type='submit' name='anulare_nir' value='Pentru a anula NIR-ul stergeti toate produsele' disabled>
 </form>" ;
		  }
	  
	   if(isset($_POST['anulare_nir'])){
$sql="DELETE from $tabel_final_nir WHERE $tabel_final_nir.nr_nir = '$nr_nir';
					";
$pdo->exec($sql) or die(print_r($pdo->errorInfo(), true));

			printf("<script>location.href='index.php'</script>");

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
</script>