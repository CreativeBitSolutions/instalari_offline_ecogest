from pathlib import Path
import re, shutil, json

ROOT = Path(__file__).resolve().parents[1]
SRC = Path('C:/xampp/htdocs/github/agecsin')
APP = ROOT / 'ecogest_offline_casaluanna'
APP.mkdir(exist_ok=True)
seeds = '''facturi.php factura.php detalii_factura.php update_factura.php get_next_nr_factura.php factura_get_client_data.php get_produs_factura.php add_product.php delete_item.php set_zero_item.php modifica_cantitate_factura.php modifica_um_factura.php modifica_pret_factura.php duplicare_factura.php stornare_factura.php stergere_factura.php listeaza_fact.php listeaza_fact_deviz_simplificat.php raport_facturi.php raport_facturi_excel.php genereaza_bon.php proc/incasare_factura.php country_utils.php save_step.php widget_touch.php'''.split()
custom = set('header.php footer.php database_connection.php index.php login.php conectare.php logout.php anaf_xml_artifacts.php'.split())
online = set('anaf_validare.php istoric_validari.php sterge_validari.php anaf_incarcare.php istoric_incarcari.php anaf_xml_genereaza.php anaf_xml_previzualizare.php email_factura.php store_mesaje_anaf.php get_company_data.php factura_importa_pvi.php'.split())
pending = seeds[:]
seen = set()
while pending:
    name = pending.pop(0).lstrip('/')
    if name in seen or name in custom or name in online:
        continue
    path = SRC / name
    if not path.is_file():
        continue
    seen.add(name)
    body = path.read_text(encoding='utf-8-sig')
    dest = APP / name
    dest.parent.mkdir(parents=True, exist_ok=True)
    dest.write_text(body, encoding='utf-8')
    # Follow PHP includes only. Navigation and linked actions are inventoried separately.
    for match in re.finditer(r'(?:include|require)(?:_once)?\s*\(?\s*(?:__DIR__\s*\.\s*)?[\'\"]([^\'\"]+\.php)[\'\"]', body):
        child = match[1].lstrip('/')
        if child not in custom and child not in online:
            if (SRC / child).is_file():
                pending.append(child)
            elif (path.parent / child).is_file():
                pending.append(str((path.parent / child).relative_to(SRC)).replace('\\', '/'))
inventory = {}
for name in sorted(seen):
    body = (APP/name).read_text(encoding='utf-8')
    inventory[name] = sorted(set(re.findall(r'[\'\"/]([a-zA-Z0-9_/-]+\.php)', body)))
(ROOT/'tools/source_inventory.json').write_text(json.dumps(inventory, ensure_ascii=False, indent=2), encoding='utf-8')
print('Copied', len(seen), 'PHP sources')
print('\n'.join(sorted(seen)))
