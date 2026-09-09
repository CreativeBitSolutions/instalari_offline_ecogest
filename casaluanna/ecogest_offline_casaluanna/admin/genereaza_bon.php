<?php
require_once __DIR__.'/database_connection.php';require_once __DIR__.'/offline_fiscal.php';
$id=(int)($_GET['id_factura']??0);$invoice=casa_one($pdo,'SELECT * FROM facturi WHERE id_factura=?',[$id]);
if(!$invoice){http_response_code(404);exit('Factura nu există.');}
$meta=casa_one($pdo,'SELECT * FROM casa_invoices WHERE id_factura=?',[$id]);
if(casa_invoice_readonly($pdo,$id)){http_response_code(409);exit('Factura se gestionează din aplicația online. Copia offline este doar pentru consultare.');}
$total=(float)$pdo->query('SELECT COALESCE(SUM(valoare_vanzare_cu_tva),0) FROM vanzari WHERE id_factura='.$id)->fetchColumn();
$payments=$_POST['metode_plata']??[['tip'=>'0','valoare'=>number_format($total,2,'.','')]];
$cui=isset($_POST['sterge_cui'])?'':trim((string)($_POST['cui']??$invoice['cod_fiscal']));$preview='';$error='';$message='';
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
    try{
        $preview=casa_fiscal_buffer($pdo,$id,is_array($payments)?$payments:[],$cui);
        if(isset($_POST['confirm_send'])){$job=casa_create_fiscal($pdo,$id,$preview);$message='Bonul '.$job.' este înregistrat pentru preluarea de către scanner. Nu reprezintă confirmarea tipăririi fiscale.';$preview='';}
    }catch(Throwable $e){$error=$e->getMessage();}
}
include __DIR__.'/header.php';
?><main class="container-fluid"><h1 class="h3">Bon fiscal, <?=casa_h($invoice['serie_factura'].' '.$invoice['nr_factura'])?></h1><p class="text-danger"><?=casa_h($error)?></p><p class="text-success"><?=casa_h($message)?></p><p>Total <?=number_format($total,2,',','.')?> lei</p>
<?php if($message===''):?><form method="post"><div id="payments"><?php foreach($payments as $i=>$payment):?><div class="form-row mb-2"><div class="col"><select class="form-control" name="metode_plata[<?=(int)$i?>][tip]"><?php foreach(['Numerar','Card','Credit','Tichete masă','Tichete valorice','Voucher','Plată modernă','Carduri de fidelitate','Alte metode','Monedă străină'] as $type=>$label):?><option value="<?=$type?>" <?=((string)$type===(string)$payment['tip']?'selected':'')?>><?=casa_h($label)?></option><?php endforeach;?></select></div><div class="col"><input class="form-control" name="metode_plata[<?=(int)$i?>][valoare]" type="number" min="0" step="0.01" required value="<?=casa_h($payment['valoare'])?>"></div></div><?php endforeach;?></div><button class="btn btn-light mb-3" type="button" onclick="addPayment()">Adaugă metodă de plată</button><label class="d-block">CUI<input class="form-control" name="cui" value="<?=casa_h($cui)?>"></label><label><input type="checkbox" name="sterge_cui"> Fără CUI pe bon</label><br>
<?php if($preview!==''):?><pre class="bg-white p-3"><?=casa_h($preview)?></pre><button class="btn btn-success" name="confirm_send" value="1">Confirmă trimiterea la scanner</button><?php else:?><button class="btn btn-primary">Previzualizare bon</button><?php endif;?></form><?php endif;?><a class="btn btn-light mt-3" href="detalii_factura.php?id_factura=<?=$id?>">Înapoi la factură</a></main>
<script>function addPayment(){const box=document.getElementById('payments');const row=box.firstElementChild.cloneNode(true);const i=box.children.length;row.querySelectorAll('[name]').forEach(x=>x.name=x.name.replace(/\[\d+\]/,'['+i+']'));row.querySelector('input').value='0.00';box.appendChild(row);}</script><?php include __DIR__.'/footer.php';?>
