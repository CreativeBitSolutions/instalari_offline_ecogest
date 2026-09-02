<?php // modal_discount_global.php
if ((int)($_SESSION['client_id'] ?? 0) === 18 && (
    isset($_POST['apl_disc_proc_global']) ||
    isset($_POST['apl_disc_fix_global']) ||
    isset($_POST['resetare_preturi_initiale'])
)) {
    http_response_code(403);
    exit('Discountul nu este permis pentru acest client.');
}
?>

<div class="modal fade" id="DiscountGlobal" tabindex="-1" role="dialog" aria-labelledby="DiscountGlobalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="DiscountGlobalLabel">Aplică Discount Global</h4>
        <button type="button" class="close" data-dismiss="modal" aria-label="Închide">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form method="POST" action="vanzare_magazin.php">
          <div class="form-group">
            <label for="val_procent_global_input">Discount procentual (%)</label>
            <div class="input-group">
              <input type="number" step="0.01" min="0" max="100" class="form-control" id="val_procent_global_input" name="val_procent_global" value="0">
              <div class="input-group-append">
                  <button class="btn btn-secondary" type="button" data-toggle="modal" data-target="#keyboardModalProcentual" title="Deschide tastatura virtuală">
                      <i class="fas fa-keyboard"></i>
                  </button>
              </div>
            </div>
          </div>
          <input style="margin-top:5px;" class="btn btn-primary btn-block" type="submit" name="apl_disc_proc_global" value="Aplică discount procentual">
          <hr>
          <div class="form-group">
            <label for="valoare_fixa_global_input">Discount fix (RON)</label>
            <div class="input-group">
              <input type="number" step="0.01" min="0" class="form-control" id="valoare_fixa_global_input" name="valoare_fixa_global" placeholder="ex: 50.00">
               <div class="input-group-append">
                  <button class="btn btn-secondary" type="button" data-toggle="modal" data-target="#keyboardModalFix" title="Deschide tastatura virtuală">
                      <i class="fas fa-keyboard"></i>
                  </button>
              </div>
            </div>
          </div>
          <input style="margin-top:5px;" class="btn btn-primary btn-block" type="submit" name="apl_disc_fix_global" value="Aplică discount fix">
          <hr>
          <div class="form-group">
            <label>Resetare discounturi</label>
            <input style="margin-top:5px;" class="btn btn-warning btn-block" type="submit" name="resetare_preturi_initiale" value="Resetare la prețuri inițiale">
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php
if (!function_exists('discountGlobalEsteProdusSgr')) {
    function discountGlobalEsteProdusSgr($numeProdus) {
        return stripos((string)$numeProdus, 'SGR') !== false;
    }
}

