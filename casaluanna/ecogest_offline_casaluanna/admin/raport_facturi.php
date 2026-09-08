<?php
// Pornim sesiunea și setăm timpul maxim de execuție (opțional)
if (session_status() != PHP_SESSION_ACTIVE) {
    session_start();
}
set_time_limit(300);

// Funcție pentru a elimina diacriticele românești
function remove_diacritics($text) {
    $diacritics = array(
        'ă', 'Ă', 'â', 'Â', 'î', 'Î', 'ș', 'Ș', 'ț', 'Ț'
    );
    $replacements = array(
        'a', 'A', 'a', 'A', 'i', 'I', 's', 'S', 't', 'T'
    );
    return str_replace($diacritics, $replacements, $text);
}


// Verificăm dacă s-au trimis datele prin POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_date'])) {
    // Începem output buffering pentru a trimite direct PDF-ul
    ob_start();

    // Preluăm datele din formular
    $start_date = $_POST['start_date'];
    $end_date   = !empty($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d');

    // Include conexiunea la baza de date
    require_once __DIR__.'/database_connection.php';
    if (!isset($pdo)) {
        echo "<h1>Eroare: Conexiunea la baza de date nu a fost stabilită. Intrați din nou în aplicație Reconectați-vă.</h1>";
        exit;
    }

    // Preluăm informațiile firmei (dacă există tabelă cu date despre firmă)
    $firma_sql  = "SELECT * FROM casa_online_company";
    $firma_stmt = $pdo->prepare($firma_sql);
    $firma_stmt->execute();
    $firma      = $firma_stmt->fetch(PDO::FETCH_ASSOC);

    /*
     * Interogare pentru facturile emise, agregând sumele din tabela vanzari.
     * Am adăugat condiția pentru a ignora facturile cu nr_factura = 0.
     */
    $sql = "
        SELECT 
            f.nume,
            f.cod_fiscal,
            f.adresa,
            f.serie_factura,
            f.nr_factura,
            f.data_factura,
            f.data_scadenta,
            SUM(v.valoare_vanzare) AS total_fara_tva,
            SUM(v.tva_col) AS total_tva,
            SUM(v.valoare_vanzare_cu_tva) AS total_cu_tva
        FROM facturi f
        LEFT JOIN vanzari v ON f.id_factura = v.id_factura
        WHERE f.data_factura BETWEEN :start_date AND :end_date AND f.nr_factura != 0
        GROUP BY 
            f.id_factura, -- Gruparea după cheia primară este suficientă
            f.nume,
            f.cod_fiscal,
            f.adresa,
            f.serie_factura,
            f.nr_factura,
            f.data_factura,
            f.data_scadenta
        ORDER BY f.data_factura ASC, f.nr_factura ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':start_date', $start_date, PDO::PARAM_STR);
    $stmt->bindParam(':end_date', $end_date, PDO::PARAM_STR);
    $stmt->execute();
    $raport = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Variabile pentru totaluri
    $total_fara_tva = 0;
    $total_tva      = 0;
    $total_cu_tva   = 0;

    // Include biblioteca FPDF (asigură-te că ai folderul fpdf186 și fișierul fpdf.php)
    require('fpdf186/fpdf.php');

    // Clasa extinsă FPDF cu metode pentru text wrapping în rânduri
    class PDF extends FPDF {
        public $firma;
        public $start_date;
        public $end_date;
        var $widths;
        var $aligns;
        
        // Setează lățimile celulelor
        function SetWidths($w) {
            $this->widths = $w;
        }
        
        // Setează aliniamentele celulelor
        function SetAligns($a) {
            $this->aligns = $a;
        }
        
        // Desenează un rând cu posibilitate de wrapping pentru celule
        function Row($data) {
            // Calculează numărul maxim de linii necesare pentru celule
            $nb = 0;
            for ($i = 0; $i < count($data); $i++)
                $nb = max($nb, $this->NbLines($this->widths[$i], $data[$i]));
            $lineHeight = 5;
            $h = $lineHeight * $nb;
            // Verifică dacă este nevoie de page break
            $this->CheckPageBreak($h);
            // Desenează fiecare celulă
            for ($i = 0; $i < count($data); $i++) {
                $w = $this->widths[$i];
                $a = isset($this->aligns[$i]) ? $this->aligns[$i] : 'L';
                $x = $this->GetX();
                $y = $this->GetY();
                // Desenează chenarul
                $this->Rect($x, $y, $w, $h);
                // Scrie textul cu wrap
                $this->MultiCell($w, $lineHeight, $data[$i], 0, $a);
                // Setează poziția pentru următoarea celulă
                $this->SetXY($x + $w, $y);
            }
            // Trecem la rândul următor
            $this->Ln($h);
        }
        
        // Verifică dacă rândul înalt poate fi adăugat pe pagină
        function CheckPageBreak($h) {
            if($this->GetY() + $h > $this->PageBreakTrigger)
                $this->AddPage($this->CurOrientation);
        }
        
        // Calculează numărul de linii necesare pentru un text într-o celulă de lățime $w
        function NbLines($w, $txt) {
            $cw = &$this->CurrentFont['cw'];
            if ($w == 0)
                $w = $this->w - $this->rMargin - $this->x;
            $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
            $s = str_replace("\r", '', $txt);
            $nb = strlen($s);
            if($nb > 0 and $s[$nb-1] == "\n")
                $nb--;
            $sep = -1;
            $i = 0;
            $j = 0;
            $l = 0;
            $nl = 1;
            while($i < $nb) {
                $c = $s[$i];
                if($c == "\n") {
                    $i++;
                    $sep = -1;
                    $j = $i;
                    $l = 0;
                    $nl++;
                    continue;
                }
                if($c == ' ')
                    $sep = $i;
                $l += $cw[$c];
                if($l > $wmax) {
                    if($sep == -1) {
                        if($i == $j)
                            $i++;
                    } else {
                        $i = $sep + 1;
                    }
                    $sep = -1;
                    $j = $i;
                    $l = 0;
                    $nl++;
                } else {
                    $i++;
                }
            }
            return $nl;
        }
        
        // Header-ul paginii
        function Header() {
            if (!empty($this->firma)) {
                $this->SetFont('Arial', 'B', 12);
                $this->Cell(0, 10, remove_diacritics($this->firma['den_ent']), 0, 1, 'C');
            }
            $this->SetFont('Arial', 'B', 14);
            $this->Cell(0, 10, 'Raport de Facturi Emise', 0, 1, 'C');
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 6, 'Perioada: ' . date('d.m.Y', strtotime($this->start_date)) . ' - ' . date('d.m.Y', strtotime($this->end_date)), 0, 1, 'C');
            $this->Ln(5);

            // Afișăm antetul tabelului conform noului format
            $this->SetFont('Arial', 'B', 9);
            $header = array('Client', 'CIF', 'Adresa', 'Factura', 'Data emiterii', 'Data scadenta', 'Valoare fara TVA', 'TVA', 'Valoare totala');
            
            // Desenăm antetul fără wrapping, cu o înălțime fixă
            for ($i = 0; $i < count($header); $i++) {
                $this->Cell($this->widths[$i], 8, remove_diacritics($header[$i]), 1, 0, 'C');
            }
            $this->Ln();
        }
        
        // Footer-ul paginii
        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, 'Pagina ' . $this->PageNo(), 0, 0, 'C');
        }
    }
    
    // Instanțiem obiectul PDF cu orientare landscape (vedere)
    $pdf = new PDF('L', 'mm', 'A4');
    $pdf->firma = $firma;
    $pdf->start_date = $start_date;
    $pdf->end_date = $end_date;
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(true, 20);

    // Setăm lățimile și aliniamentele coloanelor pentru noul format
    // Lățime totală A4 landscape ~297mm. Margini 10+10=20mm. Rămas: 277mm.
    // Suma lățimilor: 45+25+60+25+22+22+25+20+28 = 272 mm
    $pdf->SetWidths(array(45, 25, 60, 25, 22, 22, 25, 20, 28));
    $pdf->SetAligns(array('L', 'L', 'L', 'C', 'C', 'C', 'R', 'R', 'R'));
    
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 8);
    
    foreach ($raport as $row) {
        $doc = $row['serie_factura'] . ' ' . $row['nr_factura'];
        $data_fact = date('d.m.Y', strtotime($row['data_factura']));
        $data_scad = !empty($row['data_scadenta']) ? date('d.m.Y', strtotime($row['data_scadenta'])) : '-';
        
        $valoare_fara_tva = $row['total_fara_tva'] ?? 0;
        $valoare_tva = $row['total_tva'] ?? 0;
        $valoare_cu_tva = $row['total_cu_tva'] ?? 0;
        
        // Adunăm totalurile
        $total_fara_tva += $valoare_fara_tva;
        $total_tva      += $valoare_tva;
        $total_cu_tva   += $valoare_cu_tva;
        
        $rowData = array(
            remove_diacritics($row['nume']),
            $row['cod_fiscal'],
            remove_diacritics($row['adresa']),
            $doc,
            $data_fact,
            $data_scad,
            number_format($valoare_fara_tva, 2, ',', '.'),
            number_format($valoare_tva, 2, ',', '.'),
            number_format($valoare_cu_tva, 2, ',', '.')
        );
        
        $pdf->Row($rowData);
    }
    
    // Rândul de totaluri
    $pdf->SetFont('Arial', 'B', 8);
    $totalRow = array(
        '', '', '', '', '', 'TOTAL GENERAL',
        number_format($total_fara_tva, 2, ',', '.'),
        number_format($total_tva, 2, ',', '.'),
        number_format($total_cu_tva, 2, ',', '.')
    );
    $pdf->Row($totalRow);
    
    // Generăm PDF-ul
    $pdf->Output('D', 'Raport_Facturi_'.date('Y-m-d').'.pdf');
    ob_end_flush(); 
    exit;
}
?>
