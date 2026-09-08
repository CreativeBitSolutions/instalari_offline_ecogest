<?php

include 'database_connection.php'; // unset($_SESSION['shoppingbasket']);
//unset($_SESSION['custloggin']);
$title = 'Bookings';
include "header.php";
?>
<ol class="breadcrumb">
<li class="breadcrumb-item active">Customer Bookings</li>
</ol>
 <?php
if (isset($_SESSION['adminloggedin']) && $_SESSION['adminloggedin'] == true) {
?>
<a name="bottomOfPage"></a>
 <div class="card mb-3">
<div class="card-header">
<i class="fa fa-book"></i> Bookings List</div>
<div class="card-body">
<form style="float:right;" method="post" class="form-inline my-2 my-lg-0 mr-lg-2">
<div style=" width:23em;" class="input-group">
<input class="form-control" type="text" name="search" placeholder="Search for...">
<span class="input-group-btn">
<button class="btn btn-primary" type="submit">
<i class="fa fa-search"></i>
</button>
</span>
</div>
</form>
<style>td{width:15em;}
.action{width:1em;}</style>
<div class="table-responsive">
<?php
if (isset($_POST['search'])) {
    
    //if the search magnifying glass is clicked the below query selects multiple columns from  multiple tables based on the search form's input using the like operator.Some search parameters are experience title,experience category name and customer type
 $searchq = $_POST['search'];
 $searchq = preg_replace("#[^0-9a-z]#i","",$searchq);
 
 $search_query="SELECT bookings.availability_id,bookings.booking_id,bookings.company_name,bookings.customer_type,customers.customer_email_address,bookings.company_tin,bookings.booking_date,bookings.company_trade_reg_no,bookings.company_bank,bookings.company_bank_acc,bookings.company_acc_email,bookings.company_county,bookings.company_hq,experiences.experience_title,experiences.experience_image,experiences.experience_information,experiences.experience_price,experiences.start_time,experiences.end_time,bookings.quantity,bookings.experience_start,bookings.experience_end,experiences.id,bookings.customer_type,bookings.total,bookings.vat,bookings.total_with_vat,categories.category_name,customers.customer_firstname,customers.customer_lastname,bookings.status FROM bookings inner join experiences on bookings.booked_experience=experiences.id inner join categories on experiences.experience_category=categories.category_id INNER JOIN customers on bookings.customer_id=customers.customer_id WHERE experiences.experience_title LIKE '%$searchq%' or bookings.approved_by LiKE '%$searchq%' or experiences.experience_information LIKE '%$searchq%' or bookings.status LIKE '%$searchq%' or bookings.total_with_vat LIKE '%$searchq%' or bookings.total LIKE '%$searchq%'";
 $query=$pdo->prepare($search_query);
$query->execute();
$count=$query->rowCount();
 if($count == 0) {
echo "<br><h3 align='center'>No results found!</h3>";
 } else { 
echo "<table class='table table-bordered' id='dataTable' width='100%' cellspacing='0'>"; 
echo" <thead><tr><th> Experience</th><th>Booking date</th><th>Total price</th><th>Status</th><th colspan='3'>Action</th></tr></thead>"; 
 while($row = $query->fetch(PDO::FETCH_ASSOC)) {

$customer_firstname=$row['customer_firstname'];
$customer_lastname=$row['customer_lastname'];
$book_det=$row['booking_id'];
$book_del=$row['booking_id']+9000;
$book_approv=$row['booking_id']+99000;
$cust_type=$row['customer_type'];
$exp_id=$row['id'];
$quantity=$row['quantity'];
$availability_id=$row['availability_id'];
$book_status=$row['status'];
$cust_mail=$row['customer_email_address'];
echo"<tr>";
echo"<td>".$row['experience_title']."</td><td>".$row['booking_date']."</td><td>".$row['total_with_vat']."</td><td>".$row['status']."</td><td class='action'><form method='post'><input class='btn btn-primary btn-block' type='submit' value='Booking Details' name='$book_det'></td><td class='action'><input class='btn btn-primary btn-block' type='submit' value='Cancel Booking' name='$book_del'>"; if($book_status=="PENDING"){echo"<td class='action'><input class='btn btn-primary btn-block' type='submit' value='Approve' name='$book_approv'></form></td></tr>";}

if (isset($_POST[$book_det])) {

 $_SESSION['booking_id']=$book_det;
 $_SESSION['availability_id']=$availability_id;

 
printf("<script>location.href='admin_booking_details.php'</script>");
}

if (isset($_POST[$book_del])) {


$delsql = "SELECT customers.customer_title,customers.customer_firstname,customers.customer_lastname,customers.customer_address_line1,customers.customer_address_line2,customers.customer_address_line3,customers.customer_postcode,customers.customer_telephone_no,customers.customer_email_address,bookings.company_name,bookings.company_tin,bookings.company_trade_reg_no,bookings.company_bank,bookings.company_bank_acc,bookings.company_acc_email,bookings.company_county,bookings.company_hq,experiences.experience_title,experiences.start_time,experiences.end_time,bookings.quantity,bookings.experience_start,bookings.experience_end,experiences.id,bookings.total_with_vat,pick_up_points.pick_up_point_name,categories.category_name FROM bookings inner join experiences on bookings.booked_experience=experiences.id inner join categories on experiences.experience_category=categories.category_id inner join customers on bookings.customer_id=customers.customer_id inner join pick_up_points on bookings.pick_up_point_id=pick_up_points.pick_up_point_id where bookings.booking_id='$book_det'";
$delstmt = $pdo->prepare($delsql);
$delstmt->execute(); 
while ($row = $delstmt->fetch(PDO::FETCH_ASSOC)){
$exp_title=$row['experience_title'];
$curr_start_time=$row['start_time'];
$curr_end_time=$row['end_time'];
$curr_categ=$row['category_name'];
$exp_start_date=$row['experience_start'];
$exp_end_date=$row['experience_end'];
$booked_pers=$row['quantity'];
$book_total_with_vat=$row['total_with_vat'];
$book_company_name=$row['company_name'];
$book_company_tin=$row['company_tin'];
$book_company_trade_reg_no=$row['company_trade_reg_no'];
$book_company_bank=$row['company_bank'];
$book_company_bank_acc=$row['company_bank_acc'];
$book_company_acc_email=$row['company_acc_email'];
$book_company_county=$row['company_county'];
$book_company_hq=$row['company_hq'];
$customer_title=$row['customer_title'];
$customer_firstname=$row['customer_firstname'];
$customer_lastname=$row['customer_lastname'];
$customer_address_line1=$row['customer_address_line1'];
$customer_address_line2=$row['customer_address_line2'];
$customer_address_line3=$row['customer_address_line3'];
$customer_postcode=$row['customer_postcode'];
$customer_telephone_no=$row['customer_telephone_no'];
$customer_email_address=$row['customer_email_address'];
$pick_up_point=$row['pick_up_point_name'];

}
if($cust_type=="Individual"){
$subject = "Your Romantic Romania Booking has been cancelled!";
$message = "
<html>
<head>
</head>
<body><h4>Dear $customer_title $customer_firstname $customer_lastname,</h4><p>Unfortunately, we could not process your booking information. For more details, please do not hesitate to contact us at romantic-romania@gmail.com or by calling us at 0787422154.</p>
<h3>Your booking information:</h3>
<hr>
<table>
<tr><td>Booking Reference Number:</td><td>$book_det</td></tr>
<tr><td>Tour Title:</td><td>$exp_title</td></tr>
<tr><td>Tour Category:</td><td>$curr_categ</td></tr>
<tr><td>Number of Persons:</td><td>$booked_pers</td></tr>
<tr><td>Total Price:</td><td>$book_total_with_vat</td></tr>
<tr><td>Start Date:</td><td>$exp_start_date</td></tr>
<tr><td>Start Time:</td><td>$curr_start_time</td></tr>
<tr><td>End Date:</td><td>$exp_end_date</td></tr>
<tr><td>End Time:</td><td>$curr_end_time</td></tr>
<tr><td>Pick-up point:</td><td>$pick_up_point</td></tr>

</table>

<h3>Your provided billing details:</h3>
<hr>
<table>
<tr><td>First Name:</td><td>$customer_firstname</td></tr>
<tr><td>Surname:</td><td>$customer_lastname</td></tr>
<tr><td>Email Address:</td><td>$customer_email_address</td></tr>
<tr><td>Telephone Number:</td>$customer_telephone_no<td></td></tr>
<tr><td>Postcode:</td><td>$customer_postcode</td></tr>
<tr><td>Address Line 1:</td><td>$customer_address_line1</td></tr>
<tr><td>Address Line 2:</td><td>$customer_address_line2</td></tr>
<tr><td>Address Line 3:</td><td>$customer_address_line3</td></tr>
</table>


<h3><p>
Kind Regards,</p>
<p>Romantic Romania
</p></h3>
</body>
</html>
";
// Always set content-type when sending HTML email
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
// More headers
$headers .= 'From: <romantic-romania@gmail.com>' . "\r\n";
mail($cust_mail,$subject,$message,$headers);
}

else {
$subject = "Your Romantic Romania Booking has been cancelled!";
$message = "
<html>
<head>
</head>
<body><h4>Dear $customer_title $customer_firstname $customer_lastname,</h4>
<p>Unfortunately, we could not process your booking information. For more details, please do not hesitate to contact us at romantic-romania@gmail.com or by calling us at 0787422154.</p>
<h3>Company's booking information:</h3>
<hr>
<table>
<tr><td>Booking Reference Number:</td><td>$book_det</td></tr>
<tr><td>Tour Title:</td><td>$exp_title</td></tr>
<tr><td>Tour Category:</td><td>$curr_categ</td></tr>
<tr><td>Number of Persons:</td><td>$booked_pers</td></tr>
<tr><td>Total Price:</td><td>$book_total_with_vat</td></tr>
<tr><td>Start Date:</td><td>$exp_start_date</td></tr>
<tr><td>Start Time:</td><td>$curr_start_time</td></tr>
<tr><td>End Date:</td><td>$exp_end_date</td></tr>
<tr><td>End Time:</td><td>$curr_end_time</td></tr>
<tr><td>Pick-up point:</td><td>$pick_up_point</td></tr>
</table>
<h3>Company's provided billing details:</h3>
<hr>
<table>
<tr><td>Company Name:</td><td>$book_company_name</td></tr>
<tr><td>Tax Identification Number:</td><td>$book_company_tin</td></tr>
<tr><td>The Trade Registry Number:</td><td>$book_company_trade_reg_no</td></tr>
<tr><td>Bank:</td>$book_company_bank<td></td></tr>
<tr><td>Bank Account Number:</td><td>$book_company_bank_acc</td></tr>
<tr><td>Accountancy Department Email:</td><td>$book_company_acc_email</td></tr>
<tr><td>County:</td><td>$book_company_county</td></tr>
<tr><td>Headquarter Address:</td><td>$book_company_hq</td></tr>
</table>
<h3><p>
Kind Regards,</p>
<p>Romantic Romania
</p></h3>
</body>
</html>
";
// Always set content-type when sending HTML email
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

// More headers
$headers .= 'From: <romantic-romania@gmail.com>' . "\r\n";

mail($customer_email_address,$subject,$message,$headers);




}
$sql="UPDATE experiences_available_dates set quantity=quantity+'$quantity' where id='$availability_id';
DELETE FROM bookings WHERE bookings.booking_id ='$book_det';";
$pdo->exec($sql) or die(print_r($pdo->errorInfo(), true));
printf("<script>location.href='admin_bookings.php'</script>");


}

if (isset($_POST[$book_approv])) {

$approved_by=$_SESSION['adminloggedin'];

$sql="UPDATE bookings SET status='APPROVED',approved_by='$approved_by' where bookings.booking_id=$book_det;";
$pdo->exec($sql) or die(print_r($pdo->errorInfo(), true));


$bsql = "SELECT customers.customer_title,customers.customer_firstname,customers.customer_lastname,customers.customer_address_line1,customers.customer_address_line2,customers.customer_address_line3,customers.customer_postcode,customers.customer_telephone_no,customers.customer_email_address,bookings.company_name,bookings.company_tin,bookings.company_trade_reg_no,bookings.company_bank,pick_up_points.pick_up_point_name,bookings.company_bank_acc,bookings.company_acc_email,bookings.company_county,bookings.company_hq,experiences.experience_title,experiences.start_time,experiences.end_time,bookings.quantity,bookings.experience_start,bookings.experience_end,experiences.id,bookings.total_with_vat,categories.category_name FROM bookings inner join experiences on bookings.booked_experience=experiences.id inner join categories on experiences.experience_category=categories.category_id inner join customers on bookings.customer_id=customers.customer_id inner join pick_up_points on bookings.pick_up_point_id=pick_up_points.pick_up_point_id where bookings.booking_id='$book_det'";
$bstmt = $pdo->prepare($bsql);
$bstmt->execute(); 

while ($row = $bstmt->fetch(PDO::FETCH_ASSOC)){
$exp_title=$row['experience_title'];
$curr_start_time=$row['start_time'];
$curr_end_time=$row['end_time'];
$curr_categ=$row['category_name'];
$exp_start_date=$row['experience_start'];
$exp_end_date=$row['experience_end'];
$booked_pers=$row['quantity'];
$book_total_with_vat=$row['total_with_vat'];
$book_company_name=$row['company_name'];
$book_company_tin=$row['company_tin'];
$book_company_trade_reg_no=$row['company_trade_reg_no'];
$book_company_bank=$row['company_bank'];
$book_company_bank_acc=$row['company_bank_acc'];
$book_company_acc_email=$row['company_acc_email'];
$book_company_county=$row['company_county'];
$book_company_hq=$row['company_hq'];
$customer_title=$row['customer_title'];
$customer_firstname=$row['customer_firstname'];
$customer_lastname=$row['customer_lastname'];
$customer_address_line1=$row['customer_address_line1'];
$customer_address_line2=$row['customer_address_line2'];
$customer_address_line3=$row['customer_address_line3'];
$customer_postcode=$row['customer_postcode'];
$customer_telephone_no=$row['customer_telephone_no'];
$customer_email_address=$row['customer_email_address'];
$pick_up_point=$row['pick_up_point_name'];

}
if($cust_type=="Individual"){


 
$subject = "Your Romantic Romania Booking has been approved!";
$message = "
<html>
<head>
</head>
<body><h4>Dear $customer_title $customer_firstname $customer_lastname,</h4><p>Thank you for choosing to book with Romantic Romania! </p>
<p>Your booking has been approved!</p>
<h3>Your booking information:</h3>
<hr>
<table>
<tr><td>Booking Reference Number:</td><td>$book_det</td></tr>
<tr><td>Tour Title:</td><td>$exp_title</td></tr>
<tr><td>Tour Category:</td><td>$curr_categ</td></tr>
<tr><td>Number of Persons:</td><td>$booked_pers</td></tr>
<tr><td>Total Price:</td><td>$book_total_with_vat</td></tr>
<tr><td>Start Date:</td><td>$exp_start_date</td></tr>
<tr><td>Start Time:</td><td>$curr_start_time</td></tr>
<tr><td>End Date:</td><td>$exp_end_date</td></tr>
<tr><td>End Time:</td><td>$curr_end_time</td></tr>
<tr><td>Pick-up point:</td><td>$pick_up_point</td></tr>

</table>

<h3>Your provided billing details::</h3>
<hr>
<table>
<tr><td>First Name:</td><td>$customer_firstname</td></tr>
<tr><td>Surname:</td><td>$customer_lastname</td></tr>
<tr><td>Email Address:</td><td>$customer_email_address</td></tr>
<tr><td>Telephone Number:</td>$customer_telephone_no<td></td></tr>
<tr><td>Postcode:</td><td>$customer_postcode</td></tr>
<tr><td>Address Line 1:</td><td>$customer_address_line1</td></tr>
<tr><td>Address Line 2:</td><td>$customer_address_line2</td></tr>
<tr><td>Address Line 3:</td><td>$customer_address_line3</td></tr>
</table>

<p>You can now find and download the booking invoice from the website at the “Your bookings” section.</p>
<p>If you require more information, please do not hesitate to contact us at romantic-romania@gmail.com or by calling us at 0787422154.</p>
</p>
<h3><p>
Kind Regards,</p>
<p>Romantic Romania
</p></h3>
</body>
</html>
";

// Always set content-type when sending HTML email
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

// More headers
$headers .= 'From: <romantic-romania@gmail.com>' . "\r\n";

mail($cust_mail,$subject,$message,$headers);




}

else {


$subject = "Your Romantic Romania Booking has been approved!";
$message = "
<html>
<head>
</head>
<body><h4>Dear $customer_title $customer_firstname $customer_lastname,</h4>
<p>Thank you for choosing to book with Romantic Romania! </p>
<p>Your booking has been approved!</p>
<h3>Company's booking information:</h3>
<hr>
<table>
<tr><td>Booking Reference Number:</td><td>$book_det</td></tr>
<tr><td>Tour Title:</td><td>$exp_title</td></tr>
<tr><td>Tour Category:</td><td>$curr_categ</td></tr>
<tr><td>Number of Persons:</td><td>$booked_pers</td></tr>
<tr><td>Total Price:</td><td>$book_total_with_vat</td></tr>
<tr><td>Start Date:</td><td>$exp_start_date</td></tr>
<tr><td>Start Time:</td><td>$curr_start_time</td></tr>
<tr><td>End Date:</td><td>$exp_end_date</td></tr>
<tr><td>End Time:</td><td>$curr_end_time</td></tr>
<tr><td>Pick-up point:</td><td>$pick_up_point</td></tr>

</table>

<h3>Company's provided billing details::</h3>
<hr>
<table>
<tr><td>Company Name:</td><td>$book_company_name</td></tr>
<tr><td>Tax Identification Number:</td><td>$book_company_tin</td></tr>
<tr><td>The Trade Registry Number:</td><td>$book_company_trade_reg_no</td></tr>
<tr><td>Bank:</td>$book_company_bank<td></td></tr>
<tr><td>Bank Account Number:</td><td>$book_company_bank_acc</td></tr>
<tr><td>Accountancy Department Email:</td><td>$book_company_acc_email</td></tr>
<tr><td>County:</td><td>$book_company_county</td></tr>
<tr><td>Headquarter Address:</td><td>$book_company_hq</td></tr>
</table>

<p>You can now find and download the booking invoice from the website at the “Your bookings” section.</p>
<p>If you require more information, please do not hesitate to contact us at romantic-romania@gmail.com or by calling us at 0787422154.</p>
<h3><p>
Kind Regards,</p>
<p>Romantic Romania
</p></h3>
</body>
</html>
";

// Always set content-type when sending HTML email
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

// More headers
$headers .= 'From: <romantic-romania@gmail.com>' . "\r\n";

mail($customer_email_address,$subject,$message,$headers);




}
printf("<script>location.href='admin_bookings.php'</script>");

}


}
echo" <tfoot><tr><th>Experience</th><th>Booking date</th><th>Total price</th><th>Status</th><th colspan='3'>Action</th></tr></tfoot>"; 
 


echo "</table>";

}
}

if (!isset($_POST['search'])) {

echo "<table style='display:block' class='table table-bordered' id='dataTable' width='100%' cellspacing='0'>"; 
 
 
}

else {
echo "<table style='display:none' class='table table-bordered' id='dataTable' width='100%' cellspacing='0'>"; 

}
$dsql = "SELECT bookings.availability_id,bookings.booking_id,bookings.company_name,bookings.customer_type,customers.customer_email_address,bookings.company_tin,bookings.booking_date,bookings.company_trade_reg_no,bookings.company_bank,bookings.company_bank_acc,bookings.company_acc_email,bookings.company_county,bookings.company_hq,experiences.experience_title,experiences.experience_image,experiences.experience_information,experiences.experience_price,experiences.start_time,experiences.end_time,bookings.quantity,bookings.experience_start,bookings.experience_end,experiences.id,bookings.customer_type,bookings.status,bookings.total,bookings.vat,bookings.total_with_vat,categories.category_name,customers.customer_firstname,customers.customer_lastname,bookings.status FROM bookings inner join experiences on bookings.booked_experience=experiences.id inner join categories on experiences.experience_category=categories.category_id INNER JOIN customers on bookings.customer_id=customers.customer_id ORDER BY bookings.status,bookings.booking_date";
$dstmt = $pdo->prepare($dsql);
$dstmt->execute();

echo" <thead><tr><th> Experience</th><th>Booking date</th><th>Total price</th><th>Status</th><th colspan='3'>Action</th></tr></thead>"; 
while ($row = $dstmt->fetch(PDO::FETCH_ASSOC)){

$customer_firstname=$row['customer_firstname'];
 $customer_lastname=$row['customer_lastname'];
$book_det=$row['booking_id'];
$book_del=$row['booking_id']+9000;
$book_approv=$row['booking_id']+99000;
$cust_type=$row['customer_type'];
$exp_id=$row['id'];
$quantity=$row['quantity'];
$availability_id=$row['availability_id'];
$book_status=$row['status'];
$cust_mail=$row['customer_email_address'];
echo"<tr>";
echo"<td>".$row['experience_title']."</td><td>".$row['booking_date']."</td><td>".$row['total_with_vat']."</td><td>".$row['status']."</td><td class='action'><form method='post'><input class='btn btn-primary btn-block' type='submit' value='Booking Details' name='$book_det'></td><td class='action'><input class='btn btn-primary btn-block' type='submit' value='Cancel Booking' name='$book_del'><td class='action'>"; if($book_status=="PENDING"){echo"<input class='btn btn-primary btn-block' type='submit' value='Approve' name='$book_approv'></form></td></tr>";}

if (isset($_POST[$book_det])) {

 $_SESSION['booking_id']=$book_det;

 
printf("<script>location.href='admin_booking_details.php'</script>");
}

if (isset($_POST[$book_del])) {





$delsql = "SELECT customers.customer_title,customers.customer_firstname,customers.customer_lastname,customers.customer_address_line1,customers.customer_address_line2,customers.customer_address_line3,customers.customer_postcode,customers.customer_telephone_no,customers.customer_email_address,bookings.company_name,bookings.company_tin,bookings.company_trade_reg_no,bookings.company_bank,bookings.company_bank_acc,bookings.company_acc_email,bookings.company_county,bookings.company_hq,experiences.experience_title,experiences.start_time,experiences.end_time,bookings.quantity,bookings.experience_start,bookings.experience_end,experiences.id,bookings.total_with_vat,pick_up_points.pick_up_point_name,categories.category_name FROM bookings inner join experiences on bookings.booked_experience=experiences.id inner join categories on experiences.experience_category=categories.category_id inner join customers on bookings.customer_id=customers.customer_id inner join pick_up_points on bookings.pick_up_point_id=pick_up_points.pick_up_point_id where bookings.booking_id='$book_det'";
$delstmt = $pdo->prepare($delsql);
$delstmt->execute(); 


while ($row = $delstmt->fetch(PDO::FETCH_ASSOC)){
$exp_title=$row['experience_title'];
$curr_start_time=$row['start_time'];
$curr_end_time=$row['end_time'];
$curr_categ=$row['category_name'];
$exp_start_date=$row['experience_start'];
$exp_end_date=$row['experience_end'];
$booked_pers=$row['quantity'];
$book_total_with_vat=$row['total_with_vat'];
$book_company_name=$row['company_name'];
$book_company_tin=$row['company_tin'];
$book_company_trade_reg_no=$row['company_trade_reg_no'];
$book_company_bank=$row['company_bank'];
$book_company_bank_acc=$row['company_bank_acc'];
$book_company_acc_email=$row['company_acc_email'];
$book_company_county=$row['company_county'];
$book_company_hq=$row['company_hq'];
$customer_title=$row['customer_title'];
$customer_firstname=$row['customer_firstname'];
 $customer_lastname=$row['customer_lastname'];
$customer_address_line1=$row['customer_address_line1'];
$customer_address_line2=$row['customer_address_line2'];
$customer_address_line3=$row['customer_address_line3'];
$customer_postcode=$row['customer_postcode'];
$customer_telephone_no=$row['customer_telephone_no'];
$customer_email_address=$row['customer_email_address'];
$pick_up_point=$row['pick_up_point_name'];

}
if($cust_type=="Individual"){

// $ the subject of the email that appears in the receiver inbox/spam
$subject = "Your Romantic Romania Booking has been cancelled!";
// the message of the email formatted using html tags
$message = "
<html>
<head>
</head>
<body><h4>Dear $customer_title $customer_firstname $customer_lastname,</h4><p>Unfortunately, we could not process your booking information. For more details, please do not hesitate to contact us at romantic-romania@gmail.com or by calling us at 0787422154.</p>
<h3>Your booking information:</h3>
<hr>
<table>
<tr><td>Booking Reference Number:</td><td>$book_det</td></tr>
<tr><td>Tour Title:</td><td>$exp_title</td></tr>
<tr><td>Tour Category:</td><td>$curr_categ</td></tr>
<tr><td>Number of Persons:</td><td>$booked_pers</td></tr>
<tr><td>Total Price:</td><td>$book_total_with_vat</td></tr>
<tr><td>Start Date:</td><td>$exp_start_date</td></tr>
<tr><td>Start Time:</td><td>$curr_start_time</td></tr>
<tr><td>End Date:</td><td>$exp_end_date</td></tr>
<tr><td>End Time:</td><td>$curr_end_time</td></tr>
<tr><td>Pick-up point:</td><td>$pick_up_point</td></tr>

</table>

<h3>Your provided billing details:</h3>
<hr>
<table>
<tr><td>First Name:</td><td>$customer_firstname</td></tr>
<tr><td>Surname:</td><td>$customer_lastname</td></tr>
<tr><td>Email Address:</td><td>$customer_email_address</td></tr>
<tr><td>Telephone Number:</td>$customer_telephone_no<td></td></tr>
<tr><td>Postcode:</td><td>$customer_postcode</td></tr>
<tr><td>Address Line 1:</td><td>$customer_address_line1</td></tr>
<tr><td>Address Line 2:</td><td>$customer_address_line2</td></tr>
<tr><td>Address Line 3:</td><td>$customer_address_line3</td></tr>
</table>


<h3><p>
Kind Regards,</p>
<p>Romantic Romania
</p></h3>
</body>
</html>
";

// Always set content-type when sending HTML email
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";


// Set "From" header 
$headers .= 'From: <romantic-romania@gmail.com>' . "\r\n";
 
 // $cust_mail specifies the receiver or the receivers of the email message
// send email to the customer with the subject " Your Romantic Romania Booking has been cancelled!".The content of the message changes depending on the booking details.
mail($cust_mail,$subject,$message,$headers);




}

else {


$subject = "Your Romantic Romania Booking has been cancelled!";
$message = "
<html>
<head>
</head>
<body><h4>Dear $customer_title $customer_firstname $customer_lastname,</h4>
<p>Unfortunately, we could not process your booking information. For more details, please do not hesitate to contact us at romantic-romania@gmail.com or by calling us at 0787422154.</p>
<h3>Company's booking information:</h3>
<hr>
<table>
<tr><td>Booking Reference Number:</td><td>$book_det</td></tr>
<tr><td>Tour Title:</td><td>$exp_title</td></tr>
<tr><td>Tour Category:</td><td>$curr_categ</td></tr>
<tr><td>Number of Persons:</td><td>$booked_pers</td></tr>
<tr><td>Total Price:</td><td>$book_total_with_vat</td></tr>
<tr><td>Start Date:</td><td>$exp_start_date</td></tr>
<tr><td>Start Time:</td><td>$curr_start_time</td></tr>
<tr><td>End Date:</td><td>$exp_end_date</td></tr>
<tr><td>End Time:</td><td>$curr_end_time</td></tr>
<tr><td>Pick-up point:</td><td>$pick_up_point</td></tr>

</table>

<h3>Company's provided billing details:</h3>
<hr>
<table>
<tr><td>Company Name:</td><td>$book_company_name</td></tr>
<tr><td>Tax Identification Number:</td><td>$book_company_tin</td></tr>
<tr><td>The Trade Registry Number:</td><td>$book_company_trade_reg_no</td></tr>
<tr><td>Bank:</td>$book_company_bank<td></td></tr>
<tr><td>Bank Account Number:</td><td>$book_company_bank_acc</td></tr>
<tr><td>Accountancy Department Email:</td><td>$book_company_acc_email</td></tr>
<tr><td>County:</td><td>$book_company_county</td></tr>
<tr><td>Headquarter Address:</td><td>$book_company_hq</td></tr>
</table>


<h3><p>
Kind Regards,</p>
<p>Romantic Romania
</p></h3>
</body>
</html>
";

// Always set content-type when sending HTML email
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

// More headers
$headers .= 'From: <romantic-romania@gmail.com>' . "\r\n";

mail($customer_email_address,$subject,$message,$headers);




}
$sql="UPDATE experiences_available_dates set quantity=quantity+'$quantity' where id='$availability_id';
DELETE FROM bookings WHERE bookings.booking_id ='$book_det';";
$pdo->exec($sql) or die(print_r($pdo->errorInfo(), true));
printf("<script>location.href='admin_bookings.php'</script>");


}

if (isset($_POST[$book_approv])) {

$approved_by=$_SESSION['adminloggedin'];

$sql="UPDATE bookings SET status='APPROVED',approved_by='$approved_by' where bookings.booking_id=$book_det;";
$pdo->exec($sql) or die(print_r($pdo->errorInfo(), true));


$bsql = "SELECT customers.customer_title,customers.customer_firstname,customers.customer_lastname,customers.customer_address_line1,customers.customer_address_line2,customers.customer_address_line3,customers.customer_postcode,customers.customer_telephone_no,customers.customer_email_address,bookings.company_name,bookings.company_tin,bookings.company_trade_reg_no,bookings.company_bank,pick_up_points.pick_up_point_name,bookings.company_bank_acc,bookings.company_acc_email,bookings.company_county,bookings.company_hq,experiences.experience_title,experiences.start_time,experiences.end_time,bookings.quantity,bookings.experience_start,bookings.experience_end,experiences.id,bookings.total_with_vat,categories.category_name FROM bookings inner join experiences on bookings.booked_experience=experiences.id inner join categories on experiences.experience_category=categories.category_id inner join customers on bookings.customer_id=customers.customer_id inner join pick_up_points on bookings.pick_up_point_id=pick_up_points.pick_up_point_id where bookings.booking_id='$book_det'";
$bstmt = $pdo->prepare($bsql);
$bstmt->execute(); 


while ($row = $bstmt->fetch(PDO::FETCH_ASSOC)){
$exp_title=$row['experience_title'];
$curr_start_time=$row['start_time'];
$curr_end_time=$row['end_time'];
$curr_categ=$row['category_name'];
$exp_start_date=$row['experience_start'];
$exp_end_date=$row['experience_end'];
$booked_pers=$row['quantity'];
$book_total_with_vat=$row['total_with_vat'];
$book_company_name=$row['company_name'];
$book_company_tin=$row['company_tin'];
$book_company_trade_reg_no=$row['company_trade_reg_no'];
$book_company_bank=$row['company_bank'];
$book_company_bank_acc=$row['company_bank_acc'];
$book_company_acc_email=$row['company_acc_email'];
$book_company_county=$row['company_county'];
$book_company_hq=$row['company_hq'];
$customer_title=$row['customer_title'];
$customer_firstname=$row['customer_firstname'];
 $customer_lastname=$row['customer_lastname'];
$customer_address_line1=$row['customer_address_line1'];
$customer_address_line2=$row['customer_address_line2'];
$customer_address_line3=$row['customer_address_line3'];
$customer_postcode=$row['customer_postcode'];
$customer_telephone_no=$row['customer_telephone_no'];
$customer_email_address=$row['customer_email_address'];
$pick_up_point=$row['pick_up_point_name'];

}
if($cust_type=="Individual"){


 
$subject = "Your Romantic Romania Booking has been approved!";
$message = "
<html>
<head>
</head>
<body><h4>Dear $customer_title $customer_firstname $customer_lastname,</h4><p>Thank you for choosing to book with Romantic Romania! </p>
<p>Your booking has been approved!</p>
<h3>Your booking information:</h3>
<hr>
<table>
<tr><td>Booking Reference Number:</td><td>$book_det</td></tr>
<tr><td>Tour Title:</td><td>$exp_title</td></tr>
<tr><td>Tour Category:</td><td>$curr_categ</td></tr>
<tr><td>Number of Persons:</td><td>$booked_pers</td></tr>
<tr><td>Total Price:</td><td>$book_total_with_vat</td></tr>
<tr><td>Start Date:</td><td>$exp_start_date</td></tr>
<tr><td>Start Time:</td><td>$curr_start_time</td></tr>
<tr><td>End Date:</td><td>$exp_end_date</td></tr>
<tr><td>End Time:</td><td>$curr_end_time</td></tr>
<tr><td>Pick-up point:</td><td>$pick_up_point</td></tr>

</table>

<h3>Your provided billing details::</h3>
<hr>
<table>
<tr><td>First Name:</td><td>$customer_firstname</td></tr>
<tr><td>Surname:</td><td>$customer_lastname</td></tr>
<tr><td>Email Address:</td><td>$customer_email_address</td></tr>
<tr><td>Telephone Number:</td>$customer_telephone_no<td></td></tr>
<tr><td>Postcode:</td><td>$customer_postcode</td></tr>
<tr><td>Address Line 1:</td><td>$customer_address_line1</td></tr>
<tr><td>Address Line 2:</td><td>$customer_address_line2</td></tr>
<tr><td>Address Line 3:</td><td>$customer_address_line3</td></tr>
</table>

<p>You can now find and download the booking invoice from the website at the “Your bookings” section.</p>
<p>If you require more information, please do not hesitate to contact us at romantic-romania@gmail.com or by calling us at 0787422154.</p>
</p>
<h3><p>
Kind Regards,</p>
<p>Romantic Romania
</p></h3>
</body>
</html>
";

// Always set content-type when sending HTML email
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

// More headers
$headers .= 'From: <romantic-romania@gmail.com>' . "\r\n";

mail($cust_mail,$subject,$message,$headers);




}

else {


$subject = "Your Romantic Romania Booking has been approved!";
$message = "
<html>
<head>
</head>
<body><h4>Dear $customer_title $customer_firstname $customer_lastname,</h4>
<p>Thank you for choosing to book with Romantic Romania! </p>
<p>Your booking has been approved!</p>
<h3>Company's booking information:</h3>
<hr>
<table>
<tr><td>Booking Reference Number:</td><td>$book_det</td></tr>
<tr><td>Tour Title:</td><td>$exp_title</td></tr>
<tr><td>Tour Category:</td><td>$curr_categ</td></tr>
<tr><td>Number of Persons:</td><td>$booked_pers</td></tr>
<tr><td>Total Price:</td><td>$book_total_with_vat</td></tr>
<tr><td>Start Date:</td><td>$exp_start_date</td></tr>
<tr><td>Start Time:</td><td>$curr_start_time</td></tr>
<tr><td>End Date:</td><td>$exp_end_date</td></tr>
<tr><td>End Time:</td><td>$curr_end_time</td></tr>
<tr><td>Pick-up point:</td><td>$pick_up_point</td></tr>

</table>

<h3>Company's provided billing details::</h3>
<hr>
<table>
<tr><td>Company Name:</td><td>$book_company_name</td></tr>
<tr><td>Tax Identification Number:</td><td>$book_company_tin</td></tr>
<tr><td>The Trade Registry Number:</td><td>$book_company_trade_reg_no</td></tr>
<tr><td>Bank:</td>$book_company_bank<td></td></tr>
<tr><td>Bank Account Number:</td><td>$book_company_bank_acc</td></tr>
<tr><td>Accountancy Department Email:</td><td>$book_company_acc_email</td></tr>
<tr><td>County:</td><td>$book_company_county</td></tr>
<tr><td>Headquarter Address:</td><td>$book_company_hq</td></tr>
</table>

<p>You can now find and download the booking invoice from the website at the “Your bookings” section.</p>
<p>If you require more information, please do not hesitate to contact us at romantic-romania@gmail.com or by calling us at 0787422154.</p>
<h3><p>
Kind Regards,</p>
<p>Romantic Romania
</p></h3>
</body>
</html>
";

// Always set content-type when sending HTML email
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

// More headers
$headers .= 'From: <romantic-romania@gmail.com>' . "\r\n";

mail($customer_email_address,$subject,$message,$headers);




}
printf("<script>location.href='admin_bookings.php'</script>");

}


}
echo" <tfoot><tr><th>Experience</th><th>Booking date</th><th>Total price</th><th>Status</th><th colspan='3'>Action</th></tr></tfoot>"; 


echo "</table>";
?>
</div>
</div>

<div class="card-footer small text-muted">
<?php
$bookings="SELECT * FROM bookings ORDER BY booking_date DESC LIMIT 1";
 $book=$pdo->prepare($bookings);
$book->execute();
 while ($row = $book->fetch()) {
 $latest_book=$row['booking_date'];
 }

 
echo "Updated on: ".$latest_book;?> </div>
</div>
<?php
}
else {
?>
printf("<script>location.href='admin_login.php'</script>");

<?php
}
?>


<?php 
require "footer.php";
?>