<?php  //vanzare_retur_consum.php 
ini_set('display_errors', 0); // Nu afișează erorile utilizatorului
ini_set('log_errors', 1); // Activează logarea erorilor
ini_set('error_log', 'error_log.log');
header('Content-Type: application/json');

include('session.php');
if (isset($_POST['action']) && $_POST['action'] == 'consum_retur') {
    $id_retur = intval($_POST['id_retur']);
    
    // Selectăm returul doar dacă nu a fost consumat (consumat=0)
    $sqlFetch = "SELECT * FROM retururi WHERE id_retur = $id_retur AND consumat = 0";
    $resFetch = $pdo->query($sqlFetch);
    $returRow = $resFetch ? $resFetch->fetch(PDO::FETCH_ASSOC) : false;
    
    if (!$returRow) {
        echo json_encode(array('status' => 'error', 'message' => 'Returul nu a fost găsit sau este deja consumat.'));
        exit;
    }
    
    // Extragem detaliile din retur – notăm că avem nr_bon asociat
    $nr_bon = $returRow['nr_bon'];
    
    // Stabilim data și ora curente (București)
    date_default_timezone_set("Europe/Bucharest");
    $data_bon = date("Y-m-d");
    $ora_bon  = date("H:i:s");
    
    // ------------------------------
    // Inserarea în miscări – exact ca în finaliz_bon
    // ------------------------------
    $misc_sql = "SELECT {$tabel_final_nomenclator}.pret_achizitie, 
                        {$tabel_final_nomenclator}.pret_cu_tva, 
                        {$tabel_final_nomenclator}.cota_tva, 
                        {$tabel_final_nomenclator}.nume, 
                        gestiuni.denumire_gestiune, 
                        retururi.cod_p, 
                        retururi.cantitate 
                 FROM retururi 
                 INNER JOIN {$tabel_final_nomenclator} 
                   ON retururi.cod_p = {$tabel_final_nomenclator}.cod_produs  
                 INNER JOIN gestiuni 
                   ON {$tabel_final_nomenclator}.id_gestiune = gestiuni.id_gestiune  
                 WHERE nr_bon = '$nr_bon'";
    $misc_stmt = $pdo->prepare($misc_sql);
    $misc_stmt->execute();
    
    while ($row = $misc_stmt->fetch(PDO::FETCH_ASSOC)) {
        $pret_unitar_produs_selectat = $row['pret_achizitie'];
        $pret_vanzare_produs_selectat = $row['pret_cu_tva'];
        $nume_produs                = $row['nume'];
        $cota_tva_produs_vandut     = $row['cota_tva'];
        $prod                       = $row['cod_p'];
        $qt                         = $row['cantitate'];
        $gest                       = $row['denumire_gestiune'];
        
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
                              (data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota, produs_obtinut, pu, pret_vanzare, gestiune,denumire_produs,cota_tva,id_retur)
                              VALUES ('$data_bon', '$sub_cod_mat', '$sub_qt', 'O', 'BC', '$ultim_bc', '$nr_bon', '$c_m', '$sub_pret_unitar', '$sub_pret_vanzare', '$sub_denumire_gestiune','$nume_submaterial','$cota_tva_submaterial','$id_retur')";
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
                              (data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota, produs_obtinut, pu, pret_vanzare, gestiune, denumire_produs, cota_tva,id_retur)
                              VALUES ('$data_bon', '$sub_sub_cod_mat', '$sub_sub_qt', 'O', 'BC', '$ultim_bc', '$nr_bon', '$sub_cod_mat', '$sub_sub_pret_unitar', '$sub_sub_pret_vanzare', '$sub_sub_denumire_gestiune','$sub_sub_nume','$sub_sub_cota_tva','$id_retur')";
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
                   $bt_sql = "insert into $tabel_final_miscari(data,cod_p,cantitate_misc,tip_miscare,fel_doc,nr_doc,nr_nota,pu,pret_vanzare,gestiune,denumire_produs,cota_tva,id_retur) values('$data_bon','$sub_cod_mat','$sub_qt','I','BT','$ultim_bt','$nr_bon','$sub_pret_unitar','$sub_pret_vanzare','SEMIFABRICATE','$nume_submaterial','$cota_tva_submaterial','$id_retur');";
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
                                    (data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota, produs_obtinut, pu, pret_vanzare, gestiune, denumire_produs, cota_tva,id_retur)
                                    VALUES ('$data_bon', '$sub_cod_mat', '$sub_qt', 'O', 'BC', '$ultim_bc', '$nr_bon', '$c_m', '$sub_pret_unitar', '$sub_pret_vanzare', 'SEMIFABRICATE','$nume_submaterial','$cota_tva_submaterial','$id_retur')";
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
                       $bt_sql = "insert into $tabel_final_miscari(data,cod_p,cantitate_misc,tip_miscare,fel_doc,nr_doc,nr_nota,pu,pret_vanzare,gestiune,denumire_produs,cota_tva,id_retur) values('$data_bon','$c_m','$cant_folos_de_produs_finit','I','BT','$ultim_bt','$nr_bon','$pret_unitar','$pret_vanzare','SEMIFABRICATE','$nume_material','$cota_tva_material','$id_retur');";    
          
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
                                    (data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota, produs_obtinut, pu, pret_vanzare, gestiune,denumire_produs,cota_tva,id_retur)
                                    VALUES ('$data_bon', '$c_m', '$cant_folos_de_produs_finit', 'O', 'BC', '$ultim_bc', '$nr_bon', '$prod', '$pret_unitar', '$pret_vanzare', 'SEMIFABRICATE','$nume_material','$cota_tva_material','$id_retur')";
                     try {
                         $pdo->exec($iesire_bc_produs_finit_vazut_ca_materie_sql);
                     } catch(PDOException $e) {
                         echo $iesire_bc_produs_finit_vazut_ca_materie_sql . "<br>" . $e->getMessage();
                     }
          
          } else {
          // Dacă materia nu este în gestiunea "PRODUSE FINITE",
          // se inserează mișcarea pentru ea direct.
          $iessqll = "INSERT INTO $tabel_final_miscari
                   (data, cod_p, cantitate_misc, tip_miscare, fel_doc, nr_doc, nr_nota, produs_obtinut, pu, pret_vanzare, gestiune,denumire_produs,cota_tva,id_retur)
                   VALUES ('$data_bon','$c_m','$qt_m', 'O', 'BC', '$ultim_bc', '$nr_bon', '$prod', '$pret_unitar', '$pret_vanzare', '$denumire_gestiune_material','$nume_material','$cota_tva_material','$id_retur')";
          try {
           $pdo->exec($iessqll);
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
                      $bt_sql = "insert into $tabel_final_miscari(data,cod_p,cantitate_misc,tip_miscare,fel_doc,nr_doc,nr_nota,pu,pret_vanzare,gestiune,denumire_produs,cota_tva,id_retur) values('$data_bon','$prod','$qt','I','BT','$ultim_bt','$nr_bon','$pret_unitar_produs_selectat','$pret_vanzare_produs_selectat','$gest','$nume_produs','$cota_tva_produs_vandut','$id_retur');";    
              try{
          $pdo->exec($bt_sql) or die(print_r($pdo->errorInfo(), true));   
          }catch(PDOException $e)
              {
              echo $bt_sql . "<br>" . $e->getMessage();
              } 
                     $iessql = "insert into $tabel_final_miscari(data,cod_p,cantitate_misc,tip_miscare,fel_doc,nr_doc,pu,pret_vanzare,gestiune,denumire_produs,cota_tva,id_retur) values('$data_bon','$prod','$qt','O','BR','$nr_bon','$pret_unitar_produs_selectat','$pret_vanzare_produs_selectat','$gest','$nume_produs','$cota_tva_produs_vandut',$id_retur);";   
              try{
          $pdo->exec($iessql) or die(print_r($pdo->errorInfo(), true));   
          }catch(PDOException $e)
              {
              echo $iessql . "<br>" . $e->getMessage();
              }
          
          
          
          
          
             }
             // daca produsul ce se vinde nu este produs finit atunci probabil e marfa si else
             else{
                     $iessql = "insert into $tabel_final_miscari(data,cod_p,cantitate_misc,tip_miscare,fel_doc,nr_doc,pu,pret_vanzare,gestiune,denumire_produs,cota_tva,id_retur) values('$data_bon','$prod','$qt','O','BR','$nr_bon','$pret_unitar_produs_selectat','$pret_vanzare_produs_selectat','$gest','$nume_produs','$cota_tva_produs_vandut','$id_retur');";   
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
// Actualizează tabela 'retururi' pentru a marca returul ca fiind consumat
$update_sql = "UPDATE retururi SET consumat = 1 WHERE id_retur = $id_retur";
try {
    $pdo->exec($update_sql);
} catch(PDOException $e) {
    echo json_encode(array('status' => 'error', 'message' => "Eroare la actualizarea statusului: " . $e->getMessage()));
    exit;
}

          echo json_encode(array('status' => 'success'));

}

?>