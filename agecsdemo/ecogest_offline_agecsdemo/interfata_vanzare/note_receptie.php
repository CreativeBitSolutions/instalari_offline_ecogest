<?php
// note_receptie.php

$title = 'Lista Note de Recepție';
include 'session.php';

// --- Logica pentru datele de filtrare ---

// 1. Date implicite: luna curentă
$data_start_luna = date('Y-m-01');
$data_end_luna   = date('Y-m-t');

// 2. Verificăm dacă formularul a fost trimis
$is_filtered = isset($_GET['filtru_activ']);

// 3. Preluăm filtre pentru query (doar dacă s-a trimis formularul)
$furnizor  = $is_filtered ? ($_GET['furnizor']  ?? '') : '';
$din_data  = $is_filtered ? ($_GET['din_data']  ?? '') : '';
$pana_data = $is_filtered ? ($_GET['pana_data'] ?? '') : '';

// 4. Valorile pentru formular (HTML)
$form_furnizor  = $is_filtered ? $furnizor  : '';
$form_din_data  = $is_filtered ? $din_data  : $data_start_luna;
$form_pana_data = $is_filtered ? $pana_data : $data_end_luna;

// 5. WHERE în funcție de filtre
$conds  = [];
$params = [];
if ($furnizor) {
    $conds[]              = "n.cod_tert = :furnizor";
    $params[':furnizor']  = $furnizor;
}
if ($din_data) {
    $conds[]              = "n.data_nir >= :din_data";
    $params[':din_data']  = $din_data;
}
if ($pana_data) {
    $conds[]               = "n.data_nir <= :pana_data";
    $params[':pana_data']  = $pana_data;
}
$where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';

// 6. Interogarea principală
$sql = "
    SELECT n.*, f.nume AS furnizor_denumire
    FROM nir n
    JOIN furnizori f ON n.cod_tert = f.id_furnizor
    $where
    ORDER BY CAST(n.nr_nir AS INTEGER) DESC, n.nr_nir DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$nirs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 7. Lista furnizori
$furnizori_stmt = $pdo->query("SELECT id_furnizor, nume FROM furnizori ORDER BY nume");
$furnizori      = $furnizori_stmt->fetchAll(PDO::FETCH_ASSOC);

// 8. Cote TVA distincte pentru filtrul din modal
$tva_rates_stmt = $pdo->query("SELECT DISTINCT cota_tva FROM achizitii ORDER BY cota_tva ASC");
$tva_rates      = $tva_rates_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>

    <link href="vendor/offline/bootstrap5/bootstrap.min.css" rel="stylesheet">
    <link href="vendor/offline/datatables/dataTables.bootstrap5.min.css" rel="stylesheet">

    <style>
        .dt-nowrap { white-space: nowrap; }
        #nirTable .btn-sm {
            padding: 0.2rem 0.4rem;
            font-size: 0.8rem;
        }
        .nir-filter-actions {
            display: flex;
            gap: 0.5rem;
        }
        .nir-filter-actions .btn {
            white-space: nowrap;
        }
        @media (min-width: 1400px) {
            .nir-filter-row {
                display: grid;
                grid-template-columns: 0.9fr 0.9fr 1.35fr 1fr 1fr auto;
                gap: 0.75rem;
                align-items: end;
            }
            .nir-filter-row > div {
                width: 100%;
                max-width: none;
            }
        }
    </style>
