<?php
   include('session.php');
?>
<?php


$nr_nir=$_SESSION['nr_nir'];
$csql = "SELECT * from $tabel_final_nir inner join $tabel_final_terti on $tabel_final_nir.cod_tert=$tabel_final_terti.cod_tert  where nr_nir='$nr_nir'";    
$cstmt = $pdo->prepare($csql);  
$cstmt->execute(); 
while ($row = $cstmt->fetch(PDO::FETCH_ASSOC)){ 
                      $den_tert=$row['denumire'];
                      $nr_doc_int=$row['nr_doc_int'];
			     $data_nir=date( 'd-m-Y', strtotime( $row['data_nir'] ) );
$data_doc=date( 'd-m-Y', strtotime( $row['data_doc_int'] ) );

             
			

}




require('fpdf181/fpdf.php');

//A4 width:219mm


//default margin: 5 mm each side
//writable horizontal : 219-(10*2)=189mm

$pdf= new FPDF('L','mm','A4');
$pdf->SetMargins(5, 5);

$pdf->AddPage();



//set font to arial,bold, 14 pt
$pdf->SetFont('Arial','B',20);

$pdf->Cell(278	,5,'Nota receptie si constatare diferente',0,1,C);
$pdf->Cell(278	,5,'','B',1,C);

$pdf->SetFont('Arial','B',9);



//set font to arial, regular, 12pt

$pdf->SetFont('Arial','B',9);
 
$dsql = "SELECT den_ent from $tabel_final_date_firma";    
$dstmt = $pdo->prepare($dsql);  
$dstmt->execute(); 
while ($row = $dstmt->fetch(PDO::FETCH_ASSOC)){ 
                      $den_ent=$row['den_ent'];
			          
			

}
$pdf->Cell(139	,5,'Unitatea '.$den_ent,0,0);
$pdf->Cell(120	,5,'Numar document:',0,0,R);
$pdf->Cell(19	,5,$nr_nir,0,1);//end of line

$pdf->Cell(239	,5,'Data',0,0,R);
$pdf->Cell(39	,5,$data_nir,0,1);

$pdf->Cell(75	,5,'Se receptioneaza valorile materiale furnizate de  ',0,0);
$pdf->Cell(80	,5,$den_tert,0,0);
$pdf->Cell(60	,5,'conform facturii(aviz de expeditie) nr. ',0,0);
$pdf->Cell(20	,5,$nr_doc_int,0,1);
$pdf->Cell(20	,5,'din data de ',0,0);
$pdf->Cell(20	,5,$data_doc,0,0);
$pdf->Cell(10	,5,'astfel:',0,1);












$pdf->SetFont('Arial','B',7);


//CELL(width,height,text,border,end line,[align])
//end of line

$nr_nir=$_SESSION['nr_nir'];
$nr_crt1 = 1;

$nir_sql = "SELECT $tabel_final_nomenclator.gestiune,cote_tva.cota,$tabel_final_achizitii.pret_vanzare_fara_tva,$tabel_final_achizitii.cota_tva,$tabel_final_achizitii.cod_p,$tabel_final_nomenclator.den_p,$tabel_final_nomenclator.um,$tabel_final_nomenclator.proc_adaos,$tabel_final_achizitii.cantitate_doc,$tabel_final_nomenclator.pret_vanzare,$tabel_final_achizitii.cantitate_prim,$tabel_final_achizitii.pret_achiz,$tabel_final_achizitii.valoare_fara_tva,$tabel_final_achizitii.tva_ded,$tabel_final_achizitii.proc_adaos,$tabel_final_achizitii.ad_unit,$tabel_final_achizitii.valoare_adaos,$tabel_final_achizitii.tva_neex,$tabel_final_achizitii.pret_vanzare,$tabel_final_achizitii.valoare_vanzare,$tabel_final_achizitii.valoare_vanzare_cu_tva,$tabel_final_achizitii.id_achiz,$tabel_final_achizitii.tva_colect_unit from $tabel_final_nomenclator inner join $tabel_final_achizitii on $tabel_final_achizitii.cod_p=$tabel_final_nomenclator.cod_p INNER JOIN cote_tva on $tabel_final_nomenclator.cota_tva=cote_tva.id where nr_nir='$nr_nir';";    
$nir_stmt = $pdo->prepare($nir_sql);  
$nir_stmt->execute(); 

