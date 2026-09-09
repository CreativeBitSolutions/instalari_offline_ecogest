<?php
require_once __DIR__.'/database_connection.php';
header('Cache-Control: no-store');
$resetInvoiceFilters=!isset($_GET['casa_fragment'])&&!empty($_SESSION['casa_reset_invoice_filters']);
if($resetInvoiceFilters)unset($_SESSION['casa_reset_invoice_filters']);
if(isset($_GET['casa_fragment']))ob_start();
include('header.php');
if(!empty($GLOBALS['casa_unverified']))echo '<div class="alert alert-warning m-3">Lista nu a fost verificată online. Verificați seria și ultimul număr facturat înainte de emitere.</div>';
if (!isset($_GET['casa_fragment']) && isset($_SESSION['completed_steps'])) {
    unset($_SESSION['completed_steps']);
}

// Definirea mapping‑ului metodelor de plată
$metode_plata = [
    "1" => "Instrument nedefinit",
    "2" => "Plată prin sistem de compensare automată (credit)",
    "3" => "Debit prin sistem de compensare automată",
    "4" => "Reversare debit cerere ACH",
    "5" => "Reversare credit cerere ACH",
    "6" => "Credit cerere ACH",
    "7" => "Debit cerere ACH",
    "8" => "Blocat",
    "9" => "Compensare națională sau regională",
    "10" => "Numerar",
    "11" => "Reversare credit economii ACH",
    "12" => "Reversare debit economii ACH",
    "13" => "Credit economii ACH",
    "14" => "Debit economii ACH",
    "15" => "Credit transfer carte de cont",
    "16" => "Debit transfer carte de cont",
    "17" => "Credit cerere CCD prin concentrarea/distribuirea de numerar",
    "18" => "Debit cerere CCD prin concentrarea/distribuirea de numerar",
    "19" => "Credit CTP tranzacție corporativă ACH",
    "20" => "Cec",
    "21" => "Ordin bancar - Bilet la ordin",
    "22" => "Ordin bancar certificat",
    "23" => "Cec bancar (emis de o instituție bancară sau similară)",
    "24" => "Bilet la ordin în așteptarea acceptării",
    "25" => "Cec certificat",
    "26" => "Cec local",
    "27" => "Debit CTP tranzacție corporativă ACH",
    "28" => "Credit CTX tranzacție corporativă ACH",
    "29" => "Debit CTX tranzacție corporativă ACH",
    "30" => "Transfer de credit",
    "31" => "Transfer de debit",
    "32" => "Credit CCD+ prin cerere ACH",
    "33" => "Debit CCD+ prin cerere ACH",
    "34" => "Plată și depunere prearanjate ACH (PPD)",
    "35" => "Credit economii CCD prin concentrarea/distribuirea de numerar",
    "36" => "Debit economii CCD prin concentrarea/distribuirea de numerar",
    "37" => "Credit CTP economii tranzacție corporativă ACH",
    "38" => "Debit CTP economii tranzacție corporativă ACH",
    "39" => "Credit CTX economii tranzacție corporativă ACH",
    "40" => "Debit CTX economii tranzacție corporativă ACH",
    "41" => "Credit economii CCD+ prin cerere ACH",
    "42" => "Plată în cont bancar",
    "43" => "Debit economii CCD+ prin cerere ACH",
    "44" => "Bilet la ordin acceptat",
    "45" => "Transfer de credit home-banking referențiat",
    "46" => "Transfer de debit interbancar",
    "47" => "Transfer de debit home-banking",
    "48" => "Card bancar",
    "49" => "Debit direct",
    "50" => "Plată prin postgiro",
    "51" => "FR, normă 6 97-Telereglement CFONB (Organizația Franceză pentru Standarde Bancare) - Opțiunea A",
    "52" => "Plată comercială urgentă",
    "53" => "Plată urgentă de Trezorerie",
    "54" => "Card de credit",
    "55" => "Card de debit",
    "56" => "Bankgiro",
    "57" => "Acord permanent",
    "58" => "Transfer de credit SEPA",
    "59" => "Debit direct SEPA",
    "60" => "Poliță de plată",
    "61" => "Poliță de plată semnată de debitor",
    "62" => "Poliță de plată semnată de debitor și garantată de bancă",
    "63" => "Poliță de plată semnată de debitor și garantată de o terță parte",
    "64" => "Poliță de plată semnată de bancă",
    "65" => "Poliță de plată semnată de bancă și garantată de o altă bancă",
    "66" => "Poliță de plată semnată de o terță parte",
    "67" => "Poliță de plată semnată de o terță parte și garantată de bancă",
    "68" => "Serviciu de plată online",
    "70" => "Bilet tras de creditor asupra debitorului",
    "74" => "Bilet tras de creditor asupra unei bănci",
    "75" => "Bilet tras de creditor, garantat de o altă bancă",
    "76" => "Bilet tras de creditor asupra unei bănci și garantat de o terță parte",
    "77" => "Bilet tras de creditor asupra unei terțe părți",
    "78" => "Bilet tras de creditor asupra unei terțe părți, acceptat și garantat de bancă",
    "91" => "Ordin bancar netransferabil",
    "92" => "Cec local netransferabil",
    "93" => "Referință giro",
    "94" => "Giro urgent",
    "95" => "Giro format liber",
    "96" => "Metoda solicitată pentru plată nu a fost utilizată",
    "97" => "Compensare între parteneri",
    "ZZZ" => "Definit mutual"
];

// Lista principală se citește STRICT din tabela facturi.
// Nu legăm aici incasari/vanzari/alte tabele, ca să nu se piardă facturi din afișare.
$sql = "SELECT *
        FROM facturi
        ORDER BY id_factura DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute();

$count_stmt = $pdo->query("SELECT COUNT(*) FROM facturi");
$count = (int)$count_stmt->fetchColumn();

