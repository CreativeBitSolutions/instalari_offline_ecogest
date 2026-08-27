<?php  include('session.php');
	$title = 'Terti';
  include 'header.php';
	
?>

      <!-- Breadcrumbs-->
    

		
       <style>th{width:15em;}
       </style>

      <!-- Example DataTables Card-->
      <div class="card mb-3">
        <div class="card-header">
          <i class="fa fa-table"></i> Lista clientilor si Furnizorilor</div>
        <div class="card-body">
          <div class="table-responsive">
		 <hr>

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
					<option selected value="furnizor">Furnizor</option>
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
if ($_POST['denumire'] != "")   
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
        echo '<center><div>Tert creat</div></center>';
		  
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
  
  
 <br>
 

  <h3>Lista Terților</h3>
  <?php
	 
$sql = "SELECT * from $tabel_final_terti";    
$stmt = $pdo->prepare($sql);  
$stmt->execute(); 

echo "<table class='table table-bordered' >"; 
echo" <tr><td> Denumire</td><td>Adresa</td><td>Judet</td><td>CUI/CIF</td><td>Nr. Ord. Reg. Com</td><td>Cont Bancar</td><td>Banca</td><td>Categorie Tert</td></tr>"; 
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)){  
			$cod_tert=$row['cod_tert'];    
			$mod_tert=$row['cod_tert'];
			$mod_tert=strval($mod_tert);
$mod_tert.='M';
              echo"<tr>";    
              echo"<td>".$row['denumire']."</td><td>".$row['adresa']."</td><td>".$row['judet']."</td><td>".$row['cui_cif']."</td><td>".$row['nr_reg_com']."</td><td>".$row['cont_banca']."</td><td>".$row['banca']."</td><td>".$row['tip_tert']."</form></td><form method='post'><td><input class='btn btn-primary btn-block' type='submit' value='Modifica Tert' name='$mod_tert'></td><td><input class='btn btn-primary btn-block' type='submit' value='Sterge Tert' name='$cod_tert'></td></form></tr>";
		
		if (isset($_POST[$mod_tert])) {
					$_SESSION['cod_tert']=$cod_tert;
				

			printf("<script>location.href='modif_tert.php'</script>");
				}
		
		
			if (isset($_POST[$cod_tert])) {
					
					$sql="DELETE from $tabel_final_terti WHERE $tabel_final_terti.cod_tert = $cod_tert";
$pdo->exec($sql) or die(print_r($pdo->errorInfo(), true));

			printf("<script>location.href='terti.php'</script>");
				}

}
echo "</table>"; 
?>   
          </div>
        </div>
        
      </div>
    </div>

		
    
		<?php include 'footer.php'; ?>