<?php  include('session.php');
	$title = 'Bonuri de consum personalizate';
  include 'header.php';
	   include('check_priv.php');

?>

	
	   
          
			
				

		<ol class="breadcrumb">
<li class="breadcrumb-item active">Documente</li>
</ol>
	
	 <div class="card mb-3">
        <div class="card-header">
          <i class="fa fa-table"></i> Lista Bonurilor de Consum Personalizate </div>
        <div class="card-body">
            
                        <div style="width:23em;" class="input-group">
		<input id="myInput"  class='form-control' type="text" onkeyup="myFunctions()" placeholder="Caută după numărul bonului de consum...">
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
	 
$sql = "SELECT * from $tabel_final_bonuri_consum";    
$stmt = $pdo->prepare($sql);  
$stmt->execute(); 

echo "<table class='table table-bordered' id='myTable'>"; 
echo" <thead><tr><th> Nr. Bon</th><th>Data Bon </th><th>Gestiune pred.</th><th>Nr. comanda</th><th>Produs Obtinut</th><th>Stare</th><th></th></tr></thead>"; 
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)){  
 
$det_nir=$row['nr_bon'];
$del_nir=$row['nr_bon'];
$stare=$row['finalizat'];
if($stare!='1'){
    $status="Nefinalizat";
}
else{
        $status="Finalizat";

}
	$del_nir=strval($del_nir);
$del_nir.='D';
              echo"<tr>";    
              echo"<td>".$row['nr_bon']."</td><td>".date("d.m.Y", strtotime($row['data_bon']))."</td><td>".$row['gestiune_pred']."</td><td>".$row['nr_comanda']."</td><td>".$row['produs']."</td><td>".$status."<form method='post'><td><input class='btn btn-primary btn-block' type='submit' value='Detalii Bon' name='$det_nir'></td><td><input class='btn btn-primary btn-block' type='submit' value='Sterge Bon' name='$del_nir'></td></form></tr>";
if (isset($_POST[$det_nir])) {
$_SESSION['nr_bon_c']=$row['nr_bon'];

			printf("<script>location.href='detalii_bon_consum.php'</script>");
				}
	if (isset($_POST[$del_nir])) {
			
$_SESSION['status']=$stare;
$_SESSION['nr_bon_c']=$det_nir;
printf("<script>location.href='sterge_bon_consum.php'</script>");


}


}
echo" <thead><tr><th> Nr. Bon</th><th>Data Bon </th><th>Gestiune pred.</th><th>Nr. comanda</th><th>Produs Obtinut</th><th>Stare</th><th></th></tr></thead>"; 
echo "</table>";



?>

  </div>
        </div>
       
      </div>



   



<?php 
	require "footer.php";
?>