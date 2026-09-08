<?php
   include('session.php');
?>
<?php

$cod_inchidere=$_SESSION['cod_inchidere'];
$data_inchiderii=$_SESSION['data_inchiderii'];
$ora_inchiderii=$_SESSION['ora_inchiderii'];

$f_tot_sql = "SELECT sum($tabel_final_note.numerar-$tabel_final_note.rest) as total_numerar from $tabel_final_note where cod_inchidere='$cod_inchidere'; ";    
$f_tot_stmt = $pdo->prepare($f_tot_sql);  
$f_tot_stmt->execute();

while ($row = $f_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$suma_numere=$row['total_numerar'];
}

$nr_chitanta=$_SESSION['nr_chitanta'];

	 $psql3 = "SELECT $tabel_final_chitante.nrfact,$tabel_final_terti.denumire,$tabel_final_terti.adresa,$tabel_final_terti.cui_cif,$tabel_final_terti.cont_banca,$tabel_final_terti.judet,$tabel_final_terti.nr_reg_com,$tabel_final_terti.banca,$tabel_final_facturi.serie as serie_fact,$tabel_final_facturi.data_factura,$tabel_final_chitante.serie as serie_chit,$tabel_final_chitante.data_chitanta,$tabel_final_chitante.suma_numere,$tabel_final_chitante.suma_litere from $tabel_final_chitante inner join facturi on $tabel_final_chitante.nrfact=$tabel_final_facturi.nrfactura inner join $tabel_final_terti on $tabel_final_facturi.cod_client=$tabel_final_terti.cod_tert where $tabel_final_chitante.nrchitanta='$nr_chitanta';";    
$pstmt3 = $pdo->prepare($psql3);  
$pstmt3->execute(); 
while ($row = $pstmt3->fetch(PDO::FETCH_ASSOC)){ 
    $nr_fac=$row['nrfact'];
                            $data_fac=date("d/m/Y", strtotime($row['data_factura']));
                      $serie_fact=$row['serie_fact'];
			                  $den_tert=$row['denumire'];
			             $adresa=$row['adresa']; 
						 $cui_cif=$row['cui_cif']; 
						 $cont_banca=$row['cont_banca']; 
						 $judet=$row['judet'];
                        $nr_reg_com=$row['nr_reg_com'];
                        $banca=$row['banca'];  
                        $serie_chit=$row['serie_chit'];
                        $data_chitanta=date("d/m/Y", strtotime($row['data_chitanta']));
$suma_numere=$row['suma_numere'];

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


}





require('fpdf181/fpdf.php');

//A4 width:219mm
//default margin: 10 mm each side
//writable horizontal : 219-(10*2)=189mm

$pdf= new FPDF('P','mm','A4');

$pdf->AddPage();
$x = $pdf->GetX();
$y = $pdf->GetY();
$pdf->SetAutoPageBreak(false);







