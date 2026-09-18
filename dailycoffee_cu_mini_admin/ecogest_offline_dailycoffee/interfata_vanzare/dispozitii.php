<?php  include('session.php');
	$title = '$tabel_final_dispozitii';
  include 'header.php';
	
?>

	
	   
          
			
				

		<ol class="breadcrumb">
<li class="breadcrumb-item active">Documente</li>
</ol>

	 <div class="card mb-3">
        <div class="card-header">
          <i class="fa fa-table"></i> Lista Dispozițiilor de Plată/Încasare</div>
        <div class="card-body">
          <div class="table-responsive">
<?php
	 
$sql = "SELECT * from $tabel_final_dispozitii ;";    
$stmt = $pdo->prepare($sql);  
$stmt->execute(); 

echo "<table class='table table-bordered'>"; 
echo" <thead><tr><th> Număr Dispoziție</th><th>Serie </th><th>Data</th><th>Valoare</th><th>Tip Dispoziție</th><th colspan='2'>Acțiuni</th></tr></thead>"; 
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)){  

$det_chit=$row['nr_disp'];
$del_chit=$row['nr_disp'];
$del_chit=strval($del_chit);
$del_chit.='D';
$tip_dispozitie=$row['tip_disp'];
              echo"<tr>";    
              echo"<td>".$row['nr_disp']."</td><td>".$row['serie_disp']."</td><td>".$row['data_disp']."</td><td>".$row['suma_disp']."</td><td>";if($tip_dispozitie=='p'){echo 'Plată';}elseif($tip_dispozitie=='i'){echo 'Încasare';} echo"</td><td><form method='post'><input class='btn btn-primary btn-block' type='submit' value='Listează Dispoziție' name='$det_chit'>
              <td><input class='btn btn-primary btn-block' type='submit' value='Sterge Dispoziție' name='$del_chit'></td></form></td></tr>";


if (isset($_POST[$det_chit])) {
								
$_SESSION['nr_dispozitie']=$det_chit;
			printf("<script>location.href='listeaza_dispozitie.php'</script>");
				}
				
	if (isset($_POST[$del_chit])) {
								
								
				$delsql="DELETE FROM $tabel_final_dispozitii where nr_disp='$det_chit'";
$delstmt = $pdo->prepare($delsql);  
$delstmt->execute(); 


							printf("<script>location.href='dispozitii.php'</script>");
}



}
echo" <tfoot><tr><th> Număr Dispoziție</th><th>Serie </th><th>Data</th><th>Valoare</th><th>Tip Dispoziție</th><th colspan='2'>Acțiuni</th></tr></tfoot>"; 
echo "</table>";



?>
  </div>
        </div>
      
      </div>



   


<?php 
	require "footer.php";
?>