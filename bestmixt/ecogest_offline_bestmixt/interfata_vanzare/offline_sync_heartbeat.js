(function () {
    'use strict';

    if (window.__agecsOfflineSyncStarted) {
        return;
    }
    window.__agecsOfflineSyncStarted = true;

    var running = false;
    var timer = null;
    var idleIntervalMs = 30000;
    var retryIntervalMs = 15000;
    var drainIntervalMs = 2500;
    var licenseIntervalMs = 3600000;
    var licenseRunning = false;
    var licenseTimer = null;
    var badge = null;

    function schedule(delay) {
        window.clearTimeout(timer);
        timer = window.setTimeout(tick, delay);
    }

    function scheduleLicense(delay) {
        window.clearTimeout(licenseTimer);
        licenseTimer = window.setTimeout(licenseTick, delay);
    }

    function ensureBadge() {
        if (badge || !document.body) {
            return;
        }
        badge = document.createElement('div');
        badge.id = 'offline-sync-badge';
        badge.setAttribute('title', 'Starea trimiterii automate a operatiunilor offline');
        badge.style.cssText = 'position:fixed;right:10px;bottom:10px;z-index:99999;padding:5px 8px;border-radius:4px;background:#334155;color:#fff;font:12px Arial,sans-serif;box-shadow:0 1px 4px rgba(0,0,0,.25);display:none';
        document.body.appendChild(badge);
    }

    function render(data) {
        ensureBadge();
        if (!badge || !data || !data.queue) {
            return;
        }
        var pending = (data.queue.pending || 0) + (data.queue.retry || 0) + (data.queue.sending || 0);
        var blocked = data.queue.blocked || 0;
        if (blocked > 0) {
            badge.textContent = 'Sync blocat: ' + blocked;
            badge.style.background = '#991b1b';
            badge.style.display = 'block';
        } else if (pending > 0) {
            badge.textContent = 'De trimis: ' + pending;
            badge.style.background = '#92400e';
            badge.style.display = 'block';
        } else if (data.status === 'sent') {
            badge.textContent = 'Sincronizat';
            badge.style.background = '#166534';
            badge.style.display = 'block';
            window.setTimeout(function () { if (badge) { badge.style.display = 'none'; } }, 3500);
        } else {
            badge.style.display = 'none';
        }
    }

    function tick() {
        if (running) {
            return;
        }
        running = true;
        var nextDelay = idleIntervalMs;
        var controller = window.AbortController ? new AbortController() : null;
        var timeout = window.setTimeout(function () { if (controller) { controller.abort(); } }, 25000);
        fetch('offline_sync_worker.php', {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            cache: 'no-store',
            signal: controller ? controller.signal : undefined
        }).then(function (response) {
            return response.json();
        }).then(function (data) {
            render(data);
            var queue = data && data.queue ? data.queue : {};
            var active = (queue.pending || 0) + (queue.sending || 0);
            if (active > 0) {
                nextDelay = drainIntervalMs;
            } else if ((queue.retry || 0) > 0) {
                nextDelay = retryIntervalMs;
            }
        }).catch(function () {
            nextDelay = retryIntervalMs;
        }).finally(function () {
            window.clearTimeout(timeout);
            running = false;
            schedule(nextDelay);
        });
    }

    function licenseTick() {
        if (licenseRunning) {
            return;
        }
        licenseRunning = true;
        fetch('offline_license_background.php', {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            cache: 'no-store'
        }).then(function (response) {
            return response.json();
        }).then(function (data) {
            if (data && data.status === 'refreshed' && /offline_license_check\.php$/i.test(window.location.pathname)) {
                window.location.reload();
            }
        }).catch(function () {
        }).finally(function () {
            licenseRunning = false;
            scheduleLicense(licenseIntervalMs);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            schedule(2000);
            scheduleLicense(5000);
        });
    } else {
        schedule(2000);
        scheduleLicense(5000);
    }
    window.addEventListener('online', function () {
        schedule(250);
        scheduleLicense(500);
    });
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            schedule(250);
        }
    });
}());
