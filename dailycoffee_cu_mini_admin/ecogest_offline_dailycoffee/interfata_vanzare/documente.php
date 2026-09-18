<?php  include('session.php');
	$title = 'Documente';
  include 'header.php';
	
?>

	
	   
          
			
				
<!-- about -->

		<style>details summary{
	font-family:tahoma;
	
	
	margin:1ex 12pt;
	    font-size: 110%;
    margin-top: 12pt;
    margin-bottom: 0;
    padding-top: 3pt;
	color:#800000;
}
details summary:hover{
	cursor:pointer;
	font-size:150%;
}</style>
	<h3 align="center">Documente justificative</h3>
	
	 <div class="card mb-3">
        <div class="card-header">
          <i class="fa fa-table"></i> Lista documentelor justificative</div>
        <div class="card-body">
          <div class="table-responsive">
<details open><summary>
Note de recepție</summary>
<figure><?php
	 
$sql = "SELECT $tabel_final_nir.serie_doc_int,$tabel_final_nir.data_doc_int,$tabel_final_nir.nr_doc_int,$tabel_final_nir.id_nir,$tabel_final_nir.nr_nir,$tabel_final_nir.nr_doc_int,$tabel_final_nir.data_nir,$tabel_final_nir.val_nir_ftva,$tabel_final_terti.cod_tert,$tabel_final_terti.denumire from $tabel_final_nir inner join $tabel_final_terti on $tabel_final_terti.cod_tert=$tabel_final_nir.cod_tert;";    
$stmt = $conn->prepare($sql);  
$stmt->execute(); 

echo "<table class='table table-bordered'>"; 
echo" <thead><tr><th> Numar NIR</th><th>Numar factura </th><th>Data</th><th>Valoare</th><th>Furnizor</th><th></th><th></th></tr></thead>"; 
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)){  

$det_nir=$row['nr_nir']+99000;
$id_nir=$row['id_nir']+9000;
              echo"<tr>";    
              echo"<td>".$row['nr_nir']."</td><td>".$row['nr_doc_int']."</td><td>".$row['data_nir']."</td><td>".$row['val_nir_ftva']."</td><td>".$row['denumire']."<td><form method='post'><input class='btn btn-primary btn-block' type='submit' value='Detalii NIR' name='$det_nir'></form></td><td><form method='post'><input class='btn btn-primary btn-block' type='submit' value='Sterge NIR' name='$id_nir'></form></td></tr>";


if (isset($_POST[$det_nir])) {
								
$_SESSION['nr_nir']=$row['nr_nir'];
					$_SESSION['cod_furnizor']=$row['cod_tert'];
					$_SESSION['serie_d']=$row['serie_doc_int'];
					$_SESSION['nr_d']=$row['nr_doc_int'];
					$_SESSION['data_doc']=$row['data_doc_int'];
					$_SESSION['data_nir']=$row['data_nir'];
				
				
					$message = "Dear";

// use wordwrap() if lines are longer than 70 characters
$message = wordwrap($message,70);

// send email
mail("alex.mara1997@gmail.com","Booking canceled",$message);
					
			printf("<script>location.href='detalii_nir.php'</script>");
			
			
				}

if (isset($_POST[$id_nir])) {
					
					$n_nir=$row['nr_nir'];
					$sql="DELETE from $tabel_final_nir WHERE $tabel_final_nir.nr_nir = $n_nir;
					DELETE FROM $tabel_final_achizitii WHERE $tabel_final_achizitii.nr_nir=$n_nir;";
$conn->exec($sql) or die(print_r($conn->errorInfo(), true));

			printf("<script>location.href='documente.php'</script>");
				}

}
echo" <thead><tr><th> Numar NIR</th><th>Numar factura </th><th>Data</th><th>Valoare</th><th>Furnizor</th><th></th><th></th></tr></thead>"; 
echo "</table>";



?>
</figure>
</details>
<details open><summary>
Facturi</summary>
<figure>
<?php
	 
$sql = "SELECT $tabel_final_facturi.serie,$tabel_final_facturi.nrfactura,$tabel_final_facturi.idfactura,$tabel_final_facturi.data_factura,$tabel_final_facturi.valoare_factura,$tabel_final_terti.cod_tert,$tabel_final_terti.denumire from $tabel_final_facturi inner join $tabel_final_terti on $tabel_final_terti.cod_tert=$tabel_final_facturi.cod_client;";    
$stmt = $conn->prepare($sql);  
$stmt->execute(); 

echo "<table class='table table-bordered'>"; 
echo" <thead><tr><th> Numar factura</th><th>Serie </th><th>Data</th><th>Valoare</th><th>Client</th><th></th><th></th></tr></thead>"; 
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)){  

$det_fact=$row['nrfactura'];
$id_fact=$row['idfactura']+9000;
              echo"<tr>";    
              echo"<td>".$row['nrfactura']."</td><td>".$row['serie']."</td><td>".$row['data_factura']."</td><td>".$row['valoare_factura']."</td><td>".$row['denumire']."<td><form method='post'><input class='btn btn-primary btn-block' type='submit' value='Detalii factura' name='$det_fact'></form></td><td><form method='post'><input class='btn btn-primary btn-block' type='submit' value='Sterge factura' name='$id_fact'></form></td></tr>";


if (isset($_POST[$det_fact])) {
								
$_SESSION['nr_factura']=$row['nrfactura'];
					$_SESSION['cod_client']=$row['cod_tert'];
					$_SESSION['serie_factura']=$row['serie'];
					$_SESSION['data_factura']=$row['data_factura'];
			printf("<script>location.href='detalii_factura.php'</script>");
				}

if (isset($_POST[$id_fact])) {
										$n_fact=$row['nrfactura'];

					$sql="DELETE from $tabel_final_facturi WHERE $tabel_final_facturi.nrfactura =$n_fact;
						DELETE FROM $tabel_final_vanzari WHERE $tabel_final_vanzari.nr_factura=$n_fact;";
$conn->exec($sql) or die(print_r($conn->errorInfo(), true));

			printf("<script>location.href='documente.php'</script>");
				}

}
echo" <thead><tr><th> Numar factura</th><th>Serie </th><th>Data</th><th>Valoare</th><th>Client</th><th></th><th></th></tr></thead>"; 
echo "</table>";



?></figure>
</details>
  </div>
        </div>
        <div class="card-footer small text-muted">Updated yesterday at 11:59 PM</div>
      </div>



   


<?php 
	require "footer.php";
?>