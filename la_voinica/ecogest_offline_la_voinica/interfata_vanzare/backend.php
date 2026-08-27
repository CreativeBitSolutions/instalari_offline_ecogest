<?php
session_start();
    $_SESSION['masa_curenta'] = $_GET['masa_noua'];
    
   if( $_SESSION['masa_curenta']!=9999){ echo $_SESSION['masa_curenta'];}

?>