<?php include('session.php');
if (isset($_POST['stoc_la_data'])){
        $_SESSION['gestiune']=$_POST['gst'];

    $_SESSION['data_stoc']=$_POST['data'];
printf("<script>location.href='stoc_la_data.php'</script>");
 
}
if (isset($_POST['produse_vandute_la_interval'])){
        $_SESSION['gestiune']=$_POST['gst'];
    $_SESSION['data_start']=$_POST['data_start'];
        $_SESSION['data_end']=$_POST['data_end'];

printf("<script>location.href='produse_vandute_la_interval.php'</script>");
 
}
if (isset($_POST['produse_vandute_la_interval_excel'])){
        $_SESSION['gestiune']=$_POST['gst'];
    $_SESSION['data_start']=$_POST['data_start'];
        $_SESSION['data_end']=$_POST['data_end'];

printf("<script>location.href='produse_vandute_la_interval_excel.php'</script>");
 
}



if (isset($_POST['raport_de_gestiune'])){
        $_SESSION['gestiune']=$_POST['c_tva'];
    $_SESSION['data_start']=$_POST['data_start'];
        $_SESSION['data_end']=$_POST['data_end'];


if($_POST['metoda']=='cost_achizitie')
{
    printf("<script>location.href='raport_de_gestiune_cantitativ_valoric.php'</script>");

}

else

{
      printf("<script>location.href='raport_de_gestiune_global_valoric.php'</script>");

    
}


 
}

if (isset($_POST['situatia_stocurilor'])){
    $_SESSION['gestiune']=$_POST['gst'];
    $_SESSION['data_start']=$_POST['data_start'];
    $_SESSION['data_end']=$_POST['data_end'];
    printf("<script>location.href='situatia_stocurilor.php'</script>");
}



if (isset($_POST['iesiri_din_gestiune_la_interval'])){
    $_SESSION['data_start_iesiri_din_gest']=$_POST['data_start_iesiri_din_gest'];
        $_SESSION['data_end_iesiri_din_gest']=$_POST['data_end_iesiri_din_gest'];
        
printf("<script>location.href='iesiri_din_gestiune_valoric.php'</script>");
 
}

if (isset($_POST['intrari_in_gestiune_la_interval'])){
    $_SESSION['data_start_intrari_in_gest']=$_POST['data_start_intrari_in_gest'];
        $_SESSION['data_end_intrari_in_gest']=$_POST['data_end_intrari_in_gest'];
        
printf("<script>location.href='intrari_in_gestiune_valoric.php'</script>");
 
}


$dsql = "SELECT * from $tabel_final_date_firma";    
$dstmt = $pdo->prepare($dsql);  
$dstmt->execute(); 
while ($row = $dstmt->fetch(PDO::FETCH_ASSOC)){ 
                      $_SESSION['den_ent']=$row['den_ent'];
                      
}


?>  
<!-- Navigation-->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top" id="mainNav">
  <a class="navbar-brand" href="index.php"> NUVELA FOOD</a>
  <button class="navbar-toggler navbar-toggler-right" type="button" data-toggle="collapse" data-target="#navbarResponsive" aria-controls="navbarResponsive" aria-expanded="false" aria-label="Toggle navigation">
    <span class="navbar-toggler-icon"></span>
  </button>
  <div class="collapse navbar-collapse" id="navbarResponsive">
    <ul class="navbar-nav navbar-sidenav" id="exampleAccordion">
      
      
      <style>
	
.dropdown {
    position: relative;
    display: inline-block;
	float:right;
	
}

.dropdown-content {
    display: none;
    position: absolute;
    background-color: gray;
    min-width: 250px;
	min-height:50px;
    box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
    z-index: 1;
}

.dropdown-content a {
    color: black;
    padding: 12px 16px;
    text-decoration: none;
    display: block;
	text-align:right;
	font-size:10px;
}

