<?php session_start();
	include 'database_connection.php';

	
	include "website_header.php";
			

?>

	
	   
<!-- about -->
	  <a name="bottomOfPage"></a>
	  
		<style>
	h3 {
	margin:20px;
	    color:black;

}

	</style>
<section class="contact-w3-agileits" id="contact">
	<div class="container">
		<h3 class="text-center" data-aos="zoom-in">Luati legatura cu noi</h3>
		<div class="col-lg-4 col-md-4 contact-w3l1" data-aos="flip-right">
			<h4>Abonati-va la newsletter-ul nostru saptamanal</h4>
			<div class="subscribe">
				<form action="#" method="post">
					<div class="form-group1">
						<input class="form-control" id="email1" name="email1" placeholder="Introduceti adresa de email" type="email" required>
					</div>
					<div class="form-group2">
						<button name="subscribe" class="btn btn-outline btn-lg" type="submit">Abonare</button>
					</div>
					<div class="clearfix"></div>
				</form>
			</div>	
		</div>
		<div class="col-lg-8 col-md-8 contact-w3l2" data-aos="flip-left">
			<form method="post">
				<div class="form-group col-md-4 col-sm-4">
					<input type="text" class="form-control" id="name" name="name" placeholder="Nume" required/>
				</div>
				<div class="form-group col-md-4 col-sm-4">
					<input type="email" class="form-control" id="email2" name="email2" placeholder="Email" required/>
				</div>
				<div class="form-group col-md-4 col-sm-4">
					<input type="tel" class="form-control" id="phone" name="phone" placeholder="Telefon" required/>
				</div>
				<div class="clearfix"></div>
				<div class="form-group col-md-12">
					<textarea class="form-control" rows="6" name="message" placeholder="Mesaj" required></textarea>
				</div>
				<div class="form-group col-md-12">
					<button type="submit" name="contact" class="btn-outline2"><i class="fa fa-check-circle-o" aria-hidden="true"></i> Trimite</button>
				</div>
				<div class="clearfix"></div>
			</form>	

		</div>
	<?php	
		if (isset($_POST['subscribe']))
				{
					$subscriber_email_address=$_POST['email1'];

				$subsql="INSERT INTO $tabel_final_abonati(subscriber_email_address) VALUES ('$subscriber_email_address');";
			

					try{
$pdo->exec($subsql) or die(print_r($pdo->errorInfo(), true));   

echo "<h3>V-ati abonat la newsletter!</h3>";

}catch(PDOException $e)
    {
    echo $bsql . "<br>" . $e->getMessage();
    } 

					


				}
				
				if (isset($_POST['contact']))
				{
					$sender_name=$_POST['name'];
					$sender_email_address=$_POST['email2'];
					$sender_phone=$_POST['phone'];
					$sender_message=$_POST['message'];

$subject = "Mesaj formular de contact";
$message = " 
<html>
<head>
</head>
<body><h4>Aveti un mesaj nou de la un utilizator!</h4>

<hr>
<table>
<tr><td> Nume:</td><td>$sender_name</td></tr>
<tr><td> Adresa email</td><td>$sender_email_address</td></tr>
<tr><td> Numar de telefon:</td><td>$sender_phone</td></tr>
<tr><td> Mesaj:</td><td>$sender_message</td></tr>

</table>
</body>
</html>
";

// Always set content-type when sending HTML email
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

// More headers
$headers .= 'From: <m&bcomputers@gmail.com>' . "\r\n";

mail("m&bcomputers@gmail.com",$subject,$message,$headers);
					
echo "<h3>Mesaj trimis!</h3>";

				}
   ?>
		
		<div class="clearfix"></div>
	</div>
</section>



<!-- map -->
<section class="map-w3" data-aos="zoom-in">
	<div style="width:100%;height:450px;border:0;background:#f4f4f4;display:flex;align-items:center;justify-content:center;">Hartă indisponibilă offline</div>
<h5> Calea Dumbravii nr.17, Sibiu,Romania</h5>
</section><br>
<!-- /map -->
<?php 
	require "website_footer.php";
?>
