<?php
	session_start();
	unset($_SESSION['adminloggedin']);
		unset($_SESSION['admin_id']);

    printf("<script>location.href='agecs_login.php'</script>");
?>