<?php
// regenereaza_raport_z.php
// UI + backend Ã®ntr-un singur fiÈ™ier
// NecesitÄƒ: session.php -> iniÈ›ializeazÄƒ $pdo (PDO) È™i $_SESSION

include('session.php');

// Util: escapare HTML
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Citire cod_locatie din sesiune
$cod_locatie = isset($_SESSION['cod_locatie']) ? (int)$_SESSION['cod_locatie'] : 0;
if ($cod_locatie <= 0) {
    http_response_code(400);
    die("Eroare: cod_locatie invalid Ã®n sesiune.");
}

// DacÄƒ e POST, procesÄƒm regenerarea
$result = null;
$error  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data_bon = isset($_POST['data_bon']) ? trim($_POST['data_bon']) : '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_bon)) {
        $error = "Format invalid pentru data_bon; aÈ™teptat YYYY-MM-DD.";
    } else {
        try {
            $pdo->beginTransaction();

            // 1) Seria casei pentru locaÈ›ie
            $stmt = $pdo->prepare("SELECT serie_casa_marcat FROM loc_mese_12 WHERE cod_locatie = :loc LIMIT 1");
            $stmt->execute(['loc' => $cod_locatie]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $serie_casa_marcat = $row ? $row['serie_casa_marcat'] : '';

            // 2) IdentificÄƒ Z-urile deja ataÈ™ate notelor din acea zi (le È™tergem din rapoarte_z)
            $stmt = $pdo->prepare("
                SELECT DISTINCT nr_raport_z
                FROM note
                WHERE status='F'
                  AND locatie=:loc
                  AND data_bon=:data_bon
                  AND nr_raport_z <> 0
            ");
            $stmt->execute(['loc' => $cod_locatie, 'data_bon' => $data_bon]);
            $nr_z_de_sters = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($nr_z_de_sters)) {
                $in = implode(',', array_fill(0, count($nr_z_de_sters), '?'));
                $sqlDel = "DELETE FROM rapoarte_z WHERE cod_locatie = ? AND nr_raport_z IN ($in)";
                $params = array_merge([$cod_locatie], $nr_z_de_sters);
                $stmt = $pdo->prepare($sqlDel);
                $stmt->execute($params);
                // DacÄƒ ai tabele derivate ale raportului Z, È™terge aici Ã®n cascadÄƒ.
            }

            // 3) Pune note.nr_raport_z = 0 pentru ziua respectivÄƒ (status F)
            $stmt = $pdo->prepare("
                UPDATE note
                SET nr_raport_z = 0
                WHERE status='F'
                  AND locatie=:loc
                  AND data_bon=:data_bon
            ");
            $stmt->execute(['loc' => $cod_locatie, 'data_bon' => $data_bon]);

            // 4) CalculeazÄƒ totalurile DIN NOTE pentru ziua È›intÄƒ (logica ta: doar F, cod_inchidere != 0)
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(SUM(numerar), 0) AS total_numerar,
                    COALESCE(SUM(card), 0) AS total_card,
                    COALESCE(SUM(tichete), 0) AS total_tichete,
                    COALESCE(SUM(glovo), 0) AS total_online,
                    COUNT(*) AS cnt_note
                FROM note
                WHERE status='F'
                  AND locatie=:loc
                  AND data_bon=:data_bon
                  AND cod_inchidere <> 0
                  AND nr_raport_z = 0
            ");
            $stmt->execute(['loc' => $cod_locatie, 'data_bon' => $data_bon]);
            $sum_data = $stmt->fetch(PDO::FETCH_ASSOC);

            $cnt_note        = (int)($sum_data['cnt_note'] ?? 0);
            $total_numerar   = (float)$sum_data['total_numerar'];
            $total_card      = (float)$sum_data['total_card'];
            $total_tichete   = (float)$sum_data['total_tichete'];
            $total_online    = (float)$sum_data['total_online'];

            if ($cnt_note === 0) {
                // Niciun bon eligibil â€” anulÄƒm
                $pdo->rollBack();
                $error = "Nu existÄƒ note eligibile (status F, cod_inchidere != 0) pentru data $data_bon.";
            } else {
                // 5) GenereazÄƒ urmÄƒtorul nr_raport_z disponibil pentru locaÈ›ie
                $stmt = $pdo->prepare("SELECT COALESCE(MAX(nr_raport_z), 0) + 1 AS next_z FROM rapoarte_z WHERE cod_locatie = :loc");
                $stmt->execute(['loc' => $cod_locatie]);
                $next = $stmt->fetch(PDO::FETCH_ASSOC);
                $nr_raport_z = (int)($next['next_z'] ?? 1);
                if ($nr_raport_z < 1) $nr_raport_z = 1;

                // 6) InsereazÄƒ raportul Z calculat
                $stmt = $pdo->prepare("
                    INSERT INTO rapoarte_z
                        (nr_raport_z, cod_locatie, serie_casa_marcat, numerar, card, credit, tichete_masa, tichete_valorice, plata_moderna, avans_in_numerar, alte_metode)
                    VALUES
                        (:nr_raport_z, :cod_locatie, :serie_casa_marcat, :numerar, :card, :credit, :tichete_masa, :tichete_valorice, :plata_moderna, :avans_in_numerar, :alte_metode)
                ");
                $stmt->execute([
                    'nr_raport_z'       => $nr_raport_z,
                    'cod_locatie'       => $cod_locatie,
                    'serie_casa_marcat' => $serie_casa_marcat,
                    'numerar'           => round($total_numerar, 2),
                    'card'              => round($total_card, 2),
                    'credit'            => 0.00,
                    'tichete_masa'      => round($total_tichete, 2),
                    'tichete_valorice'  => 0.00,
                    'plata_moderna'     => round($total_online, 2),
                    'avans_in_numerar'  => 0.00,
                    'alte_metode'       => 0.00,
                ]);

                // 7) AsociazÄƒ notele din acea zi la noul Z
                $stmt = $pdo->prepare("
                    UPDATE note
                    SET nr_raport_z = :nr_raport_z
                    WHERE status='F'
                      AND locatie=:loc
                      AND data_bon=:data_bon
                      AND cod_inchidere <> 0
                      AND nr_raport_z = 0
                ");
                $stmt->execute([
                    'nr_raport_z' => $nr_raport_z,
                    'loc'         => $cod_locatie,
                    'data_bon'    => $data_bon
                ]);

                // 8) ActualizeazÄƒ inchideri_r_12 pentru codurile de Ã®nchidere din acea zi
                $stmt = $pdo->prepare("
                    SELECT DISTINCT cod_inchidere
                    FROM note
                    WHERE status='F'
                      AND locatie=:loc
                      AND data_bon=:data_bon
                      AND cod_inchidere <> 0
                      AND nr_raport_z = :nr_raport_z
                ");
                $stmt->execute([
                    'loc'         => $cod_locatie,
                    'data_bon'    => $data_bon,
                    'nr_raport_z' => $nr_raport_z
                ]);
                $coduri = $stmt->fetchAll(PDO::FETCH_COLUMN);

                if (!empty($coduri)) {
                    $in = implode(',', array_fill(0, count($coduri), '?'));
                    $sqlUpd = "
                        UPDATE inchideri_r_12
                        SET nr_raport_z = ?
                        WHERE cod_inchidere IN ($in)
                          AND locatie = ?
                    ";
                    $params = array_merge([$nr_raport_z], $coduri, [$cod_locatie]);
                    $stmt = $pdo->prepare($sqlUpd);
                    $stmt->execute($params);
                }

                $pdo->commit();

                $result = [
                    'data_bon'     => $data_bon,
                    'nr_raport_z'  => $nr_raport_z,
                    'cnt_note'     => $cnt_note,
                    'numerar'      => number_format($total_numerar, 2, '.', ''),
                    'card'         => number_format($total_card, 2, '.', ''),
                    'tichete_masa' => number_format($total_tichete, 2, '.', ''),
                    'plata_moderna'=> number_format($total_online, 2, '.', ''),
                    'coduri_inchidere_actualizate' => $coduri ?? []
                ];
            }

        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $error = "Eroare: " . $ex->getMessage();
        }
    }
}

?>
<!doctype html>
<html lang="ro">
<head>
  <meta charset="utf-8">
  <title>Regenerare Raport Z dupÄƒ datÄƒ</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap (opÈ›ional, pentru UI mai drÄƒguÈ›) -->
  <link rel="stylesheet" href="vendor/offline/bootstrap4/bootstrap.min.css">
  <style>
    body { padding: 24px; }
    .card { border-radius: 14px; box-shadow: 0 6px 18px rgba(0,0,0,.08); }
    .form-control { border-radius: 10px; }
    .btn { border-radius: 10px; }
    code { background: #f5f5f5; padding: 2px 6px; border-radius: 6px; }
  </style>
</head>
<body>
  <div class="container" style="max-width: 860px">
    <h1 class="mb-4">Regenerare Raport Z</h1>

    <div class="card mb-4">
      <div class="card-body">
        <form method="POST">
          <div class="form-group">
            <label for="data_bon">Alege data calendaristicÄƒ (format <code>YYYY-MM-DD</code>)</label>
            <input type="date" class="form-control" id="data_bon" name="data_bon" required
                   value="<?php echo e(isset($_POST['data_bon']) ? $_POST['data_bon'] : date('Y-m-d')); ?>">
          </div>
          <button type="submit" class="btn btn-danger">ðŸ”¥ Regenerare Raport Z</button>
        </form>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger" role="alert">
        <?php echo e($error); ?>
      </div>
    <?php elseif ($result): ?>
      <div class="card">
        <div class="card-body">
          <h5 class="card-title">Succes! Raport Z regenerat</h5>
          <p class="mb-2"><strong>Data:</strong> <?php echo e($result['data_bon']); ?></p>
          <p class="mb-2"><strong>Nr. raport Z nou:</strong> <?php echo e($result['nr_raport_z']); ?></p>
          <p class="mb-2"><strong>Note afectate:</strong> <?php echo e($result['cnt_note']); ?></p>
          <hr>
          <div class="row">
            <div class="col-md-6">
              <ul class="list-unstyled mb-0">
                <li>Numerar: <strong><?php echo e($result['numerar']); ?></strong></li>
                <li>Card: <strong><?php echo e($result['card']); ?></strong></li>
              </ul>
            </div>
            <div class="col-md-6">
              <ul class="list-unstyled mb-0">
                <li>Tichete masÄƒ: <strong><?php echo e($result['tichete_masa']); ?></strong></li>
                <li>Online: <strong><?php echo e($result['plata_moderna']); ?></strong></li>
              </ul>
            </div>
          </div>
          <?php if (!empty($result['coduri_inchidere_actualizate'])): ?>
            <hr>
            <p class="mb-1"><strong>Coduri de Ã®nchidere actualizate:</strong></p>
            <code><?php echo e(implode(', ', $result['coduri_inchidere_actualizate'])); ?></code>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <p class="text-muted mt-4">
      LogicÄƒ aplicatÄƒ: <code>status='F'</code>, <code>locatie=<?php echo e($cod_locatie); ?></code>, <code>data_bon = data selectatÄƒ</code>, <code>cod_inchidere != 0</code>.
      ÃŽnainte de regenerare se È™terg eventualele Ã®nregistrÄƒri din <code>rapoarte_z</code> deja asociate acelor bonuri È™i se reseteazÄƒ <code>note.nr_raport_z</code> la 0.
    </p>
  </div>

  <!-- Bootstrap JS (opÈ›ional) -->
  <script src="vendor/jquery/jquery.min.js"></script>
  <script src="vendor/offline/bootstrap4/bootstrap.bundle.min.js"></script>
</body>
</html>

