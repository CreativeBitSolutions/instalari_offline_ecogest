<?php

include('session.php');
   $nrr_bon = $_GET['nota'];


	date_default_timezone_set('UTC+2');
		date_default_timezone_set("Europe/Bucharest");

$ora_com_bon = date("H:i:s", strtotime('+0 hours'));
 $data_com_bon = date("Y-m-d", strtotime('+0 hours'));
 
$caut_prod_nelist_sql = "select $tabel_final_det_note.id_vanz,$tabel_final_det_note.t_list,$tabel_final_det_note.cod_p,$tabel_final_det_note.cantitate,$tabel_final_det_note.pachet from $tabel_final_det_note INNER JOIN $tabel_final_nomenclator on $tabel_final_det_note.cod_p=$tabel_final_nomenclator.cod_p where $tabel_final_det_note.nr_bon='$nrr_bon' and $tabel_final_nomenclator.departament='BUC'"; 
    $caut_prod_nelist_stmt = $pdo->prepare($caut_prod_nelist_sql);  
$caut_prod_nelist_stmt->execute(); 
while ($row = $caut_prod_nelist_stmt->fetch(PDO::FETCH_ASSOC)){ 
    $codul_prod=$row['cod_p'];
    $quant=$row['cantitate'];
	$la_pach=$row['pachet'];
    $t_list=$row['t_list'];
    $id_v=$row['id_vanz'];
$caut_pro_sql = "select sum(cantitate) as cant_ex FROM $tabel_final_de_listat_buc where id_vanz='$id_v' and cod_p='$codul_prod'"; 
    $caut_pro_stmt = $pdo->prepare($caut_pro_sql);  
$caut_pro_stmt->execute(); 
while ($row = $caut_pro_stmt->fetch(PDO::FETCH_ASSOC)){ 

$cant_ex=$row['cant_ex'];

}
    $quant=$quant-$cant_ex;
    if($t_list==0){

$upd_list_sql = "insert into $tabel_final_de_listat_buc(id_vanz,nr_bon,cod_p,cantitate,pachet,data_com,ora_com) values('$id_v','$nrr_bon','$codul_prod','$quant','$la_pach','$data_com_bon','$ora_com_bon')"; 
    $upd_list_stmt = $pdo->prepare($upd_list_sql);  
$upd_list_stmt->execute();

    $update_not_sql = "update $tabel_final_det_note set t_list='1' where id_vanz='$id_v'; "; 
    $update_not_stmt = $pdo->prepare($update_not_sql);  
$update_not_stmt->execute(); 
}

}
// trim buc end



//trim bar
   $nrr_bon = $_GET['nota'];


$caut_prod_nelist_sql2 = "select $tabel_final_det_note.id_vanz,$tabel_final_det_note.t_list,$tabel_final_det_note.cod_p,$tabel_final_det_note.cantitate,$tabel_final_det_note.pachet from $tabel_final_det_note INNER JOIN $tabel_final_nomenclator on $tabel_final_det_note.cod_p=$tabel_final_nomenclator.cod_p where $tabel_final_det_note.nr_bon='$nrr_bon' and $tabel_final_nomenclator.departament='BAR'"; 


    $caut_prod_nelist_stmt2 = $pdo->prepare($caut_prod_nelist_sql2);  
$caut_prod_nelist_stmt2->execute(); 
while ($row = $caut_prod_nelist_stmt2->fetch(PDO::FETCH_ASSOC)){ 
    $codul_prod=$row['cod_p'];
    $quant=$row['cantitate'];
	$la_pach=$row['pachet'];
    $t_list=$row['t_list'];
    $id_v=$row['id_vanz'];
$caut_pro_sql2= "select sum(cantitate) as cant_ex FROM $tabel_final_de_listat_bar where id_vanz='$id_v' and cod_p='$codul_prod'"; 
    $caut_pro_stmt2 = $pdo->prepare($caut_pro_sql2);  
$caut_pro_stmt2->execute(); 


while ($row = $caut_pro_stmt2->fetch(PDO::FETCH_ASSOC)){ 

$cant_ex=$row['cant_ex'];

}
    $quant=$quant-$cant_ex;
    

    if($t_list==0){

$upd_list_sql2 = "insert into $tabel_final_de_listat_bar(id_vanz,nr_bon,cod_p,cantitate,pachet,data_com,ora_com) values('$id_v','$nrr_bon','$codul_prod','$quant','$la_pach','$data_com_bon','$ora_com_bon')"; 
    $upd_list_stmt2 = $pdo->prepare($upd_list_sql2);  
$upd_list_stmt2->execute();

    $update_not_sql2 = "update $tabel_final_det_note set t_list='1' where id_vanz='$id_v'; "; 
    $update_not_stmt2 = $pdo->prepare($update_not_sql2);  
$update_not_stmt2->execute(); 
}

}


		    	printf("<script>location.href='mobil.php'</script>");




?>