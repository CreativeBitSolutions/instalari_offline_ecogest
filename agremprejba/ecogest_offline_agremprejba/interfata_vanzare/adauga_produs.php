<?php  include('session.php');
	$title = 'Nomenclator';
  include 'header.php';
	
?>

      <!-- Breadcrumbs-->
    

		

      <!-- Example DataTables Card-->
      <div class="card mb-3">
        <div class="card-header">
          <i class="fa fa-table"></i> Nomenclatorul de Produse</div>
        <div class="card-body">
          <div class="table-responsive">
		 <hr>

<form method="post">
<table width="100%" border="0" cellpadding="0" cellspacing="2"> 
         
         
 
        <tr> 	 
  <th><h4>Denumire:</h4></th>
  <td><input maxlength="100" class="form-control" name="den_p" type="text"></td>
 </tr>  
    <tr style='display:none;'> 	 
  <th><h4>Descriere:</h4></th>
  <td><textarea maxlength="1000" class="form-control" name="desc_p" type="text"></textarea></td>
 </tr>  
 <tr> 
 <th><h4>Unitate de Masura:</h4></th>
 <td><input class="form-control" maxlength='3' type="text" value='KG' name="um" >
 </td></tr>

 <tr> <th><h4>Cota TVA:</h4> </th>
  <td><select class="form-control" id='cota_tva' name="cota_tva" onchange='updateDue()'>
    <option  value="3">5% Cota redusă de la 9%</option>
	<option  value="1" selected>9%</option>
	<option value="2"  >19%</option>
		<option value="4"  >0%</option>

</select> </td> </tr>

 <tr> 	 
  <th><h4>Preț vânzare cu TVA:</h4></th>
   <td><input class="form-control" type="number" min="0" value='0' onchange='update_pachiz()' id='pret_vanzare' step="0.01" name="pret_vanzare" ></td></tr>
  <tr> 	 
  <th><h4>Preț Achiziție:</h4></th>
   <td><input  class="form-control" type="number" min="0.00001" step="0.00001" value='0' onchange='updateDue()' id='pret' name="pret" ></td></tr>
 <tr> 	 
  <th><h4>Cota Adaos:</h4></th>
   <td><input class="form-control" type="number" min="0" step="0.000000000000001" readonly onchange='updateDue()' value='0' id='cota_adaos' name="cota_adaos" ></td></tr>
    <tr style='display:none;'> 	 
  <th><h4>Cota Discount:</h4></th>
   <td><input class="form-control" type="number" min="0" step="0.000000000000001" onchange='updateDue()' value='0' id='proc_discount' name="cota_discount" ></td></tr>
   
  <tr>
  <th><h4>Furnizor:</h4></th><td>
  <?php
	 
$tsql = "SELECT * FROM $tabel_final_terti where tip_tert='furnizor'";    
$stmt = $pdo->prepare($tsql);  
$stmt->execute(); 

echo "<select class='form-control' name='den_furniz'>"; 
 
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)){ 
                      $den_tert=$row['denumire'];
             $cod_tert=$row['cod_tert'];
			                   
              echo "<option value='$cod_tert'>$den_tert</option>";    
             
			

}
echo "</select>";
 
?>   
  
  </td><td> <button  title="Creaza Terț" type="button" class="btn btn-primary" style="font-weight:bold;width:100%;height:auto;border-style:solid;border-color:white;" data-toggle="modal" data-target="#Creaza_tert">+</button></td></tr>
  <tr> <th><h4>Categorie:</h4> </th>
  <td> <?php
	 
$catsql = "SELECT * from $tabel_final_categorii";    
$catstmt = $pdo->prepare($catsql);  
$catstmt->execute(); 

echo "<select class='form-control' name='categorie'>"; 
 
while ($row = $catstmt->fetch(PDO::FETCH_ASSOC)){ 
                      $den_cat=$row['den_categ'];
             $cod_cat=$row['id_categorie'];
			                   
              echo "<option "; if($cod_cat==7){echo "selected";} echo " value='$cod_cat'>$den_cat</option>";    
             
			

}
echo "</select>";
 
