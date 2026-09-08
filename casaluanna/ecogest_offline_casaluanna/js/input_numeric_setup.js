$(document).ready(function() {
    $('input.virtual_keyboard_numeric').each(function() {
        var $input = $(this);
        
        // Adaugă atributele dacă nu sunt deja prezente
        if (!$input.attr('pattern')) {
            $input.attr('pattern', '^[0-9]+(\\.[0-9]{1,2})?$');
        }
        if (!$input.attr('title')) {
            $input.attr('title', 'Introduceți doar numere și punct pentru zecimale (ex: 123.45)');
        }
        if (!$input.attr('inputmode')) {
            $input.attr('inputmode', 'decimal');
        }

        // Verifică dacă mesajul informativ este deja inserat pentru a evita duplicatele
        if ($input.prev('.input-info').length === 0) {
            var infoMessage = $('<small class="form-text text-warning font-weight-bold input-info"><i class="fas fa-info-circle"></i> Doar numere și punct sunt acceptate (ex: 123.45).</small>');
            $input.before(infoMessage);
        }
    });
});
