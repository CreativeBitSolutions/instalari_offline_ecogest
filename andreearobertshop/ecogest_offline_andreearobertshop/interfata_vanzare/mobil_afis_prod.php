<table class="table table-dark" style='margin:0;width:100%;'>
  <div class='row'>
  <?php
  include('session.php');
$nr_bon=$_GET['bonul'];
  
  
  
  $date_firma = "SELECT mod_listare,vanzare_sub_stoc,ajustare_adaos from $tabel_final_date_firma";    
$date_firma_stmt = $pdo->prepare($date_firma);  
$date_firma_stmt->execute(); 
while ($row = $date_firma_stmt->fetch(PDO::FETCH_ASSOC)){ 

$_SESSION['vanzare_sub_stoc']=$row['vanzare_sub_stoc'];
$_SESSION['mod_listare']=$row['mod_listare'];
$_SESSION['ajustare_adaos']=$row['ajustare_adaos'];
}

//afisare randuri NIR
$f_sql = "SELECT $tabel_final_nomenclator.departament,$tabel_final_det_note.preparat,$tabel_final_det_note.pachet,$tabel_final_det_note.discount,$tabel_final_det_note.cod_p,$tabel_final_nomenclator.den_p,cote_tva.cota,$tabel_final_nomenclator.um,$tabel_final_det_note.cantitate,$tabel_final_det_note.tva_col,$tabel_final_det_note.pret_vanzare,$tabel_final_det_note.valoare_vanzare,$tabel_final_det_note.valoare_vanzare_cu_tva,$tabel_final_det_note.id_vanz from $tabel_final_nomenclator inner join $tabel_final_det_note on $tabel_final_det_note.cod_p=$tabel_final_nomenclator.cod_p inner join cote_tva on $tabel_final_nomenclator.cota_tva=cote_tva.id where nr_bon='$nr_bon' order by $tabel_final_det_note.id_vanz,$tabel_final_nomenclator.den_p;";    
$f_stmt = $pdo->prepare($f_sql);  
$f_stmt->execute(); 
$p_count=$f_stmt->rowCount(); 

echo "  
  <div class='input-row'><thead>
<tbody class='tbody lista_prod'  style='height:30em;'> ";
if($p_count!=0){ echo "<tr>
<th style='width:100%'>Produs</th>
<th>Cantitate</th>
<th><div>Valoare</div></th>
<th style='width:20px;'>Actiuni</th>
<th style='width:20px;'>Stare</th>



</thead>";}

else{
    
    echo "<h3 style='padding-left:20px;'>Bine ați venit!!<br/> Adăugați produsele dorite apăsând butonul cu prețul produsului din partea dreaptă a ecranului <br/> și apăsați pe butonul \"Trimite Comanda\" pentru a o trimite cofetarului.<br/>Click pe imaginea prodsului produsului sau pe <button style='display:inline-block;width:5%;height:auto;' disabled class='btn btn-primary btn-block' >  <i class='fa fa-question' aria-hidden='true'></i></button> pentru mai multe detalii.</h3>";
}
while ($row = $f_stmt->fetch(PDO::FETCH_ASSOC)){
    $departament=$row['departament'];
$codul_produsului=$row['cod_p'];
$produs=$row['den_p'];
$cantitate=$row['cantitate'];
$tva_col=$row['tva_col'];
$pret_vanzare=$row['pret_vanzare'];
$valoare_vanzare=$row['valoare_vanzare'];
$id_vanz=$row['id_vanz'];

$valoare_vanzare_c_tva=round(($row['valoare_vanzare_cu_tva'])*100)/100;
$cota=$row['cota'];
$d=$row['discount'];
$pch=$row['pachet'];
$prep=$row['preparat'];
  echo "
   <tr>
    <td style='width:100%;height:auto; white-space: normal;'><div>$produs</div></td>
    <td ><button style='display:inline-block;' name='$id_vanz' value='$codul_produsului' data-value='$cantitate' class='btn btn-primary btn-block modif_cant' title='Click pentru modificarea cantitatii'>X  $cantitate</button></td>
	<td><div>$valoare_vanzare_c_tva</div></td>
	<td><button style='background-color:white;color:red;' class='btn btn-primary btn-block sterge_prod' title='Click pentru a sterge' value='$id_vanz' data-value='$nr_bon' type='button'>X</button></td>
<td>";
if($departament=='BUC'){
$prep_sql = "SELECT sum(cantitate) as cant_prep FROM $tabel_final_de_listat_buc where id_vanz='$id_vanz' and preparat=1;";    
$prep_stmt= $pdo->prepare($prep_sql);  
$prep_stmt->execute(); 
while ($row = $prep_stmt->fetch(PDO::FETCH_ASSOC)){ 
$ct_prep=$row['cant_prep'];
    
}
if($ct_prep==$cantitate){echo "&#9989;";} else {echo $ct_prep."/".$cantitate;} 

}
elseif($departament=='BAR'){
$prep_sql = "SELECT sum(cantitate) as cant_prep FROM $tabel_final_de_listat_bar where id_vanz='$id_vanz' and preparat=1;";    
$prep_stmt= $pdo->prepare($prep_sql);  
$prep_stmt->execute(); 
while ($row = $prep_stmt->fetch(PDO::FETCH_ASSOC)){ 
$ct_prep=$row['cant_prep'];
    
}
if($ct_prep==$cantitate){echo "&#9989;";} else {echo $ct_prep."/".$cantitate;} 

}
echo"</td>
  </tr>
  
  
  
  " 
  
  ;
  }
  

 
  ?></tbody></div>  
  
  
</div></table>

		  <script>
		      
		      (function () {
$('.modif_cant').on('click',function(){$('#Cantitate').modal('show');var produs_modif=$(this).val();var cantitate_curenta=this.getAttribute("data-value");var idvanzare=this.getAttribute("name");$('[name=produs_modif_cant]').val(produs_modif);$('[name=cantitate_noua]').val(cantitate_curenta);$('[name=cantitate_veche]').val(cantitate_curenta);$('[name=idvz]').val(idvanzare)});


})();
		  </script>