$pdf->Cell(10  ,5,'','L,T,R',0);
$pdf->Cell(40  ,5,'','T,R',0);
$pdf->Cell(10  ,5,'','T,R',0);
$pdf->Cell(17  ,5,'','T,R',0);
$pdf->Cell(17  ,5,'','T,R',0);
$pdf->Cell(69  ,5,'Achizitie',1,0,C);
$pdf->Cell(45  ,5,'Adaos',1,0,C);
$pdf->Cell(17  ,5,'','T,R,L',0,C);
$pdf->Cell(39 ,5,'TVA vanzare','R,T,B',0,C);
$pdf->Cell(26 ,5,'Val vz. cu tva',1,1,C);
$pdf->Cell(10  ,20,'Nr.crt','L,B,R',0,C);
$pdf->Cell(40  ,20,'Denumire produs','B,R',0,C);
$pdf->Cell(10  ,20,'U.M.','B,R',0,C);
$pdf->Cell(17  ,20,'Cant.doc.','B,R',0,C);
$pdf->Cell(17  ,20,'Cant.prim.','B,R',0,C);
$pdf->Cell(15  ,20,'P.U.','B,R',0,C);
$pdf->Cell(26  ,20,'Valoare (fara TVA)','B,R',0,C);
$pdf->Cell(14  ,20,'Tva.ded.','B,R',0,C);
$pdf->Cell(14  ,20,'Total','B,R',0,C);

$pdf->Cell(15  ,20,'%','B,R',0,C);
$pdf->Cell(15  ,20,'Unitar','B,R',0,C);
$pdf->Cell(15 ,20,'Total','B,R',0,C);
$pdf->Cell(17 ,20,'Pret fara TVA','B,R',0,C);

$pdf->Cell(13  ,20,'TVA adaos','B,R',0,C);
$pdf->Cell(13 ,20,'Unitar','B,R',0,C);
$pdf->Cell(13  ,20,'Total','B,R',0,C);
$pdf->Cell(13  ,20,'Unitar','B,R',0,C);
$pdf->Cell(13 ,20,'Total','B,R',1,C);
$pdf->Cell(10  ,10,'1','L,B,R',0,C);
$pdf->Cell(40  ,10,'2','B,R',0,C);
$pdf->Cell(10  ,10,'3','B,R',0,C);
$pdf->Cell(17  ,10,'4','B,R',0,C);
$pdf->Cell(17  ,10,'5','B,R',0,C);
$pdf->Cell(15  ,10,'6','B,R',0,C);
$pdf->Cell(26  ,10,'7','B,R',0,C);
$pdf->Cell(14  ,10,'8','B,R',0,C);
$pdf->Cell(14  ,10,'9 = 7+8 ','B,R',0,C);

$pdf->Cell(15  ,10,'10','B,R',0,C);
$pdf->Cell(15 ,10,'11','B,R',0,C);
$pdf->Cell(15  ,10,'12=11x5','B,R',0,C);
$pdf->Cell(17 ,10,'13=11+6','B,R',0,C);
$pdf->Cell(13  ,10,'14','B,R',0,C);
$pdf->Cell(13 ,10,'15','B,R',0,C);
$pdf->Cell(13  ,10,'16=15 x 5','B,R',0,C);
$pdf->Cell(13 ,10,'17','B,R',0,C);
$pdf->Cell(13  ,10,'18= 17 x 5','B,R',1,C);

