<?php
$offlineShiftClosedCode = (int)($_SESSION['offline_shift_closed'] ?? 0);
$offlineZClosedNumber = (int)($_SESSION['offline_z_closed'] ?? 0);
$offlineZError = trim((string)($_SESSION['offline_z_error'] ?? ''));
unset($_SESSION['offline_shift_closed'], $_SESSION['offline_z_closed'], $_SESSION['offline_z_error']);

$offlineZNextNumber = 1;
try {
    $offlineZNextNumber = offline_raport_z_next_number($pdo, (int)($cod_locatie ?? offline_sequence_config()['cod_locatie']));
} catch (Throwable $e) {
    $offlineZNextNumber = 1;
}

$offlineClosedShift = null;
foreach ($offlinePendingClosures as $offlinePendingClosure) {
    if ((int)$offlinePendingClosure['cod_inchidere'] === $offlineShiftClosedCode) {
        $offlineClosedShift = $offlinePendingClosure;
        break;
    }
}

function offline_closure_ui_money($value): string
{
    return number_format((float)$value, 2, ',', '.');
}
?>
<style>
    .offline-z-summary {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 8px;
        margin-bottom: 12px;
    }
    .offline-z-summary > div {
        border: 1px solid #d7dde5;
        border-radius: 6px;
        padding: 9px 10px;
        background: #f8fafc;
    }
    .offline-z-summary small { display: block; color: #64748b; }
    .offline-z-summary strong { display: block; font-size: 18px; }
    .offline-z-table-wrap { max-height: 270px; overflow: auto; border: 1px solid #d7dde5; }
    .offline-z-table { margin: 0; font-size: 13px; }
    .offline-z-table thead th { position: sticky; top: 0; background: #eef2f7; z-index: 1; white-space: nowrap; }
    .offline-z-table td { vertical-align: middle; white-space: nowrap; }
    .offline-z-note { border-left: 4px solid #d97706; background: #fff7ed; padding: 10px 12px; margin-bottom: 12px; }
    .offline-shift-result { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
    .offline-shift-result > div { border: 1px solid #d7dde5; padding: 10px; border-radius: 6px; }
    .offline-shift-result span { display: block; color: #64748b; font-size: 12px; }
    .offline-shift-result strong { font-size: 18px; }
    @media (max-width: 767px) {
        .offline-z-summary, .offline-shift-result { grid-template-columns: 1fr; }
        .offline-z-table-wrap { max-height: 220px; }
    }
</style>

<div class="modal fade" id="raportZModal" tabindex="-1" role="dialog" aria-labelledby="raportZModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="raportZModalLabel">Închidere zi cu Raport Z</h5>
                    <small>Selectați turele înscrise pe raportul fiscal tipărit de casa de marcat.</small>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Închide"><span aria-hidden="true">&times;</span></button>
            </div>
            <form method="POST" id="raportZForm" action="vanzare_inchidere_zi.php">
                <div class="modal-body">
                    <?php if ($offlineZError !== ''): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($offlineZError, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>

                    <div class="offline-z-note">
                        Turele pot trece de miezul nopții. Data calendaristică nu le selectează automat. Includeți numai turele care aparțin raportului Z introdus mai jos.
                    </div>

                    <?php if (!$offlinePendingClosures): ?>
                        <div class="alert alert-info mb-0">Nu există ture închise care așteaptă un raport Z.</div>
                    <?php else: ?>
                        <div class="offline-z-summary">
                            <div><small>Ture selectate</small><strong id="offlineZSelectedClosures">0</strong></div>
                            <div><small>Bonuri incluse</small><strong id="offlineZSelectedReceipts">0</strong></div>
                            <div><small>Total bonuri</small><strong><span id="offlineZSelectedTotal">0,00</span> lei</strong></div>
                        </div>

                        <div class="custom-control custom-checkbox mb-2">
                            <input type="checkbox" class="custom-control-input" id="offlineZSelectAll" checked>
                            <label class="custom-control-label" for="offlineZSelectAll">Selectează toate turele afișate</label>
                        </div>

                        <div class="offline-z-table-wrap mb-3">
                            <table class="table table-sm table-striped offline-z-table">
                                <thead>
                                    <tr>
                                        <th></th><th>Tură</th><th>Operator</th><th>Închisă la</th><th>Interval bonuri</th><th>Bonuri</th><th>Total</th><th>TVA</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($offlinePendingClosures as $closure):
                                    $code = (int)$closure['cod_inchidere'];
                                ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="offline-z-closure" name="coduri_inchidere[]"
                                                   value="<?php echo $code; ?>" checked
                                                   data-bonuri="<?php echo (int)$closure['bonuri']; ?>"
                                                   data-total="<?php echo htmlspecialchars((string)$closure['valoare_cu_tva'], ENT_QUOTES, 'UTF-8'); ?>"
                                                   data-numerar="<?php echo htmlspecialchars((string)$closure['numerar'], ENT_QUOTES, 'UTF-8'); ?>"
                                                   data-card="<?php echo htmlspecialchars((string)$closure['card'], ENT_QUOTES, 'UTF-8'); ?>"
                                                   data-tichete="<?php echo htmlspecialchars((string)$closure['tichete_masa'], ENT_QUOTES, 'UTF-8'); ?>"
                                                   data-online="<?php echo htmlspecialchars((string)$closure['plata_moderna'], ENT_QUOTES, 'UTF-8'); ?>"
                                                   data-altele="<?php echo htmlspecialchars((string)$closure['alte_metode'], ENT_QUOTES, 'UTF-8'); ?>">
                                        </td>
                                        <td><strong><?php echo $code; ?></strong></td>
                                        <td><?php echo (int)$closure['operator']; ?></td>
                                        <td><?php echo htmlspecialchars(trim((string)$closure['data_inchiderii'] . ' ' . (string)$closure['ora_inchiderii']), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string)$closure['primul_bon'] . ' - ' . (string)$closure['ultimul_bon'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo (int)$closure['bonuri']; ?></td>
                                        <td><?php echo offline_closure_ui_money($closure['valoare_cu_tva']); ?> lei</td>
                                        <td><?php echo offline_closure_ui_money($closure['tva_colectata']); ?> lei</td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="row">
                            <div class="form-group col-md-4">
                                <label>Numerar</label>
                                <input type="number" step="0.01" min="0" class="form-control offline-z-payment" id="offlineZNumerar" name="numerar" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label>Card</label>
                                <input type="number" step="0.01" min="0" class="form-control offline-z-payment" id="offlineZCard" name="card" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label>Tichete masă</label>
                                <input type="number" step="0.01" min="0" class="form-control offline-z-payment" id="offlineZTichete" name="tichete_masa" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label>Credit</label>
                                <input type="number" step="0.01" min="0" class="form-control" value="0.00" name="credit" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label>Plată modernă, online</label>
                                <input type="number" step="0.01" min="0" class="form-control offline-z-payment" id="offlineZOnline" name="plata_moderna" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label>Alte metode</label>
                                <input type="number" step="0.01" min="0" class="form-control offline-z-payment" id="offlineZAltele" name="alte_metode" required>
                            </div>
                            <div class="form-group col-md-4 mb-0">
                                <label>Număr raport Z casa de marcat</label>
                                <input type="number" min="1" class="form-control" name="nr_raport_z" value="<?php echo $offlineZNextNumber; ?>" required>
                            </div>
                        </div>
                        <input type="hidden" name="tichete_valorice" value="0">
                        <input type="hidden" name="avans_in_numerar" value="0">
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Continuă vânzarea</button>
                    <?php if ($offlinePendingClosures): ?>
                        <button type="submit" class="btn btn-danger">Înregistrează Raportul Z</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($offlineShiftClosedCode > 0): ?>
<div class="modal fade" id="offlineShiftClosedModal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Tura <?php echo $offlineShiftClosedCode; ?> a fost închisă</h5></div>
        <div class="modal-body">
            <p>Tura a fost salvată și așteaptă asocierea cu un raport Z. Puteți schimba operatorul, începe o tură nouă sau închide ziua.</p>
            <?php if ($offlineClosedShift): ?>
                <div class="offline-shift-result">
                    <div><span>Bonuri</span><strong><?php echo (int)$offlineClosedShift['bonuri']; ?></strong></div>
                    <div><span>Total tură</span><strong><?php echo offline_closure_ui_money($offlineClosedShift['valoare_cu_tva']); ?> lei</strong></div>
                    <div><span>TVA</span><strong><?php echo offline_closure_ui_money($offlineClosedShift['tva_colectata']); ?> lei</strong></div>
                </div>
            <?php endif; ?>
        </div>
        <div class="modal-footer justify-content-between">
            <a href="logout.php" class="btn btn-secondary">Schimbă operatorul</a>
            <div>
                <button type="button" class="btn btn-outline-primary" data-dismiss="modal">Începe o tură nouă</button>
                <button type="button" class="btn btn-danger" id="offlineContinueToZ">Închide ziua cu Raport Z</button>
            </div>
        </div>
    </div></div>
</div>
<?php endif; ?>

<?php if ($offlineZClosedNumber > 0): ?>
<div class="modal fade" id="offlineZClosedModal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false" aria-hidden="true">
    <div class="modal-dialog" role="document"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Raportul Z <?php echo $offlineZClosedNumber; ?> a fost salvat</h5></div>
        <div class="modal-body">Turele selectate, bonurile și mișcările aferente au fost asociate raportului Z și au fost înscrise pentru transmiterea online.</div>
        <div class="modal-footer justify-content-between">
            <a href="logout.php" class="btn btn-secondary">Deconectare</a>
            <button type="button" class="btn btn-primary" data-dismiss="modal">Continuă vânzarea</button>
        </div>
    </div></div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var closureInputs = Array.prototype.slice.call(document.querySelectorAll('.offline-z-closure'));
    var selectAll = document.getElementById('offlineZSelectAll');
    var form = document.getElementById('raportZForm');

    function numberValue(input, name) {
        return parseFloat(input.getAttribute('data-' + name) || '0') || 0;
    }

    function setPayment(id, value) {
        var field = document.getElementById(id);
        if (field) field.value = value.toFixed(2);
    }

    function updateZSelection() {
        var selected = closureInputs.filter(function (input) { return input.checked; });
        var receipts = 0, total = 0, cash = 0, card = 0, tickets = 0, online = 0, other = 0;
        selected.forEach(function (input) {
            receipts += parseInt(input.getAttribute('data-bonuri') || '0', 10) || 0;
            total += numberValue(input, 'total');
            cash += numberValue(input, 'numerar');
            card += numberValue(input, 'card');
            tickets += numberValue(input, 'tichete');
            online += numberValue(input, 'online');
            other += numberValue(input, 'altele');
        });
        var countNode = document.getElementById('offlineZSelectedClosures');
        var receiptsNode = document.getElementById('offlineZSelectedReceipts');
        var totalNode = document.getElementById('offlineZSelectedTotal');
        if (countNode) countNode.textContent = selected.length;
        if (receiptsNode) receiptsNode.textContent = receipts;
        if (totalNode) totalNode.textContent = total.toLocaleString('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        setPayment('offlineZNumerar', cash);
        setPayment('offlineZCard', card);
        setPayment('offlineZTichete', tickets);
        setPayment('offlineZOnline', online);
        setPayment('offlineZAltele', other);
        if (selectAll) selectAll.checked = closureInputs.length > 0 && selected.length === closureInputs.length;
    }

    closureInputs.forEach(function (input) { input.addEventListener('change', updateZSelection); });
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            closureInputs.forEach(function (input) { input.checked = selectAll.checked; });
            updateZSelection();
        });
    }
    if (form) {
        form.addEventListener('submit', function (event) {
            if (!closureInputs.some(function (input) { return input.checked; })) {
                event.preventDefault();
                window.alert('Selectați cel puțin o tură pentru raportul Z.');
            }
        });
    }
    updateZSelection();

    <?php if ($offlineShiftClosedCode > 0): ?>
    $('#offlineShiftClosedModal').modal('show');
    var continueToZ = document.getElementById('offlineContinueToZ');
    if (continueToZ) {
        continueToZ.addEventListener('click', function () {
            $('#offlineShiftClosedModal').one('hidden.bs.modal', function () { $('#raportZModal').modal('show'); }).modal('hide');
        });
    }
    <?php elseif ($offlineZClosedNumber > 0): ?>
    $('#offlineZClosedModal').modal('show');
    <?php elseif ($offlineZError !== ''): ?>
    $('#raportZModal').modal('show');
    <?php endif; ?>
});
</script>
