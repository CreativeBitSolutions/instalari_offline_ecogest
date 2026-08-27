<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Verifică stoc online</title>
  <link href="vendor/offline/select2/select2.min.css" rel="stylesheet">
  <style>
    * { box-sizing: border-box; }
    body {
      margin: 0;
      padding: 24px;
      color: #26313d;
      background: #f4f7f9;
      font-family: Arial, sans-serif;
    }
    .stock-shell { max-width: 760px; margin: 0 auto; }
    .source-notice {
      display: flex;
      gap: 12px;
      align-items: flex-start;
      margin-bottom: 18px;
      padding: 14px 16px;
      border-left: 4px solid #23875b;
      background: #eaf6f0;
      color: #285844;
      line-height: 1.45;
    }
    .source-notice strong { display: block; margin-bottom: 2px; color: #184b35; }
    .field-label { display: block; margin-bottom: 8px; font-size: 14px; font-weight: 700; }
    .select2-container .select2-selection--single {
      height: 44px;
      border: 1px solid #b8c4cf;
      border-radius: 6px;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
      line-height: 42px;
      padding-left: 14px;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 42px; }
    .stock-action {
      width: 100%;
      min-height: 46px;
      margin-top: 12px;
      border: 0;
      border-radius: 6px;
      background: #1976d2;
      color: #fff;
      font-size: 15px;
      font-weight: 700;
      cursor: pointer;
    }
    .stock-action:hover:not(:disabled) { background: #125ea9; }
    .stock-action:disabled { background: #aeb9c3; cursor: not-allowed; }
    .result {
      display: none;
      margin-top: 18px;
      padding: 18px;
      border: 1px solid #d5dde4;
      border-radius: 6px;
      background: #fff;
    }
    .result.visible { display: block; }
    .result.loading { border-color: #8bb7e0; background: #f3f8fc; color: #315d83; }
    .result.error { border-color: #e1aaa5; background: #fff1ef; color: #8b2f29; }
    .stock-value { margin: 4px 0 8px; color: #1d6f4c; font-size: 32px; font-weight: 700; }
    .stock-product { font-size: 17px; font-weight: 700; }
    .stock-meta { color: #66727e; font-size: 13px; line-height: 1.55; }
    @media (max-width: 600px) {
      body { padding: 16px; }
      .stock-value { font-size: 27px; }
    }
  </style>
</head>
<body>
  <main class="stock-shell">
    <div class="source-notice">
      <span aria-hidden="true">●</span>
      <div>
        <strong>Stoc calculat din baza online</strong>
        Produsul este căutat în nomenclatorul local actualizat automat. Stocul se solicită online numai la apăsarea butonului și folosește exclusiv mișcările din baza online.
      </div>
    </div>
    <label class="field-label" for="verificaStocSelect">Caută produsul</label>
    <select id="verificaStocSelect" style="width: 100%;"></select>
    <button type="button" id="afiseazaStoc" class="stock-action" disabled>AFIȘEAZĂ STOC</button>
    <div id="stocRezultat" class="result" role="status" aria-live="polite"></div>
  </main>

  <script src="vendor/jquery/jquery.min.js"></script>
  <script src="vendor/offline/select2/select2.min.js"></script>
  <script>
    $(function () {
      var $result = $('#stocRezultat');
      var $showStock = $('#afiseazaStoc');
      var selectedProductId = null;
      var selectedProductName = '';
      var selectedProductUnit = '';

      function messageFromXhr(xhr, fallback) {
        if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
          return xhr.responseJSON.message;
        }
        return fallback;
      }

      function showState(type, html) {
        $result.removeClass('loading error').addClass('visible');
        if (type) {
          $result.addClass(type);
        }
        $result.html(html);
      }

      $('#verificaStocSelect').select2({
        placeholder: 'Introdu cel puțin 2 caractere...',
        minimumInputLength: 2,
        language: {
          inputTooShort: function () { return 'Introdu cel puțin 2 caractere.'; },
          searching: function () { return 'Se caută în nomenclatorul local...'; },
          noResults: function () { return 'Nu au fost găsite produse.'; },
          errorLoading: function () { return 'Nomenclatorul local nu a putut fi citit.'; }
        },
        ajax: {
          url: 'verifica_stoc_cauta_produs.php',
          dataType: 'json',
          delay: 300,
          cache: false,
          data: function (params) {
            return { q: params.term };
          },
          processResults: function (data) {
            var rows = data && data.results ? data.results : [];
            return {
              results: rows.map(function (item) {
                return {
                  id: item.cod_produs,
                  text: item.nume + ' (cod ' + item.cod_produs + ')',
                  productName: item.nume,
                  unit: item.um || ''
                };
              })
            };
          },
          error: function (xhr) {
            showState('error', messageFromXhr(xhr, 'Nomenclatorul local nu a putut fi citit.'));
          }
        }
      }).on('select2:select', function (event) {
        selectedProductId = event.params.data.id;
        selectedProductName = event.params.data.productName || event.params.data.text || '';
        selectedProductUnit = event.params.data.unit || '';
        $showStock.prop('disabled', false);
        $result.removeClass('visible loading error').empty();
      }).on('select2:clear', function () {
        selectedProductId = null;
        selectedProductName = '';
        selectedProductUnit = '';
        $showStock.prop('disabled', true);
        $result.removeClass('visible loading error').empty();
      });

      $showStock.on('click', function () {
        if (!selectedProductId) {
          return;
        }

        var requestStartedAt = Date.now();
        $showStock.prop('disabled', true);
        showState('loading', 'Se calculează stocul din mișcările online...');

        $.ajax({
          url: 'verifica_stoc_calcul.php',
          dataType: 'json',
          cache: false,
          timeout: 10000,
          data: { cod_produs: selectedProductId }
        }).done(function (response) {
          var value = Number(response.final_stock || 0).toLocaleString('ro-RO', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 3
          });
          var unit = selectedProductUnit ? ' ' + selectedProductUnit : '';
          var locations = (response.included_locations || [response.cod_locatie]).join(', ');
          var totalMs = Date.now() - requestStartedAt;
          var timing = ' · SQL: ' + Number(response.query_ms || 0) + ' ms' +
            ' · Răspuns total: ' + totalMs + ' ms';
          showState('',
            '<div class="stock-product">' + $('<div>').text(selectedProductName).html() + '</div>' +
            '<div class="stock-value">' + value + unit + '</div>' +
            '<div class="stock-meta">Sursă: baza online · Locații incluse: ' + locations +
            ' · Calculat la: ' + $('<div>').text(response.calculated_at || '').html() + timing + '</div>'
          );
        }).fail(function (xhr) {
          showState('error', messageFromXhr(xhr, 'Stocul online nu a putut fi calculat. Verifică accesul la internet.'));
        }).always(function () {
          $showStock.prop('disabled', !selectedProductId);
        });
      });
    });
  </script>
</body>
</html>
