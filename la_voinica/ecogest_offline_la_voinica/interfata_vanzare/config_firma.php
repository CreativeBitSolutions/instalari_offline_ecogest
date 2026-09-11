<?php  include('session.php');
	$title = 'Configurare Firma';
  include 'header.php';
include 'check_priv.php';
?>

      <!-- Breadcrumbs-->
    
      <div class="container">
        <section class="right">          <style>label{font-weight:bold;}</style>	

 <?php
$dsql = "SELECT * from $tabel_final_date_firma";
$dstmt = $pdo->prepare($dsql);  
$dstmt->execute(); 

while ($row = $dstmt->fetch(PDO::FETCH_ASSOC)){  
        $den_ent=$row['den_ent'];
        $cod_fiscal=$row['cod_fiscal'];
        $nr_reg_com=$row['nr_reg_com'];
        $sediu=$row['sediu'];
        $judet=$row['judet'];
        $banca=$row['banca'];
        $cont_banca=$row['cont_banca'];
        $cap_soc=$row['cap_soc'];
        $serie_casa_marcat=$row['serie_casa_marcat'];
        $nui=(int)($row['nui'] ?? 0);
        $serie_memorie_fiscala=(string)($row['serie_memorie_fiscala'] ?? '');
        $mod_listare=$row['mod_listare'];
        $cond_ent=$row['conducator_entitate'];
        $vanzare_sub_stoc=$row['vanzare_sub_stoc'];
        $ajustare_adaos=$row['ajustare_adaos'];
}
?>
	<?php
	

	if (isset($_POST['edit_admin'])) {

	
	
$stmt = $pdo->prepare("UPDATE $tabel_final_date_firma SET den_ent = :den_ent,cod_fiscal = :cod_fiscal,nr_reg_com = :nr_reg_com,sediu=:sediu,judet=:judet,banca=:banca,conducator_entitate=:cond_ent,cont_banca=:cont_banca,cap_soc=:cap_soc,serie_casa_marcat=:serie_casa_marcat,nui=:nui,serie_memorie_fiscala=:serie_memorie_fiscala,mod_listare=:mod_listare,vanzare_sub_stoc=:vanzare_sub_stoc,ajustare_adaos=:ajustare_adaos  WHERE den_ent= '$den_ent'");

$criteria = [
	'den_ent' => $_POST['den_ent'],
	'cod_fiscal' => $_POST['cod_fiscal'],
	'nr_reg_com' => $_POST['nr_reg_com'],
    'sediu' => $_POST['sediu'],
    'judet' => $_POST['judet'],
    'banca' => $_POST['banca'],
    'cont_banca' => $_POST['cont_banca'],
    'cap_soc' => $_POST['cap_soc'],
    'serie_casa_marcat' => $_POST['serie_casa_marcat'],
    'nui' => max(0, (int)($_POST['nui'] ?? 0)),
    'serie_memorie_fiscala' => trim((string)($_POST['serie_memorie_fiscala'] ?? '')),
    'cond_ent' => $_POST['cond_ent'],
    'vanzare_sub_stoc' => $_POST['vanzare_sub_stoc'],
    'mod_listare' => $_POST['mod_listare'],
       'ajustare_adaos' => $_POST['ajustare_adaos']
];

$stmt->execute($criteria);


printf("<script>location.href='config_firma.php'</script>");	}

            ?>

           

	<section class="right">
<div class="card-body">
<form action="config_firma.php" method="POST">
	<div class="form-group">
<label for="exampleInputPassword1">Denumire Entitate:</label>
<input class="form-control" id="exampleInputPassword1" type="text" name="den_ent"  placeholder="Denumire entitate" value="<?php echo $den_ent; ?>"/>
	</div>
		<div class="form-group">
<label for="exampleInputPassword1">Conducator Entitate:</label>
<input class="form-control" id="exampleInputPassword1" type="text" name="cond_ent"  placeholder="Conducator entitate" value="<?php echo $cond_ent; ?>"/>
	</div>
	<div class="form-group">
<label for="exampleInputPassword1">Codul Fiscal:</label>
<input class="form-control" id="exampleInputPassword1" type="text" name="cod_fiscal" placeholder="Cui" value="<?php echo $cod_fiscal; ?>">
	</div>
	<div class="form-group">
<label for="exampleInputPassword1">Numarul de inregistrare de la Registru Comertului:</label>
<input class="form-control" id="exampleInputPassword1" type="text" name="nr_reg_com" placeholder="JXX/XXXXX/XXXX" value="<?php echo $nr_reg_com; ?>">
	</div>
	<div class="form-group">
