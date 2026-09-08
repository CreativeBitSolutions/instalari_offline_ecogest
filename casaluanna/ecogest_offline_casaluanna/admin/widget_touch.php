<style>
    /* === 1. VARIABILE CSS PENTRU CUSTOMIZARE UȘOARĂ === */
    :root {
        --pos-primary-color: #4e73df;
        --pos-primary-hover: #2e59d9;
        --pos-success-color: #1cc88a; /* Culoare pentru butonul Enter/Accept */
        --pos-bg-color: #f1f5f9;      /* Fundalul tastaturii */
        --pos-key-bg: #ffffff;        /* Fundalul tastelor */
        --pos-key-border: #cbd5e1;
        --pos-text-color: #334155;
        --pos-btn-size: 65px;
        --pos-z-index-base: 9900;
    }

    /* === 2. STILURI BUTOANE PLUTITOARE === */
    .pos-floating-controls {
        position: fixed;
        bottom: 40px;
        right: 30px;
        z-index: var(--pos-z-index-base);
        display: flex;
        flex-direction: column;
        gap: 15px;
        pointer-events: none;
    }

    .pos-floating-btn {
        width: var(--pos-btn-size);
        height: var(--pos-btn-size);
        border-radius: 50%;
        background-color: var(--pos-primary-color);
        color: white;
        border: 2px solid rgba(255,255,255,0.2);
        box-shadow: 0px 6px 15px rgba(0, 0, 0, 0.3);
        font-size: 26px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        pointer-events: auto;
        user-select: none;
        outline: none;
        -webkit-tap-highlight-color: transparent;
        
        /* FIX PENTRU TOUCH POS: Elimină delay-ul de 300ms la apăsare */
        touch-action: manipulation; 
    }

    .pos-floating-btn:active {
        transform: scale(0.95);
    }

    /* === 3. FUNDALUL MODALULUI (BACKDROP) === */
    #pos-keyboard-backdrop {
        display: none;
        position: fixed;
        top: 0; left: 0; width: 100vw; height: 100vh;
        background: rgba(15, 23, 42, 0.85);
        backdrop-filter: blur(5px);
        -webkit-backdrop-filter: blur(5px);
        z-index: calc(var(--pos-z-index-base) + 50);
        cursor: pointer;
    }

    /* === 4. UI TASTATURĂ - SUPRASCRIERE TOTALĂ MOTTIE === */
    .ui-keyboard {
        position: fixed !important;
        top: 50% !important;
        left: 50% !important;
        transform: translate(-50%, -50%) !important;
        z-index: calc(var(--pos-z-index-base) + 100) !important;
        
        background-color: var(--pos-bg-color) !important;
        border-radius: 16px !important;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5) !important;
        border: none !important;
        padding: 20px !important;
        width: 90% !important; /* Lățime optimă pentru Desktop/POS */
        max-width: 95vw !important;
        font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif !important;
    }

    /* Header-ul injectat dinamic (Titlu + Buton X) */
    .pos-kb-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid #e2e8f0;
    }

    .pos-kb-title {
        font-size: 20px;
        font-weight: 600;
        color: var(--pos-primary-color);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .pos-kb-close {
        background: none;
        border: none;
        color: #94a3b8;
        font-size: 28px;
        cursor: pointer;
        padding: 0 10px;
        transition: color 0.2s;
        outline: none;
        touch-action: manipulation;
    }

    .pos-kb-close:active {
        color: #ef4444; /* Roșu la apăsare */
    }

    /* Input-ul de previzualizare */
    .ui-keyboard-preview-wrapper {
        margin-bottom: 15px !important;
    }
    
    .ui-keyboard-preview-wrapper input,
    .ui-keyboard-preview-wrapper textarea {
        width: 100% !important;
        box-sizing: border-box !important;
        font-size: 28px !important;
        padding: 15px 20px !important;
        border-radius: 10px !important;
        border: 2px solid #cbd5e1 !important;
        background: #ffffff !important;
        color: var(--pos-text-color) !important;
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.02) !important;
        outline: none !important;
    }

    .ui-keyboard-preview-wrapper input:focus {
        border-color: var(--pos-primary-color) !important;
    }

    /* Tastele individuale */
    .ui-keyboard-button {
        background-color: var(--pos-key-bg) !important;
        border: 1px solid var(--pos-key-border) !important;
        border-radius: 8px !important;
        color: var(--pos-text-color) !important;
        font-size: 22px !important;
        font-weight: 500 !important;
        margin: 4px !important;
        height: 65px !important;
        min-width: 65px !important;
        box-shadow: 0 4px 0px rgba(203, 213, 225, 0.5) !important; /* Efect de tastă fizică */
        cursor: pointer !important;
        transition: transform 0.05s, box-shadow 0.05s !important;
        
        /* FIX PENTRU TOUCH POS: Tastare rapidă fără lag */
        touch-action: manipulation !important;
    }

    .ui-keyboard-button:active,
    .ui-keyboard-button.ui-state-active {
        transform: translateY(4px) !important;
        box-shadow: 0 0px 0px rgba(203, 213, 225, 0.5) !important;
    }

    /* Taste speciale (Space, Enter, Shift, Bksp) */
    .ui-keyboard-space {
        width: 500px !important; /* Spacebar lat */
    }

    /* Butonul ENTER (care e de fapt Accept) */
    .ui-keyboard-accept {
        background-color: var(--pos-success-color) !important;
        color: white !important;
        border-color: #1aa06d !important;
        box-shadow: 0 4px 0px #1aa06d !important;
        min-width: 120px !important;
        font-weight: bold !important;
    }
    
    .ui-keyboard-accept:active {
        box-shadow: 0 0px 0px #1aa06d !important;
    }

    /* Taste gri pentru comenzi (Shift, Tab, Bksp) */
    .ui-keyboard-shift, .ui-keyboard-bksp, .ui-keyboard-tab {
        background-color: #e2e8f0 !important;
        border-color: #cbd5e1 !important;
        min-width: 100px !important;
    }
