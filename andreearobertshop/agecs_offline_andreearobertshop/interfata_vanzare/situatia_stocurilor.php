<?php
   include('session.php');
?>
<?php
    $gst=$_SESSION['gestiune'];
   $data_start=$_SESSION['data_start'];
        $data_end=$_SESSION['data_end'];
   $data_end_sold_initial=$_SESSION['data_start'];

require('fpdf181/fpdf.php');

//A4 width:219mm
//default margin: 10 mm each side
//writable horizontal : 149-(10*2)=129mm
class PDF extends FPDF
{
    
// Page header
function Header()
{
    
    session_start();
$gst=$_SESSION['gestiune'];
if($gst=='MP'){
    
    $gest='Gestiunea Materii prime';
}
elseif($gst=='MR'){
    
    $gest='Gestiunea Marfuri';
}
elseif($gst=='MC'){
    
    $gest='Gestiunea Materiale consumabile';
}
elseif($gst=='MC2'){
    
    $gest='Gestiunea Materiale consumabile 2';
}
elseif($gst=='OB'){
    
    $gest='Gestiunea Obiecte de inventar';
}
elseif($gst=='MA'){
    
    $gest='Gestiunea Materiale auxiliare';
}
elseif($gst=='MJ'){
    
    $gest='Gestiunea Mijloace fixe ';
}
elseif($gst=='toate'){
 $gest='Toate gestiunile';   
}
    
    // Logo

$this->SetFont('Arial','B',9);
$this->Cell(50	,5,$_SESSION['den_ent'],'T,L,R',0,C);
$this->SetFont('Arial','B',15);
$this->Cell(120	,10,'Situatia Stocurilor','T,L',0,C);
$this->SetFont('Arial','B',9);
$this->Cell(25	,5,'Pagina','T,L,R',1,C);//end of line
$this->Cell(50	,5,'(Unitatea)','B,L,R',0,C);
$this->Cell(120	,5,'' ,'B,L',0,C);
$this->SetFont('Arial','B',9);
$this->Cell(25	,5,$this->PageNo(),'B,L,R',1,C);//end of line
$this->Cell(50	,5,$gest,'T,L',0,C);
$this->Cell(145	,5,'Perioada','T,L,R',1,C);
$this->Cell(50	,5,'','B,L,R',0,C);
$this->Cell(145	,5,date("d.m.Y", strtotime($_SESSION['data_start'])).' - '.date("d.m.Y", strtotime($_SESSION['data_end'])),'B,R',1,C);//end of line

$this->SetFont('Arial','B',7);

$this->Cell(60  ,10,'Denumire produs',1,0,C);
$this->Cell(10  ,10,'UM',1,0,C);
$this->Cell(25  ,10,'Stoc precedent',1,0,C);
$this->Cell(25  ,10,'Intrari',1,0,C);
$this->Cell(25  ,10,'Iesiri',1,0,C);
$this->Cell(25  ,10,'Stoc curent',1,0,C);
$this->Cell(25  ,10,'Valoare stoc curent',1,1,C);

}

}
$pdf= new PDF('P','mm','A4');
$pdf->SetAutoPageBreak(true,25);
$pdf->AddPage();
$pdf->SetFont('Arial','B',8);

//set font to arial,bold, 9 pt



$valoare=0;
// sold initial 

// sold initial


if($gst=='toate'){
    $f_sql = "SELECT cod_p,den_p,um,pret_vanzare from $tabel_final_nomenclator where gestiune!='PF' and cod_categ!=14;";

}
else{
$f_sql = "SELECT cod_p,den_p,um,pret_vanzare from $tabel_final_nomenclator where gestiune='$gst'  and cod_categ!=14;";}
$f_stmt = $pdo->prepare($f_sql);  
$f_stmt->execute(); 




$stoc_initial=0;
$cant_intrata=0;
$cant_iesita=0;
$stoc_final=0;

$val_initial=0;
$val_intrata=0;
$val_iesita=0;




