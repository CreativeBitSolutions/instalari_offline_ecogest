<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['offline_cui_csrf'])) {
    $_SESSION['offline_cui_csrf'] = bin2hex(random_bytes(24));
}
?>
<style>
  .cif-client-trigger {
    cursor:pointer; background-color:#fff !important;
  }
  #CifClientModal { z-index:1080; }
  .cif-client-modal-backdrop { z-index:1070; }
  .cif-input-modern {
    font-size: 1.8em !important; text-align:center; font-weight:700;
    letter-spacing: 2px; border:2px solid #ced4da; padding:15px 10px !important; margin-bottom:20px !important;
  }
  #cifKeyboard { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; width:100%; }
  #cifKeyboard .btn { font-size:1.4em; padding:14px 0; font-weight:700; border-radius:8px; }
  #cifKeyboard .key-bksp { grid-column: span 2; }
  #CifClientModal .cif-company-result {
    margin-top:16px; padding:14px 16px; border:1px solid #b8d8c3; border-left:5px solid #28a745;
    border-radius:6px; background:#f3fbf6; color:#25342a;
  }
  #CifClientModal .cif-company-name { margin-bottom:10px; font-size:1.15rem; font-weight:700; }
  #CifClientModal .cif-company-row { display:grid; grid-template-columns:115px minmax(0,1fr); gap:8px; padding:3px 0; }
  #CifClientModal .cif-company-label { color:#5f6b63; font-weight:600; }
  #CifClientModal .cif-company-confirm { margin-top:10px; padding-top:10px; border-top:1px solid #d6e8dc; font-weight:600; }
  @media (max-width:575.98px) {
    #CifClientModal .cif-company-row { grid-template-columns:1fr; gap:0; }
  }
</style>

