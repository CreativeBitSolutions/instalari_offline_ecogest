<?php
include('header.php');
if(isset($_GET['id_factura'])) { $cid=(int)$_GET['id_factura'];$cm=casa_one($pdo,'SELECT state FROM casa_invoices WHERE id_factura=?',[$cid]); if(!$cm || $cm['state']!=='draft'||casa_invoice_readonly($pdo,$cid)||casa_invoice_anaf_locked($pdo,$cid)){ echo '<script>location.href="detalii_factura.php?id_factura='.$cid.'"</script>'; exit; } }

// Start the session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Include database connection
require_once __DIR__.'/database_connection.php';
require_once __DIR__ . '/country_utils.php';
require_once __DIR__ . '/anaf_xml_artifacts.php';
$disable_preia_pret_achizitie = isset($_SESSION['client_id']) && intval($_SESSION['client_id']) === 1005;
$metode_plata = [
    "1" => "Instrument nedefinit",
    "2" => "Plată prin sistem de compensare automată (credit)",
    "3" => "Debit prin sistem de compensare automată",
    "4" => "Reversare debit cerere ACH",
    "5" => "Reversare credit cerere ACH",
    "6" => "Credit cerere ACH",
    "7" => "Debit cerere ACH",
    "8" => "Blocat",
    "9" => "Compensare națională sau regională",
    "10" => "Numerar",
    "11" => "Reversare credit economii ACH",
    "12" => "Reversare debit economii ACH",
    "13" => "Credit economii ACH",
    "14" => "Debit economii ACH",
    "15" => "Credit transfer carte de cont",
    "16" => "Debit transfer carte de cont",
    "17" => "Credit cerere CCD prin concentrarea/distribuirea de numerar",
    "18" => "Debit cerere CCD prin concentrarea/distribuirea de numerar",
    "19" => "Credit CTP tranzacție corporativă ACH",
    "20" => "Cec",
    "21" => "Ordin bancar - Bilet la ordin",
    "22" => "Ordin bancar certificat",
    "23" => "Cec bancar (emis de o instituție bancară sau similară)",
    "24" => "Bilet la ordin în așteptarea acceptării",
    "25" => "Cec certificat",
    "26" => "Cec local",
    "27" => "Debit CTP tranzacție corporativă ACH",
    "28" => "Credit CTX tranzacție corporativă ACH",
    "29" => "Debit CTX tranzacție corporativă ACH",
    "30" => "Transfer de credit",
    "31" => "Transfer de debit",
    "32" => "Credit CCD+ prin cerere ACH",
    "33" => "Debit CCD+ prin cerere ACH",
    "34" => "Plată și depunere prearanjate ACH (PPD)",
    "35" => "Credit economii CCD prin concentrarea/distribuirea de numerar",
    "36" => "Debit economii CCD prin concentrarea/distribuirea de numerar",
    "37" => "Credit CTP economii tranzacție corporativă ACH",
    "38" => "Debit CTP economii tranzacție corporativă ACH",
    "39" => "Credit CTX economii tranzacție corporativă ACH",
    "40" => "Debit CTX economii tranzacție corporativă ACH",
    "41" => "Credit economii CCD+ prin cerere ACH",
    "42" => "Plată în cont bancar",
    "43" => "Debit economii CCD+ prin cerere ACH",
    "44" => "Bilet la ordin acceptat",
    "45" => "Transfer de credit home-banking referențiat",
    "46" => "Transfer de debit interbancar",
    "47" => "Transfer de debit home-banking",
    "48" => "Card bancar",
    "49" => "Debit direct",
    "50" => "Plată prin postgiro",
    "51" => "FR, normă 6 97-Telereglement CFONB (Organizația Franceză pentru Standarde Bancare) - Opțiunea A",
    "52" => "Plată comercială urgentă",
    "53" => "Plată urgentă de Trezorerie",
    "54" => "Card de credit",
    "55" => "Card de debit",
    "56" => "Bankgiro",
    "57" => "Acord permanent",
    "58" => "Transfer de credit SEPA",
    "59" => "Debit direct SEPA",
    "60" => "Poliță de plată",
    "61" => "Poliță de plată semnată de debitor",
    "62" => "Poliță de plată semnată de debitor și garantată de bancă",
    "63" => "Poliță de plată semnată de debitor și garantată de o terță parte",
    "64" => "Poliță de plată semnată de bancă",
    "65" => "Poliță de plată semnată de bancă și garantată de o altă bancă",
    "66" => "Poliță de plată semnată de o terță parte",
    "67" => "Poliță de plată semnată de o terță parte și garantată de bancă",
    "68" => "Serviciu de plată online",
    "70" => "Bilet tras de creditor asupra debitorului",
    "74" => "Bilet tras de creditor asupra unei bănci",
    "75" => "Bilet tras de creditor, garantat de o altă bancă",
    "76" => "Bilet tras de creditor asupra unei bănci și garantat de o terță parte",
    "77" => "Bilet tras de creditor asupra unei terțe părți",
    "78" => "Bilet tras de creditor asupra unei terțe părți, acceptat și garantat de bancă",
    "91" => "Ordin bancar netransferabil",
    "92" => "Cec local netransferabil",
    "93" => "Referință giro",
    "94" => "Giro urgent",
    "95" => "Giro format liber",
    "96" => "Metoda solicitată pentru plată nu a fost utilizată",
    "97" => "Compensare între parteneri",
    "ZZZ" => "Definit mutual"
];

// Initialize variables
$factura = null;
$id_factura = null;
$nr_factura = null;
$serie_factura = null;


// Verificăm dacă variabila GET 'id_factura' este setată
if (!isset($_GET['id_factura'])) {
        unset($_SESSION['completed_steps']);

    // Verificăm dacă 'tip_factura' este setat și egal cu 751
    if (isset($_GET['tip_factura']) && intval($_GET['tip_factura']) === 751) {
        // Situația 1a: 'tip_factura' este 751

        // Insertăm o nouă factură cu tip_factura=751
        $insert_sql = "INSERT INTO facturi (nr_factura, tip_factura, serie_factura) VALUES (0, 751, '')";
        $stmt = $pdo->prepare($insert_sql);
        
        $stmt->execute();
        // Obținem ultimul id_factura inserat
        $id_factura = $pdo->lastInsertId();
        
        // Stocăm id_factura în sesiune
        $_SESSION['id_factura'] = $id_factura;

        // Actualizăm tabelul 'vanzari', setând id_factura la noul id unde id_factura este 0
        $update_sql = "UPDATE vanzari SET id_factura = :id_factura WHERE id_factura = 0";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->bindParam(':id_factura', $id_factura, PDO::PARAM_INT);
        $update_stmt->execute();


        //recalculare vanzari ca mno foxpro

        $select_recalc_sql = "SELECT id_vanz, pret_vanzare, cantitate, discount, cota_tva 
        FROM vanzari 
        WHERE id_factura = :id_factura";
$select_recalc_stmt = $pdo->prepare($select_recalc_sql);
$select_recalc_stmt->bindParam(':id_factura', $id_factura, PDO::PARAM_INT);
$select_recalc_stmt->execute();
$vanzari_recalc = $select_recalc_stmt->fetchAll(PDO::FETCH_ASSOC);

$update_sql_recalc_vanz = "UPDATE vanzari 
        SET 
            valoare_vanzare = :valoare_vanzare,
            tva_col = :tva_col,
            valoare_vanzare_cu_tva = :valoare_vanzare_cu_tva
        WHERE id_vanz = :id_vanz";
$update_stmt = $pdo->prepare($update_sql_recalc_vanz);

foreach ($vanzari_recalc as $vanzare_recalc) {
$id_vanz = $vanzare_recalc['id_vanz'];
$pret_vanzare = floatval($vanzare_recalc['pret_vanzare']);
$cantitate = floatval($vanzare_recalc['cantitate']);
$discount = floatval($vanzare_recalc['discount']);
$cota_tva = floatval($vanzare_recalc['cota_tva']);

// Fortam rotunjirea la 2 zecimale pentru simetria stornarilor (+ vs -)
$valoare_vanzare_cu_tva = round(($pret_vanzare * $cantitate) - $discount, 2);
$tva_col = round($valoare_vanzare_cu_tva - ($valoare_vanzare_cu_tva / (1 + $cota_tva / 100.0)), 2);
// Deoarece primele doua sunt exact la 2 zecimale, scaderea va fi si ea perfecta
$valoare_vanzare_fara_tva = $valoare_vanzare_cu_tva - $tva_col;

$update_stmt->bindParam(':valoare_vanzare', $valoare_vanzare_fara_tva);
$update_stmt->bindParam(':tva_col', $tva_col);
$update_stmt->bindParam(':valoare_vanzare_cu_tva', $valoare_vanzare_cu_tva);
$update_stmt->bindParam(':id_vanz', $id_vanz, PDO::PARAM_INT);

try {
 $update_stmt->execute();
} catch (PDOException $e) {
 error_log("Eroare la recalcularea vanzarii ID $id_vanz: " . $e->getMessage());
}
}


  // Codul pentru actualizarea 'um' în 'vanzari'
  try {
    // Definim interogarea SQL pentru actualizarea 'um'
    $update_um_sql = "
        UPDATE vanzari SET um = COALESCE((SELECT um FROM produse_servicii WHERE produse_servicii.nume=vanzari.den_p LIMIT 1),um) WHERE id_factura=:id_factura
    ";
    $update_um_stmt = $pdo->prepare($update_um_sql);
    $update_um_stmt->bindParam(':id_factura', $id_factura, PDO::PARAM_INT);
    $update_um_stmt->execute();
} catch (PDOException $e) {
    error_log("Eroare la actualizarea 'um' în vanzari pentru factura ID $id_factura: " . $e->getMessage());
    // Opțional, puteți afișa un mesaj de eroare utilizatorului
    // echo "<script>alert('A apărut o eroare la actualizarea unităților de măsură.');</script>";
}
// **Sfârșitul codului adăugat pentru actualizarea 'um'**

        // Obținem factura inserată
        $f_sql = "SELECT * FROM facturi WHERE id_factura = :id_factura";
        $f_stmt = $pdo->prepare($f_sql);
        $f_stmt->bindParam(':id_factura', $id_factura, PDO::PARAM_INT);
        $f_stmt->execute();
        $factura = $f_stmt->fetch(PDO::FETCH_ASSOC);

        // Setăm variabilele din factura
        $nr_factura = $factura['nr_factura'];
        $serie_factura = $factura['serie_factura'];
        $data_incarcare = $factura['data_incarcare'];
        // Redirectăm la aceeași pagină cu parametrul GET 'id_factura'
        echo "<script>window.location.href='factura.php?id_factura=$id_factura';</script>";
        exit;

    } else {
        // Situația 1b: 'tip_factura' nu este 751 sau nu este setat

        // Insertăm o nouă factură cu tip_factura=380
        $insert_sql = "INSERT INTO facturi (nr_factura, tip_factura, serie_factura) VALUES (0, 380, '')";
        $stmt = $pdo->prepare($insert_sql);
        $stmt->execute();
        
        // Obținem ultimul id_factura inserat
        $id_factura = $pdo->lastInsertId();
        
        // Stocăm id_factura în sesiune
        $_SESSION['id_factura'] = $id_factura;

        // Obținem factura inserată
        $f_sql = "SELECT * FROM facturi WHERE id_factura = :id_factura";
        $f_stmt = $pdo->prepare($f_sql);
        $f_stmt->bindParam(':id_factura', $id_factura, PDO::PARAM_INT);
        $f_stmt->execute();
        $factura = $f_stmt->fetch(PDO::FETCH_ASSOC);

        // Setăm variabilele din factura
        $nr_factura = $factura['nr_factura'];
        $serie_factura = $factura['serie_factura'];
        $data_incarcare = $factura['data_incarcare'];

        // Redirectăm la aceeași pagină cu parametrul GET 'id_factura'
        echo "<script>window.location.href='factura.php?id_factura=$id_factura';</script>";
        exit;
    }

} else {
    // Situația 2: 'id_factura' este setată, completăm automat câmpurile

    $id_factura = intval($_GET['id_factura']);

    // Obținem factura din tabel pe baza id_factura
    $f_sql = "SELECT * FROM facturi WHERE id_factura = :id_factura";
    $f_stmt = $pdo->prepare($f_sql);
    $f_stmt->bindParam(':id_factura', $id_factura, PDO::PARAM_INT);
    $f_stmt->execute();
    $factura = $f_stmt->fetch(PDO::FETCH_ASSOC);

    if ($factura) {
        $nr_factura = $factura['nr_factura'];
        $serie_factura = $factura['serie_factura'];
        $tipul_facturii=$factura['tip_factura'];

        // Stocăm în sesiune
        $_SESSION['id_factura'] = $id_factura;
        $_SESSION['nr_factura'] = $nr_factura;
        $_SESSION['serie_factura'] = $serie_factura;
    } else {
        // Gestionăm cazul în care factura nu există
        echo "<script>alert('Factura nu a fost găsită.');</script>";
        exit;
    }
}




