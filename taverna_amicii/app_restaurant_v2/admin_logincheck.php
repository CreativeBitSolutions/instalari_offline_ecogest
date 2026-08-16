<?php
require_once __DIR__ . '/session_device.php';
session_start();
restaurantDeviceUid();
// resetăm auto‑login-ul
// expiră doar dacă au trecut 15 minute
unset($_SESSION['error']);

 unset($_SESSION['error']);
	function logIn(){
	include 'database_connection.php';
    require_once __DIR__ . '/offline_products_guard.php';
    $productsSyncGuard = opg_check_products_sync($pdo, $restaurantConfig ?? []);
    $productsSyncStatus = (string)($productsSyncGuard['status'] ?? '');
    if (empty($productsSyncGuard['allow']) && $productsSyncStatus !== 'products_changed') {
        $_SESSION['error'] = (string)($productsSyncGuard['message'] ?? 'Nomenclatorul local nu este sincronizat.');
        printf("<script>location.href='agecs_login.php'</script>");
        return;
    }
	$loginDateTime = new DateTime('now');
$loginTime     = $loginDateTime->format('Y-m-d H:i:s');

	//Check if admin_login button is pressed
	if(!empty($_POST['oper'])){
	    //Check if password entered matches the one from the database
		$myusername = (int)$_POST['oper'];
        $mypassword = md5((string)$_POST['calc_result']); 
        $psql = "SELECT * FROM $tabel_final_admins WHERE admin_id = :admin_id AND admin_password = :admin_password LIMIT 1";
        $pstmt = $pdo->prepare($psql);
        $pstmt->execute([
            ':admin_id' => $myusername,
            ':admin_password' => $mypassword,
        ]);
        $row = $pstmt->fetch(PDO::FETCH_ASSOC);
        // If result matched $myusername and $mypassword, the result table's number of rows must be 1 row
	// if the table has 1 rows redirect the user to admin_index.php
        if($row) {
            $_SESSION['error']='';
            $_SESSION['adminloggedin'] = $myusername;
    $admin_id = $row['admin_id'];
    $operatorName = $row['admin_firstname'] . " " . $row['admin_lastname'];
    $rank = $row['rank'];
    $adresa_de_email=$row['admin_email_address'];
    // Inserare în tabela conectari_operatori
    $insertSql = "INSERT INTO conectari_operatori (id_operator, nume_operator, login_time) VALUES (:id_operator, :nume_operator,:login_time)";
    $insertStmt = $pdo->prepare($insertSql);
    $insertStmt->execute([
        'id_operator' => $admin_id,
        'nume_operator' => $operatorName,
		'login_time'    => $loginTime

    ]);
    // *** Auto‑login setup ***
$_SESSION['last_password_time']    = time();               // timestamp curent
$_SESSION['last_operator_password'] = $_POST['calc_result']; // parola necriptată
$_SESSION['last_operator_name']     = $operatorName;         // numele complet

$_SESSION['admin_id'] = $admin_id;

if (!isset($_SESSION['no_session_validation']) || $_SESSION['no_session_validation'] != 1) {
if($rank!="bucatar" && $rank!="sefsala"){
  // *** AICI pune update-ul în ultima_conexiune ***
    restaurantTouchUltimaConexiune($pdo, (int)$_SESSION['cod_locatie'], (int)$admin_id, $loginTime);
}
}

// Update status to 1 (connected) trebuie gasita o solutie la situatia in care se inchide tab-ul fara sa dea deconectare
//$updateSql = "UPDATE $tabel_final_admins SET conectat = 1 WHERE admin_id = :admin_id";
//$updateStmt = $pdo->prepare($updateSql);
//$updateStmt->execute(['admin_id' => $admin_id]);

// doar pentru administratori, caut şi în tabela utilizatori:
if ($rank === 'administrator') {
    // 1) găsesc în 'utilizatori' după email-ul din admins
    $sqlU = "
      SELECT id_utilizator, rang, adresa_de_email
      FROM utilizatori
      WHERE adresa_de_email = :email_admin
      LIMIT 1
    ";
    $stmtU = $pdo->prepare($sqlU);
    $stmtU->execute([
        ':email_admin' => $adresa_de_email
    ]);
    if ($u = $stmtU->fetch(PDO::FETCH_ASSOC)) {
        // 2) suprascriu sesiunea cu id-ul şi rang-ul din utilizatori
        $_SESSION['admin_id']      = $u['id_utilizator'];
        $_SESSION['rang']          = $u['rang'];
        $_SESSION['adminloggedin'] = $u['adresa_de_email'];
    }
    // 3) apoi redirecţionez…
    header('Location: ../index.php');
    exit;
}
	        elseif($rank=="operator"){
	           printf("<script>location.href='creare_bon_simplu.php'</script>");

	        }
	        elseif($rank=="ospatar"){
	            printf("<script>location.href='vanzare_restaurant.php'</script>");

	        }
			elseif($rank=="sefsala"){
	            
				printf("<script>location.href='sefsala.php'</script>");

}
	        elseif($rank=="bucatar"){
	            
	            	        printf("<script>location.href='interfata_bucatarie.php'</script>");

	        }
	          elseif($rank=="barman"){
	            
	            	        printf("<script>location.href='bar.php'</script>");

	        }
	             elseif($rank=="client"){
	            $_SESSION['error']="Fluxul tableta este dezactivat.";
	            	        printf("<script>location.href='agecs_login.php'</script>");

	        }
	            elseif($rank=="receptioner"){
	            
	            	        printf("<script>location.href='hotel/index.php'</script>");

	        }
	          elseif($rank=="tableta"){
	            $_SESSION['error']="Fluxul tableta este dezactivat.";
	            	        printf("<script>location.href='agecs_login.php'</script>");

	        }
       }
			// if the table has 0 rows redirect the user back to the admin_login.php and store an error message into $_SESSION['error'] which wil be displayed on admin_login.php  
			
		else {
		    $_SESSION['error']="Date de conectare incorecte!";
printf("<script>location.href='agecs_login.php'</script>");
		}

	}
	}
	
	if(isset($_POST['continua'])){
		logIn();

	}

	

?>
