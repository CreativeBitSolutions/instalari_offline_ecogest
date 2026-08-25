<?php //vanzare_magazin.php
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
    $sql = "INSERT INTO $tabel_final_note
        (operator, locatie, cod_masa, status, cod_inchidere, nr_raport_z, cod_locatie,
         valoare_vanzare_cu_tva, tva_colectata, discount, numerar, card, tichete, rest, protocol, glovo)
        VALUES
        (:adm_id, :locatie, :cod_masa, 'S', 0, 0, :locatie,
         0, 0, 0, 0, 0, 0, 0, 0, 0)";
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
            $sql_select = "SELECT pret_vanzare, cantitate, cota_tva FROM $tabel_final_det_note WHERE id_vanz = :id_vz";
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
$total_sql = "SELECT sum(valoare_vanzare_cu_tva) as total FROM $tabel_final_det_note WHERE nr_bon=:nr_bon";
$total_stmt = $pdo->prepare($total_sql);
$total_stmt->execute(['nr_bon' => $nr_bon]);
$total_data = $total_stmt->fetch(PDO::FETCH_ASSOC);
$total_val_vz_cu_tva = $total_data['total'] ?? 0;
$total_val_vz_cu_tva = round($total_val_vz_cu_tva, 2);


// Preluare CIF din sesiune pentru a-l pasa catre JavaScript
$cif_sesiune = $_SESSION['cif_client'] ?? '';
$offlinePendingClosures = offline_pending_closures($pdo, (int)$cod_locatie);
$offlinePendingClosureCount = count($offlinePendingClosures);
$offlinePendingReceiptCount = array_sum(array_map(static function (array $closure): int {
    return (int)$closure['bonuri'];
}, $offlinePendingClosures));
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Vânzare Modernă</title>
    
    <link rel="stylesheet" href="vendor/offline/bootstrap4/bootstrap.min.css">
    <link rel="stylesheet" href="vendor/offline/fontawesome5/css/all.min.css">
        <link rel="stylesheet" href="vanzare_css.css?v=20260825-header4">
<style>
    /* Fix pentru a permite scroll în pagină când sunt deschise mai multe modale */
    .modal {
      overflow-y: auto;
    }
    </style>
<script src="js/offline-persistent-zoom.js?v=20260825-header2"></script>
</head>
<body>
    <div id="loading"></div>
    <?php if (!empty($_SESSION['offline_blocked_message'])): ?>
        <div class="alert alert-warning m-2 text-center">
            <?php
            echo htmlspecialchars($_SESSION['offline_blocked_message'], ENT_QUOTES, 'UTF-8');
            unset($_SESSION['offline_blocked_message']);
            ?>
        </div>
    <?php endif; ?>

    <div class="page-container">
        <header class="page-header">
            <div class="header-primary-actions">
            <?php
            $client_agecs = $_SESSION['client_id'] ?? null;
            if ($client_agecs == 2 || $client_agecs == 8) echo "<a href='vanzare_facturi.php'><button class='header-btn'>🧾 Facturi</button></a>";
            ?>
           <?php if ($client_agecs != 22): ?>
    <button data-toggle="modal" data-target="#sume_sertar" class="header-btn">💰 Sume Tura</button>
    <button data-toggle="modal" data-target="#sume_zi_curenta_modal" class="header-btn">📈 Sume Zi</button>
<?php endif; ?>
            <?php if ($client_agecs != 22 && $client_agecs != 18): ?>
            <button data-toggle="modal" data-target="#DiscountGlobal" class="header-btn">🏷️ Discount </button>

<?php endif; ?>
            <?php
            $btnText = 'Stoc';
            if ($client_agecs == 6) $btnText = 'Verif./Recalc. Stoc';
            echo "<button type='button' id='verifica_stoc_btn' data-toggle='modal' data-target='#verificaStocModal' class='header-btn'>📦 $btnText</button>";
            ?>
            <button data-toggle="modal" data-target="#relistareboncasamarcat" class="header-btn">📠 Retrim. Bon la CM</button>
            <?php if ($client_agecs != 18): ?>
            <a href='reglare_casa_marcat.php'><button class='header-btn'>🔨 Reglare dif. CM</button></a>
            <?php endif; ?>
            <?php if ((int)$client_agecs === 6): ?>
            <a href='vanzare_importa_pvi.php'><button class='header-btn'>📥 Import Caiet</button></a>
            <?php endif; ?>
<?php if ((int)$client_agecs === 1007 || (int)$client_agecs === 8): ?> 
            <a href='vanzare_importa_comenzi_online.php'><button class='header-btn'>📥 Import Comenzi Online</button></a>
            <?php endif; ?>
            </div>
            <div class="grup-actiuni header-secondary-actions">
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


                <?php if ($offlinePendingClosureCount > 0): ?>
                    <button type="button" class="header-btn btn-danger" data-toggle="modal" data-target="#raportZModal"
                            title="<?php echo $offlinePendingReceiptCount; ?> bonuri din ture închise așteaptă raportul Z">
                        <i class="fas fa-file-invoice"></i> Raport Z, <?php echo $offlinePendingClosureCount; ?> ture
                    </button>
                <?php endif; ?>
                <a href="export_vanzari_offline.php" class="header-btn btn-primary" style="text-decoration:none;color:#fff;" hidden aria-hidden="true">Export offline</a>
                <button type="button" class="header-btn btn-info" data-toggle="modal" data-target="#offlineSyncStatusModal" title="Situație transmitere date online"><i class="fas fa-cloud-upload-alt"></i> Transmitere online</button>
                 <div class="user-info">
                    <span>👤 <?php echo htmlspecialchars($admin_firstname . ' ' . $admin_lastname); ?></span>
                    <span>📄 <?php echo htmlspecialchars($nr_bon); ?></span>
                </div>


                <div class="header-tool-cluster" role="group" aria-label="Instrumente interfață">
                    <button type="button" class="header-btn btn-info header-icon-btn" data-toggle="modal" data-target="#helpModal" title="Ghiduri și ajutor" aria-label="Ghiduri și ajutor">
                        <i class="fas fa-question-circle"></i>
                    </button>
                    <button type="button" id="btn-fullscreen" class="header-btn btn-secondary header-icon-btn" title="Mod ecran complet" aria-label="Mod ecran complet">
                        <i class="fas fa-expand"></i>
                    </button>
                    <div class="header-zoom-controls" role="group" aria-label="Control zoom">
                        <button type="button" class="header-btn header-icon-btn" data-agecs-zoom-action="out" title="Micșorează interfața" aria-label="Micșorează interfața">
                            <i class="fas fa-search-minus"></i>
                        </button>
                        <button type="button" class="header-btn header-zoom-level" data-agecs-zoom-action="reset" data-agecs-zoom-value title="Revino la zoom 100%">100%</button>
                        <button type="button" class="header-btn header-icon-btn" data-agecs-zoom-action="in" title="Mărește interfața" aria-label="Mărește interfața">
                            <i class="fas fa-search-plus"></i>
                        </button>
                    </div>
                    <button type="button" id="btn_hard_refresh" class="header-btn btn-danger header-icon-btn" title="Șterge cache și reîncarcă tot" aria-label="Șterge cache și reîncarcă tot">
                        <i class="fas fa-sync-alt"></i>
                    </button>
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

  <!-- Buton Web Serial COM1 (ascuns implicit; va fi afișat prin JS doar dacă navigator.serial există) -->
        <?php if (in_array((int)($_SESSION['client_id'] ?? 0), [8, 18]) && (int)$cod_locatie === 1): ?>
  <button id="btn_webserial_com1_once"
          class="btn btn-warning"
          type="button"
          title="Citește cântar direct din COM1 (Web Serial)"
          style="display:none;">
    <i class="fa fa-plug"></i> Cantar
  </button>
<?php endif; ?>
<?php
$client_id_curent = (int)($_SESSION['client_id'] ?? 0);
$loc_curenta = (int)$cod_locatie;
?>

<?php if ((in_array($client_id_curent, [24, 28], true)) && $loc_curenta === 1): ?>
  <button id="citestecantarmicotexcom3"
          class="btn btn-warning"
          type="button"
          title="Citește cântar Micotex sau Magazin Rosia (COM3)"
          style="display:none;">
    <i class="fa fa-plug"></i> Cantar
  </button>

  <button id="btn_debug_micotex"
          class="btn btn-dark"
          type="button"
          data-toggle="modal"
          data-target="#modalDebugMicotex"
          title="Vezi ultimul răspuns de la cântar"
          style="display:none;">
    <i class="fas fa-bug"></i>
  </button>