// Continuăm cu restul scriptului
if ($factura) {
    // Atribuim variabilele din 'facturi'
    foreach ($factura as $key => $value) {
        $$key = $value;
    }
    $is_tip_384 = ($tip_factura == 384);

    $is_incarcata = ($data_incarcare != '0000-00-00 00:00:00');

    if ($is_incarcata) {
        echo "<script type='text/javascript'>
                window.location.href = 'detalii_factura.php?id_factura=$id_factura';
              </script>";
    }
    

    // Actualizăm data_factura dacă este necesar
    if ($data_factura == '0000-00-00' || empty($data_factura)) {
        $current_date = date('Y-m-d');
        $data_factura = $current_date;
        $update_date_sql = "UPDATE facturi SET data_factura = :data_factura WHERE id_factura = :id_factura";
        $update_date_stmt = $pdo->prepare($update_date_sql);
        $update_date_stmt->bindParam(':data_factura', $current_date);
        $update_date_stmt->bindParam(':id_factura', $id_factura, PDO::PARAM_INT);
        $update_date_stmt->execute();
    }

    // Actualizăm data_scadenta dacă este necesar
    if ($data_scadenta == '0000-00-00' || empty($data_scadenta)) {
        $current_date = date('Y-m-d');
        $data_scadenta = $current_date;
        $update_scadenta_sql = "UPDATE facturi SET data_scadenta = :data_scadenta WHERE id_factura = :id_factura";
        $update_scadenta_stmt = $pdo->prepare($update_scadenta_sql);
        $update_scadenta_stmt->bindParam(':data_scadenta', $current_date);
        $update_scadenta_stmt->bindParam(':id_factura', $id_factura, PDO::PARAM_INT);
        $update_scadenta_stmt->execute();
    }

    // Verificăm tabelul 'istoric_validari' dacă este necesar
    $validare_sql = "SELECT status, response, timestamp FROM istoric_validari WHERE id_factura = :id_factura ORDER BY timestamp DESC LIMIT 1";
    $validare_stmt = $pdo->prepare($validare_sql);
    $validare_stmt->bindParam(':id_factura', $id_factura, PDO::PARAM_INT);
    $validare_stmt->execute();
    $last_validare = $validare_stmt->fetch(PDO::FETCH_ASSOC);

    $is_validated = false;
    $validation_timestamp = '';
    if ($last_validare && strtolower($last_validare['status']) === 'validat') {
        $is_validated = true;
        $validation_timestamp = $last_validare['timestamp'];
    }


    // Similarly, check the 'istoric_incarcari' table
    $incarcare_sql = "SELECT status, index_incarcare, timestamp FROM istoric_incarcari WHERE id_factura = :id_factura ORDER BY timestamp DESC LIMIT 1";
    $incarcare_stmt = $pdo->prepare($incarcare_sql);
    $incarcare_stmt->bindParam(':id_factura', $id_factura, PDO::PARAM_INT);
    $incarcare_stmt->execute();
    $last_incarcare = $incarcare_stmt->fetch(PDO::FETCH_ASSOC);

    $is_incarcat = false;
    $incarcare_index = '';
    if ($last_incarcare && strtolower($last_incarcare['status']) === 'încărcat') {
        $is_incarcat = true;
        $incarcare_index = htmlspecialchars($last_incarcare['index_incarcare']);
    }
$anaf_xml_pregatit = null;
try {
    $anaf_xml_client_id = (int)($_SESSION['client_id'] ?? 0);
    if ($anaf_xml_client_id > 0) {
        $anaf_xml_pregatit = anafXmlGetLatestArtifact($pdo, (int)$id_factura, $anaf_xml_client_id);
    }
} catch (Throwable $anafXmlException) {
    error_log('ANAF XML factura: ' . $anafXmlException->getMessage());
}

// Are factura produse in vanzari?
$chk_sql = "SELECT 1 FROM vanzari WHERE id_factura = :id_factura LIMIT 1";
$chk_stmt = $pdo->prepare($chk_sql);
$chk_stmt->execute([':id_factura' => $id_factura]);

$are_randuri_vanzari = (bool)$chk_stmt->fetchColumn();
// Preluam emailul firmei pentru pre-completarea modalului de email
$email_firma_factura = '';

try {
    $ef_stmt = $pdo->query("SELECT email FROM casa_online_company LIMIT 1");
    $ef_row  = $ef_stmt->fetch(PDO::FETCH_ASSOC);
    $email_firma_factura = htmlspecialchars($ef_row['email'] ?? '', ENT_QUOTES, 'UTF-8');
} catch (Throwable $e) {
    $email_firma_factura = '';
}
} else {
    echo "Eroare: factura nu a putut fi găsită.";
    exit;
}

// Pre-completăm câmpurile cu valori implicite dacă sunt goale
if (empty($iban)) $iban = '-';
if (empty($banca)) $banca = '-';
if (empty($adresa_tara)) $adresa_tara = 'RO';
// compat: dacă în DB ai "ROMANIA" / "România" etc., o convertim la cod
$adresa_tara = getCountryCode($adresa_tara) ?: 'RO';

?>
<?php

if (isset($_POST['reset_steps'])) {
    unset($_SESSION['completed_steps']);
        $id_factura= $_SESSION['id_factura'] ;

    // Poți redirecționa după resetare, dacă vrei:
        echo "<script>window.location.href='factura.php?id_factura=$id_factura';</script>";
    exit;
}
?>

<!-- <h4><?php //var_dump($_SESSION['completed_steps']);?></h4> -->
<div class="container-fluid casa-actions"><a class="btn btn-success" href="sincronizare.php">Finalizare și sincronizare factură</a></div><!-- Begin Page Content -->
 <form method="post" action="">
    <button type="submit" name="reset_steps" class="btn btn-info">Modifică antetul</button>
</form>

<?php
$hideStep1 = in_array(1, $_SESSION['completed_steps'] ?? []) ? 'hidestep1' : '';

?>
<?php
$completedSteps = $_SESSION['completed_steps'] ?? [];
$hideStep2 = (empty($completedSteps) || !in_array(1, $completedSteps)) ? 'hidestep2' : '';
?>

<style>.hidestep1 {
    display: none !important;
}
.hidestep2 {
    display: none !important;
}

</style>
<style>
.hidestep1 {
    display: none !important;
}
.hidestep2 {
    display: none !important;
}

.payment-shortcut-btn {
    min-height: 52px;
    font-weight: 700;
    font-size: 16px;
    white-space: nowrap;
}

