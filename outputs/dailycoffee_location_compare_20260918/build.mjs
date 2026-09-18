import fs from "node:fs/promises";
import { spawnSync } from "node:child_process";
import { SpreadsheetFile, Workbook } from "@oai/artifact-tool";

const outputDir = "C:\\xampp\\htdocs\\github\\instalari_offline_ecogest\\outputs\\dailycoffee_location_compare_20260918";
const phpPath = "C:\\xampp\\php\\php.exe";
const analysisPath = "C:\\xampp\\htdocs\\github\\instalari_offline_ecogest\\outputs\\dailycoffee_location_compare_20260918\\analyze.php";
const outputPath = `${outputDir}\\dailycoffee_comparatie_locatii_20260918.xlsx`;
const sqlPath = `${outputDir}\\dailycoffee_actualizare_locatia_2_20260918.sql`;

const php = spawnSync(phpPath, [analysisPath], {
  encoding: "utf8",
  maxBuffer: 120 * 1024 * 1024,
});
if (php.error) throw php.error;
if (php.status !== 0) throw new Error(`Analiza PHP a eșuat: ${php.stderr || php.stdout}`);
const raw = (php.stdout || "").trim();
const data = JSON.parse(raw);

const toNumber = (value) => {
  if (value === null || value === undefined || value === "") return null;
  const n = Number(value);
  return Number.isFinite(n) ? n : null;
};
const codeOf = (row) => String(row?.cod_produs ?? row?.cod ?? "");
const priceOf = (row) => toNumber(row?.pret_cu_tva ?? row?.pret ?? row?.pret_vanzare);
const nameOf = (row) => String(row?.nume ?? row?.nume_produs ?? "");
const categoryOf = (row, categoryMap) => categoryMap.get(String(row?.id_categorie ?? "")) || "Fără categorie";
const normText = (value) => String(value ?? "").trim().toUpperCase().replace(/\s+/g, " ");
const sqlQuote = (value) => String(value ?? "").replaceAll("'", "''");
const numericCanonical = (value) => {
  const n = toNumber(value);
  return n === null ? "" : n.toFixed(6);
};
const columnName = (index) => {
  let n = index + 1;
  let result = "";
  while (n > 0) {
    const r = (n - 1) % 26;
    result = String.fromCharCode(65 + r) + result;
    n = Math.floor((n - 1) / 26);
  }
  return result;
};

const onlineProducts = data.online_products || [];
const offlineProducts = data.offline_products || [];
const onlineMaps = data.online_product_locations || [];
const offlineMaps = data.offline_product_locations || [];
const onlineCategories = data.online_categories || [];
const offlineCategories = data.offline_categories || [];
const categoryMap = new Map(onlineCategories.map((c) => [String(c.id_categorie), String(c.den_categ || "")]))
;
const offlineCategoryMap = new Map(offlineCategories.map((c) => [String(c.id_categorie), String(c.den_categ || "")]))
;
const onlineByCode = new Map(onlineProducts.map((p) => [codeOf(p), p]));
const offlineByCode = new Map(offlineProducts.map((p) => [codeOf(p), p]));
const onlineLoc2 = new Map();
for (const row of onlineMaps) {
  if (String(row.cod_locatie) === "2") onlineLoc2.set(codeOf(row), Number(row.activ) === 1 ? 1 : 0);
}
const offlineLoc2 = new Map();
for (const row of offlineMaps) {
  if (String(row.cod_locatie) === "2") offlineLoc2.set(codeOf(row), Number(row.activ) === 1 ? 1 : 0);
}

const duplicateGroups = [];
const grouped = new Map();
for (const row of data.rows || []) {
  const key = String(row._norm || "");
  if (!key) continue;
  if (!grouped.has(key)) grouped.set(key, []);
  grouped.get(key).push(row);
}

