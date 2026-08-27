<?php  include('session.php');
	$title = 'Reteta';
  include 'header.php';
	if(!isset($_SESSION['cod_p'])){
	  printf("<script>location.href='nomenclator.php'</script>");

	}
	
	$cod_produs=$_SESSION['cod_p'];
?>

<!-- about -->
<style>.table-bordered{
text-align:center;
}</style>
		

	 <div class="card mb-3">
        <div class="card-header">
            <?php 
            $prodsql = "SELECT den_p,um from $tabel_final_nomenclator where cod_p='$cod_produs' ; ";    
$prodstmt = $pdo->prepare($prodsql);  
$prodstmt->execute(); 

while ($row = $prodstmt->fetch(PDO::FETCH_ASSOC)){ 
    $den_p=$row['den_p'];
    $um_pf=$row['um'];
    
}
            
            ?>
          <i class="fa fa-table"></i> Reteta Produsului <?php echo '<span style="color:red"> '. $den_p.'</span> pentru 1 <span style="color:red">'.$um_pf.'</span>' ;?> </div>
        <div class="card-body">
          <div class="table-responsive">


 <form method="post">
<table class='table table-bordered'>
         
        <tr> 	 
  <td><h4>Denumire Materie:</h4></td>
  <td style="font-size:20px;"> <?php
$psql = "SELECT cod_p,den_p,um from $tabel_final_nomenclator where gestiune!='PF' ; ";    
$pstmt = $pdo->prepare($psql);  
$pstmt->execute(); 

echo "<select style='width:100%;height:auto;' class='form-control js-example-basic-single' name='produs'>"; 
 
while ($row = $pstmt->fetch(PDO::FETCH_ASSOC)){ 
                      $den_prod=$row['den_p'];
             $cod_p=$row['cod_p'];
             $um=$row['um'];

              echo "<option value='$cod_p'>$den_prod | $um</option>";    
             
}
echo "</select>";

if(isset($_POST['adaug_produs'])){
	 $produs=$_POST['produs'];
	 $cantitate=$_POST['cantitate'];


$psql6 = "SELECT id from $tabel_final_retete where cod_mat='$produs' and cod_p='$cod_produs';";    
$pstmt6 = $pdo->prepare($psql6);  
$pstmt6->execute(); 
$prodcount=$pstmt6->rowCount();

while ($row = $pstmt6->fetch(PDO::FETCH_ASSOC)){ 
$id_reteta=$row['id'];
}
if($prodcount==0){
$psql = "insert into $tabel_final_retete(cod_p,cod_mat,cant_folos) values('$cod_produs','$produs','$cantitate');";   

}

elseif($prodcount>0)
{
    $psql = "update $tabel_final_retete set cant_folos=cant_folos+'$cantitate' where id='$id_reteta';";   

    
}
	 
	try{
$pdo->exec($psql) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 

}catch(PDOException $e)
    {
    echo $sql . "<br>" . $e->getMessage();
    } 

    			printf("<script>location.href='reteta.php'</script>");


 
 

 }
 
 
 
?>  
</td> <td> <button  title="Creaza produs" type="button" class="btn btn-primary" style="font-weight:bold;width:100%;height:auto;" data-toggle="modal" data-target="#Creaza_produs">+</button></td></tr><tr>  <td><h4>Cantitate necesarÄƒ pentru 1 <?php echo $um_pf;?>:</h4></td>
<td ><input class="form-control" type="number" name="cantitate"  step='0.0001' min="0.0001" value="1" ></td><td><input class='btn btn-primary btn-block' type="submit" name="adaug_produs" value="Adauga"></td></tr>
 </table>
 </form>
<table class='table table-bordered'>
  <tr>
    <th>Nr.Crt.</th>
    <th width="15%">Materie</th>
    <th>U.M.</th>
    <th>Cant.</th>
    <th colspan='2'>Actiuni</th>
	

  </tr>

  <?php
$nr_factura=$_SESSION['nr_factura'];
$nr_crt1 = 1;

$f_sql = "SELECT $tabel_final_retete.id,$tabel_final_nomenclator.den_p,$tabel_final_nomenclator.um,$tabel_final_retete.cant_folos from $tabel_final_retete INNER JOIN $tabel_final_nomenclator on $tabel_final_retete.cod_mat=$tabel_final_nomenclator.cod_p where $tabel_final_retete.cod_p='$cod_produs';";    
$f_stmt = $pdo->prepare($f_sql);  
$f_stmt->execute(); 

