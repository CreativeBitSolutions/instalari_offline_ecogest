<?php

	include 'database_connection.php';
  
	$title = 'Categorii';
  
  include 'header.php';
 include 'check_priv.php';

?>

      <!-- Breadcrumbs-->
      <ol class="breadcrumb">
        
        <li class="breadcrumb-item">
          <a href="admin_categories.php">Categorii</a>
        </li>
        <li class="breadcrumb-item active">Creaza Categorie</li>
      </ol>
      <div class="container">
          <style>label{font-weight:bold;}</style>	
		
        

        <?php
				if (isset($_POST['add_category'])) 
						{
$sql ="INSERT INTO $tabel_final_categorii(den_categ,desc_categ) 
													VALUES (:den_categ,:desc_categ)";

$stmt = $pdo->prepare($sql);
							$criteria = 
							[
							   	'den_categ' => $_POST['den_categ'],

						'desc_categ' => $_POST['desc_categ']
							];

							$stmt->execute($criteria);

				echo '<script language="javascript">';
echo 'alert("Categorie creata")';
echo '</script>';			
printf("<script>location.href='add_category.php'</script>");							
							

						}

				
						
			?>			<h4 align="center">Creaza categorie</h4><hr>

			<div class="card-body">
				<form action="add_category.php" method="POST">
					<div class="form-group">
						<label for="exampleInputEmail1">Denumire Categorie:</label>
						<input class="form-control" id="exampleInputEmail1" type="text" name="den_categ" aria-describedby="emailHelp" placeholder="Introduceti denumirea categoriei">
					</div>
					<div class="form-group">
						<label for="exampleInputPassword1">Descriere Categorie:</label>
						<textarea input class="form-control" id="exampleInputPassword1" type="text" name="desc_categ" placeholder="Introduceti descrierea categoriei"></textarea>
					</div>
                    <input class="btn btn-primary btn-block" type="submit" name="add_category" value="Creaza categorie">
                 </form>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    
    
		<?php include 'footer.php'; ?>