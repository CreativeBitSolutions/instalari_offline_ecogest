<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once('database_connection.php');
require_once('tcpdf/tcpdf.php');
// --- Country name RO from JSON (code/name -> RO name) ---
function normalize_country_key($s) {
    $s = trim((string)$s);
    if ($s === '') return '';
    // elimină diacritice (pentru matching)
    $trans = [
        'ă'=>'a','Ă'=>'A','â'=>'a','Â'=>'A','î'=>'i','Î'=>'I','ș'=>'s','Ș'=>'S','ş'=>'s','Ş'=>'S','ț'=>'t','Ț'=>'T','ţ'=>'t','Ţ'=>'T'
    ];
    $s = strtr($s, $trans);
    $s = mb_strtoupper($s, 'UTF-8');
    $s = preg_replace('/\s+/', ' ', $s);
    return $s;
}

function load_country_maps() {
    $codeToRo = [];
    $nameToCode = [];

    $roPath = __DIR__ . '/countrylist_ro.json';
    $enPath = __DIR__ . '/countrylist_en.json';

    if (is_file($roPath)) {
        $ro = json_decode(file_get_contents($roPath), true);
        if (is_array($ro)) {
            foreach ($ro as $code => $nameRo) {
                $code = strtoupper(trim($code));
                $codeToRo[$code] = $nameRo;

                $k = normalize_country_key($nameRo);
                if ($k !== '') $nameToCode[$k] = $code;
            }
        }
    }

    // Optional: dacă DB are nume în EN, facem reverse map EN->code
    if (is_file($enPath)) {
        $en = json_decode(file_get_contents($enPath), true);
        if (is_array($en)) {
            foreach ($en as $code => $nameEn) {
                $code = strtoupper(trim($code));
                $k = normalize_country_key($nameEn);
                if ($k !== '') $nameToCode[$k] = $code;
            }
        }
    }

    // Alias-uri utile (cazuri frecvente în practică)
    $aliases = [
        'ROMANIA' => 'RO',
        'REPUBLICA MOLDOVA' => 'MD',
        'MOLDOVA' => 'MD',
        'OLANDA' => 'NL',
        'TARILE DE JOS' => 'NL',
        'STATELE UNITE' => 'US',
        'STATELE UNITE ALE AMERICII' => 'US',
        'SUA' => 'US',
        'UK' => 'GB',
        'MAREA BRITANIE' => 'GB',
        'REGATUL UNIT' => 'GB',
    ];
    foreach ($aliases as $name => $code) {
        $nameToCode[normalize_country_key($name)] = $code;
    }

    return [$codeToRo, $nameToCode];
}

function getCountryNameRO($input, $codeToRo, $nameToCode) {
    $input = trim((string)$input);
    if ($input === '') return 'N/A';

    // dacă e deja cod ISO (RO, DE, FR etc.)
    if (preg_match('/^[A-Za-z]{2}$/', $input)) {
        $code = strtoupper($input);
        return $codeToRo[$code] ?? $code;
    }

    // dacă e un nume (RO/EN/alias) -> code -> RO
    $norm = normalize_country_key($input);
    if ($norm !== '' && isset($nameToCode[$norm])) {
        $code = $nameToCode[$norm];
        return $codeToRo[$code] ?? $input;
    }

    // fallback: dacă nu găsim, afișăm ce era în DB
    return $input;
}

// Încarcă mapping-urile o singură dată
list($COUNTRY_CODE_TO_RO, $COUNTRY_NAME_TO_CODE) = load_country_maps();

// Funcții pentru obținerea datelor facturii, firmei, vânzărilor și totalurilor