while ($row = $f_stmt->fetch(PDO::FETCH_ASSOC)){ 


$produs=$row['den_p'];
$um=$row['um'];
$cantitate=$row['cant_folos'];
$id_vanz=$row['id'];

  echo "
   <tr>
    <td width='10%'>$nr_crt1</td>
    <td>$produs</td>
    <td>$um</td>
 
<td><button title='Click pentru a modifica' name='$id_vanz' data-toggle='modal' data-target='#Discount' class='btn btn-primary btn-block discount'>$cantitate</button></td>     

	<td><form method='post'><input class='btn btn-primary btn-block' type='submit' value='Sterge' name='$id_vanz'></form></td>

  </tr>
  
  
  
  " 
  
  ;
  if (isset($_POST[$id_vanz])) {
					
					$sterg_sql="DELETE from $tabel_final_retete where $tabel_final_retete.id = '$id_vanz' ";
					$sterg_f_stmt = $pdo->prepare($sterg_sql);  
$sterg_f_stmt->execute(); 

					
			printf("<script>location.href='reteta.php'</script>");
				}
  $nr_crt1++;}
  


  ?>
   

  
  
</table>
 

     <button onclick="location.href = 'nomenclator.php';"  class='btn btn-primary btn-block' >Salvare Reteta</button>


 </br>


 <!-- Produse vandute begin -->

		<div  class="modal fade"  id="Creaza_produs" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
		    

		  <div  class="modal-dialog modal-lg"  role="document">
			<div class="modal-content">
			    
			  <div class="modal-header">
				<h4 class="modal-title" id="myModalLabel">Creaza produs</h4>
								<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>

			  </div>
			  <div  class="modal-body">
			       <script
  src="vendor/jquery/jquery.min.js"
  integrity="sha256-iT6Q9iMJYuQiMWNd9lDyBUStIq/8PuOW33aOqmvFpqI="
  crossorigin="anonymous"></script>
                            <link href="vendor/offline/select2/select2.min.css" rel="stylesheet" />
                            <script src="vendor/offline/select2/select2.min.js"></script>

	   
				
          <div class="table-responsive">
		 <hr>

<form method="post">
<table width="100%" border="0" cellpadding="0" cellspacing="2"> 
         
         
             <tr> 	 
  <th><h4>Cod de bare:</h4></th>
  <td><input maxlength="20" class="form-control" name="cod_bare" type="text"></td>
 </tr> 
        <tr> 	 
  <th><h4>Denumire:</h4></th>
  <td><input maxlength="100" class="form-control" name="den_p" type="text"></td>
 </tr>  
    <tr> 	 
  <th><h4>Descriere:</h4></th>
  <td><textarea maxlength="1000" class="form-control" name="desc_p" type="text"></textarea></td>
 </tr>  
 <tr> 
 <th><h4>Unitate de Masura:</h4></th>
 <td><input class="form-control" type="text" name="um" >
 </td></tr>
 <tr> 	 
  <th><h4>PreÈ› AchiziÈ›ie:</h4></th>
   <td><input class="form-control" type="number" min="0.1" step="0.01" value='0' onchange='updateDue()' id='pret' name="pret" ></td></tr>
 <tr> <th><h4>Cota TVA:</h4> </th>
  <td><select class="form-control" id='cota_tva' name="cota_tva" onchange='updateDue()'>
    <option value="3">0%</option>
	<option value="1" >9%</option>
	<option value="2" selected>19%</option>
</select> </td> </tr>
 <tr> 	 
  <th><h4>Cota Adaos:</h4></th>
   <td><input class="form-control" type="number" min="0" step="1" onchange='updateDue()' value='0' id='cota_adaos' name="cota_adaos" ></td></tr>
    <tr> 	 
  <th><h4>Cota Discount:</h4></th>
   <td><input class="form-control" type="number" min="0" step="1" onchange='updateDue()' value='0' id='proc_discount' name="cota_discount" ></td></tr>
    <tr> 	 
  <th><h4>PreÈ› vÃ¢nzare cu TVA:</h4></th>
   <td><input class="form-control" type="number" min="0" value='0' onchange='update_pachiz()' id='pret_vanzare' step="0.01" name="pret_vanzare" ></td></tr>
  <tr>
  <th><h4>Furnizor:</h4></th><td>
  <?php
	 
$tsql = "SELECT * from $tabel_final_terti where tip_tert='furnizor'";    
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
			                   
              echo "<option value='$cod_cat'>$den_cat</option>";    
             
			

}
echo "</select>";
 
?>   
  
 
</td> </tr>
    <tr> 	 
  <th><h4>Nivel stoc critic:</h4></th>
   <td><input class="form-control" type="number" min="0" value='0' name="stoc critic" ></td></tr>

