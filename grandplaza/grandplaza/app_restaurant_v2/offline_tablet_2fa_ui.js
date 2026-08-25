(function () {
  'use strict';

  function createTwoFaBlock(context) {
    if (document.getElementById('offlineTablet2faBlock')) {
      return null;
    }

    var wrap = document.createElement('div');
    wrap.id = 'offlineTablet2faBlock';
    wrap.setAttribute('data-context', context);

    if (context === 'import') {
      wrap.className = 'mt-2 d-flex flex-wrap align-items-center';
      wrap.style.gap = '8px';
      wrap.innerHTML =
        '<strong>Cod 2FA tabletă:</strong> ' +
        '<span id="offlineTablet2faCode" class="badge badge-light" style="font-size:1rem;letter-spacing:.08em;">N/A</span> ' +
        '<button type="button" id="offlineTablet2faRegenerate" class="btn btn-sm btn-outline-light">Generează cod nou</button>';
    } else {
      wrap.className = 'mb-3 p-2 border rounded';
      wrap.style.background = '#f8f9fa';
      wrap.innerHTML =
        '<div class="d-flex flex-wrap align-items-center" style="gap:8px;">' +
          '<strong>Cod 2FA tabletă:</strong> ' +
          '<span id="offlineTablet2faCode" class="badge badge-primary" style="font-size:1rem;letter-spacing:.08em;">N/A</span> ' +
          '<button type="button" id="offlineTablet2faRegenerate" class="btn btn-sm btn-outline-primary">Generează cod nou</button>' +
        '</div>';
    }

    return wrap;
  }

  function placeTwoFaBlock() {
    var importHeader = document.querySelector('.page-head .container-fluid > div:first-child');
    if (importHeader && document.querySelector('.page-head h1')) {
      var importBlock = createTwoFaBlock('import');
      if (importBlock) {
        importHeader.appendChild(importBlock);
      }
      return true;
    }

    var notesHeading = document.querySelector('#setare_masa .modal-body h5.mb-2');
    if (!notesHeading) {
      var headings = document.querySelectorAll('#setare_masa h5');
      for (var i = 0; i < headings.length; i++) {
        if ((headings[i].textContent || '').indexOf('Notele operatorului') !== -1) {
          notesHeading = headings[i];
          break;
        }
      }
    }

    if (notesHeading && notesHeading.parentNode) {
      var tableBlock = createTwoFaBlock('table-selection');
      if (tableBlock) {
        notesHeading.parentNode.insertBefore(tableBlock, notesHeading.nextSibling);
      }
      return true;
    }

    return false;
  }

  function setBusy(busy) {
    var button = document.getElementById('offlineTablet2faRegenerate');
    if (!button) {
      return;
    }

    if (busy) {
      button.setAttribute('disabled', 'disabled');
      button.setAttribute('data-label', button.textContent || 'Generează cod nou');
      button.textContent = 'Se generează...';
    } else {
      button.removeAttribute('disabled');
      button.textContent = button.getAttribute('data-label') || 'Generează cod nou';
    }
  }

  function renderCode(response) {
    var code = document.getElementById('offlineTablet2faCode');
    if (!code) {
      return;
    }

    if (response && response.status === 'success' && response.active !== false && response.code) {
      code.textContent = String(response.code);
    } else {
      code.textContent = 'N/A';
    }
  }

  function requestTwoFa(action, showError) {
    var body = 'action=' + encodeURIComponent(action);
    if (action === 'regenerate') {
      setBusy(true);
    }

    fetch('vanzare_tableta_2fa_api.php', {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'Accept': 'application/json'
      },
      body: body
    })
      .then(function (response) {
        return response.json().catch(function () {
          return { status: 'error', message: 'Răspuns invalid de la serviciul 2FA.' };
        }).then(function (payload) {
          if (!response.ok) {
            var error = new Error(payload.message || ('HTTP ' + response.status));
            error.payload = payload;
            throw error;
          }
          return payload;
        });
      })
      .then(function (payload) {
        if (!payload || payload.status !== 'success') {
          throw new Error(payload && payload.message ? payload.message : 'Nu s-a putut obține codul 2FA.');
        }
        renderCode(payload);
      })
      .catch(function (error) {
        if (showError) {
          window.alert(error && error.message ? error.message : 'Nu s-a putut genera codul 2FA.');
        }
      })
      .then(function () {
        if (action === 'regenerate') {
          setBusy(false);
        }
      });
  }

  function init() {
    if (!placeTwoFaBlock()) {
      return;
    }

    var button = document.getElementById('offlineTablet2faRegenerate');
    if (button) {
      button.addEventListener('click', function () {
        requestTwoFa('regenerate', true);
      });
    }

    requestTwoFa('status', false);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
