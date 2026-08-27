<?php session_start();
include('bot_prod.php');
include('bot_most_ord.php');
include('bot_sec_ord.php');
include('bot_third_ord.php');?>
<!--Author: W3layouts
Author URL: http://w3layouts.com
License: Creative Commons Attribution 3.0 Unported
License URL: http://creativecommons.org/licenses/by/3.0/
-->
<!DOCTYPE HTML>
<html>
<head>
<title>M & B COMPUTERS SHOP </title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="keywords" content="Cruise Responsive web template, Bootstrap Web Templates, Flat Web Templates, Android Compatible web template, 
Smartphone Compatible web template, free webdesigns for Nokia, Samsung, LG, SonyEricsson, Motorola web design" />
<!-- css -->

<link href="css/styles.css" rel="stylesheet" type="text/css" media="all" />
<link href="css/style.css" rel="stylesheet" type="text/css" media="all" />

<!-- /css -->
<!-- js -->
<script src="js/modernizr.min.js"></script>
<!-- /js -->
</head>
<body>


<!-- topbar -->
<div class="topbar-w3ls">
	<div class="container">
		<a href="home_page.php" class="logo">
			<h1>
				M & B COMPUTERS SHOP
			</h1>
		</a>		
		<div class="top-agileits">
			<div class="top-w3l1">
				<span class="glyphicon glyphicon-phone-alt"></span> 	
				<p class="agile1">m&bcomputers@gmail.com</p>
				<p class="agile2">+40269258227</p>
			</div>		
			<div class="top-w3l2">
				<span class="glyphicon glyphicon-user"></span>
						
						<?php if(isset($_SESSION['custloggin'])) {
							  echo "
							<div class='dropdown'>
  <button class='dropbtn'> ".$_SESSION['custloggin']."
	  
	</button>
  <div class='dropdown-content'>
  	<a href='cos.php'>Coș!</a>
  	<a href='customer_orders.php'>Comenzile mele</a>

    <a href='edit_customer.php'>Modificare cont</a>
    
  </div>
  </div>";} ?>
				<style>
	h4{color:white;
	text-align:right;}
	a{color:white;}
	
	
	.dropbtn {
    background-color: transparent;
    color: white;
    padding: 12px;
    font-size: 12px;
    border: none;
    cursor: pointer;
	width:200px;
	height:20px;
		float:right;


}
.dropdown {
    position: relative;
    display: inline-block;
	float:right;
	
}

.dropdown-content {
    display: none;
    position: absolute;
    background-color: white;
    min-width: 180px;
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

.dropdown:hover .dropdown-content {
    display: block;
}

.menubtn {
    background-color: transparent;
    color: white;
    font-size: 16px;
    border: none;
    cursor: pointer;
	width:200px;
	height:50px;
	padding-top:10px;
	font-family:'FontAwesome';src:url('../fonts/fontawesome-webfont.eot?v=4.6.2')

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

				<?php
	$_SESSION['error']="";

		if (!isset($_SESSION['custloggin'])) {
			
		echo "<a class='agile1'><a href='customer_login.php'>Conectare </a><a href='/user_register.php'> Înregistrare</a></p> '";}
		
		else{
echo"<a href='customer_logout.php'>Deconectare</a>";

		    
		}
	?>
				
				<?php
				 unset($_SESSION['adminloggedin']);
		if (isset($_SESSION['custloggin']) && $_SESSION['custloggin'] == true) {
	?>
	
	
			
			
	

	   
	<?php
	}
	else {
		?>
		<form action = "database_connection4Cust.php" method = "POST">
			<a href="customer_login.php">Utilizator neconectat!</a>
		</form>
    <?php
	}
	?>
			</div>
			<div class="clearfix"></div>
		</div>
	</div>	
</div>
<!-- /topbar -->
<!-- navigation -->
<div class="navbar-wrapper">
    <div class="container">
		<nav class="navbar navbar-inverse navbar-static-top">
			<div class="container">
				<div class="navbar-header">
					<button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
						<span class="sr-only">Toggle navigation</span>
						<span class="icon-bar"></span>
						<span class="icon-bar"></span>
						<span class="icon-bar"></span>
					</button>
				</div>
				<div id="navbar" class="navbar-collapse collapse">
					<ul class="nav navbar-nav cl-effect-7">
						<li class="active"><a href="home_page.php" class="page-scroll">ACASĂ</a></li>
						<li><div class='menudown'>
 <a class="page-scroll"><button class='menubtn'>PRODUSE
	</button></a>
  <div class='menudown-content'>
  <?php  $dsql = "SELECT * from $tabel_final_categorii";    
$dstmt = $pdo->prepare($dsql);  
$dstmt->execute();  

while ($row = $dstmt->fetch(PDO::FETCH_ASSOC)){  
  	echo '<a href="customer_products.php?categoryId=' . $row['id_categorie'] . '">'.$row['den_categ'].'</a>';

	}
?>
  </div>
  </div></li>
							

						
						<li><a href="contact.php#bottomOfPage" class="page-scroll">Contact</a></li>
					</ul>
				</div>
			</div>
        </nav>
	</div>
</div>
<!-- /navigation -->

<!-- banner -->
<section class="banner-w3ls" style="height:400px">
		<div id="block" data-vide-bg="video/viking.jpg" data-vide-options="position: 0% 50%" style="height:400px">
			<div class="overlay" >
					
			</div>
		</div>	
</section>





        <style>
            @media only screen and (max-width : 540px) 
            {
                .chat-sidebar
                {
                    display: block !important;
                }
                
                .chat-popup
                {
                    display: block !important;
                }
            }
            
            
            
            
            
            .popup-box
            {
                display: none;
                position: fixed;
                bottom: 0px;
                right: 0px;
                height: 285px;
                background-color: rgb(237, 239, 244);
                width: 300px;
                border: 1px solid rgba(29, 49, 91, .3);
                z-index: 1000;
            }
            
            .popup-box .popup-head
            {
                background-color: black;
                padding: 5px;
                color: #FF4500;
                font-weight: bold;
                font-size: 14px;
                clear: both;
            }
            
            .popup-box .popup-head .popup-head-left
            {
                float: left;
            }
            
            .popup-box .popup-head .popup-head-right
            {
                float: right;
                opacity: 0.5;
            }
            
            .popup-box .popup-head .popup-head-right a
            {
                text-decoration: none;
                color: inherit;
            }
            
            .popup-box .popup-messages
            {
                height: 100%;
                width:100%;
                                background-color: black;

            }
            


        </style>
        
        <script>
            //this function can remove a array element.
            Array.remove = function(array, from, to) {
                var rest = array.slice((to || from) + 1 || array.length);
                array.length = from < 0 ? array.length + from : from;
                return array.push.apply(array, rest);
            };
        
            //this variable represents the total number of popups can be displayed according to the viewport width
            var total_popups = 0;
            
            //arrays of popups ids
            var popups = [];
        
            //this is used to close a popup
            function close_popup(id)
            {
                for(var iii = 0; iii < popups.length; iii++)
                {
                    if(id == popups[iii])
                    {
                        Array.remove(popups, iii);
                        
                        document.getElementById(id).style.display = "none";
                        
                        calculate_popups();
                        
                        return;
                    }
                }   
            }
        
            //displays the popups. Displays based on the maximum number of popups that can be displayed on the current viewport width
            function display_popups()
            {
                var right = 220;
                
                var iii = 0;
                for(iii; iii < total_popups; iii++)
                {
                    if(popups[iii] != undefined)
                    {
                        var element = document.getElementById(popups[iii]);
                        element.style.right = right + "px";
                        right = right + 320;
                        element.style.display = "block";
                    }
                }
                
                for(var jjj = iii; jjj < popups.length; jjj++)
                {
                    var element = document.getElementById(popups[jjj]);
                    element.style.display = "none";
                }
            }
            
            //creates markup for a new popup. Adds the id to popups array.
            function register_popup(id, name)
            {
                
                for(var iii = 0; iii < popups.length; iii++)
                {   
                    //already registered. Bring it to front.
                    if(id == popups[iii])
                    {
                        Array.remove(popups, iii);
                    
                        popups.unshift(id);
                        
                        calculate_popups();
                        
                        
                        return;
                    }
                }               
                
                var element = '<div class="popup-box chat-popup" id="'+ id +'">';
                element = element + '<div class="popup-head">';
                element = element + '<div class="popup-head-left">'+ name +'</div>';
                element = element + '<div class="popup-head-right"><a href="javascript:close_popup(\''+ id +'\');">&#10005;</a></div>';
                element = element + '<div style="clear: both"></div></div><div class="popup-messages">sdfsdf</div></div>';
                
                document.getElementsByTagName("body")[0].innerHTML = document.getElementsByTagName("body")[0].innerHTML + element;  
        
                popups.unshift(id);
                        
                calculate_popups();
                
            }
            
            //calculate the total number of popups suitable and then populate the toatal_popups variable.
            function calculate_popups()
            {
                var width = window.innerWidth;
                if(width < 540)
                {
                    total_popups = 0;
                }
                else
                {
                    width = width - 200;
                    //320 is width of a single popup box
                    total_popups = parseInt(width/320);
                }
                
                display_popups();
                
            }
            
            //recalculate when window is loaded and also when window is resized.
            window.addEventListener("resize", calculate_popups);
            window.addEventListener("load", calculate_popups);
            
            function myFunction() {
    var x = document.getElementById("minimize");
    var y = document.getElementById("maximize");

    if (x.style.display === "block") {
        x.style.display = "none";
        y.style.display = "block";

    } else {
        x.style.display = "block";
        y.style.display = "none";

    }
}
        </script>
        
       <div id="minimize" style="display:block;" class="popup-box chat-popup">
              <div class="popup-head">
                <div class="popup-head-left">CHAT </div>
               <div class="popup-head-right"><button style="background:transparent" onclick="myFunction()">&#8722;</button></div>
                <div style="clear: both"></div></div><div class="popup-messages"><?php include("bot/gui/jquery/multibot_gui_with_chatlog.php");?></div></div>
               
                <div style="display:none; height:30px" id="maximize" class="popup-box chat-popup">
              <div class="popup-head">
                <div class="popup-head-left">CHAT </div>
               <div class="popup-head-right"><button style="background:transparent" onclick="myFunction()">&#10010;</button></div>
                <div style="clear: both"></div></div></div>
                <!-- Pass username and display name to register popup -->
                
           
<!-- /banner -->
