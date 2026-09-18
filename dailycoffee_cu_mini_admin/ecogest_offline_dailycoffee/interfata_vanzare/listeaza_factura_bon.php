<?php
   include('session.php');
?>
<?php

$nr_factura=$_SESSION['nr_factura'];

$factsql = "SELECT * from $tabel_final_facturi_bonuri where idfactura='$nr_factura'";    
$factstmt = $pdo->prepare($factsql);  
$factstmt->execute(); 
while ($row = $factstmt->fetch(PDO::FETCH_ASSOC)){
      $den_tert=$row['denumire'];
			             $adresa=$row['adresa']; 
						 $cui_cif=$row['cui']; 
						 $nrbon=$row['nrbon'];
						 $cont_banca=$row['ct_banca']; 
						 $judet=$row['judet'];
                        $nr_reg_com=$row['nr_reg_com'];
                        $banca=$row['banca'];
                        $serie_factura=$row['serie_factura'];
                        $data_factura=$row['data_factura'];
                                $data_factura=date("d-m-Y", strtotime($data_factura));

                        
}

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
$pdf->SetFont('Arial','B',8);

$x_initial = $pdf->GetX();
$y_initial = $pdf->GetY();


$x = $pdf->GetX();
$y = $pdf->GetY();

    $pdf->SetXY($x+1,$y+3);

$pdf->MultiCell(86, 2, "Furnizor.: $den_ent ", '0', 'L', 0, 0, '', '', true);
    
    $x = $pdf->GetX();
$y = $pdf->GetY();
    
    $pdf->SetXY($x+1,$y+3);

$pdf->MultiCell(86, 2, "C.I.F: $cod_fiscal_ent ", '0', 'L', 0, 0, '', '', true);
    
    $x = $pdf->GetX();
$y = $pdf->GetY();
    $pdf->SetXY($x+1,$y+3);

$pdf->MultiCell(86, 4, "Nr.ord.reg.com.: $nr_reg_com_ent ", '0', 'L', 0, 0, '', '', true);
    
    $x = $pdf->GetX();
$y = $pdf->GetY();
    $pdf->SetXY($x+1,$y+3);

$pdf->MultiCell(86, 4, "Sediu: $sediu ", '0', 'L', 0, 0, '', '', true);
    
    $x = $pdf->GetX();
$y = $pdf->GetY();
    $pdf->SetXY($x+1,$y+3);

  $pdf->MultiCell(86, 4, "Judet: $judet_ent ", '0', 'L', 0, 0, '', '', true);
     $x = $pdf->GetX();
$y = $pdf->GetY();
$pdf->SetXY($x+1,$y+3);

$pdf->MultiCell(86, 4, "Cont: $cont_banca_ent ", '0', 'L', 0, 0, '', '', true);
   $x = $pdf->GetX();
$y = $pdf->GetY(); $pdf->SetXY($x+1,$y+3);

$pdf->MultiCell(86, 4, "Banca: $banca_ent ", '0', 'L', 0, 0, '', '', true);
   $x = $pdf->GetX();
$y = $pdf->GetY(); $pdf->SetXY($x+1,$y+3);

$pdf->MultiCell(86, 4, "Capital social: $cap_soc ", '0', 'L', 0, 0, '', '', true);

 $x = $pdf->GetX();
$y = $pdf->GetY();
    $pdf->SetXY($x+120,$y-53);
  
  $pdf->MultiCell(77, 2, "Cumparator.: $den_tert ", '0', 'L', 0, 0, '', '', true);
  $x = $pdf->GetX();
$y = $pdf->GetY();  $pdf->SetXY($x+120,$y+3);

$pdf->MultiCell(77, 2, "C.I.F: $cui_cif ", '0', 'L', 0, 0, '', '', true);
  $x = $pdf->GetX();
$y = $pdf->GetY();  $pdf->SetXY($x+120,$y+3);

$pdf->MultiCell(77, 4, "Nr.ord.reg.com.: $nr_reg_com ", '0', 'L', 0, 0, '', '', true);
   $x = $pdf->GetX();
$y = $pdf->GetY(); $pdf->SetXY($x+120,$y+3);

$pdf->MultiCell(77, 4, "Sediu: $adresa ", '0', 'L', 0, 0, '', '', true);
  $x = $pdf->GetX();
$y = $pdf->GetY();  $pdf->SetXY($x+120,$y+3);

  $pdf->MultiCell(77, 4, "Judet: $judet ", '0', 'L', 0, 0, '', '', true);
   $x = $pdf->GetX();
$y = $pdf->GetY(); $pdf->SetXY($x+120,$y+3);