// Datele de încasare se citesc separat, opțional, fără să influențeze lista de facturi.
// Dacă tabela incasari lipsește sau are probleme, lista facturilor se afișează oricum.
$incasari_facturi = [];
try {
    $stmt_incasari = $pdo->query("
        SELECT id_factura, MAX(data_incasarii) AS data_incasare
        FROM incasari
        GROUP BY id_factura
    ");
    while ($incasare_row = $stmt_incasari->fetch(PDO::FETCH_ASSOC)) {
        $incasari_facturi[(int)$incasare_row['id_factura']] = $incasare_row['data_incasare'];
    }
} catch (Throwable $e) {
    $incasari_facturi = [];
}

// Lista seriilor existente pentru filtrarea rapidă a facturilor
$sql_serii_facturi = "SELECT DISTINCT serie_factura 
                      FROM facturi 
                      WHERE serie_factura IS NOT NULL 
                        AND TRIM(serie_factura) <> '' 
                      ORDER BY serie_factura ASC";
$stmt_serii_facturi = $pdo->prepare($sql_serii_facturi);
$stmt_serii_facturi->execute();
$serii_facturi = $stmt_serii_facturi->fetchAll(PDO::FETCH_COLUMN);

// Interogarea pentru raportul total vânzări pe metoda de plată  
$sql_report = "SELECT f.cod_metoda_plata, SUM(v.valoare_vanzare_cu_tva) AS total_vanzari
               FROM facturi f
               LEFT JOIN vanzari v ON f.id_factura = v.id_factura
               GROUP BY f.cod_metoda_plata";
$stmt_report = $pdo->prepare($sql_report);
$stmt_report->execute();
$report_results = $stmt_report->fetchAll(PDO::FETCH_ASSOC);
?>
<!-- Begin Page Content -->
<div class="container-fluid">
  <!-- Afișare mesaje flash, dacă există -->
  <?php if(!isset($_GET['casa_fragment']) && isset($_SESSION['message'])): ?>
      <div class="alert alert-<?php echo isset($_SESSION['message_type']) ? $_SESSION['message_type'] : 'info'; ?> alert-dismissible fade show" role="alert">
          <?php
            echo htmlspecialchars($_SESSION['message']);
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
          ?>
          <button type="button" class="close" data-dismiss="alert" aria-label="Închide">
              <span aria-hidden="true">&times;</span>
          </button>
      </div>
  <?php endif; ?>

  <!-- Page Heading -->
  <div class="d-sm-flex align-items-center justify-content-between mb-4">
      <h1 class="h3 mb-0 text-gray-800">Lista Facturilor de Vânzare</h1>
  </div>

  <!-- Secțiunea pentru listarea facturilor -->
  <div class="card shadow mb-4">
    <div class="card-header py-3">
      <h6 class="m-0 font-weight-bold text-primary">
        Lista facturilor de vânzare - <?php echo htmlspecialchars($count); ?> facturi
      </h6>
    </div>
    
    <div class="card-body">
      <a href="factura.php" class="btn btn-primary mb-3">Adaugă Factura (380)</a>
      <button id="updateAnafBtn" class="btn btn-success mb-3">Verificare status trimitere ANAF</button>
      <!-- Butonul de Filtrare Facturi -->
      <button id="filterClientiBtn" class="btn btn-info mb-3" data-toggle="modal" data-target="#modal_filtrare_clienti">
          Filtrare Facturi
      </button>
      <button id="clearFacturiFiltersBtn" type="button" class="btn btn-light border mb-3">
          Afișează toate facturile
      </button>
   <!-- Buton pentru afișarea raportului în modal -->
<button id="viewSalesByPaymentBtn" class="btn btn-info mb-3" data-toggle="modal" data-target="#modal_sales_by_payment">
  Vezi Vânzări pe Metode de Plată
</button>

<!-- Buton PDF (păstrează modalul existent, doar etichetă clară) -->
<button id="btnRaportFacturiPdf" class="btn btn-secondary mb-3" data-toggle="modal" data-target="#modalRaportFacturi">
  Raport Facturi (PDF)
</button>

<!-- Buton Excel (deschide noul modal) -->
<button id="btnRaportFacturiExcel" class="btn btn-success mb-3" data-toggle="modal" data-target="#modalRaportFacturiExcel">
  Raport Facturi (Excel)
</button>

<div class="row mb-3">
  <div class="col-md-4 col-lg-3">
    <label for="serie_filter_select" class="font-weight-bold mb-1">Filtrare după serie</label>
    <select id="serie_filter_select" class="form-control" onchange="if (window.facturiDataTable) { var serie = this.value; window.facturiDataTable.column(1).search(serie ? '^' + $.fn.dataTable.util.escapeRegex(serie) + '$' : '', true, false).draw(); }">
      <option value="" selected>Toate seriile</option>
      <?php foreach ($serii_facturi as $serie_filtru): ?>
        <option value="<?php echo htmlspecialchars($serie_filtru); ?>">
          <?php echo htmlspecialchars($serie_filtru); ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
</div>

      <?php
      // Verificare înregistrări în 'vanzari' cu id_factura=0
      $verificareVanzariSQL = "SELECT * FROM vanzari WHERE id_factura = 0";
      $verificareVanzariStmt = $pdo->prepare($verificareVanzariSQL);
      $verificareVanzariStmt->execute();
      $numarVanzari = count($verificareVanzariStmt->fetchAll(PDO::FETCH_ASSOC));

      if ($numarVanzari > 0) {
          echo '<a href="factura.php?tip_factura=751" class="btn btn-danger mb-3">
                  Continua Factura Bon Fiscal din aplicatia de Vanzare (751)
                </a>';
      }
      ?>
      <style>
        .Optiuni { width: 8em; }
        .text-success { color: green; }
        .text-danger { color: red; }
        .text-warning { color: orange; }
        .facturi-table-wrap {
            overflow-x: visible;
        }
        .facturi-responsive-table {
            width: 100% !important;
            table-layout: auto;
        }
        .facturi-responsive-table th,
        .facturi-responsive-table td {
            white-space: normal;
            overflow-wrap: anywhere;
            vertical-align: middle;
        }
        .facturi-responsive-table .invoice-cell {
            position: relative;
            overflow: visible;
        }
        .facturi-responsive-table .btn-group {
            max-width: 100%;
            flex-wrap: nowrap;
        }
        .facturi-responsive-table .btn-group .btn {
            white-space: nowrap;
        }
        #facturiTable tbody tr:hover > td {
            background-color: #f8f9fc;
        }
        #facturiTable tbody tr.offline-origin-row > td {
            background-color: #eaf3ff !important;
        }
        #facturiTable tbody tr.offline-origin-row:hover > td {
            background-color: #dceaff !important;
        }
        #facturiTable tbody tr.offline-unfinished-row > td {
            background-color: #fff3cd !important;
        }
        #facturiTable tbody tr.offline-unfinished-row:hover > td {
            background-color: #ffe8a1 !important;
        }
        #facturiTable tbody tr.offline-unfinished-row > td:first-child {
            border-left: 5px solid #d39e00;
        }
        #facturiTable tbody tr.anaf-unauthorized-row > td {
            background-color: #fde8e8 !important;
            color: #212529;
        }
        #facturiTable tbody tr.anaf-unauthorized-row:hover > td {
            background-color: #fbd5d5 !important;
        }
        .anaf-error-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 16px;
            height: 16px;
            margin-right: 6px;
            border-radius: 50%;
            background-color: #dc3545;
            color: #fff;
            font-size: 10px;
            line-height: 1;
            vertical-align: middle;
        }
        .factura-produse-preview {
            display: none;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1050;
            width: min(520px, calc(100vw - 48px));
            padding: 10px 12px;
            border: 1px solid #d1d3e2;
            border-radius: 6px;
            background: #fff;
            color: #212529;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .16);
            font-size: 13px;
            line-height: 1.35;
            white-space: normal;
            pointer-events: none;
        }
        .factura-produse-preview.is-visible {
            display: block;
        }
        .factura-produse-preview-title {
            margin-bottom: 6px;
            font-weight: 700;
            color: #4e73df;
        }
        .factura-produse-preview-item {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 8px;
            padding: 5px 0;
            border-top: 1px solid #eaecf4;
        }
        .factura-produse-preview-item:first-of-type {
            border-top: 0;
        }
        .factura-produse-preview-name {
            min-width: 0;
            overflow-wrap: anywhere;
        }
        .factura-produse-preview-meta {
            color: #5a5c69;
            text-align: right;
            white-space: nowrap;
        }
        .factura-produse-preview-more {
            margin-top: 6px;
            color: #5a5c69;
            font-size: 12px;
        }
         .client-scurt {
            max-width: 150px; 
            white-space: nowrap; 
            overflow: hidden; 
            text-overflow: ellipsis; 
            word-wrap: break-word;
        }
        @media (max-width: 767.98px) {
            .card-body {
                padding-left: .75rem;
                padding-right: .75rem;
            }
            .facturi-table-wrap {
                overflow-x: visible !important;
            }
            .dataTables_wrapper .row {
                margin-left: 0;
                margin-right: 0;
            }
            .dataTables_wrapper [class*="col-"] {
                padding-left: 0;
                padding-right: 0;
            }
            .dataTables_length,
            .dataTables_filter {
                text-align: left !important;
            }
            .dataTables_filter label,
            .dataTables_filter input {
                width: 100%;
                margin-left: 0 !important;
            }
            #facturiTable {
                border: 0;
            }
            #facturiTable thead,
            #facturiTable tfoot {
                display: none;
            }
            #facturiTable,
            #facturiTable tbody,
            #facturiTable tr,
            #facturiTable td {
                display: block;
                width: 100% !important;
            }
            #facturiTable tbody tr {
                margin-bottom: 12px;
                border: 1px solid #d1d3e2;
                border-radius: 8px;
                background: #fff;
                box-shadow: 0 2px 8px rgba(0, 0, 0, .04);
                overflow: visible;
            }
            #facturiTable tbody tr > td {
                position: relative;
                min-height: 42px;
                padding: .7rem .75rem .7rem 42%;
                border-right: 0 !important;
                border-left: 0 !important;
                border-bottom: 1px solid #eaecf4;
            }
            #facturiTable tbody tr > td:last-child {
                border-bottom: 0;
            }
            #facturiTable tbody tr > td::before {
                content: attr(data-label);
                position: absolute;
                left: .75rem;
                top: .8rem;
                width: 34%;
                color: #5a5c69;
                font-size: 12px;
                font-weight: 700;
                text-transform: uppercase;
            }
            #facturiTable tbody tr > td.invoice-cell {
                padding-left: .75rem;
                padding-top: .75rem;
            }
            #facturiTable tbody tr > td.invoice-cell::before {
                position: static;
                display: block;
                width: auto;
                margin-bottom: .4rem;
            }
            .client-scurt {
                max-width: none;
                white-space: normal;
            }
            .factura-produse-preview {
                position: static;
                width: 100%;
                margin-top: 10px;
                box-shadow: none;
                pointer-events: auto;
            }
            #facturiTable tbody tr:hover .factura-produse-preview,
            #facturiTable tbody tr:focus-within .factura-produse-preview {
                display: block;
            }
        }
      </style>                   

      <div class="form-group px-3">
          <label for="casa-origin-filter">Originea facturilor</label>
          <select id="casa-origin-filter" class="form-control mb-3" aria-controls="facturiTable">
              <option value="">Afișează toate facturile</option>
              <option value="online">Afișează facturile din online</option>
              <option value="offline">Afișează facturile din offline</option>
          </select>
          <label for="casa-invoice-search">Caută în facturi</label>
          <input id="casa-invoice-search" type="search" class="form-control" placeholder="Număr, serie, client..." aria-controls="facturiTable" autocomplete="off">
      </div>
      <div class="table-responsive facturi-table-wrap">
        <link href="vendor/offline/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
        <link href="vendor/offline/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
        <table id="facturiTable" class="table table-striped table-bordered facturi-responsive-table" style="width:100%">
