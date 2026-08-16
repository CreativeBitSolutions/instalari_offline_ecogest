<?php //vanzare_magazin
include('session.php');
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.log');
error_reporting(E_ALL);
// === ADAUGAT: INSTRUCTIUNI PENTRU A PREVENI CACHE-UL BROWSERULUI ===
// Aceste headere forteaza browserul sa ceara mereu o versiune noua a paginii de la server.
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // O data din trecut
header("Pragma: no-cache");
// === SFARSIT BLOC ADAUGAT ===
$cod_locatie = $_SESSION['cod_locatie'];
$cod_masa = $cod_locatie;  // In modul magazin, cod_masa este identic cu cod_locatie
$_SESSION['cod_locatie'] = $cod_locatie;
date_default_timezone_set("Europe/Bucharest");
$adm_id = $_SESSION['admin_id'];

// Preluare nume operator
$dsql = "SELECT admin_firstname, admin_lastname FROM $tabel_final_admins where admin_id=:adm_id";
$dstmt = $pdo->prepare($dsql);
$dstmt->execute(['adm_id' => $adm_id]);
$admin_user = $dstmt->fetch(PDO::FETCH_ASSOC);
$admin_firstname = $admin_user['admin_firstname'] ?? 'N/A';
$admin_lastname = $admin_user['admin_lastname'] ?? 'N/A';

// Creare/Preluare bon de vanzare
$ccom_sql = "SELECT nrbon FROM $tabel_final_note WHERE status='S' AND operator=:adm_id AND locatie=:locatie";
$ccom_stmt = $pdo->prepare($ccom_sql);
$ccom_stmt->execute(['adm_id' => $adm_id, 'locatie' => $cod_locatie]);
$existing_bon = $ccom_stmt->fetch(PDO::FETCH_ASSOC);

if (!$existing_bon) {
    $sql = "INSERT INTO $tabel_final_note(operator,locatie,cod_masa) VALUES(:adm_id, :locatie, :cod_masa)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['adm_id' => $adm_id, 'locatie' => $cod_locatie, 'cod_masa' => $cod_masa]);
    $nr_bon = $pdo->lastInsertId();
    $_SESSION['nr_bon'] = $nr_bon;
} else {
    $nr_bon = $existing_bon['nrbon'];
    $_SESSION['nr_bon'] = $nr_bon;
}

