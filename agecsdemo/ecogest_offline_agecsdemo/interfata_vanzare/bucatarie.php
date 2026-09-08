<?php
session_start();
   include('database_connection.php');

	date_default_timezone_set('UTC+2');
		date_default_timezone_set("Europe/Bucharest");

$ora_prep_bon = date("H:i:s", strtotime('+0 hours'));
 $data_prep_bon = date("Y-m-d", strtotime('+0 hours'));
 
        // If result matched $myusername and $mypassword, the result table's number of rows must be 1 row
	// if the table has 1 rows redirect the user to admin_index.php
	
	if(!isset($_SESSION['nr_bon'])){
	    $ccom_sql = "SELECT $tabel_final_de_listat_buc.nr_bon,$tabel_final_de_listat_buc.ora,$tabel_final_note.cod_masa FROM $tabel_final_de_listat_buc inner join $tabel_final_note on $tabel_final_de_listat_buc.nr_bon=$tabel_final_note.nrbon  where $tabel_final_de_listat_buc.preparat=0 group by nr_bon desc";    
$ccom_stmt = $pdo->prepare($ccom_sql);  
$ccom_stmt->execute(); 
 $count=$ccom_stmt->rowCount();
 while ($row = $ccom_stmt->fetch(PDO::FETCH_ASSOC)){ 
    $nr_bon=$row['nr_bon'];
    $masa_1=$row['cod_masa'];
}
    
}


else{
   
    $nr_bon=$_SESSION['nr_bon'];
    $masa_1=$_SESSION['masa_curenta'];
}

?>
				<html>
				<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="description" content="">
  <meta name="author" content="">
  <title>Bucatarie</title>
  <!-- Bootstrap core CSS-->
    <!-- Bootstrap core JavaScript-->

  <!-- Core plugin JavaScript-->
  <!-- Custom fonts for this template-->
  <!-- Custom styles for this template-->
  <link href="./numpad_files/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="./numpad_files/font-awesome.min.css">


				
		



<!-- Yandex.Metrika counter -->
<!-- /Yandex.Metrika counter -->
			


		<!-- JS Global -->
<style>html {
  overflow:   scroll;
}
::-webkit-scrollbar {
    width: 0px;
    background: transparent; /* make scrollbar transparent */
}</style>
</head>

<body class="bg-dark">
<!-- about -->



 <div>     
    <div class="container-fluid">








<?php if(isset($_POST['deconectare'])){


printf("<script>location.href='logout.php'</script>");
							
							} 

 						
							
							
							if(!isset($_SESSION['masa_curenta'])){
							    
							      
  $pm = "SELECT cod_masa, from $tabel_final_note where nrbon='$nr_bon'";    
$pmstmt = $pdo->prepare($pm);  
$pmstmt->execute();
while ($row = $pmstmt->fetch(PDO::FETCH_ASSOC)){ 
$_SESSION['masa_curenta']=$row['cod_masa'];
							}
							
							}
							
							
							
							
							?> 
    <div class="tab" style='margin:0'>
        <button disabled style="float:left" class="tablinks" >Interfata Bucatarie</button>

             <button disabled class="tablinks">Nota nr:<?php echo $nr_bon; ?></button>
<button disabled class="tablinks">Masa:<?php echo $masa_1; ?></button>
<form method="POST" style="margin:0;float:right;"><button name="deconectare" type="submit" class="tablinks">Deconectare</button></form>

<button disabled style="float:right" class="tablinks" id="timestamp" ></button>

</div>
<div class="wrapper">

    <div id="one" >
<table class="table table-dark" style='margin:0;width:90.5%'>
  <div class='row'>
  <?php
  
  
  $date_firma = "SELECT mod_listare,vanzare_sub_stoc from $tabel_final_date_firma";    
$date_firma_stmt = $pdo->prepare($date_firma);  
$date_firma_stmt->execute(); 
while ($row = $date_firma_stmt->fetch(PDO::FETCH_ASSOC)){ 

$_SESSION['vanzare_sub_stoc']=$row['vanzare_sub_stoc'];
$_SESSION['mod_listare']=$row['mod_listare'];
}

