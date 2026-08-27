(function () {
    'use strict';

    var container = null;
    var message = null;
    var timer = null;
    var running = false;

    function render(state, text) {
        if (!container || !message) return;
        container.classList.remove('is-loading', 'is-ok', 'is-warning', 'is-error');
        container.classList.add(state === 'ok' ? 'is-ok' : (state === 'error' ? 'is-error' : 'is-warning'));
        message.textContent = text;
    }

    function schedule(delay) {
        window.clearTimeout(timer);
        timer = window.setTimeout(loadStatus, delay);
    }

    function loadStatus() {
        if (running) return;
        running = true;
        fetch('offline_products_autosync_status.php?ts=' + Date.now(), {
            method: 'GET',
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin',
            cache: 'no-store'
        }).then(function (response) {
            return response.json().then(function (payload) {
                if (!response.ok) throw payload;
                return payload;
            });
        }).then(function (payload) {
            render(payload.state || 'warning', payload.message || 'Starea nu este disponibilă.');
        }).catch(function (error) {
            render('error', error && error.message ? error.message : 'Starea sincronizării produselor nu a putut fi citită.');
        }).finally(function () {
            running = false;
            schedule(15000);
        });
    }

    function start() {
        container = document.getElementById('productsAutosyncStatus');
        message = document.getElementById('productsAutosyncMessage');
        if (!container || !message) return;
        loadStatus();
        window.addEventListener('online', function () { schedule(250); });
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) schedule(250);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
}());

