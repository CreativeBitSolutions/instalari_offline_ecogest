<?php

	include 'database_connection.php';
  
	$title = 'Raport';
  
  include 'header.php';
	
?>

<!-- Breadcrumbs-->



  
<!-- Example DataTables Card-->
<div class="card mb-3">
  <div class="card-header">
    <i class="fa fa-map-marker"></i> Raport Monetare</div>
  <div class="card-body">
          

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


  <div class='one'>       
  <table id="myTable" style='margin:0'>
  <tbody>
      
        <?php 
     
$test_sql = "SELECT cod_monetar,suma,data_monetar,ora_monetar FROM $tabel_final_monetar where status='F'";    
$test_stm = $pdo->prepare($test_sql);  
$test_stm->execute(); 
while ($row = $test_stm->fetch(PDO::FETCH_ASSOC)){ 
$data_monetar=date("d-m-Y", strtotime($row['data_monetar']));
    $ora_monetar=$row['ora_monetar'];
    $suma=$row['suma'];
    $cod_monetar=$row['cod_monetar'];
 echo"<tr>
   <td><form method='POST'><button style='width:100%; font-size:1em; disabled; height:auto; white-space: normal;
' class='btn btn-primary btn-block' type='submit' name='$cod_monetar'>Monetarul de la data: </br>$data_monetar ora $ora_monetar </button></form></td>
  </tr> ";
  
  if(isset($_POST[$cod_monetar])){
      
$_SESSION['cod_monetar']=$cod_monetar;


printf("<script>location.href='raport_monetare.php'</script>");

  }
}

?>
        </tbody>  </table></div> 
               <div class='two'> <object width="100%" height="400" data="listeaza_monetar.php"></object></div>

          
          </div>
      </div>
      

  </div>
      </div>
<?php include 'footer.php'; ?>