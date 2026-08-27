	<?php include 'database_connection.php';
$lvsql = "ALTER TABLE abonati DROP PRIMARY KEY ";
$lvstmt = $pdo->prepare($lvsql);
$lvstmt->execute(); 


$lvsql = "ALTER TABLE achizitii DROP PRIMARY KEY ;";
$lvstmt = $pdo->prepare($lvsql);
$lvstmt->execute(); 


$lvsql = "ALTER TABLE bonuri DROP PRIMARY KEY ;";
$lvstmt = $pdo->prepare($lvsql);
$lvstmt->execute(); 


$lvsql = "ALTER TABLE categorii DROP PRIMARY KEY ;";
$lvstmt = $pdo->prepare($lvsql);
$lvstmt->execute(); 


$lvsql = "ALTER TABLE chitante DROP PRIMARY KEY ;";
$lvstmt = $pdo->prepare($lvsql);
$lvstmt->execute(); 


$lvsql = "ALTER TABLE comenzi DROP PRIMARY KEY ;";
$lvstmt = $pdo->prepare($lvsql);
$lvstmt->execute(); 




$lvsql = "ALTER TABLE comenzi_detalii DROP PRIMARY KEY ;";
$lvstmt = $pdo->prepare($lvsql);
$lvstmt->execute(); 




$lvsql = "ALTER TABLE cosuri DROP PRIMARY KEY ;";
$lvstmt = $pdo->prepare($lvsql);
$lvstmt->execute(); 



$lvsql = "ALTER TABLE abonati DROP PRIMARY KEY ;";
$lvstmt = $pdo->prepare($lvsql);
$lvstmt->execute(); 




$lvsql = "ALTER TABLE customers DROP PRIMARY KEY ;";
$lvstmt = $pdo->prepare($lvsql);
$lvstmt->execute(); 




$lvsql = "ALTER TABLE date_firma DROP PRIMARY KEY ;";
$lvstmt = $pdo->prepare($lvsql);
$lvstmt->execute(); 




$lvsql = "ALTER TABLE det_bonuri DROP PRIMARY KEY ;";
$lvstmt = $pdo->prepare($lvsql);
$lvstmt->execute();


$lvsql = "ALTER TABLE det_monetar DROP PRIMARY KEY ;";
$lvstmt = $pdo->prepare($lvsql);
$lvstmt->execute(); 



$lvsql = "ALTER TABLE det_note DROP PRIMARY KEY ;";
$lvstmt = $pdo->prepare($lvsql);
$lvstmt->execute(); 

$lvsql = "ALTER TABLE det_procese_comp DROP PRIMARY KEY ;";
$lvstmt = $pdo->prepare($lvsql);
$lvstmt->execute(); 
$lvsql = "ALTER TABLE det_stornari DROP PRIMARY KEY ;";
$lvstmt = $pdo->prepare($lvsql);
$lvstmt->execute(); 
$lvsql = "ALTER TABLE det_stornari_fact DROP PRIMARY KEY ;";
$lvstmt = $pdo->prepare($lvsql);
$lvstmt->execute(); 



$lvsql = "ALTER TABLE det_stornari_rest DROP PRIMARY KEY ;";
$lvstmt = $pdo->prepare($lvsql);
$lvstmt->execute(); 

$lvsql = "ALTER TABLE de_listat_bar DROP PRIMARY KEY ;";
$lvstmt = $pdo->prepare($lvsql);
$lvstmt->execute(); 

$lvsql = "ALTER TABLE de_listat_buc DROP PRIMARY KEY ;";
$lvstmt = $pdo->prepare($lvsql);
$lvstmt->execute(); 

$lvsql = "ALTER TABLE dispozitii DROP PRIMARY KEY ;";
$lvstmt = $pdo->prepare($lvsql);
$lvstmt->execute(); 

$lvsql = "ALTER TABLE extra_images DROP PRIMARY KEY ;";
$lvstmt = $pdo->prepare($lvsql);
$lvstmt->execute(); 

$lvsql = "ALTER TABLE facturi DROP PRIMARY KEY ;";
$lvstmt = $pdo->prepare($lvsql);
$lvstmt->execute(); 

$lvsql = "ALTER TABLE inchideri_m DROP PRIMARY KEY ;";
$lvstmt = $pdo->prepare($lvsql);
$lvstmt->execute(); 

$lvsql = "ALTER TABLE inchideri_r DROP PRIMARY KEY ;";
$lvstmt = $pdo->prepare($lvsql);
$lvstmt->execute(); 

$lvsql = "ALTER TABLE loc_mese DROP PRIMARY KEY ;";
$lvstmt = $pdo->prepare($lvsql);
$lvstmt->execute(); 
$lvsql = "ALTER TABLE mese DROP PRIMARY KEY ;";
$lvstmt = $pdo->prepare($lvsql);
$lvstmt->execute(); 
$lvsql = "ALTER TABLE miscari DROP PRIMARY KEY ;";
$lvstmt = $pdo->prepare($lvsql);
$lvstmt->execute(); 
$lvsql = "ALTER TABLE monetar DROP PRIMARY KEY ;";
$lvstmt = $pdo->prepare($lvsql);
$lvstmt->execute(); 
$lvsql = "ALTER TABLE nir DROP PRIMARY KEY ;";
$lvstmt = $pdo->prepare($lvsql);
$lvstmt->execute(); 
$lvsql = "ALTER TABLE nomenclator DROP PRIMARY KEY ;";
$lvstmt = $pdo->prepare($lvsql);
$lvstmt->execute(); 
$lvsql = "ALTER TABLE note DROP PRIMARY KEY ;";
$lvstmt = $pdo->prepare($lvsql);
$lvstmt->execute(); 
$lvsql = "ALTER TABLE procese_comp DROP PRIMARY KEY ;";
$lvstmt = $pdo->prepare($lvsql);
$lvstmt->execute(); 
$lvsql = "ALTER TABLE recenzii DROP PRIMARY KEY ;";
$lvstmt = $pdo->prepare($lvsql);
$lvstmt->execute(); 
$lvsql = "ALTER TABLE retete DROP PRIMARY KEY ;";
$lvstmt = $pdo->prepare($lvsql);
$lvstmt->execute(); 
$lvsql = "ALTER TABLE stoc DROP PRIMARY KEY ;";
$lvstmt = $pdo->prepare($lvsql);
$lvstmt->execute(); 
$lvsql = "ALTER TABLE stornari DROP PRIMARY KEY ;";
$lvstmt = $pdo->prepare($lvsql);
$lvstmt->execute(); 
$lvsql = "ALTER TABLE stornari_fact DROP PRIMARY KEY ;";
$lvstmt = $pdo->prepare($lvsql);
$lvstmt->execute(); 
$lvsql = "ALTER TABLE stornari_rest DROP PRIMARY KEY ;";
$lvstmt = $pdo->prepare($lvsql);
$lvstmt->execute(); 
$lvsql = "ALTER TABLE terti DROP PRIMARY KEY ;";
$lvstmt = $pdo->prepare($lvsql);
$lvstmt->execute(); 
$lvsql = "ALTER TABLE vanzari DROP PRIMARY KEY ;";
$lvstmt = $pdo->prepare($lvsql);
$lvstmt->execute(); 


?>