<?php
include('session.php');
$adm_id=$_SESSION['admin_id'];
   $bon_amanat=$_GET['nota'];
  
      $_SESSION['nr_bon']=$bon_amanat;
      // setam nr 99999  pt masa tabletei
      $_SESSION['masa_curenta']=99999;

      			printf("<script>location.href='vanzare_magazin.php'</script>");

?>