<?php
//connect to the database 
	include 'database_connection.php';
  
  $title = 'Panou de control';
  
  include 'header.php';
  unset($_SESSION['nr_factura']);
  unset($_SESSION['nr_nir']);


  
?>

<!--
<script>
    
var timer = null;

function goAway() {
    clearTimeout(timer);
    timer = setTimeout(function() {
 
        window.location = 'index.php';
    }, 20000);
}

window.addEventListener('mousemove', goAway, true);
window.addEventListener('mousedown', goAway, true);
window.addEventListener('keydown', goAway, true);
window.addEventListener('scroll', goAway, true);

goAway();  // start the first timer off
</script> -->
      <!-- Breadcrumbs-->
      <ol class="breadcrumb">
        
        <li class="breadcrumb-item">Date Financiare</li>
      </ol>

			
        

      <!-- Icon Cards-->
      <div class="row">
        <div class="col-xl-3 col-sm-6 mb-3">
          <div class="card text-white bg-primary o-hidden h-100">
            <div class="card-body">
              <div class="card-body-icon">

               <i class="fa fa-money"></i>
              </div>
              <div class="mr-5">
   <?php 
       $sume_sertar_sql1 = "SELECT sum(numerar) as total_numerar,sum(rest) as total_rest,sum(card) as total_card,sum(tichete) as total_tichete,sum(protocol) as total_protocol from $tabel_final_note where cod_inchidere!=0;";    
$sume_sertar_stmt1 = $pdo->prepare($sume_sertar_sql1);  
$sume_sertar_stmt1->execute(); 
while ($row = $sume_sertar_stmt1->fetch(PDO::FETCH_ASSOC)){ 

$total_numerar=$row['total_numerar']-$row['total_rest'];
$total_card=$row['total_card'];
$total_tichete=$row['total_tichete'];
$total_protocol=$row['total_protocol'];
$total_sertar = $total_numerar+$total_card+$total_tichete+$total_protocol;
}
   
   
// print the number of Registered Customers on the blue card
	echo "<b>Valoare vanzari: ".$total_sertar."</b>
 <br>Numerar: ".$total_numerar."
 <br>Card: ".$total_card."
 <br>Tichete: ".$total_tichete."
 <br>Protocol: ".$total_protocol
     ?> 
         </div>
            </div>
           
          </div>
        </div>
        
        <div class="col-xl-3 col-sm-6 mb-3">
          <div class="card text-white bg-danger o-hidden h-100">
            <div class="card-body">
              <div class="card-body-icon">
                <i class="fa fa-medium"></i>
              </div>
              <div class="mr-5"> 
              <?php
              //// select the rows in the table "reviews" where the status is pending  
             
       $sume_sertar_sql1 = "SELECT AVG(numerar) as medie_numerar,AVG(card) as medie_card,AVG(tichete) as medie_tichete,AVG(protocol) as medie_protocol from $tabel_final_note where cod_inchidere!=0;";    
$sume_sertar_stmt1 = $pdo->prepare($sume_sertar_sql1);  
$sume_sertar_stmt1->execute(); 
while ($row = $sume_sertar_stmt1->fetch(PDO::FETCH_ASSOC)){ 

$medie_numerar=round($row['medie_numerar'],2);
$medie_card=round($row['medie_card'],2);
$medie_tichete=round($row['medie_tichete'],2);
$medie_vanzari=$medie_numerar+$medie_card+$medie_tichete;
}
 // print the number of Registered Customers on the yellow card

	echo "<b>Încasări medii: ".$medie_vanzari."</b>
