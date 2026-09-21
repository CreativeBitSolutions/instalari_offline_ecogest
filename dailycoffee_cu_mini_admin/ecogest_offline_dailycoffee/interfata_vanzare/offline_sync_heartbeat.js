(function () {
    'use strict';

    if (window.__agecsOfflineSyncStarted) {
        return;
    }
    window.__agecsOfflineSyncStarted = true;

    var running = false;
    var timer = null;
    var idleIntervalMs = 120000;
    var retryIntervalMs = 60000;
    var drainIntervalMs = 5000;
    var offlineIntervalMs = 300000;
    var discoveryIntervalMs = 300000;
    var nextDiscoveryAt = 0;
    var networkFailureCount = 0;
    var licenseIntervalMs = 3600000;
    var licenseRunning = false;
    var licenseTimer = null;
    var catalogIntervalMs = 300000;
    var catalogOfflineIntervalMs = 300000;
    var catalogRetryCount = 0;
    var catalogNextAt = 0;
    var catalogRunning = false;
    var catalogTimer = null;
    var indicatorRunning = false;
    var badge = null;
    var backgroundWorker = null;
    var backgroundTaskSequence = 0;
    var backgroundTasks = {};

    function schedule(delay) {
        window.clearTimeout(timer);
        timer = window.setTimeout(tick, delay);
    }

    function scheduleLicense(delay) {
        window.clearTimeout(licenseTimer);
        licenseTimer = window.setTimeout(licenseTick, delay);
    }

    function scheduleCatalog(delay) {
        window.clearTimeout(catalogTimer);
        delay = Math.max(0, Number(delay) || 0);
        catalogNextAt = Date.now() + delay;
        catalogTimer = window.setTimeout(catalogTick, delay);
    }

    function isLikelyOnline() {
        return typeof navigator.onLine === 'undefined' || navigator.onLine !== false;
    }

    function networkBackoff(attempt) {
        var steps = [60000, 120000, 300000, 900000];
        return steps[Math.min(Math.max(attempt - 1, 0), steps.length - 1)];
    }

    function fetchJson(url, options, timeoutMs) {
        options = options || {};
        var controller = window.AbortController ? new AbortController() : null;
        var timeout = window.setTimeout(function () {
            if (controller) {
                controller.abort();
            }
        }, timeoutMs);
        options.signal = controller ? controller.signal : undefined;
        return fetch(url, options).then(function (response) {
            return response.json().then(function (data) {
                if (!response.ok) {
                    throw new Error(data && data.message ? data.message : ('HTTP ' + response.status));
                }
                return data;
            });
        }).finally(function () {
            window.clearTimeout(timeout);
        });
    }

    function stopBackgroundWorker() {
        if (backgroundWorker) {
            backgroundWorker.terminate();
            backgroundWorker = null;
        }
        var pending = backgroundTasks;
        backgroundTasks = {};
        Object.keys(pending).forEach(function (id) {
            if (typeof pending[id].onError === 'function') {
                pending[id].onError('Workerul de fundal nu este disponibil.');
            }
        });
    }

    function startBackgroundWorker() {
        if (!window.Worker || backgroundWorker) {
            return;
        }
        try {
            backgroundWorker = new Worker('offline_sync_background_worker.js');
            backgroundWorker.onmessage = function (event) {
                var result = event.data || {};
                var task = backgroundTasks[result.id];
                if (!task) {
                    return;
                }
                delete backgroundTasks[result.id];
                if (result.ok) {
                    task.onSuccess(result.data);
                } else if (typeof task.onError === 'function') {
                    task.onError(result.error);
                }
            };
            backgroundWorker.onerror = function () {
                stopBackgroundWorker();
            };
        } catch (error) {
            backgroundWorker = null;
        }
    }

    function runBackgroundTask(type, url, method, onSuccess, onError) {
        if (!backgroundWorker) {
            return false;
        }
        var id = 'sync-' + Date.now() + '-' + (++backgroundTaskSequence);
        backgroundTasks[id] = {
            onSuccess: onSuccess,
            onError: onError
        };
        try {
            backgroundWorker.postMessage({ id: id, type: type, url: url, method: method || 'GET' });
            return true;
        } catch (error) {
            delete backgroundTasks[id];
            if (typeof onError === 'function') {
                onError(error && error.message ? error.message : error);
            }
            return true;
        }
    }

    function publishCatalogStatus(data) {
        if (!data || (data.status !== 'changed' && data.status !== 'unchanged')) {
            return;
        }
        window.dispatchEvent(new CustomEvent('offline-catalog-status-update', { detail: data }));
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

    function refreshTransmissionIndicator() {
        if (indicatorRunning) {
            return;
        }
        indicatorRunning = true;
        var success = function (data) {
            if (data && data.status === 'success') {
                window.dispatchEvent(new CustomEvent('offline-sync-status-update', { detail: data }));
            }
        };
        var failure = function () {
            indicatorRunning = false;
        };
        if (runBackgroundTask('indicator', 'offline_sync_status.php?indicator=' + Date.now(), 'GET', function (data) {
            try {
                success(data);
            } finally {
                indicatorRunning = false;
            }
        }, failure)) {
            return;
        }
        fetchJson('offline_sync_status.php?indicator=' + Date.now(), {
            headers: { 'Accept': 'application/json' },
            cache: 'no-store'
        }, 5000).then(success).catch(failure).finally(function () {
            indicatorRunning = false;
        });
    }

    function finishSync(nextDelay) {
        running = false;
        schedule(nextDelay);
    }

    function processSyncResult(data) {
        render(data);
        if (data && ['retry', 'blocked', 'sent'].indexOf(data.status) !== -1) {
            refreshTransmissionIndicator();
        }
        if (data && data.status === 'retry') {
            networkFailureCount = Math.min(networkFailureCount + 1, 4);
        } else if (data && data.status !== 'error') {
            networkFailureCount = 0;
        }
        var queue = data && data.queue ? data.queue : {};
        var active = (queue.pending || 0) + (queue.sending || 0);
        if (active > 0) {
            return networkFailureCount > 0 ? networkBackoff(networkFailureCount) : drainIntervalMs;
        }
        if ((queue.retry || 0) > 0) {
            return networkFailureCount > 0 ? networkBackoff(networkFailureCount) : retryIntervalMs;
        }
        return idleIntervalMs;
    }

    function tick() {
        if (running) {
            return;
        }
        if (!isLikelyOnline()) {
            schedule(offlineIntervalMs);
            return;
        }
        running = true;
        var shouldDiscover = Date.now() >= nextDiscoveryAt;
        if (shouldDiscover) {
            nextDiscoveryAt = Date.now() + discoveryIntervalMs;
        }
        var workerUrl = 'offline_sync_worker.php?discover=' + (shouldDiscover ? '1' : '0');
        var success = function (data) {
            try {
                finishSync(processSyncResult(data));
            } catch (error) {
                failure();
            }
        };
        var failure = function () {
            networkFailureCount = Math.min(networkFailureCount + 1, 4);
            finishSync(networkBackoff(networkFailureCount));
        };
        if (runBackgroundTask('sync', workerUrl, 'POST', success, failure)) {
            return;
        }
        fetchJson(workerUrl, {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            cache: 'no-store'
        }, 14000).then(success).catch(failure);
    }

    function licenseTick() {
        if (licenseRunning) {
            return;
        }
        licenseRunning = true;
        var success = function (data) {
            if (data && data.status === 'refreshed' && /offline_license_check\.php$/i.test(window.location.pathname)) {
                window.location.reload();
            }
        };
        var finish = function () {
            licenseRunning = false;
            scheduleLicense(licenseIntervalMs);
        };
        if (runBackgroundTask('license', 'offline_license_background.php', 'POST', function (data) {
            try {
                success(data);
            } finally {
                finish();
            }
        }, finish)) {
            return;
        }
        fetchJson('offline_license_background.php', {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            cache: 'no-store'
        }, 10000).then(success).catch(function () {}).finally(finish);
    }

    function catalogTick() {
        if (catalogRunning) {
            return;
        }
        catalogNextAt = 0;
        if (document.hidden || !isLikelyOnline()) {
            scheduleCatalog(catalogOfflineIntervalMs);
            return;
        }
        catalogRunning = true;
        var success = function (data) {
            catalogRetryCount = 0;
            publishCatalogStatus(data);
        };
        var failure = function () {
            catalogRetryCount = Math.min(catalogRetryCount + 1, 4);
        };
        var finish = function () {
            catalogRunning = false;
            scheduleCatalog(catalogRetryCount > 0 ? networkBackoff(catalogRetryCount) : catalogIntervalMs);
        };
        if (runBackgroundTask('catalog', 'offline_products_sync.php?check_only=1', 'GET', function (data) {
            try {
                success(data);
            } finally {
                finish();
            }
        }, function () {
            failure();
            finish();
        })) {
            return;
        }
        fetchJson('offline_products_sync.php?check_only=1', {
            method: 'GET',
            headers: { 'Accept': 'application/json' },
            cache: 'no-store'
        }, 7000).then(success).catch(failure).finally(finish);
    }

    window.offlineCatalogCheckNow = function () {
        catalogRetryCount = 0;
        scheduleCatalog(0);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            schedule(2000);
            scheduleLicense(5000);
            scheduleCatalog(7000);
        });
    } else {
        schedule(2000);
        scheduleLicense(5000);
        scheduleCatalog(7000);
    }
    startBackgroundWorker();
    window.addEventListener('online', function () {
        networkFailureCount = 0;
        nextDiscoveryAt = 0;
        schedule(250);
        scheduleLicense(500);
        scheduleCatalog(1000);
    });
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            schedule(250);
            if (Date.now() >= catalogNextAt) {
                scheduleCatalog(1000);
            }
        }
    });
}());
