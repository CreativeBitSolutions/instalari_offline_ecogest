<?php
include('listeaza_bon.php');
	
	unset($_SESSION['nr_bon']);
				unset($_SESSION['numerarprim']);
				unset($_SESSION['cardprim']);
				unset($_SESSION['cif_client']);
				unset($_SESSION['rest_tichete']);
				unset($_SESSION['total_tichete']);


header('Refresh:5;url=creare_bon_simplu.php');

    


?>