<div class="modal fade" id="CifClientModal" tabindex="-1" role="dialog" aria-labelledby="CifClientModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document" style="max-width:520px;">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="CifClientModalLabel">CIF / CUI client</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Închide"><span aria-hidden="true">&times;</span></button>
      </div>

      <form id="CifClientModalForm" autocomplete="off">
        <div class="modal-body">
          <div class="form-group">
            <input type="text" class="form-control cif-input-modern" id="cif_client_modal" name="cif_client_modal"
                   maxlength="12" placeholder="Introduceți CIF..."
                   value="<?php echo isset($_SESSION['cif_client']) ? htmlspecialchars($_SESSION['cif_client'],ENT_QUOTES,'UTF-8') : ''; ?>">
          </div>

          <div id="cifKeyboard">
            <button type="button" class="btn btn-light" data-key="1">1</button>
            <button type="button" class="btn btn-light" data-key="2">2</button>
            <button type="button" class="btn btn-light" data-key="3">3</button>
            <button type="button" class="btn btn-info"  data-key="RO">RO</button>

            <button type="button" class="btn btn-light" data-key="4">4</button>
            <button type="button" class="btn btn-light" data-key="5">5</button>
            <button type="button" class="btn btn-light" data-key="6">6</button>
            <button type="button" class="btn btn-warning" data-key="clear">Șterge</button>

            <button type="button" class="btn btn-light" data-key="7">7</button>
            <button type="button" class="btn btn-light" data-key="8">8</button>
            <button type="button" class="btn btn-light" data-key="9">9</button>
            <button type="button" class="btn btn-light" data-key="0">0</button>

            <button type="button" class="btn btn-secondary key-bksp" data-key="bksp">&larr; Backspace</button>
          </div>
          <div class="text-center text-info mt-3 d-none" id="cifClientLookupProgress" role="status">
            Se verifică datele firmei la ANAF...
          </div>
          <div class="cif-company-result d-none" id="cifClientCompanyResult" aria-live="polite">
            <div class="cif-company-name" id="cifClientCompanyName"></div>
            <div class="cif-company-row"><span class="cif-company-label">CUI confirmat</span><span id="cifClientCompanyCui"></span></div>
            <div class="cif-company-row"><span class="cif-company-label">Nr. registru</span><span id="cifClientCompanyRegistry"></span></div>
            <div class="cif-company-row"><span class="cif-company-label">Adresă</span><span id="cifClientCompanyAddress"></span></div>
            <div class="cif-company-row"><span class="cif-company-label">Plătitor TVA</span><span id="cifClientCompanyVat"></span></div>
            <div class="cif-company-confirm">Verificați firma, apoi apăsați „Confirmă și salvează”.</div>
          </div>
          <div class="alert alert-danger mt-3 mb-0 d-none" id="cifClientSaveError" role="alert"></div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary mr-auto d-none" id="cifClientManualSaveButton">Salvează fără verificare</button>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Anulează</button>
          <button type="submit" class="btn btn-primary" id="cifClientSaveButton">Verifică CUI</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function () {
  if (window.__appRestaurantV2CifModalAttached) return;
  window.__appRestaurantV2CifModalAttached = true;

  var cifSelector = 'input[name="cif_client"], input[name="cif_client_m"], input[name="cif_client_t"]';
  var currentCif = <?php echo json_encode($_SESSION['cif_client'] ?? '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  var csrfToken = <?php echo json_encode($_SESSION['offline_cui_csrf'], JSON_UNESCAPED_SLASHES); ?>;
  var verifiedCif = '';
  var lookupToken = '';

  function normalizeCif(value) {
    var compact = String(value || '').replace(/\s+/g, '').toUpperCase();
    var hasRo = compact.indexOf('RO') === 0;
    var digits = compact.replace(/\D/g, '').slice(0, 10);
    return digits ? (hasRo ? 'RO' : '') + digits : '';
  }

  function setAllCifFields(value) {
    currentCif = normalizeCif(value);
    $(cifSelector).val(currentCif).attr({ readonly: 'readonly', maxlength: 12 }).addClass('cif-client-trigger');
  }

  function resetCompanyResult() {
    verifiedCif = '';
    lookupToken = '';
    $('#cifClientCompanyResult, #cifClientLookupProgress, #cifClientManualSaveButton').addClass('d-none');
    $('#cifClientSaveButton').text('Verifică CUI');
  }

  function companyValue(data, keys) {
    for (var index = 0; index < keys.length; index++) {
      var item = data[keys[index]];
      if (item !== undefined && item !== null && String(item).trim() !== '') return String(item).trim();
    }
    return '';
  }

  function showCompanyResult(response) {
    var data = response.data || {};
    var companyName = companyValue(data, ['nume', 'denumire']);
    var confirmedCif = normalizeCif(response.cif_client || data.cui_formatat || data.cod_fiscal || data.cui);
    var addressParts = [
      companyValue(data, ['adresa']),
      companyValue(data, ['localitate', 'loc']),
      companyValue(data, ['judet'])
    ].filter(function (item, index, items) { return item && items.indexOf(item) === index; });
    if (!companyName || !confirmedCif) throw new Error('Datele firmei sunt incomplete.');

    $('#cif_client_modal').val(confirmedCif);
    $('#cifClientCompanyName').text(companyName);
    $('#cifClientCompanyCui').text(confirmedCif);
    $('#cifClientCompanyRegistry').text(companyValue(data, ['cod_inmatriculare', 'nr_reg_com', 'nrRegCom']) || 'Nespecificat');
    $('#cifClientCompanyAddress').text(addressParts.join(', ') || 'Nespecificată');
    $('#cifClientCompanyVat').text(String(data.tva) === '1' ? 'Da' : 'Nu');
    $('#cifClientCompanyResult').removeClass('d-none');
    verifiedCif = confirmedCif;
    lookupToken = String(response.lookup_token || '');
    $('#cifClientSaveButton').text('Confirmă și salvează');
  }

  function postAction(action, extra) {
    return $.ajax({
      url: 'offline_cui_client.php',
      method: 'POST',
      dataType: 'json',
      timeout: 25000,
      data: $.extend({
        action: action,
        cui: normalizeCif($('#cif_client_modal').val()),
        csrf_token: csrfToken
      }, extra || {})
    });
  }

  function finishSave(response) {
    setAllCifFields(response.cif_client || '');
    $(cifSelector).trigger('change');
    $('#CifClientModal').modal('hide');
  }

  function showError(message, manualAllowed) {
    $('#cifClientSaveError').removeClass('d-none').text(message || 'CUI-ul nu a putut fi verificat.');
    $('#cifClientManualSaveButton').toggleClass('d-none', !manualAllowed);
  }

  setAllCifFields(currentCif);

  $(document).on('click', '#openCifModal, ' + cifSelector, function (event) {
    event.preventDefault();
    var clickedValue = $(this).is(cifSelector) ? $(this).val() : currentCif;
    $('#cif_client_modal').val(normalizeCif(clickedValue || currentCif));
    $('#cifClientSaveError').addClass('d-none').text('');
    resetCompanyResult();
    $('#CifClientModal').modal('show');
  });

  $('#CifClientModal').on('shown.bs.modal', function () {
    $('.modal-backdrop').last().addClass('cif-client-modal-backdrop');
    $('#cif_client_modal').trigger('focus');
  });
  $('#CifClientModal').on('hidden.bs.modal', function () {
    if ($('.modal.show').length) $('body').addClass('modal-open');
  });

  $(document).on('input', '#cif_client_modal', function () {
    var normalized = normalizeCif(this.value);
    if (this.value !== normalized) this.value = normalized;
    resetCompanyResult();
    $('#cifClientSaveError').addClass('d-none').text('');
  });

  $(document).on('click', '#cifKeyboard [data-key]', function () {
    var key = String($(this).attr('data-key'));
    var input = $('#cif_client_modal');
    var value = normalizeCif(input.val());
    if (key === 'bksp') input.val(value.slice(0, -1)).trigger('input');
    else if (key === 'clear') input.val('').trigger('input');
    else if (key === 'RO') input.val('RO' + value.replace(/^RO/i, '')).trigger('input');
    else if (/^[0-9]$/.test(key) && value.replace(/\D/g, '').length < 10) input.val(value + key).trigger('input');
  });

  $(document).on('submit', '#CifClientModalForm', function (event) {
    event.preventDefault();
    var value = normalizeCif($('#cif_client_modal').val());
    var button = $('#cifClientSaveButton');
    button.prop('disabled', true);
    $('#cifClientSaveError, #cifClientManualSaveButton').addClass('d-none');

    if (value === '') {
      postAction('clear').done(finishSave).fail(function (xhr) {
        showError((xhr.responseJSON && xhr.responseJSON.message) || 'CUI-ul nu a putut fi eliminat.', false);
      }).always(function () { button.prop('disabled', false); });
      return;
    }

    if (verifiedCif === value && lookupToken !== '') {
      postAction('save_verified', { lookup_token: lookupToken }).done(finishSave).fail(function (xhr) {
        showError((xhr.responseJSON && xhr.responseJSON.message) || 'CUI-ul nu a putut fi salvat.', false);
        resetCompanyResult();
      }).always(function () { button.prop('disabled', false); });
      return;
    }

    $('#cifClientLookupProgress').removeClass('d-none');
    postAction('lookup').done(function (response) {
      try { showCompanyResult(response); }
      catch (error) { showError(error.message, false); }
    }).fail(function (xhr) {
      var response = xhr.responseJSON || {};
      showError(response.message || 'CUI-ul nu a putut fi verificat.', response.manual_allowed === true);
    }).always(function () {
      $('#cifClientLookupProgress').addClass('d-none');
      button.prop('disabled', false);
    });
  });

  $('#cifClientManualSaveButton').on('click', function () {
    var button = $(this);
    button.prop('disabled', true);
    postAction('save_manual').done(finishSave).fail(function (xhr) {
      showError((xhr.responseJSON && xhr.responseJSON.message) || 'CUI-ul nu a putut fi salvat manual.', true);
    }).always(function () { button.prop('disabled', false); });
  });
})();
</script>
