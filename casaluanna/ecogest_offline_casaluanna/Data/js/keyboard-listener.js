// keyboard-listener.js

$(document).ready(function() {
    $.ajax({
        url: 'get_activare_listener.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            // Verifică dacă activare_listener este true (1)
            if (response.activare_listener == 1) {
                console.log('Activare listener este activată. Inițializare ascultători de tastatură.');

                // Modular Keyboard Listener
                function setupKeyboardListeners() {
                    const keyElementMapping = {};

                    // Găsește toate elementele cu clase care se potrivesc '*_Listener_Keyboard'
                    const elements = document.querySelectorAll('[class*="_Listener_Keyboard"]');
                    elements.forEach((el) => {
                        const classes = el.className.split(/\s+/);
                        classes.forEach((cls) => {
                            const match = cls.match(/^(\w+)_Listener_Keyboard$/);
                            if (match) {
                                const key = match[1];
                                if (!keyElementMapping[key]) {
                                    keyElementMapping[key] = [];
                                }
                                keyElementMapping[key].push(el);
                            }
                        });
                    });

                    // Set up keydown event listener pentru fiecare tastă
                    document.addEventListener('keydown', function(event) {
                        const key = event.key;
                        if (keyElementMapping[key]) {
                            keyElementMapping[key].forEach((el) => {
                                el.click();
                            });
                        }
                    });
                }

                setupKeyboardListeners();
            } else {
                console.log('Activare listener nu este activată. Ascultătorii de tastatură nu vor fi inițializați.');
            }
        },
        error: function(xhr, status, error) {
            console.error('Eroare la obținerea activare_listener:', error);
            console.error('Răspunsul:', xhr.responseText);
        }
    });
});