$pdf->MultiCell(77, 4, "Cont: $cont_banca ", '0', 'L', 0, 0, '', '', true);
  $x = $pdf->GetX();
$y = $pdf->GetY();  $pdf->SetXY($x+120,$y+3);

$pdf->MultiCell(77, 4, "Banca: $banca ", '0', 'L', 0, 0, '', '', true);
   $x = $pdf->GetX();
$y = $pdf->GetY(); 



  
  

  
//make a dummy empty cell as a vertical spacer
$pdf->Cell(189	,36,'',0,1);//end of line

$pdf->SetFont('Arial','B',12);


//billing address
$pdf->Cell(189	,5,'FACTURA FISCALA',0,1,C);//end of line
$pdf->SetFont('Arial','B',9);
//make a dummy empty cell as a vertical spacer

$pdf->Cell(189	,3,'',0,1);//end of line

//add dummy cell at beginning of each line for indentation

$pdf->Cell(189	,5,'SR. '. $serie_factura .' NR. '.$nr_factura,0,1,C);

$pdf->Cell(47.25	,5,'',0,0,R);
$pdf->Cell(47.25	,5,'Data emiterii (zi-luna-an):',0,0,R);
$pdf->Cell(47.25	,5,$data_factura,0,0);
$pdf->Cell(47.25	,5,'',0,1,R);


$pdf->Cell(189	,5,'',0,1);//end of line

$nr_crt1 = 1;

$f_sql = "SELECT * from $tabel_final_det_note inner join $tabel_final_nomenclator on $tabel_final_det_note.cod_p=$tabel_final_nomenclator.cod_p inner join cote_tva on $tabel_final_nomenclator.cota_tva=cote_tva.id where nr_bon='$nrbon';";    
$f_stmt = $pdo->prepare($f_sql);  
$f_stmt->execute(); 

    $pdf->SetXY($x_initial,$y_initial+120);

$x = $pdf->GetX();
$y = $pdf->GetY();

$pdf->MultiCell(11, 10, 'Nr.crt', '1', 'C', 0, 0, '', '', true);
  $pdf->SetXY($x+11,$y);
$pdf->MultiCell(55, 10, 'Denumire produs', '1', 'C', 0, 0, '', '', true);
  $pdf->SetXY($x+66,$y);
$pdf->MultiCell(15, 10, 'U.M.', '1', 'C', 0, 0, '', '', true);
  $pdf->SetXY($x+81,$y);
  $pdf->MultiCell(15, 10, 'Cant.', '1', 'C', 0, 0, '', '', true);
  $pdf->SetXY($x+96,$y);
$pdf->MultiCell(18, 5, 'Pret unitar'."\n". '(fara TVA)', '1', 'C', 0, 0, '', '', true);
  $pdf->SetXY($x+114,$y);
$pdf->MultiCell(20, 5, 'Valoare'."\n". '(fara TVA)', '1', 'C', 0, 0, '', '', true);
  $pdf->SetXY($x+134,$y);
$pdf->MultiCell(15, 5, 'Cota TVA(%)', '1', 'C', 0, 0, '', '', true);
  $pdf->SetXY($x+149,$y);
$pdf->MultiCell(20, 5, 'Valoare TVA', '1', 'C', 0, 0, '', '', true);
  $pdf->SetXY($x+169,$y);
$pdf->MultiCell(20, 5, 'Valoare'."\n". '(cu TVA)', '1', 'C', 0, 0, '', '', true);

$x = $pdf->GetX();
$y = $pdf->GetY();

$pdf->MultiCell(11, 10, '0', '1', 'C', 0, 0, '', '', true);
  $pdf->SetXY($x+11,$y);
$pdf->MultiCell(55, 10, '1', '1', 'C', 0, 0, '', '', true);
  $pdf->SetXY($x+66,$y);
$pdf->MultiCell(15, 10, '2', '1', 'C', 0, 0, '', '', true);
  $pdf->SetXY($x+81,$y);
  $pdf->MultiCell(15, 10, '3', '1', 'C', 0, 0, '', '', true);
  $pdf->SetXY($x+96,$y);
$pdf->MultiCell(18, 10, '4', '1', 'C', 0, 0, '', '', true);
  $pdf->SetXY($x+114,$y);
$pdf->MultiCell(20, 5, '5'."\n". '(3 x 4)', '1', 'C', 0, 0, '', '', true);
  $pdf->SetXY($x+134,$y);