.payment-shortcut-btn.active-shortcut {
    box-shadow: inset 0 0 0 2px rgba(0,0,0,0.15);
}
</style>
<div class="container-fluid casa-actions"><a class="btn btn-success" href="sincronizare.php">Finalizare și sincronizare factură</a></div><!-- Begin Page Content -->
<div class="container-fluid">
    <div class="card mb-3 <?= $hideStep1 ?>">
        <div class="card-header">
            <h4>Introduceți CUI pentru completare automată</h4> 
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-8 col-sm-12">
                    <input type="text" name="input_cui" id="input_cui" class="form-control virtual_keyboard_numeric virtual-keyboard" placeholder="Introduceți CUI-ul">
                </div>
                <div class="col-md-4 col-sm-12">
                    <button type="button" id="fetch_cui_data" class="btn btn-secondary btn-block">Completează datele</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3 <?= $hideStep1 ?>">
        <div class="card-header">
            <h4>Selectați Client Existent</h4>
        </div>
        <div class="card-body">
            <select id="client_select" class="form-control select2">
                <option value="">Selectați un client</option>
                <?php
                // Interogăm tabela 'clienti' pentru a obține lista de clienți
                $clienti_sql = "SELECT id_client, nume, cod_fiscal FROM clienti ORDER BY nume ASC";
                $clienti_stmt = $pdo->prepare($clienti_sql);
                $clienti_stmt->execute();
                while ($client = $clienti_stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo '<option value="' . htmlspecialchars($client['id_client']) . '">'
                         . htmlspecialchars($client['nume']) . ' (' . htmlspecialchars($client['cod_fiscal']) . ')'
                         . '</option>';
                }
                ?>
            </select>
        </div>
    </div>

    <!-- Form pentru actualizarea facturii -->
    <div class="card mb-3">
        <div class="card-header">
            <i class="fa fa-table"></i> Completează Factura
        </div>
        <div class="card-body">
            <form id="update_factura_form" >
                <div class="form-group <?= $hideStep1 ?>">
                    <h4>Datele Clientului:</h4>
                    <div class="row ">
                        <?php
                        // List of fields to display
                        $client_fields = [
                            'cod_fiscal' => 'Cod Fiscal (CUI) /CNP (pentru pers. fizică fără CNP scrieți 13 de 0 adică 0000000000000)',
                            'nume' => 'Denumire/Nume Prenume',
                            'cod_inmatriculare' => 'Nr. ord. reg. com/ Serie si nr CI',
                            'banca' => 'Banca',
                            'iban' => 'IBAN',
                            'adresa' => 'Adresa',
                            'adresa_localitate' => 'Localitate',
                            'adresa_judet' => 'Județ',
                                'adresa_tara'        => 'Țara',              

                        ];

                       foreach ($client_fields as $field => $label) {
    $value = isset($$field) ? $$field : '';

    echo '<div class="form-group col-md-6">';
    echo "<label for='{$field}'><strong>{$label}</strong></label>";

    if ($field === 'adresa_tara') {
        // dacă în DB e scris nume, îl convertim în cod (RO/DE/US etc.)
        $value = getCountryCode($value) ?: (preg_match('/^[A-Z]{2}$/', strtoupper($value)) ? strtoupper($value) : 'RO');

        $countriesRo = getCountryListRoSorted();

        echo "<select name='adresa_tara' id='adresa_tara' class='form-control select2' required>";
        echo "<option value=''>Selectați țara</option>";

        foreach ($countriesRo as $code => $nameRo) {
            $code = strtoupper($code);
            $selected = ($value === $code) ? 'selected' : '';
            echo "<option value='" . htmlspecialchars($code) . "' {$selected}>"
                . htmlspecialchars($nameRo) . " - " . htmlspecialchars($code)
                . "</option>";
        }

        echo "</select>";
    } else {
        echo "<input type='text' name='{$field}' id='{$field}' value='" . htmlspecialchars($value) . "' class='form-control virtual-keyboard'>";
    }

    echo '</div>';
}

                        ?>
                    </div>

                    <!-- Butonul de deschidere a modalului -->
                    <button type="button" class="btn btn-secondary float-right" data-toggle="modal" data-target="#countyModal">
                        Schimbă județ
                    </button>
                </div>

                <!-- Modal-ul pentru selectarea județului -->
                <div class="modal fade" id="countyModal" tabindex="-1" role="dialog" aria-labelledby="countyModalLabel" aria-hidden="true">
                  <div class="modal-dialog" role="document">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title" id="countyModalLabel">Selectează Județ</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Închide">
                          <span aria-hidden="true">&times;</span>
                        </button>
                      </div>
                      <div class="modal-body">
                          <select id="countySelect" class="form-control">
                              <option value="">Selectează judetul</option>
                              <option value="ALBA">ALBA</option>
                              <option value="ARAD">ARAD</option>
                              <option value="ARGEȘ">ARGEȘ</option>
                              <option value="BACĂU">BACĂU</option>
                              <option value="BIHOR">BIHOR</option>
                              <option value="BISTRIȚA-NĂSĂUD">BISTRIȚA-NĂSĂUD</option>
                              <option value="BOTOȘANI">BOTOȘANI</option>
                              <option value="BRAȘOV">BRAȘOV</option>
                              <option value="BRĂILA">BRĂILA</option>
                              <option value="BUCUREȘTI">BUCUREȘTI</option>
                              <option value="BUZĂU">BUZĂU</option>
                              <option value="CARAȘ-SEVERIN">CARAȘ-SEVERIN</option>
                              <option value="CĂLĂRAȘI">CĂLĂRAȘI</option>
                              <option value="CLUJ">CLUJ</option>
                              <option value="CONSTANTA">CONSTANTA</option>
                              <option value="COVASNA">COVASNA</option>
                              <option value="DÂMBOVIȚA">DÂMBOVIȚA</option>
                              <option value="DOLJ">DOLJ</option>
                              <option value="GALAȚI">GALAȚI</option>
                              <option value="GIURGIU">GIURGIU</option>
                              <option value="GORJ">GORJ</option>
                              <option value="HARGHITA">HARGHITA</option>
                              <option value="HUNEDOARA">HUNEDOARA</option>
                              <option value="IALOMIȚA">IALOMIȚA</option>
                              <option value="IAȘI">IAȘI</option>
                              <option value="ILFOV">ILFOV</option>
                              <option value="MARAMUREȘ">MARAMUREȘ</option>
                              <option value="MEHEDINȚI">MEHEDINȚI</option>
                              <option value="MUREȘ">MUREȘ</option>
                              <option value="NEAMȚ">NEAMȚ</option>
                              <option value="OLT">OLT</option>
                              <option value="PRAHOVA">PRAHOVA</option>
                              <option value="SATU MARE">SATU MARE</option>
                              <option value="SĂLAJ">SĂLAJ</option>
                              <option value="SIBIU">SIBIU</option>
                              <option value="SUCEAVA">SUCEAVA</option>
                              <option value="TELEORMAN">TELEORMAN</option>
                              <option value="TIMIȘ">TIMIȘ</option>
                              <option value="TULCEA">TULCEA</option>
                              <option value="VASLUI">VASLUI</option>
                              <option value="VÂLCEA">VÂLCEA</option>
                              <option value="VRANCEA">VRANCEA</option>
                              <option value="MUNICIPIUL BUCUREȘTI">MUNICIPIUL BUCUREȘTI</option>
                              <option value="MUNICIPIUL BUCURESTI">MUNICIPIUL BUCURESTI</option>
                          </select>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-primary" id="saveCounty">Salvează</button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Închide</button>
                      </div>
                    </div>
                  </div>
                </div>