<br>Numerar: ".$medie_numerar."
<br>Card: ".$medie_card."
<br>Tichete: ".$medie_tichete;
     ?>  </div>
            </div>
           
          </div>
        </div>
        <div class="col-xl-3 col-sm-6 mb-3">
          <div class="card text-white bg-success o-hidden h-100">
            <div class="card-body">
              <div class="card-body-icon">
                <i class="fa fa-fw fa-shopping-cart"></i>
              </div>
              <div class="mr-5">
              <?php
               //// select the rows in the table "comenzi" where the status is pending  
              $comenzi="SELECT $tabel_final_nomenclator.den_p,$tabel_final_nomenclator.den_p,$tabel_final_stoc.cantitate,count($tabel_final_det_note.cod_p) AS nr_$tabel_final_vanzari from $tabel_final_det_note INNER JOIN $tabel_final_nomenclator on $tabel_final_det_note.cod_p=$tabel_final_nomenclator.cod_p inner join $tabel_final_stoc on $tabel_final_nomenclator.cod_p=$tabel_final_stoc.cod_p GROUP BY $tabel_final_nomenclator.den_p ORDER BY nr_$tabel_final_vanzari DESC LIMIT 1";
 $book=$pdo->prepare($comenzi);
	  $book->execute();
	  // count the number of rows of the result table and store it in variable $bookcount
					while ($row = $book->fetch()) {
					   $produs_pop=$row['den_p'];
					   	$nr_vanz=$row['nr_$tabel_final_vanzari'];
					   	$stoc=round($row['cantitate']);
					   $um=$row['um'];
					}
    // print the number of Registered Customers on the green card
echo "Cel Mai Vândut Produs:</br><b>".$produs_pop."</b></br>
".$nr_vanz." de unități vândute</br>Stoc: ".$stoc ." unități";
 ?> </div>
            </div>
            
          </div>
        </div>
        <div class="col-xl-3 col-sm-6 mb-3">
          <div class="card text-white bg-dark o-hidden h-100">
            <div class="card-body">
              <div class="card-body-icon">
                <i class="fa fa-fw fa-users"></i>
              </div>
              <div class="mr-5">
              <?php
                           // select all rows in the  table "abonati"

              $users="SELECT $tabel_final_note.operator from $tabel_final_det_note inner join $tabel_final_note on $tabel_final_det_note.nr_bon=$tabel_final_note.nrbon where nr_bon in (select nrbon from $tabel_final_note where $tabel_final_note.status='S') group by $tabel_final_note.operator";
 $abonati=$pdo->prepare($users);
	  $abonati->execute();
	  	  $scount=$abonati->rowCount();

	  $users2="SELECT $tabel_final_bonuri.operator FROM $tabel_final_det_bonuri inner join $tabel_final_bonuri on $tabel_final_det_bonuri.nr_bon=$tabel_final_bonuri.nrbon where nr_bon in (select nrbon FROM $tabel_final_bonuri where $tabel_final_bonuri.status='S') group by $tabel_final_bonuri.operator";
 $abonati2=$pdo->prepare($users2);
	  $abonati2->execute();
	  	  $scount2=$abonati2->rowCount();
$scountt=$scount+$scount2;
	  		
    // count the number of rows of the result table and store it in variable $scount
// print the number of abonati on the red card

