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

	$cust_id=$_SESSION['cust_id'];
$com_id=$_SESSION['com_id'];

$dsql = "SELECT * from $tabel_final_date_firma";    
$dstmt = $pdo->prepare($dsql);  
$dstmt->execute(); 
while ($row = $dstmt->fetch(PDO::FETCH_ASSOC)){ 
                      $den_ent=$row['den_ent'];
			             $sediu=$row['sediu']; 
						 $cod_fiscal_ent=$row['cod_fiscal']; 
						 $cont_banca_ent=$row['cont_banca']; 
						 $banca_ent=$row['banca'];
						 $judet_ent=$row['judet'];
                        $nr_reg_com_ent=$row['nr_reg_com'];
                        $cap_soc=$row['cap_soc'];
			

}
    $cust_type=$_SESSION['tip_client'];


 $csql = "SELECT * FROM $tabel_final_comenzi where nr_comanda='$com_id'";    
$cstmt = $pdo->prepare($csql);  
$cstmt->execute(); 
while ($row = $cstmt->fetch(PDO::FETCH_ASSOC)){ 
$data_fact=date( 'd-m-Y', strtotime($row['data_comenzii'] ) );;

    if($cust_type=="persoana_juridica")

{
                      $den_tert=$row['den_pj'];
			             $aadresa=$row['adresa_pj'];
			             $adresa=substr($aadresa, 0, 42);

						 $cui_cif=$row['cif_pj']; 
						 $cont_banca=$row['cont_banca_pj']; 
						 $banca=$row['banca_pj'];
						 $judet=$row['judet_pj'];
                        $nr_reg_com=$row['nr_reg_com_pj'];
}            
	   if($cust_type=="persoana_fizica")

{
                      
             $ccsql = "SELECT * from $tabel_final_customers where customer_id='$cust_id'";    
$ccstmt = $pdo->prepare($ccsql);  
$ccstmt->execute(); 
                     while ($row = $ccstmt->fetch(PDO::FETCH_ASSOC)){ 
                     
                         $customer_firstname=$row['customer_firstname'];
$customer_lastname=$row['customer_lastname'];
                         
                     } 
                      $den_tert=$customer_firstname .' '. $customer_lastname;
			             $adresa='';
						 $cui_cif='';
						 $cont_banca='';
						 $banca='';
						 $judet='';
                        $nr_reg_com='';
}		

}


$pdf = new PDF_AutoPrint();
$pdf->AddPage();
//set font to arial,bold, 9 pt
$pdf->SetFont('Arial','B',9);

$pdf->Cell(15	,5,'Furnizor:',0,0);
$pdf->Cell(105	,5,$den_ent,0,0);
$pdf->Cell(20	,5,'Cumparator:',0,0);
$pdf->Cell(39	,5,$den_tert,0,1);//end of line

$pdf->Cell(10	,5,'C.I.F.:',0,0);
$pdf->Cell(110	,5,$cod_fiscal_ent,0,0);
$pdf->Cell(10	,5,'C.I.F.:',0,0);
$pdf->Cell(39	,5,$cui_cif,0,1);//end of line

$pdf->Cell(25	,5,'Nr.ord.reg.com.:',0,0);
$pdf->Cell(95	,5,$nr_reg_com_ent,0,0);
$pdf->Cell(25	,5,'Nr.ord.reg.com.:',0,0);
$pdf->Cell(34	,5,$nr_reg_com,0,1);//end of line

$pdf->Cell(10	,5,'Sediu:',0,0);
$pdf->Cell(110	,5,$sediu,0,0);
$pdf->Cell(12	,5,'Sediul:',0,0);
$pdf->Cell(39	,5,$adresa,0,1);//end of line

$pdf->Cell(10	,5,'Judet:',0,0);
$pdf->Cell(110	,5,$judet_ent,0,0);
$pdf->Cell(12	,5,'Judet:',0,0);
$pdf->Cell(39	,5,$judet,0,1);//end of line

$pdf->Cell(10	,5,'Cont:',0,0);
$pdf->Cell(110	,5,$cont_banca_ent,0,0);
$pdf->Cell(10	,5,'Cont:',0,0);
$pdf->Cell(39	,5,$cont_banca,0,1);//end of line

$pdf->Cell(12	,5,'Banca:',0,0);
$pdf->Cell(108	,5,$banca_ent,0,0);
$pdf->Cell(12	,5,'Banca:',0,0);
$pdf->Cell(39	,5,$banca,0,1);//end of line

$pdf->Cell(25	,5,'Capital social:',0,0);
$pdf->Cell(95	,5,$cap_soc,0,0);


