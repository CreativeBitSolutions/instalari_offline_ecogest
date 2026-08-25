<?php
// listare_note_detalii.php

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.log');

include('session.php'); // definește $pdo și $_SESSION

// --- Endpoint AJAX pentru detalii ---
if (isset($_POST['action']) && $_POST['action'] === 'get_detalii') {
    $nrbon = intval($_POST['nrbon']);
    $detSql = "
      SELECT cod_p, nume_produs, cantitate, cota_tva, tva_col,
             pret_vanzare, valoare_vanzare_cu_tva, t_list,
             data, ora, observatie_produs
      FROM det_note
      WHERE nr_bon = :nrbon
      ORDER BY data, ora
    ";
    $detStmt = $pdo->prepare($detSql);
    $detStmt->execute(['nrbon' => $nrbon]);
    $rows = $detStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        echo "<p class='text-muted'>Nu există detalii pentru bonul $nrbon.</p>";
    } else {
        echo "<table class='table table-sm table-bordered w-100'>";
        echo "<thead class='thead-light'><tr>"
           ."<th>Cod</th><th>Produs</th><th>Cant.</th><th>TVA%</th>"
           ."<th>TVA linie</th><th>Preț</th><th>Valoare+TVA</th><th>Stare</th>"
           ."<th>Data</th><th>Ora</th><th>Observații</th>"
           ."</tr></thead><tbody>";
        foreach ($rows as $d) {
            $stare = $d['t_list'] ? 'trimis la bar/bucătărie' : 'netrimis';
            echo "<tr>"
               ."<td>{$d['cod_p']}</td>"
               ."<td>{$d['nume_produs']}</td>"
               ."<td>{$d['cantitate']}</td>"
               ."<td>{$d['cota_tva']}</td>"
               ."<td>{$d['tva_col']}</td>"
               ."<td>{$d['pret_vanzare']}</td>"
               ."<td>{$d['valoare_vanzare_cu_tva']}</td>"
               ."<td>$stare</td>"
               ."<td>{$d['data']}</td>"
               ."<td>{$d['ora']}</td>"
               ."<td>{$d['observatie_produs']}</td>"
               ."</tr>";
        }
        echo "</tbody></table>";
    }
    exit;
}

// --- Preluăm notele eligibile pentru relistare (ultimele 24h, status F, cod_inchidere=0) ---
$last24Condition = (function_exists('restaurantIsOfflineSqlite') && restaurantIsOfflineSqlite())
  ? "datetime(data_bon || ' ' || ora_bon) >= datetime('now', 'localtime', '-24 hours')"
  : "CONCAT(data_bon, ' ', ora_bon) >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";

$relistSql = "
  SELECT nrbon, data_bon, ora_bon, numerar, card, tichete,
         (valoare_vanzare_cu_tva - discount) AS valoare
  FROM note
  WHERE cod_inchidere = 0
    AND status = 'F'
    AND {$last24Condition}
  ORDER BY nrbon DESC
";
$relistStmt = $pdo->prepare($relistSql);
$relistStmt->execute();
$relistNotes = $relistStmt->fetchAll(PDO::FETCH_ASSOC);

// --- Interogare principală pentru listarea tuturor bonurilor ---
$sql = "
    SELECT
      n.nrbon,
      n.data_bon,
      n.ora_bon,
      n.valoare_vanzare_cu_tva,
      n.tva_colectata,
      n.numerar,
      n.card,
      n.tichete,
      n.status,
      n.cif_client,
      CONCAT(a.admin_firstname,' ',a.admin_lastname) AS operator_name,
      m.nume_masa
    FROM note n
    INNER JOIN admins_12 a ON n.operator = a.admin_id
    INNER JOIN mese m     ON n.cod_masa = m.cod_masa
    ORDER BY n.data_bon DESC, n.ora_bon DESC
";
$notes = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Listare Note cu Popup Detalii & Relistare</title>
  <!-- Bootstrap 4 + DataTables CSS -->
  <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="vendor/datatables/dataTables.bootstrap4.css">
  <style>
    /* Touch-friendly form controls */
    .modal .form-control { font-size: 1.1rem; padding: 0.75rem; }
    .modal .btn { font-size: 1.1rem; padding: 0.75rem 1.5rem; }
  </style>
