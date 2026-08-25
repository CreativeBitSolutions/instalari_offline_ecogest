<?php
// vanzare_procesare_finalizare_bon.php

// 1. Includem fisierul de sesiune pentru a avea acces la $pdo, $_SESSION si variabilele de tabele
include('session.php');

// Verificam daca avem datele esentiale in sesiune. Daca nu, oprim executia.
if (!isset($_SESSION['nr_bon'], $_SESSION['admin_id'], $_SESSION['cod_locatie'])) {
    // Optional: poti adauga un mesaj de eroare
    $_SESSION['error'] = 'Sesiunea a expirat sau este invalidă.';
    header('Location: logout.php');
    exit;
}

// 2. Preluam variabilele necesare din sesiune
$nr_bon = $_SESSION['nr_bon'];
$cod_locatie = $_SESSION['cod_locatie'];
$adm_id = $_SESSION['admin_id'];


// 3. Recalculam totalurile necesare (acestea nu se transfera automat de la pagina anterioara)
date_default_timezone_set("Europe/Bucharest");
$ora_bon = date("H:i:s");
$data_bon = date("Y-m-d");

// Calcul total valoare cu TVA
$f_tot_sql_val = "SELECT sum($tabel_final_det_note.valoare_vanzare_cu_tva) as total_val_vz_tva from $tabel_final_det_note where nr_bon='$nr_bon'";
$f_tot_stmt_val = $pdo->prepare($f_tot_sql_val);
$f_tot_stmt_val->execute();
$row_val = $f_tot_stmt_val->fetch(PDO::FETCH_ASSOC);
$valoare_f_vz = $row_val['total_val_vz_tva'] ?? 0;
$total_val_vz_cu_tva = round($valoare_f_vz, 2); // Folosim aceasta valoare pentru incasari

// Calcul total TVA colectata
$f_tot_sql_tva = "SELECT sum($tabel_final_det_note.tva_col) as total_tva_col from $tabel_final_det_note where nr_bon='$nr_bon'";
$f_tot_stmt_tva = $pdo->prepare($f_tot_sql_tva);
$f_tot_stmt_tva->execute();
$row_tva = $f_tot_stmt_tva->fetch(PDO::FETCH_ASSOC);
$total_tva_col = $row_tva['total_tva_col'] ?? 0;

// Calcul total discount
$disc_tot_sql = "SELECT sum($tabel_final_det_note.discount) as total_disc from $tabel_final_det_note where nr_bon='$nr_bon'";
$disc_tot_stmt = $pdo->prepare($disc_tot_sql);
$disc_tot_stmt->execute();
$row_disc = $disc_tot_stmt->fetch(PDO::FETCH_ASSOC);
$total_discount = $row_disc['total_disc'] ?? 0;