.dropdown-content a:hover {background-color: #ff4500 }

.nav-item:hover .dropdown-content {
    display: block;
}


.menudown {
	position: relative;
    display: inline-block;
	

}


.menudown-content {
    display: none;
    position: absolute;
    background-color: black;
    min-width: 200px;
	min-height:30px;
    box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
    z-index: 1;
}

.menudown-content a {
    color: white;
    padding: 20px 16px;
    text-decoration: none;
    display: block;
	text-align:right;
	font-size:15px;
}

.menudown-content a:hover {background-color: #ff4500 }

.menudown:hover .menudown-content {
    display: block;
}



	</style>
	

  <li class="nav-item" data-toggle="tooltip" data-placement="right" title="Documente">
          <a class="nav-link nav-link-collapse collapsed" data-toggle="collapse" href="#collapseMulti" data-parent="#exampleAccordion">
            <i class="fa fa-fw fa-map-marker"></i>
            <span class="nav-link-text">Documente</span>
          </a>
          <ul class="sidenav-second-level collapse" id="collapseMulti">
            
            <li>
              <a href="facturi_bonuri.php">Facturi Bonuri Fiscale</a>
            </li>
			 <li>
              <a href="bonuri_de_consum.php">Bonuri de consum</a>
            </li>
            	 <li>
              <a href="bonuri_de_consum_personalizate.php">Bonuri de consum personalizate</a>
            </li>
              <li>
              <a href="chitante.php">Chitante</a>
            </li>
              <li>
              <a href="dispozitii.php">Dispoziții de Plată/Încasare</a>
            </li>
            <li>
              <a href="note_de_receptie.php">Note de Receptie</a>
            </li>
         
            <li>
              <a href="lista_note.php">Note Restaurant</a>
            </li>
           <li>
              <a href="lista_note_hotel.php">Note Hotel</a>
            </li>
            
          </ul>
        </li>
     
	    <li class="nav-item" data-toggle="tooltip" data-placement="right" title="Nomenclator">
          <a class="nav-link nav-link-collapse collapsed" data-toggle="collapse" href="#collapseProd" data-parent="#exampleAccordion">
            <i class="fa fa-fw fa-book"></i>
            <span class="nav-link-text">Nomenclator</span>
          </a>
          <ul class="sidenav-second-level collapse" id="collapseProd">
              <li>
              <a href="produse_admin.php">Lista Produselor </a>
            </li>
            <li>
              <a href="produse_admin.php">Creaza Produs</a>
            </li>
              <li>
              <a href="categorii_admin.php">Lista Categoriilor </a>
            </li>
            <li>
              <a href="categorii_admin.php">Creaza Categorie</a>
            </li>
            
          
        </li>  
            
          </ul>
        </li>  
        <li class="nav-item" data-toggle="tooltip" data-placement="right" title="produse vandute">
          <a class="nav-link nav-link-collapse collapsed" data-toggle="collapse" href="#collapseprodvandute" data-parent="#exampleAccordion">
            <i class="fa fa-fw fa-book"></i>
            <span class="nav-link-text">Produse vandute</span>
          </a>
          <ul class="sidenav-second-level collapse" id="collapseprodvandute">
             <form method="post"><h6 style='text-indent:20px;color:white'>   De la</h6><input class="form-control" name="data_start" value="<?php echo date('Y-m-d');?>" type="date"/><br/> <h6 style='text-indent:20px;color:white'>   Până la</h6><input class="form-control" name="data_end" value="<?php echo date('Y-m-d');?>" type="date"/><br/>
  <select style="width:100%" class="js-example-basic-single" name="gst">
    <option value="MP">Materii Prime</option>
	<option value="MC" >Materiale Consumabile</option>
	<option value="MA" >Materiale Auxiliare</option>
	<option value="MR" >Marfuri</option>
		<option value="OB" >Obiecte de inventar</option>
	<option value="MJ" >Mijloace fixe</option>
	<option value="MC2" >Materiale consumabile 2</option>
		<option selected value="PF" >Produse finite</option>
	<option value="toate" >Toate gestiunile</option>

</select><br/><input style='margin-top:1em;' value="Vizualizare " name="produse_vandute_la_interval" type="submit" class="btn btn-primary btn-block"/><input style='margin-top:1em;' value="Generare excel vanzari" name="produse_vandute_la_interval_excel" type="submit" class="btn btn-primary btn-block"/></form>
            
           
            
          </ul>
        </li> 
               <li class="nav-item" data-toggle="tooltip" data-placement="right" title="produse vandute">
          <a class="nav-link nav-link-collapse collapsed" data-toggle="collapse" href="#collapserapgest" data-parent="#exampleAccordion">
            <i class="fa fa-fw fa-book"></i>
            <span class="nav-link-text">Raport gestiune</span>
          </a>
          <ul class="sidenav-second-level collapse" id="collapserapgest">
             <form method="post"><h6 style='text-indent:20px;color:white'>   De la</h6><input class="form-control" name="data_start" value="<?php echo date('Y-m-d');?>" type="date"/><br/> <h6 style='text-indent:20px;color:white'>   Până la</h6><input class="form-control" name="data_end" value="<?php echo date('Y-m-d');?>" type="date"/><br/>
  <select style="width:100%" class="js-example-basic-single" name="c_tva">
    <option value="5">5% tva vanzare</option>
	<option value="9" >9% tva vanzare</option>
	<option value="19" >19% tva vanzare</option>
	<option value="0" >0% tva vanzare</option>
	
</select>
<br><br>
  <select style="width:100%" class="js-example-basic-single" name="metoda">
    <option value="cost_achizitie">La cost de achizitie (MP,MC,MC2)</option>
	<option value="pret_vanzare" selected >La pret de vanzare (MR)</option>

	
</select>

<br/><input style='margin-top:1em;' value="Vizualizare raport" name="raport_de_gestiune" type="submit" class="btn btn-primary btn-block"/></form>
            
           
            
          </ul>
        </li> 
        
                </li> 
               <li class="nav-item" data-toggle="tooltip" data-placement="right" title="produse vandute">
          <a class="nav-link nav-link-collapse collapsed" data-toggle="collapse" href="#sit_stoc" data-parent="#exampleAccordion">
            <i class="fa fa-fw fa-book"></i>
            <span class="nav-link-text">Situatia Stocurilor</span>
          </a>
          <ul class="sidenav-second-level collapse" id="sit_stoc">
             <form method="post"><h6 style='text-indent:20px;color:white'>   De la</h6><input class="form-control" name="data_start" value="<?php echo date('Y-m-d');?>" type="date"/><br/> <h6 style='text-indent:20px;color:white'>   Până la</h6><input class="form-control" name="data_end" value="<?php echo date('Y-m-d');?>" type="date"/><br/>
   <select style="width:100%" class="js-example-basic-single" name="gst">
    <option value="MP">Materii Prime</option>
	<option value="MC" >Materiale Consumabile</option>
	<option value="MA" >Materiale Auxiliare</option>
	<option value="MR" >Marfuri</option>
		<option value="OB" >Obiecte de inventar</option>
	<option value="MJ" >Mijloace fixe</option>
	<option value="MC2" >Materiale consumabile 2</option>
	<option value="toate" >Toate gestiunile</option>

</select>
<br>
<br/><input style='margin-top:1em;' value="Vizualizare raport" name="situatia_stocurilor" type="submit" class="btn btn-primary btn-block"/></form>
            
           
            
          </ul>
        </li> 
        
        
              <li class="nav-item" data-toggle="tooltip" data-placement="right" title="produse vandute">
          <a class="nav-link nav-link-collapse collapsed" data-toggle="collapse" href="#intrari_in_gest_interval" data-parent="#exampleAccordion">
            <i class="fa fa-fw fa-book"></i>
            <span class="nav-link-text">Intrari in gestiune</span>
          </a>
          <ul class="sidenav-second-level collapse" id="intrari_in_gest_interval">
             <form method="post"><h6 style='text-indent:20px;color:white'>   De la</h6><input class="form-control" name="data_start_intrari_in_gest" value="<?php echo date('Y-m-d');?>" type="date"/><br/> <h6 style='text-indent:20px;color:white'>   Până la</h6><input class="form-control" name="data_end_intrari_in_gest" value="<?php echo date('Y-m-d');?>" type="date"/><br/><input style='margin-top:1em;' value="Vizualizare raport intrari" name="intrari_in_gestiune_la_interval" type="submit" class="btn btn-primary btn-block"/></form>
            
           
            
          </ul>
        </li>  
        
        
                <li class="nav-item" data-toggle="tooltip" data-placement="right" title="produse vandute">
          <a class="nav-link nav-link-collapse collapsed" data-toggle="collapse" href="#iesiri_din_gest_interval" data-parent="#exampleAccordion">
            <i class="fa fa-fw fa-book"></i>
            <span class="nav-link-text">Iesiri din gestiune</span>
          </a>
          <ul class="sidenav-second-level collapse" id="iesiri_din_gest_interval">
             <form method="post"><h6 style='text-indent:20px;color:white'>   De la</h6><input class="form-control" name="data_start_iesiri_din_gest" value="<?php echo date('Y-m-d');?>" type="date"/><br/> <h6 style='text-indent:20px;color:white'>   Până la</h6><input class="form-control" name="data_end_iesiri_din_gest" value="<?php echo date('Y-m-d');?>" type="date"/><br/><input style='margin-top:1em;' value="Vizualizare raport ieșiri" name="iesiri_din_gestiune_la_interval" type="submit" class="btn btn-primary btn-block"/></form>
            
           
            
          </ul>
        </li>  

    
      
      
  <li class="nav-item" data-toggle="tooltip" data-placement="right" title="Creaza Documente">
          <a class="nav-link nav-link-collapse collapsed" data-toggle="collapse" href="#collapseDoc" data-parent="#exampleAccordion">
            <i class="fa fa-fw fa-tags"></i>
            <span class="nav-link-text">Creaza Documente</span>
          </a>
          <ul class="sidenav-second-level collapse" id="collapseDoc">
            <li>
              <a href="factura_bon.php">Factura Bon Fiscal</a>
            </li>
            <li>
              <a href="furnizor.php">NIR </a>
            </li>
              <li>
              <a href="consum.php">Bon de consum nou</a>
            </li>
            <li>
              <a href="client_chitanta.php">Chitanță </a>
            </li>
           <li>
              <a href="dispp1.php">Dispoziție de </br> Plată/Încasare </a>
            </li>
             <li>
              <a href="creare_monetar.php">Creare monetar</a>
            </li>
          </ul>
        </li>
 
  <li class="nav-item" data-toggle="tooltip" data-placement="right" title="">
        <a class="nav-link" href="terti.php">
    <i class="fa fa-fw fa-pencil"></i>
          <span class="nav-link-text">Terti</span>
        </a>
      </li>
 
  
     
     
      
      <script src="vendor/offline/timepicker/timepicker.min.js"></script>
<link href="vendor/offline/timepicker/timepicker.min.css" rel="stylesheet"/>

      <script>
          
          var timepicker = new TimePicker('time', {
  lang: 'en',
  theme: 'dark'
});
timepicker.on('change', function(evt) {
  
  var value = (evt.hour || '00') + ':' + (evt.minute || '00');
  evt.element.value = value;

});
      </script>
      
      
      <li class="nav-item" data-toggle="tooltip" data-placement="right" title="Rapoarte">
          <a class="nav-link nav-link-collapse collapsed" data-toggle="collapse" href="#collapseRap" data-parent="#exampleAccordion">
            <i class="fa fa-fw fa-tags"></i>
            <span class="nav-link-text">Rapoarte</span>
          </a>
          <ul class="sidenav-second-level collapse" id="collapseRap">
              <li>
              <a href="raport_nomenclator.php">Raport Nomenclator</a>
            </li>
             <li>
              <a href="raport_monetare.php">Raport Monetare</a>
            </li>
            
             <li>
              <a href="raport_inchideri_r.php">Raport Închideri Restaurant</a>
            </li>
             <li>
              <a href="raport_note.php">Raport Note Restaurant</a>
            </li>
              <li>
              <a href="fisa_client.php">Fișă Client</a>
            </li>
           <li>
              <a href="fisa_furnizor.php">Fișă Furnizor</a>
            </li>
            
          </ul>
        </li>
        <li class="nav-item" data-toggle="tooltip" data-placement="right" title="Apartamente">
          <a class="nav-link nav-link-collapse collapsed" data-toggle="collapse" href="#collapse_apart" data-parent="#exampleAccordion">
            <i class="fa fa-fw fa-money"></i>
            <span class="nav-link-text">Vanzarile Personalului</span>
          </a>
          <ul class="sidenav-second-level collapse" id="collapse_apart">
              <li>
              <a>De la 
<input class='form-control' type='date' id='vanzarile_personalului_data_start' value="<?php echo date('Y-m-d');?>">
<br/>
<input type="time" class='form-control' id="vanzarile_personalului_timp_start" value="00:00" />
</a>
 <a>Până la
<input class='form-control' type='date' id='vanzarile_personalului_data_stop' value="<?php echo date('Y-m-d');?>">
<br/>
<input type="time" class='form-control' id="vanzarile_personalului_timp_stop" value="23:59" />
</a>
            </li>

                        </br>
                        
            <li>
<div style="width:17em; margin-left:2.8em;" class="input-group">
<span class="input-group-btn">
<button id="vanzarile_personalului" style="width:12em;" class="btn btn-primary aplica" type="button">
Aplică <i class="fa fa-search"></i>
</button>
</span>
</div>
            </li>
            </br>
          </ul>         

        </li>
        
   
    <li class="nav-item" data-toggle="tooltip" data-placement="right" title="Administrare">
          <a class="nav-link nav-link-collapse collapsed" data-toggle="collapse" href="#collapseadm" data-parent="#exampleAccordion">
            <i class="fa fa-fw fa-user"></i>
            <span class="nav-link-text">Administrare </span>
          </a>
          <ul class="sidenav-second-level collapse" id="collapseadm">
            <li>
              <a href="config_firma.php">Configurare Firma</a>
            </li>
           
             <li class="nav-item" data-toggle="tooltip" data-placement="right" title="Tables">
        <a class="nav-link" href="creare_locatie_mese.php">
          <i class="fa fa-fw fa-users"></i>
          <span class="nav-link-text">Locatie noua pentru mese</span>
        </a>
      </li>
          </ul>
        </li>
       
    </ul>
<script>

// cea mai buna metoda de folosire a onclickului pt ca functioneaza si dupa ce folosesti ajax
		$(document).ready(function() {
		    $(document).on('click', '#vanzarile_personalului', function(){

$('#raport').modal('show');

var vanzarile_personalului_data_start=document.getElementById('vanzarile_personalului_data_start').value;
var vanzarile_personalului_data_stop=document.getElementById('vanzarile_personalului_data_stop').value;
var vanzarile_personalului_timp_start=document.getElementById('vanzarile_personalului_timp_start').value;
var vanzarile_personalului_timp_stop=document.getElementById('vanzarile_personalului_timp_stop').value;
    $("#detalii_raport").load("load_raport.php?" + $.param({
        data_start: vanzarile_personalului_data_start,
         data_stop: vanzarile_personalului_data_stop,
          timp_start: vanzarile_personalului_timp_start,
           timp_stop: vanzarile_personalului_timp_stop
    }));
});


	});
</script>
    <ul class="navbar-nav sidenav-toggler bg-dark">
      <li class="nav-item">
        <a class="nav-link text-center" id="sidenavToggler">
          <i class="fa fa-fw fa-angle-left"></i>
        </a>
      </li>
    </ul>
    <ul class="navbar-nav ml-auto">
     

      <?php 

    				$admin_em = $_SESSION['adminloggedin'] ?? null;
    $asql = "SELECT * FROM $tabel_final_admins WHERE admin_id = :admin_id LIMIT 1";
    $astmt = $pdo->prepare($asql);
    $astmt->execute(['admin_id' => $admin_em]);


while ($row = $astmt->fetch(PDO::FETCH_ASSOC)){  
       $admin_firstname=$row['admin_firstname'];
       $admin_lastname=$row['admin_lastname'];



} ?>

      <li id="user" style="margin-right:5em;" class="nav-item dropdown">
        <a class="nav-link dropdown-toggle mr-lg-2" id="messagesDropdown" href="#" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
          <i class="fa fa-fw fa-user"></i>
          
          <span class="indicator text-primary d-none d-lg-block">
            <i class="fa fa-fw fa-circle"></i>
          </span>
        </a>
        <div class="dropdown-menu" aria-labelledby="messagesDropdown">
          <h6 class="dropdown-header"><?php echo $admin_firstname ." ". $admin_lastname ;?></h6>
          
          <div class="dropdown-divider"></div>
          <a class="dropdown-item small" href="logout.php">Deconectare</a>
        </div>
      </li>
     
    </ul>
  </div>
</nav>
<style>/* width */
::-webkit-scrollbar {
width: 10px;
	background-color: #F5F5F5;
}

/* Track */
::-webkit-scrollbar-track {
 -webkit-box-shadow: inset 0 0 6px rgba(0,0,0,0.1);
	background-color: #F5F5F5;
	border-radius: 10px;
}

/* Handle */
::-webkit-scrollbar-thumb {
 	border-radius: 10px;
	background-image: -webkit-gradient(linear,
									   left bottom,
									   left top,
									   color-stop(0.44, rgb(122,153,217)),
									   color-stop(0.72, rgb(73,125,189)),
									   color-stop(0.86, rgb(28,58,148)));

}

/* Handle on hover */
::-webkit-scrollbar-thumb:hover {
  background: #555; 
}</style>


   <div id='raport' class="modal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Modal title</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body" id='detalii_raport'>


      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Inchide</button>
      </div>
    </div>
  </div>
</div>