/**
* Clasa face transpunerea numerelor in cuvinte
* E dezvoltata doar pentru limba ROMANA
**/
class NConv {

/**
* Methoda de separare a grupurilor de 3 cifre, si conversie (functie de nivel)
* Va returna [NaN] daca parametrul de intrare $num nu e numeric
* Va returna [huge] daca numarul este mai mare decat 999.999.999.999
* Parametrul $sep e utilizat pentru a specifica separatorul cuvintelor
* Rezultatul metodei este numarul in cuvinte (daca $sep nu e specificat, va fi un singur cuvant)
**/
function sirDeLitere($num, $sep = '') {
$num = strval($num);
if ($num == "0") return "zero";
for ($i = 0; $i < strlen($num); ++$i) if (!is_numeric($num[$i])) return "[NaN]";
if (strlen($num) > strlen("999999999999")) return "[huge]";
$conv = ""; $level = 0; $current = "";
while (strlen($num) > 0) {
if (strlen($num) > 3) {
$current = substr($num, (strlen($num) - 3));
$num = substr($num, 0, strlen($num) - 3);
} else {
$current = $num;
$num = "";
}
$crt = ($current != "000") ? $this->convGroup($current,$level, $sep) : '';
$conv = $crt.$conv;
++$level;
}
return $conv;
}

/**
* Methoda de conversie a grupurilor de 3 cifre
**/
function convGroup($nr, $level, $sep = '') {
$val = intval($nr);
if ($val == 0) return "";
$decun = $val % 100;
$hndr = floor($val / 100);
$levels = array(
'single' => array('', 'mie', 'milion', 'miliard'),
'many' => array('', 'mii', 'milioane', 'miliarde')
);
// Sufixul grupului
$sfx = $levels[($val == 1) ? 'single' : 'many'][$level];
// Diverse forme pe care numerele le pot lua, utilizate mai jos in functie de caz
$digits = array(
array("", "unu", "doi", "trei", "patru", "cinci", "sase", "sapte", "opt", "noua"),
array("", "un", "doua", "trei", "patru", "cinci", "sase", "sapte", "opt", "noua"),
array("", "una", "doua", "trei", "patru", "cinci", "sase", "sapte", "opt", "noua"),
array("", "un", "doi", "trei", "patru", "cinci", "sai", "sapte", "opt", "noua"),
array("", "un", "doua", "trei", "patru", "cinci", "sai", "sapte", "opt", "noua"),);
// Mai jos e algoritmul de conversie asa cum l-am gandit eu (admit ca nu e perfect, dar isi face treaba)
$text = $digits[2][$hndr].$sep.(($hndr == 1) ? 'suta' : (($hndr > 1) ? 'sute' : '')).$sep;
if ($decun == 0)  return $text.$sep.$sfx.$sep;
if ($decun < 10)  return $text.$digits[(($level == 0) ? 0 : (($level == 1) ? ($hndr > 0 ? 0 : 2) : 3))][$decun].$sep.$sfx.$sep;
if ($decun == 10) return $text.'zece'.$sep.$sfx.$sep;
if ($decun < 20)  return $text.$digits[3][$decun%10].'sprezece'.$sep.$sfx.$sep;
return $text.$digits[4][$decun/10].'zeci'.$sep.(($decun % 10) == '0' ? '' : ('si'.$sep.$digits[0][$decun % 10].$sep)).(($level > 0) ? ('de'.$sep) : '').$sfx.$sep;
}



}

// Run a test...
$ncv = new NConv();
$val = $suma_numere;
$separator = '.';

$curr = 'lei';
$subcurr = 'bani';

if (strpos($val,$separator) !== false) {
   $val = explode($separator, $val);
   $cifre = $val[0];
   $zecimale = $val[1];
   
   $ncv = new NConv();
   $suma_litere=$ncv->sirDeLitere($cifre,'').''.$curr.'si'.$ncv->sirDeLitere($zecimale,'').''.$subcurr;   
}
else  {   
   $ncv = new NConv();
   $suma_litere=$ncv->sirDeLitere($val,'').' lei';
}


//set font to arial,bold, 9 pt
$pdf->SetFont('Arial','B',9);
$pdf->MultiCell(189, 1, '','T', 'C', 0, 0, '', '', true);
$pdf->Cell(189	,5,'Seria               '.$serie_chit. '      Nr'. '. '.$nr_chitanta,0,1);
$pdf->Cell(189	,5,'Furnizor: '.$den_ent,0,1);
$pdf->Cell(189	,5,'Nr.ord.reg.com.: '.$nr_reg_com_ent,0,1);
$pdf->Cell(189	,5,'C.I.F.: '.$cod_fiscal_ent,0,1);
$pdf->Cell(189	,5,'Adresa: '.$sediu,0,1);



$pdf->Cell(189	,10,'',0,1);//end of line

$pdf->SetFont('Arial','B',14);


//billing address
$pdf->Cell(160	,5,'CHITANTA',0,1,R);//end of line
$pdf->SetFont('Arial','',12);

//make a dummy empty cell as a vertical spacer

$pdf->Cell(189	,6,'',0,1);//end of line