function getFactura($pdo, $id_factura) {
    $sql = "SELECT * FROM facturi WHERE id_factura = :id_factura";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id_factura' => $id_factura]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getDateFirma($pdo) {
    $sql = "SELECT * FROM date_firma LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getVanzari($pdo, $id_factura) {
    if ((int)($_SESSION['client_id'] ?? 0) === 3 && invoiceTableColumnExists($pdo, 'produse_servicii', 'cod_mapare_import_extern')) {
        $sql = "SELECT v.*, ps.cod_mapare_import_extern
                FROM vanzari v
                LEFT JOIN produse_servicii ps ON ps.cod_produs = v.cod_p
                WHERE v.id_factura = :id_factura";
    } else {
        $sql = "SELECT * FROM vanzari WHERE id_factura = :id_factura";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id_factura' => $id_factura]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getTotaluri($pdo, $id_factura) {
    $sql = "SELECT 
                SUM(valoare_vanzare) AS total_fara_tva, 
                SUM(tva_col) AS total_tva, 
                SUM(valoare_vanzare_cu_tva) AS total_cu_tva
            FROM vanzari 
            WHERE id_factura = :id_factura";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id_factura' => $id_factura]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getIndexIncarcare($pdo, $id_factura) {
    $sql = "SELECT index_incarcare FROM istoric_incarcari 
            WHERE id_factura = :id_factura 
              AND status = 'încărcat' 
            ORDER BY id DESC 
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id_factura' => $id_factura]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['index_incarcare'] : null;
}

function hotelInvoiceTableExists($pdo, $tableName) {
    $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=:table_name");
    $stmt->execute(['table_name' => $tableName]);
    return (bool)$stmt->fetchColumn();
}

function invoiceTableColumnExists($pdo, $tableName, $columnName) {
    $stmt = $pdo->prepare("SELECT name FROM pragma_table_info('$tableName') WHERE name=:column_name");
    $stmt->execute(['column_name' => $columnName]);
    return (bool)$stmt->fetchColumn();
}

function fillHotelInvoiceCodFiscalIfMissing($pdo, &$factura) {
    if (trim((string)($factura['cod_fiscal'] ?? '')) !== '') {
        return;
    }

    $nrBon = (int)($factura['nrbon'] ?? 0);
    $idFactura = (int)($factura['id_factura'] ?? 0);
    if ($nrBon <= 0 || $idFactura <= 0) {
        return;
    }

    if (!hotelInvoiceTableExists($pdo, 'hotel_rezervari_vanzari') || !hotelInvoiceTableExists($pdo, 'rezervari')) {
        return;
    }

    $hasClienti = hotelInvoiceTableExists($pdo, 'clienti');
    $clientJoin = $hasClienti ? "LEFT JOIN clienti c ON c.id_client = r.id_client_companie" : "";
    $clientFiscalSelect = $hasClienti ? "c.cod_fiscal AS client_cod_fiscal," : "'' AS client_cod_fiscal,";

    $stmt = $pdo->prepare("SELECT r.tip_oaspete,
                                  r.cnp_oaspete,
                                  {$clientFiscalSelect}
                                  r.id_client_companie
                           FROM hotel_rezervari_vanzari rv
                           INNER JOIN rezervari r ON r.id_rezervare = rv.id_rezervare
                           {$clientJoin}
                           WHERE rv.nr_bon = :nr_bon
                           ORDER BY rv.id_legatura DESC
                           LIMIT 1");
    $stmt->execute(['nr_bon' => $nrBon]);
    $rezervare = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$rezervare) {
        return;
    }

    $codFiscal = trim((string)($rezervare['client_cod_fiscal'] ?? ''));
    if ($codFiscal === '') {
        $codFiscal = preg_replace('/\D+/', '', (string)($rezervare['cnp_oaspete'] ?? ''));
    }

    if ($codFiscal === '') {
        return;
    }

    $update = $pdo->prepare("UPDATE facturi
                             SET cod_fiscal = :cod_fiscal
                             WHERE id_factura = :id_factura
                               AND TRIM(COALESCE(cod_fiscal, '')) = ''");
    $update->execute([
        'cod_fiscal' => $codFiscal,
        'id_factura' => $idFactura,
    ]);

    $factura['cod_fiscal'] = $codFiscal;
}

// Verificăm ca id_factura să fie specificat
if (!isset($_GET['id_factura'])) {
    die("ID facturii nu este specificat.");
}
$id_factura = $_GET['id_factura'];

// Obținerea datelor din bază
$factura   = getFactura($pdo, $id_factura);
if (!$factura) {
    die("Factura cu ID-ul specificat nu a fost găsită.");
}
fillHotelInvoiceCodFiscalIfMissing($pdo, $factura);
$taraClientRO = getCountryNameRO($factura['adresa_tara'] ?? '', $COUNTRY_CODE_TO_RO, $COUNTRY_NAME_TO_CODE);

$dateFirma    = getDateFirma($pdo);
$vanzari      = getVanzari($pdo, $id_factura);
$totaluri     = getTotaluri($pdo, $id_factura);
$index_incarcare = getIndexIncarcare($pdo, $id_factura);

// Formatarea datelor
$data_factura = isset($factura['data_factura']) ? date("d-m-Y", strtotime($factura['data_factura'])) : 'N/A';
$data_scadenta = isset($factura['data_scadenta']) ? date("d-m-Y", strtotime($factura['data_scadenta'])) : 'N/A';

// Pregătirea căii fizice pentru logo.
// În DB poate rămâne o cale relativă, de forma uploads/company_logos/1/logo.png.
$logo_server_path = '';
$stored_logo_path = trim((string)($dateFirma['logo'] ?? ''));
if ($stored_logo_path !== '') {
    $is_absolute_logo_path = preg_match('/^[A-Za-z]:[\\\/]/', $stored_logo_path) === 1
        || str_starts_with($stored_logo_path, '/');

    $candidate_logo_path = $is_absolute_logo_path
        ? $stored_logo_path
        : __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($stored_logo_path, '/\\'));

    if (is_file($candidate_logo_path)) {
        $logo_server_path = $candidate_logo_path;
    }
}

// Definirea mapping-ului metodelor de plată
$metodePlata = array(
    "1"   => "Instrument nedefinit",
    "2"   => "Plată prin sistem de compensare automată (credit)",
    "3"   => "Debit prin sistem de compensare automată",
    "4"   => "Reversare debit cerere ACH",
    "5"   => "Reversare credit cerere ACH",
    "6"   => "Credit cerere ACH",
    "7"   => "Debit cerere ACH",
    "8"   => "Blocat",
    "9"   => "Compensare națională sau regională",
    "10"  => "Numerar",
    "11"  => "Reversare credit economii ACH",
    "12"  => "Reversare debit economii ACH",
    "13"  => "Credit economii ACH",
    "14"  => "Debit economii ACH",
    "15"  => "Credit transfer carte de cont",
    "16"  => "Debit transfer carte de cont",
    "17"  => "Credit cerere CCD prin concentrarea/distribuirea de numerar",
    "18"  => "Debit cerere CCD prin concentrarea/distribuirea de numerar",
    "19"  => "Credit CTP tranzacție corporativă ACH",
    "20"  => "Cec",
    "21"  => "Ordin bancar",
    "22"  => "Ordin bancar certificat",
    "23"  => "Cec bancar (emis de o instituție bancară sau similară)",
    "24"  => "Bilet la ordin în așteptarea acceptării",
    "25"  => "Cec certificat",
    "26"  => "Cec local",
    "27"  => "Debit CTP tranzacție corporativă ACH",
    "28"  => "Credit CTX tranzacție corporativă ACH",
    "29"  => "Debit CTX tranzacție corporativă ACH",
    "30"  => "Transfer de credit",
    "31"  => "Transfer de debit",
    "32"  => "Credit CCD+ prin cerere ACH",
    "33"  => "Debit CCD+ prin cerere ACH",
    "34"  => "Plată și depunere prearanjate ACH (PPD)",
    "35"  => "Credit economii CCD prin concentrarea/distribuirea de numerar",
    "36"  => "Debit economii CCD prin concentrarea/distribuirea de numerar",
    "37"  => "Credit CTP economii tranzacție corporativă ACH",
    "38"  => "Debit CTP economii tranzacție corporativă ACH",
    "39"  => "Credit CTX economii tranzacție corporativă ACH",
    "40"  => "Debit CTX economii tranzacție corporativă ACH",
    "41"  => "Credit economii CCD+ prin cerere ACH",
    "42"  => "Plată în cont bancar",
    "43"  => "Debit economii CCD+ prin cerere ACH",
    "44"  => "Bilet la ordin acceptat",
    "45"  => "Transfer de credit home-banking referențiat",
    "46"  => "Transfer de debit interbancar",
    "47"  => "Transfer de debit home-banking",
    "48"  => "Card bancar",
    "49"  => "Debit direct",
    "50"  => "Plată prin postgiro",
    "51"  => "FR, normă 6 97-Telereglement CFONB (Organizația Franceză pentru Standarde Bancare) - Opțiunea A",
    "52"  => "Plată comercială urgentă",
    "53"  => "Plată urgentă de Trezorerie",
    "54"  => "Card de credit",
    "55"  => "Card de debit",
    "56"  => "Bankgiro",
    "57"  => "Acord permanent",
    "58"  => "Transfer de credit SEPA",
    "59"  => "Debit direct SEPA",
    "60"  => "Poliță de plată",
    "61"  => "Poliță de plată semnată de debitor",
    "62"  => "Poliță de plată semnată de debitor și garantată de bancă",
    "63"  => "Poliță de plată semnată de debitor și garantată de o terță parte",
    "64"  => "Poliță de plată semnată de bancă",
    "65"  => "Poliță de plată semnată de bancă și garantată de o altă bancă",
    "66"  => "Poliță de plată semnată de o terță parte",
    "67"  => "Poliță de plată semnată de o terță parte și garantată de bancă",
    "68"  => "Serviciu de plată online",
    "70"  => "Bilet tras de creditor asupra debitorului",
    "74"  => "Bilet tras de creditor asupra unei bănci",
    "75"  => "Bilet tras de creditor, garantat de o altă bancă",
    "76"  => "Bilet tras de creditor asupra unei bănci și garantat de o terță parte",
    "77"  => "Bilet tras de creditor asupra unei terțe părți",
    "78"  => "Bilet tras de creditor asupra unei terțe părți, acceptat și garantat de bancă",
    "91"  => "Ordin bancar netransferabil",
    "92"  => "Cec local netransferabil",
    "93"  => "Referință giro",
    "94"  => "Giro urgent",
    "95"  => "Giro format liber",
    "96"  => "Metoda solicitată pentru plată nu a fost utilizată",
    "97"  => "Compensare între parteneri",
    "ZZZ" => "Definit mutual"
);

$codMetodaPlata = isset($factura['cod_metoda_plata']) ? $factura['cod_metoda_plata'] : null;
$metodaPlata = isset($metodePlata[$codMetodaPlata]) ? $metodePlata[$codMetodaPlata] : 'N/A';

function scrieSubsolFixFactura($pdf, $delegateSectionHtml, $textSubsolHtml) {
    // Footer fix pe ultima pagină. Nu intră în fluxul writeHTML(), deci nu creează pagină nouă.
    $margins = $pdf->getMargins();
    $x = $margins['left'];
    $w = $pdf->getPageWidth() - $margins['left'] - $margins['right'];
    $y = $pdf->getPageHeight() - 65;

    $html = '
        <div style="font-family:freeserif; font-size:8.5px; line-height:1.15;">' . $delegateSectionHtml . '</div>
        <div style="font-family:freeserif; font-size:8px; line-height:1.08; font-style:italic;">' . $textSubsolHtml . '</div>
    ';

    $pdf->SetAutoPageBreak(false, 0);
    $pdf->writeHTMLCell($w, 0, $x, $y, $html, 0, 0, false, true, 'L', false);
}

$pdf = new TCPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$cod_fiscal = isset($factura['cod_fiscal']) ? htmlspecialchars($factura['cod_fiscal']) : 'N/A';
$pdf->SetTitle('Factura: ' . htmlspecialchars($factura['nr_factura']) . ' client: ' . $cod_fiscal);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(15, 20, 15);
// Rezervă spațiu fix jos pentru zona delegat + text subsol, fără să împingă conținutul pe o pagină goală.
$pdf->SetAutoPageBreak(TRUE, 65);
$pdf->SetFont('freeserif', '', 11);
$pdf->AddPage();
$clientId = isset($_SESSION['client_id']) ? (int)$_SESSION['client_id'] : 0;
if (!empty($logo_server_path) && file_exists($logo_server_path)) {
    [$logoWidthPx, $logoHeightPx] = getimagesize($logo_server_path);

    
    $maxWidth = 50;   // mm
    $maxHeight = 20;  // mm
  if ($clientId === 2) {
        $maxWidth = 65;
        $maxHeight = 32;
    } else {
        $maxWidth = 50;
        $maxHeight = 20;
    }
    $ratio = min($maxWidth / $logoWidthPx, $maxHeight / $logoHeightPx);

    $logoWidthMm = $logoWidthPx * $ratio;
    $logoHeightMm = $logoHeightPx * $ratio;

    $pdf->Image(
        $logo_server_path,
        15,
        10,
        $logoWidthMm,
        $logoHeightMm,
        '',
        '',
        'T',
        false,
        300,
        '',
        false,
        false,
        0,
        false,
        false,
        false
    );
    // mutăm cursorul sub logo, ca linia din HTML să nu treacă peste el
    $pdf->SetY(36);
} else {
    $pdf->SetY(20);
}

// Băncile și IBAN-urile sunt păstrate în coloanele existente, separate prin |.
// Asocierea se face după poziție: banca 1 cu IBAN 1, banca 2 cu IBAN 2 etc.
$banci = array_map('trim', explode('|', (string)($dateFirma['banca'] ?? '')));
$ibanuri = array_map('trim', explode('|', (string)($dateFirma['cont_banca'] ?? '')));

$bancaIbanList = '';
$max = max(count($banci), count($ibanuri));
for ($i = 0; $i < $max; $i++) {
    $banca = trim((string)($banci[$i] ?? ''));
    $iban = trim((string)($ibanuri[$i] ?? ''));

    if ($banca === '' && $iban === '') {
        continue;
    }

    if ($banca !== '') {
        $bancaIbanList .= htmlspecialchars($banca) . '<br>';
    }
    if ($iban !== '') {
        $bancaIbanList .= htmlspecialchars($iban) . '<br>';
    }
}
if ($bancaIbanList === '') {
    $bancaIbanList = 'N/A';
}

$clientBancaIbanList = '';
if (isset($factura['banca']) || isset($factura['iban'])) {
    $clientBanci = isset($factura['banca']) ? (is_array($factura['banca']) ? $factura['banca'] : [$factura['banca']]) : [];
    $clientIbanuri = isset($factura['iban']) ? (is_array($factura['iban']) ? $factura['iban'] : [$factura['iban']]) : [];
    $maxClient = max(count($clientBanci), count($clientIbanuri));
    for ($i = 0; $i < $maxClient; $i++) {
        if (isset($clientBanci[$i])) {
            $clientBancaIbanList .= htmlspecialchars($clientBanci[$i]) . '<br>';
        }
        if (isset($clientIbanuri[$i])) {
            $clientBancaIbanList .= htmlspecialchars($clientIbanuri[$i]) . '<br>';
        }
    }
    if (empty($clientBancaIbanList)) {
        $clientBancaIbanList = 'N/A';
    }
}

$codFiscalDisplay = 'N/A';
if (isset($dateFirma['cod_fiscal'])) {
    $codFiscal = htmlspecialchars($dateFirma['cod_fiscal']);
    if (isset($dateFirma['tva']) && $dateFirma['tva'] == 1) {
        $codFiscalDisplay = 'RO' . $codFiscal;
    } else {
        $codFiscalDisplay = $codFiscal;
    }
}
if (isset($dateFirma['email'])) {
    $email = htmlspecialchars($dateFirma['email']);
}
$content = '
<style>
    body { font-family: freeserif, sans-serif; }
    .header { font-size: 14px; }
    .header .title { color: #0000FF; font-weight: bold; }
    .header .invoice-number { color: #0000FF; font-weight: bold; font-size: 10px; }
    .header-details { font-size: 10px; color: #555555; }
    .details { width: 100%; margin-top: 10px; }
    .details table { width: 100%; border-collapse: collapse; }
    .details td {
        vertical-align: top;
        font-size: 10px;
        word-wrap: break-word;
        padding: 1px 2px;
    }
    .nested-table { width: 100%; border-collapse: collapse; }
    .nested-table td { 
        padding: 1px 2px;
        vertical-align: top;
        font-size: 10px;
    }
    .nested-table td.label { width: 25%; }
    .nested-table td.value { width: 75%; }
    .items table {width: 700px; font-size: 9px; }
    .items th, .items td { border: 1px solid #cccccc; padding: 3px; }
    .items th { background-color: #f2f2f2; text-align: center; }
    .items tr:nth-child(even) { background-color: #f9f9f9; }
    .items tr:nth-child(odd) { background-color: #ffffff; }
    .items td.counter { text-align: center; width:30px; }
    .items td.denumire { text-align: left; width:150px; }
    .items td.cant { text-align: right; width:40px; }
    .items td.um { text-align: center; width:40px; }
    .items td.pret { text-align: right; width:50px; }
    .items td.valoare { text-align: right; width:50px; }
    .items td.cota { text-align: center; width:40px; }
    .items td.valoare_tva { text-align: right; width:50px; }
    .items td.valoare_cu_tva { text-align: right; width:50px; }
    .items td.pret_vanzare { text-align: right; width:50px; }
    .items td.blue { color: #0000FF; font-weight: bold; text-align: left; }
    .blue { color: #0000FF; font-weight: bold; text-align: left; }
    .total { width: 100%; margin-top: 10px; font-size: 10px; }
    .total table { width: 100%; border-collapse: collapse; }
    .total td { padding: 3px; }
    .total .label { text-align: left; }
    .total .amount { text-align: right; font-weight: bold; }
    .total .final { color: #0000FF; font-weight: bold; font-size: 12px; }
    .footer { clear: both; margin-top: 20px; font-size: 10px; }
</style>

<!-- Header Section -->
<table class="header" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <!-- Prima Coloană: Factura și Seria -->
        <td style="width:33%; vertical-align: top;">
            <span class="title">Factura</span><br>
            <span class="invoice-number blue">' . (isset($factura['serie_factura']) ? htmlspecialchars($factura['serie_factura']) : 'N/A') . ' ' . htmlspecialchars($factura['nr_factura']) . '</span>
        </td>
        
        <!-- A Doua Coloană: Data Emitere și Data Scadență -->
        <td style="width:34%; vertical-align: top;">
            <span class="header-details">Data Emitere:</span> ' . $data_factura . '<br>
            <span class="header-details">Data Scadență:</span> ' . $data_scadenta . '
        </td>
        
        <!-- A Treia Coloană: Index Încărcare -->
        <td style="width:33%; vertical-align: top; text-align: right;">
            <span class="header-details">Index încărcare:</span> <strong>' . $index_incarcare . '</strong><br>
        </td>
    </tr>
</table>

<br>
<hr style="border:2px solid #000000;">

<!-- Supplier and Client Information -->
<table class="details" cellpadding="0" cellspacing="0" style="width:100%;">
    <tr>
        <!-- Furnizor -->
        <td style="width: 50%; vertical-align: top; padding-right: 5px;">
            <div>
                <strong>Furnizor:</strong><br>
                <table class="nested-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="value">' . (isset($dateFirma['den_ent']) ? htmlspecialchars($dateFirma['den_ent']) : 'N/A') . '</td>
                    </tr>
                    <tr>
                        <td class="label"><strong>CUI:</strong></td>
                        <td class="value">' . (isset($codFiscalDisplay) ? htmlspecialchars($codFiscalDisplay) : 'N/A') . '</td>
                    </tr>
                    <tr>
                        <td class="label"><strong>Reg. Com.:</strong></td>
                        <td class="value">' . (isset($dateFirma['nr_reg_com']) ? htmlspecialchars($dateFirma['nr_reg_com']) : 'N/A') . '</td>
                    </tr>
                    <tr>
                        <td class="label"><strong>Județ:</strong></td>
                        <td class="value">' . (isset($dateFirma['judet']) ? htmlspecialchars($dateFirma['judet']) : 'N/A') . '</td>
                    </tr>
                    <tr>
                        <td class="label"><strong>Localitate:</strong></td>
                        <td class="value">' . (isset($dateFirma['localitate']) ? htmlspecialchars($dateFirma['localitate']) : 'N/A') . '</td>
                    </tr>
                    <tr>
                        <td class="label"><strong>Adresă:</strong></td>
                        <td class="value">' . (isset($dateFirma['sediu']) ? htmlspecialchars($dateFirma['sediu']) : 'N/A') . '</td>
                    </tr>
                    <tr>
                        <td class="label"><strong>Banca și IBAN:</strong></td>
                        <td class="value">' . $bancaIbanList . '</td>
                    </tr>
                     <tr>
                        <td class="label"><strong>Email:</strong></td>
                        <td class="value">' . $email . '</td>
                    </tr>
                </table>
            </div>
        </td>
        
        <!-- Client -->
        <td style="width: 50%; vertical-align: top; padding-left: 5px;">
            <div>
                <strong>Client:</strong><br>
                <table class="nested-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="value">' . (isset($factura['nume']) ? htmlspecialchars($factura['nume']) : 'N/A') . '</td>
                    </tr>
                    <tr>
                        <td class="label"><strong>CUI/CNP:</strong></td>
                        <td class="value">' . (isset($factura['cod_fiscal']) ? htmlspecialchars($factura['cod_fiscal']) : 'N/A') . '</td>
                    </tr>
                    <tr>
                       <td class="label"><strong>Tara:</strong></td>
<td class="value">' . htmlspecialchars($taraClientRO) . '</td>
 </tr>
                    
                    <tr>
                        <td class="label"><strong>Reg. Com./CI:</strong></td>
                        <td class="value">' . (isset($factura['cod_inmatriculare']) ? htmlspecialchars($factura['cod_inmatriculare']) : 'N/A') . '</td>
                    </tr>
                    <tr>
                        <td class="label"><strong>Județ:</strong></td>
                        <td class="value">' . (isset($factura['adresa_judet']) ? htmlspecialchars($factura['adresa_judet']) : 'N/A') . '</td>
                    </tr>
                    <tr>
                        <td class="label"><strong>Localitate:</strong></td>
                        <td class="value">' . (isset($factura['adresa_localitate']) ? htmlspecialchars($factura['adresa_localitate']) : 'N/A') . '</td>
                    </tr>
                    <tr>
                        <td class="label"><strong>Adresă:</strong></td>
                        <td class="value">' . (isset($factura['adresa']) ? htmlspecialchars($factura['adresa']) : 'N/A') . '</td>
                    </tr>
                    <tr>
                        <td class="label"><strong>Banca:</strong></td>
                        <td class="value">' . (isset($factura['banca']) ? htmlspecialchars($factura['banca']) : 'N/A') . '</td>
                    </tr>
                    <tr>
                        <td class="label"><strong>IBAN:</strong></td>
                        <td class="value">' . (isset($factura['iban']) ? htmlspecialchars($factura['iban']) : 'N/A') . '</td>
                    </tr>
                </table>
            </div>
        </td>
    </tr>
</table>

<br><br>

<!-- Invoice Items -->
<div class="items">
    <table>
        <thead>
            <tr>
                <th style="border: 1px solid #cccccc; width:30px; text-align:center;">Nr.<br>crt</th>
                <th style="border: 1px solid #cccccc; width:150px; text-align:center;">Denumire</th>
                <th style="border: 1px solid #cccccc; width:40px; text-align:center;">Cant.</th>
                <th style="border: 1px solid #cccccc; width:40px; text-align:center;">UM</th>
                <th style="border: 1px solid #cccccc; width:50px; text-align:center;">P.U.<br>(fără TVA)</th>
                <th style="border: 1px solid #cccccc; width:50px; text-align:center;">Valoare<br>(fără TVA)</th>
                <th style="border: 1px solid #cccccc; width:50px; text-align:center;">Valoare TVA <br>(Cota TVA)</th>
                <th style="border: 1px solid #cccccc; width:50px; text-align:center;">Pret cu <br>TVA</th>
                <th style="border: 1px solid #cccccc; width:50px; text-align:center;">Valoare<br>(cu TVA)</th>
            </tr>
        </thead>
        <tbody>';
        
$counter = 1;
foreach ($vanzari as $vanzare) {
    $pret_fara_tva = round((($vanzare['valoare_vanzare_cu_tva'] / $vanzare['cantitate']) - ($vanzare['tva_col'] / $vanzare['cantitate'])), 2);
    $pret_fara_tva = number_format($pret_fara_tva, 2, ',', '.');
    
    $den_p_raw = isset($vanzare['den_p']) ? (string)$vanzare['den_p'] : 'N/A';
    $cod_mapare = trim((string)($vanzare['cod_mapare_import_extern'] ?? ''));
    if ((int)($_SESSION['client_id'] ?? 0) === 3 && $cod_mapare !== '') {
        $den_p_raw = $cod_mapare . ' - ' . $den_p_raw;
    }
    $den_p = htmlspecialchars($den_p_raw);
    $cantitate = isset($vanzare['cantitate']) ? number_format($vanzare['cantitate'], 2, ',', '.') : 'N/A';
    $um = isset($vanzare['um']) ? htmlspecialchars($vanzare['um']) : 'BUC';
    $pret_vanzare = isset($vanzare['pret_vanzare']) ? number_format($vanzare['pret_vanzare'], 2, ',', '.') : 'N/A';
    $valoare_vanzare = isset($vanzare['valoare_vanzare']) ? number_format($vanzare['valoare_vanzare'], 2, ',', '.') : 'N/A';
    $cota_tva = isset($vanzare['cota_tva']) ? htmlspecialchars($vanzare['cota_tva']) : 'N/A';
    $valoare_tva = isset($vanzare['tva_col']) ? number_format($vanzare['tva_col'], 2, ',', '.') : 'N/A';
    $valoare_cu_tva = isset($vanzare['valoare_vanzare_cu_tva']) ? number_format($vanzare['valoare_vanzare_cu_tva'], 2, ',', '.') : 'N/A';

    $bgcolor = ($counter % 2 == 0) ? '#f9f9f9' : '#ffffff';

    $content .= '
            <tr style="background-color:' . $bgcolor . ';">
                <td class="counter">' . $counter++ . '</td>
                <td class="denumire">' . $den_p . '</td>
                <td class="cant">' . $cantitate . '</td>
                <td class="um">' . $um . '</td>
                <td class="pret">' . $pret_fara_tva . '</td>
                <td class="valoare">' . $valoare_vanzare . '</td>
                <td class="valoare_tva">' . $valoare_tva . ' (' . $cota_tva . '%)</td>
                <td class="pret_vanzare">' . $pret_vanzare . '</td>
                <td class="valoare_cu_tva">' . $valoare_cu_tva . '</td>
            </tr>';
}

// Decodificăm JSON-ul din coloana delegat și setăm valorile corespunzătoare
$delegatData = [];
if (!empty($factura['delegat'])) {
    $delegatData = json_decode($factura['delegat'], true);
    if (!is_array($delegatData)) {
        $delegatData = [];
    }
}
$delegatNume      = isset($delegatData['nume'])       ? htmlspecialchars($delegatData['nume'])       : '__________';
$delegatCiSerie   = isset($delegatData['ci_serie'])   ? htmlspecialchars($delegatData['ci_serie'])   : '__________';
$delegatCiNumar   = isset($delegatData['ci_numar'])   ? htmlspecialchars($delegatData['ci_numar'])   : '__________';
$delegatMasina    = isset($delegatData['masina'])     ? htmlspecialchars($delegatData['masina'])     : '_______________';
$delegatSemnatura = isset($delegatData['semnatura'])  ? htmlspecialchars($delegatData['semnatura'])  : '________';

$delegateSection = '
<table cellpadding="0" cellspacing="0" style="width:100%; font-size:8.5px; line-height:1.15;">
    <tr><td style="height:6mm;"><strong>Numele delegatului:</strong> ' . $delegatNume . '</td></tr>
    <tr><td style="height:6mm;"><strong>B.I./C.I. seria:</strong> ' . $delegatCiSerie . ' &nbsp;&nbsp; <strong>Numărul:</strong> ' . $delegatCiNumar . '</td></tr>
    <tr><td style="height:6mm;"><strong>Mașina:</strong> ' . $delegatMasina . '</td></tr>
    <tr><td style="height:6mm;"><strong>Semnătura:</strong> ' . $delegatSemnatura . '</td></tr>
</table>
<br>';

$content .= '
        </tbody>
    </table>
</div>
<br>

<!-- Total Section -->
<div class="total">
    <table>
        <tr>
            <td style="width:70%;"></td>
            <td style="width:30%;">
                <table>
                    <tr>
                        <td class="label">Total fără TVA:</td>
                        <td class="amount">' . (isset($totaluri['total_fara_tva']) ? number_format($totaluri['total_fara_tva'], 2, ',', '.') : '0,00') . ' Lei</td>
                    </tr>
                    <tr>
                        <td class="label">TVA:</td>
                        <td class="amount">' . (isset($totaluri['total_tva']) ? number_format($totaluri['total_tva'], 2, ',', '.') : '0,00') . ' Lei</td>
                    </tr>
                    <tr>
                        <td class="label blue">Total de plată:</td>
                        <td class="amount final blue">' . (isset($totaluri['total_cu_tva']) ? number_format($totaluri['total_cu_tva'], 2, ',', '.') : '0,00') . ' Lei</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>
<br>
<!-- Afișarea metodei de plată sub total -->
<div style="text-align:right; font-size: 10px; margin-top: 5px;">
    <strong>Metoda de plată:</strong> ' . htmlspecialchars($metodaPlata) . '
</div>';

$content .= '
</body>
</html>';

$pdf->writeHTML($content, true, false, true, false, '');

$textSubsolFactura = isset($dateFirma['text_subsol_factura']) ? $dateFirma['text_subsol_factura'] : '';
$pdf->setPage($pdf->getNumPages());
scrieSubsolFixFactura($pdf, $delegateSection, $textSubsolFactura);

$serie_doc = isset($factura['serie_factura']) ? $factura['serie_factura'] : '';
$numar_doc = isset($factura['nr_factura']) ? $factura['nr_factura'] : '';
$den_doc = 'Factura_' . $serie_doc . '_' . $numar_doc . '.pdf';

// Dacă fișierul este inclus din email_factura.php și vrem PDF-ul ca string
if (!empty($pdf_return_as_string)) {
    $pdf_output_string = $pdf->Output($den_doc, 'S');
    return;
}

// Comportament normal: afișează PDF-ul în browser
$pdf->Output($den_doc, 'I');
exit;
?>
