<?php
   include('session.php');
?>
<?php

$actualizare_cote_tva_sql = "SELECT id,cod_p,cota_tva from miscari_12 where cota_tva is NULL";    
$actualizare_cote_tva_stmt = $pdo->prepare($actualizare_cote_tva_sql);  
$actualizare_cote_tva_stmt->execute(); 

while ($row = $actualizare_cote_tva_stmt->fetch(PDO::FETCH_ASSOC)){ 
    $cota_din_miscari=$row['cota_tva'];
   $id=$row['id'];
    $produs_de_actualizat_cota=$row['cod_p'];
    $preluare_cota_sql = "SELECT cote_tva.cota from nomenclator_12 inner join cote_tva on nomenclator_12.cota_tva=cote_tva.id where nomenclator_12.cod_p='$produs_de_actualizat_cota'";    
$preluare_cota_stmt = $pdo->prepare($preluare_cota_sql);  
$preluare_cota_stmt->execute(); 
    while ($row_cota = $preluare_cota_stmt->fetch(PDO::FETCH_ASSOC)){ 

$cota_preluata=$row_cota['cota'];

}

  $actualizare_cota_sql = "UPDATE miscari_12 set cota_tva=$cota_preluata where id='$id'";    
$actualizare_cota_stmt= $pdo->prepare($actualizare_cota_sql);  
$actualizare_cota_stmt->execute(); 

}



    $cota_tva=$_SESSION['gestiune'];
   $data_start=$_SESSION['data_start'];
        $data_end=$_SESSION['data_end'];
   $data_end_sold_initial=$_SESSION['data_start'];

require('fpdf181/fpdf.php');

//A4 width:219mm
//default margin: 10 mm each side
//writable horizontal : 149-(10*2)=129mm
class PDF extends FPDF
{
    
// Page header
function Header()
{
    // Logo

$this->SetFont('Arial','B',9);
$this->Cell(50	,5,$_SESSION['den_ent'],'T,L,R',0,C);
$this->SetFont('Arial','B',15);
$this->Cell(120	,10,'Raport de gestiune (MR)','T,L',0,C);
$this->SetFont('Arial','B',9);
$this->Cell(15	,5,'Pagina','T,L,R',1,C);//end of line
$this->Cell(50	,5,'(Unitatea)','B,L,R',0,C);
$this->Cell(120	,5,'' ,'B,L',0,C);
$this->SetFont('Arial','B',9);
$this->Cell(15	,5,$this->PageNo(),'B,L,R',1,C);//end of line
$this->Cell(50	,5,'Restaurant '.$_SESSION['gestiune'].' % ','T,L',0,C);
$this->Cell(135	,5,'Perioada','T,L,R',1,C);
$this->Cell(50	,5,'','B,L,R',0,C);
$this->Cell(135	,5,date("d.m.Y", strtotime($_SESSION['data_start'])).' - '.date("d.m.Y", strtotime($_SESSION['data_end'])),'B,R',1,C);//end of line

$this->Cell(50  ,10,'Document',1,0,C);
$this->Cell(45  ,10,'Intrari','T,L',0,C);
$this->Cell(45  ,10,'Iesiri','T,L',0,C);
$this->Cell(45  ,10,'Sold','T,L,R',1,C);
$this->Cell(20  ,5,'Data',1,0,C);
$this->Cell(20  ,5,'Numar',1,0,C);
$this->Cell(10  ,5,'Fel',1,0,C);
$this->Cell(45  ,5,'','B,L',0,C);
$this->Cell(45  ,5,'','B,L',0,C);
$this->Cell(45  ,5,'','R,B,L',1,C);

}

}
$pdf= new PDF('P','mm','A4');
$pdf->SetAutoPageBreak(true,25);
$pdf->AddPage();
$pdf->SetFont('Arial','B',8);

//set font to arial,bold, 9 pt

$f_sql = "SELECT min(miscari_12.data) as prima_oara from miscari_12 inner join nomenclator_12 on nomenclator_12.cod_p=miscari_12.cod_p where miscari_12.fel_doc='NIR' and miscari_12.cota_tva='$cota_tva' and miscari_12.data<'$data_start' and nomenclator_12.gestiune='MR'  group by miscari_12.nr_doc order by miscari_12.data,miscari_12.id LIMIT 1;";    
$f_stmt = $pdo->prepare($f_sql);  
$f_stmt->execute(); 