$pdf->MultiCell(15, 10, '6', '1', 'C', 0, 0, '', '', true);
  $pdf->SetXY($x+149,$y);
  $pdf->MultiCell(20, 5, '7'."\n". '(5 x 6)', '1', 'C', 0, 0, '', '', true);
  $pdf->SetXY($x+169,$y);
$pdf->MultiCell(20, 5, '8'."\n". '(5 + 7)', '1', 'C', 0, 0, '', '', true);



$x = $pdf->GetX();
$y = $pdf->GetY();
$Y= 150;
	    $count=$f_stmt->rowCount();

while ($row = $f_stmt->fetch(PDO::FETCH_ASSOC)){ 

$produs=$row['den_p'];
$um=$row['um'];
$cantitate=$row['cantitate'];
$cantitate_form =  number_format($cantitate, 2, ',', '.');

$tva_col=$row['tva_col'];
$tva_col_form =  number_format($tva_col, 2, ',', '.');

$pret_vanzare=$row['pret_vanzare'];
$pret_vanzare_form =  number_format($pret_vanzare, 2, ',', '.');

$valoare_vanzare=$row['valoare_vanzare'];
$valoare_vanzare_form =  number_format($valoare_vanzare, 2, ',', '.');

$id_vanz=$row['id_vanz'];
$valoare_vanzare_c_tva=$row['valoare_vanzare_cu_tva'];
$valoare_vanzare_c_tva_form =  number_format($valoare_vanzare_c_tva, 2, ',', '.');

$c_tva=$row['cota'];
$discount=$row['discount'];
 $discount=round($discount,2);
 $pret_fara_tva=round(($valoare_vanzare/$cantitate),2);
$pret_fara_tva_form =  number_format($pret_fara_tva, 2, ',', '.');


  $pdf->SetXY($x+11,$Y);
$pdf->MultiCell(55, 5, $produs, '1', 'L', 0, 0, '', '', true);

    $H = $pdf->GetY();
    $height= $H-$Y;

    $pdf->SetXY(10,$Y);
    $pdf->Cell(11,$height,$nr_crt1,1,0,'L');
 $pdf->SetXY($x+66,$Y);
    $pdf->Cell(15,$height,$um,1,1,'L');
 $pdf->SetXY($x+81,$Y);
    $pdf->Cell(15,$height,$cantitate_form,1,1,'L');
 $pdf->SetXY($x+96,$Y);
    $pdf->Cell(18,$height,$pret_fara_tva_form,1,1,'L');
     $pdf->SetXY($x+114,$Y);
    $pdf->Cell(20,$height,$valoare_vanzare_form,1,1,'L');
 $pdf->SetXY($x+134,$Y);
     $pdf->Cell(15,$height,$c_tva,1,1,'L');
 $pdf->SetXY($x+149,$Y);
    $pdf->Cell(20,$height,$tva_col_form,1,1,'L');
 $pdf->SetXY($x+169,$Y);
    $pdf->Cell(20,$height,$valoare_vanzare_c_tva_form,1,1,'L');
    $Y=$H;
$nr_crt1++;

  
    if($discount>0){
        
          $pdf->SetXY($x+11,$Y);
$pdf->MultiCell(55, 5, '      Reducere ', '1', 'L', 0, 0, '', '', true);

    $H = $pdf->GetY();
    $height= $H-$Y;

    $pdf->SetXY(10,$Y);
    $pdf->Cell(11,$height,$nr_crt1,1,0,'L');
 $pdf->SetXY($x+66,$Y);
    $pdf->Cell(15,$height,$um,1,1,'L');
 $pdf->SetXY($x+81,$Y);
    $pdf->Cell(15,$height,$cantitate,1,1,'L');
 $pdf->SetXY($x+96,$Y);
 
 $tva_discount=$discount*$c_tva/(100+$c_tva);
 $tva_discount=round($tva_discount,2);

 $discount_fara_tva=$discount-$tva_discount;
    $pdf->Cell(18,$height,-$discount_fara_tva,1,1,'L');
     $pdf->SetXY($x+114,$Y);
    $pdf->Cell(20,$height,-$discount_fara_tva,1,1,'L');
 $pdf->SetXY($x+134,$Y);
     $pdf->Cell(15,$height,$c_tva,1,1,'L');
 $pdf->SetXY($x+149,$Y);
    $pdf->Cell(20,$height,-$tva_discount,1,1,'L');
 $pdf->SetXY($x+169,$Y);
    $pdf->Cell(20,$height,-$discount,1,1,'L');

      
    $Y=$H;


$nr_crt1++;
    }

    $total_valoare_fara_tva=$total_valoare_fara_tva+$valoare_vanzare-$discount_fara_tva;
    $total_valoare_tva=$total_valoare_tva+$tva_col-$tva_discount;
    $total_valoare_cu_tva=$total_valoare_cu_tva+$valoare_vanzare_c_tva-$discount;
}

