<?php
    include('database_connection.php');
    
    if(isset($_GET['categ']))
    {
        //connect to database
        
        
        $c = $_GET['categ'];
        
        if($c==999){
            
            $test_sql = "SELECT nrfactura from $tabel_final_facturi";    
$test_stm = $pdo->prepare($test_sql);  
$test_stm->execute(); 
while ($row = $test_stm->fetch(PDO::FETCH_ASSOC)){ 
    $nrfactura=$row['nrfactura'];


     echo"<tr>
   <td  width='700'><form method='POST' ><button style='width:100%; font-size:1em; height:auto; white-space: normal;
' class='btn btn-primary btn-block' type='submit' name='$nrfactura'>Factura $nrfactura</button></form></td>
  </tr> ";  
       if(isset($_POST[$nrfactura])){
    
$_SESSION['nr_factura']=$row['nrfactura'];
printf("<script>location.href='fisa_client.php'</script>");
	
}
  
        }
        }
        $produse = '';
        
      $ppcksql = "SELECT nrfactura from $tabel_final_facturi where cod_client='$c'";    
$ppckstmt = $pdo->prepare($ppcksql);  
$ppckstmt->execute(); 
$prod_count=$ppckstmt->rowCount();

			        	while ($row = $ppckstmt->fetch(PDO::FETCH_ASSOC)){
			        	    
			        	      $nrfactura=$row['nrfactura'];

              $produse .= "<tr>
   <td  width='700'><form method='POST' ><button  style='color:white;width:100%; font-size:1em; height:auto; white-space: normal;
' class='btn btn-primary btn-block' type='submit' name='$nrfactura'>Factura $nrfactura</button></form></td>
  </tr>";
  
   if(isset($_POST[$nrfactura])){
    
$_SESSION['nr_factura']=$row['nrfactura'];
printf("<script>location.href='fisa_client.php'</script>");
	
}
        } 
            
        
       
			        	}
        if($produse == ''){
            echo '';}
        else {
            echo $produse;}
    

?>
