import argparse
import re
import sqlite3
from pathlib import Path


TYPE_INTEGER = re.compile(r"\b(tinyint|smallint|mediumint|int|bigint|bool|boolean)\b", re.I)
TYPE_REAL = re.compile(r"\b(decimal|double|float|real)\b", re.I)


def qident(name: str) -> str:
    return '"' + name.replace('"', '""') + '"'


def split_top_level_csv(text: str) -> list[str]:
    parts = []
    current = []
    depth = 0
    in_string = False
    escape = False
    for ch in text:
        if in_string:
            current.append(ch)
            if escape:
                escape = False
            elif ch == "\\":
                escape = True
            elif ch == "'":
                in_string = False
            continue

        if ch == "'":
            in_string = True
            current.append(ch)
        elif ch == "(":
            depth += 1
            current.append(ch)
        elif ch == ")":
            depth -= 1
            current.append(ch)
        elif ch == "," and depth == 0:
            parts.append("".join(current).strip())
            current = []
        else:
            current.append(ch)

    if current:
        parts.append("".join(current).strip())
    return parts


def mysql_string_unescape(value: str) -> str:
    out = []
    i = 0
    while i < len(value):
        ch = value[i]
        if ch != "\\" or i + 1 >= len(value):
            out.append(ch)
            i += 1
            continue
        nxt = value[i + 1]
        mapping = {
            "0": "\0",
            "b": "\b",
            "n": "\n",
            "r": "\r",
            "t": "\t",
            "Z": "\x1a",
            "\\": "\\",
            "'": "'",
            '"': '"',
        }
        out.append(mapping.get(nxt, nxt))
        i += 2
    return "".join(out)


def parse_scalar(token: str):
    token = token.strip()
    if token.upper() == "NULL":
        return None
    if token.startswith("'") and token.endswith("'"):
        return mysql_string_unescape(token[1:-1])
    if token.upper().startswith("_UTF8MB4"):
        inner = token.split(" ", 1)[-1].strip()
        if inner.startswith("'") and inner.endswith("'"):
            return mysql_string_unescape(inner[1:-1])
    if token.upper().startswith("B'") and token.endswith("'"):
        try:
            return int(token[2:-1], 2)
        except ValueError:
            return token
    try:
        if re.match(r"^[+-]?\d+$", token):
            return int(token)
        if re.match(r"^[+-]?(\d+\.\d*|\d*\.\d+)([eE][+-]?\d+)?$", token) or re.match(r"^[+-]?\d+[eE][+-]?\d+$", token):
            return float(token)
    except ValueError:
        return token
    return token


def iter_insert_rows(values_sql: str):
    i = 0
    n = len(values_sql)
    while i < n:
        while i < n and values_sql[i] in " \r\n\t,":
            i += 1
        if i >= n:
            break
        if values_sql[i] != "(":
            i += 1
            continue
        i += 1
        row = []
        current = []
        in_string = False
        escape = False
        depth = 0
        while i < n:
            ch = values_sql[i]
            if in_string:
                current.append(ch)
                if escape:
                    escape = False
                elif ch == "\\":
                    escape = True
                elif ch == "'":
                    in_string = False
                i += 1
                continue

            if ch == "'":
                in_string = True
                current.append(ch)
                i += 1
            elif ch == "(":
                depth += 1
                current.append(ch)
                i += 1
            elif ch == ")" and depth > 0:
                depth -= 1
                current.append(ch)
                i += 1
            elif ch == "," and depth == 0:
                row.append(parse_scalar("".join(current)))
                current = []
                i += 1
            elif ch == ")" and depth == 0:
                row.append(parse_scalar("".join(current)))
                i += 1
                yield row
                break
            else:
                current.append(ch)
                i += 1


def collect_alter_metadata(sql: str):
    pk_map = {}
    ai_map = {}
    for table, cols in re.findall(r"ALTER TABLE\s+`([^`]+)`\s+ADD PRIMARY KEY\s*\(([^)]+)\)", sql, re.I):
        pk_map[table] = re.findall(r"`([^`]+)`", cols)
    for table, col in re.findall(r"ALTER TABLE\s+`([^`]+)`\s+MODIFY\s+`([^`]+)`\s+[^\n;]*AUTO_INCREMENT", sql, re.I):
        ai_map[table] = col
    return pk_map, ai_map


def sqlite_type(mysql_type: str) -> str:
    cleaned = mysql_type.lower()
    if TYPE_INTEGER.search(cleaned):
        return "INTEGER"
    if TYPE_REAL.search(cleaned):
        return "REAL"
    if "blob" in cleaned:
        return "BLOB"
    return "TEXT"