while ($row = $f_stmt->fetch(PDO::FETCH_ASSOC)){
    $prima_oara=$row['prima_oara'];
    
}


$valoare=0;
// sold initial 


while ($prima_oara<$data_end_sold_initial){


// afisare intrari

$f_sql = "SELECT miscari_12.id,miscari_12.data,miscari_12.fel_doc,miscari_12.nr_doc from miscari_12 inner join nomenclator_12 on nomenclator_12.cod_p=miscari_12.cod_p  where miscari_12.fel_doc='NIR' and miscari_12.cota_tva='$cota_tva' and miscari_12.data='$prima_oara' and nomenclator_12.gestiune='MR'  group by miscari_12.nr_doc order by miscari_12.data,miscari_12.id;";    
$f_stmt = $pdo->prepare($f_sql);  
$f_stmt->execute(); 



while ($row = $f_stmt->fetch(PDO::FETCH_ASSOC)){ 
$data=$row['data'];
$tip_miscare=$row['tip_miscare'];
$fel_doc=$row['fel_doc'];
$nr_doc=$row['nr_doc'];
$cod_inchidere=$row['cod_inchidere'];
$f_sql2 = "SELECT sum(miscari_12.cantitate_misc*miscari_12.pret_vanzare) as valoare_intrare_nir from miscari_12 where fel_doc='NIR' and nr_doc='$nr_doc';";    
$f_stmt2 = $pdo->prepare($f_sql2);  
$f_stmt2->execute(); 

while ($row = $f_stmt2->fetch(PDO::FETCH_ASSOC)){ 
    $valoare_intrare_nir=$row['valoare_intrare_nir'];
}


    $valoare=($valoare*10000+$valoare_intrare_nir*10000)/10000;







}


// afisare iesiri


$f_sql = "SELECT sum(miscari_12.cantitate_misc*miscari_12.pret_vanzare) as valoare_iesire,note_12.cod_inchidere from miscari_12 inner join nomenclator_12 on nomenclator_12.cod_p=miscari_12.cod_p inner join note_12 on miscari_12.nr_doc=note_12.nrbon  where miscari_12.fel_doc='BF' and miscari_12.cota_tva='$cota_tva' and miscari_12.data='$prima_oara' and nomenclator_12.gestiune='MR'  group by note_12.cod_inchidere;";    
$f_stmt = $pdo->prepare($f_sql);  
$f_stmt->execute(); 

while ($row = $f_stmt->fetch(PDO::FETCH_ASSOC)){ 


$cod_inchidere=$row['cod_inchidere'];

$valoare_iesire=$row['valoare_iesire'];

    $valoare=($valoare*10000-$valoare_iesire*10000)/10000;

}

// afisare iesiri

$prima_oara = date('Y-m-d', strtotime($prima_oara . ' +1 day'));

}

$pdf->Cell(140  ,5,'Sold initial',1,0,C);
$pdf->Cell(45  ,5,number_format($valoare,4),1,1,C);
// sold initial

