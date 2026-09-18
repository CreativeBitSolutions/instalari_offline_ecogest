<?php session_start();
if (isset($_SESSION['gestiune'])) 
{

  
 $gestiune=$_SESSION['gestiune'];
   $data_start=$_SESSION['data_start'];
      $data_end=$_SESSION['data_end'];
include('database_connection.php');
require_once 'PHPExcel/Classes/PHPExcel.php';
//create PHPExcel object 
$excel = new PHPExcel();
//insert some data to PHPExcel object
$excel->setActiveSheetIndex(0);
$excel->getActiveSheet()
  ->setCellValueExplicit('A1','Produs')
    ->setCellValueExplicit('B1','Vanzari produs')
  ->setCellValueExplicit('C1','Total vanzari gestiune '.$gestiune)
    ->setCellValueExplicit('D1','Vanzari totale');

        
    $ssql="SELECT nomenclator_12.den_p,sum(valoare_vanzare) as total FROM det_note_12 inner join nomenclator_12 on nomenclator_12.cod_p=det_note_12.cod_p inner join note_12 on note_12.nrbon=det_note_12.nr_bon where nomenclator_12.gestiune='$gestiune' and note_12.data_bon >='$data_start' and note_12.data_bon<='$data_end' GROUP by nomenclator_12.den_p;";

$stmt = $pdo->prepare($ssql);
$stmt->execute();
$rand=2;
$vanzari_totale_gestiune=0;
  while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
      $den_p=$row['den_p'];
      $suma=$row['total'];
      $vanzari_totale_gestiune=$vanzari_totale_gestiune+$suma;
 $suma_cu_virgula=str_replace('.',',',$suma);

      $excel->getActiveSheet()
 ->setCellValueExplicit('A'.$rand , $den_p)
 ->setCellValueExplicit('B'.$rand , $suma_cu_virgula);

 $rand++;
  }
       $excel->getActiveSheet()
 ->setCellValueExplicit('C2' , $vanzari_totale_gestiune);
  
    $ssql2="SELECT sum(valoare_vanzare) as total_vanzari FROM det_note_12 inner join nomenclator_12 on nomenclator_12.cod_p=det_note_12.cod_p inner join note_12 on note_12.nrbon=det_note_12.nr_bon where note_12.data_bon >='$data_start' and note_12.data_bon<='$data_end'";

$stmt2 = $pdo->prepare($ssql2);
$stmt2->execute(); 
    while($row = $stmt2->fetch(PDO::FETCH_ASSOC)){
      $total_vanzari=$row['total_vanzari'];
        
    }
       $excel->getActiveSheet()
 ->setCellValueExplicit('D2' , $total_vanzari);
  
  

$nume_document='raport vanzari '. $data_start .'-'.$data_end .' gestiunea '.$gestiune;
//redirect to browser (download) instead of saving the result as a file
//this is for MS Office Excel 2007 xlsx format
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=$nume_document.xlsx");
//this is for MS Office Excel 2003 xls format
//header('Content-Type: application/vnd.ms-excel');
//header('Content-Disposition: attachment; filename="test.xlsx"');
header('Cache-Control: max-age=0');
//write the result to a file
//for excel 2007 format
$file = PHPExcel_IOFactory::createWriter($excel,'Excel2007');
//for excel 2003 format
//$file = PHPExcel_IOFactory::createWriter($excel,'Excel5');
//output to php output instead of filename
$file->save('php://output');

}
?>