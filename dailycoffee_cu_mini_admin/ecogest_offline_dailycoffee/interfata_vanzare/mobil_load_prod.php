<?php
    include('database_connection.php');
        $nr_bon=$_SESSION['nr_bon'];

    if(isset($_GET['categ']))
    {
        //connect to database
        
        
        $c = $_GET['categ'];
        
        if($c==999){
            
     
$test_sql = "SELECT $tabel_final_nomenclator.imagine,$tabel_final_nomenclator.desc_prod,cote_tva.cota,$tabel_final_nomenclator.pret_vanzare,$tabel_final_nomenclator.proc_adaos,$tabel_final_nomenclator.gestiune,$tabel_final_nomenclator.cod_p,$tabel_final_nomenclator.um,$tabel_final_nomenclator.den_p,$tabel_final_stoc.cantitate from $tabel_final_nomenclator inner join $tabel_final_stoc on $tabel_final_stoc.cod_p=$tabel_final_nomenclator.cod_p INNER JOIN cote_tva on $tabel_final_nomenclator.cota_tva=cote_tva.id where $tabel_final_nomenclator.gestiune!='MP' AND $tabel_final_nomenclator.gestiune!='MC' AND $tabel_final_nomenclator.gestiune!='MA'";    
$test_stm = $pdo->prepare($test_sql);  
$test_stm->execute(); 
while ($row = $test_stm->fetch(PDO::FETCH_ASSOC)){ 
  $um=$row['um'];
    $den_p=$row['den_p'];
    $cod_p=$row['cod_p'];
 $stocc=$row['cantitate'];
 $gestiune=$row['gestiune'];
 $desc_p=$row['desc_prod'];
 $imagine="poze_produse/".$row['imagine'];
 			                   $pret_achiz=$row['pret'];
							   $cota_tva=$row['cota'];
							   $proc_ad=$row['proc_adaos'];
	 $adaos_unitar=$pret_achiz*$proc_ad/100;
	 $v_adaos=$adaos_unitar*1;
	 $pret_vanzare=$row['pret_vanzare'];

 
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

     echo" 					   

   <div class='col-md-6' style='border-color:white;border-style:solid;'>
          <a class='despre_prod' name='$pret_vanzare' data-codp='$cod_p' data-um='$um' data-value='$den_p' data-descriere='$desc_p' data-imagine='$imagine'> <img style='width:100%;height:12em;' src='$imagine' /></a>
          <h4>$den_p<button style='display:inline-block;width:40%;height:auto;float:right;font-size:1.2em;' name='$pret_vanzare' data-codp='$cod_p' data-um='$um' data-value='$den_p' data-descriere='$desc_p' data-imagine='$imagine' class='btn btn-primary btn-block despre_prod' title='Click pentru mai multe informații'>  <i class='fa fa-question' aria-hidden='true'></i></button></h4><p> <button  value='$cod_p' data-value='$nr_bon' type='button' class='button adaug_prod'>$pret_vanzare RON <i class='fa fa-cart-plus' aria-hidden='true'></i>
</button>
        </div> "; 
    
}

elseif($stoc_mat==$materii_reteta){
    
      echo" 					   

   <div class='col-md-6' style='border-color:white;border-style:solid;'>
          <a class='despre_prod' name='$pret_vanzare' data-codp='$cod_p' data-um='$um' data-value='$den_p' data-descriere='$desc_p' data-imagine='$imagine'> <img style='width:100%;height:12em;' src='$imagine' /></a>
          <h4>$den_p<button style='display:inline-block;width:40%;height:auto;float:right;font-size:1.2em;' name='$pret_vanzare' data-codp='$cod_p' data-um='$um' data-value='$den_p' data-descriere='$desc_p' data-imagine='$imagine' class='btn btn-primary btn-block despre_prod' title='Click pentru mai multe informații'>  <i class='fa fa-question' aria-hidden='true'></i></button></h4><p> <button  value='$cod_p' data-value='$nr_bon' type='button' class='button adaug_prod'>$pret_vanzare RON <i class='fa fa-cart-plus' aria-hidden='true'></i>
</button>
        </div> "; 
}

}
else{
 if($stocc<=0){
    echo" 					   

  <div class='col-md-6' style='border-color:white;border-style:solid;'>
          <a class='despre_prod' name='$pret_vanzare' data-codp='$cod_p' data-um='$um' data-value='$den_p' data-descriere='$desc_p' data-imagine='$imagine'> <img style='width:100%;height:12em;' src='$imagine' /></a>
          <h4>$den_p<button style='display:inline-block;width:40%;height:auto;float:right;font-size:1.2em;' name='$pret_vanzare' data-codp='$cod_p' data-um='$um' data-value='$den_p' data-descriere='$desc_p' data-imagine='$imagine' class='btn btn-primary btn-block despre_prod' title='Click pentru mai multe informații'>  <i class='fa fa-question' aria-hidden='true'></i></button></h4><p> <button  value='$cod_p' data-value='$nr_bon' type='button' class='button adaug_prod'>$pret_vanzare RON <i class='fa fa-cart-plus' aria-hidden='true'></i>
</button>
        </div> "; }
  else{
      echo" 					   

   <div class='col-md-6' style='border-color:white;border-style:solid;'>
          <a class='despre_prod' name='$pret_vanzare' data-codp='$cod_p' data-um='$um' data-value='$den_p' data-descriere='$desc_p' data-imagine='$imagine'> <img style='width:100%;height:12em;' src='$imagine' /></a>
          <h4>$den_p<button style='display:inline-block;width:40%;height:auto;float:right;font-size:1.2em;' name='$pret_vanzare' data-codp='$cod_p' data-um='$um' data-value='$den_p' data-descriere='$desc_p' data-imagine='$imagine' class='btn btn-primary btn-block despre_prod' title='Click pentru mai multe informații'>  <i class='fa fa-question' aria-hidden='true'></i></button></h4><p> <button  value='$cod_p' data-value='$nr_bon' type='button' class='button adaug_prod'>$pret_vanzare RON <i class='fa fa-cart-plus' aria-hidden='true'></i>
</button>
        </div>  "; 
      
  }
}
 					}
 					
 					else{
 					    echo" 					   

   <div class='col-md-6' style='border-color:white;border-style:solid;'>
          <a class='despre_prod' name='$pret_vanzare' data-codp='$cod_p' data-um='$um' data-value='$den_p' data-descriere='$desc_p' data-imagine='$imagine'> <img style='width:100%;height:12em;' src='$imagine' /></a>
          <h4>$den_p<button style='display:inline-block;width:40%;height:auto;float:right;font-size:1.2em;' name='$pret_vanzare' data-codp='$cod_p' data-um='$um' data-value='$den_p' data-descriere='$desc_p' data-imagine='$imagine' class='btn btn-primary btn-block despre_prod' title='Click pentru mai multe informații'>  <i class='fa fa-question' aria-hidden='true'></i></button></h4><p> <button  value='$cod_p' data-value='$nr_bon' type='button' class='button adaug_prod'>$pret_vanzare RON <i class='fa fa-cart-plus' aria-hidden='true'></i>
</button>
        </div> "; 
 					}


}
        }
        else{
        $produse = '';
        
      $ppcksql = "SELECT $tabel_final_nomenclator.imagine,$tabel_final_nomenclator.desc_prod,cote_tva.cota,$tabel_final_nomenclator.pret_vanzare,$tabel_final_nomenclator.proc_adaos,$tabel_final_nomenclator.gestiune,$tabel_final_nomenclator.cod_p,$tabel_final_nomenclator.um,$tabel_final_nomenclator.den_p,$tabel_final_stoc.cantitate from $tabel_final_nomenclator inner join $tabel_final_stoc on $tabel_final_stoc.cod_p=$tabel_final_nomenclator.cod_p INNER JOIN $tabel_final_categorii on $tabel_final_nomenclator.cod_categ=$tabel_final_categorii.id_categorie INNER JOIN cote_tva on $tabel_final_nomenclator.cota_tva=cote_tva.id where $tabel_final_nomenclator.gestiune!='MP' AND $tabel_final_nomenclator.gestiune!='MC' AND $tabel_final_nomenclator.gestiune!='MA' AND cod_categ='$c' group by $tabel_final_nomenclator.cod_p";    
$ppckstmt = $pdo->prepare($ppcksql);  
$ppckstmt->execute(); 
$prod_count=$ppckstmt->rowCount();

			        	while ($row = $ppckstmt->fetch(PDO::FETCH_ASSOC)){
			        	    
  $um=$row['um'];
    $den_p=$row['den_p'];
    $cod_p=$row['cod_p'];
 $stocc=$row['cantitate'];
 $gestiune=$row['gestiune'];
 $desc_p=$row['desc_prod'];
 $imagine="poze_produse/".$row['imagine'];
 			                   $pret_achiz=$row['pret'];
							   $cota_tva=$row['cota'];
							   $proc_ad=$row['proc_adaos'];
	 $adaos_unitar=$pret_achiz*$proc_ad/100;
	 $v_adaos=$adaos_unitar*1;
	 $pret_vanzare=$row['pret_vanzare'];

 
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

     $produse.="  					   

   <div class='col-md-6' style='border-color:white;border-style:solid;'>
          <a class='despre_prod' name='$pret_vanzare' data-codp='$cod_p' data-um='$um' data-value='$den_p' data-descriere='$desc_p' data-imagine='$imagine'> <img style='width:100%;height:12em;' src='$imagine' /></a>
          <h4>$den_p<button style='display:inline-block;width:40%;height:auto;float:right;font-size:1.2em;' name='$pret_vanzare' data-codp='$cod_p' data-um='$um' data-value='$den_p' data-descriere='$desc_p' data-imagine='$imagine' class='btn btn-primary btn-block despre_prod' title='Click pentru mai multe informații'>  <i class='fa fa-question' aria-hidden='true'></i></button></h4><p> <button  value='$cod_p' data-value='$nr_bon' type='button' class='button adaug_prod'>$pret_vanzare RON <i class='fa fa-cart-plus' aria-hidden='true'></i>
</button>
        </div> "; 
    
}

elseif($stoc_mat==$materii_reteta){
    
      $produse.="  					   

   <div class='col-md-6' style='border-color:white;border-style:solid;'>
          <a class='despre_prod' name='$pret_vanzare' data-codp='$cod_p' data-um='$um' data-value='$den_p' data-descriere='$desc_p' data-imagine='$imagine'> <img style='width:100%;height:12em;' src='$imagine' /></a>
          <h4>$den_p<button style='display:inline-block;width:40%;height:auto;float:right;font-size:1.2em;' name='$pret_vanzare' data-codp='$cod_p' data-um='$um' data-value='$den_p' data-descriere='$desc_p' data-imagine='$imagine' class='btn btn-primary btn-block despre_prod' title='Click pentru mai multe informații'>  <i class='fa fa-question' aria-hidden='true'></i></button></h4><p> <button  value='$cod_p' data-value='$nr_bon' type='button' class='button adaug_prod'>$pret_vanzare RON <i class='fa fa-cart-plus' aria-hidden='true'></i>
</button>
        </div> "; 
}

}
else{
 if($stocc<=0){
    $produse.="  					   

  <div class='col-md-6' style='border-color:white;border-style:solid;'>
          <a class='despre_prod' name='$pret_vanzare' data-codp='$cod_p' data-um='$um' data-value='$den_p' data-descriere='$desc_p' data-imagine='$imagine'> <img style='width:100%;height:12em;' src='$imagine' /></a>
          <h4>$den_p<button style='display:inline-block;width:40%;height:auto;float:right;font-size:1.2em;' name='$pret_vanzare' data-codp='$cod_p' data-um='$um' data-value='$den_p' data-descriere='$desc_p' data-imagine='$imagine' class='btn btn-primary btn-block despre_prod' title='Click pentru mai multe informații'>  <i class='fa fa-question' aria-hidden='true'></i></button></h4><p> <button  value='$cod_p' data-value='$nr_bon' type='button' class='button adaug_prod'>$pret_vanzare RON <i class='fa fa-cart-plus' aria-hidden='true'></i>
</button>
        </div> "; }
  else{
      $produse.="  					   

   <div class='col-md-6' style='border-color:white;border-style:solid;'>
          <a class='despre_prod' name='$pret_vanzare' data-codp='$cod_p' data-um='$um' data-value='$den_p' data-descriere='$desc_p' data-imagine='$imagine'> <img style='width:100%;height:12em;' src='$imagine' /></a>
          <h4>$den_p<button style='display:inline-block;width:40%;height:auto;float:right;font-size:1.2em;' name='$pret_vanzare' data-codp='$cod_p' data-um='$um' data-value='$den_p' data-descriere='$desc_p' data-imagine='$imagine' class='btn btn-primary btn-block despre_prod' title='Click pentru mai multe informații'>  <i class='fa fa-question' aria-hidden='true'></i></button></h4><p> <button  value='$cod_p' data-value='$nr_bon' type='button' class='button adaug_prod'>$pret_vanzare RON <i class='fa fa-cart-plus' aria-hidden='true'></i>
</button>
        </div> "; 
      
  }
}
 					}
 					
 					else{
 					    $produse.="  					   
 <div class='col-md-6' style='border-color:white;border-style:solid;'>
          <a class='despre_prod' name='$pret_vanzare' data-codp='$cod_p' data-um='$um' data-value='$den_p' data-descriere='$desc_p' data-imagine='$imagine'> <img style='width:100%;height:12em;' src='$imagine' /></a>
          <h4>$den_p<button style='display:inline-block;width:40%;height:auto;float:right;font-size:1.2em;' name='$pret_vanzare' data-codp='$cod_p' data-um='$um' data-value='$den_p' data-descriere='$desc_p' data-imagine='$imagine' class='btn btn-primary btn-block despre_prod' title='Click pentru mai multe informații'>  <i class='fa fa-question' aria-hidden='true'></i></button></h4><p> <button  value='$cod_p' data-value='$nr_bon' type='button' class='button adaug_prod'>$pret_vanzare RON <i class='fa fa-cart-plus' aria-hidden='true'></i>
</button>
        </div> "; 
 					}

        }
        
			        	}
    }
        if($produse == ''){
            echo '';}
        else {
            echo $produse;}
    

?> <style>.button{
    display: inline-block;
    background-color: #000;
    border-radius: 4px;
    font-family: "arial-black";
    
    color: #FFF;
    margin-top:1em;
    padding: 8px 12px;
    cursor: pointer;
    
}

</style>