if(isset($_POST['finaliz_bon'])){
    $masa_fin=$_POST['masa_curenta'];
    $sql_tip_masa = "SELECT tip_masa FROM mese WHERE cod_masa = ?";
$stmt_tip_masa = $pdo->prepare($sql_tip_masa);
$stmt_tip_masa->execute([$masa_fin]);
$tip_masa = $stmt_tip_masa->fetchColumn();
$new_status="F";
$new_stare=0;
if ($tip_masa === "bratara") {
$new_stare=1;
}
		date_default_timezone_set("Europe/Bucharest");
$ora_bon = date("H:i:s", strtotime('+0 hours'));
 $data_bon = date("Y-m-d", strtotime('+0 hours'));
 $f_tot_sql = "SELECT sum($tabel_final_det_note.tva_col) as total_tva_col from $tabel_final_det_note  where nr_bon='$nr_bon'; ";    
$f_tot_stmt = $pdo->prepare($f_tot_sql);  
$f_tot_stmt->execute();
while ($row = $f_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_tva_col=$row['total_tva_col'];
}
	if($_POST['finaliz_bon']=='numerar'){
	    $cif_client=$_POST['cif_client'];
	    $_SESSION['cif_client']=$_POST['cif_client'];
$rest = $_POST['rest_numerar'] ?? 0;

$numerar=$total_val_vz_cu_tva;
$_SESSION['numerarprim']=$total_val_vz_cu_tva;
$fin_sql = "update $tabel_final_note SET rest='$rest',tva_colectata='$total_tva_col',valoare_vanzare_cu_tva='$valoare_f_vz',data_bon='$data_bon',ora_bon='$ora_bon',status='$new_status',numerar='$numerar',discount='$total_discount',cif_client='$cif_client' WHERE nrbon='$nr_bon';";    
	}
		elseif($_POST['finaliz_bon']=='card'){
		    	    $cif_client=$_POST['cif_client'];
		    	    	    $_SESSION['cif_client']=$_POST['cif_client'];
		  $card=$total_val_vz_cu_tva;
$_SESSION['cardprim']=$total_val_vz_cu_tva;
$fin_sql = "update $tabel_final_note SET tva_colectata='$total_tva_col',valoare_vanzare_cu_tva='$valoare_f_vz',data_bon='$data_bon',ora_bon='$ora_bon',status='$new_status',card='$card',discount='$total_discount',cif_client='$cif_client' WHERE nrbon='$nr_bon';";    
		}
			elseif($_POST['finaliz_bon']=='numerar_si_card'){
			    	    $cif_client_m=$_POST['cif_client_m'];
			    	    $_SESSION['cif_client']=$_POST['cif_client_m'];
$card=$_POST['card'];
$numerar=$_POST['numerar'];
$_SESSION['numerarprim']=$_POST['numerar'];
$_SESSION['cardprim']=$_POST['card'];
$fin_sql = "update $tabel_final_note SET tva_colectata='$total_tva_col',valoare_vanzare_cu_tva='$valoare_f_vz',data_bon='$data_bon',ora_bon='$ora_bon',status='$new_status',numerar='$numerar',card='$card',discount='$total_discount',cif_client='$cif_client_m' WHERE nrbon='$nr_bon';";    
		}
				elseif($_POST['finaliz_bon']=='tichete_de_masa'){
			    	    $cif_client_t=$_POST['cif_client_t'];
			    	    $_SESSION['cif_client']=$_POST['cif_client_t'];
$tichete=$_POST['total_tichete'];
$numerar=$_POST['rest_de_incasat'];
$rest=$_POST['rest_de_returnat'];
$_SESSION['rest_tichete']=$_POST['rest_de_incasat'];
$_SESSION['total_tichete']=$_POST['total_tichete'];
$fin_sql = "update $tabel_final_note SET tva_colectata='$total_tva_col',valoare_vanzare_cu_tva='$valoare_f_vz',data_bon='$data_bon',ora_bon='$ora_bon',status='$new_status',numerar='$numerar',tichete='$tichete',rest='$rest',discount='$total_discount',cif_client='$cif_client_t' WHERE nrbon='$nr_bon';";    
		}
		elseif($_POST['finaliz_bon']=='protocol'){
		    $pe_protocol=1;
	    $cif_client=$_POST['cif_client'];
	    $_SESSION['cif_client']=$_POST['cif_client'];
$rest = $_POST['rest_numerar'] ?? 0;
$numerar=$_POST['numerarprim'];
$_SESSION['numerarprim']=$_POST['numerarprim'];
$fin_sql = "update $tabel_final_note SET rest='$rest',tva_colectata='$total_tva_col',valoare_vanzare_cu_tva='$valoare_f_vz',data_bon='$data_bon',ora_bon='$ora_bon',status='$new_status',protocol='$numerar',discount='$total_discount',cif_client='$cif_client' WHERE nrbon='$nr_bon';";    
	}
	 	elseif($_POST['finaliz_bon']=='virament_bancar_separat_fara_casa_marcat'){
		    $virament_bancar_separat_fara_casa_marcat=1;
        
	    $cif_client=$_POST['cif_client'];
	    $_SESSION['cif_client']=$_POST['cif_client'];
$rest = $_POST['rest_numerar'] ?? 0;
$numerar=$_POST['numerarprim'];
$_SESSION['numerarprim']=$_POST['numerarprim'];
$fin_sql = "update $tabel_final_note SET rest='$rest',tva_colectata='$total_tva_col',valoare_vanzare_cu_tva='$valoare_f_vz',data_bon='$data_bon',ora_bon='$ora_bon',status='$new_status',virament_bancar='$numerar',discount='$total_discount',cif_client='$cif_client' WHERE nrbon='$nr_bon';";    
	}

	elseif($_POST['finaliz_bon']=='platit_din_sold'){
    $platit_din_sold=1;
    
  $cif_client=$_POST['cif_client'];
  $_SESSION['cif_client']=$_POST['cif_client'];
$rest = $_POST['rest_numerar'] ?? 0;
$numerar=$_POST['numerarprim'];
$_SESSION['numerarprim']=$_POST['numerarprim'];

$sql = "UPDATE mese SET sold = sold - :numerar WHERE cod_masa = :masa_fin";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':numerar' => $numerar,
    ':masa_fin' => $masa_fin
]);

