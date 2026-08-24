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
  <title>Conectare Client</title>
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
      <div style="padding:0;" class="card-header">
		<img src="images/login.png" alt="Romantic Romania Logo" width="200px" height="200px"/>Conectare Client
	</div>
      <div class="card-body">

		<?php
			if (isset($_SESSION['custloggin']) && $_SESSION['custloggin'] == true) {
		?>
				printf("<script>location.href='home_page.php'</script>");


		<?php
			}
			else {echo "<h3>Conectați-vă!</h3>";
		?>
				<form action = "database_connection4Cust.php" method = "POST">
					<div class="form-group">
						<label for="exampleInputEmail1">Adresa de email</label>
						<input class="form-control" id="exampleInputEmail1" type="email" name="customer_email_address" aria-describedby="emailHelp" placeholder="Introduceti adresa de email">
					</div>
					<div class="form-group">
						<label for="exampleInputPassword1">Parola</label>
						<input class="form-control" id="exampleInputPassword1" type="password" name="customer_password" placeholder="Introduceti parola">
					</div>
				
					<input class="btn btn-primary btn-block" type="submit" name="submit" value="Conectare">
				</form><h4 style="color:red" align="center">
				<?php
			}echo $_SESSION['error'];

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