// Setari firma
$date_firma_sql = "SELECT mod_listare, vanzare_sub_stoc, ajustare_adaos FROM $tabel_final_date_firma LIMIT 1";
$date_firma_stmt = $pdo->query($date_firma_sql);
$date_firma = $date_firma_stmt->fetch(PDO::FETCH_ASSOC);
if ($date_firma) {
    $_SESSION['vanzare_sub_stoc'] = $date_firma['vanzare_sub_stoc'];
    $_SESSION['mod_listare'] = $date_firma['mod_listare'];
    $_SESSION['ajustare_adaos'] = $date_firma['ajustare_adaos'];
}
// LOGICA PROCESARE FORMULAR DISCOUNT
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificăm dacă este o acțiune de discount individual
    if (isset($_POST['apl_disc_proc']) || isset($_POST['apl_disc_fix'])) {
        $id_vz = $_POST['idvanzare'];
        $adm_id = $_SESSION['admin_id'];

        try {
            // Începem o tranzacție pentru integritatea datelor
            $pdo->beginTransaction();

            // Preluăm starea inițială a produsului de pe bon
            $sql_select = "SELECT pret_vanzare, cantitate, cota_tva FROM $tabel_final_det_note WHERE id_vanz = :id_vz FOR UPDATE";
            $stmt_select = $pdo->prepare($sql_select);
            $stmt_select->execute(['id_vz' => $id_vz]);
            $row = $stmt_select->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $pret_unitar_initial = (float)$row['pret_vanzare'];
                $cantitate = (float)$row['cantitate'];
                $cota_tva = (int)$row['cota_tva'];
                
                $discount_unitar = 0;
                $tip_discount = '';
                $valoare_procent = null;

                // Determinăm tipul și valoarea discountului
                if (isset($_POST['apl_disc_proc'])) {
                    $procent = (float)$_POST['val_procent'];
                    $discount_unitar = $pret_unitar_initial * $procent / 100;
                    $tip_discount = 'procentual';
                    $valoare_procent = $procent;
                } elseif (isset($_POST['apl_disc_fix'])) {
                    $discount_unitar = (float)$_POST['valoare_fixa'];
                    $tip_discount = 'valoric_fix';
                }

                // Validăm și aplicăm discountul
                if ($discount_unitar > 0 && $discount_unitar <= $pret_unitar_initial) {
                    $pret_unitar_final = $pret_unitar_initial - $discount_unitar;
                    $discount_total_ron = $discount_unitar * $cantitate;

                    // Recalculăm valorile pentru tabelul det_note
                    $new_valoare_vanzare_cu_tva = round($pret_unitar_final * $cantitate, 2);
                    $new_tva_col = round($new_valoare_vanzare_cu_tva * $cota_tva / (100 + $cota_tva), 2);
                    $new_valoare_vanzare = round($new_valoare_vanzare_cu_tva - $new_tva_col, 2);

                    // INSERARE ÎN TABELUL DE AUDIT (NOU)
                    $sql_insert_discount = "INSERT INTO discounturi_acordate 
                                            (id_vanz, id_operator, tip_discount, valoare_procent, valoare_discount_ron, pret_unitar_initial, pret_unitar_final) 
                                            VALUES (:id_vanz, :id_op, :tip, :proc, :val_ron, :pret_init, :pret_fin)";
                    $stmt_insert = $pdo->prepare($sql_insert_discount);
                    $stmt_insert->execute([
                        ':id_vanz' => $id_vz,
                        ':id_op' => $adm_id,
                        ':tip' => $tip_discount,
                        ':proc' => $valoare_procent,
                        ':val_ron' => $discount_total_ron,
                        ':pret_init' => $pret_unitar_initial,
                        ':pret_fin' => $pret_unitar_final
                    ]);

                    // ACTUALIZARE ÎN TABELUL ORIGINAL (det_note)
                    $update_sql = "UPDATE $tabel_final_det_note 
                                   SET pret_vanzare = :new_pret, discount = :disc_total, valoare_vanzare_cu_tva = :new_val_cu_tva, 
                                       tva_col = :new_tva, valoare_vanzare = :new_val_fara_tva
                                   WHERE id_vanz = :id_vz";
                    $update_stmt = $pdo->prepare($update_sql);
                    $update_stmt->execute([
                        'new_pret' => $pret_unitar_final, 
                        'disc_total' => $discount_total_ron, 
                        'new_val_cu_tva' => $new_valoare_vanzare_cu_tva,
                        'new_tva' => $new_tva_col, 
                        'new_val_fara_tva' => $new_valoare_vanzare, 
                        'id_vz' => $id_vz
                    ]);

                    $pdo->commit(); // Confirmăm ambele operațiuni
                } else {
                    $pdo->rollBack(); // Anulăm tranzacția dacă discountul nu este valid
                    if ($discount_unitar > $pret_unitar_initial) {
                        echo '<script>alert("Valoarea discountului unitar depășește prețul unitar!");</script>';
                    }
                }
            } else {
                 $pdo->rollBack(); // Anulăm dacă produsul nu a fost găsit
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Eroare la aplicare discount individual: " . $e->getMessage());
            echo '<script>alert("A apărut o eroare la aplicarea discountului.");</script>';
        }

        // Reîncărcăm pagina pentru a afișa modificările
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Calcul total pentru modalul de plata mixta
$total_sql = "SELECT sum(valoare_vanzare_cu_tva - discount) as total FROM $tabel_final_det_note WHERE nr_bon=:nr_bon";
$total_stmt = $pdo->prepare($total_sql);
$total_stmt->execute(['nr_bon' => $nr_bon]);
$total_data = $total_stmt->fetch(PDO::FETCH_ASSOC);
$total_val_vz_cu_tva = $total_data['total'] ?? 0;
$total_val_vz_cu_tva = round($total_val_vz_cu_tva, 2);


// Preluare CIF din sesiune pentru a-l pasa catre JavaScript
$cif_sesiune = $_SESSION['cif_client'] ?? '';
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Vânzare Modernă</title>
    
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
        <link rel="stylesheet" href="vanzare_css.css">
<style>
    /* Fix pentru a permite scroll în pagină când sunt deschise mai multe modale */
    .modal {
      overflow-y: auto;
    }
    </style>
</head>
<body>
    <div id="loading"></div>

    <div class="page-container">
        <header class="page-header">
            <?php
            $client_agecs = $_SESSION['client_id'] ?? null;
            if ($client_agecs == 2 || $client_agecs == 8) echo "<a href='vanzare_facturi.php'><button class='header-btn'>🧾 Facturi</button></a>";
            ?>
           <?php if ($client_agecs != 22): ?>
    <button data-toggle="modal" data-target="#sume_sertar" class="header-btn">💰 Sume Tura</button>
    <button data-toggle="modal" data-target="#sume_zi_curenta_modal" class="header-btn">📈 Sume Zi</button>
            <button data-toggle="modal" data-target="#DiscountGlobal" class="header-btn">🏷️ Discount </button>

<?php endif; ?>
            <?php
            $btnText = 'Stoc';
            if ($client_agecs == 6) $btnText = 'Verifică / Recalculează Stoc';
            echo "<button type='button' id='verifica_stoc_btn' data-toggle='modal' data-target='#verificaStocModal' class='header-btn'>📦 $btnText</button>";
            ?>
            <button data-toggle="modal" data-target="#relistareboncasamarcat" class="header-btn">📠 Retrimite Bon la CM</button>
            <a href='reglare_casa_marcat.php'><button class='header-btn'>🔨 Reglare dif. CM</button></a>
            
            <div class="grup-actiuni" style="margin-left: auto; display: flex; gap: 10px; align-items: center;">
               <?php if ($client_agecs != 22): ?>
    <?php
    $bon_sql = "SELECT COUNT(*) FROM $tabel_final_note WHERE cod_inchidere=0 AND status='F' AND locatie=:locatie AND operator=:adm_id";
    $bon_stmt = $pdo->prepare($bon_sql);
    $bon_stmt->execute(['locatie' => $cod_locatie, 'adm_id' => $adm_id]);
    if ((int)$bon_stmt->fetchColumn() >= 1) {
        echo "<form method='POST' action='procesare_vanzare.php' class='m-0'><button type='submit' class='header-btn btn-warning' name='inchidere_zi'>🌇 Închide Tura</button></form>";
    }
    ?>
<?php endif; ?>


                <?php
                $sql_total = "SELECT COUNT(*) FROM $tabel_final_note WHERE locatie = :locatie AND status = 'F' AND nr_raport_z = 0";
                $stmt_total = $pdo->prepare($sql_total);
                $stmt_total->execute(['locatie' => $cod_locatie]);
                $total = $stmt_total->fetchColumn();

                $sql_valid = "SELECT COUNT(*) FROM $tabel_final_note WHERE locatie = :locatie AND status = 'F' AND nr_raport_z = 0 AND cod_inchidere != 0";
                $stmt_valid = $pdo->prepare($sql_valid);
                $stmt_valid->execute(['locatie' => $cod_locatie]);
                $valid = $stmt_valid->fetchColumn();
                
                if ($total > 0 && $total == $valid) {
                    echo '<button type="button" class="header-btn btn-danger" data-toggle="modal" data-target="#raportZModal">📊 Raport Z</button>';
                }
                ?>
                 <div class="user-info">
                    <span>👤 <?php echo htmlspecialchars($admin_firstname . ' ' . $admin_lastname); ?></span>
                    <span>📄 Bon: <?php echo htmlspecialchars($nr_bon); ?></span>
                </div>
                <form method="POST" action="procesare_vanzare.php" class="m-0">
                    <button name="deconectare" class='header-btn btn-dark' type="submit">🚪 Deconectare</button>
                </form>
                
            </div>
        </header>

        <main class="main-content">
            <div class="left-panel" id="bon-curent-panel">
                </div>
            
            <div class="right-panel">
                <div class="category-wrapper">
                    <button id="scroll-cat-left" class="scroll-btn"><i class="fas fa-chevron-left"></i></button>
                    <div id="category-tabs">
                        <?php $client_agecs = (int)($_SESSION['client_id'] ?? 0); ?>
<button class="category-tab-btn active" data-value="all">TOATE</button>

<?php if (in_array($client_agecs, [8, 17])): ?>
  <button class="category-tab-btn" data-value="__MENIURI__">MENIURI</button>
<?php endif; ?>

                        <?php
                            $categ_sql = "SELECT id_categorie, den_categ FROM $tabel_final_categorii WHERE se_vinde='1' ORDER BY den_categ ASC;";
                            $categ_stmt = $pdo->query($categ_sql);
                            while ($row = $categ_stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo "<button class='category-tab-btn' data-value='{$row['id_categorie']}'>{$row['den_categ']}</button>";
                            }
                        ?>
                    </div>
                    <button id="scroll-cat-right" class="scroll-btn"><i class="fas fa-chevron-right"></i></button>
                </div>

                <div id="product-controls">
                    
                
<div class="form-group">
    <label for="prod_filter" class="label-nume">
        Caută Nume <small class="shortcut-hint">(Ctrl)</small>
    </label>
    <div class="input-group">
        <input type="text" id="prod_filter" autocomplete="off" class="form-control">
        <div class="input-group-append">
            <button class="btn btn-secondary" type="button" data-toggle="modal" data-target="#text-keyboard-modal" title="Deschide tastatura virtuală">
                <i class="fas fa-keyboard"></i>
            </button>
        </div>
    </div>
</div>


                 <?php if (($_SESSION['client_id'] ?? null) != 17): ?>
<div class="form-group">
    <label for="prod_filter_cod_bare" class="label-codbare">
        Caută Cod Bare <small class="shortcut-hint">(Ctrl) ( / x2 pentru total)</small>
    </label>
    <input type="text" id="prod_filter_cod_bare" class="form-control">
</div>
<?php endif; ?>
                    <div class="form-group">
                        <label for="cantitate_de_adaugat_prod" class="label-cantitate">
                            Cantitate <small class="shortcut-hint">(Shift + -)</small>
                        </label>
                        <div class="input-group">
<?php $initialQty = ($_SESSION['client_id'] == 6) ? '' : '1'; ?>
<input type="text"
       id="cantitate_de_adaugat_prod"
       inputmode="decimal"
       pattern="^-?\d*[.,]?\d{0,5}$"
       value="<?php echo $initialQty; ?>"
       class="form-control">

                            <div class="input-group-append">
                                <button id="btn_modifica_cantitate_modal" class="btn btn-secondary" type="button" title="Modifică Cantitatea (Shift)"><i class="fas fa-edit"></i></button>
                                  <?php if (!in_array($_SESSION['client_id'], [17])): ?>
                <button id="btn_citeste_cantar_nou" class="btn btn-info" type="button" title="Citește Cântar (Caps Lock)"><i class="fa fa-balance-scale"></i></button>
            <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="product-grid-wrapper">
                    <div class="product-grid" id="product-list-container">
                    </div>
                    <div class="product-scroll-buttons">
                        <button id="scroll-prod-up" class="scroll-btn-v"><i class="fas fa-chevron-up"></i></button>
                        <button id="scroll-prod-down" class="scroll-btn-v"><i class="fas fa-chevron-down"></i></button>
                    </div>
                </div>
                
            </div>
        </main>

       <footer class="page-footer">
    <div class="grup-stanga">
        <form id="plata_form" method="POST" action="procesare_vanzare.php">
            <input type="hidden" name="masa_curenta" value="<?php echo $cod_masa; ?>">
            <input type="hidden" id="cif_client_hidden" name="cif_client">
            <button class="footer-btn btn-success" type="submit" name="finaliz_bon" value="numerar">💵 Numerar</button>
            <button class="footer-btn btn-primary" type="submit" name="finaliz_bon" value="card">💳 Card</button>
            <button class="footer-btn btn-info" type="button" data-toggle="modal" data-target="#Plata_numerar_si_card">💶 Mix</button>
            
            <?php
            $client_agecs = $_SESSION['client_id'] ?? null;
            // Ascundem butonul Protocol pentru clientii 17, 18, 21
            if (!in_array($client_agecs, [17, 18, 21])): ?>
                <button class="footer-btn btn-secondary" type="submit" name="finaliz_bon" value="protocol">📝 Protocol</button>
            <?php endif; ?>

          <?php
    // Adaugam butonul ONLINE (glovo) doar pentru clientii 17 sau 8
    if (in_array($client_agecs, [17, 8])): ?>
        <button class="footer-btn btn-warning" type="submit" name="finaliz_bon" value="glovo">🌐 ONLINE</button>
    <?php endif; 
    ?>

        </form>
    </div>

    <div class="grup-dreapta">
        </div>
</footer>
    </div> 

    <div class="modal fade" id="Discount" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Aplică Discount</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <form method='POST' action="vanzare_magazin.php">
                        <input type="hidden" name='idvanzare' />
                        <div class="form-group">
                            <label for="val_procent_individual_input">Procent discount (%)</label>
                            <div class="input-group">
                                <input class="form-control" max='100' step='0.01' min='0' value='0' type='number' name='val_procent' id="val_procent_individual_input">
                                <div class="input-group-append">
                                    <button class="btn btn-secondary" type="button" data-toggle="modal" data-target="#keyboardModalIndividualProcentual" title="Deschide tastatura virtuală">
                                        <i class="fas fa-keyboard"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <button class='btn btn-primary btn-block mb-3' type="submit" name="apl_disc_proc">Aplică %</button>
                        <hr>
                        <div class="form-group">
                            <label for="valoare_fixa_individual_input">Valoare discount (RON)</label>
                            <div class="input-group">
                                <input type='number' step='0.01' class='form-control' name='valoare_fixa' id="valoare_fixa_individual_input">
                                 <div class="input-group-append">
                                    <button class="btn btn-secondary" type="button" data-toggle="modal" data-target="#keyboardModalIndividualFix" title="Deschide tastatura virtuală">
                                        <i class="fas fa-keyboard"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <button class='btn btn-primary btn-block' type="submit" name="apl_disc_fix">Aplică Valoric</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="Plata_numerar_si_card" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Încasare Numerar & Card</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div><div class="modal-body"><form method="POST" action="procesare_vanzare.php"><input type="hidden" name="masa_curenta" value="<?php echo $cod_masa; ?>"><div class="form-group"><label>Total de plată</label><input readonly type="number" step='0.01' name="total" value="<?php echo $total_val_vz_cu_tva; ?>" class="form-control" id="totalmixt"></div><div class="form-group"><label>Numerar</label><input type="number" step='0.01' name="numerar"  value="<?php echo $total_val_vz_cu_tva; ?>" min="0" class="form-control" id="numerar" onchange="updateCard()"></div><div class="form-group"><label>Card</label><input type="number" step='0.01' min="0" value="0" name="card" class="form-control"  onchange="updateNumerar()" id="card"></div><div class="form-group"><label>CIF CLIENT</label><input type="text" maxlength='10' name="cif_client_m" class="form-control" placeholder="Opțional..."></div><div class="modal-footer mt-3 p-0 border-0"><button class='btn btn-primary btn-block' type="submit" name="finaliz_bon" value="numerar_si_card">Finalizare Bon</button></div></form></div></div></div></div>
    <?php include('modal_sume_sertar.php');?>
    <div class="modal fade" id="verificaStocModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Verifică Stoc</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div><div class="modal-body p-0"><iframe src="verifica_stoc_iframe.php" style="border:0; width:100%; height:400px;"></iframe></div></div></div></div>
    <div class="modal fade" id="relistareboncasamarcat" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Atentie! Se va retrimite bon fiscal către casa de marcat</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div><div class="modal-body"><form method="POST" action="casa_marcat_vanzare.php"><div class="form-group"><label for="notaSelect">Selectează nota</label><select class="form-control" name="nota_de_relistat" id="notaSelect">
    <option value="">-- Alege --</option>
    <?php
    $sql_relist = "
        SELECT nrbon, data_bon, ora_bon, 
               (valoare_vanzare_cu_tva - discount) as val,
               numerar, card 
        FROM $tabel_final_note 
        WHERE cod_inchidere = 0 
          AND status = 'F' 
          AND CONCAT(data_bon, ' ', ora_bon) >= DATE_SUB(NOW(), INTERVAL 24 HOUR) 
          AND operator = :adm_id 
          AND locatie = :locatie 
        ORDER BY nrbon DESC
    ";

    $stmt_relist = $pdo->prepare($sql_relist);
    $stmt_relist->execute(['adm_id' => $adm_id, 'locatie' => $cod_locatie]);

    foreach ($stmt_relist->fetchAll(PDO::FETCH_ASSOC) as $note) {
        $val_format = number_format($note['val'], 2);
        $numerar_format = number_format($note['numerar'], 2);
        $card_format = number_format($note['card'], 2);
        echo "<option value='{$note['nrbon']}'>Bon {$note['nrbon']} / {$note['data_bon']} {$note['ora_bon']} / Val: {$val_format} RON / Numerar: {$numerar_format} RON / Card: {$card_format} RON</option>";
    }
    ?>
</select></div><div id="detNoteDetails" class="mt-3"></div><div class="modal-footer mt-3 p-0 border-0"><button type="submit" name="relistare_nota" class="btn btn-primary" value="Relisteaza">Relistează</button></div></form></div></div></div></div>
    <div class="modal fade" id="continuati" tabindex="-1" data-backdrop="static" data-keyboard="false"><div class="modal-dialog"><div class="modal-content"><div class="modal-body text-center"><h2 class='my-4'>Ai fost inactiv. Apasă pentru a continua.</h2><a class='btn btn-primary btn-lg' href='vanzare_magazin.php'>Continuă</a></div></div></div></div>
    
<div class="modal fade" id="raportZModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="raportZModalLabel">Completează Valorile Raport Z</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Închide">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <?php
                // Preluare totaluri pentru bonurile cu tură închisă, dar fără raport Z generat
                $sql_z = "SELECT 
                                     COALESCE(SUM(numerar), 0) AS total_numerar,
                                     COALESCE(SUM(card), 0) AS total_card,
                                     COALESCE(SUM(tichete), 0) AS total_tichete,
                                                                          COALESCE(SUM(glovo), 0) AS total_online

                                  FROM $tabel_final_note  
                                  WHERE status='F' AND locatie=:locatie AND nr_raport_z=0 AND cod_inchidere!=0";
                $stmt_z = $pdo->prepare($sql_z);
                $stmt_z->execute(['locatie' => $cod_locatie]);
                $sum_data_z = $stmt_z->fetch(PDO::FETCH_ASSOC);

                $total_numerar_z = number_format($sum_data_z['total_numerar'], 2, '.', '');
                $total_card_z    = number_format($sum_data_z['total_card'], 2, '.', '');
                $total_tichete_z = number_format($sum_data_z['total_tichete'], 2, '.', '');
                                $total_online_z = number_format($sum_data_z['total_online'], 2, '.', '');

            ?>
            <div class="modal-body">
                <form method="POST" id="raportZForm" action="vanzare_inchidere_zi.php">
                    <div class="form-group">
                        <label for="numerarInput">Numerar</label>
                        <input type="number" step="0.01" min="0" class="form-control" value="<?php echo $total_numerar_z; ?>" name="numerar" required>
                    </div>
                    <div class="form-group">
                        <label for="cardInput">Card</label>
                        <input type="number" step="0.01" min="0" class="form-control" value="<?php echo $total_card_z; ?>" name="card" required>
                    </div>
                    <div class="form-group">
                        <label for="ticheteMasaInput">Tichete Masă</label>
                        <input type="number" step="0.01" min="0" class="form-control" value="<?php echo $total_tichete_z; ?>" name="tichete_masa" required>
                    </div>
                         <div class="form-group">
                        <label for="creditInput">Credit</label>
                        <input type="number" step="0.01" min="0" class="form-control" value="0" name="credit" required>
                         </div>
                         <div class="form-group">
                        <label for="ticheteValoriceInput">Tichete Valorice</label>
                        <input type="number" step="0.01" min="0" class="form-control" value="0" name="tichete_valorice" required>
                         </div>
                         <div class="form-group">
                        <label for="voucherInput">Voucher</label>
                        <input type="number" step="0.01" min="0" class="form-control" value="0" name="voucher" required>
                         </div>
                           <div class="form-group">
                        <label for="platamodernaInput">Plata modernă (Online)</label>
                        <input type="number" step="0.01" min="0" class="form-control"  value="<?php echo $total_online_z; ?>" name="plata_moderna" required>
                         </div>
                         <div class="form-group">
                        <label for="nrRaportZInput">Nr. raport Z casa de marcat</label>
                        <input type="number" min="1" class="form-control" value="" name="nr_raport_z" required>
                         </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Anulează</button>
                <button type="submit" form="raportZForm" class="btn btn-danger" name="submit_raportz" value="Submit">🔥 Închide Ziua Complet</button>
            </div>
        </div>
    </div>
</div>

<?php include('modal_discount_global.php');?>
<?php include('modal_keyboard_procentual.php');?>
<?php include('modal_keyboard_fix.php');?>
<?php include('modal_keyboard_individual_procentual.php');?>
<?php include('modal_keyboard_individual_fix.php');?>
<?php include('modal_sume_zi_curenta.php');?>
<div class="modal fade" id="cif-keyboard-modal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Introduceți C.I.F.</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <input type="text" id="cif-keyboard-display" class="form-control" placeholder="Introduceți..." readonly>
                    <div class="cif-keyboard mt-3">
                        <button class="btn btn-light key" data-key="-">-</button>
                        <button class="btn btn-light key" data-key="1">1</button>
                        <button class="btn btn-light key" data-key="2">2</button>
                        <button class="btn btn-light key" data-key="3">3</button>
                        <button class="btn btn-light key" data-key="4">4</button>
                        <button class="btn btn-light key" data-key="5">5</button>
                        <button class="btn btn-light key" data-key="6">6</button>
                        <button class="btn btn-light key" data-key="7">7</button>
                        <button class="btn btn-light key" data-key="8">8</button>
                        <button class="btn btn-light key" data-key="9">9</button>
                        <button class="btn btn-info key" data-action="prefix-ro">RO</button>
                        <button class="btn btn-light key" data-key="0">0</button>
                        <button class="btn btn-warning key" data-action="backspace"><i class="fas fa-backspace"></i></button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger mr-auto key" data-action="clear">Șterge Tot</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Anulează</button>
                    <button type="button" class="btn btn-primary" id="cif-keyboard-save">Salvează</button>
                </div>
            </div>
        </div>
    </div>
<div class="modal fade" id="numeric-keyboard-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document" style="max-width: 320px;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Introduceți Suma Încasată</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="text" id="numeric-keyboard-display" class="form-control text-right mb-3" style="font-size: 1.8rem; height: auto;">
                <div class="numeric-keyboard">
                    <button class="btn btn-light key" data-key="1">1</button>
                    <button class="btn btn-light key" data-key="2">2</button>
                    <button class="btn btn-light key" data-key="3">3</button>
                    <button class="btn btn-light key" data-key="4">4</button>
                    <button class="btn btn-light key" data-key="5">5</button>
                    <button class="btn btn-light key" data-key="6">6</button>
                    <button class="btn btn-light key" data-key="7">7</button>
                    <button class="btn btn-light key" data-key="8">8</button>
                    <button class="btn btn-light key" data-key="9">9</button>
                    <button class="btn btn-light key" data-key=".">.</button>
                    <button class="btn btn-light key" data-key="0">0</button>
                    <button class="btn btn-warning key" data-action="backspace"><i class="fas fa-backspace"></i></button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger mr-auto key" data-action="clear">Șterge</button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Anulează</button>
                <button type="button" class="btn btn-primary" id="numeric-keyboard-save">Salvează</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="quantity-keyboard-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document" style="max-width: 320px;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Introduceți Cantitate</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="text" id="quantity-keyboard-display" class="form-control text-right mb-3" style="font-size: 1.8rem; height: auto;">
                <div class="numeric-keyboard">
                    <button class="btn btn-light key" data-key="1">1</button>
                    <button class="btn btn-light key" data-key="2">2</button>
                    <button class="btn btn-light key" data-key="3">3</button>
                    <button class="btn btn-light key" data-key="4">4</button>
                    <button class="btn btn-light key" data-key="5">5</button>
                    <button class="btn btn-light key" data-key="6">6</button>
                    <button class="btn btn-light key" data-key="7">7</button>
                    <button class="btn btn-light key" data-key="8">8</button>
                    <button class="btn btn-light key" data-key="9">9</button>
                    <button class="btn btn-light key" data-key=".">.</button>
                    <button class="btn btn-light key" data-key="0">0</button>
                    <button class="btn btn-warning key" data-action="backspace"><i class="fas fa-backspace"></i></button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger mr-auto key" data-action="clear">Șterge</button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Anulează</button>
                <button type="button" class="btn btn-primary" id="quantity-keyboard-save">Salvează</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="text-keyboard-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Introduceți textul pentru căutare</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="text" id="text-keyboard-display" class="form-control mb-3 text-center" style="font-size: 1.5rem; height: auto;" readonly>
                <div class="full-keyboard">
                    <div class="keyboard-row">
                        <button class="btn btn-light key" data-key="1">1</button>
                        <button class="btn btn-light key" data-key="2">2</button>
                        <button class="btn btn-light key" data-key="3">3</button>
                        <button class="btn btn-light key" data-key="4">4</button>
                        <button class="btn btn-light key" data-key="5">5</button>
                        <button class="btn btn-light key" data-key="6">6</button>
                        <button class="btn btn-light key" data-key="7">7</button>
                        <button class="btn btn-light key" data-key="8">8</button>
                        <button class="btn btn-light key" data-key="9">9</button>
                        <button class="btn btn-light key" data-key="0">0</button>
                    </div>
                    <div class="keyboard-row">
                        <button class="btn btn-light key" data-key="q">q</button>
                        <button class="btn btn-light key" data-key="w">w</button>
                        <button class="btn btn-light key" data-key="e">e</button>
                        <button class="btn btn-light key" data-key="r">r</button>
                        <button class="btn btn-light key" data-key="t">t</button>
                        <button class="btn btn-light key" data-key="y">y</button>
                        <button class="btn btn-light key" data-key="u">u</button>
                        <button class="btn btn-light key" data-key="i">i</button>
                        <button class="btn btn-light key" data-key="o">o</button>
                        <button class="btn btn-light key" data-key="p">p</button>
                    </div>
                    <div class="keyboard-row">
                        <button class="btn btn-light key" data-key="a">a</button>
                        <button class="btn btn-light key" data-key="s">s</button>
                        <button class="btn btn-light key" data-key="d">d</button>
                        <button class="btn btn-light key" data-key="f">f</button>
                        <button class="btn btn-light key" data-key="g">g</button>
                        <button class="btn btn-light key" data-key="h">h</button>
                        <button class="btn btn-light key" data-key="j">j</button>
                        <button class="btn btn-light key" data-key="k">k</button>
                        <button class="btn btn-light key" data-key="l">l</button>
                    </div>
                    <div class="keyboard-row">
                        <button class="btn btn-info key shift-key" data-action="shift">Shift</button>
                        <button class="btn btn-light key" data-key="z">z</button>
                        <button class="btn btn-light key" data-key="x">x</button>
                        <button class="btn btn-light key" data-key="c">c</button>
                        <button class="btn btn-light key" data-key="v">v</button>
                        <button class="btn btn-light key" data-key="b">b</button>
                        <button class="btn btn-light key" data-key="n">n</button>
                        <button class="btn btn-light key" data-key="m">m</button>
                        <button class="btn btn-warning key backspace-key" data-action="backspace"><i class="fas fa-backspace"></i></button>
                    </div>
                     <div class="keyboard-row">
                        <button class="btn btn-light key" data-key="ă">ă</button>
                        <button class="btn btn-light key" data-key="â">â</button>
                        <button class="btn btn-light key" data-key="î">î</button>
                        <button class="btn btn-light key" data-key="ș">ș</button>
                        <button class="btn btn-light key" data-key="ț">ț</button>
                        <button class="btn btn-light key space-key" data-action="space"></button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                 <button type="button" class="btn btn-danger mr-auto" id="text-keyboard-clear">Șterge Tot</button>
                 <button type="button" class="btn btn-secondary" data-dismiss="modal">Anulează</button>
                 <button type="button" class="btn btn-primary" id="text-keyboard-save">Caută</button>
            </div>
        </div>
    </div>
</div>


<div class="modal fade" id="editProductModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifică Produs</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
            <form id="editProductForm" onsubmit="return false;">
    <input type="hidden" id="id_vanz_edit" name="id_vanz_edit">
    <input type="hidden" id="cod_p_edit" name="cod_p_edit"> <div class="form-group">
        <label for="new_product_name">Nume nou:</label>
        <input type="text" class="form-control" id="new_product_name" name="new_product_name" required autocomplete="off">
    </div>
    
   <div class="form-group">
    <label for="new_product_price">Preț nou (cu TVA):</label>
    <div class="input-group">
        <input type="number" step="0.01" class="form-control" id="new_product_price" name="new_product_price" required autocomplete="off">
        <div class="input-group-append">
            <button class="btn btn-info" type="button" id="setPretAchizitieBtn" title="Setează prețul de vânzare la valoarea prețului de achiziție">
                <i class="fas fa-dollar-sign"></i> Preț Ach.
            </button>
        </div>
    </div>
</div>
</form>

<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-dismiss="modal">Anulează</button>
    <button type="button" class="btn btn-primary" id="saveProductChanges">Salvează (doar pe bon)</button>
    <button type="button" class="btn btn-success" id="saveProductChangesAndUpdatePermanent">Salvează și Actualizează Preț Produs Permanent</button>
</div>
            
        </div>
    </div>
</div>



 <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
<script>
$(document).ready(function() {
    
   // ======== CONSTANTE ȘI VARIABILE INIȚIALE ========
const nrBon = '<?php echo $nr_bon; ?>';
const clientId = '<?php echo $_SESSION['client_id']; ?>';
const codMasa = '<?php echo $cod_masa; ?>';
const sessionId = '<?php echo session_id(); ?>'; // <-- NOU: pentru a diferenția URL-urile în cache-ul browserului

const loadFile = clientId == 6 ? "load_prod_cu_stoc.php" : "load_prod.php";

let inactivityTimer = null;
let cifInputTimer = null;
let barcodeTimeout;
let isReadingScale = false;

// Cache pentru produse scanate (cod de bare)
let productCache = {};

// Cache pentru listele de produse (categorie + search + pagină)
const MAX_CACHE_ENTRIES = 500;
const categoryPageCache = new Map(); // key: string, value: HTML

function makeCategoryKey(categoryId, searchTerm, page) {
    return `${loadFile}|c=${categoryId}|q=${(searchTerm||'').trim().toLowerCase()}|p=${page}`;
}
function categoryCacheGet(key) {
    return categoryPageCache.get(key) || null;
}
function categoryCacheSet(key, html) {
    if (!html) return;
    // LRU simplu: dacă există, îl re-mutăm la final
    if (categoryPageCache.has(key)) categoryPageCache.delete(key);
    categoryPageCache.set(key, html);
    if (categoryPageCache.size > MAX_CACHE_ENTRIES) {
        const firstKey = categoryPageCache.keys().next().value;
        categoryPageCache.delete(firstKey);
    }
}
function categoryCacheClear() {
    categoryPageCache.clear();
}

// Variabile pentru încărcarea paginată
let currentPage = 1;
let isLoading = false;
let noMoreProducts = false;
let currentCategory = 'all';
let searchTimeout = null;

// ======== sfârșit bloc variabile ========


    // Referințe către elemente
    const categoryTabs = $('#category-tabs');
    const productListContainer = $('#product-list-container');
    const nameFilterInput = $('#prod_filter');
    const barcodeFilterInput = $('#prod_filter_cod_bare');
    const quantityInput = $('#cantitate_de_adaugat_prod');

// =============================================================
    // =========== START NOUA LOGICĂ DE MANIPULARE A BONULUI ===========
    // =============================================================

    function createReceiptItemHTML(item) {
        const ascundeActiuni = clientId == 22;
        const unitate_masura = (item.um === 'H87' || !item.um) ? 'buc' : escapeHTML(item.um);
        let actiuniHTML = '';
        if (!ascundeActiuni) {
            actiuniHTML = `
                <button name="${item.id_vanz}" value="${item.cod_p}" data-value="${item.cota_tva}" class="btn btn-sm btn-success discount-btn discount mb-1" title="Aplică discount">%</button>
                <button type="button" class="btn btn-sm btn-info edit-product-btn mb-1"
                    data-idvanz="${item.id_vanz}" data-codp="${item.cod_p}"
                    data-current-name="${escapeHTML(item.nume)}"
                    data-current-price="${item.pret_vanzare}"
                    title="Modifică Produs">
                    <i class="fas fa-pencil-alt"></i>
                </button>
            `;
        }

        return `
        <li class="receipt-item" id="item-${item.id_vanz}">
            <div class="product-info">
                <div class="product-name">${escapeHTML(item.nume)}</div>
                <div class="product-price">${item.pret_vanzare} RON / ${unitate_masura}</div>
            </div>
            <button name="${item.id_vanz}" class="btn btn-primary" title="Modifică cantitatea">
                x ${item.cantitate}
            </button>
            <div class="item-value">
                ${parseFloat(item.valoare_vanzare_cu_tva).toFixed(2)} RON
            </div>
            <div>
                ${actiuniHTML}
                <button class="btn btn-sm btn-danger sterge_prod" value="${item.id_vanz}" title="Șterge produs">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </li>`;
    }

    function escapeHTML(str) {
        if (typeof str !== 'string') return '';
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }
    
    function updateReceiptItem(item) {
        const itemElement = $(`#item-${item.id_vanz}`);
        if (itemElement.length) {
            itemElement.find('.btn-primary').text(`x ${item.cantitate}`);
            itemElement.find('.item-value').text(`${parseFloat(item.valoare_vanzare_cu_tva).toFixed(2)} RON`);
        }
    }

    function addReceiptItem(item) {
        const itemHTML = createReceiptItemHTML(item);
        $('#lista_produse_bon').append(itemHTML);
    }
    
    function recalculateTotals() {
        let totalBon = 0;
        $('.receipt-item').each(function() {
            const valueText = $(this).find('.item-value').text();
            const itemValue = parseFloat(valueText.replace(' RON', '')) || 0;
            totalBon += itemValue;
        });

        const totalFormatted = totalBon.toFixed(2);
        
        if ($('#total_de_incasat_display').length) {
            $('#total_de_incasat_display').text(totalFormatted);
            $('#suma-incasata-input').val(totalFormatted);
            $('#totalmixt').val(totalFormatted);
            
            calculateRest();
            updateCard();
        }
    }

function processAddProductResponse(response) {
  if (response.updated) {
    response.updated.forEach(item => updateReceiptItem(item));
  }
  if (response.added) {
    response.added.forEach(item => addReceiptItem(item));
  }
  if (response.removed) {
    response.removed.forEach(id => {
      const el = $(`#item-${id}`);
      if (el.length) el.fadeOut(200, function() { $(this).remove(); recalculateTotals(); });
    });
  }
  recalculateTotals();
}

    
    // ===========================================================
    // ========= SFÂRȘIT NOUA LOGICĂ DE MANIPULARE A BONULUI =========
    // ===========================================================
    

    // ======== FUNCȚII PENTRU SCROLL (Originale) ========
    function updateScrollButtons() {
        if (categoryTabs.length > 0 && categoryTabs[0].scrollWidth > categoryTabs[0].clientWidth) {
            const catScrollLeft = categoryTabs.scrollLeft();
            $('#scroll-cat-left').prop('disabled', catScrollLeft <= 0);
            $('#scroll-cat-right').prop('disabled', catScrollLeft >= categoryTabs[0].scrollWidth - categoryTabs[0].clientWidth - 1);
        } else if (categoryTabs.length > 0) { $('#scroll-cat-left, #scroll-cat-right').prop('disabled', true); }
        if (productListContainer.length > 0 && productListContainer[0].scrollHeight > productListContainer[0].clientHeight) {
            const prodScrollTop = productListContainer.scrollTop();
            $('#scroll-prod-up').prop('disabled', prodScrollTop <= 0);
            $('#scroll-prod-down').prop('disabled', prodScrollTop >= productListContainer[0].scrollHeight - productListContainer[0].clientHeight - 1);
        } else if (productListContainer.length > 0) { $('#scroll-prod-up, #scroll-prod-down').prop('disabled', true); }
    }
    $('#scroll-cat-left').on('click', function() { categoryTabs.animate({ scrollLeft: '-=350' }, 300); });
    $('#scroll-cat-right').on('click', function() { categoryTabs.animate({ scrollLeft: '+=350' }, 300); });
    $('#scroll-prod-up').on('click', function() { productListContainer.animate({ scrollTop: '-=400' }, 300); });
    $('#scroll-prod-down').on('click', function() { productListContainer.animate({ scrollTop: '+=400' }, 300); });
    categoryTabs.on('scroll', updateScrollButtons);
    productListContainer.on('scroll', updateScrollButtons);

    // ======== FUNCȚII PRINCIPALE ALE APLICAȚIEI (Originale + Modernizate) ========
    function showLoading(show = true) { $('#loading').css('display', show ? 'flex' : 'none'); }

    //Funcția se apelează DOAR la încărcarea paginii

    function initialLoadBonPanel() {
        const cacheBuster = new Date().getTime();
        $("#bon-curent-panel").load(`elemente_bon.php?nr_bon=${nrBon}&cod_masa=${codMasa}&v=${cacheBuster}`, function() {
            const totalsElement = $(this).find('.receipt-totals');
            if (totalsElement.length) {
                $('.grup-dreapta').html(totalsElement);
            }
            // Sincronizăm totalul calculat de PHP cu funcția noastră de calcul
            recalculateTotals(); 
        });
        
        // Logica de focus rămâne neschimbată
if (['18', '21', '22'].includes(String(clientId))) {
            barcodeFilterInput.focus().select();
        } else {
            nameFilterInput.focus();
        }
        if (clientId === '6') {
    quantityInput.val('');
} else {
    quantityInput.val(1);
}
    }

    // MODERNIZAT: Funcția de încărcare a produselor a fost înlocuită pentru a suporta paginare și căutare pe server
 function prefetchNextPage(categoryId, page, searchTerm = '') {
    const key = makeCategoryKey(categoryId, searchTerm, page);
    if (categoryCacheGet(key)) return; // deja în cache
    $.ajax({
        url: loadFile, type: 'GET',
        data: { categ: categoryId, page: page, limit: 40, search: searchTerm },
        success: (response) => { categoryCacheSet(key, response); }
    });
}

function renderFromCacheIfAny(categoryId, page, searchTerm) {
    const key = makeCategoryKey(categoryId, searchTerm, page);
    const cached = categoryCacheGet(key);
    if (cached) {
        productListContainer.html(cached);
        currentPage = page;
        updateScrollButtons();
        return true;
    }
    return false;
}

function loadProducts(categoryId, page, searchTerm = '', append = false) {
    // Dacă avem deja pagina în cache și nu facem append, randăm instant și ieșim
    if (!append && renderFromCacheIfAny(categoryId, page, searchTerm)) {
        // Prefetch next page pentru UX mai bun
        if (page === 1) prefetchNextPage(categoryId, 2, searchTerm);
        return;
    }

    if (isLoading || (append && noMoreProducts)) return;
    isLoading = true;

    if (!append) {
        // Dacă nu e în cache, arată spinner; dacă e, a fost randat deja mai sus
        if (!renderFromCacheIfAny(categoryId, page, searchTerm)) {
            productListContainer.html('<div class="text-center p-4"><i class="fas fa-spinner fa-spin fa-3x"></i></div>');
        }
    }

    $.ajax({
        url: loadFile, type: 'GET',
        data: { categ: categoryId, page: page, limit: 40, search: searchTerm, sid: sessionId },
         headers: { 'X-Requested-With': 'XMLHttpRequest' },
        cache: true,
        success: (response) => {
            const key = makeCategoryKey(categoryId, searchTerm, page);
            if (response && response.trim() !== '') {
                categoryCacheSet(key, response);
                if (append) {
                    productListContainer.append(response);
                } else {
                    productListContainer.html(response);
                }
                currentPage = page;
                updateScrollButtons();

                // Prefetch next page la prima pagină
                if (page === 1) prefetchNextPage(categoryId, 2, searchTerm);
            } else {
                if (append) {
                    noMoreProducts = true;
                } else if (!categoryCacheGet(key)) {
                    productListContainer.html('<p class="text-muted p-4">Nu sunt produse.</p>');
                }
            }
        },
        error: () => {
            if (!append) {
                // Randăm fallback doar dacă nu aveam cache
                if (!renderFromCacheIfAny(categoryId, page, searchTerm)) {
                    productListContainer.html('<p class="text-danger p-4">Eroare la încărcare.</p>');
                }
            }
        },
        complete: () => { isLoading = false; }
    });
}


    function resetInactivityTimer() {
        clearTimeout(inactivityTimer);
        inactivityTimer = setTimeout(() => { $("#continuati").modal("show"); }, 1800000);
    }
    
    function saveCifToSession(cifValue) {
        $.post("save_cif_to_session.php", { cif_client: cifValue })
         .fail(function() { alert("Eroare la salvarea CIF în sesiune."); });
    }
function processBarcode() {
        const codBare = barcodeFilterInput.val();
        if (!codBare) return;

        if (productCache[codBare]) {
            const cachedProduct = productCache[codBare];
            const productData = {
                prod: cachedProduct.cod_produs, bonul: nrBon, cod_masa: codMasa,
                cantitate_de_adaugat_prod: quantityInput.val() || 1,
                nume_produs: cachedProduct.nume, pret_vanzare: cachedProduct.pret_cu_tva,
                cota_tva: cachedProduct.cota_tva, um: cachedProduct.um, sgr: cachedProduct.sgr,
                sgr_pet: cachedProduct.sgr_pet, sgr_alumin: cachedProduct.sgr_alumin,
                sgr_sticla: cachedProduct.sgr_sticla, gestiune: cachedProduct.denumire_gestiune
            };
            $.get("vanzare_adaug_prod_pe_nota.php", productData, 'json')
            .done(response => {
                processAddProductResponse(response);
                finalizeAndResetInputs(); // Păstrăm logica originală
            })
            .fail(() => alert('Eroare la adăugarea produsului din cache.'));
        } else {
            $.get("vanzare_adaug_prod_pe_nota.php", {
                prod_cod_bare: codBare, bonul: nrBon, cod_masa: codMasa,
                cantitate_de_adaugat_prod: quantityInput.val() || 1
            }, 'json')
            .done(response => {
                if(response.product_details_for_cache) {
                    productCache[codBare] = response.product_details_for_cache;
                }
                processAddProductResponse(response);
                finalizeAndResetInputs(); // Păstrăm logica originală
            })
            .fail(() => {
                alert('Eroare: produs negăsit sau fără stoc.');
                barcodeFilterInput.select();
            });
        }
    }

    function finalizeAndResetInputs() {
        // Aici păstrăm logica ta originală de focus și resetare, dar fără reloadBonPanel
        barcodeFilterInput.val('').focus();
        if (clientId === '6') {
            quantityInput.val('');
        } else {
            quantityInput.val(1);
        }
    }

    // ======== START: ÎMBUNĂTĂȚIRI UZABILITATE (SCURTĂTURI, MODAL CANTITATE) ========
    function openQuantityModal() {
        $('#quantity-keyboard-display').val(''); // Golește inputul la deschidere
        $('#quantity-keyboard-modal').modal('show');
    }

    // Focus pe input când modalul de cantitate este afișat
    $('#quantity-keyboard-modal').on('shown.bs.modal', function () {
        $('#quantity-keyboard-display').focus();
    });

   // Tratează apăsarea tastelor în modalul de cantitate
$('#quantity-keyboard-display').on('keydown', function(e) {
  if (e.key === ',') { e.preventDefault(); if ($(this).val().indexOf('.') === -1) $(this).val($(this).val() + '.'); }
  if (e.key === '-') {
    e.preventDefault();
    const v = $(this).val();
    if (!v.startsWith('-')) $(this).val('-' + v);
  }
  if (e.key === 'Enter') { e.preventDefault(); $('#quantity-keyboard-save').click(); }
});


    $('#btn_modifica_cantitate_modal').on('click', openQuantityModal);

    // Gestiune click pe butoanele din modalul de cantitate
    $('#quantity-keyboard-modal .key').on('click', function() {
        const display = $('#quantity-keyboard-display');
        let currentValue = display.val();
        const action = $(this).data('action');
        const key = $(this).data('key');
        if (action === 'backspace') display.val(currentValue.slice(0, -1));
        else if (action === 'clear') display.val('');
       else if (key !== undefined) {
  if (key === '.' && currentValue.includes('.')) return;
  if (key === '-') {
    if (!currentValue.startsWith('-')) display.val('-' + currentValue);
    return;
  }
  display.val(currentValue + key);
}

    });
    
    // Salvarea cantității și revenirea focusului
    $('#quantity-keyboard-save').on('click', function() {
        const newValue = parseFloat($('#quantity-keyboard-display').val());
        if (!isNaN(newValue) && newValue > 0) {
            quantityInput.val(newValue.toFixed(5));
        } else {
            if (clientId === '6') {
    quantityInput.val('');
} else {
    quantityInput.val(1);
}
        }
        $('#quantity-keyboard-modal').modal('hide');
        barcodeFilterInput.focus().select(); // Focus mereu pe cod bare după salvare
    });
    
    // ======== END: ÎMBUNĂTĂȚIRI UZABILITATE ========
    
    
    // ======== START BLOC: LOGICA PENTRU MODIFICARE PRODUS (NUME ȘI PREȚ) ========

// Deschide modalul și populează datele la click pe butonul de editare
$(document).on('click', '.edit-product-btn', function() {
    const idVanz = $(this).data('idvanz');
    const codProdus = $(this).data('codp'); // Preluăm și codul produsului
    const currentName = $(this).data('current-name');
    const currentPrice = $(this).data('current-price');
    
    // Pre-populează câmpurile din modal, inclusiv cel ascuns
    $('#id_vanz_edit').val(idVanz);
    $('#cod_p_edit').val(codProdus); // Populăm câmpul ascuns
    $('#new_product_name').val(currentName);
    $('#new_product_price').val(currentPrice);
    
    $('#editProductModal').modal('show');
    // ADAUGĂ ACEST BLOC NOU
// Când se apasă pe butonul de setare a prețului de achiziție
$(document).on('click', '#setPretAchizitieBtn', function() {
    const codProdus = $('#cod_p_edit').val();
    if (!codProdus) {
        alert('Codul produsului nu a fost găsit.');
        return;
    }
    
    const self = $(this);
    self.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>'); // Arată un indicator de încărcare

    // Facem un apel AJAX către noul script PHP
    $.ajax({
        url: 'vanzare_get_pret_achizitie.php',
        type: 'POST',
        data: { cod_p: codProdus },
        dataType: 'json',
        success: function(response) {
            if (response.error) {
                alert('Eroare: ' + response.error);
            } else if (response.pret_achizitie !== null) {
                // Setăm valoarea în câmpul de preț
                $('#new_product_price').val(response.pret_achizitie);
            } else {
                alert('Prețul de achiziție nu a fost găsit pentru acest produs.');
            }
        },
        error: function() {
            alert('A apărut o eroare de comunicare cu serverul.');
        },
        complete: function() {
            // Refacem butonul la starea inițială indiferent de rezultat
            self.prop('disabled', false).html('Preia preț achiziție cu TVA');
        }
    });
});
// SFÂRȘITUL BLOCULUI NOU
});
// după definiții:
window.processAddProductResponse = processAddProductResponse;
window.reloadBonPanel = reloadBonPanel;

// relaodbonpanel doar daca avem nevoie de el ca noi incercam sa incarcam doar o singura data
    function reloadBonPanel() {
        const cacheBuster = new Date().getTime();
        $("#bon-curent-panel").load(`elemente_bon.php?nr_bon=${nrBon}&cod_masa=${codMasa}&v=${cacheBuster}`, function() {
            // **MODIFICARE**: Mută secțiunea de totaluri din #bon-curent-panel în .grup-dreapta (footer)
            const totalsElement = $(this).find('.receipt-totals');
            if (totalsElement.length) {
                $('.grup-dreapta').html(totalsElement); // Folosim .html() pentru a înlocui complet
            }
            
            // Restul logicii originale, care depinde de elementele încărcate
            let total = parseFloat($('#total_de_incasat_display').text().replace(',', '.')) || 0;
            $('#totalmixt').val(total.toFixed(2));
           $('#numerar').val(total.toFixed(2));   //  fără .attr('max', ...)
        $('#card').val('0.00');               //  fără .attr('max', ...)
            updateCard();
        });
        
        // Logica de focus rămâne neschimbată
if (['18', '21', '22'].includes(String(clientId))) {
            barcodeFilterInput.focus().select();
        } else {
            nameFilterInput.focus();
        }
        if (clientId === '6') {
    quantityInput.val('');
} else {
    quantityInput.val(1);
}
    }
// Pune focus pe câmpul de nume când modalul este deschis
$('#editProductModal').on('shown.bs.modal', function () {
    $('#new_product_name').focus().select();
});

// Funcționalitate pentru tasta Enter în câmpurile de text
$('#editProductModal').on('keydown', 'input', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault(); 
        // Simulează click pe butonul principal de salvare (cel albastru)
        $('#saveProductChanges').click();
    }
});

// Handler UNIC pentru ambele butoane de salvare
$('#editProductModal .modal-footer').on('click', '#saveProductChanges, #saveProductChangesAndUpdatePermanent', function() {
    const idVanz = $('#id_vanz_edit').val();
    const codProdus = $('#cod_p_edit').val();
    const newName = $('#new_product_name').val().trim();
    const newPrice = $('#new_product_price').val().trim();

    if (!newName || newPrice === '' || isNaN(newPrice)) {
        alert('Numele și prețul sunt obligatorii și prețul trebuie să fie numeric.');
        return;
    }

    // Determinăm ce buton a fost apăsat
    const isPermanentUpdate = $(this).is('#saveProductChangesAndUpdatePermanent');
    
    // Alegem scriptul PHP corespunzător
    const targetUrl = isPermanentUpdate ? 'vanzare_update_product_permanent.php' : 'vanzare_update_product_name.php';
    
    // Pregătim datele pentru trimitere
    const postData = {
        id_vanz: idVanz,
        new_name: newName,
        new_price: newPrice,
        cod_p: codProdus // Trimitem codul produsului în ambele cazuri
    };

    showLoading(true);
    $('#editProductModal').modal('hide');

    $.ajax({
        url: targetUrl,
        type: 'POST',
        data: postData,
        success: function(response) {
            // Invalidează cache-ul de listări din browser (pot exista prețuri/nume schimbate)
            categoryCacheClear();
              productCache = {}; // <- invalidează și cache-ul de coduri de bare

            // Reîncarcă lista curentă rapid (va cere din server sau va umple din nou cache-ul)
            loadProducts(currentCategory, 1, nameFilterInput.val() || '');
            // Reîncarcă și panoul de bon pentru a reflecta noile valori
            reloadBonPanel();
        },
        error: function(xhr) {
            alert('Eroare la salvare: ' + xhr.responseText);
        },
        complete: function() {
            showLoading(false);
        }
    });
});

// ======== END BLOC: LOGICA PENTRU MODIFICARE PRODUS ========

    // ======== GESTIONAREA EVENIMENTELOR GENERALE (KEYBOARD SHORTCUTS) ========
    
    $(document).on('keydown', function(e) {
        // Ignorăm shortcut-urile dacă un alt modal (care nu e de cantitate sau plată) este deschis
        const activeModal = $('.modal.show');
        if (activeModal.length > 0 && !activeModal.is('#quantity-keyboard-modal, #numeric-keyboard-modal')) {
            return;
        }

        const target = $(e.target);
        const isInputFocused = target.is('input, textarea');

        // Shortcut pentru comutare între câmpurile de căutare
        if (e.key === 'Control') {
            e.preventDefault();
            if (nameFilterInput.is(':focus')) {
                barcodeFilterInput.focus().select();
            } else {
                nameFilterInput.focus();
            }
        }

        // Shortcut pentru deschiderea modalului de cantitate
if (e.key === 'Shift') {
            e.preventDefault();
            openQuantityModal();
        }
        
        // NOU: Shortcut pentru deschiderea modalului de plată
        if (e.key === '/' && !isInputFocused) {
            e.preventDefault();
            // Verificăm dacă elementul există înainte de a declanșa click
            if ($('#suma-incasata-input').length > 0) {
                $('#suma-incasata-input').click();
            }
        }

        // Shortcut pentru cântar
        if (e.key === 'CapsLock') {
            e.preventDefault();
            $('#btn_citeste_cantar_nou').trigger('click');
        }

        // Shortcut pentru adăugarea primului produs din listă
        if (e.key === 'Enter' && nameFilterInput.is(':focus')) {
            e.preventDefault();
            const firstProduct = $('#product-list-container .product-card:not(.disabled):first');
            if (firstProduct.length) { firstProduct.trigger('click'); }
        }
    });

// 1) În inputul de cantitate: gestionează Enter, transformă virgulă în punct, permite semn minus
quantityInput.on('keydown', function(e) {
    // Logica pentru tasta Enter
    if (e.key === 'Enter') {
        e.preventDefault(); // Previne orice acțiune implicită (ex: trimitere formular)
        
        if (clientId === '17') {
            nameFilterInput.focus(); // Mută focusul pe căutare nume pentru clientul 17
        } else {
            barcodeFilterInput.focus().select(); // Mută focusul pe cod de bare pentru ceilalți clienți
        }
        return; // Oprește executarea restului funcției pentru tasta Enter
    }

    // Transformă virgulă în punct
    if (e.key === ',') {
        e.preventDefault();
        if (!this.value.includes('.')) {
            this.value = this.value + '.';
        }
        return;
    }

    // Permite adăugarea semnului minus la început
    if (e.key === '-' || e.code === 'NumpadSubtract') {
        if (!this.value.startsWith('-')) {
            e.preventDefault();
            this.value = '-' + this.value;
        }
        // Asigură-te că cursorul rămâne la final după adăugarea semnului
        setTimeout(() => { this.setSelectionRange(this.value.length, this.value.length); }, 0);
    }
});
// normalizează și la paste
quantityInput.on('input', function() {
  this.value = this.value.replace(',', '.');
});

// 2) Shortcuts globale: '+' = focus & select pe cantitate; '-' = focus și pune minus
$(document).on('keydown', function(e) {
  // dacă e deschis alt modal (în afară de numeric/quantity keyboard), nu interceptăm
  const activeModal = $('.modal.show');
  if (activeModal.length > 0 && !activeModal.is('#quantity-keyboard-modal, #numeric-keyboard-modal')) return;

  if (e.key === '+' || e.code === 'NumpadAdd') {
    e.preventDefault();
    quantityInput.focus().select();
    return;
  }
  if (e.key === '-' || e.code === 'NumpadSubtract') {
    e.preventDefault();
    quantityInput.focus();
    if (!quantityInput.val().startsWith('-')) {
      quantityInput.val('-' + (quantityInput.val() || ''));
    }
    return;
  }
});

    // ======== GESTIONAREA EVENIMENTELOR (Originale + Modernizate) ========
    $('#cif-keyboard-modal .key').on('click', function() {
        const display = $('#cif-keyboard-display');
        let currentValue = display.val();
        const action = $(this).data('action');
        const key = $(this).data('key');
        if (action === 'backspace') display.val(currentValue.slice(0, -1));
        else if (action === 'clear') display.val('');
        else if (action === 'prefix-ro') { if (!currentValue.toUpperCase().startsWith('RO')) { display.val('RO' + currentValue); } }
        else if (key !== undefined) { if (currentValue.length < 15) { display.val(currentValue + key); } }
    });
    $('#cif-keyboard-save').on('click', function() {
        const cifValue = $('#cif-keyboard-display').val();
        $('#cif_client_input').val(cifValue);
        saveCifToSession(cifValue);
        $('#cif-keyboard-modal').modal('hide');
    });
    
    $(document)
        .on('keyup', '#cif_client_input', function() {
            clearTimeout(cifInputTimer);
            const cifValue = $(this).val();
            cifInputTimer = setTimeout(() => saveCifToSession(cifValue), 500);
        })
        .on('click', '#cif-kbd-btn', function() {
            const currentValue = $('#cif_client_input').val();
            $('#cif-keyboard-display').val(currentValue);
            $('#cif-keyboard-modal').modal('show');
        })
        .on('click', '.discount', function() {
            $("#Discount").find("[name='idvanzare']").val($(this).attr("name")).end().modal("show");
        })
  .on('click', '.sterge_prod', function() {
    const itemElement = $(this).closest('.receipt-item');
    const idVanz = $(this).val();

    $.get("sterge_prod.php", { id_vanz: idVanz, nr_bon: nrBon })
    .done(() => {
        // Succes! Eliminăm vizual elementul și recalculăm totalul.
        itemElement.fadeOut(300, function() { 
            $(this).remove();
            recalculateTotals();
        });
    })
    .fail(() => { 
        alert('Eroare la ștergerea produsului.');
    });
})
    // MODIFICAT: Adăugare produs prin click (fără blocare UI)
.on('click', '.adaug_prod:not(.disabled)', function() {
    const productCard = $(this);
    const productData = {
        prod: productCard.attr('value'),
        bonul: nrBon,
        cod_masa: codMasa,
        cantitate_de_adaugat_prod: quantityInput.val() || 1,
        nume_produs: productCard.data('nume'),
        pret_vanzare: productCard.data('pret'),
        cota_tva: productCard.data('tva'),
        um: productCard.data('um'), // Asigură-te că load_prod.php trimite și data-um
        sgr: productCard.data('sgr'),
        sgr_pet: productCard.data('sgr-pet'),
        sgr_alumin: productCard.data('sgr-alumin'),
        sgr_sticla: productCard.data('sgr-sticla'),
        gestiune: productCard.data('gestiune')
    };

    $.get("vanzare_adaug_prod_pe_nota.php", productData, 'json')
    .done(response => {
        processAddProductResponse(response);
        // Păstrăm logica ta originală de refocus
        if (['18', '21', '22'].includes(String(clientId))) {
            barcodeFilterInput.focus().select();
        } else {
            nameFilterInput.focus();
        }
        if (clientId === '6') {
            quantityInput.val('');
        } else {
            quantityInput.val(1);
        }
    })
    .fail(() => { 
        alert('Eroare la adăugarea produsului.');
    });
})
        .on('click', '.category-tab-btn', function() {
             $('.category-tab-btn').removeClass('active');
  $(this).addClass('active');

  const val = $(this).data('value');
  currentCategory = val;
  currentPage = 1;
  noMoreProducts = false;
  nameFilterInput.val('');

  if (val === '__MENIURI__') {
    // NU mai apela loadProducts aici!
    if (window.__MENIU_WIDGET__) window.__MENIU_WIDGET__.show();
    // focus, la fel ca la produse
    if (['18','21','22'].includes(String(clientId))) {
      barcodeFilterInput.focus().select();
    } else {
      nameFilterInput.focus();
    }
    return; // IMPORTANT
  }

  // Orice altă categorie: ascunde panoul de meniuri și încarcă produsele clasice
  if (window.__MENIU_WIDGET__) window.__MENIU_WIDGET__.hide();
  loadProducts(currentCategory, 1);

  if (['18','21','22'].includes(String(clientId))) {
    barcodeFilterInput.focus().select();
  } else {
    nameFilterInput.focus();
  }
        })
        .on('submit', '#plata_form', function() {
            const cif = $('#cif_client_input').val();
            $('#cif_client_hidden').val(cif);
            showLoading(true);
        });

    // MODERNIZAT: Căutarea după nume se face pe server
    nameFilterInput.on("keyup", function(e) {
        if (['Control', 'Shift', 'Enter', 'CapsLock', '/'].includes(e.key)) return;

        
  // Dacă suntem pe MENIURI, folosește loader-ul de meniuri, nu loadProducts
  if (currentCategory === '__MENIURI__') {
    clearTimeout(searchTimeout);
    const s = $(this).val();
    searchTimeout = setTimeout(() => {
      if (window.__MENIU_WIDGET__) window.__MENIU_WIDGET__.reload(s);
    }, 350);
    return; // IMPORTANT
  }

        clearTimeout(searchTimeout);
        const searchTerm = $(this).val();
        searchTimeout = setTimeout(() => {
            currentPage = 1;
            noMoreProducts = false;
            loadProducts(currentCategory, 1, searchTerm);
        }, 350);
    });

    // dacă se apasă slash în câmpul de cod bare, dăm blur (pierdem focusul)
    barcodeFilterInput.on('keydown', function(e) {
        if (e.key === '/') {
            e.preventDefault();
            $(this).blur();
        }
    });

    // Păstrat handler-ul original de cod bare
 // Căutarea se declanșează doar la apăsarea tastei Enter
barcodeFilterInput.on("keyup", function(event) {
    if (event.key === 'Enter' || event.keyCode === 13) {
        processBarcode();
    }
});

    // Adăugat handler-ul pentru scroll infinit
    productListContainer.on('scroll', function() {
        if (!isLoading && !noMoreProducts && this.scrollTop + this.clientHeight >= this.scrollHeight - 250) {
            const searchTerm = nameFilterInput.val();
            loadProducts(currentCategory, currentPage + 1, searchTerm, true);
        }
    });


$('#btn_citeste_cantar_nou').on('click', function() {
    // Blochează click-urile multiple dacă o citire este deja în desfășurare
    if (isReadingScale) return;
    isReadingScale = true;
    
    const self = $(this);
    self.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

    // Funcție ajutătoare pentru a trimite comanda de citire la cântar
    const triggerScale = () => {
        return fetch("https://agecs.agecs.in/api/declanseaza_cantar.php", {
            method: "POST",
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ trigger: true })
        });
    };
    
    // Funcție pentru a șterge fișierul cu cantitatea după citire
    const deleteWeightFile = () => {
        const deleteUrl = `https://agecs.agecs.in/api/delete_cantitate_cantarita.php?client_id=${clientId}&cod_locatie=${codMasa}`;
        fetch(deleteUrl)
            .then(response => response.text())
            .then(text => console.log('Rezultat ștergere:', text))
            .catch(err => console.error('Eroare la ștergerea fișierului:', err));
    };

    // --- LOGICA DE DUBLĂ CITIRE ---

    // Pasul 1: Trimitem PRIMA comandă de citire
    console.log('Se trimite prima comandă de citire...');
    triggerScale()
    .then(() => {
        // Așteptăm o secundă
        setTimeout(() => {
            // Pasul 2: Trimitem a DOUA comandă pentru a suprascrie valoarea veche
            console.log('Se trimite a doua comandă de citire pentru siguranță...');
            triggerScale().then(() => {
                // Pasul 3: Acum, după a doua comandă, începem să căutăm rezultatul
                let checkAttempts = 0;
                const intervalCheck = setInterval(() => {
                    if (checkAttempts++ > 15) {
                        clearInterval(intervalCheck);
                        isReadingScale = false;
                        self.prop('disabled', false).html('<i class="fa fa-balance-scale"></i>');
                        alert('Nu s-a putut citi cântarul în timpul alocat.');
                        deleteWeightFile(); // Curățăm fișierul chiar dacă a eșuat
                        return;
                    }

                    const checkUrl = `https://agecs.agecs.in/api/${clientId}/${codMasa}/cantitate_cantarita.json?ts=${new Date().getTime()}`;
                    
                    fetch(checkUrl)
                        .then(response => {
                            if (!response.ok) throw new Error('Așteptare rezultat cântar...');
                            return response.json();
                        })
                        .then(data => {
                            // SUCCES! Am găsit rezultatul.
                            clearInterval(intervalCheck);

                            if (data && data.weight !== undefined) {
                                const weight = parseFloat(data.weight.trim().replace(/"/g, '')).toFixed(3);
                                quantityInput.val(weight);
                                console.log(`Valoare finală citită: ${weight}. Se șterge fișierul.`);
                                deleteWeightFile(); // Ștergem fișierul după ce l-am folosit
                            }

                            // Resetăm butonul la starea inițială
                            isReadingScale = false;
                            self.prop('disabled', false).html('<i class="fa fa-balance-scale"></i>');
                        })
                        .catch(error => console.log(error.message));
                }, 800);
            });
        }, 1000); // Pauză de 1 secundă între cele două citiri
    })
    .catch(() => {
        alert('Eroare la comanda cântarului.');
        isReadingScale = false;
        self.prop('disabled', false).html('<i class="fa fa-balance-scale"></i>');
    });
});
    
    $('#verifica_stoc_btn').on('click', function() {
        if(clientId == 6){
            $.ajax({ url: "update_stoc_produse.php", method: "GET" });
        }
    });
    $('#notaSelect').on('change', function(){
        const nrbon = $(this).val();
        const detailsContainer = $('#detNoteDetails');
        if(nrbon === "") { detailsContainer.html(""); return; }
        detailsContainer.html("Se încarcă...");
        $.ajax({
            url: 'get_det_note.php', type: 'GET', data: { nrbon: nrbon },
            success: function(r){ detailsContainer.html(r); },
            error: function(){ detailsContainer.html('<p class="text-danger">Eroare.</p>');}
        });
    });

    // ======== BLOC ÎMBUNĂTĂȚIT PENTRU TASTATURA NUMERICĂ DE PLATĂ ========
    function calculateRest() {
        const totalPlata = parseFloat($('#total_de_incasat_display').text()) || 0;
        const sumaIncasata = parseFloat($('#suma-incasata-input').val()) || 0;
        let rest = (sumaIncasata > totalPlata) ? sumaIncasata - totalPlata : 0;
        $('#rest-de-dat-display').text(rest.toFixed(2) + ' RON');
    }

    // Deschide modalul
    $(document).on('click', '#suma-incasata-input', function() {
        $('#numeric-keyboard-display').val($(this).val()).focus(); // Adaugă și focus
        $('#numeric-keyboard-modal').modal('show');
    });
    
    // Focus pe input când modalul de plată este afișat
    $('#numeric-keyboard-modal').on('shown.bs.modal', function () {
        $('#numeric-keyboard-display').focus().select();
    });
    
    // Tratează apăsarea tastei Enter în modalul de plată
    $('#numeric-keyboard-display').on('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            $('#numeric-keyboard-save').click();
        }
    });

    // Gestiune click pe butoanele din modalul de plată
    $('#numeric-keyboard-modal .key').on('click', function() {
        const display = $('#numeric-keyboard-display');
        let currentValue = display.val();
        const action = $(this).data('action');
        const key = $(this).data('key');
        if (action === 'backspace') display.val(currentValue.slice(0, -1));
        else if (action === 'clear') display.val('');
        else if (key !== undefined) {
            if (key === '.' && currentValue.includes('.')) return;
            display.val(currentValue + key);
        }
    });

    // Salvarea sumei și calcularea restului
    $('#numeric-keyboard-save').on('click', function() {
        $('#suma-incasata-input').val($('#numeric-keyboard-display').val());
        $('#numeric-keyboard-modal').modal('hide');
        calculateRest();
        barcodeFilterInput.focus().select(); // Revenire focus pe cod bare
    });
    // ======== SFÂRȘIT BLOC PLATĂ ========
    
