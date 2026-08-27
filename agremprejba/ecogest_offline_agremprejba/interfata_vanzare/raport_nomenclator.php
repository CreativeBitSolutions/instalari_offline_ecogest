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
     
$test_sql = "SELECT cod_p,den_p from $tabel_final_nomenclator ";    
$test_stm = $pdo->prepare($test_sql);  
$test_stm->execute(); 
while ($row = $test_stm->fetch(PDO::FETCH_ASSOC)){ 
    $den_p=$row['den_p'];
    $cod_p=$row['cod_p'];
 $um=$row['cod_p'];
 echo"<tr>
   <td><form method='POST'><button style='width:100%; font-size:1em; disabled; height:auto; white-space: normal;
' class='btn btn-primary btn-block' type='submit' name='$cod_p'>$den_p</button></form></td>
  </tr> ";
  
  if(isset($_POST[$cod_p])){
      
$_SESSION['cod_p']=$cod_p;
$_SESSION['den_p']=$den_p;
$_SESSION['pret_p']=$pret;
$_SESSION['um_p']=$um;
$dsql = "SELECT * from $tabel_final_date_firma";    
$dstmt = $pdo->prepare($dsql);  
$dstmt->execute(); 
while ($row = $dstmt->fetch(PDO::FETCH_ASSOC)){ 
                      $_SESSION['den_ent']=$row['den_ent'];
			            
			

}
printf("<script>location.href='raport_nomenclator.php'</script>");

  }
}

?>
        </tbody>  </table></div> 
               <div class='two'> <object width="100%" height="400" data="listeaza_fisa_mag.php"></object></div>

          
          </div>
      </div>
      

  </div>
      </div>
<?php include 'footer.php'; ?>