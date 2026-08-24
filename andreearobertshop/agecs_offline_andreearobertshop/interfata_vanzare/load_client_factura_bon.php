<?php
include('database_connection.php');

 $id=$_GET['pers_jur'];

    $dsql = "SELECT * from $tabel_final_facturi_bonuri where idfactura='$id' group by denumire order by idfactura desc limit 1";    
$dstmt = $pdo->prepare($dsql);  
$dstmt->execute(); 
while ($row = $dstmt->fetch(PDO::FETCH_ASSOC)){ 
    $denumire=$row['denumire'];
    $adresa=$row['adresa'];
						 $cui_cif=$row['cui']; 
						 $cont_banca=$row['ct_banca']; 
						 $judet=$row['judet'];
                        $nr_reg_com=$row['nr_reg_com'];
                        $banca=$row['banca'];
}
?>

<form method="post">
   <tr> 
            <th><h6>Denumire</h6></th> 
            <td><input class='form-control' type="text" value="<?php echo $denumire; ?>" name="denumire" /></td> 
        </tr> 
        <tr> 
            <th><h6>Adresa</h6></th> 
            <td><input class='form-control' value="<?php echo $adresa; ?>" type="text" name="adresa" /></td> 
        </tr> 
        <tr> 
            <th><h6>Judet</h6></th> 
            <td><select class='form-control' name="judet">
	<option <?php if($judet=='ALBA'){echo "selected";} ?>  value="ALBA">ALBA</option>
	<option  <?php if($judet=='ARAD'){echo "selected";} ?>  value="ARAD">ARAD</option>
	<option  <?php if($judet=='ARGES'){echo "selected";} ?>  value="ARGES">ARGES</option>
	<option <?php if($judet=='BACAU'){echo "selected";} ?>   value="BACAU">BACAU</option>
	<option  <?php if($judet=='BIHOR'){echo "selected";} ?>  value="BIHOR">BIHOR</option>
	<option  <?php if($judet=='BISTRITA-NASAUD'){echo "selected";} ?>  value="BISTRITA-NASAUD">BISTRITA-NASAUD</option>
	<option  <?php if($judet=='BOTOSANI'){echo "selected";} ?>  value="BOTOSANI">BOTOSANI</option>
	<option  <?php if($judet=='BRAILA'){echo "selected";} ?>  value="BRAILA">BRAILA</option>
	<option  <?php if($judet=='BRASOV'){echo "selected";} ?>  value="BRASOV">BRASOV</option>
	<option  <?php if($judet=='BUCURESTI'){echo "selected";} ?>  value="BUCURESTI">BUCURESTI</option>
	<option  <?php if($judet=='BUZAU'){echo "selected";} ?>  value="BUZAU">BUZAU</option>
	<option  <?php if($judet=='CALARASI'){echo "selected";} ?>  value="CALARASI">CALARASI</option>
	<option  <?php if($judet=='CARAS'){echo "selected";} ?>  value="CARAS-SEVERIN">CARAS-SEVERIN</option>
	<option  <?php if($judet=='CLUJ'){echo "selected";} ?>  value="CLUJ">CLUJ</option>
	<option  <?php if($judet=='CONSTANTA'){echo "selected";} ?>  value="CONSTANTA">CONSTANTA</option>
	<option  <?php if($judet=='COVASNA'){echo "selected";} ?>  value="COVASNA">COVASNA</option>
	<option  <?php if($judet=='DAMBOVITA'){echo "selected";} ?>  value="DAMBOVITA">DAMBOVITA</option>
	<option  <?php if($judet=='DOLJ'){echo "selected";} ?>  value="DOLJ">DOLJ</option>
	<option  <?php if($judet=='GALATI'){echo "selected";} ?>  value="GALATI">GALATI</option>
	<option  <?php if($judet=='GIURGIU'){echo "selected";} ?>  value="GIURGIU">GIURGIU</option>
	<option  <?php if($judet=='GORJ'){echo "selected";} ?>  value="GORJ">GORJ</option>
	<option  <?php if($judet=='HARGHITA'){echo "selected";} ?>  value="HARGHITA">HARGHITA</option>
	<option  <?php if($judet=='HUNEDOARA'){echo "selected";} ?>  value="HUNEDOARA">HUNEDOARA</option>
	<option  <?php if($judet=='IALOMITA'){echo "selected";} ?>  value="IALOMITA">IALOMITA</option>
	<option  <?php if($judet=='IASI'){echo "selected";} ?>  value="IASI">IASI</option>
	<option  <?php if($judet=='ILFOV'){echo "selected";} ?>  value="ILFOV">ILFOV</option>
	<option  <?php if($judet=='MARAMURES'){echo "selected";} ?>  value="MARAMURES">MARAMURES</option>
	<option  <?php if($judet=='MEHEDINTI'){echo "selected";} ?>  value="MEHEDINTI">MEHEDINTI</option>
	<option  <?php if($judet=='MURES'){echo "selected";} ?>  value="MURES" selected>MURES</option>
	<option  <?php if($judet=='NEAMT'){echo "selected";} ?>  value="NEAMT">NEAMT</option>
	<option  <?php if($judet=='OLT'){echo "selected";} ?>  value="OLT">OLT</option>
	<option  <?php if($judet=='PRAHOVA'){echo "selected";} ?>  value="PRAHOVA">PRAHOVA</option>
	<option  <?php if($judet=='SALAJ'){echo "selected";} ?>  value="SALAJ">SALAJ</option>
	<option  <?php if($judet=='SATU MARE'){echo "selected";} ?>  value="SATU MARE">SATU MARE</option>
	<option  <?php if($judet=='SIBIU'){echo "selected";} ?>  value="SIBIU">SIBIU</option>
	<option <?php if($judet=='SUCEAVA'){echo "selected";} ?>   value="SUCEAVA">SUCEAVA</option>
	<option  <?php if($judet=='TELEORMAN'){echo "selected";} ?>  value="TELEORMAN">TELEORMAN</option>
	<option  <?php if($judet=='TIMIS'){echo "selected";} ?>  value="TIMIS">TIMIS</option>
	<option  <?php if($judet=='TULCEA'){echo "selected";} ?>  value="TULCEA">TULCEA</option>
	<option  <?php if($judet=='VALCEA'){echo "selected";} ?>  value="VALCEA">VALCEA</option>
	<option  <?php if($judet=='VASLUI'){echo "selected";} ?>  value="VASLUI">VASLUI</option>
	<option  <?php if($judet=='VRANCEA'){echo "selected";} ?>  value="VRANCEA">VRANCEA</option>