while ($row = $nir_stmt->fetch(PDO::FETCH_ASSOC)){ 
$gestiune=$row['gestiune'];
$pprodus=$row['den_p'];
$produs=substr($pprodus, 0, 24);
   $pret_vanzare=$row['pret_vanzare'];
$codul_produsului=$row['cod_p'];
$um=$row['um'];
$cantitate_doc=$row['cantitate_doc'];
$cantitate_prim=$row['cantitate_prim'];
$pret_achiz=$row['pret_achiz'];
$valoare_fara_tva=$row['valoare_fara_tva'];
$c_tva=$row['cota_tva'];
$tva_ded=$row['tva_ded'];
$c_tva_vanz=$row['cota'];
$total=$valoare_fara_tva+$tva_ded;
$pret_fara_tva=$row['pret_vanzare_fara_tva'];
$ad_unit=$row['ad_unit'];
$valoare_adaos=$row['valoare_adaos'];
$proc_adaos=$row['proc_adaos'];
$tva_neex=$row['tva_neex'];
$tva_col_unitar=$row['tva_colect_unit'];
$tva_col_total=$tva_col_unitar*$cantitate_prim;
$valoare_vanzare=$row['valoare_vanzare'];
$id_achz=$row['id_achiz'];
$valoare_vanzare_c_tva=$row['valoare_vanzare_cu_tva'];
$pret_vanzare_final=$pret_fara_tva+$tva_col_unitar;
$total_vanzare_final=$pret_vanzare_final*$cantitate_prim;

if($gestiune=='PF')
{
    $PF=$PF+$valoare_fara_tva;
        $PF_vanzare=$PF_vanzare+$valoare_vanzare_c_tva;

}
elseif($gestiune=='MP')
{
       $MP=$MP+$valoare_fara_tva;
        $MP_vanzare=$MP_vanzare+$valoare_vanzare_c_tva;

    
}
elseif($gestiune=='MR')
{
    
        $MR=$MR+$valoare_fara_tva;
        
          if($c_tva==5){
        $MR_achizitie_c_5=$MR_achizitie_c_5+$valoare_fara_tva;
        }
        elseif($c_tva==9){
                    $MR_achizitie_c_9=$MR_achizitie_c_9+$valoare_fara_tva;

        }
 elseif($c_tva==19){
                    $MR_achizitie_c_19=$MR_achizitie_c_19+$valoare_fara_tva;

        }
    elseif($c_tva==0){
                    $MR_achizitie_c_0=$MR_achizitie_c_0+$valoare_fara_tva;

        }
        
        $MR_vanzare=$MR_vanzare+$valoare_vanzare_c_tva;

        if($c_tva_vanz==5){
        $MR_vanzare_c_5=$MR_vanzare_c_5+$valoare_vanzare_c_tva;
        }
        elseif($c_tva_vanz==9){
                    $MR_vanzare_c_9=$MR_vanzare_c_9+$valoare_vanzare_c_tva;

        }
 elseif($c_tva_vanz==19){
                    $MR_vanzare_c_19=$MR_vanzare_c_19+$valoare_vanzare_c_tva;

        }
    elseif($c_tva_vanz==0){
                    $MR_vanzare_c_0=$MR_vanzare_c_0+$valoare_vanzare_c_tva;

        }
}
elseif($gestiune=='MC')
{
    
        $MC=$MC+$valoare_fara_tva;
        $MC_vanzare=$MC_vanzare+$valoare_vanzare_c_tva;

}
elseif($gestiune=='MC2')
{
        $MC2=$MC2+$valoare_fara_tva;
        $MC2_vanzare=$MC2_vanzare+$valoare_vanzare_c_tva;

    
}
elseif($gestiune=='OB')
{
        $OB=$OB+$valoare_fara_tva;
        $OB_vanzare=$OB_vanzare+$valoare_vanzare_c_tva;

    
}


$pdf->Cell(10  ,5,$nr_crt1,'L,B,R',0,C);
$pdf->Cell(40  ,5,$produs,'B,R',0,C);
$pdf->Cell(10  ,5,$um,'B,R',0,C);
$pdf->Cell(17  ,5,$cantitate_doc,'B,R',0,C);
$pdf->Cell(17  ,5,$cantitate_prim,'B,R',0,C);
$pdf->Cell(15  ,5,$pret_achiz,'B,R',0,C);
$pdf->Cell(26  ,5,$valoare_fara_tva,'B,R',0,C);
$pdf->Cell(14  ,5,$tva_ded,'B,R',0,C);
$pdf->Cell(14  ,5,$total,'B,R',0,C);

$pdf->Cell(15  ,5,$proc_adaos,'B,R',0,C);
$pdf->Cell(15 ,5,$ad_unit,'B,R',0,C);
$pdf->Cell(15  ,5,$valoare_adaos,'B,R',0,C);
$pdf->Cell(17 ,5,$pret_fara_tva,'B,R',0,C);
$pdf->Cell(13  ,5,$tva_neex,'B,R',0,C);
$pdf->Cell(13 ,5,$tva_col_unitar,'B,R',0,C);
$pdf->Cell(13  ,5,$tva_col_total,'B,R',0,C);
$pdf->Cell(13 ,5,$pret_vanzare_final,'B,R',0,C);
$pdf->Cell(13  ,5,$total_vanzare_final,'B,R',1,C);





$nr_crt1++;}

 $nr_nir=$_SESSION['nr_nir'];

 
 $nr_nir=$_SESSION['nr_nir'];
 $nir_tot_sql = "SELECT sum($tabel_final_achizitii.valoare_fara_tva) as a from $tabel_final_achizitii  where nr_nir='$nr_nir'; ";    
