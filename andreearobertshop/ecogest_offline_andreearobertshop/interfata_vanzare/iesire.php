<form id="myForm" action="agecs_login.php" method="get">
<?php

        $cust_id=$_GET['l'];
?>

<input type='number' hidden name='iesit' value="<?php echo $cust_id?>"/>
</form>
<script type="text/javascript">
    document.getElementById('myForm').submit();
</script>
