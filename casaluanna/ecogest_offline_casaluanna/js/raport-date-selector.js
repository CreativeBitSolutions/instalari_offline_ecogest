/**
 * Auto-discovery și inițializare automată pentru selectoare rapidă an/lună în modale rapoarte
 * 
 * Caută automat toate modalele care au selectoare de an/lună și le inițializează
 * Convenție de denumire:
 * - {prefix}_select_year
 * - {prefix}_select_month
 * - {prefix}_data_start / {prefix}_start_date / {prefix}_data_inceput
 * - {prefix}_data_end / {prefix}_end_date / {prefix}_data_sfarsit
 */

function raportDisplayDateToIso(value) {
    var match = String(value || '').trim().match(/^(\d{2})\.(\d{2})\.(\d{4})$/);
    if (!match) return '';

    var day = Number(match[1]);
    var month = Number(match[2]);
    var year = Number(match[3]);
    var testDate = new Date(Date.UTC(year, month - 1, day));
    if (testDate.getUTCFullYear() !== year || testDate.getUTCMonth() !== month - 1 || testDate.getUTCDate() !== day) {
        return '';
    }

    return String(year).padStart(4, '0') + '-' + String(month).padStart(2, '0') + '-' + String(day).padStart(2, '0');
}

function raportIsoDateToDisplay(value) {
    var match = String(value || '').trim().match(/^(\d{4})-(\d{2})-(\d{2})$/);
    return match ? match[3] + '.' + match[2] + '.' + match[1] : '';
}

function initReportCalendarButtons() {
    $('.report-calendar-button').each(function() {
        var button = $(this);
        if (button.data('calendar-initialized')) return;
        button.data('calendar-initialized', true);

        var picker = document.getElementById(button.attr('data-picker-target'));
        var dateInput = document.getElementById(button.attr('data-date-target'));
        if (!picker || !dateInput) return;

        button.on('click', function() {
            picker.value = raportDisplayDateToIso(dateInput.value);
            if (typeof picker.showPicker === 'function') {
                picker.showPicker();
            } else {
                picker.click();
            }
        });

        $(picker).on('change', function() {
            if (picker.value) {
                dateInput.value = raportIsoDateToDisplay(picker.value);
                $(dateInput).trigger('change');
            }
        });
    });
}

// Funcție de inițializare care poate fi apelată și după Select2
function initRaportDateSelectors() {
    // Găsește toate selectoarele de an care au pattern-ul "_select_year"
    $('select[id$="_select_year"]').each(function() {
        var yearSelect = $(this);
        var yearId = yearSelect.attr('id');
        
        // Verifică dacă deja a fost inițializat
        if (yearSelect.data('date-selector-initialized')) {
            return;
        }
        yearSelect.data('date-selector-initialized', true);
        
        // Extrage prefixul (tot ce e înainte de "_select_year")
        var prefix = yearId.replace('_select_year', '');
        
        // Construiește ID-urile pentru celelalte elemente
        var monthSelectId = prefix + '_select_month';
        var dateStartId = prefix + '_data_start';
        var dateEndId = prefix + '_data_end';
        
        // Variante alternative pentru câmpurile de dată
        var dateStartAlt1Id = prefix + '_start_date';
        var dateEndAlt1Id = prefix + '_end_date';
        var dateStartAlt2Id = prefix + '_data_inceput';
        var dateEndAlt2Id = prefix + '_data_sfarsit';
        var dateStartAlt3Id = prefix + '_date_from';
        var dateEndAlt3Id = prefix + '_date_to';
        
        // Verifică dacă elementele există
        var monthSelect = $('#' + monthSelectId);
        var dateStart = $('#' + dateStartId);
        var dateEnd = $('#' + dateEndId);
        
        // Încearcă variantele alternative dacă prima nu există
        if (dateStart.length === 0) {
            dateStart = $('#' + dateStartAlt1Id);
            dateStartId = dateStartAlt1Id;
        }
        if (dateStart.length === 0) {
            dateStart = $('#' + dateStartAlt2Id);
            dateStartId = dateStartAlt2Id;
        }
        if (dateStart.length === 0) {
            dateStart = $('#' + dateStartAlt3Id);
            dateStartId = dateStartAlt3Id;
        }
        if (dateEnd.length === 0) {
            dateEnd = $('#' + dateEndAlt1Id);
            dateEndId = dateEndAlt1Id;
        }
        if (dateEnd.length === 0) {
            dateEnd = $('#' + dateEndAlt2Id);
            dateEndId = dateEndAlt2Id;
        }
        if (dateEnd.length === 0) {
            dateEnd = $('#' + dateEndAlt3Id);
            dateEndId = dateEndAlt3Id;
        }
        
        // Dacă toate elementele necesare există, inițializează funcționalitatea
        if (monthSelect.length > 0 && dateStart.length > 0 && dateEnd.length > 0) {
            
            // Găsește modalul sau formularul părinte
            var modal = yearSelect.closest('.modal');
            var modalId = modal.attr('id');
            
            // Funcție pentru actualizarea datelor
            function updateDates() {
                var year = parseInt(yearSelect.val());
                var month = parseInt(monthSelect.val());
                
                if (isNaN(year) || isNaN(month)) {
                    return;
                }
                
                // Prima zi a lunii
                var firstDayStr = year + '-' + String(month).padStart(2, '0') + '-01';
                
                // Ultima zi a lunii
                var lastDay = new Date(year, month, 0);
                var lastDayStr = year + '-' + String(month).padStart(2, '0') + '-' + String(lastDay.getDate()).padStart(2, '0');

                if (dateStart.hasClass('report-date-input')) {
                    firstDayStr = '01.' + String(month).padStart(2, '0') + '.' + year;
                }
                if (dateEnd.hasClass('report-date-input')) {
                    lastDayStr = String(lastDay.getDate()).padStart(2, '0') + '.' + String(month).padStart(2, '0') + '.' + year;
                }
                
                dateStart.val(firstDayStr);
                dateEnd.val(lastDayStr);
            }
            
            // Event handlers pentru schimbarea anului sau lunii
            // Suport pentru select normal
            yearSelect.on('change', updateDates);
            monthSelect.on('change', updateDates);
            
            // Suport pentru Select2
            yearSelect.on('select2:select', updateDates);
            monthSelect.on('select2:select', updateDates);
            
            // Event handler pentru deschiderea modalului
            if (modalId) {
                $('#' + modalId).on('shown.bs.modal', function() {
                    updateDates();
                });
            }
            
            // Completare automată la încărcare (doar dacă câmpurile sunt goale)
            if (!dateStart.val() || dateStart.val() === '') {
                updateDates();
            }
        }
    });
}

