<?php
require_once __DIR__ . '/det_note_departament_listare_schema.php';
include('session.php'); // Conexiunea $pdo și session_start()

// [PĂSTRAT INTEGRAL] - Logica de validare sesiune și procesare formular
// ========================================================================
if (!isset($_SESSION['cod_locatie'], $_SESSION['admin_id'], $_SESSION['nr_bon'])) {
    header('Location: logout.php');
    exit;
}

ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.log');
error_reporting(E_ALL);

date_default_timezone_set("Europe/Bucharest");
$ora_bon = date("H:i:s");
$data_bon = date("Y-m-d");
$adm_id = $_SESSION['admin_id'];
$cod_locatie = $_SESSION['cod_locatie'];
$nr_bon_orig = $_SESSION['nr_bon'];
agecs_ensure_det_note_departament_listare($pdo);
$client_id = (int)($_SESSION['client_id'] ?? 0);
$admin_firstname = (string)($_SESSION['admin_firstname'] ?? 'Operator');
$admin_lastname = (string)($_SESSION['admin_lastname'] ?? '');
$operatorDisplay = trim($admin_firstname . ' ' . $admin_lastname);
if ($operatorDisplay === '') { $operatorDisplay = 'Operator'; }
$hide_discount = in_array($client_id, [25, 26], true);

function getPseudonimFirma(PDO $pdo): string {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $stmt = $pdo->query("SELECT pseudonim_firma FROM date_firma LIMIT 1");
    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    $cached = (string)($row['pseudonim_firma'] ?? '');
    return $cached;
}

