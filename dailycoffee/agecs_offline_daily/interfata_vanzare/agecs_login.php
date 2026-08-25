<?php
include('db.php');
date_default_timezone_set("Europe/Bucharest");
session_start();

$_config = offline_config_all();
$_SESSION['client_id'] = (int)$_config['client_id'];
$_SESSION['cod_locatie'] = (int)$_config['cod_locatie_default'];

                        if (!isset($_SESSION['adminloggedin'])) {
                                $_SESSION['adminloggedin'] = 1;
                            }

	
		if(isset($_SESSION['nr_bon']))
	{ 
	unset($_SESSION['nr_bon']);    
	    
	}
	$cust_id = $_SESSION['client_id'];
	?>
<!DOCTYPE html>
<html lang="ro">

<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="description" content="">
  <meta name="author" content="">
  <title>Conectare operator</title>
  <!-- Bootstrap core CSS-->
  <link href="vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">

  <!-- Custom fonts for this template-->
  <!-- Custom styles for this template-->
  <link href="css/sb-admin.css" rel="stylesheet">
  <link href="css/offline-login.css" rel="stylesheet">
</head>

<body class="bg-dark offline-login-page">
  <div class="container">
          <div class="row">

    <div style='float:left;display: inline-block;
' class="card card-login col-xs-6 mx-auto mt-5">
      <div class="card-header">

    <div class="login-heading">
        <div>
            <span class="login-kicker">AGECS POS OFFLINE</span>
            <h1>Conectare operator</h1>
        </div>
        <span class="location-badge">Locatia <?php echo (int)$_SESSION['cod_locatie'];?></span>
    </div>

		<section class="operators-section">
            <div class="section-heading">
                <span>Acces vanzare</span>
                <h2>Alege operatorul</h2>
            </div>
            <div class="operators-grid">
    <style>label{font-weight:bold;}
   
    </style>


<hr>
<style>

.my_button:focus {
  border-color:blue;
}
</style>

    <!-- Bootstrap core JavaScript-->
  <script src="vendor/jquery/jquery.min.js"></script>
  <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <!-- Core plugin JavaScript-->
  <script src="vendor/jquery-easing/jquery.easing.min.js"></script>
  <script src="offline_sync_heartbeat.js"></script>
  
  <script>
    document.addEventListener("DOMContentLoaded", function() {
        document.querySelectorAll('.blocked').forEach(button => {
            button.addEventListener('click', function(event) {
                event.preventDefault();
                event.stopPropagation();
            });
        });

    });
    $(document).ready(function() {
        
        
        
        
        
        $('.my_button').click(function() {
             $("#c").css("display", "inline-block");
             $("#pinBackdrop").css("display", "block");
            var operator = $(this).val();
$('[name=oper]').val(operator);
             document.getElementById("calc_result").focus();
 var str= $("#calc_result").val();
    var position = document.getElementById('calc_result').selectionStart-1;

    str = str.substr(10, position) + '' + str.substr(position + 1);
    $("#calc_result").val(str);
            
            
        });
    });
    

</script>
<?php 

$tabel_admins = $tabel_final_admins;
$cod_locatie=$_SESSION['cod_locatie'];
if (($_SESSION['d'] ?? 0) == 1) {
                $dsql = "SELECT * FROM $tabel_admins WHERE locatie='$cod_locatie' AND lucreaza_la='magazin'";
    } else {
                $dsql = "SELECT * FROM $tabel_admins WHERE locatie='$cod_locatie' AND lucreaza_la='magazin'";
    }
    

$dstmt = $pdo->prepare($dsql);
$dstmt->execute(); 

