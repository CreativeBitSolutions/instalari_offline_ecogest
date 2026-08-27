<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['offline_cui_csrf'])) {
    $_SESSION['offline_cui_csrf'] = bin2hex(random_bytes(24));
}
$offlineCuiCurrent = isset($_SESSION['cif_client']) ? (string)$_SESSION['cif_client'] : '';
?>
<style>
    #cif-keyboard-modal { z-index: 1080; }
    #cif-keyboard-modal .modal-dialog { max-width: 620px; }
    #cif-keyboard-modal .modal-content { border-radius: 6px; overflow: hidden; }
    #cif-keyboard-modal .offline-cui-input {
        height: 58px; font-size: 1.65rem; text-align: center; font-weight: 700;
        letter-spacing: 0; border: 2px solid #b8c3ce;
    }
    #cif-keyboard-modal .offline-cui-keyboard {
        display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; margin-top: 14px;
    }
    #cif-keyboard-modal .offline-cui-keyboard .btn { min-height: 48px; font-size: 1.15rem; font-weight: 700; }
    #cif-keyboard-modal .offline-cui-result {
        margin-top: 16px; padding: 14px 16px; border: 1px solid #b8d8c3;
        border-left: 5px solid #238653; border-radius: 5px; background: #f1faf5;
    }
    #cif-keyboard-modal .offline-cui-company { margin-bottom: 9px; font-size: 1.15rem; font-weight: 700; }
    #cif-keyboard-modal .offline-cui-row { display: grid; grid-template-columns: 120px minmax(0, 1fr); gap: 8px; padding: 3px 0; }
    #cif-keyboard-modal .offline-cui-label { color: #59656f; font-weight: 600; }
    #cif-keyboard-modal .offline-cui-progress { margin-top: 15px; color: #1769aa; text-align: center; font-weight: 600; }
    #cif-keyboard-modal .offline-cui-note { margin-top: 10px; padding-top: 10px; border-top: 1px solid #d6e8dc; font-weight: 600; }
    #cif-keyboard-modal .offline-cui-error { margin-top: 15px; margin-bottom: 0; }
    #cif_client_input.offline-cui-trigger { cursor: pointer; background: #fff; }
    @media (max-width: 575px) {
        #cif-keyboard-modal .offline-cui-row { grid-template-columns: 1fr; gap: 0; }
        #cif-keyboard-modal .modal-footer { align-items: stretch; }
        #cif-keyboard-modal .modal-footer .btn { width: 100%; margin: 3px 0; }
    }
</style>

<div class="modal fade" id="cif-keyboard-modal" tabindex="-1" role="dialog" aria-labelledby="offlineCuiModalTitle" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="offlineCuiModalTitle">Verificare CUI client</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Închide"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="text" id="cif-keyboard-display" class="form-control offline-cui-input" maxlength="12" inputmode="text" autocomplete="off" placeholder="Introduceți CUI-ul">
                <div class="offline-cui-keyboard" aria-label="Tastatură CUI">
                    <button type="button" class="btn btn-light offline-cui-key" data-key="1">1</button>
                    <button type="button" class="btn btn-light offline-cui-key" data-key="2">2</button>
                    <button type="button" class="btn btn-light offline-cui-key" data-key="3">3</button>
                    <button type="button" class="btn btn-info offline-cui-key" data-action="prefix-ro">RO</button>
                    <button type="button" class="btn btn-light offline-cui-key" data-key="4">4</button>
                    <button type="button" class="btn btn-light offline-cui-key" data-key="5">5</button>
                    <button type="button" class="btn btn-light offline-cui-key" data-key="6">6</button>
                    <button type="button" class="btn btn-warning offline-cui-key" data-action="clear">Șterge</button>
                    <button type="button" class="btn btn-light offline-cui-key" data-key="7">7</button>
                    <button type="button" class="btn btn-light offline-cui-key" data-key="8">8</button>
                    <button type="button" class="btn btn-light offline-cui-key" data-key="9">9</button>
                    <button type="button" class="btn btn-light offline-cui-key" data-key="0">0</button>
                    <button type="button" class="btn btn-secondary offline-cui-key" data-action="backspace" style="grid-column: span 4;">
                        <i class="fas fa-backspace" aria-hidden="true"></i> Șterge ultimul caracter
                    </button>
                </div>
                <div class="offline-cui-progress d-none" id="offlineCuiProgress" role="status">
                    <i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Se verifică datele firmei...
                </div>
                <div class="offline-cui-result d-none" id="offlineCuiResult" aria-live="polite">
                    <div class="offline-cui-company" id="offlineCuiCompanyName"></div>
                    <div class="offline-cui-row"><span class="offline-cui-label">CUI confirmat</span><span id="offlineCuiCompanyCode"></span></div>
                    <div class="offline-cui-row"><span class="offline-cui-label">Nr. registru</span><span id="offlineCuiCompanyRegistry"></span></div>
                    <div class="offline-cui-row"><span class="offline-cui-label">Adresă</span><span id="offlineCuiCompanyAddress"></span></div>
                    <div class="offline-cui-row"><span class="offline-cui-label">Plătitor TVA</span><span id="offlineCuiCompanyVat"></span></div>
                    <div class="offline-cui-note">Verificați firma, apoi confirmați salvarea.</div>
                </div>
                <div class="alert alert-danger offline-cui-error d-none" id="offlineCuiError" role="alert"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary mr-auto d-none" id="offlineCuiManualButton">SALVEAZĂ FĂRĂ VERIFICARE</button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">ANULEAZĂ</button>
                <button type="button" class="btn btn-primary" id="offlineCuiPrimaryButton">VERIFICĂ CUI</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
