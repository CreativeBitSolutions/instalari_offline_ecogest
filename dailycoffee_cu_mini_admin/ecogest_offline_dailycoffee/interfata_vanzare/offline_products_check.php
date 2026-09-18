<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Bucharest');
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verificare sincronizare produse</title>
    <style>
        :root {
            color-scheme: light;
            --ink: #172033;
            --muted: #64748b;
            --line: #dbe3ec;
            --panel: #ffffff;
            --soft: #f5f8fb;
            --green: #138a5b;
            --green-soft: #eaf8f1;
            --blue: #1769aa;
            --blue-soft: #edf6ff;
            --amber: #b66a00;
            --amber-soft: #fff6df;
            --red: #b42318;
            --red-soft: #fff0ee;
        }
        * { box-sizing: border-box; }
        body {
            min-height: 100vh;
            margin: 0;
            background: #20272f;
            color: var(--ink);
            font-family: Arial, Helvetica, sans-serif;
        }
        .page-wrap {
            width: min(1120px, calc(100% - 28px));
            margin: 0 auto;
            padding: 28px 0 44px;
        }
        .panel {
            border-radius: 12px;
            background: var(--panel);
            box-shadow: 0 18px 42px rgba(0, 0, 0, .22);
        }
        .panel-header {
            padding: 27px 30px 22px;
            border-bottom: 1px solid var(--line);
        }
        .kicker {
            display: block;
            color: var(--muted);
            font-size: .76rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        h1 {
            margin: 7px 0 8px;
            color: #111827;
            font-size: clamp(1.45rem, 3vw, 2rem);
        }
        .subtitle {
            max-width: 850px;
            margin: 0;
            color: #475569;
            line-height: 1.55;
        }
        .panel-body { padding: 24px 30px 30px; }
        .notice,
        .warning,
        .result {
            border-radius: 8px;
            padding: 13px 15px;
            line-height: 1.45;
        }
        .notice {
            border-left: 4px solid var(--blue);
            background: var(--blue-soft);
            color: #164e76;
        }
        .warning {
            display: none;
            margin-top: 15px;
            border-left: 4px solid var(--amber);
            background: var(--amber-soft);
            color: #7a4a00;
        }
        .scope-line {
            display: flex;
            flex-wrap: wrap;
            gap: 9px 22px;
            margin: 17px 0 20px;
            color: var(--muted);
            font-size: .9rem;
        }
        .scope-line strong { color: var(--ink); }
        .loading {
            padding: 34px 10px;
            color: var(--muted);
            text-align: center;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 10px;
            margin: 18px 0 24px;
        }
        .stat {
            min-height: 102px;
            padding: 14px 13px;
            border: 1px solid var(--line);
            border-radius: 9px;
            background: var(--soft);
        }
        .stat .number {
            display: block;
            color: #111827;
            font-size: 1.8rem;
            font-weight: 700;
            line-height: 1;
        }
        .stat .label {
            display: block;
            margin-top: 10px;
            color: #526174;
            font-size: .84rem;
            line-height: 1.25;
        }
        .stat.good { background: var(--green-soft); border-color: #bce5d1; }
        .stat.good .number { color: var(--green); }
        .stat.change { background: var(--blue-soft); border-color: #c7e2f7; }
        .stat.change .number { color: var(--blue); }
        .stat.warn { background: var(--amber-soft); border-color: #f0d89d; }
        .stat.warn .number { color: var(--amber); }
        .stat.danger { background: var(--red-soft); border-color: #efc2bd; }
        .stat.danger .number { color: var(--red); }
        .section-title {
            margin: 24px 0 10px;
            color: #1f2937;
            font-size: 1.05rem;
        }
        .table-wrap { overflow-x: auto; border: 1px solid var(--line); border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; min-width: 700px; }
        th,
        td {
            padding: 10px 11px;
            border-bottom: 1px solid var(--line);
            text-align: left;
            font-size: .88rem;
            white-space: nowrap;
        }
        th { background: #eef3f7; color: #475569; font-size: .78rem; text-transform: uppercase; }
        tr:last-child td { border-bottom: 0; }
        td.num { text-align: right; font-variant-numeric: tabular-nums; }
        td.action { color: var(--red); font-weight: 700; }
        details {
            margin-top: 10px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fbfcfd;
        }
        summary {
            cursor: pointer;
            padding: 12px 14px;
            color: #334155;
            font-weight: 700;
        }
        .detail-body { padding: 0 14px 13px; color: #526174; font-size: .88rem; }
        .detail-body ul { margin: 0; padding-left: 19px; }
        .detail-body li { margin: 4px 0; }
        .empty-detail { color: var(--muted); }
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 26px;
        }
        button,
        .button-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 10px 17px;
            border: 1px solid transparent;
            border-radius: 7px;
            font: inherit;
            font-size: .9rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }
        button:disabled { cursor: not-allowed; opacity: .55; }
        .primary { background: var(--green); color: #fff; }
        .primary:hover:not(:disabled) { background: #0d714a; }
        .secondary { border-color: #aab7c4; background: #fff; color: #425466; }
        .secondary:hover { background: #f4f7fa; }
        .reload { border-color: #a9cde7; background: var(--blue-soft); color: #155d8d; }
        .result { display: none; margin-top: 17px; }
        .result.success { display: block; background: var(--green-soft); color: #12613f; }
        .result.info { display: block; background: var(--blue-soft); color: #164e76; }
        .result.error { display: block; background: var(--red-soft); color: #8f211a; }
        .sync-loader {
            display: none;
            width: 1rem;
            height: 1rem;
            margin-right: .45rem;
            border: .16rem solid rgba(255, 255, 255, .55);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .75s linear infinite;
        }
        .running .sync-loader { display: inline-block; }
        @keyframes spin { to { transform: rotate(360deg); } }
        @media (max-width: 950px) {
            .stats-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }
        @media (max-width: 600px) {
            .page-wrap { width: min(100% - 18px, 1120px); padding-top: 10px; }
            .panel-header, .panel-body { padding-left: 17px; padding-right: 17px; }
            .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .actions > * { width: 100%; }
        }
    </style>
</head>
<body>
<main class="page-wrap">
    <section class="panel">
        <header class="panel-header">
            <span class="kicker">ECOGEST POS OFFLINE, locatia 2</span>
            <h1>Verificare sincronizare produse</h1>
            <p class="subtitle">Mai intai se compara catalogul online cu baza locala. Nimic nu se modifica pana cand nu confirmi sincronizarea.</p>
        </header>
        <div class="panel-body">
            <div class="notice">
                Produsele existente nu sunt sterse din baza locala. Cele care nu mai exista online sunt dezactivate. Se pot elimina doar asocierile produsului sau categoriei cu o locatie, daca acestea lipsesc din lista completa primita din online.
            </div>
            <div id="scopeWarning" class="warning"></div>
            <div id="previewLoading" class="loading">Se compara baza locala cu nomenclatorul online...</div>
            <div id="previewContent" hidden>
                <div class="scope-line">
                    <span>Client: <strong id="scopeClient">2</strong></span>
                    <span>Locatie configurata: <strong id="scopeLocation">2</strong></span>
                    <span>Asocieri primite: <strong id="scopeMappings">-</strong></span>
                </div>

                <div class="stats-grid" aria-label="Rezumat modificari">
                    <div class="stat change"><span class="number" id="statNew">0</span><span class="label">Produse noi</span></div>
                    <div class="stat change"><span class="number" id="statUpdated">0</span><span class="label">Produse actualizate</span></div>
                    <div class="stat warn"><span class="number" id="statDeactivate">0</span><span class="label">Produse de dezactivat, nu sterse</span></div>
                    <div class="stat good"><span class="number" id="statUnchanged">0</span><span class="label">Produse neschimbate</span></div>
                    <div class="stat danger"><span class="number" id="statRemoved">0</span><span class="label">Asocieri de locatie eliminate</span></div>
                    <div class="stat"><span class="number" id="statOnline">0</span><span class="label">Produse in online</span></div>
                </div>

                <h2 class="section-title">Rezumat pe tabele</h2>
                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Set de date</th>
                            <th>Online</th>
                            <th>Local</th>
                            <th>Noi</th>
                            <th>Actualizate</th>
                            <th>Neschimbate</th>
                            <th>Actiune speciala</th>
                        </tr>
                        </thead>
                        <tbody id="summaryRows"></tbody>
                    </table>
                </div>

                <h2 class="section-title">Disponibilitate produse pe locatii</h2>
                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Locatie</th>
                            <th>Asocieri noi</th>
                            <th>Schimbari disponibilitate</th>
                            <th>Asocieri eliminate</th>
                            <th>Observatie</th>
                        </tr>
                        </thead>
                        <tbody id="locationRows"></tbody>
                    </table>
                </div>

                <h2 class="section-title">Detalii care pot fi verificate</h2>
                <div id="detailSections"></div>

                <div class="actions">
                    <button type="button" id="applyButton" class="primary" disabled>
                        <span class="sync-loader" aria-hidden="true"></span>
                        CONFIRMA SI APLICA SINCRONIZAREA
                    </button>
                    <button type="button" id="reloadButton" class="reload">REFA PREVIEW</button>
                    <a class="button-link secondary" href="agecs_login.php">INAPOI LA OPERATORI</a>
                </div>
                <div id="result" class="result" role="status" aria-live="polite"></div>
            </div>
        </div>
    </section>
</main>

<script>
(function () {
    'use strict';

    var loading = document.getElementById('previewLoading');
    var content = document.getElementById('previewContent');
    var warning = document.getElementById('scopeWarning');
    var applyButton = document.getElementById('applyButton');
    var reloadButton = document.getElementById('reloadButton');
    var result = document.getElementById('result');
    var currentPreview = null;

    function number(value) {
        return new Intl.NumberFormat('ro-RO').format(Number(value || 0));
    }

    function setText(id, value) {
        document.getElementById(id).textContent = value;
    }

    function showResult(type, message) {
        result.className = 'result ' + type;
        result.textContent = message;
    }

    function setLoading(active) {
        loading.hidden = !active;
        if (active) {
            content.hidden = true;
            applyButton.disabled = true;
        }
    }

    function itemList(items, emptyText) {
        if (!Array.isArray(items) || items.length === 0) {
            return '<p class="empty-detail">' + emptyText + '</p>';
        }
        var html = '<ul>';
        items.forEach(function (item) {
            var locationText = '';
            if (Array.isArray(item.locations) && item.locations.length) {
                locationText = ' | locatii: ' + item.locations.map(function (location) {
                    return 'locatia ' + location.location + ' (' + (Number(location.active) === 1 ? 'activ' : 'inactiv') + ')';
                }).join(', ');
            } else if (Array.isArray(item.locations)) {
                locationText = ' | fara asociere de locatie primita din online';
            }
            html += '<li>' + escapeHtml((item.label || item.key || '') + locationText) + '</li>';
        });
        html += '</ul>';
        return html;
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function detail(title, count, items, emptyText) {
        var suffix = count > items.length ? ' Se afiseaza primele ' + items.length + '.' : '';
        return '<details><summary>' + escapeHtml(title) + ' (' + number(count) + ')</summary>'
            + '<div class="detail-body">' + itemList(items, emptyText) + '<p>' + escapeHtml(suffix) + '</p></div></details>';
    }

    function tableRow(label, data, action) {
        return '<tr>'
            + '<td>' + escapeHtml(label) + '</td>'
            + '<td class="num">' + number(data.online) + '</td>'
            + '<td class="num">' + number(data.local) + '</td>'
            + '<td class="num">' + number(data.new) + '</td>'
            + '<td class="num">' + number(data.updated) + '</td>'
            + '<td class="num">' + number(data.unchanged) + '</td>'
            + '<td class="action">' + escapeHtml(action || '') + '</td>'
            + '</tr>';
    }

    function locationBucketText(bucket, kind) {
        if (!bucket || !bucket.count) {
            return '0';
        }
        if (kind === 'new') {
            return number(bucket.count) + ' (active: ' + number(bucket.active) + ', inactive: ' + number(bucket.inactive) + ')';
        }
        if (kind === 'updated') {
            return number(bucket.count) + ' (activari: ' + number(bucket.became_active) + ', dezactivari: ' + number(bucket.became_inactive) + ')';
        }
        return number(bucket.count);
    }

    function renderLocationRows(productLocations) {
        var byLocation = productLocations.by_location || {};
        var grouped = {};
        ['new', 'updated', 'removed'].forEach(function (kind) {
            (byLocation[kind] || []).forEach(function (bucket) {
                var key = String(bucket.location);
                if (!grouped[key]) {
                    grouped[key] = { location: Number(bucket.location), newData: null, updatedData: null, removedData: null };
                }
                grouped[key][kind === 'new' ? 'newData' : (kind === 'updated' ? 'updatedData' : 'removedData')] = bucket;
            });
        });

        var locations = Object.keys(grouped).sort(function (left, right) {
            return grouped[left].location - grouped[right].location;
        });
        if (!locations.length) {
            return '<tr><td colspan="5" class="empty-detail">Nu exista modificari de disponibilitate pe locatii.</td></tr>';
        }

        return locations.map(function (key) {
            var row = grouped[key];
            var notes = [];
            if (row.newData && row.newData.active) notes.push(number(row.newData.active) + ' noi active');
            if (row.newData && row.newData.inactive) notes.push(number(row.newData.inactive) + ' noi inactive');
            if (row.updatedData && row.updatedData.became_active) notes.push(number(row.updatedData.became_active) + ' devin active');
            if (row.updatedData && row.updatedData.became_inactive) notes.push(number(row.updatedData.became_inactive) + ' devin inactive');
            if (row.removedData && row.removedData.count) notes.push(number(row.removedData.count) + ' iesite din lista');
            return '<tr>'
                + '<td>Locatia ' + escapeHtml(row.location) + '</td>'
                + '<td class="num">' + escapeHtml(locationBucketText(row.newData, 'new')) + '</td>'
                + '<td class="num">' + escapeHtml(locationBucketText(row.updatedData, 'updated')) + '</td>'
                + '<td class="num">' + escapeHtml(locationBucketText(row.removedData, 'removed')) + '</td>'
                + '<td>' + escapeHtml(notes.join(', ') || 'Fara diferente') + '</td>'
                + '</tr>';
        }).join('');
    }

    function renderPreview(data) {
        var preview = data.preview || {};
        var scope = preview.scope || {};
        var products = preview.products || {};
        var categories = preview.categories || {};
        var gestiuni = preview.gestiuni || {};
        var vatRates = preview.vat_rates || {};
        var categoryLocations = preview.category_locations || {};
        var productLocations = preview.product_locations || {};
        var removedMappings = Number(categoryLocations.removed || 0) + Number(productLocations.removed || 0);

        setText('scopeClient', scope.client_id || 2);
        setText('scopeLocation', scope.location || 2);
        setText('scopeMappings', scope.mapping_scope || 'locatia 2');
        setText('statNew', number(products.new));
        setText('statUpdated', number(products.updated));
        setText('statDeactivate', scope.products_complete ? number(products.deactivate) : '-');
        setText('statUnchanged', number(products.unchanged));
        setText('statRemoved', number(removedMappings));
        setText('statOnline', number(products.online));

        warning.style.display = 'none';
        warning.textContent = '';
        var warnings = [];
        if (!scope.products_complete) {
            warnings.push('Lista completa de produse nu a fost confirmata. Produsele lipsa nu vor fi dezactivate pana cand raspunsul online nu contine toate produsele.');
        }
        if (!scope.all_locations_included) {
            warnings.push('Online nu a confirmat includerea tuturor locatiilor. Preview-ul si sincronizarea asocierilor sunt limitate la locatia 2.');
        }
        if (!scope.product_locations_available) {
            warnings.push('Online nu a trimis tabela de asocieri produse pe locatii. Pentru aceasta parte nu se aplica stergeri.');
        }
        if (Number(categoryLocations.local || 0) > 0 && Number(categoryLocations.online || 0) === 0) {
            warnings.push('Online nu a trimis asocieri de categorii pe locatii. Aceste asocieri raman local si nu se sterg automat.');
        }
        if (Number(productLocations.local || 0) > 0 && Number(productLocations.online || 0) === 0) {
            warnings.push('Online nu a trimis randuri pentru asocierile produselor pe locatii. Asocierile locale raman intacte.');
        }
        if (warnings.length) {
            warning.style.display = 'block';
            warning.textContent = warnings.join(' ');
        }

        var rows = '';
        rows += tableRow('Produse', products, scope.products_complete ? number(products.deactivate) + ' dezactivari' : 'fara dezactivare');
        rows += tableRow('Categorii', categories, 'nu se sterg');
        rows += tableRow('Gestiuni', gestiuni, 'nu se sterg');
        rows += tableRow('Cote TVA', vatRates, 'nu se sterg');
        rows += tableRow('Asocieri categorii pe locatii', categoryLocations, number(categoryLocations.removed || 0) + ' eliminate');
        rows += tableRow('Asocieri produse pe locatii', productLocations, productLocations.enabled ? number(productLocations.removed || 0) + ' eliminate' : 'neactivate');
        document.getElementById('summaryRows').innerHTML = rows;
        document.getElementById('locationRows').innerHTML = renderLocationRows(productLocations);

        var details = '';
        details += detail('Produse noi care vor fi adaugate', products.new || 0, products.new_items || [], 'Nu exista produse noi.');
        details += detail('Produse existente care vor fi actualizate', products.updated || 0, products.updated_items || [], 'Nu exista diferente de actualizat.');
        details += detail('Produse care vor fi dezactivate, fara stergere', scope.products_complete ? (products.deactivate || 0) : 0, products.deactivate_items || [], 'Nu exista produse de dezactivat.');
        details += detail('Asocieri produse pe locatii care vor fi eliminate', productLocations.removed || 0, productLocations.removed_items || [], 'Nu exista asocieri de eliminat.');
        details += detail('Asocieri categorii pe locatii care vor fi eliminate', categoryLocations.removed || 0, categoryLocations.removed_items || [], 'Nu exista asocieri de eliminat.');
        document.getElementById('detailSections').innerHTML = details;

        currentPreview = data;
        content.hidden = false;
        applyButton.disabled = false;
    }

    function readJson(response) {
        return response.text().then(function (text) {
            var data;
            try {
                data = JSON.parse(text);
            } catch (error) {
                data = { status: 'error', message: text || 'Raspuns invalid de la server.' };
            }
            if (!response.ok) {
                data.status = 'error';
            }
            return data;
        });
    }

    function loadPreview() {
        setLoading(true);
        warning.style.display = 'none';
        showResult('info', 'Se solicita un preview nou de la online.');
        fetch('offline_products_sync.php?preview=1', {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
            cache: 'no-store'
        })
            .then(readJson)
            .then(function (data) {
                if (data.status !== 'preview') {
                    throw new Error(data.message || 'Preview-ul nu a putut fi generat.');
                }
                renderPreview(data);
                showResult('info', 'Preview generat. Baza locala nu a fost modificata.');
            })
            .catch(function (error) {
                content.hidden = true;
                showResult('error', error && error.message ? error.message : 'Preview-ul nu a putut fi generat.');
            })
            .finally(function () {
                loading.hidden = true;
            });
    }

    applyButton.addEventListener('click', function () {
        if (!currentPreview || !window.confirm('Aplica sincronizarea dupa acest preview?')) {
            return;
        }
        applyButton.disabled = true;
        applyButton.classList.add('running');
        showResult('info', 'Se aplica sincronizarea. Asteapta raspunsul serverului.');
        fetch('offline_products_sync.php?force=1', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
            cache: 'no-store'
        })
            .then(readJson)
            .then(function (data) {
                if (data.status !== 'synced' && data.status !== 'unchanged') {
                    throw new Error(data.message || 'Sincronizarea catalogului nu a reusit.');
                }
                showResult('success', 'Sincronizarea s-a finalizat. Se reincarca preview-ul pentru verificare.');
                loadPreview();
            })
            .catch(function (error) {
                showResult('error', error && error.message ? error.message : 'Sincronizarea catalogului nu a reusit.');
                applyButton.disabled = false;
            })
            .finally(function () {
                applyButton.classList.remove('running');
            });
    });

    reloadButton.addEventListener('click', loadPreview);
    loadPreview();
}());
</script>
</body>
</html>