for (const [key, itemsRaw] of grouped.entries()) {
  const items = [...itemsRaw].sort((a, b) => Number(a._code) - Number(b._code));
  if (items.length < 2) continue;
  const loc1 = items[0];
  const loc2 = items[items.length - 1];
  const oldPrice = priceOf(loc1);
  const newPrice = priceOf(loc2);
  const oldCategory = categoryOf(loc1, categoryMap);
  const newCategory = categoryOf(loc2, categoryMap);
  const indicators = [];
  if (Number(loc2._code) > Number(loc1._code)) indicators.push("cod produs mai mare");
  if (newPrice !== null && oldPrice !== null && newPrice < oldPrice) indicators.push("pret mai mic pe codul nou");
  if (newPrice !== null && oldPrice !== null && newPrice === oldPrice) indicators.push("pret identic pe cele doua coduri");
  if (newCategory === "ALTELE" && oldCategory !== "ALTELE") indicators.push("categoria ALTELE pe codul nou");
  if (String(loc1.cod_bare || "") && !String(loc2.cod_bare || "")) indicators.push("cod de bare pe codul vechi");
  duplicateGroups.push({
    key,
    name: nameOf(loc1),
    loc1,
    loc2,
    oldCode: Number(loc1._code),
    newCode: Number(loc2._code),
    oldPrice,
    newPrice,
    oldCategory,
    newCategory,
    oldCurrentLoc2: onlineLoc2.get(codeOf(loc1)) || 0,
    newCurrentLoc2: onlineLoc2.get(codeOf(loc2)) || 0,
    indicators: indicators.join("; "),
  });
}
duplicateGroups.sort((a, b) => a.oldCode - b.oldCode);

const decisions = [];
for (const group of duplicateGroups) {
  decisions.push({
    code: group.oldCode,
    desired: 0,
    name: group.name,
    role: "Locatia 1",
    pairCode: group.newCode,
    current: group.oldCurrentLoc2,
    note: `cod vechi, ${group.indicators}`,
  });
  decisions.push({
    code: group.newCode,
    desired: 1,
    name: group.name,
    role: "Locatia 2",
    pairCode: group.oldCode,
    current: group.newCurrentLoc2,
    note: `cod nou, ${group.indicators}`,
  });
}
decisions.sort((a, b) => a.code - b.code);
const decisionByCode = new Map(decisions.map((d) => [String(d.code), d]));

const currentLoc2Codes = new Set([...onlineLoc2.entries()].filter(([, active]) => active === 1).map(([code]) => code));
const expectedLoc2Codes = new Set(currentLoc2Codes);
for (const decision of decisions) {
  if (decision.desired === 1) expectedLoc2Codes.add(String(decision.code));
  else expectedLoc2Codes.delete(String(decision.code));
}

const coreFields = ["nume", "pret_cu_tva", "activ", "id_categorie", "cod_bare", "um", "id_gestiune"];
const productDifferences = [];
for (const [code, online] of onlineByCode.entries()) {
  const offline = offlineByCode.get(code);
  if (!offline) continue;
  for (const field of coreFields) {
    const left = field === "pret_cu_tva" ? numericCanonical(online[field]) : normText(online[field]);
    const right = field === "pret_cu_tva" ? numericCanonical(offline[field]) : normText(offline[field]);
    if (left !== right) productDifferences.push({ code: Number(code), field, online: online[field] ?? "", offline: offline[field] ?? "" });
  }
}

const onlineLoc2Set = new Set([...onlineLoc2.entries()].filter(([, active]) => active === 1).map(([code]) => code));
const offlineLoc2Set = new Set([...offlineLoc2.entries()].filter(([, active]) => active === 1).map(([code]) => code));
const locationDifferences = [];
for (const code of new Set([...onlineLoc2.keys(), ...offlineLoc2.keys()])) {
  const left = onlineLoc2.get(code) || 0;
  const right = offlineLoc2.get(code) || 0;
  if (left !== right) locationDifferences.push({ code: Number(code), online: left, offline: right, name: nameOf(onlineByCode.get(code) || offlineByCode.get(code)) });
}

const onlyOfflineCodes = [...offlineByCode.keys()].filter((code) => !onlineByCode.has(code)).sort((a, b) => Number(a) - Number(b));
const onlyOnlineCodes = [...onlineByCode.keys()].filter((code) => !offlineByCode.has(code)).sort((a, b) => Number(a) - Number(b));

