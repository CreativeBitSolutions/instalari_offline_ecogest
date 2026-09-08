<?php
require_once __DIR__.'/database_connection.php';require_once __DIR__.'/offline_sync.php';$error='';
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
    try{
        $id=(int)($_POST['id_factura']??0);$action=(string)($_POST['action']??'');
        if($action==='retry'){$pdo->exec("UPDATE casa_outbox SET next_attempt=0 WHERE status='pending'");}
        elseif($action==='finalize'){$pdo->beginTransaction();$pdo->prepare("UPDATE casa_invoices SET finalized=1,state='queued' WHERE id_factura=? AND origin='local'")->execute([$id]);casa_snapshot($pdo,$id);casa_enqueue($pdo,$id);$pdo->commit();}
        elseif($action==='reopen'){
            $pending=casa_one($pdo,"SELECT 1 FROM casa_outbox WHERE id_factura=? AND status!='acked'",[$id]);
            if($pending)throw new RuntimeException('Așteptați confirmarea transmiterii înainte de redeschidere.');
            $pdo->prepare("UPDATE casa_invoices SET state='draft',finalized=0 WHERE id_factura=? AND state='synced' AND origin='local'")->execute([$id]);
        }
        header('Location: sincronizare.php');exit;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$error=$e->getMessage();}
}
include __DIR__.'/header.php';
$items=casa_rows($pdo,"SELECT f.id_factura,f.serie_factura,f.nr_factura,COALESCE(NULLIF(f.denumire,''),f.nume) AS denumire,c.* FROM facturi f JOIN casa_invoices c ON c.id_factura=f.id_factura WHERE c.state!='historical' ORDER BY f.id_factura DESC");
$labels=['draft'=>'În lucru','queued'=>'În coadă','synced'=>'Confirmată online'];
?><main class="container-fluid"><h1 class="h3">Sincronizare facturi</h1><p>Facturile în lucru rămân locale. Finalizarea pune documentul complet în coada automată. ANAF și trimiterea prin email se gestionează din aplicația online după confirmare.</p><p class="text-danger"><?=casa_h($error)?></p><form method="post" class="mb-3"><button class="btn btn-primary" name="action" value="retry">Reîncearcă transmiterile</button></form><table class="table table-striped bg-white"><thead><tr><th>Factură</th><th>Beneficiar</th><th>Stare</th><th>Detalii</th><th>Acțiuni</th></tr></thead><tbody>
<?php foreach($items as $item):?><tr><td><a href="factura.php?id_factura=<?=(int)$item['id_factura']?>"><?=casa_h($item['serie_factura'].' '.$item['nr_factura'])?></a></td><td><?=casa_h($item['denumire'])?></td><td><?=casa_h($labels[$item['state']]??$item['state'])?></td><td><?=casa_h($item['error'])?></td><td><form method="post"><input type="hidden" name="id_factura" value="<?=(int)$item['id_factura']?>"><?php if($item['state']==='draft'):?><button class="btn btn-success btn-sm" name="action" value="finalize">Finalizează și sincronizează</button><?php elseif($item['state']==='synced'):?><?php if($item['origin']==='local'):?><button class="btn btn-outline-primary btn-sm" name="action" value="reopen">Redeschide</button><?php endif;?> <a class="btn btn-light btn-sm" target="_blank" rel="noopener" href="<?=casa_h(casa_config()['online_base_url'])?>/detalii_factura.php?id_factura=<?=(int)$item['online_id']?>">Deschide online</a><?php endif;?></form></td></tr><?php endforeach;?></tbody></table>
<h2 class="h4 mt-4">Casa de marcat</h2>
<?php if(casa_one($pdo,"SELECT name FROM sqlite_master WHERE type='table' AND name='casa_fiscal_jobs'")):?>
<table class="table bg-white"><tr><th>Bon</th><th>Factură</th><th>Data</th><th>Stare scanner</th></tr>
<?php foreach(casa_rows($pdo,'SELECT j.*,f.serie_factura,f.nr_factura FROM casa_fiscal_jobs j JOIN facturi f ON f.id_factura=j.id_factura ORDER BY j.id DESC LIMIT 100') as $job):?><tr><td><?=(int)$job['bon_id']?></td><td><?=casa_h($job['serie_factura'].' '.$job['nr_factura'])?></td><td><?=casa_h($job['created_at'])?></td><td><?=casa_h($job['state']==='queued'?'În așteptarea scannerului':'Preluat de scanner, tipărire neconfirmată de aplicație')?></td></tr><?php endforeach;?></table>
<?php else:?><p>Nu există bonuri pregătite local.</p><?php endif;?>
<h2 class="h4 mt-4">Produse</h2><p>Actualizarea automată este făcută de AutoScannerul de produse, la intervalul configurat de 10 minute. Aplicația folosește nomenclatorul local când internetul lipsește.</p>
</main><?php include __DIR__.'/footer.php';?>
