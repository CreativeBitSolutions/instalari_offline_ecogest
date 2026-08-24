<table class="table table-dark" style='margin:0;width:90.5%;'>
  <div  class='row'>
  <?php

include('session.php');
$nr_bon=$_GET['bonul'];
  $m_n=$_GET['cod_masa'];

  
  $date_firma = "SELECT mod_listare,vanzare_sub_stoc,ajustare_adaos from $tabel_final_date_firma";    
$date_firma_stmt = $pdo->prepare($date_firma);  
$date_firma_stmt->execute(); 
while ($row = $date_firma_stmt->fetch(PDO::FETCH_ASSOC)){ 

$_SESSION['vanzare_sub_stoc']=$row['vanzare_sub_stoc'];
$_SESSION['mod_listare']=$row['mod_listare'];
$_SESSION['ajustare_adaos']=$row['ajustare_adaos'];
}

//afisare randuri vanzare
$f_sql = "SELECT $tabel_final_nomenclator.pret_cu_tva,$tabel_final_nomenclator.departament,$tabel_final_det_note.preparat,$tabel_final_det_note.pachet,$tabel_final_det_note.discount,$tabel_final_det_note.cod_p,$tabel_final_nomenclator.nume,$tabel_final_nomenclator.cota_tva,$tabel_final_nomenclator.um,$tabel_final_det_note.cantitate,$tabel_final_det_note.tva_col,$tabel_final_det_note.pret_vanzare,$tabel_final_det_note.valoare_vanzare,$tabel_final_det_note.valoare_vanzare_cu_tva,$tabel_final_det_note.id_vanz from $tabel_final_nomenclator inner join $tabel_final_det_note on $tabel_final_det_note.cod_p=$tabel_final_nomenclator.cod_produs  where nr_bon='$nr_bon' order by $tabel_final_det_note.id_vanz,$tabel_final_nomenclator.nume;";    
$f_stmt = $pdo->prepare($f_sql);  
$f_stmt->execute(); 
echo "  
   <div class='input-row'><thead>
<tbody  class='tbody lista_prod'  style='height:20em;'>
<tr>
<th style='width:100%'>Produs</th>
<th>Cantitate</th>
<th><div>Valoare</div></th>
<th><div>Aplica</div><div>Discount</div></th>
<th style='width:20px;'>Sterge</th>
</thead>";
while ($row = $f_stmt->fetch(PDO::FETCH_ASSOC)){
    $departament=$row['departament'];
$codul_produsului=$row['cod_p'];
$produs=$row['nume'];
$cantitate=$row['cantitate'];
$tva_col=$row['tva_col'];
$pret_initial  = round($row['pret_cu_tva'], 2);
$pret_vanzare=$row['pret_vanzare'];
$valoare_vanzare=$row['valoare_vanzare'];
$id_vanz=$row['id_vanz'];
$id_simplu_vanz=$row['id_vanz'];
$id_simplu_vanz=strval($id_simplu_vanz);
$id_simplu_vanz.='D';
$valoare_vanzare_c_tva=round(($row['valoare_vanzare_cu_tva'])*100)/100;
$cota=$row['cota'];
$d=$row['discount'];
$pch=$row['pachet'];
$prep=$row['preparat'];
  echo "
   <tr>
    <td style='width:100%;height:auto; white-space: normal;'><div>$produs | $pret_vanzare</div></td>
    <td ><button style='display:inline-block;' name='$id_vanz' value='$codul_produsului' data-value='$cantitate' class='btn btn-primary btn-block modif_cant' disabled  title='Click pentru modificarea cantitatii'>X  $cantitate</button></td>
	<td><div>$valoare_vanzare_c_tva</div>";echo"</td>
	<td><button name='$id_vanz' value='$codul_produsului' data-value='$cota' class='btn btn-primary btn-block discount' title='Click pentru aplicarea unui discount'>%</button>";
      // Dacă prețul de vânzare (modificat pe nota) este diferit de cel inițial, afișăm prețul inițial sub buton
    if ($pret_vanzare != $pret_initial) {
        echo "<div style='font-size:0.8em; color:gray; margin-top:5px;'>Pret inițial: $pret_initial RON</div>";
    }
   echo "</td>";

echo"<td><button style='background-color:white;color:red;' class='btn btn-primary btn-block sterge_prod' value='$id_vanz' data-value='$nr_bon' type='button'>X</button></td>
  </tr>
  
  
  
  " 
  
  ;

				
  }
  

 
  ?></tbody></div>   <div class="col-6 buttons">  <button class='btn-sm scrol hidden-xs' title='Mentineti apasat pentru a derula in sus' id ="scrollup"><i class="fa fa-chevron-up"></i></button>
