<?php
include('session.php');
	
   	     $nr_bon=$_SESSION['nr_bon'];

	         $disc_tot_sql767 = "SELECT sum($tabel_final_det_note.cantitate) as numar_cant_prod from $tabel_final_det_note inner join $tabel_final_nomenclator on $tabel_final_nomenclator.cod_p=$tabel_final_det_note.cod_p inner join $tabel_final_categorii on $tabel_final_categorii.id_categorie=$tabel_final_nomenclator.cod_categ where $tabel_final_det_note.nr_bon='$nr_bon' and $tabel_final_categorii.den_categ='BUCATARIE'; ";    
$disc_tot_stmt767 = $pdo->prepare($disc_tot_sql767);  
$disc_tot_stmt767->execute();
while ($urow = $disc_tot_stmt767->fetch(PDO::FETCH_ASSOC)){
	$numar_cant_prod=$urow['numar_cant_prod'];
}

$de_cate_ori_intra = floor($numar_cant_prod/4);


for ($x = 0; $x < $de_cate_ori_intra; $x++) {

   $psql3 = "SELECT * from $tabel_final_det_note where nr_bon='$nr_bon' and valoare_vanzare!=0 and valoare_vanzare_cu_tva-discount!=0 order by pret_vanzare ASC LIMIT 1;";    
$pstmt3 = $pdo->prepare($psql3);  
$pstmt3->execute(); 

while ($row = $pstmt3->fetch(PDO::FETCH_ASSOC)){ 
    
    $id_vanz=$row['id_vanz'];
    $pret_vanzare=$row['pret_vanzare'];
    

}

 $updn_sql="update $tabel_final_det_note set discount=discount+$pret_vanzare where id_vanz='$id_vanz' and discount<valoare_vanzare_cu_tva"; 	 
    $updnstmt = $pdo->prepare($updn_sql);  
$updnstmt->execute(); 

}


    printf("<script>location.href='vanzare_magazin.php'</script>");

?>