const sqlTable = "backup_dailycoffee_locatia2_disponibilitate_20260918";
const sqlValues = decisions.map((d) => `  (${d.code}, 2, ${d.desired}, '${sqlQuote(d.name)}', '${sqlQuote(d.role)}')`).join(",\n");
const sql = `-- Daily Coffee, client_id 2, marcaje disponibilitate locatie 2
-- Verifica rezultatul SELECT-ului de previzualizare inainte de COMMIT.
-- Scriptul nu modifica produse_servicii.activ, preturi, retete sau tranzactii.
USE u681731335_dailycoffee;

CREATE TEMPORARY TABLE tmp_dailycoffee_locatia2_decizii (
  cod_produs BIGINT NOT NULL PRIMARY KEY,
  cod_locatie BIGINT NOT NULL,
  activ_nou TINYINT NOT NULL,
  denumire_produs VARCHAR(255) NOT NULL,
  rol_recomandat VARCHAR(30) NOT NULL
) ENGINE=InnoDB;

INSERT INTO tmp_dailycoffee_locatia2_decizii
  (cod_produs, cod_locatie, activ_nou, denumire_produs, rol_recomandat)
VALUES
${sqlValues};

-- PREVIEW: randurile care urmeaza sa fie schimbate pe locatia 2.
SELECT
  d.cod_produs,
  p.nume,
  p.pret_cu_tva,
  COALESCE(psl.activ, 0) AS activ_actual,
  d.activ_nou,
  d.rol_recomandat
FROM tmp_dailycoffee_locatia2_decizii d
INNER JOIN produse_servicii p ON p.cod_produs = d.cod_produs
LEFT JOIN produse_servicii_locatii psl
  ON psl.cod_produs = d.cod_produs AND psl.cod_locatie = 2
WHERE COALESCE(psl.activ, 0) <> d.activ_nou
ORDER BY d.denumire_produs, d.cod_produs;

-- BACKUP separat, pastrat pentru revenire. INSERT IGNORE face scriptul rerulabil.
CREATE TABLE IF NOT EXISTS ${sqlTable} LIKE produse_servicii_locatii;

START TRANSACTION;

INSERT IGNORE INTO ${sqlTable}
SELECT psl.*
FROM produse_servicii_locatii psl
INNER JOIN tmp_dailycoffee_locatia2_decizii d
  ON d.cod_produs = psl.cod_produs AND d.cod_locatie = psl.cod_locatie
WHERE psl.cod_locatie = 2;

INSERT INTO produse_servicii_locatii (cod_produs, cod_locatie, activ)
SELECT cod_produs, cod_locatie, activ_nou
FROM tmp_dailycoffee_locatia2_decizii
ON DUPLICATE KEY UPDATE
  activ = VALUES(activ),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

-- VERIFICARE dupa actualizare.
SELECT
  d.cod_produs,
  p.nume,
  COALESCE(psl.activ, 0) AS activ_final,
  d.activ_nou AS activ_asteptat
FROM tmp_dailycoffee_locatia2_decizii d
INNER JOIN produse_servicii p ON p.cod_produs = d.cod_produs
LEFT JOIN produse_servicii_locatii psl
  ON psl.cod_produs = d.cod_produs AND psl.cod_locatie = 2
WHERE COALESCE(psl.activ, 0) <> d.activ_nou
ORDER BY d.denumire_produs, d.cod_produs;

SELECT COUNT(*) AS randuri_backup
FROM ${sqlTable};

DROP TEMPORARY TABLE tmp_dailycoffee_locatia2_decizii;
`;

await fs.writeFile(sqlPath, sql, "utf8");

const workbook = Workbook.create();
const summary = workbook.worksheets.add("Rezumat");
const loc1Sheet = workbook.worksheets.add("Locatia 1");
const loc2Sheet = workbook.worksheets.add("Locatia 2");
const pairsSheet = workbook.worksheets.add("Propuneri L2");
const compareSheet = workbook.worksheets.add("Comparatie BD");
const sqlSheet = workbook.worksheets.add("SQL actualizare");

const colors = {
  navy: "#17365D",
  blue: "#1F4E78",
  teal: "#0F766E",
  pale: "#EAF2F8",
  paleTeal: "#E6F4F1",
  paleGold: "#FFF4CC",
  paleRed: "#FCE8E6",
  text: "#1F2937",
  grid: "#D9E2F3",
  white: "#FFFFFF",
};
const font = "Aptos";

