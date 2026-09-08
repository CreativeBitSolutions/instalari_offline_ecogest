from pathlib import Path
import re,shutil,json
ROOT=Path(__file__).resolve().parents[1];APP=ROOT/'ecogest_offline_casaluanna';API=ROOT/'api_offline_ecogest_casaluanna'
def edit(name,old,new):
    p=APP/name;t=p.read_text(encoding='utf-8');
    if old not in t:raise RuntimeError('Missing edit anchor: '+name+' '+old[:50])
    backup=ROOT/'backups'/name.replace('/','_');i=2
    while backup.with_name(backup.stem+'_v'+str(i)+backup.suffix).exists():i+=1
    shutil.copy2(p,backup.with_name(backup.stem+'_v'+str(i)+backup.suffix))
    p.write_text(t.replace(old,new),encoding='utf-8')
edit('offline_sync.php','ORDER BY m.id_miscare','ORDER BY m.id')
edit('database_connection.php',"if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'||basename($_SERVER['SCRIPT_NAME']??'')==='factura.php'){", "if(!isset($GLOBALS['casa_write_lock'])&& (($_SERVER['REQUEST_METHOD']??'GET')==='POST'||basename($_SERVER['SCRIPT_NAME']??'')==='factura.php')){")
edit('save_step.php','session_start();',"require_once __DIR__.'/database_connection.php';")
edit('set_zero_item.php','SET pret_vanzare = 0,','SET pret_vanzare = 0, discount = 0,')
edit('get_company_data.php',"],404);", "]);" )
aliases='anaf_validare.php istoric_validari.php sterge_validari.php anaf_incarcare.php istoric_incarcari.php anaf_xml_genereaza.php anaf_xml_previzualizare.php email_factura.php store_mesaje_anaf.php factura_importa_pvi.php'.split()
for name in aliases:(APP/name).write_text("<?php require __DIR__.'/online_action.php';\n",encoding='utf-8')
# Runtime dependencies for the local scanner remain external to the compiled executable.
for name in ['offline_runtime.php','offline_schema.php','offline_fiscal.php']:
    shutil.copy2(APP/name,API/name)
for path in [ROOT/'backups',ROOT/'tools',ROOT/'online_de_publicat',API/'db_local']:
    (path/'.htaccess').write_text('Require all denied\n',encoding='utf-8')
(ROOT/'.htaccess').write_text('<FilesMatch "\\.(json|sql|sqlite|db|log|bak|exop|py|ps1)$">\nRequire all denied\n</FilesMatch>\n',encoding='utf-8')
(API/'.htaccess').write_text('Require local\n<FilesMatch "\\.(json|sqlite|db|log|lock|preluat)$">\nRequire all denied\n</FilesMatch>\n',encoding='utf-8')
(ROOT/'Admin Login.url').write_text('[InternetShortcut]\nURL=http://localhost/github/instalari_offline_ecogest/casaluanna/ecogest_offline_casaluanna/login.php\n',encoding='utf-8')
# Include only runtime application sources and resources, never the database or API key.
manifest=[str(p.relative_to(APP)).replace('\\','/') for p in APP.rglob('*') if p.is_file()]
(ROOT/'FISIERE_PENTRU_COMPILARE.txt').write_text('Punct de intrare: index.php\nFolder surse: ecogest_offline_casaluanna\nPHP necesar: PDO_SQLite, cURL, mbstring, XML, ZIP, GD.\nBaza, cheia API și endpointurile locale rămân externe.\n\n'+'\n'.join(sorted(manifest)),encoding='utf-8')
print('External runtime, action routes and compilation manifest prepared.')