if($count=$nr_crt1)
{
  $pdf->SetXY($x+11,$Y);
$pdf->MultiCell(55, 5, '', '0', 'L', 0, 0, '', '', true);
    $H = $pdf->GetY();
$height= 80-($count*5);
    $pdf->SetXY(10,$Y);
    $pdf->Cell(11,$height,'','L,R',0);
 $pdf->SetXY($x+66,$Y);
    $pdf->Cell(15,$height,'','L',0);
 $pdf->SetXY($x+81,$Y);
    $pdf->Cell(15,$height,'','L',0);
 $pdf->SetXY($x+96,$Y);
    $pdf->Cell(18,$height,'','L',0);
     $pdf->SetXY($x+114,$Y);
    $pdf->Cell(20,$height,'','L',0);
 $pdf->SetXY($x+134,$Y);
     $pdf->Cell(15,$height,'','L',0);
 $pdf->SetXY($x+149,$Y);
    $pdf->Cell(20,$height,'','L',1);
 $pdf->SetXY($x+169,$Y);
    $pdf->Cell(20,$height,'','L,R',1);
    
    
$H = $pdf->GetY();
$height= $H-$Y;
$Y=$H;
    
    
}

  $total_valoare_fara_tva =  number_format($total_valoare_fara_tva, 2, ',', '.');
    $total_valoare_tva =  number_format($total_valoare_tva, 2, ',', '.');
    $total_valoare_cu_tva =  number_format($total_valoare_cu_tva, 2, ',', '.');
 $pdf->SetY($Y);

 




$pdf->SetFont('Arial','B',7);

$pdf->Cell(30  ,5,'Semnatura si','T,L,R',0);
$pdf->Cell(60  ,5,'Date privind expeditia','T,R',0);

$pdf->SetFont('Arial','B',9);

$pdf->Cell(24  ,5,'Total',1,0);
$pdf->Cell(20  ,5,$total_valoare_fara_tva,1,0);
$pdf->Cell(15 ,5,'-',1,0);
$pdf->Cell(20  ,5,$total_valoare_tva,1,0);
$pdf->Cell(20  ,5,$total_valoare_cu_tva,1,1);
$pdf->SetFont('Arial','B',7);

$pdf->Cell(30  ,5,'stampila furnizorului','L,R',0);
$pdf->Cell(30  ,5,'Numele delegatului',0,0);
$pdf->Cell(30  ,5,' ','R',0);



$pdf->Cell(24  ,5,'din care accize','1',0);
$pdf->Cell(20  ,5,'',1,0);
$pdf->Cell(15  ,5,'X',1,0,C);
$pdf->Cell(20  ,5,'X',1,0,C);
$pdf->Cell(20  ,5,'X',1,1,C);
$pdf->Cell( 30, 10, '', 'L,R', 0, 'L', false );
$pdf->Cell(28  ,5,'Cartea de identitate',0,0);
$pdf->Cell(8  ,5,'seria',0,0);
$pdf->Cell(5  ,5,' ',0,0);
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
$pdf->Cell(19  ,5,$old_mij_transp,0,0);
$pdf->Cell(4  ,5,'nr.',0,0);
$pdf->Cell(14  ,5,$old_nr_mij_transp,0,0);
$pdf->Cell(28  ,5,'','L',0);
$pdf->Cell(71  ,5,'','R',1);
$pdf->Cell(30  ,5,'','L,R',0);
$pdf->Cell(60  ,5,'Expedierea s-a efectuat in prezenta noastra la',0,0);
$pdf->Cell(28  ,5,'','L',0);
$pdf->Cell(71  ,5,'','R',1);
$pdf->Cell(30  ,5,'','L,R,B',0);
$pdf->Cell(6  ,5,'data','B',0);
$pdf->Cell(14  ,5,$old_data_exp,'B',0);
$pdf->Cell(5  ,5,'ora','B',0);
$pdf->Cell(11 ,5,$old_ora_exp,'B',0);
$pdf->Cell(13 ,5,'Semnaturi','B',0);
$pdf->Cell(11  ,5,'','B',0);
$pdf->Cell(28  ,5,'','L,B',0);
$pdf->Cell(71  ,5,'','B,R',1);


$pdf->Cell(30  ,10,'',0,0);

$pdf->Cell(189	,5,'',0,1,C);//end of line
$pdf->SetFont('Arial','B',7);




  

 
$pdf->Output();
?>

