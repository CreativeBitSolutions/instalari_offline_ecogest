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

// In instalarea SQLite offline, incarca UI-ul 2FA doar pe cele doua ecrane unde este necesar.
// Scriptul este injectat la finalul documentului pentru a nu modifica markup-ul paginilor existente.
$currentScript = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
$offline2faPages = ['vanzare_restaurant.php', 'vanzare_importa_comanda_tableta.php'];
if (
    function_exists('restaurantIsOfflineSqlite')
    && restaurantIsOfflineSqlite()
    && in_array($currentScript, $offline2faPages, true)
) {
    ob_start(function ($html) {
        $tag = '<script src="offline_tablet_2fa_ui.js?v=20260817"></script>';
        if (stripos($html, 'offline_tablet_2fa_ui.js') !== false) {
            return $html;
        }
        $bodyPos = strripos($html, '</body>');
        if ($bodyPos !== false) {
            return substr($html, 0, $bodyPos) . $tag . substr($html, $bodyPos);
        }
        return $html . $tag;
    });
}

   
   
			 
?>