function titleBlock(sheet, title, subtitle, lastCol) {
  sheet.mergeCells(`A1:${lastCol}1`);
  sheet.getRange("A1").values = [[title]];
  sheet.getRange(`A1:${lastCol}1`).format = {
    fill: colors.navy,
    font: { name: font, size: 16, bold: true, color: colors.white },
    horizontalAlignment: "left",
    verticalAlignment: "center",
  };
  sheet.getRange(`A1:${lastCol}1`).format.rowHeight = 30;
  sheet.mergeCells(`A2:${lastCol}2`);
  sheet.getRange("A2").values = [[subtitle]];
  sheet.getRange(`A2:${lastCol}2`).format = {
    fill: colors.pale,
    font: { name: font, size: 10, color: colors.text },
    wrapText: true,
    verticalAlignment: "center",
  };
  sheet.getRange(`A2:${lastCol}2`).format.rowHeight = 32;
  sheet.showGridLines = false;
}

function headerStyle(range) {
  range.format = {
    fill: colors.blue,
    font: { name: font, size: 10, bold: true, color: colors.white },
    wrapText: true,
    verticalAlignment: "center",
    horizontalAlignment: "center",
    borders: { preset: "all", style: "thin", color: colors.grid },
  };
  range.format.rowHeight = 30;
}

function bodyStyle(range) {
  range.format = {
    font: { name: font, size: 10, color: colors.text },
    verticalAlignment: "center",
    borders: { preset: "all", style: "thin", color: colors.grid },
  };
}

function setWidths(sheet, widths, rows = 1000) {
  for (const [col, width] of Object.entries(widths)) {
    sheet.getRange(`${col}1:${col}${rows}`).format.columnWidth = width;
  }
}

function addTable(sheet, range, name, style = "TableStyleMedium2") {
  const table = sheet.tables.add(range, true, name);
  table.style = style;
  table.showFilterButton = true;
  return table;
}

titleBlock(summary, "Daily Coffee, comparație disponibilitate pe locații", "Client 2, locația offline 2. Sursa online este API-ul de produse, iar baza offline este pos.db. Raportul este construit pe datele citite la 18.09.2026.", "H");
summary.getRange("A4:B4").values = [["Indicator", "Valoare"]];
headerStyle(summary.getRange("A4:B4"));
const summaryRows = [
  ["Produse online, total", onlineProducts.length],
  ["Produse online, active global, cod produs > 0", onlineProducts.filter((p) => Number(p.activ) === 1 && Number(codeOf(p)) > 0).length],
  ["Produse offline, total", offlineProducts.length],
  ["Marcaje online active pentru locația 2", onlineLoc2Set.size],
  ["Marcaje offline active pentru locația 2", offlineLoc2Set.size],
  ["Diferențe de marcaj online versus offline", locationDifferences.length],
  ["Perechi cu denumire duplicată analizate", duplicateGroups.length],
  ["Coduri propuse fără locația 2", decisions.filter((d) => d.desired === 0).length],
  ["Coduri propuse cu locația 2", decisions.filter((d) => d.desired === 1).length],
  ["Schimbări efective față de marcajul actual", decisions.filter((d) => d.current !== d.desired).length],
  ["Marcaje locația 2 după SQL", expectedLoc2Codes.size],
];
summary.getRange(`A5:B${4 + summaryRows.length}`).values = summaryRows;
bodyStyle(summary.getRange(`A5:B${4 + summaryRows.length}`));
summary.getRange(`B5:B${4 + summaryRows.length}`).format.numberFormat = "#,##0";
addTable(summary, `A4:B${4 + summaryRows.length}`, "SummaryTable", "TableStyleMedium2");

