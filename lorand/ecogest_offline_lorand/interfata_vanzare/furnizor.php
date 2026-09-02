<?php  include('session.php');
	$title = 'NIR noua';
  include 'header.php';
	 include('check_priv.php');
?>

	
	   
				
<!-- about -->

<style>.table-bordered{
text-align:center;
}</style>
		
	<h3 align="center">NIR noua</h3>
	
	 <div class="card mb-3">
        <div class="card-header">
          <i class="fa fa-table"></i> Antetul Notei de Receptie si Constatare Diferente</div>
        <div class="card-body">
          <div class="table-responsive">

         
<form action='<?php $_PHP_SELF?>' method="post">
<table class='table table-bordered'> 
         <form action='<?php $_PHP_SELF?>' method="post">
        
  <tr>
  <td><h4>Furnizor:</h4></td><td>
  <?php
	 
$tsql = "SELECT * from $tabel_final_terti where tip_tert='furnizor'";    
$stmt = $pdo->prepare($tsql);  
$stmt->execute(); 

echo "<select class='form-control' name='furnizor'>"; 
 
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)){ 
                      $den_tert=$row['denumire'];
			             $cod_tert=$row['cod_tert']; 
              echo "<option value='$cod_tert'>$den_tert</option>";    
             
			

}
echo "</select>";

echo "</td></tr>"; 

$ultim_fact_sql = "SELECT max(nr_doc) as ultim_nir from $tabel_final_miscari  where fel_doc='NIR' and nr_doc<9999999 ";    
$ultim_fact_stmt = $pdo->prepare($ultim_fact_sql);  
$ultim_fact_stmt->execute(); 


while ($row = $ultim_fact_stmt->fetch(PDO::FETCH_ASSOC)){ 
                      $ultim_nir=$row['ultim_nir']+1;
}
?>


<tr> 	 
  <td><h4>Numar NIR:</h4></td>
   <td><input class="form-control" type="text" value="<?php echo $ultim_nir; ?>" name="nr_nir" ></td></tr> 
   
<tr> 
 <td><h4>Serie Doc. Intrare:</h4></td>
 <td><input class="form-control" type="text" name="serie_d" maxlength="11">
 </td></tr>
  <tr> 	 
  <td><h4>Numar Doc. Intrare:</h4></td>
 <td><input class="form-control" type="number" name="nr_d" ></td></tr>
       <tr><td><h4>Data  doc.intrare:</h4></td>
   <td><input class="form-control" type="date" name="data_doc" value="<?php echo date('Y-m-d');?>" ></td></tr> 
    <tr><td><h4>Data  NIR: </h4></td>
   <td><input class="form-control" type="date" name="data_nir" value="<?php echo date('Y-m-d');?>" ></td></tr>
    <tr><td><input class='btn btn-primary btn-block' type="submit" name="date_nir" value="Continuati"> </form></td>
 </tr></table>
 
 <?php 
if(isset($_POST['date_nir'])){
//  verifica daca exista date transmise 
if ($_POST['nr_d'] != "" &&
$_POST['nr_nir'] != '' &&
$_POST['data_doc'] != '' )   
{ 	$gestionar=$_SESSION["login_user"];
     // preia datele din formular
	     $nr_nir = $_POST['nr_nir'];
    $serie_d = $_POST['serie_d'];
    $nr_d = $_POST['nr_d'];
	$furnizor = $_POST['furnizor'];
	$data_doc=date($_POST['data_doc']);
	$_SESSION['cod_furnizor']=$furnizor;
	$_SESSION['serie_d']=$serie_d;
	$_SESSION['nr_nir']=$nr_nir;
	$_SESSION['nr_d']=$nr_d;

	$_SESSION['data_doc']=$data_doc;
	$data_nir = $_POST['data_nir']; 

     // formeaza si executa queryul de inserare in baza de date 	 
$sql="insert into $tabel_final_nir(nr_nir,data_nir,cod_tert,serie_doc_int,nr_doc_int,data_doc_int,gestionar) values('$nr_nir','$data_nir','$furnizor','$serie_d','$nr_d','$data_doc','$gestionar')"; 	 

try{
$pdo->exec($sql) or die("<h3>Exista deja un nir cu acest numar.Va rugam introduceti alte date...</h3>"); 
   
    // afiseaza un mesaj de succes 
			printf("<script>location.href='nir.php'</script>");
		  
}catch(PDOException $e)
    {
    echo $sql . "<br>" . $e->getMessage();
    }
}
else 
	echo"<h3>Atentie completati toate campurile</h3>";
}
else {echo"<h3 align='center'>Completati antetul NIR-ului</h3>";}

 
  ?>

 
 <!-- InstanceEndEditable -->




  </div>
        </div>
        
      </div>



<?php 
	require "footer.php";
?>