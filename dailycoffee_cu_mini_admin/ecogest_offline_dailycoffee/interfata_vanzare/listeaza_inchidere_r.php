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




$cod_inchidere=$_SESSION['cod_inchidere'];
$bon_sql = "SELECT $tabel_final_inchideri_r.data_inchiderii,$tabel_final_inchideri_r.ora_inchiderii,$tabel_final_inchideri_r.tva_colectata,$tabel_final_inchideri_r.valoare_cu_tva,$tabel_final_admins.admin_firstname,admin_lastname FROM $tabel_final_inchideri_r inner join $tabel_final_admins on $tabel_final_inchideri_r.operator=$tabel_final_admins.admin_id  where cod_inchidere='$cod_inchidere'";    
$bon_stmt = $pdo->prepare($bon_sql);  
$bon_stmt->execute(); 
while ($row = $bon_stmt->fetch(PDO::FETCH_ASSOC)){
    $operator=$row['operator'];
    $data_inchiderii=date("d-m-Y", strtotime($row['data_inchiderii']));
    $ora_inchiderii=$row['ora_inchiderii'];
    $tva_colectata=$row['tva_colectata'];
    $valoare_cu_tva=$row['valoare_cu_tva'];
    $admin_firstname=$row['admin_firstname'];
    $admin_lastname=$row['admin_lastname'];

}
$min_datetime_sql="select min(cast(concat($tabel_final_note.data_bon, ' ',$tabel_final_note.ora_bon) as datetime)) as min_dt from $tabel_final_note where $tabel_final_note.cod_inchidere='$cod_inchidere'";
$min_datetime_stmt = $pdo->prepare($min_datetime_sql);  
$min_datetime_stmt->execute();


while ($row = $min_datetime_stmt->fetch(PDO::FETCH_ASSOC)){ 

$time_primul_bon=$row['min_dt'];

    
}
$max_datetime_sql="select max(cast(concat($tabel_final_note.data_bon, ' ',$tabel_final_note.ora_bon) as datetime)) as max_dt from $tabel_final_note where $tabel_final_note.cod_inchidere='$cod_inchidere'";
$max_datetime_stmt = $pdo->prepare($max_datetime_sql);  
$max_datetime_stmt->execute();


while ($row = $max_datetime_stmt->fetch(PDO::FETCH_ASSOC)){ 

$time_ultimul_bon=$row['max_dt'];

    
}
$lungime=80;
$latime=74;

$pdf = new PDF_AutoPrint('P','mm',array(80,$lungime));


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
$pdf->MultiCell($latime*95/100,5,'RAPORT INCHIDERE '. $cod_inchidere,'T','C',false);
$pdf->MultiCell($latime*95/100,5,'Data: '.$data_inchiderii.'; Ora: '.$ora_inchiderii.';' ,0,'C',false);
$pdf->MultiCell($latime*95/100,5,'De la: '.$time_primul_bon ,0,'C',false);
$pdf->MultiCell($latime*95/100,5,'Pana la: '.$time_ultimul_bon ,0,'C',false);
$pdf->Cell($latime*95/100,5,'OPERATOR: '.$admin_firstname.' '.$admin_lastname ,'B',1,'C',false);

//CELL(width,height,text,border,end line,[align])
 $f_tot_sql = "SELECT sum($tabel_final_note.numerar-$tabel_final_note.rest) as total_numerar from $tabel_final_note where cod_inchidere='$cod_inchidere'; ";    
$f_tot_stmt = $pdo->prepare($f_tot_sql);  
$f_tot_stmt->execute();

while ($row = $f_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_numerar=$row['total_numerar'];
}

$tichete_tot_sql = "SELECT sum($tabel_final_note.tichete) as total_tichete from $tabel_final_note where cod_inchidere='$cod_inchidere'; ";
$tichete_tot_stmt = $pdo->prepare($tichete_tot_sql);  
$tichete_tot_stmt->execute();

while ($row = $tichete_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_tichete=$row['total_tichete'];
}
 $rest_tot_sql = "SELECT sum($tabel_final_note.rest) as total_rest from $tabel_final_note where cod_inchidere='$cod_inchidere'; ";    
$rest_tot_stmt = $pdo->prepare($rest_tot_sql);  
$rest_tot_stmt->execute();

