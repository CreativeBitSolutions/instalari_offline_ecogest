<?php
require_once __DIR__.'/database_connection.php';
try {
    if(($_SERVER['REQUEST_METHOD']??'')!=='POST')throw new RuntimeException('Metodă nepermisă.');
    $id=(int)($_POST['id_vanz']??0);$raw=str_replace(',','.',(string)($_POST['cantitate']??''));
    if(!is_numeric($raw)||(float)$raw==0)throw new RuntimeException('Cantitate invalidă.');
    $pdo->beginTransaction();$line=casa_one($pdo,'SELECT * FROM vanzari WHERE id_vanz=?',[$id]);
    if(!$line||(float)$line['cantitate']==0)throw new RuntimeException('Linia nu poate fi modificată.');
    $quantity=(float)$raw;$gross=round($quantity*(float)$line['pret_vanzare']-(float)$line['discount'],2);
    $tax=round($gross*(float)$line['cota_tva']/(100+(float)$line['cota_tva']),2);
    $pdo->prepare('UPDATE vanzari SET cantitate=?,valoare_vanzare=?,tva_col=?,valoare_vanzare_cu_tva=? WHERE id_vanz=?')->execute([$quantity,$gross-$tax,$tax,$gross,$id]);
    $ratio=$quantity/(float)$line['cantitate'];
    $pdo->prepare('UPDATE miscari SET cantitate_misc=cantitate_misc*?,valoare_achizitie=valoare_achizitie*?,valoare_vanzare=valoare_vanzare*? WHERE id_vanz_fact=?')->execute([$ratio,$ratio,$ratio,$id]);
    $pdo->commit();header('Location: factura.php?id_factura='.(int)$line['id_factura']);exit;
} catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();casa_json(['success'=>false,'error'=>$e->getMessage()],422);}
