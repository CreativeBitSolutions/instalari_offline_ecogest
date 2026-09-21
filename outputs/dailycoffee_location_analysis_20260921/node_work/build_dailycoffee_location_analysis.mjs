import fs from "node:fs/promises";
import { SpreadsheetFile, Workbook } from "@oai/artifact-tool";

const outputDir = "C:/xampp/htdocs/github/instalari_offline_ecogest/outputs/dailycoffee_location_analysis_20260921";
const jsonPath = `${outputDir}/analysis.json`;
const xlsxPath = `${outputDir}/dailycoffee_categorii_produse_locatii_20260921.xlsx`;
const data = JSON.parse(await fs.readFile(jsonPath, "utf8"));

const workbook = Workbook.create();
const fontFamily = "Aptos";
const navy = "#1F4E78";
const blue = "#D9EAF7";
const light = "#F6F8FA";
const green = "#E2F0D9";
const yellow = "#FFF2CC";
const red = "#FCE4D6";

function colLetter(index) {
  let n = index;
  let result = "";
  while (n > 0) {
    const rem = (n - 1) % 26;
    result = String.fromCharCode(65 + rem) + result;
    n = Math.floor((n - 1) / 26);
  }
  return result;
}

function cleanValue(value) {
  return value === null || value === undefined ? "" : value;
}

function matrixFromRows(headers, rows) {
  return [headers, ...rows.map((row) => headers.map((header) => cleanValue(row[header])))];
}

function addStatusFormatting(sheet, columnNumber, firstDataRow, lastDataRow) {
  if (lastDataRow < firstDataRow) return;
  const range = sheet.getRange(`${colLetter(columnNumber)}${firstDataRow}:${colLetter(columnNumber)}${lastDataRow}`);
  range.conditionalFormats.add("containsText", {
    text: "DA",
    format: { fill: green, font: { color: "#006100" } },
  });
  range.conditionalFormats.add("containsText", {
    text: "NU",
    format: { fill: red, font: { color: "#9C0006" } },
  });
  range.conditionalFormats.add("containsText", {
    text: "Lipsă",
    format: { fill: red, font: { color: "#9C0006" } },
  });
  range.conditionalFormats.add("containsText", {
    text: "VERIFICARE",
    format: { fill: yellow, font: { color: "#7F6000" } },
  });
  range.conditionalFormats.add("containsText", {
    text: "Trebuie",
    format: { fill: red, font: { color: "#9C0006" } },
  });
}