<thead>
    <tr>
        <th data-priority="1">Numar Factura</th>
        <th>Serie</th>
        <th data-priority="2">Data</th>
        <th data-priority="3">Valoare</th>
        <th data-priority="4">Client</th>
        <th>Tip Factură</th>
        <th>Data Validare</th>
        <th>Data Încărcare</th>
        <th>Trimis ANAF</th>
        <th>Data Corectare</th>
        <th>Data Încasare</th>
    </tr>
</thead>
          <tbody>
            <?php 
            if(isset($_GET['casa_fragment']))ob_start();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
              $storn_factura = strval($row['nr_factura']) . 'S';
              $nr_factura    = $row['nr_factura'];
              $serie_factura = $row['serie_factura'];
              $id_factura    = $row['id_factura'];
          // Considerăm automat încasată dacă e tip 751 sau există nrbon != 0
$este_bon_automat = ($row['tip_factura'] === '751') 
                    || (!empty($row['nrbon']) && (int)$row['nrbon'] !== 0);

              // Maparea tipului facturii
              switch ($row['tip_factura']) {
                  case '751':
                      $invoiceTypeCode = '751 - Bon Fiscal';
                      break;
                  case '389':
                      $invoiceTypeCode = '389 - Autofactură';
                      break;
                  case '384':
                      $invoiceTypeCode = '384 - Factură corectată';
                      break;
                  case '381':
                      $invoiceTypeCode = '381 - Factură storno';
                      break;
                  case '380':
                  default:
                      $invoiceTypeCode = '380 - Factură fiscală';
                      break;
              }
          
              // Calculul valorii totale din vanzari si lista de produse pentru preview
              $vanzari_factura = [];
              $f_tot_sql = "SELECT den_p, um, cantitate, pret_vanzare, valoare_vanzare_cu_tva
                            FROM vanzari
                            WHERE id_factura = :id_factura
                            ORDER BY id_vanz ASC";
              $f_tot_stmt = $pdo->prepare($f_tot_sql);
              $f_tot_stmt->execute(['id_factura' => $id_factura]);
              $total_val_vz_cu_tva = 0;
              while ($vanzare_row = $f_tot_stmt->fetch(PDO::FETCH_ASSOC)) {
                  $vanzari_factura[] = $vanzare_row;
                  $total_val_vz_cu_tva += (float)($vanzare_row['valoare_vanzare_cu_tva'] ?? 0);
              }
              $remaining_amount = $total_val_vz_cu_tva;
          
              // Calcul pentru stornări
              $stornari_sql = "SELECT id_factura FROM stornari WHERE id_factura_stornata = :id_factura";
              $stornari_stmt = $pdo->prepare($stornari_sql);
              $stornari_stmt->execute(['id_factura' => $id_factura]);
              $stornare_total = 0;
              while ($stornare_row = $stornari_stmt->fetch(PDO::FETCH_ASSOC)) {
                  $id_factura_stornare = $stornare_row['id_factura'];
                  $stornare_tot_sql = "SELECT SUM(valoare_vanzare_cu_tva) AS total_stornare FROM vanzari WHERE id_factura = :id_factura";
                  $stornare_tot_stmt = $pdo->prepare($stornare_tot_sql);
                  $stornare_tot_stmt->execute(['id_factura' => $id_factura_stornare]);
                  $stornare_tot_row = $stornare_tot_stmt->fetch(PDO::FETCH_ASSOC);
                  $stornare_amount = $stornare_tot_row['total_stornare'] ?? 0;
                  $stornare_total += $stornare_amount;
              }
              $remaining_amount += $stornare_total;
              $remaining_amount = max(0, $remaining_amount);
              $remaining_amount_formatted = htmlspecialchars(number_format($remaining_amount, 2, ',', '.'));
              $can_receive_payment = $remaining_amount > 0;
              $produse_preview_output = "<div class='factura-produse-preview' role='tooltip'>";
              $produse_preview_output .= "<div class='factura-produse-preview-title'>Produse pe factura</div>";
              if (!empty($vanzari_factura)) {
                  $produse_preview_limit = 5;
                  foreach (array_slice($vanzari_factura, 0, $produse_preview_limit) as $produs_preview) {
                      $cantitate_preview = number_format((float)($produs_preview['cantitate'] ?? 0), 2, ',', '.');
                      $cantitate_preview = rtrim(rtrim($cantitate_preview, '0'), ',');
                      $um_preview = trim((string)($produs_preview['um'] ?? ''));
                      $valoare_preview = number_format((float)($produs_preview['valoare_vanzare_cu_tva'] ?? 0), 2, ',', '.');
                      $produse_preview_output .= "<div class='factura-produse-preview-item'>";
                      $produse_preview_output .= "<div class='factura-produse-preview-name'>" . htmlspecialchars((string)($produs_preview['den_p'] ?? 'Produs')) . "</div>";
                      $produse_preview_output .= "<div class='factura-produse-preview-meta'>" . htmlspecialchars($cantitate_preview . ($um_preview !== '' ? ' ' . $um_preview : '')) . "<br>" . htmlspecialchars($valoare_preview) . " lei</div>";
                      $produse_preview_output .= "</div>";
                  }
                  $produse_ramase_preview = count($vanzari_factura) - $produse_preview_limit;
                  if ($produse_ramase_preview > 0) {
                      $produse_preview_output .= "<div class='factura-produse-preview-more'>+" . htmlspecialchars((string)$produse_ramase_preview) . " produse</div>";
                  }
              } else {
                  $produse_preview_output .= "<div class='text-muted'>Nu exista produse in factura.</div>";
              }
              $produse_preview_output .= "</div>";
          
              // Formatăm valoarea facturii
              if ($total_val_vz_cu_tva < 0) { 
                  $valoare_formatted = "<span class='text-danger'>" . htmlspecialchars(number_format($total_val_vz_cu_tva, 2, ',', '.')) . "</span>";
              } else {
                  $valoare_formatted = htmlspecialchars(number_format($total_val_vz_cu_tva, 2, ',', '.'));
              }
          
              // Data validare
              $remoteRecord=casa_one($pdo,'SELECT payload,checked_at FROM casa_remote_status WHERE id_factura=?',[$id_factura]);
              $remoteStatus=$remoteRecord?json_decode($remoteRecord['payload'],true):null;
              if($remoteStatus){$row['data_validare']=$remoteStatus['data_validare'];$row['data_incarcare']=$remoteStatus['data_incarcare'];}
              $data_validare = $row['data_validare'];
              if ($data_validare != '0000-00-00 00:00:00' && !empty($data_validare)) {
                  $data_validare_formatted = date("d.m.Y H:i:s", strtotime($data_validare));
                  $data_validare_output = "<span class='text-success'>" . htmlspecialchars($data_validare_formatted) . "</span>";
              } else {
                  $data_validare_output = "<span class='text-danger'>Nevalidată</span>";
              }
          
             // Data încărcare, index și status trimitere ANAF