</style>

<div id="pos-keyboard-backdrop" title="Apasă pe fundal pentru a închide tastatura"></div>

<div class="pos-floating-controls">
    <button type="button" class="pos-floating-btn" id="pos-scroll-up" title="Scroll Sus">
        <i class="fas fa-chevron-up"></i>
    </button>
    <button type="button" class="pos-floating-btn" id="pos-keyboard-btn" title="Afișează Tastatura">
        <i class="fas fa-keyboard"></i>
    </button>
    <button type="button" class="pos-floating-btn" id="pos-scroll-down" title="Scroll Jos">
        <i class="fas fa-chevron-down"></i>
    </button>
</div>

<script>
const POSTouchManager = (function($) {
    'use strict';

    let lastActiveInput = null;

    // 1. Urmărim ultimul input selectat
    const bindFocusTracker = function() {
        $(document).on('focus click', 'input[type="text"], input[type="number"], textarea', function() {
            if (!$(this).hasClass('ui-keyboard-preview')) {
                lastActiveInput = this;
            }
        });
    };

    // 2. Inițializare și Configurare Tastatură
    const setupKeyboardInteraction = function() {
        const $kbBtn = $('#pos-keyboard-btn');
        const $backdrop = $('#pos-keyboard-backdrop');

        // Deschidere tastatură (fără preventDefault pe touchstart, folosim click curat cu touch-action CSS)
        $kbBtn.on('click', function(e) {
            e.preventDefault();
            
            if (!lastActiveInput) {
                alert('Vă rugăm să atingeți un câmp de text mai întâi.');
                return;
            }

            const $input = $(lastActiveInput);
            let kbInstance = $input.getkeyboard();
            
            if (!kbInstance) {
                $input.keyboard({
                    // SETĂM LAYOUT CUSTOM
                    layout: 'custom',
                    customLayout: {
                        'normal': [
                            '` 1 2 3 4 5 6 7 8 9 0 - = {bksp}',
                            '{tab} q w e r t y u i o p [ ] \\',
                            'a s d f g h j k l ; \' {accept}', // Enter mascat ca Accept
                            '{shift} z x c v b n m , . / {shift}',
                            '{space}'
                        ],
                        'shift': [
                            '~ ! @ # $ % ^ & * ( ) _ + {bksp}',
                            '{tab} Q W E R T Y U I O P { } |',
                            'A S D F G H J K L : " {accept}',
                            '{shift} Z X C V B N M < > ? {shift}',
                            '{space}'
                        ]
                    },
                    display: {
                        'accept': 'Enter',
                        'bksp': '\u232b',     // Simbol de Backspace
                        'shift': 'Shift',
                        'tab': 'Tab \u21E2'
                    },
                    
                    usePreview: true,
                    autoAccept: true,
                    openOn: '',
                    stayOpen: true,
                    position: false,
                    
                    // INJECTARE HEADER DINAMIC (Label + Buton X)
                    beforeVisible: function(e, keyboard, el) {
                        let labelText = '';
                        const inputId = $(el).attr('id');
                        
                        // Căutăm Label-ul asociat
                        if (inputId) {
                            const $label = $('label[for="' + inputId + '"]');
                            if ($label.length) {
                                labelText = $label.text().trim();
                            }
                        }
                        // Fallback la placeholder
                        if (!labelText) {
                            labelText = $(el).attr('placeholder') || 'Introducere Date';
                        }

                        // Construim header-ul dacă nu există
                        if (keyboard.$keyboard.find('.pos-kb-header').length === 0) {
                            const $header = $('<div class="pos-kb-header"></div>');
                            const $title = $('<span class="pos-kb-title"></span>').text(labelText);
                            const $closeBtn = $('<button type="button" class="pos-kb-close" title="Închide"><i class="fas fa-times"></i></button>');

                            // Funcția pentru butonul X (Închidere fără salvare)
                            $closeBtn.on('click', function(e) {
                                e.preventDefault();
                                keyboard.close(); 
                            });

                            $header.append($title).append($closeBtn);
                            keyboard.$keyboard.prepend($header);
                        } else {
                            // Updatăm titlul dacă tastatura e deja creată
                            keyboard.$keyboard.find('.pos-kb-title').text(labelText);
                        }
                    },
                    
                    // Animații Backdrop
                    visible: function() { $backdrop.stop(true, true).fadeIn(200); },
                    hidden: function() { $backdrop.stop(true, true).fadeOut(200); },
                    canceled: function() { $backdrop.stop(true, true).fadeOut(200); }
                });
                
                kbInstance = $input.getkeyboard();
            }
            
            // Afișăm tastatura
            if (!kbInstance.isOpen) {
                kbInstance.reveal();
            }
        });

        // Închidere la atingerea fundalului (Cancel implicit)
        $backdrop.on('click', function(e) {
            e.preventDefault();
            if (lastActiveInput) {
                const kb = $(lastActiveInput).getkeyboard();
                if (kb && kb.isOpen) kb.close();
            }
        });
    };

    // 3. Funcțiile de Scroll pe Touch
    const setupScrolling = function() {
        $('#pos-scroll-up').on('click', function(e) { 
            e.preventDefault(); 
            window.scrollBy({ top: -400, behavior: 'smooth' }); 
        });
        
        $('#pos-scroll-down').on('click', function(e) { 
            e.preventDefault(); 
            window.scrollBy({ top: 400, behavior: 'smooth' }); 
        });
    };

    return {
        init: function() {
            bindFocusTracker();
            setupKeyboardInteraction();
            setupScrolling();
        }
    };

})(jQuery);

// Executăm scriptul la încărcarea paginii
$(document).ready(function() {
    POSTouchManager.init();
});
</script>