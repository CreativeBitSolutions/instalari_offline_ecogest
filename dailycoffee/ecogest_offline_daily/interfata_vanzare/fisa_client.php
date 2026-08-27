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

                       
<script>

$(document).ready(function(){ $(".js-example-basic-single").select2();
$("#selectInp").val(999);$("#toate").trigger("click")});function loadStates(){var formName='get_state';var country=document[formName].categ.value;var xmlhttp=null;if(typeof XMLHttpRequest!='undefined'){xmlhttp=new XMLHttpRequest()}else if(typeof ActiveXObject!='undefined'){xmlhttp=new ActiveXObject('Microsoft.XMLHTTP')}else throw new Error('You browser doesn\'t support ajax');xmlhttp.open('GET','load_fact_client.php?categ='+country,!0);xmlhttp.onreadystatechange=function(){if(xmlhttp.readyState==4)
window.insertStates(xmlhttp)};xmlhttp.send(null)}
function insertStates(xhr){if(xhr.status==200){document.getElementById('states_container').innerHTML=xhr.responseText}else throw new Error('Server has encountered an error\n'+'Error code = '+xhr.status)}
</script>
 <form name="get_state">


<select id='selectInp' style="width:100%;" onclick='window.loadStates()' onchange='window.loadStates()' name="categ" class="js-example-basic-single"><option id='toate' value='999'>TOTI CLIENTII</option>
  <?php
	 
$tsql = "SELECT * from $tabel_final_terti where tip_tert='client'";    
$stmt = $pdo->prepare($tsql);  
$stmt->execute(); 
$prod_count=$stmt->rowCount(); 

      
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)){ 
                      $den_tert=$row['denumire'];
			             $cod_tert=$row['cod_tert']; 
              echo "<option value='$cod_tert'>$den_tert</option>";    
             
			

}

if(isset($_POST[$nrfactura])){
    
$_SESSION['nr_factura']=$nrfactura;
printf("<script>location.href='fisa_client.php'</script>");
	
}
?>

</select></form></br>
  <div class='one'>       
  <table id="myTable" style='margin:0'>
    <tbody id='states_container' class='tbody'>
 <?php 
 
    $test_sql = "SELECT nrfactura from $tabel_final_facturi";    
$test_stm = $pdo->prepare($test_sql);  
$test_stm->execute(); 
while ($row = $test_stm->fetch(PDO::FETCH_ASSOC)){ 
    $nrfactura=$row['nrfactura'];

     echo"<tr>
   <td  width='700'><form method='POST' ><button style='width:100%; font-size:1em; height:auto; white-space: normal;
' class='btn btn-primary btn-block' type='submit' name='$nrfactura'>Factura $nrfactura</button></form></td>
  </tr> ";  
       if(isset($_POST[$nrfactura])){
    
$_SESSION['nr_factura']=$row['nrfactura'];
printf("<script>location.href='fisa_client.php'</script>");
	
}
}
 ?>
</tbody>  </table></div> 
               <div class='two'> <object width="100%" height="600" data="listeaza_fact.php"></object></div>

          
          </div>
      </div>
      

  </div>
      </div>
<?php include 'footer.php'; ?>