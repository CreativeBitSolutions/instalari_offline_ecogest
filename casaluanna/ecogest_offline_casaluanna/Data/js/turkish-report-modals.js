(function ($) {
  'use strict';

  var modalSelectors = [
    '#raportGestiuneMarfuriModal',
    '#raportGestiuneMarfuricuProdVandutaModal',
    '#raportGestiuneMateriiModal',
    '#raportGestiuneSgrModal',
    '#raportGestiuneambalajModal',
    '#raportGestiuneAmbalajeModal',
    '#raportGestiuneMarfuriProductieSGRModal',
    '#stocuriModal',
    '#raportProductieV2Modal'
  ];

  var directTurkishTargets = [
    'raport_gestiune_ambalaje_turkish.php',
    'raport_gestiune_marfuri_turkish.php',
    'raport_gestiune_marfuri_si_productie_turkish.php',
    'raport_gestiune_marfuri_si_productie_si_sgr_turkish.php',
    'raport_gestiune_materii_turkish.php',
    'raport_gestiune_sgr_turkish.php',
    'v2_raport_productie_turkish.php'
  ];

  function escapeHtml(value) {
    return String(value === null || value === undefined ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function findField($form, selector) {
    var $control = $form.find(selector).first();
    return $control.length ? $control.closest('.form-group') : $();
  }

  function dateControls($form) {
    return $form.find('input[type="date"]');
  }

  function timeControls($form) {
    return $form.find('input[type="time"]');
  }

  function filterSignature($form) {
    return JSON.stringify({
      mode: $form.find('[name="mod_filtrare"]').val(),
      dataStart: dateControls($form).eq(0).val(),
      dataEnd: dateControls($form).eq(1).val(),
      oraStart: timeControls($form).eq(0).val(),
      oraEnd: timeControls($form).eq(1).val(),
      nrStart: $form.find('[name="nr_z_start"]').val(),
      nrEnd: $form.find('[name="nr_z_end"]').val(),
      locatie: $form.find('[name="locatie"]').val()
    });
  }

  function filterRequestData($form, modeOverride) {
    var $dates = dateControls($form);
    var $times = timeControls($form);
    return {
      inspect: 1,
      mod_filtrare: modeOverride || $form.find('[name="mod_filtrare"]').val() || 'raport_z',
      data_start: $dates.eq(0).val(),
      data_end: $dates.eq(1).val(),
      ora_start: $times.eq(0).val() || '00:00',
      ora_end: $times.eq(1).val() || '23:59',
      nr_z_start: $form.find('[name="nr_z_start"]').val(),
      nr_z_end: $form.find('[name="nr_z_end"]').val(),
      locatie: $form.find('[name="locatie"]').val() || 'toate'
    };
  }

  function renderMissingZWarning($form, warning) {
    var $warning = $form.find('.turkish-missing-z-warning');
    var count = Number(warning && warning.count ? warning.count : 0);
    if (!count) {
      $warning.stop(true, true).slideUp(120).empty();
      return;
    }

    var interval = '';
    if (warning.first_moment && warning.last_moment) {
      interval = '<small>Între ' + escapeHtml(warning.first_moment) + ' și ' + escapeHtml(warning.last_moment) + '.</small>';
    }
    $warning.html(
      '<i class="fas fa-exclamation-triangle"></i>'
      + '<div><strong>Atenție, ' + count + ' note finalizate nu au număr de raport Z.</strong>'
      + '<span>Aceste note vor fi încadrate după data și ora documentului.</span>'
      + interval + '</div>'
    ).stop(true, true).slideDown(140);
  }

  function renderFilterStatus($form, response) {
    var $status = $form.find('.turkish-filter-status');
    var mode = $form.find('[name="mod_filtrare"]').val();
    var first = response.first_z;
    var last = response.last_z;

    if (mode === 'raport_z') {
      $status.html(
        '<i class="fas fa-check-circle"></i><div><strong>Interval validat: Z '
        + escapeHtml(first.nr_raport_z) + ' până la Z ' + escapeHtml(last.nr_raport_z) + '</strong>'
        + '<span>Perioada fiscală rezultată: ' + escapeHtml(response.resolved_data_start)
        + ' până la ' + escapeHtml(response.resolved_data_end) + '.</span></div>'
      ).addClass('is-valid').removeClass('is-error').show();
    } else if (first && last) {
      $status.html(
        '<i class="fas fa-receipt"></i><div><strong>În interval au fost identificate Z '
        + escapeHtml(first.nr_raport_z) + ' până la Z ' + escapeHtml(last.nr_raport_z) + '.</strong>'
        + '<span>Raportul rămâne filtrat strict după data și ora introduse.</span></div>'
      ).addClass('is-valid').removeClass('is-error').show();
    } else {
      $status.html(
        '<i class="fas fa-info-circle"></i><div><strong>Nu există închideri Z în interval.</strong>'
        + '<span>Raportul va folosi numai data și ora documentelor.</span></div>'
      ).addClass('is-valid').removeClass('is-error').show();
    }
  }

  function renderFilterError($form, message) {
    $form.find('.turkish-filter-status').html(
      '<i class="fas fa-times-circle"></i><div><strong>Intervalul nu poate fi folosit.</strong>'
      + '<span>' + escapeHtml(message || 'Verifică valorile introduse.') + '</span></div>'
    ).removeClass('is-valid').addClass('is-error').show();
  }

  function applyResolvedPeriod($form, response) {
    var $dates = dateControls($form);
    if (response.resolved_data_start) {
      $dates.eq(0).val(response.resolved_data_start).trigger('change.select2');
    }
    if (response.resolved_data_end) {
      $dates.eq(1).val(response.resolved_data_end).trigger('change.select2');
    }
  }

  function inspectFilter($form, options) {
    options = options || {};
    var modeOverride = options.modeOverride || null;
    var data = filterRequestData($form, modeOverride);
    if (options.identifyZ) {
      data.identify_z = 1;
    }
    var $buttons = $form.find('.turkish-filter-check, .turkish-identify-z');
    var $status = $form.find('.turkish-filter-status');

    if (!data.data_start || !data.data_end) {
      renderFilterError($form, 'Completează data de început și data de sfârșit.');
      return $.Deferred().reject().promise();
    }
    if (data.mod_filtrare === 'raport_z' && (!data.nr_z_start || !data.nr_z_end)) {
      renderFilterError($form, 'Completează primul și ultimul număr Z.');
      return $.Deferred().reject().promise();
    }

    $buttons.prop('disabled', true);
    $status.removeClass('is-valid is-error').html(
      '<i class="fas fa-circle-notch fa-spin"></i><div><strong>Verific intervalul</strong>'
      + '<span>Se citesc numai limitele necesare.</span></div>'
    ).show();

    return $.ajax({
      url: 'ajax_lista_rapoarte_z_turkish.php',
      method: 'GET',
      dataType: 'json',
      data: data
    }).done(function (response) {
      if (!response || !response.ok) {
        renderFilterError($form, response && response.message ? response.message : 'Verificarea nu a putut fi efectuată.');
        return;
      }

      if (options.identifyZ) {
        if (!response.first_z || !response.last_z) {
          renderFilterError($form, 'Nu există rapoarte Z în intervalul de dată și oră selectat.');
          return;
        }
        $form.find('[name="nr_z_start"]').val(response.first_z.nr_raport_z);
        $form.find('[name="nr_z_end"]').val(response.last_z.nr_raport_z);
        $form.find('.turkish-z-date-helper').slideUp(140);
        applyResolvedPeriod($form, response);
        $form.data('turkishFilterVerified', filterSignature($form));
        renderFilterStatus($form, response);
        renderMissingZWarning($form, response.missing_z_notes || {});
        return;
      }

      if ($form.find('[name="mod_filtrare"]').val() === 'raport_z') {
        applyResolvedPeriod($form, response);
      }
      $form.data('turkishFilterVerified', filterSignature($form));
      renderFilterStatus($form, response);
      renderMissingZWarning($form, response.missing_z_notes || {});
    }).fail(function () {
      renderFilterError($form, 'Serverul nu a putut verifica intervalul. Încearcă din nou.');
    }).always(function () {
      $buttons.prop('disabled', false);
    });
  }

  function setFilterMode($form, mode) {
    var isZ = mode === 'raport_z';
    var $mode = $form.find('[name="mod_filtrare"]');
    var $timeFilter = $form.find('[name="use_time_filter"]');

    $mode.val(isZ ? 'raport_z' : 'ora');
    $timeFilter.prop('checked', !isZ).val('1');
    dateControls($form).add(timeControls($form)).prop('disabled', false);
    $form.find('.turkish-filter-mode').attr('aria-pressed', 'false').removeClass('is-active');
    $form.find('.turkish-filter-mode[data-mode="' + (isZ ? 'raport_z' : 'ora') + '"]')
      .attr('aria-pressed', 'true').addClass('is-active');
    $form.find('.turkish-z-mode-fields').toggle(isZ);
    $form.find('.turkish-date-mode-fields').toggle(!isZ);
    $form.find('.turkish-calendar-fields').appendTo(
      isZ ? $form.find('.turkish-z-date-helper') : $form.find('.turkish-date-mode-fields')
    );
    if (!isZ) {
      $form.find('.turkish-z-date-helper').show();
    } else {
      $form.find('.turkish-z-date-helper').hide();
    }
    $form.removeData('turkishFilterVerified');
    $form.find('.turkish-filter-status').hide().empty();
    $form.find('.turkish-missing-z-warning').hide().empty();
  }

  function buildFilterControl($form, index) {
    var $dates = dateControls($form);
    var $times = timeControls($form);
    var $mode = $form.find('[name="mod_filtrare"]').first();
    var $timeFilter = $form.find('[name="use_time_filter"]').first();

    if (!$mode.length) {
      $mode = $('<input type="hidden" name="mod_filtrare" value="raport_z">').appendTo($form);
    } else {
      var $modeGroup = $mode.closest('.form-group');
      $mode.detach().removeAttr('id').hide().appendTo($form);
      $modeGroup.remove();
    }

    if (!$timeFilter.length) {
      $timeFilter = $('<input type="checkbox" name="use_time_filter" value="1" hidden>').appendTo($form);
    } else {
      var $timeFilterGroup = $timeFilter.closest('.form-group');
      $timeFilter.detach().hide().appendTo($form);
      if ($timeFilterGroup.length) {
        $timeFilterGroup.remove();
      }
    }

    var $calendar = $('<div class="turkish-calendar-fields"></div>');
    var $month = $form.children('.report-month-picker-wrapper').first();
    if ($month.length) {
      $calendar.append($month);
    }
    $dates.each(function () {
      var $group = $(this).closest('.form-group');
      if ($group.length) {
        $calendar.append($group);
      }
    });
    $times.each(function () {
      var $group = $(this).closest('.form-group');
      if ($group.length) {
        $group.addClass('turkish-time-field');
        $calendar.append($group);
      }
    });

    var startId = 'turkishNrZStart' + index;
    var endId = 'turkishNrZEnd' + index;
    var $control = $(
      '<section class="turkish-filter-card" aria-label="Alegerea perioadei">'
        + '<div class="turkish-filter-heading"><div><span class="turkish-filter-kicker">PERIOADA RAPORTULUI</span>'
        + '<h6>Alege reperul contabil</h6></div><i class="fas fa-cash-register"></i></div>'
        + '<div class="turkish-filter-switch" role="group" aria-label="Mod de filtrare">'
          + '<button type="button" class="turkish-filter-mode is-active" data-mode="raport_z" aria-pressed="true">'
            + '<i class="fas fa-receipt"></i><span><strong>După rapoarte Z</strong><small>Introdu primul și ultimul Z</small></span></button>'
          + '<button type="button" class="turkish-filter-mode" data-mode="ora" aria-pressed="false">'
            + '<i class="far fa-clock"></i><span><strong>După dată și oră</strong><small>Interval calendaristic exact</small></span></button>'
        + '</div>'
        + '<div class="turkish-z-mode-fields">'
          + '<div class="turkish-z-number-grid">'
            + '<div class="form-group"><label for="' + startId + '">Număr Z început</label>'
              + '<div class="turkish-z-input"><span>Z</span><input id="' + startId + '" name="nr_z_start" type="number" min="1" step="1" class="form-control" inputmode="numeric"></div></div>'
            + '<div class="form-group"><label for="' + endId + '">Număr Z sfârșit</label>'
              + '<div class="turkish-z-input"><span>Z</span><input id="' + endId + '" name="nr_z_end" type="number" min="1" step="1" class="form-control" inputmode="numeric"></div></div>'
          + '</div>'
          + '<div class="turkish-filter-actions"><button type="button" class="turkish-filter-check"><i class="fas fa-check mr-1"></i>Verifică intervalul Z</button>'
            + '<button type="button" class="turkish-z-helper-toggle"><i class="far fa-calendar-alt mr-1"></i>Identifică Z-urile după dată și oră</button></div>'
          + '<div class="turkish-z-date-helper"><div class="turkish-helper-copy"><strong>Zi fiscală: 04:00 până la 03:59 în ziua următoare.</strong> Alege datele fiscale. Primul și ultimul Z vor fi completate automat, inclusiv închiderea scoasă noaptea după ora 00:00.</div>'
            + '<button type="button" class="turkish-identify-z"><i class="fas fa-search mr-1"></i>Identifică primul și ultimul Z</button></div>'
        + '</div>'
        + '<div class="turkish-date-mode-fields"></div>'
        + '<div class="turkish-filter-status" role="status"></div>'
        + '<div class="turkish-missing-z-warning" role="alert"></div>'
      + '</section>'
    );

    $form.prepend($control);
    $calendar.appendTo($control.find('.turkish-z-date-helper'));

    $control.on('click', '.turkish-filter-mode', function () {
      setFilterMode($form, $(this).data('mode'));
    });
    $control.on('click', '.turkish-z-helper-toggle', function () {
      $control.find('.turkish-z-date-helper').stop(true, true).slideToggle(150);
    });
    $control.on('click', '.turkish-filter-check', function () {
      inspectFilter($form);
    });
    $control.on('click', '.turkish-identify-z', function () {
      inspectFilter($form, {modeOverride: 'ora', identifyZ: true});
    });
    $control.on('input change', 'input, select', function () {
      $form.removeData('turkishFilterVerified');
      $control.find('.turkish-filter-status').hide().empty();
      $control.find('.turkish-missing-z-warning').hide().empty();
    });

    setFilterMode($form, 'raport_z');
    return $control;
  }

  function simplifyModal(modalSelector, index) {
    var $modal = $(modalSelector);
    var $form = $modal.find('form').first();
    if (!$modal.length || !$form.length || $form.data('turkishSimplified')) {
      return;
    }

    $modal.addClass('turkish-report-modal');
    $form.addClass('turkish-report-form').data('turkishSimplified', true);

    var title = $.trim($modal.find('.modal-title').first().text());
    $modal.find('.modal-title').first().text(title.replace(/,?\s*perioadă fiscală/gi, ''));

    var $filter = buildFilterControl($form, index);
    var $simple = $('<div class="turkish-simple-fields"></div>');
    var $advanced = $('<div class="turkish-advanced-fields"></div>');
    var advancedId = 'turkishAdvancedFields' + index;
    $advanced.attr('id', advancedId);
    var $toggle = $('<button type="button" class="turkish-advanced-toggle" aria-expanded="false" aria-controls="' + advancedId + '"><span><i class="fas fa-sliders-h mr-2"></i>Opțiuni avansate</span><i class="fas fa-chevron-down"></i></button>');
    var $gestion = findField($form, '[name="gst"]');
    var $vat = findField($form, '[name="cota_tva"]');
    var $submit = $form.find('button[type="submit"], input[type="submit"]').last();

    $filter.after($simple);
    if ($gestion.length) {
      $gestion.addClass('turkish-wide-field').appendTo($simple);
    }
    if ($vat.length) {
      $vat.addClass('turkish-wide-field').appendTo($simple);
    }

    $form.children().each(function () {
      var $child = $(this);
      if ($child.is($filter) || $child.is($simple) || $child.is($submit) || $child.is('input, select[name="mod_filtrare"]')) {
        return;
      }
      if (!$child.text().trim() && !$child.find('input, select, button, textarea').length) {
        $child.remove();
        return;
      }
      $advanced.append($child);
    });

    $simple.after($toggle, $advanced);
    if ($submit.length) {
      $submit.addClass('mt-2').appendTo($form);
    }

    $toggle.on('click', function () {
      var expanded = $toggle.attr('aria-expanded') === 'true';
      $toggle.attr('aria-expanded', expanded ? 'false' : 'true');
      $advanced.stop(true, true).slideToggle(160);
    });

    $form.on('submit.turkishFilterValidation', function (event) {
      if ($form.data('turkishFilterVerified') === filterSignature($form)) {
        return;
      }
      event.preventDefault();
      event.stopImmediatePropagation();
      inspectFilter($form).done(function (response) {
        if (response && response.ok && $form.data('turkishFilterVerified') === filterSignature($form)) {
          window.setTimeout(function () { $form.trigger('submit'); }, 0);
        }
      });
    });

    $modal.on('shown.bs.modal', function () {
      var $nrStart = $form.find('[name="nr_z_start"]');
      var $nrEnd = $form.find('[name="nr_z_end"]');
      if (!$nrStart.val() && !$nrEnd.val() && !$form.data('turkishAutoIdentified')) {
        $form.data('turkishAutoIdentified', true);
        inspectFilter($form, {modeOverride: 'ora', identifyZ: true});
      }
    });
  }

  function showLaunchNotice() {
    var $notice = $('#turkishReportLaunchNotice');
    if (!$notice.length) {
      $notice = $(
        '<div id="turkishReportLaunchNotice" class="turkish-launch-notice" role="status">'
          + '<span class="turkish-launch-icon"><i class="fas fa-file-invoice"></i></span>'
          + '<span><strong>Raportul se deschide într-o filă nouă</strong>'
          + '<small>Poți continua lucrul în această pagină.</small></span>'
        + '</div>'
      ).appendTo(document.body);
    }
    $notice.addClass('is-visible');
    window.clearTimeout($notice.data('hideTimer'));
    $notice.data('hideTimer', window.setTimeout(function () {
      $notice.removeClass('is-visible');
    }, 3600));
  }

  function appendDispatchField(form, name, value) {
    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value === null || value === undefined ? '' : String(value);
    form.appendChild(input);
  }

  function dispatchTurkishReport(event) {
    var form = event.currentTarget;
    var $form = $(form);
    var actionUrl;
    try {
      actionUrl = new URL(form.action, window.location.href);
    } catch (error) {
      return;
    }

    var targetScript = actionUrl.pathname.split('/').pop();
    var isStockCalculator = targetScript === 'calcul_situatia_stocurilor.php';
    var isDirectTurkish = directTurkishTargets.indexOf(targetScript) !== -1;
    if (!isStockCalculator && !isDirectTurkish) {
      return;
    }
    if ($form.data('turkishDispatching')) {
      event.preventDefault();
      return;
    }

    $form.data('turkishDispatching', true);
    var $submit = $form.find('button[type="submit"], input[type="submit"]').last();
    $submit.prop('disabled', true).addClass('turkish-submit-busy');
    showLaunchNotice();

    if (isStockCalculator) {
      window.setTimeout(function () {
        $form.data('turkishDispatching', false);
        $submit.prop('disabled', false).removeClass('turkish-submit-busy');
      }, 12000);
      return;
    }

    event.preventDefault();
    var windowName = 'turkishReport_' + Date.now() + '_' + Math.floor(Math.random() * 10000);
    var reportWindow = window.open('', windowName);
    var dispatchForm = document.createElement('form');
    dispatchForm.method = 'post';
    dispatchForm.action = 'turkish_report_dispatch.php';
    dispatchForm.target = reportWindow ? windowName : '_blank';
    dispatchForm.style.display = 'none';

    appendDispatchField(dispatchForm, '_turkish_target', targetScript);
    appendDispatchField(dispatchForm, '_turkish_method', (form.method || 'GET').toUpperCase());
    new FormData(form).forEach(function (value, name) {
      if (typeof value === 'string') {
        appendDispatchField(dispatchForm, name, value);
      }
    });

    document.body.appendChild(dispatchForm);
    dispatchForm.submit();
    dispatchForm.remove();
    window.setTimeout(function () {
      $form.data('turkishDispatching', false);
      $submit.prop('disabled', false).removeClass('turkish-submit-busy');
    }, 12000);
  }

  $(function () {
    modalSelectors.forEach(simplifyModal);
    $(document).on('submit.turkishReportDispatch', 'form', dispatchTurkishReport);
  });
})(jQuery);