summary.getRange("D4:H4").values = [["Regula de interpretare", "Detaliu", "", "", ""]];
summary.mergeCells("E4:H4");
headerStyle(summary.getRange("D4:H4"));
const notes = [
  ["Locația 1", "activ=1 în produse_servicii, fără schimbare prin SQL."],
  ["Locația 2", "produse_servicii_locatii cu cod_locatie=2 și activ=1."],
  ["Pereche analizată", "Denumire normalizată identică, cod nou mai mare și structură de preț/categorie compatibilă cu separarea pe locații."],
  ["Exemplul cerut", "FLAT WHITE, cod 250 la 12 lei, cod 925 la 10 lei. Propunerea este 250 fără locația 2 și 925 cu locația 2."],
  ["Siguranță", "SQL-ul modifică numai marcajele pentru cod_locatie=2. Nu modifică activ global, prețuri, rețete, NIR-uri sau tranzacții."],
  ["După rulare", "Se recomandă sincronizarea produselor în offline pentru preluarea noilor marcaje."],
];
summary.getRange(`D5:E${4 + notes.length}`).values = notes;
bodyStyle(summary.getRange(`D5:H${4 + notes.length}`));
summary.getRange(`E5:H${4 + notes.length}`).format.wrapText = true;
for (let r = 5; r <= 4 + notes.length; r++) summary.getRange(`E${r}:H${r}`).merge();
setWidths(summary, { A: 34, B: 18, C: 3, D: 25, E: 30, F: 20, G: 20, H: 20 }, 40);
summary.getRange("A18:H18").values = [["Surse", "", "", "", "", "", "", ""]];
summary.mergeCells("A18:H18");
summary.getRange("A18:H18").format = { fill: colors.teal, font: { name: font, size: 11, bold: true, color: colors.white } };
summary.getRange("A19:B21").values = [
  ["API online", "https://agecs.agecs.in/api/offline-products.php"],
  ["Bază offline", "pos.db, instalarea Daily Coffee locația 2"],
  ["SQL livrat", "dailycoffee_actualizare_locatia_2_20260918.sql"],
];
bodyStyle(summary.getRange("A19:B21"));
summary.getRange("B19:B21").format.wrapText = true;
addTable(summary, "A19:B21", "SourcesTable", "TableStyleMedium4");
summary.freezePanes.freezeRows(4);

const productHeaders = ["Cod produs", "Denumire", "Preț cu TVA", "Categorie", "Cod bare", "UM", "Gestiune ID", "Activ global", "Marcaj L2 actual", "Marcaj L2 după SQL", "Acțiune propusă", "Cod pereche"];
function productRowsForLocation1() {
  return onlineProducts
    .filter((p) => Number(p.activ) === 1 && Number(codeOf(p)) > 0)
    .sort((a, b) => nameOf(a).localeCompare(nameOf(b), "ro") || Number(codeOf(a)) - Number(codeOf(b)))
    .map((p) => {
      const code = codeOf(p);
      const decision = decisionByCode.get(code);
      const after = decision ? decision.desired : (onlineLoc2.get(code) || 0);
      return [
        Number(code), nameOf(p), priceOf(p), categoryOf(p, categoryMap), String(p.cod_bare || ""), String(p.um || ""),
        toNumber(p.id_gestiune), Number(p.activ) === 1 ? "Da" : "Nu", onlineLoc2.get(code) === 1 ? "Da" : "Nu",
        after === 1 ? "Da" : "Nu", decision ? (decision.desired === 0 ? "Dezactivează L2" : "Păstrează/activează L2") : "Fără schimbare", decision ? decision.pairCode : "",
      ];
    });
}
function productRowsForLocation2() {
  return [...onlineLoc2.entries()]
    .filter(([, active]) => active === 1)
    .map(([code]) => onlineByCode.get(code))
    .filter(Boolean)
    .sort((a, b) => nameOf(a).localeCompare(nameOf(b), "ro") || Number(codeOf(a)) - Number(codeOf(b)))
    .map((p) => {
      const code = codeOf(p);
      const decision = decisionByCode.get(code);
      const after = decision ? decision.desired : 1;
      return [
        Number(code), nameOf(p), priceOf(p), categoryOf(p, categoryMap), String(p.cod_bare || ""), String(p.um || ""),
        toNumber(p.id_gestiune), Number(p.activ) === 1 ? "Da" : "Nu", onlineLoc2.get(code) === 1 ? "Da" : "Nu",
        after === 1 ? "Da" : "Nu", decision ? (decision.desired === 0 ? "Elimină L2" : "Păstrează L2") : "Fără schimbare", decision ? decision.pairCode : "",
      ];
    });
}
function buildProductSheet(sheet, title, subtitle, rows, tableName, tabColor) {
  sheet.tabColor = tabColor;
  titleBlock(sheet, title, subtitle, "L");
  sheet.getRange("A4:L4").values = [productHeaders];
  headerStyle(sheet.getRange("A4:L4"));
  if (rows.length) sheet.getRange(`A5:L${4 + rows.length}`).values = rows;
  bodyStyle(sheet.getRange(`A5:L${4 + rows.length}`));
  sheet.getRange(`A5:A${4 + rows.length}`).format.numberFormat = "0";
  sheet.getRange(`C5:C${4 + rows.length}`).format.numberFormat = "0.00";
  sheet.getRange(`G5:G${4 + rows.length}`).format.numberFormat = "0";
  sheet.getRange(`A4:L${4 + rows.length}`).format.wrapText = true;
  sheet.getRange(`A5:L${4 + rows.length}`).format.rowHeight = 22;
  addTable(sheet, `A4:L${4 + rows.length}`, tableName, "TableStyleMedium2");
  sheet.getRange(`K5:K${4 + rows.length}`).conditionalFormats.add("containsText", { text: "Dezactivează", format: { fill: colors.paleRed, font: { color: "#991B1B", bold: true } } });
  sheet.getRange(`K5:K${4 + rows.length}`).conditionalFormats.add("containsText", { text: "Elimină", format: { fill: colors.paleRed, font: { color: "#991B1B", bold: true } } });
  sheet.getRange(`K5:K${4 + rows.length}`).conditionalFormats.add("containsText", { text: "Păstrează", format: { fill: colors.paleTeal, font: { color: "#065F46", bold: true } } });
  setWidths(sheet, { A: 12, B: 28, C: 14, D: 22, E: 20, F: 10, G: 12, H: 13, I: 15, J: 17, K: 22, L: 12 }, rows.length + 10);
  sheet.freezePanes.freezeRows(4);
}
buildProductSheet(loc1Sheet, "Produse pentru locația 1", "Lista folosește activarea globală din online. Coloana L2 după SQL arată efectul propunerii asupra marcajului locației 2, nu schimbă activarea globală.", productRowsForLocation1(), "Location1Products", colors.blue);
buildProductSheet(loc2Sheet, "Produse marcate pentru locația 2", "Lista reflectă marcajul online actual. Coloana L2 după SQL arată lista rezultată după aplicarea deciziilor din foaia SQL actualizare.", productRowsForLocation2(), "Location2Products", colors.teal);

