<?php
// Configurarea raportării erorilor
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.log');

// Include fișierul de conexiune la baza de date
include('database_connection.php'); 
require_once __DIR__ . '/det_note_departament_listare_schema.php';
agecs_ensure_det_note_departament_listare($pdo, $tabel_final_det_note);
$departamentListareSql = agecs_departament_listare_sql('dn', 'ps');

// --- FUNCȚII UTILITARE ---

// Funcție pentru gruparea produselor fără observații
function groupProducts($products) {
    $grouped = [];
    foreach ($products as $prod) {
        // Grupăm doar produsele care nu sunt terminate (preluat_osp = 0)
        if (empty($prod['observatie_produs']) && $prod['preluat_osp'] == 0) {
            $key = $prod['nume_produs'];
            if (isset($grouped[$key])) {
                $grouped[$key]['cantitate'] += floatval($prod['cantitate']);
                // Stocăm ID-urile produselor grupate
                $grouped[$key]['ids'][] = $prod['id_vanz'];
            } else {
                $prod['cantitate'] = floatval($prod['cantitate']);
                // Inițializăm un array de ID-uri
                $prod['ids'] = [$prod['id_vanz']];
                $grouped[$key] = $prod;
            }
        } else {
            // Produsele cu observații sau cele deja terminate (preluat_osp != 0) rămân separate
            $grouped[] = $prod;
        }
    }
    return array_values($grouped);
}

// Funcție pentru formatarea cantității
function formatQuantity($qty) {
    $cant = floatval($qty);
    return ($cant == floor($cant))
        ? number_format($cant, 0, '.', '')
        : rtrim(rtrim(number_format($cant, 2, '.', ''), '0'), '.');
}

// Funcție pentru calcularea sumei cantităților dintr-o listă de produse
function sumProductQuantities($products) {
    $sum = 0;
    foreach ($products as $prod) {
        $sum += floatval($prod['cantitate']);
    }
    return $sum;
}

// --- LOGICA DE PRELUARE A DATELOR ---

// Selectăm toate bonurile cu status 'S', adăugând și numele mesei
$sqlNote = "SELECT n.*, a.admin_firstname, a.admin_lastname, m.nume_masa
            FROM note AS n
            JOIN admins_12 AS a ON n.operator = a.admin_id
            LEFT JOIN mese AS m ON n.cod_masa = m.cod_masa
            WHERE n.status = 'S'
            ORDER BY n.data_deschidere ASC";
$stmtNote = $pdo->prepare($sqlNote);
$stmtNote->execute();
$allNotes = $stmtNote->fetchAll(PDO::FETCH_ASSOC);

$activeNotes = [];
$finishedNotesCount = 0;

// Procesăm fiecare bon pentru a-i adăuga produsele și a-l clasifica
foreach ($allNotes as $note) {
    $nrbon = $note['nrbon'];
    // Preluăm TOATE produsele care trebuie listate, indiferent de statusul preluat_osp
   // Preluăm doar produsele de la BUCATARIE care trebuie listate
$sqlDet = "SELECT dn.*
           FROM det_note AS dn
           LEFT JOIN produse_servicii AS ps ON dn.cod_p = ps.cod_produs
           WHERE dn.nr_bon = :nrbon 
             AND dn.t_list = 1 
             AND {$departamentListareSql} = 'BUCATARIE'
           ORDER BY dn.preluat_osp ASC, dn.data ASC, dn.ora ASC";
    $stmtDet = $pdo->prepare($sqlDet);
    $stmtDet->execute(['nrbon' => $nrbon]);
    $products = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

    // Dacă bonul nu are produse de listat, îl ignorăm
    if (empty($products)) {
        continue;
    }
    
    // Verificăm dacă toate produsele de pe bon sunt terminate (preluat_osp != 0)
    $pending_items = array_filter($products, function($p) {
        return $p['preluat_osp'] == 0;
    });

    if (empty($pending_items)) {
        // Dacă nu mai sunt produse în așteptare, bonul este considerat terminat
        $finishedNotesCount++;
    } else {
        $note['products'] = groupProducts($products);
        $activeNotes[] = $note;
    }
}

// Total bonuri active
$totalActiveNotes = count($activeNotes);

// Separăm bonurile active: primele 2 ca focus și restul ca alte bonuri
$focusedNotes = array_slice($activeNotes, 0, 2);
$otherNotes   = array_slice($activeNotes, 2);

