$(document).ready(function() {
    // Verifică setarea mod_touch de pe server
    $.ajax({
        url: 'get_mod_touch.php',
        dataType: 'json',
        success: function(response) {
            // Dacă mod_touch este 1, initializează tastatura virtuală
            if (response.mod_touch && response.mod_touch == 1) {
                console.log("Tastatura Virtuala se va afisa.");

                $('input.virtual-keyboard').each(function(){
                    var $input = $(this);
                    $input.keyboard({
                        layout: 'qwerty',
                        usePreview: false,
                        autoAccept: true,
                        position: false, // Dezactivează poziționarea automată
                        reposition: true,
                        css: {
                            // Clase CSS personalizate pentru stilizare
                            input: 'virtual-keyboard-input',
                            container: 'virtual-keyboard-container',
                            buttonDefault: 'virtual-keyboard-button',
                            buttonHover: 'virtual-keyboard-button-hover',
                            buttonAction: 'virtual-keyboard-action-button',
                            buttonDisabled: 'virtual-keyboard-disabled-button'
                        },
                        beforeVisible: function(e, keyboard, el) {
                            // Ascunde tastatura până când este poziționată corect
                            keyboard.$keyboard.css('display', 'none');
                            // Recalculează poziția tastaturii
                            var inputOffset = $input.offset();
                            var inputHeight = $input.outerHeight();
                            keyboard.$keyboard.css({
                                position: 'absolute',
                                top: inputOffset.top + inputHeight,
                                left: inputOffset.left,
                                zIndex: 1000,
                                display: 'block' // Afișează tastatura după poziționare
                            });
                        }
                    });
                });
                // Inițializează atributele și mesajele informative pentru câmpurile numerice ATENTIE , inputul trebuie sa fie de tip NUMERIC.
                $('input.virtual_keyboard_numeric').each(function() {
                    var $input = $(this);
                    
                    if (!$input.attr('pattern')) {
                        $input.attr('pattern', '^[0-9]+(\\.[0-9]{1,2})?$');
                    }
                    if (!$input.attr('title')) {
                        $input.attr('title', 'Introduceți doar numere și punct pentru zecimale (ex: 123.45)');
                    }
                    if (!$input.attr('inputmode')) {
                        $input.attr('inputmode', 'decimal');
                    }
                    
                    if ($input.prev('.input-info').length === 0) {
                        var infoMessage = $('<small class="form-text text-warning font-weight-bold input-info"><i class="fas fa-info-circle"></i> Doar numere și punct sunt acceptate (ex: 123.45).</small>');
                        $input.before(infoMessage);
                    }
                });
            } else {
                console.log("Tastatura Virtuala nu se va afisa.");
            }
        },
        error: function() {
            console.log("Nu s-a putut obține setarea mod_touch.");
        }
    });
});
