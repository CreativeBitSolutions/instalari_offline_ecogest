<?php

	include 'database_connection.php';
  
	$title = 'Nomenclator';
  
  include 'header.php';
	
?>

<!-- Breadcrumbs-->
<ol class="breadcrumb">
<li class="breadcrumb-item active">Nomenclator</li>
</ol>

<script>
function myFunctions() {
  // Declare variables
  var input, filter, table, tr, td, i;
  input = document.getElementById("myInput");
  filter = input.value.toUpperCase();
  table = document.getElementById("myTable");
  tr = table.getElementsByTagName("tr");

  // Loop through all table rows, and hide those who don't match the search query
  for (i = 0; i < tr.length; i++) {
    td = tr[i].getElementsByTagName("td")[0];
    if (td) {
      if (td.innerHTML.toUpperCase().indexOf(filter) > -1) {
        tr[i].style.display = "";
      } else {
        tr[i].style.display = "none";
      }
    }
  }
}

</script>


  
<!-- Example DataTables Card-->
<div class="card mb-3">
  <div class="card-header">
    <i class="fa fa-map-marker"></i> Lista Produselor</div>
  <div class="card-body">
      
<div style="width:23em;" class="input-group">
		<input id="myInput"  class='form-control' type="text" onkeyup="myFunctions()" placeholder="Caută după denumirea produsului...">

</div>
<style>.Optiuni{width:8em;}</style>     


	<section class="right">



<table class="table table-bordered" id="myTable" width="100%" cellspacing="0" style="display:block;overflow:auto;">
<thead>
<tr>
   <th>Cod produs</th> 
<th>Denumire</th>
<th>Pret vanzare</th>
<th>Cota TVA(%)</th>
<!--<th>Procent Adaos(%)</th>-->
<th>Stoc</th>
<th>Gestiune</th>
<th>Categorie</th>
<th>Sgr</th>

<th colspan="3">Optiuni</th>

</tr>
	</thead>
	<tfoot>
<tr>
        <th>Cod produs</th>

<th>Denumire</th>
<th>Pret vanzare</th>
<th>Cota TVA(%)</th>
<!--<th>Procent Adaos(%)</th>-->
<th>Stoc</th>
<th>Gestiune</th>
<th>Categorie</th>
<th>Sgr</th>
<th colspan="3">Optiuni</th>
</tr>
	</tfoot>
<tbody>
<?php

$sql ="SELECT *,$tabel_final_stoc.cantitate as stoc from $tabel_final_nomenclator INNER JOIN cote_tva on $tabel_final_nomenclator.cota_tva=cote_tva.id INNER JOIN $tabel_final_categorii on $tabel_final_nomenclator.cod_categ=$tabel_final_categorii.id_categorie inner join $tabel_final_terti on $tabel_final_nomenclator.cod_furnizor=$tabel_final_terti.cod_tert inner join $tabel_final_stoc on $tabel_final_nomenclator.cod_p=$tabel_final_stoc.cod_p where $tabel_final_nomenclator.cod_categ!='14' ";
 $stmt=$pdo->prepare($sql);
