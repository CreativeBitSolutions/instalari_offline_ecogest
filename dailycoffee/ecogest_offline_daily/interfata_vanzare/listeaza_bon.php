<?php
session_start();
   include('database_connection.php');?><?php
require('fpdf181/pdf_js.php');

class PDF_AutoPrint extends PDF_JavaScript
{
	function AutoPrint($printer='')
	{
		// Open the print dialog
		if($printer)
		{
			$printer = str_replace('\\', '\\\\', $printer);
			$script = "var pp = getPrintParams();";
			$script .= "pp.interactive = pp.constants.interactionLevel.full;";
			$script .= "pp.printerName = '$printer'";
			$script .= "print(pp);";
		}
		 else
            $script = 'print(true);';
        $this->IncludeJS($script);
	}

}


$dsql = "SELECT * from $tabel_final_date_firma";    
$dstmt = $pdo->prepare($dsql);  
$dstmt->execute(); 
while ($row = $dstmt->fetch(PDO::FETCH_ASSOC)){ 
                      $den_ent=$row['den_ent'];
			             $sediu=$row['sediu']; 
						 $cod_fiscal_ent=$row['cod_fiscal']; 
$serie_c_m=$row['serie_casa_marcat'];
			

}



$cif_client=$_SESSION['cif_client'];
$nr_bon=$_SESSION['nr_bon'];

$bon_sql = "SELECT * FROM $tabel_final_bonuri where nrbon='$nr_bon'";    
$bon_stmt = $pdo->prepare($bon_sql);  
$bon_stmt->execute(); 
while ($row = $bon_stmt->fetch(PDO::FETCH_ASSOC)){
    $operator=$row['operator'];
    $data_bon=date("d-m-Y", strtotime($row['data_bon']));
    $ora_bon=$row['ora_bon'];
    $tva_colectata=$row['tva_colectata'];
    $numerar=$row['numerar'];
    $card=$row['card'];
}
$f_sql = "SELECT $tabel_final_det_bonuri.discount,$tabel_final_det_bonuri.cod_p,$tabel_final_nomenclator.den_p,$tabel_final_nomenclator.um,$tabel_final_det_bonuri.cantitate,$tabel_final_det_bonuri.tva_col,$tabel_final_det_bonuri.pret_vanzare,$tabel_final_det_bonuri.valoare_vanzare,$tabel_final_det_bonuri.valoare_vanzare_cu_tva,cote_tva.cota,cote_tva.dep_casa,$tabel_final_det_bonuri.id_vanz from $tabel_final_nomenclator inner join $tabel_final_det_bonuri on $tabel_final_det_bonuri.cod_p=$tabel_final_nomenclator.cod_p inner join cote_tva on $tabel_final_nomenclator.cota_tva=cote_tva.id where nr_bon='$nr_bon';";    
$f_stmt = $pdo->prepare($f_sql);  
$f_stmt->execute(); 
$count=$f_stmt->rowCount();
$lungime=70;
$lungime=$lungime+$count*10;
$pdf = new PDF_AutoPrint('P','mm',array(40,$lungime));


$pdf->SetLeftMargin(1);
$pdf->SetRightMargin(1);
$pdf->SetTopMargin(1);
$pdf->SetAutoPageBreak(false);

$pdf->AddPage();

 
//set font to arial,bold, 9 pt
$pdf->SetFont('Arial','',6);


$pdf->MultiCell(38,5,$den_ent ,0,'C',false);
$pdf->MultiCell(38,5,$sediu ,0,'C',false);
$pdf->MultiCell(38,5,'C.I.F.: '.$cod_fiscal_ent ,0,'C',false);
$pdf->MultiCell(38,5,'C.I.F. CLIENT: '.$cif_client ,0,'C',false);
$pdf->Cell(35,5,$data_bon .' '.$ora_bon ,0,'L',false);
$pdf->Cell(3,5,'LEI',0,1,'R');
$pdf->Cell(38,5,'COD OPERATOR: '.$operator ,'B',1,'L',false);

//CELL(width,height,text,border,end line,[align])
//end of line



