<!-- Modal „Închide brățara” begin -->
<div class="modal fade" id="inchide_bratara_modal" tabindex="-1" role="dialog" aria-labelledby="inchideBrataraLabel">
  <div class="modal-dialog" role="document">
    <div class="modal-content">

      <div class="modal-header">
        <h4 class="modal-title" id="inchideBrataraLabel">Închide Brățara</h4>
        <button type="button" class="close" data-dismiss="modal" aria-label="Închide">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <div class="modal-body">
<?php
  // Masa curentă
  $cod_masa = isset($_SESSION['masa_curenta']) ? (int)$_SESSION['masa_curenta'] : 0;
  $nr_bon_ses = isset($_SESSION['nr_bon']) ? (int)$_SESSION['nr_bon'] : 0;

  // 1) Afișăm toate produsele active pe brățară
  $stmt = $pdo->prepare("
    SELECT nume_produs, cantitate, valoare_vanzare_cu_tva, data, ora
    FROM incasari_bratari
    WHERE cod_masa = :cod_masa AND bratara_inchisa = 0
    ORDER BY data ASC, ora ASC
  ");
  $stmt->execute([':cod_masa' => $cod_masa]);
  $lines = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if ($lines) {
    echo '<table class="table table-striped"><thead><tr>
            <th>Produs</th><th>Cantitate</th><th>Val. cu TVA</th><th>Data</th><th>Ora</th>
          </tr></thead><tbody>';
    foreach ($lines as $row) {
      $d = !empty($row['data']) ? DateTime::createFromFormat('Y-m-d', $row['data']) : null;
      $o = !empty($row['ora'])  ? DateTime::createFromFormat('H:i:s', $row['ora'])   : null;
      echo '<tr>';
      echo '<td>'.htmlspecialchars($row['nume_produs']).'</td>';
      echo '<td>'.htmlspecialchars($row['cantitate']).'</td>';
      echo '<td>'.number_format((float)$row['valoare_vanzare_cu_tva'], 2, ',', '.').' lei</td>';
      echo '<td>'.($d ? $d->format('d.m.Y') : '').'</td>';
      echo '<td>'.($o ? $o->format('H:i')    : '').'</td>';
      echo '</tr>';
    }
    echo '</tbody></table>';
  } else {
    echo '<p class="text-muted mb-0">Nu există încasări active pe această brățară.</p>';
  }

  // 2) Calculăm taxa extra pentru produse de tip intrare/abonament
  $stmt2 = $pdo->prepare("
    SELECT cod_p, nume_produs, cota_tva, valoare_vanzare_cu_tva, data, ora
    FROM incasari_bratari
    WHERE cod_masa = :cod_masa
      AND bratara_inchisa = 0
      AND (nume_produs LIKE '%INTRAR%' OR nume_produs LIKE '%ABONAM%')
  ");
  $stmt2->execute([':cod_masa' => $cod_masa]);
  $intrari = $stmt2->fetchAll(PDO::FETCH_ASSOC);

  $total_extra    = 0.0;
  $max_extra_min  = 0;
  $now            = new DateTime('now', new DateTimeZone('Europe/Bucharest'));

  foreach ($intrari as $item) {
    $vanz_dt = DateTime::createFromFormat(
      'Y-m-d H:i:s',
      trim(($item['data'] ?? '').' '.($item['ora'] ?? '')),
      new DateTimeZone('Europe/Bucharest')
    );
    if (!$vanz_dt) { continue; }

    $diff    = $vanz_dt->diff($now);
    $minutes = $diff->days * 24 * 60 + $diff->h * 60 + $diff->i;
    if ($minutes > 60) {
      $extra_min = $minutes - 60;
      $max_extra_min = max($max_extra_min, $extra_min);
      $total_extra += ((float)$item['valoare_vanzare_cu_tva'] / 60.0) * $extra_min;
    }
  }

  if ($total_extra > 0) {
    echo '<div class="alert alert-warning mb-2">';
    echo 'A fost depășită durata inclusă (cea mai mare depășire: <strong>'.(int)$max_extra_min.' min</strong>).<br>';
    echo 'Taxă extra totală: <strong>'.number_format($total_extra, 2, ',', '.').' lei</strong>';
    echo '</div>';

    echo '<form method="post" class="mb-3">
            <button type="submit" name="adauga_taxa_extra" class="btn btn-danger">
              Adaugă Taxa extra depășire program intrare
            </button>
          </form>';
  }

  // Buton închidere brățară
  echo '<form method="post">
          <button type="submit" name="inchide_bratara" class="btn btn-success">
            Închide Brățara
          </button>
        </form>';
?>

<?php
// --- Handler: Adaugă taxa extra în det_note pentru nota curentă ---
if (isset($_POST['adauga_taxa_extra'])) {
  require_once __DIR__ . '/det_note_departament_listare_schema.php';
  agecs_ensure_det_note_departament_listare($pdo);
  $nr_bon    = $nr_bon_ses;
  $cod_masa  = $cod_masa;
  $data_bon  = date('Y-m-d');
  $ora_bon   = date('H:i:s');

  // recalculăm exact pe baza înregistrărilor active
  $sql = "
    SELECT cod_p, nume_produs, valoare_vanzare_cu_tva, cota_tva, data, ora
    FROM incasari_bratari
    WHERE cod_masa = :cod_masa
      AND bratara_inchisa = 0
      AND (nume_produs LIKE '%INTRAR%' OR nume_produs LIKE '%ABONAM%')
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':cod_masa' => $cod_masa]);
  $intrari = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $now = new DateTime('now', new DateTimeZone('Europe/Bucharest'));

  foreach ($intrari as $item) {
    $vanz_dt = DateTime::createFromFormat('Y-m-d H:i:s', $item['data'].' '.$item['ora'], new DateTimeZone('Europe/Bucharest'));
    if (!$vanz_dt) { continue; }

    $diff    = $vanz_dt->diff($now);
    $minutes = $diff->days * 24 * 60 + $diff->h * 60 + $diff->i;

    if ($minutes > 60) {
      $extra_min = $minutes - 60;

      // valoarea totală cu TVA pentru taxa extra aferentă acestui articol
      $valoare_ctva = round(((float)$item['valoare_vanzare_cu_tva'] / 60.0) * $extra_min, 2);
      $cota_tva     = (float)$item['cota_tva'];
      $tva_col      = round($valoare_ctva * $cota_tva / (100.0 + $cota_tva), 2);
      $valoare_fara = round($valoare_ctva - $tva_col, 2);

      $ins = $pdo->prepare("
        INSERT INTO det_note
          (nr_bon, cod_p, nume_produs, cantitate,
           cota_tva, tva_col,
           pret_vanzare, valoare_vanzare, valoare_vanzare_cu_tva,
           data, ora)
        VALUES
          (:nr_bon, :cod_p, :nume_produs, 1,
           :cota_tva, :tva_col,
           :pret_vanzare, :valoare_vanzare, :valoare_vanzare_cu_tva,
           :data, :ora)
      ");
      $ins->execute([
        ':nr_bon'                 => $nr_bon,
        ':cod_p'                  => (int)$item['cod_p'],
        ':nume_produs'            => ($item['nume_produs'] ?? 'INTRARE').' - Taxă extra depășire timp',
        ':cota_tva'               => $cota_tva,
        ':tva_col'                => $tva_col,
        // În modelul tău „pret_vanzare” este folosit ca total pe linie la cant. 1
        ':pret_vanzare'           => $valoare_ctva,
        ':valoare_vanzare'        => $valoare_fara,
        ':valoare_vanzare_cu_tva' => $valoare_ctva,
        ':data'                   => $data_bon,
        ':ora'                    => $ora_bon,
      ]);
    }
  }

  agecs_snapshot_det_note_departamente($pdo, (int)$nr_bon);

  echo "<script>location.href='vanzare_restaurant.php'</script>";
  exit;
}

// --- Handler: Închide brățara (resetează masa, marchează încasările, golește nota curentă) ---
if (isset($_POST['inchide_bratara'])) {
  $cod_masa = $cod_masa;
  $nr_bon   = $nr_bon_ses;

  // 1) Resetăm masa
  $stmt1 = $pdo->prepare("UPDATE mese SET vandut_intrare = 0, stare = 0 WHERE cod_masa = :cod_masa");
  $stmt1->execute([':cod_masa' => $cod_masa]);

  // 2) Marcăm încasările ca închise
  $stmt2 = $pdo->prepare("UPDATE incasari_bratari SET bratara_inchisa = 1 WHERE cod_masa = :cod_masa AND bratara_inchisa = 0");
  $stmt2->execute([':cod_masa' => $cod_masa]);

  // 3) Ștergem nota curentă și detaliile ei (dacă există)
  if ($nr_bon > 0) {
    $pdo->prepare("DELETE FROM $tabel_final_note WHERE nrbon = :nr_bon")->execute([':nr_bon' => $nr_bon]);
    $pdo->prepare("DELETE FROM det_note WHERE nr_bon = :nr_bon")->execute([':nr_bon' => $nr_bon]);
  }

  // 4) Curățăm sesiunea
  unset($_SESSION['nr_bon'], $_SESSION['masa_curenta']);
  $_SESSION['trimis_comanda'] = 0;

  echo "<script>location.href='vanzare_restaurant.php'</script>";
  exit;
}
?>
      </div>
    </div>
  </div>
</div>
<!-- Modal „Închide brățara” end -->