<script>
var disablePreiaPretAchizitie = <?php echo $disable_preia_pret_achizitie ? 'true' : 'false'; ?>;
</script>
                <!-- Scripturile pentru evenimente -->
                <script>
                  $(document).ready(function(){
                    // La click pe butonul "Salvează" din modal, se actualizează inputul și se închide modalul
                    $('#saveCounty').on('click', function(){
                      var selectedCounty = $('#countySelect').val();
                      if(selectedCounty) {
                        $('#adresa_judet').val(selectedCounty);
                      }
                      if (typeof normalizeFacturaLocalitateBucuresti === 'function') {
                        normalizeFacturaLocalitateBucuresti(false);
                      }
                      // Închide modalul
                      $('#countyModal').modal('hide');
                    });
                  });
                </script>

                <?php
                // Verificăm dacă adresa județului conține "BUC" (ex: "BUCUREȘTI", "MUN. BUCUREȘTI" etc.)
                if (isset($adresa_judet) && stripos($adresa_judet, 'BUC') !== false) {
                    // Lista corectă de localități pentru București
                    $localitati_bucuresti = ['SECTOR1', 'SECTOR2', 'SECTOR3', 'SECTOR4', 'SECTOR5', 'SECTOR6'];

                    // Verificăm dacă localitatea nu este una dintre valorile corecte
                    if (!in_array(strtoupper(trim($adresa_localitate)), $localitati_bucuresti)) {
                        echo '<div class="alert alert-warning" style="background-color:red;color:white;">
                            <strong>Atenție!</strong> Dacă județul este Municipiul București, localitatea trebuie să fie scrisă în formatul <strong>SECTOR1, SECTOR2, SECTOR3, SECTOR4, SECTOR5, SECTOR6</strong>.
                        </div>';
                    }
                }
                ?>

                <hr>

                <!-- Seria și Numărul Facturii -->
                <div class="form-group <?= $hideStep1 ?>">
                    <h4>Seria și Numărul Facturii</h4>
                    <div class="row">
                        <div class="col-md-6 col-sm-12 mb-3">
                            <label for="serie_factura"><strong>Seria Facturii</strong></label>
                            <select name="serie_factura" id="serie_factura" class="form-control" required>
                                <option value="">Selectați o serie</option>
                                <?php
                                // Fetch series where tip_registru contains 'factura'
                                $series_sql = "SELECT serie FROM casa_online_series WHERE tip_registru LIKE '%factura%'";
                                $series_stmt = $pdo->prepare($series_sql);
                                $series_stmt->execute();
                                while ($serie = $series_stmt->fetch(PDO::FETCH_ASSOC)) {
                                    // Preserve the selected option
                                    $selected = ($serie_factura === $serie['serie']) ? 'selected' : '';
                                    echo "<option value='" . htmlspecialchars($serie['serie']) . "' $selected>" . htmlspecialchars($serie['serie']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6 col-sm-12 mb-3">
                            <label for="numar_factura"><strong>Număr Factură</strong></label>
                            <input type="number" name="nr_factura" id="numar_factura" value="<?php echo htmlspecialchars(max(1, (int)$nr_factura)); ?>" min="1" step="1" class="form-control virtual-keyboard" required>
                        </div>
                    </div>
                </div>

                <div class="form-group <?= $hideStep1 ?>">
                    <h4>Data Facturii</h4>
                    <input type="date" name="data_factura" value="<?php echo htmlspecialchars($data_factura); ?>" class="form-control" required>
                </div>
                <div class="form-group <?= $hideStep1 ?>">
                    <h4>Data Scadenței:</h4>
                    <input type="date" name="data_scadenta" value="<?php echo htmlspecialchars($data_scadenta); ?>" class="form-control" required>
                </div>

                <div class="form-group <?= $hideStep1 ?>">
    <h4>Metoda de Plată:</h4>

    <div class="d-flex align-items-stretch mb-2 payment-shortcuts-row" style="gap:8px;">
        <button type="button" class="btn btn-outline-success flex-fill payment-shortcut-btn" data-value="10">
            Numerar
        </button>
        <button type="button" class="btn btn-outline-primary flex-fill payment-shortcut-btn" data-value="48">
            Card
        </button>
        <button type="button" class="btn btn-outline-warning flex-fill payment-shortcut-btn" data-value="42">
            OP
        </button>
        <button type="button" class="btn btn-outline-info flex-fill payment-shortcut-btn" data-value="68">
            Online
        </button>
    </div>

    <select name="cod_metoda_plata" id="cod_metoda_plata" class="form-control select2" required>
        <option value="">Selectează metoda de plată</option>
        <?php
        foreach ($metode_plata as $cod => $descriere) {
            $selected = ($cod_metoda_plata == $cod) ? 'selected' : '';
            echo "<option value=\"{$cod}\" {$selected}>{$cod} - {$descriere}</option>";
        }
        ?>
    </select>
</div>
                <?php
                // Dacă există date pentru delegat în factura din baza de date, le decodificăm
                if (!empty($factura['delegat'])) {
                    $delegat_data = json_decode($factura['delegat'], true);
                    $delegat_nume      = $delegat_data['nume'] ?? '';
                    $delegat_ci_serie  = $delegat_data['ci_serie'] ?? '';
                    $delegat_ci_numar  = $delegat_data['ci_numar'] ?? '';
                    $delegat_masina    = $delegat_data['masina'] ?? '';
                    $delegat_semnatura = $delegat_data['semnatura'] ?? '';
                } else {
                    $delegat_nume = $delegat_ci_serie = $delegat_ci_numar = $delegat_masina = $delegat_semnatura = '';
                }
                ?>

                <!-- Secțiune Date Delegat (opțional) -->
                <div class="form-group <?= $hideStep1 ?>">
                    <h4>Date delegat (opțional):</h4>
                    <div class="row">
                        <div class="col-md-6">
                            <label for="delegat_nume"><strong>Numele delegatului</strong></label>
                            <input type="text" name="delegat_nume" id="delegat_nume" class="form-control" 
                                   value="<?php echo htmlspecialchars($delegat_nume); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="delegat_ci_serie"><strong>B.I./C.I. seria</strong></label>
                            <input type="text" name="delegat_ci_serie" id="delegat_ci_serie" class="form-control" 
                                   value="<?php echo htmlspecialchars($delegat_ci_serie); ?>">
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-6">
                            <label for="delegat_ci_numar"><strong>Numărul</strong></label>
                            <input type="text" name="delegat_ci_numar" id="delegat_ci_numar" class="form-control" 
                                   value="<?php echo htmlspecialchars($delegat_ci_numar); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="delegat_masina"><strong>Mașina</strong></label>
                            <input type="text" name="delegat_masina" id="delegat_masina" class="form-control" 
                                   value="<?php echo htmlspecialchars($delegat_masina); ?>">
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-12">
                            <label for="delegat_semnatura"><strong>Semnătura</strong></label>
                            <input type="text" name="delegat_semnatura" id="delegat_semnatura" class="form-control" 
                                   value="<?php echo htmlspecialchars($delegat_semnatura); ?>">
                        </div>
                    </div>
                </div>

                <div class="form-group <?= $hideStep1 ?>">
                    <input class="btn btn-primary btn-block" type="submit" value="Salvare Antet Factură / Deblocare Adăugare Produs">
                </div>
            </form>

            <?php if (!$is_tip_384): ?>
                <?php 
                $form_class = isset($_SESSION['factura_actualizata']) && $_SESSION['factura_actualizata'] == 0 ? 'd-none' : '';
                ?>

                <!-- Form pentru adăugarea unui produs -->
                <form id="adaug_produs_form" class="mt-4 <?php echo $form_class; ?> <?= $hideStep2 ?>">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h4 class="mb-0">Adaugă Produs</h4>
                        <a href="factura_importa_pvi.php?id_factura=<?php echo urlencode($id_factura); ?>"
                           class="btn btn-warning btn-sm">
                            <i class="fas fa-file-import"></i> Import PVI
                        </a>
                    </div>
                    <table class="table table-bordered">
                        <tr>
                            <td><h5>Denumire produs</h5></td>
                            <td>
                                <select class="form-control select2" id="id_produs" name="produs" required>
                                    <option value="">Selectați un produs</option>
                                    <?php
                                    // Query pentru a combina produsele din 'vanzari' și 'produse_servicii'
                                    $product_sql = "
                                        SELECT cod_produs, nume AS denumire_produs FROM produse_servicii
                                        ORDER BY denumire_produs ASC
                                    ";
                                    $product_stmt = $pdo->prepare($product_sql);
                                    $product_stmt->execute();
                                    while ($product = $product_stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo "<option value='" . htmlspecialchars($product['cod_produs']) . "'>" . htmlspecialchars($product['denumire_produs']) . "</option>";
                                    }
                                    ?>
                                </select>
                                <input type="hidden" name="nume_produs" id="nume_produs">
                            </td>
                        </tr>
                        <tr>
                            <td><h5>Detalii denumire produs</h5></td>
                            <td><input class="form-control" type="text" name="detalii_denumire"/></td>
                        </tr>
                        <?php
                        // Citește fișierul CSV
                        $data = [];
                        if (($handle = fopen("um_efactura.csv", "r")) !== false) {
                            // Sari peste antetul fișierului CSV dacă există
                            fgetcsv($handle, 1000, ";");

                            while (($row = fgetcsv($handle, 1000, ";")) !== false) {
                                // Presupunem că fișierul are două coloane: cod_um;denumire_um
                                $cod_um = $row[0];
                                $denumire_um = $row[1];
                                $data[$cod_um] = $denumire_um;
                            }
                            fclose($handle);
                        }
                        // Definește unitățile de măsură cele mai uzuale care vor fi afișate primele
                        $most_used = ['H87','KGM','LTR','MTK','MTQ','MTR'];

                        // Construim un array nou astfel încât unitățile cele mai folosite să apară primele
                        $sorted_data = [];

                        // Adaugă întâi unitățile cele mai folosite în ordinea dorită
                        foreach ($most_used as $mu) {
                            if (isset($data[$mu])) {
                                $sorted_data[$mu] = $data[$mu];
                                unset($data[$mu]);
                            }
                        }

                        // Adaugă restul unităților
                        foreach ($data as $k => $v) {
                            $sorted_data[$k] = $v;
                        }
                        ?>

                        <tr>
                            <td><h5>U.M.</h5></td>
                            <td style="font-size:16px;">
                                <select class="select2 form-control" required name="um">
                                    <option value="">Selectați U.M.</option>
                                    <?php foreach ($sorted_data as $cod_um => $denumire_um): ?>
                                        <option value="<?= htmlspecialchars($cod_um) ?>">
                                            <?= htmlspecialchars($denumire_um) . ' - ' . htmlspecialchars($cod_um) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <td><h5>Cantitate</h5></td>
<td><input class="form-control virtual-keyboard" type="number" name="cantitate" value="1" step="0.00001" required></td>
                        </tr>
                        <tr id="row_total_booking" style="display: none; background-color: #fff3cd;">
    <td>
        <h5 style="color: #856404; font-weight: bold;">
            Total de încasat (Booking)<br>
            <small style="font-weight: normal; font-size: 12px;">(Include Cazare + TVA + Taxa Hotelieră 2%)</small>
        </h5>
    </td>
    <td>
        <input class="form-control virtual-keyboard" type="number" id="input_total_booking" step="0.0001" placeholder="Ex: 1000 lei">
        <small class="text-muted">Introduceți suma totală plătită de client. Sistemul va calcula automat prețul cazării și taxa.</small>
    </td>
</tr>

                       <tr>
                                     <td><h5>Pret cu TVA</h5></td>
                                     <td>
                                         <div class="input-group">
                                             <input class="form-control virtual-keyboard" type="number" name="pret_cu_tva" value="1" step="0.00001" required>
                                             <div class="input-group-append">
                                                 <button type="button" class="btn btn-info" id="set_pret_achizitie" <?= $disable_preia_pret_achizitie ? 'disabled' : '' ?>>Preia preț achiziție cu tva</button>
                                             </div>
                                         </div>
                                     </td>
                                 </tr>
                        <tr>
                            <td><h5>Cota TVA</h5></td>
                            <td>
                                <select name="cota_tva" class="form-control select2" required>
                                    <option value="">Selectați Cota TVA</option>
                                    <option value="0">0%</option>
                                    <option value="5">5%</option>
                                    <option value="9">9%</option>
                                    <option value="19">19%</option>
                                       <option value="11">11%</option>
                                    <option value="21">21%</option>
                                </select>
                            </td>
                        </tr>
                        
                       <tr id="row_preview_sume" style="display: none; background-color: #f8f9fa; border-top: 2px solid #4e73df;">
                            <td>
                                <h5 style="color: #4e73df; font-weight: bold;">Previzualizare Cazare</h5>
                            </td>
                            <td>
                                <div class="row text-center">
                                    <div class="col-md-4">
                                        <small class="text-muted font-weight-bold">Valoare Netă</small><br>
                                        <span id="preview_val_neta" style="font-size: 1.1rem; font-weight: bold;">0.00</span> Lei
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted font-weight-bold">Valoare TVA</small><br>
                                        <span id="preview_val_tva" style="font-size: 1.1rem; font-weight: bold; color: #e74a3b;">0.00</span> Lei
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted font-weight-bold">Total + Taxă</small><br>
                                        <span id="preview_total_general" style="font-size: 1.1rem; font-weight: bold; color: #1cc88a;">0.00</span> Lei
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <tr id="row_taxa_hoteliera" style="display: none; background-color: #e8f4f8;">
                            <td>
                                <h5 style="color: #2c3e50; font-weight: bold;">Taxa Hotelieră (2%) <br><small>Se adaugă automat la total</small></h5>
                            </td>
                            <td>
                                <input type="text" id="input_taxa_hoteliera" class="form-control" readonly style="font-weight: bold; color: #d35400;" value="0.00 Lei">
                                <small class="text-muted">Calculată la valoarea netă (fără TVA).</small>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <input class="btn btn-primary btn-block" type="submit" value="Adaugă Produs">
                            </td>
                        </tr>
                    </table>
                </form>
            <?php else: ?>
                <div class="alert alert-warning mt-4" role="alert">
                    Modificarea conținutului pentru această factură este dezactivat. Dacă doriți să corectați conținutul facturii creați o factură de stornare.
                </div>
            <?php endif; ?>

            <!-- Afișare produse din 'vanzari' -->
            <h4 class="mt-5 <?= $hideStep2 ?>">Conținutul Facturii</h4>
            <div class="table-responsive <?= $hideStep2 ?>">
                <table class="table table-bordered table-hover table-striped">
                    <thead class="thead-dark">
                        <tr>
                            <th>Nr. Crt.</th>
                            <th>Denumire Produs</th>
                            <th>U.M. <?php if ($tipul_facturii == 751) {
                                echo '<p style="color: red;">Verificați unitatea de măsură să fie H87 (BUC), KGM (KG), LTR (LITRU), MTK (METRU PĂTRAT), MTQ (METRU CUB) sau MTR (METRU) și dați click pe aceasta pentru a o modifica.</p>';
                            } ?></th>
                            <th>Cantitate</th>
                            <th>TVA</th>
                            <th>Preț (Lei)</th>
                            <th>Valoare (Lei)</th>
                            <th>Cota TVA (%)</th>
                            <th>Valoare cu TVA (Lei)</th>
                            <th>Acțiuni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Pregătește interogarea SQL pentru a obține toate coloanele necesare
                        $item_sql = "SELECT den_p, um, cantitate, tva_col, pret_vanzare, valoare_vanzare, valoare_vanzare_cu_tva, discount, cota_tva, id_vanz, cod_p FROM vanzari WHERE id_factura = :id_factura";
                        $item_stmt = $pdo->prepare($item_sql);
                        $item_stmt->bindParam(':id_factura', $id_factura, PDO::PARAM_INT);
                        $item_stmt->execute();
                        $nr_crt = 1;

                        // Inițializează variabilele de sumă
                        $total_valoare_fara_tva = 0;
                        $total_tva_colectat = 0;
                        $total_valoare_cu_tva = 0;

                        // Verifică dacă există înregistrări
                        $invoiceItems=$item_stmt->fetchAll(PDO::FETCH_ASSOC);
                        if(count($invoiceItems)>0){
                            foreach ($invoiceItems as $item) {
                                // Extrage și scapează datele pentru securitate
                                $id_vanz = htmlspecialchars($item['id_vanz']);
                                $den_p = htmlspecialchars($item['den_p']);
                                $um = htmlspecialchars($item['um']);
                                $cantitate = number_format($item['cantitate'], 2);
                                $tva_col = number_format($item['tva_col'], 2);
                                $pret_vanzare = number_format($item['pret_vanzare'], 2);
                                $valoare_vanzare = number_format($item['valoare_vanzare'], 2);
                                $discount = number_format($item['discount'], 3);
                                $cota_tva = htmlspecialchars($item['cota_tva']);
                                $valoare_vanzare_cu_tva = number_format($item['valoare_vanzare_cu_tva'], 2);

                                // Adaugă valorile la totaluri
                                $total_valoare_fara_tva += $item['valoare_vanzare'];
                                $total_tva_colectat += $item['tva_col'];
                                $total_valoare_cu_tva += $item['valoare_vanzare_cu_tva'];

                                echo "<tr>
                                        <td>{$nr_crt}</td>
                                        <td>{$den_p}</td>
                                        <td>
                                            <button class='btn btn-primary edit-um-button' 
                                                    data-id_vanz='{$id_vanz}' 
                                                    data-um='{$um}' 
                                                    data-toggle='modal' 
                                                    data-target='#editUMModal'>
                                                <span class='um-display' data-cod-um='{$um}'>{$um}</span>
                                            </button>
                                        </td>
                                        <td>
                                            <button class='btn btn-primary edit-quantity-button' data-id_vanz='{$id_vanz}' data-cantitate='{$item['cantitate']}' data-toggle='modal' data-target='#editQuantityModal'>
                                                {$cantitate}
                                            </button>
                                        </td>
                                        <td>{$tva_col}</td>
                                        <td>{$pret_vanzare}</td>
                                        <td>{$valoare_vanzare}</td>
                                        <td>{$cota_tva}</td>
                                        <td>{$valoare_vanzare_cu_tva}</td>
                                        <td>";
                                if (!$is_tip_384) {
                                    echo "<form method='post'>
                                            <button type='submit' data-id_vanz='{$id_vanz}' class='btn btn-danger btn-sm delete_item_button'>Șterge</button>
                                          </form>";
                                } else {
                                    echo "<button class='btn btn-light btn-sm' disabled>Șterge</button>";
                                }
                                if (!$is_tip_384) {
                                    echo "<button type='button' class='btn btn-info btn-sm edit-price-button'
                                                data-id_vanz='{$id_vanz}'
                                                data-cod_p='" . htmlspecialchars((string)$item['cod_p'], ENT_QUOTES, 'UTF-8') . "'
                                                data-pret='{$item['pret_vanzare']}'
                                                data-den_p=\"" . htmlspecialchars($item['den_p'], ENT_QUOTES, 'UTF-8') . "\"
                                                data-toggle='modal'
                                                data-target='#editPriceModal'>
                                                Preț
                                          </button>";
                                } else {
                                    echo "<button class='btn btn-light btn-sm' disabled>Preț</button>";
                                }
                                echo "<button type='button' data-id_vanz='{$id_vanz}' class='btn btn-warning btn-sm set_zero_button' title='Pune valorile pe 0'>Pune valorile pe 0</button>";
                                echo     "</td>
                                      </tr>";
                                $nr_crt++;
                            }

                            // Formatează totalurile pentru afișare
                            $total_valoare_fara_tva = number_format($total_valoare_fara_tva, 2);
                            $total_tva_colectat = number_format($total_tva_colectat, 2);
                            $total_valoare_cu_tva = number_format($total_valoare_cu_tva, 2);

                            echo "<tr class='font-weight-bold bg-light'>
                                    <td colspan='5' class='text-right'>Total fără TVA:</td>
                                    <td>{$total_valoare_fara_tva} Lei</td>
                                    <td>Total TVA:</td>
                                    <td>{$total_tva_colectat} Lei</td>
                                    <td>Total cu TVA:</td>
                                    <td>{$total_valoare_cu_tva} Lei</td>
                                  </tr>";
                        } else {
                            echo "<tr><td colspan='10' class='text-center'>Nu există produse asociate acestei facturi.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

          <?php if ($are_randuri_vanzari): ?>

    <?php
    $statusRow=casa_one($pdo,'SELECT payload,checked_at FROM casa_remote_status WHERE id_factura=?',[$id_factura]);
    $statusOnline=$statusRow?json_decode($statusRow['payload'],true):null;
    ?>
    <div class="alert alert-info">
        ANAF se gestionează din aplicația online.
        <?php if($statusOnline): ?>
        Status preluat: <?=casa_h($statusOnline['anaf']['status']??'Fără încărcare confirmată')?>.
        <?=!empty($statusOnline['anaf_sent'])?'Factura este confirmată ca trimisă.':''?>
        Verificat la <?=casa_h($statusRow['checked_at'])?>.
        <?php else: ?>Statusul se actualizează la deschiderea listei de facturi.<?php endif; ?>
    </div>
    <a href="sincronizare.php" class="btn btn-primary">Finalizare și stare sincronizare</a>

    <br/>
    <?php
    $este_client_20 = isset($_SESSION['client_id']) && intval($_SESSION['client_id']) === 20;
    if ($este_client_20): ?>
        <a href="listeaza_fact.php?id_factura=<?php echo urlencode($id_factura); ?>" class="btn btn-success btn-block <?= $hideStep2 ?>">
            Listează factura (PDF)
        </a>

        <a href="listeaza_fact_deviz_simplificat.php?id_factura=<?php echo urlencode($id_factura); ?>" class="btn btn-success btn-block <?= $hideStep2 ?>">
            Listează factura Deviz Simplificat (PDF)
        </a>
    <?php else: ?>
        <a href="listeaza_fact.php?id_factura=<?php echo urlencode($id_factura); ?>" class="btn btn-success btn-block <?= $hideStep2 ?>">
            Listează factura (PDF)
        </a>
    <?php endif; ?>
<button type="button"
        class="btn btn-info btn-block <?= $hideStep2 ?>"
        data-toggle="modal"
        data-target="#modal_email_factura">
    <i class="fas fa-envelope"></i> Trimite pe email
</button>
    <a href="genereaza_bon.php?id_factura=<?php echo urlencode($id_factura);?>" class="btn btn-info btn-block <?= $hideStep2 ?>">
        Trimite la casa de marcat
    </a>
<a href="https://docs.google.com/document/d/1BF0YKedrpc_gj6B0VRHGLdnnn0pylhVXZ7Tj6lRyU4M/edit?usp=sharing" class="btn btn-danger btn-block" target="_blank" rel="noopener noreferrer">
Nu a iesit bonul la casa de marcat? Click pentru a verifica urmatoarele. Fisco
</a>
    <br/>

<?php else: ?>

    <!-- optional: un mesaj (dacă vrei) -->
    <div class="alert alert-info <?= $hideStep2 ?>">
        Adaugă cel puțin un produs în factură ca să apară butoanele de listare / ANAF / casa de marcat.
    </div>

<?php endif; ?>

        </div>
    </div>
</div>

<!-- /.container-fluid -->

<!-- Scroll to Top Button-->
<a class="scroll-to-top rounded" href="#page-top">
    <i class="fas fa-angle-up"></i>
</a>

<!-- Include jQuery și alte scripturi externe -->

<!-- Include jQuery UI Position pentru poziționarea tastaturii -->
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
<!-- Include Virtual Keyboard CSS și JS -->
<link rel="stylesheet" href="css/dist/css/keyboard.min.css">
<script src="css/dist/js/jquery.keyboard.min.js"></script>
<!-- Include Bootstrap și alte scripturi -->

<script src="vendor/jquery-easing/jquery.easing.min.js"></script>
<!-- Custom scripts for all pages -->
<script src="js/sb-admin-2.min.js"></script>
<script>
$(document).ready(function() {
    $('#ef_btn_trimite').on('click', function() {
        var email = $('#ef_email_dest').val().trim();
        var $btn  = $(this);
        var $msg  = $('#ef_msg');

        if (!email) {
            $msg.html('<div class="alert alert-danger py-1 mt-2">Completați adresa de email.</div>');
            return;
        }

        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            $msg.html('<div class="alert alert-danger py-1 mt-2">Adresă de email invalidă.</div>');
            return;
        }

        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Se trimite...');
        $msg.html('');

        $.post('email_factura.php', {
            id_factura: <?php echo (int)$id_factura; ?>,
            email: email
        }, function(resp) {
            if (resp.ok) {
                $msg.html('<div class="alert alert-success py-1 mt-2"><i class="fas fa-check"></i> Email trimis cu succes!</div>');
                $btn.html('<i class="fas fa-check"></i> Trimis');

                setTimeout(function() {
                    $('#modal_email_factura').modal('hide');
                }, 800);
            } else {
                $msg.html('<div class="alert alert-danger py-1 mt-2">' + $('<div>').text(resp.err).html() + '</div>');
                $btn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Trimite');
            }
        }, 'json').fail(function(xhr, status, error) {
            var msg = 'Eroare de comunicare cu serverul.';

            if (xhr.responseText) {
                msg += '<br><small style="word-break:break-word;">' + $('<div>').text(xhr.responseText).html() + '</small>';
            }

            $msg.html('<div class="alert alert-danger py-1 mt-2">' + msg + '</div>');
            $btn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Trimite');
        });
    });

    $('#modal_email_factura').on('hidden.bs.modal', function() {
        $('#ef_msg').html('');
        $('#ef_btn_trimite').prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Trimite');

        $('body').removeClass('modal-open');
        $('.modal-backdrop').remove();
        $('body').css('padding-right', '');
        $('body').css('overflow', '');
    });
});
</script>
<!-- Select2 -->
<link href="vendor/offline/select2/select2.min.css" rel="stylesheet" />

<!-- Include scriptul pentru tastatura virtuală -->
<script src="js/virtual_keyboard.js"></script>

<script src="CBS_functions.js"></script>

<link rel="stylesheet" href="css/virtual_keyboard.css">

<link rel="stylesheet" href="vendor/offline/fontawesome5/css/all.min.css">

<!-- Script pentru actualizarea numărului facturii la schimbarea seriei -->
<script>
document.getElementById('serie_factura').addEventListener('change', function() {
    var selectedSerie = this.value;
    var nrFacturaInput = document.getElementById('numar_factura'); // se preia inputul pentru număr factură

    if (selectedSerie) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'get_next_nr_factura.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.next_nr_factura) {
                        // Setăm numărul următor așteptat în input
                        nrFacturaInput.value = response.next_nr_factura;
                        // Declanșăm un eveniment 'input' pentru a notifica eventualele scripturi legate de schimbarea valorii
                        var inputEvent = new Event('input', { bubbles: true });
                        nrFacturaInput.dispatchEvent(inputEvent);
                    } else if (response.error) {
                        alert('Eroare: ' + response.error);
                        nrFacturaInput.value = '';
                    } else {
                        nrFacturaInput.value = '';
                        alert('Nu s-a putut obține numărul facturii.');
                    }
                } catch (e) {
                    alert('Eroare la procesarea răspunsului de la server.');
                }
            } else {
                alert('Eroare la cerere. Cod status: ' + xhr.status);
            }
        };

        xhr.onerror = function() {
            alert('Eroare de rețea sau de server.');
        };

        // Trimitem seria selectată
        xhr.send('serie_factura=' + encodeURIComponent(selectedSerie));
    } else {
        // Dacă nu este selectată nicio serie, golim inputul
        nrFacturaInput.value = '';
        var inputEvent = new Event('input', { bubbles: true });
        nrFacturaInput.dispatchEvent(inputEvent);
    }
});
</script>

