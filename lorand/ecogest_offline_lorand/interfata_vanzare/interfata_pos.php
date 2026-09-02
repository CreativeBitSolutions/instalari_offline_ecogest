<?php
session_start();
date_default_timezone_set("Europe/Bucharest");
include('database_connection.php'); // defineÈ™te $pdo, $tabel_final_det_note, $tabel_final_nomenclator, $tabel_final_note
require_once __DIR__ . '/offline_api_path.php';

// Definirea cÄƒii cÄƒtre fiÈ™ierul JSON
$client_id      = $_SESSION['client_id'];
$locatie        = $_SESSION['cod_locatie'];
$json_file_path = offline_api_path($client_id, $locatie, 'refresh_pos_client.json');

// CiteÈ™te È™i stocheazÄƒ nr_bon Ã®n sesiune doar o singurÄƒ datÄƒ
if (!isset($_SESSION['nr_bon_curent'])) {
    if (file_exists($json_file_path)) {
        $json_data = file_get_contents($json_file_path);
        $data = json_decode($json_data, true);
        if (isset($data['nr_bon']) && is_numeric($data['nr_bon'])) {
            $_SESSION['nr_bon_curent'] = (int)$data['nr_bon'];
        }
        unlink($json_file_path);
    }
}

// PreluÄƒm nr_bon din sesiune
$nr_bon_curent = $_SESSION['nr_bon_curent'] ?? null;
if (!$nr_bon_curent) {
    echo "<p class='text-center mt-5'><strong>Nu existÄƒ un bon deschis Ã®n acest moment.</strong></p>";
    exit;
}

// SetÄƒri tabele
$tabel_det          = $tabel_final_det_note;
$tabel_nom          = $tabel_final_nomenclator;
$tabel_produse_serv = 'produse_servicii';
$tabel_note         = $tabel_final_note;

// PreluÄƒm antetul (numerar, rest, cif_client)
$antet = $pdo->prepare("
    SELECT numerar, rest, cif_client
    FROM $tabel_note
    WHERE nrbon = :nr_bon
");
$antet->execute(['nr_bon' => $nr_bon_curent]);
$antetInfo = $antet->fetch(PDO::FETCH_ASSOC);
$suma_incasata = isset($antetInfo['numerar']) ? (float)$antetInfo['numerar'] : 0.0;
$rest_rachitat = isset($antetInfo['rest'])    ? (float)$antetInfo['rest']    : 0.0;
$cif_client    = isset($antetInfo['cif_client'])
    ? htmlspecialchars($antetInfo['cif_client'], ENT_QUOTES, 'UTF-8')
    : '';

// ENDPOINT AJAX â€žCHECKâ€ (timestamp-ul JSON-ului)
if (isset($_GET['ajax_check']) && $_GET['ajax_check'] === '1') {
    clearstatcache();
    $lastModified = file_exists($json_file_path) ? filemtime($json_file_path) : 0;
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['last_modified' => $lastModified]);
    exit;
}

// ENDPOINT AJAX â€žLOADâ€ (structura tabelului)
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    afiseaza_tabel(
        $pdo,
        $nr_bon_curent,
        $tabel_det,
        $tabel_nom,
        $tabel_produse_serv,
        $tabel_note,
        $suma_incasata,
        $rest_rachitat,
        $cif_client
    );
    exit;
}

