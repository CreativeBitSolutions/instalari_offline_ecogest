<?php
// sefsala_operatori_tura_partial.php
if (!isset($pdo)) {
    include_once('session.php');
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    echo '<div class="alert alert-danger mb-0">Conexiunea la baza de date nu este disponibila.</div>';
    return;
}

$adm_id_local = (int)($_SESSION['admin_id'] ?? 0);
$cod_locatie_local = isset($admin_location) && (int)$admin_location > 0 ? (int)$admin_location : (int)($_SESSION['cod_locatie'] ?? 0);
$tabel_note_local = !empty($tabel_final_note) ? $tabel_final_note : 'note';
$tabel_admins_local = !empty($tabel_final_admins) ? $tabel_final_admins : 'admins_12';
$tabel_det_note_local = !empty($tabel_final_det_note) ? $tabel_final_det_note : 'det_note';

if ($adm_id_local <= 0 || $cod_locatie_local <= 0) {
    echo '<div class="alert alert-warning mb-0">Nu s-au putut determina operatorul curent sau locatia curenta.</div>';
    return;
}

try {
    $opsSql = "
      SELECT
          base.operator,
          COALESCE(a.admin_firstname,'') AS fn,
          COALESCE(a.admin_lastname,'') AS ln,
          base.bonuri_F,
          base.total_vanz,
          base.last_dt,
          COALESCE(opn.open_cnt, 0) AS open_cnt,
          COALESCE(sert.total_numerar, 0) AS total_numerar,
          COALESCE(sert.total_card, 0) AS total_card,
          COALESCE(sert.total_tichete, 0) AS total_tichete,
          COALESCE(sert.total_protocol, 0) AS total_protocol,
          COALESCE(sert.total_glovo, 0) AS total_glovo,
          COALESCE(sert.total_virament_bancar, 0) AS total_virament_bancar,
          COALESCE(tip.bacsis_acumulat, 0) AS bacsis_acumulat,
          COALESCE(sopen.total_incasat_s, 0) AS total_incasat_s
      FROM (
          SELECT
              n.operator,
              COUNT(*) AS bonuri_F,
              COALESCE(SUM(n.valoare_vanzare_cu_tva),0) AS total_vanz,
              MAX(CONCAT(n.data_bon,' ',n.ora_bon)) AS last_dt
          FROM {$tabel_note_local} n
          WHERE n.locatie = :loc_base
            AND n.status = 'F'
            AND COALESCE(n.cod_inchidere, 0) = 0
          GROUP BY n.operator
      ) base
      LEFT JOIN {$tabel_admins_local} a
             ON a.admin_id = base.operator
      LEFT JOIN (
          SELECT
              operator,
              COUNT(*) AS open_cnt
          FROM {$tabel_note_local}
          WHERE locatie = :loc_open
            AND status = 'S'
          GROUP BY operator
      ) opn
             ON opn.operator = base.operator
      LEFT JOIN (
          SELECT
              operator,
              COALESCE(SUM(numerar),0) - COALESCE(SUM(rest),0) AS total_numerar,
              COALESCE(SUM(card),0) AS total_card,
              COALESCE(SUM(tichete),0) AS total_tichete,
              COALESCE(SUM(protocol),0) AS total_protocol,
              COALESCE(SUM(glovo),0) AS total_glovo,
              COALESCE(SUM(virament_bancar),0) AS total_virament_bancar
          FROM {$tabel_note_local}
          WHERE locatie = :loc_sert
            AND COALESCE(cod_inchidere,0) = 0
          GROUP BY operator
      ) sert
             ON sert.operator = base.operator
      LEFT JOIN (
          SELECT
              n.operator,
              COALESCE(SUM(d.valoare_vanzare_cu_tva),0) AS bacsis_acumulat
          FROM {$tabel_det_note_local} d
          INNER JOIN {$tabel_note_local} n
                  ON n.nrbon = d.nr_bon
          WHERE d.cod_p = -1
            AND n.status = 'F'
            AND COALESCE(n.cod_inchidere,0) = 0
            AND n.locatie = :loc_tip
          GROUP BY n.operator
      ) tip
             ON tip.operator = base.operator
      LEFT JOIN (
          SELECT
              n.operator,
              COALESCE(SUM(d.valoare_vanzare_cu_tva),0) AS total_incasat_s
          FROM {$tabel_det_note_local} d
          INNER JOIN {$tabel_note_local} n
                  ON n.nrbon = d.nr_bon
          WHERE n.status = 'S'
            AND n.locatie = :loc_sopen
          GROUP BY n.operator
      ) sopen
             ON sopen.operator = base.operator
      ORDER BY base.last_dt DESC
    ";

    $opsStmt = $pdo->prepare($opsSql);
    $opsStmt->execute([
        ':loc_base' => $cod_locatie_local,
        ':loc_open' => $cod_locatie_local,
        ':loc_sert' => $cod_locatie_local,
        ':loc_tip' => $cod_locatie_local,
        ':loc_sopen' => $cod_locatie_local
    ]);
    $opsRows = $opsStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!function_exists('nf_tura')) {
        function nf_tura($v) {
            return number_format((float)$v, 2, ',', '.');
        }
    }
} catch (Throwable $e) {
    error_log('sefsala_operatori_tura_partial.php: ' . $e->getMessage());
    echo '<div class="alert alert-danger mb-0">Eroare la incarcarea operatorilor.</div>';
    return;
}
?>