<!-- Validator simplu pentru număr factură consecutiv -->
<script>
var facturaNrInitial = <?php echo json_encode((string)$nr_factura); ?>;
var facturaSerieInitiala = <?php echo json_encode((string)$serie_factura); ?>;

function facturaParseNr(value) {
    var nr = parseInt(value, 10);
    return isNaN(nr) ? 0 : nr;
}

function facturaAfiseazaMesajNumar(text) {
    var input = $('#numar_factura');
    if (!input.length) {
        return;
    }

    var msg = $('#mesaj_validare_numar_factura');
    if (!msg.length) {
        input.after('<small id="mesaj_validare_numar_factura" class="form-text text-danger" style="display:none;"></small>');
        msg = $('#mesaj_validare_numar_factura');
    }

    if (text) {
        msg.text(text).show();
    } else {
        msg.text('').hide();
    }
}

function verificaNumarFacturaConsecutiv(afiseazaAlerta, sincron) {
    var serie = $('#serie_factura').val();
    var nrIntrodus = facturaParseNr($('#numar_factura').val());
    var nrInitial = facturaParseNr(facturaNrInitial);

    if (nrIntrodus < 1) {
        var mesajMinim = 'Numărul facturii nu poate fi 0. Valoarea minimă permisă este 1.';
        facturaAfiseazaMesajNumar(mesajMinim);
        if (afiseazaAlerta) {
            alert(mesajMinim);
        }
        return false;
    }

    if (!serie) {
        facturaAfiseazaMesajNumar('');
        return true;
    }

    // Dacă utilizatorul modifică doar alte date ale unei facturi deja salvate,
    // iar numărul și seria au rămas aceleași, nu blocăm formularul.
    if (nrInitial > 0 && nrIntrodus === nrInitial && serie.toString() === facturaSerieInitiala.toString()) {
        facturaAfiseazaMesajNumar('');
        return true;
    }

    var esteValid = true;
    var mesaj = '';

    $.ajax({
        url: 'get_next_nr_factura.php',
        type: 'POST',
        data: { serie_factura: serie },
        dataType: 'json',
        async: sincron ? false : true,
        success: function(response) {
            if (response && response.next_nr_factura) {
                var nrCorect = facturaParseNr(response.next_nr_factura);

                if (nrCorect > 0 && nrIntrodus !== nrCorect) {
                    esteValid = false;
                    mesaj = 'Numărul facturii trebuie să fie consecutiv. Următorul număr permis pentru seria ' + serie + ' este ' + nrCorect + ', nu ' + nrIntrodus + '. Exemplu: dacă ultima factură este 1037, următoarea trebuie să fie 1038, nu 1039.';
                }
            }
        },
        error: function() {
            // Dacă verificarea AJAX eșuează, nu blocăm aici formularul;
            // verificarea finală rămâne obligatorie în update_factura.php.
            esteValid = true;
            mesaj = '';
        }
    });

    facturaAfiseazaMesajNumar(mesaj);

    if (!esteValid && afiseazaAlerta) {
        alert(mesaj);
    }

    return esteValid;
}
</script>