titleBlock(pairsSheet, "Perechi de produse propuse pentru separarea pe locații", "Perechile sunt ordonate după codul vechi. Criteriul este practic și verificabil în nomenclator. Pentru produsele fără o pereche clară nu se propune nicio modificare.", "N");
const pairHeaders = ["Denumire", "Cod propus L1", "Preț L1", "Categorie L1", "Marcaj L2 actual L1", "Cod propus L2", "Preț L2", "Categorie L2", "Marcaj L2 actual L2", "L2 după SQL L1", "L2 după SQL L2", "Indicii observate", "Cod bare L1", "Cod bare L2"];
pairsSheet.getRange("A4:N4").values = [pairHeaders];
headerStyle(pairsSheet.getRange("A4:N4"));
const pairRows = duplicateGroups.map((g) => [
  g.name, g.oldCode, g.oldPrice, g.oldCategory, g.oldCurrentLoc2 ? "Da" : "Nu", g.newCode, g.newPrice, g.newCategory, g.newCurrentLoc2 ? "Da" : "Nu", "Nu", "Da", g.indicators, String(g.loc1.cod_bare || ""), String(g.loc2.cod_bare || ""),
]);
pairsSheet.getRange(`A5:N${4 + pairRows.length}`).values = pairRows;
bodyStyle(pairsSheet.getRange(`A5:N${4 + pairRows.length}`));
pairsSheet.getRange(`B5:B${4 + pairRows.length}`).format.numberFormat = "0";
pairsSheet.getRange(`C5:C${4 + pairRows.length}`).format.numberFormat = "0.00";
pairsSheet.getRange(`F5:F${4 + pairRows.length}`).format.numberFormat = "0";
pairsSheet.getRange(`G5:G${4 + pairRows.length}`).format.numberFormat = "0.00";
pairsSheet.getRange(`A4:N${4 + pairRows.length}`).format.wrapText = true;
addTable(pairsSheet, `A4:N${4 + pairRows.length}`, "LocationPairs", "TableStyleMedium4");
pairsSheet.getRange(`J5:K${4 + pairRows.length}`).conditionalFormats.add("containsText", { text: "Nu", format: { fill: colors.paleRed, font: { color: "#991B1B", bold: true } } });
pairsSheet.getRange(`J5:K${4 + pairRows.length}`).conditionalFormats.add("containsText", { text: "Da", format: { fill: colors.paleTeal, font: { color: "#065F46", bold: true } } });
setWidths(pairsSheet, { A: 27, B: 13, C: 12, D: 20, E: 16, F: 13, G: 12, H: 20, I: 16, J: 14, K: 14, L: 55, M: 18, N: 18 }, 40);
pairsSheet.freezePanes.freezeRows(4);

