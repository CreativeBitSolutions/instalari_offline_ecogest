<?php
include 'database_connection.php';
$title = 'Creare Locatie/Mese';
  
  include 'header.php';
    include 'check_priv.php';

?>

      <!-- Breadcrumbs-->
      <ol class="breadcrumb">
        <li class="breadcrumb-item">
        </li>
      
      </ol>
      <div class="container">          <style>label{font-weight:bold;}</style>



<section class="right">
<h4 align="center">Adauga Locatie/Mese</h4><hr>
<div class="card-body">
<form action="creare_locatie_mese.php" method="POST">
<div class="form-group">
<label for="exampleInputPassword1">Nume Locatie:</label>
<input class="form-control" id="exampleInputPassword1" type="text" maxlength="30" name="nume_locatie">
</div>
<div class="form-group">
<label for="exampleInputPassword1">Numar de Mese:</label>
<input class="form-control" id="exampleInputPassword1" type="number" name="numar_mese" >
</div>
<input class="btn btn-primary btn-block" type="submit" name="add_admin" value="Creaza">
</form>
</div><br>
</section>

<?php
				if (isset($_POST['add_admin'])) 
						{
						    
						    $nr_mese=$_POST['numar_mese'];
						$ccom_sql="INSERT INTO $tabel_final_loc_mese (den_loc) 
													VALUES (:nume_locatie)";
$stmt = $pdo->prepare($ccom_sql);  
							$criteria = 
							[
							   	'nume_locatie' => $_POST['nume_locatie'],
							];

							$stmt->execute($criteria);
							
			 $inchidere_sql = "SELECT max(cod_locatie) as ultim_loc FROM $tabel_final_loc_mese";    
$inchidere_stmt = $pdo->prepare($inchidere_sql);  
$inchidere_stmt->execute();
while ($row = $inchidere_stmt->fetch(PDO::FETCH_ASSOC)){ 
    $ultim_loc=$row['ultim_loc'];
}				
							
							for ($i = 1; $i <= $nr_mese; $i++) {

$psql = "insert into $tabel_final_mese(cod_locatie) values('$ultim_loc');";

	try{
$pdo->exec($psql) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 
      	 $psql7 = "SELECT $tabel_final_retete.cod_mat,$tabel_final_retete.cant_folos from $tabel_final_retete where $tabel_final_retete.cod_p='$produs';";    
$pstmt7 = $pdo->prepare($psql7);  
$pstmt7->execute(); 

}catch(PDOException $e)
    {
    echo $sql . "<br>" . $e->getMessage();
    } 

}
							
printf("<script>location.href='index.php'</script>");							
							

						}

				
						
			?>

  </div>
    <!-- /.container-fluid-->
    <!-- /.content-wrapper-->
    		<?php include 'footer.php'; ?>
    <!-- Scroll to Top Button-->
    <a class="scroll-to-top rounded" href="#page-top">
      <i class="fa fa-angle-up"></i>
    </a>
    <!-- Bootstrap core JavaScript-->

  </div>
</body>

</html>
