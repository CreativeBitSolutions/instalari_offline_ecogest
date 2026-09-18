<?php
// genereaza_etichete_nir.php
// Generează etichete pentru toate produsele dintr-un NIR, folosind aceeași logică ca "etichete pentru selecție"
// + LOGICA NOUĂ: afișare preț informativ /kg sau /L pe baza infopret_kg, nume produs și UM

session_start();

include 'db.php';
require_once __DIR__ . '/tcpdf_bootstrap.php';

// 1) Validare input
$nr_nir = $_GET['nr_nir'] ?? null;
if ($nr_nir === null || $nr_nir === '') {
    die("Lipsește parametrul nr_nir.");
}

// 2) Construim lista de coduri produs din achizitii (distinct) care există în produse_servicii
try {
    $sql_coduri = "
        SELECT DISTINCT a.cod_p AS cod_produs
        FROM achizitii a
        JOIN produse_servicii ps ON ps.cod_produs = a.cod_p
        WHERE a.nr_nir = :nr_nir
          AND a.cod_p IS NOT NULL
    ";
    $stmt_coduri = $pdo->prepare($sql_coduri);
    $stmt_coduri->execute([':nr_nir' => $nr_nir]);
    $coduri_produse = array_map(
        fn($r) => (int)$r['cod_produs'],
        $stmt_coduri->fetchAll(PDO::FETCH_ASSOC)
    );
} catch (PDOException $e) {
    die("Eroare DB la preluarea produselor din NIR: " . htmlspecialchars($e->getMessage()));
}

if (empty($coduri_produse)) {
    die("NIR-ul selectat nu conține produse cu corespondent în produse_servicii.");
}

// 3) Preluăm informațiile firmei o singură dată
try {
    $firma_stmt = $pdo->query("SELECT den_ent FROM date_firma");
    $firma = $firma_stmt->fetch(PDO::FETCH_ASSOC);
    $firmaText = $firma['den_ent'] ?? 'Nume Firma';
} catch (PDOException $e) {
    $firmaText = 'Nume Firma';
}

// 4) Inițializăm PDF-ul (A4) — IDENTIC cu generatorul existent
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Sistem Gestiune');
$pdf->SetTitle('Etichete NIR ' . $nr_nir);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(5, 10, 5);
$pdf->SetAutoPageBreak(TRUE, 10);

$pdf->AddPage();

// Layout 18 etichete / pagină (3x6) — IDENTIC
$labelWidth  = 65;
$labelHeight = 38;
$hSpacing    = 5;
$vSpacing    = 5;
$cols        = 3;
$rows        = 6;

$x_start = $pdf->GetX();
$y_start = $pdf->GetY();
$col = 0;
$row = 0;
$labelCounter = 0;

// Statement pentru produs
$sql_prod = "SELECT cod_produs, nume, cod_bare, pret_cu_tva, um
             FROM produse_servicii
             WHERE cod_produs = :cod_produs
             LIMIT 1";
$stmt_prod = $pdo->prepare($sql_prod);

/**
 * FUNCȚIE NOUĂ (identică cu scriptul de selecție):
 * determină textul pentru preț informativ /kg sau /L pe baza infopret_kg + regex pe denumire + UM
 */
function getInfoPriceText($product) {
    if (!isset($product['infopret_kg'])) {
        return '';
    }

    $value = (float)$product['infopret_kg'];
    if ($value <= 0) {
        return '';
    }

    $name = strtoupper($product['nume'] ?? '');
    $um   = strtoupper(trim($product['um'] ?? ''));

    $unit = null;

    // Din denumire: 500G, 200 GR, 1KG → /KG
    if (preg_match('/\b\d+(?:[.,]\d+)?\s*(KG|KILOG?RAM(E|I)?|G|GR|GRAM(E|I)?)\b/i', $name)) {
        $unit = 'KG';
    }
    // Din denumire: 500ML, 1L, 1.5L → /L
    elseif (preg_match('/\b\d+(?:[.,]\d+)?\s*(ML|M?L?L?I?LITR?U?|L|LITR(I|U)?)\b/i', $name)) {
        $unit = 'L';
    }
    // Dacă nu e în nume, încercăm după UM
    elseif (in_array($um, ['KG', 'G', 'GR'], true)) {
        $unit = 'KG';
    } elseif (in_array($um, ['L', 'ML'], true)) {
        $unit = 'L';
    }

    // Fallback: dacă nu detectăm nimic, afișăm simplu ca /KG
    $suffix = $unit ?? 'KG';

    return 'Pret: ' . number_format($value, 2, ',', '.') . ' RON/' . $suffix;
}