function addSheet({ name, title, subtitle, rows, headers, tableName, tabColor, widths, statusHeaders, moneyHeaders = [] }) {
  const sheet = workbook.worksheets.add(name);
  sheet.tabColor = tabColor;
  sheet.showGridLines = false;

  const tableData = matrixFromRows(headers, rows);
  const lastColumn = colLetter(headers.length);
  const lastRow = 6 + rows.length;
  const tableRange = `A6:${lastColumn}${lastRow}`;

  for (const rowNumber of [1, 2, 3, 4]) {
    sheet.mergeCells(`A${rowNumber}:${lastColumn}${rowNumber}`);
  }
  sheet.getRange("A1").values = [[title]];
  sheet.getRange("A2").values = [[subtitle]];
  sheet.getRange("A3").values = [["Surse: baza_date_online_veche_19_09_2026.sql, u681731335_dailycoffee.sql actualizat, pos_cu_vanzari_extra.db"]];
  sheet.getRange("A4").values = [["Rândurile au caracter de audit. Statusurile nu modifică baza de date."]];
  sheet.getRange("A6").values = tableData;

  const titleRange = sheet.getRange(`A1:${lastColumn}1`);
  titleRange.format = { fill: navy, font: { name: fontFamily, size: 15, bold: true, color: "#FFFFFF" }, horizontalAlignment: "left" };
  titleRange.format.rowHeight = 28;
  const noteRange = sheet.getRange(`A2:${lastColumn}4`);
  noteRange.format = { fill: light, font: { name: fontFamily, size: 10, color: "#44546A" }, wrapText: true, horizontalAlignment: "left" };
  noteRange.format.rowHeight = 22;
  const headerRange = sheet.getRange(`A6:${lastColumn}6`);
  headerRange.format = {
    fill: navy,
    font: { name: fontFamily, size: 10, bold: true, color: "#FFFFFF" },
    wrapText: true,
    horizontalAlignment: "center",
    verticalAlignment: "center",
  };
  headerRange.format.rowHeight = 42;

  const table = sheet.tables.add(tableRange, true, tableName);
  table.style = "TableStyleMedium2";
  table.showFilterButton = true;
  table.showBandedColumns = false;

  const bodyRange = sheet.getRange(`A7:${lastColumn}${lastRow}`);
  bodyRange.format.font = { name: fontFamily, size: 9 };
  bodyRange.format.verticalAlignment = "center";
  bodyRange.format.borders = { preset: "inside", style: "thin", color: "#D9E1F2" };

  for (const [header, width] of Object.entries(widths)) {
    const index = headers.indexOf(header) + 1;
    if (index > 0) {
      const range = sheet.getRange(`${colLetter(index)}6:${colLetter(index)}${lastRow}`);
      range.format.columnWidth = width;
    }
  }
  for (const header of moneyHeaders) {
    const index = headers.indexOf(header) + 1;
    if (index > 0 && rows.length > 0) {
      sheet.getRange(`${colLetter(index)}7:${colLetter(index)}${lastRow}`).format.numberFormat = "0.00";
    }
  }
  for (const header of ["observație", "observație", "status_recomandat_loc1", "status_recomandat_loc2", "status_mapare", "status_loc2"]) {
    const index = headers.indexOf(header) + 1;
    if (index > 0 && rows.length > 0) {
      sheet.getRange(`${colLetter(index)}7:${colLetter(index)}${lastRow}`).format.wrapText = true;
    }
  }
  for (const header of statusHeaders) {
    const index = headers.indexOf(header) + 1;
    if (index > 0) addStatusFormatting(sheet, index, 7, lastRow);
  }

  sheet.freezePanes.freezeRows(6);
  sheet.freezePanes.freezeColumns(2);
  return sheet;
}

addSheet({
  name: "categorii_loc_1",
  title: "Categorii recomandate și de verificat pentru locația 1",
  subtitle: `BD veche: ${data.counts.old_categories} categorii, BD actuală: ${data.counts.current_categories} categorii. Cele 7 categorii cu prefixul A nu existau în dump-ul vechi.`,
  rows: data.loc1_categories,
  headers: [
    "id_categorie", "categorie", "se_vinde", "există_în_bd_veche_19_09", "există_în_offline",
    "produse_active_vechi", "produse_candidat_loc1", "produse_de_verificat_loc1",
    "produse_offline_loc2_categorie", "status_recomandat_loc1", "observație",
  ],
  tableName: "CategoriiLoc1",
  tabColor: "#1F4E78",
  widths: {
    "id_categorie": 12, "categorie": 28, "se_vinde": 10, "există_în_bd_veche_19_09": 18,
    "există_în_offline": 16, "produse_active_vechi": 17, "produse_candidat_loc1": 18,
    "produse_de_verificat_loc1": 21, "produse_offline_loc2_categorie": 23,
    "status_recomandat_loc1": 42, "observație": 55,
  },
  statusHeaders: ["status_recomandat_loc1"],
});

addSheet({
  name: "produse_loc_1",
  title: "Produse candidate pentru locația 1",
  subtitle: `${data.counts.loc1_products} produse active fără identificator explicit de locația 2. Filtrează coloana status_recomandat_loc1 pentru cele 297 propuse și cele 20 de verificat.`,
  rows: data.loc1_products,
  headers: [
    "cod_produs_online", "produs", "pret_cu_tva", "cota_tva", "id_categorie_veche", "categorie_veche",
    "id_categorie_actual", "categorie_actuala", "activ_online", "marcaj_loc1", "marcaj_loc2",
    "exista_în_bd_veche_19_09", "identificator_offline", "status_recomandat_loc1", "observație",
  ],
  tableName: "ProduseLoc1",
  tabColor: "#1F4E78",
  widths: {
    "cod_produs_online": 15, "produs": 34, "pret_cu_tva": 13, "cota_tva": 10,
    "id_categorie_veche": 16, "categorie_veche": 24, "id_categorie_actual": 17,
    "categorie_actuala": 28, "activ_online": 12, "marcaj_loc1": 18, "marcaj_loc2": 18,
    "exista_în_bd_veche_19_09": 18, "identificator_offline": 42, "status_recomandat_loc1": 42,
    "observație": 48,
  },
  statusHeaders: ["status_recomandat_loc1"],
  moneyHeaders: ["pret_cu_tva"],
});

