<?php
   include('session.php');
    
    $gst=$_SESSION['gestiune'];
   $data_start=$_SESSION['data_start'];
        $data_end=$_SESSION['data_end'];
?>
<?php


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
$gst=$_SESSION['gestiune'];
if($gst=='MP'){
    
    $gest='Materii prime';
}
elseif($gst=='MR'){
    
    $gest='Marfuri';
}
elseif($gst=='MC'){
    
    $gest='Materiale consumabile';
}
elseif($gst=='MC2'){
    
    $gest='Materiale consumabile 2';
}
elseif($gst=='OB'){
    
    $gest='Obiecte de inventar';
}
elseif($gst=='MA'){
    
    $gest='Materiale auxiliare';
}
elseif($gst=='MJ'){
    
    $gest='Mijloace fixe ';
}
$this->SetFont('Arial','B',9);
$this->Cell(111	,10,'Vanzari '.$gest.' de la '.date("d-m-Y", strtotime($_SESSION['data_start'])).' pana la '.date("d-m-Y", strtotime($_SESSION['data_end'])),'T,L,B',0,C);
$this->Cell(25	,10,'Pagina ' .$this->PageNo(),'1',1,C);//end of line
$this->Cell(111	,10,'Denumirea produsului','L,B',0,C);
$this->Cell(25	,10,'Cantitate','1',1,C);//end of line

}

 
}
$pdf= new PDF('P','mm','A5');
$pdf->SetAutoPageBreak(true,25);
$pdf->AddPage();
$pdf->SetFont('Arial','B',7);

 
//set font to arial,bold, 9 pt






//CELL(width,height,text,border,end line,[align])
//end of line



if($gst=='toate'){
    $f_sql = "SELECT cod_p,den_p from $tabel_final_nomenclator;";

}
else{
$f_sql = "SELECT cod_p,den_p from $tabel_final_nomenclator where gestiune='$gst' ORDER BY den_p;";}
$f_stmt = $pdo->prepare($f_sql);  
$f_stmt->execute(); 

$stoc=0;

while ($row = $f_stmt->fetch(PDO::FETCH_ASSOC)){ 

$den_p=$row['den_p'];
$cod_p=$row['cod_p'];

$f_sql2 = "SELECT sum($tabel_final_det_note.cantitate) as vanzari from $tabel_final_det_note inner join $tabel_final_note on $tabel_final_note.nrbon=$tabel_final_det_note.nr_bon where $tabel_final_det_note.cod_p='$cod_p' and $tabel_final_note.data_bon BETWEEN '$data_start' AND '$data_end' and $tabel_final_note.locatie='1' GROUP BY cod_p;";    
$f_stmt2 = $pdo->prepare($f_sql2);  
$f_stmt2->execute(); 
$pdf->SetFont('Arial','B',9);

while ($row = $f_stmt2->fetch(PDO::FETCH_ASSOC)){ 


$tip_miscare=$row['tip_miscare'];
$stoc=$row['vanzari'];
}
if($stoc>0){
$pdf->Cell(111	,10,$den_p,'L,B',0,C);
$pdf->Cell(25	,10,$stoc,'1',1,C);
}
//end of line
$stoc=0;
}









  

 
$pdf->Output();
?>