while ($data_start<=$data_end){


// afisare intrari

$f_sql = "SELECT miscari_12.id,miscari_12.data,miscari_12.fel_doc,miscari_12.nr_doc from miscari_12 inner join nomenclator_12 on nomenclator_12.cod_p=miscari_12.cod_p  where miscari_12.fel_doc='NIR' and miscari_12.cota_tva='$cota_tva' and miscari_12.data='$data_start' and nomenclator_12.gestiune='MR'  group by miscari_12.nr_doc order by miscari_12.data,miscari_12.id;";    
$f_stmt = $pdo->prepare($f_sql);  
$f_stmt->execute(); 


while ($row = $f_stmt->fetch(PDO::FETCH_ASSOC)){ 
$data=$row['data'];
$tip_miscare=$row['tip_miscare'];
$fel_doc=$row['fel_doc'];
$nr_doc=$row['nr_doc'];
$cod_inchidere=$row['cod_inchidere'];
$f_sql2 = "SELECT sum(miscari_12.cantitate_misc*miscari_12.pret_vanzare) as valoare_intrare_nir from miscari_12 where fel_doc='NIR' and nr_doc='$nr_doc';";    
$f_stmt2 = $pdo->prepare($f_sql2);  
$f_stmt2->execute(); 

while ($row = $f_stmt2->fetch(PDO::FETCH_ASSOC)){ 
    $valoare_intrare_nir=$row['valoare_intrare_nir'];
}

$pdf->Cell(20  ,5,$data,1,0,C);
$pdf->Cell(20  ,5,$nr_doc,1,0,C);
$pdf->Cell(10  ,5,$fel_doc,1,0,C);



$pdf->Cell(45  ,5,$valoare_intrare_nir,'B,L',0,C);
$pdf->Cell(45  ,5,'','B,L',0,C);
    $valoare=($valoare*10000+$valoare_intrare_nir*10000)/10000;







$pdf->Cell(45  ,5,number_format($valoare,4),1,1,C);
}


// afisare iesiri


$f_sql = "SELECT sum(miscari_12.cantitate_misc*miscari_12.pret_vanzare) as valoare_iesire,note_12.cod_inchidere from miscari_12 inner join nomenclator_12 on nomenclator_12.cod_p=miscari_12.cod_p inner join note_12 on miscari_12.nr_doc=note_12.nrbon  where miscari_12.fel_doc='BF' and miscari_12.cota_tva='$cota_tva' and miscari_12.data='$data_start' and nomenclator_12.gestiune='MR'  group by note_12.cod_inchidere;";    
$f_stmt = $pdo->prepare($f_sql);  
$f_stmt->execute(); 

while ($row = $f_stmt->fetch(PDO::FETCH_ASSOC)){ 


$cod_inchidere=$row['cod_inchidere'];

$valoare_iesire=$row['valoare_iesire'];
$pdf->Cell(20  ,5,$data_start,1,0,C);
$pdf->Cell(20  ,5,$cod_inchidere,1,0,C);
$pdf->Cell(10  ,5,'RZ',1,0,C);



$pdf->Cell(45  ,5,'','B,L',0,C);
$pdf->Cell(45  ,5,$valoare_iesire,'B,L',0,C);
    $valoare=($valoare*10000-$valoare_iesire*10000)/10000;







$pdf->Cell(45  ,5,number_format($valoare,4),1,1,C);
}

// afisare iesiri




// afisare iesiri

//am modificat miscari_12.pret_vanzare in pu update 02.02.2022
$f_sql = "SELECT sum(miscari_12.cantitate_misc*miscari_12.pu) as valoare_iesire,bonuri_consum_12.nr_bon from miscari_12 inner join nomenclator_12 on nomenclator_12.cod_p=miscari_12.cod_p inner join bonuri_consum_12 on miscari_12.nr_doc=bonuri_consum_12.nr_bon  where miscari_12.fel_doc='BCF' and miscari_12.cota_tva='$cota_tva' and miscari_12.data='$data_start' and nomenclator_12.gestiune='MR'  group by bonuri_consum_12.nr_bon;";    
$f_stmt = $pdo->prepare($f_sql);  
$f_stmt->execute(); 

while ($row = $f_stmt->fetch(PDO::FETCH_ASSOC)){ 


$cod_inchidere=$row['nr_bon'];

$valoare_iesire=$row['valoare_iesire'];
$pdf->Cell(20  ,5,$data_start,1,0,C);
$pdf->Cell(20  ,5,$cod_inchidere,1,0,C);
$pdf->Cell(10  ,5,'BCF',1,0,C);



$pdf->Cell(45  ,5,'','B,L',0,C);
$pdf->Cell(45  ,5,$valoare_iesire,'B,L',0,C);
    $valoare=($valoare*10000-$valoare_iesire*10000)/10000;







$pdf->Cell(45  ,5,number_format($valoare,4),1,1,C);
}

// afisare iesiri






$data_start = date('Y-m-d', strtotime($data_start . ' +1 day'));



}
  

 
$pdf->Output();
?>

