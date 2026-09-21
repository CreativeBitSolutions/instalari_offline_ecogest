import json
import re
import sqlite3
import sys
import unicodedata
from collections import Counter, defaultdict
from pathlib import Path


CURRENT_SQL = Path(r"C:\xampp\htdocs\github\agecsin\baze_date_clienti\u681731335_dailycoffee.sql")
OLD_SQL = Path(r"C:\Users\Mara\Desktop\baza_date_online_veche_19_09_2026.sql")
OFFLINE_DB = Path(r"C:\Users\Mara\Desktop\pos_cu_vanzari_extra.db")
OUT_JSON = Path(r"C:\xampp\htdocs\github\instalari_offline_ecogest\outputs\dailycoffee_location_analysis_20260921\analysis.json")


def read_text(path):
    return path.read_text(encoding="utf-8", errors="replace")


def find_statement(text, marker):
    start = text.find(marker)
    if start < 0:
        return None
    quote = False
    esc = False
    i = start
    while i < len(text):
        ch = text[i]
        if quote:
            if esc:
                esc = False
            elif ch == "\\":
                esc = True
            elif ch == "'":
                quote = False
        else:
            if ch == "'":
                quote = True
            elif ch == ";":
                return text[start : i + 1]
        i += 1
    return text[start:]


def split_top_level(text, delimiter=","):
    parts = []
    start = 0
    quote = False
    esc = False
    depth = 0
    for i, ch in enumerate(text):
        if quote:
            if esc:
                esc = False
            elif ch == "\\":
                esc = True
            elif ch == "'":
                quote = False
            continue
        if ch == "'":
            quote = True
        elif ch == "(":
            depth += 1
        elif ch == ")":
            depth -= 1
        elif ch == delimiter and depth == 0:
            parts.append(text[start:i].strip())
            start = i + 1
    parts.append(text[start:].strip())
    return parts


def parse_value(token):
    token = token.strip()
    if token.upper() == "NULL":
        return None
    if len(token) >= 2 and token[0] == "'" and token[-1] == "'":
        body = token[1:-1]
        body = body.replace("\\'", "'").replace("\\\\", "\\")
        body = body.replace("\\n", "\n").replace("\\r", "\r").replace("\\t", "\t")
        return body
    if re.fullmatch(r"-?\d+", token):
        return int(token)
    if re.fullmatch(r"-?(?:\d+\.\d*|\d*\.\d+)(?:[eE][+-]?\d+)?", token):
        return float(token)
    return token


def extract_tuples(values_sql):
    tuples = []
    i = 0
    while i < len(values_sql):
        while i < len(values_sql) and values_sql[i] != "(":
            i += 1
        if i >= len(values_sql):
            break
        begin = i + 1
        i += 1
        depth = 1
        quote = False
        esc = False
        while i < len(values_sql) and depth:
            ch = values_sql[i]
            if quote:
                if esc:
                    esc = False
                elif ch == "\\":
                    esc = True
                elif ch == "'":
                    quote = False
            else:
                if ch == "'":
                    quote = True
                elif ch == "(":
                    depth += 1
                elif ch == ")":
                    depth -= 1
            i += 1
        if depth == 0:
            tuples.append([parse_value(x) for x in split_top_level(values_sql[begin : i - 1])])
    return tuples


def table_columns(sql_text, table):
    marker = f"CREATE TABLE `{table}`"
    stmt = find_statement(sql_text, marker)
    if not stmt:
        return []
    open_pos = stmt.find("(")
    close_pos = stmt.rfind(")")
    body = stmt[open_pos + 1 : close_pos]
    columns = []
    for line in body.splitlines():
        match = re.match(r"\s*`([^`]+)`\s+", line)
        if match:
            columns.append(match.group(1))
    return columns


def table_rows(sql_text, table):
    pattern = re.compile(
        rf"INSERT INTO\s+`{re.escape(table)}`\s*(?:\((.*?)\))?\s+VALUES\s*(.*?);",
        re.IGNORECASE | re.DOTALL,
    )
    matches = list(pattern.finditer(sql_text))
    if not matches:
        return [], []
    rows = []
    columns = []
    for match in matches:
        explicit = match.group(1)
        if explicit:
            columns = [x.strip().strip("`") for x in split_top_level(explicit)]
        elif not columns:
            columns = table_columns(sql_text, table)
        for values in extract_tuples(match.group(2)):
            if columns and len(values) != len(columns):
                raise ValueError(f"{table}: columns={len(columns)} values={len(values)}")
            rows.append(dict(zip(columns, values)))
    return rows, columns


