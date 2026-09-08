from pathlib import Path
import re,shutil
ROOT=Path(__file__).resolve().parents[1]
APP=ROOT/'ecogest_offline_casaluanna'
SRC=Path('C:/xampp/htdocs/github/agecsin')
backup=ROOT/'backups/surse_initiale_v2'
backup.mkdir(parents=True,exist_ok=True)
names=set(__import__('json').loads((ROOT/'tools/source_inventory.json').read_text(encoding='utf-8')))
names.update(['duplicare_factura_corectare.php','preview_factura_stornare.php','descarca_xml_saga_factura.php'])
for name in sorted(names):
    if '/' in name and not name.startswith('proc/incasare'):continue
    p=APP/name
    if not p.exists():continue
    target=backup/name;target.parent.mkdir(parents=True,exist_ok=True)
    if not target.exists():shutil.copy2(p,target)
    text=p.read_text(encoding='utf-8-sig')
    text=text.replace("include('database_connection.php');","require_once __DIR__.'/database_connection.php';")
    text=text.replace("include('../database_connection.php');","require_once dirname(__DIR__).'/database_connection.php';")
    text=text.replace(' AS UNSIGNED',' AS INTEGER')
    text=text.replace("window.location.href = '/factura.php", "window.location.href = 'factura.php")
    text=text.replace("$q = intval($_GET['q']);", "$q = trim((string)$_GET['q']);")
    text=text.replace('SELECT um, pret_cu_tva, cota_tva FROM vanzari WHERE cod_produs = :cod_produs', 'SELECT um, pret_vanzare AS pret_cu_tva, cota_tva FROM vanzari WHERE cod_p = :cod_produs ORDER BY id_vanz DESC LIMIT 1')
    text=text.replace('cota_tva / 100)', 'cota_tva / 100.0)')
    text=text.replace('$prodcount = $pstmt3->rowCount();', '$existingProduct=$pstmt3->fetch(PDO::FETCH_ASSOC);\n        $prodcount = $existingProduct ? 1 : 0;').replace('$row = $pstmt3->fetch(PDO::FETCH_ASSOC);','$row = $existingProduct;')
    text=text.replace('if ($select_stmt->rowCount() > 0) {\n            $product = $select_stmt->fetch(PDO::FETCH_ASSOC);', 'if ($product = $select_stmt->fetch(PDO::FETCH_ASSOC)) {')
    text=text.replace('if($item_stmt->rowCount() > 0){\n                            while ($item = $item_stmt->fetch(PDO::FETCH_ASSOC)) {', '$invoiceItems=$item_stmt->fetchAll(PDO::FETCH_ASSOC);\n                        if(count($invoiceItems)>0){\n                            foreach ($invoiceItems as $item) {')
    text=text.replace('$numarVanzari = $verificareVanzariStmt->rowCount();','$numarVanzari = count($verificareVanzariStmt->fetchAll(PDO::FETCH_ASSOC));')
    text=text.replace('DESCRIBE clienti',"SELECT name AS Field FROM pragma_table_info('clienti')")
    text=text.replace('SHOW TABLES LIKE :table_name',"SELECT name FROM sqlite_master WHERE type='table' AND name=:table_name")
    text=text.replace('SHOW COLUMNS FROM `$tableName` LIKE :column_name',"SELECT name FROM pragma_table_info('$tableName') WHERE name=:column_name")
    text=re.sub(r'UPDATE vanzari\s+JOIN produse_servicii ON vanzari.den_p = produse_servicii.nume\s+SET vanzari.um = produse_servicii.um\s+WHERE vanzari.id_factura = :id_factura', 'UPDATE vanzari SET um = COALESCE((SELECT um FROM produse_servicii WHERE produse_servicii.nume=vanzari.den_p LIMIT 1),um) WHERE id_factura=:id_factura',text)
    # One jQuery/bootstrap/select2 instance is loaded in the shared header.
    if name in ['factura.php','facturi.php','detalii_factura.php']:
        text=re.sub(r'<script[^>]+src=[\'"][^\'"]*(?:jquery(?:-[\d.]+)?(?:\.slim)?(?:\.min)?\.js|bootstrap(?:\.bundle)?(?:\.min)?\.js|select2(?:\.min)?\.js|dataTables[^\'"]*\.js)[\'"][^>]*>\s*</script>','',text,flags=re.I)
    replacements={
      'https://cdnjs.cloudflare.com/ajax/libs/virtual-keyboard/1.30.1/css/keyboard.min.css':'css/dist/css/keyboard.min.css',
      'https://cdnjs.cloudflare.com/ajax/libs/virtual-keyboard/1.30.1/js/jquery.keyboard.min.js':'css/dist/js/jquery.keyboard.min.js',
      'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css':'vendor/offline/select2/select2.min.css',
      'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css':'vendor/offline/fontawesome5/css/all.min.css',
      'https://cdn.datatables.net/1.10.21/css/dataTables.bootstrap4.min.css':'vendor/offline/datatables/dataTables.bootstrap4.min.css',
      '//cdn.datatables.net/plug-ins/1.10.21/i18n/Romanian.json':'vendor/offline/datatables/ro.json'}
    for old,new in replacements.items():text=text.replace(old,new)
    if name=='factura.php':
        text=text.replace("include('header.php');", "include('header.php');\nif(isset($_GET['id_factura'])) { $cm=casa_one($pdo,'SELECT state FROM casa_invoices WHERE id_factura=?',[(int)$_GET['id_factura']]); if($cm && $cm['state']!=='draft'){ echo '<script>location.href=\"detalii_factura.php?id_factura='.(int)$_GET['id_factura'].'\"</script>'; exit; } }")
        text=text.replace('<!-- Begin Page Content -->', '<div class="container-fluid casa-actions"><a class="btn btn-success" href="sincronizare.php">Finalizare și sincronizare factură</a></div><!-- Begin Page Content -->')
    if name in ['duplicare_factura.php','stornare_factura.php','duplicare_factura_corectare.php']:
        text=text.replace('$new_factura = $old_factura;', "$new_factura = $old_factura;\n$new_factura['data_incarcare']='0000-00-00 00:00:00';\n$new_factura['data_validare']='0000-00-00 00:00:00';\n$new_factura['nrbon']=0;\n$new_factura['nr_nota']=0;\n$new_factura['factura_restaurant']=0;\n$new_factura['id_deviz']=0;\n$new_factura['data_stornare']=null;\n$new_factura['id_factura_stornare']=null;")
    if name=='stornare_factura.php':
        text=text.replace('$stmt_last = $pdo->query("SELECT MAX(nr_factura) AS last_nr FROM facturi");', "$stmt_last = $pdo->prepare('SELECT MAX(nr_factura) AS last_nr FROM facturi WHERE serie_factura=?');$stmt_last->execute([$serie_stornare]);")
        text=text.replace("$vanzare['nr_factura'] = $new_factura['nr_factura'];", "$vanzare['nr_factura'] = $new_factura['nr_factura'];$vanzare['serie_factura']=$serie_stornare;")
    p.write_text(text,encoding='utf-8')
for suffix in ['js','css']:
    shutil.copy2(ROOT.parent/'bestmixt/ecogest_offline_bestmixt/Data/vendor/datatables'/('dataTables.bootstrap4.'+suffix),APP/'vendor/offline/datatables'/('dataTables.bootstrap4.min.'+suffix))
if (SRC/'CBS_functions.js').exists():shutil.copy2(SRC/'CBS_functions.js',APP/'CBS_functions.js')
online=(SRC/'sincronizare_online_app_restaurant/sincronizare_date_offline.php').read_text(encoding='utf-8-sig')
online=online.replace('$pdo = null;',"if (defined('CASA_INVOICE_LIBRARY_ONLY') && CASA_INVOICE_LIBRARY_ONLY) { return; }\n\n$pdo = null;",1)
(ROOT/'online_de_publicat/sincronizare_date_offline.php').write_text(online,encoding='utf-8')
print('Legacy invoice sources adapted. Original copies retained in backups/surse_initiale_v2.')
