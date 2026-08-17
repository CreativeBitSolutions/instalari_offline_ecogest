(function () {
    'use strict';

    var running = false;

    function injectUsersSyncControl() {
        if (!/\/agecs_login\.php$/i.test(window.location.pathname)) return;
        if (document.getElementById('offlineUsersSyncForm')) return;

        var header = document.querySelector('.container > .d-flex.flex-column.align-items-center.text-center.text-white');
        if (!header) return;

        var form = document.createElement('form');
        form.id = 'offlineUsersSyncForm';
        form.method = 'post';
        form.action = 'offline_users_sync.php';
        form.className = 'mt-2';

        var button = document.createElement('button');
        button.type = 'submit';
        button.className = 'btn btn-warning btn-sm';
        button.textContent = 'Preia utilizatorii din online';
        button.addEventListener('click', function () {
            button.disabled = true;
            button.textContent = 'Se preiau utilizatorii...';
        });

        form.appendChild(button);
        header.appendChild(form);

        var params = new URLSearchParams(window.location.search);
        var syncStatus = params.get('users_sync');
        if (!syncStatus) return;

        var notice = document.createElement('div');
        notice.className = 'alert text-center mb-3 ' + (syncStatus === 'success' ? 'alert-success' : 'alert-danger');
        notice.setAttribute('role', 'alert');

        if (syncStatus === 'success') {
            var received = Number(params.get('received')) || 0;
            var inserted = Number(params.get('inserted')) || 0;
            var updated = Number(params.get('updated')) || 0;
            notice.textContent = 'Utilizatori online preluați: ' + received + '. Adăugați: ' + inserted + ', actualizați: ' + updated + '.';
        } else {
            notice.textContent = params.get('message') || 'Nu s-au putut prelua utilizatorii din online.';
        }

        header.insertAdjacentElement('afterend', notice);
    }

    function tick() {
        if (running || document.hidden) return;
        running = true;
        fetch('offline_sync_worker.php', {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin',
            cache: 'no-store',
            keepalive: true
        }).then(function (response) {
            return response && response.ok ? response.json() : null;
        }).then(function (payload) {
            if (!payload || typeof payload.tablet_pending_for_operator === 'undefined') return;
            var count = Number(payload.tablet_pending_for_operator) || 0;
            var badge = document.getElementById('tabletImportBadge');
            var tile = document.getElementById('tabletImportTile');
            if (badge) badge.textContent = String(count);
            if (tile) tile.classList.toggle('has-woo-new', count > 0);
        }).catch(function () {
            return null;
        }).finally(function () {
            running = false;
            if (typeof window.refreshRestaurantSyncStatus === 'function' && window.jQuery && window.jQuery('#restaurantSyncStatusModal').hasClass('show')) {
                window.refreshRestaurantSyncStatus();
            }
        });
    }

    injectUsersSyncControl();
    window.setTimeout(tick, 1500);
    window.setInterval(tick, 30000);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) tick();
    });
}());
