<?php  include('session.php');
	$title = 'Modifica tert';
  include 'header.php';
	
?>

      <!-- Breadcrumbs-->
    
		
       <style>th{width:15em;}
       </style>

      <!-- Example DataTables Card-->
      <div class="card mb-3">
        <div class="card-header">
          <i class="fa fa-table"></i> Modifica tert</div>
        <div class="card-body">
          <div class="table-responsive">
		 <hr>
<?php 
				
				

					$cod_tert=$_SESSION['cod_tert'];

$nsql = "SELECT * from $tabel_final_terti where cod_tert='$cod_tert'";    
$nstmt = $pdo->prepare($nsql);  
$nstmt->execute(); 









while ($row = $nstmt->fetch(PDO::FETCH_ASSOC)){ 
                   $den_cur = $row['denumire'];
    $adresa_cur =$row['adresa'];
	$cui_cur = $row['cui_cif'];
	$ct_cur = $row['cont_banca'];
	$nr_reg_com_cur=$row['nr_reg_com'];
	$banca_cur=$row['banca'];

                  
}?> 
<form method="post">
<table width="100%" border="0" cellpadding="0" cellspacing="2"> 
  <tr> 
            <th><h4>Denumire:</h4></th> 
            <td><input value="<?php echo $den_cur;?>" class='form-control' type="text" name="denumire" /></td> 
        </tr> 
        <tr> 
            <th><h4>Adresa:</h4></th> 
            <td><input value="<?php echo $adresa_cur;?>" class='form-control' type="text" name="adresa" /></td> 
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
            <th><h4>Cod unic de identificare(CUI/CIF):</h4></th> 
            <td><input value="<?php echo $cui_cur;?>" class='form-control' type="text" name="cui" maxlength="10"></td> 
        </tr>
        <tr> 
            <th><h4>Nr.ord.reg.com:</h4></th> 
            <td>  <input value="<?php echo $nr_reg_com_cur;?>" class='form-control'  type="text" maxlength="14" name="nr_reg_com" placeholder="JXX/XXXXX/XXXX"  title="Numarul de ordine la Oficiul National al Registrului Comertului ">
</td> 
        </tr>
        
		<tr> 
            <th><h4>Contul bancar:</h4></th> 
            <td><input value="<?php echo $ct_cur;?>" class='form-control' type="text" name="ct_banca"  ></td> 
        </tr>
        
        	<tr> 
            <th><h4>Banca:</h4></th> 
            <td><input value="<?php echo $banca_cur;?>" class='form-control' type="text" name="banca" ></td> 
        </tr>
        
		
        <tr> 
            <td>&nbsp</td> 
            <td><input class="btn btn-primary btn-block" type="submit" name="modifica" value="Salveaza" ></td> 
        </tr> 
        
            

    </table> 
</form> 
  <br>
  <?php 

if(isset($_POST['modifica'])){
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
     // formeaza si executa queryul de inserare in baza de date 
$sql="update $tabel_final_terti set denumire='$den',adresa='$adresa',cui_cif='$cui',cont_banca='$ct',judet='$judet',banca='$banca',nr_reg_com='$nr_reg_com' where cod_tert='$cod_tert'"; 	

try{
$pdo->exec($sql) or die(print_r($pdo->errorInfo(), true)); 
   
    // afiseaza un mesaj de succes 
			printf("<script>location.href='terti.php'</script>");
		  
}catch(PDOException $e)
    {
    echo $sql . "br" . $e->getMessage ();
    }
}
else 
	echo"<h3>Atentie completati denumirea</h3>";
}
else {echo"<h4 align='center'>Modificati tertul</h4>";}


?>
	
  
  
 
 

  

 

  
          </div>
        </div>
        
      </div>
    </div>

		
    
		<?php include 'footer.php'; ?>