titleBlock(compareSheet, "Comparație baza online și baza offline", "Comparația produselor folosește codul produs ca identificator. Pentru câmpurile numerice se ignoră diferențele de format, de exemplu 12 și 12.00.", "H");
compareSheet.getRange("A4:C4").values = [["Verificare", "Rezultat", "Observație"]];
headerStyle(compareSheet.getRange("A4:C4"));
const commonProductCount = [...onlineByCode.keys()].filter((c) => offlineByCode.has(c)).length;
const compareRows = [
  ["Produse online", onlineByCode.size, "Rânduri primite prin API."],
  ["Produse offline", offlineByCode.size, "Rânduri în pos.db."],
  ["Produse comune", commonProductCount, "Identificate după cod produs."],
  ["Produse numai offline", onlyOfflineCodes.length, onlyOfflineCodes.length ? `Coduri: ${onlyOfflineCodes.join(", ")}` : "Niciunul."],
  ["Produse numai online", onlyOnlineCodes.length, onlyOnlineCodes.length ? `Coduri: ${onlyOnlineCodes.join(", ")}` : "Niciunul."],
  ["Diferențe pe câmpurile de bază", productDifferences.length, productDifferences.length ? "Vezi tabelul de mai jos." : "Nicio diferență după normalizarea formatului numeric."],
  ["Marcaje L2 online", onlineLoc2Set.size, "produse_servicii_locatii, cod_locatie=2, activ=1."],
  ["Marcaje L2 offline", offlineLoc2Set.size, "produse_servicii_locatii, cod_locatie=2, activ=1."],
  ["Diferențe de marcaj L2", locationDifferences.length, locationDifferences.length ? "Vezi tabelul de mai jos." : "Maparea online și offline este identică."],
  ["Categorii online", onlineCategories.length, "API online."],
  ["Categorii offline", offlineCategories.length, "Baza offline păstrează și categorii istorice."],
  ["Mapări categorie online", (data.online_category_locations || []).length, "API-ul nu a returnat rânduri pentru categorii pe locații."],
  ["Mapări categorie offline", (data.offline_category_locations || []).length, "Rândurile existente sunt pentru cod_locatie=1."],
];
compareSheet.getRange(`A5:C${4 + compareRows.length}`).values = compareRows;
bodyStyle(compareSheet.getRange(`A5:C${4 + compareRows.length}`));
compareSheet.getRange(`B5:B${4 + compareRows.length}`).format.numberFormat = "#,##0";
compareSheet.getRange(`A4:C${4 + compareRows.length}`).format.wrapText = true;
addTable(compareSheet, `A4:C${4 + compareRows.length}`, "ComparisonSummary", "TableStyleMedium2");

compareSheet.getRange("A20:E20").values = [["Cod produs", "Câmp", "Online", "Offline", "Denumire"]];
headerStyle(compareSheet.getRange("A20:E20"));
const diffRows = productDifferences.length
  ? productDifferences.map((d) => [d.code, d.field, d.online, d.offline, nameOf(onlineByCode.get(String(d.code)) || offlineByCode.get(String(d.code)))])
  : [["", "", "", "", "Nicio diferență pe câmpurile verificate"]];
compareSheet.getRange(`A21:E${20 + diffRows.length}`).values = diffRows;
bodyStyle(compareSheet.getRange(`A21:E${20 + diffRows.length}`));
addTable(compareSheet, `A20:E${20 + diffRows.length}`, "ProductDifferences", "TableStyleMedium4");

const locDiffStart = 24 + diffRows.length;
compareSheet.getRange(`A${locDiffStart}:D${locDiffStart}`).values = [["Cod produs", "Denumire", "Online L2", "Offline L2"]];
headerStyle(compareSheet.getRange(`A${locDiffStart}:D${locDiffStart}`));
const locDiffRows = locationDifferences.length
  ? locationDifferences.map((d) => [d.code, d.name, d.online ? "Da" : "Nu", d.offline ? "Da" : "Nu"])
  : [["", "Nicio diferență de marcaj pe locația 2", "", ""]];
