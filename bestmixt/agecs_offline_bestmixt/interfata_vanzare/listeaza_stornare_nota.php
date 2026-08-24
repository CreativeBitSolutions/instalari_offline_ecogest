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

$bon_sql = "SELECT * from $tabel_final_stornari_rest where nr_bon='$nr_bon'";    
$bon_stmt = $pdo->prepare($bon_sql);  
$bon_stmt->execute(); 
while ($row = $bon_stmt->fetch(PDO::FETCH_ASSOC)){
    $operator=$row['operator'];
    $data_stornare=date("d-m-Y", strtotime($row['data_stornare']));
    $ora_bon=$row['ora_bon'];
    $tva_colectata=$row['tva_colectata'];

        $tichete=$row['tichete'];
}
$f_sql = "SELECT $tabel_final_det_stornari_rest.discount,$tabel_final_det_stornari_rest.cod_p,$tabel_final_nomenclator.den_p,$tabel_final_nomenclator.um,$tabel_final_det_stornari_rest.cantitate,$tabel_final_det_stornari_rest.tva_col,$tabel_final_det_stornari_rest.pret_vanzare,$tabel_final_det_stornari_rest.valoare_vanzare,$tabel_final_det_stornari_rest.valoare_vanzare_cu_tva,cote_tva.cota,cote_tva.dep_casa,$tabel_final_det_stornari_rest.id_vanzare from $tabel_final_nomenclator inner join $tabel_final_det_stornari_rest on $tabel_final_det_stornari_rest.cod_p=$tabel_final_nomenclator.cod_p inner join cote_tva on $tabel_final_nomenclator.cota_tva=cote_tva.id where nr_bon='$nr_bon';";    
$f_stmt = $pdo->prepare($f_sql);  
$f_stmt->execute(); 
$count=$f_stmt->rowCount();
$lungime=80;
$latime=74;
$lungime=$lungime+$count*10;
$pdf = new PDF_AutoPrint('P','mm',array($latime,$lungime));


$pdf->SetLeftMargin(4);
$pdf->SetRightMargin(4);
$pdf->SetTopMargin(4);
$pdf->SetAutoPageBreak(false);

$pdf->AddPage();

 
//set font to arial,bold, 9 pt
$pdf->SetFont('Arial','',6);


$pdf->MultiCell($latime*95/100,5,$den_ent ,0,'C',false);
$pdf->MultiCell($latime*95/100,5,$sediu ,0,'C',false);
$pdf->MultiCell($latime*95/100,5,'C.I.F.: '.$cod_fiscal_ent ,0,'C',false);
$pdf->MultiCell($latime*95/100,5,'C.I.F. CLIENT: '.$cif_client ,0,'C',false);
$pdf->Cell($latime*87.5/100,5,$data_stornare .' '.$ora_bon ,0,'L',false);
$pdf->Cell($latime*7.5/100,5,'LEI',0,1,'R');

$op_sql = "SELECT admin_firstname,admin_lastname FROM $tabel_final_admins where admin_id='$operator'";    
$op_stmt = $pdo->prepare($op_sql);  
$op_stmt->execute(); 
while ($row = $op_stmt->fetch(PDO::FETCH_ASSOC)){ 
$admin_firstname=$row['admin_firstname'];
$admin_lastname=$row['admin_lastname'];
}
$pdf->Cell($latime*95/100,5,'OPERATOR: '.$admin_firstname.' '. $admin_lastname ,'B',1,'L',false);

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
    
     if($_SESSION['ajustare_adaos']==0){
							       $cota_tva=5;
							       
}
								   $dep_casa=3;
							   }
							   			   if($cota_tva==9 && $pachet==1 ){
$produs.=" -P";

}
$discount=$row['discount'];
$discount_unitar=$discount/$cantitate;
$pret_vanzare_cu_tva=$pret_vanzare+($pret_vanzare*$cota_tva/100)-$discount_unitar;
    if($_SESSION['ajustare_adaos']==1 && $cota_tva==9 && $pachet!=1){
    $cota_tva=9;
        $pret_vanzare_cu_tva2=$pret_vanzare+($pret_vanzare*$cota_tva/100)-$discount_unitar;

        $dif=$pret_vanzare_cu_tva2-$pret_vanzare_cu_tva;
    }
$pret_vanzare_cu_tva=round(($pret_vanzare_cu_tva+$dif),2);
$valoare_vanzare_c_tva=$pret_vanzare_cu_tva*$cantitate;

$pdf->Cell($latime*95/100  ,5,$produs,0,1);
$pdf->Cell($latime*12.5/100  ,5,$cantitate,0,0);
$pdf->Cell($latime*20/100  ,5,$um.' X ',0,0);
$pdf->Cell($latime*25/100,5,$pret_vanzare_cu_tva,0,0);

if($dep_casa==1){
    
    $cat_tva='A';
}
elseif($dep_casa==2){
    
    $cat_tva='B';
}
elseif($dep_casa==3){
    
    $cat_tva='C';
}
elseif($cota_tva==7){
    
    $cat_tva='';
}
$pdf->Cell($latime*37.5/100,5,- $valoare_vanzare_c_tva.' '. $cat_tva,0,1,'R');
}




  
 $nr_bon=$_SESSION['nr_bon'];
 $f_tot_sql = "SELECT sum($tabel_final_det_stornari_rest.valoare_vanzare_cu_tva) as a from $tabel_final_det_stornari_rest  where nr_bon='$nr_bon'; ";    