if (!window.jQuery) return;
(function ($) {
    if (window.__offlineCuiModalAttached) return;
    window.__offlineCuiModalAttached = true;

    var endpoint = 'offline_cui_client.php';
    var fieldSelector = '#cif_client_input, input[name="cif_client"], input[name="cif_client_m"], input[name="cif_client_t"]';
    var csrfToken = <?php echo json_encode($_SESSION['offline_cui_csrf'], JSON_UNESCAPED_SLASHES); ?>;
    var currentCif = <?php echo json_encode($offlineCuiCurrent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var verifiedCif = '';
    var lookupToken = '';

    function normalize(value) {
        var compact = String(value || '').replace(/\s+/g, '').toUpperCase();
        var hasRo = compact.indexOf('RO') === 0;
        var digits = compact.replace(/\D/g, '').slice(0, 10);
        return digits ? (hasRo ? 'RO' : '') + digits : '';
    }

    function resetResult() {
        verifiedCif = '';
        lookupToken = '';
        $('#offlineCuiResult, #offlineCuiProgress, #offlineCuiManualButton').addClass('d-none');
        $('#offlineCuiError').addClass('d-none').text('');
        $('#offlineCuiPrimaryButton').text('VERIFICĂ CUI');
    }

    function setAllFields(value) {
        currentCif = normalize(value);
        $(fieldSelector).val(currentCif).prop('readonly', true).addClass('offline-cui-trigger');
        $('#cif_client_hidden').val(currentCif);
    }

    function showError(message, manualAllowed) {
        $('#offlineCuiError').removeClass('d-none').text(message || 'CUI-ul nu a putut fi verificat.');
        $('#offlineCuiManualButton').toggleClass('d-none', !manualAllowed);
    }

    function post(action, extra) {
        return $.ajax({
            url: endpoint,
            method: 'POST',
            dataType: 'json',
            timeout: 25000,
            data: $.extend({
                action: action,
                cui: normalize($('#cif-keyboard-display').val()),
                csrf_token: csrfToken
            }, extra || {})
        });
    }

    function value(data, keys) {
        for (var index = 0; index < keys.length; index++) {
            if (data[keys[index]] !== undefined && data[keys[index]] !== null && String(data[keys[index]]).trim() !== '') {
                return String(data[keys[index]]).trim();
            }
        }
        return '';
    }

    function showCompany(response) {
        var data = response.data || {};
        var name = value(data, ['nume', 'denumire']);
        var confirmed = normalize(response.cif_client || data.cui_formatat || data.cod_fiscal || data.cui);
        if (!name || !confirmed) throw new Error('Datele firmei sunt incomplete.');
        var address = [value(data, ['adresa']), value(data, ['localitate', 'loc']), value(data, ['judet'])]
            .filter(function (item, index, items) { return item && items.indexOf(item) === index; })
            .join(', ');
        $('#cif-keyboard-display').val(confirmed);
        $('#offlineCuiCompanyName').text(name);
        $('#offlineCuiCompanyCode').text(confirmed);
        $('#offlineCuiCompanyRegistry').text(value(data, ['cod_inmatriculare', 'nr_reg_com', 'nrRegCom']) || 'Nespecificat');
        $('#offlineCuiCompanyAddress').text(address || 'Nespecificată');
        $('#offlineCuiCompanyVat').text(String(data.tva) === '1' ? 'Da' : 'Nu');
        $('#offlineCuiResult').removeClass('d-none');
        verifiedCif = confirmed;
        lookupToken = String(response.lookup_token || '');
        $('#offlineCuiPrimaryButton').text('CONFIRMĂ ȘI SALVEAZĂ');
    }

    function finishSave(response) {
        setAllFields(response.cif_client || '');
        $('#cif_client_input').trigger('change');
        $('#cif-keyboard-modal').modal('hide');
    }

    setAllFields(currentCif);

    $(document).on('click', '#cif-kbd-btn, ' + fieldSelector, function (event) {
        event.preventDefault();
        var clickedValue = $(this).is(fieldSelector) ? $(this).val() : currentCif;
        $('#cif-keyboard-display').val(normalize(clickedValue || currentCif));
        resetResult();
        $('#cif-keyboard-modal').modal('show');
    });

    $('#cif-keyboard-modal').on('shown.bs.modal', function () {
        $('#cif-keyboard-display').trigger('focus');
    });

    $(document).on('input', '#cif-keyboard-display', function () {
        var normalized = normalize(this.value);
        if (this.value !== normalized) this.value = normalized;
        resetResult();
    });

    $(document).on('click', '#cif-keyboard-modal .offline-cui-key', function () {
        var input = $('#cif-keyboard-display');
        var current = normalize(input.val());
        var action = String($(this).data('action') || '');
        var key = String($(this).data('key') || '');
        if (action === 'clear') input.val('').trigger('input');
        else if (action === 'backspace') input.val(current.slice(0, -1)).trigger('input');
        else if (action === 'prefix-ro') input.val('RO' + current.replace(/^RO/, '')).trigger('input');
        else if (/^[0-9]$/.test(key) && current.replace(/\D/g, '').length < 10) input.val(current + key).trigger('input');
    });

    $('#offlineCuiPrimaryButton').on('click', function () {
        var button = $(this);
        var entered = normalize($('#cif-keyboard-display').val());
        button.prop('disabled', true);
        $('#offlineCuiError, #offlineCuiManualButton').addClass('d-none');

        if (entered === '') {
            post('clear').done(finishSave).fail(function (xhr) {
                showError((xhr.responseJSON && xhr.responseJSON.message) || 'CUI-ul nu a putut fi eliminat.', false);
            }).always(function () { button.prop('disabled', false); });
            return;
        }

        if (verifiedCif === entered && lookupToken !== '') {
            post('save_verified', { lookup_token: lookupToken }).done(finishSave).fail(function (xhr) {
                resetResult();
                showError((xhr.responseJSON && xhr.responseJSON.message) || 'CUI-ul nu a putut fi salvat.', false);
            }).always(function () { button.prop('disabled', false); });
            return;
        }

        $('#offlineCuiProgress').removeClass('d-none');
        post('lookup').done(function (response) {
            try { showCompany(response); }
            catch (error) { showError(error.message, false); }
        }).fail(function (xhr) {
            var response = xhr.responseJSON || {};
            showError(response.message || 'CUI-ul nu a putut fi verificat.', response.manual_allowed === true);
        }).always(function () {
            $('#offlineCuiProgress').addClass('d-none');
            button.prop('disabled', false);
        });
    });

    $('#offlineCuiManualButton').on('click', function () {
        var button = $(this);
        button.prop('disabled', true);
        post('save_manual').done(finishSave).fail(function (xhr) {
            showError((xhr.responseJSON && xhr.responseJSON.message) || 'CUI-ul nu a putut fi salvat manual.', true);
        }).always(function () { button.prop('disabled', false); });
    });
})(window.jQuery);
});
</script>