$data_incarcare = $row['data_incarcare'];
// Ridicăm ultimele detalii din istoric_incarcari
$query_incarcare = "
    SELECT index_incarcare, status, response 
    FROM istoric_incarcari 
    WHERE id_factura = :id_factura 
    ORDER BY id DESC 
    LIMIT 1
";
$stmt_incarcare = $pdo->prepare($query_incarcare);
$stmt_incarcare->bindParam(':id_factura', $id_factura, PDO::PARAM_INT);
$stmt_incarcare->execute();
$inc = $stmt_incarcare->fetch(PDO::FETCH_ASSOC);
if($remoteStatus!==null)$inc=$remoteStatus['anaf'];

$index_incarcare     = $inc['index_incarcare'] ?? null;
$status_incarcare    = $inc['status']         ?? '';
$response_incarcare  = $inc['response']       ?? '';
$status_incarcare_normalizat = strtolower(trim((string)$status_incarcare));
$response_incarcare_decodat = json_decode((string)$response_incarcare, true);
$response_este_unauthorized = false;
if (is_array($response_incarcare_decodat)) {
    $response_este_unauthorized = strcasecmp((string)($response_incarcare_decodat['message'] ?? ''), 'Unauthorized') === 0
        && (string)($response_incarcare_decodat['status'] ?? '') === '401';
} else {
    $response_este_unauthorized = stripos((string)$response_incarcare, 'Unauthorized') !== false
        && strpos((string)$response_incarcare, '401') !== false;
}
$rand_eroare_autorizare_anaf = $status_incarcare_normalizat === 'eroare' && $response_este_unauthorized;

if (!empty($data_incarcare) && $data_incarcare !== '0000-00-00 00:00:00') {
    $data_incarcare_output = "<span class='text-success'>"
        . htmlspecialchars(date("d.m.Y H:i:s", strtotime($data_incarcare)))
        . "</span>";

    if ($status_incarcare_normalizat === 'eroare') {
        $data_incarcare_output .= "<br><span class='text-danger'>
            Factura nu s-a trimis la ANAF: "
            . htmlspecialchars($response_incarcare)
            . "</span>";
    } elseif (!empty($index_incarcare)) {
        $data_incarcare_output .= "<br><span class='text-muted'>
            Index încărcare: "
            . htmlspecialchars($index_incarcare)
            . "</span>";
    }
} else {
    $data_incarcare_output = "<span class='text-danger'>Neîncărcată</span>";
}
        // Factura este considerată încărcată la ANAF doar dacă are data încărcării,
// are index de încărcare și ultimul status nu este eroare.
$factura_este_incarcata_anaf = (
    !empty($data_incarcare)
    && $data_incarcare !== '0000-00-00 00:00:00'
    && !empty($index_incarcare)
    && $status_incarcare_normalizat !== 'eroare'
);
              // Verificare mesaj ANAF
              $trimis_anaf = "nu";
              if (!empty($index_incarcare)) {
                  $sql_check = "SELECT COUNT(*) FROM mesaje_anaf_structurat 
                                WHERE index_incarcare = :index_incarcare 
                                AND tip = 'FACTURA TRIMISA'";
                  $check_stmt = $pdo->prepare($sql_check);
                  $check_stmt->execute(['index_incarcare' => $index_incarcare]);
                  $check_count = $check_stmt->fetchColumn();
                  if ($check_count > 0) {
                      $trimis_anaf = "da";
                  }
              }
          
              if($remoteStatus!==null)$trimis_anaf=$remoteStatus['anaf_sent']?'da':'nu';
              // Data corectare
              $data_corectare = $row['data_corectare'];
              if ($data_corectare != '0000-00-00 00:00:00' && !empty($data_corectare)) {
                  $data_corectare_formatted = date("d.m.Y H:i:s", strtotime($data_corectare));
                  $data_corectare_output = "<span class='text-warning'>" . htmlspecialchars($data_corectare_formatted) . "</span>";
              } else {
                  $data_corectare_output = "";
              }
              
              // Data Încasare – preluată din tabela incasari
            // Data Încasare – cu prioritate: 751 sau nrbon != 0 => automat încasată/generată pe baza notei
$data_incasare = $incasari_facturi[(int)$id_factura] ?? null;
if ($este_bon_automat) {
    // Folosim data facturii ca reper vizual; afișăm și nrbon dacă există
    $bonDate = !empty($row['data_factura']) ? date("d.m.Y H:i:s", strtotime($row['data_factura'])) : '';
    $bonLabel = !empty($row['nrbon']) ? " (bon " . htmlspecialchars($row['nrbon']) . ")" : "";
    $data_incasare_output = "<span class='text-success'>Încasată / generată pe baza notei{$bonLabel}" 
        . ($bonDate ? " – " . htmlspecialchars($bonDate) : "") 
        . "</span>";
} else {
    if (!empty($data_incasare) && $data_incasare != '0000-00-00 00:00:00') {
        $data_incasare_formatted = date("d.m.Y H:i:s", strtotime($data_incasare));
        $data_incasare_output = "<span class='text-success'>" . htmlspecialchars($data_incasare_formatted) . "</span>";
    } else {
        $data_incasare_output = "<span class='text-danger'>Neîncasată</span>";
    }
}

          
        $localSync=casa_one($pdo,'SELECT state,origin,online_id,error,finalized,authority FROM casa_invoices WHERE id_factura=?',[$id_factura]);
        $createdHere=($localSync['origin']??'')==='local';
        $offlineWithoutNumber=$createdHere && is_numeric($nr_factura) && (int)$nr_factura===0;
        $canDeleteOffline=$createdHere && !$factura_este_incarcata_anaf && empty($remoteStatus['online_changed']) && ($remoteStatus['lifecycle']??'')!=='online' && !in_array($localSync['state']??'', ['delete_requested','deleted'], true);
        $rowClasses=[];
        if($createdHere || ($remoteStatus['source_origin']??'')==='offline')$rowClasses[]='offline-origin-row';
        if($offlineWithoutNumber)$rowClasses[]='offline-unfinished-row';
        if($rand_eroare_autorizare_anaf)$rowClasses[]='anaf-unauthorized-row';
        $invoiceOrigin=($createdHere || ($remoteStatus['source_origin']??'')==='offline')?'offline':'online';
        echo '<tr data-invoice-origin="'.$invoiceOrigin.'" class="'.casa_h(implode(' ', $rowClasses)).'">';

    // 1. Coloana "Numar Factura", care acum conține și meniul de acțiuni