// Procesare Discount Global Fix
if (isset($_POST['apl_disc_fix_global'])) {
    $nr_bon = $_SESSION['nr_bon'];
    $adm_id = $_SESSION['admin_id'];
    $global_discount_total_input = floatval($_POST['valoare_fixa_global']);
    $id_operatiune_globala = uniqid('global_fix_', true);

    $sql_fetch = "SELECT id_vanz, pret_vanzare, cantitate, cota_tva, nume_produs FROM $tabel_final_det_note WHERE nr_bon = :nr_bon ORDER BY id_vanz ASC";
    $stmt_fetch = $pdo->prepare($sql_fetch);
    $stmt_fetch->execute([':nr_bon' => $nr_bon]);
    $all_products = $stmt_fetch->fetchAll(PDO::FETCH_ASSOC);

    if (!$all_products || $global_discount_total_input <= 0) {
        printf("<script>location.href='vanzare_magazin.php'</script>");
        exit;
    }

    $non_sgr_products = [];
    $total_capacity = 0;
    foreach ($all_products as $prod) {
        if (!discountGlobalEsteProdusSgr($prod['nume_produs'])) {
            $non_sgr_products[] = $prod;
            $price = floatval($prod['pret_vanzare']);
            $qty = floatval($prod['cantitate']);
            if ($price > 1 && $qty > 0) {
                $total_capacity += ($price - 1) * $qty;
            }
        }
    }

    if ($global_discount_total_input > round($total_capacity, 2)) {
        echo '<script>alert("Discountul global (' . number_format($global_discount_total_input, 2) . ' lei) depășește capacitatea totală de discount (' . number_format($total_capacity, 2) . ' lei)!");</script>';
        printf("<script>location.href='vanzare_magazin.php'</script>");
        exit();
    }

    $pdo->beginTransaction();
    try {
        $remaining_discount = $global_discount_total_input;
        foreach ($non_sgr_products as $prod) {
            if ($remaining_discount <= 0.001) break;

            $id_vanz = $prod['id_vanz'];
            $pret_unitar_initial = floatval($prod['pret_vanzare']);
            $qty = floatval($prod['cantitate']);
            $cota_tva = floatval($prod['cota_tva']);

            if ($qty <= 0) continue;
            
            $row_capacity = ($pret_unitar_initial > 1) ? round(($pret_unitar_initial - 1) * $qty, 2) : 0;
            if ($row_capacity <= 0) continue;

            $discount_for_row = min($remaining_discount, $row_capacity);
            $discount_per_unit = $discount_for_row / $qty;
            $pret_unitar_final = $pret_unitar_initial - $discount_per_unit;
            
            $remaining_discount -= $discount_for_row;

            $new_val_cu_tva = round($pret_unitar_final * $qty, 2);
            $new_tva_col = round($new_val_cu_tva * $cota_tva / (100 + $cota_tva), 2);
            $new_val_fara_tva = $new_val_cu_tva - $new_tva_col;

            // NOU: Inserare audit
            $stmt_insert = $pdo->prepare("INSERT INTO discounturi_acordate (id_vanz, id_operator, tip_discount, valoare_discount_ron, pret_unitar_initial, pret_unitar_final, id_operatiune_globala) VALUES (?, ?, 'global_valoric', ?, ?, ?, ?)");
            $stmt_insert->execute([$id_vanz, $adm_id, $discount_for_row, $pret_unitar_initial, $pret_unitar_final, $id_operatiune_globala]);

            // Actualizare det_note
            $stmt_update = $pdo->prepare("UPDATE $tabel_final_det_note SET pret_vanzare = ?, discount = ?, valoare_vanzare_cu_tva = ?, tva_col = ?, valoare_vanzare = ? WHERE id_vanz = ?");
            $stmt_update->execute([$pret_unitar_final, $discount_for_row, $new_val_cu_tva, $new_tva_col, $new_val_fara_tva, $id_vanz]);
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Eroare la aplicare discount global fix: " . $e->getMessage());
    }
    printf("<script>location.href='vanzare_magazin.php'</script>");
}

// Procesare Discount Global Procentual
if (isset($_POST['apl_disc_proc_global'])) {
    $nr_bon = $_SESSION['nr_bon'];
    $adm_id = $_SESSION['admin_id'];
    $procent = (float)$_POST['val_procent_global'];
    $id_operatiune_globala = uniqid('global_proc_', true);
    
    $sql_fetch = "SELECT id_vanz, pret_vanzare, cantitate, cota_tva, nume_produs FROM $tabel_final_det_note WHERE nr_bon = :nr_bon";
    $stmt_fetch = $pdo->prepare($sql_fetch);
    $stmt_fetch->execute([':nr_bon' => $nr_bon]);
    $all_products = $stmt_fetch->fetchAll(PDO::FETCH_ASSOC);

    $total_non_sgr = 0;
    $lista_produse_non_sgr = [];
    foreach ($all_products as $row) {
        $cantitate = (float)$row['cantitate'];
        $line_value = round((float)$row['pret_vanzare'] * $cantitate, 2);
        if (!discountGlobalEsteProdusSgr($row['nume_produs']) && $cantitate > 0 && $line_value > 0) {
            $row['valoare_initiala_linie_cu_tva'] = $line_value;
            $lista_produse_non_sgr[] = $row;
            $total_non_sgr += $line_value;
        }
    }

    if ($procent <= 0 || $procent > 100 || $total_non_sgr <= 0 || empty($lista_produse_non_sgr)) {
        if ($procent > 100) {
            echo '<script>alert("Discountul procentual nu poate depăși 100%.");</script>';
        }
        printf("<script>location.href='vanzare_magazin.php'</script>");
        exit;
    }

    if ($total_non_sgr > 0 && $procent > 0) {
        $pdo->beginTransaction();
        try {
            $discount_total = round(($total_non_sgr * $procent / 100), 2);
            $discount_ramas = $discount_total;
            
            for ($i = 0; $i < count($lista_produse_non_sgr); $i++) {
                $produs = $lista_produse_non_sgr[$i];
                $valoare_initiala_linie = (float)$produs['valoare_initiala_linie_cu_tva'];
                
                $discount_pe_linie = ($i < count($lista_produse_non_sgr) - 1)
                    ? round($discount_total * ($valoare_initiala_linie / $total_non_sgr), 2)
                    : $discount_ramas;
                $discount_pe_linie = min($discount_pe_linie, $valoare_initiala_linie);
                
                $discount_ramas -= $discount_pe_linie;

                $id_vanz = $produs['id_vanz'];
                $pret_unitar_initial = (float)$produs['pret_vanzare'];
                $cantitate = (float)$produs['cantitate'];
                if ($cantitate <= 0) {
                    continue;
                }
                $discount_unitar = ($cantitate > 0) ? ($discount_pe_linie / $cantitate) : 0;
                $pret_unitar_final = max(0, $pret_unitar_initial - $discount_unitar);

                $new_val_cu_tva = round($pret_unitar_final * $cantitate, 2);
                $cota_tva = (float)$produs['cota_tva'];
                $new_tva_col = round($new_val_cu_tva * $cota_tva / (100 + $cota_tva), 2);
                $new_val_fara_tva = $new_val_cu_tva - $new_tva_col;

                $stmt_insert = $pdo->prepare("INSERT INTO discounturi_acordate (id_vanz, id_operator, tip_discount, valoare_procent, valoare_discount_ron, pret_unitar_initial, pret_unitar_final, id_operatiune_globala) VALUES (?, ?, 'global_procentual', ?, ?, ?, ?, ?)");
                $stmt_insert->execute([$id_vanz, $adm_id, $procent, $discount_pe_linie, $pret_unitar_initial, $pret_unitar_final, $id_operatiune_globala]);

                $stmt_update = $pdo->prepare("UPDATE $tabel_final_det_note SET pret_vanzare = ?, discount = ?, valoare_vanzare_cu_tva = ?, tva_col = ?, valoare_vanzare = ? WHERE id_vanz = ?");
                $stmt_update->execute([$pret_unitar_final, $discount_pe_linie, $new_val_cu_tva, $new_tva_col, $new_val_fara_tva, $id_vanz]);
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Eroare la aplicare discount global procentual: " . $e->getMessage());
        }
    }
    printf("<script>location.href='vanzare_magazin.php'</script>");
}

// Procesare Resetare Prețuri
if (isset($_POST['resetare_preturi_initiale'])) {
    $nr_bon = $_SESSION['nr_bon'];
    $adm_id = $_SESSION['admin_id'];
    $id_operatiune_globala = uniqid('reset_', true);

    $sql = "SELECT d.id_vanz, d.cantitate, d.cota_tva, d.pret_vanzare as pret_curent, p.pret_cu_tva as pret_initial_catalog
            FROM $tabel_final_det_note d
            JOIN $tabel_final_nomenclator p ON d.cod_p = p.cod_produs
            WHERE d.nr_bon = :nr_bon";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':nr_bon' => $nr_bon]);
    $products_to_reset = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($products_to_reset) {
        $pdo->beginTransaction();
        try {
            foreach ($products_to_reset as $row) {
                $id_vanz = $row['id_vanz'];
                $pret_unitar_curent = (float)$row['pret_curent'];
                $pret_unitar_resetat = (float)$row['pret_initial_catalog'];
                
                if (abs($pret_unitar_curent - $pret_unitar_resetat) < 0.0001) continue;

                $cantitate = (float)$row['cantitate'];
                $valoare_modificata_ron = ($pret_unitar_resetat - $pret_unitar_curent) * $cantitate;

                $new_val_cu_tva = round($pret_unitar_resetat * $cantitate, 2);
                $cota_tva = (int)$row['cota_tva'];
                $new_tva_col = round($new_val_cu_tva * $cota_tva / (100 + $cota_tva), 2);
                $new_val_fara_tva = $new_val_cu_tva - $new_tva_col;

                $stmt_insert = $pdo->prepare("INSERT INTO discounturi_acordate (id_vanz, id_operator, tip_discount, valoare_discount_ron, pret_unitar_initial, pret_unitar_final, id_operatiune_globala) VALUES (?, ?, 'resetare', ?, ?, ?, ?)");
                $stmt_insert->execute([$id_vanz, $adm_id, $valoare_modificata_ron, $pret_unitar_curent, $pret_unitar_resetat, $id_operatiune_globala]);

                $stmt_update = $pdo->prepare("UPDATE $tabel_final_det_note SET pret_vanzare = ?, discount = 0, valoare_vanzare_cu_tva = ?, tva_col = ?, valoare_vanzare = ? WHERE id_vanz = ?");
                $stmt_update->execute([$pret_unitar_resetat, $new_val_cu_tva, $new_tva_col, $new_val_fara_tva, $id_vanz]);
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Eroare la resetarea prețurilor: " . $e->getMessage());
        }
    }
    printf("<script>location.href='vanzare_magazin.php'</script>");
}
?>