//afisare randuri 
$f_sql = "SELECT $tabel_final_de_listat_buc.id,$tabel_final_de_listat_buc.id_vanz,$tabel_final_de_listat_buc.nr_bon,$tabel_final_de_listat_buc.cantitate,$tabel_final_de_listat_buc.pachet,$tabel_final_de_listat_buc.preparat,$tabel_final_nomenclator.den_p,$tabel_final_de_listat_buc.data_com,$tabel_final_de_listat_buc.ora_com,$tabel_final_de_listat_buc.data_prep,$tabel_final_de_listat_buc.ora_prep FROM $tabel_final_de_listat_buc INNER JOIN $tabel_final_nomenclator on $tabel_final_de_listat_buc.cod_p=$tabel_final_nomenclator.cod_p where $tabel_final_de_listat_buc.nr_bon='$nr_bon' order by $tabel_final_de_listat_buc.id;";    
$f_stmt = $pdo->prepare($f_sql);  
$f_stmt->execute(); 
echo "  
  <div class='input-row'><thead>
<tbody class='tbody lista_prod'  style='height:450px;'>
<tr>
<th style='width:100%'>Produs</th>
<th>Cantitate</th>
<th>Comandat la </th>
<th>Finalizat la</th>
<th style='width:20px;'>Actiuni</th>



</thead>";
while ($row = $f_stmt->fetch(PDO::FETCH_ASSOC)){ 
 $data_com=$row['data_com'];
    $data_com=date("d.m.Y", strtotime($data_com));
$ora_com=$row['ora_com'];
 $data_prep=$row['data_com'];
    $data_prep=date("d.m.Y", strtotime($data_prep));
$ora_prep=$row['ora_prep'];
$codul_produsului=$row['cod_p'];
$produs=$row['den_p'];
$cantitate=$row['cantitate'];
$id_vanz=$row['id'];
$id_simplu_vanz=$row['id'];
$id_simplu_vanz=strval($id_simplu_vanz);
$id_simplu_vanz.='VZ';
$pch=$row['pachet'];
$prep=$row['preparat'];
  echo "
   <tr>
    <td style='width:100%;height:auto; white-space: normal;'><div>$produs</div>";if($pch==0){echo "<div style='color:#ca2222'>Servit la masa";}else{echo "<div style='color:green'>La pachet";} echo"</div></td>
    <td>X $cantitate</td><td> $data_com $ora_com</td><td>$data_prep $ora_prep</td>
	<td><form method='post'>"; if($prep==0){echo "<input style='background-color:white;color:green;' class='btn btn-primary btn-block' title='Click pentru a confirma finalizarea preparatului' type='submit' value='FINALIZARE' name='$id_simplu_vanz'>";} else {echo "&#9989;";} echo "</form></td>

  </tr>
  " 
  
  ;
  if (isset($_POST[$id_simplu_vanz])) {
					$sterg_sql="UPDATE $tabel_final_de_listat_buc set preparat='1',ora_prep='$ora_prep_bon',data_prep='$data_prep_bon' where id='$id_vanz'";
					$sterg_f_stmt = $pdo->prepare($sterg_sql);  
$sterg_f_stmt->execute(); 



			printf("<script>location.href='bucatarie.php'</script>");
				}
  }
  

 
  ?></tbody></div>   <div class="buttons">  <button class='btn-sm scrol hidden-xs' title='Mentineti apasat pentru a derula in sus' id ="scrollup"><i class="fa fa-chevron-up"></i></button>
</br>
<button title='Mentineti apasat pentru a derula in jos' class='btn-sm scrol hidden-xs' id ="scrolldown"><i class="fa fa-chevron-down"></i></button>   
</div></div></table>
  <style>.row div{
  float: left;
}
.input-row{
  line-height: 30px;
  height: 30px;
}
.scrol {
  background-color: #4f4f4f;
  border-radius: 0;
  color: white;
  padding: 0 4px;
  height:16em;
}
.scrol2 {
  background-color: #4f4f4f;
  border-radius: 0;
  color: white;
  padding: 0 4px;
  height:10em;
}
.buttons{
  float:right;
  margin-right:0.1em;
}