$sort_key = is_numeric($nr_factura) ? (int)$nr_factura : (int)preg_replace('/\D+/', '', (string)$nr_factura);
echo "<td class='invoice-cell' data-label='Numar factura' data-order='" . htmlspecialchars($sort_key, ENT_QUOTES) . "'>";
        if ($rand_eroare_autorizare_anaf) {
            echo "<span class='anaf-error-icon' title='Eroare autorizare ANAF'><i class='fas fa-exclamation'></i></span>";
        }
        echo "<div class='btn-group'>";
            // Partea vizibilă: numărul facturii, care este și un link rapid către detalii.
echo "<a href='detalii_factura.php?id_factura=" . htmlspecialchars($id_factura) . "'  class='btn btn-light btn-sm' style='border-top-right-radius: 0; border-bottom-right-radius: 0;'>Factura " . htmlspecialchars($nr_factura) . "</a>";
            
            // Butonul mic (săgeata) care deschide meniul dropdown
            echo "<button type='button' class='btn btn-light btn-sm dropdown-toggle dropdown-toggle-split' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'>
                    <span class='sr-only'>Toggle Dropdown</span>
                  </button>";
                  
            // Meniul dropdown ascuns, care conține TOATE opțiunile
            echo "<div class='dropdown-menu dropdown-menu-right'>";
                
                echo "<a class='dropdown-item' href='detalii_factura.php?id_factura=" . htmlspecialchars($id_factura) . "'>
                        <i class='fas fa-info-circle fa-fw mr-2 text-info'></i>Detalii Factură
                      </a>";

                echo "<button class='dropdown-item duplicare' data-toggle='modal' data-target='#modal_duplicare'
                        data-id_factura='" . htmlspecialchars($id_factura) . "' 
                        data-nr_fact_duplicata='" . htmlspecialchars($storn_factura) . "'>
                        <i class='fas fa-copy fa-fw mr-2 text-muted'></i>Duplicare
                      </button>";
$este_deja_incasata = $este_bon_automat || (!empty($data_incasare) && $data_incasare != '0000-00-00 00:00:00');

                // Logica pentru Încasare
           if (!$este_deja_incasata && $createdHere && ($localSync['authority']??'')==='local') {
    if ($can_receive_payment) {
        echo "<button class='dropdown-item incasare' data-toggle='modal' data-target='#modal_incasare'
                data-id_factura='" . htmlspecialchars($id_factura) . "'
                data-nr_fact='" . htmlspecialchars($nr_factura) . "'
                data-suma_incasata='" . htmlspecialchars($remaining_amount) . "'>
                <i class='fas fa-dollar-sign fa-fw mr-2 text-success'></i>Încasare
              </button>";
    } else {
        echo "<button class='dropdown-item' disabled>
                <i class='fas fa-dollar-sign fa-fw mr-2 text-muted'></i>Nu se poate încasa
              </button>";
    }
}
                
                echo "<button style='display:none' class='dropdown-item stornare' data-toggle='modal' data-target='#modal_stornare' 
                        data-id_factura='" . htmlspecialchars($id_factura) . "' 
                        data-nr_factura='" . htmlspecialchars($nr_factura) . "'>
                        <i class='fas fa-undo fa-fw mr-2 text-warning'></i>Stornare
                      </button>";

                echo "<div class='dropdown-divider'></div>";
                
             if ($canDeleteOffline) {
    echo "<button class='dropdown-item stergere' data-toggle='modal' data-target='#modal_stergere' 
            data-id-factura='" . htmlspecialchars($id_factura) . "'>
            <i class='fas fa-trash-alt fa-fw mr-2 text-danger'></i>Ștergere
          </button>";
}

            echo "</div>"; // Sfârșit dropdown-menu
        echo "</div>"; // Sfârșit btn-group
        echo $produse_preview_output;
        if($offlineWithoutNumber){
            echo '<div class="mt-2 font-weight-bold text-dark">Factură neterminată, fără număr alocat</div>';
            echo '<div class="mt-1">';
            if(($localSync['state']??'')==='draft'&&($localSync['authority']??'')==='local')echo '<a class="btn btn-warning btn-sm mr-1" href="factura.php?id_factura='.urlencode($id_factura).'">Continuă</a>';
            if($canDeleteOffline)echo '<button type="button" class="btn btn-outline-danger btn-sm stergere" data-toggle="modal" data-target="#modal_stergere" data-id-factura="'.casa_h($id_factura).'">Șterge</button>';
            echo '</div>';
        }
        $originLabel=($localSync['origin']??'')==='local'?'Origine OFFLINE, această instalare':(($remoteStatus['source_origin']??'')==='offline'?'Origine OFFLINE, altă instalare, preluată online':'Origine ONLINE, preluată local');
        echo '<div class="mt-2"><span class="badge '.(($localSync['origin']??'')==='local'?'badge-primary':'badge-secondary').'">'.casa_h($originLabel).'</span></div>';
        if(($localSync['authority']??'')==='online')echo '<div class="small">Consultare offline. Corecțiile se fac online.</div>';
        if(($localSync['state']??'')==='remote_deleted')echo '<div class="text-success font-weight-bold">Factura a fost ștearsă online. Poate fi ștearsă și din copia offline.</div>';
        if(($localSync['state']??'')==='delete_requested')echo '<div class="text-warning font-weight-bold">Ștergere în așteptarea confirmării online</div>';
        if($localSync&&$localSync['origin']==='local'){
            if(!$offlineWithoutNumber)echo '<div class="small mt-1">'.casa_h($localSync['online_id']?'Număr înregistrat online':'Număr neconfirmat online').'</div>';
            if($localSync['error']!=='')echo '<div class="text-danger small">'.casa_h($localSync['error']).'</div>';
        }
        if($remoteRecord)echo '<div class="small text-muted">Status online verificat: '.casa_h($remoteRecord['checked_at']).'</div>';
    echo "</td>";


    // 2. Coloana "Serie"
    echo "<td data-label='Serie'>" . htmlspecialchars($serie_factura) . "</td>";

    // 3. Coloana "Data"
    echo "<td data-label='Data'>" . htmlspecialchars(date("d.m.Y", strtotime($row['data_factura']))) . "</td>";

    // 4. Coloana "Valoare"
   
// 4. Coloana "Valoare" — ÎNLOCUIEȘTE blocul existent cu acesta
// (afișează sub valoare metoda de plată din f.cod_metoda_plata)
$cod_mp = isset($row['cod_metoda_plata']) ? (string)$row['cod_metoda_plata'] : '';
$et_mp  = $metode_plata[$cod_mp] ?? ($cod_mp !== '' ? $cod_mp : 'Nespecificat');

// pentru sortare corectă numerică în DataTables
$valoare_order = is_numeric($total_val_vz_cu_tva) ? number_format($total_val_vz_cu_tva, 4, '.', '') : '0';

echo "<td data-label='Valoare' data-order='" . htmlspecialchars($valoare_order, ENT_QUOTES) . "'>";
    // valoarea (deja formatată mai sus în $valoare_formatted)
    echo "<div>{$valoare_formatted}</div>";
    // metoda de plată sub valoare (cod + etichetă)
    echo "<div class='text-muted small mt-1'>";
        echo "<span class='badge badge-light text-dark border' style='font-weight:normal;'>";
            echo htmlspecialchars($cod_mp !== '' ? $cod_mp : '—') . " · " . htmlspecialchars($et_mp);
        echo "</span>";
    echo "</div>";
echo "</td>";


    // 5. Coloana "Client"
