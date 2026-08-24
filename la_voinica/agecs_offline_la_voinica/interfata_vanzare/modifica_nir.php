<?php
// modifica_nir.php

$title = 'Modificare Antet NIR';
include 'session.php';

// Verificăm dacă avem nr_nir în GET
if (!isset($_GET['nr_nir'])) {
    header("Location: note_receptie.php");
    exit;
}

$nr_nir_original = $_GET['nr_nir'];

// Preluăm datele originale ale NIR-ului pentru a le avea la dispoziție în caz de eroare sau pentru comparații
$sql_get_nir = "SELECT * FROM nir WHERE nr_nir = :nr_nir";
$stmt_get_nir = $pdo->prepare($sql_get_nir);
$stmt_get_nir->execute(['nr_nir' => $nr_nir_original]);
$nir_data = $stmt_get_nir->fetch(PDO::FETCH_ASSOC);

// Dacă nu există date, afișăm un mesaj și oprim execuția
if (!$nir_data) {
    echo "NIR inexistent!";
    exit;
}


// Dacă se apasă butonul "modifica_nir", procesăm formularul
if (isset($_POST['modifica_nir'])) {
    $nr_nir_form  = $_POST['nr_nir'];
    $data_nir_form = $_POST['data_nir'];
    $cod_furnizor = $_POST['cod_furnizor'];
    $serie_doc    = $_POST['serie_doc'];
    $nr_doc       = $_POST['nr_doc'];
    $data_doc     = $_POST['data_doc'];

    // VALIDARE: luna si anul din data_nir trebuie sa fie identice cu luna si anul din data_doc
    $dt_doc = DateTime::createFromFormat('Y-m-d', $data_doc);
    $dt_nir = DateTime::createFromFormat('Y-m-d', $data_nir_form);

    if(!$dt_doc || !$dt_nir) {
        echo "<script>alert('Datele introduse nu sunt valide.');</script>";
        // Suprascriem $nir_data cu valorile din POST pentru a nu pierde inputul utilizatorului
        $nir_data['nr_nir'] = $nr_nir_form;
        $nir_data['data_nir'] = $data_nir_form;
        $nir_data['cod_tert'] = $cod_furnizor;
        $nir_data['serie_doc_int'] = $serie_doc;
        $nir_data['nr_doc_int'] = $nr_doc;
        $nir_data['data_doc_int'] = $data_doc;
    } else {
        $ym_doc = $dt_doc->format('Y-m');
        $ym_nir = $dt_nir->format('Y-m');

        if($ym_doc !== $ym_nir) {
            echo "<script>alert('Luna si anul din Data NIR trebuie sa fie identice cu luna si anul din Data Document Intrare.');</script>";
            // Suprascriem $nir_data cu valorile din POST pentru a nu pierde inputul utilizatorului
            $nir_data['nr_nir'] = $nr_nir_form;
            $nir_data['data_nir'] = $data_nir_form;
            $nir_data['cod_tert'] = $cod_furnizor;
            $nir_data['serie_doc_int'] = $serie_doc;
            $nir_data['nr_doc_int'] = $nr_doc;
            $nir_data['data_doc_int'] = $data_doc;
        } else {

            // Verificăm dacă există deja un NIR cu acest număr (doar dacă numărul a fost schimbat)
            $check_sql = "SELECT COUNT(*) FROM nir WHERE nr_nir = :nr_nir AND nr_nir != :old_nr_nir";
            $stmt_check = $pdo->prepare($check_sql);
            $stmt_check->execute([
                'nr_nir' => $nr_nir_form,
                'old_nr_nir' => $nr_nir_original
            ]);
            $exists = $stmt_check->fetchColumn();

            if ($exists > 0) {
                // Dacă numărul de NIR există deja, alertăm utilizatorul și reumplem formularul cu datele introduse
                echo "<script>alert('Numărul de NIR " . htmlspecialchars($nr_nir_form) . " există deja! Modificarea nu poate continua.');</script>";
                // Suprascriem $nir_data cu valorile din POST pentru a nu pierde inputul utilizatorului
                $nir_data['nr_nir'] = $nr_nir_form;
                $nir_data['data_nir'] = $data_nir_form;
                $nir_data['cod_tert'] = $cod_furnizor;
                $nir_data['serie_doc_int'] = $serie_doc;
                $nir_data['nr_doc_int'] = $nr_doc;
                $nir_data['data_doc_int'] = $data_doc;

            } else {
                // Dacă nu există, continuăm cu update-ul
                $sql_update = "UPDATE nir
                               SET nr_nir = :nr_nir,
                                   data_nir = :data_nir,
                                   cod_tert = :cod_furnizor,
                                   serie_doc_int = :serie_doc,
                                   nr_doc_int = :nr_doc,
                                   data_doc_int = :data_doc
                               WHERE nr_nir = :old_nr_nir";
                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->execute([
                    'nr_nir'       => $nr_nir_form,
                    'data_nir'     => $data_nir_form,
                    'cod_furnizor' => $cod_furnizor,
                    'serie_doc'    => $serie_doc,
                    'nr_doc'       => $nr_doc,
                    'data_doc'     => $data_doc,
                    'old_nr_nir'   => $nr_nir_original
                ]);
                
                $number_has_changed = ($nr_nir_form !== $nr_nir_original);
                $date_has_changed   = ($data_nir_form !== $nir_data['data_nir']);

                // Dacă numărul de NIR s-a modificat, actualizăm în `achizitii` și `miscari`
                if ($number_has_changed) {
                    // 1) Update în tabela `achizitii`
                    $sql_update_achizitii = "UPDATE achizitii SET nr_nir = :new_nr_nir WHERE nr_nir = :old_nr_nir";
                    $stmt_achizitii = $pdo->prepare($sql_update_achizitii);
                    $stmt_achizitii->execute([
                        'new_nr_nir' => $nr_nir_form,
                        'old_nr_nir' => $nr_nir_original
                    ]);

                    // 2) Update în tabela `miscari` (nr_doc și data)
                    // Această interogare actualizează și data, acoperind cazul în care se schimbă și numărul și data.
                    $sql_update_miscari = "UPDATE miscari
                                           SET nr_doc = :new_nr_nir, data = :new_data_nir
                                           WHERE nr_doc = :old_nr_nir AND fel_doc = 'NIR'";
                    $stmt_miscari = $pdo->prepare($sql_update_miscari);
                    $stmt_miscari->execute([
                        'new_nr_nir'   => $nr_nir_form,
                        'new_data_nir' => $data_nir_form,
                        'old_nr_nir'   => $nr_nir_original
                    ]);
                } 
                // NOU: Dacă s-a modificat DOAR data (numărul a rămas același)
                else if ($date_has_changed) {
                    // Actualizăm data în `miscari` pe baza legăturii prin `achizitii`
                    $sql_update_miscari_date = "
                        UPDATE miscari
                        SET data = :new_data_nir
                        WHERE id_achiz IN (
                            SELECT id_achiz
                            FROM achizitii
                            WHERE nr_nir = :nr_nir_curent
                        )
                    ";
                    $stmt_miscari_date = $pdo->prepare($sql_update_miscari_date);
                    $stmt_miscari_date->execute([
                        'new_data_nir'   => $data_nir_form,
                        'nr_nir_curent'  => $nr_nir_original // Folosim numărul original, deoarece nu s-a schimbat
                    ]);
                }

                // Redirecționăm înapoi la lista de NIR-uri cu un mesaj de succes
                echo "<script>alert('NIR modificat cu succes!'); location.href='note_receptie.php';</script>";
                exit;
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($title); ?></title>
    <link href="vendor/offline/bootstrap5/bootstrap.min.css" rel="stylesheet">
    <link href="vendor/offline/select2/select2.min.css" rel="stylesheet" />
    
    <script src="js/jquery-3.6.0.min.js"></script>
    <script src="vendor/offline/bootstrap5/bootstrap.bundle.min.js"></script>
    <script src="vendor/offline/select2/select2.min.js"></script>
</head>
<body>
<div class="container mt-4">
    <h3 class="text-center"><?php echo htmlspecialchars($title); ?></h3>
    <div class="card">
        <div class="card-header">
            Informații Antet NIR
            <a href="note_receptie.php" class="btn btn-outline-secondary btn-sm float-end">Înapoi la listă</a>
        </div>
        <div class="card-body">
            <form method="post" id="nir_form">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="nr_nir" class="form-label">Număr NIR:</label>
                        <input type="text" class="form-control" id="nr_nir" name="nr_nir"
                               value="<?php echo htmlspecialchars($nir_data['nr_nir']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="data_nir" class="form-label">Data NIR:</label>
                        <input type="date" name="data_nir" id="data_nir" class="form-control"
                               value="<?php echo htmlspecialchars($nir_data['data_nir']); ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="cod_furnizor" class="form-label">Furnizor:</label>
                    <select class="form-control select2" id="cod_furnizor" name="cod_furnizor" required>
                        </select>
                </div>
                
                <div class="mb-3">
                    <a href="add_furnizor.php" class="btn btn-secondary">Adaugă Furnizor Nou</a>
                </div>

                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="serie_doc" class="form-label">Serie Document Intrare:</label>
                        <input type="text" class="form-control" id="serie_doc"
                               name="serie_doc" value="<?php echo htmlspecialchars($nir_data['serie_doc_int']); ?>">
                    </div>
                    <div class="col-md-5 mb-3">
                        <label for="nr_doc" class="form-label">Număr Document Intrare:</label>
                        <input type="text" class="form-control" id="nr_doc"
                               name="nr_doc" value="<?php echo htmlspecialchars($nir_data['nr_doc_int']); ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="data_doc" class="form-label">Data Document Intrare:</label>
                        <input type="date" name="data_doc" id="data_doc" class="form-control"
                               value="<?php echo htmlspecialchars($nir_data['data_doc_int']); ?>" required>
                    </div>
                </div>

                <div class="text-center">
                    <button type="submit" class="btn btn-primary" name="modifica_nir">Salvează modificările</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Inițializăm select2
    $('#cod_furnizor').select2({
        placeholder: 'Căutați și alegeți un furnizor',
        theme: "bootstrap-5",
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
        minimumInputLength: 1
    });
    
    // Setăm valoarea curentă din baza de date
    var currentFurnizorId = "<?php echo $nir_data['cod_tert']; ?>";
    
    // Încărcăm furnizorul curent și îl setăm ca "selected"
    if (currentFurnizorId) {
        $.ajax({
            url: 'get_furnizor_by_id.php',
            method: 'GET',
            data: { id: currentFurnizorId },
            dataType: 'json',
            success: function(data) {
                if(data){
                    var option = new Option(data.nume, data.id_furnizor, true, true);
                    $('#cod_furnizor').append(option).trigger('change');
                }
            }
        });
    }

    // VALIDARE frontend: luna/an data_nir == luna/an data_doc (check pe butonul de salveaza / submit)
    $('#nir_form').on('submit', function(e) {
        var dataDoc = $('#data_doc').val();
        var dataNir = $('#data_nir').val();

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