</style>
  
	  <style>@media 
only screen and (max-width: 760px),
(min-device-width: 768px) and (max-device-width: 1024px)  {

	/* Force table to not be like tables anymore */
	.test, .thead, .tbody, .th, .td, .tr { 
		display: block; 
	}
	
	/* Hide table headers (but not display: none;, for accessibility) */
	.thead .tr { 
		position: absolute;
		top: -9999px;
		left: -9999px;
	}
	
	.tr { border: 1px solid #ccc; }
	
	.td { 
		/* Behave  like a "row" */
		border: none;
		border-bottom: 1px solid #eee; 
		position: relative;
		padding-left: 50%; 
	}
	
	.td:before { 
		/* Now like a table header */
		position: absolute;
		/* Top/left values mimic padding */
		top: 6px;
		left: 6px;
		width: 45%; 
		padding-right: 10px; 
		white-space: nowrap;
	}
	
	/*
	Label the data
	td:nth-of-type(1):before { content: "First Name"; }
	td:nth-of-type(2):before { content: "Last Name"; }
	td:nth-of-type(3):before { content: "Job Title"; }
	td:nth-of-type(4):before { content: "Favorite Color"; }
	td:nth-of-type(5):before { content: "Wars of Trek?"; }
	td:nth-of-type(6):before { content: "Secret Alias"; }
	td:nth-of-type(7):before { content: "Date of Birth"; }
	td:nth-of-type(8):before { content: "Dream Vacation City"; }
	td:nth-of-type(9):before { content: "GPA"; }
	td:nth-of-type(10):before { content: "Arbitrary Data"; }	*/

}</style>
	
	<script>
	    
	        
    $('#baniprim')
	.keyboard({
		layout : 'num',
		restrictInput : true, // Prevent keys not in the displayed keyboard from being typed in
		preventPaste : true,  // prevent ctrl-v and right click
		autoAccept : true
	})
	.addTyping();

	
	</script>
		<?php

	if(!isset($_SESSION['masa_curenta'])){

 $m_n_sql = "SELECT $tabel_final_note.cod_masa from $tabel_final_note where nrbon='$nr_bon';";    
$m_n_stmt = $pdo->prepare($m_n_sql);  
$m_n_stmt->execute();

while ($row = $m_n_stmt->fetch(PDO::FETCH_ASSOC)){
 $m_n=$row['cod_masa'];
$_SESSION['masa_curenta']=$m_n;
}

}

else{
    
 $m_n_sql = "SELECT $tabel_final_note.cod_masa from $tabel_final_note where nrbon='$nr_bon';";    
$m_n_stmt = $pdo->prepare($m_n_sql);  
$m_n_stmt->execute();

while ($row = $m_n_stmt->fetch(PDO::FETCH_ASSOC)){
    $m_n=$row['cod_masa'];
}
}
?>
	


</div>
 <div id="two">

            <?php 
         // select latest 5 rows in the table "nomenclator"


$mese_desch_sql = "SELECT $tabel_final_note.cod_masa,$tabel_final_de_listat_buc.nr_bon FROM $tabel_final_de_listat_buc inner join $tabel_final_note on $tabel_final_de_listat_buc.nr_bon=$tabel_final_note.nrbon where $tabel_final_de_listat_buc.preparat='0' group by $tabel_final_de_listat_buc.nr_bon";    
$mese_desch_stmt = $pdo->prepare($mese_desch_sql);  
$mese_desch_stmt->execute(); 
 $ccount=$mese_desch_stmt->rowCount();

if($ccount!=0){
    echo '<div><h2> De Preparat</h2>
<div style="float:left;width:80%;height:300px;" class="tbody lista_nomencl">';

    
					while ($row = $mese_desch_stmt->fetch()) {
					   $nota=$row['nr_bon'];
					   $not=$row['nr_bon'];
					   $not=strval($not);
$not.='N';
$masa=$row['cod_masa'];
$produss=$row['den_p'];
	// display inside cards the nomenclator from the result table of the sql query
              echo" 
              <div >
                <p><form method='POST'><button style='width:100%; background-color:white;color:black; font-size:1em; height:auto; white-space: normal;
' class='btn btn-primary btn-block' type='submit' name='$not'>Nota: $nota Masa: $masa</br>";

$mese_desch_sql2 = "SELECT $tabel_final_nomenclator.den_p from $tabel_final_det_note INNER JOIN $tabel_final_nomenclator on $tabel_final_det_note.cod_p=$tabel_final_nomenclator.cod_p where $tabel_final_det_note.nr_bon='$nota' and $tabel_final_nomenclator.departament='BUC' AND $tabel_final_nomenclator.gestiune!='MP' AND $tabel_final_nomenclator.gestiune!='MC' AND $tabel_final_nomenclator.gestiune!='MA'";    
$mese_desch_stmt2 = $pdo->prepare($mese_desch_sql2);  
$mese_desch_stmt2->execute(); 

					while ($row = $mese_desch_stmt2->fetch()) {
					    
					   echo $row['den_p'].'</br>'; 
					}
echo "
</button></form>
                </p>
              </div>";
					    if(isset($_POST[$not])){

								
							$_SESSION['nr_bon']=$nota;
							$_SESSION['masa_curenta']=$masa;
							printf("<script>location.href='bucatarie.php'</script>");

							
	
}	
					    
					    
					}
		echo "			
				</div>
<div class='buttons'>  </br><button class='btn-sm scrol2 hidden-xs' title='Mentineti apasat pentru a derula in sus' id ='scrollup2'><i class='fa fa-chevron-up'></i></button>
</br>
<button title='Mentineti apasat pentru a derula in jos' class='btn-sm scrol2 hidden-xs' id ='scrolldown2'><i class='fa fa-chevron-down'></i></button>   
</div></div>";
	}
				
              
              ?>

            <?php 
$mese_desch_sql = "SELECT $tabel_final_note.cod_masa,$tabel_final_de_listat_buc.nr_bon FROM $tabel_final_de_listat_buc inner join $tabel_final_note on $tabel_final_de_listat_buc.nr_bon=$tabel_final_note.nrbon group by $tabel_final_de_listat_buc.nr_bon";    
$mese_desch_stmt = $pdo->prepare($mese_desch_sql);  
$mese_desch_stmt->execute(); 
 $ccount2=$mese_desch_stmt->rowCount();
if($ccount2!=0){
    echo '<div style="margin-top:20em;"><h2> Preparate</h2>
<div style="float:left;width:80%;height:200px;" class="tbody lista_nomencl2">';
					while ($row = $mese_desch_stmt->fetch()) {
   $nota=$row['nr_bon'];
					   $not=$row['nr_bon'];
					   $not=strval($not);
$not.='N';

$masa=$row['cod_masa'];


$ttest_sql = "SELECT sum($tabel_final_de_listat_buc.cantitate) as total_cant_bon FROM $tabel_final_de_listat_buc where $tabel_final_de_listat_buc.nr_bon='$nota' and $tabel_final_de_listat_buc.preparat='0'";
$ttest_stm = $pdo->prepare($ttest_sql);  
$ttest_stm->execute(); 
while ($row = $ttest_stm->fetch(PDO::FETCH_ASSOC)){
      $cantitate_totala_bon=$row['total_cant_bon'];

}

if(is_null($cantitate_totala_bon)){

	
	// display inside cards the nomenclator from the result table of the sql query
              echo" 
              <div >
                <p ><form method='POST'><button style='width:100%; background-color:white;color:black; font-size:1em; height:auto; white-space: normal;
' class='btn btn-primary btn-block' type='submit' name='$not'>Nota: $nota Masa: $masa</br>";

$mese_desch_sql2 = "SELECT $tabel_final_nomenclator.den_p from $tabel_final_det_note INNER JOIN $tabel_final_nomenclator on $tabel_final_det_note.cod_p=$tabel_final_nomenclator.cod_p where $tabel_final_det_note.nr_bon='$nota' and $tabel_final_nomenclator.departament='BUC' AND $tabel_final_nomenclator.gestiune!='MP' AND $tabel_final_nomenclator.gestiune!='MC' AND $tabel_final_nomenclator.gestiune!='MA'";    
$mese_desch_stmt2 = $pdo->prepare($mese_desch_sql2);  
$mese_desch_stmt2->execute(); 

					while ($row = $mese_desch_stmt2->fetch()) {
					    
					   echo $row['den_p'].'</br>'; 
					}
echo "
</button></form>
                </p>
              </div>";
					    if(isset($_POST[$not])){

								
							$_SESSION['nr_bon']=$nota;
							printf("<script>location.href='bucatarie.php'</script>");

							
	
}	
					    
}
					    
					}
      echo "</div>      
              <div class='buttons'>  </br><button class='btn-sm scrol2 hidden-xs' title='Mentineti apasat pentru a derula in sus' id ='scrollup3'><i class='fa fa-chevron-up'></i></button>
</br>
<button title='Mentineti apasat pentru a derula in jos' class='btn-sm scrol2 hidden-xs' id ='scrolldown3'><i class='fa fa-chevron-down'></i></button>   
</div>
</div>";
              }
              ?>



</div>    





<!-- 3rd row responsive images in background with centered content -->




</div>			  
			</div>	

			<script src="javascript.js"></script>

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



 $('.rightArrow').on({
    'mousedown touchstart': function() {
      var leftPos = $('.content_cat').scrollLeft();
  $(".content_cat").animate({scrollLeft: leftPos + 200}, 800);
    },
    'mouseup touchend': function() {
      $(".content_cat").stop(true);
    }
});
 $('.leftArrow').on({
    'mousedown touchstart': function() {
      var leftPos = $('.content_cat').scrollLeft();
  $(".content_cat").animate({scrollLeft: leftPos - 200}, 800);
    },
    'mouseup touchend': function() {
      $(".content_cat").stop(true);
    }
});

    $('#scrollup2').on({
    'mousedown touchstart': function() {
      $(".lista_nomencl").animate({scrollTop:  0}, 500);
    },
    'mouseup touchend': function() {
      $(".lista_nomencl").stop(true);
    }
  });

  $('#scrolldown2').on({
    'mousedown touchstart': function() {
      $(".lista_nomencl").animate({
        scrollTop:  $(".lista_nomencl")[0].scrollHeight
      }, 1000);
    },
    'mouseup touchend': function() {
      $(".lista_nomencl").stop(true);
    }
});



    $('#scrollup3').on({
    'mousedown touchstart': function() {
      $(".lista_nomencl2").animate({scrollTop:  0}, 500);
    },
    'mouseup touchend': function() {
      $(".lista_nomencl2").stop(true);
    }
  });

  $('#scrolldown3').on({
    'mousedown touchstart': function() {
      $(".lista_nomencl2").animate({
        scrollTop:  $(".lista_nomencl2")[0].scrollHeight
      }, 1000);
    },
    'mouseup touchend': function() {
      $(".lista_nomencl2").stop(true);
    }
});



})();
		  </script>
		<script>
		
		$(document).ready(function() {
		    $(document).ready(function() {
    setInterval(timestamp, 1000);
});

setInterval(function() {
    var date = new Date();
    $('#timestamp').html(
        date.getHours() + ":" + date.getMinutes() + ":" + date.getSeconds()
        );
}, 500);
	

  


	});

</script>

<script>
    
var timer = null;

function goAway() {
    clearTimeout(timer);
    timer = setTimeout(function() {
        window.location = 'bucatarie.php';
    }, 2000);
}

window.addEventListener('mousemove', goAway, true);
window.addEventListener('mousedown', goAway, true);
window.addEventListener('keydown', goAway, true);
window.addEventListener('scroll', goAway, true);

goAway();  // start the first timer off
</script>
</body>
</html>