</head>
<body>
<div class="container-fluid mt-4">

    <h3 class="text-center mb-3"><?= htmlspecialchars($title) ?></h3>

    <!-- Bara de butoane pentru rapoarte (design clasic, rapid) -->
    <div class="mb-3 d-flex flex-wrap gap-2">
        <a href="vanzare_magazin.php" class="btn btn-secondary">
            &larr; Inapoi la POS
        </a>
        <a href="creare_nir.php" class="btn btn-primary">
            Creare NIR manual
        </a>
        <a href="furnizori_admin.php" class="btn btn-outline-primary">
            Furnizori
        </a>
        <button type="button" class="btn btn-outline-secondary"
                data-bs-toggle="modal" data-bs-target="#modalRaportNirPdf">
            Raport NIR standard (PDF)
        </button>
        <button type="button" class="btn btn-outline-success"
                data-bs-toggle="modal" data-bs-target="#modalRaportNirExcel">
            Raport NIR standard (Excel)
        </button>
        <button type="button" class="btn btn-outline-dark"
                data-bs-toggle="modal" data-bs-target="#modalRaportNirAgregatPdf">
            Raport NIR agregat (PDF)
        </button>
        <button type="button" class="btn btn-outline-primary"
                data-bs-toggle="modal" data-bs-target="#modalRaportNirAgregatExcel">
            Raport NIR agregat (Excel)
        </button>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light py-3">
            <h5 class="mb-0">Filtre</h5>
            <form method="get" class="row g-2 align-items-end mt-1 nir-filter-row">
                <input type="hidden" name="filtru_activ" value="1">

                <div class="col-lg-2 col-md-6">
                    <label for="notereceptie_select_year" class="form-label">Anul:</label>
                    <select id="notereceptie_select_year" class="form-select">
                        <?php 
                            $current_year = date('Y');
                            $current_month = date('n');
                            for ($y = $current_year - 5; $y <= $current_year + 5; $y++) {
                                $selected = ($y == $current_year) ? ' selected' : '';
                                echo "<option value=\"$y\"$selected>$y</option>";
                            }
                        ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label for="notereceptie_select_month" class="form-label">Luna:</label>
                    <select id="notereceptie_select_month" class="form-select">
                        <?php 
                        $months = array('1' => 'Ianuarie', '2' => 'Februarie', '3' => 'Martie', '4' => 'Aprilie', '5' => 'Mai', '6' => 'Iunie', '7' => 'Iulie', '8' => 'August', '9' => 'Septembrie', '10' => 'Octombrie', '11' => 'Noiembrie', '12' => 'Decembrie');
                        for ($m = 1; $m <= 12; $m++) {
                            $selected = ($m == $current_month) ? ' selected' : '';
                            echo "<option value=\"$m\"$selected>{$months[$m]}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="col-lg-3 col-md-6">
                    <label for="furnizor" class="form-label">Furnizor</label>
                    <select id="furnizor" name="furnizor" class="form-select">
                        <option value="">Toți furnizorii</option>
                        <?php foreach ($furnizori as $f): ?>
                            <option value="<?= $f['id_furnizor'] ?>" <?= $form_furnizor == $f['id_furnizor'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($f['nume']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label">Din data</label>
                    <input type="date" id="notereceptie_data_inceput" name="din_data" class="form-control" value="<?= htmlspecialchars($form_din_data) ?>">
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label">Până la data</label>
                    <input type="date" id="notereceptie_data_sfarsit" name="pana_data" class="form-control" value="<?= htmlspecialchars($form_pana_data) ?>">
                </div>

                <div class="col-lg-3 col-md-12">
                    <label class="form-label d-block invisible">Acțiuni</label>
                    <div class="nir-filter-actions">
                        <button type="submit" class="btn btn-primary flex-fill">Aplică Filtre</button>
                        <a href="note_receptie.php" class="btn btn-info flex-fill">Resetare</a>
                    </div>
                </div>
            </form>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped w-100" id="nirTable">
                    <thead class="table-light">
                    <tr>
                        <th class="dt-nowrap">Nr. NIR</th>
                        <th class="dt-nowrap">Nr. Document Intern</th>
                        <th class="dt-nowrap">Data NIR</th>
                        <th class="text-end dt-nowrap">Valoare fără TVA</th>
                        <th>Furnizor</th>
                        <th class="dt-nowrap">Acțiuni</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($nirs as $nir): ?>
                        <tr>
                            <td class="dt-nowrap"><?= htmlspecialchars($nir['nr_nir']) ?></td>
                            <td class="dt-nowrap"><?= htmlspecialchars($nir['serie_doc_int'] . $nir['nr_doc_int']) ?></td>
                            <td class="dt-nowrap"><?= date('d.m.Y', strtotime($nir['data_nir'])) ?></td>
                            <td class="text-end dt-nowrap"><?= number_format($nir['val_nir_ftva'], 2, ',', '.') ?></td>
                            <td><?= htmlspecialchars($nir['furnizor_denumire']) ?></td>
                            <td class="dt-nowrap">
                                <a href="nir.php?nr_nir=<?= $nir['nr_nir'] ?>" class="btn btn-sm btn-primary" title="Detalii">Detalii</a>
                                <a href="listeaza_nir.php?nr_nir=<?= $nir['nr_nir'] ?>" class="btn btn-sm btn-info" title="PDF">PDF</a>
                                <a href="modifica_nir.php?nr_nir=<?= $nir['nr_nir'] ?>" class="btn btn-sm btn-warning" title="Modifică Antet">Modifică antet</a>
                                <a href="sterge_nir.php?nr_nir=<?= $nir['nr_nir'] ?>" class="btn btn-sm btn-danger" title="Șterge">Șterge</a>
                                <a href="genereaza_etichete_nir.php?nr_nir=<?= urlencode($nir['nr_nir']) ?>"
                                   class="btn btn-sm btn-dark" target="_blank"
                                   title="Generează etichete pentru toate produsele din NIR">
                                    Etichete
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Raport NIR standard (PDF) cu alegere orientare -->
<div class="modal fade" id="modalRaportNirPdf" tabindex="-1" aria-labelledby="modalRaportNirPdfLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formRaportNirPdf" method="POST" target="_blank">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalRaportNirPdfLabel">Raport NIR standard (PDF)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Închide"></button>
                </div>
                <div class="modal-body">
                    <!-- Selectare rapidă An și Lună -->
                    <div class="row g-3 mb-2">
                        <div class="col-md-6">
                            <label for="nirpdf_select_year" class="form-label"><b>Selectare rapidă: An</b></label>
                            <select id="nirpdf_select_year" class="form-control">
                                <?php 
                                $currentYear = date('Y');
                                for($y = $currentYear; $y >= $currentYear - 5; $y--): ?>
                                  <option value="<?php echo $y; ?>" <?php echo ($y == $currentYear) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="nirpdf_select_month" class="form-label"><b>Selectare rapidă: Lună</b></label>
                            <select id="nirpdf_select_month" class="form-control">
                                <?php 
                                $currentMonth = date('n');
                                $months = ['Ianuarie', 'Februarie', 'Martie', 'Aprilie', 'Mai', 'Iunie', 
                                          'Iulie', 'August', 'Septembrie', 'Octombrie', 'Noiembrie', 'Decembrie'];
                                for($m = 1; $m <= 12; $m++): ?>
                                  <option value="<?php echo $m; ?>" <?php echo ($m == $currentMonth) ? 'selected' : ''; ?>><?php echo $months[$m-1]; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="nirpdf_start_date" class="form-label">Data de început</label>
                            <input type="date" class="form-control" id="nirpdf_start_date" name="start_date" required>
                        </div>
                        <div class="col-md-4">
                            <label for="nirpdf_end_date" class="form-label">Data de sfârșit</label>
                            <input type="date" class="form-control" id="nirpdf_end_date" name="end_date">
                        </div>
                        <div class="col-md-4">
                            <label for="cota_tva_pdf" class="form-label">Cota TVA</label>
                            <select class="form-control" id="cota_tva_pdf" name="cota_tva">
                                <option value="">Toate cotele</option>
                                <?php foreach ($tva_rates as $rate): ?>
                                    <option value="<?= htmlspecialchars($rate['cota_tva']) ?>">
                                        <?= htmlspecialchars($rate['cota_tva']) ?>%
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mt-3">
                        <div class="col-md-12">
                            <label class="form-label">Orientare pagină</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio"
                                       id="nir_pdf_landscape" name="nir_pdf_orientare"
                                       value="landscape" checked>
                                <label class="form-check-label" for="nir_pdf_landscape">Landscape (orizontal)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio"
                                       id="nir_pdf_portrait" name="nir_pdf_orientare"
                                       value="portrait">
                                <label class="form-check-label" for="nir_pdf_portrait">Portret (vertical)</label>
                            </div>
                        </div>
                    </div>

                    <small class="text-muted d-block mt-2">
                        Se generează raportul standard cu cote TVA, în funcție de orientarea aleasă.
                    </small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Închide</button>
                    <button type="submit" class="btn btn-outline-secondary">Generează PDF</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Raport NIR standard (Excel) (fără orientare, rămâne la fel) -->
<div class="modal fade" id="modalRaportNirExcel" tabindex="-1" aria-labelledby="modalRaportNirExcelLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="raport_nir_excel.php" method="POST" target="_blank">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalRaportNirExcelLabel">Raport NIR standard (Excel)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Închide"></button>
                </div>
                <div class="modal-body">
                    <!-- Selectare rapidă An și Lună -->
                    <div class="row g-3 mb-2">
                        <div class="col-md-6">
                            <label for="nirxls_select_year" class="form-label"><b>Selectare rapidă: An</b></label>
                            <select id="nirxls_select_year" class="form-control">
                                <?php 
                                $currentYear = date('Y');
                                for($y = $currentYear; $y >= $currentYear - 5; $y--): ?>
                                  <option value="<?php echo $y; ?>" <?php echo ($y == $currentYear) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="nirxls_select_month" class="form-label"><b>Selectare rapidă: Lună</b></label>
                            <select id="nirxls_select_month" class="form-control">
                                <?php 
                                $currentMonth = date('n');
                                $months = ['Ianuarie', 'Februarie', 'Martie', 'Aprilie', 'Mai', 'Iunie', 
                                          'Iulie', 'August', 'Septembrie', 'Octombrie', 'Noiembrie', 'Decembrie'];
                                for($m = 1; $m <= 12; $m++): ?>
                                  <option value="<?php echo $m; ?>" <?php echo ($m == $currentMonth) ? 'selected' : ''; ?>><?php echo $months[$m-1]; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="nirxls_start_date" class="form-label">Data de început</label>
                            <input type="date" class="form-control" id="nirxls_start_date" name="start_date" required>
                        </div>
                        <div class="col-md-4">
                            <label for="nirxls_end_date" class="form-label">Data de sfârșit</label>
                            <input type="date" class="form-control" id="nirxls_end_date" name="end_date">
                        </div>
                        <div class="col-md-4">
                            <label for="cota_tva_excel" class="form-label">Cota TVA</label>
                            <select class="form-control" id="cota_tva_excel" name="cota_tva">
                                <option value="">Toate cotele</option>
                                <?php foreach ($tva_rates as $rate): ?>
                                    <option value="<?= htmlspecialchars($rate['cota_tva']) ?>">
                                        <?= htmlspecialchars($rate['cota_tva']) ?>%
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <small class="text-muted d-block mt-2">Export în format Excel.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Închide</button>
                    <button type="submit" class="btn btn-success">Generează Excel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Raport NIR agregat (PDF) cu alegere orientare -->
<div class="modal fade" id="modalRaportNirAgregatPdf" tabindex="-1" aria-labelledby="modalRaportNirAgregatPdfLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formRaportNirAgregatPdf" method="POST" target="_blank">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalRaportNirAgregatPdfLabel">Raport NIR agregat (PDF)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Închide"></button>
                </div>
                <div class="modal-body">
                    <!-- Selectare rapidă An și Lună -->
                    <div class="row g-3 mb-2">
                        <div class="col-md-6">
                            <label for="niragpdf_select_year" class="form-label"><b>Selectare rapidă: An</b></label>
                            <select id="niragpdf_select_year" class="form-control">
                                <?php 
                                $currentYear = date('Y');
                                for($y = $currentYear; $y >= $currentYear - 5; $y--): ?>
                                  <option value="<?php echo $y; ?>" <?php echo ($y == $currentYear) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="niragpdf_select_month" class="form-label"><b>Selectare rapidă: Lună</b></label>
                            <select id="niragpdf_select_month" class="form-control">
                                <?php 
                                $currentMonth = date('n');
                                $months = ['Ianuarie', 'Februarie', 'Martie', 'Aprilie', 'Mai', 'Iunie', 
                                          'Iulie', 'August', 'Septembrie', 'Octombrie', 'Noiembrie', 'Decembrie'];
                                for($m = 1; $m <= 12; $m++): ?>
                                  <option value="<?php echo $m; ?>" <?php echo ($m == $currentMonth) ? 'selected' : ''; ?>><?php echo $months[$m-1]; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="niragpdf_start_date" class="form-label">Data de început</label>
                            <input type="date" class="form-control" id="niragpdf_start_date" name="start_date" required>
                        </div>
                        <div class="col-md-6">
                            <label for="niragpdf_end_date" class="form-label">Data de sfârșit</label>
                            <input type="date" class="form-control" id="niragpdf_end_date" name="end_date">
                        </div>
                    </div>

                    <div class="row g-3 mt-3">
                        <div class="col-md-12">
                            <label class="form-label">Orientare pagină</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio"
                                       id="nir_agregat_pdf_landscape" name="nir_agregat_pdf_orientare"
                                       value="landscape" checked>
                                <label class="form-check-label" for="nir_agregat_pdf_landscape">Landscape (orizontal)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio"
                                       id="nir_agregat_pdf_portrait" name="nir_agregat_pdf_orientare"
                                       value="portrait">
                                <label class="form-check-label" for="nir_agregat_pdf_portrait">Portret (vertical)</label>
                            </div>
                        </div>
                    </div>

                    <small class="text-muted d-block mt-2">
                        Export în format PDF. Acest raport grupează toate cotele TVA per NIR.
                    </small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Închide</button>
                    <button type="submit" class="btn btn-outline-dark">Generează PDF</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Raport NIR agregat (Excel) (fără orientare, rămâne la fel) -->
<div class="modal fade" id="modalRaportNirAgregatExcel" tabindex="-1" aria-labelledby="modalRaportNirAgregatExcelLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="raport_nir_agregat_excel.php" method="POST" target="_blank">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalRaportNirAgregatExcelLabel">Raport NIR agregat (Excel)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Închide"></button>
                </div>
                <div class="modal-body">
                    <!-- Selectare rapidă An și Lună -->
                    <div class="row g-3 mb-2">
                        <div class="col-md-6">
                            <label for="niragxls_select_year" class="form-label"><b>Selectare rapidă: An</b></label>
                            <select id="niragxls_select_year" class="form-control">
                                <?php 
                                $currentYear = date('Y');
                                for($y = $currentYear; $y >= $currentYear - 5; $y--): ?>
                                  <option value="<?php echo $y; ?>" <?php echo ($y == $currentYear) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="niragxls_select_month" class="form-label"><b>Selectare rapidă: Lună</b></label>
                            <select id="niragxls_select_month" class="form-control">
                                <?php 
                                $currentMonth = date('n');
                                $months = ['Ianuarie', 'Februarie', 'Martie', 'Aprilie', 'Mai', 'Iunie', 
                                          'Iulie', 'August', 'Septembrie', 'Octombrie', 'Noiembrie', 'Decembrie'];
                                for($m = 1; $m <= 12; $m++): ?>
                                  <option value="<?php echo $m; ?>" <?php echo ($m == $currentMonth) ? 'selected' : ''; ?>><?php echo $months[$m-1]; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="niragxls_start_date" class="form-label">Data de început</label>
                            <input type="date" class="form-control" id="niragxls_start_date" name="start_date" required>
                        </div>
                        <div class="col-md-6">
                            <label for="niragxls_end_date" class="form-label">Data de sfârșit</label>
                            <input type="date" class="form-control" id="niragxls_end_date" name="end_date">
                        </div>
                    </div>
                    <small class="text-muted d-block mt-2">
                        Export în format Excel. Acest raport grupează toate cotele TVA per NIR.
                    </small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Închide</button>
                    <button type="submit" class="btn btn-dark">Generează Excel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="js/jquery-3.6.0.min.js"></script>
<script src="vendor/offline/bootstrap5/bootstrap.bundle.min.js"></script>
<script src="vendor/offline/datatables/jquery.dataTables.min.js"></script>
<script src="vendor/offline/datatables/dataTables.bootstrap5.min.js"></script>
<script src="vendor/offline/datatables/ro.json"></script>
<script src="vendor/offline/select2/select2.min.js"></script>

<script>
    const defaultStartDate = '<?= $data_start_luna ?>';
    const defaultEndDate   = '<?= $data_end_luna ?>';
    
    // Initialize Select2 on all form-control elements
    $(document).ready(function() {
        $('select.form-control').each(function() {
            var $modal = $(this).closest('.modal');
            $(this).select2({
                dropdownParent: $modal.length ? $modal : null,
                width: '100%'
            });
        });
    });

    $(function () {
        // DataTables
       $('#nirTable').DataTable({
        order: [[0, 'desc']],
        columnDefs: [
            { orderable: false, targets: 5 }
        ],
        language: {
            url: "vendor/offline/datatables/ro.json"
        },
        pageLength: 25,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Toate"]],
        stateSave: true,
        stateDuration: -1
    });

        // Prefill date în modale
        function prefillModalDates(modalId, startSelector, endSelector) {
            const modal = document.getElementById(modalId);
            if (!modal) return;
            modal.addEventListener('shown.bs.modal', function () {
                if (startSelector) $(startSelector).val(defaultStartDate);
                if (endSelector && $(endSelector).length) {
                    $(endSelector).val(defaultEndDate);
                }
            });
        }

        prefillModalDates('modalRaportNirPdf', '#nirpdf_start_date', '#nirpdf_end_date');
        prefillModalDates('modalRaportNirExcel', '#nirxls_start_date', '#nirxls_end_date');
        prefillModalDates('modalRaportNirAgregatPdf', '#niragpdf_start_date', '#niragpdf_end_date');
        prefillModalDates('modalRaportNirAgregatExcel', '#niragxls_start_date', '#niragxls_end_date');

        // Alegere script în funcție de orientare - NIR standard PDF
        const formStdPdf = document.getElementById('formRaportNirPdf');
        if (formStdPdf) {
            formStdPdf.addEventListener('submit', function () {
                const orient = document.querySelector('input[name="nir_pdf_orientare"]:checked')?.value || 'landscape';
                this.action = (orient === 'portrait')
                    ? 'raport_nir_standard_portrait.php'
                    : 'raport_nir_standard_landscape.php';
            });
        }

        // Alegere script în funcție de orientare - NIR agregat PDF
        const formAgrPdf = document.getElementById('formRaportNirAgregatPdf');
        if (formAgrPdf) {
            formAgrPdf.addEventListener('submit', function () {
                const orient = document.querySelector('input[name="nir_agregat_pdf_orientare"]:checked')?.value || 'landscape';
                this.action = (orient === 'portrait')
                    ? 'raport_nir_agregat_portrait.php'
                    : 'raport_nir_agregat_landscape.php';
            });
        }
    });
</script>

<!-- Script pentru selectare rapidă an/lună în rapoarte -->
<script src="js/raport-date-selector.js"></script>

</body>
</html>
