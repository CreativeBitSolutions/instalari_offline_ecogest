<?php
session_start();
$_SESSION['nr_bon']=$_GET['nr_bon'];
include('list_prod_bar.php');
	



header('Refresh:5;url=vanzare_magazin.php');

    


?>
