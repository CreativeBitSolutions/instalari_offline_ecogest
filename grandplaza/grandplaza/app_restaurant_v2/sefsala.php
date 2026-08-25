<?php
include('session.php');

// Setări erori
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.log');

// Preluăm id-ul și locația adminului din sesiune
$cel_ce_modifica = $_SESSION['admin_id'];
$admin_location = 0; // Default

// Preluăm locația administratorului
$sqlAdmin = "SELECT locatie FROM admins_12 WHERE admin_id = ?";
$stmtAdmin = $pdo->prepare($sqlAdmin);
$stmtAdmin->execute([$cel_ce_modifica]);
$admin_row = $stmtAdmin->fetch(PDO::FETCH_ASSOC);
if ($admin_row) {
    $admin_location = $admin_row['locatie'];
}

$autoSalesSyncConfig = isset($restaurantConfig['offline_sales_sync']) && is_array($restaurantConfig['offline_sales_sync'])
    ? $restaurantConfig['offline_sales_sync']
    : [];
$autoSalesSyncEnabled = filter_var($autoSalesSyncConfig['automatic'] ?? false, FILTER_VALIDATE_BOOL);
$autoSalesSyncInterval = max(60, (int)($autoSalesSyncConfig['automatic_interval_seconds'] ?? 120));

// Obținem toate bonurile (notele) cu status 'S' pentru locația adminului
$sqlNote = "
    SELECT 
        n.nrbon, 
        n.data_deschidere,
        n.operator,
        a.admin_firstname, 
        a.admin_lastname,
        m.nume_masa
    FROM note n
    LEFT JOIN admins_12 a ON n.operator = a.admin_id
    LEFT JOIN mese m ON n.cod_masa = m.cod_masa
    WHERE n.status = 'S' 
    AND n.locatie = ?
    ORDER BY n.data_deschidere DESC
";
$stmtNote = $pdo->prepare($sqlNote);
$stmtNote->execute([$admin_location]);
$notes = $stmtNote->fetchAll(PDO::FETCH_ASSOC);