$nir_tot_stmt = $pdo->prepare($nir_tot_sql);  
$nir_tot_stmt->execute();

while ($row = $nir_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$valoare_nir_f_tva=$row['a'];
}
   ?>
    
  
 <?php 
 $nr_nir=$_SESSION['nr_nir'];
 $nir_tot_sql = "SELECT sum($tabel_final_achizitii.tva_ded) as d from $tabel_final_achizitii  where nr_nir='$nr_nir'; ";    
$nir_tot_stmt = $pdo->prepare($nir_tot_sql);  
$nir_tot_stmt->execute();

while ($row = $nir_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_tva_ded=$row['d'];
}
  ?>
   
     <?php $t=$total_tva_ded+$valoare_nir_f_tva;
    ?>  
  

  
 <?php 
 $nr_nir=$_SESSION['nr_nir'];
 $nir_tot_sql = "SELECT sum($tabel_final_achizitii.valoare_adaos) as b from $tabel_final_achizitii  where nr_nir='$nr_nir'; ";    
$nir_tot_stmt = $pdo->prepare($nir_tot_sql);  
$nir_tot_stmt->execute();

while ($row = $nir_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$valoare_adaos_nir=$row['b'];
}
   ?>
   
    

  
 <?php 
 $nr_nir=$_SESSION['nr_nir'];
 $nir_tot_sql = "SELECT sum($tabel_final_achizitii.tva_neex) as e from $tabel_final_achizitii  where nr_nir='$nr_nir'; ";    
$nir_tot_stmt = $pdo->prepare($nir_tot_sql);  
$nir_tot_stmt->execute();

while ($row = $nir_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_tva_neex=$row['e'];
}
  
  


  ?>
   
  
  
      
  
  
   
           <?php 
 $nr_nir=$_SESSION['nr_nir'];
 $nir_tot_sql = "SELECT sum($tabel_final_achizitii.tva_colect_unit*$tabel_final_achizitii.cantitate_prim) as gg from $tabel_final_achizitii  where nr_nir='$nr_nir'; ";    
$nir_tot_stmt = $pdo->prepare($nir_tot_sql);  
$nir_tot_stmt->execute();

while ($row = $nir_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$tva_colect_unit=$row['gg'];
}
  
  


  ?>
         

 
  
  

 
  
  
 <?php 
 $nr_nir=$_SESSION['nr_nir'];
 $nir_tot_sql = "SELECT sum($tabel_final_achizitii.valoare_vanzare_cu_tva) as f from $tabel_final_achizitii  where nr_nir='$nr_nir'; ";    
$nir_tot_stmt = $pdo->prepare($nir_tot_sql);  
$nir_tot_stmt->execute();

while ($row = $nir_tot_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_val_vz_cu_tva=$row['f'];
}

 $nr_nir=$_SESSION['nr_nir'];
 $nir_cant_sql = "SELECT sum($tabel_final_achizitii.cantitate_doc) as g from $tabel_final_achizitii  where nr_nir='$nr_nir'; ";    
$nir_cant_stmt = $pdo->prepare($nir_cant_sql);  
$nir_cant_stmt->execute();

while ($row = $nir_cant_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_cant_doc=$row['g'];
}

 $nr_nir=$_SESSION['nr_nir'];
 $nir_cant_prim_sql = "SELECT sum($tabel_final_achizitii.cantitate_prim) as h from $tabel_final_achizitii  where nr_nir='$nr_nir'; ";    
$nir_cant_prim_stmt = $pdo->prepare($nir_cant_prim_sql);  
$nir_cant_prim_stmt->execute();

