<?php
require_once __DIR__.'/database_connection.php';
require_once __DIR__.'/offline_delete.php';
try {
    if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||!isset($_POST['exec_stergere']))throw new RuntimeException('Cerere de ștergere invalidă.');
    $id=(int)($_POST['id_fact_stergere']??0);
    $pdo->beginTransaction();casa_request_delete($pdo,$id);$pdo->commit();
    $meta=casa_one($pdo,'SELECT state FROM casa_invoices WHERE id_factura=?',[$id]);
    $_SESSION['message']=$meta['state']==='deleted'?'Factura offline a fost ștearsă și arhivată.':'Ștergerea este în așteptarea confirmării online. Factura rămâne blocată până la confirmare.';
    $_SESSION['message_type']='success';
} catch(Throwable $e) {
    if($pdo->inTransaction())$pdo->rollBack();
    $_SESSION['message']=$e->getMessage();$_SESSION['message_type']='danger';
}
header('Location: facturi.php');exit;