<!-- Scripturile principale -->
<script>
function facturaCurataText(value) {
    if (value === null || value === undefined) {
        return '';
    }

    var text = value.toString().trim();

    text = text.replace(/ă/g, 'a')
               .replace(/â/g, 'a')
               .replace(/î/g, 'i')
               .replace(/ș/g, 's')
               .replace(/ş/g, 's')
               .replace(/ț/g, 't')
               .replace(/ţ/g, 't')
               .replace(/Ă/g, 'A')
               .replace(/Â/g, 'A')
               .replace(/Î/g, 'I')
               .replace(/Ș/g, 'S')
               .replace(/Ş/g, 'S')
               .replace(/Ț/g, 'T')
               .replace(/Ţ/g, 'T');

    text = text.toUpperCase();
    text = text.replace(/\s+/g, ' ');

    return text.trim();
}

function facturaEsteJudetBucuresti(value) {
    var text = facturaCurataText(value);

    if (text.indexOf('BUC') !== -1) {
        return true;
    }

    if (text.indexOf('BUCURESTI') !== -1) {
        return true;
    }

    if (text.indexOf('MUNICIPIUL BUCURESTI') !== -1) {
        return true;
    }

    return false;
}

function facturaExtrageSectorDinText(value) {
    var text = facturaCurataText(value);

    if (!text) {
        return '';
    }

    var match = text.match(/(?:^|[^A-Z])SECTOR(?:UL)?\.?\s*([1-6])(?:[^0-9]|$)/);

    if (match && match[1]) {
        return match[1];
    }

    match = text.match(/(?:^|[^A-Z])SECT\.?\s*([1-6])(?:[^0-9]|$)/);

    if (match && match[1]) {
        return match[1];
    }

    match = text.match(/^SECTOR([1-6])$/);

    if (match && match[1]) {
        return match[1];
    }

    return '';
}

function normalizeFacturaLocalitateBucuresti(afiseazaAlerta) {
    var localitateInput = document.getElementById('adresa_localitate');
    var judetInput = document.getElementById('adresa_judet');

    if (!localitateInput) {
        return true;
    }

    var localitateOriginala = localitateInput.value || '';
    var judetOriginal = judetInput ? (judetInput.value || '') : '';

    var sector = facturaExtrageSectorDinText(localitateOriginala);
    var esteBucuresti = facturaEsteJudetBucuresti(judetOriginal);

    if (!sector && esteBucuresti) {
        var localitateCurata = facturaCurataText(localitateOriginala);

        if (/^[1-6]$/.test(localitateCurata)) {
            sector = localitateCurata;
        }
    }

    if (sector) {
        localitateInput.value = 'SECTOR' + sector;
        return true;
    }

    if (esteBucuresti) {
        var localitateVerificata = facturaCurataText(localitateInput.value).replace(/\s+/g, '');

        if (!/^SECTOR[1-6]$/.test(localitateVerificata)) {
            if (afiseazaAlerta) {
                alert('Pentru Municipiul București, localitatea trebuie să fie în format SECTOR1, SECTOR2, SECTOR3, SECTOR4, SECTOR5 sau SECTOR6.');
            }

            return false;
        }

        localitateInput.value = localitateVerificata;
    }

    return true;
}