</br>
<button title='Mentineti apasat pentru a derula in jos' class='btn-sm scrol hidden-xs' id ="scrolldown"><i class="fa fa-chevron-down"></i></button>   
</div></div></table>

  <table class='table table-bordered test'>
            <script>
    
    function showloading() {
  document.getElementById("loading").style.display = "block";
}
</script>
<form id='plata' onsubmit="showloading()"  action="procesare_vanzare.php" method='POST'>
    
 <tr class='tr' style='font-weight:bold;font-size:20px;'>
<?php 
 
 $f_tot_sql = "SELECT sum($tabel_final_det_note.valoare_vanzare_cu_tva) as total_val_vz_tva from $tabel_final_det_note  where nr_bon='$nr_bon'; ";    
$f_tot_stmt = $pdo->prepare($f_tot_sql);  
$f_tot_stmt->execute();

while ($row = $f_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$valoare_f_vz=$row['total_val_vz_tva'];
}

   ?>

 <?php 
 
 $f_tot_sql = "SELECT sum($tabel_final_det_note.valoare_vanzare_cu_tva) as total_de_incasat from $tabel_final_det_note where nr_bon='$nr_bon'; ";    
$f_tot_stmt = $pdo->prepare($f_tot_sql);  
$f_tot_stmt->execute();

while ($row = $f_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_val_vz_cu_tva=round($row['total_de_incasat'],2);
}
 $disc_tot_sql = "SELECT sum($tabel_final_det_note.discount) as total_disc from $tabel_final_det_note where nr_bon='$nr_bon'; ";    
$disc_tot_stmt = $pdo->prepare($disc_tot_sql);  
$disc_tot_stmt->execute();

while ($row = $disc_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_discount=$row['total_disc'];
}

$total_val_vz_cu_tva=round($total_val_vz_cu_tva,2);?>
<td class='td'>Total de încasat<input  step='0.001' readonly type="number" name="total" value="<?php echo $total_val_vz_cu_tva; ?>" class="form-control" id="total" onchange="updateDue()">  </td>	

	<!-- jQuery (required) & jQuery UI + theme (optional) -->


	<!-- demo only -->
		<td class='td'>C.I.F. CLIENT <input  id="cif_client" form="plata" value="<?php echo $_SESSION['cif_client'];?>" type="text" maxlength="10" name="cif_client" placeholder="Ex. RO12345678"><!--  <button type="button"  class="btn btn-primary" style='width:100%' data-toggle="modal" data-target="#set_client" >ALEGE CLIENT</button>  -->

</td><td class='td'><label for="rest">Suma încasată</label>	<div class="input-group"><input step="0.001" form='plata' value="<?php echo $total_val_vz_cu_tva; ?>" name='numerarprim' min="<?php echo $total_val_vz_cu_tva;?>" type="number"  id="baniprim" class="form-control" style="width:10em;"  onfocusout="updateDue()">

	 <label for="rest">Rest</label>
 <input readonly type="number" step='0.001' value="0" min="0" name="rest_numerar" class="form-control" id="rest"></td></tr>
	
	                <input class='masa_bon' hidden id="servire" type="number"   name="masa_curenta" form='plata' value="<?php echo $_SESSION['masa_curenta'];?>" />


	<script>// Autocomplete demo
