<?php //vanzare_modal_setare_masa.php
/* =========================
 *  MODAL: SETARE MASĂ — identic logicii tale (rămâne inline aici)
 * ========================= */
$meses_sql = "SELECT cod_masa, nume_masa, tip_masa, stare
              FROM $tabel_final_mese
              WHERE cod_locatie = :cod_locatie
              ORDER BY stare ASC, nume_masa ASC";
$stmt = $pdo->prepare($meses_sql);
$stmt->execute([':cod_locatie' => $cod_locatie]);
$meses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$categories_sql = "SELECT DISTINCT categorie_masa FROM $tabel_final_mese WHERE cod_locatie = :cod_locatie";
$stmtCat = $pdo->prepare($categories_sql);
$stmtCat->execute([':cod_locatie' => $cod_locatie]);
$categories = $stmtCat->fetchAll(PDO::FETCH_ASSOC);
?>
<?php
$hide_tura_actions_in_modal = in_array((int)($_SESSION['client_id'] ?? 0), [25, 26], true);
?>
<div class="modal fade modal-lock" id="setare_masa" tabindex="-1" role="dialog" aria-labelledby="setareMasaLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header" style="padding:10px 20px;background:#f7f7f7;border-bottom:1px solid #ccc;">
        <h4 class="modal-title" id="setareMasaLabel" style="margin:0;font-size:1.3em;">Alegeți masa pentru comanda nouă</h4>
      </div>
      <div class="modal-body" style="overflow:hidden;padding:20px;">
<div style="display:flex; flex-wrap:nowrap; height:100%; width:100%;">
          <!-- ===== STÂNGA (TAB-URI) ===== -->
<div style="flex: 0 0 25%; max-width:25%; min-width:250px; padding:15px; border-right:1px solid #ccc; box-sizing:border-box; display:flex; flex-direction:column; background:#f9f9f9;">            <!-- Deconectare sus (nemodificat) -->
            <div class="mb-2">
              <form method="POST" action="logout.php" style="margin:0;">
                <button name="deconectare" id="deconectare0" class="btn btn-outline-danger btn-block" type="submit">Deconectare</button>
              </form>
            </div>

            <!-- Tabs pentru conținutul din stânga -->
            <ul class="nav nav-tabs" id="leftToolsTabs" role="tablist" style="margin-bottom:8px;">
              <li class="nav-item">
                <a class="nav-link active" id="tab-rapoarte" data-toggle="tab" href="#pane-rapoarte" role="tab" aria-controls="pane-rapoarte" aria-selected="true">
                  Rapoarte
                </a>
              </li>
            <?php if (!$hide_tura_actions_in_modal): ?>
<li class="nav-item">
  <a class="nav-link" id="tab-inchidere" data-toggle="tab" href="#pane-inchidere" role="tab" aria-controls="pane-inchidere" aria-selected="false">
    Închidere Tură
  </a>