<script src="vendor/jquery/jquery.min.js"></script>
<script>
$(document).ready(function(){
    $(".js-example-basic-single").select2();
$('.discount').on('click', function() {

var idvanzare = this.getAttribute("name");

$('[name=idvanzare]').val(idvanzare);



});

});
</script>
 <tr> <th><h4>Gestiune:</h4> </th>
  <td><select style="width:100%" class='form-control' name="gestiune">
    <option value="MP">Materii Prime</option>
	<option value="MC" >Materiale Consumabile</option>
	<option value="MA" >Materiale Auxiliare</option>
	<option value="PF" >Produse Finite</option>
	<option value="MR" >Marfuri</option>
</select> </td> </tr>

</table>

		<style>
		
		th{width:15em;}</style>


  <tr><th></th><td><input class="btn btn-primary btn-block" type="submit" name="adauga_produs" value="Creaza Produs"></td>
 </tr>


  </form>
<br>  
  <?php 
  
 if (isset($_POST['adauga_produs']))     
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
			   $cota_discount=$_POST['cota_discount']; 
			    $furnizor=$_POST['den_furniz'];
			    			    $stoc_critic=$_POST['stoc_critic']; 

				$cod_categ=$_POST['categorie'];
				$gestiune=$_POST['gestiune'];
$cod_bare=$_POST['cod_bare']; 
if($cod_bare==''){
    $cod_bare=$denp;
}

	$sql="insert into $tabel_final_nomenclator(stoc_critic,den_p,um,pret,cota_tva,proc_adaos,proc_discount,cod_furnizor,cod_categ,imagine,desc_prod,cod_bare,gestiune) values('$stoc_critic','$denp','$um','$pret_unit','$cota_tva','$cota_adaos','$cota_discount','$furnizor','$cod_categ','$image','$desc_p','$cod_bare','$gestiune');";

try{
$pdo->exec($sql) or die(print_r($pdo->errorInfo(), true));  

   		 $inchidere_sql = "SELECT max(cod_p) as ultim_prod from $tabel_final_nomenclator";    
$inchidere_stmt = $pdo->prepare($inchidere_sql);  
$inchidere_stmt->execute();
while ($row = $inchidere_stmt->fetch(PDO::FETCH_ASSOC)){ 
    $ultim_prod=$row['ultim_prod'];
}
 		 $insert_stoc_sql = "insert into $tabel_final_stoc(cod_p) values('$ultim_prod')";    
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
 	 echo '<script language="javascript">';
echo 'alert("Produsul nu a fost creat! Completati campurile obligatorii!")';
echo '</script>';}
else {echo"<h4 align='center'>Adaugati un produs completand formularul de mai sus</h4>";}


	
  
  
 
 
  ?>
  
  <br>
 
 

  
          </div>
 </div>

				  </div>
		

							  </div>			  
	  
							  </div>
 <!-- Produse vandute end -->
 
		<!------ Modal modif cantitate begin -->

							  <div class="modal fade" id="Discount" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
		  <div class="modal-dialog" role="document">
			<div class="modal-content">
			    
			  <div class="modal-header">
				<h4 class="modal-title" id="myModalLabel">Modificare cantitate folosita</h4>
								<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>

			  </div>
			  <div class="modal-body">
			      

				    <form method='POST'>
				    <input type="number"  hidden name='idvanzare'/>

			<label for="exampleInputEmail1">Cantitate noua</label></br>
	    	
<input type='number' value='1' min='0.0001' step='0.0001' name='cantitate_doc_noua'/>

 <input style='margin-top:5px;' class='btn btn-primary btn-block' type="submit" name="apl_disc_fix" value="Salveaza"/>

</div>



</div></div>
		<?php 
if(isset($_POST['apl_disc_fix'])){
    
    
    $id_vz=$_POST['idvanzare'];
        $cant_doc_noua=$_POST['cantitate_doc_noua'];

    
    $disc_sql = "update $tabel_final_retete set cant_folos='$cant_doc_noua' where id='$id_vz';";    
	try{
$pdo->exec($disc_sql) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 
		  
}catch(PDOException $e)
    {
    echo $disc_sql . "<br>" . $e->getMessage();
    } 
    
        printf("<script>location.href='reteta.php'</script>");

}
 
    


?>		    
				    </form>

	
  </div>

				  </div>
  </div>
        </div>
								<!------ Modal modif cantitate end -->
	  						<!------ Modal discount-->

      </div>


<?php 
	require "footer.php";
?>

		<script>
		function updateDue(){
		    var pret=parseFloat(document.getElementById("pret").value).toFixed(2);
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
</script>
