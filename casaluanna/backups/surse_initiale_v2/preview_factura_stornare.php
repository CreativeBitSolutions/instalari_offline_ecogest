<?php
include('header.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include('database_connection.php');

function h($value) {
    if (is_array($value) || is_object($value)) {
        return htmlspecialchars(json_encode($value, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
    }
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function getNextNrFactura(PDO $pdo, string $serie_factura): int {
    $sql = "SELECT MAX(nr_factura) AS max_nr FROM facturi WHERE serie_factura = :serie_factura";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':serie_factura' => $serie_factura]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result && $result['max_nr'] !== null) {
        return (int)$result['max_nr'] + 1;
    }

    $sqlStart = "SELECT nr_inceput FROM serii_documente WHERE serie = :serie_factura LIMIT 1";
    $stmtStart = $pdo->prepare($sqlStart);
    $stmtStart->execute([':serie_factura' => $serie_factura]);
    $rowStart = $stmtStart->fetch(PDO::FETCH_ASSOC);

    if (!$rowStart) {
        throw new Exception("Seria facturii nu există în serii_documente.");
    }

    return (int)$rowStart['nr_inceput'];
}

function renderKeyValueTable(array $row) {
    echo '<div class="table-responsive">';
    echo '<table class="table table-bordered table-striped">';
    echo '<thead class="thead-dark"><tr><th style="width:30%">Câmp</th><th>Valoare</th></tr></thead><tbody>';
    foreach ($row as $key => $value) {
        echo '<tr>';
        echo '<td><strong>' . h($key) . '</strong></td>';
        echo '<td>' . h($value) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '</div>';
}

function renderRowsTable(array $rows, string $emptyMessage = 'Nu există înregistrări.') {
    if (empty($rows)) {
        echo '<div class="alert alert-info mb-0">' . h($emptyMessage) . '</div>';
        return;
    }

    $headers = array_keys($rows[0]);

    echo '<div class="table-responsive">';
    echo '<table class="table table-bordered table-hover table-striped table-sm">';
    echo '<thead class="thead-dark"><tr>';
    foreach ($headers as $header) {
        echo '<th>' . h($header) . '</th>';
    }
    echo '</tr></thead><tbody>';

    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($headers as $header) {
            $value = $row[$header] ?? '';
            echo '<td>' . h($value) . '</td>';
        }
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
}

$id_factura = isset($_GET['id_factura']) ? (int)$_GET['id_factura'] : 0;

if ($id_factura <= 0) {
    echo '<div class="container-fluid"><div class="alert alert-danger">ID factură invalid.</div></div>';
    include('footer.php');
    exit;
}

try {
    $stmtFactura = $pdo->prepare("SELECT * FROM facturi WHERE id_factura = :id_factura LIMIT 1");
    $stmtFactura->execute([':id_factura' => $id_factura]);
    $factura = $stmtFactura->fetch(PDO::FETCH_ASSOC);

    if (!$factura) {
        throw new Exception("Factura nu a fost găsită.");
    }

    $stmtVanzari = $pdo->prepare("SELECT * FROM vanzari WHERE id_factura = :id_factura ORDER BY id_vanz ASC");
    $stmtVanzari->execute([':id_factura' => $id_factura]);
    $vanzari = $stmtVanzari->fetchAll(PDO::FETCH_ASSOC);

    $miscari = [];
    if (!empty($vanzari)) {
        $idsVanzari = array_column($vanzari, 'id_vanz');
        $placeholders = implode(',', array_fill(0, count($idsVanzari), '?'));

        $sqlMiscari = "SELECT * FROM miscari WHERE id_vanz_fact IN ($placeholders) ORDER BY id ASC";
        $stmtMiscari = $pdo->prepare($sqlMiscari);
        $stmtMiscari->execute($idsVanzari);
        $miscari = $stmtMiscari->fetchAll(PDO::FETCH_ASSOC);
    }

    $nextNrFactura = getNextNrFactura($pdo, (string)$factura['serie_factura']);

    $totalVanzari = count($vanzari);
    $totalMiscari = count($miscari);
    $totalFacturaCuTva = 0;

    foreach ($vanzari as $v) {
        $totalFacturaCuTva += (float)($v['valoare_vanzare_cu_tva'] ?? 0);
    }

} catch (Throwable $e) {
    echo '<div class="container-fluid"><div class="alert alert-danger">' . h($e->getMessage()) . '</div></div>';
    include('footer.php');
    exit;
}
?>

<div class="container-fluid">
    <?php if (isset($_GET['created']) && $_GET['created'] == '1'): ?>
        <div class="alert alert-success">
            Factura de stornare a fost generată cu succes.
            <?php if (!empty($_GET['source_id'])): ?>
                Factura sursă: <strong>#<?= h($_GET['source_id']) ?></strong>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($_GET['error'])): ?>
        <div class="alert alert-danger">
            <?= h($_GET['error']) ?>
        </div>
    <?php endif; ?>

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            Vizualizare factură #<?= h($factura['id_factura']) ?>
        </h1>
        <a href="factura.php?id_factura=<?= urlencode($id_factura) ?>" class="btn btn-secondary">
            Înapoi la factură
        </a>
    </div>

    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Serie</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= h($factura['serie_factura'] ?? '') ?></div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Număr curent</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= h($factura['nr_factura'] ?? '') ?></div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Număr stornare</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= h($nextNrFactura) ?></div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total cu TVA</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($totalFacturaCuTva, 2) ?> Lei</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4 border-left-danger">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-danger">Generează factura de stornare</h6>
        </div>
        <div class="card-body">
            <p>
                Se va crea o factură nouă cu:
                <strong>tip_factura = 384</strong>,
                aceeași serie,
                <strong>număr nou = <?= h($nextNrFactura) ?></strong>,
                iar liniile din <strong>vanzari</strong> și <strong>miscari</strong> vor fi copiate cu semn inversat la cantități și sume.
            </p>

            <form method="post" action="genereaza_factura_stornare.php" onsubmit="return confirm('Sigur vrei să generezi factura de stornare?');">
                <input type="hidden" name="id_factura" value="<?= h($id_factura) ?>">
                <button type="submit" class="btn btn-danger" <?= empty($vanzari) ? 'disabled' : '' ?>>
                    Generează factura de stornare
                </button>
            </form>

            <?php if (empty($vanzari)): ?>
                <div class="alert alert-warning mt-3 mb-0">
                    Factura nu are linii în tabelul vanzari, deci butonul este dezactivat.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-primary text-white">
            <h6 class="m-0 font-weight-bold">Tabela facturi</h6>
        </div>
        <div class="card-body">
            <?php renderKeyValueTable($factura); ?>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-success text-white d-flex justify-content-between">
            <h6 class="m-0 font-weight-bold">Tabela vanzari</h6>
            <span>Total rânduri: <?= h($totalVanzari) ?></span>
        </div>
        <div class="card-body">
            <?php renderRowsTable($vanzari, 'Nu există linii în vanzari pentru această factură.'); ?>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-info text-white d-flex justify-content-between">
            <h6 class="m-0 font-weight-bold">Tabela miscari</h6>
            <span>Total rânduri: <?= h($totalMiscari) ?></span>
        </div>
        <div class="card-body">
            <?php renderRowsTable($miscari, 'Nu există mișcări legate de liniile acestei facturi.'); ?>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>