// ======== START BLOC: LOGICA PENTRU TASTATURA VIRTUALA TEXT (CORECTAT) ========
let isShiftActive = false;

// Funcție pentru a comuta starea tastei Shift și a actualiza tastele
function toggleShift() {
    isShiftActive = !isShiftActive;
    $('#text-keyboard-modal .shift-key').toggleClass('active-shift', isShiftActive);
    
    $('#text-keyboard-modal .key[data-key]').each(function() {
        const key = $(this);
        let char = key.data('key');
        
        // Comută între majuscule și minuscule doar pentru litere
        if (char.length === 1 && char.match(/[a-zăâîșț]/i)) {
            key.text(isShiftActive ? char.toUpperCase() : char.toLowerCase());
        }
    });
}

// Când modalul este pe cale să fie afișat, preia textul din inputul principal
$('#text-keyboard-modal').on('show.bs.modal', function () {
    const currentSearchText = $('#prod_filter').val();
    $('#text-keyboard-display').val(currentSearchText);
    
    // Resetează starea Shift la fiecare deschidere
    if (isShiftActive) {
        toggleShift(); 
    }
});

// Gestionează click-urile pe tastele virtuale
$('#text-keyboard-modal').on('click', '.key', function() {
    const display = $('#text-keyboard-display');
    let currentValue = display.val();
    const action = $(this).data('action');
    let key = $(this).data('key');

    if (action) {
        switch(action) {
            case 'backspace':
                display.val(currentValue.slice(0, -1));
                break;
            case 'space':
                display.val(currentValue + ' ');
                break;
            case 'shift':
                toggleShift();
                break;
        }
    } else if (key) {
        let characterToAdd = isShiftActive ? key.toUpperCase() : key.toLowerCase();
        display.val(currentValue + characterToAdd);
        
        // Odată ce o literă a fost adăugată, dezactivează Shift
        if (isShiftActive) {
            toggleShift();
        }
    }
});

