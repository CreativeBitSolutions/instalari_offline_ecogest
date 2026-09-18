<?php  include('session.php');
	$title = 'Note de receptie';
  include 'header.php';
	   include('check_priv.php');

?>

	
	   
          
			
				

		<ol class="breadcrumb">
<li class="breadcrumb-item active">Documente</li>
</ol>
	
	 <div class="card mb-3">
        <div class="card-header">
          <i class="fa fa-table"></i> Lista Notelor de Receptie si Constatare Diferente </div>
        <div class="card-body">
            
                        <div style="width:23em;" class="input-group">
		<input id="myInput"  class='form-control' type="text" onkeyup="myFunctions()" placeholder="Caută după numărul notei de recepție...">
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
</div>
          <div class="table-responsive">
<?php
	 
$sql = "SELECT $tabel_final_nir.status,$tabel_final_nir.serie_doc_int,$tabel_final_nir.data_doc_int,$tabel_final_nir.nr_doc_int,$tabel_final_nir.id_nir,$tabel_final_nir.nr_nir,$tabel_final_nir.nr_doc_int,$tabel_final_nir.data_nir,$tabel_final_nir.val_nir_ftva,$tabel_final_terti.cod_tert,$tabel_final_terti.denumire from $tabel_final_nir inner join $tabel_final_terti on $tabel_final_terti.cod_tert=$tabel_final_nir.cod_tert order by $tabel_final_nir.data_nir;";    
$stmt = $pdo->prepare($sql);  
$stmt->execute(); 

echo "<table class='table table-bordered' id='myTable'>"; 
echo" <thead><tr><th> Nr. NIR</th><th>Nr. Factura </th><th>Data</th><th>Valoare</th><th>Furnizor</th><th>Stare</th><th></th></tr></thead>"; 
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)){  
 
$det_nir=$row['nr_nir'];
$del_nir=$row['nr_nir'];
$stare=$row['status'];
if($stare!='F'){
    $status="Nefinalizată";
}
else{
        $status="Finalizată";

}
	$del_nir=strval($del_nir);
$del_nir.='D';
              echo"<tr>";    
              echo"<td>".$row['nr_nir']."</td><td>".$row['nr_doc_int']."</td><td>".date("d.m.Y", strtotime($row['data_nir']))."</td><td>".$row['val_nir_ftva']."</td><td>".$row['denumire']."</td><td>".$status."<form method='post'><td><input class='btn btn-primary btn-block' type='submit' value='Detalii NIR' name='$det_nir'></td><td><input class='btn btn-primary btn-block' type='submit' value='Sterge NIR' name='$del_nir'></td></form></tr>";


if (isset($_POST[$det_nir])) {
								
$_SESSION['nr_nir']=$row['nr_nir'];
					$_SESSION['cod_furnizor']=$row['cod_tert'];
					$_SESSION['serie_d']=$row['serie_doc_int'];
					$_SESSION['nr_d']=$row['nr_doc_int'];
					$_SESSION['data_doc']=$row['data_doc_int'];
					$_SESSION['data_nir']=$row['data_nir'];
				$_SESSION['status']=$row['status'];

					
					
			printf("<script>location.href='detalii_nir.php'</script>");
			
			
				}
	if (isset($_POST[$del_nir])) {
								
$_SESSION['nr_nir']=$det_nir;
				$_SESSION['status']=$row['status'];

printf("<script>location.href='sterge_nir.php'</script>");


}


}
echo" <thead><tr><th> Nr. NIR</th><th>Nr. Factura </th><th>Data</th><th>Valoare</th><th>Furnizor</th><th>Stare</th><th></th></tr></thead>"; 
echo "</table>";



?>

  </div>
        </div>
       
      </div>



   



<?php 
	require "footer.php";
?>