//add dummy cell at beginning of each line for indentation
$pdf->SetX($x+106);
$pdf->MultiCell(80, 6, 'Seria         '.$serie_chit. ' Nr'.'.'.$nr_chitanta."\n".'Data(zi/luna/an): '.$data_chitanta, '1', 'C', 0, 0, '', '', true);
$pdf->Cell(189	,6,'',0,1);//end of line

$pdf->MultiCell(189, 6, 'Am primit de la: ', '0', 'L', 0, 0, '', '', true);
$pdf->MultiCell(189, 6, 'Suma: '.$suma_numere. ' , adica '. $suma_litere, '0', 'L', 0, 0, '', '', true);
$pdf->MultiCell(189, 6, 'Reprezentand contravaloarea R.Z. numarul '. $cod_inchidere. ' din data de '. $data_inchiderii.' ora: '. $ora_inchiderii, '0', 'L', 0, 0, '', '', true);


 $pdf->SetX($x+106);
$pdf->MultiCell(80, 6, 'Casier,' , '0', 'C', 0, 0, '', '', true);

$pdf->Cell(189	,15,'',0,1);//end of line
$pdf->SetFont('Arial','B',9);

 $pdf->SetX($x+106);
$pdf->MultiCell(80, 6, 'Document generat cu ECOGEST','0', 'C', 0, 0, '', '', true);
$pdf->MultiCell(189, 1, '','B', 'C', 0, 0, '', '', true);


$pdf->Cell(189	,30,'','0',1);//end of line
$pdf->Cell(189	,6,'',0,1);//end of line


//set font to arial,bold, 9 pt
$pdf->SetFont('Arial','B',9);
$pdf->MultiCell(189, 1, '','T', 'C', 0, 0, '', '', true);
$pdf->Cell(189	,5,'Seria '.$serie_chit. ' Nr'. '. '.$nr_chitanta,0,1);
$pdf->Cell(189	,5,'Furnizor: '.$den_ent,0,1);
$pdf->Cell(189	,5,'Nr.ord.reg.com.: '.$nr_reg_com_ent,0,1);
$pdf->Cell(189	,5,'C.I.F.: '.$cod_fiscal_ent,0,1);
$pdf->Cell(189	,5,'Adresa: '.$sediu,0,1);



$pdf->Cell(189	,10,'',0,1);//end of line

$pdf->SetFont('Arial','B',14);


//billing address
$pdf->Cell(160	,5,'CHITANTA',0,1,R);//end of line
$pdf->SetFont('Arial','',12);

//make a dummy empty cell as a vertical spacer

$pdf->Cell(189	,6,'',0,1);//end of line

//add dummy cell at beginning of each line for indentation
$pdf->SetX($x+106);
$pdf->MultiCell(80, 6, 'Seria '.$serie_chit. ' Nr'.'.'.$nr_chitanta."\n".'Data(zi/luna/an): '.$data_chitanta, '1', 'C', 0, 0, '', '', true);
$pdf->Cell(189	,6,'',0,1);//end of line

$pdf->MultiCell(189, 6, 'Am primit de la: '.$den_tert. ' C.I.F.'. ' '.$cui_cif.' Nr. reg. com. '.$nr_reg_com.' Adresa '.$adresa.' Judet '.$judet, '0', 'L', 0, 0, '', '', true);

$pdf->MultiCell(189, 6, 'Suma: '.$suma_numere. ' , adica '. $suma_litere, '0', 'L', 0, 0, '', '', true);
$pdf->MultiCell(189, 6, 'Reprezentand contravaloarea facturii seria: '.$serie_fact. ' , numarul '. $nr_fac. ' din data de '. $data_fac, '0', 'L', 0, 0, '', '', true);


 $pdf->SetX($x+106);
$pdf->MultiCell(80, 6, 'Casier,' , '0', 'C', 0, 0, '', '', true);

$pdf->Cell(189	,15,'',0,1);//end of line
$pdf->SetFont('Arial','B',9);

 $pdf->SetX($x+106);
$pdf->MultiCell(80, 6, 'Document generat cu ECOGEST','0', 'C', 0, 0, '', '', true);
$pdf->MultiCell(189, 1, '','B', 'C', 0, 0, '', '', true);



$pdf->Output();
?>

