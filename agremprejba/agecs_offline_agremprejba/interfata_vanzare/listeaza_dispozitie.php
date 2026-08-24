<?php
   include('session.php');
?>
<?php

$nr_dispozitie=$_SESSION['nr_dispozitie'];

	 $psql3 = "SELECT * from $tabel_final_dispozitii where nr_disp='$nr_dispozitie';";    
$pstmt3 = $pdo->prepare($psql3);  
$pstmt3->execute(); 
while ($row = $pstmt3->fetch(PDO::FETCH_ASSOC)){ 
    $nr_fac=$row['nrfact'];
                            $data_disp=date("d/m/Y", strtotime($row['data_disp']));
                      $serie_disp=$row['serie_disp'];
			                  $tip_dispozitie=$row['tip_disp'];
			             $suma_disp=$row['suma_disp']; 
						 $cond_ent=$row['conducator_ent']; 
						 $act_id=$row['act_id']; 
						 $serie_act=$row['serie_act'];
                        $nr_act=$row['nr_act'];
                        $nume_si_prenume=$row['nume_si_prenume'];
                        $functie=$row['functie'];
                        $scop=$row['scop'];


}


$dsql = "SELECT * from $tabel_final_date_firma";    
$dstmt = $pdo->prepare($dsql);  
$dstmt->execute(); 
while ($row = $dstmt->fetch(PDO::FETCH_ASSOC)){ 
                      $den_ent=$row['den_ent'];
	

}



require('fpdf181/cellpdf.php');

//A4 width:219mm
//default margin: 10 mm each side
//writable horizontal : 219-(10*2)=189mm

$pdf= new CellPDF('P','mm','A4');

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
$val = $suma_disp;
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
if($tip_dispozitie=='p'){$tip_disp='plata';}elseif($tip_dispozitie=='i'){$tip_disp='incasare';}

//set font to arial,bold, 9 pt
$pdf->SetFont('Arial','B',9);
$pdf->MultiCell(189, 1, '','T', 'C', 0, 0, '', '', true);
$pdf->Cell(189	,5,'Entitatea '.$den_ent,0,1);




$pdf->Cell(189	,10,'',0,1);//end of line

$pdf->SetFont('Arial','B',14);


//billing address
$pdf->Cell(189	,7,'Dispozitie de '.$tip_disp. ' catre casierie' ,0,1,C);//end of line
$pdf->Cell(189	,5,'Nr. '.$nr_dispozitie. ' Seria '.$serie_disp. ' din '. $data_disp ,0,1,C);//end of line

$pdf->SetFont('Arial','',12);

//make a dummy empty cell as a vertical spacer

$pdf->Cell(189	,6,'',0,1);//end of line



$pdf->MultiCell(189, 6, 'Numele si prenumele: '.$nume_si_prenume, '0', 'L', 0, 0, '', '', true);
$pdf->MultiCell(189, 6, 'Functia (calitatea): '.$functie, '0', 'L', 0, 0, '', '', true);

$pdf->MultiCell(189, 6, 'Suma: '.$suma_disp. ' , adica '. $suma_litere, '0', 'L', 0, 0, '', '', true);
$pdf->MultiCell(189, 6, 'Scopul platii: '.$scop, '0', 'L', 0, 0, '', '', true);

$pdf->VCell(10,24,'Semnaturi',1,0,'C');
$pdf->Cell(58	,6,'Conducatorul','T,R',0);//end of line
$pdf->Cell(58	,6,'Viza de control','T,R',0);//end of line
$pdf->Cell(58	,6,'Compartimentul','T,R',1);//end of line
 $pdf->SetX($x+10);
$pdf->Cell(58	,6,'Unitatii','B,R',0);//end of line
$pdf->Cell(58	,6,'financiar - preventiv','B,R',0);//end of line
$pdf->Cell(58	,6,'financiar - contabil','B,R',1);//end of line
 $pdf->SetX($x+10);

$pdf->Cell(58	,6,'','T,R',0);//end of line
$pdf->Cell(58	,6,'','T,R',0);//end of line
$pdf->Cell(58	,6,'','T,R',1);//end of line
 $pdf->SetX($x+10);
$pdf->Cell(58	,6,'','B,R',0);//end of line
$pdf->Cell(58	,6,'','B,R',0);//end of line
$pdf->Cell(58	,6,'','B,R',1);//end of line

if($tip_dispozitie=='p'){

 $pdf->SetXY($x,$y+84);

$pdf->MultiCell(94, 6, 'Beneficiarul sumei'."\n". 'Actul de identitate '.$act_id."\n". 'Seria '.$serie_act."\n". 'Nr '.$nr_act , '0', 'C', 0, 0, '', '', true);

$pdf->SetXY($x+94,$y+84);

$pdf->MultiCell(94, 6, 'Casier'."\n". 'Am platit '.$suma_disp.' RON'. "\n". 'Data '.$data_disp."\n". 'Semnatura........................ ' , '0', 'C', 0, 0, '', '', true);
}
if($tip_dispozitie=='i'){


$pdf->SetXY($x+94,$y+84);

$pdf->MultiCell(94, 6, 'Casier'."\n". 'Am primit '.$suma_disp.' RON'."\n". 'Data '.$data_disp."\n". 'Semnatura........................ ' , '0', 'C', 0, 0, '', '', true);
}



