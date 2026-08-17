<style>
    #restaurantSyncStatusModal {
        --rsync-ink: #17212b;
        --rsync-slate: #425466;
        --rsync-line: #d9e1e8;
        --rsync-paper: #f7f5ef;
        --rsync-green: #16815d;
        --rsync-amber: #c77813;
        --rsync-red: #b63838;
        --rsync-blue: #2c6d9b;
    }
    #restaurantSyncStatusModal .modal-dialog { max-width: 1240px; }
    #restaurantSyncStatusModal .modal-content { border: 0; border-radius: 10px; overflow: hidden; box-shadow: 0 26px 80px rgba(12, 24, 36, .34); }
    #restaurantSyncStatusModal .rsync-header { background: var(--rsync-ink); color: #fff; border: 0; padding: 17px 22px; }
    #restaurantSyncStatusModal .rsync-header .close { color: #fff; opacity: .8; text-shadow: none; }
    #restaurantSyncStatusModal .rsync-kicker { display: block; color: #9fc4d8; font-size: 11px; font-weight: 700; letter-spacing: .13em; text-transform: uppercase; }
    #restaurantSyncStatusModal .modal-title { font-family: Georgia, 'Times New Roman', serif; font-size: 24px; line-height: 1.1; }
    #restaurantSyncStatusModal .modal-body { background: var(--rsync-paper); padding: 18px 20px 20px; color: var(--rsync-ink); }
    #restaurantSyncStatusModal .rsync-summary { display: flex; align-items: center; gap: 13px; padding: 13px 15px; border: 1px solid var(--rsync-line); border-left: 6px solid var(--rsync-slate); background: #fff; border-radius: 7px; }
    #restaurantSyncStatusModal .rsync-summary.is-ok { border-left-color: var(--rsync-green); }
    #restaurantSyncStatusModal .rsync-summary.is-waiting { border-left-color: var(--rsync-amber); }
    #restaurantSyncStatusModal .rsync-summary.is-error { border-left-color: var(--rsync-red); }
    #restaurantSyncStatusModal .rsync-summary.is-running { border-left-color: var(--rsync-blue); }
    #restaurantSyncStatusModal .rsync-summary-icon { width: 38px; height: 38px; border-radius: 50%; display: grid; place-items: center; flex: 0 0 38px; background: #edf2f6; font-size: 18px; }
    #restaurantSyncStatusModal .rsync-summary strong { display: block; font-size: 15px; }
    #restaurantSyncStatusModal .rsync-summary small { color: #5d6b77; }
    #restaurantSyncStatusModal .rsync-counts { display: grid; grid-template-columns: repeat(6, minmax(125px, 1fr)); gap: 9px; margin: 12px 0; }
    #restaurantSyncStatusModal .rsync-count { min-height: 82px; padding: 11px 12px; border: 1px solid var(--rsync-line); border-radius: 7px; background: #fff; }
    #restaurantSyncStatusModal .rsync-count strong { display: block; font-family: Georgia, 'Times New Roman', serif; font-size: 28px; line-height: 1; }
    #restaurantSyncStatusModal .rsync-count span { display: block; margin-top: 7px; color: #5b6975; font-size: 11px; line-height: 1.2; text-transform: uppercase; letter-spacing: .04em; }
    #restaurantSyncStatusModal .rsync-count.is-ready strong { color: var(--rsync-amber); }
    #restaurantSyncStatusModal .rsync-count.is-confirmed strong { color: var(--rsync-green); }
    #restaurantSyncStatusModal .rsync-meta { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1px; overflow: hidden; margin-bottom: 13px; border: 1px solid var(--rsync-line); border-radius: 7px; background: var(--rsync-line); }
    #restaurantSyncStatusModal .rsync-meta > div { min-height: 66px; padding: 10px 12px; background: #fff; }
    #restaurantSyncStatusModal .rsync-meta span { display: block; margin-bottom: 3px; color: #70808e; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
    #restaurantSyncStatusModal .rsync-meta strong { display: block; font-size: 13px; overflow-wrap: anywhere; }
    #restaurantSyncStatusModal .rsync-tabs { display: flex; gap: 4px; padding: 0; margin: 0; border-bottom: 2px solid var(--rsync-ink); list-style: none; overflow-x: auto; }
    #restaurantSyncStatusModal .rsync-tab { border: 1px solid var(--rsync-line); border-bottom: 0; border-radius: 6px 6px 0 0; padding: 9px 13px; background: #e9edf0; color: #3d4c59; font-weight: 700; white-space: nowrap; }
    #restaurantSyncStatusModal .rsync-tab.active { background: var(--rsync-ink); color: #fff; border-color: var(--rsync-ink); }
    #restaurantSyncStatusModal .rsync-tab-count { display: inline-block; min-width: 22px; margin-left: 5px; padding: 1px 6px; border-radius: 10px; background: rgba(255,255,255,.2); text-align: center; font-size: 11px; }
    #restaurantSyncStatusModal .rsync-tab:not(.active) .rsync-tab-count { background: #fff; }
    #restaurantSyncStatusModal .rsync-table-headline { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 10px 1px 7px; }
    #restaurantSyncStatusModal .rsync-table-headline small { color: #6d7a85; }
    #restaurantSyncStatusModal .rsync-table-wrap { max-height: 310px; overflow: auto; border: 1px solid var(--rsync-line); background: #fff; }
    #restaurantSyncStatusModal table { margin: 0; font-size: 12px; }
    #restaurantSyncStatusModal thead th { position: sticky; top: 0; z-index: 1; border-top: 0; background: #edf1f4; color: #34414d; white-space: nowrap; }
    #restaurantSyncStatusModal tbody td { vertical-align: middle; white-space: nowrap; }
    #restaurantSyncStatusModal tbody tr.is-clickable { cursor: pointer; }
    #restaurantSyncStatusModal tbody tr.is-clickable:hover { background: #eef6f7; }
    #restaurantSyncStatusModal .rsync-badge { display: inline-block; min-width: 78px; padding: 4px 7px; border-radius: 4px; color: #fff; font-size: 11px; font-weight: 700; text-align: center; }
    #restaurantSyncStatusModal .rsync-badge.success { background: var(--rsync-green); }
    #restaurantSyncStatusModal .rsync-badge.empty { background: #687683; }
    #restaurantSyncStatusModal .rsync-badge.error { background: var(--rsync-red); }
    #restaurantSyncStatusModal .rsync-badge.waiting { background: var(--rsync-amber); }
    #restaurantSyncStatusModal .rsync-badge.running { background: var(--rsync-blue); }
    #restaurantSyncStatusModal .rsync-detail { display: none; margin-top: 11px; border: 1px solid #bcc9d2; border-radius: 7px; background: #fff; }
    #restaurantSyncStatusModal .rsync-detail.is-visible { display: block; animation: rsyncReveal .18s ease-out; }
    #restaurantSyncStatusModal .rsync-detail-title { display: flex; justify-content: space-between; gap: 10px; padding: 10px 13px; background: #e9eef1; border-bottom: 1px solid var(--rsync-line); }
    #restaurantSyncStatusModal .rsync-detail-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1px; background: var(--rsync-line); }
    #restaurantSyncStatusModal .rsync-detail-grid > div { padding: 9px 12px; background: #fff; min-height: 59px; }
    #restaurantSyncStatusModal .rsync-detail-grid span { display: block; color: #71808c; font-size: 10px; text-transform: uppercase; letter-spacing: .05em; }
    #restaurantSyncStatusModal .rsync-detail-grid strong { display: block; margin-top: 2px; font-size: 12px; overflow-wrap: anywhere; }
    #restaurantSyncStatusModal .rsync-message { padding: 10px 12px; border-top: 1px solid var(--rsync-line); color: #3e4b56; font-size: 12px; white-space: pre-wrap; overflow-wrap: anywhere; }
    #restaurantSyncStatusModal .rsync-message.is-error { color: #8e2828; background: #fff4f3; }
    #restaurantSyncStatusModal .rsync-empty { padding: 30px 15px; color: #73808b; text-align: center; }
    #restaurantSyncStatusModal .modal-footer { background: #fff; border-top: 1px solid var(--rsync-line); }
    @keyframes rsyncReveal { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: translateY(0); } }
    @media (max-width: 980px) {
        #restaurantSyncStatusModal .rsync-counts { grid-template-columns: repeat(3, minmax(120px, 1fr)); }
        #restaurantSyncStatusModal .rsync-meta, #restaurantSyncStatusModal .rsync-detail-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 580px) {
        #restaurantSyncStatusModal .modal-body { padding: 12px; }
        #restaurantSyncStatusModal .rsync-counts { grid-template-columns: repeat(2, minmax(105px, 1fr)); }
        #restaurantSyncStatusModal .rsync-meta, #restaurantSyncStatusModal .rsync-detail-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="modal fade" id="restaurantSyncStatusModal" tabindex="-1" role="dialog" aria-labelledby="restaurantSyncStatusTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header rsync-header">
                <div>
                    <span class="rsync-kicker">Registru operațional</span>
                    <h5 class="modal-title" id="restaurantSyncStatusTitle">Situație trimiteri către online</h5>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Închide"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div id="restaurantSyncSummary" class="rsync-summary">
                    <span class="rsync-summary-icon"><i class="fas fa-cloud-upload-alt"></i></span>
                    <div><strong>Se citesc datele locale.</strong><small>Jurnalul și notele sunt verificate.</small></div>
                </div>

                <div class="rsync-counts">
                    <div class="rsync-count is-ready"><strong id="rsyncEligible">0</strong><span>Note F pregătite</span></div>
                    <div class="rsync-count"><strong id="rsyncLines">0</strong><span>Linii pregătite</span></div>
                    <div class="rsync-count"><strong id="rsyncClosure">0</strong><span>Fără închidere</span></div>
                    <div class="rsync-count"><strong id="rsyncZ">0</strong><span>Fără raport Z</span></div>
                    <div class="rsync-count"><strong id="rsyncOpen">0</strong><span>Note încă deschise</span></div>
                    <div class="rsync-count is-confirmed"><strong id="rsyncExported">0</strong><span>Note confirmate online</span></div>
                </div>

                <div class="rsync-meta">
                    <div><span>Instalare</span><strong id="rsyncInstallation">...</strong></div>
                    <div><span>Mod de lucru</span><strong id="rsyncMode">...</strong></div>
                    <div><span>Ultima confirmare online</span><strong id="rsyncLastSuccess">...</strong></div>
                    <div><span>Ultima eroare</span><strong id="rsyncLastFailure">...</strong></div>
                </div>

                <ul class="rsync-tabs" role="tablist">
                    <li><button type="button" class="rsync-tab active" data-rsync-tab="pending">Pregătite pentru trimitere <span id="rsyncPendingTabCount" class="rsync-tab-count">0</span></button></li>
                    <li><button type="button" class="rsync-tab" data-rsync-tab="blocked">Așteaptă închidere sau Z <span id="rsyncBlockedTabCount" class="rsync-tab-count">0</span></button></li>
                    <li><button type="button" class="rsync-tab" data-rsync-tab="history">Istoric pachete <span id="rsyncHistoryTabCount" class="rsync-tab-count">0</span></button></li>
                    <li><button type="button" class="rsync-tab" data-rsync-tab="tablet">Comenzi tabletă <span id="rsyncTabletTabCount" class="rsync-tab-count">0</span></button></li>
                </ul>

                <div class="rsync-table-headline">
                    <strong id="rsyncTableTitle">Note pregătite pentru următoarea trimitere</strong>
                    <small id="rsyncGeneratedAt">Actualizare automată la 20 secunde</small>
                </div>
                <div class="rsync-table-wrap">
                    <table class="table table-sm table-hover">
                        <thead><tr id="rsyncTableHead"></tr></thead>
                        <tbody id="rsyncTableBody"><tr><td class="rsync-empty">Se încarcă...</td></tr></tbody>
                    </table>
                </div>

                <div id="rsyncDetail" class="rsync-detail" aria-live="polite"></div>
            </div>
            <div class="modal-footer">
                <small class="mr-auto text-muted"><i class="fas fa-stream"></i> Nota F, închiderea și raportul Z circulă prin evenimente separate.</small>
                <button type="button" id="restaurantSyncRetry" class="btn btn-warning"><i class="fas fa-cloud-upload-alt"></i> Retrimite acum</button>
                <button type="button" id="restaurantSyncRefresh" class="btn btn-primary"><i class="fas fa-redo-alt"></i> Actualizează</button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Închide</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    var currentData = null;
    var activeTab = 'pending';
    var refreshTimer = null;

    function setText(id, value, fallback) {
        var element = document.getElementById(id);
        if (!element) return;
        element.textContent = value === null || value === undefined || value === '' ? (fallback || '0') : String(value);
    }

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function money(value) {
        return Number(value || 0).toLocaleString('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' lei';
    }

    function bytes(value) {
        var amount = Number(value || 0);
        if (!amount) return '0 KB';
        return (amount / 1024).toLocaleString('ro-RO', { maximumFractionDigits: 1 }) + ' KB';
    }

    function duration(value) {
        var ms = Number(value || 0);
        if (ms < 1000) return ms + ' ms';
        return (ms / 1000).toLocaleString('ro-RO', { maximumFractionDigits: 2 }) + ' sec';
    }

    function attemptStatus(item) {
        if (item.status === 'success_online' || item.status === 'success') return ['success', 'Confirmat'];
        if (item.status === 'empty') return ['empty', 'Fără date'];
        if (item.status === 'pending') return ['waiting', 'În așteptare'];
        if (item.status === 'sending') return ['running', 'În curs'];
        if (item.status === 'retry') return ['waiting', 'Reîncercare'];
        if (item.status === 'blocked') return ['error', 'Blocat'];
        if (item.status === 'success_local_online_error') return ['waiting', 'Local, neconfirmat'];
        return ['error', 'Eroare'];
    }

    function countBreakdown(values) {
        var labels = {
            note: 'note', det_note: 'linii', inchideri_r_12: 'închideri',
            rapoarte_z: 'rapoarte Z', discounturi_acordate: 'discounturi', miscari: 'mișcări'
        };
        var parts = [];
        Object.keys(labels).forEach(function (key) {
            if (Number(values && values[key] || 0) > 0) parts.push(labels[key] + ': ' + Number(values[key]));
        });
        return parts.length ? parts.join(', ') : '0 elemente';
    }

    function renderHead(columns) {
        var head = document.getElementById('rsyncTableHead');
        head.innerHTML = columns.map(function (column) { return '<th>' + escapeHtml(column) + '</th>'; }).join('');
    }

    function renderEmpty(colspan, text) {
        document.getElementById('rsyncTableBody').innerHTML = '<tr><td colspan="' + colspan + '" class="rsync-empty">' + escapeHtml(text) + '</td></tr>';
    }

    function noteDate(item) {
        var date = item.data_bon || item.data_deschidere || '-';
        return item.ora_bon ? date + ' ' + item.ora_bon : date;
    }

    function renderPending() {
        var rows = currentData.pending || [];
        setText('rsyncTableTitle', 'Note pregătite pentru următoarea trimitere');
        renderHead(['Bon', 'Data și ora', 'Operator', 'Linii', 'Total', 'Închidere', 'Raport Z', 'Stare']);
        document.getElementById('rsyncDetail').className = 'rsync-detail';
        if (!rows.length) {
            renderEmpty(8, 'Nu există note eligibile în așteptare. Datele trimise sunt la zi.');
            return;
        }
        document.getElementById('rsyncTableBody').innerHTML = rows.map(function (item) {
            return '<tr>' +
                '<td><strong>#' + item.nrbon + '</strong></td>' +
                '<td>' + escapeHtml(noteDate(item)) + '</td>' +
                '<td>' + escapeHtml(item.operator_nume || ('Operator ' + item.operator)) + '</td>' +
                '<td>' + item.linii + '</td>' +
                '<td>' + escapeHtml(money(item.valoare_vanzare_cu_tva)) + '</td>' +
                '<td>' + item.cod_inchidere + '</td>' +
                '<td>' + item.nr_raport_z + '</td>' +
                '<td><span class="rsync-badge waiting">În așteptare</span></td>' +
                '</tr>';
        }).join('');
    }

    function renderBlocked() {
        var rows = currentData.not_eligible || [];
        setText('rsyncTableTitle', 'Note F trimise separat, care așteaptă închiderea turei sau raportul Z');
        renderHead(['Bon', 'Data și ora', 'Operator', 'Total', 'Închidere', 'Raport Z', 'Motiv']);
        document.getElementById('rsyncDetail').className = 'rsync-detail';
        if (!rows.length) {
            renderEmpty(7, 'Nu există note care așteaptă completarea închiderii sau a raportului Z.');
            return;
        }
        document.getElementById('rsyncTableBody').innerHTML = rows.map(function (item) {
            return '<tr>' +
                '<td><strong>#' + item.nrbon + '</strong></td>' +
                '<td>' + escapeHtml(noteDate(item)) + '</td>' +
                '<td>' + escapeHtml(item.operator_nume || ('Operator ' + item.operator)) + '</td>' +
                '<td>' + escapeHtml(money(item.valoare_vanzare_cu_tva)) + '</td>' +
                '<td>' + (item.cod_inchidere || '-') + '</td>' +
                '<td>' + (item.nr_raport_z || '-') + '</td>' +
                '<td class="text-danger font-weight-bold">' + escapeHtml(item.motiv) + '</td>' +
                '</tr>';
        }).join('');
    }

    function renderHistory() {
        var rows = currentData.history || [];
        setText('rsyncTableTitle', 'Ultimele tentative de transmitere, selectați un rând pentru detalii');
        renderHead(['Data și ora', 'Eveniment', 'Stare', 'Note', 'Linii', 'Închideri', 'Rapoarte Z', 'HTTP', 'Încercări', 'Operator']);
        if (!rows.length) {
            document.getElementById('rsyncDetail').className = 'rsync-detail';
            renderEmpty(10, 'Nu există încă tentative în jurnal.');
            return;
        }
        document.getElementById('rsyncTableBody').innerHTML = rows.map(function (item, index) {
            var status = attemptStatus(item);
            return '<tr class="is-clickable" data-rsync-history="' + index + '">' +
                '<td>' + escapeHtml(item.data_ora || '-') + '</td>' +
                '<td>' + escapeHtml(({sale_finalized: 'Notă F', shift_closed: 'Închidere tură', z_closed: 'Raport Z'})[item.event_type] || item.event_type || '-') + '</td>' +
                '<td><span class="rsync-badge ' + status[0] + '">' + status[1] + '</span></td>' +
                '<td>' + item.note_count + '</td><td>' + item.det_note_count + '</td>' +
                '<td>' + item.inchideri_count + '</td><td>' + item.rapoarte_z_count + '</td>' +
                '<td>' + (item.online_http_code || '-') + '</td>' +
                '<td>' + Number(item.attempts || 0) + '</td>' +
                '<td>' + escapeHtml(item.utilizator_nume || ('ID ' + item.utilizator_id)) + '</td>' +
                '</tr>';
        }).join('');
        document.querySelectorAll('[data-rsync-history]').forEach(function (row) {
            row.addEventListener('click', function () { renderDetail(Number(row.getAttribute('data-rsync-history'))); });
        });
        renderDetail(0);
    }

    function renderTablet() {
        var tablet = currentData.tablet_sync || {};
        var counts = tablet.counts || {};
        var runtime = tablet.runtime || {};
        var rows = tablet.pending || [];
        setText('rsyncTableTitle', 'Comenzi tabletă, în așteptare ' + Number(counts.waiting_import || 0) + ', confirmări de retrimis ' + Number(counts.ack_pending || 0) + ', confirmate ' + Number(counts.ack_sent || 0));
        renderHead(['Comandă online', 'Data și ora', 'Ospătar', 'Masă', 'Linii', 'Total', 'Preluată local', 'Amprentă']);
        document.getElementById('rsyncDetail').className = 'rsync-detail';
        if (!rows.length) {
            var lastSuccess = runtime.last_pull_success_at || 'niciodată';
            var lastError = runtime.last_error ? ' Ultima eroare: ' + runtime.last_error : '';
            renderEmpty(8, 'Nu există comenzi de tabletă pentru import. Ultima verificare reușită: ' + lastSuccess + '.' + lastError);
            return;
        }
        document.getElementById('rsyncTableBody').innerHTML = rows.map(function (item) {
            return '<tr>' +
                '<td><strong>#' + item.nrbon + '</strong></td>' +
                '<td>' + escapeHtml(noteDate(item)) + '</td>' +
                '<td>' + escapeHtml(item.owner_operator_name || ('Operator ' + item.owner_operator_id)) + '</td>' +
                '<td>' + item.cod_masa + '</td>' +
                '<td>' + item.linii + '</td>' +
                '<td>' + escapeHtml(money(item.valoare_vanzare_cu_tva)) + '</td>' +
                '<td>' + escapeHtml(item.fetched_at || '-') + '</td>' +
                '<td>' + escapeHtml(item.payload_hash_scurt || '-') + '</td>' +
                '</tr>';
        }).join('');
    }

    function renderDetail(index) {
        var item = currentData.history[index];
        if (!item) return;
        var status = attemptStatus(item);
        var message = item.erori || item.online_message || (item.status === 'empty' ? 'Nu au existat date eligibile noi.' : 'Fără mesaj suplimentar.');
        var detail = document.getElementById('rsyncDetail');
        detail.className = 'rsync-detail is-visible';
        detail.innerHTML =
            '<div class="rsync-detail-title"><strong>Detalii pachet ' + escapeHtml(item.export_id || ('jurnal #' + item.id)) + '</strong><span class="rsync-badge ' + status[0] + '">' + status[1] + '</span></div>' +
            '<div class="rsync-detail-grid">' +
                '<div><span>Conținut local</span><strong>Note ' + item.note_count + ', linii ' + item.det_note_count + ', închideri ' + item.inchideri_count + ', Z ' + item.rapoarte_z_count + ', discounturi ' + item.discounturi_count + '</strong></div>' +
                '<div><span>Inserate online</span><strong>' + escapeHtml(countBreakdown(item.online_inserted)) + '</strong></div>' +
                '<div><span>Duplicate confirmate</span><strong>' + escapeHtml(countBreakdown(item.online_duplicates)) + '</strong></div>' +
                '<div><span>Actualizate online</span><strong>' + escapeHtml(countBreakdown(item.online_updated)) + '</strong></div>' +
                '<div><span>Confirmare endpoint</span><strong>' + escapeHtml((item.online_status || '-') + (item.online_http_code ? ', HTTP ' + item.online_http_code : '')) + '</strong></div>' +
                '<div><span>Fișier export</span><strong>' + escapeHtml(item.fisier_nume || '-') + '</strong></div>' +
                '<div><span>Fișier local</span><strong>' + (item.fisier_exista ? 'Prezent, ' + escapeHtml(bytes(item.fisier_marime)) : 'Nu există sau nu a fost generat') + '</strong></div>' +
                '<div><span>Amprentă payload</span><strong>' + escapeHtml(item.payload_hash_scurt || '-') + '</strong></div>' +
                '<div><span>Încercări și reprogramare</span><strong>' + escapeHtml(String(item.attempts || 0) + (item.next_attempt_at ? ', următoarea ' + item.next_attempt_at : '')) + '</strong></div>' +
            '</div>' +
            '<div class="rsync-message' + (item.erori ? ' is-error' : '') + '"><strong>Răspuns:</strong> ' + escapeHtml(message) + '</div>';
    }

    function renderActiveTab() {
        document.querySelectorAll('[data-rsync-tab]').forEach(function (tab) {
            tab.classList.toggle('active', tab.getAttribute('data-rsync-tab') === activeTab);
        });
        if (activeTab === 'blocked') renderBlocked();
        else if (activeTab === 'history') renderHistory();
        else if (activeTab === 'tablet') renderTablet();
        else renderPending();
    }

    function renderStatus(data) {
        currentData = data;
        var counts = data.counts || {};
        var config = data.configuration || {};
        var history = data.history || [];
        var latest = history.length ? history[0] : null;
        var summary = document.getElementById('restaurantSyncSummary');
        var summaryTitle = 'Datele online sunt la zi.';
        var summaryText = config.selection_rule || '';
        var summaryClass = 'rsync-summary is-ok';
        var summaryIcon = 'fa-check';

        if (config.in_progress) {
            summaryTitle = 'O trimitere este în curs.';
            summaryText = 'Jurnalul se actualizează după primirea răspunsului online.';
            summaryClass = 'rsync-summary is-running';
            summaryIcon = 'fa-spinner fa-spin';
        } else if (Number(counts.queue_blocked || 0) > 0) {
            summaryTitle = counts.queue_blocked + ' eveniment(e) sunt blocate.';
            summaryText = 'Deschideți istoricul pentru răspunsul online și evenimentul afectat.';
            summaryClass = 'rsync-summary is-error';
            summaryIcon = 'fa-exclamation-triangle';
        } else if (Number(counts.queue_retry || 0) > 0) {
            summaryTitle = counts.queue_retry + ' eveniment(e) așteaptă reîncercarea.';
            summaryText = 'Datele rămân în coadă și nu sunt marcate ca trimise.';
            summaryClass = 'rsync-summary is-waiting';
            summaryIcon = 'fa-redo-alt';
        } else if (latest && (latest.status === 'error' || latest.status === 'success_local_online_error')) {
            summaryTitle = 'Ultima trimitere nu a fost confirmată online.';
            summaryText = latest.erori || latest.online_message || 'Verificați detaliile ultimei tentative.';
            summaryClass = 'rsync-summary is-error';
            summaryIcon = 'fa-exclamation-triangle';
        } else if (Number(counts.queue_pending || 0) + Number(counts.eligible_notes || 0) > 0) {
            summaryTitle = (Number(counts.queue_pending || 0) + Number(counts.queue_sending || 0)) + ' eveniment(e) sunt pregătite pentru trimitere.';
            summaryText = config.automatic ? 'Coada include separat notele, închiderile și rapoartele Z.' : 'Evenimentele pot fi trimise prin butonul Sync Online.';
            summaryClass = 'rsync-summary is-waiting';
            summaryIcon = 'fa-clock';
        } else if (Number(counts.waiting_closure || 0) + Number(counts.waiting_z || 0) > 0) {
            summaryTitle = 'Vânzările sunt transmise, iar documentele de tură nu sunt încă încheiate.';
            summaryText = 'Închiderea și raportul Z vor intra separat în coadă după generarea locală.';
            summaryClass = 'rsync-summary is-waiting';
            summaryIcon = 'fa-filter';
        }

        summary.className = summaryClass;
        summary.innerHTML = '<span class="rsync-summary-icon"><i class="fas ' + summaryIcon + '"></i></span><div><strong>' + escapeHtml(summaryTitle) + '</strong><small>' + escapeHtml(summaryText) + '</small></div>';

        setText('rsyncEligible', counts.eligible_notes || 0);
        setText('rsyncLines', counts.eligible_lines || 0);
        setText('rsyncClosure', counts.waiting_closure || 0);
        setText('rsyncZ', counts.waiting_z || 0);
        setText('rsyncOpen', counts.open_notes || 0);
        setText('rsyncExported', counts.exported_notes || 0);
        setText('rsyncPendingTabCount', (data.pending || []).length);
        setText('rsyncBlockedTabCount', (data.not_eligible || []).length);
        setText('rsyncHistoryTabCount', (data.history || []).length);
        setText('rsyncTabletTabCount', Number(data.tablet_sync && data.tablet_sync.counts && data.tablet_sync.counts.waiting_import || 0));
        setText('rsyncInstallation', 'Client ' + data.client_id + ', locația ' + data.cod_locatie);
        setText('rsyncMode', (config.automatic ? 'Automat la ' + config.interval_seconds + ' secunde' : 'Doar manual') + (config.strict_confirmation ? ', confirmare strictă' : ''));
        setText('rsyncLastSuccess', data.last_success ? data.last_success.data_ora : 'Nicio confirmare încă', '-');
        setText('rsyncLastFailure', data.last_failure ? data.last_failure.data_ora : 'Nicio eroare înregistrată', '-');
        setText('rsyncGeneratedAt', 'Actualizat: ' + data.generated_at + ', automat la 20 secunde', '-');
        renderActiveTab();
    }

    function loadStatus() {
        var button = document.getElementById('restaurantSyncRefresh');
        if (button) button.disabled = true;
        return fetch('offline_sync_status.php?ts=' + Date.now(), {
            headers: { 'Accept': 'application/json' },
            cache: 'no-store'
        }).then(function (response) {
            return response.json().then(function (data) {
                if (!response.ok || data.status !== 'success') throw new Error(data.message || 'Situația nu poate fi citită.');
                return data;
            });
        }).then(renderStatus).catch(function (error) {
            var summary = document.getElementById('restaurantSyncSummary');
            summary.className = 'rsync-summary is-error';
            summary.innerHTML = '<span class="rsync-summary-icon"><i class="fas fa-exclamation-triangle"></i></span><div><strong>Situația nu poate fi citită.</strong><small>' + escapeHtml(error.message) + '</small></div>';
        }).finally(function () {
            if (button) button.disabled = false;
        });
    }

    function retryNow() {
        var button = document.getElementById('restaurantSyncRetry');
        if (button) button.disabled = true;
        return fetch('offline_sync_export.php', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: 'automatic=0',
            cache: 'no-store'
        }).then(function (response) {
            return response.json().then(function (data) {
                if (!response.ok || data.status !== 'success') throw new Error(data.message || 'Retrimiterea nu a putut fi pornită.');
                return data;
            });
        }).then(function () {
            return fetch('offline_sync_worker.php', {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                cache: 'no-store'
            });
        }).then(function () {
            return loadStatus();
        }).catch(function (error) {
            var summary = document.getElementById('restaurantSyncSummary');
            summary.className = 'rsync-summary is-error';
            summary.innerHTML = '<span class="rsync-summary-icon"><i class="fas fa-exclamation-triangle"></i></span><div><strong>Retrimiterea a eșuat.</strong><small>' + escapeHtml(error.message) + '</small></div>';
        }).finally(function () {
            if (button) button.disabled = false;
        });
    }

    window.refreshRestaurantSyncStatus = loadStatus;

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-rsync-tab]').forEach(function (tab) {
            tab.addEventListener('click', function () {
                activeTab = tab.getAttribute('data-rsync-tab');
                if (currentData) renderActiveTab();
            });
        });
        document.getElementById('restaurantSyncRefresh').addEventListener('click', loadStatus);
        document.getElementById('restaurantSyncRetry').addEventListener('click', retryNow);
        $('#restaurantSyncStatusModal').on('shown.bs.modal', function () {
            loadStatus();
            window.clearInterval(refreshTimer);
            refreshTimer = window.setInterval(loadStatus, 20000);
        }).on('hidden.bs.modal', function () {
            window.clearInterval(refreshTimer);
            refreshTimer = null;
        });
    });
}());
</script>
