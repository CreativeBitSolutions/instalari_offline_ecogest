<?php
ini_set('display_errors', 0); // Nu afișează erorile utilizatorului
ini_set('log_errors', 1); // Activează logarea erorilor
ini_set('error_log', 'error_log.log');

include('session.php');

// Definirea variabilelor cu numele tabelelor (adaptă-le dacă este necesar)
$tabel_final_nomenclator = 'nomenclator';
$tabel_final_det_note    = 'det_note';
$tabel_final_retete      = 'retete';
$tabel_final_miscari     = 'miscari';
$tabel_final_mese        = 'mese'; // dacă este necesar

// ------------------------------
// Proces AJAX: Consum Retur
// ------------------------------

// ------------------------------
// Afișare: Listă Retururi neconsumate
// ------------------------------
$sqlRetururi = "SELECT retururi.*, note.operator, admins_12.admin_firstname, admins_12.admin_lastname 
                FROM retururi 
                INNER JOIN note ON note.nrbon = retururi.nr_bon 
                INNER JOIN admins_12 ON admins_12.admin_id = note.operator 
                WHERE retururi.consumat = 0 
                ORDER BY retururi.data DESC, retururi.ora DESC";
$resRetururi = $pdo->query($sqlRetururi);
$retururi = [];
if ($resRetururi) {
    while ($row = $resRetururi->fetch(PDO::FETCH_ASSOC)) {
        $retururi[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Retururi - Consum Retur</title>
  <!-- Bootstrap CDN -->
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <style>
    .mt-4 { margin-top: 1.5rem!important; }
  </style>
</head>
<body>
  <div class="container">
    <h2 class="mt-4">Lista Retururi (Neconsumate)</h2>
    
    <?php if (count($retururi) > 0): ?>
    <div class="table-responsive">
      <table class="table table-bordered table-striped">
        <thead class="thead-light">
          <tr>
            <th>ID Retur</th>
            <th>Nr Bon</th>
            <th>Cod Produs</th>
            <th>Nume Produs</th>
            <th>Cantitate</th>
            <th>Pret Vânzare</th>
            <th>Data</th>
            <th>Ora</th>
            <th>Admin (Nume)</th>
            <th>Acțiuni</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($retururi as $retur): ?>
          <tr id="row-<?php echo $retur['id_retur']; ?>">
            <td><?php echo $retur['id_retur']; ?></td>
            <td><?php echo $retur['nr_bon']; ?></td>
            <td><?php echo $retur['cod_p']; ?></td>
            <td><?php echo $retur['nume_produs']; ?></td>
            <td><?php echo $retur['cantitate']; ?></td>
            <td><?php echo $retur['pret_vanzare']; ?></td>
            <td><?php echo $retur['data']; ?></td>
            <td><?php echo $retur['ora']; ?></td>
            <td><?php echo $retur['admin_firstname'] . ' ' . $retur['admin_lastname']; ?></td>
            <td>
              <button class="btn btn-success btn-sm consum-btn" data-id="<?php echo $retur['id_retur']; ?>">Consum Retur</button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
      <p>Nu există retururi disponibile pentru consum.</p>
    <?php endif; ?>
    
    <h2 class="mt-4"><a href="logout.php">Deconectare</a></h2>
  </div>
  
  <!-- Include jQuery și Bootstrap JS -->
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
  <script>
    $(document).ready(function(){
      $('.consum-btn').click(function(){
  var id_retur = $(this).data('id');
  var row = $('#row-' + id_retur);
  $.ajax({
  url: 'vanzare_retur_consum.php',
  type: 'POST',
  dataType: 'json',  // Adaugă această linie
  data: { action: 'consum_retur', id_retur: id_retur },
  success: function(response){
  console.log("Răspunsul complet de la server:", response);
  if(response.status === 'success'){
    // Refresh la pagina curentă
    window.location.reload();
  } else {
    alert('Eroare: ' + response.message);
  }
},

  error: function(){
    alert('Eroare la comunicare.');
  }
});

});

    });
  </script>
</body>
</html>
