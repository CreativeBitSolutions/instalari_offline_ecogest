<style>
    #offlineSyncStatusModal .modal-dialog { max-width: 1180px; }
    #offlineSyncStatusModal .modal-content { border-radius: 6px; }
    #offlineSyncStatusModal .sync-summary { border-left: 5px solid #64748b; padding: 12px 14px; background: #f8fafc; }
    #offlineSyncStatusModal .sync-summary.is-ok { border-color: #198754; background: #edf8f1; }
    #offlineSyncStatusModal .sync-summary.is-waiting { border-color: #d97706; background: #fff7e6; }
    #offlineSyncStatusModal .sync-summary.is-error { border-color: #dc3545; background: #fff0f1; }
    #offlineSyncStatusModal .sync-counts { display: grid; grid-template-columns: repeat(5, minmax(120px, 1fr)); gap: 8px; margin: 14px 0; }
    #offlineSyncStatusModal .sync-count { border: 1px solid #d9dee5; border-radius: 6px; padding: 10px; background: #fff; }
    #offlineSyncStatusModal .sync-count strong { display: block; font-size: 23px; line-height: 1.15; }
    #offlineSyncStatusModal .sync-count span { color: #52606d; font-size: 12px; }
    #offlineSyncStatusModal .sync-meta { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; margin-bottom: 14px; }
    #offlineSyncStatusModal .sync-meta div { border-bottom: 1px solid #e5e7eb; padding: 6px 0; }
    #offlineSyncStatusModal .sync-meta span { display: block; color: #64748b; font-size: 12px; }
    #offlineSyncStatusModal .sync-tabs { flex-wrap: nowrap; overflow-x: auto; overflow-y: hidden; border-bottom: 1px solid #cfd6dd; }
    #offlineSyncStatusModal .sync-tabs .nav-link { color: #344054; border-radius: 4px 4px 0 0; white-space: nowrap; padding: 9px 12px; cursor: pointer; }
    #offlineSyncStatusModal .sync-tabs .nav-link.active { color: #0b5ed7; font-weight: 700; background: #fff; border-color: #cfd6dd #cfd6dd #fff; }
    #offlineSyncStatusModal .sync-tab-count { display: inline-block; min-width: 20px; margin-left: 4px; padding: 1px 5px; border-radius: 9px; background: #e9ecef; color: #344054; font-size: 11px; text-align: center; }
    #offlineSyncStatusModal .sync-table-header { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin: 12px 0 7px; }
    #offlineSyncStatusModal .sync-table-wrap { max-height: 330px; overflow: auto; border: 1px solid #dee2e6; }
    #offlineSyncStatusModal table { margin: 0; font-size: 13px; }
    #offlineSyncStatusModal thead th { position: sticky; top: 0; z-index: 1; background: #f1f3f5; border-top: 0; white-space: nowrap; }
    #offlineSyncStatusModal tbody td { white-space: nowrap; vertical-align: middle; }
    #offlineSyncStatusModal .sync-error-text { max-width: 260px; white-space: normal; color: #b42318; }
    #offlineSyncStatusModal .sync-status { display: inline-block; border-radius: 4px; padding: 3px 7px; color: #fff; font-weight: 600; white-space: nowrap; }
    #offlineSyncStatusModal .sync-status.sent { background: #198754; }
    #offlineSyncStatusModal .sync-status.pending,
    #offlineSyncStatusModal .sync-status.sending { background: #0d6efd; }
    #offlineSyncStatusModal .sync-status.retry { background: #d97706; }
    #offlineSyncStatusModal .sync-status.blocked { background: #dc3545; }
    @media (max-width: 760px) {
        #offlineSyncStatusModal .sync-counts { grid-template-columns: repeat(2, minmax(110px, 1fr)); }
        #offlineSyncStatusModal .sync-meta { grid-template-columns: 1fr; }
    }
</style>

