<?php
// regenereaza_raport_z.php
// UI + backend într-un singur fișier
// Necesită: session.php -> inițializează $pdo (PDO) și $_SESSION

include('session.php');

// Util: escapare HTML
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Citire cod_locatie din sesiune
$cod_locatie = isset($_SESSION['cod_locatie']) ? (int)$_SESSION['cod_locatie'] : 0;
if ($cod_locatie <= 0) {
    http_response_code(400);
    die("Eroare: cod_locatie invalid în sesiune.");
}

// Dacă e POST, procesăm regenerarea
$result = null;
$error  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data_bon = isset($_POST['data_bon']) ? trim($_POST['data_bon']) : '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_bon)) {
        $error = "Format invalid pentru data_bon; așteptat YYYY-MM-DD.";
    } else {
        try {
            $pdo->beginTransaction();
            offline_raport_z_ensure_schema($pdo);
            $identity = offline_raport_z_current_identification($pdo, $cod_locatie);
            $serie_casa_marcat = $identity['serie_casa_marcat'];
            $nui = $identity['nui'];
            $serie_memorie_fiscala = $identity['serie_memorie_fiscala'];

            // 1) Seria casei pentru locație

            // 2) Identifică Z-urile deja atașate notelor din acea zi (le ștergem din rapoarte_z)
            $stmt = $pdo->prepare("
                SELECT DISTINCT nr_raport_z
                FROM note
                WHERE status='F'
                  AND locatie=:loc
                  AND data_bon=:data_bon
                  AND nr_raport_z <> 0
                  AND (COALESCE(nui, 0) = :nui_filter OR COALESCE(nui, 0) = 0)
                  AND (COALESCE(serie_memorie_fiscala, '') = :memory_filter OR COALESCE(serie_memorie_fiscala, '') = '')
            ");
            $stmt->execute([
                'loc' => $cod_locatie,
                'data_bon' => $data_bon,
                'nui_filter' => $nui,
                'memory_filter' => $serie_memorie_fiscala,
            ]);
            $nr_z_de_sters = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($nr_z_de_sters)) {
                $in = implode(',', array_fill(0, count($nr_z_de_sters), '?'));
                $sqlDel = "DELETE FROM rapoarte_z WHERE cod_locatie = ? AND COALESCE(serie_casa_marcat, '') = ? AND COALESCE(nui, 0) = ? AND COALESCE(serie_memorie_fiscala, '') = ? AND nr_raport_z IN ($in)";
                $params = array_merge([$cod_locatie, $serie_casa_marcat, $nui, $serie_memorie_fiscala], $nr_z_de_sters);
                $stmt = $pdo->prepare($sqlDel);
                $stmt->execute($params);
                // Dacă ai tabele derivate ale raportului Z, șterge aici în cascadă.
            }

            // 3) Pune note.nr_raport_z = 0 pentru ziua respectivă (status F)
            $stmt = $pdo->prepare("
                UPDATE note
                SET nr_raport_z = 0
                WHERE status='F'
                  AND locatie=:loc
                  AND data_bon=:data_bon
                  AND (COALESCE(nui, 0) = :nui_filter OR COALESCE(nui, 0) = 0)
                  AND (COALESCE(serie_memorie_fiscala, '') = :memory_filter OR COALESCE(serie_memorie_fiscala, '') = '')
            ");
            $stmt->execute([
                'loc' => $cod_locatie,
                'data_bon' => $data_bon,
                'nui_filter' => $nui,
                'memory_filter' => $serie_memorie_fiscala,
            ]);

            // 4) Calculează totalurile DIN NOTE pentru ziua țintă (logica ta: doar F, cod_inchidere != 0)
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
                   AND (COALESCE(nui, 0) = :nui_filter OR COALESCE(nui, 0) = 0)
                   AND (COALESCE(serie_memorie_fiscala, '') = :memory_filter OR COALESCE(serie_memorie_fiscala, '') = '')
            ");
            $stmt->execute([
                'loc' => $cod_locatie,
                'data_bon' => $data_bon,
                'nui_filter' => $nui,
                'memory_filter' => $serie_memorie_fiscala,
            ]);
            $sum_data = $stmt->fetch(PDO::FETCH_ASSOC);

            $cnt_note        = (int)($sum_data['cnt_note'] ?? 0);
            $total_numerar   = (float)$sum_data['total_numerar'];
            $total_card      = (float)$sum_data['total_card'];
            $total_tichete   = (float)$sum_data['total_tichete'];
            $total_online    = (float)$sum_data['total_online'];

            if ($cnt_note === 0) {
                // Niciun bon eligibil — anulăm
                $pdo->rollBack();
                $error = "Nu există note eligibile (status F, cod_inchidere != 0) pentru data $data_bon.";
            } else {
                // 5) Generează următorul nr_raport_z disponibil pentru locație
                $nr_raport_z = offline_raport_z_next_number($pdo, $cod_locatie, $nui, $serie_memorie_fiscala);

                // 6) Inserează raportul Z calculat
                $stmt = $pdo->prepare("
                    INSERT INTO rapoarte_z
                        (nr_raport_z, cod_locatie, serie_casa_marcat, nui, serie_memorie_fiscala, numerar, card, credit, tichete_masa, tichete_valorice, plata_moderna, avans_in_numerar, alte_metode)
                    VALUES
                        (:nr_raport_z, :cod_locatie, :serie_casa_marcat, :nui, :serie_memorie_fiscala, :numerar, :card, :credit, :tichete_masa, :tichete_valorice, :plata_moderna, :avans_in_numerar, :alte_metode)
                ");
                $stmt->execute([
                    'nr_raport_z'       => $nr_raport_z,
                    'cod_locatie'       => $cod_locatie,
                    'serie_casa_marcat' => $serie_casa_marcat,
                    'nui'               => $nui,
                    'serie_memorie_fiscala' => $serie_memorie_fiscala,
                    'numerar'           => round($total_numerar, 2),
                    'card'              => round($total_card, 2),
                    'credit'            => 0.00,
                    'tichete_masa'      => round($total_tichete, 2),
                    'tichete_valorice'  => 0.00,
                    'plata_moderna'     => round($total_online, 2),
                    'avans_in_numerar'  => 0.00,
                    'alte_metode'       => 0.00,
                ]);

                // 7) Asociază notele din acea zi la noul Z
                $stmt = $pdo->prepare("
                    UPDATE note
                    SET nr_raport_z = :nr_raport_z, nui = :nui, serie_memorie_fiscala = :serie_memorie_fiscala
                    WHERE status='F'
                      AND locatie=:loc
                      AND data_bon=:data_bon
                      AND cod_inchidere <> 0
                      AND nr_raport_z = 0
                      AND (COALESCE(nui, 0) = :nui_filter OR COALESCE(nui, 0) = 0)
                      AND (COALESCE(serie_memorie_fiscala, '') = :memory_filter OR COALESCE(serie_memorie_fiscala, '') = '')
                ");
                $stmt->execute([
                    'nr_raport_z' => $nr_raport_z,
                    'nui'         => $nui,
                    'serie_memorie_fiscala' => $serie_memorie_fiscala,
                    'loc'         => $cod_locatie,
                    'data_bon'    => $data_bon,
                    'nui_filter' => $nui,
                    'memory_filter' => $serie_memorie_fiscala,
                ]);

                // 8) Actualizează inchideri_r_12 pentru codurile de închidere din acea zi
                $stmt = $pdo->prepare("
                    SELECT DISTINCT cod_inchidere
                    FROM note
                    WHERE status='F'
                      AND locatie=:loc
                       AND data_bon=:data_bon
                       AND cod_inchidere <> 0
                       AND nr_raport_z = :nr_raport_z
                       AND COALESCE(nui, 0) = :nui
                       AND COALESCE(serie_memorie_fiscala, '') = :serie_memorie_fiscala
                ");
                $stmt->execute([
                    'loc'         => $cod_locatie,
                    'data_bon'    => $data_bon,
                    'nr_raport_z' => $nr_raport_z,
                    'nui' => $nui,
                    'serie_memorie_fiscala' => $serie_memorie_fiscala,
                ]);
                $coduri = $stmt->fetchAll(PDO::FETCH_COLUMN);

                if (!empty($coduri)) {
                    $in = implode(',', array_fill(0, count($coduri), '?'));
                    $sqlUpd = "
                        UPDATE inchideri_r_12
                        SET nr_raport_z = ?, nui = ?, serie_memorie_fiscala = ?
                        WHERE cod_inchidere IN ($in)
                          AND locatie = ?
                          AND (COALESCE(nui, 0) = ? OR COALESCE(nui, 0) = 0)
                          AND (COALESCE(serie_memorie_fiscala, '') = ? OR COALESCE(serie_memorie_fiscala, '') = '')
                    ";
                    $params = array_merge([$nr_raport_z, $nui, $serie_memorie_fiscala], $coduri, [$cod_locatie, $nui, $serie_memorie_fiscala]);
                    $stmt = $pdo->prepare($sqlUpd);
                    $stmt->execute($params);
                }

                foreach ([['BF', 'nr_doc', true], ['BC', 'nr_nota', false], ['BT', 'nr_nota', false]] as [$document, $linkColumn, $outOnly]) {
                    $extra = $outOnly ? " AND miscari.tip_miscare = 'O'" : '';
                    $sqlMiscari = "UPDATE miscari SET nr_raport_z = ?, cod_locatie = ?, nui = ?, serie_memorie_fiscala = ?
                        WHERE fel_doc = ?{$extra}
                          AND EXISTS (SELECT 1 FROM note n WHERE n.nrbon = miscari.{$linkColumn}
                            AND n.locatie = ? AND n.nr_raport_z = ? AND COALESCE(n.nui, 0) = ?
                            AND COALESCE(n.serie_memorie_fiscala, '') = ? AND n.data_bon = ? )";
                    $stmt = $pdo->prepare($sqlMiscari);
                    $stmt->execute([$nr_raport_z, $cod_locatie, $nui, $serie_memorie_fiscala, $document, $cod_locatie, $nr_raport_z, $nui, $serie_memorie_fiscala, $data_bon]);
                }

                if (function_exists('offline_sequence_record')) {
                    offline_sequence_record($pdo, 'nr_raport_z', $nr_raport_z, $cod_locatie, offline_raport_z_sequence_key($serie_casa_marcat, $nui, $serie_memorie_fiscala), 'z_regenerated', 'rapoarte_z', (string)$nr_raport_z);
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
  <title>Regenerare Raport Z după dată</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap (opțional, pentru UI mai drăguț) -->
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
            <label for="data_bon">Alege data calendaristică (format <code>YYYY-MM-DD</code>)</label>
            <input type="date" class="form-control" id="data_bon" name="data_bon" required
                   value="<?php echo e(isset($_POST['data_bon']) ? $_POST['data_bon'] : date('Y-m-d')); ?>">
          </div>
          <button type="submit" class="btn btn-danger">🔥 Regenerare Raport Z</button>
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
                <li>Tichete masă: <strong><?php echo e($result['tichete_masa']); ?></strong></li>
                <li>Plată modernă (online): <strong><?php echo e($result['plata_moderna']); ?></strong></li>
              </ul>
            </div>
          </div>
          <?php if (!empty($result['coduri_inchidere_actualizate'])): ?>
            <hr>
            <p class="mb-1"><strong>Coduri de închidere actualizate:</strong></p>
            <code><?php echo e(implode(', ', $result['coduri_inchidere_actualizate'])); ?></code>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <p class="text-muted mt-4">
      Logică aplicată: <code>status='F'</code>, <code>locatie=<?php echo e($cod_locatie); ?></code>, <code>data_bon = data selectată</code>, <code>cod_inchidere != 0</code>.
      Înainte de regenerare se șterg eventualele înregistrări din <code>rapoarte_z</code> deja asociate acelor bonuri și se resetează <code>note.nr_raport_z</code> la 0.
    </p>
  </div>

  <!-- Bootstrap JS (opțional) -->
  <script src="js/jquery-3.6.0.min.js"></script>
  <script src="vendor/offline/bootstrap4/bootstrap.bundle.min.js"></script>
</body>
</html>
