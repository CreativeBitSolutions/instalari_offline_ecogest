from pathlib import Path
import json, shutil, re, importlib.util

ROOT = Path(__file__).resolve().parents[1]
SRC = Path('C:/xampp/htdocs/github/agecsin')
BASE = ROOT.parent/'bestmixt'
APP = ROOT/'ecogest_offline_casaluanna'/'admin'
API = ROOT/'api_offline_ecogest_casaluanna'
API.mkdir(exist_ok=True)
(API/'db_local').mkdir(exist_ok=True)
(API/'19/1').mkdir(parents=True, exist_ok=True)
for name in ['bonuri_backup','bonuri_trimise','online_de_publicat','backups']:
    (ROOT/name).mkdir(exist_ok=True)
for name in ['tcpdf','fpdf186','vendor','css','js','img']:
    if (SRC/name).is_dir():
        shutil.copytree(SRC/name, APP/name, dirs_exist_ok=True, ignore=shutil.ignore_patterns('*.log','.git','cache'))
shutil.copytree(BASE/'ecogest_offline_bestmixt/Data/vendor/offline', APP/'vendor/offline', dirs_exist_ok=True)
for name in ['ecogest_casa_marcat_v3_inp','ecogest_autoscanner_products_magazin_1_4_6_0']:
    shutil.copytree(BASE/name, ROOT/name, dirs_exist_ok=True, ignore=shutil.ignore_patterns('*.log','logs','Logs','*.bak','*.db','*.sqlite','*.json','*.lnk'))
    # Runtime/dependency manifests are program resources, not customer settings.
    for path in (BASE/name).rglob('*.json'):
        if path.name.endswith(('.runtimeconfig.json','.deps.json')):
            dest=ROOT/name/path.relative_to(BASE/name)
            dest.parent.mkdir(parents=True,exist_ok=True)
            shutil.copy2(path,dest)
def write_json(path, obj):
    path.write_text(json.dumps(obj, ensure_ascii=False, indent=2), encoding='utf-8')
origin=json.loads((BASE/'config_offline_bestmixt.json').read_text(encoding='utf-8-sig'))
config={
    'client_id':19,'cod_locatie_default':1,'driver':'sqlite',
    'db_runtime_file':str(API/'db_local/pos.db'),
    'api_root_absolute':str(API),
    'online_base_url':'https://agecs.agecs.in',
    'invoice_sync_url':'https://agecs.agecs.in/sincronizare_online_app_restaurant/sincronizare_facturi_offline.php',
    'api_key':'casaluanna33e2ea3a39d7936322364e268140274e8bf37297ede4878b',
    'online_products_sync':dict(origin.get('online_products_sync',{})),
    'ca_bundle_path':str(API/'cacert.pem')
}
config['online_products_sync'].update({'client_id':19,'api_key':config['api_key'],'enabled':True})
write_json(ROOT/'config_offline_casaluanna.json',config)
for path in (BASE/'api_offline_ecogest_bestmixt').rglob('*.pem'):
    if 'backup' not in str(path).lower():
        shutil.copy2(path,API/'cacert.pem')
        break
write_json(ROOT/'ecogest_autoscanner_products_magazin_1_4_6_0/aplicatie/settings.json',{
    'OfflineApiPath':str(API),'DatabasePath':str(API/'db_local/pos.db'),
    'ProductsApiUrl':'https://agecs.agecs.in/sincronizare_online_app_restaurant/sincronizare_date_offline.php',
    'ClientId':19,'ScanIntervalMinutes':10,'RequestTimeoutMinutes':15,
    'SchedulerEnabled':True,'RunImmediatelyOnLaunch':True,'StartWithWindows':False,'StartMinimized':False})
write_json(ROOT/'ecogest_casa_marcat_v3_inp/appsettings.json',{'ScanSettings':{
    'Url':'http://localhost/github/instalari_offline_ecogest/casaluanna/api_offline_ecogest_casaluanna/bonuri_de_trimis_casa_marcat.php','IntervalSeconds':3}})
write_json(ROOT/'ecogest_casa_marcat_v3_inp/config.json',{'client_id':'19','CasaMarcatFolder':'C:\\Fisco\\Bonuri','BackupFolder':str(ROOT/'bonuri_backup'),'LocationId':'1','Extensie':'inp'})
# Casa Luanna keeps its own scanner endpoint. Copy only the shared atomic helper,
# otherwise a later resource assembly would silently replace the client-specific
# JSON queue implementation with the Bestmixt endpoint.
for name in ['printer_queue_atomic_helper.php']:
    shutil.copy2(BASE/'api_offline_ecogest_bestmixt'/name,API/name)
for name in ['preview_factura_stornare.php','duplicare_factura_corectare.php','descarca_xml_saga_factura.php']:
    shutil.copy2(SRC/name,APP/name)
# Source-only SQL conversion. The SQLite connection is used to produce the installation artifact.
converter=BASE/'ecogest_offline_bestmixt/interfata_vanzare/tools/convert_mysql_dump_to_sqlite.py'
shutil.copy2(converter,ROOT/'tools/convert_mysql_dump_to_sqlite.py')
spec=importlib.util.spec_from_file_location('dump',converter)
dump=importlib.util.module_from_spec(spec)
spec.loader.exec_module(dump)
sqlpath=SRC/'baze_date_clienti/u681731335_casaluanna.sql'
sql=sqlpath.read_text(encoding='utf-8')
pk,ai=dump.collect_alter_metadata(sql)
blocks=list(dump.parse_create_blocks(sql))
schema=[]
for table, body in blocks:
    cols=[]
    for definition in dump.split_top_level_csv(body):
        m=re.match(r'`([^`]+)`\s+(.+)',definition.strip(),re.S)
        if not m: continue
        name,rest=m.groups()
        if ai.get(table)==name and pk.get(table)==[name]:
            cols.append(dump.qident(name)+' INTEGER PRIMARY KEY AUTOINCREMENT')
            continue
        typ=dump.sqlite_type(rest)
        dm=re.search(r"\bDEFAULT\s+('(?:\\.|[^'])*'|NULL|[-\d.]+|current_timestamp\(\))",rest,re.I)
        if dm:
            val=dm[1]
            if val.lower()=='current_timestamp()': val='CURRENT_TIMESTAMP'
        elif 'NOT NULL' in rest.upper():
            val='0' if typ in ['INTEGER','REAL'] else "''"
            if re.match('datetime|timestamp',rest,re.I): val="'0000-00-00 00:00:00'"
            elif re.match('date\\b',rest,re.I): val="'0000-00-00'"
        else: val='NULL'
        cols.append(dump.qident(name)+' '+typ+' DEFAULT '+val)
    if pk.get(table) and not any('PRIMARY KEY' in c for c in cols):
        cols.append('PRIMARY KEY ('+','.join(dump.qident(c) for c in pk[table])+')')
    schema.append('CREATE TABLE '+dump.qident(table)+' ('+','.join(cols)+');')
(ROOT/'tools/schema_from_dump.sql').write_text('\n'.join(schema),encoding='utf-8')
db=API/'db_local/pos.db'
if db.exists():
    raise SystemExit('Database exists. Refusing to replace it.')
import sqlite3
con=sqlite3.connect(db)
try:
    for statement in schema: con.execute(statement)
    counts,skipped=dump.import_inserts(sqlpath,con)
    if skipped: raise RuntimeError('Unparsed INSERT statements: '+str(skipped))
    con.commit()
finally: con.close()
print('Installation resources copied. Database created from supplied dump. Tables:',len(schema))