// Adăugăm clasa 'client-scurt' și atributul 'title' pentru tooltip
echo "<td class='client-scurt' data-label='Client' title='" . htmlspecialchars($row['nume']) . "'>"
     . htmlspecialchars($row['nume']) . 
     "</td>";

    // 6. Coloana "Tip Factură"
    echo "<td data-label='Tip factura'>" . htmlspecialchars($invoiceTypeCode) . "</td>";

    // 7. Coloana "Data Validare"
    echo "<td data-label='Data validare'>" . $data_validare_output . "</td>";

    // 8. Coloana "Data Încărcare"
    echo "<td data-label='Data incarcare'>" . $data_incarcare_output . "</td>";

    // 9. Coloana "Trimis ANAF"
    echo "<td data-label='Trimis ANAF'>" . htmlspecialchars($trimis_anaf) . "</td>";

    // 10. Coloana "Data Corectare"
    echo "<td data-label='Data corectare'>" . $data_corectare_output . "</td>";

    // 11. Coloana "Data Încasare"
    echo "<td data-label='Data incasare'>" . $data_incasare_output . "</td>";


// Sfârșitul rândului
echo "</tr>";}
            if(isset($_GET['casa_fragment'])){
                // Capture the rows directly. Parsing the entire HTML response with
                // a regular expression can exceed PCRE limits on larger lists.
                $rowsHtml=ob_get_clean();
                ob_end_clean();
                $version=hash('sha256',$rowsHtml);
                $reply=['success'=>true,'version'=>$version];
                if(($_GET['version']??'')!==$version)$reply['rows']=$rowsHtml;
                casa_json($reply);
            }
            ?>
          </tbody>
          <tfoot>
            <tr>
              <th>Numar Factura</th>
              <th>Serie</th>
              <th>Data</th>
              <th>Valoare</th>
              <th>Client</th>
              <th>Tip Factură</th>
              <th>Data Validare</th>
              <th>Data Încărcare</th>
              <th>Trimis ANAF</th>
              <th>Data Corectare</th>
              <th>Data Încasare</th>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</div>
<!-- End of Main Content -->

<!-- Modal Incasare Factura -->
<div class="modal fade" id="modal_incasare" role="dialog" aria-labelledby="myModalLabel">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">      
      <div class="modal-header">
        <h6 class="modal-title" id="myModalLabel">Incasare Factura</h6>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"> 
          <span aria-hidden="true">&times;</span>
        </button>
      </div>      
      <div class="modal-body">
        <form action="proc/incasare_factura.php" method="POST">
          <input type="hidden" id="id_factura_incasata" name="id_factura" />
          <table width="100%" border="0" cellpadding="0" cellspacing="2"> 
            <tr> 
              <th><h6>Nr. factura</h6></th> 
              <td><input readonly class="form-control" type="number" id="nr_factura_incasata" name="nr_factura_incasata" /></td> 
            </tr> 
            <tr> 
              <th><h6>Suma</h6></th> 
              <td><input readonly class="form-control" type="number" step="0.01" id="suma_incasata" name="suma_incasata" /></td> 
            </tr> 
            <tr> 
  <th><h6>Tip document</h6></th> 
  <td>
    <select class="form-control" id="tip_doc_incasare" name="tip_doc_incasare">
      <option value="ordin_de_plata">Ordin de plata</option>
      <option value="chitanta">Chitanta</option>
      <option value="bon">Bon</option>
      <option value="tichet">Tichet</option>
    </select>
  </td> 
</tr>
<tr>
  <th><h6>Cont bancar</h6></th>
  <td>
    <select class="form-control" id="cont_bancar" name="cont_bancar">
      <?php
      // Asigură-te că ai inclus conexiunea la baza de date (ex. require_once __DIR__.'/database_connection.php';)
      $stmt = $pdo->query("SELECT cont_banca FROM casa_online_company");
      while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
          echo '<option value="' . $row['cont_banca'] . '">' . $row['cont_banca'] . '</option>';
      }
      ?>
    </select>
  </td>
</tr>
            <tr> 
              <th><h6>Nr. doc. incasare</h6></th> 
              <td><input class="form-control" type="text" id="nr_doc_incasare" name="nr_doc_incasare" ></td> 
            </tr>
            <tr> 
              <th><h6>Data încasării</h6></th> 
              <td><input class="form-control data" type="date" id="data_incasare" name="data_incasare" value="<?php echo date('Y-m-d'); ?>"/></td> 
            </tr>
            <tr>
              <td></td>
              <td>
                <button type="submit" class="btn btn-primary" style="float:right; margin-top:1em;" name="exec_incasare" disabled>Încasare</button>
              </td>
            </tr>
          </table> 
        </form>
        <br>
      </div>
    </div>              
  </div>              
</div>
<!-- End Modal Incasare Factura -->

<!-- Modal Duplicare Factura -->
<div class="modal fade" id="modal_duplicare" role="dialog" aria-labelledby="myModalLabel">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">      
      <div class="modal-header">
        <h6 class="modal-title" id="myModalLabel">Duplicare factura</h6>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"> 
          <span aria-hidden="true">&times;</span>
        </button>
      </div>    
      <div class="modal-body">
        <form action="duplicare_factura.php" method="POST">
          <table width="100%" border="0" cellpadding="0" cellspacing="2">
            <tr>
              <td><input hidden class="form-control" type="number" id="id_factura" name="id_factura" /></td>
            </tr>
            <tr>
              <th><h6>Nr factura</h6></th>
              <td><input class="form-control" type="number" id="nr_fact_duplicata" name="nr_fact_duplicata" /></td>
            </tr>
            <tr>
              <th><h6>Data emitere</h6></th>
              <td><input class="form-control data" type="date" id="data_emitere" name="data_emitere" value="<?php echo date('Y-m-d'); ?>"/></td>
            </tr>
            <tr>
              <th><h6>Data scadenta</h6></th>
              <td><input class="form-control data" type="date" id="data_scadenta" name="data_scadenta" value="<?php echo date('Y-m-d'); ?>"/></td>
            </tr>
            <tr>
              <td></td>
              <td>
                <button type="submit" class="btn btn-primary" style="float:right; margin-top:1em;" name="exec_duplicare">Duplicare</button>
              </td>
            </tr>
          </table>
        </form>
        <br>
      </div>
    </div>              
  </div>              
</div>
<!-- End Modal Duplicare Factura -->

<!-- Modal Stergere Factura -->
<div class="modal fade" id="modal_stergere" role="dialog" aria-labelledby="myModalLabel">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">      
      <div class="modal-header">
        <h2 style="text-align:center;">Sunteți sigur că doriți să ștergeți factura?</h2>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>      
      <div class="modal-body">
        <form action="stergere_factura.php" method="POST">
          <p>Se șterge factura creată în această instalare offline. Dacă a fost sincronizată, se solicită și ștergerea online. Până la confirmare, factura rămâne blocată. Se păstrează o copie în arhivă.</p>
          <table width="100%" border="0" cellpadding="0" cellspacing="2"> 
            <tr>
              <td><input class="form-control" hidden type="number" id="id_fact_stergere" name="id_fact_stergere" /></td>
            </tr> 
            <tr>
              <td></td>
              <td>
                <button type="submit" class="btn btn-primary Enter_Listener_Keyboard" style="float:left; margin-top:1em; margin-left:20em;" name="exec_stergere">Da</button>
                <button type="button" class="btn btn-secondary" style="float:right; margin-top:1em; margin-right:20em;" data-dismiss="modal">Nu</button>
              </td>
            </tr>
          </table> 
        </form>
        <br>
      </div>
    </div>              
  </div>              
</div>
<!-- End Modal Stergere Factura -->