if($scountt==0){echo "Niciun angajat activ";}elseif($scountt==1){
	echo $scountt." angajat activ!"; }else{	echo $scountt." angajați activi!";}echo "</br>";			while ($row = $abonati->fetch()) {
	    $opr=$row['operator'];
	    $op_sql="SELECT admin_firstname,admin_lastname FROM $tabel_final_admins where admin_id='$opr';";
 $op_stmt=$pdo->prepare($op_sql);
	  $op_stmt->execute();
														    						while ($rrrow = $op_stmt->fetch()) {
$f_n=$rrrow['admin_firstname'];
$l_n=$rrrow['admin_lastname'];
														    						}				    						    

echo $f_n.' '.$l_n.'</br>';}
		while ($row = $abonati2->fetch()) {
	    $opr=$row['operator'];
	    $op_sql="SELECT admin_firstname,admin_lastname FROM $tabel_final_admins where admin_id='$opr';";
 $op_stmt=$pdo->prepare($op_sql);
	  $op_stmt->execute();
														    						while ($rrrow2 = $op_stmt->fetch()) {
$f_n=$rrrow2['admin_firstname'];
$l_n=$rrrow2['admin_lastname'];
														    						}				    						    

echo $f_n.' '.$l_n.'</br>';}
 
 ?></div>
            </div>
           
          </div>
        </div>
      </div>
      <!-- Area Chart Example-->
      
      <div class="row">
          
           <?php 
   $m_d_osp_sql="SELECT $tabel_final_note.operator from $tabel_final_det_note inner join $tabel_final_note on $tabel_final_det_note.nr_bon=$tabel_final_note.nrbon where nr_bon in (select nrbon from $tabel_final_note where $tabel_final_note.status='S') group by $tabel_final_note.operator";
 $m_d_osp_stmt=$pdo->prepare($m_d_osp_sql);
	  $m_d_osp_stmt->execute();
	  $m_d_osp_stmt_count=$m_d_osp_stmt->rowCount();

		
   		
									if($m_d_osp_stmt_count>0){ 
									    						while ($row = $m_d_osp_stmt->fetch()) {
									    									 $op=$row['operator'];
			    
$op_sql="SELECT admin_firstname,admin_lastname FROM $tabel_final_admins where admin_id='$op';";
 $op_stmt=$pdo->prepare($op_sql);
	  $op_stmt->execute();
														    						while ($row = $op_stmt->fetch()) {
$admin_firstname=$row['admin_firstname'];
$admin_lastname=$row['admin_lastname'];
														    						}				    						    


                    echo '   <div class="col-lg-4">
          <!-- Example Pie Chart Card-->
          
          <!-- Example Notifications Card-->
          <div class="card mb-3">
            <div class="card-header">
              <i class="fa fa-bell-o"></i>Mese deschise de '.$admin_firstname.' '.$admin_lastname.'</div>
            <div class="list-group list-group-flush small">';
$note_op_sql="select $tabel_final_note.nrbon,$tabel_final_note.cod_masa from $tabel_final_det_note inner join $tabel_final_note on $tabel_final_det_note.nr_bon=$tabel_final_note.nrbon where nr_bon in (select nrbon from $tabel_final_note where $tabel_final_note.status='S') and $tabel_final_note.operator='$op' group by $tabel_final_note.nrbon;";
 $note_op_stmt=$pdo->prepare($note_op_sql);
	  $note_op_stmt->execute();
														    						while ($row = $note_op_stmt->fetch()) {
														    						    $nota=$row['nrbon'];
														    						    $masa=$row['cod_masa'];
														    						 
             echo '<a class="list-group-item list-group-item-action">
                <div class="media">
                  <div class="media-body">
					<i class="fa fa-fw fa-pencil"></i>

                    <strong>'.Nota.' '.$nota;     if($masa==9999){
echo ' La Pachet ';
                    }
                    else{
                        echo ' Masa: ' .$masa;
                    }echo '</strong>';
                    $prod_nota_sql="SELECT $tabel_final_det_note.data,$tabel_final_det_note.ora,$tabel_final_det_note.preparat,$tabel_final_det_note.pachet,$tabel_final_det_note.discount,$tabel_final_det_note.cod_p,$tabel_final_nomenclator.den_p,cote_tva.cota,$tabel_final_nomenclator.um,$tabel_final_det_note.cantitate,$tabel_final_det_note.tva_col,$tabel_final_det_note.pret_vanzare,$tabel_final_det_note.valoare_vanzare,$tabel_final_det_note.valoare_vanzare_cu_tva,$tabel_final_det_note.id_vanz from $tabel_final_nomenclator inner join $tabel_final_det_note on $tabel_final_det_note.cod_p=$tabel_final_nomenclator.cod_p inner join cote_tva on $tabel_final_nomenclator.cota_tva=cote_tva.id where nr_bon='$nota' order by $tabel_final_det_note.id_vanz,$tabel_final_nomenclator.den_p;";
 $prod_nota_stmt=$pdo->prepare($prod_nota_sql);
	  $prod_nota_stmt->execute();
														    						while ($row = $prod_nota_stmt->fetch()) {
														    						    
														    						    
														    	$codul_produsului=$row['cod_p'];
														    	 $data=date("d-m-Y", strtotime($row['data']));
														    	  $ora=date("H:i", strtotime($row['ora']));
$produs=$row['den_p'];
$cantitate=$row['cantitate'];
$tva_col=$row['tva_col'];
$pret_vanzare=$row['pret_vanzare'];
$valoare_vanzare=$row['valoare_vanzare'];
$valoare_vanzare_c_tva=round(($row['valoare_vanzare_cu_tva'])*100)/100;
$cota=$row['cota'];
$d=$row['discount'];
$v_fin=$valoare_vanzare_c_tva-$d;
$pch=$row['pachet'];
$prep=$row['preparat'];	
$um=$row['um'];
														    						    
                    echo '<div class="text smaller">'.$produs; if($pch!=1){ echo ' (Servit la masa) ';} else{echo ' (La pachet) ';} echo  $cantitate.'  '.$um.' '; if($d==0){ echo $v_fin;} elseif ($d!=0){echo $valoare_vanzare_c_tva.' - '.$d.' = '.$v_fin;} echo' LEI; Data: '.$data .' Ora: '.$ora .'</div>';}
                  echo '</div>
                </div>
              </a>  ';
              }
              echo'
          </div>
        </div></div> ';}
              
									}
              
     	
              ?> 
         

          
          
                   	<?php
   
					$bsql = "SELECT nrfactura,data_factura from $tabel_final_facturi where status='S'";    