addSheet({
  name: "categorii_loc_2",
  title: "Categorii din catalogul offline pentru locația 2",
  subtitle: `${data.counts.offline_categories} categorii există în offline. Sunt listate categoriile cu produse active și vândabile din baza offline, inclusiv cele care încă nu au corespondent în online.`,
  rows: data.loc2_categories,
  headers: [
    "id_categorie_offline", "categorie_offline", "id_categorie_online", "categorie_online",
    "produse_active_offline", "produse_mapate_online", "produse_fără_corespondent_online",
    "categorii_observate_pe_produsele_mapate", "categorie_exista_în_bd_veche", "se_vinde_offline",
    "status_recomandat_loc2", "observație",
  ],
  tableName: "CategoriiLoc2",
  tabColor: "#548235",
  widths: {
    "id_categorie_offline": 20, "categorie_offline": 32, "id_categorie_online": 19,
    "categorie_online": 32, "produse_active_offline": 20, "produse_mapate_online": 20,
    "produse_fără_corespondent_online": 27, "categorii_observate_pe_produsele_mapate": 38,
    "categorie_exista_în_bd_veche": 25, "se_vinde_offline": 15, "status_recomandat_loc2": 37,
    "observație": 48,
  },
  statusHeaders: ["status_recomandat_loc2"],
});

addSheet({
  name: "produse_loc2",
  title: "Produse active din baza offline pentru locația 2",
  subtitle: `${data.counts.loc2_products} produse active și vândabile în offline. Maparea exactă folosește identificator_offline, iar corespondența directă folosește codul și denumirea.`,
  rows: data.loc2_products,
  headers: [
    "cod_produs_offline", "produs_offline", "pret_offline", "cota_tva_offline", "id_categorie_offline",
    "categorie_offline", "cod_produs_online", "produs_online", "cod_online_candidat", "produs_online_candidat",
    "pret_online", "cota_tva_online", "categorie_online", "activ_online", "marcaj_loc2", "marcaj_loc1",
    "status_mapare", "status_loc2",
  ],
  tableName: "ProduseLoc2",
  tabColor: "#548235",
  widths: {
    "cod_produs_offline": 18, "produs_offline": 33, "pret_offline": 13, "cota_tva_offline": 15,
    "id_categorie_offline": 20, "categorie_offline": 31, "cod_produs_online": 17, "produs_online": 33,
    "cod_online_candidat": 18, "produs_online_candidat": 33, "pret_online": 13, "cota_tva_online": 15,
    "categorie_online": 31, "activ_online": 13, "marcaj_loc2": 16, "marcaj_loc1": 16,
    "status_mapare": 39, "status_loc2": 43,
  },
  statusHeaders: ["status_mapare", "status_loc2"],
  moneyHeaders: ["pret_offline", "pret_online"],
});

workbook.recalculate();

const inspectSummary = await workbook.inspect({
  kind: "workbook,sheet,table",
  maxChars: 10000,
  tableMaxRows: 4,
  tableMaxCols: 8,
  tableMaxCellChars: 80,
});
const formulaSummary = await workbook.inspect({
  kind: "formula",
  maxChars: 2000,
  options: { maxResults: 20 },
});
await fs.writeFile(`${outputDir}/inspection.json`, JSON.stringify({ inspectSummary, formulaSummary }, null, 2), "utf8");

for (const sheetName of ["categorii_loc_1", "produse_loc_1", "categorii_loc_2", "produse_loc2"]) {
  const preview = await workbook.render({ sheetName, autoCrop: "all", scale: 1, format: "png" });
  await fs.writeFile(`${outputDir}/${sheetName}.png`, new Uint8Array(await preview.arrayBuffer()));
}

const xlsx = await SpreadsheetFile.exportXlsx(workbook);
await xlsx.save(xlsxPath);
console.log(JSON.stringify({ xlsxPath, sheets: ["categorii_loc_1", "produse_loc_1", "categorii_loc_2", "produse_loc2"], inspectPath: `${outputDir}/inspection.json` }, null, 2));