$fin_sql = "update $tabel_final_note SET rest='$rest',tva_colectata='$total_tva_col',valoare_vanzare_cu_tva='$valoare_f_vz',data_bon='$data_bon',ora_bon='$ora_bon',status='$new_status',platit_din_sold='$numerar',discount='$total_discount',cif_client='$cif_client' WHERE nrbon='$nr_bon';";    

}
  
	 		elseif($_POST['finaliz_bon']=='glovo'){
	    $cif_client=$_POST['cif_client'];
	    $_SESSION['cif_client']=$_POST['cif_client'];
$rest = $_POST['rest_numerar'] ?? 0;
$numerar=$_POST['numerarprim'];
$_SESSION['glovo']=$_POST['numerarprim'];
$fin_sql = "update $tabel_final_note SET rest='$rest',tva_colectata='$total_tva_col',valoare_vanzare_cu_tva='$valoare_f_vz',data_bon='$data_bon',ora_bon='$ora_bon',status='$new_status',glovo='$numerar',discount='$total_discount',cif_client='$cif_client' WHERE nrbon='$nr_bon';";    
	}
	try{
$pdo->exec($fin_sql) or die(print_r($pdo->errorInfo(), true));   
}catch(PDOException $e)
    {
    echo $fin_sql . "<br>" . $e->getMessage();
    } 
    
 //incepem inserarea in miscari pentru vanzarile de pe nota
$misc_sql = "SELECT $tabel_final_nomenclator.pret_achizitie,$tabel_final_nomenclator.pret_cu_tva,$tabel_final_nomenclator.cota_tva,$tabel_final_nomenclator.nume,gestiuni.denumire_gestiune,$tabel_final_det_note.cod_p,$tabel_final_det_note.cantitate,$tabel_final_det_note.pret_vanzare AS pret_vanzare_det from $tabel_final_det_note INNER JOIN $tabel_final_nomenclator on $tabel_final_det_note.cod_p=$tabel_final_nomenclator.cod_produs  INNER JOIN 
            gestiuni 
            ON $tabel_final_nomenclator.id_gestiune = gestiuni.id_gestiune  where nr_bon='$nr_bon';";     
$misc_stmt = $pdo->prepare($misc_sql);  
$misc_stmt->execute(); 
  // NOU: pregătim interogarea separată în produse_servicii
