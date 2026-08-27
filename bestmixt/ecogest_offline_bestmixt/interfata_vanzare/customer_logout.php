<?php
	session_start();
	unset($_SESSION['custloggin']);
		unset($_SESSION['cust_id']);
		   	printf("<script>location.href='home_page.php'</script>");
?>