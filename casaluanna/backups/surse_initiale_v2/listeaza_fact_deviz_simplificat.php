<?php
session_start();
require_once('database_connection.php');
require_once('tcpdf/tcpdf.php');

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
    $sql = "SELECT * FROM vanzari WHERE id_factura = :id_factura";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id_factura' => $id_factura]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getDevizDetails($pdo, $id_deviz) {
    if (!$id_deviz) return null;
    $sql = "SELECT nr_deviz, marca_auto, tip_auto, nr_inmatriculare FROM devize WHERE id_deviz = :id_deviz";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id_deviz' => $id_deviz]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
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

// Verificăm ca id_factura să fie specificat
if (!isset($_GET['id_factura'])) {
    die("ID facturii nu este specificat.");
}
$id_factura = $_GET['id_factura'];

// Obținerea datelor din bază
$factura = getFactura($pdo, $id_factura);
if (!$factura) {
    die("Factura cu ID-ul specificat nu a fost găsită.");
}
$dateFirma = getDateFirma($pdo);
$vanzari_brute = getVanzari($pdo, $id_factura); // Preluam vanzarile brute
$totaluri = getTotaluri($pdo, $id_factura);
$index_incarcare = getIndexIncarcare($pdo, $id_factura);

// LOGICA DE PROCESARE SI AGREGARE A VANZARILOR
$vanzari_procesate = [];
$deviz_agregat = [];

foreach ($vanzari_brute as $vanzare) {
    if (!empty($vanzare['id_deviz']) && $vanzare['id_deviz'] > 0) {
        $id_deviz = $vanzare['id_deviz'];
        // Daca este prima intrare pentru acest deviz, initializam agregarea
        if (!isset($deviz_agregat[$id_deviz])) {
            $devizDetails = getDevizDetails($pdo, $id_deviz);
            $deviz_agregat[$id_deviz] = [
                'den_p' => 'REPARATIE AUTO conform deviz nr. ' . htmlspecialchars($devizDetails['nr_deviz'] ?? '') . ' - ' . htmlspecialchars($devizDetails['nr_inmatriculare'] ?? ''),
                'cantitate' => 1,
                'um' => 'H87',
                'pret_vanzare' => 0,
                'valoare_vanzare' => 0,
                'tva_col' => 0,
                'valoare_vanzare_cu_tva' => 0,
                'cota_tva' => $vanzare['cota_tva'], // Presupunem ca TVA-ul este constant pentru un deviz
                'is_aggregated' => true,
            ];
        }
        // Insumam valorile
        $deviz_agregat[$id_deviz]['valoare_vanzare'] += $vanzare['valoare_vanzare'];
        $deviz_agregat[$id_deviz]['tva_col'] += $vanzare['tva_col'];
        $deviz_agregat[$id_deviz]['valoare_vanzare_cu_tva'] += $vanzare['valoare_vanzare_cu_tva'];
    } else {
        // Adaugam vanzarile fara deviz direct in lista procesata
        $vanzari_procesate[] = $vanzare;
    }
}

// Adaugam liniile agregate de deviz in lista finala si calculam preturile
foreach ($deviz_agregat as $id_deviz => $agregat) {
    // Pretul de vanzare pentru linia agregata este totalul cu TVA
    $deviz_agregat[$id_deviz]['pret_vanzare'] = $agregat['valoare_vanzare_cu_tva'];
    $vanzari_procesate[] = $deviz_agregat[$id_deviz];
}

// Formatarea datelor (partea aceasta ramane la fel)
$data_factura = isset($factura['data_factura']) ? date("d-m-Y", strtotime($factura['data_factura'])) : 'N/A';
$data_scadenta = isset($factura['data_scadenta']) ? date("d-m-Y", strtotime($factura['data_scadenta'])) : 'N/A';

$logo_server_path = '';
if (!empty($dateFirma['logo'])) {
    $logo_server_path = ltrim($dateFirma['logo'], '/\\');
    if (!file_exists($logo_server_path)) {
        $logo_server_path = '';
    }
}

