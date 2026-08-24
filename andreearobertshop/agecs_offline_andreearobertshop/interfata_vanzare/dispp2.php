<?php  include('session.php');
	$title = 'Dispozitie noua';
  include 'header.php';
	if(!isset($_SESSION['nr_dispozitie'])){
	  printf("<script>location.href='facturi.php'</script>");

	}
?>

	
	   
				
<!-- about -->

<style>.table-bordered{
text-align:center;
}</style>
		

	 <div class="card mb-3">
        <div class="card-header">
          <i class="fa fa-table"></i> Completati Dispoziția</div>
        <div class="card-body">
     <table class='table table-bordered'>   
<tr>
  <tr> 
 <td><h4>Seria Dispoziției:</h4></td>
 <td> <?php
echo $_SESSION['serie_dispozitie']; 
 

?>
 </td></tr>
  <tr> 	 
  <td><h4>Numărul Dispoziției:</h4></td>
   <td> <?php
echo $_SESSION['nr_dispozitie']; 
 

?></td></tr> 
    <tr><td><h4>Data Dispoziției:</h4></td>
   <td><?php
   $data_fact=date( 'd-m-Y', strtotime( $_SESSION['data_dispozitie'] ) );
echo  $data_fact; 
 
 $sql = "SELECT conducator_entitate from $tabel_final_date_firma;";    
$stmt = $pdo->prepare($sql);  
$stmt->execute(); 

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)){ 
$cond_ent=$row['conducator_entitate'];
}
?>
</td></tr>
<form method="post">

       <tr>  <td><h4>Nume și prenume:</h4></td>
<td><input class="form-control" type="text" name="nume_si_prenume" maxlength="50"  ></td></tr>
       <tr>  <td><h4>Functie (calitate):</h4></td>
<td><input class="form-control" type="text" name="functie"  maxlength="50"  ></td></tr>
       <tr>  <td><h4>Scopul operatiunii</h4></td>
<td><input class="form-control" type="text" name="scop" maxlength="100"  ></td></tr>
 </table>
 

<table class='table table-bordered'>
        <tr>  <td><h4>Conducatorul Entitatii:</h4></td>
<td><input class="form-control" type="text" name="conducator_ent" value="<?php echo $cond_ent;?>" ></td></tr>
<tr>  <td><h4>Suma:</h4></td>
<td><input class='form-control' type='number' step='0.01' name='suma' ></td></tr>
<?php 
$tip_disp=$_SESSION['tip_disp'];
if($tip_disp=='p'){echo "

 <tr><th colspan='2'><h3>Date Suplimentare privind Beneficiarul Sumei:</h3></th></tr>
<tr> <td><h4>Act de identitate (Ci/Pasaport/etc):</h4></td>
<td><input class='form-control' type='text' name='act_id'  max='8' ></td></tr></td></tr> 
</td> </tr><tr>  <td><h4>Seria Actului de identitate):</h4></td>
<td><input class='form-control' type='text' name='serie_act'  max='2' ></td></tr></td></tr> 
</td> </tr><tr>  <td><h4>Numarul Actului de identitate:</h4></td>
<td><input class='form-control' type='number' name='nr_act'  max='999999' ></td></tr></td></tr> 

</td></tr> ";}

?>
   </table>
 



  
  
  <th >
 <?php
 echo $tip_disp;

if(isset($_POST['finaliz_disp'])){
	
$tip_disp=$_SESSION['tip_disp'];
$conducator_ent=$_POST['conducator_ent'];
if($tip_disp=='p'){
$act_id=$_POST['act_id'];
$nr_act=$_POST['nr_act'];
$serie_act=$_POST['serie_act'];}
$suma=$_POST['suma'];
 $nr_dispozitie=$_SESSION['nr_dispozitie'];
$nume_si_prenume=$_POST['nume_si_prenume'];
$functie=$_POST['functie'];
 $scop=$_POST['scop'];
$fin_sql = "UPDATE $tabel_final_dispozitii SET nume_si_prenume='$nume_si_prenume',functie='$functie',scop='$scop',suma_disp='$suma',conducator_ent='$conducator_ent' WHERE nr_disp='$nr_dispozitie';"; 
			
if($tip_disp=='p'){
    $fin_sql = "UPDATE $tabel_final_dispozitii SET nume_si_prenume='$nume_si_prenume',functie='$functie',scop='$scop',suma_disp='$suma',conducator_ent='$conducator_ent',act_id='$act_id',serie_act='$serie_act',nr_act='$nr_act' WHERE nr_disp='$nr_dispozitie';";    

    
    
}			
	 
	try{
$pdo->exec($fin_sql) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 
			printf("<script>location.href='dispozitii.php'</script>");
		  
}catch(PDOException $e)
    {
    echo $fin_sql . "<br>" . $e->getMessage();
    } 
 
	
	
	
}

  ?>
  </th>
  
</table>

 <input class='btn btn-primary btn-block' type="submit" name="finaliz_disp" value="Finalizare Dispozitie">
  <input class='btn btn-primary btn-block' type="submit" name="anulare_dispozitie" value="Anulare Dispozitie">

 </form>

	  
	   
<?php if(isset($_POST['anulare_dispozitie'])){
     $nr_dispozitie=$_SESSION['nr_dispozitie'];
$csql="DELETE from $tabel_final_chitante WHERE $tabel_final_dispozitii.nr_disp = '$nr_dispozitie';
					";
$pdo->exec($csql) or die(print_r($pdo->errorInfo(), true));
    	unset($_SESSION['nr_chitanta']);

			printf("<script>location.href='dispp1.php'</script>");

	   }



?>
 <!-- InstanceEndEditable -->




  
        </div>
        
      </div>



<?php 
	require "footer.php";
?>