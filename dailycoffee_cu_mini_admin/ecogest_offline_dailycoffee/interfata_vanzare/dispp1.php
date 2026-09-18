<?php  include('session.php');
	$title = 'Dispozitie noua';
  include 'header.php';
	
?>

	
	   
				
<!-- about -->

<style>.table-bordered{
text-align:center;
}</style>
		
	<h3 align="center">Dispozitie Noua</h3>
	
	 <div class="card mb-3">
        <div class="card-header">
          <i class="fa fa-table"></i> Antetul Dispozitiei</div>
        <div class="card-body">
          <div class="table-responsive">

         
<form action='<?php $_PHP_SELF?>' method="post">
<table class='table table-bordered'> 
  <tr>
  <td><h4>Tip Dispozitie:</h4></td><td>
  <select class = "form-control" name="tip_disp">
  <option value="p">Platire</option>
  <option value="i">Incasare</option>
 
</select>
</td>
<tr> 	 
  <td><h4>Numarul Dispozitiei:</h4></td>
   <td><input class="form-control" type="number" name="nr_dispozitie" ></td></tr> 
   
<tr> 
 <td><h4>Seria Dispozitiei:</h4></td>
 <td><input class="form-control" type="text" name="serie_dispozitie" maxlength="11">
 </td></tr>
 
     <td><h4>Data Dispozitie:</h4></td>
   <td><input class="form-control" type="date" name="data_dispozitie" ></td></tr> 
    <tr><td><input class='btn btn-primary btn-block' type="submit" name="date_dispozitie" value="Continuati"> </form></td>
 </tr></table>
 
 <?php 
if(isset($_POST['date_dispozitie'])){
//  verifica daca exista date transmise 
if ($_POST['serie_dispozitie'] != "" &&
$_POST['nr_dispozitie'] != '' &&
$_POST['data_dispozitie'] != '' )   
{ 
     // preia datele din formular
	     $nr_dispozitie = $_POST['nr_dispozitie'];
	     $tip_disp = $_POST['tip_disp'];
    $serie_dispozitie = $_POST['serie_dispozitie'];
		date_default_timezone_set('UTC');
	$data_dispozitie=date($_POST['data_dispozitie']);
	$_SESSION['serie_dispozitie']=$serie_dispozitie;
	$_SESSION['tip_disp']=$tip_disp;
	$_SESSION['nr_dispozitie']=$nr_dispozitie;
	$_SESSION['data_dispozitie']=$data_dispozitie;
$sql="insert into $tabel_final_dispozitii(nr_disp,data_disp,serie_disp,tip_disp) values('$nr_dispozitie','$data_dispozitie','$serie_dispozitie','$tip_disp')"; 	 

try{
$pdo->exec($sql) or die("<h3>Exista deja o dispozitie cu acest numar.Va rugam introduceti alte date...</h3>"); 
   
    // afiseaza un mesaj de succes 
			printf("<script>location.href='dispp2.php'</script>");
		  
}catch(PDOException $e)
    {
    echo $sql . "<br>" . $e->getMessage();
    }
}
else 
	echo"<h3>Atentie completati toate campurile</h3>";
}
else {echo"<h3 align='center'>Completati antetul dispozitiei</h3>";}

 
  ?>

 
 <!-- InstanceEndEditable -->




  </div>
        </div>
        
      </div>



<?php 
	require "footer.php";
?>