<!-- Modal Stornare Factura -->
<div class="modal fade" id="modal_stornare" tabindex="-1" role="dialog" aria-labelledby="modalStornareLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title" id="modalStornareLabel">Stornare Factura</h6>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"> 
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form method="POST">
          <table width="100%" border="0" cellpadding="0" cellspacing="2">
            <tr>
              <td><input type="hidden" class="form-control" id="id_factura_stornare" name="id_factura" /></td>
            </tr>
            <tr>
              <th><h6>Motiv Stornare</h6></th>
              <td><textarea class="form-control" id="motiv_stornare" name="motiv_stornare" required></textarea></td>
            </tr>
            <tr>
              <td></td>
              <td>
                <button type="submit" class="btn btn-primary" style="float:right; margin-top:1em;" name="exec_stornare">Stornare</button>
              </td>
            </tr>
          </table>
        </form>
        <br>
      </div>
    </div>              
  </div>              
</div>
<!-- End Modal Stornare Factura -->

<!-- Modal pentru Filtrare Facturi -->
<div class="modal fade" id="modal_filtrare_clienti" tabindex="-1" role="dialog" aria-labelledby="modalFiltrareClientiLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalFiltrareClientiLabel">Filtrare Facturi</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Închide">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form id="form_filtrare_clienti">
          <div class="form-group">
            <label for="client_filter_select">Selectează clientul:</label>
            <select class="form-control" id="client_filter_select">
              <option value="">Toți clienții</option>
              <?php
                $sqlClients = "SELECT DISTINCT nume FROM facturi ORDER BY nume ASC";
                $stmtClients = $pdo->prepare($sqlClients);
                $stmtClients->execute();
                while ($clientRow = $stmtClients->fetch(PDO::FETCH_ASSOC)) {
                    $clientName = htmlspecialchars($clientRow['nume']);
                    echo "<option value=\"$clientName\">$clientName</option>";
                }
              ?>
            </select>
          </div>
          <div class="form-group">
            <label for="date_from">Din Data:</label>
            <input type="date" class="form-control" id="date_from">
          </div>
          <div class="form-group">
            <label for="date_to">Până în Data:</label>
            <input type="date" class="form-control" id="date_to">
          </div>
          <div class="form-group">
            <label for="invoice_type">Tip Factură:</label>
            <select class="form-control" id="invoice_type">
              <option value="">Toate tipurile</option>
              <option value="380">380 - Factură fiscală</option>
              <option value="751">751 - Bon Fiscal</option>
              <option value="389">389 - Autofactură</option>
              <option value="384">384 - Factură corectată</option>
              <option value="381">381 - Factură storno</option>
            </select>
          </div>
          <button type="submit" class="btn btn-primary">Aplică Filtru</button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modal_sales_by_payment" tabindex="-1" role="dialog" aria-labelledby="modalSalesByPaymentLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
         <h5 class="modal-title" id="modalSalesByPaymentLabel">Raport Vânzări pe Metode de Plată</h5>
         <button type="button" class="close" data-dismiss="modal" aria-label="Închide">
           <span aria-hidden="true">&times;</span>
         </button>
      </div>
      <div class="modal-body">
         <div class="table-responsive">
           <table class="table table-bordered" id="reportTable" width="100%" cellspacing="0">
             <thead>
               <tr>
                 <th>Metoda de Plată</th>
                 <th>Total Vânzări (Lei)</th>
               </tr>
             </thead>
             <tbody>
               <?php foreach ($report_results as $row): 
                 $cod = $row['cod_metoda_plata'];
                 $total_vanzari = $row['total_vanzari'];
                 $total_formatted = number_format($total_vanzari, 2, ',', '.');
                 $metoda_descriere = isset($metode_plata[$cod]) ? $metode_plata[$cod] : ($cod ? $cod : "Nespecificat");
               ?>
               <tr>
                 <td><?php echo htmlspecialchars($cod . " - " . $metoda_descriere); ?></td>
                 <td><?php echo htmlspecialchars($total_formatted); ?> Lei</td>
               </tr>
               <?php endforeach; ?>
             </tbody>
           </table>
         </div>
      </div>
      <div class="modal-footer">
         <button type="button" class="btn btn-secondary" data-dismiss="modal">Închide</button>
      </div>
    </div>
  </div>
</div>


<!-- Modal Raport Facturi (PDF) -->
<div class="modal fade" id="modalRaportFacturi" tabindex="-1" role="dialog" aria-labelledby="modalRaportFacturiLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <!-- Form-ul pentru generarea raportului PDF -->
      <form action="raport_facturi.php" method="POST" target="_blank">
        <div class="modal-header">
          <h5 class="modal-title" id="modalRaportFacturiLabel">Raport Facturi (PDF)</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>      
        <div class="modal-body">
          <div class="form-group">
            <label for="start_date">Data de început</label>
            <input type="date" class="form-control" id="start_date" name="start_date" required>
          </div>
          <div class="form-group">
            <label for="end_date">Data de sfârșit</label>
            <input type="date" class="form-control" id="end_date" name="end_date">
          </div>
          <small class="text-muted">Se exportă în format PDF.</small>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Închide</button>
          <button type="submit" class="btn btn-secondary">Generează PDF</button>
        </div>
      </form>
    </div>
  </div>
</div>



<!-- Modal Raport Facturi (Excel) -->
<div class="modal fade" id="modalRaportFacturiExcel" tabindex="-1" role="dialog" aria-labelledby="modalRaportFacturiExcelLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <!-- Form-ul pentru generarea raportului Excel -->
      <form action="raport_facturi_excel.php" method="POST" target="_blank">
        <div class="modal-header">
          <h5 class="modal-title" id="modalRaportFacturiExcelLabel">Raport Facturi (Excel)</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>      
        <div class="modal-body">
          <div class="form-group">
            <label for="start_date_excel">Data de început</label>
            <input type="date" class="form-control" id="start_date_excel" name="start_date" required>
          </div>
          <div class="form-group">
            <label for="end_date_excel">Data de sfârșit</label>
            <input type="date" class="form-control" id="end_date_excel" name="end_date">
          </div>
          <small class="text-muted">Se exportă în format Excel (XLSX/CSV, în funcție de implementarea din raport_facturi_excel.php).</small>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Închide</button>
          <button type="submit" class="btn btn-success">Generează Excel</button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- Footer -->
<?php include('footer.php'); ?>
<!-- End of Footer -->
</div>
<!-- End of Content Wrapper -->
</div>
<!-- End of Page Wrapper -->

<!-- Scroll to Top Button-->
<a class="scroll-to-top rounded" href="#page-top">
  <i class="fas fa-angle-up"></i>
</a>

<!-- Scripturile -->

<script src="vendor/jquery-easing/jquery.easing.min.js"></script>
<script src="js/sb-admin-2.min.js"></script>




