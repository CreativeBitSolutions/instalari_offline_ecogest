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
date_default_timezone_set('UTC+2');
		date_default_timezone_set("Europe/Bucharest");
 $data_bon = date("Y-m-d", strtotime('+0 hours'));
 $ora_bon = date("H:i:s", strtotime('+0 hours'));

$dsql = "SELECT * from $tabel_final_date_firma";    
$dstmt = $pdo->prepare($dsql);  
$dstmt->execute(); 
while ($row = $dstmt->fetch(PDO::FETCH_ASSOC)){ 
                      $den_ent=$row['den_ent'];
			             $sediu=$row['sediu']; 
						 $cod_fiscal_ent=$row['cod_fiscal']; 
$serie_c_m=$row['serie_casa_marcat'];
			

}
$nr_bon=$_SESSION['nr_bon'];

$bon_sql = "SELECT * from $tabel_final_note where nrbon='$nr_bon'";    
$bon_stmt = $pdo->prepare($bon_sql);  
$bon_stmt->execute(); 
$total_val_vz_cu_tva=0;
$total_discount=0;
while ($row = $bon_stmt->fetch(PDO::FETCH_ASSOC)){
    $operator=$row['operator'];
    $cod_masa=$row['cod_masa'];
 
}
$f_sql = "SELECT $tabel_final_det_note.pachet,$tabel_final_det_note.discount,$tabel_final_det_note.cod_p,$tabel_final_nomenclator.den_p,$tabel_final_nomenclator.um,$tabel_final_det_note.cantitate,$tabel_final_det_note.tva_col,$tabel_final_det_note.pret_vanzare,$tabel_final_det_note.valoare_vanzare,$tabel_final_det_note.valoare_vanzare_cu_tva,cote_tva.cota,cote_tva.dep_casa,$tabel_final_det_note.id_vanz from $tabel_final_nomenclator inner join $tabel_final_det_note on $tabel_final_det_note.cod_p=$tabel_final_nomenclator.cod_p inner join cote_tva on $tabel_final_nomenclator.cota_tva=cote_tva.id where nr_bon='$nr_bon' and $tabel_final_nomenclator.departament='BAR';";    
$f_stmt = $pdo->prepare($f_sql);  
$f_stmt->execute(); 
$count=$f_stmt->rowCount();
$lungime=50;
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
$pdf->Cell(35,5,$data_bon .' '.$ora_bon ,0,'L',false);
$pdf->Cell(3,5,'LEI',0,1,'R');
$pdf->Cell(38,5,'COD OPERATOR: '.$operator ,'B',1,'L',false);

//CELL(width,height,text,border,end line,[align])
//end of line



while ($row = $f_stmt->fetch(PDO::FETCH_ASSOC)){ 
$pachet=$row['pachet'];
$pprodus=$row['den_p'];
$produs=substr($pprodus, 0, 20);

$um=$row['um'];
$cantitate=$row['cantitate'];
$tva_col=$row['tva_col'];
$pret_vanzare=$row['pret_vanzare'];
$valoare_vanzare=$row['valoare_vanzare'];
$cota_tva=$row['cota'];
$dep_casa=$row['dep_casa'];
if($cota_tva==9 && $pachet!=1 ){
							       $cota_tva=5;
								   $dep_casa=3;
							   }
							   			   if($cota_tva==9 && $pachet==1 ){
$produs.=" -P";

}
$discount=$row['discount'];
$discount_unitar=$discount/$cantitate;
$pret_vanzare_cu_tva=$pret_vanzare+($pret_vanzare*$cota_tva/100)-$discount_unitar;
$pret_vanzare_cu_tva=round(($pret_vanzare_cu_tva),2);
$valoare_vanzare_c_tva=$pret_vanzare_cu_tva*$cantitate;

$pdf->Cell(38  ,5,$produs,0,1);
$pdf->Cell(5  ,5,$cantitate,0,0);
$pdf->Cell(8  ,5,$um.' X ',0,0);
$pdf->Cell(10,5,$pret_vanzare_cu_tva,0,0);


$total_val_vz_cu_tva=$total_val_vz_cu_tva+$valoare_vanzare_c_tva;
$pdf->Cell(15,5,$valoare_vanzare_c_tva.' '. $cat_tva,0,1,'R');
}



$pdf->Cell(13  ,5,'TOTAL LEI','T',0);
$pdf->Cell(23  ,5,$total_val_vz_cu_tva,'T',0,'R');
$pdf->Cell(15,5,'',0,1,'R');
$pdf->Cell(13  ,5,'Masa: '.$cod_masa,0,0);



//Do not open the print dialog
$pdf->AutoPrint();
$pdf->Output();


?>

