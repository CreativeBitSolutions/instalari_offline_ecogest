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

 $adm_id=$_SESSION['operator'];
$dsql = "SELECT * FROM $tabel_final_admins where admin_id='$adm_id'";
$dstmt = $pdo->prepare($dsql);
$dstmt->execute(); 
while ($row = $dstmt->fetch(PDO::FETCH_ASSOC)){
$admin_firstname=$row['admin_firstname'];
$admin_lastname=$row['admin_lastname'];
}
$dsql = "SELECT * from $tabel_final_date_firma";    
$dstmt = $pdo->prepare($dsql);  
$dstmt->execute(); 
while ($row = $dstmt->fetch(PDO::FETCH_ASSOC)){ 
                      $den_ent=$row['den_ent'];
			             $sediu=$row['sediu']; 
						 $cod_fiscal_ent=$row['cod_fiscal']; 


}


$cod_inchidere=$_SESSION['cod_inchidere'];
     $bon_sql = "SELECT $tabel_final_inchideri_m.data_inchiderii,$tabel_final_inchideri_m.ora_inchiderii FROM $tabel_final_inchideri_m where $tabel_final_inchideri_m.cod_inchidere='$cod_inchidere'";    
$bon_stmt = $pdo->prepare($bon_sql);  
$bon_stmt->execute(); 
while ($row = $bon_stmt->fetch(PDO::FETCH_ASSOC)){
    $data_inchiderii=date("d-m-Y", strtotime($row['data_inchiderii']));
    $ora_inchiderii=$row['ora_inchiderii'];
}

$min_datetime_sql="select min(cast(concat($tabel_final_bonuri.data_bon, ' ',$tabel_final_bonuri.ora_bon) as datetime)) as min_dt, max(cast(concat($tabel_final_bonuri.data_bon, ' ',$tabel_final_bonuri.ora_bon) as datetime)) as max_dt FROM $tabel_final_bonuri where $tabel_final_bonuri.cod_inchidere='$cod_inchidere' and $tabel_final_bonuri.operator='$adm_id'";
$min_datetime_stmt = $pdo->prepare($min_datetime_sql);  
$min_datetime_stmt->execute();
while ($row = $min_datetime_stmt->fetch(PDO::FETCH_ASSOC)){ 

$time_primul_bon=$row['min_dt'];
$time_ultimul_bon=$row['max_dt'];

}

$f_sql = "select $tabel_final_nomenclator.um,$tabel_final_nomenclator.den_p as produs,sum($tabel_final_det_bonuri.cantitate) as cantitate_vanduta,sum($tabel_final_det_bonuri.valoare_vanzare_cu_tva)-sum($tabel_final_det_bonuri.discount) as valoare_vanduta from $tabel_final_det_bonuri INNER JOIN $tabel_final_nomenclator on $tabel_final_det_bonuri.cod_p=$tabel_final_nomenclator.cod_p inner join $tabel_final_bonuri on $tabel_final_det_bonuri.nr_bon=$tabel_final_bonuri.nrbon where $tabel_final_bonuri.cod_inchidere='$cod_inchidere' and $tabel_final_bonuri.operator='$adm_id'  group by $tabel_final_nomenclator.den_p";    
$f_stmt = $pdo->prepare($f_sql);  
$f_stmt->execute(); 
$count=$f_stmt->rowCount();
$lungime=80;
$lungime=$lungime+$count*20;
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
$pdf->MultiCell(38,5,'OPERATOR: '.$admin_firstname .' '.$admin_lastname ,0,'C',false);
$pdf->MultiCell(38,5,'PRODUSE VANDUTE INCHIDERE '.$cod_inchidere ,'T','C',false);
$pdf->MultiCell(38,5,'Data: '.$data_inchiderii.'; Ora: '.$ora_inchiderii.';' ,0,'C',false);
$pdf->MultiCell(38,5,'De la: '.$time_primul_bon ,0,'C',false);
$pdf->MultiCell(38,5,'Pana la: '.$time_ultimul_bon ,'B','C',false);

//CELL(width,height,text,border,end line,[align])
//end of line

    $pdf->MultiCell(38,5,'Produs X Cantitate | Valoare' ,'B','L',false);
while ($row = $f_stmt->fetch(PDO::FETCH_ASSOC)){ 

$produs=$row['produs'];
$cantitate=$row['cantitate_vanduta'];
$valoare_vanduta=$row['valoare_vanduta'];
$um=$row['um'];


$pdf->MultiCell(38, 8, ' '.$produs ."\n" .$cantitate. ' '.$um . ' | '.$valoare_vanduta .' LEI' , '1', 'L', 0, 0, '', '', true);
}




  $f_total_sql = "SELECT sum($tabel_final_bonuri.numerar) as total_numerar,sum($tabel_final_bonuri.tichete) as total_tichete,sum($tabel_final_bonuri.rest) as total_rest,sum($tabel_final_bonuri.protocol) as total_protocol,sum($tabel_final_bonuri.card) as total_card,sum($tabel_final_bonuri.discount) as total_discount,sum(valoare_vanzare_cu_tva) as total_valoare from $tabel_final_bonuri where cod_inchidere='$cod_inchidere' and operator='$adm_id' ;";
$f_total_stmt = $pdo->prepare($f_total_sql);  
$f_total_stmt->execute();

while ($row = $f_total_stmt->fetch(PDO::FETCH_ASSOC)){

    $total_numerar=$row['total_numerar'];
        $total_card=$row['total_card'];
        $total_discount=$row['total_discount'];
        $total_valoare=$row['total_valoare'];
                $total_rest=$row['total_rest'];
        $total_protocol=$row['total_protocol'];
        $total_tichete=$row['total_tichete'];

    
}

$pdf->Cell($latime*32.5/100  ,5,'TOTAL VALOARE','T',0);
$pdf->Cell($latime*57.5/100  ,5,$total_valoare,'T',0,'R');
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
$pdf->Cell($latime*32.5/100  ,5,'TOTAL DISCOUNT','T',0);
$pdf->Cell($latime*57.5/100  ,5,$total_discount,'T',0,'R');
$pdf->Cell($latime*37.5/100,5,'',0,1,'R');

$pdf->Cell(38  ,5,'Powered by www.agecs.net',0,1,'C');


//Do not open the print dialog
$pdf->Output();


?>

