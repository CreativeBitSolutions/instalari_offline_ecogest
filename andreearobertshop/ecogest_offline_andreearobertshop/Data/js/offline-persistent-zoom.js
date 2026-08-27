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

    function updateZoomControls(zoom) {
        var percentage = Math.round(zoom * 100) + '%';

        document.querySelectorAll('[data-agecs-zoom-value]').forEach(function (element) {
            element.textContent = percentage;
            element.setAttribute('aria-label', 'Resetează zoom-ul. Nivel curent ' + percentage);
        });

        document.querySelectorAll('[data-agecs-zoom-action="out"]').forEach(function (button) {
            button.disabled = zoom <= MIN_ZOOM;
        });
        document.querySelectorAll('[data-agecs-zoom-action="in"]').forEach(function (button) {
            button.disabled = zoom >= MAX_ZOOM;
        });
    }

    function applyZoom(value, persist) {
        var zoom = normalize(value);
        document.documentElement.style.zoom = String(zoom);
        document.documentElement.dataset.agecsZoom = String(Math.round(zoom * 100));
        document.documentElement.style.setProperty('--agecs-persistent-zoom', String(zoom));
        compensateApplicationViewport(zoom);
        updateZoomControls(zoom);
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
            updateZoomControls(currentZoom);
        });
    } else {
        compensateApplicationViewport(currentZoom);
        updateZoomControls(currentZoom);
    }

    function changeZoom(direction) {
        currentZoom = applyZoom(direction === 0 ? 1 : currentZoom + direction * STEP, true);
    }

    window.AgecsOfflineZoom = {
        zoomIn: function () { changeZoom(1); },
        zoomOut: function () { changeZoom(-1); },
        reset: function () { changeZoom(0); },
        getValue: function () { return currentZoom; }
    };

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-agecs-zoom-action]');
        if (!button || button.disabled) {
            return;
        }

        var action = button.getAttribute('data-agecs-zoom-action');
        if (action === 'in') {
            changeZoom(1);
        } else if (action === 'out') {
            changeZoom(-1);
        } else if (action === 'reset') {
            changeZoom(0);
        }
    });

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
