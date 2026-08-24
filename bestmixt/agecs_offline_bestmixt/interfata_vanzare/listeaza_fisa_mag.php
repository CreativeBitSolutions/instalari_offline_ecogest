<?php
   include('session.php');
?>
<?php

$cod_p=$_SESSION['cod_p'];







 
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
$this->Cell(70	,10,'Fisa de magazie','T,L',0,C);
$this->SetFont('Arial','B',9);
$this->Cell(15	,5,'Pagina','T,L,R',1,C);//end of line
$this->Cell(50	,5,'(Unitatea)','B,L,R',0,C);
$this->Cell(70	,5,'','B,L',0,C);
$this->SetFont('Arial','B',7);
$this->Cell(15	,5,$this->PageNo(),'B,L,R',1,C);//end of line
$this->Cell(30	,5,'Magazie','T,L',0,C);
$this->Cell(105	,5,'Materialul(produsul),sort,calitate,marca,profil,dimensiune','T,L,R',1,C);
$this->Cell(30	,5,'','B,L,R',0,C);
$this->Cell(105	,5,$_SESSION['den_p'],'B,R',1,C);//end of line
$this->Cell(30	,5,'Cod','T,L',0,C);
$this->Cell(20	,5,'U/M','T,L',0,C);
$this->Cell(45	,5,'Pret unitar','T,L,R',0,C);
$this->Cell(20	,5,'','T,',0,C);
$this->Cell(20	,5,'','T,L,R',1,C);
$this->Cell(30	,5,$_SESSION['cod_p'],'L',0,C);
$this->Cell(20	,5,$_SESSION['um_p'],'T,L',0,C);
$this->Cell(45	,5,$_SESSION['pret_p'] .' LEI','T,L',0,C);
$this->Cell(20	,5,'','L',0,C);
$this->Cell(20	,5,'','L,R',1,C);
$this->Cell(39  ,10,'Document',1,0,C);
$this->Cell(24  ,10,'Intrari','T,L',0,C);
$this->Cell(24  ,10,'Iesiri','T,L',0,C);
$this->Cell(24  ,10,'Stoc','T,L',0,C);
$this->Cell(24  ,10,'Data si semnatura','T,L,R',1,C);
$this->Cell(15  ,5,'Data',1,0,C);
$this->Cell(13  ,5,'Numar',1,0,C);
$this->Cell(11  ,5,'Fel',1,0,C);
$this->Cell(24  ,5,'','B,L',0,C);
$this->Cell(24  ,5,'','B,L',0,C);
$this->Cell(24  ,5,'','B,L',0,C);
$this->Cell(24  ,5,' de control','B,L,R',1,C);
}

 
}
$pdf= new PDF('P','mm','A5');
$pdf->SetAutoPageBreak(true,25);
$pdf->AddPage();
$pdf->SetFont('Arial','B',7);

 
//set font to arial,bold, 9 pt






//CELL(width,height,text,border,end line,[align])
//end of line

$cod_p=$_SESSION['cod_p'];



$f_sql = "SELECT * from $tabel_final_miscari where cod_p='$cod_p' ORDER BY data;";    
$f_stmt = $pdo->prepare($f_sql);  
$f_stmt->execute(); 


$stoc=0.0000;
while ($row = $f_stmt->fetch(PDO::FETCH_ASSOC)){ 

$data=$row['data'];
$tip_miscare=$row['tip_miscare'];
$cantitate_misc=$row['cantitate_misc'];
$fel_doc=$row['fel_doc'];
$nr_doc=$row['nr_doc'];





$pdf->Cell(15  ,5,$data,1,0,C);
$pdf->Cell(13  ,5,$nr_doc,1,0,C);
$pdf->Cell(11  ,5,$fel_doc,1,0,C);
if($tip_miscare=='I'){
$pdf->Cell(24  ,5,$cantitate_misc,'B,L',0,C);
$pdf->Cell(24  ,5,'','B,L',0,C);
    $stoc=($stoc*10000+$cantitate_misc*10000)/10000;
}
if($tip_miscare=='O'){
$pdf->Cell(24  ,5,'','B,L',0,C);
$pdf->Cell(24  ,5,$cantitate_misc,'B,L',0,C);
    $stoc=($stoc*10000-$cantitate_misc*10000)/10000;
}
$pdf->Cell(24  ,5,number_format($stoc,4),'B,L',0,C);
$pdf->Cell(24  ,5,'','B,L,R',1,C);
}









  

 
$pdf->Output();
?>

