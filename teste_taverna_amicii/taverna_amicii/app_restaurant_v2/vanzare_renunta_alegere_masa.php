<?php

include('session.php');

$adm_id = $_SESSION['admin_id'];
$cod_locatie=$_SESSION['cod_locatie'];



$bon_sql = "DELETE FROM $tabel_final_note WHERE $tabel_final_note.status ='S' and cod_masa=0 and operator='$adm_id' and locatie='$cod_locatie'";
          $bon_stmt = $pdo->prepare($bon_sql);
          $bon_stmt->execute(); 
unset($_SESSION['nr_bon']);
$_SESSION['trimis_comanda']=0;

          printf("<script>location.href='vanzare_restaurant.php'</script>");

          ?>