<div class="modal fade" id="offlineSyncStatusModal" tabindex="-1" role="dialog" aria-labelledby="offlineSyncStatusTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="offlineSyncStatusTitle">Situație transmitere date online</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Închide"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div id="offlineSyncSummary" class="sync-summary">Se citesc datele de sincronizare...</div>

                <div class="sync-counts">
                    <div class="sync-count"><strong id="syncCountSent">0</strong><span>Elemente confirmate</span></div>
                    <div class="sync-count"><strong id="syncCountPending">0</strong><span>Elemente în așteptare</span></div>
                    <div class="sync-count"><strong id="syncCountSending">0</strong><span>Elemente în curs</span></div>
                    <div class="sync-count"><strong id="syncCountRetry">0</strong><span>Elemente de reîncercat</span></div>
                    <div class="sync-count"><strong id="syncCountBlocked">0</strong><span>Elemente blocate</span></div>
                </div>

                <div class="sync-meta">
                    <div><span>Client și locație</span><strong id="syncClientLocation">-</strong></div>
                    <div><span>Ultima confirmare online</span><strong id="syncLastSuccess">-</strong></div>
                    <div><span>Ultima verificare automată</span><strong id="syncLastTick">-</strong></div>
                </div>

                <div id="offlineSyncRuntimeError" class="alert alert-danger d-none"></div>

                <ul class="nav nav-tabs sync-tabs" id="offlineSyncTabs" role="tablist"></ul>
                <div class="sync-table-header">
                    <strong id="offlineSyncTableTitle">Elemente sincronizate</strong>
                    <small class="text-muted" id="syncGeneratedAt"></small>
                </div>
                <div class="sync-table-wrap">
                    <table class="table table-sm table-striped">
                        <thead><tr id="offlineSyncTableHead"></tr></thead>
                        <tbody id="offlineSyncTableBody">
                            <tr><td class="text-center text-muted py-3">Se încarcă...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="offlineSyncRefresh" class="btn btn-primary"><i class="fas fa-sync-alt"></i> Actualizează</button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Închide</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    var activeTab = 'note';
    var currentData = null;
    var statusLabels = {
        sent: 'Confirmat',
        pending: 'În așteptare',
        sending: 'În curs',
        retry: 'Reîncercare',
        blocked: 'Blocat'
    };
    var typeLabels = {
        sale: 'Vânzare',
        nir: 'NIR',
        cash_adjustment: 'Reglare casă'
    };
    var tableDefinitions = [
        { key: 'note', label: 'Note', columns: [['nrbon', 'Bon'], ['data_bon', 'Data'], ['ora_bon', 'Ora'], ['operator_nume', 'Operator'], ['valoare_vanzare_cu_tva', 'Total cu TVA'], ['tva_colectata', 'TVA'], ['numerar', 'Numerar'], ['card', 'Card'], ['cod_inchidere', 'Închidere'], ['nr_raport_z', 'Raport Z']] },
        { key: 'det_note', label: 'Detalii note', columns: [['id_vanz', 'ID linie'], ['nr_bon', 'Bon'], ['nume_produs', 'Produs'], ['cantitate', 'Cantitate'], ['pret_vanzare', 'Preț'], ['valoare_vanzare_cu_tva', 'Valoare'], ['discount', 'Discount'], ['cota_tva', 'TVA %']] },
        { key: 'discounturi_acordate', label: 'Discounturi', columns: [['id_discount', 'ID'], ['id_vanz', 'ID linie'], ['operator_nume', 'Operator'], ['valoare_discount', 'Valoare'], ['procent_discount', 'Procent'], ['data', 'Data'], ['ora', 'Ora']] },
        { key: 'bonuri_casa_marcat', label: 'Bonuri casă', columns: [['id', 'ID'], ['nrbon', 'Bon'], ['data', 'Data'], ['ora', 'Ora'], ['locatie', 'Locație']] },
        { key: 'inchideri_r_12', label: 'Închideri', columns: [['id_inch', 'ID'], ['cod_inchidere', 'Închidere'], ['operator_nume', 'Operator'], ['data_inchiderii', 'Data'], ['ora_inchiderii', 'Ora'], ['valoare_cu_tva', 'Total cu TVA'], ['tva_colectata', 'TVA'], ['nr_raport_z', 'Raport Z']] },
        { key: 'rapoarte_z', label: 'Rapoarte Z', columns: [['id', 'ID'], ['nr_raport_z', 'Raport Z'], ['data_ora_raport_z', 'Data și ora'], ['serie_casa_marcat', 'Serie CM'], ['numerar', 'Numerar'], ['card', 'Card'], ['credit', 'Credit'], ['tichete_masa', 'Tichete'], ['plata_moderna', 'Online'], ['alte_metode', 'Alte metode']] },
        { key: 'nir', label: 'NIR', columns: [['id_nir', 'ID'], ['nr_nir', 'NIR'], ['data_nir', 'Data'], ['ora_nir', 'Ora'], ['furnizor', 'Furnizor'], ['nr_factura', 'Factură'], ['valoare_cu_tva', 'Total cu TVA'], ['tva', 'TVA']] },
        { key: 'achizitii', label: 'Achiziții', columns: [['id_achiz', 'ID'], ['nr_nir', 'NIR'], ['cod_produs', 'Cod produs'], ['nume_produs', 'Produs'], ['cantitate', 'Cantitate'], ['pret_achizitie', 'Preț achiziție'], ['valoare_cu_tva', 'Valoare'], ['cota_tva', 'TVA %']] },
        { key: 'miscari', label: 'Mișcări', columns: [['id', 'ID'], ['fel_doc', 'Document'], ['nr_doc', 'Nr. document'], ['nr_nota', 'Bon'], ['cod_p', 'Cod produs'], ['denumire_produs', 'Produs'], ['tip_miscare', 'Tip'], ['cantitate_misc', 'Cantitate'], ['gestiune', 'Gestiune'], ['data', 'Data']] },
        { key: 'log_reglari_casa_marcat', label: 'Reglări casă', columns: [['id', 'ID'], ['operator_nume', 'Operator'], ['tip_reglare', 'Tip'], ['suma', 'Sumă'], ['motiv', 'Motiv'], ['data', 'Data'], ['ora', 'Ora']] }
    ];

    function setText(id, value) {
        var element = document.getElementById(id);
        if (element) {
            element.textContent = value === null || value === undefined || value === '' ? '-' : String(value);
        }
    }

    function statusBadge(status) {
        var badge = document.createElement('span');
        badge.className = 'sync-status ' + status;
        badge.textContent = statusLabels[status] || status;
        return badge;
    }

    function addCell(row, value, className) {
        var cell = document.createElement('td');
        cell.textContent = value === null || value === undefined || value === '' ? '-' : String(value);
        if (className) {
            cell.className = className;
        }
        row.appendChild(cell);
    }

    function renderElementTable(definition) {
        var table = currentData.tables[definition.key] || { total: 0, rows: [] };
        var head = document.getElementById('offlineSyncTableHead');
        var body = document.getElementById('offlineSyncTableBody');
        head.innerHTML = '';
        body.innerHTML = '';
        setText('offlineSyncTableTitle', definition.label + ' (' + table.total + ')');

        definition.columns.concat([['__status', 'Stare'], ['__sent', 'Confirmat online'], ['__response', 'Răspuns']]).forEach(function (column) {
            var th = document.createElement('th');
            th.textContent = column[1];
            head.appendChild(th);
        });

        if (!table.rows || table.rows.length === 0) {
            var empty = document.createElement('tr');
            var emptyCell = document.createElement('td');
            emptyCell.colSpan = definition.columns.length + 3;
            emptyCell.className = 'text-center text-muted py-3';
            emptyCell.textContent = 'Nu există elemente în această categorie.';
            empty.appendChild(emptyCell);
            body.appendChild(empty);
            return;
        }

        table.rows.forEach(function (item) {
            var row = document.createElement('tr');
            definition.columns.forEach(function (column) {
                addCell(row, item.data[column[0]]);
            });
            var statusCell = document.createElement('td');
            statusCell.appendChild(statusBadge(item.transmission.status));
            row.appendChild(statusCell);
            addCell(row, item.transmission.sent_at);
            addCell(row, item.transmission.last_error || (item.transmission.last_http_code ? 'HTTP ' + item.transmission.last_http_code : '-'), item.transmission.last_error ? 'sync-error-text' : '');
            body.appendChild(row);
        });
    }

    function renderTransmissionTable() {
        var head = document.getElementById('offlineSyncTableHead');
        var body = document.getElementById('offlineSyncTableBody');
        var columns = ['Operațiune', 'Identificator', 'Locație', 'Stare', 'Încercări', 'Creat', 'Confirmat', 'Răspuns'];
        head.innerHTML = '';
        body.innerHTML = '';
        setText('offlineSyncTableTitle', 'Istoric transmiteri (' + currentData.events.length + ')');
        columns.forEach(function (label) {
            var th = document.createElement('th');
            th.textContent = label;
            head.appendChild(th);
        });

        if (!currentData.events.length) {
            var empty = document.createElement('tr');
            empty.innerHTML = '<td colspan="8" class="text-center text-muted py-3">Nu există transmiteri înregistrate.</td>';
            body.appendChild(empty);
            return;
        }

        currentData.events.forEach(function (event) {
            var row = document.createElement('tr');
            addCell(row, typeLabels[event.aggregate_type] || event.aggregate_type || event.event_type);
            addCell(row, event.aggregate_id);
            addCell(row, event.cod_locatie);
            var statusCell = document.createElement('td');
            statusCell.appendChild(statusBadge(event.status));
            row.appendChild(statusCell);
            addCell(row, event.attempts);
            addCell(row, event.created_at);
            addCell(row, event.sent_at);
            addCell(row, event.last_error || (event.last_http_code ? 'HTTP ' + event.last_http_code : '-'), event.last_error ? 'sync-error-text' : '');
            body.appendChild(row);
        });
    }

    function activateTab(key) {
        activeTab = key;
        document.querySelectorAll('#offlineSyncTabs .nav-link').forEach(function (tab) {
            tab.classList.toggle('active', tab.getAttribute('data-sync-tab') === key);
        });
        if (key === '__transmissions') {
            renderTransmissionTable();
            return;
        }
        var definition = tableDefinitions.find(function (item) { return item.key === key; });
        if (definition) {
            renderElementTable(definition);
        }
    }

    function renderTabs(data) {
        var tabs = document.getElementById('offlineSyncTabs');
        tabs.innerHTML = '';
        var available = tableDefinitions.filter(function (definition) {
            return data.tables[definition.key] && data.tables[definition.key].total > 0;
        });
        available.push({ key: '__transmissions', label: 'Transmiteri', total: data.events.length });

        available.forEach(function (definition) {
            var item = document.createElement('li');
            item.className = 'nav-item';
            var link = document.createElement('button');
            link.type = 'button';
            link.className = 'nav-link';
            link.setAttribute('data-sync-tab', definition.key);
            link.appendChild(document.createTextNode(definition.label));
            var count = document.createElement('span');
            count.className = 'sync-tab-count';
            count.textContent = definition.key === '__transmissions' ? definition.total : data.tables[definition.key].total;
            link.appendChild(count);
            link.addEventListener('click', function () { activateTab(definition.key); });
            item.appendChild(link);
            tabs.appendChild(item);
        });

        if (!available.some(function (definition) { return definition.key === activeTab; })) {
            activeTab = available[0].key;
        }
        activateTab(activeTab);
    }

    function renderStatus(data) {
        currentData = data;
        var counts = data.counts || {};
        var pending = Number(counts.pending || 0);
        var retry = Number(counts.retry || 0);
        var sending = Number(counts.sending || 0);
        var sent = Number(counts.sent || 0);
        var blocked = Number(counts.blocked || 0);
        var summary = document.getElementById('offlineSyncSummary');

        setText('syncCountSent', sent);
        setText('syncCountPending', pending);
        setText('syncCountSending', sending);
        setText('syncCountRetry', retry);
        setText('syncCountBlocked', blocked);
        setText('syncClientLocation', 'Client ' + data.client_id + ', locația ' + data.cod_locatie);
        setText('syncLastSuccess', data.runtime && data.runtime.last_success_at);
        setText('syncLastTick', data.runtime && data.runtime.last_tick_at);
        setText('syncGeneratedAt', data.generated_at ? 'Actualizat: ' + data.generated_at : '');

        summary.className = 'sync-summary';
        if (blocked > 0) {
            summary.classList.add('is-error');
            summary.textContent = blocked + ' elemente sunt blocate și necesită verificare.';
        } else if (pending + retry + sending > 0) {
            summary.classList.add('is-waiting');
            summary.textContent = (pending + retry + sending) + ' elemente urmează să fie confirmate de aplicația online.';
        } else if (sent > 0) {
            summary.classList.add('is-ok');
            summary.textContent = 'Toate elementele afișate au fost confirmate de aplicația online.';
        } else {
            summary.textContent = 'Nu există încă elemente în coada de transmitere.';
        }

        var runtimeError = document.getElementById('offlineSyncRuntimeError');
        var errorText = data.runtime && data.runtime.last_error ? data.runtime.last_error : '';
        runtimeError.textContent = errorText;
        runtimeError.classList.toggle('d-none', !errorText);
        renderTabs(data);
    }

    function loadStatus() {
        var refresh = document.getElementById('offlineSyncRefresh');
        if (refresh) {
            refresh.disabled = true;
        }
        fetch('offline_sync_status.php?ts=' + Date.now(), {
            headers: { 'Accept': 'application/json' },
            cache: 'no-store'
        }).then(function (response) {
            return response.json().then(function (data) {
                if (!response.ok || data.status !== 'success') {
                    throw new Error(data.message || 'Situația sincronizării nu poate fi citită.');
                }
                return data;
            });
        }).then(renderStatus).catch(function (error) {
            var summary = document.getElementById('offlineSyncSummary');
            summary.className = 'sync-summary is-error';
            summary.textContent = error.message;
        }).finally(function () {
            if (refresh) {
                refresh.disabled = false;
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        $('#offlineSyncStatusModal').on('show.bs.modal', loadStatus);
        document.getElementById('offlineSyncRefresh').addEventListener('click', loadStatus);
    });
}());
</script>