</head>
<body>
  <div class="container mt-4">
    <div class="d-flex mb-3">
      <button class="btn btn-secondary mr-2" onclick="window.history.back();">
        ← Înapoi
      </button>
      <button
        class="btn btn-primary"
        data-toggle="modal"
        data-target="#relistareboncasamarcat"
        <?= empty($relistNotes) ? 'disabled' : '' ?>
      >
        Retrimite bon la casa de marcat (ATENTIE! SE VA INREGISTRA VANZAREA LA CASA DE MARCAT)
      </button>
    </div>

    <h2>Toate bonurile</h2>
    <div class="row mb-3">
      <div class="col-md-3">
        <label>Data minimă:</label>
        <input type="date" id="minDate" class="form-control">
      </div>
      <div class="col-md-3">
        <label>Data maximă:</label>
        <input type="date" id="maxDate" class="form-control">
      </div>
    </div>

    <table id="notesTable" class="table table-striped table-bordered">
      <thead>
        <tr>
          <th>Detalii</th>
          <th>Nr Bon</th>
          <th>Data Bon</th>
          <th>Ora Bon</th>
          <th>Valoare Totală</th>
          <th>TVA Colectată</th>
          <th>Operator</th>
          <th>Numerar</th>
          <th>Card</th>
          <th>Tichete</th>
          <th>Status</th>
          <th>CIF Client</th>
          <th>Masa</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($notes as $n): ?>
        <tr>
          <td class="text-center">
            <button class="btn btn-info btn-sm details-btn"
                    data-nrbon="<?= $n['nrbon'] ?>">
              Detalii
            </button>
          </td>
          <td><?= htmlspecialchars($n['nrbon']) ?></td>
          <td><?= htmlspecialchars($n['data_bon']) ?></td>
          <td><?= htmlspecialchars($n['ora_bon']) ?></td>
          <td><?= htmlspecialchars($n['valoare_vanzare_cu_tva']) ?></td>
          <td><?= htmlspecialchars($n['tva_colectata']) ?></td>
          <td><?= htmlspecialchars($n['operator_name']) ?></td>
          <td><?= htmlspecialchars($n['numerar']) ?></td>
          <td><?= htmlspecialchars($n['card']) ?></td>
          <td><?= htmlspecialchars($n['tichete']) ?></td>
          <td>
            <?= $n['status'] === 'S'
               ? '<span class="badge badge-warning">nefinalizată</span>'
               : '<span class="badge badge-success">finalizată</span>' ?>
          </td>
          <td><?= htmlspecialchars($n['cif_client']) ?></td>
          <td><?= htmlspecialchars($n['nume_masa']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Modal Detalii -->
  <div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="detailsModalLabel">Detalii bon</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <div class="text-center"><em>Se încarcă...</em></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Retrimite bon la casa de marcat (ATENTIE! SE VA INREGISTRA VANZAREA LA CASA DE MARCAT) -->
  <div class="modal fade" id="relistareboncasamarcat" tabindex="-1" role="dialog" aria-labelledby="relistareboncasaLabel" aria-hidden="true">
    <div class="modal-dialog" role="document" style="max-width:500px;">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="relistareboncasaLabel">Retrimite bon la casa de marcat (ATENTIE! SE VA INREGISTRA VANZAREA LA CASA DE MARCAT)</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <form method="POST" action="casa_marcat_vanzare.php">
          <div class="modal-body">
            <div class="form-group">
              <label for="notaSelect">Selectează nota</label>
              <select
                id="notaSelect"
                name="nota_de_relistat"
                class="form-control form-control-lg"
                required
              >
                <option value="">-- Alege o notă --</option>
                <?php foreach ($relistNotes as $rn): ?>
                  <option value="<?= $rn['nrbon'] ?>">
                    Bon <?= $rn['nrbon'] ?>
                    — <?= $rn['data_bon'].' '.$rn['ora_bon'] ?>
                    | Numerar: <?= $rn['numerar'] ?>
                    | Card: <?= $rn['card'] ?>
                    | Tichete: <?= $rn['tichete'] ?>
                    | Valoare: <?= number_format($rn['valoare'],2,',','.') ?> lei
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary">Anulează</button>
            <button type="submit" class="btn btn-primary">Relistează</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- JS: jQuery, Bootstrap, DataTables -->
  <script src="js/jquery-3.6.0.min.js"></script>
  <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="vendor/datatables/jquery.dataTables.js"></script>
  <script src="vendor/datatables/dataTables.bootstrap4.js"></script>

  <script>
  $(function(){
    // Filtru DataTables după interval de date
    $.fn.dataTable.ext.search.push(function(settings, data){
      var min = $('#minDate').val();
      var max = $('#maxDate').val();
      var date = data[2]; // coloana Data Bon
      if ((!min && !max) ||
          (!min && date <= max) ||
          (min <= date && !max) ||
          (min <= date && date <= max)) {
        return true;
      }
      return false;
    });

    var table = $('#notesTable').DataTable({
      order: [[2,'desc'],[3,'desc']],
      pageLength: 25
    });
    $('#minDate, #maxDate').on('change', function(){ table.draw(); });

    // AJAX Detalii
    $('#notesTable').on('click', '.details-btn', function(){
      var nrbon = $(this).data('nrbon');
      $('#detailsModalLabel').text('Detalii pentru bon #' + nrbon);
      $('#detailsModal .modal-body').html('<div class="text-center"><em>Se încarcă...</em></div>');
      $('#detailsModal').modal('show');
      $.post('', { action: 'get_detalii', nrbon: nrbon }, function(html){
        $('#detailsModal .modal-body').html(html);
      });
    });
  });
  </script>
</body>
</html>
