<?php
   include('session.php');
       $data_stoc=date("Y-m-d", strtotime($_SESSION['data_stoc']));
$gst=$_SESSION['gestiune'];
?>
<?php
    $_SESSION['data_stoc']=date("d-m-Y", strtotime($_SESSION['data_stoc']));
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
$this->Cell(111	,10,'Stoc la data '.$_SESSION['data_stoc'].' Gestiunea: '. $gest,'T,L,B',0,C);
$this->Cell(25	,10,'Pagina ' .$this->PageNo(),'1',1,C);//end of line
$this->Cell(111	,10,'Denumirea produsului','L,B',0,C);
$this->Cell(25	,10,'Stoc','1',1,C);//end of line

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
    $f_sql = "SELECT cod_p,den_p from $tabel_final_nomenclator where gestiune!='PF';";

}
else{
$f_sql = "SELECT cod_p,den_p from $tabel_final_nomenclator where gestiune='$gst';";}
$f_stmt = $pdo->prepare($f_sql);  
$f_stmt->execute(); 

$stoc=0;

while ($row = $f_stmt->fetch(PDO::FETCH_ASSOC)){ 

$den_p=$row['den_p'];
$cod_p=$row['cod_p'];

$f_sql2 = "SELECT * from $tabel_final_miscari where cod_p='$cod_p' and data<='$data_stoc' ORDER BY data ASC;";    
$f_stmt2 = $pdo->prepare($f_sql2);  
$f_stmt2->execute(); 
$pdf->SetFont('Arial','B',9);

while ($row = $f_stmt2->fetch(PDO::FETCH_ASSOC)){ 


$tip_miscare=$row['tip_miscare'];
$cantitate_misc=$row['cantitate_misc'];

if($tip_miscare=='I'){
    $stoc=($stoc*10000+$cantitate_misc*10000)/10000;

}
if($tip_miscare=='O'){

    $stoc=($stoc*10000-$cantitate_misc*10000)/10000;
}



}
$pdf->Cell(111	,10,$den_p,'L,B',0,C);
$pdf->Cell(25	,10,number_format($stoc,4),'1',1,C);//end of line
$stoc=0;
}









  

 
$pdf->Output();
?>