while ($row = $nir_cant_prim_stmt->fetch(PDO::FETCH_ASSOC)){
	$total_cant_prim=$row['h'];
}

$compar='';
if($total_cant_prim==$total_cant_doc){
    
    $compar="Nu s-au constatat diferente";
}


$pdf->Cell(94  ,5,'Total',1,0,C);
$pdf->Cell(15  ,5,'-',1,0,C);
$pdf->Cell(26  ,5,$valoare_nir_f_tva,1,0,C);
$pdf->Cell(14  ,5,$total_tva_ded,1,0,C);
$pdf->Cell(14  ,5,$t,1,0,C);
$pdf->Cell(15  ,5,'-',1,0,C);
$pdf->Cell(15  ,5,'-',1,0,C);
$pdf->Cell(15  ,5,$valoare_adaos_nir,1,0,C);

$pdf->Cell(17  ,5,'-',1,0,C);
$pdf->Cell(13  ,5,$total_tva_neex,1,0,C);
$pdf->Cell(13  ,5,'-',1,0,C);

$pdf->Cell(13  ,5,round($tva_colect_unit,2),1,0,C);
$pdf->Cell(13  ,5,'-',1,0,C);
$pdf->Cell(13  ,5,$total_val_vz_cu_tva,1,0,C);


$pdf->Cell(278  ,5,'',0,1);
$pdf->Cell(104  ,5,'Observatii:',0,0,R);
$pdf->Cell(186  ,5,$compar,1,1,L);
$pdf->Cell(278  ,5,'',0,1);

$pdf->Cell(90  ,5,'Numele si prenumele membrilor comisiei de receptie',1,0);
$pdf->Cell(49  ,5,'Semnatura',1,0);
$pdf->Cell(90  ,5,'Numele si prenumele gestionarului',1,0);
$pdf->Cell(49  ,5,'Semnatura',1,1);
$pdf->Cell(90  ,20,'',1,0);
$pdf->Cell(49  ,20,'',1,0);
$pdf->Cell(90  ,20,'',1,0);
$pdf->Cell(49  ,20,'',1,1);

if($PF!=0)
{
$pdf->Cell(278  ,5,'Gestiune produse finite : '. $PF .' lei',1,1);

}
if($MP!=0)
{
$pdf->Cell(278  ,5,'Gestiune materii prime : '. $MP.' lei',1,1);

}
if($MC!=0)
{
$pdf->Cell(278  ,5,'Gestiune materiale consumabile : '. $MC.' lei',1,1);

}
if($MC2!=0)
{
$pdf->Cell(278  ,5,'Gestiune materiale consumabile 2 : '. $MC2.' lei',1,1);

}
if($MR!=0)
{
$pdf->Cell(278  ,5,'Gestiune totala marfuri (achizitie): '. $MR.' lei',1,1);

$pdf->Cell(278  ,5,'Gestiune marfuri 5% (valoare achizitie) '.$MR_achizitie_c_5,1,1);
$pdf->Cell(278  ,5,'Gestiune marfuri 9% (valoare achizitie) '.$MR_achizitie_c_9,1,1);
$pdf->Cell(278  ,5,'Gestiune marfuri 19% (valoare achizitie) '.$MR_achizitie_c_19,1,1);
$pdf->Cell(278  ,5,'Gestiune marfuri 0% (valoare achizitie) '.$MR_achizitie_c_0,1,1);

$pdf->Cell(278  ,5,'Gestiune totala marfuri (vanzare): '. $MR_vanzare.' lei',1,1);

$pdf->Cell(278  ,5,'Gestiune marfuri 5% (valoare vanzare) '.$MR_vanzare_c_5,1,1);
$pdf->Cell(278  ,5,'Gestiune marfuri 9% (valoare vanzare) '.$MR_vanzare_c_9,1,1);
$pdf->Cell(278  ,5,'Gestiune marfuri 19% (valoare vanzare) '.$MR_vanzare_c_19,1,1);
$pdf->Cell(278  ,5,'Gestiune marfuri 0% (valoare vanzare) '.$MR_vanzare_c_0,1,1);

}
if($OB!=0)
{
$pdf->Cell(278  ,5,'Gestiune obiecte de inventar : '. $OB.' lei',1,1);

}


  

 
$pdf->Output();
?>

