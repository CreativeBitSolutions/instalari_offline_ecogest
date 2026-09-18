<?php //elemente_bon.php
// =========== LOGICA PHP ORIGINALĂ (NEATINSĂ) ===========
include('session.php');
// În fișierul principal, bonul se preia din sesiune. Aici, îl luăm din GET, cum ai specificat.
$nr_bon = $_GET['nr_bon'];
$cod_masa = $_GET['cod_masa']; // Asigură-te că transmiți și cod_masa în AJAX call dacă e necesar
$client_agecs = $_SESSION['client_id'] ?? null;
$ascunde_actiuni = ($client_agecs == 22) ? " style='display:none'" : "";

// Preluare setari firma (logica originală)
$date_firma = "SELECT mod_listare, vanzare_sub_stoc, ajustare_adaos from $tabel_final_date_firma";
$date_firma_stmt = $pdo->prepare($date_firma);
$date_firma_stmt->execute();
if ($row = $date_firma_stmt->fetch(PDO::FETCH_ASSOC)){
    $_SESSION['vanzare_sub_stoc'] = $row['vanzare_sub_stoc'];
    $_SESSION['mod_listare'] = $row['mod_listare'];
    $_SESSION['ajustare_adaos'] = $row['ajustare_adaos'];
}
?>

<div class="panel-header">Bon Curent</div>

<ul class="receipt-items" id="lista_produse_bon">
<?php
// Am păstrat modificarea esențială de la `n.nume` la `dn.nume_produs`
$f_sql = "SELECT n.pret_cu_tva as pret_initial, dn.preparat, dn.pachet, dn.discount, dn.cod_p, dn.nume_produs as nume, n.cota_tva, n.um, dn.cantitate, dn.tva_col, dn.pret_vanzare, dn.valoare_vanzare, dn.valoare_vanzare_cu_tva, dn.id_vanz
          FROM $tabel_final_det_note dn
          JOIN $tabel_final_nomenclator n ON dn.cod_p = n.cod_produs
          WHERE dn.nr_bon = :nr_bon
          ORDER BY dn.id_vanz";
$f_stmt = $pdo->prepare($f_sql);
$f_stmt->execute(['nr_bon' => $nr_bon]);

while ($row = $f_stmt->fetch(PDO::FETCH_ASSOC)) {
    // Preluare variabile (logica originală)
    $produs = $row['nume'];
    $cantitate = round($row['cantitate'],2);
    $pret_vanzare = $row['pret_vanzare'];
    $pret_initial = round($row['pret_initial'], 2);
    $valoare_vanzare_c_tva = round($row['valoare_vanzare_cu_tva'], 2);
    $id_vanz = $row['id_vanz'];
    $codul_produsului = $row['cod_p'];
    $cota = $row['cota_tva'];
// Determină unitatea de măsură pentru afișare
$unitate_masura = ($row['um'] == 'H87' || empty($row['um'])) ? 'buc' : htmlspecialchars($row['um']);
    // Afișare în noul format de listă
    echo "
    <li class='receipt-item' id='item-{$id_vanz}'>
        <div class='product-info'>
            <div class='product-name'>" . htmlspecialchars($produs, ENT_QUOTES, 'UTF-8') . "</div>
<div class='product-price'>{$pret_vanzare} RON / {$unitate_masura}</div>";
    // Afișează prețul inițial dacă e diferit (logica originală)
    if ($pret_vanzare != $pret_initial) {
        echo "<div style='font-size:0.8em; color:gray; margin-top:2px;'>Preț original: {$pret_initial} RON</div>";
    }

   echo "
        </div>
        <button name='{$id_vanz}' value='{$codul_produsului}' data-value='{$cantitate}' class='btn btn-primary' title='Modifică cantitatea'>
            x {$cantitate}
        </button>
        <div class='item-value'>
            {$valoare_vanzare_c_tva} RON
        </div>
        <div>
            <button name='{$id_vanz}' value='{$codul_produsului}' data-value='{$cota}' class='btn btn-sm btn-success discount-btn discount mb-1'{$ascunde_actiuni} title='Aplică discount'>%</button>
            
       <button type='button' class='btn btn-sm btn-info edit-product-btn mb-1'{$ascunde_actiuni} 
    data-idvanz='{$id_vanz}' 
    data-codp='{$codul_produsului}'
    data-current-name='" . htmlspecialchars($produs, ENT_QUOTES, 'UTF-8') . "'
    data-current-price='{$pret_vanzare}'
    title='Modifică Produs'>
    <i class='fas fa-pencil-alt'></i>
</button>
            
            <button class='btn btn-sm btn-danger delete-btn sterge_prod' value='{$id_vanz}' data-value='$nr_bon' title='Șterge produs'>
                <i class='fas fa-trash'></i>
            </button>
        </div>
    </li>";

}
?>
</ul>

<div class="receipt-totals">
    <?php
    $total_sql = "SELECT sum(valoare_vanzare_cu_tva) as total FROM $tabel_final_det_note WHERE nr_bon=:nr_bon";
    $total_stmt = $pdo->prepare($total_sql);
    $total_stmt->execute(['nr_bon' => $nr_bon]);
    $total_row = $total_stmt->fetch(PDO::FETCH_ASSOC);
    $total_val_vz_cu_tva = $total_row['total'] ? round($total_row['total'], 2) : 0.00;
    $cif_curent = htmlspecialchars($_SESSION['cif_client'] ?? '', ENT_QUOTES);
    ?>
    <ul class="nav nav-tabs nav-fill" id="footer-tab" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="total-tab-link" data-toggle="tab" href="#total-tab-content" role="tab" aria-controls="total-tab-content" aria-selected="true">💰 Totaluri</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="cif-tab-link" data-toggle="tab" href="#cif-tab-content" role="tab" aria-controls="cif-tab-content" aria-selected="false">🧾 C.I.F.</a>
        </li>
    </ul>
    <div class="tab-content" id="footer-tab-content">
        <div class="tab-pane fade show active" id="total-tab-content" role="tabpanel" aria-labelledby="total-tab-link">
            <div class="totals-inline-grid">
                <div class="total-item">
                    <label></label>
                    <span id="total_de_incasat_display" style="font-size:1.5em;"><?php echo number_format($total_val_vz_cu_tva, 2, '.', ''); ?></span>
                </div>
                <div class="total-item">
                    <label for="suma-incasata-input">S:</label>
                    <input type="text" id="suma-incasata-input" class="form-control form-control-sm" placeholder="0.00" readonly value="<?php echo number_format($total_val_vz_cu_tva, 2, '.', ''); ?>">
                </div>
                <div class="total-item">
                    <label>R:</label>
                    <span id="rest-de-dat-display">0.00</span>
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="cif-tab-content" role="tabpanel" aria-labelledby="cif-tab-link">
            <div class="cif-container">
                <div class="input-group">
                    <input type="text" id="cif_client_input" class="form-control offline-cui-trigger" placeholder="Apăsați pentru verificare CUI" value="<?php echo $cif_curent; ?>" readonly>
                    <div class="input-group-append">
                        <button id="cif-kbd-btn" type="button" class="btn btn-secondary cif-kbd-btn" title="Verifică CUI client">
                            <i class="fas fa-search" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