<?php elseif ($client_id_curent === 24 && $loc_curenta === 2): ?>
  <button id="citestecantarmicotexcom1"
          class="btn btn-warning"
          type="button"
          title="Citește cântar Micotex (COM1)"
          style="display:none;">
    <i class="fa fa-plug"></i> Cantar
  </button>

  <button id="btn_debug_micotex"
          class="btn btn-dark"
          type="button"
          data-toggle="modal"
          data-target="#modalDebugMicotex"
          title="Vezi ultimul răspuns de la cântar"
          style="display:none;">
    <i class="fas fa-bug"></i>
  </button>
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
            <input type="hidden" id="baniprimiti_hidden" name="baniprimiti" value="">
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
          AND datetime(data_bon || ' ' || ora_bon) >= datetime('now','localtime','-24 hours') 
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
    
<?php include('offline_closure_flow_ui.php'); ?>

<?php include('modal_discount_global.php');?>
<?php include('modal_keyboard_procentual.php');?>
<?php include('modal_keyboard_fix.php');?>
<?php include('modal_keyboard_individual_procentual.php');?>
<?php include('modal_keyboard_individual_fix.php');?>
<?php include('modal_sume_zi_curenta.php');?>
<?php include('modal_ghiduri.php');?>
<?php include('modal_situatie_sincronizare.php');?>
<?php include('vanzare_modal_edit_cantitate.php');?>

<?php include('modal_cui_verificare_offline.php'); ?>
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


<div class="modal fade" id="modalDebugMicotex" tabindex="-1" role="dialog" aria-labelledby="modalDebugMicotexLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalDebugMicotexLabel">Debug cântar - ultimul răspuns</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Închide">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">

        <div class="form-group row">
          <label class="col-sm-2 col-form-label"><strong>Status</strong></label>
          <div class="col-sm-10">
            <div id="dbg_scale_status" class="form-control" style="height:auto; min-height:38px;">Idle</div>
          </div>
        </div>

        <div class="form-group row">
          <label class="col-sm-2 col-form-label"><strong>Greutate parsată</strong></label>
          <div class="col-sm-10">
            <input type="text" id="dbg_scale_weight" class="form-control" readonly>
          </div>
        </div>

        <div class="form-group row">
          <label class="col-sm-2 col-form-label"><strong>Port / sursă</strong></label>
          <div class="col-sm-10">
            <input type="text" id="dbg_scale_source" class="form-control" readonly>
          </div>
        </div>

        <div class="form-group row">
          <label class="col-sm-2 col-form-label"><strong>Bytes trimiși</strong></label>
          <div class="col-sm-10">
            <textarea id="dbg_scale_sent" class="form-control" rows="3" readonly style="font-family:Consolas, monospace;"></textarea>
          </div>
        </div>

        <div class="form-group row">
          <label class="col-sm-2 col-form-label"><strong>Răspuns brut HEX</strong></label>
          <div class="col-sm-10">
            <textarea id="dbg_scale_raw_hex" class="form-control" rows="4" readonly style="font-family:Consolas, monospace;"></textarea>
          </div>
        </div>

        <div class="form-group row">
          <label class="col-sm-2 col-form-label"><strong>Răspuns brut TEXT</strong></label>
          <div class="col-sm-10">
            <textarea id="dbg_scale_raw_text" class="form-control" rows="4" readonly style="font-family:Consolas, monospace;"></textarea>
          </div>
        </div>

        <div class="form-group row mb-0">
          <label class="col-sm-2 col-form-label"><strong>Log</strong></label>
          <div class="col-sm-10">
            <textarea id="dbg_scale_log" class="form-control" rows="8" readonly style="font-family:Consolas, monospace;"></textarea>
          </div>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" id="btn_clear_debug_micotex" class="btn btn-secondary">Șterge</button>
        <button type="button" class="btn btn-primary" data-dismiss="modal">Închide</button>
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
    <input type="hidden" id="cod_p_edit" name="cod_p_edit">
    <input type="hidden" id="current_qty_edit" name="current_qty_edit" value="1">
    <input type="hidden" id="current_tva_edit" name="current_tva_edit" value="0">
    <div class="form-group">
        <label for="new_product_name">Nume nou:</label>
        <input type="text" class="form-control" id="new_product_name" name="new_product_name" required autocomplete="off">
    </div>
    
   <div class="form-group">
    <label for="new_product_price">Preț nou (cu TVA):</label>
    <div class="input-group">
        <input type="number" step="0.00001" class="form-control" id="new_product_price" name="new_product_price" required autocomplete="off">
        <div class="input-group-append">
            <button class="btn btn-info" type="button" id="setPretAchizitieBtn" title="Setează prețul de vânzare la valoarea prețului de achiziție">
                <i class="fas fa-dollar-sign"></i> Preț Ach.
            </button>
        </div>
    </div>
</div>

<div id="hotelTaxPreviewWrapEdit" style="display:none;">
    <div class="form-group" style="background:#fff3cd; padding:10px; border-radius:6px;">
        <label for="input_total_booking_edit" style="font-weight:bold; margin-bottom:4px;">Total de incasat (Booking)</label>
        <small class="d-block text-muted mb-2">(Include Cazare + TVA + Taxa Hoteliera 2%)</small>
        <input type="number" id="input_total_booking_edit" class="form-control" step="0.0001" placeholder="Ex: 1000">
    </div>
    <div class="form-group" style="background:#f8f9fa; padding:10px; border-radius:6px;">
        <div class="row text-center">
            <div class="col-4">
                <small class="text-muted d-block">Valoare Neta</small>
                <strong id="preview_val_neta_edit">0.00</strong>
            </div>
            <div class="col-4">
                <small class="text-muted d-block">Valoare TVA</small>
                <strong id="preview_val_tva_edit">0.00</strong>
            </div>
            <div class="col-4">
                <small class="text-muted d-block">Total + Taxa</small>
                <strong id="preview_total_general_edit">0.00</strong>
            </div>
        </div>
    </div>
    <div class="form-group" style="background:#e8f4f8; padding:10px; border-radius:6px;">
        <label for="input_taxa_hoteliera_edit" style="font-weight:bold;">Taxa Hoteliera (2%)</label>
        <input type="text" id="input_taxa_hoteliera_edit" class="form-control" readonly value="0.00 Lei" style="font-weight:bold; color:#d35400;">
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





 <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/offline/popper/popper.min.js"></script>
    <script src="vendor/offline/bootstrap4/bootstrap.min.js"></script>
  <script>