while ($row = $dstmt->fetch(PDO::FETCH_ASSOC)) {
    $id = $row['admin_id'];
    $admin_firstname = $row['admin_firstname'];
    $admin_lastname = $row['admin_lastname'];
    $nr_tableta = $row['nr_tableta'];
    $rank = $row['rank'];
    $conectat = $row['conectat']; // Preluăm starea de conectare

    // Dacă utilizatorul este conectat, adăugăm `disabled`
    $disabled = ($conectat == 1) ? "disabled" : "";
    $mesajConectat = ($conectat == 1) ? "<p style='color: red; font-weight: bold;'>UTILIZATOR DEJA CONECTAT</p>" : "";

    if ($rank == "operator") {
        echo "
        <figure>
            <button type='button' value='$id' class='my_button' $disabled>
                <img width='90px' height='90px' src='images/operator1.jpg' />
            </button>
            <figcaption style='text-align:center;'>$admin_firstname $admin_lastname</figcaption>
            $mesajConectat
        </figure>";
    } elseif ($rank == "bucatar") {
        echo "
        <figure>
            <button type='button' value='$id' class='my_button' $disabled>
                <img width='90px' height='90px' src='images/chef.jpg' />
            </button>
            <figcaption style='text-align:center;'>$admin_firstname $admin_lastname</figcaption>
            $mesajConectat
        </figure>";
    } elseif ($rank == "ospatar") {
        echo "
        <figure>
            <button type='button' value='$id' class='my_button' $disabled>
                <img width='90px' height='90px' src='images/waiter.png' />
            </button>
            <figcaption style='text-align:center;'>$admin_firstname $admin_lastname</figcaption>
            $mesajConectat
        </figure>";
    } elseif ($rank == "barman") {
        echo "
        <figure>
            <button type='button' value='$id' class='my_button' $disabled>
                <img width='90px' height='90px' src='images/barman.png' />
            </button>
            <figcaption style='text-align:center;'>$admin_firstname $admin_lastname</figcaption>
            $mesajConectat
        </figure>";
    } elseif ($rank == "client") {
        echo "
        <figure>
            <button type='button' value='$id' class='my_button' $disabled>
                <img width='90px' height='90px' src='images/ipad.png' />
            </button>
            <figcaption style='text-align:center;'>Tableta $nr_tableta</figcaption>
            $mesajConectat
        </figure>";
                }
}  
?>

	</div>
    </section>
    <div class="buttons">
        <span class="actions-label">Export si administrare locala</span>
        <div class="sync-actions">
            <a class="button2 export-button" href="export_vanzari_offline.php" title="Deschide exportul manual de vanzari in format XML sau SQL">DESCARCA EXPORT XML / SQL</a>
            <a class="button2 license-button" href="offline_license_check.php" title="Verifica licenta aplicatiei offline">VERIFICA LICENTA</a>
            <a class="button2 cleanup-button" href="curatare_date_locale.php" title="Deschide preview-ul pentru curatarea bazei locale">CURATARE DATE LOCALE</a>
        </div>
    </div>
    <?php include __DIR__ . '/offline_pending_closures_notice.php'; ?>
<form method="POST" action="admin_logincheck.php">
	<input hidden type="text" value="This is some text" name="oper"  />

<br />
<style>
    .my_button{background:white}
.active{  border-color:blue;
}
.inactive{background:white}
    
</style>

<script>
    
    $(document).ready(function(){
  $('.my_button').click(function(){
    $('.my_button').removeClass('active').addClass('inactive');
     $(this).removeClass('inactive').addClass('active');
   
      
  });
})
</script>
      <div class="card-body">

	<!-- the following form sends the login data to file admin_logincheck.php for processing-->
		<?php
	include 'db.php';
	?>
<!DOCTYPE html>
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
        width:100%;
        height:100%;
        font-size:3em;
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


<style>
.buttons {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  text-align: center;
  margin-left: auto;
  margin-right: auto;
}

.login-location-title {
  display: block;
  line-height: 1.2;
}

.products-sync-notice {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-wrap: wrap;
  gap: 10px;
  max-width: 100%;
  padding: 9px 12px;
  border: 1px solid #ffe08a;
  border-radius: 8px;
  background: #fff3cd;
  color: #664d03;
  font-size: 0.95rem;
  font-weight: 700;
  line-height: 1.35;
}

.products-sync-notice .products-sync-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 250px;
  min-height: 38px;
  width: auto;
  height: auto;
  margin: 0;
  padding: 8px 14px;
  border: 0;
  border-radius: 8px;
  background: #198754;
  box-shadow: none;
  color: #fff;
  font-size: 0.95rem;
  font-weight: 700;
  line-height: 1.2;
  text-decoration: none;
}

.products-sync-notice .products-sync-btn:hover {
  background: #146c43;
  color: #fff;
  text-decoration: none;
}

.sync-actions {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  flex-wrap: wrap;
  gap: 12px;
  width: 100%;
}