// Extragem operatorii unici pentru select-ul de filtrare
$operatori_unici = [];
foreach ($notes as $n) {
    $op_nume_complet = trim($n['admin_firstname'] . ' ' . $n['admin_lastname']);
    if (!empty($op_nume_complet)) {
        $operatori_unici[$n['operator']] = $op_nume_complet;
    }
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Management Note Fiscale</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { background-color: #f4f7f6; }
        .sidebar {
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            background-color: #343a40;
            color: #fff;
            padding-top: 20px;
            overflow-y: auto;
        }
        .main-content {
            margin-left: 290px;
            padding: 20px;
        }
        .note-list-item {
            cursor: pointer;
            padding: 15px;
            border-bottom: 1px solid #495057;
            transition: background-color 0.2s;
        }
        .note-list-item:hover, .note-list-item.active {
            background-color: #007bff;
        }
        .note-details-card {
            display: none; 
        }
        .card-header-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .loader {
            border: 5px solid #f3f3f3;
            border-top: 5px solid #3498db;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            display: none;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsive minimal */
        @media (max-width: 991.98px) {
            .sidebar {
                position: static;
                width: 100%;
                height: auto;
            }
            .main-content {
                margin-left: 0;
            }
        }
        
        /* Modal icon animation */
        .modal-icon-anim {
            animation: popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
            transform: scale(0);
        }
        @keyframes popIn {
            to { transform: scale(1); }
        }
    </style>
</head>
<body>

<div class="sidebar">
    <h4 class="text-center">Bonuri Deschise</h4>
    
    <div class="px-3 mb-3">
        <select id="filterOperator" class="form-control form-control-sm bg-dark text-white border-secondary">
            <option value="all">Toți operatorii</option>
            <?php foreach ($operatori_unici as $op_id => $op_nume): ?>
                <option value="<?php echo htmlspecialchars($op_id); ?>"><?php echo htmlspecialchars($op_nume); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <hr class="bg-light m-0">
    <div id="noteList" class="list-group">
        <?php foreach ($notes as $note): ?>
            <a href="#" class="list-group-item list-group-item-action bg-dark text-white note-list-item" 
               data-nrbon="<?php echo $note['nrbon']; ?>" 
               data-operator="<?php echo $note['operator']; ?>">
                <strong>Bon #<?php echo $note['nrbon']; ?></strong><br>
                <small>Masa: <?php echo htmlspecialchars($note['nume_masa']); ?></small><br>
                <small>De la: <?php echo htmlspecialchars(trim($note['admin_firstname'].' '.$note['admin_lastname'])); ?></small><br>
                <small><?php echo date("d.m.Y H:i", strtotime($note['data_deschidere'])); ?></small>
            </a>
        <?php endforeach; ?>
    </div>
    <div class="text-center mt-4 mb-4">
        <a href="logout.php" class="btn btn-outline-light btn-sm m-1">Deconectare</a>
        <a href="vanzare_retururi.php" class="btn btn-outline-light btn-sm m-1">Retururi</a>
        <a href="listare_note.php" class="btn btn-outline-light btn-sm m-1">Listare Note</a>
        <a href="configurare_listare_imprimanta.php" class="btn btn-outline-warning btn-sm m-1">Configurare imprimantă</a>
        <button type="button" id="btnSyncOnline" class="btn btn-success btn-sm m-1">
            <i class="fas fa-sync-alt"></i> Sync Online
        </button>
        <button type="button" class="btn btn-info btn-sm m-1" data-toggle="modal" data-target="#restaurantSyncStatusModal">
            <i class="fas fa-clipboard-list"></i> Status trimiteri
        </button>
        <small id="autoSyncOnlineStatus" class="d-block text-light mt-1">Trimitere automată activă</small>
    </div>
</div>

<div class="main-content">
    <?php include('sefsala_operatori_tura_partial.php'); ?>

    <div id="detailsContainer">
        <div class="jumbotron text-center bg-light">
            <h1 class="display-4">Bine ați venit!</h1>
            <p class="lead">Selectați un bon din lista din stânga pentru a vedea detaliile și a efectua modificări.</p>
        </div>
    </div>
    <div class="loader" id="loader"></div>
</div>

<div class="modal fade" id="modifyModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Modifică Cantitate Produs</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form id="modifyForm">
          <input type="hidden" id="modifyIdVanz">
          <input type="hidden" id="originalQuantity">
          <div class="form-group">
            <label for="productName">Produs</label>
            <input type="text" class="form-control" id="productName" readonly>
          </div>
          <div class="form-group">
            <label for="newQuantity">Cantitate Nouă</label>
            <input type="number" class="form-control" id="newQuantity" step="0.01" required>
          </div>
          <div class="form-group">
            <label for="modifyMotiv">Motivul Modificării</label>
            <input type="text" class="form-control" id="modifyMotiv" value="Corecție cantitate" required>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Anulează</button>
        <button type="button" class="btn btn-primary" id="confirmModify">Confirmă Modificarea</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmă Ștergerea Produsului</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="deleteForm">
                    <input type="hidden" id="deleteIdVanz">
                    <p>Ești sigur că vrei să ștergi acest produs?</p>
                    <div class="form-group">
                        <label for="deleteMotiv">Motivul Ștergerii</label>
                        <input type="text" class="form-control" id="deleteMotiv" value="Adăugat din greșeală">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Anulează</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">Confirmă Ștergerea</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteAllModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmă Anularea Întregului Bon</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-danger font-weight-bold">Atenție! Această acțiune va șterge TOATE produsele de pe bonul selectat. Acțiunea este ireversibilă.</p>
                <form id="deleteAllForm">
                    <input type="hidden" id="deleteAllNrBon">
                    <div class="form-group">
                        <label for="deleteAllMotiv">Motivul Anulării Bonului</label>
                        <input type="text" class="form-control" id="deleteAllMotiv" value="Bon anulat complet" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Anulează</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteAll">Confirmă Anularea Totală</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="genericConfirmModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning text-dark border-0">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Confirmare</h5>
                <button type="button" class="close text-dark" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center py-4">
                <h5 id="genericConfirmText" class="font-weight-normal">Sunteți sigur?</h5>
            </div>
            <div class="modal-footer bg-light border-0 justify-content-center">
                <button type="button" class="btn btn-light" data-dismiss="modal" style="width: 100px;">Anulează</button>
                <button type="button" class="btn btn-warning" id="btnGenericConfirmOk" style="width: 100px;">Da</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="genericInfoModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm" role="document">
        <div class="modal-content border-0 shadow-lg text-center">
            <div class="modal-body py-5">
                <div id="genericInfoIcon" class="mb-3 modal-icon-anim">
                    <i class="fas fa-check-circle fa-4x text-success"></i>
                </div>
                <h5 id="genericInfoTitle" class="mb-2">Succes!</h5>
                <p id="genericInfoText" class="text-muted mb-0">Acțiunea a fost finalizată.</p>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/modal_situatie_sincronizare.php'; ?>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script>
$(document).ready(function() {
    let currentBon = null;

    // --- LOGICĂ PENTRU FILTRARE OPERATORI ---
    $('#filterOperator').on('change', function() {
        const selectedOp = $(this).val();
        
        if (selectedOp === 'all') {
            $('.note-list-item').show();
        } else {
            $('.note-list-item').each(function() {
                if ($(this).data('operator') == selectedOp) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        }
    });

    // ----- FUNCȚII HELPER MODALE -----
    function showInfoModal(type, title, text) {
        let iconHtml = '';
        if (type === 'success') {
            iconHtml = '<i class="fas fa-check-circle fa-4x text-success"></i>';
        } else if (type === 'error') {
            iconHtml = '<i class="fas fa-times-circle fa-4x text-danger"></i>';
        } else if (type === 'warning') {
            iconHtml = '<i class="fas fa-exclamation-circle fa-4x text-warning"></i>';
        }
        
        $('#genericInfoIcon').html(iconHtml);
        $('#genericInfoTitle').text(title);
        $('#genericInfoText').html(text);
        
        // Reset animation class to re-trigger it
        $('#genericInfoIcon').removeClass('modal-icon-anim');
        void $('#genericInfoIcon')[0].offsetWidth; // trigger reflow
        $('#genericInfoIcon').addClass('modal-icon-anim');

        $('#genericInfoModal').modal('show');
    }

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    // Afișează detaliile bonului la click
    $('.note-list-item').click(function(e) {
        e.preventDefault();
        const nrbon = $(this).data('nrbon');
        currentBon = nrbon;
        
        $('.note-list-item').removeClass('active');
        $(this).addClass('active');

        $('#loader').show();
        $('#detailsContainer').hide();

        $.ajax({
            url: 'sef_sala_modifica_cantitate.php',
            type: 'POST',
            data: { action: 'get_details', nrbon: nrbon },
            success: function(response) {
                $('#detailsContainer').html(response).show();
                $('#loader').hide();
            },
            error: function() {
                showInfoModal('error', 'Eroare', 'Eroare la încărcarea detaliilor bonului.');
                $('#loader').hide();
            }
        });
    });
    
    // --- Logica pentru MODIFICARE CANTITATE ---
    $(document).on('click', '.modify-btn', function() {
        const id_vanz = $(this).data('id');
        const row = $('#row-' + id_vanz);
        
        $('#modifyIdVanz').val(id_vanz);
        $('#productName').val(row.find('td:nth-child(1)').text());
        const originalQty = parseFloat(row.find('td:nth-child(2)').text().replace(',', '.'));
        $('#originalQuantity').val(originalQty);
        $('#newQuantity').val(originalQty);
        $('#modifyModal').modal('show');
    });

    $('#confirmModify').click(function() {
        const id_vanz = $('#modifyIdVanz').val();
        const newQuantity = $('#newQuantity').val();
        const motiv = $('#modifyMotiv').val();

        if (parseFloat(newQuantity) < 0) {
            showInfoModal('warning', 'Atenție', 'Cantitatea nu poate fi negativă.');
            return;
        }

        $.ajax({
            url: 'sef_sala_modifica_cantitate.php',
            type: 'POST',
            data: {
                action: 'modify_quantity',
                id_vanz: id_vanz,
                new_quantity: newQuantity,
                motiv: motiv
            },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    $('#modifyModal').modal('hide');
                    $(`.note-list-item[data-nrbon="${currentBon}"]`).click(); 
                    showInfoModal('success', 'Modificat', 'Cantitatea a fost actualizată.');
                    setTimeout(() => $('#genericInfoModal').modal('hide'), 1500);
                } else {
                    showInfoModal('error', 'Eroare', res.message);
                }
            },
            error: function() {
                showInfoModal('error', 'Eroare conexiune', 'Eroare la comunicarea cu serverul.');
            }
        });
    });

    // --- Logica pentru ȘTERGERE PRODUS INDIVIDUAL ---
    $(document).on('click', '.delete-btn', function() {
        $('#deleteIdVanz').val($(this).data('id'));
        $('#deleteModal').modal('show');
    });

    $('#confirmDelete').click(function() {
        const id_vanz = $('#deleteIdVanz').val();
        const motiv = $('#deleteMotiv').val();

        $.ajax({
            url: 'sef_sala_modifica_cantitate.php',
            type: 'POST',
            data: { action: 'delete_item', id_vanz: id_vanz, motiv: motiv },
            dataType: 'json',
            success: function(res) {
                if(res.status === 'success') {
                    $('#deleteModal').modal('hide');
                     $(`.note-list-item[data-nrbon="${currentBon}"]`).click();
                     if (res.items_left === 0) {
                        $(`.note-list-item[data-nrbon="${currentBon}"]`).remove();
                        $('#detailsContainer').html('<div class="jumbotron text-center bg-light"><p class="lead">Bonul a fost golit și închis.</p></div>');
                     }
                     showInfoModal('success', 'Șters', 'Produsul a fost șters.');
                     setTimeout(() => $('#genericInfoModal').modal('hide'), 1500);
                } else {
                    showInfoModal('error', 'Eroare', res.message);
                }
            },
            error: function(){
                showInfoModal('error', 'Eroare conexiune', 'Eroare la comunicare.');
            }
        });
    });

    // --- Logica pentru ANULARE BON COMPLET ---
    $(document).on('click', '.delete-all-btn', function() {
        const nrbon = $(this).data('nrbon');
        $('#deleteAllNrBon').val(nrbon);
        $('#deleteAllModal').modal('show');
    });

    $('#confirmDeleteAll').click(function() {
        const nrbon = $('#deleteAllNrBon').val();
        const motiv = $('#deleteAllMotiv').val();

        if (!motiv) {
            showInfoModal('warning', 'Atenție', 'Introduceți un motiv pentru anularea bonului.');
            return;
        }

        $.ajax({
            url: 'sef_sala_modifica_cantitate.php',
            type: 'POST',
            data: { action: 'delete_all_items', nrbon: nrbon, motiv: motiv },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    $('#deleteAllModal').modal('hide');
                    $(`.note-list-item[data-nrbon="${nrbon}"]`).fadeOut(500, function() {
                        $(this).remove();
                    });
                    $('#detailsContainer').html('<div class="jumbotron text-center bg-light"><p class="lead">Bonul a fost anulat complet cu succes.</p></div>');
                    showInfoModal('success', 'Anulat', 'Bonul a fost șters în totalitate.');
                    setTimeout(() => $('#genericInfoModal').modal('hide'), 1500);
                } else {
                    showInfoModal('error', 'Eroare', res.message);
                }
            },
            error: function() {
                showInfoModal('error', 'Eroare conexiune', 'Eroare la comunicarea cu serverul.');
            }
        });
    });

    // --- Logica pentru ÎNCHIDERE MASĂ ---
    $(document).on('click', '.close-table-btn', function() {
        const nrbon = $(this).data('nrbon');
        
        $('#genericConfirmText').html('Sunteți sigur că vreți să închideți masa pentru acest bon?<br><small class="text-danger">Această acțiune este ireversibilă.</small>');
        $('#genericConfirmModal').modal('show');
        
        $('#btnGenericConfirmOk').off('click').on('click', function() {
            $('#genericConfirmModal').modal('hide');
            
            $.ajax({
                url: 'sef_sala_modifica_cantitate.php',
                type: 'POST',
                data: { action: 'close_table', nrbon: nrbon },
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        $(`.note-list-item[data-nrbon="${nrbon}"]`).remove();
                        $('#detailsContainer').html('<div class="jumbotron text-center bg-light"><p class="lead">Masa a fost închisă cu succes.</p></div>');
                        showInfoModal('success', 'Închisă', 'Masa a fost închisă din sistem.');
                        setTimeout(() => $('#genericInfoModal').modal('hide'), 1500);
                    } else {
                        showInfoModal('error', 'Eroare', res.message);
                    }
                },
                error: function() {
                    showInfoModal('error', 'Eroare conexiune', 'Eroare la comunicare.');
                }
            });
        });
    });

    // --- Logica pentru ÎNCHIDERE TURĂ OPERATOR ---
    $(document).on('click', '.btn-inchide-tura-op', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const opId = $btn.data('opid');
        const opName = $btn.data('opname');

        $('#genericConfirmText').html('Sunteți sigur că doriți să închideți tura pentru <strong>' + opName + '</strong>?');
        $('#genericConfirmModal').modal('show');

        $('#btnGenericConfirmOk').off('click').on('click', function() {
            $('#genericConfirmModal').modal('hide');
            
            const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Se procesează...');

            $.ajax({
                url: 'sefsala_inchide_tura_operator.php',
                type: 'POST',
                data: { operator_id: opId },
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        if (res.trigger_z === true && res.nr_raport_z > 0) {
                            showInfoModal('success', 'Tură Închisă', res.message + '<br><br><small class="text-primary font-weight-bold"><i class="fas fa-print"></i> Se generează automat și raportul Z (Nr: ' + res.nr_raport_z + ')...</small>');
                            setTimeout(function() {
                                window.location.href = 'vanzare_listare_inchidere_zi.php?nr_raport_z=' + res.nr_raport_z;
                            }, 3500);
                        } else {
                            showInfoModal('success', 'Tură Închisă', res.message);
                            setTimeout(function() {
                                location.reload(); 
                            }, 2000);
                        }
                    } else {
                        showInfoModal('error', 'Eroare', res.message);
                        $btn.prop('disabled', false).html(originalHtml);
                    }
                },
                error: function(xhr) {
                    let errorMsg = 'Eroare de comunicare cu serverul.';
                    if(xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    showInfoModal('error', 'Eroare', errorMsg);
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        });
    });

    let syncOnlineInProgress = false;
    const autoSalesSyncEnabled = <?php echo $autoSalesSyncEnabled ? 'true' : 'false'; ?>;
    const autoSalesSyncInterval = <?php echo (int)$autoSalesSyncInterval * 1000; ?>;

    function setAutoSyncStatus(text, cssClass) {
        $('#autoSyncOnlineStatus')
            .removeClass('text-light text-success text-warning text-danger')
            .addClass(cssClass || 'text-light')
            .text(text);
    }

    function runAutomaticSalesSync() {
        if (!autoSalesSyncEnabled || syncOnlineInProgress || document.hidden) {
            return;
        }

        syncOnlineInProgress = true;
        setAutoSyncStatus('Verificare automată...', 'text-warning');

        $.ajax({
            url: 'offline_sync_export.php',
            type: 'POST',
            dataType: 'json',
            data: { automatic: 1 },
            success: function(res) {
                if (res.status !== 'success') {
                    setAutoSyncStatus('Trimitere automată nereușită', 'text-danger');
                    return;
                }

                if (res.empty === true) {
                    setAutoSyncStatus(res.busy === true ? 'Sincronizare deja în curs' : 'Datele online sunt la zi', 'text-light');
                    return;
                }

                const online = res.online_sync || {};
                if (online.enabled === true && online.status === 'success') {
                    setAutoSyncStatus('Ultima trimitere automată a fost confirmată', 'text-success');
                } else {
                    setAutoSyncStatus('Trimiterea automată așteaptă reconectarea', 'text-danger');
                }
            },
            error: function() {
                setAutoSyncStatus('Trimiterea automată așteaptă reconectarea', 'text-danger');
            },
            complete: function() {
                syncOnlineInProgress = false;
                if (typeof window.refreshRestaurantSyncStatus === 'function' && $('#restaurantSyncStatusModal').hasClass('show')) {
                    window.refreshRestaurantSyncStatus();
                }
            }
        });
    }

    if (autoSalesSyncEnabled) {
        window.setTimeout(runAutomaticSalesSync, 10000);
        window.setInterval(runAutomaticSalesSync, autoSalesSyncInterval);
    } else {
        setAutoSyncStatus('Trimitere automată dezactivată', 'text-light');
    }

    $('#btnSyncOnline').on('click', function(e) {
        e.preventDefault();
        const $btn = $(this);

        $('#genericConfirmText').html('Generati pachetul JSON si il trimiteti catre sincronizarea online?');
        $('#genericConfirmModal').modal('show');

        $('#btnGenericConfirmOk').off('click').on('click', function() {
            $('#genericConfirmModal').modal('hide');

            if (syncOnlineInProgress) {
                showInfoModal('warning', 'Sync Online', 'O sincronizare este deja în curs.');
                return;
            }

            syncOnlineInProgress = true;

            const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Se sincronizeaza...');

            $.ajax({
                url: 'offline_sync_export.php',
                type: 'POST',
                dataType: 'json',
                data: {},
                success: function(res) {
                    if (res.status === 'success') {
                        if (res.empty === true) {
                            showInfoModal('warning', 'Sync Online', escapeHtml(res.message || 'Nu exista inregistrari noi pentru export.'));
                            return;
                        }

                        const counts = res.counts || {};
                        const online = res.online_sync || {};
                        const inserted = online.inserted || {};
                        const duplicates = online.duplicates || {};
                        const debugDb = online.debug_db || {};
                        let onlineText = '';
                        let debugText = '';
                        if (online.enabled === true && online.status === 'success') {
                            onlineText = [
                                '<br><small class="text-success">',
                                'Import online: ' + escapeHtml(online.message || 'confirmat'),
                                ' | Inserate: ',
                                escapeHtml(
                                    (inserted.note || 0) +
                                    (inserted.det_note || 0) +
                                    (inserted.inchideri_r_12 || 0) +
                                    (inserted.rapoarte_z || 0) +
                                    (inserted.discounturi_acordate || 0)
                                ),
                                ' | Duplicate: ',
                                escapeHtml(
                                    (duplicates.note || 0) +
                                    (duplicates.det_note || 0) +
                                    (duplicates.inchideri_r_12 || 0) +
                                    (duplicates.rapoarte_z || 0) +
                                    (duplicates.discounturi_acordate || 0)
                                ),
                                '</small>'
                            ].join('');
                        } else if (online.enabled === true && online.status === 'error') {
                            onlineText = '<br><small class="text-danger">Import online esuat: ' + escapeHtml(online.message || '') + '</small>';
                        }
                        if (debugDb && (debugDb.target_database || debugDb.connected_database)) {
                            debugText = [
                                '<br><small class="text-info">',
                                'Debug DB online: ',
                                escapeHtml(debugDb.connected_database || debugDb.target_database || ''),
                                debugDb.target_user ? ' | user: ' + escapeHtml(debugDb.target_user) : '',
                                debugDb.client_id ? ' | client: ' + escapeHtml(debugDb.client_id) : '',
                                debugDb.server_host ? ' | host: ' + escapeHtml(debugDb.server_host) : '',
                                '</small>'
                            ].join('');
                        } else if (online.debug_db_requested === true) {
                            debugText = [
                                '<br><small class="text-warning">',
                                'Debug DB activ, dar endpointul nu a returnat detalii DB',
                                online.endpoint ? ' | endpoint: ' + escapeHtml(online.endpoint) : '',
                                '</small>'
                            ].join('');
                        }

                        const text = [
                            escapeHtml(res.message || 'Pachetul de sync a fost generat cu succes.'),
                            '<br><small>',
                            'Note: ' + escapeHtml(counts.note || 0),
                            ' | Detalii: ' + escapeHtml(counts.det_note || 0),
                            ' | Inchideri: ' + escapeHtml(counts.inchideri_r_12 || 0),
                            ' | Rapoarte Z: ' + escapeHtml(counts.rapoarte_z || 0),
                            ' | Discounturi: ' + escapeHtml(counts.discounturi_acordate || 0),
                            '</small>',
                            onlineText,
                            debugText,
                            '<br><small class="text-muted">' + escapeHtml(res.file || '') + '</small>'
                        ].join('');
                        showInfoModal('success', 'Sync Online', text);
                    } else {
                        showInfoModal('error', 'Eroare', escapeHtml(res.message || 'Exportul de sync a esuat.'));
                    }
                },
                error: function(xhr) {
                    let msg = 'Eroare la generarea exportului de sync.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    showInfoModal('error', 'Eroare', escapeHtml(msg));
                },
                complete: function() {
                    syncOnlineInProgress = false;
                    $btn.prop('disabled', false).html(originalHtml);
                    if (typeof window.refreshRestaurantSyncStatus === 'function') {
                        window.refreshRestaurantSyncStatus();
                    }
                }
            });
        });
    });
    
});
</script>
<script src="offline_sync_heartbeat.js"></script>
</body>
</html>
