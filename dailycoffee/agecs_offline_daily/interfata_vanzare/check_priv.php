<?php
   $administrator="administrator";
   include('database_connection.php');
   
   
	$admin_em=$_SESSION['admin_id'];
	
$sql="SELECT * FROM $tabel_final_admins WHERE admin_id='$admin_em' and rank='$administrator'";
$zsql=$pdo->prepare($sql);


	$zsql->execute();
	//count the returned number of rows
	$count=$zsql->rowCount();
	 
	// if no rows are found disconnect the logged user due to lack of authorization
	if($count == 0) {
         
        printf("<script>location.href='admin_login.php'</script>");	}
      
	  // if no user is logged in redirect to the login page
    if(!isset($_SESSION['adminloggedin'])){
        printf("<script>location.href='admin_login.php'</script>");	
   }
?>