$metodePlata = [
    "1" => "Instrument nedefinit", "2" => "Plată prin sistem de compensare automată (credit)", "3" => "Debit prin sistem de compensare automată", "4" => "Reversare debit cerere ACH", "5" => "Reversare credit cerere ACH", "6" => "Credit cerere ACH", "7" => "Debit cerere ACH", "8" => "Blocat", "9" => "Compensare națională sau regională", "10" => "Numerar", "11" => "Reversare credit economii ACH", "12" => "Reversare debit economii ACH", "13" => "Credit economii ACH", "14" => "Debit economii ACH", "15" => "Credit transfer carte de cont", "16" => "Debit transfer carte de cont", "17" => "Credit cerere CCD prin concentrarea/distribuirea de numerar", "18" => "Debit cerere CCD prin concentrarea/distribuirea de numerar", "19" => "Credit CTP tranzacție corporativă ACH", "20" => "Cec", "21" => "Ordin bancar", "22" => "Ordin bancar certificat", "23" => "Cec bancar (emis de o instituție bancară sau similară)", "24" => "Bilet la ordin în așteptarea acceptării", "25" => "Cec certificat", "26" => "Cec local", "27" => "Debit CTP tranzacție corporativă ACH", "28" => "Credit CTX tranzacție corporativă ACH", "29" => "Debit CTX tranzacție corporativă ACH", "30" => "Transfer de credit", "31" => "Transfer de debit", "32" => "Credit CCD+ prin cerere ACH", "33" => "Debit CCD+ prin cerere ACH", "34" => "Plată și depunere prearanjate ACH (PPD)", "35" => "Credit economii CCD prin concentrarea/distribuirea de numerar", "36" => "Debit economii CCD prin concentrarea/distribuirea de numerar", "37" => "Credit CTP economii tranzacție corporativă ACH", "38" => "Debit CTP economii tranzacție corporativă ACH", "39" => "Credit CTX economii tranzacție corporativă ACH", "40" => "Debit CTX economii tranzacție corporativă ACH", "41" => "Credit economii CCD+ prin cerere ACH", "42" => "Plată în cont bancar", "43" => "Debit economii CCD+ prin cerere ACH", "44" => "Bilet la ordin acceptat", "45" => "Transfer de credit home-banking referențiat", "46" => "Transfer de debit interbancar", "47" => "Transfer de debit home-banking", "48" => "Card bancar", "49" => "Debit direct", "50" => "Plată prin postgiro", "51" => "FR, normă 6 97-Telereglement CFONB (Organizația Franceză pentru Standarde Bancare) - Opțiunea A", "52" => "Plată comercială urgentă", "53" => "Plată urgentă de Trezorerie", "54" => "Card de credit", "55" => "Card de debit", "56" => "Bankgiro", "57" => "Acord permanent", "58" => "Transfer de credit SEPA", "59" => "Debit direct SEPA", "60" => "Poliță de plată", "61" => "Poliță de plată semnată de debitor", "62" => "Poliță de plată semnată de debitor și garantată de bancă", "63" => "Poliță de plată semnată de debitor și garantată de o terță parte", "64" => "Poliță de plată semnată de bancă", "65" => "Poliță de plată semnată de bancă și garantată de o altă bancă", "66" => "Poliță de plată semnată de o terță parte", "67" => "Poliță de plată semnată de o terță parte și garantată de bancă", "68" => "Serviciu de plată online", "70" => "Bilet tras de creditor asupra debitorului", "74" => "Bilet tras de creditor asupra unei bănci", "75" => "Bilet tras de creditor, garantat de o altă bancă", "76" => "Bilet tras de creditor asupra unei bănci și garantat de o terță parte", "77" => "Bilet tras de creditor asupra unei terțe părți", "78" => "Bilet tras de creditor asupra unei terțe părți, acceptat și garantat de bancă", "91" => "Ordin bancar netransferabil", "92" => "Cec local netransferabil", "93" => "Referință giro", "94" => "Giro urgent", "95" => "Giro format liber", "96" => "Metoda solicitată pentru plată nu a fost utilizată", "97" => "Compensare între parteneri", "ZZZ" => "Definit mutual"
];
$codMetodaPlata = $factura['cod_metoda_plata'] ?? null;
$metodaPlata = $metodePlata[$codMetodaPlata] ?? 'N/A';

// START GENERARE PDF (la fel ca originalul)
$pdf = new TCPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$cod_fiscal = htmlspecialchars($factura['cod_fiscal'] ?? 'N/A');
$pdf->SetTitle('Factura: ' . htmlspecialchars($factura['nr_factura']) . ' client: ' . $cod_fiscal);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(15, 20, 15);
$pdf->SetAutoPageBreak(TRUE, 20);
$pdf->SetFont('freeserif', '', 11);
$pdf->AddPage();

if (!empty($logo_server_path) && file_exists($logo_server_path)) {
    $pdf->Image($logo_server_path, 15, 10, 50, 20, '', '', 'T', false, 300, '', false, false, 0, false, false, false);
}

// ... Restul codului pentru definirea $bancaIbanList, $clientBancaIbanList, $codFiscalDisplay este identic ...
// ... Codul HTML pentru antet si detalii furnizor/client este identic ...
$banci = isset($dateFirma['banca']) ? (is_array($dateFirma['banca']) ? $dateFirma['banca'] : [$dateFirma['banca']]) : [];
$ibanuri = isset($dateFirma['cont_banca']) ? (is_array($dateFirma['cont_banca']) ? $dateFirma['cont_banca'] : [$dateFirma['cont_banca']]) : [];

