<?php  include('session.php');
	$title = 'Factura noua';
  include 'header.php';
	
?>

	
	   
				
<!-- about -->

<style>.table-bordered{
text-align:center;
}</style>
		
	<h3 align="center">Factura Noua</h3>
	
	 <div class="card mb-3">
        <div class="card-header">
          <i class="fa fa-table"></i> Antetul Facturii</div>
        <div class="card-body">
          <div class="table-responsive">

         
<form action='<?php $_PHP_SELF?>' method="post">
<table class='table table-bordered'> 
  <tr>
  <td><h4>Client:</h4></td><td>
  <?php
	 
$tsql = "SELECT * from $tabel_final_terti where tip_tert='client'";    
$stmt = $pdo->prepare($tsql);  
$stmt->execute(); 

echo "<select class='form-control' name='client'>"; 
 
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)){ 
                      $den_tert=$row['denumire'];
			             $cod_tert=$row['cod_tert']; 
              echo "<option value='$cod_tert'>$den_tert</option>";    
             
			

}
echo "</select>";

echo "</td></tr>"; 

$ultim_fact_sql = "SELECT max(nrfactura) as ultim_fact from $tabel_final_facturi";    
$ultim_fact_stmt = $pdo->prepare($ultim_fact_sql);  
$ultim_fact_stmt->execute(); 


while ($row = $ultim_fact_stmt->fetch(PDO::FETCH_ASSOC)){ 
                      $utlim_fact=$row['ultim_fact']+1;
}


?>
<tr> 	 
  <td><h4>Numarul Facturii:</h4></td>
   <td><input class="form-control" type="number" value="<?php echo $ultim_fact;?>" name="nr_factura" ></td></tr> 
   
<tr> 
 <td><h4>Seria Facturii:</h4></td>
 <td><input class="form-control" type="text" name="serie_factura" maxlength="11">
 </td></tr>
 
     <td><h4>Data Facturii:</h4></td>
   <td><input class="form-control" type="date" value="<?php echo date('Y-m-d');?>" name="data_factura" ></td></tr> 
    <tr><td><input class='btn btn-primary btn-block' type="submit" name="date_factura" value="Continuati"> </form></td>
 </tr></table>
 
 <?php 
if(isset($_POST['date_factura'])){
//  verifica daca exista date transmise 
if ($_POST['serie_factura'] != "" &&
$_POST['nr_factura'] != '' &&
$_POST['data_factura'] != '' )   
{ 	$gestionar=$_SESSION["login_user"];
     // preia datele din formular
	     $nr_factura = $_POST['nr_factura'];
    $serie_factura = $_POST['serie_factura'];
	$client = $_POST['client'];
		date_default_timezone_set('UTC');
	$data_factura=date($_POST['data_factura']);
	$_SESSION['cod_client']=$client;
	$_SESSION['serie_factura']=$serie_factura;
	$_SESSION['nr_factura']=$nr_factura;
	$_SESSION['data_factura']=$data_factura;
     // formeaza si executa queryﾂｭul de inserare in baza de date 	 
$sql="insert into $tabel_final_facturi(nrfactura,data_factura,cod_client,serie,gestionar) values('$nr_factura','$data_factura','$client','$serie_factura','$gestionar')"; 	 

try{
$pdo->exec($sql) or die("<h3>Exista deja un factura cu acest numar.Va rugam introduceti alte date...</h3>"); 
   
    // afiseaza un mesaj de succes 
			printf("<script>location.href='factura.php'</script>");
		  
}catch(PDOException $e)
    {
    echo $sql . "<br>" . $e->getMessage();
    }
}
else 
	echo"<h3>Atentie completati toate campurile</h3>";
}
else {echo"<h3 align='center'>Completati antetul facturii</h3>";}

 
  ?>

 
 <!-- InstanceEndEditable -->




  </div>
        </div>
        
      </div>



<?php 
	require "footer.php";
?>