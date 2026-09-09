<?php
require_once __DIR__.'/offline_runtime.php';
function casa_delete_local(PDO $db,int $id): void {
    // Called inside the deletion transaction. Preserve the document and dependencies.
    $archive=['facturi'=>casa_one($db,'SELECT * FROM facturi WHERE id_factura=?',[$id])];
    foreach(['vanzari','incasari','stornari','istoric_validari','istoric_incarcari','bonuri_casa_marcat'] as $table)$archive[$table]=casa_rows($db,'SELECT * FROM '.$table.' WHERE id_factura=?',[$id]);
    $archive['miscari']=casa_rows($db,'SELECT m.* FROM miscari m JOIN vanzari v ON v.id_vanz=m.id_vanz_fact WHERE v.id_factura=?',[$id]);
    $db->prepare('INSERT INTO casa_remote_archive(entity,local_id,payload,archived_at) VALUES(?,?,?,?)')->execute(['deleted_offline_invoice',$id,json_encode($archive,JSON_THROW_ON_ERROR),date('Y-m-d H:i:s')]);
    $db->prepare('DELETE FROM miscari WHERE id_vanz_fact IN (SELECT id_vanz FROM vanzari WHERE id_factura=?)')->execute([$id]);
    foreach(['vanzari','incasari','stornari','istoric_validari','istoric_incarcari','bonuri_casa_marcat','facturi'] as $table)$db->prepare('DELETE FROM '.$table.' WHERE id_factura=?')->execute([$id]);
    $db->prepare("UPDATE casa_invoices SET state='deleted',error='' WHERE id_factura=?")->execute([$id]);
}
function casa_request_delete(PDO $db,int $id): void {
    $meta=casa_one($db,'SELECT * FROM casa_invoices WHERE id_factura=?',[$id]);
    $invoice=casa_one($db,'SELECT * FROM facturi WHERE id_factura=?',[$id]);
    if(!$invoice||!$meta||$meta['origin']!=='local')throw new RuntimeException('Se pot șterge numai facturile create în această instalare offline.');
    if(casa_one($db,'SELECT 1 FROM istoric_incarcari WHERE id_factura=?',[$id])||(!empty($invoice['data_incarcare'])&&$invoice['data_incarcare']!=='0000-00-00 00:00:00'))throw new RuntimeException('Factura are o încărcare ANAF. Ștergerea nu este disponibilă.');
    $remote=casa_one($db,'SELECT payload FROM casa_remote_status WHERE id_factura=?',[$id]);$status=$remote?json_decode($remote['payload'],true):[];
    if(!empty($status['online_changed'])||($status['lifecycle']??'')==='online')throw new RuntimeException('Factura a fost corectată online. Ștergerea se gestionează din aplicația online.');
    if(!empty($status['anaf_sent'])||!empty($status['anaf']['index_incarcare']))throw new RuntimeException('Factura este transmisă la ANAF. Folosiți stornarea.');
    if(casa_one($db,'SELECT 1 FROM incasari WHERE id_factura=?',[$id])||casa_one($db,'SELECT 1 FROM bonuri_casa_marcat WHERE id_factura=?',[$id])||casa_one($db,'SELECT 1 FROM stornari WHERE id_factura_stornata=?',[$id]))throw new RuntimeException('Factura are încasări, bon fiscal sau o stornare asociată. Ștergerea este blocată.');
    if(casa_one($db,"SELECT name FROM sqlite_master WHERE type='table' AND name='casa_fiscal_jobs'")&&casa_one($db,'SELECT 1 FROM casa_fiscal_jobs WHERE id_factura=?',[$id]))throw new RuntimeException('Factura are un bon fiscal pregătit.');
    if((int)$invoice['nr_factura']>0&&casa_one($db,'SELECT 1 FROM facturi WHERE serie_factura=? AND nr_factura>?',[$invoice['serie_factura'],(int)$invoice['nr_factura']]))throw new RuntimeException('Ștergeți mai întâi factura cu numărul cel mai mare din această serie.');
    if((int)$meta['revision']===0&&empty($meta['online_id'])){casa_delete_local($db,$id);return;}
    $db->prepare("UPDATE casa_invoices SET state='delete_requested',error='' WHERE id_factura=?")->execute([$id]);
    require_once __DIR__.'/offline_sync.php';casa_enqueue($db,$id);
}
