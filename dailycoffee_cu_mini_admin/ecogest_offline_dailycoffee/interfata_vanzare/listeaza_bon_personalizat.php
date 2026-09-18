<?php
   include('session.php');
?>
<?php
$nr_bc=$_SESSION['nr_bon_c'];

		  $fsql3 = "SELECT * from $tabel_final_bonuri_consum where nr_bon='$nr_bc'";    
$fstmt3 = $pdo->prepare($fsql3);  
$fstmt3->execute(); 
while ($row = $fstmt3->fetch(PDO::FETCH_ASSOC)){
	  	$data_bon=$row['data_bon']; 
$gestiune_pred=$row['gestiune_pred'];
$nr_com=$row['nr_comanda'];
$den_p=$row['produs'];
 
    
}



$dsql = "SELECT * from $tabel_final_date_firma";    
$dstmt = $pdo->prepare($dsql);  
$dstmt->execute(); 
while ($row = $dstmt->fetch(PDO::FETCH_ASSOC)){ 
                      $den_ent=$row['den_ent'];			     			
}




$total_valoare_fara_tva=0;
$total_valoare_tva=0;
$total_valoare_cu_tva=0;
require('fpdf181/fpdf.php');

//A4 width:219mm
//default margin: 10 mm each side
//writable horizontal : 219-(10*2)=189mm

$pdf= new FPDF('P','mm','A4');

$pdf->AddPage();

 
//set font to arial,bold, 9 pt
$pdf->SetFont('Arial','B',9);
$data = date("d-m-Y", strtotime($data_bon));

$pdf->Cell(15	,5,'Unitatea:',0,0);
$pdf->Cell(115	,5,$den_ent,0,0);
$pdf->Cell(20	,5,'Nr.doc: ',0,0);
$pdf->Cell(39	,5,$nr_bc,0,1);//end of line
$pdf->Cell(10	,5,'Gestiunea predatoare: '.$gestiune_pred,0,0);
$pdf->Cell(120	,5,'',0,0);
$pdf->Cell(10	,5,'Data:',0,0);
$pdf->Cell(39	,5,$data,0,1);//end of line
$pdf->Cell(25	,5,'',0,0);
$pdf->Cell(105	,5,'',0,0);
$pdf->Cell(25	,5,'Nr. comanda: ',0,0);
$pdf->Cell(34	,5,$nr_com,0,1);//end of line
$pdf->Cell(10	,5,'',0,0);
$pdf->Cell(120	,5,'',0,0);
$pdf->Cell(12	,5,'Produs: ',0,0);
$pdf->Cell(39	,5,$den_p,0,1);//end of line
//make a dummy empty cell as a vertical spacer
$pdf->Cell(189	,10,'',0,1);//end of line
$pdf->Cell(189	,10,'',0,1);//end of line
$pdf->Cell(189	,10,'',0,1);//end of line



//billing address
$pdf->Cell(189	,5,'BON DE CONSUM',0,1,C);//end of line

//make a dummy empty cell as a vertical spacer

$pdf->Cell(189	,3,'',0,1);//end of line

//add dummy cell at beginning of each line for indentation




//make a dummy empty cell as a vertical spacer
$pdf->Cell(189	,5,'',0,1);//end of line




//CELL(width,height,text,border,end line,[align])
//end of line

$nr_crt1 = 1;



$x = $pdf->GetX();
$y = $pdf->GetY();

$pdf->MultiCell(11, 10, 'Nr.crt', '1', 'C', 0, 0, '', '', true);
  $pdf->SetXY($x+11,$y);
$pdf->MultiCell(85, 10, 'Denumire material', '1', 'C', 0, 0, '', '', true);
  $pdf->SetXY($x+96,$y);
$pdf->MultiCell(15, 10, 'U.M.', '1', 'C', 0, 0, '', '', true);
  $pdf->SetXY($x+111,$y);
  $pdf->MultiCell(18, 5, 'Cantitate necesara', '1', 'C', 0, 0, '', '', true);
  $pdf->SetXY($x+129,$y);
