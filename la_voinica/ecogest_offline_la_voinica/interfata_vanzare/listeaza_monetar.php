<?php
   include('session.php');
?>
<?php

$cod_monetar=$_SESSION['cod_monetar'];
$dsql = "SELECT * from $tabel_final_date_firma";    
$dstmt = $pdo->prepare($dsql);  
$dstmt->execute(); 
while ($row = $dstmt->fetch(PDO::FETCH_ASSOC)){ 
                      $den_ent=$row['den_ent'];
			             $sediu=$row['sediu']; 
						 $cod_fiscal_ent=$row['cod_fiscal']; 
						 $judet=$row['judet'];
}


$test_sql = "SELECT * FROM $tabel_final_monetar where cod_monetar='$cod_monetar'";    
$test_stm = $pdo->prepare($test_sql);  
$test_stm->execute(); 
while ($row = $test_stm->fetch(PDO::FETCH_ASSOC)){ 
$data_monetar=date("d-m-Y", strtotime($row['data_monetar']));
    $ora_monetar=$row['ora_monetar'];
    $suma=$row['suma'];

}
require('fpdf181/fpdf.php');

//A4 width:219mm
//default margin: 10 mm each side
//writable horizontal : 105-(10*2)=85mm

$pdf= new FPDF('P','mm',array(105,148));

$pdf->AddPage();
$x = $pdf->GetX();
$y = $pdf->GetY();
//set font to arial,bold, 9 pt
$pdf->SetFont('Arial','',9);
$pdf->MultiCell(55, 8, 'Unitatea '."\n".$den_ent."\n".'CIF: '.$cod_fiscal_ent."\n".'Domiciliul/Sediul: '.$sediu, 'L,T,B', 'L', 0, 0, '', '', true);
$pdf->SetXY($x + 55, $y);
$pdf->SetFont('Arial','B',12);
$pdf->MultiCell(30, 5.8, 'Monetar'."\n".'Seria: '.'..............'."\n".'Nr.: '.$cod_monetar."\n".'Data: '.$data_monetar, 'L,R,B', 'C', 0, 0, '', '', true);
$pdf->SetXY($x + 55, $y+34);
$pdf->SetFont('Arial','',9);
$pdf->MultiCell(30, 9, 'Judetul '.$judet, 'B', 'L', 0, 0, '', '', true);
$pdf->MultiCell(85, 8, '       Magazin.................'."                    ".'Casa..........................', 'R,B', 'L', 0, 0, '', '', true);

$pdf->MultiCell(85, 2, '', 'L', 'L', 0, 0, '', '', true);


$f_sql = "SELECT * from $tabel_final_det_monetar where cod_monetar='$cod_monetar' order by valoare desc;";    
$f_stmt = $pdo->prepare($f_sql);  
$f_stmt->execute(); 

while ($row = $f_stmt->fetch(PDO::FETCH_ASSOC)){ 

$cantitate=$row['cantitate'];
$valoare=$row['valoare'];
$total_valoare=$row['total_valoare'];
$pdf->Cell(25, 5, $cantitate.' ',1,0,'C');
$pdf->Cell(5, 5, ' '.' ',0,0,'C');


if($valoare==0.01){
    $pdf->Cell(15, 5, 'buc X 1','L,T,B',0);
$pdf->Cell(9, 5,' ban','B,R,T',0);

}
elseif($valoare==0.05)
{
        $pdf->Cell(15, 5, 'buc X 5','L,T,B',0);
$pdf->Cell(9, 5,' bani','B,R,T',0);
}
elseif($valoare==0.10)
{
        $pdf->Cell(15, 5, 'buc X 10','L,T,B',0);
$pdf->Cell(9, 5,' bani','B,R,T',0);
}
elseif($valoare==0.50)
{
        $pdf->Cell(15, 5, 'buc X 50','L,T,B',0);
$pdf->Cell(9, 5,' bani','B,R,T',0);
}

else{
    $pdf->Cell(15, 5, 'buc X '.intval($valoare),'L,T,B',0);
        if($valoare==1.00){
$pdf->Cell(9, 5,' leu','B,R,T',0);}
else{
    $pdf->Cell(9, 5,' lei','B,R,T',0);

}
}
$pdf->Cell(4, 5, '=',0,0);
$pdf->Cell(30, 5, $total_valoare.' ',1,1,'C');
}

$pdf->SetAutoPageBreak(false);

$pdf->MultiCell(55, 6, 'Casier predator,'."\n".'..............................', 'T', 'L', 0, 0, '', '', true);
$pdf->SetXY($x + 55, $y+108);
$pdf->MultiCell(33, 6, 'Casier primitor,'."\n".'..............................', 'R,T', 'L', 0, 0, '', '', true);
$pdf->SetXY($x + 40, $y+120);
$pdf->MultiCell(48, 10, 'Responsabil, ..............................', 'R', 'L', 0, '0', '', '', true);





  

 
$pdf->Output();
?>

