<?php 	date_default_timezone_set('UTC+2');

		date_default_timezone_set("Europe/Bucharest");?>
		<!DOCTYPE html> 
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="description" content="">
  <meta name="author" content="">

  <title><?php echo $title; 												$_SESSION['error']="";
?></title>
  
  <!-- Bootstrap core CSS-->
  <link href="vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <!-- Custom fonts for this template-->
  <link href="vendor/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css">
  <!-- Custom styles for this template-->
  <link href="css/sb-admin.css" rel="stylesheet">
  <!-- Bootstrap core JavaScript-->
  

  <script
  src="vendor/jquery/jquery.min.js"
  integrity="sha256-iT6Q9iMJYuQiMWNd9lDyBUStIq/8PuOW33aOqmvFpqI="
  crossorigin="anonymous"></script>
                            <link href="vendor/offline/select2/select2.min.css" rel="stylesheet" />
                            <script src="vendor/offline/select2/select2.min.js"></script>
  <script src="js/offline-persistent-zoom.js"></script>
</head>

<body class="fixed-nav sticky-footer bg-dark" id="page-top">

  <?php include 'navigation.php';
  
  ?>

  <div class="content-wrapper">
    <div class="container-fluid">
	
	<?php
				if (!isset($_SESSION['adminloggedin']) && $_SESSION['adminloggedin'] == false) {
												printf("<script>location.href='admin_login.php'</script>");

				}
          
				?>

