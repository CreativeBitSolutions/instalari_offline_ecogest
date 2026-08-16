(function () {
    'use strict';

    var running = false;

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

    window.setTimeout(tick, 1500);
    window.setInterval(tick, 30000);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) tick();
    });
}());