a {
  text-decoration: none;
  color: white;
  width: 200px;
  height: 100px;
  background: #F2385A;
  position: relative;
  margin: 30px;
  padding: 16px;
  font-size: 2em;
  border-radius: 10px;
  box-shadow: 0px 15px 0px 0px #f02046, 0px 0px 20px 0px #bbb;
  transition: all 0.2s;
}

a:active {
  box-shadow: 0px 7px 0px 0px #f02046;
  text-decoration:none;
}
a:hover{color:red;}

.button1 {
  background-color: #F2385A;
}

.button2 {
  background-color: #3498DB;
  box-shadow: 0px 15px 0px 0px #258cd1;
      text-decoration:none;

}

.buttons .button2 {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 280px;
  min-height: 44px;
  width: auto;
  height: auto;
  margin: 0;
  padding: 9px 18px;
  color: #fff;
  font-size: 1em;
  line-height: 1.3;
  text-align: center;
  text-decoration: none;
}

.buttons .button2:hover {
  color: #fff;
  filter: brightness(1.05);
}

.button2:active {
  box-shadow: 0px 7px 0px 0px #258cd1;
    text-decoration:none;

}

.sync-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 280px;
  min-height: 44px;
  width: auto;
  height: auto;
  margin: 0;
  padding: 9px 18px;
  border: 0;
  border-radius: 10px;
  background: linear-gradient(180deg, #2ecc71 0%, #22a85a 100%);
  color: #fff;
  font-size: 1em;
  font-weight: 700;
  line-height: 1.3;
  text-align: center;
  text-decoration: none;
  box-shadow: 0px 12px 0px 0px #168246, 0px 8px 18px rgba(0, 0, 0, 0.18);
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.15s ease, filter 0.15s ease;
}

.sync-button:hover {
  filter: brightness(1.05);
}

.sync-button:active {
  transform: translateY(5px);
  box-shadow: 0px 7px 0px 0px #168246, 0px 6px 14px rgba(0, 0, 0, 0.16);
}

.sync-button:focus {
  outline: none;
  box-shadow: 0px 12px 0px 0px #168246, 0px 8px 18px rgba(0, 0, 0, 0.18), 0 0 0 3px rgba(46, 204, 113, 0.35);
}

.sync-button:disabled {
  filter: grayscale(0.35);
  opacity: 0.75;
  cursor: wait;
}

.sync-status {
  display: block;
  max-width: 100%;
  margin-top: 2px;
  font-size: 0.9em;
  font-weight: 700;
  line-height: 1.35;
  color: #475569;
}

</style>
        
 <h4 style="color:red" align="center">
				<?php
			
		// display an error message if admin_logincheck fails to find the credentials in admins table	
echo isset($_SESSION['error']) ? $_SESSION['error'] : '';

			
			?>
</h4>   
      </div>
    </div>
  </div>
  
  
  
  
   <div id="pinBackdrop" class="pin-backdrop" style="display:none;"></div>
   <div id='c' style="display:none;" class="card card-login pin-card col-xs-6 mx-auto mt-5" role="dialog" aria-modal="true" aria-labelledby="pinTitle">
      <div class="card-header">
      <div class="pin-heading">
          <div>
              <span>Operator selectat</span>
              <h2 id="pinTitle">Introdu codul PIN</h2>
          </div>
          <button type="button" class="pin-close" id="pinClose" title="Inchide tastatura" aria-label="Inchide tastatura">&times;</button>
      </div>
          
      <div>
<table class="calculator"  id="calc">
            <tr>
                <td colspan="3" class="calc_td_result">
                    <input type="password"  name="calc_result" maxlength='10' id="calc_result" class="calc_result" onkeydown="javascript:key_detect_calc('calc',event);" />
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
                <td style="text-align:center;" colspan="3" class="calc_td_btn">
                        <input type="submit" style=" color:green;" class="calc_btn" name="continua" value="Continuă &#10004"/>
                </td>
            </tr>
        </table></form></div>
        <script type="text/javascript">
                document.getElementById('calc').onload=init_calc('calc');
                document.getElementById('pinClose').addEventListener('click', function() {
                    document.getElementById('c').style.display = 'none';
                    document.getElementById('pinBackdrop').style.display = 'none';
                    document.querySelectorAll('.my_button').forEach(function(button) {
                        button.classList.remove('active');
                    });
                });
        </script>
        
 

          
          </div>
          
          </div>
  
  
  
  
  
  
  
  </div>
  
</div>










</body>
</html>