var availableTags = ["ActionScript", "AppleScript", "Asp", "BASIC", "C", "C++", "Clojure",
	"COBOL", "ColdFusion", "Erlang", "Fortran", "Groovy", "Haskell", "Java", "JavaScript",
	"Lisp", "Perl", "PHP", "Python", "Ruby", "Scala", "Scheme" ];

$('#cif_client')
	.keyboard({ layout: 'qwerty' })
	.autocomplete({
		source: availableTags
	})

	//.addTyping();
	</script>
	<script>
    
    $('#baniprim')
	.keyboard({
		layout : 'num',
		restrictInput : true, // Prevent keys not in the displayed keyboard from being typed in
		preventPaste : true,  // prevent ctrl-v and right click
		autoAccept : true
	})
	//.addTyping();
	
</script>


	  <script>
		      
		      (function () {

  $('#scrollup').on({
    'mousedown touchstart': function() {
      $(".lista_prod").animate({scrollTop:  0}, 500);
    },
    'mouseup touchend': function() {
      $(".lista_prod").stop(true);
    }
  });

  $('#scrolldown').on({
    'mousedown touchstart': function() {
      $(".lista_prod").animate({
        scrollTop:  $(".lista_prod")[0].scrollHeight
      }, 1000);
    },
    'mouseup touchend': function() {
      $(".lista_prod").stop(true);
    }
});



 

})();
		  </script>
		  		<script>
		
		$(document).ready(function() {

		    

$('.discount').on('click', function() {
$('#Discount').modal('show');
var prod_discount = $(this).val();
var dataValue = this.getAttribute("data-value");
var idvanzare = this.getAttribute("name");

$('[name=prod_discount]').val(prod_discount);
$('[name=cota_calc_tva]').val(dataValue);
$('[name=idvanzare]').val(idvanzare);



});
$('.modif_cant').on('click', function() {
$('#Cantitate').modal('show');
var produs_modif = $(this).val();
var cantitate_curenta = this.getAttribute("data-value");
var idvanzare = this.getAttribute("name");

$('[name=produs_modif_cant]').val(produs_modif);
$('[name=cantitate_noua]').val(cantitate_curenta);
$('[name=cantitate_veche]').val(cantitate_curenta);
$('[name=idvz]').val(idvanzare);



});

		$('#plata_numerar_si_card').on('click', function() {
$('#Plata_numerar_si_card').modal('show'); 
});
		$('#plata_tichete').on('click', function() {
$('#Plata_tichete').modal('show'); 
});
		$('#produse_vandute').on('click', function() {
$('#Prod_vandute').modal('show'); 
});
	$('#amanate').on('click', function() {
$('#Amanate').modal('show'); 
});
	$('#relistare').on('click', function() {
$('#Relistare').modal('show'); 
});

  
    
$(function () {
    $('.masa_bon').focusout(function () {
        var text_val = $(this).val();
        if (text_val == 0) {
$('#setare_masa').modal('show');
        } 
    }
	
	
	
	).focusout();
  //trigger the focusout event manually
});

$('#setare_masa').modal({
                        backdrop: 'static',
                        keyboard: true, 
                        show: false
                });
                


function play_int() {
      $('#servire').val(9999);
      

    // do play
}

function play_pause() {
        var masa = <?php echo json_encode($m_n); ?>;
        if(masa!=9999){
        $('#servire').val(masa);
        }
                    else{

$('#setare_masa').modal({
                        backdrop: 'static',
                        keyboard: true, 
                        show: true
                });    
                
                    }   
                    

    // do pause
}


var totalwidth = 190 * $('.list-group').length;

$('.scoll-tree').css('width', totalwidth);



	});

</script>

	<style>#buttons_categ_modal{
position:absolute !important;
right:0 !important;}</style>