<?php  include('session.php');
	$title = 'Bonuri de consum';
  include 'header.php';
	$adm_id=$_SESSION['admin_id'];
?>

	
	   
          
			
				
		<ol class="breadcrumb">
<li class="breadcrumb-item active">Documente</li>
</ol>

	 <div class="card mb-3">
        <div class="card-header">
          <i class="fa fa-table"></i> Lista Bonurilor de consum</div>
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
$sql = "SELECT * from $tabel_final_miscari inner join  $tabel_final_nomenclator on $tabel_final_miscari.produs_obtinut=$tabel_final_nomenclator.cod_p where fel_doc='BC' group by $tabel_final_miscari.nr_doc";    
$stmt = $pdo->prepare($sql);  
$stmt->execute(); 
echo '<table class="table table-bordered" id="myTable" width="100%" cellspacing="0">'; 
echo" <thead><tr><th>Numar Bon de Consum</th><th>Data</th><th>Produs obtinut</th><th>Nota aferenta consumului</th><th>Actiuni</th></tr></thead>"; 
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)){ 
$det_fact=$row['nr_doc'];
$produs_obtinut=$row['den_p'];

      echo"<tr>";    
      echo"<td>".$row['nr_doc']."</td><td>".date("d.m.Y", strtotime($row['data']))."</td><td>".$row['den_p']."</td><td>".$row['nr_nota']."</td><td><form method='post'><input class='btn btn-primary btn-block' type='submit' value='Listeaza Bonul de Consum' name='$det_fact'>";
    


if (isset($_POST[$det_fact])){

    $_SESSION['nr_doc']=$det_fact;
	printf("<script>location.href='listeaza_bon_consum.php'</script>");
				}
}
echo" <thead><tr><th>Numar Bon de Consum</th><th>Data</th><th>Produs obtinut</th><th>Nota aferenta consumului</th><th>Actiuni</th></tr></thead>"; 
echo "</table>";



?>
  </div>
        </div>
      
      </div>



   


<?php 
	require "footer.php";
?>