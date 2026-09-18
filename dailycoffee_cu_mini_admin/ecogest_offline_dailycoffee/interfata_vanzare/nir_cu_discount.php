<?php
// nir_cu_discount.php
ini_set('display_errors', 0); // Nu afișează erorile utilizatorului
ini_set('log_errors', 1); // Activează logarea erorilor
ini_set('error_log', 'error_log.log'); // Specifică calea către fișierul de log
error_reporting(E_ALL); // Raportează toate tipurile de erori

$title = 'NIR cu Discount';
include 'session.php';

if (isset($_GET['nr_nir'])) {
    $nr_nir = $_GET['nr_nir'];
    $_SESSION['nr_nir'] = $nr_nir;
} else if (isset($_SESSION['nr_nir'])) {
    $nr_nir = $_SESSION['nr_nir'];
} else {
    echo "<script>location.href='creare_nir.php';</script>";
    exit;
}

$sql_id_furnizor = "SELECT cod_tert FROM nir WHERE nr_nir = :nr_nir";
$stmt_id_furnizor = $pdo->prepare($sql_id_furnizor);
$stmt_id_furnizor->execute(['nr_nir' => $nr_nir]);
$id_furnizor_data = $stmt_id_furnizor->fetch(PDO::FETCH_ASSOC);
if ($id_furnizor_data) {
    $id_furnizor = $id_furnizor_data['cod_tert'];
} else {
    echo "Cod furnizor nu există.";
    exit;
}

// Fetch NIR data
$sql_nir = "SELECT * FROM nir WHERE nr_nir = :nr_nir";
$stmt_nir = $pdo->prepare($sql_nir);
$stmt_nir->execute(['nr_nir' => $nr_nir]);
$nir_data = $stmt_nir->fetch(PDO::FETCH_ASSOC);

if (!$nir_data) {
    echo "NIR nu există.";
    exit;
}

// Fetch furnizor data
$sql_furnizor = "SELECT * FROM furnizori WHERE id_furnizor = :id_furnizor";
$stmt_furnizor = $pdo->prepare($sql_furnizor);
$stmt_furnizor->execute(['id_furnizor' => $id_furnizor]);
$furnizor_data = $stmt_furnizor->fetch(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>NIR cu Discount/Spor - <?php echo htmlspecialchars($nr_nir); ?></title>
    <link href="vendor/offline/select2/select2.min.css" rel="stylesheet" />
    <link href="vendor/offline/bootstrap5/bootstrap.min.css" rel="stylesheet">
    <script src="js/jquery-3.6.0.min.js"></script>
    <script src="vendor/offline/bootstrap5/bootstrap.bundle.min.js"></script> <script src="vendor/offline/select2/select2.min.js"></script>
    <style>
        #continut_nir {
            max-height: 65vh; /* Înălțime maximă pentru lista de produse */
            overflow-y: auto; /* Adaugă scrollbar vertical doar pentru această listă */
        }
    </style>
