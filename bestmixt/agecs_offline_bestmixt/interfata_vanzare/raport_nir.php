<?php
// raport_nir.php

if (session_status() != PHP_SESSION_ACTIVE) {
    session_start();
}
set_time_limit(300);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['start_date'])) {
    die("Acces invalid sau lipsesc datele necesare.");
}

require('fpdf181/fpdf.php');
include 'db.php';

function remove_diacritics($text) {
    $diacritics = ['ă', 'Ă', 'â', 'Â', 'î', 'Î', 'ș', 'Ș', 'ț', 'Ț'];
    $replacements = ['a', 'A', 'a', 'A', 'i', 'I', 's', 'S', 't', 'T'];
    return str_replace($diacritics, $replacements, $text);
}

// Preluăm datele din formular
$start_date = $_POST['start_date'];
$end_date   = !empty($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d');
$cota_tva   = null;

// MODIFICAT: Verificare corectă pentru a include cota TVA 0
if (isset($_POST['cota_tva']) && $_POST['cota_tva'] !== '') {
    $cota_tva = (int)$_POST['cota_tva'];
}

if (!isset($pdo)) {
    die("<h1>Eroare: Conexiunea la baza de date nu a fost stabilită. Reconectați-vă.</h1>");
}

$firma_sql  = "SELECT * FROM date_firma";
$firma_stmt = $pdo->prepare($firma_sql);
$firma_stmt->execute();
$firma      = $firma_stmt->fetch(PDO::FETCH_ASSOC);

$sql_where_tva = "";
$params = [
    ':start_date' => $start_date,
    ':end_date'   => $end_date,
];
if ($cota_tva !== null) {
    $sql_where_tva = " AND a.cota_tva = :cota_tva";
    $params[':cota_tva'] = $cota_tva;
}

$sql = "
    SELECT 
        f.nume AS furnizor_nume, f.cod_fiscal AS furnizor_cif, f.adresa AS furnizor_adresa,
        n.serie_doc_int, n.nr_doc_int, n.data_doc_int, n.data_nir, a.cota_tva,
        SUM(a.valoare_achizitie) AS total_valoare_fara_tva, SUM(a.valoare_tva_achizitie) AS total_tva,
        SUM(a.valoare_achizitie_cu_tva) AS total_valoare_factura, SUM(a.valoare_pret_amanunt) AS total_valoare_cu_adaos
    FROM nir n
    JOIN furnizori f ON n.cod_tert = f.id_furnizor
    LEFT JOIN achizitii a ON n.nr_nir = a.nr_nir
    WHERE n.data_nir BETWEEN :start_date AND :end_date AND a.cota_tva IS NOT NULL
    {$sql_where_tva}
    GROUP BY n.id_nir, a.cota_tva
    ORDER BY n.data_nir ASC, n.id_nir ASC, a.cota_tva ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$raport = $stmt->fetchAll(PDO::FETCH_ASSOC);

class PDF extends FPDF {
    public $firma;
    public $start_date;
    public $end_date;
    public $cota_tva;
    var $widths;
    var $aligns;

    function SetWidths($w) { $this->widths = $w; }
    function SetAligns($a) { $this->aligns = $a; }

    function Header() {
        if (!empty($this->firma)) {
            $this->SetFont('Arial', 'B', 12);
            $this->Cell(0, 10, remove_diacritics($this->firma['den_ent']), 0, 1, 'C');
        }
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, 'Raport Note de Receptie (NIR)', 0, 1, 'C');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 6, 'Perioada: ' . date('d.m.Y', strtotime($this->start_date)) . ' - ' . date('d.m.Y', strtotime($this->end_date)), 0, 1, 'C');
        if ($this->cota_tva !== null) {
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(0, 6, 'Filtrat pentru Cota TVA: ' . $this->cota_tva . '%', 0, 1, 'C');
        }
        $this->Ln(5);

        $this->SetFont('Arial', 'B', 8);
        $header = ['Furnizor', 'CIF', 'Adresa', 'Factura', 'Data emiterii', 'Data NIR', 'Cota TVA', 'Val. fara TVA', 'TVA', 'Total factura', 'Val. cu adaos'];
        for ($i = 0; $i < count($header); $i++) {
            $this->Cell($this->widths[$i], 8, $header[$i], 1, 0, 'C');
        }
        $this->Ln();
    }
    
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Pagina ' . $this->PageNo(), 0, 0, 'C');
    }

    function Row($data) {
        $nb = 0;
        for ($i = 0; $i < count($data); $i++) $nb = max($nb, $this->NbLines($this->widths[$i], $data[$i]));
        $h = 5 * $nb;
        $this->CheckPageBreak($h);
        for ($i = 0; $i < count($data); $i++) {
            $w = $this->widths[$i];
            $a = isset($this->aligns[$i]) ? $this->aligns[$i] : 'L';
            $x = $this->GetX(); $y = $this->GetY();
            $this->Rect($x, $y, $w, $h);
            $this->MultiCell($w, 5, $data[$i], 0, $a);
            $this->SetXY($x + $w, $y);
        }
        $this->Ln($h);
    }

    function CheckPageBreak($h) { if($this->GetY() + $h > $this->PageBreakTrigger) $this->AddPage($this->CurOrientation); }

    function NbLines($w, $txt) {
        $cw = &$this->CurrentFont['cw']; if ($w == 0) $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize; $s = str_replace("\r", '', $txt);
        $nb = strlen($s); if ($nb > 0 && $s[$nb - 1] == "\n") $nb--; $sep = -1; $i = 0; $j = 0; $l = 0; $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") { $i++; $sep = -1; $j = $i; $l = 0; $nl++; continue; }
            if ($c == ' ') $sep = $i; $l += $cw[$c];
            if ($l > $wmax) {
                if ($sep == -1) { if ($i == $j) $i++; } else $i = $sep + 1;
                $sep = -1; $j = $i; $l = 0; $nl++;
            } else $i++;
        } return $nl;
    }
}

