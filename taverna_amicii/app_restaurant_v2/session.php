<?php session_start();     date_default_timezone_set("Europe/Bucharest");

 if(!isset($_SESSION['admin_id'])){
printf("<script>location.href='agecs_login.php'</script>");	
   }
   include('database_connection.php');
   $live_id=12;
   $user_check=(int)$_SESSION['admin_id'];
   
   		$zsql = "SELECT admin_id FROM $tabel_final_admins WHERE admin_id = :admin_id LIMIT 1";
$zstmt = $pdo->prepare($zsql);
$zstmt->execute([':admin_id' => $user_check]); 
	  $adminExists = (bool)$zstmt->fetch(PDO::FETCH_ASSOC);
	 
	
	 if(!$adminExists) {
         
         
         
			printf("<script>location.href='agecs_login.php'</script>");
      }
	  
  	    $live_id=12;

   
   
			 
?>
