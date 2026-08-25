(function () {
    'use strict';

    var MIN_ZOOM = 0.7;
    var MAX_ZOOM = 1.8;
    var STEP = 0.1;

    function storageKey() {
        var parts = window.location.pathname.split('/').filter(Boolean);
        var boundary = parts.indexOf('interfata_vanzare');
        if (boundary < 0) {
            boundary = parts.indexOf('app_restaurant_v2');
        }
        var scope = boundary >= 0 ? parts.slice(0, boundary + 1).join('/') : 'application';
        return 'agecs_offline_zoom:' + scope;
    }

    function normalize(value) {
        var zoom = Number(value);
        if (!Number.isFinite(zoom)) {
            zoom = 1;
        }
        return Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, Math.round(zoom * 10) / 10));
    }

    function readZoom() {
        try {
            return normalize(window.localStorage.getItem(storageKey()) || 1);
        } catch (error) {
            return 1;
        }
    }

    function compensateApplicationViewport(zoom) {
        var viewportHeight = (100 / zoom) + 'vh';
        var pageContainers = document.querySelectorAll('.page-container');

        pageContainers.forEach(function (container) {
            container.style.height = viewportHeight;
            container.style.minHeight = viewportHeight;
            container.style.maxHeight = viewportHeight;
        });
    }

    function applyZoom(value, persist) {
        var zoom = normalize(value);
        document.documentElement.style.zoom = String(zoom);
        document.documentElement.dataset.agecsZoom = String(Math.round(zoom * 100));
        document.documentElement.style.setProperty('--agecs-persistent-zoom', String(zoom));
        compensateApplicationViewport(zoom);
        if (persist) {
            try {
                window.localStorage.setItem(storageKey(), String(zoom));
            } catch (error) {
                // Aplicația rămâne utilizabilă și când stocarea browserului nu este disponibilă.
            }
        }
        return zoom;
    }

    var currentZoom = applyZoom(readZoom(), false);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            compensateApplicationViewport(currentZoom);
        });
    } else {
        compensateApplicationViewport(currentZoom);
    }

    function changeZoom(direction) {
        currentZoom = applyZoom(direction === 0 ? 1 : currentZoom + direction * STEP, true);
    }

    window.addEventListener('keydown', function (event) {
        if (!event.ctrlKey || event.altKey || event.metaKey) {
            return;
        }

        var key = String(event.key || '').toLowerCase();
        var code = String(event.code || '');
        if (key === '+' || key === '=' || code === 'NumpadAdd') {
            event.preventDefault();
            changeZoom(1);
        } else if (key === '-' || code === 'NumpadSubtract') {
            event.preventDefault();
            changeZoom(-1);
        } else if (key === '0' || code === 'Numpad0') {
            event.preventDefault();
            changeZoom(0);
        }
    }, true);

    window.addEventListener('wheel', function (event) {
        if (!event.ctrlKey) {
            return;
        }
        event.preventDefault();
        changeZoom(event.deltaY < 0 ? 1 : -1);
    }, { passive: false, capture: true });
}());
