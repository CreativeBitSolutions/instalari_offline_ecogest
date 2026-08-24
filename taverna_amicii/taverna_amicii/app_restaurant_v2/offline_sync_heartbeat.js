(function () {
    'use strict';

    var running = false;
    var timer = null;
    var licenseRunning = false;
    var licenseTimer = null;
    var idleIntervalMs = 30000;
    var retryIntervalMs = 15000;
    var drainIntervalMs = 2500;
    var licenseIntervalMs = 3600000;

    function schedule(delay) {
        window.clearTimeout(timer);
        timer = window.setTimeout(tick, delay);
    }

    function scheduleLicense(delay) {
        window.clearTimeout(licenseTimer);
        licenseTimer = window.setTimeout(licenseTick, delay);
    }

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
        if (running) return;
        running = true;
        var nextDelay = idleIntervalMs;
        fetch('offline_sync_worker.php', {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin',
            cache: 'no-store',
            keepalive: true
        }).then(function (response) {
            return response && response.ok ? response.json() : null;
        }).then(function (payload) {
            var queue = payload && payload.queue ? payload.queue : {};
            var active = (queue.pending || 0) + (queue.sending || 0);
            if (active > 0) {
                nextDelay = drainIntervalMs;
            } else if ((queue.retry || 0) > 0) {
                nextDelay = retryIntervalMs;
            }
            if (!payload || typeof payload.tablet_pending_for_operator === 'undefined') return;
            var count = Number(payload.tablet_pending_for_operator) || 0;
            var badge = document.getElementById('tabletImportBadge');
            var tile = document.getElementById('tabletImportTile');
            if (badge) badge.textContent = String(count);
            if (tile) tile.classList.toggle('has-woo-new', count > 0);
        }).catch(function () {
            nextDelay = retryIntervalMs;
            return null;
        }).finally(function () {
            running = false;
            if (typeof window.refreshRestaurantSyncStatus === 'function' && window.jQuery && window.jQuery('#restaurantSyncStatusModal').hasClass('show')) {
                window.refreshRestaurantSyncStatus();
            }
            schedule(nextDelay);
        });
    }

    function licenseTick() {
        if (licenseRunning) return;
        licenseRunning = true;
        fetch('offline_license_background.php', {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin',
            cache: 'no-store'
        }).then(function (response) {
            return response.json();
        }).then(function (data) {
            if (data && data.status === 'refreshed' && /offline_license_check\.php$/i.test(window.location.pathname)) {
                window.location.reload();
            }
        }).catch(function () {
            return null;
        }).finally(function () {
            licenseRunning = false;
            scheduleLicense(licenseIntervalMs);
        });
    }

    injectUsersSyncControl();
    schedule(1500);
    scheduleLicense(5000);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) schedule(250);
    });
    window.addEventListener('online', function () {
        schedule(250);
        scheduleLicense(500);
    });
}());
