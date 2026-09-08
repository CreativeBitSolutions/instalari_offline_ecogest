<?php  include('session.php');
	$title = 'Modifica produs';
  include 'header.php';
	
?>

      <!-- Breadcrumbs-->
    

		
       <style>th{width:15em;}
       details summary{
	font-family:tahoma;
	font-size: 90%;
    margin-bottom:1em;
	color:#007bff;
}
details summary:hover{
	cursor:pointer;
	font-size:110%;
}
.radio{width:50em;}
       </style>

      <!-- Example DataTables Card-->
      <div class="card mb-3">
        <div class="card-header">
          <i class="fa fa-table"></i> Modifica produs</div>
        <div class="card-body">
          <div class="table-responsive">
		 <hr>

<?php 
					$prodid=$_SESSION['cod_p'];

$nsql = "SELECT * from $tabel_final_nomenclator where cod_p='$prodid'";    
$nstmt = $pdo->prepare($nsql);  
$nstmt->execute(); 









while ($row = $nstmt->fetch(PDO::FETCH_ASSOC)){
    $cod_bare_curr=$row['cod_bare'];
                  $den_p_curr=$row['den_p'];
                     $um_curr=$row['um'];
			          $pret_curr=$row['pret'];
			          $cota_tva_curr=$row['cota_tva'];
                      $cota_adaos_curr=$row['proc_adaos'];
			$den_furniz_curr=$row['cod_furnizor'];
            $desc_p=$row['desc_prod'];
            $cota_discount_curr=$row['proc_discount'];
            $gestiune=$row['gestiune'];
            $old_departament=$row['departament'];
            $categ=$row['cod_categ'];
            $tert=$row['cod_furnizor'];
            $pr_vz=$row['pret_vanzare'];
}?> 
<form method="post">
<table width="100%" border="0" cellpadding="0" cellspacing="2"> 
      
          <tr style='display:none;'> 	 
  <th><h4>Cod de bare:</h4></th>
  <td><input maxlength="20" class="form-control" name="cod_bare" value="<?php echo $cod_bare_curr;?>" type="text"></td>
 </tr>    
        <tr> 	 
  <th><h4>Denumire:</h4></th>
  <td><input maxlength="100" class="form-control" name="den_p" value="<?php echo $den_p_curr;?>" type="text"></td>
 </tr>  
 <tr style='display:none;'> 	 
  <th><h4>Descriere:</h4></th>
  <td><textarea maxlength="1000" class="form-control" name="desc_p" type="text"> <?php echo $desc_p;?></textarea></td>
 </tr>  
 <tr> 
 <th><h4>Unitate de masura:</h4></th>
 <td><input value="<?php echo $um_curr;?>" maxlength='3' class="form-control" type="text" name="um" >
 </td></tr>
  <tr> 	 
  <th><h4>Pret Achiziție:</h4></th>
   <td><input value="<?php echo $pret_curr;?>" class="form-control" type="number" min="0.00001" onchange='updateDue()' id='pret' step="0.00001" name="pret" ></td></tr>
 <tr> <th><h4>Cota tva:</h4> </th>

<?php
	 
$tsql = "SELECT * FROM cote_tva";    
$stmt = $pdo->prepare($tsql);  
$stmt->execute(); 

echo "<td><select class='form-control' id='cota_tva' name='cota_tva' onchange='updateDue()'>"; 
 
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)){ 
                      $den_cota=$row['cota'];
             $cod_cota=$row['id'];
		if($cota_tva_curr==$cod_cota){ 
 
              echo "<option selected value='$cod_cota'>".$den_cota."%</option>"; 
		    
		}   
		
		else{
		                  echo "<option value='$cod_cota'>".$den_cota."%</option>"; 

		}
             
			

}
echo "</select></td>";
 
?>   

</td></tr>

 <tr> 	 
  <th><h4>Cota Adaos:</h4></th>
   <td><input class="form-control" type="number" min="0"  step="0.000000000000001" onchange='updateDue()' value="<?php echo $cota_adaos_curr;?>" id='cota_adaos' name="cota_adaos" ></td></tr>
    <tr style='display:none;'> 	 
  <th><h4>Cota Discount:</h4></th>
   <td><input class="form-control" value="<?php echo $cota_discount_curr;?>" type="number" min="0" step="0.000000000000001" onchange='updateDue()'  id='proc_discount' name="cota_discount" ></td></tr>
    <tr> 	 
  <th><h4>Preț vânzare cu TVA:</h4></th>
   <td><input class="form-control" type="number" min="0"  value="<?php 
  
   echo $pr_vz; ?>" onchange='update_pachiz()' id='pret_vanzare' step="0.01" name="pret_vanzare" ></td></tr>
  <th><h4>Furnizor:</h4></th><td>
  <?php