$bstmt = $pdo->prepare($bsql);  
$bstmt->execute();  
$bcount=$bstmt->rowCount();
   		
									if($bcount!=0){       
            echo ' <div class="col-lg-4">
          <!-- Example Pie Chart Card-->
          
          <!-- Example Notifications Card-->
          <div class="card mb-3">
            <div class="card-header">
              <i class="fa fa-bell-o"></i>Facturi nefinalizate </div>
            <div class="list-group list-group-flush small">';

						while ($row = $bstmt->fetch()) {
						      $nrfactura=$row['nrfactura'];
						      					   $data_facturii=date("d-m-Y", strtotime($row['data_factura']));
       	// display the first 5 rows from the result table of the bsql query

             echo '<a href="detalii_factura.php?f='.$nrfactura.'" class="list-group-item list-group-item-action">
                <div class="media">
                  <div class="media-body">
					<i class="fa fa-fw fa-pencil"></i>

                    <strong>'.Factura.' '.$nrfactura.'</strong>
                    <div class="text-muted smaller"> din '.$data_facturii.'</div>
                  </div>
                </div>
              </a> ';}
           echo '
            </div>
            <div class="card-footer medium text-muted"><a class="dropdown-item medium" href="facturi.php">Vedeti toate facturile...</a></div>
          </div>
        </div>';

         }    ?>
         
                	<?php
               // select latest 5 rows in the table "comenzi"

					$bsql = "SELECT data_nir,nr_nir from $tabel_final_nir where status='S'";    
$bstmt = $pdo->prepare($bsql);  
$bstmt->execute();  
$bcount=$bstmt->rowCount();
   		
					if($bcount!=0){
         
       echo '<div class="col-lg-4">
          <!-- Example Pie Chart Card-->
          
          <!-- Example Notifications Card-->
          <div class="card mb-3">
            <div class="card-header">
              <i class="fa fa-bell-o"></i>Note de recepție nefinalizate</div>
            <div class="list-group list-group-flush small">';
         
     
						while ($row = $bstmt->fetch()) {
						      $nr_nir=$row['nr_nir'];
						      						      						      					   $data_nir=date("d-m-Y", strtotime($row['data_nir']));


       	// display the first 5 rows from the result table of the bsql query

             echo '<a href="detalii_nir.php?n='.$nr_nir.'" class="list-group-item list-group-item-action">
                <div class="media">
                  <div class="media-body">
					<i class="fa fa-fw fa-pencil"></i>

                    <strong>Nota de recepție '.$nr_nir.'</strong>
                    <div class="text-muted smaller"> din '.$data_nir.'</div>
                  </div>
                </div>
              </a> ';}
               echo '
            </div>
            <div class="card-footer medium text-muted"><a class="dropdown-item medium" href="note_de_receptie.php">Vedeti toate notele de recepție...</a></div>
          </div>
        </div> ';}
         ?>
        <div class="col-lg-4">
          <!-- Example Pie Chart Card-->
          
          <!-- Example Notifications Card-->
          <div class="card mb-3">
            <div class="card-header">
              <i class="fa fa-bell-o"></i> Produse cu Stoc Critic</div>
            <div class="list-group list-group-flush small">
                	<?php
               // select latest 5 rows in the table "comenzi"

					$bsqlz = "SELECT  $tabel_final_stoc.cantitate,$tabel_final_nomenclator.den_p FROM $tabel_final_stoc INNER JOIN $tabel_final_nomenclator on $tabel_final_stoc.cod_p=$tabel_final_nomenclator.cod_p where $tabel_final_stoc.cantitate<=$tabel_final_nomenclator.stoc_critic and gestiune!='PF' order by $tabel_final_stoc.cantitate LIMIT 5";    
$bstmtz = $pdo->prepare($bsqlz);  
$bstmtz->execute();  
$bzcount=$bstmtz->rowCount();
   		  $date_firma = "SELECT mod_listare,vanzare_sub_stoc from $tabel_final_date_firma";    
$date_firma_stmt = $pdo->prepare($date_firma);  
$date_firma_stmt->execute(); 
while ($row = $date_firma_stmt->fetch(PDO::FETCH_ASSOC)){ 

$_SESSION['vanzare_sub_stoc']=$row['vanzare_sub_stoc'];
}
if($_SESSION['vanzare_sub_stoc']==1){
    $bzcount=0;
}
					if($bzcount==0){
     	echo "<h6 style='margin:2em;'>Niciun produs nu se afla la nivelul stocului critic!</h6>"; }else{
						while ($row = $bstmtz->fetch()) {
						      $cantitate=$row['cantitate'];
						     $den_p=$row['den_p'];
    
       	// display the first 5 rows from the result table of the bsql query

             echo '<a class="list-group-item list-group-item-action">
                <div class="media">
                  <div class="media-body">
				
					
				
                    <strong>Denumire produs: '.$den_p.' <br>Stoc: '.$cantitate.'</strong> 
                  </div>
                </div>
              </a> ';}
     	}
              ?> 
            </div>
            <div class="card-footer medium text-muted"><a class="dropdown-item medium" href="nomenclator.php">Vedeți toate produsele...</a></div>
          </div>
        </div>
      </div>
      <!-- Example DataTables Card-->
      
      </div>
    </div>

	<!-- /.container-fluid-->
	<!-- /.content-wrapper-->
	<footer class="sticky-footer">
		<div class="container">
			<div class="text-center">
				<small>© 2019 AGECS</small>
			</div>
		</div>
	</footer>
	<!-- Scroll to Top Button-->
	<a class="scroll-to-top rounded" href="#page-top">
		<i class="fa fa-angle-up"></i>
	</a>
	<!-- Logout Modal-->

	<!-- Bootstrap core JavaScript-->
	<script src="vendor/jquery/jquery.min.js"></script>
	<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
	<!-- Core plugin JavaScript-->
	<script src="vendor/jquery-easing/jquery.easing.min.js"></script>
	<!-- Page level plugin JavaScript-->
	<script src="vendor/datatables/jquery.dataTables.js"></script>
	<script src="vendor/datatables/dataTables.bootstrap4.js"></script>
	<!-- Custom scripts for all pages-->
	<script src="js/sb-admin.min.js"></script>
	<!-- Custom scripts for this page-->
	<script src="js/sb-admin-datatables.min.js"></script>
	<script src="js/sb-admin-charts.min.js"></script>
</div>
</body>

</html>
