(function () {
    'use strict';

    if (window.__agecsOfflineSyncStarted) {
        return;
    }
    window.__agecsOfflineSyncStarted = true;

    var running = false;
    var intervalMs = 15000;
    var badge = null;

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
        var controller = window.AbortController ? new AbortController() : null;
        var timeout = window.setTimeout(function () { if (controller) { controller.abort(); } }, 14000);
        fetch('offline_sync_worker.php', {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            cache: 'no-store',
            signal: controller ? controller.signal : undefined
        }).then(function (response) {
            return response.json();
        }).then(render).catch(function () {
        }).finally(function () {
            window.clearTimeout(timeout);
            running = false;
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { window.setTimeout(tick, 2000); });
    } else {
        window.setTimeout(tick, 2000);
    }
    window.setInterval(tick, intervalMs);
}());

