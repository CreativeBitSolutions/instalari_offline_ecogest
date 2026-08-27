<table class='table'><thead><tr><th>Operator</th><th>Valoare</th></tr></thead>
<?php 
     include('database_connection.php');

$data_start=$_GET['data_start'];
$data_stop=$_GET['data_stop'];
$timp_start=$_GET['timp_start'];
$timp_stop=$_GET['timp_stop'];

echo "<h3>Raportul vânzărilor pe angajat</h3>";
echo "<h5>De la: ".$data_start." ".$timp_start." <br/> La: ".$data_stop." ".$timp_stop."</h5>";

				$admin_em=$_SESSION['adminloggedin'];
$asql = "SELECT admin_id,admin_firstname,admin_lastname FROM $tabel_final_admins";    
$astmt = $pdo->prepare($asql);  
$astmt->execute(); 


while ($row = $astmt->fetch(PDO::FETCH_ASSOC)){  
      $admin_id=$row['admin_id'];
      $admin_firstname=$row['admin_firstname'];
            $admin_lastname=$row['admin_lastname'];
 echo '<tr><td>'.$admin_firstname.' '.$admin_lastname.'</td>';
 
      $asql2 = "SELECT sum(valoare_vanzare_cu_tva) as total FROM $tabel_final_note where operator='$admin_id' and data_bon>='$data_start' and data_bon<='$data_stop' and ora_bon>='$timp_start' and ora_bon<='$timp_stop'";    
$astmt2 = $pdo->prepare($asql2);  
$astmt2->execute(); 
while ($rrow = $astmt2->fetch(PDO::FETCH_ASSOC)){  
    
    $total=$rrow['total'];
    
    if (empty($total)) {
        $total=0;
        
    }
    echo '<td>'.$total.'</td>';
    
}


}

?>

</table>