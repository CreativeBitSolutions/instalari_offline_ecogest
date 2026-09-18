<?php
   include('session.php');
    
    $gst=$_SESSION['gestiune'];
   $data_start=$_SESSION['data_start_iesiri_din_gest'];
        $data_end=$_SESSION['data_end_iesiri_din_gest'];
?>
<?php


require('fpdf181/fpdf.php');

//A4 width:219mm
//default margin: 10 mm each side
//writable horizontal : 149-(10*2)=129mm
class PDF extends FPDF
{
    
// Page header
function Header()
{

$this->SetFont('Arial','B',9);
$this->Cell(115	,10,'Vanzari '.$gest.' de la '.date("d-m-Y", strtotime($_SESSION['data_start_iesiri_din_gest'])).' pana la '.date("d-m-Y", strtotime($_SESSION['data_end_iesiri_din_gest'])),1,1,C);
$this->Cell(60	,10,'Data','L,B',0,C);
$this->Cell(55	,10,'Valoare','1',1,C);//end of line

}

 
}
$pdf= new PDF('P','mm','A5');
$pdf->SetAutoPageBreak(true,25);
$pdf->AddPage();
$pdf->SetFont('Arial','B',7);

 
//set font to arial,bold, 9 pt

$gest_sql = "SELECT $tabel_final_nomenclator.gestiune,cote_tva.cota from $tabel_final_nomenclator inner join cote_tva on $tabel_final_nomenclator.cota_tva=cote_tva.id group by $tabel_final_nomenclator.gestiune,cote_tva.cota;";
$gest_stmt = $pdo->prepare($gest_sql);  
$gest_stmt->execute(); 


while ($row = $gest_stmt->fetch(PDO::FETCH_ASSOC)){ 
    
$gest=$row['gestiune'];
$cota_tva=$row['cota'];
if($gest=='MC'){
        $pdf->Cell(115	,10,'Gestiunea materiale consumabile',0,1,C);

}
if($gest=='MC2'){
        $pdf->Cell(115	,10,'Gestiunea materiale consumabile 2',0,1,C);

}

if($gest=='MP' && $cota_tva==19){
        $pdf->Cell(115	,10,'Gestiunea materii prime 19%',0,1,C);

}
if($gest=='MP' && $cota_tva==5){
        $pdf->Cell(115	,10,'Gestiunea materii prime 5%',0,1,C);

}
if($gest=='MP' && $cota_tva==9){
        $pdf->Cell(115	,10,'Gestiunea materii prime 9%',0,1,C);

}
if($gest=='MP' && $cota_tva==0){
        $pdf->Cell(115	,10,'Gestiunea materii prime 0%',0,1,C);

}
if($gest=='MR' && $cota_tva==0 ){
        $pdf->Cell(115	,10,'Gestiunea marfuri 0%',0,1,C);

}

if($gest=='MR' && $cota_tva==5 ){
        $pdf->Cell(115	,10,'Gestiunea marfuri 5%',0,1,C);

}

if($gest=='MR' && $cota_tva==9 ){
        $pdf->Cell(115	,10,'Gestiunea marfuri 9%',0,1,C);

}

if($gest=='MR' && $cota_tva==19 ){
        $pdf->Cell(115	,10,'Gestiunea marfuri 19%',0,1,C);

}




if($gest=='OB'){
        $pdf->Cell(115	,10,'Gestiunea obiecte de inventar',0,1,C);

}
if($gest=='PF' && $cota_tva==0){
        $pdf->Cell(115	,10,'Gestiunea produse finite 0%',0,1,C);

}

if($gest=='PF' && $cota_tva==5){
        $pdf->Cell(115	,10,'Gestiunea produse finite 5%',0,1,C);

}

if($gest=='PF' && $cota_tva==9){
        $pdf->Cell(115	,10,'Gestiunea produse finite 9%',0,1,C);

}

if($gest=='PF' && $cota_tva==19){
        $pdf->Cell(115	,10,'Gestiunea produse finite 19%',0,1,C);

}

    $f_sql = "SELECT YEAR($tabel_final_miscari.data) as anul, MONTH($tabel_final_miscari.data) as luna,DAY($tabel_final_miscari.data) as ziua, SUM($tabel_final_miscari.pret_vanzare*$tabel_final_miscari.cantitate_misc) AS valoare_pret_vanzare,SUM($tabel_final_miscari.pret_vanzare*$tabel_final_miscari.cantitate_misc) AS valoare_cost_achizitie FROM $tabel_final_miscari inner join $tabel_final_nomenclator on $tabel_final_miscari.cod_p=$tabel_final_nomenclator.cod_p inner join cote_tva on $tabel_final_nomenclator.cota_tva=cote_tva.id where $tabel_final_miscari.tip_miscare='O' and $tabel_final_miscari.data>='$data_start' and $tabel_final_miscari.data<='$data_end' and $tabel_final_nomenclator.gestiune='$gest' and cote_tva.cota='$cota_tva' GROUP BY YEAR($tabel_final_miscari.data), MONTH($tabel_final_miscari.data),DAY($tabel_final_miscari.data) ORDER BY YEAR($tabel_final_miscari.data), MONTH($tabel_final_miscari.data),DAY($tabel_final_miscari.data)
";
$f_stmt = $pdo->prepare($f_sql);  
$f_stmt->execute(); 


while ($row = $f_stmt->fetch(PDO::FETCH_ASSOC)){ 
    
    $ziua=$row['ziua']; 
    $luna=$row['luna']; 
    $anul=$row['anul'];
    $valoare_pret_vanzare=round($row['valoare_pret_vanzare'],4);
        $valoare_cost_achizitie=round($row['valoare_cost_achizitie'],4);

    $pdf->Cell(60	,10,$ziua.'-'.$luna.'-'.$anul,1,0,C);
$pdf->Cell(55	,10,'Val pr vz: '.$valoare_pret_vanzare .' | Valoare pr achiz '.$valoare_cost_achizitie,'1',1,C);//end of line



}
    
    
}














  

 
$pdf->Output();
?>