?>   
  
 
</td><td> <button  title="Creaza Categorie" type="button" class="btn btn-primary" style="font-weight:bold;width:100%;height:auto;border-style:solid;border-color:white;" data-toggle="modal" data-target="#Creaza_produs">+</button></td>  </tr>
 <tr> 	 
  <th><h4>Nivel stoc critic:</h4></th>
   <td><input class="form-control" type="number" min="0" value='0' name="stoc_critic" ></td></tr>
<script>
$(document).ready(function(){ $(".js-example-basic-single").select2();});
</script>
 <tr> <th><h4>Gestiune:</h4> </th>
  <td><select style="width:100%" class="js-example-basic-single" name="gestiune">
    <option value="MP" selected>Materii Prime</option>
	<option value="MC" >Materiale Consumabile</option>
	<option value="MA" >Materiale Auxiliare</option>
	<option value="PF" >Produse Finite</option>
	<option value="MR"  >Marfuri</option>
		<option value="OB" >Obiecte de inventar</option>
	<option value="MJ" >Mijloace fixe</option>
	<option value="MC2" >Materiale consumabile 2</option>

</select> </td> </tr>
 <tr > <th><h4>Departament</h4> </th>
  <td><select class="form-control" name="departament">
    <option value="BAR">BAR</option>
	<option value="BUC"selected >BUCATARIE</option>
		<option value="FRM" >FIRMA</option>

</select> </td> </tr>
</table>

		<style>
		
		th{width:15em;}
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


  <tr><th></th><td><input class="btn btn-primary btn-block" type="submit" name="adauga_produs" value="Creaza Produs"></td>
 </tr>


  </form>
<br>  
  <?php 
  
 if (isset($_POST['adauga_produs']))     
{if ($_POST['den_p'] != "" && $_POST['um'] != "" && $_POST['pret'] != "" &&                  
$_POST['cota_tva'] != ''  )   
{ 
	
	   $logged_user=$_SESSION['login_user'];     

$denp=strtoupper ($_POST['den_p']);		
         
		$um=strtoupper($_POST['um']);
		      

		  $departament=$_POST['departament']; 

		  $ddesc_p=$_POST['desc_p']; 
           $desc_p=str_replace("\n","<br>",$ddesc_p);
		  $pret_unit=$_POST['pret']; 
			  $cota_tva=$_POST['cota_tva']; 
			    $cota_adaos=$_POST['cota_adaos']; 
			   $cota_discount=$_POST['cota_discount']; 
			    $furnizor=$_POST['den_furniz']; 
				$cod_categ=$_POST['categorie'];
				$stoc_critic=$_POST['stoc_critic'];
				$gestiune=$_POST['gestiune'];
				$pret_vanzare=$_POST['pret_vanzare'];




	$sql="insert into $tabel_final_nomenclator(stoc_critic,den_p,um,pret,cota_tva,proc_adaos,proc_discount,cod_furnizor,cod_categ,desc_prod,gestiune,departament,pret_vanzare) values('$stoc_critic','$denp','$um','$pret_unit','$cota_tva','$cota_adaos','$cota_discount','$furnizor','$cod_categ','$desc_p','$gestiune','$departament','$pret_vanzare');";

try{
$pdo->exec($sql) or die(print_r($pdo->errorInfo(), true));  

   		 $inchidere_sql = "SELECT max(cod_p) as ultim_prod from $tabel_final_nomenclator";    
$inchidere_stmt = $pdo->prepare($inchidere_sql);  
$inchidere_stmt->execute();
while ($row = $inchidere_stmt->fetch(PDO::FETCH_ASSOC)){ 
    $ultim_prod=$row['ultim_prod'];
}
 		 $insert_stoc_sql = "INSERT into $tabel_final_stoc(cod_p) values('$ultim_prod')";    
$insert_stoc_stmt = $pdo->prepare($insert_stoc_sql);  
$insert_stoc_stmt->execute();
    // afiseaza un mesaj de succes 
   echo '<script language="javascript">';
echo 'alert("Produsul a fost creat!")';
echo '</script>';		  
}catch(PDOException $e)
    {
    echo $sql . "<br>" . $e->getMessage();
    }
    
    

    
    
}
else 
	echo"<h3>Atentie completati toate campurile</h3>";
}
else {echo"<h4 align='center'>Adaugati un produs completand formularul de mai sus</h4>";}


	
  
  
 
 
  ?>
  
  <br>
 
 

  
          </div>
        </div>
        
      </div>
 <!-- Produse vandute begin -->

		<div  class="modal fade"  id="Creaza_produs" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
		    

		  <div  class="modal-dialog modal-lg"  role="document">
			<div class="modal-content">
			    
			  <div class="modal-header">
				<h4 class="modal-title" id="myModalLabel">Creaza categorie</h4>
								<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>

			  </div>
			  <div  class="modal-body">

        <?php
				if (isset($_POST['add_category'])) 
						{
$sql ="INSERT INTO $tabel_final_categorii(den_categ,desc_categ) 
													VALUES (:den_categ,:desc_categ)";

$stmt = $pdo->prepare($sql);
							$criteria = 
							[
							   	'den_categ' => $_POST['den_categ'],

						'desc_categ' => $_POST['desc_categ']
							];

							$stmt->execute($criteria);

				echo '<script language="javascript">';
echo 'alert("Categorie creata")';
echo '</script>';			
printf("<script>location.href='admin_categories.php'</script>");							
							

						}
 
				
						
			?>		
			<div class="card-body">
				<form action="add_category.php" method="POST">
					<div class="form-group">
						<label for="exampleInputEmail1">Denumire Categorie:</label>
						<input class="form-control" id="exampleInputEmail1" type="text" name="den_categ" aria-describedby="emailHelp" placeholder="Introduceti denumirea categoriei">
					</div>
					<div class="form-group">
						<label for="exampleInputPassword1">Descriere Categorie:</label>
						<textarea input class="form-control" id="exampleInputPassword1" type="text" name="desc_categ" placeholder="Introduceti descrierea categoriei"></textarea>
					</div>
                    <input class="btn btn-primary btn-block" type="submit" name="add_category" value="Creaza categorie">
                 </form>
            </div>
 </div>

				  </div>
		

							  </div>			  
	  
							  </div>
 <!-- Produse vandute end -->
 
		
    <!-- Produse vandute begin -->

		<div  class="modal fade"  id="Creaza_tert" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
		    

		  <div  class="modal-dialog modal-lg"  role="document">
			<div class="modal-content">
			    
			  <div class="modal-header">
				<h4 class="modal-title" id="myModalLabel">Crează Terț</h4>
								<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			  </div>
			  <div  class="modal-body">