// Acțiune pentru butonul "Șterge Tot"
$('#text-keyboard-clear').on('click', function() {
    $('#text-keyboard-display').val('');
});

// Salvează textul, închide modalul și declanșează căutarea
$('#text-keyboard-save').on('click', function() {
    const newSearchText = $('#text-keyboard-display').val();
    $('#prod_filter').val(newSearchText);
    $('#text-keyboard-modal').modal('hide');
    
    // Declansează evenimentul 'keyup' pentru a porni căutarea automată
    $('#prod_filter').trigger('keyup');
});
// ======== END BLOC: LOGICA PENTRU TASTATURA VIRTUALA TEXT (CORECTAT) ========

    // ======== INIȚIALIZARE PAGINĂ ========
    $(window).on('mousemove mousedown keydown scroll', resetInactivityTimer);
    resetInactivityTimer();
initialLoadBonPanel();
    loadProducts('all', 1); // Încărcare modernizată
    
    // Focus inițial
if (['18', '21', '22'].includes(String(clientId))) {
        barcodeFilterInput.focus().select();
    } else {
        nameFilterInput.focus();
    }
//setare cantitate empty pentru client 6 

    setTimeout(updateScrollButtons, 500);
    $(window).on('resize', updateScrollButtons);


    // ======== START BLOC: LOGICA PENTRU TASTATURI DISCOUNT ========

    // Funcție generică pentru a gestiona o tastatură numerică
    function setupDiscountKeyboard(modalId, displayId, saveButtonId, targetInputId) {
        // Când modalul tastaturii se deschide, preia valoarea din inputul țintă
        $(modalId).on('show.bs.modal', function () {
            const targetValue = $(targetInputId).val();
            $(displayId).val(targetValue);
        });

        // Când modalul s-a deschis, pune focus
        $(modalId).on('shown.bs.modal', function () {
            $(displayId).focus();
        });

        // Gestionează click-urile pe taste
        $(`${modalId} .key`).on('click', function() {
            const display = $(displayId);
            let currentValue = display.val();
            const action = $(this).data('action');
            const key = $(this).data('key');

            if (action === 'backspace') {
                display.val(currentValue.slice(0, -1));
            } else if (action === 'clear') {
                display.val('');
            } else if (key !== undefined) {
                if (key === '.' && currentValue.includes('.')) {
                    return; // Doar un singur punct zecimal
                }
                display.val(currentValue + key);
            }
        });

        // La click pe Salvează, actualizează inputul țintă și închide modalul
        $(saveButtonId).on('click', function() {
            const newValue = $(displayId).val();
            $(targetInputId).val(newValue);
            $(modalId).modal('hide');
        });
    }

    // Inițializează tastaturile pentru discount global
    setupDiscountKeyboard(
        '#keyboardModalProcentual', 
        '#keyboard-display-procentual', 
        '#keyboard-save-procentual', 
        '#val_procent_global_input'
    );

    setupDiscountKeyboard(
        '#keyboardModalFix', 
        '#keyboard-display-fix', 
        '#keyboard-save-fix', 
        '#valoare_fixa_global_input'
    );

    // Inițializează tastaturile pentru discount individual
    setupDiscountKeyboard(
        '#keyboardModalIndividualProcentual', 
        '#keyboard-display-individual-procentual', 
        '#keyboard-save-individual-procentual', 
        '#val_procent_individual_input'
    );

    setupDiscountKeyboard(
        '#keyboardModalIndividualFix', 
        '#keyboard-display-individual-fix', 
        '#keyboard-save-individual-fix', 
        '#valoare_fixa_individual_input'
    );
    // ======== END BLOC: LOGICA PENTRU TASTATURI DISCOUNT ========
});



// end doc ready

// ======== FUNCȚII GLOBALE (Originale) ========
function updateCard(){
    const total = parseFloat($('#totalmixt').val()) || 0;
    let numerar = parseFloat($('#numerar').val()) || 0;
    if(numerar > total) { numerar = total; $('#numerar').val(numerar.toFixed(2)); }
    if(numerar < 0) { numerar = 0; $('#numerar').val(numerar.toFixed(2)); }
    $('#card').val((total - numerar).toFixed(2));
};
function updateNumerar(){
    const total = parseFloat($('#totalmixt').val()) || 0;
    let card = parseFloat($('#card').val()) || 0;
    if(card > total) { card = total; $('#card').val(card.toFixed(2)); }
    if(card < 0) { card = 0; $('#card').val(card.toFixed(2)); }
    $('#numerar').val((total - card).toFixed(2));
};
function keepSessionAlive() {
    $.post('keep_alive.php').fail(function() {
        console.error('Eroare la menținerea sesiunii active.');
    });
}
setInterval(keepSessionAlive, 900000);
</script>
<?php if (in_array((int)($_SESSION['client_id'] ?? 0), [8, 17])) { include('meniu_vanzare_widget.php'); } ?>

</body>
</html>
