		    <?php 
		    include('session.php');
		    $c_bare=$_SESSION['c_bare'];
			echo "<center><img src='https://barcode.tec-it.com/barcode.ashx?data=$c_bare&code=Code128&multiplebarcodes=false&translate-esc=false&unit=Fit&dpi=96&imagetype=Gif&rotation=0&color=%23000000&bgcolor=%23ffffff&qunit=Mm&quiet=0' alt='Barcode Generator TEC-IT'/>";?>
			
			</br>
<button id="back" onclick="goBack()">Inapoi</button>

<script>
function goBack() {
    window.history.back();
}
</script>			<button id="show_button">Listeaza</button>
<script type="text/javascript">
    var button = document.getElementById('show_button')
    button.addEventListener('click',hideshow,false);

    function hideshow() {
        this.style.display = 'none';
                    var back = document.getElementById('back')
back.style.display='none';
            window.print();

    }   
</script>

</center>