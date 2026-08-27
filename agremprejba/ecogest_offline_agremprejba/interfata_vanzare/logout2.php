<?php
	session_start();
  session_unset();
    session_destroy();
    session_write_close();
    setcookie(session_name(),'',0,'/');
    printf("<script>location.href='agecs_login.php'</script>");
?>