$(document).ready(function() {
    $('.um-display').each(function(){
        var span = $(this);
        var cod_um = span.data('cod-um');
        span.text(getDenumireUM(cod_um));
    });
    // Inițializare Select2 pentru toate elementele cu clasa .select2
    $('.select2').select2();

    // Listener simplu pentru numărul facturii: verifică să nu fie sărit următorul număr.
    $('#numar_factura').on('keyup input', function() {
        verificaNumarFacturaConsecutiv(false, false);
    });

    $('#numar_factura, #serie_factura').on('change blur', function() {
        verificaNumarFacturaConsecutiv(true, false);
    });
function syncPaymentShortcutButtons() {
    var selectedValue = $('#cod_metoda_plata').val();
    $('.payment-shortcut-btn').removeClass('active-shortcut btn-success btn-primary btn-warning btn-info')
                              .addClass(function() {
                                  if ($(this).data('value') == '10') return 'btn-outline-success';
                                  if ($(this).data('value') == '48') return 'btn-outline-primary';
                                  if ($(this).data('value') == '42') return 'btn-outline-warning';
                                  if ($(this).data('value') == '68') return 'btn-outline-info';
                                  return '';
                              });

    $('.payment-shortcut-btn').each(function() {
        if ($(this).data('value').toString() === selectedValue.toString()) {
            $(this).removeClass('btn-outline-success btn-outline-primary btn-outline-warning btn-outline-info')
                   .addClass('active-shortcut');

            if (selectedValue == '10') $(this).addClass('btn-success');
            if (selectedValue == '48') $(this).addClass('btn-primary');
            if (selectedValue == '42') $(this).addClass('btn-warning');
            if (selectedValue == '68') $(this).addClass('btn-info');
        }
    });
}

$(document).on('click', '.payment-shortcut-btn', function() {
    var metoda = $(this).data('value').toString();
    $('#cod_metoda_plata').val(metoda).trigger('change');

    // Păstrăm comportamentul vechi, dar forțăm și redesenarea Select2 dacă este deja inițializat
    if ($('#cod_metoda_plata').hasClass('select2-hidden-accessible')) {
        $('#cod_metoda_plata').trigger('change.select2');
    }

    syncPaymentShortcutButtons();
});

$('#cod_metoda_plata').on('change', function() {
    syncPaymentShortcutButtons();
});

syncPaymentShortcutButtons();
    // *** Modificarea pentru submit-ul formularului de actualizare factura ***
    $('#update_factura_form').submit(function(e) {
        e.preventDefault(); // Previne trimiterea standard a formularului


        // Verificăm numărul facturii înainte de salvarea antetului
        if (!verificaNumarFacturaConsecutiv(true, true)) {
            return;
        }

        // Verificăm și corectăm localitatea pentru București înainte de salvarea antetului
        if (typeof normalizeFacturaLocalitateBucuresti === 'function' && !normalizeFacturaLocalitateBucuresti(true)) {
            return;
        }

        // Preluăm seria selectată și numărul facturii introduse de utilizator
        var serie = $('#serie_factura').val();
        var entered_nr = parseInt($('#numar_factura').val());

        // Efectuăm un apel AJAX către get_next_nr_factura.php pentru a obține numărul următor așteptat
        $.ajax({
            url: 'get_next_nr_factura.php',
            type: 'POST',
            data: { serie_factura: serie },
            dataType: 'json',
            success: function(response) {
                if(response.next_nr_factura) {
                    var next_nr = parseInt(response.next_nr_factura);
                    // Dacă numărul introdus este mai mare decât cel așteptat, avertizăm utilizatorul
                    if(entered_nr > next_nr) {
                        if(!confirm("Numărul facturii introdus (" + entered_nr + ") este mai mare decât cel următor așteptat (" + next_nr + ").\nDoriți să continuați?")) {
                            return; // Anulăm trimiterea formularului dacă utilizatorul refuză
                        }
                    }
                    // Dacă totul este în regulă, trimitem formularul pentru actualizare
                    var formData = $('#update_factura_form').serialize();
                    $.ajax({
                        url: 'update_factura.php',
                        type: 'POST',
                        data: formData + '&id_factura=<?php echo $_SESSION['id_factura']; ?>',
                        dataType: 'json',
                        success: function(response) {
                            if(response.success) {
                                   fetch('save_step.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ step: 1 })
      }).catch(err => console.error('Pas 1 save error', err));
                                alert('Factura a fost actualizată cu succes.');
                                window.location.href = 'factura.php?id_factura=<?php echo $_SESSION['id_factura']; ?>';
                            } else {
                                alert('Eroare: ' + response.error);
                            }
                        },
                        error: function(xhr, status, error) {
                            alert('A apărut o eroare la actualizarea facturii.');
                        }
                    });
                } else {
                    alert('Nu s-a putut obține numărul următor al facturii.');
                }
            },
            error: function(xhr, status, error) {
                alert('A apărut o eroare la verificarea numărului facturii.');
            }
        });
    });
    // ****************** Sfârșit modificare submit formular factura ******************

    // Evenimentul de schimbare pentru selectul de clienți
    $('#client_select').on('change', function() {
        var clientId = $(this).val();
        if (clientId) {
            $.ajax({
                url: 'factura_get_client_data.php',
                type: 'GET',
                data: { id_client: clientId },
                dataType: 'json',
                success: function(data) {
                    if (data.error) {
                        alert('Eroare: ' + data.error);
                    } else {
                        $('input[name="cod_fiscal"]').val(data.cod_fiscal);
                        $('input[name="nume"]').val(data.nume);
                        $('input[name="cod_inmatriculare"]').val(data.cod_inmatriculare);
                        $('input[name="banca"]').val(data.banca);
                        $('input[name="iban"]').val(data.iban);
                        $('input[name="adresa"]').val(data.adresa);
                        $('input[name="adresa_localitate"]').val(data.adresa_localitate);
                        $('input[name="adresa_judet"]').val(data.adresa_judet);
                        if (typeof normalizeFacturaLocalitateBucuresti === 'function') {
                            normalizeFacturaLocalitateBucuresti(false);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    alert('Eroare la preluarea datelor clientului.');
                }
            });
        }
    });

    // Evenimentul de schimbare pentru selectul de produse
    
    $('#id_produs').on('change', function(){
        var cod_produs = $(this).val();
        
        // Dezactivează butonul dacă niciun produs nu este selectat
        if(!cod_produs) {
            $('#set_pret_achizitie').prop('disabled', true).removeData('pret-achizitie');
            $("select[name='um']").val('').trigger('change');
            $("input[name='pret_cu_tva']").val('');
            $("select[name='cota_tva']").val('').trigger('change');
            $("#nume_produs").val('');
            return;
        }
        
        $.ajax({
            type: 'GET',
            url: 'get_produs_factura.php',
            data: { q: cod_produs },
            dataType: 'json',
            success: function(data){
                if(!data.error){
                    $("select[name='um']").val(data.um).trigger('change');
                    $("input[name='pret_cu_tva']").val(data.pret_cu_tva);
                    $("select[name='cota_tva']").val(data.cota_tva).trigger('change');
                    $("#nume_produs").val(data.nume_produs);
                    // --- LINIE NOUA DE ADAUGAT AICI: ---
                    calculeazaDesignCazare(); 
                    // -----------------------------------
                    // Stochează prețul de achiziție și activează butonul dacă există
                   if(data.pret_achizitie !== null && data.pret_achizitie !== undefined) {
    $('#set_pret_achizitie').data('pret-achizitie', data.pret_achizitie);

    if (disablePreiaPretAchizitie) {
        $('#set_pret_achizitie').prop('disabled', true);
    } else {
        $('#set_pret_achizitie').prop('disabled', false);
    }
} else {
    $('#set_pret_achizitie').prop('disabled', true).removeData('pret-achizitie');
}
                } else {
                    alert(data.error);
                    $("select[name='um']").val('').trigger('change');
                    $("input[name='pret_cu_tva']").val('');
                    $("select[name='cota_tva']").val('').trigger('change');
                    $("#nume_produs").val('');
                    $('#set_pret_achizitie').prop('disabled', true).removeData('pret-achizitie');
                }
            },
            error: function(xhr, status, error){
                console.error("AJAX Error:", status, error);
                 $('#set_pret_achizitie').prop('disabled', true).removeData('pret-achizitie');
            }
        });
    });

    // La încărcarea paginii, butonul este initial dezactivat
$('#set_pret_achizitie').prop('disabled', true);

if (disablePreiaPretAchizitie) {
    $('#set_pret_achizitie').prop('disabled', true);
}
    // Noul eveniment de click pentru butonul adăugat
  $('#set_pret_achizitie').on('click', function() {
    if (disablePreiaPretAchizitie) {
        return false;
    }

    var pretAchizitie = $(this).data('pret-achizitie');
    if(pretAchizitie !== null && pretAchizitie !== undefined) {
        $("input[name='pret_cu_tva']").val(pretAchizitie);
    } else {
        alert("Prețul de achiziție nu este disponibil pentru acest produs.");
    }
});


    // Submit pentru formularul de adăugare produs
    $('#adaug_produs_form').submit(function(e) {
        e.preventDefault(); // Previne trimiterea standard a formularului
        var formData = $(this).serialize();
        $.ajax({
            url: 'add_product.php',
            type: 'POST',
            data: formData + '&id_factura=<?php echo $_SESSION['id_factura']; ?>',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('Produsul a fost adăugat cu succes.');
                    location.reload();
                } else {
                    alert('Eroare: ' + response.error);
                }
            },
            error: function(xhr, status, error) {
                alert('A apărut o eroare la adăugarea produsului.');
            }
        });
    });

    // Ștergerea unui produs
    $('.delete_item_button').click(function() {
        var id_vanz = $(this).data('id_vanz');
        if (confirm('Ești sigur că vrei să ștergi acest produs?')) {
            $.ajax({
                url: 'delete_item.php',
                type: 'POST',
                data: { id_vanz: id_vanz, id_factura: <?php echo $_SESSION['id_factura']; ?> },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('Produsul a fost șters cu succes.');
window.location.href = 'factura.php?id_factura=<?php echo $_SESSION["id_factura"]; ?>';
                    } else {
                        alert('Eroare: ' + response.error);
                    }
                },
                error: function(xhr, status, error) {
                    alert('A apărut o eroare la ștergerea produsului.');
                }
            });
        }
    });
// Handler pentru butonul "Set 0"
    $('.set_zero_button').click(function() {
        var id_vanz = $(this).data('id_vanz');
        
        if (confirm('Ești sigur că vrei să setezi valorile (Preț/Total) pe 0 pentru acest produs? Cota TVA va rămâne neschimbată.')) {
            $.ajax({
                url: 'set_zero_item.php',
                type: 'POST',
                data: { id_vanz: id_vanz },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Reîncărcăm pagina pentru a vedea modificările
                        location.reload(); 
                    } else {
                        alert('Eroare: ' + response.error);
                    }
                },
                error: function(xhr, status, error) {
                    alert('A apărut o eroare de conexiune.');
                }
            });
        }
    });
    // Handler pentru editarea cantității
    $('.edit-quantity-button').on('click', function() {
        var id_vanz = $(this).data('id_vanz');
        var cantitate = $(this).data('cantitate');
        $('#modal_id_vanz').val(id_vanz);
        $('#modal_cantitate').val(parseFloat(cantitate).toFixed(2));
    });

    // Handler pentru editarea unității de măsură (UM)
    $('.edit-um-button').on('click', function() {
        var id_vanz = $(this).data('id_vanz');
        var um = $(this).data('um');
        $('#modal_id_vanz_um').val(id_vanz);
    });

    // Handler pentru editarea prețului
    $('.edit-price-button').on('click', function() {
        var id_vanz = $(this).data('id_vanz');
        var codProdus = $(this).attr('data-cod_p') || '';
        var pret = $(this).data('pret');
        var denumire = $(this).data('den_p') || '';

        $('#modal_id_vanz_pret').val(id_vanz);
        $('#modal_cod_produs_pret').val(codProdus);
        $('#modal_pret_vanzare').val(parseFloat(pret).toFixed(5));
        $('#modal_denumire_pret').text(denumire);
        $('#modal_set_pret_achizitie').prop('disabled', disablePreiaPretAchizitie || !codProdus);
    });

    $('#modal_set_pret_achizitie').on('click', function() {
        if (disablePreiaPretAchizitie) {
            return;
        }

        var codProdus = $('#modal_cod_produs_pret').val();
        if (!codProdus) {
            alert('Codul produsului nu este disponibil.');
            return;
        }

        var $button = $(this);
        $button.prop('disabled', true);

        $.ajax({
            type: 'GET',
            url: 'get_produs_factura.php',
            data: { q: codProdus },
            dataType: 'json'
        }).done(function(data) {
            if (data.error) {
                alert(data.error);
            } else if (data.pret_achizitie !== null && data.pret_achizitie !== undefined) {
                $('#modal_pret_vanzare').val(data.pret_achizitie).trigger('change');
            } else {
                alert('Prețul de achiziție nu este disponibil pentru acest produs.');
            }
        }).fail(function() {
            alert('Prețul de achiziție nu a putut fi preluat.');
        }).always(function() {
            $button.prop('disabled', disablePreiaPretAchizitie);
        });
    });

    // Resetarea formularului când modalul de editare cantitate este închis
    $('#editQuantityModal').on('hidden.bs.modal', function () {
        $('#editQuantityForm')[0].reset();
    });

    // Resetarea formularului când modalul de editare UM este închis
    $('#editUMModal').on('hidden.bs.modal', function () {
        $('#editUMForm')[0].reset();
    });

    // Resetarea formularului când modalul de editare preț este închis
    $('#editPriceModal').on('hidden.bs.modal', function () {
        $('#editPriceForm')[0].reset();
        $('#modal_denumire_pret').text('');
        $('#modal_cod_produs_pret').val('');
        $('#modal_set_pret_achizitie').prop('disabled', true);
    });
});
</script>

<!-- Script pentru auto-completarea datelor pe baza CUI -->
<script>
document.getElementById('fetch_cui_data').addEventListener('click', function() {
    var cuiInput = document.getElementById('input_cui');
    var cui = cuiInput.value.trim();

    if (!cui) {
        alert('Vă rugăm să introduceți un CUI.');
        return;
    }

    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'get_company_data.php?cui=' + encodeURIComponent(cui), true);

    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var data = JSON.parse(xhr.responseText);
                if (data.error) {
                    alert('Eroare: ' + data.error);
                } else {
                    for (var key in data) {
                        if (data.hasOwnProperty(key)) {
                            var input = document.querySelector('input[name="' + key + '"]');
                            if (input) {
                                if (key === 'cod_fiscal') {
                                    if (data['tva'] === '1') {
                                        input.value = 'RO' + data[key];
                                    } else {
                                        input.value = data[key];
                                    }
                                } else {
                                    input.value = data[key];
                                }
                            }
                        }
                    }
                var tara = document.querySelector('[name="adresa_tara"]'); // merge și pe select
if (tara) {
  tara.value = 'RO';
  if (window.jQuery) { $(tara).trigger('change'); } // ca să se vadă corect în Select2
}

                    if (typeof normalizeFacturaLocalitateBucuresti === 'function') {
                        normalizeFacturaLocalitateBucuresti(false);
                    }

                }
            } catch (e) {
                alert('Eroare la procesarea răspunsului de la server.');
            }
        } else {
            alert('Eroare la cerere. Cod status: ' + xhr.status);
        }
    };

    xhr.onerror = function() {
        alert('Eroare de rețea sau de server.');
    };

    xhr.send();
});
</script>
<script>
// --- SCRIPT ACTUALIZAT PENTRU CAZARE SI TAXA HOTELIERA ---

// Definim variabila pe baza sesiunii PHP
var isClientTaxaHoteliera = <?php 
    echo (isset($_SESSION['client_id']) && 
         ($_SESSION['client_id'] == 4 || $_SESSION['client_id'] == 7  || $_SESSION['client_id']==1005 || $_SESSION['client_id']==1006)) 
         ? 'true' 
         : 'false'; 