// Funcția de desen — bazată pe generatorul existent + info price ca în scriptul de selecție
function drawLabel($pdf, $product, $firmaText, $x, $y, $width, $height) {
    $pdf->startTransaction();

    $textLineHeight = 4;
    $barcodeHeight  = 15;

    $cod_bare      = !empty($product['cod_bare']) ? $product['cod_bare'] : $product['cod_produs'];
    $nameText      = $product['nume'];
    $pretText      = "Pret: " . number_format((float)$product['pret_cu_tva'], 2) . " RON";
    $infoPretText  = getInfoPriceText($product); // NOU: preț informativ /kg sau /L, dacă există

    $pdf->SetXY($x, $y);
    $pdf->SetFont('helvetica', 'B', 7);
    $pdf->MultiCell($width, $textLineHeight, $firmaText, 0, 'C', 0, 1, $x, $y, true);

    $currentY = $pdf->GetY();
    $pdf->SetFont('helvetica', 'B', 9);
    if ($pdf->getNumLines($nameText, $width) > 2) {
        $pdf->SetFont('helvetica', 'B', 8);
    }
    $pdf->MultiCell(
        $width,
        $textLineHeight * 2,
        $nameText,
        0,
        'C',
        0,
        1,
        $x,
        $currentY,
        true,
        0,
        false,
        true,
        $textLineHeight * 2,
        'M'
    );

    $currentY = $pdf->GetY();
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->MultiCell($width, $textLineHeight, $pretText, 0, 'C', 0, 1, $x, $currentY, true);
    $currentY = $pdf->GetY();

    // Linie nouă: preț informativ /kg sau /L (dacă avem)
    if ($infoPretText !== '') {
        $pdf->SetFont('helvetica', '', 8);
        $pdf->MultiCell($width, $textLineHeight, $infoPretText, 0, 'C', 0, 1, $x, $currentY, true);
        $currentY = $pdf->GetY();
    }

    // Mic spațiu înainte de codul de bare
    $currentY += 1;

    $barcodeStyle = [
        'align' => 'C',
        'stretch' => false,
        'fitwidth' => true,
        'border' => false,
        'hpadding' => 'auto',
        'vpadding' => 'auto',
        'fgcolor' => [0,0,0],
        'bgcolor' => false,
        'text' => true,
        'font' => 'helvetica',
        'fontsize' => 7,
        'stretchtext' => 4
    ];
    $pdf->write1DBarcode($cod_bare, 'C128', $x, $currentY, $width, $barcodeHeight, 0.4, $barcodeStyle, 'N');

    $pdf->Rect(
        $x, $y, $width, $height, 'D',
        ['all' => ['width' => 0.1, 'cap' => 'butt', 'join' => 'miter', 'dash' => '1,2', 'color' => [150,150,150]]]
    );

    $pdf->commitTransaction();
}

// 5) Loop pe coduri — IDENTIC (paginare 18/foaie)
foreach ($coduri_produse as $cod_produs) {
    if ($labelCounter > 0 && $labelCounter % ($cols * $rows) == 0) {
        $pdf->AddPage();
        $x_start = $pdf->GetX();
        $y_start = $pdf->GetY();
        $row = 0;
        $col = 0;
    }

    $current_x = $x_start + $col * ($labelWidth + $hSpacing);
    $current_y = $y_start + $row * ($labelHeight + $vSpacing);

    $stmt_prod->execute([':cod_produs' => (int)$cod_produs]);
    $product = $stmt_prod->fetch(PDO::FETCH_ASSOC);

    if ($product) {
        drawLabel($pdf, $product, $firmaText, $current_x, $current_y, $labelWidth, $labelHeight);
        $labelCounter++;
    }

    $col++;
    if ($col >= $cols) {
        $col = 0;
        $row++;
    }
}

// 6) Output — IDENTIC
$pdf->Output('etichete_nir_' . preg_replace('/[^0-9A-Za-z_-]/', '', (string)$nr_nir) . '.pdf', 'I');