</li>
<?php endif; ?>
            </ul>

            <div class="tab-content border-left border-right border-bottom" id="leftToolsContent"
                 style="padding:10px; max-height: calc(100vh - 240px); overflow:auto;">
              <!-- TAB: Rapoarte -->
              <div class="tab-pane fade show active" id="pane-rapoarte" role="tabpanel" aria-labelledby="tab-rapoarte">
                <!-- Raport X Informativ - Sume sertar (nemodificat) -->
                <form method="POST" style="margin:0;margin-bottom:10px;" action="vanzare_restaurant_listare_sume_sertar.php">
                  <button name="vanzare_restaurant_listare_sume_sertar" id="vanzare_restaurant_listare_sume_sertar" class="btn btn-danger btn-block" type="submit" style="margin:5% 0;">Raport X Informativ - Sume sertar</button>
                </form>

                <?php
                $stmt = $pdo->prepare("
                  SELECT rz.nr_raport_z, MAX(CONCAT(n.data_bon,' ',n.ora_bon)) AS ultima_data_ora
                  FROM rapoarte_z rz
                  JOIN note n ON n.nr_raport_z = rz.nr_raport_z
                  WHERE rz.cod_locatie = ?
                  GROUP BY rz.nr_raport_z
                  ORDER BY ultima_data_ora DESC
                ");
                $stmt->execute([$cod_locatie]);
                $rapoarte = $stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <form method="get" action="vanzare_listare_inchidere_zi.php" class="mb-3">
                  <label for="nr_raport_z">Alege închidere zi de listat:</label>
                  <select class="form-control" id="nr_raport_z0" name="nr_raport_z" required>
                    <option value="" disabled selected>-- selectează închidere --</option>
                    <?php foreach ($rapoarte as $rz):
                      $dt = DateTime::createFromFormat('Y-m-d H:i:s', $rz['ultima_data_ora']);
                      $labelDate = $dt ? $dt->format('d.m.Y, H:i') : 'N/A';
                    ?>
                    <option value="<?php echo htmlspecialchars($rz['nr_raport_z']); ?>">
                      Inchidere <?php echo htmlspecialchars($rz['nr_raport_z']); ?> &ndash; <?php echo $labelDate; ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                  <button class="btn btn-primary btn-block mt-2" type="submit">Generează raport</button>
                </form>

                <?php
                  // valori implicite: azi 00:00 → acum, în Europe/Bucharest
                  $tz = new DateTimeZone('Europe/Bucharest');
                  $now = new DateTime('now', $tz);
                  $def_end_date = $now->format('Y-m-d');
                  $def_end_time = $now->format('H:i');
                  $start = (clone $now)->setTime(0,0);
                  $def_start_date = $start->format('Y-m-d');
                  $def_start_time = $start->format('H:i');
                ?>
                <hr>
                <form method="get" action="vanzare_listare_produse_interval.php" class="mt-3">
                  <label class="font-weight-bold d-block mb-2">Raport produse vândute (interval)</label>
                  <div class="form-row">
                    <div class="form-group col-6">
                      <label>Data start</label>
                      <input type="date" name="data_start" class="form-control" required value="<?php echo htmlspecialchars($def_start_date); ?>">
                    </div>
                    <div class="form-group col-6">
                      <label>Ora start</label>
                      <input type="time" name="ora_start" class="form-control" required value="<?php echo htmlspecialchars($def_start_time); ?>">
                    </div>
                  </div>
                  <div class="form-row">
                    <div class="form-group col-6">
                      <label>Data end</label>
                      <input type="date" name="data_end" class="form-control" required value="<?php echo htmlspecialchars($def_end_date); ?>">
                    </div>
                    <div class="form-group col-6">
                      <label>Ora end</label>
                      <input type="time" name="ora_end" class="form-control" required value="<?php echo htmlspecialchars($def_end_time); ?>">
                    </div>
                  </div>
                  <button class="btn btn-success btn-block" type="submit">Generează raport produse</button>
                  <small class="text-muted d-block mt-2">Se va tipări pe imprimantele de departament (BAR/BUCĂTĂRIE)</small>
                </form>
              </div>

              <!-- TAB: Închidere / Parolă -->
              <!-- TAB: Închidere / Parolă -->
<div class="tab-pane fade" id="pane-inchidere" role="tabpanel" aria-labelledby="tab-inchidere">

  <div id="inchidereTuraActionsWrap" style="<?php echo $hide_tura_actions_in_modal ? 'display:none;' : ''; ?>">
    <div id="turaContainer" style="margin-top: 10px;"></div>

    <div id="passwordConfirmSection" class="mt-3 p-3 border rounded">
      <p>Introduceți parola pentru a confirma închiderea turei sau a zilei:</p>
      <input type="password" id="confirmPassword" class="form-control" placeholder="Parola" readonly>
      <div id="keypad" class="mt-2 d-flex flex-wrap" style="gap:6px;max-width:220px;">
        <?php for($i=1;$i<=9;$i++): ?>
        <button type="button" class="btn btn-light keypad-btn" data-digit="<?php echo $i; ?>" style="width:30%;"><?php echo $i; ?></button>
        <?php endfor; ?>
        <button type="button" id="keypad-backspace" class="btn btn-secondary" style="width:30%;">&#9003;</button>
        <button type="button" class="btn btn-light keypad-btn" data-digit="0" style="width:30%;">0</button>
      </div>
      <button id="submitPassword" type="button" class="btn btn-primary btn-block mt-2">Verifică Parola</button>
    </div>
  </div>

                <!-- Operatorii care mai au tura de închis (pune acest bloc după #passwordConfirmSection) -->
<div class="mt-3 p-2 border rounded">
  <h6 class="mb-2">Operatori care trebuie să își închidă tura</h6>
  <?php
    // 1) Operatorii cu vânzări finalizate dar neînchise pe tura curentă (cod_inchidere=0)
    $opsSql = "
      SELECT 
          n.operator,
          COALESCE(a.admin_firstname,'') AS fn,
          COALESCE(a.admin_lastname,'')  AS ln,
          COUNT(*) AS bonuri_F,
          COALESCE(SUM(n.valoare_vanzare_cu_tva),0) AS total_vanz,
          MAX(CONCAT(n.data_bon,' ',n.ora_bon)) AS last_dt
      FROM $tabel_final_note n
      LEFT JOIN $tabel_final_admins a ON a.admin_id = n.operator
      WHERE n.locatie = :loc
        AND n.status  = 'F'
        AND n.cod_inchidere = 0
      GROUP BY n.operator, a.admin_firstname, a.admin_lastname
      ORDER BY last_dt DESC
    ";
    $opsStmt = $pdo->prepare($opsSql);
    $opsStmt->execute([':loc' => $cod_locatie]);
    $opsRows = $opsStmt->fetchAll(PDO::FETCH_ASSOC);

    if ($opsRows) {
      // 2) Pentru toți acești operatori, vedem dacă mai au mese deschise (status='S')
      $openSql = "
        SELECT operator, COUNT(*) AS open_cnt
          FROM $tabel_final_note
         WHERE locatie = :loc AND status = 'S'
         GROUP BY operator
      ";
      $openStmt = $pdo->prepare($openSql);
      $openStmt->execute([':loc' => $cod_locatie]);
      $openMap = [];
      foreach ($openStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $openMap[(int)$r['operator']] = (int)$r['open_cnt'];
      }

      echo '<ul class="list-group list-group-flush">';
      foreach ($opsRows as $row) {
        $opId    = (int)$row['operator'];
        $name    = trim(($row['fn'] ?? '').' '.($row['ln'] ?? ''));
        if ($name === '') $name = 'Op '.$opId;

        $isSelf  = ($opId === (int)$adm_id);
        $openCnt = $openMap[$opId] ?? 0;

        $badge = $openCnt > 0
          ? '<span class="badge badge-warning ml-2">mese deschise: '.$openCnt.'</span>'
          : '<span class="badge badge-success ml-2">pregătit pt. închidere</span>';

        $last   = $row['last_dt']
          ? DateTime::createFromFormat('Y-m-d H:i:s', $row['last_dt'])->format('d.m.Y H:i')
          : '';

        echo '<li class="list-group-item d-flex justify-content-between align-items-center" style="padding:.5rem .75rem;">';
        echo   '<div><strong>'.htmlspecialchars($name).'</strong> <small class="text-muted">#'.$opId.'</small>'
             . ($isSelf ? ' <span class="badge badge-info ml-1">tu</span>' : '')
             . $badge
             . '</div>';
        echo   '<div class="text-right">';
        echo     '<div><small>bonuri: '.(int)$row['bonuri_F'].' | total: '.number_format((float)$row['total_vanz'], 2).' LEI</small></div>';
        echo     '<div><small class="text-muted">ultimul: '.htmlspecialchars($last).'</small></div>';
        echo   '</div>';
        echo '</li>';
      }
      echo '</ul>';
    } else {
      echo '<div class="text-muted small">Niciun operator nu are vânzări de închis în acest moment.</div>';
    }
  ?>
</div>

              </div>
            </div>
          </div>

          <!-- ===== DREAPTA (nemodificată) ===== -->
<div style="flex: 1; min-width: 0; padding:15px; box-sizing:border-box; display:flex; flex-direction:column; height:100%; background:#fff;">            <h5 class="mb-2">
              Notele operatorului <?php echo htmlspecialchars($_SESSION['admin_firstname'].' '.$_SESSION['admin_lastname']); ?>
</h5>
            <form method="POST" action="vanzare_note_operator_select.php"
                  style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:15px;">
<?php
  $adm_id      = $_SESSION['admin_id'];
  $cod_locatie = $_SESSION['cod_locatie'];
  $sql = "SELECT n.nrbon, n.cod_masa, m.nume_masa, n.listat_nota_plata
          FROM $tabel_final_note n
          INNER JOIN mese m ON m.cod_masa = n.cod_masa
          WHERE n.status='S' AND n.operator=:op AND n.locatie=:loc
          ORDER BY n.nrbon ASC";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':op'=>$adm_id, ':loc'=>$cod_locatie]);

  while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $nr_bon = $r['nrbon'];
    $cod_masa = $r['cod_masa'];
    $nume_masa = $r['nume_masa'];

    $styleBtn = "background:#fff;color:#000;";
    $det_sql = "SELECT COUNT(*) AS total, SUM(CASE WHEN t_list=0 THEN 1 ELSE 0 END) AS not_listed
                FROM $tabel_final_det_note WHERE nr_bon=:nb";
    $detStmt = $pdo->prepare($det_sql);
    $detStmt->execute([':nb'=>$nr_bon]);
    $det = $detStmt->fetch(PDO::FETCH_ASSOC);

    if ($det && intval($det['not_listed']) > 0) {
      $styleBtn = "background:#f8d7da;color:#721c24;";
    } else {
      $styleBtn = ($r['listat_nota_plata']==1)
      ? "background:#d4edda;color:#155724;"
      : "background:#fff3cd;color:#856404;";
    }
    if (isset($_SESSION['masa_curenta']) && $_SESSION['masa_curenta'] == $cod_masa) {
      $styleBtn = "background:#0578F5;color:#fff;";
    }

    echo '<button type="submit" name="nota_selectata"
                  value="'.htmlspecialchars($nr_bon.'|'.$cod_masa).'"
                  class="btn"
                  style="'.$styleBtn.';padding:8px 12px;border:1px solid #999;border-radius:6px;">'
          .htmlspecialchars($nume_masa).' Nota: '.(int)$nr_bon.
         '</button>';
  }
?>
            </form>

            <div class="mb-2">
              <input type="text" id="searchInput" onkeyup="filterTables()" placeholder="Caută masa după nume"
                     class="form-control">
            </div>

            <div class="mb-2 d-flex flex-wrap" style="gap:6px;border-bottom:1px solid #ccc;">
<?php foreach($categories as $index=>$category): $catName = $category['categorie_masa']; ?>
              <button type="button" class="tablink btn <?php echo $index==0 ? 'btn-secondary' : 'btn-light'; ?>"
                      onclick="openTab(event,'<?php echo htmlspecialchars($catName); ?>')"
                      style="border-radius:4px;">
                <?php echo htmlspecialchars($catName); ?>
              </button>
<?php endforeach; ?>
            </div>

            <div id="masaGridScroll" class="masa-grid-scroll">
              <div class="masa-scroll-buttons">
                <button id="scroll-mese-up" class="scroll-btn-v" type="button" title="Scroll up">
                  <i class="fas fa-chevron-up"></i>
                </button>
                <button id="scroll-mese-down" class="scroll-btn-v" type="button" title="Scroll down">
                  <i class="fas fa-chevron-down"></i>
                </button>
              </div>
<?php foreach($categories as $index=>$category):
      $cat = $category['categorie_masa'];
      $mesas_sql = "SELECT cod_masa, nume_masa, tip_masa, stare, cod_bratara
                    FROM $tabel_final_mese
                    WHERE cod_locatie = :cod_locatie AND categorie_masa = :categorie
                    ORDER BY stare ASC, cod_masa ASC";
      $stmtCatMesas = $pdo->prepare($mesas_sql);
      $stmtCatMesas->execute([':cod_locatie'=>$cod_locatie, ':categorie'=>$cat]);
      $mesas_cat = $stmtCatMesas->fetchAll(PDO::FETCH_ASSOC);
?>
              <div id="<?php echo htmlspecialchars($cat); ?>" class="tabcontent" style="display:<?php echo $index==0?'block':'none'; ?>;">
                <div class="tablesGrid">
<?php foreach($mesas_cat as $mesa):
      $cod_masa = $mesa['cod_masa'];
      $nume_masa = $mesa['nume_masa'];
      if ($mesa['tip_masa'] === "bratara" && !empty($mesa['cod_bratara'])) {
        $nume_masa .= " <br> ".$mesa['cod_bratara'];
      }
      $stare = (int)$mesa['stare'];

      $buttonStyle = "background-color:#fff;color:#000;";
      $operator_info = "";
      $operator_actual_id = "";

      if ($stare === 1) {
        $note_sql = "SELECT operator, nrbon, listat_nota_plata
                     FROM $tabel_final_note
                     WHERE cod_masa = :cod_masa AND status='S'
                     ORDER BY nrbon DESC LIMIT 1";
        $stmtNote = $pdo->prepare($note_sql);
        $stmtNote->execute([':cod_masa'=>$cod_masa]);
        $note = $stmtNote->fetch(PDO::FETCH_ASSOC);
        if ($note) {
          $operator_id = $note['operator'];
          $nrbon_mesa  = $note['nrbon'];
          $listat_nota_plata_m = $note['listat_nota_plata'];

          if ($operator_id) {
            $operator_actual_id = $operator_id;
            $admin_sql = "SELECT admin_firstname, admin_lastname FROM $tabel_final_admins WHERE admin_id = :admin_id";
            $stmtAdmin = $pdo->prepare($admin_sql);
            $stmtAdmin->execute([':admin_id'=>$operator_id]);
            $admin = $stmtAdmin->fetch(PDO::FETCH_ASSOC);
            if ($admin) {
              $operator_info = $admin['admin_firstname'].' '.$admin['admin_lastname'];
            }
          }

          $det_note_sql = "SELECT COUNT(*) AS total, SUM(CASE WHEN t_list=0 THEN 1 ELSE 0 END) AS not_listed
                           FROM $tabel_final_det_note WHERE nr_bon = :nrbon";
          $stmtDet = $pdo->prepare($det_note_sql);
          $stmtDet->execute([':nrbon'=>$nrbon_mesa]);
          $det_result = $stmtDet->fetch(PDO::FETCH_ASSOC);

          if ($det_result && intval($det_result['not_listed']) > 0) {
            $buttonStyle = "background-color:#f8d7da;color:#721c24;";
          } else {
            $buttonStyle = ($listat_nota_plata_m == 1)
              ? "background-color:#d4edda;color:#155724;"
              : "background-color:#fff3cd;color:#856404;";
          }
        } else {
          $buttonStyle = "background-color:#f8d7da;color:#721c24;";
        }
      }
?>
                  <div class="masaCard">
                    <button type="button" class="table-btn"
                            data-masa="<?php echo (int)$cod_masa; ?>"
                            <?php if(!empty($operator_actual_id)) echo 'data-operator="'.(int)$operator_actual_id.'"'; ?>>
                      <div class="masa-title"><?php echo $nume_masa; ?></div>
                      <?php if ($stare === 1 && !empty($operator_info)): ?>
                        <div class="masa-sub">Ocupată de:</div>
                        <div class="masa-sub"><?php echo htmlspecialchars($operator_info)." Nota ".(int)$nrbon_mesa; ?></div>
                      <?php endif; ?>
                    </button>
                  </div>
<?php endforeach; ?>
                </div>
              </div>
<?php endforeach; ?>
            </div><!-- /tabContainer -->
          </div><!-- /dreapta -->
        </div>
      </div>
    </div>
  </div>
</div>

<!-- JS mic: memorează tab-ul activ din stânga (nu atinge alte logici) -->
<script>
  (function($){
    if (!window.localStorage) return;
    var key = 'leftToolsTabs.active';
    $(function(){
      var sel = localStorage.getItem(key);
      if (sel && $('#leftToolsTabs a[href="'+sel+'"]').length) {
        $('#leftToolsTabs a[href="'+sel+'"]').tab('show');
      }
    });
    $(document).on('shown.bs.tab', '#leftToolsTabs a[data-toggle="tab"]', function(e){
      localStorage.setItem(key, $(e.target).attr('href'));
    });
  })(jQuery);
</script>