while ($row = $f_stmt->fetch(PDO::FETCH_ASSOC)){ 




$den_p=$row['den_p'];
$cod_p=$row['cod_p'];
$um=$row['um'];
$pret_vanzare=$row['pret_vanzare'];



// stoc initial

$f_sql2 = "SELECT * from $tabel_final_miscari where cod_p='$cod_p' and data<'$data_end_sold_initial' ORDER BY data ASC;";    
$f_stmt2 = $pdo->prepare($f_sql2);  
$f_stmt2->execute(); 
$pdf->SetFont('Arial','B',8);

while ($row = $f_stmt2->fetch(PDO::FETCH_ASSOC)){ 


$tip_miscare=$row['tip_miscare'];
$cantitate_misc=$row['cantitate_misc'];
$valoare_vanzare=$row['pret_vanzare']*$row['cantitate_misc'];
if($tip_miscare=='I'){
    $stoc_initial=($stoc_initial*10000+$cantitate_misc*10000)/10000;
        $val_initial=($val_initial*10000+$valoare_vanzare*10000)/10000;


}
if($tip_miscare=='O'){

    $stoc_initial=($stoc_initial*10000-$cantitate_misc*10000)/10000;
            $val_initial=($val_initial*10000-$valoare_vanzare*10000)/10000;

}



}






$pdf->Cell(60	,10,$den_p,'L,B',0,C);
$pdf->Cell(10	,10,$um,'L,B',0,C);

$pdf->Cell(25	,10,number_format($stoc_initial,4),'1',0,C);





// stoc initial






// intrata

$f_sql2 = "SELECT sum(cantitate_misc) as cant_intrata,sum(cantitate_misc*pret_vanzare) as val_intrata from $tabel_final_miscari where cod_p='$cod_p' and data>='$data_start' and data<='$data_end' and tip_miscare='I' ORDER BY data ASC;";    
$f_stmt2 = $pdo->prepare($f_sql2);  
$f_stmt2->execute(); 
$pdf->SetFont('Arial','B',8);

while ($row = $f_stmt2->fetch(PDO::FETCH_ASSOC)){ 

$cant_intrata=$row['cant_intrata'];
$val_intrata=$row['val_intrata'];


}






$pdf->Cell(25	,10,number_format($cant_intrata,4),'1',0,C);





// intrata

//iesita
$f_sql2 = "SELECT sum(cantitate_misc) as cant_iesita,sum(cantitate_misc*pret_vanzare) as val_iesita from $tabel_final_miscari where cod_p='$cod_p' and data>='$data_start' and data<='$data_end' and tip_miscare='O' ORDER BY data ASC;";    
$f_stmt2 = $pdo->prepare($f_sql2);  
$f_stmt2->execute(); 
$pdf->SetFont('Arial','B',8);

while ($row = $f_stmt2->fetch(PDO::FETCH_ASSOC)){ 

$cant_iesita=$row['cant_iesita'];
$val_iesita=$row['val_iesita'];

}

$pdf->Cell(25	,10,number_format($cant_iesita,4),'1',0,C);
$valoare_vanzare_stoc_curent=number_format(($stoc_initial+$cant_intrata-$cant_iesita),4)*$pret_vanzare;
//iesita
$pdf->Cell(25	,10,number_format(($stoc_initial+$cant_intrata-$cant_iesita),4),'1',0,C);
$pdf->Cell(25	,10,$valoare_vanzare_stoc_curent,'1',1,C);


$stoc_curent=$stoc_initial+$cant_intrata-$cant_iesita;

// actualizare stoc conform miscari trb pus la stoc la data data de azi ca sa se recalculeze NU DA PE TOATE GESTIUNILE!
 $act_stocsql = "UPDATE $tabel_final_stoc set cantitate='$stoc_curent' where cod_p='$cod_p';";    
$act_stocstmt = $pdo->prepare($act_stocsql);  
$act_stocstmt->execute(); 

$stoc_initial=0;
$cant_intrata=0;
$cant_iesita=0;
$stoc_final=0;

$val_initial=0;
$val_intrata=0;
$val_iesita=0;





}






 
$pdf->Output();
?>

