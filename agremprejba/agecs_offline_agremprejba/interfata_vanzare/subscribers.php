<?php

include 'database_connection.php';
$title = 'Abonati';
include 'header.php';

?>

      <!-- Breadcrumbs-->
      <ol class="breadcrumb">
        
        <li class="breadcrumb-item active">Abonati</li>
      </ol>


       

      <!-- Example DataTables Card-->
      <div class="card mb-3">
        <div class="card-header">
          <i class="fa fa-table"></i> Lista Abonatilor</div>
        <div class="card-body">
          <div class="table-responsive">


<?php

echo '<section class="right">';
$sql="SELECT * FROM $tabel_final_abonati";
$stmt = $pdo->prepare($sql);

$stmt->execute();

echo '<table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">';
echo '<thead>
<tr>
<th>ID</th>
<th>Adresa de Email</th>
<th>Optiune</th>

</tr>
</thead>';
echo '<tfoot>
<tr>
<th>ID</th>
<th>Adresa de email</th>
<th>Optiune</th>

</tr>
</tfoot>';
echo '<tbody>';

while ($row = $stmt->fetch()) {
    
$sub_del=$row['id_abonat'];
$sub_email=$row['adresa_de_email_abonat'];
echo '<tr>
<td>' . $row['id_abonat'] . '</td>
<td>' . $row['adresa_de_email_abonat'] . '</td>';
echo "<td><form method='post'><input class='btn btn-primary btn-block' type='submit' value='Sterge' name='$sub_del'></form></td></tr>";


if (isset($_POST[$sub_del])) {



$sql="DELETE FROM $tabel_final_abonati WHERE id_abonat ='$sub_del';";
$pdo->exec($sql) or die(print_r($pdo->errorInfo(), true));
$subject = "Dezabonat de la newsletter";
$message = " 
<html>
<head>
</head>
<body><h4>Ati fost dezabonat de la newsletterul M&B COMPUTERS SHOP!</h4>

</body>
</html>
";

// Always set content-type when sending HTML email
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

// More headers
$headers .= 'From: <m&bcomputers@gmail.com>' . "\r\n";

mail($sub_email,$subject,$message,$headers);

echo "<h3>Message sent!</h3>";
printf("<script>location.href='subscribers.php'</script>");


}

}
echo '</tbody>';
echo '</table>';

echo '</section>';
?>
          </div>
        </div>
      </div>
    </div>


    
<?php include 'footer.php'; ?>