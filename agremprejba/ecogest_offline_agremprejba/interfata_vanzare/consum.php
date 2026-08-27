<?php  include('session.php');
	$title = 'Bon de consum nou';
  include 'header.php';
	 include('check_priv.php');
?>

	
	   
				
<!-- about -->

<style>.table-bordered{
text-align:center;
}</style>
		
	<h3 align="center">Bon de consum nou</h3>
	
	 <div class="card mb-3">
        <div class="card-header">
          <i class="fa fa-table"></i> Antetul bonului de consum </div>
        <div class="card-body">
          <div class="table-responsive">

         
<form action='<?php $_PHP_SELF?>' method="post">
<table class='table table-bordered'> 
         <form action='<?php $_PHP_SELF?>' method="post">
        
 <?php 

$ultim_fact_sql = "SELECT max(nr_doc) as ultim_bon from $tabel_final_miscari where fel_doc='BCF'";    
$ultim_fact_stmt = $pdo->prepare($ultim_fact_sql);  
$ultim_fact_stmt->execute(); 

while ($row = $ultim_fact_stmt->fetch(PDO::FETCH_ASSOC)){ 
                      $ultim_bon=$row['ultim_bon']+1;
}
?>

 <?php 

$ultim_fact_sql2 = "SELECT max(nr_nota) as ultim_com from $tabel_final_miscari where fel_doc='BCF'";    
$ultim_fact_stmt2 = $pdo->prepare($ultim_fact_sql2);  
$ultim_fact_stmt2->execute(); 

while ($row = $ultim_fact_stmt2->fetch(PDO::FETCH_ASSOC)){ 
                      $ultim_com=$row['ultim_com']+1;
}
?>

<tr> 	 
  <td><h4>Număr Bon:</h4></td>
   <td><input  class="form-control" type="number" value="<?php echo $ultim_bon; ?>" name="nr_bon" ></td></tr> 
   
<tr> 	 
  <td><h4>Număr Comanda:</h4></td>
   <td><input  class="form-control" type="number" min="<?php echo $ultim_com; ?>" value="<?php echo $ultim_com; ?>" name="nr_com" ></td></tr> 
    <tr><td><h4>Data  Bon: </h4></td>
   <td><input class="form-control" type="date" name="data_bon" value="<?php echo date('Y-m-d');?>" ></td></tr>
   <tr><td><h4>Produs/ Factura servicii: </h4></td>
   <td><input class="form-control" type="text" name="produs"  ></td></tr>
   <tr><td><h4>Gestiunea predatoare: </h4></td>
   <td><input class="form-control" type="text" name="gestiune_pred"  ></td></tr>
    <tr><td><input class='btn btn-primary btn-block' type="submit" name="date_bon" value="Continuati"> </form></td>
 </tr></table>
 
 <?php 
if(isset($_POST['date_bon'])){
//  verifica daca exista date transmise 
  
     // preia datele din formular
	     $nr_bon = $_POST['nr_bon'];
    $produs = $_POST['produs'];
    $gestiune_pred = $_POST['gestiune_pred'];
	$data_bon=date($_POST['data_bon']);
	$nr_com=$_POST['nr_com'];
	$_SESSION['nr_bon_c']=$nr_bon;
	$_SESSION['data_bon']=$data_bon;


     // formeaza si executa queryul de inserare in baza de date 	 
$sql="insert into $tabel_final_bonuri_consum(nr_bon,data_bon,produs,gestiune_pred,nr_comanda) values('$nr_bon','$data_bon','$produs','$gestiune_pred','$nr_com')"; 	 

try{
$pdo->exec($sql) or die("<h3>Exista deja un bon cu acest numar.Va rugam introduceti alte date...</h3>"); 
   
    // afiseaza un mesaj de succes 
			printf("<script>location.href='bon_de_consum.php'</script>");
		  
}catch(PDOException $e)
    {
    echo $sql . "<br>" . $e->getMessage();
    }

}



else {echo"<h3 align='center'>Completati antetul bonului</h3>";}

 
  ?>

 
 <!-- InstanceEndEditable -->




  </div>
        </div>
        
      </div>



<?php 
	require "footer.php";
?>