$psCheckSql  = "SELECT produse_servicii.tip, gestiuni.denumire_gestiune FROM produse_servicii inner join gestiuni on produse_servicii.id_gestiune=gestiuni.id_gestiune WHERE produse_servicii.cod_produs = :cod_produs LIMIT 1";
$psCheckStmt = $pdo->prepare($psCheckSql);
while ($row = $misc_stmt->fetch(PDO::FETCH_ASSOC)){ 
             $pret_unitar_produs_selectat=$row['pret_achizitie'];
             $pret_vanzare_produs_selectat=$row['pret_cu_tva'];
             $nume_produs=$row['nume'];
             $cota_tva_produs_vandut=$row['cota_tva'];
         $prod=$row['cod_p'];
     $qt=$row['cantitate'];
     $gest=$row['denumire_gestiune'];
   // Verificăm dacă produsul este un serviciu sau bacșiș
// pentru a prelua prețul de vânzare real din det_note și a seta prețul de achiziție la 0.
$esteCazSpecial = false;

// Condiția 1: Gestiunea produsului curent (din $gest) este 'BACSIS'
if (strtoupper($gest) === 'BACSIS') {
    $esteCazSpecial = true;
}

// Condiția 2 (cea existentă): Verificăm dacă este de tip 'serviciu'
if (!$esteCazSpecial) {
    try {
        $psCheckStmt->execute([':cod_produs' => $prod]);
        $ps = $psCheckStmt->fetch(PDO::FETCH_ASSOC);

        if ($ps) {
            $tipPS  = strtolower(trim($ps['tip'] ?? ''));
            $gestPS = strtoupper(trim($ps['denumire_gestiune'] ?? ''));
            if (($tipPS === 'serviciu') || (strpos($gestPS, 'SERVICII') !== false)) {
                $esteCazSpecial = true;
            }
        }
    } catch (PDOException $e) {
        // opțional: log sau ignoră; nu blocăm finalizarea bonului
    }
}

// Dacă oricare dintre condiții este adevărată, suprascriem ambele prețuri
if ($esteCazSpecial && isset($row['pret_vanzare_det']) && $row['pret_vanzare_det'] !== null) {
    // Folosim prețul de vânzare real, cel de pe nota fiscală
    $pret_vanzare_produs_selectat = (float)$row['pret_vanzare_det'];
    // Prețul de achiziție (unitar) pentru un serviciu sau bacșiș este 0
    $pret_unitar_produs_selectat  = 0;
}

   if($gest=='PRODUSE FINITE'){
           
  // Interogarea rețetei pentru produsul final $prod
$reteta_sql = "SELECT cod_mat, cant_folos 
FROM $tabel_final_retete 
WHERE cod_p = '$prod'";
$reteta_stmt = $pdo->prepare($reteta_sql);
$reteta_stmt->execute();

// Pregătește interogarea pentru nomenclator (alături de gestiuni)
$nomenclator_sql = "SELECT pret_achizitie, pret_cu_tva, denumire_gestiune,nume,cota_tva 
     FROM $tabel_final_nomenclator 
     INNER JOIN gestiuni 
       ON gestiuni.id_gestiune = $tabel_final_nomenclator.id_gestiune 
     WHERE cod_produs = :cod_mat";
$nomenclator_stmt = $pdo->prepare($nomenclator_sql);

// Se obține numărul următor pentru documentul de tip BC
$b_c_sql = "SELECT max(nr_doc) as ultim_bc 
FROM $tabel_final_miscari 
WHERE fel_doc = 'BC'";
$b_c_stmt = $pdo->prepare($b_c_sql);  
$b_c_stmt->execute(); 
while ($row = $b_c_stmt->fetch(PDO::FETCH_ASSOC)) { 
$ultim_bc = $row['ultim_bc'] + 1;
}

while ($row = $reteta_stmt->fetch(PDO::FETCH_ASSOC)) { 
$c_m = $row['cod_mat'];
// Calculăm cantitatea pentru materia din rețetă
$cant_folos_de_produs_finit=$row['cant_folos'];
$qt_m = $row['cant_folos'] * $qt;
// Obținem detaliile materialului din nomenclator
$nomenclator_stmt->execute(['cod_mat' => $c_m]);
$nomenclator_row = $nomenclator_stmt->fetch(PDO::FETCH_ASSOC);

if ($nomenclator_row) {
$pret_unitar = $nomenclator_row['pret_achizitie'];
$pret_vanzare = $nomenclator_row['pret_cu_tva'];
$denumire_gestiune_material = $nomenclator_row['denumire_gestiune'];
$cota_tva_material=$nomenclator_row['cota_tva'];
$nume_material=$nomenclator_row['nume'];
// Dacă materia se află în gestiunea "PRODUSE FINITE"
if ($denumire_gestiune_material == 'PRODUSE FINITE') {
 
//  căutăm rețeta lui pentru a procesa doar ingredientele sale
$reteta_sub_sql = "SELECT cod_mat, cant_folos 
                FROM $tabel_final_retete 
                WHERE cod_p = :produs";
$reteta_sub_stmt = $pdo->prepare($reteta_sub_sql);
$reteta_sub_stmt->execute(['produs' => $c_m]);

while ($sub_row = $reteta_sub_stmt->fetch(PDO::FETCH_ASSOC)) {
 $sub_cod_mat = $sub_row['cod_mat'];
 // Calculăm cantitatea pentru ingredientul din rețeta sub-produsului
 $sub_qt = $sub_row['cant_folos'] * $qt_m;

 // Obținem detaliile ingredientului
 $nomenclator_stmt->execute(['cod_mat' => $sub_cod_mat]);
 $sub_nomenclator_row = $nomenclator_stmt->fetch(PDO::FETCH_ASSOC);

 if ($sub_nomenclator_row) {
     $sub_pret_unitar = $sub_nomenclator_row['pret_achizitie'];
     $sub_pret_vanzare = $sub_nomenclator_row['pret_cu_tva'];
     $sub_denumire_gestiune = $sub_nomenclator_row['denumire_gestiune'];
     $cota_tva_submaterial=$sub_nomenclator_row['cota_tva'];
     $nume_submaterial=$sub_nomenclator_row['nume'];
     // Inserăm mișcarea pentru ingredientul produsului finit (sub-produs)
     $iessql_sub = "INSERT INTO $tabel_final_miscari
                    (data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota, produs_obtinut, pu, pret_vanzare, gestiune,denumire_produs,cota_tva,cod_locatie, ora_miscarii)
                    VALUES ('$data_bon', '$sub_cod_mat', '$sub_qt', 'O', 'BC', '$ultim_bc', '$nr_bon', '$c_m', '$sub_pret_unitar', '$sub_pret_vanzare', '$sub_denumire_gestiune','$nume_submaterial','$cota_tva_submaterial','$cod_locatie', '$ora_bon')";
     try {
         $pdo->exec($iessql_sub);
     } catch(PDOException $e) {
         echo $iessql_sub . "<br>" . $e->getMessage();
     }
     
     // Tratăm situația în care submaterialul este în gestiunea "PRODUSE FINITE"
     if ($sub_denumire_gestiune == 'PRODUSE FINITE') {
         // Căutăm rețeta sub a submaterialului pentru a procesa doar ingredientele sale
         $reteta_sub_sub_sql = "SELECT cod_mat, cant_folos 
                    FROM $tabel_final_retete 
                    WHERE cod_p = :produs";
         $reteta_sub_sub_stmt = $pdo->prepare($reteta_sub_sub_sql);
         $reteta_sub_sub_stmt->execute(['produs' => $sub_cod_mat]);
         while ($sub_sub_row = $reteta_sub_sub_stmt->fetch(PDO::FETCH_ASSOC)) {
             $sub_sub_cod_mat = $sub_sub_row['cod_mat'];
             // Calculăm cantitatea pentru ingredientul din rețeta sub-materialului
             $sub_sub_qt = $sub_sub_row['cant_folos'] * $sub_qt;

             // Obținem detaliile ingredientului
             $nomenclator_stmt->execute(['cod_mat' => $sub_sub_cod_mat]);
             $sub_sub_nomenclator_row = $nomenclator_stmt->fetch(PDO::FETCH_ASSOC);

             if ($sub_sub_nomenclator_row) {
                 $sub_sub_pret_unitar = $sub_sub_nomenclator_row['pret_achizitie'];
                 $sub_sub_pret_vanzare = $sub_sub_nomenclator_row['pret_cu_tva'];
                 $sub_sub_denumire_gestiune = $sub_sub_nomenclator_row['denumire_gestiune'];
                 $sub_sub_cota_tva = $sub_sub_nomenclator_row['cota_tva'];
                 $sub_sub_nume = $sub_sub_nomenclator_row['nume'];
                 // Inserăm mișcarea pentru ingredientul sub-produsului (sub-sub material)
                 $iessql_sub_sub = "INSERT INTO $tabel_final_miscari
                    (data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota, produs_obtinut, pu, pret_vanzare, gestiune, denumire_produs, cota_tva,cod_locatie, ora_miscarii)
                    VALUES ('$data_bon', '$sub_sub_cod_mat', '$sub_sub_qt', 'O', 'BC', '$ultim_bc', '$nr_bon', '$sub_cod_mat', '$sub_sub_pret_unitar', '$sub_sub_pret_vanzare', '$sub_sub_denumire_gestiune','$sub_sub_nume','$sub_sub_cota_tva','$cod_locatie', '$ora_bon')";
                 try {
                     $pdo->exec($iessql_sub_sub);
                 } catch(PDOException $e) {
                     echo $iessql_sub_sub . "<br>" . $e->getMessage();
                 }
             }
         }
         
         // Inserăm intrarea de obținere și ieșirea submaterialului în gestiunea PRODUSE FINITE
         $b_c_sql = "SELECT max(nr_doc) as ultim_bt from $tabel_final_miscari where $tabel_final_miscari.fel_doc='BT'";
         $b_c_stmt = $pdo->prepare($b_c_sql);
         $b_c_stmt->execute();
         while ($row = $b_c_stmt->fetch(PDO::FETCH_ASSOC)) {
             $ultim_bt = $row['ultim_bt']+1;
         }
         $bt_sql = "insert into $tabel_final_miscari(data,cod_p,cantitate_misc,tip_miscare,fel_doc,nr_doc,nr_nota,pu,pret_vanzare,gestiune,denumire_produs,cota_tva,cod_locatie, ora_miscarii) values('$data_bon','$sub_cod_mat','$sub_qt','I','BT','$ultim_bt','$nr_bon','$sub_pret_unitar','$sub_pret_vanzare','SEMIFABRICATE','$nume_submaterial','$cota_tva_submaterial','$cod_locatie', '$ora_bon');";
         try{
             $pdo->exec($bt_sql);
         } catch(PDOException $e) {
             echo $bt_sql . "<br>" . $e->getMessage();
         }
         
         $b_c_sql = "SELECT max(nr_doc) as ultim_bc 
         FROM $tabel_final_miscari 
         WHERE fel_doc = 'BC'";
         $b_c_stmt = $pdo->prepare($b_c_sql);  
         $b_c_stmt->execute(); 
         while ($row = $b_c_stmt->fetch(PDO::FETCH_ASSOC)) { 
             $ultim_bc = $row['ultim_bc'] + 1;
         }
         $iesire_bc_submaterial_sql = "INSERT INTO $tabel_final_miscari
                          (data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota, produs_obtinut, pu, pret_vanzare, gestiune, denumire_produs, cota_tva,cod_locatie, ora_miscarii)
                          VALUES ('$data_bon', '$sub_cod_mat', '$sub_qt', 'O', 'BC', '$ultim_bc', '$nr_bon', '$c_m', '$sub_pret_unitar', '$sub_pret_vanzare', 'SEMIFABRICATE','$nume_submaterial','$cota_tva_submaterial','$cod_locatie', '$ora_bon')";
         try {
             $pdo->exec($iesire_bc_submaterial_sql);
         } catch(PDOException $e) {
             echo $iesire_bc_submaterial_sql . "<br>" . $e->getMessage();
         }
     }
 }
}
     
 //inseram intrarea de obtinere a materialului  ce se afla in gestiunea produse finite
 $b_c_sql = "SELECT max(nr_doc) as ultim_bt from $tabel_final_miscari where $tabel_final_miscari.fel_doc='BT'";    
 $b_c_stmt = $pdo->prepare($b_c_sql);  
 $b_c_stmt->execute(); 
        while ($row = $b_c_stmt->fetch(PDO::FETCH_ASSOC)){ 
            $ultim_bt=$row['ultim_bt']+1;
        }       
             $bt_sql = "insert into $tabel_final_miscari(data,cod_p,cantitate_misc,tip_miscare,fel_doc,nr_doc,nr_nota,pu,pret_vanzare,gestiune,denumire_produs,cota_tva,cod_locatie, ora_miscarii) values('$data_bon','$c_m','$cant_folos_de_produs_finit','I','BT','$ultim_bt','$nr_bon','$pret_unitar','$pret_vanzare','SEMIFABRICATE','$nume_material','$cota_tva_material','$cod_locatie', '$ora_bon');";    

             try{
               $pdo->exec($bt_sql) or die(print_r($pdo->errorInfo(), true));   
               }catch(PDOException $e)
                   {
                   echo $bt_sql . "<br>" . $e->getMessage();
                   } 

           // Inserăm mișcarea iesire bon consum pentru produsul finit ce se vinde
           
      // Se obține numărul următor pentru documentul de tip BC
      $b_c_sql = "SELECT max(nr_doc) as ultim_bc 
      FROM $tabel_final_miscari 
      WHERE fel_doc = 'BC'";
      $b_c_stmt = $pdo->prepare($b_c_sql);  
      $b_c_stmt->execute(); 
      while ($row = $b_c_stmt->fetch(PDO::FETCH_ASSOC)) { 
      $ultim_bc = $row['ultim_bc'] + 1;
      }
           $iesire_bc_produs_finit_vazut_ca_materie_sql = "INSERT INTO $tabel_final_miscari
                          (data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota, produs_obtinut, pu, pret_vanzare, gestiune,denumire_produs,cota_tva,cod_locatie, ora_miscarii)
                          VALUES ('$data_bon', '$c_m', '$cant_folos_de_produs_finit', 'O', 'BC', '$ultim_bc', '$nr_bon', '$prod', '$pret_unitar', '$pret_vanzare', 'SEMIFABRICATE','$nume_material','$cota_tva_material','$cod_locatie', '$ora_bon')";
           try {
               $pdo->exec($iesire_bc_produs_finit_vazut_ca_materie_sql);
           } catch(PDOException $e) {
               echo $iesire_bc_produs_finit_vazut_ca_materie_sql . "<br>" . $e->getMessage();
           }

} else {
// Dacă materia nu este în gestiunea "PRODUSE FINITE",
// se inserează mișcarea pentru ea direct.
$iessqll = "INSERT INTO $tabel_final_miscari
         (data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota, produs_obtinut, pu, pret_vanzare, gestiune, denumire_produs, cota_tva, cod_locatie, ora_miscarii)
         VALUES (:data, :cod_p, :cantitate_misc, 'O', 'BC', :nr_doc, :nr_nota, :produs_obtinut, :pu, :pret_vanzare, :gestiune, :denumire_produs, :cota_tva, :cod_locatie, :ora_miscarii)";
try {
 $iessqll_stmt = $pdo->prepare($iessqll);
 $iessqll_stmt->execute([
     ':data' => $data_bon,
     ':cod_p' => $c_m,
     ':cantitate_misc' => $qt_m,
     ':nr_doc' => $ultim_bc,
     ':nr_nota' => $nr_bon,
     ':produs_obtinut' => $prod,
     ':pu' => $pret_unitar,
     ':pret_vanzare' => $pret_vanzare,
     ':gestiune' => $denumire_gestiune_material,
     ':denumire_produs' => $nume_material,
     ':cota_tva' => $cota_tva_material,
     ':cod_locatie' => $cod_locatie,
     ':ora_miscarii' => $ora_bon
 ]);
} catch(PDOException $e) {
 echo $iessqll . "<br>" . $e->getMessage();
}
}
} else {
// Poți trata situația în care codul de material nu se găsește în nomenclator
echo "Materialul cu codul $c_m nu a fost găsit în nomenclator.";
}
}

// se insereaza miscarea de intrare bon transfer a produsului finit ce s-a vandut la bun inceput si apoi iesirea sa pe bon fiscal

$b_c_sql = "SELECT max(nr_doc) as ultim_bt from $tabel_final_miscari where $tabel_final_miscari.fel_doc='BT'";    
$b_c_stmt = $pdo->prepare($b_c_sql);  
$b_c_stmt->execute(); 
       while ($row = $b_c_stmt->fetch(PDO::FETCH_ASSOC)){ 
           $ultim_bt=$row['ultim_bt']+1;
       }       
            $bt_sql = "insert into $tabel_final_miscari(data,cod_p,cantitate_misc,tip_miscare,fel_doc,nr_doc,nr_nota,pu,pret_vanzare,gestiune,denumire_produs,cota_tva,cod_locatie, ora_miscarii) values('$data_bon','$prod','$qt','I','BT','$ultim_bt','$nr_bon','$pret_unitar_produs_selectat','$pret_vanzare_produs_selectat','$gest','$nume_produs','$cota_tva_produs_vandut','$cod_locatie', '$ora_bon');";    
	try{
$pdo->exec($bt_sql) or die(print_r($pdo->errorInfo(), true));   
}catch(PDOException $e)
    {
    echo $bt_sql . "<br>" . $e->getMessage();
    } 
           $iessql = "insert into $tabel_final_miscari(data,cod_p,cantitate_misc,tip_miscare,fel_doc,nr_doc,pu,pret_vanzare,gestiune,denumire_produs,cota_tva,cod_locatie, ora_miscarii) values('$data_bon','$prod','$qt','O','BF','$nr_bon','$pret_unitar_produs_selectat','$pret_vanzare_produs_selectat','$gest','$nume_produs','$cota_tva_produs_vandut','$cod_locatie', '$ora_bon');";   
	try{
$pdo->exec($iessql) or die(print_r($pdo->errorInfo(), true));   
}catch(PDOException $e)
    {
    echo $iessql . "<br>" . $e->getMessage();
    }

   }
   // daca produsul ce se vinde nu este produs finit atunci probabil e marfa si else
   else{
           $iessql = "insert into $tabel_final_miscari(data,cod_p,cantitate_misc,tip_miscare,fel_doc,nr_doc,pu,pret_vanzare,gestiune,denumire_produs,cota_tva,cod_locatie, ora_miscarii) values('$data_bon','$prod','$qt','O','BF','$nr_bon','$pret_unitar_produs_selectat','$pret_vanzare_produs_selectat','$gest','$nume_produs','$cota_tva_produs_vandut','$cod_locatie', '$ora_bon');";   
	try{
$pdo->exec($iessql) or die(print_r($pdo->errorInfo(), true));   
}catch(PDOException $e)
    {
    echo $iessql . "<br>" . $e->getMessage();
    }
}

} 
$curat_bucla_meniu_sql = "DELETE FROM $tabel_final_miscari 
        WHERE produs_obtinut != 0 
          AND gestiune = 'PRODUSE FINITE' 
          AND fel_doc = 'BC' 
          AND nr_nota = :nr_bon";

