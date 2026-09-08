(function () {
    'use strict';

    var script = document.currentScript;
    var mode = script ? script.getAttribute('data-agecs-tab-mode') : '';
    var conflictFromServer = script ? script.getAttribute('data-agecs-lock-conflict') === '1' : false;
    var justValidated = script ? script.getAttribute('data-agecs-client-validated') === '1' : false;
    var lockKey = 'agecs_conectare_used_v1';
    var noticeKey = 'agecs_conectare_conflict_notice_v1';
    var oldKeys = ['agecs_active_app_tab_v1', 'agecs_tab_ping_v1', 'agecs_tab_pong_v1'];
    var alertMessage = 'Pagina de conectare a fost resetat\u0103 deoarece era deja blocat\u0103 \u00een acest browser.';

    function setLock() {
        try {
            localStorage.setItem(lockKey, JSON.stringify({
                lockedAt: Date.now ? Date.now() : new Date().getTime(),
                path: location.pathname
            }));
        } catch (e) {}
    }

    function clearLock() {
        try {
            localStorage.removeItem(lockKey);
            for (var i = 0; i < oldKeys.length; i++) {
                localStorage.removeItem(oldKeys[i]);
            }
        } catch (e) {}
    }

    function hasLock() {
        try {
            return !!localStorage.getItem(lockKey);
        } catch (e) {
            return false;
        }
    }

    function markConflictNotice() {
        try {
            sessionStorage.setItem(noticeKey, '1');
        } catch (e) {}
    }

    function shouldShowConflictNotice() {
        if (conflictFromServer) {
            return true;
        }

        try {
            var value = sessionStorage.getItem(noticeKey);
            if (value) {
                sessionStorage.removeItem(noticeKey);
                return true;
            }
        } catch (e) {}

        return false;
    }

    function showConflictNotice() {
        if (!shouldShowConflictNotice()) {
            return;
        }

        window.setTimeout(function () {
            window.alert(alertMessage);
        }, 250);
    }

    function blockLogin() {
        document.documentElement.style.display = 'none';
        markConflictNotice();
        clearLock();
        window.location.replace('logout.php?lock_conflict=1');
    }

    function removeValidationMarkerFromUrl() {
        if (!window.history || !window.history.replaceState || location.search.indexOf('client_validated=1') === -1) {
            return;
        }

        try {
            var url = new URL(location.href);
            url.searchParams.delete('client_validated');
            window.history.replaceState(null, document.title, url.pathname + url.search + url.hash);
        } catch (e) {}
    }

    if (mode === 'reset') {
        clearLock();
        return;
    }

    if (mode === 'app') {
        setLock();
        return;
    }

    if (mode === 'login') {
        showConflictNotice();

        if (justValidated) {
            setLock();
            removeValidationMarkerFromUrl();
            return;
        }

        if (hasLock()) {
            blockLogin();
            return;
        }
    }
})();