// Inițializare la document ready
$(document).ready(function() {
    initRaportDateSelectors();
    initReportCalendarButtons();
    
    // Completare automată după un delay mai mic (pentru pagini non-modal)
    setTimeout(function() {
        $('select[id$="_select_year"]').each(function() {
            var yearId = $(this).attr('id');
            var prefix = yearId.replace('_select_year', '');
            
            // Verifică toate variantele posibile de ID-uri pentru date
            var dateStartId = prefix + '_data_start';
            var dateStartAlt1Id = prefix + '_start_date';
            var dateStartAlt2Id = prefix + '_data_inceput';
            var dateStartAlt3Id = prefix + '_date_from';
            
            var dateStart = $('#' + dateStartId);
            if (dateStart.length === 0) dateStart = $('#' + dateStartAlt1Id);
            if (dateStart.length === 0) dateStart = $('#' + dateStartAlt2Id);
            if (dateStart.length === 0) dateStart = $('#' + dateStartAlt3Id);
            
            // Completare automată doar dacă câmpul există și este gol
            if (dateStart.length > 0 && (!dateStart.val() || dateStart.val() === '')) {
                var monthSelect = $('#' + prefix + '_select_month');
                if (monthSelect.length > 0) {
                    var year = parseInt($(this).val());
                    var month = parseInt(monthSelect.val());
                    
                    if (!isNaN(year) && !isNaN(month)) {
                        var firstDayStr = year + '-' + String(month).padStart(2, '0') + '-01';
                        var lastDay = new Date(year, month, 0);
                        var lastDayStr = year + '-' + String(month).padStart(2, '0') + '-' + String(lastDay.getDate()).padStart(2, '0');

                        if (dateStart.hasClass('report-date-input')) {
                            firstDayStr = '01.' + String(month).padStart(2, '0') + '.' + year;
                        }
                        
                        dateStart.val(firstDayStr);
                        
                        // Găsește și completează data de sfârșit
                        var dateEndId = prefix + '_data_end';
                        var dateEndAlt1Id = prefix + '_end_date';
                        var dateEndAlt2Id = prefix + '_data_sfarsit';
                        var dateEndAlt3Id = prefix + '_date_to';
                        
                        var dateEnd = $('#' + dateEndId);
                        if (dateEnd.length === 0) dateEnd = $('#' + dateEndAlt1Id);
                        if (dateEnd.length === 0) dateEnd = $('#' + dateEndAlt2Id);
                        if (dateEnd.length === 0) dateEnd = $('#' + dateEndAlt3Id);
                        
                        if (dateEnd.length > 0) {
                            if (dateEnd.hasClass('report-date-input')) {
                                lastDayStr = String(lastDay.getDate()).padStart(2, '0') + '.' + String(month).padStart(2, '0') + '.' + year;
                            }
                            dateEnd.val(lastDayStr);
                        }
                    }
                }
            }
        });
    }, 100);
});

// Re-inițializare după un delay pentru a permite Select2 să se inițializeze
setTimeout(function() {
    initRaportDateSelectors();
}, 500);