while ($row = $f_stmt->fetch(PDO::FETCH_ASSOC)){ 

$pprodus=$row['den_p'];
$produs=substr($pprodus, 0, 20);
$um=$row['um'];
$cantitate=$row['cantitate'];
$tva_col=$row['tva_col'];
$pret_vanzare=$row['pret_vanzare'];
$valoare_vanzare=$row['valoare_vanzare'];
$cota_tva=$row['cota'];
$dep_casa=$row['dep_casa'];
$discount=$row['discount'];
$discount_unitar=$discount/$cantitate;
$pret_vanzare_cu_tva=$pret_vanzare+($pret_vanzare*$cota_tva/100)-$discount_unitar;
$pret_vanzare_cu_tva=round(($pret_vanzare_cu_tva),2);
$valoare_vanzare_c_tva=$pret_vanzare_cu_tva*$cantitate;

$pdf->Cell(38  ,5,$produs,0,1);
$pdf->Cell(5  ,5,$cantitate,0,0);
$pdf->Cell(8  ,5,$um.' X ',0,0);
$pdf->Cell(10,5,$pret_vanzare_cu_tva,0,0);

if($dep_casa==1){
    
    $cat_tva='A';
}
elseif($dep_casa==2){
    
    $cat_tva='B';
}
elseif($cota_tva==7){
    
    $cat_tva='';
}
$pdf->Cell(15,5,$valoare_vanzare_c_tva.' '. $cat_tva,0,1,'R');
}




  
 $nr_bon=$_SESSION['nr_bon'];
 $f_tot_sql = "SELECT sum($tabel_final_det_bonuri.valoare_vanzare_cu_tva) as a from $tabel_final_det_bonuri  where nr_bon='$nr_bon'; ";    
$f_tot_stmt = $pdo->prepare($f_tot_sql);  
$f_tot_stmt->execute();

while ($row = $f_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_val_vz_cu_tva=$row['a'];
}
 $ds_tot_sql = "SELECT sum($tabel_final_det_bonuri.discount) as b from $tabel_final_det_bonuri  where nr_bon='$nr_bon'; ";    
$ds_tot_stmt = $pdo->prepare($ds_tot_sql);  
$ds_tot_stmt->execute();

while ($row = $ds_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_disc=$row['b'];
}

$total_val_vz_cu_tva=$total_val_vz_cu_tva-$total_disc;
$pdf->Cell(13  ,5,'TOTAL LEI','T',0);
$pdf->Cell(23  ,5,$total_val_vz_cu_tva,'T',0,'R');
$pdf->Cell(15,5,'',0,1,'R');
$rest=$numerar-$total_val_vz_cu_tva;

if($card!=0){
        $rest=0;

}
if($numerar!=0 && $card!=0){
    $rest=0;

}
if($numerar!=0){
$pdf->Cell(13  ,5,'Numerar',0,0);
$pdf->Cell(23  ,5,$numerar,0,0,'R');
$pdf->Cell(15,5,'',0,1,'R');}
if($card!=0){
$pdf->Cell(13  ,5,'Card',0,0);
$pdf->Cell(23  ,5,$card,0,0,'R');
$pdf->Cell(15,5,'',0,1,'R');
}
$pdf->Cell(13  ,5,'Rest',0,0);
$pdf->Cell(23  ,5,$rest,0,0,'R');
$pdf->Cell(15,5,'',0,1,'R');

 $cote_tva_sql = "SELECT cote_tva.cota,cote_tva.dep_casa,sum($tabel_final_det_bonuri.tva_col) as total_tva,sum($tabel_final_det_bonuri.discount) as total_discount from $tabel_final_nomenclator inner join $tabel_final_det_bonuri on $tabel_final_det_bonuri.cod_p=$tabel_final_nomenclator.cod_p inner join cote_tva on $tabel_final_nomenclator.cota_tva=cote_tva.id where $tabel_final_det_bonuri.nr_bon='$nr_bon' group by cote_tva.dep_casa;";    
$cote_tva_stmt = $pdo->prepare($cote_tva_sql);  
$cote_tva_stmt->execute();
while ($row = $cote_tva_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_dep=round($row['total_tva'],2);
	$dep_casa=$row['dep_casa'];
	$total_discount=$row['total_discount']*$row['cota']/(100+$row['cota']);
	$total_dep=round($total_dep,2);
	if($dep_casa==1){
	    $den_dep_casa='A: TVA A (19%) ';
	}
	elseif($dep_casa==2){
	    	    $den_dep_casa='B: TVA B (9%) ';
	    
	}
    elseif($dep_casa==7){
	    	    $den_dep_casa='FARA TVA ';
	    
	}
	$pdf->Cell(13  ,5,$den_dep_casa,0,0);
$pdf->Cell(23  ,5,$total_dep,0,1,'R');
	
}
	$pdf->Cell(13  ,5,'TOTAL TVA',0,0);
$pdf->Cell(23  ,5,$tva_colectata,0,1,'R');
$pdf->Cell(38  ,5,'Nr. bon: '.$nr_bon,0,1);
$pdf->Cell(38  ,5,'S/N: '.$serie_c_m,0,1);

$pdf->Cell(38  ,5,'Powered by www.agecs.net',0,1,'C');


//Do not open the print dialog
$pdf->AutoPrint();
$pdf->Output();


?>

