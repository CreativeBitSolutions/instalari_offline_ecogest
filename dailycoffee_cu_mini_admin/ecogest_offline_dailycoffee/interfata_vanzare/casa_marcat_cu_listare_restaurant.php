<?php 
include('session.php');
$cr= chr(13) . chr(10);
$K="K,1,______,_,__;";
$H="H,1,______,_,__;";
$T="T,1,______,_,__;";
$T2="T,1,______,_,__;";
$F="F,1,______,_,__;";
$nr_bon=$_SESSION['nr_bon'];
$f_sql = "SELECT $tabel_final_det_note.pachet,$tabel_final_det_note.discount,$tabel_final_det_note.cod_p,$tabel_final_nomenclator.pret,$tabel_final_nomenclator.proc_discount,$tabel_final_nomenclator.proc_adaos,$tabel_final_nomenclator.den_p,cote_tva.cota,cote_tva.dep_casa,$tabel_final_nomenclator.um,$tabel_final_det_note.cantitate,$tabel_final_det_note.pret_vanzare,$tabel_final_det_note.id_vanz from $tabel_final_nomenclator inner join $tabel_final_det_note on $tabel_final_det_note.cod_p=$tabel_final_nomenclator.cod_p inner join cote_tva on $tabel_final_nomenclator.cota_tva=cote_tva.id where nr_bon='$nr_bon';";    
$f_stmt = $pdo->prepare($f_sql);  
$f_stmt->execute();
if($_SESSION['cif_client']!=''){
    $K.=$_SESSION['cif_client'];
    $myBuffer=$K.$cr.$H.$cr;

}
else{
$myBuffer=$H.$cr;}
while ($row = $f_stmt->fetch(PDO::FETCH_ASSOC)){ 
    $pachet=$row['pachet'];
    $pret_achiz=$row['pret'];
	$dep_casa=$row['dep_casa'];

							   $cota_tva=$row['cota'];
							   $proc_ad=$row['proc_adaos'];
							   
							   if($cota_tva==9 && $pachet!=1 ){
							       	if($_SESSION['ajustare_adaos']==0){
							       $cota_tva=5;
							       	}
								   $dep_casa=3;

							   }
							   
							   
$um=$row['um'];
$produs=$row['den_p'];
				   if($cota_tva==9 && $pachet==1 ){
$produs.=" P";

}
$cantitate=$row['cantitate'];

	 $adaos_unitar=$pret_achiz*$proc_ad/100;
	 $v_adaos=$adaos_unitar*$cantitate;
	 $tva_neex=$v_adaos*$cota_tva/100;
	 $pret_vanzare=round(($pret_achiz+$adaos_unitar),2);
	 $tva_col=round(($pret_vanzare*$cota_tva/100),2);
	 $pret_vanzare=$pret_vanzare+$tva_col;
	 
	 
	 
			if($_SESSION['ajustare_adaos']==1){
	 					 
 if($cota_tva==9 && $masaa_fin!=9999 ){
							       $cota_tva=9;
					
							   }
							   
							   	 $adaos_unitar2=$pret_achiz*$proc_ad/100;
	 $v_adaos2=$adaos_unitar*$cantitate;
	 $tva_neex2=$v_adaos2*$cota_tva/100;
	 $pret_vanzare2=round(($pret_achiz+$adaos_unitar2),2);
	 $tva_col2=round(($pret_vanzare2*$cota_tva/100),2);
	 $pret_vanzare2=$pret_vanzare2+$tva_col;
	 
	
			      $dif_adaos=$pret_vanzare2-$pret_vanzare;
			      
			      $pret_vanzare=$pret_vanzare+$dif_adaos;
}
	 $discount=$row['discount'];
	 $discount=round(($discount/$cantitate),2);
$pret_vanzare_fin=round(($pret_vanzare-$discount),2);



$id_vanz=$row['id_vanz'];
$myBuffer.="S,1,______,_,__;"."$produs;$pret_vanzare_fin;$cantitate;1;1;$dep_casa;0;0;$um".$cr;

}

if($_SESSION['numerarprim']!=0 && $_SESSION['cardprim']==0){
$incasat=$_SESSION['numerarprim'];
    $T.='0;'.$incasat.';;;;';
    $myBuffer.=$T.$cr.$F;

    
}if($_SESSION['cardprim']!=0 && $_SESSION['numerarprim']==0){
    $incasat=$_SESSION['cardprim'];
    $T.='1;'.$incasat.';;;;';
$myBuffer.=$T.$cr.$F;

}
if($_SESSION['cardprim']!=0 && $_SESSION['numerarprim']!=0){
        $incasat_num=$_SESSION['numerarprim'];
    $incasat_card=$_SESSION['cardprim'];
        $T.='0;'.$incasat_num.';;;;';
    $T2.='1;'.$incasat_card.';;;;';
$myBuffer.=$T.$cr.$T2.$cr.$F;

}




if($_SESSION['total_tichete']!=0 && $_SESSION['rest_tichete']!=0){
        $incasat_num=$_SESSION['rest_tichete'];
    $incasat_tichete=$_SESSION['total_tichete'];
        $T.='3;'.$incasat_tichete.';;;;';
    $T2.='0;'.$incasat_num.';;;;';
$myBuffer.=$T.$cr.$T2.$cr.$F;

}
elseif($_SESSION['total_tichete']!=0 && $_SESSION['rest_tichete']=0){
    $incasat_tichete=$_SESSION['total_tichete'];
        $T.='3;'.$incasat_tichete.';;;;';
$myBuffer.=$T.$cr.$F;

}






file_put_contents('cm/bon.txt', $myBuffer);
	


// Quick check to verify that the file exists

// Force the download
header("Content-disposition: attachment; filename=bon.txt");
header("Content-type: text/plain");
readfile("cm/bon.txt");

$files = [
    'cm/bon.txt'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        unlink($file);
    } else {
        // File not found.
    }
}

?>