def load_sql(path):
    text = read_text(path)
    tables = {}
    for table in ["categorii", "categorii_locatii", "produse_servicii", "produse_servicii_locatii"]:
        rows, columns = table_rows(text, table)
        tables[table] = {"columns": columns, "rows": rows}
    return tables


def load_sqlite(path):
    connection = sqlite3.connect(path)
    connection.row_factory = sqlite3.Row
    result = {}
    for table in ["categorii", "produse_servicii"]:
        rows = [dict(row) for row in connection.execute(f"SELECT * FROM `{table}`")]
        result[table] = rows
    connection.close()
    return result


def norm(value):
    value = "" if value is None else str(value)
    value = unicodedata.normalize("NFKD", value)
    value = "".join(ch for ch in value if not unicodedata.combining(ch))
    return " ".join(value.upper().split())


def as_int(value, default=0):
    try:
        return int(value)
    except (TypeError, ValueError):
        return default


def as_float(value, default=0.0):
    try:
        return float(value)
    except (TypeError, ValueError):
        return default


def money(value):
    return round(as_float(value), 2)


def product_key(row):
    return as_int(row.get("cod_produs"))


def category_key(row):
    return as_int(row.get("id_categorie"))


def active(row):
    return as_int(row.get("activ")) == 1


def saleable(row):
    return active(row) and as_int(row.get("id_categorie")) not in {28, 29, 30, 31, 32}


def make_category_map(rows):
    return {category_key(row): row for row in rows}


def make_product_map(rows):
    return {product_key(row): row for row in rows}


def psl_map(rows):
    return {(as_int(row.get("cod_produs")), as_int(row.get("cod_locatie"))): as_int(row.get("activ")) for row in rows}


def offline_identifier(code):
    return f"client2_loc2_dailycoffee_produs_{code}"


