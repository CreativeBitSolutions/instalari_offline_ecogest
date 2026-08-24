<?php
// config_firma.php

$title = 'Date firma';
include 'session.php';

$message = '';
$messageType = 'success';

function company_form_value($row, $key)
{
    return htmlspecialchars((string)($row[$key] ?? ''), ENT_QUOTES, 'UTF-8');
}

$fields = [
    'den_ent',
    'conducator_entitate',
    'cod_fiscal',
    'nr_reg_com',
    'sediu',
    'judet',
    'banca',
    'cont_banca',
    'cap_soc',
    'serie_casa_marcat',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_company'])) {
    $values = [];
    foreach ($fields as $field) {
        $values[$field] = trim((string)($_POST[$field] ?? ''));
    }

    try {
        $countStmt = $pdo->query("SELECT COUNT(*) FROM $tabel_final_date_firma");
        $exists = (int)$countStmt->fetchColumn() > 0;

        if ($exists) {
            $stmt = $pdo->prepare("
                UPDATE $tabel_final_date_firma
                SET den_ent = :den_ent,
                    conducator_entitate = :conducator_entitate,
                    cod_fiscal = :cod_fiscal,
                    nr_reg_com = :nr_reg_com,
                    sediu = :sediu,
                    judet = :judet,
                    banca = :banca,
                    cont_banca = :cont_banca,
                    cap_soc = :cap_soc,
                    serie_casa_marcat = :serie_casa_marcat
            ");
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO $tabel_final_date_firma
                    (den_ent, conducator_entitate, cod_fiscal, nr_reg_com, sediu, judet, banca, cont_banca, cap_soc, serie_casa_marcat)
                VALUES
                    (:den_ent, :conducator_entitate, :cod_fiscal, :nr_reg_com, :sediu, :judet, :banca, :cont_banca, :cap_soc, :serie_casa_marcat)
            ");
        }

        $stmt->execute($values);
        $message = 'Datele firmei au fost salvate.';
    } catch (Throwable $e) {
        $messageType = 'danger';
        $message = 'Datele firmei nu au putut fi salvate.';
        error_log('Eroare config_firma.php: ' . $e->getMessage());
    }
}

$company = [];
try {
    $stmt = $pdo->query("SELECT * FROM $tabel_final_date_firma LIMIT 1");
    $company = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $messageType = 'danger';
    $message = 'Datele firmei nu au putut fi incarcate.';
    error_log('Eroare incarcare date firma: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>

    <link href="vendor/offline/bootstrap5/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: #f3f4f6;
            color: #111827;
        }
        .page-shell {
            max-width: 1120px;
            margin: 0 auto;
            padding: 24px 16px 40px;
        }
        .top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 16px;
        }
        .top-bar h3 {
            margin: 0;
            font-weight: 700;
        }
        .form-card {
            background: #fff;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
        }
        .form-card-header {
            padding: 16px 18px;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
        }
        .form-card-header p {
            margin: 4px 0 0;
            color: #6b7280;
        }
        .form-card-body {
            padding: 18px;
        }
        label {
            font-weight: 600;
        }
        .actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 10px;
        }
        @media (max-width: 720px) {
            .top-bar,
            .actions {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
<main class="page-shell">
    <div class="top-bar">
        <div>
            <h3>Date firma</h3>
            <div class="text-muted">Configurare manuala pentru datele afisate pe documente.</div>
        </div>
        <a class="btn btn-secondary" href="vanzare_magazin.php">Inapoi la vanzare</a>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?php echo htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <section class="form-card">
        <div class="form-card-header">
            <strong>Date identificare firma</strong>
            <p>Campurile se completeaza manual. Nu exista completare automata dupa CUI.</p>
        </div>
        <div class="form-card-body">
            <form action="config_firma.php" method="POST" autocomplete="off">
                <div class="row g-3">
                    <div class="col-lg-8">
                        <label for="den_ent" class="form-label">Denumire firma</label>
                        <input class="form-control" id="den_ent" type="text" name="den_ent" value="<?php echo company_form_value($company, 'den_ent'); ?>">
                    </div>
                    <div class="col-lg-4">
                        <label for="cod_fiscal" class="form-label">CUI/CIF</label>
                        <input class="form-control" id="cod_fiscal" type="text" name="cod_fiscal" value="<?php echo company_form_value($company, 'cod_fiscal'); ?>">
                    </div>

                    <div class="col-md-6">
                        <label for="nr_reg_com" class="form-label">Nr. Registrul Comertului</label>
                        <input class="form-control" id="nr_reg_com" type="text" name="nr_reg_com" value="<?php echo company_form_value($company, 'nr_reg_com'); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="conducator_entitate" class="form-label">Conducator entitate</label>
                        <input class="form-control" id="conducator_entitate" type="text" name="conducator_entitate" value="<?php echo company_form_value($company, 'conducator_entitate'); ?>">
                    </div>

                    <div class="col-12">
                        <label for="sediu" class="form-label">Sediu</label>
                        <input class="form-control" id="sediu" type="text" name="sediu" value="<?php echo company_form_value($company, 'sediu'); ?>">
                    </div>

                    <div class="col-md-4">
                        <label for="judet" class="form-label">Judet</label>
                        <input class="form-control" id="judet" type="text" name="judet" value="<?php echo company_form_value($company, 'judet'); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="banca" class="form-label">Banca</label>
                        <input class="form-control" id="banca" type="text" name="banca" value="<?php echo company_form_value($company, 'banca'); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="cont_banca" class="form-label">Cont banca</label>
                        <input class="form-control" id="cont_banca" type="text" name="cont_banca" value="<?php echo company_form_value($company, 'cont_banca'); ?>">
                    </div>

                    <div class="col-md-6">
                        <label for="cap_soc" class="form-label">Capital social</label>
                        <input class="form-control" id="cap_soc" type="text" name="cap_soc" value="<?php echo company_form_value($company, 'cap_soc'); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="serie_casa_marcat" class="form-label">Serie casa de marcat</label>
                        <input class="form-control" id="serie_casa_marcat" type="text" name="serie_casa_marcat" value="<?php echo company_form_value($company, 'serie_casa_marcat'); ?>">
                    </div>
                </div>

                <div class="actions">
                    <a class="btn btn-outline-secondary" href="vanzare_magazin.php">Renunta</a>
                    <button class="btn btn-primary" type="submit" name="save_company" value="1">Salveaza datele firmei</button>
                </div>
            </form>
        </div>
    </section>
</main>
</body>
</html>
