<?php
// creare_nir.php

$title = 'Creare NIR Nou';
include 'session.php';

if(isset($_POST['creaza_nir'])) {
    // Process form submission to create new NIR
    $nr_nir = $_POST['nr_nir'];
    $data_nir = $_POST['data_nir'];
    $cod_furnizor = $_POST['cod_furnizor'];
    $serie_doc = $_POST['serie_doc'];
    $nr_doc = $_POST['nr_doc'];
    $data_doc = $_POST['data_doc'];
    $cod_locatie = (int)($_SESSION['cod_locatie'] ?? 2);

    // VALIDARE: luna si anul din data_nir trebuie sa fie identice cu luna si anul din data_doc
    $dt_doc = DateTime::createFromFormat('Y-m-d', $data_doc);
    $dt_nir = DateTime::createFromFormat('Y-m-d', $data_nir);

    if(!$dt_doc || !$dt_nir) {
        echo "<script>
                alert('Datele introduse nu sunt valide.');
                window.location.href = 'creare_nir.php';
              </script>";
        exit();
    }

    $ym_doc = $dt_doc->format('Y-m');
    $ym_nir = $dt_nir->format('Y-m');

    if($ym_doc !== $ym_nir) {
        echo "<script>
                alert('Luna si anul din Data NIR trebuie sa fie identice cu luna si anul din Data Document Intrare.');
                window.location.href = 'creare_nir.php';
              </script>";
        exit();
    }
    
    // Verificăm dacă nr_nir există deja în baza de date
    $sql_check = "SELECT COUNT(*) FROM nir WHERE nr_nir = :nr_nir";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute(['nr_nir' => $nr_nir]);
    $count = $stmt_check->fetchColumn();
    
    if($count > 0) {
        // Dacă nr_nir există deja, se afișează o alertă JS și se redirecționează
        echo "<script>
                alert('Numărul NIR există deja. Nu se va insera.');
                window.location.href = 'creare_nir.php';
              </script>";
        exit();
    }
    
    // Insert into nir table
    $sql = "INSERT INTO nir (nr_nir, data_nir, cod_tert, serie_doc_int, nr_doc_int, data_doc_int, cod_locatie)
            VALUES (:nr_nir, :data_nir, :cod_tert, :serie_doc_int, :nr_doc_int, :data_doc_int, :cod_locatie)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'nr_nir' => $nr_nir,
        'data_nir' => $data_nir,
        'cod_tert' => $cod_furnizor,
        'serie_doc_int' => $serie_doc,
        'nr_doc_int' => $nr_doc,
        'data_doc_int' => $data_doc,
        'cod_locatie' => $cod_locatie,
    ]);
    
    // Store nr_nir in session and redirect to nir.php
    $_SESSION['nr_nir'] = $nr_nir;
    $_SESSION['cod_furnizor'] = $cod_furnizor;
    $_SESSION['serie_doc'] = $serie_doc;
    $_SESSION['nr_doc'] = $nr_doc;
    $_SESSION['data_doc'] = $data_doc;
    
    echo "<script>location.href='nir.php';</script>";
}

// Generate new NIR number in a SQLite-compatible way.
$sql_last_nr_nir = "SELECT COALESCE(MAX(CAST(nr_nir AS INTEGER)), 0) AS last_nr_nir FROM nir";
$stmt_last_nr_nir = $pdo->prepare($sql_last_nr_nir);
$stmt_last_nr_nir->execute();
$row = $stmt_last_nr_nir->fetch();
$last_nr_nir = $row['last_nr_nir'];
$new_nr_nir = $last_nr_nir + 1;

?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title><?php echo $title; ?></title>
    <!-- Include CSS and JS files for select2 -->
    <link href="vendor/offline/select2/select2.min.css" rel="stylesheet" />
    <!-- Include Bootstrap CSS and JS for modal support -->
    <link href="vendor/offline/bootstrap5/bootstrap.min.css" rel="stylesheet">
    <!-- Include jQuery and select2 JS -->
    <script src="js/jquery-3.6.0.min.js"></script>
    <script src="vendor/offline/select2/select2.min.js"></script>
</head>
<body>
<div class="container">
    <h3 align="center">Creare NIR Nou</h3>
    <div class="card">
        <div class="card-header">Informații NIR</div>
        <div class="card-body">
            <form method="post" id="nir_form">
                  <div class="form-group">
                    <label for="serie_doc">Serie Document Intrare (ex seria facturii):</label>
                    <input type="text" class="form-control" id="serie_doc" name="serie_doc" required>
                </div>
                <div class="form-group">
                    <label for="nr_doc">Număr Document Intrare (ex nr.facturii):</label>
                    <input type="text" class="form-control" id="nr_doc" name="nr_doc" required>
                </div>
                <div class="form-group">
                    <label>Data Document Intrare (Ex. data facturii):</label>
                    <input type="date" name="data_doc" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="nr_nir">Număr NIR:</label>
                    <input type="text" class="form-control" id="nr_nir" name="nr_nir" value="<?php echo $new_nr_nir; ?>" required>
                </div>
                <div class="form-group">
                    <label>Data NIR:</label>
                    <input type="date" name="data_nir" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="cod_furnizor">Furnizor:</label>
                    <select class="form-control select2" id="cod_furnizor" name="cod_furnizor" required>
                    </select>
                </div>
                <div class="ml-3 mt-4">
                    <button type="button" onclick="window.location.href='add_furnizor.php'" class="btn btn-secondary" id="add_furnizor_btn">Adaugă Furnizor Nou</button>
                </div>
              
                <button type="submit" class="btn btn-primary" name="creaza_nir">Crează NIR</button>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#cod_furnizor').select2({
        placeholder: 'Alegeți un furnizor',
        ajax: {
            url: 'search_furnizori.php',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return { q: params.term };
            },
            processResults: function(data) {
                return {
                    results: data.map(function(item) {
                        return { id: item.id_furnizor, text: item.nume };
                    })
                };
            },
            cache: true
        },
        minimumInputLength: 1,
    });

    // VALIDARE frontend: luna/an data_nir == luna/an data_doc
    $('#nir_form').on('submit', function(e) {
        var dataDoc = $('input[name="data_doc"]').val();
        var dataNir = $('input[name="data_nir"]').val();

        if (dataDoc && dataNir) {
            var ymDoc = dataDoc.slice(0, 7); // YYYY-MM
            var ymNir = dataNir.slice(0, 7); // YYYY-MM

            if (ymDoc !== ymNir) {
                alert('Luna si anul din Data NIR trebuie sa fie identice cu luna si anul din Data Document Intrare.');
                e.preventDefault();
                return false;
            }
        }
    });
});
</script>

</body>
</html>