$tsql = "SELECT * from $tabel_final_terti where tip_tert='furnizor'";    
$stmt = $pdo->prepare($tsql);  
$stmt->execute(); 

echo "<select class='form-control' name='den_furniz'>"; 
 
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)){ 
                      $den_tert=$row['denumire'];
             $cod_tert=$row['cod_tert'];
		if($tert==$cod_tert){ 
 
              echo "<option selected value='$cod_tert'>$den_tert</option>"; 
		    
		}   
		
		else{
		                  echo "<option value='$cod_tert'>$den_tert</option>"; 

		}
             
			

}
echo "</select>";
 
?>   
  
  </td></tr>
  <tr> <th><h4>Categorie:</h4> </th>
  <td> <?php
	 
$catsql = "SELECT * from $tabel_final_categorii";    
$catstmt = $pdo->prepare($catsql);  
$catstmt->execute(); 

echo "<select class='form-control' name='categorie'>"; 
 
while ($row = $catstmt->fetch(PDO::FETCH_ASSOC)){ 
                      $den_cat=$row['den_categ'];
             $cod_cat=$row['id_categorie'];
			                  if($categ==$cod_cat){ 
              echo "<option selected value='$cod_cat'>$den_cat</option>";    
			                  }
			                  else{
			              echo "<option value='$cod_cat'>$den_cat</option>"; 
			                  }
			

}
echo "</select>";
 
?>   
  <tr> 	 
  <th><h4>Nivel stoc critic:</h4></th>
   <td><input class="form-control" type="number" min="0" value='0' name="stoc_critic" ></td></tr>
 <script>
