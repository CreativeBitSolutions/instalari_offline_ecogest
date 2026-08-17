<?php
// note_fara_z_modal_body.php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/vanzare_init.php';

$locationId = $_SESSION['cod_locatie'] ?? null;
if (!$locationId) { http_response_code(403); ?>
  <div class="alert alert-danger m-3">Sesiune invalidă.</div>
<?php exit; }

$limit = isset($_GET['limit']) ? max(1,min(500,(int)$_GET['limit'])) : 300;

$sql = "SELECT nrbon, data_bon, ora_bon, valoare_vanzare_cu_tva AS total, fiscalizat
        FROM note
        WHERE locatie = :loc AND nr_raport_z = 0
        ORDER BY nrbon DESC
        LIMIT {$limit}";
$st = $pdo->prepare($sql);
$st->execute([':loc'=>$locationId]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<style>
#modalVerificaBonuri .table thead th { white-space: nowrap; }
#modalVerificaBonuri .status-pill { padding: .2rem .5rem; border-radius: 999px; font-weight: 600; font-size: 12px; }
#modalVerificaBonuri .status-0 { background: #ffe8e8; color:#a12020; border:1px solid #f2c2c2; }
#modalVerificaBonuri .status-1 { background: #e6f4ea; color:#0e6b2f; border:1px solid #bfe3cb; }
</style>

<?php if (!$rows): ?>
  <div class="p-3 text-center text-muted">Nu există bonuri fără Z în acest moment.</div>
<?php else: ?>
  <div class="table-responsive">
    <table class="table table-sm table-hover mb-0">
      <thead class="thead-light">
        <tr>
          <th>Nr. bon</th>
          <th>Data</th>
          <th>Ora</th>
          <th class="text-right">Total (cu TVA)</th>
          <th>Fiscalizat</th>
          <th style="width:120px;">Acțiuni</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r):
        $nr = (int)$r['nrbon'];
        $f  = (int)$r['fiscalizat'];
      ?>
        <tr id="row-bon-<?= $nr ?>">
          <td><strong><?= $nr ?></strong></td>
          <td><?= htmlspecialchars($r['data_bon']) ?></td>
          <td><?= htmlspecialchars($r['ora_bon']) ?></td>
          <td class="text-right"><?= number_format((float)$r['total'], 2, '.', '') ?></td>
          <td>
            <span class="status-pill status-<?= $f ?>" id="pill-<?= $nr ?>">fiscalizat = <?= $f ?></span>
          </td>
          <td>
            <button type="button" class="btn btn-sm btn-outline-primary btn-verifica-bon" data-nrbon="<?= $nr ?>">
              Verifică
            </button>
            <div class="small mt-1" id="msg-<?= $nr ?>"></div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>