$curat_bucla_meniu_stmt = $pdo->prepare($curat_bucla_meniu_sql);
$curat_bucla_meniu_stmt->execute(['nr_bon' => $nr_bon]);

 // incheiere sectiune inserare in miscari a produselor vandute de pe nota 

require_once __DIR__ . '/offline_sync_queue_lib.php';
$restaurantQueueConfig = restaurant_sync_queue_config($restaurantConfig);
restaurant_sync_queue_enqueue_safely(static function () use ($pdo, $restaurantQueueConfig, $nr_bon, $adm_id): bool {
    return restaurant_sync_queue_enqueue_sale($pdo, $restaurantQueueConfig, (int)$nr_bon, (int)$adm_id);
});

    $_SESSION['nr_bon']=$nr_bon;
 $masaa_fin=$_SESSION['masa_curenta'];
 $updm_sq="update $tabel_final_mese set stare=$new_stare where cod_masa='$masaa_fin'"; 	 
    $updmstmt = $pdo->prepare($updm_sq);  
$updmstmt->execute(); 

if ($tip_masa === "bratara") {

  $masa = $masaa_fin;
    $operatorId   = $_SESSION['admin_id'];
    $cod_locatie  = $_SESSION['cod_locatie'];

    // 1) Verific intrare/abonament
    $sql_check_intrare = "
        SELECT COUNT(*) AS found 
        FROM det_note 
        WHERE nr_bon = :nr_bon 
          AND (nume_produs LIKE '%INTRAR%' OR nume_produs LIKE '%ABONAM%')
    ";
    $stmt = $pdo->prepare($sql_check_intrare);
    $stmt->execute([':nr_bon' => $nr_bon]);
    $found = $stmt->fetch(PDO::FETCH_ASSOC)['found'];

    if ($found > 0) {
        // 2) Marchez masa ca "vandut intrare"
        if (isset($_SESSION['masa_curenta'])) {
            $stmt_update = $pdo->prepare(
                "UPDATE mese 
                 SET vandut_intrare = 1 
                 WHERE cod_masa = ?"
            );
            $stmt_update->execute([$_SESSION['masa_curenta']]);
        }

    }

    // 3) Log incasari in tabelul dedicat
        $sql_insert = "
            INSERT INTO incasari_bratari (
                id_vanz, nr_bon, cod_masa, cod_p, nume_produs, cantitate,
                cota_tva, tva_col, pret_vanzare, valoare_vanzare,
                valoare_vanzare_cu_tva, discount, pachet, preparat,
                t_list, data, ora, operator
            )
            SELECT
                d.id_vanz,
                d.nr_bon,
                :cod_masa          AS cod_masa,
                d.cod_p,
                d.nume_produs,
                d.cantitate,
                d.cota_tva,
                d.tva_col,
                d.pret_vanzare,
                d.valoare_vanzare,
                d.valoare_vanzare_cu_tva,
                d.discount,
                d.pachet,
                d.preparat,
                d.t_list,
                d.data,
                d.ora,
                :operatorId        AS operator
            FROM det_note d
            WHERE d.nr_bon = :nr_bon
        ";
        $stmt_ins = $pdo->prepare($sql_insert);
        $stmt_ins->execute([
            ':cod_masa'   => $_SESSION['masa_curenta'],
            ':operatorId' => $operatorId,
            ':nr_bon'     => $nr_bon,
        ]);
       // 1) Preluăm ultimul nrbon pentru locație și calculăm următorul
$sqlMax = "SELECT COALESCE(MAX(nrbon), 0) AS lastBon 
           FROM $tabel_final_note";
$stmtMax = $pdo->prepare($sqlMax);
$stmtMax->execute();

$row = $stmtMax->fetch(PDO::FETCH_ASSOC);
$nextBon = $row['lastBon'] + 1;

// 2) Inserăm nota cu nrbon = lastBon + 1
$sqlIns = "INSERT INTO $tabel_final_note (nrbon, operator, locatie, cod_masa) 
       VALUES (:nrbon, :operator, :locatie, :cod_masa)";
$stmtIns = $pdo->prepare($sqlIns);
$stmtIns->execute([
'nrbon'    => $nextBon,
'operator' => $operatorId,
'locatie'  => $cod_locatie,
'cod_masa' => $masa
]);

$_SESSION['nextbon'] = $nextBon;
}

if (isset($pe_protocol) && $pe_protocol == 1) {
if (in_array((int)($_SESSION['client_id'] ?? 0), [25, 26], true)) {
    $_SESSION['skip_protocol_extra_print'] = 1;
}
printf("<script>location.href='listeaza_nota_fin.php'</script>");
exit;
}
if (isset($virament_bancar_separat_fara_casa_marcat) && $virament_bancar_separat_fara_casa_marcat == 1) {
  printf("<script>location.href='listeaza_nota_fin.php'</script>");
  }
  if (isset($platit_din_sold) && $platit_din_sold == 1) {
    printf("<script>location.href='listeaza_nota_fin.php'</script>");
    }
    if($_SESSION['mod_listare']=='complex'){
printf("<script>location.href='dwred_restaurant_cu_listare.php'</script>");
}
else{
    printf("<script>location.href='casa_marcat_vanzare.php'</script>");
}
}?>