<label for="exampleInputPassword1">Sediu:</label>
<input class="form-control" id="exampleInputPassword1" type="text" name="sediu" placeholder="Sediu" value="<?php echo $sediu; ?>">
	</div>
	<div class="form-group">
<label for="exampleInputPassword1">Judetul:</label>
<select class='form-control' name="judet">
 <option disabled selected><?php echo $judet; ?></option>
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
</select>
	</div>
	<div class="form-group">
<label for="exampleInputPassword1">Banca:</label>
<input class="form-control" id="exampleInputPassword1" type="text" name="banca" placeholder="Banca" value="<?php echo $banca; ?>">
	</div>
	<div class="form-group">
<label for="exampleInputPassword1">Cont la banca:</label>
<input class="form-control" id="exampleInputPassword1" type="text" name="cont_banca" placeholder="Contul la banca" value="<?php echo $cont_banca; ?>">
	</div>
	<div class="form-group">
<label for="exampleInputPassword1">Capital Social:</label>
<input class="form-control" id="exampleInputPassword1" type="number" name="cap_soc" placeholder="Capital social" value="<?php echo $cap_soc; ?>">
	</div>
	<div class="form-group">
<label for="exampleInputPassword1">Serie Casa de Marcat:</label>
<input class="form-control" id="exampleInputPassword1" type="text" name="serie_casa_marcat" placeholder="SB0000111111" value="<?php echo $serie_casa_marcat; ?>">
	</div>
	<div class="form-group">
<label for="nui">NUI:</label>
<input class="form-control" id="nui" type="number" min="0" name="nui" value="<?php echo $nui; ?>">
	</div>
	<div class="form-group">
<label for="serie_memorie_fiscala">Serie memoriei fiscale:</label>
<input class="form-control" id="serie_memorie_fiscala" type="text" name="serie_memorie_fiscala" value="<?php echo htmlspecialchars($serie_memorie_fiscala, ENT_QUOTES, 'UTF-8'); ?>">
	</div>
	<div class="form-group">
<label for="exampleInputPassword1">Modul de Listare:</label>
<select class='form-control' name="mod_listare">
    <?php
if ($mod_listare=="simplu") {
echo"	<option selected value='simplu'>Simplu (Fara imprimanta nefiscala)</option>'";
echo"	<option value='complex'>Complex (Cu imprimanta nefiscala)</option>";
}elseif ($mod_listare=="complex") {
echo"	<option  value='simplu'>Simplu (Fara imprimanta nefiscala)</option>";
echo"	<option selected value='complex'>Complex (Cu imprimanta nefiscala)</option>";
}else  {
echo"	<option  value='simplu'>Simplu (Fara imprimanta nefiscala)</option>";
echo"	<option  value='complex'>Complex (Cu imprimanta nefiscala)</option>";
}?>

</select>
	</div>
		<div class="form-group">
<label for="exampleInputPassword1">Vanzare sub limita stocului:</label>
<select class='form-control' name="vanzare_sub_stoc">
    <?php
if ($vanzare_sub_stoc=="0") {
echo"	<option selected value='0'>NU</option>'";
echo"	<option value='1'>DA</option>";
}elseif ($vanzare_sub_stoc=="1") {
echo"	<option  value='0'>NU</option>";
echo"	<option selected 1='DA'>DA</option>";
}else  {
echo"	<option  value='0'>NU</option>";
echo"	<option  value='1'>DA</option>";
}?>

</select>
	</div>
			<div class="form-group">
<label for="exampleInputPassword1">Mentine pret de vanzare fix indiferent de modul de servire:(In cazul servirii la masa a unui produs se va aplica cota tva de 5% dar pentru a se mentine acelasi pret de vanzare ca pentru cota de 9% se va creste cota adaosului comercial)</label>
<select class='form-control' name="ajustare_adaos">
    <?php
if ($ajustare_adaos=="0") {
echo"	<option selected value='0'>NU</option>'";
echo"	<option value='1'>DA</option>";
}elseif ($ajustare_adaos=="1") {
echo"	<option  value='0'>NU</option>";
echo"	<option selected 1='DA'>DA</option>";
}else  {
echo"	<option  value='0'>NU</option>";
echo"	<option  value='1'>DA</option>";
}?>

</select>
	</div>
	<input class="btn btn-primary btn-block" type="submit" name="edit_admin" value="Salvare Modificari">
</form>
</div>

<hr>



	</section>

	
	
	
	
	
        

        
     

	  </div>
   </section>

   <?php include 'footer.php'; ?>
