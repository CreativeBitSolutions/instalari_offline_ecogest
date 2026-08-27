<?php
// nir.php

$title = 'NIR';

// --- Config erori (prod safe) ---
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.log');
error_reporting(E_ALL);

// --- Include-uri ---
include 'session.php';

// --- Determinare nr_nir din GET sau din sesiune ---
if (isset($_GET['nr_nir'])) {
    $nr_nir = $_GET['nr_nir'];
    $_SESSION['nr_nir'] = $nr_nir;
} elseif (isset($_SESSION['nr_nir'])) {
    $nr_nir = $_SESSION['nr_nir'];
} else {
    echo "<script>location.href='creare_nir.php';</script>";
    exit;
}

// --- Id furnizor pentru NIR ---
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

// --- Date NIR ---
$sql_nir = "SELECT * FROM nir WHERE nr_nir = :nr_nir";
$stmt_nir = $pdo->prepare($sql_nir);
$stmt_nir->execute(['nr_nir' => $nr_nir]);
$nir_data = $stmt_nir->fetch(PDO::FETCH_ASSOC);

if (!$nir_data) {
    echo "NIR nu există.";
    exit;
}

// --- Date furnizor ---
$sql_furnizor = "SELECT * FROM furnizori WHERE id_furnizor = :id_furnizor";
$stmt_furnizor = $pdo->prepare($sql_furnizor);
$stmt_furnizor->execute(['id_furnizor' => $id_furnizor]);
$furnizor_data = $stmt_furnizor->fetch(PDO::FETCH_ASSOC);

// --- Produse disponibile pentru selectie manuala ---
$sql_produse = "
    SELECT ps.cod_produs, ps.nume, ps.cod_bare
    FROM produse_servicii ps
    LEFT JOIN gestiuni g ON g.id_gestiune = ps.id_gestiune
    WHERE COALESCE(g.denumire_gestiune, '') NOT IN ('PRODUSE FINITE', 'ALTELE')
    ORDER BY ps.nume ASC
";
$stmt_produse = $pdo->prepare($sql_produse);
$stmt_produse->execute();
$produse_selectie = $stmt_produse->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8" />
    <title>NIR - <?= htmlspecialchars($nr_nir); ?></title>

    <link rel="stylesheet" href="vendor/offline/select2/select2.min.css" />
    <link rel="stylesheet" href="vendor/offline/bootstrap5/bootstrap.min.css" />

    <script src="js/jquery-3.6.0.min.js"></script>
    <script src="vendor/offline/bootstrap5/bootstrap.bundle.min.js"></script>
    <script src="vendor/offline/select2/select2.min.js"></script>

    <style>
        /* --- Modal lateral (50% dreapta) --- */
        .modal.fade .modal-dialog.modal-right-half { transform: translateX(100%); }
        .modal.show .modal-dialog.modal-right-half { transform: translateX(0); }

        .modal-dialog.modal-right-half {
            position: fixed;
            top: 0;
            right: 0;
            margin: 0;
            width: 50%;
            height: 100%;
            max-width: none;
            transition: transform .3s ease-out;
        }

        .modal-content.h-100 {
            height: 100%;
            border-radius: 0;
            display: flex;
            flex-direction: column; /* flex vertical */
        }
        .modal-body {
            flex: 1 1 auto;
            overflow-y: auto;
        }

        /* Lista produse (scroll dedicat) */
        #continut_nir {
            max-height: 65vh;
            overflow-y: auto;
        }

        /* Grid de detalii în modalele de produse */
        @media (min-width: 768px) {
            #product_details {
                grid-column: 1 / -1;
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 8px 12px;
            }

            #modalAdaugaProdusManual .form-control,
            #modalAdaugaProdusManual .form-select {
                padding: .25rem .5rem;
                font-size: .875rem;
                height: calc(1.5em + .5rem + 2px);
            }

            #modalAdaugaProdusManual .form-label {
                margin-bottom: .15rem;
                font-size: .8rem;
            }
        }

        /* Accent vizual pe câmpuri */
        .camp-principal {
            background-color: #e7f3ff;
            padding: 8px 12px;
            border-radius: 5px;
            border-left: 4px solid #0d6efd;
        }
        .camp-secundar {
            background-color: #f8f9fa;
            padding: 8px 12px;
            border-radius: 5px;
            border-left: 4px solid #adb5bd;
        }
    </style>