</select></td> 
        </tr> 
		<tr> 
            <th><h6>Cod Unic de Identificare(CUI/CIF/CNP)</h6></th> 
            <td><input class='form-control'  value="<?php echo $cui_cif; ?>" required type="text" name="cui" ></td> 
        </tr>
        <tr> 
            <th><h6>Nr. Ord. Reg. Com</h6></th> 
            <td>  <input value="<?php echo $nr_reg_com; ?>"  class='form-control'  type="text"  name="nr_reg_com" placeholder="JXX/XXXXX/XXXX"  title="Numarul de ordine la Oficiul National al Registrului Comertului ">
</td> 
        </tr>
        
		<tr> 
            <th><h6>Contul Bancar</h6></th> 
            <td><input   value="<?php echo $cont_banca; ?>"   class='form-control' max="50" type="text" name="ct_banca"  ></td> 
        </tr>
        
        	<tr> 
            <th><h6>Banca</h6></th> 
            <td><input class='form-control' value="<?php echo $banca; ?>"   type="text" name="banca" ></td> 
        </tr>
  
  <tr>

<tr> 	 
  <td><h6>Numarul Bonului</h6></td>
   <td>
   <?php 
	 
$catsql = "SELECT nrbon,data_bon,ora_bon from $tabel_final_note where status='F' and valoare_vanzare_cu_tva>1 and nrbon not in (SELECT nrbon from facturi_bonuri_12) order by data_bon,ora_bon DESC";    
$catstmt = $pdo->prepare($catsql);  
$catstmt->execute(); 

echo "<select class='form-control' name='nrbon'>"; 
 
while ($row = $catstmt->fetch(PDO::FETCH_ASSOC)){ 
                      $nrbon=$row['nrbon'];
             $data_bon=$row['data_bon'];
			              $ora_bon=$row['ora_bon'];
                       $data_bon=date("d/m/Y", strtotime($data_bon));

              echo "<option value='$nrbon'>Bonul $nrbon | Data $data_bon | Ora $ora_bon</option>";    
             
			

}
echo "</select>";

?>
   </tr> 
<tr> 
 <td><h6>Seria Facturii</h6></td>
 <td><input class="form-control" required type="text" name="serie_factura" value='FB' maxlength="11">
 </td></tr>
 <tr>
     <td><h6>Data Emiterii</h6></td>
   <td><input class="form-control" type="date" value="<?php echo date('Y-m-d');?>" name="data_factura" ></td></tr> 
<br>
<tr><td><input class='btn btn-primary btn-block' type="submit" name="date_factura" value="Continuati"> </form>