compareSheet.getRange(`A${locDiffStart + 1}:D${locDiffStart + locDiffRows.length}`).values = locDiffRows;
bodyStyle(compareSheet.getRange(`A${locDiffStart + 1}:D${locDiffStart + locDiffRows.length}`));
addTable(compareSheet, `A${locDiffStart}:D${locDiffStart + locDiffRows.length}`, "LocationDifferences", "TableStyleMedium4");
setWidths(compareSheet, { A: 30, B: 22, C: 34, D: 34, E: 28, F: 4, G: 4, H: 4 }, locDiffStart + locDiffRows.length + 10);
compareSheet.freezePanes.freezeRows(4);

titleBlock(sqlSheet, "SQL actualizare marcaje locația 2", "Scriptul complet este livrat și ca fișier .sql separat. În această foaie fiecare rând păstrează câte o linie SQL pentru copiere și verificare.", "B");
sqlSheet.getRange("A4:B4").values = [["Nr.", "Linie SQL"]];
headerStyle(sqlSheet.getRange("A4:B4"));
const sqlLines = sql.split("\n").map((line) => [line]);
sqlSheet.getRange(`A5:A${4 + sqlLines.length}`).values = sqlLines.map((_, i) => [i + 1]);
sqlSheet.getRange(`B5:B${4 + sqlLines.length}`).values = sqlLines;
bodyStyle(sqlSheet.getRange(`A5:B${4 + sqlLines.length}`));
sqlSheet.getRange(`A5:A${4 + sqlLines.length}`).format.numberFormat = "0";
sqlSheet.getRange(`B5:B${4 + sqlLines.length}`).format.wrapText = true;
sqlSheet.getRange(`B5:B${4 + sqlLines.length}`).format.font = { name: "Consolas", size: 9, color: colors.text };
sqlSheet.getRange(`B5:B${4 + sqlLines.length}`).format.rowHeight = 18;
addTable(sqlSheet, `A4:B${4 + sqlLines.length}`, "SqlLines", "TableStyleMedium2");
setWidths(sqlSheet, { A: 8, B: 130 }, sqlLines.length + 10);
sqlSheet.freezePanes.freezeRows(4);

await fs.mkdir(outputDir, { recursive: true });
const workbookSummary = await workbook.inspect({
  kind: "sheet,table",
  maxChars: 6000,
  tableMaxRows: 5,
  tableMaxCols: 8,
  tableMaxCellChars: 80,
});
console.log(workbookSummary.ndjson);
const formulaErrors = await workbook.inspect({
  kind: "match",
  searchTerm: "#REF!|#DIV/0!|#VALUE!|#NAME\\?|#N/A|#NUM!|#NULL!|#SPILL!|#CALC!",
  options: { useRegex: true, maxResults: 300 },
  summary: "final formula error scan",
});
console.log(formulaErrors.ndjson);
const previews = [
  ["preview_rezumat.png", "Rezumat", "A1:H21"],
  ["preview_locatia1.png", "Locatia 1", "A1:L25"],
  ["preview_locatia2.png", "Locatia 2", "A1:L25"],
  ["preview_propune_l2.png", "Propuneri L2", "A1:N27"],
  ["preview_comparatie.png", "Comparatie BD", "A1:H35"],
  ["preview_sql.png", "SQL actualizare", "A1:B35"],
];
for (const [fileName, sheetName, range] of previews) {
  const preview = await workbook.render({ sheetName, range, scale: 1.2, format: "png" });
  await fs.writeFile(`${outputDir}\\${fileName}`, new Uint8Array(await preview.arrayBuffer()));
}
const xlsx = await SpreadsheetFile.exportXlsx(workbook);
await xlsx.save(outputPath);

const checks = {
  onlineProducts: onlineProducts.length,
  offlineProducts: offlineProducts.length,
  duplicateGroups: duplicateGroups.length,
  decisionRows: decisions.length,
  locationDifferences: locationDifferences.length,
  productDifferences: productDifferences.length,
  currentLoc2: currentLoc2Codes.size,
  expectedLoc2: expectedLoc2Codes.size,
  outputPath,
  sqlPath,
};
console.log(JSON.stringify(checks, null, 2));
