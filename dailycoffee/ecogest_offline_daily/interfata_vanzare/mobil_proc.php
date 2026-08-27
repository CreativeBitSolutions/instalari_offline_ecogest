<?php
include('session.php');
$cod_p=$_GET['prod'];
$nr_bon=$_GET['bonul'];

	date_default_timezone_set('UTC+2');
		date_default_timezone_set("Europe/Bucharest");

$ora_bon = date("H:i:s", strtotime('+0 hours'));
 $data_bon = date("Y-m-d", strtotime('+0 hours'));
 $pach=0;
   
	 $produs=$cod_p;
	 $psql2 = "SELECT $tabel_final_nomenclator.gestiune,$tabel_final_nomenclator.pret_vanzare,$tabel_final_nomenclator.cod_p,$tabel_final_nomenclator.proc_discount,$tabel_final_nomenclator.um,$tabel_final_nomenclator.den_p,$tabel_final_nomenclator.pret,cote_tva.cota,$tabel_final_nomenclator.proc_adaos,$tabel_final_stoc.cantitate from $tabel_final_nomenclator INNER JOIN cote_tva on $tabel_final_nomenclator.cota_tva=cote_tva.id inner join $tabel_final_stoc on $tabel_final_stoc.cod_p=$tabel_final_nomenclator.cod_p where $tabel_final_nomenclator.cod_p='$produs';";    
$pstmt2 = $pdo->prepare($psql2);  
$pstmt2->execute(); 


while ($row = $pstmt2->fetch(PDO::FETCH_ASSOC)){ 
                      $den_prod=$row['den_p'];
							   $cota_tva=$row['cota'];
			                   $pret_vz=$row['pret_vanzare'];

							   if($cota_tva==9 && $masaa_fin!=9999 ){
							       			if($_SESSION['ajustare_adaos']==0){

							       $cota_tva=5;
							   }
							   }
							   	 				
							   $stoc=$row['cantitate'];             
			$um=$row['um'];
			$proc_d=$row['proc_discount'];
			 $gestiune=$row['gestiune'];

}
	 
	 $cantitate=1;
	 $pret_vanzare=$pret_vz;
	 
	 $valoare_vanzare_cu_tva=$pret_vanzare*$cantitate;
	 	 $valoare_vanzare=$valoare_vanzare_cu_tva;
	 $tva_col=round(($valoare_vanzare_cu_tva*$cota_tva/(100+$cota_tva)),3);
	 

	 $discount=$valoare_vanzare_cu_tva*$proc_d/100;
	 // aici stergi daca ai probleme
$discount=round($discount,2);



$psql3 = "SELECT pachet from $tabel_final_det_note where nr_bon='$nr_bon' and cod_p='$produs' and pachet='$pach';";    
$pstmt3 = $pdo->prepare($psql3);  
$pstmt3->execute(); 
$prodcount=$pstmt3->rowCount();


if($gestiune!="PF"){
if($prodcount==0){

$psql = "insert into $tabel_final_det_note(nr_bon,cod_p,cantitate,tva_col,pret_vanzare,valoare_vanzare,valoare_vanzare_cu_tva,discount,pachet,data,ora) values('$nr_bon','$produs','$cantitate','$tva_col','$pret_vanzare','$valoare_vanzare','$valoare_vanzare_cu_tva','$discount','$pach','$data_bon','$ora_bon');
update $tabel_final_stoc set cantitate=cantitate-'$cantitate' where cod_p='$produs'; ";   }

elseif($prodcount>0){
    
 
while ($row = $pstmt3->fetch(PDO::FETCH_ASSOC)){ 

$pachet=$row['pachet'];

}

if($pachet==$pach){
    $psql = "update $tabel_final_det_note set cantitate=cantitate+'$cantitate',t_list='0',tva_col=tva_col+'$tva_col',valoare_vanzare=valoare_vanzare+'$valoare_vanzare',valoare_vanzare_cu_tva=valoare_vanzare_cu_tva+'$valoare_vanzare_cu_tva',discount=discount+'$discount' where nr_bon='$nr_bon' and cod_p='$produs' and pachet='$pachet';
update $tabel_final_stoc set cantitate=cantitate-'$cantitate' where cod_p='$produs'; "; }

elseif($pachet!=$pach){
    $psql = "insert into $tabel_final_det_note(nr_bon,cod_p,cantitate,tva_col,pret_vanzare,valoare_vanzare,valoare_vanzare_cu_tva,discount,pachet,data,ora) values('$nr_bon','$produs','$cantitate','$tva_col','$pret_vanzare','$valoare_vanzare','$valoare_vanzare_cu_tva','$discount','$pach','$data_bon','$ora_bon');
update $tabel_final_stoc set cantitate=cantitate-'$cantitate' where cod_p='$produs'; "; 

}
     
}

	try{
$pdo->exec($psql) or die(print_r($pdo->errorInfo(), true));  



}catch(PDOException $e)
    {
    echo $psql . "<br>" . $e->getMessage();
    } 
}

elseif($gestiune="PF"){
    if($prodcount==0){

    $psql = "insert into $tabel_final_det_note(nr_bon,cod_p,cantitate,tva_col,pret_vanzare,valoare_vanzare,valoare_vanzare_cu_tva,discount,pachet,data,ora) values('$nr_bon','$produs','$cantitate','$tva_col','$pret_vanzare','$valoare_vanzare','$valoare_vanzare_cu_tva','$discount','$pach','$data_bon','$ora_bon');";   }

elseif($prodcount>0){
    
    while ($row = $pstmt3->fetch(PDO::FETCH_ASSOC)){ 

$pachet=$row['pachet'];

}

    $psql = "update $tabel_final_det_note set cantitate=cantitate+'$cantitate',t_list='0',tva_col=tva_col+'$tva_col',valoare_vanzare=valoare_vanzare+'$valoare_vanzare',valoare_vanzare_cu_tva=valoare_vanzare_cu_tva+'$valoare_vanzare_cu_tva',discount=discount+'$discount' where nr_bon='$nr_bon' and cod_p='$produs' and pachet='$pachet'; "; 
    
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
    





 
 }
 

?>
<script>
    
var timerr = null;

function goAway3() {
    clearTimeout(timerr);
    timerr = setTimeout(function() {
            var bon = "<?php echo $nr_bon; ?>";
              $("#one").load("tableta_afis_prod.php?" + $.param({
        bonul: bon}));
    }, 50);
}


goAway3();  // start the first timer off
</script>