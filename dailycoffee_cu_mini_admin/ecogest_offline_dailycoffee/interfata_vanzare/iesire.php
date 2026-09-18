<form id="myForm" action="https://agecs.net/index.php" method="post">
<?php

        $cust_id=$_GET['l'];
?>

<input type='number' hidden name='iesit' value="<?php echo $cust_id?>"/>
</form>
<script type="text/javascript">
    document.getElementById('myForm').submit();
</script>