function buildNotaInformativaContent(
    PDO $pdo,
    int $nrBon,
    string $operatorDisplay,
    bool $hideDiscount,
    int $clientId
): ?string {
    if ($nrBon <= 0) {
        return null;
    }

    $noteStmt = $pdo->prepare("
        SELECT
            n.nrbon,
            n.data_bon,
            n.ora_bon,
            n.cod_masa,
            COALESCE(NULLIF(m.nume_masa, ''), CONCAT('Masa ', n.cod_masa)) AS nume_masa
        FROM note n
        LEFT JOIN mese m
          ON m.cod_masa = n.cod_masa
         AND m.cod_locatie = n.locatie
        WHERE n.nrbon = :nrbon
        LIMIT 1
    ");
    $noteStmt->execute([':nrbon' => $nrBon]);
    $note = $noteStmt->fetch(PDO::FETCH_ASSOC);
    if (!$note) {
        return null;
    }

    $itemsStmt = $pdo->prepare("
        SELECT
            dn.cod_p,
            dn.observatie_produs,
            dn.cantitate,
            dn.pret_vanzare,
            dn.valoare_vanzare_cu_tva,
            COALESCE(NULLIF(ps.descriere, ''), ps.nume) AS descriere,
            ps.pret_cu_tva
        FROM det_note dn
        JOIN produse_servicii ps ON dn.cod_p = ps.cod_produs
        WHERE dn.nr_bon = :nrbon
          AND dn.pret_vanzare > 0
        ORDER BY dn.id_vanz ASC
    ");
    $itemsStmt->execute([':nrbon' => $nrBon]);
    $allProducts = $itemsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (empty($allProducts)) {
        return null;
    }

    $sumaDiscountTotal = 0.0;
    foreach ($allProducts as $prod) {
        $pretCuTva = (float)($prod['pret_cu_tva'] ?? 0);
        $pretVanzare = (float)($prod['pret_vanzare'] ?? 0);
        $cant = (float)($prod['cantitate'] ?? 0);
        if (abs($pretCuTva - $pretVanzare) > 0.0001) {
            $sumaDiscountTotal += ($pretCuTva - $pretVanzare) * $cant;
        }
    }

    $groupedProducts = [];
    foreach ($allProducts as $idx => $product) {
        $obs = trim((string)($product['observatie_produs'] ?? ''));
        $descriere = (string)($product['descriere'] ?? '');
        $key = ($obs === '') ? ('plain|' . $descriere) : ('obs|' . $idx);
        if (!isset($groupedProducts[$key])) {
            $groupedProducts[$key] = $product;
        } else {
            $groupedProducts[$key]['cantitate'] += $product['cantitate'];
            $groupedProducts[$key]['valoare_vanzare_cu_tva'] += $product['valoare_vanzare_cu_tva'];
        }
    }

    $separator = "-----\n";
    $nota = "NOTA DE PLATA\n";
    $nota .= getPseudonimFirma($pdo) . "\n";
    $nota .= ((string)$note['data_bon']) . " " . ((string)$note['ora_bon']) . "\n";
    $nota .= "OPERATOR: " . $operatorDisplay . "\n";
    $nota .= $separator;

    $totalNota = 0.0;
    foreach ($groupedProducts as $product) {
        $produs = (string)($product['descriere'] ?? '');
        if ($clientId === 9) {
            $produs .= " (" . number_format((float)$product['pret_vanzare'], 2) . ")";
        }

        $obs = trim((string)($product['observatie_produs'] ?? ''));
        $cant = round((float)($product['cantitate'] ?? 0), 2);
        $valoare = (float)($product['valoare_vanzare_cu_tva'] ?? 0);
        $totalNota += $valoare;

        $line = $cant . " x " . $produs;
        if ($obs !== '' && $clientId !== 9) {
            $line .= " " . $obs;
        }
        $line .= " = " . number_format($valoare, 2) . " LEI";
        $nota .= $line . "\n";
    }

    $nota .= "Nr. nota: " . $nrBon . "\n";
    $nota .= "Masa: " . ((string)($note['nume_masa'] ?? '')) . "\n";
    $nota .= $separator;

    if ($sumaDiscountTotal > 0 && !$hideDiscount) {
        $valoareFaraDiscount = $totalNota + $sumaDiscountTotal;
        $procentDiscount = ($valoareFaraDiscount > 0)
            ? ($sumaDiscountTotal / $valoareFaraDiscount) * 100
            : 0.0;

        $bacsisStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM det_note dn
            JOIN produse_servicii ps ON dn.cod_p = ps.cod_produs
            WHERE dn.nr_bon = :nrbon
              AND ps.nume LIKE '%BACSIS%'
        ");
        $bacsisStmt->execute([':nrbon' => $nrBon]);
        $cuBacsis = ((int)$bacsisStmt->fetchColumn() > 0) ? " +BACSIS " : "";

        $nota .= "Val fara discount {$cuBacsis}:" . number_format($valoareFaraDiscount, 2) . " LEI\n";
        $nota .= "Discount {$cuBacsis}:" . number_format($sumaDiscountTotal, 2) . " LEI\n";
        $nota .= "Discount procentual {$cuBacsis}:" . number_format($procentDiscount, 2) . " %\n";
    }

    $nota .= "TOTAL: " . number_format($totalNota, 2) . " LEI\n";

    $checkTipsStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM det_note
        WHERE nr_bon = :nrbon
          AND cod_p = -1
    ");
    $checkTipsStmt->execute([':nrbon' => $nrBon]);
    $hasTipsLine = ((int)$checkTipsStmt->fetchColumn() > 0);

    if (!$hasTipsLine) {
        $nota .= "\nBacsisul nu este inclus / Tips not included\n\n";
        if (!in_array($clientId, [23], true)) {
            $nota .= "Va oferim urmatoarele sugestii pentru calculul bacsisului / Please consider the following suggestions for tips calculation:\n\n";
        }
        $nota .= "Bacsis\tTotal nota\n";
        foreach ([10, 12, 15] as $pct) {
            $tip = round($totalNota * $pct / 100, 2);
            $totalTip = round($totalNota + $tip, 2);
            $nota .= $pct . "%\t" . number_format($tip, 2) . "\t" . number_format($totalTip, 2) . "\n";
        }
        $nota .= "\nAlta valoare: ...\n";
    }

    return $nota;
}

function queuePrinterJobsNoSleep(PDO $pdo, int $clientId, int $codLocatie, array $jobs): void {
    if (empty($jobs)) {
        return;
    }

    $folderPath = RESTAURANT_OFFLINE_API_DIR . "/" . $clientId . "/" . $codLocatie;
    if (!is_dir($folderPath)) {
        mkdir($folderPath, 0777, true);
    }
    $jsonPath = $folderPath . "/de_listat_la_imprimanta.json";

    $existingJobs = [];
    if (is_file($jsonPath)) {
        $raw = @file_get_contents($jsonPath);
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && isset($decoded['data']) && is_array($decoded['data'])) {
                $existingJobs = $decoded['data'];
            }
        }
    }

    $payload = [
        "status" => "success",
        "message" => "Note de plata generate din impartire nota.",
        "data" => array_merge($existingJobs, $jobs),
    ];

    file_put_contents(
        $jsonPath,
        json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['products_json']) && isset($_POST['masa_select'])) {
    $masaSelectata = $_POST['masa_select'];
    $productsData = json_decode($_POST['products_json'], true);

    if (empty($masaSelectata) || json_last_error() !== JSON_ERROR_NONE || empty($productsData['notaNoua'])) {
        $_SESSION['error_message'] = 'Eroare: Trebuie să selectezi o masă și să muți cel puțin un produs pe nota nouă.';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    try {
        agecs_snapshot_det_note_departamente($pdo, (int)$nr_bon_orig);
        $pdo->beginTransaction();

        $sqlNewNota = "INSERT INTO note (operator, locatie, cod_masa, data_bon, ora_bon, status,listat_nota_plata) VALUES (?, ?, ?, ?, ?, 'S',1)";
        $stmtNewNota = $pdo->prepare($sqlNewNota);
        $stmtNewNota->execute([$adm_id, $cod_locatie, $masaSelectata, $data_bon, $ora_bon]);
        $new_nr_bon = $pdo->lastInsertId();

        $stmtDepartamentSursa = $pdo->prepare(
            "SELECT departament_listare FROM det_note WHERE id_vanz = ? AND nr_bon = ? LIMIT 1"
        );

        foreach ($productsData['notaNoua'] as $prod) {
            $pret_unitar_cu_tva = $prod['pret_vanzare'];
            $cantitate = $prod['cantitate'];
            $cota_tva = $prod['cota_tva'];
            $valoare_vanzare_cu_tva = round($pret_unitar_cu_tva * $cantitate, 2);
            $tva_col = round($valoare_vanzare_cu_tva * $cota_tva / (100 + $cota_tva), 2);
            $valoare_vanzare = round($valoare_vanzare_cu_tva - $tva_col, 2);
            $stmtDepartamentSursa->execute([(int)$prod['id_vanz'], (int)$nr_bon_orig]);
            $departamentListare = $stmtDepartamentSursa->fetchColumn();
            $sqlInsertProd = "INSERT INTO det_note (nr_bon, cod_p, nume_produs, cantitate, cota_tva, tva_col, pret_vanzare, valoare_vanzare, valoare_vanzare_cu_tva, data, ora, cod_meniu, observatie_produs, departament_listare, t_list) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
            $stmtInsertProd = $pdo->prepare($sqlInsertProd);
            $stmtInsertProd->execute([$new_nr_bon, $prod['cod_p'], $prod['nume_produs'], $cantitate, $cota_tva, $tva_col, $pret_unitar_cu_tva, $valoare_vanzare, $valoare_vanzare_cu_tva, $data_bon, $ora_bon, $prod['cod_meniu'], $prod['observatie_produs'], $departamentListare ?: null]);
        }
        
        foreach ($productsData['notaOriginala'] as $prod) {
            $pret_unitar_cu_tva = $prod['pret_vanzare'];
            $cantitate_ramasa = $prod['cantitate'];
            $cota_tva = $prod['cota_tva'];
            $valoare_vanzare_cu_tva = round($pret_unitar_cu_tva * $cantitate_ramasa, 2);
            $tva_col = round($valoare_vanzare_cu_tva * $cota_tva / (100 + $cota_tva), 2);
            $valoare_vanzare = round($valoare_vanzare_cu_tva - $tva_col, 2);
            $sqlUpdateProd = "UPDATE det_note SET cantitate = ?, valoare_vanzare = ?, tva_col = ?, valoare_vanzare_cu_tva = ? WHERE id_vanz = ?";
            $stmtUpdateProd = $pdo->prepare($sqlUpdateProd);
            $stmtUpdateProd->execute([$cantitate_ramasa, $valoare_vanzare, $tva_col, $valoare_vanzare_cu_tva, $prod['id_vanz']]);
        }

        $sqlDeleteZero = "DELETE FROM det_note WHERE nr_bon = ? AND cantitate < 0.001";
        $stmtDelete = $pdo->prepare($sqlDeleteZero);
        $stmtDelete->execute([$nr_bon_orig]);

        $noteToUpdate = [$nr_bon_orig, $new_nr_bon];
        foreach ($noteToUpdate as $bon) {
            $sqlRecalc = "UPDATE note n SET n.valoare_vanzare_cu_tva = (SELECT COALESCE(SUM(d.valoare_vanzare_cu_tva), 0) FROM det_note d WHERE d.nr_bon = n.nrbon), n.tva_colectata = (SELECT COALESCE(SUM(d.tva_col), 0) FROM det_note d WHERE d.nr_bon = n.nrbon), n.discount = (SELECT COALESCE(SUM(d.discount), 0) FROM det_note d WHERE d.nr_bon = n.nrbon) WHERE n.nrbon = ?";
            $stmtRecalc = $pdo->prepare($sqlRecalc);
            $stmtRecalc->execute([$bon]);
        }

        $stmtListat = $pdo->prepare("UPDATE note SET listat_nota_plata = 1 WHERE nrbon = ?");
        foreach ($noteToUpdate as $bon) {
            $stmtListat->execute([$bon]);
        }

        $sqlUpdateMasa = "UPDATE mese SET stare = 1 WHERE cod_masa = ?";
        $stmtUpdateMasa = $pdo->prepare($sqlUpdateMasa);
        $stmtUpdateMasa->execute([$masaSelectata]);
        
        $pdo->commit();

        $printJobs = [];
        $currentDate = date('Y-m-d');
        $currentTime = date('H:i:s');

        $contentNew = buildNotaInformativaContent($pdo, (int)$new_nr_bon, $operatorDisplay, $hide_discount, $client_id);
        if ($contentNew !== null) {
            $printJobs[] = [
                'data' => $currentDate,
                'ora' => $currentTime,
                'de_trimis_la_imprimanta' => 1,
                'nrbon' => (int)$new_nr_bon,
                'locatie' => (int)$cod_locatie,
                'departament_listare' => 'BAR',
                'continut' => $contentNew,
            ];
        }

        $contentOld = buildNotaInformativaContent($pdo, (int)$nr_bon_orig, $operatorDisplay, $hide_discount, $client_id);
        if ($contentOld !== null) {
            $printJobs[] = [
                'data' => $currentDate,
                'ora' => $currentTime,
                'de_trimis_la_imprimanta' => 1,
                'nrbon' => (int)$nr_bon_orig,
                'locatie' => (int)$cod_locatie,
                'departament_listare' => 'BAR',
                'continut' => $contentOld,
            ];
        }

        queuePrinterJobsNoSleep($pdo, $client_id, (int)$cod_locatie, $printJobs);

        header('Location: vanzare_restaurant.php');
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error_message'] = "A apărut o eroare în timpul procesării: " . $e->getMessage();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}
// ========================================================================

// [PĂSTRAT INTEGRAL] - Logica de afișare
// ========================================================================
$sqlProd = "SELECT id_vanz, cod_p, nume_produs, cantitate, pret_vanzare, cota_tva, cod_meniu, observatie_produs FROM det_note WHERE nr_bon = ?";
$stmtProd = $pdo->prepare($sqlProd);
$stmtProd->execute([$nr_bon_orig]);
$produseNota = $stmtProd->fetchAll(PDO::FETCH_ASSOC);

$sqlMese = "SELECT cod_masa, nume_masa FROM mese WHERE categorie_masa = 'TEMPORARA' AND stare = 0 AND cod_locatie = ? ORDER BY nume_masa ASC";
$stmtMese = $pdo->prepare($sqlMese);
$stmtMese->execute([$cod_locatie]);
$meseDisponibile = $stmtMese->fetchAll(PDO::FETCH_ASSOC);
// ========================================================================
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Împărțire Notă Complexă</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .product-list { min-height: 300px; background-color: #fff; border: 1px solid #ddd; border-radius: 5px; padding: 10px; }
        .product-item { display: flex; justify-content: space-between; align-items: center; padding: 8px; border: 1px solid #eee; margin-bottom: 5px; border-radius: 4px; }
        .product-item:hover { background-color: #f0f0f0; }
        .product-info { flex-grow: 1; }
        .product-actions button { margin-left: 5px; }
        .list-header { font-weight: bold; margin-bottom: 15px; border-bottom: 2px solid #007bff; padding-bottom: 10px;}
        .total-footer { font-weight: bold; margin-top: 15px; text-align: right; font-size: 1.2em;}

        /* Stiluri pentru tastatura numerică */
        #numpadDisplay {
            font-size: 2rem;
            text-align: right;
            padding: 10px;
            background-color: #e9ecef;
            border-radius: 5px;
            margin-bottom: 15px;
            height: 60px;
        }
        .numpad-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }
        .numpad-btn {
            font-size: 1.5rem;
            padding: 20px;
        }
    </style>
</head>
<body>

<div class="container mt-4">
    <h2>Împărțire Notă de Plată (Bon Nr: <?php echo htmlspecialchars($nr_bon_orig); ?>)</h2>
    <a href='logout.php' style="color:black; margin-left: 20px;">Deconectare</a>
    <p>Mutați produsele dorite din nota originală în nota nouă. Puteți ajusta cantitățile.</p>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger">
            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
        </div>
    <?php endif; ?>

    <form id="splitForm" method="POST" action="">
        <div class="row">
            <div class="col-md-6">
                <div class="list-header">Nota Originală</div>
                <div id="notaOriginala" class="product-list"></div>
                <div id="totalOriginal" class="total-footer">Total: 0.00 RON</div>
            </div>
            <div class="col-md-6">
                <div class="list-header">Nota Nouă</div>
                <div id="notaNoua" class="product-list"></div>
                <div id="totalNou" class="total-footer">Total: 0.00 RON</div>
            </div>
        </div>
        <div class="row mt-4">
            <div class="col-md-6">
                <label for="masaSelect"><strong>Alege masa de destinație pentru nota nouă:</strong></label>
                <select name="masa_select" id="masaSelect" class="form-control" required>
                    <option value="">-- Alege o masă temporară --</option>
                    <?php foreach ($meseDisponibile as $masa): ?>
                        <option value="<?php echo htmlspecialchars($masa['cod_masa']); ?>"><?php echo htmlspecialchars($masa['nume_masa']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6 d-flex align-items-end justify-content-end">
                <button type="submit" class="btn btn-primary btn-lg">Confirmă Împărțirea</button>
            </div>
        </div>
        <input type="hidden" name="products_json" id="products_json">
    </form>
</div>

<div class="modal fade" id="numpadModal" tabindex="-1" role="dialog" aria-labelledby="numpadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="numpadModalLabel">Introduceți Cantitatea</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="numpadDisplay">1</div>
                <div class="numpad-grid">
                    <button type="button" class="btn btn-light numpad-btn" data-value="1">1</button>
                    <button type="button" class="btn btn-light numpad-btn" data-value="2">2</button>
                    <button type="button" class="btn btn-light numpad-btn" data-value="3">3</button>
                    <button type="button" class="btn btn-light numpad-btn" data-value="4">4</button>
                    <button type="button" class="btn btn-light numpad-btn" data-value="5">5</button>
                    <button type="button" class="btn btn-light numpad-btn" data-value="6">6</button>
                    <button type="button" class="btn btn-light numpad-btn" data-value="7">7</button>
                    <button type="button" class="btn btn-light numpad-btn" data-value="8">8</button>
                    <button type="button" class="btn btn-light numpad-btn" data-value="9">9</button>
                    <button type="button" class="btn btn-light numpad-btn" data-value=".">.</button>
                    <button type="button" class="btn btn-light numpad-btn" data-value="0">0</button>
                    <button type="button" class="btn btn-warning numpad-btn" data-value="backspace">&larr;</button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Anulează</button>
                <button type="button" class="btn btn-primary" id="numpadConfirm">Confirmă</button>
            </div>
        </div>
    </div>
</div>


<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    let initialProducts = <?php echo json_encode($produseNota); ?>.map((p, index) => ({
        ...p,
        uid: `prod_${index}`,
        cantitate: parseFloat(p.cantitate),
        pret_vanzare: parseFloat(p.pret_vanzare)
    }));
    
    let state = {
        notaOriginala: [...initialProducts],
        notaNoua: []
    };

    function render() {
        $('#notaOriginala').empty();
        $('#notaNoua').empty();
        let totalOriginal = 0;
        let totalNou = 0;

        state.notaOriginala.forEach(p => {
            if (p.cantitate > 0.001) { 
                const valoare = p.cantitate * p.pret_vanzare;
                totalOriginal += valoare;
                $('#notaOriginala').append(createProductHtml(p, 'original'));
            }
        });

        state.notaNoua.forEach(p => {
            if (p.cantitate > 0.001) {
                const valoare = p.cantitate * p.pret_vanzare;
                totalNou += valoare;
                $('#notaNoua').append(createProductHtml(p, 'nou'));
            }
        });

        $('#totalOriginal').text(`Total: ${totalOriginal.toFixed(2)} RON`);
        $('#totalNou').text(`Total: ${totalNou.toFixed(2)} RON`);
    }

    function createProductHtml(product, type) {
        const arrowButton = type === 'original'
            ? `<button type="button" class="btn btn-sm btn-success" onclick="openNumpad('${product.uid}', 'toNew')">&rarr;</button>`
            : `<button type="button" class="btn btn-sm btn-warning" onclick="openNumpad('${product.uid}', 'toOriginal')">&larr;</button>`;
        
        return `
            <div class="product-item" id="${product.uid}">
                <div class="product-info">
                    <strong>${product.nume_produs}</strong><br>
                    <small>Cantitate: ${product.cantitate.toFixed(2)} / Preț unitar: ${product.pret_vanzare.toFixed(2)} RON</small>
                </div>
                <div class="product-actions">${arrowButton}</div>
            </div>
        `;
    }

    // Funcția care deschide modalul cu tastatura
    window.openNumpad = function(uid, direction) {
        // Stocăm contextul (ce produs și ce direcție) în atributul data al modalului
        $('#numpadModal').data('uid', uid).data('direction', direction);
        $('#numpadDisplay').text('1'); // Resetăm afișajul la 1
        $('#numpadModal').modal('show');
    };

    // Logica pentru butoanele tastaturii
    $('.numpad-btn').on('click', function() {
        const value = $(this).data('value');
        let currentDisplay = $('#numpadDisplay').text();

        if (value === 'backspace') {
            currentDisplay = currentDisplay.slice(0, -1);
            if (currentDisplay === '') {
                currentDisplay = '0';
            }
        } else if (value === '.') {
            if (!currentDisplay.includes('.')) {
                currentDisplay += '.';
            }
        } else {
            if (currentDisplay === '0') {
                currentDisplay = value;
            } else {
                currentDisplay += value;
            }
        }
        $('#numpadDisplay').text(currentDisplay);
    });

    // Logica pentru butonul de confirmare
    $('#numpadConfirm').on('click', function() {
        const cantitateStr = $('#numpadDisplay').text();
        const cantitateDeMutat = parseFloat(cantitateStr);

        // Preluăm contextul salvat
        const uid = $('#numpadModal').data('uid');
        const direction = $('#numpadModal').data('direction');

        if (isNaN(cantitateDeMutat) || cantitateDeMutat <= 0) {
            alert("Cantitate invalidă.");
            return;
        }
        
        // Aici începe logica de mutare efectivă (preluată din funcția veche)
        let sourceProduct, targetProduct, sourceList, targetList;
        
        if (direction === 'toNew') {
            sourceList = state.notaOriginala;
            targetList = state.notaNoua;
        } else {
            sourceList = state.notaNoua;
            targetList = state.notaOriginala;
        }

        sourceProduct = sourceList.find(p => p.uid === uid);
        
        if (cantitateDeMutat > sourceProduct.cantitate + 0.001) {
            alert("Nu puteți muta o cantitate mai mare decât cea existentă.");
            return;
        }

        sourceProduct.cantitate -= cantitateDeMutat;
        targetProduct = targetList.find(p => p.id_vanz === sourceProduct.id_vanz);

        if (targetProduct) {
            targetProduct.cantitate += cantitateDeMutat;
        } else {
            targetList.push({ ...sourceProduct, cantitate: cantitateDeMutat });
        }
        
        $('#numpadModal').modal('hide');
        render(); // Re-desenează interfața după modificare
    });
    
    // Logica de submit a formularului principal
    $('#splitForm').on('submit', function(e) {
        if (state.notaNoua.filter(p => p.cantitate > 0.001).length === 0) {
            alert("Trebuie să mutați cel puțin un produs pe nota nouă.");
            e.preventDefault();
            return;
        }
        if (!$('#masaSelect').val()) {
            alert("Vă rugăm să alegeți o masă de destinație.");
            e.preventDefault();
            return;
        }
        const finalState = {
            notaOriginala: state.notaOriginala,
            notaNoua: state.notaNoua.filter(p => p.cantitate > 0.001)
        };
        $('#products_json').val(JSON.stringify(finalState));
    });

    render();
});
</script>

</body>
</html>
