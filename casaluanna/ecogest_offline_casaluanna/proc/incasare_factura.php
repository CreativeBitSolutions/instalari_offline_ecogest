<?php
require_once dirname(__DIR__).'/database_connection.php';require_once dirname(__DIR__).'/offline_sync.php';
try{
    if(($_SERVER['REQUEST_METHOD']??'')!=='POST')throw new RuntimeException('Metodă nepermisă.');
    $id=(int)($_POST['nr_factura_incasata']??0);$sum=str_replace(',','.',(string)($_POST['suma_incasata']??''));
    $date=(string)($_POST['data_incasare']??'');$meta=casa_one($pdo,'SELECT * FROM casa_invoices WHERE id_factura=?',[$id]);
    if(!$meta||!is_numeric($sum)||(float)$sum<=0||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))throw new RuntimeException('Datele încasării nu sunt valide.');
    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO incasari(id_factura,suma,tip_doc,data_incasarii,nr_doc_incasare,serie_doc_incasare) VALUES(?,?,?,?,?,?)')->execute([$id,round((float)$sum,2),(string)($_POST['tip_doc_incasare']??''),$date,(string)($_POST['nr_doc_incasare']??''),'']);
    if($meta['state']!=='draft')casa_enqueue($pdo,$id);
    $pdo->commit();$_SESSION['message']='Încasarea a fost salvată local.';$_SESSION['message_type']='success';
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$_SESSION['message']=$e->getMessage();$_SESSION['message_type']='danger';}
header('Location: ../facturi.php');