while ($row = $rest_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_rest=$row['total_rest'];
}

$protocol_sql = "SELECT sum($tabel_final_note.protocol) as tot_protocol FROM $tabel_final_note where cod_inchidere='$cod_inchidere'; ";
$protocol_stmt = $pdo->prepare($protocol_sql);  
$protocol_stmt->execute();

while ($row = $protocol_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_protocol=$row['tot_protocol'];
}

 $disc_tot_sql = "SELECT sum($tabel_final_note.discount) as total_discount from $tabel_final_note where cod_inchidere='$cod_inchidere'; ";    
$disc_tot_stmt = $pdo->prepare($disc_tot_sql);  
$disc_tot_stmt->execute();

while ($row = $disc_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_discount=$row['total_discount'];
}
 $ds_tot_sql = "SELECT sum($tabel_final_note.card) as total_card from $tabel_final_note where cod_inchidere='$cod_inchidere'; ";    
$ds_tot_stmt = $pdo->prepare($ds_tot_sql);  
$ds_tot_stmt->execute();

while ($row = $ds_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_card=$row['total_card'];
}

$max_bon_sql = "SELECT max($tabel_final_note.nrbon) as max_bon from $tabel_final_note where cod_inchidere='$cod_inchidere'; ";    
$max_bon_stmt = $pdo->prepare($max_bon_sql);  
$max_bon_stmt->execute();

while ($row = $max_bon_stmt->fetch(PDO::FETCH_ASSOC)){
	$nr_ultim_bon=$row['max_bon'];
}
$min_bon_sql = "SELECT min($tabel_final_note.nrbon) as min_bon from $tabel_final_note where cod_inchidere='$cod_inchidere'; ";    
$min_bon_stmt = $pdo->prepare($min_bon_sql);  
$min_bon_stmt->execute();

while ($row = $min_bon_stmt->fetch(PDO::FETCH_ASSOC)){
	$nr_primul_bon=$row['min_bon'];
}

$pdf->Cell($latime*32.5/100  ,5,'TOTAL LEI','T',0);
$pdf->Cell($latime*57.5/100  ,5,$valoare_cu_tva-$total_discount,'T',0,'R');
$pdf->Cell($latime*37.5/100,5,'',0,1,'R');
$pdf->Cell($latime*32.5/100  ,5,'TOTAL NUMERAR','T',0);
$pdf->Cell($latime*57.5/100  ,5,$total_numerar,'T',0,'R');
$pdf->Cell($latime*37.5/100,5,'',0,1,'R');
$pdf->Cell($latime*32.5/100  ,5,'TOTAL REST','T',0);
$pdf->Cell($latime*57.5/100  ,5,$total_rest,'T',0,'R');
$pdf->Cell($latime*37.5/100,5,'',0,1,'R');

$pdf->Cell($latime*32.5/100  ,5,'TOTAL CARD','T',0);
$pdf->Cell($latime*57.5/100  ,5,$total_card,'T',0,'R');
$pdf->Cell($latime*37.5/100,5,'',0,1,'R');
$pdf->Cell($latime*32.5/100  ,5,'TOTAL TICHETE','T',0);
$pdf->Cell($latime*57.5/100  ,5,$total_tichete,'T',0,'R');
$pdf->Cell($latime*37.5/100,5,'',0,1,'R');
$pdf->Cell($latime*32.5/100  ,5,'TOTAL PROTOCOL','T',0);
$pdf->Cell($latime*57.5/100  ,5,$total_protocol,'T',0,'R');
$pdf->Cell($latime*37.5/100,5,'',0,1,'R');
$pdf->Cell($latime*32.5/100  ,5,'TOTAL TVA COLECTATA','T',0);
$pdf->Cell($latime*57.5/100  ,5,$tva_colectata,'T',0,'R');
$pdf->Cell($latime*37.5/100,5,'',0,1,'R');
$pdf->Cell($latime*95/100,5,'note: '.$nr_primul_bon.' - '. $nr_ultim_bon ,'B,T',1,'C',false);
$pdf->Cell($latime*95/100  ,5,'Powered by www.agecs.net',0,1,'C');

//Do not open the print dialog
$pdf->AutoPrint();
$pdf->Output();


?>