<div class="card shadow-sm mb-3 border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <div>
            <strong><i class="fas fa-user-clock mr-2 text-primary"></i>Operatori care necesita inchidere tura</strong>
            <div class="small text-muted">Locatie: <?php echo (int)$cod_locatie_local; ?></div>
        </div>
        <span class="badge badge-info"><?php echo is_array($opsRows) ? count($opsRows) : 0; ?> operator(i)</span>
    </div>

    <div class="card-body p-2">
        <?php if (!empty($opsRows)): ?>
            <ul class="list-group list-group-flush mb-0">
                <?php foreach ($opsRows as $row):
                    $opId = (int)$row['operator'];
                    $name = trim(($row['fn'] ?? '') . ' ' . ($row['ln'] ?? '')) ?: 'Op ' . $opId;
                    $openCnt = (int)($row['open_cnt'] ?? 0);

                    $totalNumerar = (float)($row['total_numerar'] ?? 0);
                    $totalCard = (float)($row['total_card'] ?? 0);
                    $totalTichete = (float)($row['total_tichete'] ?? 0);
                    $totalProtocol = (float)($row['total_protocol'] ?? 0);
                    $totalGlovo = (float)($row['total_glovo'] ?? 0);
                    $totalViramentBancar = (float)($row['total_virament_bancar'] ?? 0);
                    $totalBacsis = (float)($row['bacsis_acumulat'] ?? 0);
                    $totalDeIncasat = (float)($row['total_incasat_s'] ?? 0);
                    $totalIncasatMain = $totalNumerar + $totalCard + $totalTichete + $totalProtocol + $totalGlovo + $totalViramentBancar;

                    $badge = $openCnt > 0
                        ? '<span class="badge badge-warning ml-2">mese deschise: ' . $openCnt . '</span>'
                        : '<span class="badge badge-success ml-2">pregatit</span>';

                    $disableReason = '';
                    if ($openCnt > 0) {
                        $disableReason = 'Are mese deschise';
                    }

                    $actionBtn = $disableReason !== ''
                        ? '<button class="btn btn-sm btn-outline-secondary mt-2 w-100" disabled>' . htmlspecialchars($disableReason, ENT_QUOTES, 'UTF-8') . '</button>'
                        : '<button class="btn btn-sm btn-primary mt-2 w-100 btn-inchide-tura-op" data-opid="' . $opId . '" data-opname="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '"><i class="fas fa-lock"></i> Inchide Tura</button>';
                ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center" style="padding:.6rem .75rem;">
                        <div>
                            <strong><?php echo htmlspecialchars($name); ?></strong> <small class="text-muted">#<?php echo $opId; ?></small>
                            <?php echo $badge; ?>
                            <div class="mt-1">
                                <small class="d-block">
                                    bonuri: <?php echo (int)$row['bonuri_F']; ?>
                                    | total bonuri F: <?php echo nf_tura($row['total_vanz']); ?> LEI
                                </small>

                                <small class="d-block text-muted">
                                    incasat: <?php echo nf_tura($totalIncasatMain); ?> LEI
                                    | de incasat: <?php echo nf_tura($totalDeIncasat); ?> LEI
                                </small>

                                <small class="d-block text-muted">
                                    numerar: <?php echo nf_tura($totalNumerar); ?> |
                                    card: <?php echo nf_tura($totalCard); ?> |
                                    tichete: <?php echo nf_tura($totalTichete); ?>
                                </small>

                                <small class="d-block text-muted">
                                    protocol: <?php echo nf_tura($totalProtocol); ?> |
                                    online: <?php echo nf_tura($totalGlovo); ?> |
                                    virament: <?php echo nf_tura($totalViramentBancar); ?>
                                </small>

                                <small class="d-block text-muted">
                                    bacsis: <?php echo nf_tura($totalBacsis); ?> LEI
                                </small>

                            </div>
                        </div>
                        <div class="text-right" style="min-width: 130px;">
                            <?php echo $actionBtn; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="text-muted small p-2">Niciun operator nu are vanzari de inchis in acest moment.</div>
        <?php endif; ?>
    </div>
</div>