<script>
  // Funcția custom de filtrare pentru DataTables
  $.fn.dataTable.ext.search.push(
    function(settings, data, dataIndex) {
      if (settings.nTable.id !== 'facturiTable') return true;
      var originFilter = $('#casa-origin-filter').val();
      var rowNode = settings.aoData[dataIndex].nTr;
      if (originFilter && (!rowNode || rowNode.getAttribute('data-invoice-origin') !== originFilter)) return false;
      // Filtrele din modal se aplică doar după apăsarea butonului „Aplică Filtru”.
      // Astfel, lista inițială rămâne completă.
      if (!window.facturiFiltruActiv) {
        return true;
      }

      var clientFilter = $('#client_filter_select').val();
      var dateFrom = $('#date_from').val();
      var dateTo = $('#date_to').val();
      var invoiceTypeFilter = $('#invoice_type').val();
      var invoiceDateStr = data[2] || "";
      var clientData = data[4] || "";
      var invoiceTypeData = data[5] || "";
      var invoiceDateFormatted = "";
      if(invoiceDateStr) {
        var parts = invoiceDateStr.split('.');
        if(parts.length === 3) {
          invoiceDateFormatted = parts[2] + '-' + parts[1] + '-' + parts[0];
        }
      }
      if(clientFilter && clientData.toLowerCase().indexOf(clientFilter.toLowerCase()) === -1) {
        return false;
      }
      if(invoiceTypeFilter && invoiceTypeData.indexOf(invoiceTypeFilter) === -1) {
        return false;
      }
      if(dateFrom && invoiceDateFormatted < dateFrom) {
        return false;
      }
      if(dateTo && invoiceDateFormatted > dateTo) {
        return false;
      }
      return true;
    }
  );

  $(document).ready(function() {
    var filterKey = 'casa-invoice-filters:' + location.pathname + ':<?= (int)$_SESSION['admin_id'] ?>';
    var savedFilters = {};
    try {
      if (<?= $resetInvoiceFilters?'true':'false' ?>) sessionStorage.removeItem(filterKey);
      savedFilters = JSON.parse(sessionStorage.getItem(filterKey) || '{}') || {};
    } catch(e) { savedFilters = {}; }
    var filterFields = ['client_filter_select','date_from','date_to','invoice_type','serie_filter_select','casa-origin-filter','casa-invoice-search'];
    filterFields.forEach(function(id){$('#'+id).val(typeof savedFilters[id] === 'string' ? savedFilters[id] : '');});
    window.facturiFiltruActiv = savedFilters.active === true;
    function rememberFilters(){
      var value = {active: window.facturiFiltruActiv};
      filterFields.forEach(function(id){value[id]=$('#'+id).val() || '';});
      try { sessionStorage.setItem(filterKey, JSON.stringify(value)); } catch(e) {}
    }

    var table = $('#facturiTable').DataTable({
      responsive: false,
      searching: true,
      pageLength: 50,
      lengthMenu: [[25, 50, 100, -1], [25, 50, 100, "Toate"]],
      order: [[0, 'desc']],
      stateSave: false,
      dom: 'lrtip',
      language: {search: 'Caută:', searchPlaceholder: 'Număr, serie, client...', lengthMenu: 'Afișează _MENU_ facturi', info: '_START_ - _END_ din _TOTAL_ facturi', infoEmpty: 'Nicio factură', infoFiltered: '(din _MAX_ facturi)', zeroRecords: 'Nicio factură găsită', emptyTable: 'Nu există facturi', paginate: {first: 'Prima', previous: 'Înapoi', next: 'Înainte', last: 'Ultima'}}
    });
    window.facturiDataTable = table;
    $('#casa-origin-filter').on('change', function(){table.draw();});
    $('#casa-invoice-search').on('input', function(){table.search(this.value).draw();});
    var restoredSeries = $('#serie_filter_select').val() || '';
    table.search($('#casa-invoice-search').val() || '');
    table.column(1).search(restoredSeries ? '^' + $.fn.dataTable.util.escapeRegex(restoredSeries) + '$' : '', true, false).draw();
    $('#facturiTable').on('draw.dt', function() {
      rememberFilters();
      $('[data-toggle="dropdown"]').dropdown();
    });
    $('#facturiTable tbody').on('mousemove', 'tr', function(e) {
      if (window.matchMedia('(max-width: 767.98px)').matches) {
        return;
      }
      var preview = $(this).find('.factura-produse-preview');
      if (!preview.length) {
        return;
      }
      if ($(e.target).closest('.btn-group, .dropdown-menu').length) {
        preview.removeClass('is-visible');
        return;
      }
      preview.addClass('is-visible');
      var offset = 18;
      var left = e.clientX + offset;
      var top = e.clientY + offset;
      var width = preview.outerWidth();
      var height = preview.outerHeight();
      var maxLeft = window.innerWidth - width - 12;
      var maxTop = window.innerHeight - height - 12;
      if (left > maxLeft) {
        left = Math.max(12, e.clientX - width - offset);
      }
      if (top > maxTop) {
        top = Math.max(12, maxTop);
      }
      preview.css({
        left: left + 'px',
        top: top + 'px'
      });
    });
    $('#facturiTable tbody').on('mouseleave', 'tr', function() {
      $(this).find('.factura-produse-preview').removeClass('is-visible');
    });
    $(document).on('click', '#facturiTable .incasare', function(){
        $("#nr_factura_incasata").val($(this).data("nr_fact"));
        $("#suma_incasata").val($(this).data("suma_incasata"));
        $("#id_factura_incasata").val($(this).data("id_factura"));
        var sumaIncasata = parseFloat($(this).data("suma_incasata"));
        if (sumaIncasata <= 0) {
          $("#modal_incasare button[type='submit']").prop('disabled', true);
        } else {
          $("#modal_incasare button[type='submit']").prop('disabled', false);
        }
    });
    $(document).on('click', '#facturiTable .duplicare', function(){
        var id_factura = $(this).data('id_factura');
        var nr_fact_duplicata = $(this).data('nr_fact_duplicata');
        $('#id_factura').val(id_factura);
        $('#nr_fact_duplicata').val(nr_fact_duplicata);
    });
    $(document).on('click', '#facturiTable .stergere', function() {
        var idFactura = parseInt($(this).attr('data-id-factura'), 10);
        $('#modal_stergere input[name="id_fact_stergere"]').val(idFactura > 0 ? idFactura : '');
    });
    $('#modal_stergere').on('show.bs.modal', function(event) {
        var trigger = event.relatedTarget ? $(event.relatedTarget) : $();
        var idFactura = parseInt(trigger.attr('data-id-factura'), 10);
        if (idFactura > 0) {
          $(this).find('input[name="id_fact_stergere"]').val(idFactura);
        }
    });
    $('#modal_stergere form').on('submit', function(event) {
        var idFactura = parseInt($(this).find('input[name="id_fact_stergere"]').val(), 10);
        if (!(idFactura > 0)) {
          event.preventDefault();
          alert('Factura selectată nu are un ID valid. Închideți fereastra și selectați din nou factura.');
        }
    });
    $(document).on('click', '#facturiTable .stornare', function(){
        var id_factura = $(this).data('id_factura');
        var nr_factura = $(this).data('nr_factura');
        $('#modal_stornare form').attr('action', 'stornare_factura.php?id_factura=' + id_factura);
        $('#id_factura_stornare').val(id_factura);
        $('#motiv_stornare').val("");
    });
    $('#updateAnafBtn').on('click', function() {
      if (!confirm('Ești sigur că dorești să actualizezi mesajele ANAF?')) {
        return;
      }
      $(this).prop('disabled', true).text('Actualizare în curs...');
      $.ajax({
        url: 'store_mesaje_anaf.php',
        method: 'POST',
        dataType: 'json',
        success: function(response) {
          if (response.success) {
            alert('Mesajele ANAF au fost actualizate cu succes.');
          } else {
            alert('A apărut o eroare: ' + response.message);
          }
          location.reload();
        },
        error: function(jqXHR, textStatus, errorThrown) {
          alert('A apărut o eroare la comunicarea cu serverul: ' + textStatus);
          $('#updateAnafBtn').prop('disabled', false).text('Verificare status trimitere ANAF');
        }
      });
    });
    $('#form_filtrare_clienti').on('submit', function(e) {
      e.preventDefault();
      window.facturiFiltruActiv = true;
      table.draw();
      $('#modal_filtrare_clienti').modal('hide');
    });

    $('#clearFacturiFiltersBtn').on('click', function() {
      window.facturiFiltruActiv = false;
      $('#client_filter_select').val('');
      $('#date_from').val('');
      $('#date_to').val('');
      $('#invoice_type').val('');
      $('#serie_filter_select').val('');
      table.search('');
      $('#casa-invoice-search').val('');
      $('#casa-origin-filter').val('');
      table.columns().search('');
      table.draw();
    });
  });
</script>
</body>
</html>
