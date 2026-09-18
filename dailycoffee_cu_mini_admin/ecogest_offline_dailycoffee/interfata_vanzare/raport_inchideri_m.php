<?php

	include 'database_connection.php';
  
	$title = 'Raport';
  
  include 'header.php';
	
?>

<!-- Breadcrumbs-->



  
<!-- Example DataTables Card-->
<div class="card mb-3">
  <div class="card-header">
    <i class="fa fa-map-marker"></i> Raport Închideri de Zi</div>
  <div class="card-body">
          
<script>
function myFunctions() {
  // Declare variables
  var input, filter, table, tr, td, i;
  input = document.getElementById("myInput");
  filter = input.value.toUpperCase();
  table = document.getElementById("myTable");
  tr = table.getElementsByTagName("tr");

  // Loop through all table rows, and hide those who don't match the search query
  for (i = 0; i < tr.length; i++) {
    td = tr[i].getElementsByTagName("td")[0];
    if (td) {
      if (td.innerHTML.toUpperCase().indexOf(filter) > -1) {
        tr[i].style.display = "";
      } else {
        tr[i].style.display = "none";
      }
    }
  }
}

</script>
          <style>
 

.one {
    width: 20%;
    height: auto;
    float: left;
    margin:0;
    padding:0;
}
.two {
    width:80%;
    float:right;
    height: auto;
    margin:0;
    padding:0;
}

tbody {
    display:block;
    height: 400px;       
    overflow-y: auto;    /* Trigger vertical scroll    */
    overflow-x: hidden;  /* Hide the horizontal scroll */
}
</style>
 <input type="text" id="myInput"  style="width:100%;" onkeyup="myFunctions()" placeholder="Cauta închidere..."> </br>  </br>


  <div class='one'>       
  <table id="myTable" style='margin:0'>
  <tbody>
      
             <?php 

$test_sql = "SELECT $tabel_final_inchideri_m.operator,$tabel_final_inchideri_m.cod_inchidere,$tabel_final_inchideri_m.data_inchiderii,$tabel_final_inchideri_m.ora_inchiderii,$tabel_final_inchideri_m.tva_colectata,$tabel_final_inchideri_m.valoare_cu_tva,$tabel_final_admins.admin_firstname,admin_lastname FROM $tabel_final_inchideri_m inner join $tabel_final_admins on $tabel_final_inchideri_m.operator=$tabel_final_admins.admin_id";    
$test_stm = $pdo->prepare($test_sql);  
$test_stm->execute(); 
while ($row = $test_stm->fetch(PDO::FETCH_ASSOC)){ 
$data_inchiderii=date("d-m-Y", strtotime($row['data_inchiderii']));
    $ora_inchiderii=$row['ora_inchiderii'];
    $valoare_cu_tva=$row['valoare_cu_tva'];
    $cod_inchidere=$row['cod_inchidere'];
        $admin_firstname=$row['admin_firstname'];
    $admin_lastname=$row['admin_lastname'];
    $operator=$row['operator'];
    $disc_tot_sql = "SELECT sum($tabel_final_bonuri.discount) as total_discount FROM $tabel_final_bonuri where cod_inchidere='$cod_inchidere'; ";    
$disc_tot_stmt = $pdo->prepare($disc_tot_sql);  
$disc_tot_stmt->execute();

while ($row = $disc_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_discount=$row['total_discount'];
}
    $valoare_inchidere=$valoare_cu_tva-$total_discount;

    
 echo"<tr>
   <td><form method='POST'><button style='width:100%; font-size:1em; disabled; height:auto; white-space: normal;
' class='btn btn-primary btn-block' type='submit' name='$cod_inchidere'>Închiderea de la : </br>$data_inchiderii ora $ora_inchiderii </br> Valoare: $valoare_inchidere </br>Operator: $admin_firstname $admin_lastname </button></form></td>
  </tr> ";
  
  if(isset($_POST[$cod_inchidere])){
      
$_SESSION['cod_inchidere']=$cod_inchidere;
$_SESSION['operator']=$operator;


printf("<script>location.href='raport_inchideri_m.php'</script>");

  }
}

?>
        </tbody>  </table></div> 
               <div class='two'> <div style='float:left;width:47%;margin-right:20px'><h3 style='text-align:center'>Raport închidere</h3><object  width="100%" height="400" data="vizualizare_inchidere_m.php"></object></div>
               <div style='float:left;width:47%' ><h3 style='text-align:center'>Raport produse vândute</h3><object width="100%" height="400" data="vizualizare_produse_vandute_pe_inchidere_m.php"></object></div></div>

          
          </div>
      </div>
      

  </div>
      </div>
<?php include 'footer.php'; ?>