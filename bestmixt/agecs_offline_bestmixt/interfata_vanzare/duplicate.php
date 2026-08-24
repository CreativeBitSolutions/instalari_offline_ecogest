<?php
include('database_connection.php');

for ($i = 1; $i <= 267; $i++) {
   
   
    $adauga_inchidere = "insert into $tabel_final_stoc(cod_p,cantitate) values($i,'50');";

	 
	try{
$pdo->exec($adauga_inchidere) or die(print_r($pdo->errorInfo(), true));   
    // afiseaza un mesaj de succes 

}catch(PDOException $e)
    {
    echo $adauga_inchidere . "<br>" . $e->getMessage();
    } 
    
}

?>