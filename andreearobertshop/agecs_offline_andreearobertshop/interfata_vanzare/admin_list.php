<?php

	include 'database_connection.php';
  
	$title = 'Administratori';
  
  include 'header.php';

?>

      <!-- Breadcrumbs-->
      <ol class="breadcrumb">
        
        <li class="breadcrumb-item active">Administratori</li>
      </ol>

			<?php
				if (isset($_SESSION['adminloggedin']) && $_SESSION['adminloggedin'] == true) {
          
				?>
      

      <!-- Example DataTables Card-->
      <div class="card mb-3">
        <div class="card-header">
          <i class="fa fa-user"></i> Lista Administratorilor</div>
        <div class="card-body">
          <div class="table-responsive">
<style>td{width:20em;}</style>     
			<?php 

				echo '<section class="right">';
$sql="SELECT * FROM $tabel_final_admins";
					$stmt = $pdo->prepare($sql);

					$stmt->execute();

					echo '<table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">';
						echo '<thead>
							<tr>
							  <th>Adresa de Email</th>
							  <th>Prenume</th>
							  <th>Nume</th>
							  <th>Funcție</th>
							  <th colspan="2">Actiune</th>
							</tr>
						</thead>';
						echo '<tfoot>
							<tr>
								<th>Adresa de Email</th>
								  <th>Prenume</th>
							  <th>Nume</th>
                            <th>Funcție</th>
								<th colspan="2">Actiune</th>
							</tr>
						</tfoot>';
					echo '<tbody>';
						while ($row = $stmt->fetch()) {
									$adm_id=$row['admin_id'];
									$adm_del=$row['admin_id'];
									$adm_del=strval($adm_del);
$adm_del.='D';

									

							echo '<tr>
								<td>' . $row['admin_email_address'] . '</td>
								<td>' . $row['admin_firstname'] . '</td>
								<td>' . $row['admin_lastname'] . '</td>
<td>' . $row['rank'] . '</td>

								';
								$administrator="administrator";
   include('database_connection.php');
$admin_em=$_SESSION['admin_id'];

				
$sql="SELECT * FROM $tabel_final_admins WHERE admin_id='$admin_em' and rank='$administrator'";
$zsql=$pdo->prepare($sql);


	  $zsql->execute();
	  //count the returned number of rows

	  $count=$zsql->rowCount();
	 // if 1 row is found show the connected user a full navigation bar with the option to add categories,experiences and add administrators
	 if($count == 1) {
								echo "<td><form method='post'><input class='btn btn-primary btn-block' type='submit' value='Modifica' name='$adm_id'></td><td><input class='btn btn-primary btn-block' type='submit' value='Sterge' name='$adm_del'></form></td></tr>";}
							
								
												if(isset($_POST[$adm_id])){

								
							$_SESSION['adm_id']=$adm_id;
							printf("<script>location.href='edit_admin.php'</script>");

							
	
}
if (isset($_POST[$adm_del])) {



					$sql="DELETE FROM $tabel_final_admins WHERE admin_id ='$adm_id';";
$pdo->exec($sql) or die(print_r($pdo->errorInfo(), true));



			printf("<script>location.href='admin_list.php'</script>");
			
			
				}
						}
					echo '</tbody>';
					echo '</table>';

				echo '</section>';
			
			?>
          </div>          <a name="bottomOfPage"></a>

        </div>
        
      </div>
    </div>
  
  	<?php
}
	else {
		?>
							printf("<script>location.href='admin_login.php'</script>");

    <?php
	}
?>
    
		<?php include 'footer.php'; ?>