$pdf->Cell(189	,15,'',0,1);//end of line
$pdf->SetFont('Arial','B',9);

 $pdf->SetX($x+106);
$pdf->MultiCell(80, 6, 'Document Generat cu AGECS, www.agecs.net','0', 'C', 0, 0, '', '', true);
$pdf->MultiCell(189, 1, '','B', 'C', 0, 0, '', '', true);


$pdf->Cell(189	,6,'',0,1);//end of line


if($tip_dispozitie=='p'){$tip_disp='plata';}elseif($tip_dispozitie=='i'){$tip_disp='incasare';}

//set font to arial,bold, 9 pt
$pdf->SetFont('Arial','B',9);
$pdf->MultiCell(189, 1, '','T', 'C', 0, 0, '', '', true);
$pdf->Cell(189	,5,'Entitatea '.$den_ent,0,1);




$pdf->Cell(189	,10,'',0,1);//end of line

$pdf->SetFont('Arial','B',14);


//billing address
$pdf->Cell(189	,7,'Dispozitie de '.$tip_disp. ' catre casierie' ,0,1,C);//end of line
$pdf->Cell(189	,5,'Nr. '.$nr_dispozitie. ' Seria '.$serie_disp. ' din '. $data_disp ,0,1,C);//end of line

$pdf->SetFont('Arial','',12);

//make a dummy empty cell as a vertical spacer

$pdf->Cell(189	,6,'',0,1);//end of line



$pdf->MultiCell(189, 6, 'Numele si prenumele: '.$nume_si_prenume, '0', 'L', 0, 0, '', '', true);
$pdf->MultiCell(189, 6, 'Functia (calitatea): '.$functie, '0', 'L', 0, 0, '', '', true);

$pdf->MultiCell(189, 6, 'Suma: '.$suma_disp. ' , adica '. $suma_litere, '0', 'L', 0, 0, '', '', true);
$pdf->MultiCell(189, 6, 'Scopul platii: '.$scop, '0', 'L', 0, 0, '', '', true);

$pdf->VCell(10,24,'Semnaturi',1,0,'C');
$pdf->Cell(58	,6,'Conducatorul','T,R',0);//end of line
$pdf->Cell(58	,6,'Viza de control','T,R',0);//end of line
$pdf->Cell(58	,6,'Compartimentul','T,R',1);//end of line
 $pdf->SetX($x+10);
$pdf->Cell(58	,6,'Unitatii','B,R',0);//end of line
$pdf->Cell(58	,6,'financiar - preventiv','B,R',0);//end of line
$pdf->Cell(58	,6,'financiar - contabil','B,R',1);//end of line
 $pdf->SetX($x+10);

$pdf->Cell(58	,6,'','T,R',0);//end of line
$pdf->Cell(58	,6,'','T,R',0);//end of line
$pdf->Cell(58	,6,'','T,R',1);//end of line
 $pdf->SetX($x+10);
$pdf->Cell(58	,6,'','B,R',0);//end of line
$pdf->Cell(58	,6,'','B,R',0);//end of line
$pdf->Cell(58	,6,'','B,R',1);//end of line

if($tip_dispozitie=='p'){

 $pdf->SetXY($x,$y+224);

$pdf->MultiCell(94, 6, 'Beneficiarul sumei'."\n". 'Actul de identitate '.$act_id."\n". 'Seria '.$serie_act."\n". 'Nr '.$nr_act , '0', 'C', 0, 0, '', '', true);

$pdf->SetXY($x+94,$y+224);

$pdf->MultiCell(94, 6, 'Casier'."\n". 'Am platit '.$suma_disp.' RON'. "\n". 'Data '.$data_disp."\n". 'Semnatura........................ ' , '0', 'C', 0, 0, '', '', true);
}
if($tip_dispozitie=='i'){


$pdf->SetXY($x+94,$y+224);

$pdf->MultiCell(94, 6, 'Casier'."\n". 'Am primit '.$suma_disp.' RON'."\n". 'Data '.$data_disp."\n". 'Semnatura........................ ' , '0', 'C', 0, 0, '', '', true);
}



$pdf->Cell(189	,15,'',0,1);//end of line
$pdf->SetFont('Arial','B',9);

 $pdf->SetX($x+106);
$pdf->MultiCell(80, 6, 'Document Generat cu AGECS, www.agecs.net','0', 'C', 0, 0, '', '', true);
$pdf->MultiCell(189, 1, '','B', 'C', 0, 0, '', '', true);



$pdf->Output();
?>

