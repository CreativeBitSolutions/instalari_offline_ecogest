<?php

include 'database_connection.php';

$title = 'Categorii';

include 'header.php';

?>
<!-- Breadcrumbs-->


  

<!-- Example DataTables Card-->
<div class="card mb-3">
<div class="card-header">
<i class="fa fa-tags"></i> Creare monetar</div>
<div class="card-body">
    
<?php  


	date_default_timezone_set('UTC+2');
		date_default_timezone_set("Europe/Bucharest");
 $adm_id=$_SESSION['admin_id'];
$dsql = "SELECT * FROM $tabel_final_admins where admin_id='$adm_id'";
$dstmt = $pdo->prepare($dsql);
$dstmt->execute(); 
while ($row = $dstmt->fetch(PDO::FETCH_ASSOC)){
$admin_firstname=$row['admin_firstname'];
$admin_lastname=$row['admin_lastname'];
}
    $ccom_sql = "SELECT cod_monetar FROM $tabel_final_monetar where status='S'";    
$ccom_stmt = $pdo->prepare($ccom_sql);  
$ccom_stmt->execute(); 
 $count=$ccom_stmt->rowCount();
         if($count == 0) {
                $insql="insert into $tabel_final_monetar(operator) values('$adm_id')"; 	 

try{
$pdo->exec($insql) or die(print_r($pdo->errorInfo(), true));   

    // afiseaza un mesaj de succes 

}catch(PDOException $e)
    {
    echo $insql . "<br>" . $e->getMessage();
    }
    
    $crccom_sql = "SELECT max(cod_monetar) as nrb FROM $tabel_final_monetar";    
$crccom_stmt = $pdo->prepare($crccom_sql);  
$crccom_stmt->execute(); 
while ($row = $crccom_stmt->fetch(PDO::FETCH_ASSOC)){ 
    $cod_monetar=$row['nrb'];
}



         }

        // If result matched $myusername and $mypassword, the result table's number of rows must be 1 row
	// if the table has 1 rows redirect the user to admin_index.php
	
   $cccom_sql = "SELECT max(cod_monetar) as nrb FROM $tabel_final_monetar";    
$cccom_stmt = $pdo->prepare($cccom_sql);  
$cccom_stmt->execute(); 
while ($row = $cccom_stmt->fetch(PDO::FETCH_ASSOC)){ 
    $cod_monetar=$row['nrb'];
}

?>
	
  <!-- Bootstrap core CSS-->
    <!-- Bootstrap core JavaScript-->
  <script src="vendor/jquery/jquery.min.js"></script>
  <!-- Core plugin JavaScript-->
  <script src="vendor/jquery-easing/jquery.easing.min.js"></script>
  <!-- Custom fonts for this template-->
  <!-- Custom styles for this template-->

<!-- about -->

<style>.table-bordered{
text-align:center;
}
/* Style the tab */
.tab {
overflow: hidden;
border: 1px solid #ccc;
background-color: #343a40;
margin-top:2em;
}

/* Style the buttons inside the tab */
.tab button {
background-color: #343a40;
float: left;
border: none;
outline: none;
cursor: pointer;
padding: 14px 16px;
transition: 0.3s;
font-size: 17px;
color:white;
}

/* Change background color of buttons on hover */
.tab button:hover {
background-color: #007bff;
}

/* Create an active/current tablink class */
.tab button.active {
background-color: #007bff;
}

/* Style the tab content */
.tabcontent {
display: none;
padding: 6px 12px;
border: 1px solid #ccc;
border-top: none;
}

.paymentcontent {
display: none;
padding: 6px 12px;
border: 1px solid #ccc;
border-top: none;
}
</style>
</style>
		
 <div>     
    <div class="container-fluid">