$(document).ready(function(){ $(".js-example-basic-single").select2();});
</script>
 <tr> <th><h4>Gestiune:</h4> </th>
  <td><select style="width:100%" class="js-example-basic-single" name="gestiune">
      
      <?php if($gestiune=='MP'){echo '
    <option value="MP" selected>Materii Prime</option>
	<option value="MC" >Materiale Consumabile</option>
	<option value="MA" >Materiale Auxiliare</option>
	<option value="PF" >Produse Finite</option>
	<option value="MR" >Marfuri</option>
		<option value="OB" >Obiecte de inventar</option>
	<option value="MJ" >Mijloace fixe</option>
	<option value="MC2" >Materiale consumabile 2</option>
'; }
	elseif($gestiune=='MC'){echo '
    <option value="MP" >Materii Prime</option>
	<option value="MC" selected >Materiale Consumabile</option>
	<option value="MA" >Materiale Auxiliare</option>
	<option value="PF" >Produse Finite</option>
	<option value="MR" >Marfuri</option>
		<option value="OB" >Obiecte de inventar</option>
	<option value="MJ" >Mijloace fixe</option>
	<option value="MC2" >Materiale consumabile 2</option>
'; }
		elseif($gestiune=='MA'){echo '
    <option value="MP" >Materii Prime</option>
	<option value="MC"  >Materiale Consumabile</option>
	<option value="MA" selected>Materiale Auxiliare</option>
	<option value="PF" >Produse Finite</option>
	<option value="MR" >Marfuri</option>
		<option value="OB" >Obiecte de inventar</option>
	<option value="MJ" >Mijloace fixe</option>
	<option value="MC2" >Materiale consumabile 2</option>
'; }
		elseif($gestiune=='PF'){echo '
    <option value="MP" >Materii Prime</option>
	<option value="MC"  >Materiale Consumabile</option>
	<option value="MA" >Materiale Auxiliare</option>
	<option value="PF" selected >Produse Finite</option>
	<option value="MR" >Marfuri</option>
		<option value="OB" >Obiecte de inventar</option>
	<option value="MJ" >Mijloace fixe</option>
	<option value="MC2" >Materiale consumabile 2</option>
'; }
		elseif($gestiune=='MR'){echo '
    <option value="MP" >Materii Prime</option>
	<option value="MC"  >Materiale Consumabile</option>
	<option value="MA" >Materiale Auxiliare</option>
	<option value="PF" >Produse Finite</option>
	<option value="MR" selected>Marfuri</option>
		<option value="OB" >Obiecte de inventar</option>
	<option value="MJ" >Mijloace fixe</option>
	<option value="MC2" >Materiale consumabile 2</option>
'; }
		elseif($gestiune=='OB'){echo '
    <option value="MP" >Materii Prime</option>
	<option value="MC"  >Materiale Consumabile</option>
	<option value="MA" >Materiale Auxiliare</option>
	<option value="PF" >Produse Finite</option>
	<option value="MR" >Marfuri</option>
		<option value="OB" selected>Obiecte de inventar</option>
	<option value="MJ" >Mijloace fixe</option>
	<option value="MC2" >Materiale consumabile 2</option>
'; }
		elseif($gestiune=='MJ'){echo '
    <option value="MP" >Materii Prime</option>
	<option value="MC"  >Materiale Consumabile</option>
	<option value="MA" >Materiale Auxiliare</option>
	<option value="PF" >Produse Finite</option>
	<option value="MR" selected>Marfuri</option>
		<option value="OB" >Obiecte de inventar</option>
	<option value="MJ" selected >Mijloace fixe</option>
	<option value="MC2" >Materiale consumabile 2</option>
'; }
		elseif($gestiune=='MC2'){echo '
    <option value="MP" >Materii Prime</option>
	<option value="MC"  >Materiale Consumabile</option>
	<option value="MA" >Materiale Auxiliare</option>
	<option value="PF" >Produse Finite</option>
	<option value="MR" >Marfuri</option>
		<option value="OB" >Obiecte de inventar</option>
	<option value="MJ" >Mijloace fixe</option>
	<option value="MC2" selected >Materiale consumabile 2</option>
'; }
		else{echo '
    <option value="MP" >Materii Prime</option>
	<option value="MC"  >Materiale Consumabile</option>
	<option value="MA" >Materiale Auxiliare</option>
	<option value="PF" >Produse Finite</option>
	<option value="MR" >Marfuri</option>
		<option value="OB" >Obiecte de inventar</option>
	<option value="MJ" >Mijloace fixe</option>
	<option value="MC2" >Materiale consumabile 2</option>
'; }
	?>
</select> </td> </tr>
</td> </tr>
<th><h4>Departament</h4> </th>
  <td><select class="form-control" name="departament">
    		<?php if($old_departament=='BAR'){echo '

    <option value="BAR" selected >BAR</option>
	<option value="BUC"  >BUCATARIE</option>
		<option value="FRM" >FIRMA</option>
';}
elseif($old_departament=='BUC'){echo '

    <option value="BAR"  >BAR</option>
	<option value="BUC" selected >BUCATARIE</option>
		<option value="FRM" >FIRMA</option>
';}
elseif($old_departament=='FRM'){echo '

    <option value="BAR"  >BAR</option>
	<option value="BUC"  >BUCATARIE</option>
		<option value="FRM"  selected >FIRMA</option>
';}

?>
</select> </td> </tr>
</table>

  <tr><th></th><td><input class="btn btn-primary btn-block" type="submit" name="modifica_produs" value="Salveaza"></td>
 </tr>

 </table>

  </form>
<br>  
  <?php 
  
 if (isset($_POST['modifica_produs']))     
{if ($_POST['den_p'] != "" && $_POST['um'] != "" && $_POST['pret'] != "" &&                  
$_POST['cota_tva'] != '' && $_POST['den_furniz'] != '' )   
{ 
	
	   $logged_user=$_SESSION['login_user'];     

$denp=strtoupper ($_POST['den_p']);		
         
		$um=strtoupper($_POST['um']);
$image=$_POST['image']; 

$ddesc_p=$_POST['desc_p']; 
           $desc_p=str_replace("\n","<br>",$ddesc_p);		 
           $pret_unit=$_POST['pret']; 
			  $cota_tva=$_POST['cota_tva']; 
			    $cota_adaos=$_POST['cota_adaos']; 
			    $furnizor=$_POST['den_furniz']; 
				$cod_categ=$_POST['categorie']; 
				$cod_bare=$_POST['cod_bare'];
				$departament=$_POST['departament'];
								$stoc_critic=$_POST['stoc_critic'];
$pret_vanzare=$_POST['pret_vanzare'];
				$cota_discount=$_POST['cota_discount'];
                $gestiune=$_POST['gestiune'];
$cod_bare=$_POST['cod_bare']; 
if($cod_bare==''){
    $cod_bare=$denp;
}
				
	$sql="update $tabel_final_nomenclator set den_p='$denp',um='$um',stoc_critic='$stoc_critic',pret='$pret_unit',cota_tva='$cota_tva',proc_adaos='$cota_adaos',proc_discount='$cota_discount',cod_furnizor='$furnizor',cod_categ='$cod_categ',imagine='$image',desc_prod='$desc_p',cod_bare='$cod_bare',gestiune='$gestiune',pret_vanzare='$pret_vanzare',departament='$departament' where cod_p='$prodid';";
try{
$pdo->exec($sql) or die("<h3>Nu ati modificat nimic...</h3>"); 
   
    // afiseaza un mesaj de succes 
			printf("<script>location.href='nomenclator.php'</script>");
		  
}catch(PDOException $e)
    {
    echo $sql . "<br>" . $e->getMessage();
    }
}
else 
	echo"<h3>Atentie completati toate campurile</h3>";
}
else {echo"<h4 align='center'>Modificati produsul</h4>";}

?>
	
  
  
 
 

  

 

  
          </div>
        </div>
        
      </div>
    </div>

		
    
		<?php include 'footer.php'; ?>
		
				<script>
		function updateDue(){
		    var pret=parseFloat(document.getElementById("pret").value);
		var cota_tva=parseFloat(document.getElementById("cota_tva").value).toFixed(2);
			var cota_adaos=parseFloat(document.getElementById("cota_adaos").value).toFixed(2);
		var proc_discount=parseFloat(document.getElementById("proc_discount").value).toFixed(2);

		if(!pret){pret=0}
if(!cota_tva){cota_tva=0}
		if(!cota_adaos){cota_adaos=0}
if(!proc_discount){proc_discount=0}
if(cota_tva==1){
    cota_tva=9;
}
if(cota_tva==2){
    cota_tva=19;
}
if(cota_tva==3){
    cota_tva=0;
}
var adaos_unit=pret*cota_adaos/100;
var tva=(Number(adaos_unit)+Number(pret))*Number(cota_tva)/100;
var pret_vanzare=Number(pret)+Number(adaos_unit)+Number(tva);
var pret_vanzare_cu_disc=pret_vanzare-(pret_vanzare*proc_discount/100);

var ansD=document.getElementById("pret_vanzare");
ansD.value=Math.round(pret_vanzare_cu_disc*100)/100
   
		    
		}
	function update_pachiz(){
	    
	    		    var pret_achizitie=parseFloat(document.getElementById("pret").value).toFixed(2);
if(pret_achizitie!=0){

		    var pret_vanzare=parseFloat(document.getElementById("pret_vanzare").value).toFixed(2);
		var cota_tva=parseFloat(document.getElementById("cota_tva").value).toFixed(2);


		if(!pret_vanzare){pret_vanzare=0}
if(!cota_tva){cota_tva=0}
	
if(cota_tva==1){
    cota_tva=9;
}
if(cota_tva==2){
    cota_tva=19;
}
if(cota_tva==3){
    cota_tva=0;
}


var tva=(pret_vanzare*cota_tva)/(Number(cota_tva)+Number(100));
var pret_achiz=pret_vanzare-tva;

var adaos_calc=pret_achiz-pret_achizitie;
var cota_adaos_calc=adaos_calc/pret_achizitie*100;

var ansD=document.getElementById("cota_adaos");
ansD.value=cota_adaos_calc;

}

else{
		    var pret_vanzare=parseFloat(document.getElementById("pret_vanzare").value).toFixed(2);
		var cota_tva=parseFloat(document.getElementById("cota_tva").value).toFixed(2);


		if(!pret_vanzare){pret_vanzare=0}
if(!cota_tva){cota_tva=0}
	
if(cota_tva==1){
    cota_tva=9;
}
if(cota_tva==2){
    cota_tva=19;
}
if(cota_tva==3){
    cota_tva=0;
}


var tva=(pret_vanzare*cota_tva)/(Number(cota_tva)+Number(100));
var pret_achiz=pret_vanzare-tva;
var ansD=document.getElementById("pret");
ansD.value=Math.round(pret_achiz*100)/100
		  updateDue(); 
		  
	}
		}
</script>