</head>
<body>
<div class="container-fluid my-3">
    <h3 align="center">Aplicare Discount / Cost Transport - NIR <?php echo htmlspecialchars($nr_nir); ?></h3>
    <hr>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">Detalii Furnizor</div>
                 <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><b>Denumire:</b> <?php echo htmlspecialchars($furnizor_data['nume']); ?></p>
                            <p><b>Adresa:</b> <?php echo htmlspecialchars($furnizor_data['adresa']); ?></p>
                            <p><b>Județ:</b> <?php echo htmlspecialchars($furnizor_data['adresa_judet']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><b>CUI/CIF:</b> <?php echo htmlspecialchars($furnizor_data['cod_fiscal']); ?></p>
                            <p><b>Nr.ord.reg.com:</b> <?php echo htmlspecialchars($furnizor_data['cod_inmatriculare']); ?></p>
                            <hr>
                            <p><b>Reducere Comercială Totală Aplicată:</b> <span id="reducere_comerciala_display" class="fw-bold text-success"><?php echo htmlspecialchars(number_format($nir_data['reducere_comerciala'] ?? 0, 2, ',', '.')); ?></span> Lei</p>
                            <p><b>Cost Transport Total Adăugat:</b> <span id="sporire_pret_transp_display" class="fw-bold text-danger"><?php echo htmlspecialchars(number_format($nir_data['sporire_pret_transp'] ?? 0, 2, ',', '.')); ?></span> Lei</p>
                        </div>
                    </div>
                 </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <h4>Produse pe NIR (cu prețuri recalculate)</h4>
            <div class="card" id="continut_nir">
                </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4 mb-3">
            <div class="card h-100">
                <div class="card-header">Aplică Discount Global</div>
                <div class="card-body">
                     <div class="form-group mb-2">
                         <label for="discount_value">Valoare Discount Comercial (Lei):</label>
                         <input type="number" step="0.01" class="form-control" id="discount_value" placeholder="ex: 50.75">
                     </div>
                     <button class="btn btn-success w-100" id="btn_aplica_discount">Adaugă Discount Global</button>
                     <small class="form-text text-muted mt-2 d-block">Discountul va fi distribuit proporțional la toate produsele de pe NIR, reducând prețul de achiziție.</small>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card h-100">
                <div class="card-header">Aplică Cost Transport</div>
                <div class="card-body">
                    <div class="form-group mb-2">
                        <label for="sporire_value">Valoare Cost Transport (Lei):</label>
                        <input type="number" step="0.01" class="form-control" id="sporire_value" placeholder="ex: 120.50">
                    </div>
                    <button class="btn btn-warning w-100" id="btn_aplica_sporire">Adaugă Cost Transport</button>
                     <small class="form-text text-muted mt-2 d-block">Costul de transport va fi distribuit proporțional la toate produsele de pe NIR (exceptând gestiunile SGR), crescând prețul de achiziție.</small>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card h-100">
                <div class="card-header">Acțiuni și Listare</div>
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <form method="GET" action="listeaza_nir.php" class="mb-3">
                            <div class="form-group">
                                <label for="gestiune">Selectează Gestiunea:</label>
                                <select name="id_gestiune" id="gestiune" class="form-control">
                                    <?php
                                    $sql_gestiuni = "SELECT id_gestiune, denumire_gestiune FROM gestiuni ORDER BY denumire_gestiune ASC";
                                    $stmt_gestiuni = $pdo->prepare($sql_gestiuni);
                                    $stmt_gestiuni->execute();
                                    while ($row_g = $stmt_gestiuni->fetch(PDO::FETCH_ASSOC)) {
                                        echo "<option value='" . htmlspecialchars($row_g['id_gestiune']) . "'>"
                                             . htmlspecialchars($row_g['denumire_gestiune']) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <input type="hidden" name="nr_nir" value="<?php echo htmlspecialchars($nr_nir); ?>">
                            <button type="submit" class="btn btn-secondary btn-block mt-2 w-100">Listează NIR filtrat</button>
                        </form>
                        <a href='listeaza_nir.php?nr_nir=<?php echo $nr_nir; ?>' class='btn btn-primary btn-block w-100'>Listează NIR complet</a>
                    </div>
                    <a href='nir.php?nr_nir=<?php echo $nr_nir; ?>' class='btn btn-info btn-block w-100 mt-3'>Înapoi la NIR</a>
                </div>
            </div>
        </div>
    </div>

</div>


<script>
// TOT CODUL JAVASCRIPT A FOST PĂSTRAT 100% IDENTIC
$(document).ready(function() {

    // SCRIPT NOU: Handler pentru butonul de discount
    $('#btn_aplica_discount').on('click', function() {
        var discountValue = $('#discount_value').val();
        if (!discountValue || parseFloat(discountValue) <= 0) {
            alert('Vă rugăm să introduceți o valoare validă pentru discount.');
            return;
        }

        if (confirm('Sunteți sigur că doriți să aplicați acest discount? Acțiunea este ireversibilă și va recalcula prețurile de achiziție pentru toate produsele de pe acest NIR.')) {
            // ÎNTREBARE SUPLIMENTARĂ PENTRU ACTUALIZAREA CATALOGULUI
            var updateCatalog = confirm('Doriți să actualizați prețurile de achiziție în catalogul de produse cu noile valori reduse? OK = DA, Anulare = NU');
            var updateCatalogFlag = updateCatalog ? 1 : 0; // Convertim boolean în 0 sau 1 pentru PHP

            $.ajax({
                url: 'nir_cu_discount_aplica_discount.php', // ATENTIE: Cererea se trimite catre noul fisier!
                method: 'POST',
                data: {
                    discount_value: discountValue,
                    nr_nir: '<?php echo $nr_nir; ?>',
                    update_preturi_catalog: updateCatalogFlag // <-- PARAMETRU NOU ADĂUGAT
                },
                dataType: 'json',
                success: function(response) {
                    alert(response.message);
                    if (response.success) {
                        loadNIRProducts();

                        var currentDiscount = parseFloat($('#reducere_comerciala_display').text().replace(/\./g, '').replace(',', '.')) || 0;
                        var newTotalDiscount = currentDiscount + parseFloat(discountValue);
                        $('#reducere_comerciala_display').text(newTotalDiscount.toLocaleString('ro-RO', {minimumFractionDigits: 2, maximumFractionDigits: 2}));

                        $('#discount_value').val('');
                    }
                },
                error: function(xhr, status, error) {
                    alert('A apărut o eroare tehnică. Vă rugăm să reîncercați.');
                    console.error("XHR Response: ", xhr.responseText);
                }
            });
        }
    });

    // ### START SCRIPT NOU DE ADAUGAT ###
    $('#btn_aplica_sporire').on('click', function() {
        var sporireValue = $('#sporire_value').val();
        if (!sporireValue || parseFloat(sporireValue) <= 0) {
            alert('Vă rugăm să introduceți o valoare validă pentru costul de transport.');
            return;
        }

        if (confirm('Sunteți sigur că doriți să adăugați acest cost de transport? Acțiunea este ireversibilă și va recalcula prețurile de achiziție pentru toate produsele eligibile de pe acest NIR.')) {
            // ÎNTREBARE SUPLIMENTARĂ PENTRU ACTUALIZAREA CATALOGULUI
            var updateCatalog = confirm('Doriți să actualizați prețurile de achiziție în catalogul de produse cu noile valori majorate? OK = DA, Anulare = NU');
            var updateCatalogFlag = updateCatalog ? 1 : 0; // Convertim boolean în 0 sau 1 pentru PHP

            $.ajax({
                url: 'nir_cu_discount_aplica_sporire.php', // ATENTIE: Cererea se trimite catre noul fisier!
                method: 'POST',
                data: {
                    sporire_value: sporireValue,
                    nr_nir: '<?php echo $nr_nir; ?>',
                    update_preturi_catalog: updateCatalogFlag // PARAMETRU NOU ADĂUGAT
                },
                dataType: 'json',
                success: function(response) {
                    alert(response.message);
                    if (response.success) {
                        loadNIRProducts();

                        // Actualizam afisajul costului de transport total
                        var currentSporire = parseFloat($('#sporire_pret_transp_display').text().replace(/\./g, '').replace(',', '.')) || 0;
                        var newTotalSporire = currentSporire + parseFloat(sporireValue);
                        $('#sporire_pret_transp_display').text(newTotalSporire.toLocaleString('ro-RO', {minimumFractionDigits: 2, maximumFractionDigits: 2}));

                        $('#sporire_value').val('');
                    }
                },
                error: function(xhr, status, error) {
                    alert('A apărut o eroare tehnică la adăugarea costului. Vă rugăm să reîncercați.');
                    console.error("XHR Response: ", xhr.responseText);
                }
            });
        }
    });
    // ### END SCRIPT NOU DE ADAUGAT ###

    $('.toggle-header').click(function(){
        $(this).next('.card-body').slideToggle();
    });
    
    // Funcționalitatea de adăugare produse este păstrată, dar nu este expusă în UI-ul principal al acestei pagini
    $('#cod_produs').select2({
        placeholder: 'Alegeți un produs',
        ajax: {
            url: 'search_produse.php',
            dataType: 'json',
            delay: 250,
            data: function(params) { return { q: params.term }; },
            processResults: function(data) {
                return {
                    results: data.map(function(item) {
                        return { id: item.cod_produs, text: item.nume };
                    })
                };
            },
            cache: true
        },
        minimumInputLength: 1,
    });

    $('#cod_produs').on('change', function() {
        var cod_produs = $(this).val();
        if (cod_produs) {
            $.ajax({
                url: 'nir_get_produs.php',
                method: 'GET',
                data: { q: cod_produs },
                success: function(data) { $('#product_details').html(data); }
            });
        } else {
            $('#product_details').html('');
        }
    });

    function loadNIRProducts() {
        var nr_nir = <?php echo json_encode($nr_nir); ?>;
        $.ajax({
            url: 'nir_produse.php',
            method: 'GET',
            data: { nr_nir: nr_nir },
            success: function(response) { $('#continut_nir').html(response); },
            error: function() { $('#continut_nir').html('<p class="text-danger">Eroare la încărcarea produselor.</p>'); }
        });
    }

    loadNIRProducts();

    $('#add_product_form').on('submit', function(event) {
        event.preventDefault();
        var pretAchiz = parseFloat($('#pret_achiz').val());
        var initialPretAchiz = parseFloat($('#initial_pret_achiz').val());
        if (pretAchiz !== initialPretAchiz) {
            if (confirm("Prețul de achiziție este diferit de cel din baza de date. Doriți să actualizați prețul în catalog? Ok=DA Cancel=NU")) {
                $('#update_price').val('1');
            } else {
                $('#update_price').val('0');
            }
        }
        var formData = $(this).serialize();
        $.post('nir_adauga_produs.php', formData, function(response) {
            if (response.indexOf("Eroare") !== -1) { alert(response); }
            loadNIRProducts();
        });
    });

    $(document).on('submit', '.delete-product-form', function(event) {
        event.preventDefault();
        if (confirm("Sigur doriți să ștergeți acest produs? Acțiunea poate afecta calculele de discount deja aplicate.")) {
            var formData = $(this).serialize();
            $.post('nir_sterge_produs.php', formData, function(response) {
                if(response.indexOf("Eroare") !== -1) { alert(response); }
                loadNIRProducts();
            });
        }
    });
});

</script>

</body>
</html>
