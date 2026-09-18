<?php
include('session.php');
$nr_bon=$_GET['nr_bon'];
$id_vanz=$_GET['id_vanz'];
				$sterg_sql="SELECT $tabel_final_nomenclator.departament,$tabel_final_nomenclator.gestiune,$tabel_final_det_note.cod_p,$tabel_final_det_note.cantitate from $tabel_final_det_note INNER JOIN $tabel_final_nomenclator on $tabel_final_det_note.cod_p=$tabel_final_nomenclator.cod_p where $tabel_final_det_note.id_vanz = '$id_vanz' ";
					$sterg_f_stmt = $pdo->prepare($sterg_sql);  
$sterg_f_stmt->execute(); 

while ($row = $sterg_f_stmt->fetch(PDO::FETCH_ASSOC)){ 

$c_p=$row['cod_p'];
$cant_vand=$row['cantitate'];
$gst=$row['gestiune'];
$dep=$row['departament'];
}

if($gst=="PF"){
					$sql="DELETE from $tabel_final_det_note WHERE $tabel_final_det_note.id_vanz = '$id_vanz';";
					
$pdo->exec($sql);

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
    	$sql="DELETE from $tabel_final_det_note WHERE $tabel_final_det_note.id_vanz = '$id_vanz';update $tabel_final_stoc set cantitate=cantitate+'$cant_vand' where cod_p='$c_p';";
				
$pdo->exec($sql); 

    
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