<?php  include('session.php');
	$title = 'Chitanta noua';
  include 'header.php';
	
?>

	
	   
				
<!-- about -->

<style>.table-bordered{
text-align:center;
}</style>
		
	<h3 align="center">Chitanta Noua</h3>
	
	 <div class="card mb-3">
        <div class="card-header">
          <i class="fa fa-table"></i> Antetul Chitantei</div>
        <div class="card-body">
          <div class="table-responsive">

         
<form action='<?php $_PHP_SELF?>' method="post">
<table class='table table-bordered'> 

<tr> 	 
  <td><h4>Numarul Chitantei:</h4></td>
   <td><input class="form-control" type="number" name="nr_chitanta" ></td></tr> 
   
<tr> 
 <td><h4>Seria Chitantei:</h4></td>
 <td><input class="form-control" type="text" name="serie_chitanta" maxlength="11">
 </td></tr>
 <tr>
     <td><h4>Data Chitantei:</h4></td>
   <td><input class="form-control" type="date" name="data_chitanta" ></td></tr> 
    <tr>
     <td><h4>Factura:</h4></td>
<?php
	 
$tsql = "SELECT nrfactura from $tabel_final_facturi";    
$stmt = $pdo->prepare($tsql);  
$stmt->execute(); 

echo "<td><select class='form-control' name='factura'>"; 
 
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)){ 
                      $nrfactura=$row['nrfactura'];
              echo "<option value='$nrfactura'>Factura $nrfactura</option>";    
             
			

}
echo "</select>";

echo "</td></tr>"; 
?>    <tr><td><input class='btn btn-primary btn-block' type="submit" name="date_chitanta" value="Continuati"> </form></td>
 </tr></table>
 
 <?php 
if(isset($_POST['date_chitanta'])){
//  verifica daca exista date transmise 
if ($_POST['serie_chitanta'] != "" &&
$_POST['nr_chitanta'] != '' &&
$_POST['data_chitanta'] != '' )   
{ 
     // preia datele din formular
	     $nr_chitanta = $_POST['nr_chitanta'];
    $serie_chitanta = $_POST['serie_chitanta'];
		$factura= $_POST['factura'];

	 $psql2 = "SELECT sum($tabel_final_chitante.suma_numere) as total_chitante from $tabel_final_chitante where nrfact='$factura';";    
$pstmt2 = $pdo->prepare($psql2);  
$pstmt2->execute(); 
while ($row = $pstmt2->fetch(PDO::FETCH_ASSOC)){ 
                      $total_chitante=$row['total_chitante'];
			                          
}


if($total_chitante==5000) {
		   
$alerta="Nu se mai pot crea chitante pentru factura nr. ".$factura." ! ". " Suma maximă de 5000 de lei a fost atinsă!";
 echo '<script language="javascript">';
  echo 'var myvar = ';echo json_encode($alerta).';';

echo 'alert';echo'('.myvar.');';
echo '</script>';
	  printf("<script>location.href='client_chitanta.php'</script>");
		 }



	$_SESSION['factura'] = $_POST['factura'];
	
	
	
	$data_chitanta=date($_POST['data_chitanta']);
	$_SESSION['serie_chitanta']=$serie_chitanta;
	$_SESSION['nr_chitanta']=$nr_chitanta;
	$_SESSION['data_chitanta']=$data_chitanta;
	
	
	
	
	
     // formeaza si executa queryﾂｭul de inserare in baza de date 	 
$sql="insert into $tabel_final_chitante(nrchitanta,data_chitanta,serie,nrfact) values('$nr_chitanta','$data_chitanta','$serie_chitanta','$factura')"; 	 

try{
$pdo->exec($sql) or die("<h3>Exista deja o chitanta cu acest numar.Va rugam introduceti alte date...</h3>"); 
   
    // afiseaza un mesaj de succes 
			printf("<script>location.href='chitanta.php'</script>");
		  
}catch(PDOException $e)
    {
    echo $sql . "<br>" . $e->getMessage();
    }
}
else 
	echo"<h3>Atentie completati toate campurile</h3>";
}
else {echo"<h3 align='center'>Completati antetul chitantei</h3>";}

 
  ?>

 
 <!-- InstanceEndEditable -->




  </div>
        </div>
        
      </div>



<?php 
	require "footer.php";
?>