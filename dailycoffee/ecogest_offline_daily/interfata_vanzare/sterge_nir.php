<?php
// sterge_nir.php

$title = 'Șterge NIR';
include 'session.php';

// Construim query string-ul pentru filtre (dacă există)
$filterArray = [];
if (isset($_GET['furnizor'])) {
    $filterArray['furnizor'] = $_GET['furnizor'];
}
if (isset($_GET['din_data'])) {
    $filterArray['din_data'] = $_GET['din_data'];
}
if (isset($_GET['pana_data'])) {
    $filterArray['pana_data'] = $_GET['pana_data'];
}
$filterQuery = !empty($filterArray) ? '?' . http_build_query($filterArray) : '';

// Obține numărul NIR din parametrul GET
if (isset($_GET['nr_nir'])) {
    $nr_nir = $_GET['nr_nir'];
} else {
    echo '<script>alert("Număr NIR invalid."); window.location.href = "note_receptie.php' . $filterQuery . '";</script>';
    exit;
}

// Preluăm datele NIR-ului
$sql_nir = "SELECT * FROM nir WHERE nr_nir = :nr_nir";
$stmt_nir = $pdo->prepare($sql_nir);
$stmt_nir->execute(['nr_nir' => $nr_nir]);
$nir_data = $stmt_nir->fetch(PDO::FETCH_ASSOC);

if (!$nir_data) {
    echo '<script>alert("NIR nu există."); window.location.href = "note_receptie.php' . $filterQuery . '";</script>';
    exit;
}

// Prelucrarea formularului
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['yes'])) {
        // Începem tranzacția
        $pdo->beginTransaction();

        try {
            // Dacă NIR-ul este finalizat, inversăm stocul și ștergem mișcările
                // Preluăm toate produsele din NIR
                $sql_products = "SELECT * FROM achizitii WHERE nr_nir = :nr_nir";
                $stmt_products = $pdo->prepare($sql_products);
                $stmt_products->execute(['nr_nir' => $nr_nir]);
                $products = $stmt_products->fetchAll(PDO::FETCH_ASSOC);

                foreach ($products as $product) {
                    $cod_p = $product['cod_p'];
                    // Ștergem înregistrările din tabelul de mișcări
                    $sql_delete_miscari = "DELETE FROM miscari WHERE fel_doc = 'NIR' AND nr_doc = :nr_nir AND cod_p = :cod_p";
                    $stmt_delete_miscari = $pdo->prepare($sql_delete_miscari);
                    $stmt_delete_miscari->execute(['nr_nir' => $nr_nir, 'cod_p' => $cod_p]);
                }
            

            // Ștergem produsele din tabelul de achiziții
            $sql_delete_achizitii = "DELETE FROM achizitii WHERE nr_nir = :nr_nir";
            $stmt_delete_achizitii = $pdo->prepare($sql_delete_achizitii);
            $stmt_delete_achizitii->execute(['nr_nir' => $nr_nir]);

            // Ștergem înregistrarea din tabelul NIR
            $sql_delete_nir = "DELETE FROM nir WHERE nr_nir = :nr_nir";
            $stmt_delete_nir = $pdo->prepare($sql_delete_nir);
            $stmt_delete_nir->execute(['nr_nir' => $nr_nir]);

            // Commit la tranzacție
            $pdo->commit();

            // Afișează mesajul de succes și redirecționează, păstrând filtrele
            echo '<script>alert("NIR a fost șters cu succes!"); window.location.href = "note_receptie.php' . $filterQuery . '";</script>';
            exit;

        } catch (Exception $e) {
            // Rollback la tranzacție în caz de eroare
            $pdo->rollBack();
            echo '<script>alert("Eroare la ștergerea NIR: ' . addslashes($e->getMessage()) . '"); window.location.href = "note_receptie.php' . $filterQuery . '";</script>';
            exit;
        }

    } elseif (isset($_POST['no'])) {
        // Redirecționează utilizatorul la pagina note_receptie.php cu filtrele păstrate
        echo '<script>window.location.href = "note_receptie.php' . $filterQuery . '";</script>';
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title><?php echo $title; ?></title>
    <!-- Include Bootstrap CSS -->
    <link href="vendor/offline/bootstrap5/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container">
    <h3 class="text-center">Șterge NIR - <?php echo $nr_nir; ?></h3>
    <div class="alert alert-warning" role="alert">
        Sunteți sigur că doriți să ștergeți NIR-ul cu numărul <strong><?php echo $nr_nir; ?></strong>?
    </div>
    <form method="post">
        <button type="submit" name="yes" class="btn btn-danger">Da, șterge</button>
        <button type="submit" name="no" class="btn btn-secondary">Nu, anulează</button>
    </form>
</div>
</body>
</html>