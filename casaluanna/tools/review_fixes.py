from pathlib import Path
import shutil,json,re
ROOT=Path(__file__).resolve().parents[1];APP=ROOT/'ecogest_offline_casaluanna';API=ROOT/'api_offline_ecogest_casaluanna'
def save(p,t):
    if p.exists():
        base=ROOT/'backups'/p.name;i=2
        while base.with_name(base.stem+'_v'+str(i)+base.suffix).exists():i+=1
        shutil.copy2(p,base.with_name(base.stem+'_v'+str(i)+base.suffix))
    p.write_text(t,encoding='utf-8')
def edit(name,old,new):
    p=APP/name;t=p.read_text(encoding='utf-8')
    if old not in t:raise RuntimeError(name+' anchor missing: '+old[:50])
    save(p,t.replace(old,new))
edit('genereaza_bon.php',"($meta['state']??'')==='historical'", "($meta['origin']??'')==='historical'")
edit('sincronizare.php','<button class="btn btn-outline-primary btn-sm" name="action" value="reopen">Redeschide</button>', '<?php if($item[\'origin\']===\'local\'):?><button class="btn btn-outline-primary btn-sm" name="action" value="reopen">Redeschide</button><?php endif;?>')
edit('update_factura.php',"if ($key != 'id_factura') {", "if ($key != 'id_factura' && in_array($key, array_column($pdo->query(\"PRAGMA table_info('facturi')\")->fetchAll(PDO::FETCH_ASSOC),'name'),true) && !in_array($key,['data_validare','data_incarcare','data_corectare','id_factura_stornare'],true) && !is_array($value)) {")
edit('modifica_pret_factura.php','SELECT id_factura, cod_p, cantitate, cota_tva','SELECT id_factura, cod_p, cantitate, cota_tva, discount')
edit('modifica_pret_factura.php','round($pret_vanzare_nou * $cantitate, 2)','round($pret_vanzare_nou * $cantitate - (float)$row[\'discount\'], 2)')
edit('modifica_cantitate_factura.php','SELECT pret_vanzare, cota_tva, id_factura','SELECT pret_vanzare, cota_tva, id_factura, discount, cantitate')
edit('modifica_cantitate_factura.php','$valoare_vanzare_cu_tva = $pret_vanzare * $cantitate;', "$valoare_vanzare_cu_tva = round($pret_vanzare * $cantitate - (float)$product['discount'],2);")
edit('modifica_cantitate_factura.php','$tva_col = $valoare_vanzare_cu_tva * $cota_tva / (100 + $cota_tva);','$tva_col = round($valoare_vanzare_cu_tva * $cota_tva / (100 + $cota_tva),2);')
edit('modifica_cantitate_factura.php','SET cantitate_misc = :cantitate_misc', 'SET cantitate_misc = cantitate_misc * :cantitate_misc')
edit('modifica_cantitate_factura.php',"':cantitate_misc' => $cantitate,", "':cantitate_misc' => (float)$product['cantitate'] != 0 ? $cantitate/(float)$product['cantitate'] : 0,")
edit('offline_sync.php',"$storno['offline_reference']=$ref;", "$ref['online_id']=$ref['origin']==='historical'?$ref['online_id']:null;$storno['offline_reference']=$ref;")
edit('database_connection.php',"basename($_SERVER['SCRIPT_NAME']??'')==='factura.php'", "in_array(basename($_SERVER['SCRIPT_NAME']??''),['factura.php','duplicare_factura_corectare.php'],true)")
p=ROOT/'config_offline_casaluanna.json';c=json.loads(p.read_text(encoding='utf-8'));c['online_products_sync']['cod_client']=19;c['online_products_sync']['client_id']=19;c['online_products_sync']['dry_run']=False
save(p,json.dumps(c,ensure_ascii=False,indent=2))
save(ROOT/'.htaccess','<FilesMatch "^config_offline.*\\.json$|\\.(sql|sqlite|db|log|bak|exop|py|ps1)$">\nRequire all denied\n</FilesMatch>\n')
for name in ['ecogest_autoscanner_products_magazin_1_4_6_0','ecogest_casa_marcat_v3_inp']:(ROOT/name/'.htaccess').write_text('Require all denied\n',encoding='utf-8')
for name in ['offline_runtime.php','offline_schema.php','offline_fiscal.php']:shutil.copy2(APP/name,API/name)
print('Static review corrections applied.')
