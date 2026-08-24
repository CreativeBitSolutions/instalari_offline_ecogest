<?php
	function logIn(){
  include('database_connection.php'); 
  session_start();

		//Check if admin_login button is pressed
		if(!empty($_POST['customer_email_address'])){
			
			

		//Check if password entered matches the one from the database
		$myusername = $_POST['customer_email_address'];
      $mypassword = md5($_POST['customer_password']); 
      $sql="SELECT * from $tabel_final_customers WHERE customer_email_address='$myusername' and customer_password='$mypassword'";
      $psql=$pdo->prepare($sql);

	  $psql->execute();
	  	while ($row = $psql->fetch(PDO::FETCH_ASSOC)){  
        $customer_id=$row['customer_id'];
        

}
	  $count=$psql->rowCount();
      // If result matched $myusername and $mypassword, table row must be 1 row
	
      if($count == 1) {
         $_SESSION['custloggin'] = $myusername;
				$_SESSION['cust_id']=$customer_id;
	printf("<script>location.href='home_page.php'</script>");
       
      }
else{
							$_SESSION['error']="Date de conectare incorecte!";

			
		}
		}
	}
	if(isset($_POST['submit'])){
		logIn();
	}
							printf("<script>location.href='customer_login.php'</script>");

			?>

			
			