// Inițializare PDF
$pdf = new PDF('L', 'mm', 'A4');
$pdf->firma = $firma;
$pdf->start_date = $start_date;
$pdf->end_date = $end_date;
$pdf->cota_tva = $cota_tva;
$pdf->SetMargins(5, 10, 5);
$pdf->SetAutoPageBreak(true, 20);
$pdf->SetWidths([40, 22, 45, 25, 20, 20, 15, 25, 20, 25, 25]);
$pdf->SetAligns(['L', 'L', 'L', 'C', 'C', 'C', 'C', 'R', 'R', 'R', 'R']);
$pdf->AddPage();
$pdf->SetFont('Arial', '', 7);

$total_fara_tva = 0; $total_tva = 0; $total_factura = 0; $total_cu_adaos = 0;

foreach ($raport as $row) {
    $pdf->Row([
        remove_diacritics($row['furnizor_nume']), $row['furnizor_cif'], remove_diacritics($row['furnizor_adresa']),
        $row['serie_doc_int'] . ' ' . $row['nr_doc_int'], date('d.m.Y', strtotime($row['data_doc_int'])),
        date('d.m.Y', strtotime($row['data_nir'])), $row['cota_tva'] . '%',
        number_format($row['total_valoare_fara_tva'], 2, ',', '.'), number_format($row['total_tva'], 2, ',', '.'),
        number_format($row['total_valoare_factura'], 2, ',', '.'), number_format($row['total_valoare_cu_adaos'], 2, ',', '.')
    ]);
    
    $total_fara_tva += $row['total_valoare_fara_tva']; $total_tva += $row['total_tva'];
    $total_factura += $row['total_valoare_factura']; $total_cu_adaos += $row['total_valoare_cu_adaos'];
}

$pdf->SetFont('Arial', 'B', 8);
$pdf->Row([
    '', '', '', '', '', '', 'TOTAL',
    number_format($total_fara_tva, 2, ',', '.'), number_format($total_tva, 2, ',', '.'),
    number_format($total_factura, 2, ',', '.'), number_format($total_cu_adaos, 2, ',', '.')
]);

// NOU: Construim numele dinamic al fișierului
$filename_parts = ['Raport_NIR', 'de_la_' . $start_date, 'pana_la_' . $end_date];
if ($cota_tva !== null) {
    $filename_parts[] = 'TVA_' . $cota_tva;
}
$filename = implode('_', $filename_parts) . '.pdf';

// MODIFICAT: Trimitem PDF-ul către browser pentru preview (inline) cu numele dinamic
$pdf->Output('I', $filename);
exit;
?>