$stmt->execute();
while ($row = $stmt->fetch()) {
    
    $stoc=$row['stoc'];
$cod_p=$row['cod_p'];
            $sgr_checked = $row['sgr'] == 1 ? 'checked' : '';

$gestiune=$row['gestiune'];
$prod_det=$row['cod_p'];
	$prod_det=strval($prod_det);
$prod_det.='PT';
$prod_del=$row['cod_p'];
	$prod_del=strval($prod_del);
$prod_del.='PD';
$prod_mag=$row['cod_p'];
	$prod_mag=strval($prod_mag);
$prod_mag.='PM';
$cod_bare=$row['cod_p'];
	$cod_bare=strval($cod_bare);
$cod_bare.='CB';
$reteta=$row['cod_p'];
	$reteta=strval($reteta);
$reteta.='RE';
$c_bare=$row['cod_bare'];
$den_p=$row['den_p'];
$um=$row['um'];
$pret=$row['pret'];
//for each record display into td tags the records from the result table of the query from $stmt  
echo '<tr><td>'.$cod_p.'</td>';
if($gestiune!='PF'){
if($stoc==0){ echo "<td style='color:red'>";} else{ echo "<td>";}}
elseif($gestiune=='PF'){
      $reteta_sql = "SELECT $tabel_final_retete.cod_mat from $tabel_final_retete where $tabel_final_retete.cod_p='$cod_p'";    
$reteta_stmt = $pdo->prepare($reteta_sql);  
$reteta_stmt->execute(); 
$materii_reteta=$reteta_stmt->rowCount(); 
    $pf_sql = "SELECT $tabel_final_retete.cod_mat,$tabel_final_stoc.cantitate as stoc_mat,$tabel_final_retete.cant_folos from $tabel_final_retete inner join $tabel_final_stoc on $tabel_final_retete.cod_mat=$tabel_final_stoc.cod_p where $tabel_final_retete.cod_p='$cod_p' and $tabel_final_stoc.cantitate>=$tabel_final_retete.cant_folos";    
$pf_stm = $pdo->prepare($pf_sql);  
$pf_stm->execute(); 
$stoc_mat=$pf_stm->rowCount();
if($stoc_mat!=$materii_reteta){

    echo "<td style='color:red'>";
    
}

elseif($stoc_mat==$materii_reteta){
    echo "<td>";
    
}
}
echo $row['den_p'] . '</td><td>' . $row['pret_vanzare'] . '</td><td>' . $row['cota'] . '</td><td>' . ''; if($gestiune!='PF'){ echo $row['stoc'];} echo '</td><td>'.$row['gestiune'];  
if($gestiune=='PF'){
if($materii_reteta>0){
    echo '<br/>Cu reteta';
}
else{
    echo '<br/>Fara reteta';
}
}
echo '</td><td>' . $row['den_categ'] . '</td>';
echo "<td><input type='checkbox' class='sgr-checkbox' data-cod-p='$cod_p' $sgr_checked></td>";
//echo "<td class='Optiuni'><form method='post'><input class='btn btn-primary btn-block' type='submit' title='Detalii' value='Detalii' name='$prod_det'></form>";
echo "<td class='Optiuni'><form method='post'><input class='btn btn-primary btn-block' type='submit' title='Fisa de Magazie' value='Fisa de Magazie' name='$prod_mag'></form></td>";

$administrator="administrator";
   include('database_connection.php');
$admin_em=$_SESSION['admin_id'];

				
$ssql="SELECT * FROM $tabel_final_admins WHERE admin_id='$admin_em' and rank='$administrator'";

$zsql=$pdo->prepare($ssql);

	  $zsql->execute();
	  //count the returned number of rows

	  $count=$zsql->rowCount();
	 // if 1 row is found show the connected user a full table with forms that have the option to Modifica or Sterge an experience
	 if($count == 1) {
echo "<td  class='Optiuni'><form method='post'><input class='btn btn-primary btn-block' type='submit' title='Modifica' value='Modifica' name='$cod_p'></form>
<br/><form method='post'><input class='btn btn-primary btn-block' type='submit' title='Cod de Bare'value='Cod de bare' name='$cod_bare'></form></td></td>";
if($gestiune=='PF'){echo "<td  class='Optiuni'><form method='post'><input class='btn btn-primary btn-block' type='submit' value='Reteta' name='$reteta'></form></br><form method='post'><input class='btn btn-primary btn-block' type='submit' value='Sterge' name='$prod_del'></form></td></tr>";}
else{echo "<td><form method='post'><input class='btn btn-primary btn-block' type='submit' title='Sterge' value='Sterge' name='$prod_del'></form></td></tr>";}
}
if(isset($_POST[$reteta])){
$_SESSION['cod_p']=$cod_p;
printf("<script>location.href='reteta.php'</script>");
}
if(isset($_POST[$cod_p])){
$_SESSION['cod_p']=$cod_p;
printf("<script>location.href='modif_prod.php'</script>");
}
if(isset($_POST[$cod_bare])){
    //if the Modifica button is clicked store the experience id into a session  variable and redirect the user to modif_prod.php
$_SESSION['c_bare']=$c_bare;
printf("<script>location.href='vizualizare_cod_bare.php'</script>");
}
if(isset($_POST[$prod_mag])){
$_SESSION['cod_p']=$cod_p;
$_SESSION['den_p']=$den_p;
$_SESSION['pret_p']=$pret;
$_SESSION['um_p']=$um;
$dsql = "SELECT * from $tabel_final_date_firma";    
$dstmt = $pdo->prepare($dsql);  
$dstmt->execute(); 
while ($row = $dstmt->fetch(PDO::FETCH_ASSOC)){ 
                      $_SESSION['den_ent']=$row['den_ent'];
			            
			

}
printf("<script>location.href='listeaza_fisa_mag.php'</script>");
}
	if(isset($_POST[$prod_det])){
    //if the Detalii button is clicked store the experience id into a session  variable and redirect the user to product_details.php
$_SESSION['cod_p']=$cod_p;
printf("<script>location.href='product_details.php'</script>");


	
}

	if(isset($_POST[$prod_mag])){
    //if the Detalii button is clicked store the experience id into a session  variable and redirect the user to product_details.php
$_SESSION['cod_p']=$cod_p;
$_SESSION['den_p']=$den_p;
$_SESSION['pret_p']=$pret;
$_SESSION['um_p']=$um;
$dsql = "SELECT * from $tabel_final_date_firma";    
$dstmt = $pdo->prepare($dsql);  
$dstmt->execute(); 
while ($row = $dstmt->fetch(PDO::FETCH_ASSOC)){ 
                      $_SESSION['den_ent']=$row['den_ent'];
			            
			

}
printf("<script>location.href='listeaza_fisa_mag.php'</script>");


	
}
	if(isset($_POST[$prod_del])){
//if the Sterge button is clicked Sterge the record that has the experience id equal to $cod_p

$_SESSION['cod_pr']=$cod_p;
$_SESSION['den_p']=$den_p;
printf("<script>location.href='sterge_produs.php'</script>");




	
}
	}

?>
</tbody>
</table>

</section>
    </div>
    <a name="bottomOfPage"></a>

  
  </div>
</div><br>

    </div>

						<!------ Modal cod_bare begin -->

							<div class="modal fade" id="Prod_vandute" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
		  <div class="modal-dialog" role="document">
			<div class="modal-content">
			    
			  <div class="modal-header">
				<h4 class="modal-title" id="myModalLabel">Codul de bare al produsului</h4>
								<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>

			  </div>
			  <div class="modal-body">
				    <object width="100%" height="400" data="vizualizare_cod_bare.php"></object>
 </div>

				  </div>
		

							  </div>			  
	  
							  </div>
								<!------ Modal cod_bare end -->


    <script>
document.querySelectorAll('.sgr-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const cod_p = this.dataset.codP;
        const sgr_value = this.checked ? 1 : 0;

        fetch('update_sgr.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ cod_p, sgr_value })
        });
    });
});
</script>
<?php include 'footer.php'; ?>