<form method="post">
<table width="100%" border="0" cellpadding="0" cellspacing="2"> 
  <tr> 
            <th><h4>Denumire:</h4></th> 
            <td><input class='form-control' type="text" name="denumire" /></td> 
        </tr> 
        <tr> 
            <th><h4>Adresa:</h4></th> 
            <td><input class='form-control' type="text" name="adresa" /></td> 
        </tr> 
        <tr> 
            <th><h4>Judet:</h4></th> 
            <td><select class='form-control' name="judet">
	<option value="ALBA">ALBA</option>
	<option value="ARAD">ARAD</option>
	<option value="ARGES">ARGES</option>
	<option value="BACAU">BACAU</option>
	<option value="BIHOR">BIHOR</option>
	<option value="BISTRITA-NASAUD">BISTRITA-NASAUD</option>
	<option value="BOTOSANI">BOTOSANI</option>
	<option value="BRAILA">BRAILA</option>
	<option value="BRASOV">BRASOV</option>
	<option value="BUCURESTI">BUCURESTI</option>
	<option value="BUZAU">BUZAU</option>
	<option value="CALARASI">CALARASI</option>
	<option value="CARAS-SEVERIN">CARAS-SEVERIN</option>
	<option value="CLUJ">CLUJ</option>
	<option value="CONSTANTA">CONSTANTA</option>
	<option value="COVASNA">COVASNA</option>
	<option value="DAMBOVITA">DAMBOVITA</option>
	<option value="DOLJ">DOLJ</option>
	<option value="GALATI">GALATI</option>
	<option value="GIURGIU">GIURGIU</option>
	<option value="GORJ">GORJ</option>
	<option value="HARGHITA">HARGHITA</option>
	<option value="HUNEDOARA">HUNEDOARA</option>
	<option value="IALOMITA">IALOMITA</option>
	<option value="IASI">IASI</option>
	<option value="ILFOV">ILFOV</option>
	<option value="MARAMURES">MARAMURES</option>
	<option value="MEHEDINTI">MEHEDINTI</option>
	<option value="MURES">MURES</option>
	<option value="NEAMT">NEAMT</option>
	<option value="OLT">OLT</option>
	<option value="PRAHOVA">PRAHOVA</option>
	<option value="SALAJ">SALAJ</option>
	<option value="SATU MARE">SATU MARE</option>
	<option value="SIBIU">SIBIU</option>
	<option value="SUCEAVA">SUCEAVA</option>
	<option value="TELEORMAN">TELEORMAN</option>
	<option value="TIMIS">TIMIS</option>
	<option value="TULCEA">TULCEA</option>
	<option value="VALCEA">VALCEA</option>
	<option value="VASLUI">VASLUI</option>
	<option value="VRANCEA">VRANCEA</option>
