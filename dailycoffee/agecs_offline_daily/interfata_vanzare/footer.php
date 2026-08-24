<!-- /.container-fluid-->
<!-- /.content-wrapper-->

<footer class="sticky-footer">
  <div class="container">
    <div class="text-center">
      <small>© 2019 AGECS</small><div id='ses'></div>
    </div>
  </div>
</footer>

<!-- Scroll to Top Button-->
<a class="scroll-to-top rounded" href="#page-top">
  <i class="fa fa-angle-up"></i>
</a>

<script src="vendor/jquery/jquery.min.js"></script>
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- Core plugin JavaScript-->
<script src="vendor/jquery-easing/jquery.easing.min.js"></script>
<!-- Custom scripts for all pages-->
<script src="js/sb-admin.min.js"></script>

  <link href="vendor/offline/select2/select2.min.css" rel="stylesheet" />
  <script src="vendor/offline/select2/select2.min.js"></script>

<script>

		$(document).ready(function() {
		    
 setInterval(function() {
        
		              $("#ses").load("session.php");
    }, 50000);
	

		});
		
		</script>
<script src="offline_sync_heartbeat.js"></script>
</div>
</body>

</html>
