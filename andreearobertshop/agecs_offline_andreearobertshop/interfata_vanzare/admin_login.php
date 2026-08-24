<?php
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
  <title>Admin Login</title>
  <!-- Bootstrap core CSS-->
  <link href="vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <!-- Custom fonts for this template-->
  <link href="vendor/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css">
  <!-- Custom styles for this template-->
  <link href="css/sb-admin.css" rel="stylesheet">
</head>

<body class="bg-dark">
  <div class="container">
    <div class="card card-login mx-auto mt-5">
      <div class="card-header">
		<img src="images/login.png" alt="Romantic Romania Logo" width="100px" height="100px"/><b>Conectare Administrator</b>
	</div>
      <div class="card-body">

	<!-- the following form sends the login data to file admin_logincheck.php for processing-->
				<form action = "admin_logincheck.php" method = "POST">
			<div class="form-group">
				<label for="exampleInputEmail1">Adresa de Email</label>
				<input class="form-control" type="text" name="admin_email_address" aria-describedby="emailHelp" placeholder="Introduceti adresa de email">
			</div>
			<div class="form-group">
				<label for="exampleInputPassword1">Parola</label>
				<input class="form-control" type="password" name="admin_password" placeholder="Introduceti parola">
			</div>
		
			<input class="btn btn-primary btn-block" type="submit" name="submit" value="Conectare">
		</form> <h4 style="color:red" align="center">
				<?php
			
		// display an error message if admin_logincheck fails to find the credentials in admins table	
echo $_SESSION['error'];

			
			?>
</h4>
			
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
