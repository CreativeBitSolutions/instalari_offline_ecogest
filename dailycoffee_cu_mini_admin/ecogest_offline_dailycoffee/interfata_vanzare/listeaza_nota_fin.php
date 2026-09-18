<?php
//include('listeaza_nota.php');
	
	unset($_SESSION['nr_bon']);
				unset($_SESSION['numerarprim']);
				unset($_SESSION['cardprim']);
				unset($_SESSION['cif_client']);
				unset($_SESSION['rest_tichete']);
				unset($_SESSION['total_tichete']);
unset($_SESSION['nota_noua']);
unset($_SESSION['masa_curenta']);


//header('Refresh:5;url=vanzare_magazin.php');

    printf("<script>location.href='vanzare_magazin.php'</script>");



?>