?>;

function calculeazaDesignCazare() {
    // 1. Luam numele produsului
    var numeProdus = $('#nume_produs').val();
    
    // 2. Verificam daca contine "CAZARE" SI daca este clientul specific (ID 7)
    if (isClientTaxaHoteliera && numeProdus && numeProdus.toUpperCase().includes("CAZARE")) {
        
        // Afisam elementele specifice
        $('#row_preview_sume').show();
        $('#row_taxa_hoteliera').show();
        $('#row_total_booking').show();

        // Facem inputul de pret cu TVA readonly, deoarece va fi calculat
        $("input[name='pret_cu_tva']").prop('readonly', true).css('background-color', '#e9ecef');
        $('#set_pret_achizitie').prop('disabled', true); 

    } else {
        // Cazul DEFAULT (pentru restul clienților sau alte produse)
        
        // Ascundem tot ce tine de taxa hoteliera
        $('#row_preview_sume').hide();
        $('#row_taxa_hoteliera').hide();
        $('#row_total_booking').hide();
        
        // Revenim la normal: inputul de preț devine EDITABIL
        $("input[name='pret_cu_tva']").prop('readonly', false).css('background-color', '');
        
        // Reactivam butonul daca e cazul (logica veche)
       var pretAchizitie = $('#set_pret_achizitie').data('pret-achizitie');
if (disablePreiaPretAchizitie) {
    $('#set_pret_achizitie').prop('disabled', true);
} else if (pretAchizitie) {
    $('#set_pret_achizitie').prop('disabled', false);
} else {
    $('#set_pret_achizitie').prop('disabled', true);
}
    }
}

// Logica de calcul invers cand se introduce Totalul Booking (ruleaza doar daca inputul e vizibil)
// Logica de calcul invers cand se introduce Totalul Booking (ruleaza doar daca inputul e vizibil)
$('#input_total_booking').on('input keyup change', function() {
    if (!isClientTaxaHoteliera) return; // Siguranta extra

    var totalBooking = parseFloat($(this).val()) || 0;
    var cotaTva = parseFloat($("select[name='cota_tva']").val()) || 0;
    var cantitate = parseFloat($("input[name='cantitate']").val()) || 1;

    if (totalBooking !== 0 && !isNaN(totalBooking)) {
        // Folosim valoarea absolută pentru a forța Prețul Unitar să fie MEREU pozitiv
        var totalBookingAbs = Math.abs(totalBooking);
        var coeficientTotal = 1 + (cotaTva / 100) + 0.02;
        
        // 1. Aflam Valoarea Neta Totala in modul
        var valoareNetaTotalaAbs = totalBookingAbs / coeficientTotal;
        
        // 2. Calculam Taxa (2% din Net) in modul
        var valoareTaxaAbs = valoareNetaTotalaAbs * 0.02;
        
        // 3. Calculam Valoarea Cazarii cu TVA in modul
        var valoareCazareCuTVA_TotalaAbs = totalBookingAbs - valoareTaxaAbs;
        
        // 4. Pretul unitar (per bucata) - întotdeauna POZITIV
        var pretUnitarCazareCuTVA = valoareCazareCuTVA_TotalaAbs / Math.abs(cantitate);

        // Setam valoarea in input-ul formularului (pt trimitere la PHP) la 5 zecimale
        $("input[name='pret_cu_tva']").val(pretUnitarCazareCuTVA.toFixed(5)); 
        
        // Afisare preview vizual cu semn corect
        var semn = (cantitate < 0) ? -1 : 1;
        $('#preview_val_neta').text((valoareNetaTotalaAbs * semn).toFixed(2));
        var valoareTVA = valoareNetaTotalaAbs * (cotaTva / 100);
        $('#preview_val_tva').text((valoareTVA * semn).toFixed(2));
        $('#preview_total_general').text((totalBookingAbs * semn).toFixed(2));
        $('#input_taxa_hoteliera').val((valoareTaxaAbs * semn).toFixed(2) + " Lei");
    } else {
        // Resetăm valorile vizuale dacă e zero sau gol
        $('#preview_val_neta').text('0.00');
        $('#preview_val_tva').text('0.00');
        $('#preview_total_general').text('0.00');
        $('#input_taxa_hoteliera').val('0.00 Lei');
        $("input[name='pret_cu_tva']").val('');
    }
});

// Opțional, ca să recalculeze preview-ul vizual în timp real dacă utilizatorul tastează întâi booking-ul și apoi modifică "Cantitate" pe -1:
$("input[name='cantitate']").on('input change', function() {
    $('#input_total_booking').trigger('change');
});

</script>
<!-- Script pentru ajustări suplimentare pentru facturile de tip 751 -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    var invoiceType = <?php echo json_encode($tip_factura); ?>;
    
    if (invoiceType == 751) {
        var addProductForm = document.getElementById('adaug_produs_form');
        if (addProductForm) {
            addProductForm.style.display = 'none';
        }
        
        var editUmButtons = document.querySelectorAll('.edit-um-button');
        editUmButtons.forEach(function(button) {
            button.setAttribute('disabled', 'disabled');
            button.style.opacity = '0.6';
            button.style.cursor = 'not-allowed';
        });
        
        var editQuantityButtons = document.querySelectorAll('.edit-quantity-button');
        editQuantityButtons.forEach(function(button) {
            button.setAttribute('disabled', 'disabled');
            button.style.opacity = '0.6';
            button.style.cursor = 'not-allowed';
        });

        var editPriceButtons = document.querySelectorAll('.edit-price-button');
        editPriceButtons.forEach(function(button) {
            button.setAttribute('disabled', 'disabled');
            button.style.opacity = '0.6';
            button.style.cursor = 'not-allowed';
        });
        
        var deleteButtons = document.querySelectorAll('.delete_item_button');
        deleteButtons.forEach(function(button) {
            button.setAttribute('disabled', 'disabled');
            button.style.opacity = '0.6';
            button.style.cursor = 'not-allowed';
        });
        
        var container = document.querySelector('.container-fluid');
        if (container) {
            var message = document.createElement('div');
            message.className = 'alert alert-warning';
            message.style.marginBottom = '20px';
  message.textContent = 'Continutul facturilor de tip 751 cu bon fiscal nu se poate modifica. Doar antetul acestora. \nÎn cazul în care observați o eroare în conținutul facturii, aceasta poate fi ștearsă din <a href="facturi.php">lista facturilor</a> urmând să faceți una nouă.';
            message.innerHTML = message.textContent.replace('<a href="facturi.php">lista facturilor</a>', '<a href="facturi.php">lista facturilor</a>');
                        container.insertBefore(message, container.firstChild);
        }
    }
});
</script>

<!-- Include Virtual Keyboard (reiterare a includerii, dacă este necesar) -->
<link rel="stylesheet" href="css/dist/css/keyboard.min.css">
<script src="css/dist/js/jquery.keyboard.min.js"></script>

<!-- Footer -->
<?php include('footer.php'); ?>
<?php include('widget_touch.php'); ?>
<!-- End of Footer -->
<!-- Modal pentru editarea cantității -->
<div class="modal fade" id="editQuantityModal" tabindex="-1" role="dialog" aria-labelledby="editQuantityModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form id="editQuantityForm" method="post" action="modifica_cantitate_factura.php">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="editQuantityModalLabel">Editare Cantitate</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Închide">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
            <input type="hidden" name="id_vanz" id="modal_id_vanz">
            <div class="form-group">
                <label for="modal_cantitate">Cantitate</label>
<input type="number" step="0.00001" class="form-control" id="modal_cantitate" name="cantitate" required>
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Anulează</button>
          <button type="submit" class="btn btn-primary">Salvează modificările</button>
        </div>
      </div>
    </form>
  </div>
</div>


<!-- Modal pentru editarea Unității de Măsură am scos tabindex -1 ca sa mearga select2 in modal-->
<div class="modal fade" id="editUMModal" role="dialog" aria-labelledby="editUMModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form id="editUMForm" method="post" action="modifica_um_factura.php">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="editUMModalLabel">Editare Unitate de Măsură</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Închide">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
            <input type="hidden" name="id_vanz" id="modal_id_vanz_um">
            <div class="form-group">
                <label for="modal_um">Unitate de Măsură</label>
                <select class='select2 form-control' required name='um' id="modal_um">
                    <option value="">Selectați U.M.</option>
                    <?php foreach ($sorted_data as $cod_um => $denumire_um): ?>
                        <option value="<?= htmlspecialchars($cod_um) ?>">
                            <?= htmlspecialchars($denumire_um) . ' - ' . htmlspecialchars($cod_um) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Anulează</button>
          <button type="submit" class="btn btn-primary">Salvează modificările</button>
        </div>
      </div>
    </form>
  </div>
</div>


<!-- Modal pentru editarea prețului -->
<div class="modal fade" id="editPriceModal" tabindex="-1" role="dialog" aria-labelledby="editPriceModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form id="editPriceForm" method="post" action="modifica_pret_factura.php">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="editPriceModalLabel">Editare Preț</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Închide">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
            <input type="hidden" name="id_vanz" id="modal_id_vanz_pret">
            <input type="hidden" id="modal_cod_produs_pret">
            <div class="form-group">
                <label>Produs</label>
                <div id="modal_denumire_pret" class="font-weight-bold"></div>
            </div>
            <div class="form-group">
                <label for="modal_pret_vanzare">Preț nou (cu TVA)</label>
                <div class="input-group">
                    <input type="number" step="0.00001" min="0" class="form-control" id="modal_pret_vanzare" name="pret_vanzare" required>
                    <div class="input-group-append">
                        <button type="button" class="btn btn-info" id="modal_set_pret_achizitie" <?= $disable_preia_pret_achizitie ? 'disabled' : '' ?>>
                            Preia preț achiziție cu TVA
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Anulează</button>
          <button type="submit" class="btn btn-primary">Salvează modificările</button>
        </div>
      </div>
    </form>
  </div>
</div>


<!-- Modal Trimite Email Factura -->
<div class="modal fade" id="modal_email_factura" tabindex="-1" role="dialog" aria-labelledby="emailFacturaLabel">
  <div class="modal-dialog" role="document">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title" id="emailFacturaLabel">
            <i class="fas fa-envelope"></i> Trimite factura pe email
        </h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Închide">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <div class="modal-body">
        <p>
            Factura
            <strong><?php echo htmlspecialchars($serie_factura . ' ' . $nr_factura); ?></strong>
            va fi trimisă ca atașament PDF.
        </p>

        <div class="form-group">
          <label for="ef_email_dest">Adresă email destinatar:</label>
          <input type="email"
                 class="form-control"
                 id="ef_email_dest"
                 value="<?php echo $email_firma_factura; ?>"
                 placeholder="exemplu@email.ro">
        </div>

        <div id="ef_msg"></div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Anulează</button>
        <button type="button" class="btn btn-info" id="ef_btn_trimite">
          <i class="fas fa-paper-plane"></i> Trimite
        </button>
      </div>

    </div>
  </div>
</div>
<!-- End Modal Trimite Email Factura -->
</body>
</html>
