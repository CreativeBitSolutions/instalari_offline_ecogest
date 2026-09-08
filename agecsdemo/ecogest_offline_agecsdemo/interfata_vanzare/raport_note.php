<?php

	include 'database_connection.php';
  
	$title = 'Raport';
  
  include 'header.php';
	
?>

<!-- Breadcrumbs-->



  
<!-- Example DataTables Card-->
<div class="card mb-3">
  <div class="card-header">
    <i class="fa fa-map-marker"></i> Raport Nomenclator</div>
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
 <input type="text" id="myInput"  style="width:100%;" onkeyup="myFunctions()" placeholder="Cauta dupa denumirea produsului..."> </br>  </br>


  <div class='one'>       
  <table id="myTable" style='margin:0'>
  <tbody>
      
        <?php 
     
$test_sql = "SELECT 
  $tabel_final_note.tva_colectata, 
  $tabel_final_note.valoare_vanzare_cu_tva, 
  $tabel_final_note.cif_client, 
  $tabel_final_note.cod_masa, 
  $tabel_final_note.nrbon, 
  $tabel_final_note.status, 
  $tabel_final_note.operator, 
  $tabel_final_admins.admin_firstname, 
  $tabel_final_admins.admin_lastname 
FROM 
  $tabel_final_note 
INNER JOIN 
  $tabel_final_admins 
ON 
  $tabel_final_note.operator = $tabel_final_admins.admin_id 
WHERE 
  $tabel_final_note.status = 'F'
ORDER BY 
  $tabel_final_note.nrbon DESC
LIMIT 500;
";    
$test_stm = $pdo->prepare($test_sql);  
$test_stm->execute(); 
while ($row = $test_stm->fetch(PDO::FETCH_ASSOC)){ 
    $nrbon=$row['nrbon'];
    $cif_client=$row['cif_client'];
      $firstname=$row['admin_firstname'];
            $lastname=$row['admin_lastname'];
$val_vanz_cu_tva=$row['valoare_vanzare_cu_tva'];
$val_tva=$row['tva_colectata'];
 echo"<tr>
   <td><form method='POST'><button style='width:100%; font-size:1em; disabled; height:auto; white-space: normal;
' class='btn btn-primary btn-block' type='submit' name='$nrbon'>Nota $nrbon <br>Operator: $firstname  $lastname<br>Suma: $val_vanz_cu_tva<br>Din care TVA : $val_tva </button></form></td>
  </tr> ";
  
  if(isset($_POST[$nrbon])){
$_SESSION['nr_bon']=$nrbon;
$_SESSION['cif_client']=$cif_client;

printf("<script>location.href='raport_note.php'</script>");

  }
}

?>
        </tbody>  </table></div> 
               <div class='two'> <object width="100%" height="400" data="vizualizare_nota.php"></object></div>

          
          </div>
      </div>
      

  </div>
      </div>
<?php include 'footer.php'; ?>