// FuncÈ›ia PHP care genereazÄƒ HTML-ul tabelului
function afiseaza_tabel($pdo, $nr_bon, $tabel_det, $tabel_nom, $tabel_produse_serv, $tabel_note, $suma_incasata, $rest_rachitat, $cif_client) {
    $sql = "
        SELECT
            N.nume                         AS produs,
            PS.imagine                     AS imagine_produs,
            D.cantitate                    AS cantitate,
            D.pret_vanzare                 AS pret_vanzare_unitar,
            (D.pret_vanzare * D.cantitate) AS total_vanzare
        FROM $tabel_det D
        INNER JOIN $tabel_nom N
            ON D.cod_p = N.cod_produs
        LEFT JOIN $tabel_produse_serv PS
            ON N.cod_produs = PS.cod_produs
        WHERE D.nr_bon = :nr_bon
        ORDER BY N.nume ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['nr_bon' => $nr_bon]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) === 0) {
        echo "<p class='text-center py-4'><em>Bonul nu conÈ›ine produse.</em></p>";
        return;
    }
    ?>
    <table class="table table-hover align-middle mb-0">
      <thead class="table-dark">
        <tr>
          <th scope="col">#</th>
          <th scope="col"></th>
          <th scope="col">Produs</th>
          <th scope="col">Cantitate</th>
          <th scope="col">PreÈ› vÃ¢nzare<br>(unitar)</th>
          <th scope="col">Total<br>vÃ¢nzare</th>
        </tr>
      </thead>
      <tbody>
        <?php
          $totalVanzare = 0.0;
          $index = 1;
          foreach ($rows as $row) {
              $produs           = htmlspecialchars($row['produs'], ENT_QUOTES, 'UTF-8');
              $imagine          = trim($row['imagine_produs']);
              $cantitate        = (float)$row['cantitate'];
              $pv_unit          = (float)$row['pret_vanzare_unitar'];
              $total_vanz_linie = (float)$row['total_vanzare'];
              $totalVanzare    += $total_vanz_linie;

              if ($imagine === '' || !file_exists($imagine)) {
                  $imagineSrc = 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=';
              } else {
                  $imagineSrc = $imagine;
              }
        ?>
        <tr>
          <td><?php echo $index++; ?></td>
          <td>
            <img src="<?php echo $imagineSrc; ?>" alt="<?php echo $produs; ?>"
                 class="img-produs">
          </td>
          <td><?php echo $produs; ?></td>
          <td><?php echo number_format($cantitate, 3, ',', ''); ?></td>
          <td><?php echo number_format($pv_unit, 2, ',', ''); ?></td>
          <td><?php echo number_format($total_vanz_linie, 2, ',', ''); ?></td>
        </tr>
        <?php } ?>
        <!-- Total de Ã®ncasat -->
        <tr class="summary-row">
          <td colspan="4" class="text-end">Total de Ã®ncasat:</td>
          <td colspan="2"><?php echo number_format($totalVanzare, 2, ',', ''); ?></td>
        </tr>
      </tbody>
    </table>
    <?php
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>INTERFATA CLIENT</title>
  <!-- Bootstrap 5 CSS -->
  <link href="vendor/offline/bootstrap5/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="vendor/offline/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body {
      background-color: #f4f7fa;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .card {
      border: none;
      border-radius: 8px;
      box-shadow: 0 4px 8px rgba(0,0,0,0.05);
    }
    .table thead {
      background-color: #2c3e50;
      color: #fff;
    }
    .table tbody tr:hover {
      background-color: #f1f3f5;
    }
    .img-produs {
      width: 60px;
      height: 60px;
      object-fit: cover;
      border-radius: 4px;
    }
    .summary-row {
      background-color: #e9ecef;
      font-weight: 600;
    }
    .btn-delete-prod {
      padding: 4px 8px;
      font-size: 0.85rem;
    }
  </style>
</head>
<body>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-10">
      <div class="card mb-4">
        <div class="card-header bg-white border-bottom">
          <h3 class="mb-0">Comanda dumneavoastrÄƒ</h3>
        </div>
        <div class="card-body">
          <div id="prod-container">
            <?php
            // AfiÈ™Äƒm tabelul complet, generat de PHP
            afiseaza_tabel(
                $pdo,
                $nr_bon_curent,
                $tabel_det,
                $tabel_nom,
                $tabel_produse_serv,
                $tabel_note,
                $suma_incasata,
                $rest_rachitat,
                $cif_client
            );
            ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Bootstrap 5 JS Bundle -->
<script src="vendor/offline/bootstrap5/bootstrap.bundle.min.js"></script>
<script>
  const POLL_INTERVAL_MS = 5000; // interval polling, ajustabil

  function incarcaTabel() {
    fetch('?ajax=1')
      .then(response => response.text())
      .then(html => {
        document.getElementById('prod-container').innerHTML = html;
      })
      .catch(err => console.error('Eroare la Ã®ncÄƒrcarea tabelului:', err));
  }

  let ultimaModificare = 0;
  function verificaProduseNoi() {
    fetch('?ajax_check=1')
      .then(response => response.json())
      .then(data => {
        const currentModified = parseInt(data.last_modified, 10);
        if (currentModified > ultimaModificare) {
          ultimaModificare = currentModified;
          incarcaTabel();
        }
      })
      .catch(err => console.error('Eroare la verificarea modificÄƒrilor:', err));
  }

  document.addEventListener('DOMContentLoaded', () => {
    // iniÈ›ializÄƒm timestamp È™i pornim polling-ul
    verificaProduseNoi();
    setInterval(verificaProduseNoi, POLL_INTERVAL_MS);
  });
</script>
</body>
</html>

