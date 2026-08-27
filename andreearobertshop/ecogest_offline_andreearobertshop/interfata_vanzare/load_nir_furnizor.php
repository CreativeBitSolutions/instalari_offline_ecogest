<?php
    include('database_connection.php');
    
    if(isset($_GET['categ']))
    {
        //connect to database
        
        
        $c = $_GET['categ'];
        
        if($c==999){
            
            $test_sql = "SELECT nr_nir from $tabel_final_nir";    
$test_stm = $pdo->prepare($test_sql);  
$test_stm->execute(); 
while ($row = $test_stm->fetch(PDO::FETCH_ASSOC)){ 
    $nrnir=$row['nr_nir'];


     echo"<tr>
   <td  width='700'><form method='POST' ><button style='width:100%; font-size:1em; height:auto; white-space: normal;
' class='btn btn-primary btn-block' type='submit' name='$nrnir'>Nir $nrnir</button></form></td>
  </tr> ";  
       if(isset($_POST[$nrnir])){
    
$_SESSION['nr_nir']=$row['nr_nir'];
printf("<script>location.href='fisa_furnizor.php'</script>");
	
}
  
        }
        }
        $produse = '';
        
      $ppcksql = "SELECT nr_nir from $tabel_final_nir where cod_tert='$c'";    
$ppckstmt = $pdo->prepare($ppcksql);  
$ppckstmt->execute(); 
$prod_count=$ppckstmt->rowCount();

			        	while ($row = $ppckstmt->fetch(PDO::FETCH_ASSOC)){
			        	    
			        	      $nrnir=$row['nr_nir'];

              $produse .= "<tr>
   <td  width='700'><form method='POST' ><button  style='color:white;width:100%; font-size:1em; height:auto; white-space: normal;
' class='btn btn-primary btn-block' type='submit' name='$nrnir'>Nir $nrnir</button></form></td>
  </tr>";
  
   if(isset($_POST[$nrnir])){
    
$_SESSION['nr_nir']=$row['nr_nir'];
printf("<script>location.href='fisa_furnizor.php'</script>");
	
}
        } 
            
        
       
			        	}
        if($produse == ''){
            echo '';}
        else {
            echo $produse;}
    

?>