<script>
function openCity(evt, cityName) {

var i, tabcontent, tablinks;
tabcontent = document.getElementsByClassName("tabcontent");
for (i = 0; i < tabcontent.length; i++) {
tabcontent[i].style.display = "none";
}
tablinks = document.getElementsByClassName("tablinks");
for (i = 0; i < tablinks.length; i++) {
tablinks[i].className = tablinks[i].className.replace(" active", "");
}
document.getElementById(cityName).style.display = "block";
evt.currentTarget.className += " active";
}


function openPaymentMethod(evt, cityName) {
var i, paymentcontent, tablinks;
paymentcontent = document.getElementsByClassName("paymentcontent");
for (i = 0; i < paymentcontent.length; i++) {
paymentcontent[i].style.display = "none";
}
tablinks = document.getElementsByClassName("tablinks");
for (i = 0; i < tablinks.length; i++) {
tablinks[i].className = tablinks[i].className.replace(" active", "");
}
document.getElementById(cityName).style.display = "block";
evt.currentTarget.className += " active";
}
function focusscanner(){
document.getElementById("cod_bare").focus();

    
}
</script>
	 <div class="card mb-3">

            


        			<div id="Mod_simplu" class="tabcontent" style="display:block;" >
         <style>
 

.one {
    width: 50%;
    height: auto;
    float: left;
}
.two {
    width:50%;
    float:right;
    height: auto;
}</style>			  
    <div class="one">
<table class='table table-bordered'>
 
 

  
  <?php
//afisare randuri NIR

$f_sql = "SELECT * from $tabel_final_det_monetar where cod_monetar='$cod_monetar';";    
$f_stmt = $pdo->prepare($f_sql);  
$f_stmt->execute(); 

while ($row = $f_stmt->fetch(PDO::FETCH_ASSOC)){ 
$id_inreg=$row['id_inreg'];
$valoare=$row['valoare'];
$cantitate=$row['cantitate'];
$total_valoare=$row['total_valoare'];



  echo "
   <tr>
    <td>$valoare lei</td>
    <td>X $cantitate</td>
	<td>$total_valoare</td>
	<td><form method='post'><input class='btn btn-primary btn-block' type='submit' value='Sterge' name='$id_inreg'></form></td>

  </tr>
  
  
  
  " 
  
  ;
  if (isset($_POST[$id_inreg])) {
					
					$sterg_sql="SELECT * from $tabel_final_det_monetar where $tabel_final_det_monetar.id_inreg = '$id_inreg' ";
					$sterg_f_stmt = $pdo->prepare($sterg_sql);  
$sterg_f_stmt->execute(); 

while ($row = $sterg_f_stmt->fetch(PDO::FETCH_ASSOC)){ 

$c_p=$row['cod_p'];
$cant_vand=$row['cantitate'];
}
					
					$sql="DELETE from $tabel_final_det_monetar WHERE $tabel_final_det_monetar.id_inreg = '$id_inreg';";
$pdo->exec($sql) or die(print_r($pdo->errorInfo(), true));

			printf("<script>location.href='creare_monetar.php'</script>");
				}
  }
  
  
 
  ?>
 <th colspan="2">Total</th>  

 
  
  <th >
 <?php 
 
 $f_tot_sql = "SELECT sum($tabel_final_det_monetar.total_valoare) as a from $tabel_final_det_monetar where cod_monetar='$cod_monetar'; ";    
$f_tot_stmt = $pdo->prepare($f_tot_sql);  
$f_tot_stmt->execute();

while ($row = $f_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_val_vz_cu_tva=$row['a'];
}
  echo $total_val_vz_cu_tva;
  

if(isset($_POST['finaliz_mon'])){
    
    
    

 $ora_bon = date("H:i:s", strtotime('+0 hours'));
 $data_bon = date("Y-m-d", strtotime('+0 hours'));

$fin_sql = "UPDATE $tabel_final_monetar SET operator='$adm_id',suma='$total_val_vz_cu_tva',data_monetar='$data_bon',ora_monetar='$ora_bon',status='F' WHERE cod_monetar='$cod_monetar';";    
	
	try{
$pdo->exec($fin_sql) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 
		  
}catch(PDOException $e)
    {
    echo $fin_sql . "<br>" . $e->getMessage();
    } 
 
	
	 

    $_SESSION['cod_monetar']=$cod_monetar;
printf("<script>location.href='listeaza_monetar.php'</script>");

	
}

  ?>
  </th>
  <th >LEI</th>  

  
  