$(document).ready(function() {
    
   // ======== CONSTANTE ȘI VARIABILE INIȚIALE ========
const nrBon = '<?php echo $nr_bon; ?>';
const clientId = '<?php echo $_SESSION['client_id']; ?>';
const offlineApiWebRoot = <?php echo json_encode(rtrim((string)offline_config_value('api_web_root'), '/')); ?>;
const codMasa = '<?php echo $cod_masa; ?>';
const sessionId = '<?php echo session_id(); ?>'; // <-- NOU: pentru a diferenția URL-urile în cache-ul browserului

const loadFile = clientId == 6 ? "load_prod_cu_stoc.php" : "load_prod.php";
const isClient1005 = ['1005', '8','1006'].includes(String(clientId));
const addProductEndpoint = isClient1005 ? "vanzare_adaug_prod_pe_nota_1005.php" : "vanzare_adaug_prod_pe_nota.php";
const bonPanelEndpoint = isClient1005 ? "elemente_bon_1005.php" : "elemente_bon.php";
const updateProductNameEndpoint = isClient1005 ? "vanzare_update_product_name_1005.php" : "vanzare_update_product_name.php";
const updateProductPermanentEndpoint = isClient1005 ? "vanzare_update_product_permanent_1005.php" : "vanzare_update_product_permanent.php";

let inactivityTimer = null;
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


// ======== DEBUG MICOTEX ========
const micotexDebugState = {
  source: '',
  status: 'Idle',
  weight: '',
  sent: '',
  rawHex: '',
  rawText: '',
  log: ''
};

function appendMicotexDebugLog(msg) {
  const now = new Date().toLocaleTimeString();
  micotexDebugState.log += `[${now}] ${msg}\n`;
}

function setMicotexDebugStatus(text) {
  micotexDebugState.status = text || '';
}

function renderMicotexDebugModal() {
  $('#dbg_scale_status').text(micotexDebugState.status || '');
  $('#dbg_scale_weight').val(micotexDebugState.weight || '');
  $('#dbg_scale_source').val(micotexDebugState.source || '');
  $('#dbg_scale_sent').val(micotexDebugState.sent || '');
  $('#dbg_scale_raw_hex').val(micotexDebugState.rawHex || '');
  $('#dbg_scale_raw_text').val(micotexDebugState.rawText || '');
  $('#dbg_scale_log').val(micotexDebugState.log || '');
}

function clearMicotexDebugState() {
  micotexDebugState.source = '';
  micotexDebugState.status = 'Idle';
  micotexDebugState.weight = '';
  micotexDebugState.sent = '';
  micotexDebugState.rawHex = '';
  micotexDebugState.rawText = '';
  micotexDebugState.log = '';
  renderMicotexDebugModal();
}

$('#modalDebugMicotex').on('show.bs.modal', function () {
  renderMicotexDebugModal();
});

$('#btn_clear_debug_micotex').on('click', function() {
  clearMicotexDebugState();
});
// ======== SFÂRȘIT DEBUG MICOTEX ========



// ============ Web Serial COM1 (client 8 & 18, loc 1) ============
// Trimite 'W' (și variante) și citește ~0.9s. Refolosește exact același decodor.
// La final: setează inputul doar când e chemat din buton; în variantă headless returnează greutatea.

(function initWebSerialCOM1() {
  const btn = document.getElementById('btn_webserial_com1_once');
  if (!btn) return;
  if (!('serial' in navigator)) return; // Chrome/Edge + HTTPS/localhost
  btn.style.display = 'inline-block';

  let busy = false;

  function bytesToHex(bytes) {
    return Array.from(bytes, b => b.toString(16).padStart(2, '0')).join(' ').toUpperCase();
  }

  function decodeScalePayload(bytes) {
    let s = '';
    for (const b of bytes) {
      if (b === 0x02 || b === 0x0D) continue; // STX/CR
      if (b >= 0xB0 && b <= 0xB9) { s += String.fromCharCode(0x30 + (b - 0xB0)); continue; }
      if (b === 0xAE || b === 0x2E) { s += '.'; continue; }
      if (b >= 0x30 && b <= 0x39) { s += String.fromCharCode(b); continue; } // ASCII 0..9
      if (b === 0x46) { s += 'F'; continue; }  // 'F'
      if (b === 0x6B) { s += 'k'; continue; }  // 'k'
      if (b === 0x67) { s += 'g'; continue; }  // 'g'
      if (b === 0x20) { s += ' '; continue; }  // space
    }
    s = s.trim();
    if (s.startsWith('F0.')) return 'F0.';
    if (s.startsWith('F1.')) return 'F1.';
    const matches = s.match(/\d+(?:\.\d+)?/g);
    if (matches && matches.length) return matches[matches.length - 1];
    return '';
  }

  // --- Nucleu reutilizabil. Dacă uiBtn=true, gestionează și butonul + setarea inputului.
  async function readOnceCore({ uiBtn = false } = {}) {
    if (busy) return { ok:false, w:null, reason:'busy' };
    busy = true;

    let originalHTML;
    if (uiBtn) {
      originalHTML = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Citește...';
    }

    let port;
    try {
      const granted = await navigator.serial.getPorts();
      port = granted[0] || await navigator.serial.requestPort();
      await port.open({ baudRate: 9600, dataBits: 8, stopBits: 1, parity: 'none' });
      try { await port.setSignals?.({ dataTerminalReady: true, requestToSend: true }); } catch (_) {}

      // flush input ~150ms
      try {
        const flusher = port.readable.getReader();
        const t0 = Date.now();
        while (Date.now() - t0 < 150) {
          const r = await Promise.race([ flusher.read(), new Promise(res => setTimeout(() => res({ value:null, done:false, _t:true }), 25)) ]);
          if (!r || r.done || r._t || !r.value) break;
        }
        flusher.releaseLock();
      } catch (_) {}

      const attempts = [
        { label: 'W',       bytes: new TextEncoder().encode('W') },
        { label: 'W\\r',    bytes: Uint8Array.from([0x57, 0x0D]) },
        { label: 'W\\r\\n', bytes: Uint8Array.from([0x57, 0x0D, 0x0A]) },
      ];

      let decoded = '';
      for (const attempt of attempts) {
        const writer = port.writable.getWriter();
        await writer.write(attempt.bytes);
        writer.releaseLock();
        console.log(`[COM1] Sent ${attempt.label}:`, bytesToHex(attempt.bytes));

        const readerTmp = port.readable.getReader();
        const chunks = [];
        const deadline = Date.now() + 900;
        while (Date.now() < deadline) {
          const res = await Promise.race([ readerTmp.read(), new Promise(res => setTimeout(() => res({ value:null, done:false, _t:true }), 50)) ]);
          if (res && res.value && !res._t) chunks.push(...res.value);
          if (res && res.done) break;
        }
        readerTmp.releaseLock();

        decoded = decodeScalePayload(new Uint8Array(chunks));
        console.log(`[COM1] Decoded after ${attempt.label}:`, decoded || '(gol)');
        if (decoded && (/\d/.test(decoded) || decoded === 'F0.' || decoded === 'F1.')) break;
      }

      if (decoded === 'F0.') alert('Ridicați produsul de pe cântar pentru recântărire!');
      if (decoded === 'F1.') alert('Cântar instabil, cântăriți din nou!');

      let w = NaN;
      if (decoded) {
        const m = decoded.match(/(\d+(?:\.\d+)?)/);
        if (m) w = parseFloat(m[1]);
      }
      if (!isNaN(w) && w >= 0) {
        if (uiBtn) {
          $('#cantitate_de_adaugat_prod').val(w.toFixed(3)).trigger('input').trigger('change');
        }
        return { ok:true, w: parseFloat(w.toFixed(3)) };
      } else {
        if (uiBtn) alert('Nu am putut extrage o greutate validă din răspunsul cântarului.');
        return { ok:false, w:null, reason:'invalid' };
      }

    } catch (err) {
      console.error('Eroare Web Serial COM1:', err);
      if (uiBtn) alert('Eroare Web Serial: ' + err);
      return { ok:false, w:null, reason:'exception' };
    } finally {
      try {
        await (async () => {
          try { const r = port?.readable?.getReader(); if (r) { await r.cancel(); r.releaseLock(); } } catch (_) {}
          try { await port?.close(); } catch (_) {}
        })();
      } catch(_) {}
      if (uiBtn) {
        btn.disabled = false;
        btn.innerHTML = originalHTML;
      }
      busy = false;
    }
  }

  // Click pe buton = UX ca înainte
  async function readOnceCOM1() {
    const res = await readOnceCore({ uiBtn:true });
    if (res.ok) console.log('[COM1] Final weight:', res.w.toFixed(3));
  }
  btn.addEventListener('click', readOnceCOM1);

  // EXPORT: citire „headless”, pentru a fi folosită din fluxul de scanare
  window.readScaleCOM1Once = async () => {
    const r = await readOnceCore({ uiBtn:false });
    return (r.ok ? r.w : null);
  };
})();


// end cantar

// ===================== Micotex COM3 (buton id="citestecantarmicotexcom3") =====================
(function initMicotexCOM3() {
  const btn = document.getElementById('citestecantarmicotexcom3');
  if (!btn) return;
  if (!('serial' in navigator)) return;

  btn.style.display = 'inline-block';
  const debugBtn = document.getElementById('btn_debug_micotex');
  if (debugBtn) debugBtn.style.display = 'inline-block';

  let busy = false;

  function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }

  function bytesToHex(bytes) {
    return Array.from(bytes, b => b.toString(16).padStart(2, '0')).join(' ').toUpperCase();
  }

  function cleanControlChars(str) {
    return String(str || '').replace(/[\x00-\x1F]/g, '');
  }

  // Același algoritm ca în pagina ta de test
  function parseMicotexCOM3(raw) {
  const s = String(raw || '').replace(/[\x00-\x1F]/g, ' ');

  // 1) Caută explicit toate valorile care au kg după ele
  //    ex: "0.105kg", "  1,250 kg", "-0.050KG"
  const kgMatches = [...s.matchAll(/(-?\d+(?:[.,]\d+)?)\s*kg\b/ig)]
    .map(m => parseFloat(String(m[1]).replace(',', '.')))
    .filter(v => Number.isFinite(v));

  // Preferăm ultima valoare > 0 găsită cu kg
  const positiveKgMatches = kgMatches.filter(v => v > 0);
  if (positiveKgMatches.length) {
    return positiveKgMatches[positiveKgMatches.length - 1];
  }

  // Dacă nu avem > 0, dar avem totuși valori cu kg, luăm ultima
  if (kgMatches.length) {
    return kgMatches[kgMatches.length - 1];
  }

  // 2) Fallback: caută toate numerele cu zecimale și preferă ultima valoare > 0
  const decimalMatches = [...s.matchAll(/-?\d+(?:[.,]\d+)/g)]
    .map(m => parseFloat(String(m[0]).replace(',', '.')))
    .filter(v => Number.isFinite(v));

  const positiveDecimals = decimalMatches.filter(v => v > 0);
  if (positiveDecimals.length) {
    return positiveDecimals[positiveDecimals.length - 1];
  }

  if (decimalMatches.length) {
    return decimalMatches[decimalMatches.length - 1];
  }

  // 3) Ultim fallback: dacă avem doar întregi, le tratăm ca grame / 1000
  const ints = s.match(/-?\d+/g);
  if (ints && ints.length) {
    const iv = parseInt(ints[ints.length - 1], 10);
    if (!Number.isNaN(iv)) return iv / 1000;
  }

  return null;
}

  async function flushInput(port, ms = 250) {
    try {
      const reader = port.readable.getReader();
      const deadline = Date.now() + ms;

      while (Date.now() < deadline) {
        const res = await Promise.race([
          reader.read(),
          new Promise(resolve => setTimeout(() => resolve({ _timeout: true }), 40))
        ]);

        if (!res || res.done || res._timeout || !res.value) break;
      }

      reader.releaseLock();
    } catch (e) {
      appendMicotexDebugLog('Flush skipped: ' + e.message);
      console.log('Micotex COM3 flush skipped:', e.message);
    }
  }

  async function readFor(port, ms = 700) {
    const reader = port.readable.getReader();
    const chunks = [];
    const deadline = Date.now() + ms;

    try {
      while (Date.now() < deadline) {
        const res = await Promise.race([
          reader.read(),
          new Promise(resolve => setTimeout(() => resolve({ _timeout: true }), 50))
        ]);

        if (res && res.value && !res._timeout) {
          chunks.push(...res.value);
        }

        if (res && res.done) break;
      }
    } finally {
      reader.releaseLock();
    }

    return new Uint8Array(chunks);
  }

  async function closePort(port) {
    if (!port) return;
    try {
      await port.close();
    } catch (e) {
      appendMicotexDebugLog('Port close warning: ' + e.message);
      console.log('Micotex COM3 close warning:', e.message);
    }
  }

  async function readOnceCore({ uiBtn = true } = {}) {
    if (busy) return { ok: false, weight: null, reason: 'busy' };
    busy = true;

    micotexDebugState.source = 'Micotex COM3';
    micotexDebugState.status = 'Pornire citire...';
    micotexDebugState.weight = '';
    micotexDebugState.sent = '';
    micotexDebugState.rawHex = '';
    micotexDebugState.rawText = '';
    micotexDebugState.log = '';
    appendMicotexDebugLog('Inițializare citire COM3.');
    renderMicotexDebugModal();

    let originalHTML = '';
    let port = null;

    try {
      if (uiBtn) {
        originalHTML = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Micotex COM3...';
      }

      appendMicotexDebugLog('Requesting serial port. Selectează dispozitivul COM3.');
      const grantedPorts = await navigator.serial.getPorts();
      port = grantedPorts[0] || await navigator.serial.requestPort();

      const info = port.getInfo ? port.getInfo() : {};
      appendMicotexDebugLog('Port selectat. USB info: ' + JSON.stringify(info));

      await port.open({
        baudRate: 9600,
        dataBits: 8,
        stopBits: 1,
        parity: 'none'
      });

      setMicotexDebugStatus('Port deschis.');
      appendMicotexDebugLog('Port COM3 deschis cu 9600 8N1.');
      renderMicotexDebugModal();

      try {
        await port.setSignals?.({
          dataTerminalReady: true,
          requestToSend: true
        });
      } catch (e) {
        appendMicotexDebugLog('Signal setup skipped: ' + e.message);
        console.log('Micotex COM3 signal setup skipped:', e.message);
      }

      // 1) ENQ
      setMicotexDebugStatus('Se trimite ENQ...');
      {
        const writer = port.writable.getWriter();
        const enq = Uint8Array.from([0x05]);
        await writer.write(enq);
        writer.releaseLock();

        const hex = bytesToHex(enq);
        micotexDebugState.sent += `ENQ: ${hex}\n`;
        appendMicotexDebugLog(`Trimis ENQ: ${hex}`);
        console.log('[Micotex COM3] Sent ENQ:', hex);
        renderMicotexDebugModal();
      }

      await sleep(200);

      // flush
      await flushInput(port, 250);
      appendMicotexDebugLog('Input flush executat.');
      renderMicotexDebugModal();

      // 2) DC1
      setMicotexDebugStatus('Se trimite DC1...');
      {
        const writer = port.writable.getWriter();
        const dc1 = Uint8Array.from([0x11]);
        await writer.write(dc1);
        writer.releaseLock();

        const hex = bytesToHex(dc1);
        micotexDebugState.sent += `DC1: ${hex}\n`;
        appendMicotexDebugLog(`Trimis DC1: ${hex}`);
        console.log('[Micotex COM3] Sent DC1:', hex);
        renderMicotexDebugModal();
      }

      await sleep(200);

      // 3) read response
      setMicotexDebugStatus('Se citește răspunsul...');
      renderMicotexDebugModal();

      const raw = await readFor(port, 700);
      const text = new TextDecoder('utf-8').decode(raw);
      const rawHex = bytesToHex(raw);

      micotexDebugState.rawHex = rawHex;
      micotexDebugState.rawText = text;
      appendMicotexDebugLog(`Răspuns HEX: ${rawHex}`);
      appendMicotexDebugLog(`Răspuns TEXT: ${JSON.stringify(text)}`);

      console.log('[Micotex COM3] RAW HEX:', rawHex);
      console.log('[Micotex COM3] RAW TXT:', JSON.stringify(text));

      const parsed = parseMicotexCOM3(text);

      if (parsed !== null && isFinite(parsed)) {
        const weight = Number(parsed).toFixed(3);

        micotexDebugState.weight = weight;
        micotexDebugState.status = 'Greutate citită cu succes.';
        appendMicotexDebugLog(`Greutate parsată: ${weight}`);

        $('#cantitate_de_adaugat_prod')
          .val(weight)
          .trigger('input')
          .trigger('change');

        renderMicotexDebugModal();
        return { ok: true, weight: parseFloat(weight) };
      }

      micotexDebugState.status = 'Nu s-a putut extrage o greutate validă.';
      appendMicotexDebugLog('Parsare eșuată: greutate invalidă.');
      renderMicotexDebugModal();

      if (uiBtn) {
        alert('Micotex COM3: nu am putut extrage o greutate validă.');
      }
      return { ok: false, weight: null, reason: 'invalid' };

    } catch (err) {
      console.error('Eroare Web Serial Micotex COM3:', err);
      micotexDebugState.status = 'Eroare la citirea COM3.';
      appendMicotexDebugLog('Eroare: ' + err.message);
      renderMicotexDebugModal();

      if (uiBtn) {
        alert('Eroare Web Serial Micotex COM3: ' + err.message);
      }
      return { ok: false, weight: null, reason: 'exception' };
    } finally {
      await closePort(port);

      if (uiBtn) {
        btn.disabled = false;
        btn.innerHTML = originalHTML;
      }

      busy = false;
      renderMicotexDebugModal();
    }
  }

  async function readOnceCOM3() {
    const res = await readOnceCore({ uiBtn: true });
    if (res.ok) {
      console.log('[Micotex COM3] Final weight:', res.weight.toFixed(3));
    }
  }

  btn.addEventListener('click', readOnceCOM3);

  // export pentru folosire ulterioară din alte fluxuri
  window.readScaleMicotexCOM3Once = async () => {
    const res = await readOnceCore({ uiBtn: false });
    return res.ok ? res.weight : null;
  };
})();


// ===================== Micotex COM1 (buton id="citestecantarmicotexcom1") =====================
(function initMicotexCOM1() {
  const btn = document.getElementById('citestecantarmicotexcom1');
  if (!btn) return;
  if (!('serial' in navigator)) return;

  btn.style.display = 'inline-block';
  const debugBtn = document.getElementById('btn_debug_micotex');
  if (debugBtn) debugBtn.style.display = 'inline-block';

  let busy = false;
  const sleep = (ms) => new Promise(r => setTimeout(r, ms));
  const bytesToHex = (bytes) => Array.from(bytes, b => b.toString(16).padStart(2, '0')).join(' ').toUpperCase();

  // Parser „stil FoxPro” pentru COM1:
  // v1 = SUBSTR(cc, LEN(cc)-ATCC(cc,'KG')-10, 6)
  // semn = SUBSTR(cc, LEN(cc)-ATCC(cc,'KG')-11, 1)
  function parseMicotexCOM1(raw) {
    const s = String(raw || '').replace(/[\x00-\x1F]/g, '');
    const S = s.toUpperCase();
    let idxKG = S.lastIndexOf('KG');
    if (idxKG < 0) idxKG = s.lastIndexOf('kg');

    if (idxKG >= 0) {
      const start = Math.max(0, idxKG - 10);
      const chunk = s.substr(start, 6);
      const signChar = s.charAt(idxKG - 11);
      const semn = (signChar === '-') ? -1 : 1;

      let num = parseFloat(chunk.replace(/[^0-9,.-]/g, '').replace(',', '.'));
      if (!Number.isNaN(num)) return semn * num;

      // fallback robust în fereastra din stânga „KG”
      const wins = s.substring(Math.max(0, idxKG - 30), idxKG);
      let m = wins.match(/-?\d+[.,]\d{3}(?!\d)/) || wins.match(/-?\d+[.,]\d{2,3}(?!\d)/) || wins.match(/-?\d+[.,]\d+/);
      if (m) return parseFloat(m[0].replace(',', '.'));

      const ints = wins.match(/-?\d+/g);
      if (ints && ints.length) {
        const iv = parseInt(ints[ints.length - 1], 10);
        if (!Number.isNaN(iv)) return iv / 1000;
      }
    }

    // fallback global
    const m2 = s.match(/-?\d+[.,]\d{3}(?!\d)/) || s.match(/-?\d+[.,]\d+/);
    if (m2) return parseFloat(m2[m2.length - 1].replace(',', '.'));

    const ints2 = s.match(/-?\d+/g);
    if (ints2 && ints2.length) {
      const iv2 = parseInt(ints2[ints2.length - 1], 10);
      if (!Number.isNaN(iv2)) return iv2 / 1000;
    }

    return null;
  }

  async function readOnceCOM1() {
    if (busy) return;
    busy = true;

    micotexDebugState.source = 'Micotex COM1';
    micotexDebugState.status = 'Pornire citire...';
    micotexDebugState.weight = '';
    micotexDebugState.sent = '';
    micotexDebugState.rawHex = '';
    micotexDebugState.rawText = '';
    micotexDebugState.log = '';
    appendMicotexDebugLog('Inițializare citire COM1.');
    renderMicotexDebugModal();

    const saved = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Micotex COM1...';

    let port;
    try {
      appendMicotexDebugLog('Requesting serial port. Selectează dispozitivul COM1.');
      const granted = await navigator.serial.getPorts();
      port = granted[0] || await navigator.serial.requestPort();

      const info = port.getInfo ? port.getInfo() : {};
      appendMicotexDebugLog('Port selectat. USB info: ' + JSON.stringify(info));

      await port.open({ baudRate: 9600, dataBits: 8, stopBits: 1, parity: 'none' });
      setMicotexDebugStatus('Port deschis.');
      appendMicotexDebugLog('Port COM1 deschis cu 9600 8N1.');
      renderMicotexDebugModal();

      try {
        await port.setSignals?.({ dataTerminalReady: true, requestToSend: true });
      } catch (e) {
        appendMicotexDebugLog('Signal setup skipped: ' + e.message);
      }

      // 1) ENQ
      setMicotexDebugStatus('Se trimite ENQ...');
      {
        const w = port.writable.getWriter();
        const enq = Uint8Array.from([0x05]);
        await w.write(enq);
        w.releaseLock();

        const hex = bytesToHex(enq);
        micotexDebugState.sent += `ENQ: ${hex}\n`;
        appendMicotexDebugLog(`Trimis ENQ: ${hex}`);
        renderMicotexDebugModal();
      }

      await sleep(200);

      // flush ~250ms
      try {
        const flusher = port.readable.getReader();
        const t0 = Date.now();
        while (Date.now() - t0 < 250) {
          const r = await Promise.race([flusher.read(), new Promise(res => setTimeout(() => res({ value:null, done:false, _t:true }), 50))]);
          if (!r || r.done || r._t || !r.value) break;
        }
        flusher.releaseLock();
        appendMicotexDebugLog('Input flush executat.');
      } catch (e) {
        appendMicotexDebugLog('Flush skipped: ' + e.message);
      }
      renderMicotexDebugModal();

      // 2) DC1
      setMicotexDebugStatus('Se trimite DC1...');
      {
        const w2 = port.writable.getWriter();
        const dc1 = Uint8Array.from([0x11]);
        await w2.write(dc1);
        w2.releaseLock();

        const hex = bytesToHex(dc1);
        micotexDebugState.sent += `DC1: ${hex}\n`;
        appendMicotexDebugLog(`Trimis DC1: ${hex}`);
        renderMicotexDebugModal();
      }

      await sleep(200);

      // 3) Citește ~700ms
      setMicotexDebugStatus('Se citește răspunsul...');
      renderMicotexDebugModal();

      const reader = port.readable.getReader();
      const chunks = [];
      const deadline = Date.now() + 700;
      while (Date.now() < deadline) {
        const res = await Promise.race([reader.read(), new Promise(res => setTimeout(() => res({ value:null, done:false, _t:true }), 50))]);
        if (res && res.value && !res._t) chunks.push(...res.value);
        if (res && res.done) break;
      }
      reader.releaseLock();

      const raw = new Uint8Array(chunks);
      const text = new TextDecoder('utf-8').decode(raw);
      const rawHex = bytesToHex(raw);

      micotexDebugState.rawHex = rawHex;
      micotexDebugState.rawText = text;
      appendMicotexDebugLog(`Răspuns HEX: ${rawHex}`);
      appendMicotexDebugLog(`Răspuns TEXT: ${JSON.stringify(text)}`);

      console.log('[Micotex COM1] RAW HEX:', rawHex);
      console.log('[Micotex COM1] RAW TXT:', JSON.stringify(text));

      const val = parseMicotexCOM1(text);
      if (val !== null && isFinite(val)) {
        const weight = Number(val).toFixed(3);

        micotexDebugState.weight = weight;
        micotexDebugState.status = 'Greutate citită cu succes.';
        appendMicotexDebugLog(`Greutate parsată: ${weight}`);

        $('#cantitate_de_adaugat_prod').val(weight).trigger('input').trigger('change');
        renderMicotexDebugModal();
      } else {
        micotexDebugState.status = 'Nu s-a putut extrage o greutate validă.';
        appendMicotexDebugLog('Parsare eșuată: greutate invalidă.');
        renderMicotexDebugModal();
        alert('Micotex COM1: nu am putut extrage o greutate validă.');
      }
    } catch (err) {
      console.error('Eroare Web Serial Micotex COM1:', err);
      micotexDebugState.status = 'Eroare la citirea COM1.';
      appendMicotexDebugLog('Eroare: ' + (err.message || err));
      renderMicotexDebugModal();
      alert('Eroare Web Serial Micotex COM1: ' + err);
    } finally {
      try {
        try { const r = port?.readable?.getReader(); if (r) { await r.cancel(); r.releaseLock(); } } catch(_) {}
        try { await port?.close(); } catch(_) {}
      } catch(_) {}
      btn.disabled = false;
      btn.innerHTML = saved;
      busy = false;
      renderMicotexDebugModal();
    }
  }

  btn.addEventListener('click', readOnceCOM1);
  window.readScaleMicotexCOM1Once = readOnceCOM1;
})();

// end micotex cantar



    // Referințe către elemente
    const categoryTabs = $('#category-tabs');
    const productListContainer = $('#product-list-container');
    const nameFilterInput = $('#prod_filter');
    const barcodeFilterInput = $('#prod_filter_cod_bare');
    const quantityInput = $('#cantitate_de_adaugat_prod');


    // === LOGICA PENTRU MODIFICARE CANTITATE PE RAND ===

    // 1. Deschide modalul la click pe butonul de cantitate din bon
    $(document).on('click', '.edit-qty-btn', function() {
        const idVanz = $(this).data('idvanz');
        const currentQty = $(this).data('current-qty');
        const productName = $(this).data('nume');

        $('#qty_id_vanz').val(idVanz);
        $('#qty_product_name_label').text(productName);
        $('#new_qty_input').val(currentQty);

        $('#editQtyModal').modal('show');
    });

    // 2. Focus pe input când se deschide modalul
    $('#editQtyModal').on('shown.bs.modal', function () {
        $('#new_qty_input').focus().select();
    });

    // 3. Suport pentru tasta Enter
    $('#new_qty_input').on('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            $('#saveQtyChange').click();
        }
    });

    // 4. Salvarea noii cantități
    $('#saveQtyChange').on('click', function() {
        const idVanz = $('#qty_id_vanz').val();
        const newQty = $('#new_qty_input').val();

        if (newQty <= 0 || newQty === '') {
            alert('Introduceți o cantitate validă mai mare ca 0.');
            return;
        }

        // Afișăm loading
        if(typeof showLoading === 'function') showLoading(true);
        $('#editQtyModal').modal('hide');

        $.ajax({
            url: 'vanzare_update_product_qty.php',
            type: 'POST',
            data: {
                id_vanz: idVanz,
                new_qty: newQty
            },
            success: function(response) {
                // Reîncărcăm panoul bonului
                if(typeof reloadBonPanel === 'function') {
                    reloadBonPanel();
                } else {
                    // Fallback dacă funcția reloadBonPanel nu e accesibilă
                    location.reload(); 
                }
            },
            error: function(xhr) {
                alert('Eroare: ' + xhr.responseText);
            },
            complete: function() {
                if(typeof showLoading === 'function') showLoading(false);
            }
        });
    });
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
                    data-current-qty="${item.cantitate}"
                    data-current-tva="${item.cota_tva}"
                    title="Modifică Produs">
                    <i class="fas fa-pencil-alt"></i>
                </button>
            `;
        }

        // === MODIFICAREA E AICI JOS (BUTONUL DE CANTITATE) ===
        return `
        <li class="receipt-item" id="item-${item.id_vanz}">
            <div class="product-info">
                <div class="product-name">${escapeHTML(item.nume)}</div>
                <div class="product-price">${item.pret_vanzare} RON / ${unitate_masura}</div>
            </div>
            
            <button type="button" class="btn btn-primary edit-qty-btn" 
                    data-idvanz="${item.id_vanz}" 
                    data-current-qty="${item.cantitate}"
                    data-nume="${escapeHTML(item.nume)}"
                    title="Modifică cantitatea">
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
            const qtyBtn = itemElement.find('.edit-qty-btn').first();
            qtyBtn.text(`x ${item.cantitate}`);
            qtyBtn.attr('data-current-qty', item.cantitate).data('current-qty', item.cantitate);
            itemElement.find('.item-value').text(`${parseFloat(item.valoare_vanzare_cu_tva).toFixed(2)} RON`);

            const editBtn = itemElement.find('.edit-product-btn').first();
            if (editBtn.length) {
                if (typeof item.pret_vanzare !== 'undefined') {
                    editBtn.attr('data-current-price', item.pret_vanzare).data('current-price', item.pret_vanzare);
                }
                if (typeof item.nume !== 'undefined') {
                    editBtn.attr('data-current-name', item.nume).data('current-name', item.nume);
                }
                if (typeof item.cantitate !== 'undefined') {
                    editBtn.attr('data-current-qty', item.cantitate).data('current-qty', item.cantitate);
                }
                if (typeof item.cota_tva !== 'undefined') {
                    editBtn.attr('data-current-tva', item.cota_tva).data('current-tva', item.cota_tva);
                }
            }
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

    const hotelTaxEditorState = {
        active: false
    };

    function parseNumberOrDefault(value, fallback = 0) {
        const normalized = String(value ?? '').replace(',', '.').trim();
        const parsed = parseFloat(normalized);
        return Number.isFinite(parsed) ? parsed : fallback;
    }

    function isHotelTaxEligibleName(name) {
        const upper = String(name || '').toUpperCase();
        return upper.includes('CAZARE') && !upper.includes('TAXA HOTELIERA');
    }

    function resetHotelTaxPreviewEdit() {
        $('#preview_val_neta_edit').text('0.00');
        $('#preview_val_tva_edit').text('0.00');
        $('#preview_total_general_edit').text('0.00');
        $('#input_taxa_hoteliera_edit').val('0.00 Lei');
    }

    function setHotelTaxPreviewVisible(visible) {
        if (visible) {
            $('#hotelTaxPreviewWrapEdit').show();
        } else {
            $('#hotelTaxPreviewWrapEdit').hide();
        }
    }

    function readQtyAndTvaForEdit() {
        let qty = parseNumberOrDefault($('#current_qty_edit').val(), 1);
        if (Math.abs(qty) < 0.0000001) {
            qty = 1;
        }

        const tva = parseNumberOrDefault($('#current_tva_edit').val(), 0);
        return { qty, tva };
    }

    function updateHotelTaxPreviewFromPriceEdit() {
        const { qty, tva } = readQtyAndTvaForEdit();
        const pretCuTva = Math.abs(parseNumberOrDefault($('#new_product_price').val(), 0));
        const qtyAbs = Math.abs(qty);

        if (pretCuTva <= 0 || qtyAbs <= 0) {
            resetHotelTaxPreviewEdit();
            $('#input_total_booking_edit').val('');
            return;
        }

        const semn = qty < 0 ? -1 : 1;
        const valoareCazareCuTVAAbs = pretCuTva * qtyAbs;
        const valoareNetaAbs = valoareCazareCuTVAAbs / (1 + (tva / 100));
        const valoareTVAAbs = valoareNetaAbs * (tva / 100);
        const valoareTaxaAbs = valoareNetaAbs * 0.02;
        const totalBookingAbs = valoareCazareCuTVAAbs + valoareTaxaAbs;

        $('#preview_val_neta_edit').text((valoareNetaAbs * semn).toFixed(2));
        $('#preview_val_tva_edit').text((valoareTVAAbs * semn).toFixed(2));
        $('#preview_total_general_edit').text((totalBookingAbs * semn).toFixed(2));
        $('#input_taxa_hoteliera_edit').val((valoareTaxaAbs * semn).toFixed(2) + ' Lei');
        $('#input_total_booking_edit').val((totalBookingAbs * semn).toFixed(2));
    }

    function updateHotelTaxPreviewFromBookingEdit() {
        const { qty, tva } = readQtyAndTvaForEdit();
        const totalBooking = parseNumberOrDefault($('#input_total_booking_edit').val(), 0);

        if (!Number.isFinite(totalBooking) || Math.abs(totalBooking) < 0.0000001) {
            resetHotelTaxPreviewEdit();
            return;
        }

        const qtyAbs = Math.abs(qty) || 1;
        const totalBookingAbs = Math.abs(totalBooking);
        const coeficientTotal = 1 + (tva / 100) + 0.02;
        const valoareNetaTotalaAbs = totalBookingAbs / coeficientTotal;
        const valoareTaxaAbs = valoareNetaTotalaAbs * 0.02;
        const valoareCazareCuTVATotalaAbs = totalBookingAbs - valoareTaxaAbs;
        const pretUnitarCazareCuTVA = valoareCazareCuTVATotalaAbs / qtyAbs;
        const semn = qty < 0 ? -1 : 1;
        const valoareTVAAbs = valoareNetaTotalaAbs * (tva / 100);

        $('#new_product_price').val(pretUnitarCazareCuTVA.toFixed(5));
        $('#preview_val_neta_edit').text((valoareNetaTotalaAbs * semn).toFixed(2));
        $('#preview_val_tva_edit').text((valoareTVAAbs * semn).toFixed(2));
        $('#preview_total_general_edit').text((totalBookingAbs * semn).toFixed(2));
        $('#input_taxa_hoteliera_edit').val((valoareTaxaAbs * semn).toFixed(2) + ' Lei');
    }

    function syncHotelTaxEditVisibilityAndPreview() {
        if (!isClient1005) {
            hotelTaxEditorState.active = false;
            setHotelTaxPreviewVisible(false);
            resetHotelTaxPreviewEdit();
            $('#input_total_booking_edit').val('');
            return false;
        }

        const numeProdus = $('#new_product_name').val() || '';
        const shouldShow = isHotelTaxEligibleName(numeProdus);
        hotelTaxEditorState.active = shouldShow;
        setHotelTaxPreviewVisible(shouldShow);

        if (!shouldShow) {
            resetHotelTaxPreviewEdit();
            $('#input_total_booking_edit').val('');
            return false;
        }

        updateHotelTaxPreviewFromPriceEdit();
        return true;
    }

    function extractQtyFromEditButton($btn) {
        let qty = parseNumberOrDefault($btn.attr('data-current-qty'), NaN);
        if (!Number.isFinite(qty)) {
            qty = parseNumberOrDefault($btn.closest('.receipt-item').find('.edit-qty-btn').first().attr('data-current-qty'), 1);
        }
        if (Math.abs(qty) < 0.0000001) {
            qty = 1;
        }
        return qty;
    }

    function extractTvaFromEditButton($btn) {
        let tva = parseNumberOrDefault($btn.attr('data-current-tva'), NaN);
        if (!Number.isFinite(tva)) {
            tva = parseNumberOrDefault($btn.closest('.receipt-item').find('.discount-btn').first().attr('data-value'), 0);
        }
        return Number.isFinite(tva) ? tva : 0;
    }

    function openEditModalWithItem(itemData) {
        $('#id_vanz_edit').val(itemData.id_vanz || '');
        $('#cod_p_edit').val(itemData.cod_p || '');
        $('#new_product_name').val(itemData.nume || '');
        $('#new_product_price').val(itemData.pret_vanzare || '');
        $('#current_qty_edit').val(itemData.cantitate || 1);
        $('#current_tva_edit').val(itemData.cota_tva || 0);

        const showHotelPreview = syncHotelTaxEditVisibilityAndPreview();
        $('#editProductModal').modal('show');

        if (showHotelPreview) {
            setTimeout(function() {
                $('#input_total_booking_edit').focus().select();
            }, 120);
        }
    }

    function maybeOpenHotelierEditFromResponse(response) {
        if (!isClient1005 || !response || !response.popup_edit_item) {
            return false;
        }

        const popupItem = response.popup_edit_item;
        if (!isHotelTaxEligibleName(popupItem.nume || '')) {
            return false;
        }

        openEditModalWithItem({
            id_vanz: popupItem.id_vanz,
            cod_p: popupItem.cod_p,
            nume: popupItem.nume,
            pret_vanzare: popupItem.pret_vanzare,
            cantitate: popupItem.cantitate,
            cota_tva: popupItem.cota_tva
        });
        return true;
    }

    $('#new_product_price').on('input keyup change', function() {
        if (hotelTaxEditorState.active) {
            updateHotelTaxPreviewFromPriceEdit();
        }
    });

    $('#input_total_booking_edit').on('input keyup change', function() {
        if (hotelTaxEditorState.active) {
            updateHotelTaxPreviewFromBookingEdit();
        }
    });

    $('#new_product_name').on('input change', function() {
        syncHotelTaxEditVisibilityAndPreview();
    });

    $('#editProductModal').on('hidden.bs.modal', function() {
        hotelTaxEditorState.active = false;
        setHotelTaxPreviewVisible(false);
        resetHotelTaxPreviewEdit();
        $('#input_total_booking_edit').val('');
    });

    
    // ===========================================================
    // ========= SFÂRȘIT NOUA LOGICĂ DE MANIPULARE A BONULUI =========
    // ===========================================================
    
    // ======== LOGICA PENTRU HARD REFRESH & CLEAR CACHE ========
    $('#btn_hard_refresh').on('click', function() {
        // Confirmare opțională pentru a nu apăsa din greșeală
        if (!confirm("Ești sigur că vrei să ștergi cache-ul și să reîncarci aplicația?")) {
            return;
        }

        // Arată loading
        showLoading(true);

        // 1. Curățare cache SERVER
        $.ajax({
            url: 'ajax_clear_cache.php',
            type: 'POST',
            dataType: 'json',
            success: function(response) {
                console.log('Server cache cleared:', response);
            },
            error: function(xhr, status, error) {
                console.error('Eroare la ștergerea cache-ului server:', error);
                // Continuăm totuși cu refresh-ul clientului
            },
            complete: function() {
                // 2. Curățare cache CLIENT (Browser Memory)
                
                // Golim variabilele globale JS definite în script
                if (typeof categoryPageCache !== 'undefined') categoryPageCache.clear();
                if (typeof productCache !== 'undefined') productCache = {};
                
                // Ștergem sessionStorage (dacă există date stocate)
                sessionStorage.clear();

                // 3. FORȚARE RELOAD (Efect de Ctrl+F5)
                // Adăugăm un parametru unic de timp la URL pentru a forța browserul 
                // să descarce din nou toate resursele, ignorând cache-ul intern.
                
                const url = new URL(window.location.href);
                url.searchParams.set('force_reload', new Date().getTime());
                
                // Redirecționare
                window.location.href = url.toString();
            }
        });
    });
    // ==========================================================
    
    // ======== LOGICA AVANSATĂ PENTRU FULL SCREEN CU PERSISTENȚĂ ========
    
    const elem = document.documentElement;
    const btnFullscreen = $('#btn-fullscreen');

    // 1. Funcția care execută efectiv comanda (Intrare/Iesire)
    function toggleFullScreen() {
        if (!document.fullscreenElement && 
            !document.mozFullScreenElement && 
            !document.webkitFullscreenElement && 
            !document.msFullscreenElement) {
            
            // INTRARE
            if (elem.requestFullscreen) elem.requestFullscreen();
            else if (elem.msRequestFullscreen) elem.msRequestFullscreen();
            else if (elem.mozRequestFullScreen) elem.mozRequestFullScreen();
            else if (elem.webkitRequestFullscreen) elem.webkitRequestFullscreen(Element.ALLOW_KEYBOARD_INPUT);
            
        } else {
            // IESIRE
            if (document.exitFullscreen) document.exitFullscreen();
            else if (document.msExitFullscreen) document.msExitFullscreen();
            else if (document.mozCancelFullScreen) document.mozCancelFullScreen();
            else if (document.webkitExitFullscreen) document.webkitExitFullscreen();
        }
    }

    // 2. Click pe buton
    btnFullscreen.on('click', function(e) {
        e.preventDefault(); // Previne alte acțiuni
        toggleFullScreen();
    });

    // 3. Actualizare iconiță ȘI SALVARE STARE în LocalStorage
    function updateFullscreenIcon() {
        const icon = btnFullscreen.find('i');
        
        if (document.fullscreenElement || 
            document.webkitFullscreenElement || 
            document.mozFullScreenElement || 
            document.msFullscreenElement) {
            
            // Ești în Full Screen
            icon.removeClass('fa-expand').addClass('fa-compress');
            btnFullscreen.attr('title', 'Ieșire Ecran Complet');
            btnFullscreen.removeClass('btn-secondary').addClass('btn-warning');
            
            // === MEMORARE: Utilizatorul vrea Full Screen ===
            localStorage.setItem('pos_wants_fullscreen', '1');

        } else {
            // Ești Normal
            icon.removeClass('fa-compress').addClass('fa-expand');
            btnFullscreen.attr('title', 'Mod Ecran Complet');
            btnFullscreen.removeClass('btn-warning').addClass('btn-secondary');

            // === MEMORARE: Utilizatorul NU vrea Full Screen ===
            localStorage.setItem('pos_wants_fullscreen', '0');
        }
    }

    // Ascultători pentru schimbarea stării (F11, ESC, Button)
    document.addEventListener('fullscreenchange', updateFullscreenIcon);
    document.addEventListener('webkitfullscreenchange', updateFullscreenIcon);
    document.addEventListener('mozfullscreenchange', updateFullscreenIcon);
    document.addEventListener('MSFullscreenChange', updateFullscreenIcon);

    // 4. === RESTAURARE DUPĂ REFRESH ===
    // Verificăm dacă utilizatorul a lăsat Full Screen activat înainte de refresh
    if (localStorage.getItem('pos_wants_fullscreen') === '1') {
        // Browserul NU ne lasă să intrăm automat. 
        // Așteptăm PRIMUL click oriunde pe pagină (sau o tastă apăsată) pentru a reactiva.
        
        const restoreFullscreen = function() {
            // Verificăm dacă nu cumva e deja full screen
            if (!document.fullscreenElement) {
                // Încercăm să activăm
                if (elem.requestFullscreen) elem.requestFullscreen().catch(err => {
                    // Ignorăm erorile silențioase dacă browserul refuză
                    console.log('Așteptare interacțiune utilizator pentru FullScreen...');
                });
                else if (elem.webkitRequestFullscreen) elem.webkitRequestFullscreen();
            }
            // Scoatem ascultătorul după ce a fost executat o dată, ca să nu deranjeze
            $(document).off('click keydown touchstart', restoreFullscreen);
        };

        // Ascultăm orice interacțiune pe document
        $(document).one('click keydown touchstart', restoreFullscreen);
    }
    // ===================================================================

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
        $("#bon-curent-panel").load(`${bonPanelEndpoint}?nr_bon=${nrBon}&cod_masa=${codMasa}&v=${cacheBuster}`, function() {
            const totalsElement = $(this).find('.receipt-totals');
            if (totalsElement.length) {
                $('.grup-dreapta').html(totalsElement);
            }
            // Sincronizăm totalul calculat de PHP cu funcția noastră de calcul
            recalculateTotals(); 
        });
        
        // Logica de focus rămâne neschimbată
        if (['18', '21', '22','16'].includes(String(clientId))) {
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
    
    async function processBarcode() {
        const codBare = barcodeFilterInput.val();
        if (!codBare) return;

        // helper: adaugă folosind DETALII (fără lookup prin cod_bare)
        const addWithDetails = (p, qty) => {
            const productData = {
                prod: p.cod_produs, bonul: nrBon, cod_masa: codMasa,
                cantitate_de_adaugat_prod: qty,
                nume_produs: p.nume, pret_vanzare: p.pret_cu_tva,
                cota_tva: p.cota_tva, um: p.um,
                sgr: p.sgr, sgr_pet: p.sgr_pet, sgr_alumin: p.sgr_alumin, sgr_sticla: p.sgr_sticla,
                gestiune: p.denumire_gestiune
            };
            return $.get(addProductEndpoint, productData, 'json');
        };

        const onDone = (response) => {
            processAddProductResponse(response);
            const openedAutoPopup = maybeOpenHotelierEditFromResponse(response);
            finalizeAndResetInputs(openedAutoPopup);
        };

        const onFail = () => { alert('Eroare: produs negăsit sau fără stoc.'); barcodeFilterInput.select(); };

        try {
            // ——— DOAR pentru client 8: verificăm UM; dacă e KG și avem Web Serial, citim cântarul ÎNAINTE de adăugare
            if (String(clientId) === '18') {
                let p = productCache[codBare];
                if (!p) {
                    try {
                        p = await $.getJSON('vanzare_lookup_prod_by_barcode.php', { prod_cod_bare: codBare });
                        if (p && p.cod_produs) productCache[codBare] = p;
                    } catch (_) { /* dacă nu găsim, cădem pe fluxul clasic mai jos */ }
                }

                if (p) {
                    let qty = quantityInput.val() || 1;

                    // Web Serial disponibil și UM cu "KG" ?
                    const umHasKG = typeof p.um === 'string' && p.um.toUpperCase().includes('KG');
                    const canSerial = (typeof window.readScaleCOM1Once === 'function') && ('serial' in navigator);

                    if (umHasKG && canSerial) {
                        // ✅ Citește COM1 folosind aceeași logică ca butonul (fără UI)
                        const w = await window.readScaleCOM1Once();
                        if (typeof w === 'number' && !isNaN(w)) {
                            qty = w.toFixed(3);
                            quantityInput.val(qty); // reflectăm vizual
                        }
                        // dacă nu reușește citirea, mergem cu qty curent (fallback)
                    }

                    return addWithDetails(p, qty).done(onDone).fail(onFail);
                }
                // dacă nu avem detalii, cădem pe fluxul clasic de mai jos
            }

            // ——— Flux CLASIC (toți ceilalți clienți & fallback)
            if (productCache[codBare]) {
                const p = productCache[codBare];
                const qty = quantityInput.val() || 1;
                return addWithDetails(p, qty).done(onDone).fail(onFail);
            } else {
                return $.get(addProductEndpoint, {
                    prod_cod_bare: codBare, bonul: nrBon, cod_masa: codMasa,
                    cantitate_de_adaugat_prod: quantityInput.val() || 1
                }, 'json').done(resp => {
                    if (resp.product_details_for_cache) productCache[codBare] = resp.product_details_for_cache;
                    onDone(resp);
                }).fail(onFail);
            }
        } catch (e) {
            // fallback robust
            if (productCache[codBare]) {
                const p = productCache[codBare];
                const qty = quantityInput.val() || 1;
                addWithDetails(p, qty).done(onDone).fail(onFail);
            } else {
                $.get(addProductEndpoint, {
                    prod_cod_bare: codBare, bonul: nrBon, cod_masa: codMasa,
                    cantitate_de_adaugat_prod: quantityInput.val() || 1
                }, 'json').done(onDone).fail(onFail);
            }
        }
    }

    function finalizeAndResetInputs(skipFocus = false) {
        // Aici păstrăm logica ta originală de focus și resetare, dar fără reloadBonPanel
        barcodeFilterInput.val('');
        if (!skipFocus) {
            barcodeFilterInput.focus();
        }
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
        const $btn = $(this);
        openEditModalWithItem({
            id_vanz: $btn.data('idvanz'),
            cod_p: $btn.data('codp'),
            nume: $btn.data('current-name'),
            pret_vanzare: $btn.data('current-price'),
            cantitate: extractQtyFromEditButton($btn),
            cota_tva: extractTvaFromEditButton($btn)
        });
    });

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
                    if (hotelTaxEditorState.active) {
                        updateHotelTaxPreviewFromPriceEdit();
                    }
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

    // după definiții:
    window.processAddProductResponse = processAddProductResponse;
    window.reloadBonPanel = reloadBonPanel;

    // reloadBonPanel doar dacă avem nevoie de el ca noi încercăm să încărcăm doar o singură dată
    function reloadBonPanel() {
        const cacheBuster = new Date().getTime();
        $("#bon-curent-panel").load(`${bonPanelEndpoint}?nr_bon=${nrBon}&cod_masa=${codMasa}&v=${cacheBuster}`, function() {
            // **MODIFICARE**: Mută secțiunea de totaluri din #bon-curent-panel în .grup-dreapta (footer)
            const totalsElement = $(this).find('.receipt-totals');
            if (totalsElement.length) {
                $('.grup-dreapta').html(totalsElement); // Folosim .html() pentru a înlocui complet
            }
            
            // Restul logicii originale, care depinde de elementele încărcate
            let total = parseFloat($('#total_de_incasat_display').text().replace(',', '.')) || 0;
            $('#totalmixt').val(total.toFixed(2));
            $('#numerar').val(total.toFixed(2));   // fără .attr('max', ...)
            $('#card').val('0.00');                // fără .attr('max', ...)
            updateCard();
        });
        
        // Logica de focus rămâne neschimbată
        if (['18', '21', '22','16'].includes(String(clientId))) {
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
        const targetUrl = isPermanentUpdate ? updateProductPermanentEndpoint : updateProductNameEndpoint;
        
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
    $(document)
        .on('click', '.discount', function() {
            $("#Discount").find("[name='idvanzare']").val($(this).attr("name")).end().modal("show");
        })
        .on('click', '.sterge_prod', function() {
            const itemElement = $(this).closest('.receipt-item');
            const idVanz = $(this).val();

            $.getJSON("sterge_prod.php", { id_vanz: idVanz, nr_bon: nrBon })
            .done((response) => {
                if (!response || response.success !== true) {
                    alert('Pozitia nu a putut fi stearsa.');
                    return;
                }

                reloadBonPanel();
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

            $.get(addProductEndpoint, productData, 'json')
            .done(response => {
                processAddProductResponse(response);
                const openedAutoPopup = maybeOpenHotelierEditFromResponse(response);
                if (!openedAutoPopup) {
                    if (['18', '21', '22','16'].includes(String(clientId))) {
                        barcodeFilterInput.focus().select();
                    } else {
                        nameFilterInput.focus();
                    }
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

            // === ADAUGAT: Copiem suma din inputul vizibil in cel ascuns ===
            // Daca inputul S: e gol, punem totalul de incasat default
            var sumaVizibila = $('#suma-incasata-input').val();
            if(!sumaVizibila || sumaVizibila == '') {
                sumaVizibila = $('#total_de_incasat_display').text();
            }
            $('#baniprimiti_hidden').val(sumaVizibila);
            // ==============================================================

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
            return fetch(`${offlineApiWebRoot}/declanseaza_cantar.php`, {
                method: "POST",
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ trigger: true })
            });
        };
        
        // Funcție pentru a șterge fișierul cu cantitatea după citire
        const deleteWeightFile = () => {
            const deleteUrl = `${offlineApiWebRoot}/delete_cantitate_cantarita.php?client_id=${clientId}&cod_locatie=${codMasa}`;
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

                        const checkUrl = `${offlineApiWebRoot}/${clientId}/${codMasa}/cantitate_cantarita.json?ts=${new Date().getTime()}`;
                        
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

      // Citește cheia ca STRING (evită conversia la număr a lui jQuery)
      const keyAttr = $(this).attr('data-key');
      const key = (keyAttr !== undefined) ? String(keyAttr) : undefined;

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
      } else if (key !== undefined) {
        const characterToAdd = isShiftActive ? key.toUpperCase() : key.toLowerCase();
        display.val(currentValue + characterToAdd);

        // Dezactivează Shift după o literă apăsată
        if (isShiftActive) toggleShift();
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
        
        // Declanșează evenimentul 'keyup' pentru a porni căutarea automată
        $('#prod_filter').trigger('keyup');
    });
    // ======== END BLOC: LOGICA PENTRU TASTATURA VIRTUALA TEXT (CORECTAT) ========

    // ======== INIȚIALIZARE PAGINĂ ========
    $(window).on('mousemove mousedown keydown scroll', resetInactivityTimer);
    resetInactivityTimer();
    initialLoadBonPanel();
    loadProducts('all', 1); // Încărcare modernizată
    
    // Focus inițial
    if (['18', '21', '22','16'].includes(String(clientId))) {
        barcodeFilterInput.focus().select();
    } else {
        nameFilterInput.focus();
    }

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

// === Hover popup mare pentru #total_de_incasat_display (nonintruziv) ===
(function initTotalHoverPopup(){
  const POPUP_ID = 'totalHoverPopup';

  function placePopup($target, $popup){
    const off   = $target.offset();
    const tW    = $target.outerWidth();
    const pW    = $popup.outerWidth();
    const pH    = $popup.outerHeight();
    const left  = off.left + (tW / 2);
    const top   = off.top - pH - 12; // 12px deasupra span-ului
    $popup.css({ top: top, left: left, transform: 'translateX(-50%)' });
  }

  // Delegat -> funcționează și dacă secțiunea se reîncarcă dinamic
  $(document)
    .on('mouseenter', '#total_de_incasat_display', function(){
      const val = $(this).text().trim();         // luăm valoarea afișată (ex: "123.45")
      const $popup = $('<div/>', { id: POPUP_ID, class: 'total-hover-popup', text: val });
      $('body').append($popup);
      placePopup($(this), $popup);

      // Menține poziția corectă dacă utilizatorul dă scroll / resize
      $(window).on('scroll.totalPopup resize.totalPopup', () => placePopup($(this), $popup));
    })
    .on('mouseleave', '#total_de_incasat_display', function(){
      $('#'+POPUP_ID).remove();
      $(window).off('.totalPopup');
    });
})();

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
<script src="offline_sync_heartbeat.js"></script>

</body>
</html>
