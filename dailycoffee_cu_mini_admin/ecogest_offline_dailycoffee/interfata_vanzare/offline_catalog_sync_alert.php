<style>
    #offlineCatalogSyncAlert {
        position: fixed;
        right: 18px;
        bottom: 18px;
        z-index: 1040;
        width: min(430px, calc(100vw - 36px));
        padding: 17px 18px 16px;
        border: 1px solid #f0c36d;
        border-left: 5px solid #d97706;
        border-radius: 10px;
        background: #fffaf0;
        color: #3f2a0b;
        box-shadow: 0 14px 32px rgba(53, 35, 8, .22);
        font-size: 14px;
    }
    #offlineCatalogSyncAlert.d-none { display: none !important; }
    #offlineCatalogSyncAlert .catalog-alert-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
    }
    #offlineCatalogSyncAlert .catalog-alert-title {
        margin: 0;
        color: #7a4a00;
        font-size: 16px;
        font-weight: 700;
        line-height: 1.25;
    }
    #offlineCatalogSyncAlert .catalog-alert-close {
        flex: 0 0 auto;
        width: 28px;
        height: 28px;
        padding: 0;
        border: 0;
        border-radius: 50%;
        background: transparent;
        color: #7a4a00;
        font-size: 21px;
        line-height: 26px;
        cursor: pointer;
    }
    #offlineCatalogSyncAlert .catalog-alert-close:hover,
    #offlineCatalogSyncAlert .catalog-alert-close:focus { background: #fbe7bd; }
    #offlineCatalogSyncAlert .catalog-alert-message {
        margin: 10px 0 9px;
        line-height: 1.45;
    }
    #offlineCatalogSyncAlert .catalog-alert-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 6px 14px;
        margin: 0 0 14px;
        color: #785c2d;
        font-size: 12px;
    }
    #offlineCatalogSyncAlert .catalog-alert-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
    }
    #offlineCatalogSyncAlert .catalog-alert-primary,
    #offlineCatalogSyncAlert .catalog-alert-secondary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 36px;
        padding: 7px 11px;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
        cursor: pointer;
    }
    #offlineCatalogSyncAlert .catalog-alert-primary {
        border: 1px solid #b45309;
        background: #d97706;
        color: #fff;
    }
    #offlineCatalogSyncAlert .catalog-alert-primary:hover,
    #offlineCatalogSyncAlert .catalog-alert-primary:focus { background: #b45309; color: #fff; }
    #offlineCatalogSyncAlert .catalog-alert-secondary {
        border: 1px solid #e6c88f;
        background: #fff;
        color: #785c2d;
    }
    #offlineCatalogSyncAlert .catalog-alert-secondary:hover,
    #offlineCatalogSyncAlert .catalog-alert-secondary:focus { background: #fff4da; color: #5b410f; }
</style>

<div id="offlineCatalogSyncAlert" class="d-none" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="catalog-alert-head">
        <h2 id="offlineCatalogSyncAlertTitle" class="catalog-alert-title">Catalogul local trebuie actualizat</h2>
        <button type="button" id="offlineCatalogSyncAlertClose" class="catalog-alert-close" aria-label="Ascunde pentru un minut">&times;</button>
    </div>
    <p id="offlineCatalogSyncAlertMessage" class="catalog-alert-message"></p>
    <div id="offlineCatalogSyncAlertMeta" class="catalog-alert-meta"></div>
    <div class="catalog-alert-actions">
        <a href="offline_products_check.php" class="catalog-alert-primary">Intră la sincronizare</a>
        <button type="button" id="offlineCatalogSyncAlertSnooze" class="catalog-alert-secondary">Ascunde 1 minut</button>
    </div>
</div>

<script>
(function () {
    'use strict';

    var alertBox = document.getElementById('offlineCatalogSyncAlert');
    var title = document.getElementById('offlineCatalogSyncAlertTitle');
    var message = document.getElementById('offlineCatalogSyncAlertMessage');
    var meta = document.getElementById('offlineCatalogSyncAlertMeta');
    var snoozeButton = document.getElementById('offlineCatalogSyncAlertSnooze');
    var closeButton = document.getElementById('offlineCatalogSyncAlertClose');
    var snoozeKey = 'dailycoffee_catalog_sync_alert_snooze_until';
    var wakeTimer = null;

    function readSnoozeUntil() {
        try {
            return Number(window.localStorage.getItem(snoozeKey) || 0);
        } catch (error) {
            return 0;
        }
    }

    function writeSnoozeUntil(value) {
        try {
            window.localStorage.setItem(snoozeKey, String(value));
        } catch (error) {
        }
    }

    function clearSnooze() {
        try {
            window.localStorage.removeItem(snoozeKey);
        } catch (error) {
        }
        window.clearTimeout(wakeTimer);
        wakeTimer = null;
    }

    function hideAlert() {
        alertBox.classList.add('d-none');
    }

    function scheduleWake(until) {
        window.clearTimeout(wakeTimer);
        wakeTimer = window.setTimeout(function () {
            if (typeof window.offlineCatalogCheckNow === 'function') {
                window.offlineCatalogCheckNow();
            }
        }, Math.max(1000, until - Date.now() + 50));
    }

    function snoozeAlert() {
        var until = Date.now() + 60000;
        writeSnoozeUntil(until);
        hideAlert();
        scheduleWake(until);
    }

    function renderMeta(data) {
        var onlineCount = Number(data.products_count || 0);
        var localCount = Number(data.local_products_count || 0);
        var parts = [];
        if (onlineCount > 0) {
            parts.push('Online: ' + onlineCount + ' produse');
        }
        if (localCount > 0) {
            parts.push('Ultima versiune locală: ' + localCount + ' produse');
        }
        meta.textContent = parts.join(' · ');
        meta.style.display = parts.length ? 'flex' : 'none';
    }

    function applyStatus(data) {
        if (!data || data.status === 'unchanged') {
            clearSnooze();
            hideAlert();
            return;
        }
        if (data.status !== 'changed') {
            return;
        }

        var snoozeUntil = readSnoozeUntil();
        if (snoozeUntil > Date.now()) {
            hideAlert();
            scheduleWake(snoozeUntil);
            return;
        }

        title.textContent = data.needs_initial_sync
            ? 'Catalogul local trebuie sincronizat'
            : 'Catalogul online are modificări';
        message.textContent = data.needs_initial_sync
            ? 'Instalarea nu are încă o sincronizare confirmată. Intră la sincronizare și verifică preview-ul înainte de aplicare.'
            : 'Există diferențe între ultima versiune locală confirmată și catalogul online. Intră la sincronizare și verifică preview-ul înainte de aplicare.';
        renderMeta(data);
        alertBox.classList.remove('d-none');
    }

    if (!alertBox) {
        return;
    }

    snoozeButton.addEventListener('click', snoozeAlert);
    closeButton.addEventListener('click', snoozeAlert);
    window.addEventListener('offline-catalog-status-update', function (event) {
        applyStatus(event.detail || {});
    });
}());
</script>