def create_table_sql(table: str, body: str, pk_map: dict[str, list[str]], ai_map: dict[str, str]) -> str:
    definitions = split_top_level_csv(body)
    cols = []
    pk_cols = pk_map.get(table, [])
    ai_col = ai_map.get(table)
    single_ai_pk = len(pk_cols) == 1 and ai_col == pk_cols[0]

    for definition in definitions:
        item = definition.strip().rstrip(",")
        if not item.startswith("`"):
            continue
        m = re.match(r"`([^`]+)`\s+(.+)$", item, re.S)
        if not m:
            continue
        name, rest = m.group(1), m.group(2)
        if single_ai_pk and name == ai_col:
            cols.append(f"{qident(name)} INTEGER PRIMARY KEY AUTOINCREMENT")
        else:
            cols.append(f"{qident(name)} {sqlite_type(rest)}")

    if not single_ai_pk and pk_cols:
        existing = {c.split(" ", 1)[0].strip('"').replace('""', '"') for c in cols}
        valid_pk = [c for c in pk_cols if c in existing]
        if valid_pk:
            cols.append("PRIMARY KEY (" + ",".join(qident(c) for c in valid_pk) + ")")

    if not cols:
        raise ValueError(f"No columns parsed for table {table}")
    return f"CREATE TABLE {qident(table)} (\n  " + ",\n  ".join(cols) + "\n)"


def parse_create_blocks(sql: str):
    pattern = re.compile(r"CREATE TABLE\s+`([^`]+)`\s*\((.*?)\)\s*ENGINE\s*=", re.I | re.S)
    for match in pattern.finditer(sql):
        yield match.group(1), match.group(2)


def read_insert_statement(first_line: str, handle):
    chunks = [first_line]
    if first_line.rstrip().endswith(";"):
        return "".join(chunks)
    for line in handle:
        chunks.append(line)
        if line.rstrip().endswith(";"):
            break
    return "".join(chunks)


def import_inserts(sql_path: Path, con: sqlite3.Connection):
    insert_re = re.compile(r"INSERT INTO\s+`([^`]+)`\s*\((.*?)\)\s+VALUES\s*(.*);?\s*$", re.I | re.S)
    counts = {}
    skipped = 0
    with sql_path.open("r", encoding="utf-8", errors="replace") as handle:
        for line in handle:
            if not line.startswith("INSERT INTO"):
                continue
            statement = read_insert_statement(line, handle).strip()
            if statement.endswith(";"):
                statement = statement[:-1]
            m = insert_re.match(statement)
            if not m:
                skipped += 1
                continue
            table = m.group(1)
            cols = re.findall(r"`([^`]+)`", m.group(2))
            values_sql = m.group(3)
            placeholders = ",".join("?" for _ in cols)
            query = f"INSERT OR REPLACE INTO {qident(table)} ({','.join(qident(c) for c in cols)}) VALUES ({placeholders})"
            rows = list(iter_insert_rows(values_sql))
            if not rows:
                continue
            con.executemany(query, rows)
            counts[table] = counts.get(table, 0) + len(rows)
    return counts, skipped


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--sql", required=True)
    parser.add_argument("--db", required=True)
    args = parser.parse_args()

    sql_path = Path(args.sql)
    db_path = Path(args.db)
    db_path.parent.mkdir(parents=True, exist_ok=True)
    if db_path.exists():
        db_path.unlink()

    sql_text = sql_path.read_text(encoding="utf-8", errors="replace")
    pk_map, ai_map = collect_alter_metadata(sql_text)
    create_blocks = list(parse_create_blocks(sql_text))

    con = sqlite3.connect(db_path)
    con.execute("PRAGMA journal_mode = OFF")
    con.execute("PRAGMA synchronous = OFF")
    con.execute("PRAGMA foreign_keys = OFF")
    try:
        for table, body in create_blocks:
            con.execute(create_table_sql(table, body, pk_map, ai_map))
        con.commit()

        counts, skipped = import_inserts(sql_path, con)
        con.commit()

        print(f"tables_created={len(create_blocks)}")
        print(f"insert_tables={len(counts)}")
        print(f"rows_inserted={sum(counts.values())}")
        print(f"insert_statements_skipped={skipped}")
        for name in sorted(counts):
            print(f"{name}={counts[name]}")
    finally:
        con.close()


if __name__ == "__main__":
    main()
