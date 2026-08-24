<?php 

if($_GET['p']!=''){
            session_start();

    if($_GET['p']=='magazin'){
        
        $_SESSION['platforma']='m';
        
    }
    elseif($_GET['p']=='restaurant')
    {
    $_SESSION['platforma']='r';

        
    }
        printf("<script>location.href='agecs_login.php'</script>");							

}

else{
    printf("<script>location.href='https://agecs.net/index.php'</script>");							

}
?>