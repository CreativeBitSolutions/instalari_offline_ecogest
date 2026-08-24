<?php  include('session.php');
	$title = 'Chitante';
  include 'header.php';
	
?>

	
	   
          
			
				

		<ol class="breadcrumb">
<li class="breadcrumb-item active">Documente</li>
</ol>

	 <div class="card mb-3">
        <div class="card-header">
          <i class="fa fa-table"></i> Lista Chitanțelor</div>
        <div class="card-body">
          <div class="table-responsive">
<?php
	 
$sql = "SELECT * from $tabel_final_chitante inner join $tabel_final_admins on $tabel_final_chitante.gestionar=$tabel_final_admins.admin_id ;";    
$stmt = $pdo->prepare($sql);  
$stmt->execute(); 

echo "<table class='table table-bordered'>"; 
echo" <thead><tr><th> Număr Chitanță</th><th>Serie </th><th>Data</th><th>Valoare</th><th>Nr. Factură</th><th>Gestionar</th><th colspan='2'>Acțiuni</th></tr></thead>"; 
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)){  

$det_chit=$row['nrchitanta'];
$del_chit=$row['nrchitanta'];
$del_chit=strval($del_chit);
$del_chit.='D';
              echo"<tr>";    
              echo"<td>".$row['nrchitanta']."</td><td>".$row['serie']."</td><td>".$row['data_chitanta']."</td><td>".$row['suma_numere']."</td><td>".$row['nrfact']."</td><td>".$row['admin_lastname']." " .$row['admin_firstname']."<td><form method='post'><input class='btn btn-primary btn-block' type='submit' value='Listează Chitanță' name='$det_chit'>
              <td><input class='btn btn-primary btn-block' type='submit' value='Sterge Chitanță' name='$del_chit'></td></form></td></tr>";


if (isset($_POST[$det_chit])) {
								
$_SESSION['nr_chitanta']=$det_chit;
			printf("<script>location.href='listeaza_chitanta.php'</script>");
				}
				
	if (isset($_POST[$del_chit])) {
								
								
				$delsql="DELETE from $tabel_final_chitante where nrchitanta='$det_chit'";
$delstmt = $pdo->prepare($delsql);  
$delstmt->execute(); 


							printf("<script>location.href='$tabel_final_chitante.php'</script>");
}



}
echo" <tfoot><tr><th> Număr Chitanță</th><th>Serie </th><th>Data</th><th>Valoare</th><th>Nr. Factură</th><th>Gestionar</th><th colspan='2'>Acțiuni</th></tr></tfoot>"; 
echo "</table>";



?>
  </div>
        </div>
      
      </div>



   


<?php 
	require "footer.php";
?>