// Pentru bonurile din zona dreaptă, calculăm numărul de coloane și rânduri
$numOther = count($otherNotes);
$cols = ($numOther < 4) ? ($numOther ?: 1) : 4;
$rows = ($cols > 0) ? ceil($numOther / $cols) : 1;
?>
<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8">
  <title>Interfață Bucătărie</title>
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
  <style>
    html, body { margin: 0; padding: 0; overflow: hidden; }
    .total-notes { height: 50px; background-color: rgb(44, 27, 51); padding: 10px; text-align: center; font-size: 1.7em; border-bottom: 1px solid #ccc; box-sizing: border-box; color: white; display: flex; justify-content: space-between; align-items: center; }
    .container-full { height: calc(100vh - 50px); }
    .left-column { flex: 0 0 40%; max-width: 40%; height: 100%; display: flex; flex-direction: row; }
    .right-column { flex: 0 0 60%; max-width: 60%; height: 100%; }
    .note-card { border: 1px solid #e8eddf; border-radius: 5px; padding: 5px; background-color: #e8eddf; overflow: hidden; box-sizing: border-box; height: 100%; display: flex; flex-direction: column; }
    .left-card, .right-card { padding: 5px; box-sizing: border-box; }
    .left-card { width: 50%; height: 100%; }
    .right-cards-container { display: flex; flex-wrap: wrap; height: 100%; }
    .note-header { background-color: #254441; padding: 5px; border-bottom: 1px solid #333; display: flex; justify-content: space-between; align-items: center; color: white; }
    .left-card .note-header { font-size: clamp(12px, 1.8vh, 16px); }
    .right-card .note-header { font-size: clamp(8px, 1.2vh, 12px); }
    .note-body { overflow-y: auto; flex-grow: 1; }
    .note-body ul { list-style: none; padding: 0; margin: 0; }
    .note-body li { border: 1px dashed #ccc; padding: 5px; margin: 5px; border-radius: 4px; background: #fff; cursor: pointer; transition: background-color 0.2s; }
    .note-body li:hover { background-color: #f0f0f0; }
    .produs-terminat { background-color: #d3ffd3 !important; text-decoration: line-through; color: #555; cursor: not-allowed; }
    .produs-terminat strong { font-weight: normal; }
    .left-card .note-body { font-size: clamp(14px, 2vh, 18px); }
    .right-card .note-body { font-size: clamp(10px, 1.5vh, 14px); }
    .no-notes { text-align: center; color: #666; padding: 20px; width: 100%; font-size: 1.5em; align-self: center; }
    #toast-container { position: fixed; top: 20px; right: 20px; z-index: 1055; }
    .toast { min-width: 250px; }
  </style>
</head>

<body>
  <div class="total-notes">
    <span>Bonuri Active: <strong><?php echo $totalActiveNotes; ?></strong></span>
    <div>
        <button id="showFinishedBtn" class="btn btn-info btn-sm">Vezi Bonuri Terminate (<?php echo $finishedNotesCount; ?>)</button>
        <a href='logout.php' class="btn btn-danger btn-sm" style="margin-left: 20px;">Deconectare</a>
    </div>
  </div>

  <div class="d-flex container-full">
    <div class="left-column">
      <?php if (empty($focusedNotes)) : ?>
        <div class="no-notes">Niciun bon în focus</div>
      <?php else : ?>
        <?php foreach ($focusedNotes as $note) :
            $nrbon = $note['nrbon'];
            $formattedDateTime = date("d.m.Y H:i", strtotime($note['data_deschidere']));
            $products = $note['products'];
            $ospatar = htmlspecialchars($note['admin_firstname'] . ' ' . $note['admin_lastname']);
            $numeMasa = htmlspecialchars($note['nume_masa'] ?? 'N/A');
        ?>
        <div class="left-card">
          <div class="note-card">
            <div class="note-header">
              <strong>Bon #<?php echo $nrbon; ?></strong>
              <span><?php echo $formattedDateTime; ?> | Osp: <?php echo $ospatar; ?> | Masa: <?php echo $numeMasa; ?></span>
            </div>
            <div class="note-body">
              <ul>
                <?php foreach ($products as $prod) :
                    $isCompleted = ($prod['preluat_osp'] != 0);
                    $prodId = isset($prod['ids']) ? implode(',', $prod['ids']) : $prod['id_vanz'];
                ?>
                  <li class="<?php echo $isCompleted ? 'produs-terminat' : 'produs-neterminat'; ?>" 
                      data-id="<?php echo $prodId; ?>"
                      data-nume="<?php echo htmlspecialchars($prod['nume_produs']); ?>">
                    <strong>
                      <?php echo htmlspecialchars($prod['nume_produs']) . ' x' . formatQuantity($prod['cantitate']); ?>
                    </strong>
                    <?php if (!empty($prod['observatie_produs'])) : ?>
                      <br><small>Obs: <?php echo htmlspecialchars($prod['observatie_produs']); ?></small>
                    <?php endif; ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <div class="right-column">
      <?php if (empty($otherNotes)) : ?>
        <div class="no-notes">Niciun alt bon disponibil</div>
      <?php else : ?>
        <div class="right-cards-container">
          <?php foreach ($otherNotes as $note) :
              $nrbon = $note['nrbon'];
              $formattedDateTime = date("d.m.Y H:i", strtotime($note['data_deschidere']));
              $products = $note['products'];
              $ospatar = htmlspecialchars($note['admin_firstname'] . ' ' . $note['admin_lastname']);
              $numeMasa = htmlspecialchars($note['nume_masa'] ?? 'N/A');
          ?>
            <div class="right-card" style="width: calc(100% / <?php echo $cols; ?>); height: calc((100vh - 50px) / <?php echo $rows; ?>);">
              <div class="note-card">
                <div class="note-header">
                  <strong>Bon #<?php echo $nrbon; ?></strong>
                  <span><?php echo $formattedDateTime; ?> | <?php echo $ospatar; ?> | <?php echo $numeMasa; ?></span>
                </div>
                <div class="note-body">
                  <ul>
                    <?php foreach ($products as $prod) : 
                        $isCompleted = ($prod['preluat_osp'] != 0);
                        $prodId = isset($prod['ids']) ? implode(',', $prod['ids']) : $prod['id_vanz'];
                    ?>
                      <li class="<?php echo $isCompleted ? 'produs-terminat' : 'produs-neterminat'; ?>" 
                          data-id="<?php echo $prodId; ?>"
                          data-nume="<?php echo htmlspecialchars($prod['nume_produs']); ?>">
                        <strong>
                          <?php echo htmlspecialchars($prod['nume_produs']) . ' x' . formatQuantity($prod['cantitate']); ?>
                        </strong>
                        <?php if (!empty($prod['observatie_produs'])) : ?>
                          <br><small>Obs: <?php echo htmlspecialchars($prod['observatie_produs']); ?></small>
                        <?php endif; ?>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div id="toast-container"></div>

  <div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmare Acțiune</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Sunteți sigur că doriți să marcați produsul <strong id="product-name-confirm"></strong> ca fiind terminat?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Anulează</button>
                <button type="button" class="btn btn-primary" id="confirm-mark-btn">Confirmă</button>
            </div>
        </div>
    </div>
  </div>
  
  <div class="modal fade" id="finishedNotesModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bonuri Terminate</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" id="finishedNotesContainer">
                <p class="text-center">Se încarcă...</p>
            </div>
        </div>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- LOGICĂ PENTRU REFRESH INTELIGENT ---
        let refreshTimer;
        function startRefreshTimer() {
            clearTimeout(refreshTimer);
            refreshTimer = setTimeout(() => { window.location.reload(); }, 10000); // 10 secunde
        }
        function resetRefreshTimer() { startRefreshTimer(); }
        startRefreshTimer();
        document.addEventListener('mousemove', resetRefreshTimer);
        document.addEventListener('click', resetRefreshTimer);
        document.addEventListener('keypress', resetRefreshTimer);
        // --- SFÂRȘIT LOGICĂ REFRESH ---

        let currentProductId = null;

        function showToast(message, type = 'success') {
            const toastId = 'toast-' + Date.now();
            const toastHTML = `
                <div id="${toastId}" class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-delay="3000">
                    <div class="toast-header">
                        <strong class="mr-auto ${type === 'success' ? 'text-success' : 'text-danger'}">${type === 'success' ? 'Succes' : 'Eroare'}</strong>
                        <button type="button" class="ml-2 mb-1 close" data-dismiss="toast" aria-label="Close">&times;</button>
                    </div>
                    <div class="toast-body">${message}</div>
                </div>`;
            $('#toast-container').append(toastHTML);
            $('#' + toastId).toast('show').on('hidden.bs.toast', function () { $(this).remove(); });
        }

        document.querySelector('.container-full').addEventListener('click', function(e) {
            const targetLi = e.target.closest('li.produs-neterminat');
            if (targetLi) {
                currentProductId = targetLi.getAttribute('data-id');
                document.getElementById('product-name-confirm').textContent = targetLi.getAttribute('data-nume');
                $('#confirmModal').modal('show');
            }
        });

        document.getElementById('confirm-mark-btn').addEventListener('click', function() {
            if (!currentProductId) return;
            fetch('mark_item_completed.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'ids=' + encodeURIComponent(currentProductId)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Produs marcat cu succes!');
                    setTimeout(() => window.location.reload(), 500);
                } else {
                    showToast(data.message || 'A apărut o eroare.', 'error');
                }
            })
            .catch(error => {
                console.error('Eroare AJAX:', error);
                showToast('Eroare la procesarea cererii.', 'error');
            })
            .finally(() => {
                $('#confirmModal').modal('hide');
                currentProductId = null;
            });
        });
        
        document.getElementById('showFinishedBtn').addEventListener('click', function() {
            const modalBody = document.getElementById('finishedNotesContainer');
            modalBody.innerHTML = '<p class="text-center">Se încarcă...</p>';
            $('#finishedNotesModal').modal('show');
            fetch('get_finished_notes.php')
                .then(response => response.text())
                .then(html => { modalBody.innerHTML = html; })
                .catch(error => {
                    console.error('Eroare la încărcarea bonurilor terminate:', error);
                    modalBody.innerHTML = '<p class="text-center text-danger">Eroare la încărcarea datelor.</p>';
                });
        });
    });
  </script>
</body>
</html>
