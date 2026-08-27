<?php

  //Connect to database
	include 'database_connection.php';

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="description" content="">
  <meta name="author" content="">
  <title>Inregistrare</title>
  <!-- Bootstrap core CSS-->
  <link href="vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <!-- Custom fonts for this template-->
  <link href="vendor/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css">
  <!-- Custom styles for this template-->
  <link href="css/sb-admin.css" rel="stylesheet">
</head>

<body class="bg-dark">
  <div class="container">
    <div class="card card-register mx-auto mt-5">
      <div class="card-header"><h4>Creati un cont</h4></div>
      <?php
			echo "<h4 align='center'>".$_SESSION['success']."</h4>";

			?>
      <div class="card-body">
        <form action = "user_register.php" method = "POST">
          		  <h4>Informatii cont</h4>
		  <div class="form-group">
            <div class="form-row">
              <div class="col-md-6">
                <label for="exampleInputName">Prenume</label>
                <input class="form-control" id="exampleInputName" type="text" name="customer_firstname" aria-describedby="nameHelp" placeholder="Prenume">
              </div>
              <div class="col-md-6">
                <label for="exampleInputLastName">Nume</label>
                <input class="form-control" id="exampleInputLastName" type="text" name="customer_lastname" aria-describedby="nameHelp" placeholder="Nume">
              </div>
            </div>
          </div>
          <div class="form-group">
            <label for="exampleInputEmail1">Adresa de email</label>
            <input class="form-control" id="exampleInputEmail1" type="email" name="customer_email_address" aria-describedby="emailHelp" placeholder="Adresa de email">
          </div> 
          <div class="form-group">
            <div class="form-row">
              <div class="col-md-6">
                <label for="exampleInputPassword1">Parola</label>
                <input class="form-control" id="exampleInputPassword1" name="customer_password" type="password" placeholder="Parola">
              </div>
              <div class="col-md-6">
                <label for="exampleConfirmPassword">Confirmare parola</label>
                <input class="form-control" id="exampleConfirmPassword" name="confirm_customer_password" type="password" placeholder="Confirm password">
              </div>
            </div>
          </div>
		  <h4>Date de facturare</h4>
		  	<div class="form-group">
							<label >Mod de adresare</label>
											<select input class="form-control" name="customer_title">

                       <option value="Dl."</option>Dl.
                       <option value="Dna."</option>Dna.
                       <option value="Dra."</option>Dra.

                      

                        
                   </select>
                            </div>	
		  <div class="form-group">
				    <label for="exampleInputEmail1">Linie de adresa 1</label>
			        <input class="form-control" id="exampleInputEmail1" type="text" name="customer_address_line1" placeholder="Enter address line 1" />
				</div>
				<div class="form-group">
				    <label for="exampleInputEmail1">Linie de adresa  2</label>
			        <input class="form-control" id="exampleInputEmail1" type="text" name="customer_address_line2" placeholder="Enter address line 2" />
				</div>
				<div class="form-group">
				    <label for="exampleInputEmail1">Linie de adresa  3</label>
			        <input class="form-control" id="exampleInputEmail1" type="text" name="customer_address_line3" placeholder="Enter address line 3" />
				</div>
				<div class="form-group">
				    <label for="exampleInputEmail1">Cod postal</label>
			        <input class="form-control" id="exampleInputEmail1" type="text" name="customer_postcode" placeholder="Enter postcode" />
				</div>
					<div class="form-group">
				    <label for="exampleInputEmail1">Numar de telefor </label>
			        <input class="form-control" id="exampleInputEmail1" type="text" name="customer_telephone_no" placeholder="Numar de telefon" />
				</div>
				<div class="form-group">
							<label >Metoda de contactare preferata</label>
											<select input class="form-control" name="customer_preferred_contact_method">

                       <option value="Email"</option>Email
                       <option value="Telefonic"</option>Telefonic

                        
                   </select>
                            </div>	
          <input class="btn btn-primary btn-block" type = "submit" name = "customer_register" value = "Inregistrare"/>
        </form>
		<?php
		

			if(isset($_POST['customer_register'])){
				
	if($_POST['customer_firstname']!= "" && $_POST['customer_firstname']!= ""  && $_POST['customer_lastname']!= "" && $_POST['customer_email_address']!= ""&& $_POST['customer_password']!= "" && $_POST['confirm_customer_password']!= "" && $_POST['customer_address_line1']!= "" && $_POST['customer_address_line2']!= "" && $_POST['customer_address_line3']!= "" && $_POST['customer_postcode']!= "" && $_POST['customer_telephone_no']!= ""){
	    
	    
	    

            
 
if($_POST['customer_password']==$_POST['confirm_customer_password']){
				
			
$username = $_POST['customer_email_address'];
$sql="SELECT * from $tabel_final_customers WHERE customer_email_address='$username'";
        $psql=$pdo->prepare($sql);
      
	    $psql->execute();

	    $count=$psql->rowCount();

        if($count == 0) {
            
            
        
			
			$sqql="INSERT INTO $tabel_final_customers (customer_firstname, customer_lastname, customer_email_address, customer_password,customer_address_line1,customer_address_line2,customer_address_line3,customer_postcode,customer_telephone_no,customer_preferred_contact_method,customer_title)												
													VALUES(:customer_firstname, :customer_lastname, :customer_email_address,:customer_password,:customer_address_line1,:customer_address_line2,:customer_address_line3,:customer_postcode,:customer_telephone_no,:customer_preferred_contact_method,:customer_title)";
				$stmt = $pdo->prepare($sqql);
				$pass = md5($_POST['customer_password']);	
				//Get text data from the fields
				$criteria = [
					'customer_firstname' => $_POST['customer_firstname'],
          'customer_lastname' => $_POST['customer_lastname'],
					'customer_email_address' => $_POST['customer_email_address'],						
					'customer_password' => $pass,
					'customer_address_line1' => $_POST['customer_address_line1'],
					'customer_address_line2' => $_POST['customer_address_line2'],
					'customer_address_line3' => $_POST['customer_address_line3'],
					'customer_postcode' => $_POST['customer_postcode'],
					'customer_telephone_no' => $_POST['customer_telephone_no'],
				 'customer_preferred_contact_method' => $_POST['customer_preferred_contact_method'],
                    'customer_title'=>$_POST['customer_title']
				];
					
				$stmt->execute($criteria);
		    $_SESSION['success']="V-ati inregistrat cu succes!";
		    	printf("<script>location.href='user_register.php'</script>");
}

else {
    
    echo "Adresa de email este deja folosita!";
}
	
}	

else{
	echo "Parolele nu se potrivesc";
}
			}
			
			
			
			else {
				
				echo "Completati toate campurile!";
			}
			

			}	
		
		
			
			
		
		
			
		?>
		
        <div class="text-center">
          <a class="d-block small mt-3" href="customer_login.php">Conectare</a>
        </div>
      </div>
    </div>
  </div>
  <!-- Bootstrap core JavaScript-->
  <script src="vendor/jquery/jquery.min.js"></script>
  <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <!-- Core plugin JavaScript-->
  <script src="vendor/jquery-easing/jquery.easing.min.js"></script>
</body>

</html>