//make a dummy empty cell as a vertical spacer
$pdf->Cell(189	,10,'',0,1);//end of line
$pdf->Cell(189	,10,'',0,1);//end of line
$pdf->Cell(189	,10,'',0,1);//end of line



//billing address
$pdf->Cell(189	,5,'FACTURA FISCALA',0,1,C);//end of line

//make a dummy empty cell as a vertical spacer

$pdf->Cell(189	,3,'',0,1);//end of line

//add dummy cell at beginning of each line for indentation

$pdf->Cell(47.25	,5,'',0,0,R);
$pdf->Cell(47.25	,5,'SERIA:',0,0,R);
$pdf->Cell(47.25	,5,'mra',0,0);
$pdf->Cell(47.25	,5,'',0,1,R);

$pdf->Cell(47.25	,5,'',0,0,R);
$pdf->Cell(47.25	,5,'NR.FACTURII:',0,0,R);
$pdf->Cell(47.25	,5,$_SESSION['com_id'],0,0);
$pdf->Cell(47.25	,5,'',0,1,R);

$pdf->Cell(47.25	,5,'',0,0,R);
$pdf->Cell(47.25	,5,'DATA(zi-luna-an):',0,0,R);
$pdf->Cell(47.25	,5,$data_fact,0,0);
$pdf->Cell(47.25	,5,'',0,1,R);



//make a dummy empty cell as a vertical spacer
$pdf->Cell(189	,5,'',0,1);//end of line

$pdf->Cell(189	,5,'Cota TVA:19% ',0,1);//end of line



//CELL(width,height,text,border,end line,[align])
//end of line

$com_id=$_SESSION['com_id'];
$nr_crt1 = 1;

$f_sql = "SELECT $tabel_final_nomenclator.den_p,$tabel_final_nomenclator.um,$tabel_final_comenzi_detalii.cantitate,$tabel_final_comenzi_detalii.tva_col,$tabel_final_comenzi_detalii.pret_vanzare,$tabel_final_comenzi_detalii.valoare_vanzare,$tabel_final_comenzi_detalii.valoare_vanzare_cu_tva FROM $tabel_final_comenzi_detalii INNER JOIN $tabel_final_nomenclator on $tabel_final_comenzi_detalii.cod_p=$tabel_final_nomenclator.cod_p where nr_comanda='$com_id';";    
$f_stmt = $pdo->prepare($f_sql);  
$f_stmt->execute(); 
$pdf->Cell(10  ,20,'Nr.crt',1,0,C);
$pdf->Cell(50  ,20,'Denumire produs',1,0,C);
$pdf->Cell(15  ,20,'U.M.',1,0,C);
$pdf->Cell(15  ,20,'Cant',1,0,C);
$pdf->Cell(28  ,20,'P.U. fara TVA',1,0,C);
$pdf->Cell(28  ,20,'Valoare fara TVA',1,0,C);
$pdf->Cell(15  ,20,'TVA',1,0,C);
$pdf->Cell(28  ,20,'Valoare (cu TVA)',1,1,C);

$pdf->Cell(10  ,5,'0',1,0,C);
$pdf->Cell(50  ,5,'1',1,0,C);
$pdf->Cell(15  ,5,'2',1,0,C);
$pdf->Cell(15  ,5,'3',1,0,C);
$pdf->Cell(28  ,5,'4',1,0,C);
$pdf->Cell(28  ,5,'5(=3x4)',1,0,C);
$pdf->Cell(15  ,5,'6',1,0,C);
$pdf->Cell(28  ,5,'7(=5+6)',1,1,C);

while ($row = $f_stmt->fetch(PDO::FETCH_ASSOC)){ 


$pprodus=$row['den_p'];
$produs=substr($pprodus, 0, 24);

$um=$row['um'];
$cantitate=$row['cantitate'];
$tva_col=$row['tva_col'];
$pret_vanzare=$row['pret_vanzare'];
$valoare_vanzare=$row['valoare_vanzare'];
$valoare_vanzare_c_tva=$row['valoare_vanzare_cu_tva'];




$pdf->Cell(10  ,5,$nr_crt1,1,0,C);
$pdf->Cell(50  ,5,$produs,1,0);
$pdf->Cell(15  ,5,$um,1,0);
$pdf->Cell(15  ,5,$cantitate,1,0);
$pdf->Cell(28  ,5,$pret_vanzare,1,0);
$pdf->Cell(28  ,5,$valoare_vanzare,1,0);
$pdf->Cell(15  ,5,$tva_col,1,0);
$pdf->Cell(28  ,5,$valoare_vanzare_c_tva,1,1);



$nr_crt1++;}


 $com_id=$_SESSION['com_id'];
 $f_tot_sql = "SELECT sum($tabel_final_comenzi_detalii.valoare_vanzare) as a FROM $tabel_final_comenzi_detalii  where nr_comanda='$com_id'; ";    