def main():
    current = load_sql(CURRENT_SQL)
    old = load_sql(OLD_SQL)
    offline = load_sqlite(OFFLINE_DB)

    current_cats = make_category_map(current["categorii"]["rows"])
    old_cats = make_category_map(old["categorii"]["rows"])
    current_products = make_product_map(current["produse_servicii"]["rows"])
    old_products = make_product_map(old["produse_servicii"]["rows"])
    current_psl = psl_map(current["produse_servicii_locatii"]["rows"])
    old_psl = psl_map(old["produse_servicii_locatii"]["rows"])
    offline_cats = make_category_map(offline["categorii"])
    offline_products = make_product_map(offline["produse_servicii"])

    current_by_identifier = defaultdict(list)
    for row in current["produse_servicii"]["rows"]:
        identifier = row.get("identificator_offline")
        if identifier:
            current_by_identifier[str(identifier)].append(row)

    current_by_code = defaultdict(list)
    for row in current["produse_servicii"]["rows"]:
        current_by_code[product_key(row)].append(row)

    # The offline database contains local product codes. A location 2 product is
    # mapped to the online catalogue by identificator_offline when available.
    # If there is no identifier, a same-code and same-name row is only a direct
    # correspondence, not a replacement for an identifier-based mapping.
    offline_map = {}
    for code, row in offline_products.items():
        identifier_matches = current_by_identifier.get(offline_identifier(code), [])
        if identifier_matches:
            offline_map[code] = {
                "matches": identifier_matches,
                "mode": "identificator_offline",
                "candidate": identifier_matches[0],
            }
            continue
        same_code = current_by_code.get(code, [])
        same_name = [p for p in same_code if norm(p.get("nume")) == norm(row.get("nume"))]
        if same_name:
            offline_map[code] = {
                "matches": same_name,
                "mode": "cod_si_denumire",
                "candidate": same_name[0],
            }
        else:
            offline_map[code] = {
                "matches": [],
                "mode": "lipsă",
                "candidate": same_code[0] if same_code else None,
            }

    loc2_codes = {code for code, row in offline_products.items() if saleable(row)}
    loc2_online_codes = {
        product_key(info["candidate"])
        for code, info in offline_map.items()
        if saleable(offline_products[code]) and info["candidate"] is not None
    }
    loc2_missing = [
        code for code in sorted(loc2_codes)
        if not offline_map[code]["matches"]
    ]

    # Categories 33 to 39 were created after the 19.09 dump and are present in
    # the offline catalogue. They are treated as location 2 categories for this
    # audit. Products mapped by identificator_offline are also location 2 origin.
    loc2_only_category_ids = {
        cid for cid, row in current_cats.items()
        if cid not in old_cats and norm(row.get("den_categ")).startswith("A ")
    }
    loc1_products = [
        p for p in current["produse_servicii"]["rows"]
        if active(p)
        and not p.get("identificator_offline")
    ]
    loc2_products = [offline_products[code] for code in sorted(loc2_codes)]

    def loc1_status(row):
        cid = as_int(row.get("id_categorie"))
        code = product_key(row)
        if cid in loc2_only_category_ids:
            return "NU, categorie A nouă, rezervată locației 2"
        if code not in old_products:
            return "VERIFICARE, produs nou față de BD 19.09"
        if current_psl.get((code, 1)) == 0:
            return "NU, marcaj explicit inactiv la locația 1"
        return "DA, candidat locația 1"

    def loc2_allowed(row):
        code = product_key(row)
        return active(row) and current_psl.get((code, 2)) == 1

    # Build category sets from the source catalogues and product eligibility.
    loc1_cat_ids = set(old_cats)
    loc2_cat_ids = {as_int(p.get("id_categorie")) for p in loc2_products}

    current_categories = []
    for cid, row in sorted(current_cats.items()):
        current_categories.append({
            "id": cid,
            "name": row.get("den_categ", ""),
            "se_vinde": as_int(row.get("se_vinde")),
            "in_old": cid in old_cats,
            "in_offline": cid in offline_cats,
            "loc1_candidate_count": sum(1 for p in loc1_products if as_int(p.get("id_categorie")) == cid and loc1_status(p).startswith("DA")),
            "loc1_review_count": sum(1 for p in loc1_products if as_int(p.get("id_categorie")) == cid and loc1_status(p).startswith("NU")),
            "loc2_offline_count": sum(1 for p in loc2_products if as_int(p.get("id_categorie")) == cid),
            "old_active_count": sum(1 for p in old["produse_servicii"]["rows"] if active(p) and as_int(p.get("id_categorie")) == cid),
        })

    online_categories_by_norm = defaultdict(list)
    for row in current["categorii"]["rows"]:
        online_categories_by_norm[norm(row.get("den_categ"))].append(row)

    category_map_offline_to_current = {}
    for cid, row in offline_cats.items():
        category_map_offline_to_current[cid] = online_categories_by_norm.get(norm(row.get("den_categ")), [])

    loc1_category_rows = []
    for cid, row in sorted(current_cats.items()):
        current_name = row.get("den_categ", "")
        candidate_count = sum(1 for p in loc1_products if as_int(p.get("id_categorie")) == cid and loc1_status(p).startswith("DA"))
        review_count = sum(1 for p in loc1_products if as_int(p.get("id_categorie")) == cid and loc1_status(p).startswith("NU"))
        if cid in loc2_only_category_ids:
            status = "NU, categorie nouă după 19.09, locația 2"
        elif as_int(row.get("se_vinde")) != 1:
            status = "NU, se_vinde=0"
        elif candidate_count:
            status = "DA, categorie veche cu produse candidate"
        elif cid in old_cats:
            status = "VERIFICARE, exista vechi dar nu are produs candidat actual"
        else:
            status = "VERIFICARE, categorie actuală fără dovadă în BD veche"
        loc1_category_rows.append({
            "id_categorie": cid,
            "categorie": current_name,
            "se_vinde": as_int(row.get("se_vinde")),
            "există_în_bd_veche_19_09": "DA" if cid in old_cats else "NU",
            "există_în_offline": "DA" if cid in offline_cats else "NU",
            "produse_active_vechi": sum(1 for p in old["produse_servicii"]["rows"] if active(p) and as_int(p.get("id_categorie")) == cid),
            "produse_candidat_loc1": candidate_count,
            "produse_de_verificat_loc1": review_count,
            "produse_offline_loc2_categorie": sum(1 for p in loc2_products if as_int(p.get("id_categorie")) == cid),
            "status_recomandat_loc1": status,
            "observație": "Categoria nu exista în dump-ul din 19.09 și este prezentă în catalogul offline" if cid in loc2_only_category_ids else "",
        })

    loc2_category_rows = []
    for cid in sorted(loc2_cat_ids):
        off_cat = offline_cats.get(cid, {})
        online_matches = category_map_offline_to_current.get(cid, [])
        online_match = online_matches[0] if online_matches else None
        online_id = as_int(online_match.get("id_categorie")) if online_match else None
        category_products = [p for p in loc2_products if as_int(p.get("id_categorie")) == cid]
        mapped = [p for p in category_products if offline_map[product_key(p)]["matches"]]
        missing = [p for p in category_products if not offline_map[product_key(p)]["matches"]]
        observed_online_names = sorted({
            current_cats.get(as_int(offline_map[product_key(p)]["candidate"].get("id_categorie")), {}).get("den_categ", "")
            for p in mapped
            if offline_map[product_key(p)]["candidate"] is not None
        })
        loc2_category_rows.append({
            "id_categorie_offline": cid,
            "categorie_offline": off_cat.get("den_categ", ""),
            "id_categorie_online": online_id,
            "categorie_online": online_match.get("den_categ", "") if online_match else "[lipsă online]",
            "produse_active_offline": len(category_products),
            "produse_mapate_online": len(mapped),
            "produse_fără_corespondent_online": len(missing),
            "categorii_observate_pe_produsele_mapate": ", ".join(observed_online_names),
            "categorie_exista_în_bd_veche": "DA" if cid in old_cats else "NU",
            "se_vinde_offline": as_int(off_cat.get("se_vinde")),
            "status_recomandat_loc2": "Lipsă categorie online" if not online_match else ("Categorie loc2, verifică produsele nemapate" if missing else "Categorie loc2 mapată"),
            "observație": "Categorie pentru locația 2" + (", creată după dump-ul din 19.09" if cid not in old_cats else ""),
        })

    loc1_product_rows = []
    for p in sorted(loc1_products, key=lambda r: (norm(r.get("nume")), product_key(r))):
        code = product_key(p)
        cid = as_int(p.get("id_categorie"))
        cat = current_cats.get(cid, {})
        old_product = old_products.get(code, {})
        old_cat = old_cats.get(as_int(old_product.get("id_categorie")), {})
        loc1_product_rows.append({
            "cod_produs_online": code,
            "produs": p.get("nume", ""),
            "pret_cu_tva": money(p.get("pret_cu_tva")),
            "cota_tva": as_int(p.get("cota_tva")),
            "id_categorie_veche": as_int(old_product.get("id_categorie")) if old_product else None,
            "categorie_veche": old_cat.get("den_categ", "[produs nou]"),
            "id_categorie_actual": cid,
            "categorie_actuala": cat.get("den_categ", ""),
            "activ_online": as_int(p.get("activ")),
            "marcaj_loc1": current_psl.get((code, 1), "fără rând, activ implicit"),
            "marcaj_loc2": current_psl.get((code, 2), "fără rând"),
            "exista_în_bd_veche_19_09": "DA" if code in old_products else "NU",
            "identificator_offline": p.get("identificator_offline") or "",
            "status_recomandat_loc1": loc1_status(p),
            "observație": "Categoria actuală este nouă și provine din catalogul offline" if cid in loc2_only_category_ids else "",
        })

    loc2_product_rows = []
    for p in loc2_products:
        code = product_key(p)
        mapping = offline_map[code]
        match = mapping["candidate"] if mapping["matches"] else None
        candidate = mapping["candidate"]
        online_code = product_key(match) if match else None
        candidate_code = product_key(candidate) if candidate else None
        online_cat = current_cats.get(as_int(match.get("id_categorie"))) if match else None
        off_cat = offline_cats.get(as_int(p.get("id_categorie")), {})
        mode = mapping["mode"]
        price_diff = match is not None and money(match.get("pret_cu_tva")) != money(p.get("pret_cu_tva"))
        if not match:
            status_mapare = "Lipsă corespondent online"
        elif mode == "identificator_offline":
            status_mapare = "Mapare prin identificator_offline"
        elif price_diff:
            status_mapare = "Corespondent direct, dar preț diferit"
        else:
            status_mapare = "Corespondent direct, fără identificator"
        if not match:
            status_loc2 = "Trebuie creat sau verificat în online"
        elif not loc2_allowed(match):
            status_loc2 = "Corespondent găsit, dar marcajul loc2 nu este activ"
        else:
            status_loc2 = "Activ pentru locația 2"
        loc2_product_rows.append({
            "cod_produs_offline": code,
            "produs_offline": p.get("nume", ""),
            "pret_offline": money(p.get("pret_cu_tva")),
            "cota_tva_offline": as_int(p.get("cota_tva")),
            "id_categorie_offline": as_int(p.get("id_categorie")),
            "categorie_offline": off_cat.get("den_categ", ""),
            "cod_produs_online": online_code,
            "produs_online": match.get("nume", "") if match else "[lipsă online]",
            "cod_online_candidat": candidate_code,
            "produs_online_candidat": candidate.get("nume", "") if candidate and not match else "",
            "pret_online": money(match.get("pret_cu_tva")) if match else None,
            "cota_tva_online": as_int(match.get("cota_tva")) if match else None,
            "categorie_online": online_cat.get("den_categ", "") if online_cat else "[lipsă online]",
            "activ_online": as_int(match.get("activ")) if match else None,
            "marcaj_loc2": current_psl.get((online_code, 2), "lipsă") if match else "lipsă online",
            "marcaj_loc1": current_psl.get((online_code, 1), "fără rând" ) if match else "lipsă online",
            "status_mapare": status_mapare,
            "status_loc2": status_loc2,
        })

    # Diagnostic focused on the categories shown in the screenshot and on the old snapshot.
    category_names = sorted({row.get("den_categ", "") for row in current["categorii"]["rows"] if as_int(row.get("se_vinde")) == 1})
    old_visible_names = sorted({row.get("den_categ", "") for row in old["categorii"]["rows"] if as_int(row.get("se_vinde")) == 1})
    current_only_categories = sorted(set(category_names) - set(old_visible_names))

    result = {
        "sources": {
            "current_sql": str(CURRENT_SQL),
            "old_sql": str(OLD_SQL),
            "offline_db": str(OFFLINE_DB),
        },
        "counts": {
            "current_categories": len(current["categorii"]["rows"]),
            "old_categories": len(old["categorii"]["rows"]),
            "offline_categories": len(offline["categorii"]),
            "current_products": len(current["produse_servicii"]["rows"]),
            "old_products": len(old["produse_servicii"]["rows"]),
            "offline_products": len(offline["produse_servicii"]),
            "current_psl_rows": len(current["produse_servicii_locatii"]["rows"]),
            "old_psl_rows": len(old["produse_servicii_locatii"]["rows"]),
            "loc1_products": len(loc1_product_rows),
            "loc2_products": len(loc2_product_rows),
            "loc2_missing_online": len(loc2_missing),
        },
        "current_only_visible_categories": current_only_categories,
        "loc2_missing_online_codes": loc2_missing,
        "loc1_categories": loc1_category_rows,
        "loc1_products": loc1_product_rows,
        "loc2_categories": loc2_category_rows,
        "loc2_products": sorted(loc2_product_rows, key=lambda r: (norm(r.get("categorie_offline")), norm(r.get("produs_offline")), as_int(r.get("cod_produs_offline")))),
        "current_psl_by_location": {
            "loc1": Counter(str(v) for (code, loc), v in current_psl.items() if loc == 1),
            "loc2": Counter(str(v) for (code, loc), v in current_psl.items() if loc == 2),
        },
    }
    result["current_psl_by_location"] = {k: dict(v) for k, v in result["current_psl_by_location"].items()}
    OUT_JSON.write_text(json.dumps(result, ensure_ascii=False, indent=2), encoding="utf-8")

    print(json.dumps({
        "counts": result["counts"],
        "current_only_visible_categories": current_only_categories,
        "loc2_missing_online_codes": loc2_missing,
        "loc1_category_names": [r["categorie"] for r in loc1_category_rows],
        "loc2_category_names": [r["categorie_offline"] for r in loc2_category_rows],
        "current_psl_by_location": result["current_psl_by_location"],
    }, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
