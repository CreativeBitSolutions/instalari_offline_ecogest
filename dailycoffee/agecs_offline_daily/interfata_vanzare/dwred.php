<?php 

session_start();

?>
<head>
  
  <!-- Bootstrap core CSS-->
 
    <!-- Bootstrap core JavaScript-->
  <script src="vendor/jquery/jquery.min.js"></script>
  <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <!-- Core plugin JavaScript-->
  <script src="vendor/jquery-easing/jquery.easing.min.js"></script>
  <!-- Custom fonts for this template-->
  <!-- Custom styles for this template-->
</head>

<button hidden id='cm' onclick="DownloadAndRedirect()">Click me</button>


<script type="text/javascript">
function DownloadAndRedirect()
{
   var DownloadURL = "casa_marcat.php";
   var RedirectURL = "creare_bon_simplu.php";
   var RedirectPauseSeconds = 0.5;
   location.href = DownloadURL;
   setTimeout("DoTheRedirect('"+RedirectURL+"')",parseInt(RedirectPauseSeconds*1000));
}
function DoTheRedirect(url) { window.location=url; }
</script>

<script>
$(document).ready(function() {
   $("#cm").trigger('click');
});
</script>