$f_tot_stmt = $pdo->prepare($f_tot_sql);  
$f_tot_stmt->execute();

while ($row = $f_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_val_vz_cu_tva=$row['a'];
}
 $ds_tot_sql = "SELECT sum($tabel_final_det_stornari_rest.discount) as b from $tabel_final_det_stornari_rest  where nr_bon='$nr_bon'; ";    
$ds_tot_stmt = $pdo->prepare($ds_tot_sql);  
$ds_tot_stmt->execute();

while ($row = $ds_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_disc=$row['b'];
}

$total_val_vz_cu_tva=$total_val_vz_cu_tva-$total_disc;
$pdf->Cell($latime*32.5/100  ,5,'TOTAL LEI','T',0);
$pdf->Cell($latime*57.5/100  ,5,- $total_val_vz_cu_tva,'T',0,'R');
$pdf->Cell($latime*37.5/100,5,'',0,1,'R');

if($card!=0){
        $rest=0;

}
if($numerar!=0 && $card!=0){
    $rest=0;

}
if($numerar!=0){
$pdf->Cell($latime*32.5/100  ,5,'Numerar',0,0);
$pdf->Cell($latime*57.5/100  ,5,$numerar,0,0,'R');
$pdf->Cell($latime*37.5/100,5,'',0,1,'R');}
if($tichete!=0){
$pdf->Cell($latime*32.5/100  ,5,'Tichete',0,0);
$pdf->Cell($latime*57.5/100  ,5,$tichete,0,0,'R');
$pdf->Cell($latime*37.5/100,5,'',0,1,'R');
}
if($card!=0){
$pdf->Cell($latime*32.5/100  ,5,'Card',0,0);
$pdf->Cell($latime*57.5/100  ,5,$card,0,0,'R');
$pdf->Cell($latime*37.5/100,5,'',0,1,'R');
}
if($protocol!=0){
$pdf->Cell($latime*32.5/100  ,5,'Protocol',0,0);
$pdf->Cell($latime*57.5/100  ,5,$protocol,0,0,'R');
$pdf->Cell($latime*37.5/100,5,'',0,1,'R');
}


$tva_a=0;
$tva_b=0;
$tva_c=0;
$faratva=0;

 $cote_tva_sql = "SELECT $tabel_final_det_stornari_rest.pachet,$tabel_final_det_stornari_rest.discount,cote_tva.cota,cote_tva.dep_casa,$tabel_final_det_stornari_rest.tva_col from $tabel_final_nomenclator inner join $tabel_final_det_stornari_rest on $tabel_final_det_stornari_rest.cod_p=$tabel_final_nomenclator.cod_p inner join cote_tva on $tabel_final_nomenclator.cota_tva=cote_tva.id where nr_bon='$nr_bon';";    
$cote_tva_stmt = $pdo->prepare($cote_tva_sql);  
$cote_tva_stmt->execute();
while ($row = $cote_tva_stmt->fetch(PDO::FETCH_ASSOC)){
    
    $dep_casa=$row['dep_casa'];
$pachet=$row['pachet'];
	$tva_col=$row['tva_col'];

							   $cota_tva=$row['cota'];
							   if($cota_tva==9 && $pachet!=1 ){
								   $dep_casa=3;
							   }
	//$total_discount=$row['total_discount']*$row['cota']/(100+$row['cota']);
	$total_dep=round($total_dep,2);
	if($dep_casa==1){
	    $den_dep_casa='A: TVA A (19%) ';
	    $tva_a=$tva_a+$tva_col;
	}
	elseif($dep_casa==2){
	    	    $den_dep_casa='B: TVA B (9%) ';
	    	    $tva_b=$tva_b+$tva_col;

	}
	elseif($dep_casa==3){
	    	    $den_dep_casa='C: TVA C (5%) ';
	    	    $tva_c=$tva_c+$tva_col;

	}
    elseif($dep_casa==7){
	    	    $den_dep_casa='FARA TVA ';
	    	    $faratva=$faratva+$tva_col;

	}


    
    
}
if($tva_a>0){
	$pdf->Cell($latime*32.5/100  ,5,'A: TVA A (19%) ',0,0);
$pdf->Cell($latime*57.5/100  ,5,$tva_a,0,1,'R');}
if($tva_b>0){
	$pdf->Cell($latime*32.5/100  ,5,'B: TVA B (9%) ',0,0);
$pdf->Cell($latime*57.5/100  ,5,$tva_b,0,1,'R');}
	if($tva_c>0){$pdf->Cell($latime*32.5/100  ,5,'C: TVA C (5%) ',0,0);
$pdf->Cell($latime*57.5/100  ,5,$tva_c,0,1,'R');}
	$pdf->Cell($latime*32.5/100  ,5,'FARA TVA ',0,0);
$pdf->Cell($latime*57.5/100  ,5,$faratva,0,1,'R');
	$pdf->Cell($latime*32.5/100  ,5,'TOTAL TVA',0,0);
$pdf->Cell($latime*57.5/100  ,5,$tva_colectata,0,1,'R');
$pdf->Cell($latime*95/100  ,5,'Nr. nota: '.$nr_bon,0,1);
$pdf->Cell($latime*95/100  ,5,'S/N: '.$serie_c_m,0,1);

$pdf->Cell($latime*95/100  ,5,'Powered by www.agecs.net',0,1,'C');


//Do not open the print dialog


$pdf->AutoPrint();
$pdf->Output();

?>