$f_tot_stmt = $pdo->prepare($f_tot_sql);  
$f_tot_stmt->execute();

while ($row = $f_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$valoare_f_vz=$row['a'];
}


$com_id=$_SESSION['com_id'];
 $f_tot_sql = "SELECT sum($tabel_final_comenzi_detalii.tva_col) as b FROM $tabel_final_comenzi_detalii  where nr_comanda='$com_id'; ";    
$f_tot_stmt = $pdo->prepare($f_tot_sql);  
$f_tot_stmt->execute();

while ($row = $f_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_tva_col=$row['b'];
}

  
 $com_id=$_SESSION['com_id'];
 $f_tot_sql = "SELECT sum($tabel_final_comenzi_detalii.valoare_vanzare_cu_tva) as c FROM $tabel_final_comenzi_detalii  where nr_comanda='$com_id'; ";    
$f_tot_stmt = $pdo->prepare($f_tot_sql);  
$f_tot_stmt->execute();

while ($row = $f_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_val_vz_cu_tva=$row['c'];
}






$pdf->SetFont('Arial','B',7);

$pdf->Cell(30  ,5,'Semnatura si','T,L,R',0);
$pdf->Cell(60  ,5,'Date privind expeditia','T,R',0);

$pdf->SetFont('Arial','B',9);

$pdf->Cell(28  ,5,'Total',1,0);
$pdf->Cell(28  ,5,$valoare_f_vz,1,0);
$pdf->Cell(15  ,5,$total_tva_col,1,0);
$pdf->Cell(28  ,5,$total_val_vz_cu_tva,1,1);
$pdf->SetFont('Arial','B',7);

$pdf->Cell(30  ,5,'stampila furnizorului','L,R',0);
$pdf->Cell(30  ,5,'Numele delegatului',0,0);
$pdf->Cell(30  ,5,'','R',0);



$pdf->Cell(28  ,5,'din care accize','1',0);
$pdf->Cell(28  ,5,'',1,0);
$pdf->Cell(15  ,5,'X',1,0,C);
$pdf->Cell(28  ,5,'X',1,1,C);
$pdf->Cell(30  ,10,'','L,R',0);

$pdf->Cell(28  ,5,'Cartea de identitate',0,0);
$pdf->Cell(8  ,5,'seria',0,0);
$pdf->Cell(5  ,5,'',0,0);
$pdf->Cell(5  ,5,'nr.',0,0);
$pdf->Cell(14  ,5,'',0,0);

$pdf->Cell(28  ,5,'Semnatura de primire','L',0);
$pdf->Cell(71  ,5,'','R',1);
$pdf->Cell(30  ,5,'','L',0);
$pdf->Cell(30  ,5,'Eliberat(a)',0,0);
$pdf->Cell(30  ,5,'',0,0);
$pdf->Cell(28  ,5,'','L',0);
$pdf->Cell(71  ,5,'','R',1);
$pdf->Cell(30  ,5,'','L,R',0);
$pdf->Cell(23  ,5,'Mijloc de transport:',0,0);
$pdf->Cell(19  ,5,'',0,0);
$pdf->Cell(4  ,5,'nr.',0,0);
$pdf->Cell(14  ,5,'',0,0);
$pdf->Cell(28  ,5,'','L',0);
$pdf->Cell(71  ,5,'','R',1);
$pdf->Cell(30  ,5,'','L,R',0);
$pdf->Cell(60  ,5,'Expedierea s-a efectuat in prezenta noastra la',0,0);
$pdf->Cell(28  ,5,'','L',0);
$pdf->Cell(71  ,5,'','R',1);
$pdf->Cell(30  ,5,'','L,R,B',0);
$pdf->Cell(6  ,5,'data','B',0);
$pdf->Cell(14  ,5,'','B',0);
$pdf->Cell(5  ,5,'ora','B',0);
$pdf->Cell(11 ,5,'','B',0);
$pdf->Cell(13 ,5,'Semnaturi','B',0);
$pdf->Cell(11  ,5,'','B',0);
$pdf->Cell(28  ,5,'','L,B',0);
$pdf->Cell(71  ,5,'','B,R',1);
//Do not open the print dialog
$pdf->AutoPrint();
$pdf->Output();
?>