</table></div>
 <div class="two">
<style>
.btn-group{
    margin:2px;
}
.btn-group button {
   
    cursor: pointer; /* Pointer/hand icon */
    float: left; /* Float the buttons side by side */
    margin:2px;
}

/* Clear floats (clearfix hack) */
.btn-group:after {
    content: "";
    clear: both;
    display: table;
}



/* Add a background color on hover */
.btn-group button:hover {
    background-color: #3e8e41;
}
.ban{
    width:100px;
    height:100px;
}
</style>

<div class="btn-group">
  <button value='1'  data-toggle="modal" data-target="#monetar" class='ban' style="width:180px;background: url('images/1leu.jpg') no-repeat top transparent;
"></button>
  <button value='5' style="width:180px;background: url('images/5lei.jpg') no-repeat top transparent;
" data-toggle="modal" data-target="#monetar" class='ban'></button></div>
<div class="btn-group">
  <button value='10' style="width:180px;background: url('images/10lei.jpg') no-repeat top transparent;
" data-toggle="modal" data-target="#monetar" class='ban'></button>

    <button value='50' data-toggle="modal" data-target="#monetar" class='ban' style="width:180px;background: url('images/50lei.jpg') no-repeat top transparent;
"></button>
  </div>
  <div class="btn-group">
      <button value='100' style="width:180px;background: url('images/100lei.jpg') no-repeat top transparent;
" data-toggle="modal" data-target="#monetar" class='ban'></button>
        <button value='200' style="width:180px;background: url('images/200lei.jpg') no-repeat top transparent;
" data-toggle="modal" data-target="#monetar" class='ban'></button>
</div><div class="btn-group" style="padding-left:20%">
<button style="width:180px;background: url('images/500lei.jpg') no-repeat top transparent;
" value='500' data-toggle="modal" data-target="#monetar" class='ban'></button></div>

</br>
<div class="btn-group">
 <tr> <button value='0.01' style="border-radius: 50%;background: url('images/1ban.jpg') no-repeat top transparent;
" data-toggle="modal" data-target="#monetar" class='ban'></button></tr>
  <tr><button value='0.05' data-toggle="modal" data-target="#monetar" class='ban' style="border-radius: 50%;background: url('images/5bani.jpg') no-repeat top transparent;"></button></tr>
  <button value='0.10' style="border-radius: 50%;background: url('images/10bani.jpg') no-repeat top transparent;
" data-toggle="modal" data-target="#monetar" class='ban'></button>
    <button style="border-radius: 50%;background: url('images/50bani.jpg') no-repeat top transparent;
" value='0.50' data-toggle="modal" data-target="#monetar" class='ban'></button>
    
       
</div>

</br>

   </br>
     </br>

  <form method="POST"><button class='btn btn-primary btn-block' type="submit" name="finaliz_mon" value="numerar">Finalizare Monetar</button></form>

</div>			  
			</div>	
			
			
       </div> 
       