</select></td> 
        </tr> 
		<tr> 
            <th><h4>Cod Unic de Identificare(CUI/CIF):</h4></th> 
            <td><input class='form-control' type="text" name="cui" maxlength="10"></td> 
        </tr>
        <tr> 
            <th><h4>Nr. Ord. Reg. Com:</h4></th> 
            <td>  <input class='form-control'  type="text" maxlength="14" name="nr_reg_com" placeholder="JXX/XXXXX/XXXX"  title="Numarul de ordine la Oficiul National al Registrului Comertului ">
</td> 
        </tr>
        
		<tr> 
            <th><h4>Contul Bancar:</h4></th> 
            <td><input class='form-control' max="50" type="text" name="ct_banca"  ></td> 
        </tr>
        
        	<tr> 
            <th><h4>Banca:</h4></th> 
            <td><input class='form-control'  type="text" name="banca" ></td> 
        </tr>
        <tr> 
			<th><h4>Categorie Tert:</h4></th>
			<td><select class='form-control' id="tip_tert" name="tip_tert">                      
					<option value="client">Client</option>
					<option value="furnizor" selected>Furnizor</option>
				</select>
			</td>
			</tr>
        <tr> 
            <td>&nbsp</td> 
            <td><input class="btn btn-primary btn-block" type="submit" name="adauga" value="Adauga Tert" ></td> 
        </tr> 
        
            

    </table> 
</form> 
  <br>
  <?php 

if(isset($_POST['adauga'])){
//  verifica daca exista date transmise 
if ($_POST['denumire'] != "" )   
{ 
     // preia datele din formular 
    $den = strtoupper($_POST['denumire']);  
    $adresa = $_POST['adresa'];
	$cui = $_POST['cui'];
	$ct = $_POST['ct_banca'];
	$judet=$_POST['judet'];
	$nr_reg_com=$_POST['nr_reg_com'];
	$banca=$_POST['banca'];
	$tip_tert = $_POST['tip_tert'];
     // formeaza si executa queryul de inserare in baza de date 
$sql="insert into $tabel_final_terti(denumire,adresa,cui_cif,cont_banca,tip_tert,judet,banca,nr_reg_com) values('$den','$adresa','$cui','$ct','$tip_tert','$judet','$banca','$nr_reg_com')"; 	 

try{
$pdo->exec($sql) or die(print_r($pdo->errorInfo(), true)); 
   
    // afiseaza un mesaj de succes 
printf("<script>location.href='adauga_produs.php'</script>");							
		  
}catch(PDOException $e)
    {
    echo $sql . "br" . $e->getMessage ();
    }
}
else 
	echo"<h3>Atentie completati denumirea</h3>";
}
else {echo"<h4 align='center'>Adaugati un tert completand formularul de mai sus</h4>";}


?>
 </div>

				  </div>
		

							  </div>			  
	  
							  </div>
 <!-- Produse vandute end -->
 
		
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
    cota_tva=5;
}
if(cota_tva==2){
    cota_tva=19;
}
if(cota_tva==3){
    cota_tva=0;
}
if(cota_tva==4){
    cota_tva=9;
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
    cota_tva=5;
}
if(cota_tva==2){
    cota_tva=19;
}
if(cota_tva==3){
    cota_tva=0;
}
if(cota_tva==4){
    cota_tva=9;
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
    cota_tva=5;
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