$bancaIbanList = '';
$max = max(count($banci), count($ibanuri));
for ($i = 0; $i < $max; $i++) {
    if (isset($banci[$i])) {
        $bancaIbanList .= htmlspecialchars($banci[$i]) . '<br>';
    }
    if (isset($ibanuri[$i])) {
        $bancaIbanList .= htmlspecialchars($ibanuri[$i]) . '<br>';
    }
}
if (empty($bancaIbanList)) {
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

<table class="header" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td style="width:33%; vertical-align: top;">
            <span class="title">Factura</span><br>
            <span class="invoice-number blue">' . (isset($factura['serie_factura']) ? htmlspecialchars($factura['serie_factura']) : 'N/A') . ' ' . htmlspecialchars($factura['nr_factura']) . '</span>
        </td>
        <td style="width:34%; vertical-align: top;">
            <span class="header-details">Data Emitere:</span> ' . $data_factura . '<br>
            <span class="header-details">Data Scadență:</span> ' . $data_scadenta . '
        </td>
        <td style="width:33%; vertical-align: top; text-align: right;">
            <span class="header-details">Index încărcare:</span> <strong>' . $index_incarcare . '</strong><br>
        </td>
    </tr>
</table>

<br>
<hr style="border:2px solid #000000;">

<table class="details" cellpadding="0" cellspacing="0" style="width:100%;">
    <tr>
        <td style="width: 50%; vertical-align: top; padding-right: 5px;">
            <div>
                <strong>Furnizor:</strong><br>
                <table class="nested-table" cellpadding="0" cellspacing="0">
                    <tr><td class="value">' . ($dateFirma['den_ent'] ?? 'N/A') . '</td></tr>
                    <tr><td class="label"><strong>CUI:</strong></td><td class="value">' . ($codFiscalDisplay ?? 'N/A') . '</td></tr>
                    <tr><td class="label"><strong>Reg. Com.:</strong></td><td class="value">' . ($dateFirma['nr_reg_com'] ?? 'N/A') . '</td></tr>
                    <tr><td class="label"><strong>Județ:</strong></td><td class="value">' . ($dateFirma['judet'] ?? 'N/A') . '</td></tr>
                    <tr><td class="label"><strong>Localitate:</strong></td><td class="value">' . ($dateFirma['localitate'] ?? 'N/A') . '</td></tr>
                    <tr><td class="label"><strong>Adresă:</strong></td><td class="value">' . ($dateFirma['sediu'] ?? 'N/A') . '</td></tr>
                    <tr><td class="label"><strong>Banca și IBAN:</strong></td><td class="value">' . $bancaIbanList . '</td></tr>
                    <tr><td class="label"><strong>Email:</strong></td><td class="value">' . $email . '</td></tr>
                </table>
            </div>
        </td>
        <td style="width: 50%; vertical-align: top; padding-left: 5px;">
            <div>
                <strong>Client:</strong><br>
                <table class="nested-table" cellpadding="0" cellspacing="0">
                    <tr><td class="value">' . ($factura['nume'] ?? 'N/A') . '</td></tr>
                    <tr><td class="label"><strong>CUI/CNP:</strong></td><td class="value">' . ($factura['cod_fiscal'] ?? 'N/A') . '</td></tr>
                    <tr><td class="label"><strong>Tara:</strong></td><td class="value">' . ($factura['adresa_tara'] ?? 'N/A') . '</td></tr>
                    <tr><td class="label"><strong>Reg. Com./CI:</strong></td><td class="value">' . ($factura['cod_inmatriculare'] ?? 'N/A') . '</td></tr>
                    <tr><td class="label"><strong>Județ:</strong></td><td class="value">' . ($factura['adresa_judet'] ?? 'N/A') . '</td></tr>
                    <tr><td class="label"><strong>Localitate:</strong></td><td class="value">' . ($factura['adresa_localitate'] ?? 'N/A') . '</td></tr>
                    <tr><td class="label"><strong>Adresă:</strong></td><td class="value">' . ($factura['adresa'] ?? 'N/A') . '</td></tr>
                    <tr><td class="label"><strong>Banca:</strong></td><td class="value">' . ($factura['banca'] ?? 'N/A') . '</td></tr>
                    <tr><td class="label"><strong>IBAN:</strong></td><td class="value">' . ($factura['iban'] ?? 'N/A') . '</td></tr>
                </table>
            </div>
        </td>
    </tr>
</table>

<br><br>

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
        
// NOUA BUCLA PENTRU AFIȘAREA PRODUSELOR/SERVICIILOR
$counter = 1;
foreach ($vanzari_procesate as $vanzare) {
    // Verificam daca linia este una agregata sau una standard
    if (isset($vanzare['is_aggregated']) && $vanzare['is_aggregated'] === true) {
        // LINIE AGREGATA (DEVIZ)
        $den_p = $vanzare['den_p'];
        $cantitate = number_format($vanzare['cantitate'], 2, ',', '.');
        $um = htmlspecialchars($vanzare['um']);
        $valoare_vanzare = number_format($vanzare['valoare_vanzare'], 2, ',', '.');
        $cota_tva = htmlspecialchars($vanzare['cota_tva']);
        $valoare_tva = number_format($vanzare['tva_col'], 2, ',', '.');
        $valoare_cu_tva = number_format($vanzare['valoare_vanzare_cu_tva'], 2, ',', '.');
        
        // Pentru o linie agregata cu cantitatea 1, pretul unitar este egal cu valoarea totala
        $pu_fara_tva_display = $valoare_vanzare;
        $pret_cu_tva_display = $valoare_cu_tva;
    } else {
        // LINIE STANDARD (PRODUS INDIVIDUAL)
        $pret_fara_tva = ($vanzare['cantitate'] != 0) ? round((($vanzare['valoare_vanzare_cu_tva'] / $vanzare['cantitate']) - ($vanzare['tva_col'] / $vanzare['cantitate'])), 2) : 0;
        
        $den_p = htmlspecialchars($vanzare['den_p'] ?? 'N/A');
        $cantitate = number_format($vanzare['cantitate'] ?? 0, 2, ',', '.');
        $um = htmlspecialchars($vanzare['um'] ?? 'BUC');
        $valoare_vanzare = number_format($vanzare['valoare_vanzare'] ?? 0, 2, ',', '.');
        $cota_tva = htmlspecialchars($vanzare['cota_tva'] ?? 'N/A');
        $valoare_tva = number_format($vanzare['tva_col'] ?? 0, 2, ',', '.');
        $valoare_cu_tva = number_format($vanzare['valoare_vanzare_cu_tva'] ?? 0, 2, ',', '.');

        $pu_fara_tva_display = number_format($pret_fara_tva, 2, ',', '.');
        $pret_cu_tva_display = number_format($vanzare['pret_vanzare'] ?? 0, 2, ',', '.');
    }

    $bgcolor = ($counter % 2 == 0) ? '#f9f9f9' : '#ffffff';

    $content .= '
            <tr style="background-color:' . $bgcolor . ';">
                <td class="counter">' . $counter++ . '</td>
                <td class="denumire">' . $den_p . '</td>
                <td class="cant">' . $cantitate . '</td>
                <td class="um">' . $um . '</td>
                <td class="pret">' . $pu_fara_tva_display . '</td>
                <td class="valoare">' . $valoare_vanzare . '</td>
                <td class="valoare_tva">' . $valoare_tva . ' (' . $cota_tva . '%)</td>
                <td class="pret_vanzare">' . $pret_cu_tva_display . '</td>
                <td class="valoare_cu_tva">' . $valoare_cu_tva . '</td>
            </tr>';
}


// ... Restul codului pentru sectiunea de totaluri, delegat, si subsol ramane identic ...
$delegatData = [];
if (!empty($factura['delegat'])) {
    $delegatData = json_decode($factura['delegat'], true);
    if (!is_array($delegatData)) {
        $delegatData = [];
    }
}
$delegatNume = $delegatData['nume'] ?? '__________';
$delegatCiSerie = $delegatData['ci_serie'] ?? '__________';
$delegatCiNumar = $delegatData['ci_numar'] ?? '__________';
$delegatMasina = $delegatData['masina'] ?? '_______________';
$delegatSemnatura = $delegatData['semnatura'] ?? '________';

$delegateSection = '
<div style="margin-top:20px; font-size:10px;">
    <p><strong>Numele delegatului:</strong> ' . htmlspecialchars($delegatNume) . '</p>
    <p><strong>B.I./C.I. seria:</strong> ' . htmlspecialchars($delegatCiSerie) . '  <strong>Numărul:</strong> ' . htmlspecialchars($delegatCiNumar) . '</p>
    <p><strong>Mașina:</strong> ' . htmlspecialchars($delegatMasina) . '</p>
    <p><strong>Semnătura:</strong> ' . htmlspecialchars($delegatSemnatura) . '</p>
</div>
<br>';

$content .= '
        </tbody>
    </table>
</div>
<br>

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
<div style="text-align:right; font-size: 10px; margin-top: 5px;">
    <strong>Metoda de plată:</strong> ' . htmlspecialchars($metodaPlata) . '
</div>
<br><br>';

$content .= $delegateSection;

$content .= '
<div class="footer">' . ($dateFirma['text_subsol_factura'] ?? '') . '</div>
</body>
</html>';

$pdf->writeHTML($content, true, false, true, false, '');

$den_doc = 'Factura_' . htmlspecialchars($factura['serie_factura']) . '_' . htmlspecialchars($factura['nr_factura']) . '.pdf';

$pdf->Output($den_doc, 'I');
?>