</head>
<body>
<div class="container-fluid my-3">

    <div class="d-flex align-items-center justify-content-between mb-2">
        <h3 class="m-0">NIR - <?= htmlspecialchars($nr_nir); ?></h3>
        <button type="button" class="btn btn-success" onclick="window.history.back();">
            Salveaza
        </button>
    </div>
    <hr />

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">Detalii Furnizor</div>
                <div class="card-body">
                    <p class="mb-1"><b>Denumire:</b> <?= htmlspecialchars($furnizor_data['nume'] ?? ''); ?></p>
                    <p class="mb-1"><b>Adresa:</b> <?= htmlspecialchars($furnizor_data['adresa'] ?? ''); ?></p>
                    <p class="mb-1"><b>Județ:</b> <?= htmlspecialchars($furnizor_data['adresa_judet'] ?? ''); ?></p>
                    <p class="mb-1"><b>CUI/CIF:</b> <?= htmlspecialchars($furnizor_data['cod_fiscal'] ?? ''); ?></p>
                    <p class="mb-0"><b>Nr. ord. reg. com.:</b> <?= htmlspecialchars($furnizor_data['cod_inmatriculare'] ?? ''); ?></p>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">Adaugă Produse pe NIR</div>
                <div class="card-body d-flex flex-column justify-content-center">
                    <button
                        type="button"
                        class="btn btn-info w-100"
                        data-bs-toggle="modal"
                        data-bs-target="#modalAdaugaProdusManual">
                        Adaugă Produs Manual
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <h4 class="mb-2">Produse pe NIR</h4>
            <div class="card" id="continut_nir"></div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">

                <div class="card-header">Acțiuni și Listare</div>

                <div class="card-body">
                    <form method="GET" action="listeaza_nir.php" class="row g-2">
                        <div class="col-md-6">
                            <label for="gestiune" class="form-label">Selectează Gestiunea</label>
                            <select name="id_gestiune" id="gestiune" class="form-select">
                                <?php
                                // Selectăm id + denumire gestiune
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

                        <div class="col-md-6 d-flex align-items-end">
                            <input type="hidden" name="nr_nir" value="<?= htmlspecialchars($nr_nir); ?>" />
                            <div class="d-flex gap-2 w-100">
                                <button type="submit" name="spatii" value="1" class="btn btn-outline-secondary w-50">
                                    LISTEAZA CU SPATII
                                </button>
                                <button type="submit" name="spatii" value="0" class="btn btn-outline-dark w-50">
                                    LISTEAZA FARA SPATII
                                </button>
                            </div>
                        </div>
                    </form>

                    <hr /> <form method="GET" action="listeaza_nir.php" class="row g-2">
                        <div class="col-md-6">
                            <label for="cota_tva" class="form-label">Selectează Cota TVA</label>
                            <select name="cota_tva" id="cota_tva" class="form-select">
                                <?php
                                // Selectăm cotele TVA din tabela dedicată
                                $sql_cote = "SELECT cota FROM cote_tva ORDER BY cota ASC";
                                $stmt_cote = $pdo->prepare($sql_cote);
                                $stmt_cote->execute();
                                while ($row_c = $stmt_cote->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<option value='" . htmlspecialchars($row_c['cota']) . "'>"
                                        . htmlspecialchars($row_c['cota']) . "%</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-md-6 d-flex align-items-end">
                            <input type="hidden" name="nr_nir" value="<?= htmlspecialchars($nr_nir); ?>" />
                            <div class="d-flex gap-2 w-100">
                                <button type="submit" name="spatii" value="1" class="btn btn-info w-50">
                                    LISTEAZA CU SPATII
                                </button>
                                <button type="submit" name="spatii" value="0" class="btn btn-outline-info w-50">
                                    LISTEAZA FARA SPATII
                                </button>
                            </div>
                        </div>
                    </form>
                    </div>

            <div class="card-footer">
    <div class="row g-2">
        <div class="col-md-3">
            <a
                href="listeaza_nir.php?nr_nir=<?= urlencode($nr_nir); ?>&spatii=1"
                class="btn btn-primary w-100">
                LISTEAZA CU SPATII
            </a>
        </div>

        <div class="col-md-3">
            <a
                href="listeaza_nir.php?nr_nir=<?= urlencode($nr_nir); ?>&spatii=0"
                class="btn btn-outline-primary w-100">
                LISTEAZA FARA SPATII
            </a>
        </div>

        <div class="col-md-3">
            <a
                href="nir_cu_discount.php?nr_nir=<?= urlencode($nr_nir); ?>"
                class="btn btn-success w-100">
                Aplică discount / Cost adițional (transport)
            </a>
        </div>

        <div class="col-md-3">
            <?php if (!empty($nr_nir)): ?>
                <a
                    href="genereaza_etichete_nir.php?nr_nir=<?= urlencode($nr_nir); ?>"
                    class="btn btn-dark w-100"
                    target="_blank">
                    Generează etichete pentru acest NIR
                </a>
            <?php endif; ?>
        </div>
        
        <div class="col-md-3">
            <a
                href="listeaza_nir_simplificat.php?nr_nir=<?= urlencode($nr_nir); ?>"
                class="btn btn-info w-100">
                Listează NIR simplificat fara defalcare pe TVA
            </a>
        </div>
<div class="col-md-3">
            <a
                href="listeaza_nir_defalcat_tva_fara_totaluri_generale.php?nr_nir=<?= urlencode($nr_nir); ?>"
                class="btn btn-primary w-100">
                Listează NIR complet defalcat pe cote TVA fara sectiunea TOTALURI GENERALE
            </a>
        </div>



         <div class="col-md-3">
            <a
                href="listeaza_nir_simplificat_fara_vanzare.php?nr_nir=<?= urlencode($nr_nir); ?>"
                class="btn btn-info w-100">
                Listează NIR simplificat fara detalii sub NIR si secțiunea de vanzare (doar col 1-8)
            </a>
        </div>
    </div>
</div>


            </div> </div>
    </div>
<div class="modal fade" id="modalAdaugaProdusManual" tabindex="-1" aria-labelledby="modalAdaugaProdusManualLabel" aria-hidden="true">
    <div class="modal-dialog modal-right-half">
        <div class="modal-content h-100">
            <form method="post" id="add_product_form" class="d-flex flex-column h-100">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalAdaugaProdusManualLabel">Adaugă Produs Manual</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Închide"></button>
                </div>

                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label for="cod_produs" class="form-label">Produs</label>
                        <select class="form-control select2" id="cod_produs" name="cod_produs" required style="width:100%;">
                            <option value="">Selecteaza produsul</option>
                            <?php foreach ($produse_selectie as $produs): ?>
                                <?php
                                $text_produs = $produs['nume'];
                                if (!empty($produs['cod_bare'])) {
                                    $text_produs .= ' (' . $produs['cod_bare'] . ')';
                                }
                                ?>
                                <option value="<?= htmlspecialchars($produs['cod_produs']); ?>">
                                    <?= htmlspecialchars($text_produs); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="product_details"></div>

                    <input type="hidden" name="adaug_produs" value="1" />
                    <input type="hidden" name="update_price" id="update_price" value="0" />
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anulează</button>
                    <button type="submit" class="btn btn-primary">Adaugă produs (Un singur click)</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Toggle pentru card-body generic (dacă vrei să pliezi secțiuni cu .toggle-header)
    $('.toggle-header').on('click', function () {
        $(this).next('.card-body').slideToggle();
    });
</script>

<script>
/* TOT CODUL JAVASCRIPT A RĂMAS LOGIC IDENTIC — doar formatat */

$(document).ready(function () {

    // Lățimi pe 2 coloane pentru anumite câmpuri din modale
    (function () {
        // Modal "Produs Manual"
        $('#modalAdaugaProdusManual #cod_produs').closest('.form-group').addClass('span-2');

    })();

    // --- Select2 pentru produse (căutare după nume sau cod de bare) ---
    $('#cod_produs').select2({
        dropdownParent: $('#modalAdaugaProdusManual'),
        placeholder: 'Caută după nume sau cod de bare...'
    });

    // La selectarea produsului (manual) -> încărcăm detaliile
    $('#cod_produs').on('change', function () {
        var cod_produs = $(this).val();
        if (cod_produs) {
            $.ajax({
                url: 'nir_get_produs.php',
                method: 'GET',
                data: { q: cod_produs },
                success: function (data) {
                    $('#product_details').html(data);
                }
            });
        } else {
            $('#product_details').html('');
        }
    });

    // ===== Funcție: încarcă produsele din NIR în card-ul dedicat =====
    function loadNIRProducts() {
        var nr_nir = <?= json_encode($nr_nir); ?>;
        $.ajax({
            url: 'nir_produse.php',
            method: 'GET',
            data: { nr_nir: nr_nir },
            success: function (response) {
                $('#continut_nir').html(response);
            },
            error: function () {
                $('#continut_nir').html('<p class="text-danger m-3">Eroare la încărcarea produselor.</p>');
            }
        });
    }

    // La încărcarea paginii
    loadNIRProducts();

    // ===== Submit: Adăugare produs MANUAL =====
    $('#add_product_form').on('submit', function (event) {
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
        $.post('nir_adauga_produs.php', formData, function (response) {
            if (response.indexOf("Eroare") !== -1) {
                alert(response);
            }
            loadNIRProducts();
            $('#modalAdaugaProdusManual').modal('hide');
        });
    });

    // ===== Ștergere produs din NIR =====
    $(document).on('submit', '.delete-product-form', function (event) {
        event.preventDefault();

        if (confirm("Sigur doriți să ștergeți acest produs?")) {
            var formData = $(this).serialize();
            $.post('nir_sterge_produs.php', formData, function (response) {
                if (response.indexOf("Eroare") !== -1) {
                    alert(response);
                }
                loadNIRProducts();
            });
        }
    });

});
</script>
</body>
</html>
