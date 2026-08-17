<?php
// sume_sertar_partial.php
// Returnează HTML-ul pentru corpul modalului "Sume încasate", cu date proaspete

include('session.php');
include('vanzare_init.php');

// Dezactivează cache pe răspunsul AJAX
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$adm_id = $_SESSION['adm_id'] ?? $_SESSION['admin_id'] ?? null; // adaptează dacă ai altă cheie
$cod_locatie = $_SESSION['cod_locatie'] ?? null;

if (!$adm_id || !$cod_locatie) {
  http_response_code(400);
  echo '<div class="alert alert-danger m-0">Sesiune invalidă: lipsesc operatorul sau locația.</div>';
  exit;
}

// TOTALURI pe note deschise ale operatorului la locația curentă
$stmt = $pdo->prepare("
  SELECT COALESCE(SUM(numerar),0) - COALESCE(SUM(rest),0) AS total_numerar,
         COALESCE(SUM(card),0) AS total_card,
         COALESCE(SUM(tichete),0) AS total_tichete,
         COALESCE(SUM(protocol),0) AS total_protocol,
         COALESCE(SUM(glovo),0) AS total_glovo,
         COALESCE(SUM(virament_bancar),0) AS total_virament_bancar
  FROM {$tabel_final_note}
  WHERE cod_inchidere = 0
    AND operator = :op
    AND locatie = :loc
");
$stmt->execute([':op' => $adm_id, ':loc' => $cod_locatie]);
$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
  'total_numerar' => 0,
  'total_card' => 0,
  'total_tichete' => 0,
  'total_protocol' => 0,
  'total_glovo' => 0,
  'total_virament_bancar' => 0
];

// Bacșiș din notele FINALIZATE (F), neînchise, pentru operatorul și locația curente
$stTipsF = $pdo->prepare("
  SELECT COALESCE(SUM(d.valoare_vanzare_cu_tva),0)
  FROM {$tabel_final_det_note} d
  INNER JOIN {$tabel_final_note} n ON n.nrbon = d.nr_bon
  WHERE d.cod_p = -1
    AND n.status = 'F'
    AND n.cod_inchidere = 0
    AND n.operator = :op
    AND n.locatie = :loc
");
$stTipsF->execute([':op' => $adm_id, ':loc' => $cod_locatie]);
$bacsis_acumulat = (float)$stTipsF->fetchColumn();

// Total de încasat din notele cu status S pentru ACEL operator ȘI ACEA LOCAȚIE
$stTotalS = $pdo->prepare("
  SELECT COALESCE(SUM(d.valoare_vanzare_cu_tva),0)
  FROM {$tabel_final_det_note} d
  INNER JOIN {$tabel_final_note} n ON n.nrbon = d.nr_bon
  WHERE n.status = 'S'
    AND n.operator = :op
    AND n.locatie = :loc
");
$stTotalS->execute([':op' => $adm_id, ':loc' => $cod_locatie]);
$total_incasat_s = (float)$stTotalS->fetchColumn();

function nf($v) {
  return number_format((float)$v, 2, '.', '');
}

// total încasat (fără bacșiș) pe metode de plată
$total_incasat_main =
  (float)$row['total_numerar'] +
  (float)$row['total_card'] +
  (float)$row['total_tichete'] +
  (float)$row['total_protocol'] +
  (float)$row['total_glovo'] +
  (float)$row['total_virament_bancar'];

$now = new DateTime('now', new DateTimeZone('Europe/Bucharest'));
?>
<div class="container-fluid px-0">
  <div class="row">
    <!-- STÂNGA: De încasat -->
    <div class="col-12 col-md-6 mb-3">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-1">De încasat</h5>
            <span class="badge badge-warning">Mese deschise</span>
          </div>

          <div class="display-4 my-2">
            <?= nf($total_incasat_s) ?> <small class="h5">LEI</small>
          </div>

          <small class="text-muted">
            Sumă totală a notelor cu status <strong>S</strong> (operator curent, locația curentă).
          </small>
        </div>
      </div>
    </div>

    <!-- DREAPTA: Încasat până acum -->
    <div class="col-12 col-md-6 mb-3">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h5 class="mb-1">Încasat până acum</h5>
          <div class="display-4 my-2">
            <?= nf($total_incasat_main) ?> <small class="h5">LEI</small>
          </div>

          <div class="table-responsive">
            <table class="table table-sm mb-2">
              <tbody>
                <tr>
                  <td>Numerar</td>
                  <td class="text-right"><?= nf($row['total_numerar']) ?> LEI</td>
                </tr>
                <tr>
                  <td>Card</td>
                  <td class="text-right"><?= nf($row['total_card']) ?> LEI</td>
                </tr>
                <tr>
                  <td>Virament bancar</td>
                  <td class="text-right"><?= nf($row['total_virament_bancar']) ?> LEI</td>
                </tr>
                <tr>
                  <td>Tichete de masă</td>
                  <td class="text-right"><?= nf($row['total_tichete']) ?> LEI</td>
                </tr>
                <tr>
                  <td>Protocol</td>
                  <td class="text-right"><?= nf($row['total_protocol']) ?> LEI</td>
                </tr>
                <tr>
                  <td>Glovo</td>
                  <td class="text-right"><?= nf($row['total_glovo']) ?> LEI</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="d-flex justify-content-between pt-2 border-top">
            <span>Bacșiș încasat</span>
            <strong><?= nf($bacsis_acumulat) ?> LEI</strong>
          </div>

          <small class="text-muted d-block mt-2">
            Actualizat: <?= $now->format('d.m.Y H:i:s') ?> (Europe/Bucharest)
          </small>
        </div>
      </div>
    </div>
  </div>
</div>