$pdf->MultiCell(18, 5, 'Cantitate eliberata', '1', 'C', 0, 0, '', '', true);
  $pdf->SetXY($x+147,$y);
$pdf->MultiCell(18, 10, 'Pret unitar', '1', 'C', 0, 0, '', '', true);
  $pdf->SetXY($x+165,$y);
$pdf->MultiCell(25, 10, 'Valoare', '1', 'C', 0, 0, '', '', true);

$x = $pdf->GetX();
$y = $pdf->GetY();

$pdf->MultiCell(11, 10, '0', '1', 'C', 0, 0, '', '', true);
  $pdf->SetXY($x+11,$y);
$pdf->MultiCell(85, 10, '1', '1', 'C', 0, 0, '', '', true);
  $pdf->SetXY($x+96,$y);
$pdf->MultiCell(15, 10, '2', '1', 'C', 0, 0, '', '', true);
  $pdf->SetXY($x+111,$y);
  $pdf->MultiCell(18, 10, '3', '1', 'C', 0, 0, '', '', true);
  $pdf->SetXY($x+129,$y);
$pdf->MultiCell(18, 10, '4', '1', 'C', 0, 0, '', '', true);
  $pdf->SetXY($x+147,$y);
$pdf->MultiCell(18, 10, '5', '1', 'C', 0, 0, '', '', true);
  $pdf->SetXY($x+165,$y);
$pdf->MultiCell(25, 5, '6'."\n". '(4 x 5)', '1', 'C', 0, 0, '', '', true);


$x = $pdf->GetX();
$y = $pdf->GetY();

$Y= 93;
$factsql = "SELECT * from $tabel_final_miscari inner join $tabel_final_nomenclator on $tabel_final_miscari.cod_p=$tabel_final_nomenclator.cod_p where $tabel_final_miscari.nr_doc='$nr_bc' and $tabel_final_miscari.fel_doc='BC' ";
$factstmt = $pdo->prepare($factsql);  
$factstmt->execute(); 
while ($row = $factstmt->fetch(PDO::FETCH_ASSOC)){

$produs=$row['den_p'];
$um=$row['um'];
$cantitate=$row['cantitate_misc'];
$pret_unitar=$row['pu'];
$valoare_consum=$pret_unitar*$row['cantitate_misc'];


  $pdf->SetXY($x+11,$Y);
$pdf->MultiCell(85, 5, $produs, '1', 'L', 0, 0, '', '', true);

    $H = $pdf->GetY();
    $height= $H-$Y;

    $pdf->SetXY(10,$Y);
    $pdf->Cell(11,$height,$nr_crt1,1,0,'L');
 $pdf->SetXY($x+96,$Y);
    $pdf->Cell(15,$height,$um,1,1,'L');
 $pdf->SetXY($x+111,$Y);
    $pdf->Cell(18,$height,$cantitate,1,1,'L');
 $pdf->SetXY($x+129,$Y);
    $pdf->Cell(18,$height,$cantitate,1,1,'L');
     $pdf->SetXY($x+147,$Y);
    $pdf->Cell(18,$height,$pret_unitar,1,1,'L');
 $pdf->SetXY($x+165,$Y);
     $pdf->Cell(25,$height,$valoare_consum,1,1,'L');

    
    
    $Y=$H;


$nr_crt1++;
    

    
    $total_valoare=$total_valoare+$valoare_consum;
 
}

 $pdf->SetY($Y);

 




$pdf->SetFont('Arial','B',9);

$pdf->Cell(190  ,5,'Total: '.$total_valoare,'1',1,'R');

$pdf->Cell(47.5  ,5,'Gestionar',1,0);
$pdf->Cell(47.5  ,5,'Semnatura',1,0);
$pdf->Cell(47.5  ,5,'Primitor',1,0);
$pdf->Cell(47.5  ,5,'Semnatura',1,1);
$pdf->Cell(47.5  ,20,'',1,0);
$pdf->Cell(47.5  ,20,'',1,0);
$pdf->Cell(47.5  ,20,'',1,0);
$pdf->Cell(47.5  ,20,'',1,1);

  

 
$pdf->Output();
?>