<div class="modal fade" id="monetar" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
		  <div class="modal-dialog" role="document">
			<div class="modal-content">
			    
			  <div class="modal-header">
				<h4 class="modal-title" id="myModalLabel">Numărul de bancnote</h4>
								<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>

			  </div>
			  <div class="modal-body">
				        

	<style>.calculator
{
        width:300px;
        height:300px;
        background-color:#eeeeee;
        border:2px solid #CCCCCC;
        margin:auto;
        padding-left:5px;
        padding-bottom:5px;
}
.calculator td
{
        height:16.66%;
}
.calc_td_result
{
        text-align:center;
}
.calc_result
{
        width:90%;
        text-align:right;
}
.calc_td_calculs
{
        text-align:center;
}
.calc_calculs
{
        width:90%;
        text-align:left;
}
.calc_td_btn
{
        width:25%;
        height:100%;
}
.calc_btn
{
        width:90%;
        height:90%;
        font-size:20px;
}
 @media only screen and (max-width : 767px) {
.calculator {
padding-left:0;
margin-left:0;
width:200px;
        height:200px;
}

</style>

<script>calc_array = new Array();
var calcul=0;
var pas_ch=0;
function $id(id)
{
        return document.getElementById(id);
}
function f_calc(id,n)
{
        if(n=='ce')
        {
                init_calc(id);
        }
        else if(n=='=')
        {
                if(calc_array[id][0]!='=' && calc_array[id][1]!=1)
                {
                        eval('calcul='+calc_array[id][2]+calc_array[id][0]+calc_array[id][3]+';');
                        calc_array[id][0] = '=';
                        $id(id+'_result').value=calcul;
                        calc_array[id][2]=calcul;
                        calc_array[id][3]=0;
                }
        }
        else if(n=='+-')
        {
                $id(id+'_result').value=$id(id+'_result').value*(-1);
                if(calc_array[id][0]=='=')
                {
                        calc_array[id][2] = $id(id+'_result').value;
                        calc_array[id][3] = 0;
                }
                else
                {
                        calc_array[id][3] = $id(id+'_result').value;
                }
                pas_ch = 1;
        }
        else if(n=='nbs')
        {
                if($id(id+'_result').value<10 && $id(id+'_result').value>-10)
                {
                        $id(id+'_result').value=0;
                }
                else
                {
                        $id(id+'_result').value=$id(id+'_result').value.slice(0,$id(id+'_result').value.length-1);
                }
                if(calc_array[id][0]=='=')
                {
                        calc_array[id][2] = $id(id+'_result').value;
                        calc_array[id][3] = 0;
                }
                else
                {
                        calc_array[id][3] = $id(id+'_result').value;
                }
        }
        else
        {
                        if(calc_array[id][0]!='=' && calc_array[id][1]!=1)
                        {
                                eval('calcul='+calc_array[id][2]+calc_array[id][0]+calc_array[id][3]+';');
                                $id(id+'_result').value=calcul;
                                calc_array[id][2]=calcul;
                                calc_array[id][3]=0;
                        }
                        calc_array[id][0] = n;
        }
        if(pas_ch==0)
        {
                calc_array[id][1] = 1;
        }
        else
        {
                pas_ch=0;
        }
        document.getElementById(id+'_result').focus();
        return true;
}
function add_calc(id,n)
{
        if(calc_array[id][1]==1)
        {
                $id(id+'_result').value=n;
        }
        else
        {
                $id(id+'_result').value+=n;
        }
        if(calc_array[id][0]=='=')
        {
                calc_array[id][2] = $id(id+'_result').value;
                calc_array[id][3] = 0;
        }
        else
        {
                calc_array[id][3] = $id(id+'_result').value;
        }
        calc_array[id][1] = 0;
        document.getElementById(id+'_result').focus();
        return true;
}
function init_calc(id)
{
        $id(id+'_result').value=0;
        calc_array[id] = new Array('=',1,'0','0',0);
        document.getElementById(id+'_result').focus();
        return true;
}
</script>
<form method="POST">
<input hidden type="number" step='0.01' name='tip_ban'/>
<table class="calculator"  id="calc">
            <tr>
                <td colspan="3" class="calc_td_result">
                    <input type="number"  name="calc_result" id="calc_result" class="calc_result" onkeydown="javascript:key_detect_calc('calc',event);" />
                </td>
            </tr>
            <tr>
            </tr>
            <tr>
                <td class="calc_td_btn">
                        <input type="button" class="calc_btn" value="7" onclick="javascript:add_calc('calc',7);" />
                </td>
                <td class="calc_td_btn">
                        <input type="button" class="calc_btn" value="8" onclick="javascript:add_calc('calc',8);" />
                </td>
                <td class="calc_td_btn">
                        <input type="button" class="calc_btn" value="9" onclick="javascript:add_calc('calc',9);" />
                </td>
               
            </tr>
                        <tr>
                <td class="calc_td_btn">
                        <input type="button" class="calc_btn" value="4" onclick="javascript:add_calc('calc',4);" />
                </td>
                <td class="calc_td_btn">
                        <input type="button" class="calc_btn" value="5" onclick="javascript:add_calc('calc',5);" />
                </td>
                <td class="calc_td_btn">
                        <input type="button" class="calc_btn" value="6" onclick="javascript:add_calc('calc',6);" />
                </td>
           
            </tr>
            <tr>
                <td class="calc_td_btn">
                        <input type="button" class="calc_btn" value="1" onclick="javascript:add_calc('calc',1);" />
                </td>
                <td class="calc_td_btn">
                        <input type="button" class="calc_btn" value="2" onclick="javascript:add_calc('calc',2);" />
                </td>
                <td class="calc_td_btn">
                        <input type="button" class="calc_btn" value="3" onclick="javascript:add_calc('calc',3);" />
                </td>
               
            </tr>
            <tr>
                <td colspan="2" class="calc_td_btn">
                        <input type="button" class="calc_btn" value="0" onclick="javascript:add_calc('calc',0);" />
                </td>
                <td class="calc_td_btn">
                        <input type="button" class="calc_btn" value="&larr;" onclick="javascript:f_calc('calc','nbs');" />
                </td>
                </tr><tr><td></td></tr>
                <tr>
                <td style="text-align:center;padding-right:10px;" colspan="3" class="calc_td_btn">
                        <input type="submit" style=" color:green;" class="calc_btn" name="continua" value="Continuă &#10004"/>
                </td>
            </tr>
        </table></form>
        <?php 
        
        if(isset($_POST['continua'])){
            
            $val_ban=$_POST['tip_ban'];
            $cantitate=$_POST['calc_result'];
$total_valoare=$val_ban*$cantitate;
            #sa faci sa adauge in det_monetar si codul monetarului
            
            	
$psql3 = "SELECT id_inreg from $tabel_final_det_monetar where cod_monetar='$cod_monetar' and valoare='$val_ban';";    
$pstmt3 = $pdo->prepare($psql3);  
$pstmt3->execute(); 
$prodcount=$pstmt3->rowCount();
if($prodcount==0){

$psql = "insert into $tabel_final_det_monetar(cod_monetar,cantitate,valoare,total_valoare) values('$cod_monetar','$cantitate','$val_ban','$total_valoare')";   }

elseif($prodcount>0){
    $psql = "UPDATE $tabel_final_det_monetar set cantitate=cantitate+'$cantitate',total_valoare=total_valoare+'$total_valoare' where cod_monetar='$cod_monetar' and valoare='$val_ban';"; 
    
}
            
	try{
$pdo->exec($psql) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 

}catch(PDOException $e)
    {
    echo $sql . "<br>" . $e->getMessage();
    } 
    
    			printf("<script>location.href='creare_monetar.php'</script>");
            
            
        }
        
        ?>
        
        <script type="text/javascript">
                document.getElementById('calc').onload=init_calc('calc');
        </script>
				
		
				
				
				  
				  

				  
				
			  

							  </div>			  
							  
							  
							  
							  
							  </div>

			</div>
		</div>
			<!--- Modal lista monetar amanate-->
			

			</div>
		
			
			
		  </div>
</div>
<!------ Modal lista monetar amanate-->

			
		  </div>
</div>


<!------ Mod Scanner-->



  
    
</div>
</div>


<?php include 'footer.php'; ?>

		  
		<script>
		
		$(document).ready(function() {
		    
		    
		$('.ban').on('click', function() {
var tip_ban = $(this).val();
$('[name=tip_ban]').val(tip_ban);
             document.getElementById("calc_result").focus();

});

	});

</script>