<?php
if (!isset($_SESSION['product_report_csrf']) || !is_string($_SESSION['product_report_csrf'])) {
    $_SESSION['product_report_csrf'] = bin2hex(random_bytes(24));
}
$productReportCsrf = (string)$_SESSION['product_report_csrf'];
?>
<style>
    #productReportModal {
        --report-ink: #172026;
        --report-paper: #fffdf5;
        --report-accent: #e0a21a;
        --report-teal: #157a72;
        --report-muted: #68727a;
        --report-line: #d8d2c3;
    }
    #productReportModal .modal-dialog {
        width: calc(100vw - 18px);
        max-width: 1120px;
        height: calc(100vh - 18px);
        margin: 9px auto;
        display: flex;
        align-items: center;
    }
    #productReportModal .modal-content {
        max-height: calc(100vh - 18px);
        overflow: hidden;
        border: 0;
        border-radius: 12px;
        background: var(--report-paper);
        color: var(--report-ink);
        box-shadow: 0 22px 70px rgba(0, 0, 0, .42);
    }
    #productReportModal .modal-header {
        flex: 0 0 auto;
        padding: 12px 16px;
        border-bottom: 4px solid var(--report-accent);
        background: var(--report-ink);
        color: #fff;
    }
    #productReportModal .report-kicker {
        display: block;
        margin-bottom: 2px;
        color: #f4c85d;
        font-family: Consolas, 'Courier New', monospace;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .12em;
        text-transform: uppercase;
    }
    #productReportModal .modal-title {
        font-family: Georgia, 'Times New Roman', serif;
        font-size: 20px;
        line-height: 1.15;
    }
    #productReportModal .modal-body {
        min-height: 0;
        padding: 12px;
        overflow-y: auto;
        background:
            linear-gradient(rgba(23, 32, 38, .025) 1px, transparent 1px),
            linear-gradient(90deg, rgba(23, 32, 38, .025) 1px, transparent 1px),
            var(--report-paper);
        background-size: 18px 18px;
    }
    #productReportModal .report-layout {
        display: grid;
        grid-template-columns: minmax(255px, 310px) minmax(0, 1fr);
        gap: 12px;
        min-height: 520px;
    }
    #productReportModal .report-panel {
        border: 1px solid var(--report-line);
        border-radius: 9px;
        background: rgba(255, 255, 255, .9);
        box-shadow: 0 8px 20px rgba(23, 32, 38, .07);
    }
    #productReportModal .report-filters {
        align-self: start;
        padding: 13px;
    }
    #productReportModal .report-section-label {
        margin: 0 0 7px;
        color: var(--report-muted);
        font-family: Consolas, 'Courier New', monospace;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .1em;
        text-transform: uppercase;
    }
    #productReportModal .report-type-grid {
        display: grid;
        gap: 7px;
        margin-bottom: 12px;
    }
    #productReportModal .report-type-input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }
    #productReportModal .report-type-card {
        position: relative;
        display: block;
        margin: 0;
        padding: 9px 10px 9px 38px;
        border: 2px solid #d7d4ca;
        border-radius: 8px;
        background: #fff;
        color: var(--report-ink);
        cursor: pointer;
        transition: border-color .15s ease, transform .15s ease, box-shadow .15s ease;
    }
    #productReportModal .report-type-card::before {
        position: absolute;
        top: 13px;
        left: 12px;
        width: 14px;
        height: 14px;
        content: '';
        border: 2px solid #8d9498;
        border-radius: 50%;
        background: #fff;
    }
    #productReportModal .report-type-input:checked + .report-type-card {
        border-color: var(--report-teal);
        box-shadow: 0 0 0 3px rgba(21, 122, 114, .12);
        transform: translateY(-1px);
    }
    #productReportModal .report-type-input:checked + .report-type-card::before {
        border: 4px solid var(--report-teal);
    }
    #productReportModal .report-type-card strong {
        display: block;
        font-size: 14px;
        line-height: 1.2;
    }
    #productReportModal .report-type-card small {
        display: block;
        margin-top: 2px;
        color: var(--report-muted);
        font-size: 11px;
        line-height: 1.25;
    }
    #productReportModal .report-datetime-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 92px;
        gap: 7px;
    }
    #productReportModal .form-group {
        margin-bottom: 9px;
    }
    #productReportModal .form-group label {
        margin-bottom: 3px;
        color: #3b454b;
        font-size: 11px;
        font-weight: 700;
    }
    #productReportModal .form-control {
        height: 34px;
        padding: 5px 8px;
        border-color: #c8cdd0;
        border-radius: 6px;
        font-size: 13px;
    }
    #productReportModal .report-presets {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
        margin-top: 3px;
    }
    #productReportModal .report-preset {
        padding: 4px 7px;
        border: 1px solid #c4c9cc;
        border-radius: 999px;
        background: #fff;
        color: #334047;
        font-size: 11px;
        cursor: pointer;
    }
    #productReportModal .report-preset:hover {
        border-color: var(--report-accent);
        background: #fff6d9;
    }
    #productReportModal .report-preview {
        position: relative;
        display: flex;
        min-width: 0;
        min-height: 520px;
        flex-direction: column;
        overflow: hidden;
    }
    #productReportModal .report-empty,
    #productReportModal .report-loading {
        display: flex;
        min-height: 420px;
        padding: 28px;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        text-align: center;
        color: var(--report-muted);
    }
    #productReportModal .report-empty-icon {
        width: 64px;
        height: 64px;
        margin-bottom: 12px;
        border: 2px dashed #aeb5b8;
        border-radius: 50%;
        display: grid;
        place-items: center;
        color: var(--report-teal);
        font-size: 25px;
    }
    #productReportModal .report-result {
        display: flex;
        min-height: 0;
        flex: 1 1 auto;
        flex-direction: column;
    }
    #productReportModal .report-result-head {
        display: flex;
        gap: 10px;
        padding: 10px 12px;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid var(--report-line);
        background: #f8f4e9;
    }
    #productReportModal .report-result-head h3 {
        margin: 0;
        font-family: Georgia, 'Times New Roman', serif;
        font-size: 17px;
    }
    #productReportModal .report-result-head small {
        display: block;
        color: var(--report-muted);
        font-size: 11px;
    }
    #productReportModal .report-destination {
        flex: 0 0 auto;
        padding: 5px 9px;
        border-radius: 999px;
        background: var(--report-ink);
        color: #fff;
        font-family: Consolas, 'Courier New', monospace;
        font-size: 11px;
        font-weight: 700;
    }
    #productReportModal .report-summary {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 6px;
        padding: 8px 10px;
        border-bottom: 1px solid var(--report-line);
    }
    #productReportModal .report-stat {
        min-width: 0;
        padding: 7px;
        border-radius: 6px;
        background: #eef3f2;
    }
    #productReportModal .report-stat span {
        display: block;
        overflow: hidden;
        color: var(--report-muted);
        font-size: 9px;
        font-weight: 700;
        letter-spacing: .04em;
        text-overflow: ellipsis;
        text-transform: uppercase;
        white-space: nowrap;
    }
    #productReportModal .report-stat strong {
        display: block;
        margin-top: 2px;
        overflow: hidden;
        color: var(--report-ink);
        font-family: Consolas, 'Courier New', monospace;
        font-size: 14px;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    #productReportModal .report-view-switch {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
        padding: 7px 10px 0;
    }
    #productReportModal .report-view-button {
        padding: 4px 9px;
        border: 1px solid #c8cdd0;
        border-radius: 6px 6px 0 0;
        background: #fff;
        color: #3f4a50;
        font-size: 11px;
        font-weight: 700;
        cursor: pointer;
    }
    #productReportModal .report-view-button.active {
        border-color: var(--report-teal);
        background: var(--report-teal);
        color: #fff;
    }
    #productReportModal .report-view {
        min-height: 0;
        flex: 1 1 auto;
        overflow: auto;
        padding: 0 10px 10px;
    }
    #productReportModal .report-table {
        width: 100%;
        margin: 0;
        font-size: 11px;
    }
    #productReportModal .report-table thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        padding: 7px 6px;
        border-top: 0;
        background: var(--report-ink);
        color: #fff;
        font-size: 10px;
        letter-spacing: .03em;
        white-space: nowrap;
    }
    #productReportModal .report-table td {
        padding: 6px;
        vertical-align: middle;
    }
    #productReportModal .report-table .report-product-name {
        min-width: 150px;
        font-weight: 700;
    }
    #productReportModal .report-groups {
        display: grid;
        gap: 9px;
        padding-top: 8px;
    }
    #productReportModal .report-group {
        overflow: hidden;
        border: 1px solid #cbd3d1;
        border-radius: 8px;
        background: #fff;
    }
    #productReportModal .report-group-head {
        display: flex;
        gap: 10px;
        padding: 8px 10px;
        align-items: center;
        justify-content: space-between;
        background: var(--report-ink);
        color: #fff;
    }
    #productReportModal .report-group-head strong {
        font-family: Consolas, 'Courier New', monospace;
        font-size: 12px;
    }
    #productReportModal .report-group-head span {
        color: #f4c85d;
        font-size: 11px;
        font-weight: 700;
        white-space: nowrap;
    }
    #productReportModal .report-product-line {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 10px;
        padding: 7px 10px;
        border-top: 1px solid #edf0ef;
        align-items: center;
        font-size: 12px;
    }
    #productReportModal .report-product-line strong {
        word-break: break-word;
    }
    #productReportModal .report-payment-box {
        margin-top: 10px;
        padding: 10px;
        border: 2px solid var(--report-accent);
        border-radius: 8px;
        background: #fff8df;
    }
    #productReportModal .report-payment-box h4 {
        margin: 0 0 7px;
        font-size: 13px;
    }
    #productReportModal .report-payment-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        gap: 7px;
    }
    #productReportModal .report-payment-operator {
        padding: 8px;
        border: 1px solid #e2c975;
        border-radius: 6px;
        background: #fff;
        font-size: 11px;
    }
    #productReportModal .report-payment-operator strong {
        display: block;
        margin-bottom: 5px;
        color: var(--report-ink);
    }
    #productReportModal .report-payment-row {
        display: flex;
        justify-content: space-between;
        gap: 8px;
    }
    #productReportModal .report-payment-row.total {
        margin-top: 4px;
        padding-top: 4px;
        border-top: 1px solid #d8d2c3;
        font-weight: 700;
    }
    #productReportModal .report-note-list {
        display: grid;
        gap: 7px;
        padding-top: 8px;
    }
    #productReportModal .report-note-card {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto auto;
        gap: 9px;
        padding: 9px;
        border: 1px solid #d7dcda;
        border-radius: 7px;
        align-items: center;
        background: #fff;
        font-size: 11px;
    }
    #productReportModal .report-note-card strong,
    #productReportModal .report-note-card small {
        display: block;
    }
    #productReportModal .report-note-card small {
        color: var(--report-muted);
    }
    #productReportModal .report-note-detail {
        position: absolute;
        z-index: 5;
        inset: 0;
        display: flex;
        min-height: 0;
        flex-direction: column;
        background: var(--report-paper);
    }
    #productReportModal .report-note-detail[hidden] {
        display: none;
    }
    #productReportModal .report-note-detail-head {
        display: flex;
        gap: 10px;
        padding: 10px 12px;
        align-items: center;
        justify-content: space-between;
        border-bottom: 4px solid var(--report-accent);
        background: var(--report-ink);
        color: #fff;
    }
    #productReportModal .report-note-detail-body {
        min-height: 0;
        padding: 10px;
        overflow: auto;
    }
    #productReportModal .report-paper {
        min-height: 100%;
        margin: 0;
        padding: 16px;
        border-top: 5px solid var(--report-ink);
        border-bottom: 5px solid var(--report-ink);
        background: #fff;
        color: #101010;
        font-family: Consolas, 'Courier New', monospace;
        font-size: 12px;
        line-height: 1.3;
        white-space: pre-wrap;
    }
    #productReportModal .report-alert {
        display: none;
        margin: 0 12px 8px;
        padding: 7px 9px;
        border-radius: 6px;
        font-size: 12px;
    }
    #productReportModal .modal-footer {
        flex: 0 0 auto;
        gap: 7px;
        padding: 9px 12px;
        border-top: 1px solid var(--report-line);
        background: #f3efe4;
    }
    #productReportModal .report-print-button {
        border-color: var(--report-teal);
        background: var(--report-teal);
        color: #fff;
    }
    #productReportModal .report-print-button:hover:not(:disabled) {
        border-color: #0d5d57;
        background: #0d5d57;
        color: #fff;
    }
    #productReportModal .report-spinner {
        width: 34px;
        height: 34px;
        margin-bottom: 12px;
        border: 4px solid #d5dedc;
        border-top-color: var(--report-teal);
        border-radius: 50%;
        animation: reportSpin .8s linear infinite;
    }
    @keyframes reportSpin { to { transform: rotate(360deg); } }
    @media (max-width: 800px) {
        #productReportModal .report-layout {
            grid-template-columns: 1fr;
            min-height: 0;
        }
        #productReportModal .report-datetime-grid {
            grid-template-columns: minmax(0, 1fr) minmax(90px, .55fr);
        }
        #productReportModal .report-preview,
        #productReportModal .report-empty,
        #productReportModal .report-loading {
            min-height: 390px;
        }
    }
    @media (max-width: 560px) {
        #productReportModal .modal-dialog {
            width: 100vw;
            height: 100vh;
            margin: 0;
        }
        #productReportModal .modal-content {
            max-height: 100vh;
            border-radius: 0;
        }
        #productReportModal .report-summary {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        #productReportModal .report-note-card {
            grid-template-columns: minmax(0, 1fr) auto;
        }
        #productReportModal .report-note-card .btn {
            grid-column: 1 / -1;
            width: 100%;
        }
        #productReportModal .modal-footer {
            align-items: stretch;
            flex-direction: column-reverse;
        }
        #productReportModal .modal-footer .btn {
            width: 100%;
        }
    }
</style>

<div class="modal fade" id="productReportModal" tabindex="-1" role="dialog" aria-labelledby="productReportModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <span class="report-kicker">Previzualizare înainte de listare</span>
                    <h2 class="modal-title" id="productReportModalTitle">Rapoarte produse pentru imprimante</h2>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Închide">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <div class="report-layout">
                    <form id="productReportForm" class="report-panel report-filters" autocomplete="off">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($productReportCsrf, ENT_QUOTES, 'UTF-8'); ?>">

                        <p class="report-section-label">Tip raport</p>
                        <div class="report-type-grid">
                            <input class="report-type-input" type="radio" name="report_type" id="productReportTypeProtocol" value="protocol" checked>
                            <label class="report-type-card" for="productReportTypeProtocol">
                                <strong>Produse vândute pe protocol</strong>
                                <small>Include notele finalizate cu valoare protocol și trimite raportul la BAR.</small>
                            </label>

                            <input class="report-type-input" type="radio" name="report_type" id="productReportTypeDepartment" value="department">
                            <label class="report-type-card" for="productReportTypeDepartment">
                                <strong>Produse pe departament</strong>
                                <small>Poți alege un departament sau toate. Raportul complet se trimite la BAR.</small>
                            </label>
                        </div>

                        <p class="report-section-label">Interval vânzări</p>
                        <div class="report-datetime-grid">
                            <div class="form-group">
                                <label for="productReportDateStart">Data început</label>
                                <input type="date" class="form-control" id="productReportDateStart" name="date_start" required>
                            </div>
                            <div class="form-group">
                                <label for="productReportTimeStart">Ora început</label>
                                <input type="time" class="form-control" id="productReportTimeStart" name="time_start" step="60" required>
                            </div>
                            <div class="form-group">
                                <label for="productReportDateEnd">Data sfârșit</label>
                                <input type="date" class="form-control" id="productReportDateEnd" name="date_end" required>
                            </div>
                            <div class="form-group">
                                <label for="productReportTimeEnd">Ora sfârșit</label>
                                <input type="time" class="form-control" id="productReportTimeEnd" name="time_end" step="60" required>
                            </div>
                        </div>

                        <div class="report-presets" aria-label="Intervale rapide">
                            <button type="button" class="report-preset" data-report-preset="today">Azi</button>
                            <button type="button" class="report-preset" data-report-preset="last2">Ultimele 2 ore</button>
                            <button type="button" class="report-preset" data-report-preset="shift">De la 06:00</button>
                        </div>

                        <div class="form-group mt-3" id="productReportDepartmentGroup">
                            <label for="productReportDepartment">Filtru departament de listare</label>
                            <select class="form-control" id="productReportDepartment" name="department">
                                <option value="">Se încarcă departamentele...</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="productReportOperator">Filtru operator sau ospătar</label>
                            <select class="form-control" id="productReportOperator" name="operator">
                                <option value="all">Toți operatorii</option>
                            </select>
                        </div>
                    </form>

                    <section class="report-panel report-preview" aria-live="polite">
                        <div id="productReportAlert" class="report-alert" role="alert"></div>

                        <div id="productReportEmpty" class="report-empty">
                            <div class="report-empty-icon"><i class="fas fa-receipt"></i></div>
                            <strong>Raportul nu a fost generat încă</strong>
                            <small>Alege tipul și intervalul, apoi generează previzualizarea.</small>
                        </div>

                        <div id="productReportLoading" class="report-loading" hidden>
                            <div class="report-spinner"></div>
                            <strong>Se pregătește raportul...</strong>
                            <small>Sunt centralizate numai notele finalizate din locația curentă.</small>
                        </div>

                        <div id="productReportResult" class="report-result" hidden>
                            <div class="report-result-head">
                                <div>
                                    <h3 id="productReportTitle">Raport produse</h3>
                                    <small id="productReportInterval"></small>
                                </div>
                                <span id="productReportDestination" class="report-destination"></span>
                            </div>

                            <div class="report-summary">
                                <div class="report-stat"><span>Note</span><strong id="productReportStatNotes">0</strong></div>
                                <div class="report-stat"><span>Produse</span><strong id="productReportStatProducts">0</strong></div>
                                <div class="report-stat"><span>Cantitate</span><strong id="productReportStatQuantity">0</strong></div>
                                <div class="report-stat"><span>Valoare</span><strong id="productReportStatValue">0</strong></div>
                                <div class="report-stat" id="productReportProtocolStat"><span>Protocol</span><strong id="productReportStatProtocol">0</strong></div>
                            </div>

                            <div class="report-view-switch">
                                <button type="button" class="report-view-button active" data-report-view="aggregate">Produse agregate</button>
                                <button type="button" class="report-view-button" data-report-view="lines">Linii vândute</button>
                                <button type="button" class="report-view-button" data-report-view="notes">Note</button>
                                <button type="button" class="report-view-button" data-report-view="paper">Previzualizare hârtie</button>
                            </div>

                            <div id="productReportAggregateView" class="report-view">
                                <div id="productReportGroups" class="report-groups"></div>
                                <div id="productReportPayments" class="report-payment-box" hidden>
                                    <h4>Total pe metode de plată, pentru fiecare ospătar</h4>
                                    <div id="productReportPaymentRows" class="report-payment-grid"></div>
                                </div>
                            </div>
                            <div id="productReportLinesView" class="report-view" hidden>
                                <table class="table table-striped table-sm report-table">
                                    <thead>
                                        <tr>
                                            <th>Data și ora</th>
                                            <th>Nota</th>
                                            <th>Produs</th>
                                            <th>Departament</th>
                                            <th>Ospătar</th>
                                            <th class="text-right">Cantitate</th>
                                            <th class="text-right">Valoare</th>
                                        </tr>
                                    </thead>
                                    <tbody id="productReportLineRows"></tbody>
                                </table>
                            </div>
                            <div id="productReportNotesView" class="report-view" hidden>
                                <div id="productReportNoteRows" class="report-note-list"></div>
                            </div>
                            <div id="productReportPaperView" class="report-view" hidden>
                                <pre id="productReportPaper" class="report-paper" data-i18n-ignore></pre>
                            </div>

                            <div id="productReportNoteDetail" class="report-note-detail" hidden>
                                <div class="report-note-detail-head">
                                    <div>
                                        <strong id="productReportNoteDetailTitle">Detalii notă</strong>
                                        <small id="productReportNoteDetailMeta"></small>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-light" id="productReportNoteDetailClose">
                                        <i class="fas fa-arrow-left"></i> Înapoi la note
                                    </button>
                                </div>
                                <div class="report-note-detail-body">
                                    <div id="productReportNoteDetailPayments" class="report-payment-box"></div>
                                    <table class="table table-striped table-sm report-table mt-2">
                                        <thead>
                                            <tr>
                                                <th>Data și ora</th>
                                                <th>Produs</th>
                                                <th>Departament</th>
                                                <th class="text-right">Cantitate</th>
                                                <th class="text-right">Valoare</th>
                                            </tr>
                                        </thead>
                                        <tbody id="productReportNoteDetailRows"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-dismiss="modal">Închide</button>
                <button type="button" class="btn btn-outline-dark" id="productReportPreviewButton">
                    <i class="fas fa-search"></i> Generează previzualizarea
                </button>
                <button type="button" class="btn report-print-button" id="productReportPrintButton" disabled>
                    <i class="fas fa-print"></i> Confirmă și trimite la imprimantă
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var endpoint = 'sefsala_raport_produse_api.php';
        var openButton = document.getElementById('btnProductReport');
        var modal = document.getElementById('productReportModal');
        var form = document.getElementById('productReportForm');
        var previewButton = document.getElementById('productReportPreviewButton');
        var printButton = document.getElementById('productReportPrintButton');
        var departmentGroup = document.getElementById('productReportDepartmentGroup');
        var departmentSelect = document.getElementById('productReportDepartment');
        var operatorSelect = document.getElementById('productReportOperator');
        var emptyState = document.getElementById('productReportEmpty');
        var loadingState = document.getElementById('productReportLoading');
        var resultState = document.getElementById('productReportResult');
        var alertBox = document.getElementById('productReportAlert');
        var previewToken = '';
        var departmentsLoaded = false;
        var currentReport = null;

        if (!openButton || !modal || !form) {
            return;
        }

        function pad(value) {
            return String(value).padStart(2, '0');
        }

        function localDate(value) {
            return value.getFullYear() + '-' + pad(value.getMonth() + 1) + '-' + pad(value.getDate());
        }

        function localTime(value) {
            return pad(value.getHours()) + ':' + pad(value.getMinutes());
        }

        function setIntervalValues(start, end) {
            document.getElementById('productReportDateStart').value = localDate(start);
            document.getElementById('productReportTimeStart').value = localTime(start);
            document.getElementById('productReportDateEnd').value = localDate(end);
            document.getElementById('productReportTimeEnd').value = localTime(end);
            invalidatePreview();
        }

        function applyPreset(name) {
            var end = new Date();
            var start = new Date(end.getTime());
            if (name === 'today') {
                start.setHours(0, 0, 0, 0);
            } else if (name === 'last2') {
                start = new Date(end.getTime() - (2 * 60 * 60 * 1000));
            } else {
                start.setHours(6, 0, 0, 0);
                if (start > end) {
                    start.setDate(start.getDate() - 1);
                }
            }
            setIntervalValues(start, end);
        }

        function showModal() {
            if (window.jQuery && typeof window.jQuery.fn.modal === 'function') {
                window.jQuery(modal).modal('show');
            }
        }

        function showAlert(type, message) {
            alertBox.className = 'report-alert alert-' + type;
            alertBox.textContent = message || '';
            alertBox.style.display = message ? 'block' : 'none';
        }

        function setBusy(isBusy) {
            previewButton.disabled = isBusy;
            if (isBusy) {
                emptyState.hidden = true;
                resultState.hidden = true;
                loadingState.hidden = false;
                showAlert('', '');
            } else {
                loadingState.hidden = true;
            }
        }

        function invalidatePreview() {
            previewToken = '';
            currentReport = null;
            printButton.disabled = true;
            resultState.hidden = true;
            loadingState.hidden = true;
            emptyState.hidden = false;
            showAlert('', '');
        }

        function updateReportType() {
            var type = form.querySelector('input[name="report_type"]:checked').value;
            departmentGroup.hidden = false;
            departmentSelect.required = false;
            invalidatePreview();
        }

        function escapeHtml(value) {
            var node = document.createElement('div');
            node.textContent = value == null ? '' : String(value);
            return node.innerHTML;
        }

        function formatNumber(value, decimals) {
            var number = Number(value || 0);
            return number.toLocaleString('ro-RO', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            });
        }

        var paymentLabels = {
            cash: 'Numerar',
            card: 'Card',
            tickets: 'Tichete',
            bank: 'Virament',
            protocol: 'Protocol',
            glovo: 'Glovo'
        };

        function renderPaymentRows(payments, includeZero) {
            return Object.keys(paymentLabels).map(function (key) {
                var amount = Number((payments || {})[key] || 0);
                if (!includeZero && Math.abs(amount) < 0.005) {
                    return '';
                }
                return '<div class="report-payment-row"><span>' + paymentLabels[key] + '</span><strong>'
                    + escapeHtml(formatNumber(amount, 2)) + ' LEI</strong></div>';
            }).join('');
        }

        async function readJson(response) {
            var payload;
            try {
                payload = await response.json();
            } catch (error) {
                throw new Error('Răspunsul serverului nu este valid.');
            }
            if (!response.ok || payload.status !== 'success') {
                throw new Error(payload.message || 'Operațiunea nu a putut fi finalizată.');
            }
            return payload;
        }

        async function loadDepartments() {
            if (departmentsLoaded) {
                return;
            }
            departmentSelect.innerHTML = '<option value="">Se încarcă departamentele...</option>';
            try {
                var response = await fetch(endpoint + '?action=options', {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' }
                });
                var payload = await readJson(response);
                departmentSelect.innerHTML = '';
                var allDepartments = document.createElement('option');
                allDepartments.value = 'all';
                allDepartments.textContent = 'Toate departamentele';
                departmentSelect.appendChild(allDepartments);
                if (payload.departments.length) {
                    payload.departments.forEach(function (department) {
                        var option = document.createElement('option');
                        option.value = department;
                        option.textContent = department;
                        departmentSelect.appendChild(option);
                    });
                }

                operatorSelect.innerHTML = '';
                var allOperators = document.createElement('option');
                allOperators.value = 'all';
                allOperators.textContent = 'Toți operatorii';
                operatorSelect.appendChild(allOperators);
                (payload.operators || []).forEach(function (operator) {
                    var option = document.createElement('option');
                    option.value = operator.id;
                    option.textContent = operator.label;
                    operatorSelect.appendChild(option);
                });
                departmentsLoaded = true;
            } catch (error) {
                departmentSelect.innerHTML = '<option value="">Departamente indisponibile</option>';
                showAlert('danger', error.message);
            }
        }

        function renderReport(report) {
            currentReport = report;
            document.getElementById('productReportTitle').textContent = report.title;
            document.getElementById('productReportInterval').textContent = report.interval
                + ' | ' + report.operator_label
                + ' | ' + (report.department === 'ALL' ? 'Toate departamentele' : report.department);
            document.getElementById('productReportDestination').textContent = 'IMPRIMANTĂ ' + report.destination;
            document.getElementById('productReportStatNotes').textContent = report.summary.note_count;
            document.getElementById('productReportStatProducts').textContent = report.summary.product_count;
            document.getElementById('productReportStatQuantity').textContent = formatNumber(report.summary.quantity_total, 3);
            document.getElementById('productReportStatValue').textContent = formatNumber(report.summary.value_total, 2) + ' LEI';
            document.getElementById('productReportStatProtocol').textContent = formatNumber(report.summary.protocol_total, 2) + ' LEI';
            document.getElementById('productReportProtocolStat').hidden = report.report_type !== 'protocol';
            document.getElementById('productReportPaper').textContent = report.content;

            var groups = (report.groups || []).map(function (group, groupIndex) {
                var products = (group.products || []).map(function (product) {
                    return '<div class="report-product-line">'
                        + '<strong data-i18n-ignore>' + escapeHtml(formatNumber(product.quantity, 3)) + ' X ' + escapeHtml(product.product) + '</strong>'
                        + '<span>' + escapeHtml(formatNumber(product.value, 2)) + ' LEI</span>'
                        + '</div>';
                }).join('');
                return '<section class="report-group">'
                    + '<div class="report-group-head"><strong>' + (groupIndex + 1) + '. ' + escapeHtml(group.department) + '</strong>'
                    + '<span>' + escapeHtml(formatNumber(group.quantity_total, 3)) + ' | ' + escapeHtml(formatNumber(group.value_total, 2)) + ' LEI</span></div>'
                    + products
                    + '</section>';
            }).join('');
            document.getElementById('productReportGroups').innerHTML = groups
                || '<div class="text-center text-muted py-4">Nu există produse în interval.</div>';

            var lineRows = (report.lines || []).map(function (line) {
                return '<tr>'
                    + '<td>' + escapeHtml(line.date + ' ' + line.time) + '</td>'
                    + '<td>#' + escapeHtml(line.note_id) + '</td>'
                    + '<td class="report-product-name" data-i18n-ignore>' + escapeHtml(line.product) + '</td>'
                    + '<td data-i18n-ignore>' + escapeHtml(line.department) + '</td>'
                    + '<td data-i18n-ignore>' + escapeHtml(line.operator_label) + '</td>'
                    + '<td class="text-right">' + escapeHtml(formatNumber(line.quantity, 3)) + '</td>'
                    + '<td class="text-right">' + escapeHtml(formatNumber(line.value, 2)) + ' LEI</td>'
                    + '</tr>';
            }).join('');
            document.getElementById('productReportLineRows').innerHTML = lineRows
                || '<tr><td colspan="7" class="text-center text-muted py-4">Nu există linii în interval.</td></tr>';

            var noteRows = (report.notes || []).map(function (note, index) {
                return '<article class="report-note-card">'
                    + '<div><strong>Nota #' + escapeHtml(note.note_id) + '</strong><small>'
                    + escapeHtml(note.date + ' ' + note.time + ' | ' + note.operator_label) + '</small></div>'
                    + '<strong>' + escapeHtml(formatNumber(note.product_value, 2)) + ' LEI</strong>'
                    + '<button type="button" class="btn btn-sm btn-outline-dark" data-report-note-index="' + index + '">Detalii</button>'
                    + '</article>';
            }).join('');
            document.getElementById('productReportNoteRows').innerHTML = noteRows
                || '<div class="text-center text-muted py-4">Nu există note în interval.</div>';

            var paymentRows = (report.payment_totals || []).map(function (operator) {
                return '<section class="report-payment-operator"><strong>' + escapeHtml(operator.operator_label) + '</strong>'
                    + renderPaymentRows(operator.payments, false)
                    + '<div class="report-payment-row total"><span>Total ospătar</span><strong>'
                    + escapeHtml(formatNumber(operator.total, 2)) + ' LEI</strong></div></section>';
            }).join('');
            var paymentBox = document.getElementById('productReportPayments');
            paymentBox.hidden = report.department !== 'ALL' || paymentRows === '';
            document.getElementById('productReportPaymentRows').innerHTML = paymentRows;

            emptyState.hidden = true;
            resultState.hidden = false;
            closeNoteDetail();
            switchView('aggregate');
        }

        function switchView(view) {
            document.getElementById('productReportAggregateView').hidden = view !== 'aggregate';
            document.getElementById('productReportLinesView').hidden = view !== 'lines';
            document.getElementById('productReportNotesView').hidden = view !== 'notes';
            document.getElementById('productReportPaperView').hidden = view !== 'paper';
            closeNoteDetail();
            modal.querySelectorAll('[data-report-view]').forEach(function (button) {
                button.classList.toggle('active', button.getAttribute('data-report-view') === view);
            });
        }

        function showNoteDetail(index) {
            var note = currentReport && currentReport.notes ? currentReport.notes[index] : null;
            if (!note) {
                return;
            }
            document.getElementById('productReportNoteDetailTitle').textContent = 'Nota #' + note.note_id;
            document.getElementById('productReportNoteDetailMeta').textContent = note.date + ' ' + note.time + ' | ' + note.operator_label;
            document.getElementById('productReportNoteDetailPayments').innerHTML = '<h4>Metode de plată</h4>'
                + renderPaymentRows(note.payments, true)
                + '<div class="report-payment-row total"><span>Total plăți</span><strong>'
                + escapeHtml(formatNumber(note.payment_total, 2)) + ' LEI</strong></div>';
            document.getElementById('productReportNoteDetailRows').innerHTML = (note.products || []).map(function (product) {
                return '<tr><td>' + escapeHtml(product.date + ' ' + product.time) + '</td>'
                    + '<td class="report-product-name" data-i18n-ignore>' + escapeHtml(product.product) + '</td>'
                    + '<td data-i18n-ignore>' + escapeHtml(product.department) + '</td>'
                    + '<td class="text-right">' + escapeHtml(formatNumber(product.quantity, 3)) + '</td>'
                    + '<td class="text-right">' + escapeHtml(formatNumber(product.value, 2)) + ' LEI</td></tr>';
            }).join('');
            document.getElementById('productReportNoteDetail').hidden = false;
        }

        function closeNoteDetail() {
            document.getElementById('productReportNoteDetail').hidden = true;
        }

        async function generatePreview() {
            if (!form.reportValidity()) {
                return;
            }
            setBusy(true);
            previewToken = '';
            printButton.disabled = true;
            var data = new FormData(form);
            data.append('action', 'preview');
            try {
                var response = await fetch(endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' },
                    body: data
                });
                var payload = await readJson(response);
                previewToken = payload.preview_token || '';
                renderReport(payload.report);
                printButton.disabled = !payload.report.can_print || previewToken === '';
                showAlert(payload.report.can_print ? 'success' : 'warning', payload.message);
            } catch (error) {
                emptyState.hidden = false;
                showAlert('danger', error.message);
            } finally {
                setBusy(false);
            }
        }

        async function printPreview() {
            if (!previewToken) {
                showAlert('warning', 'Generează mai întâi previzualizarea raportului.');
                return;
            }
            if (!window.confirm('Confirmi trimiterea raportului previzualizat către imprimantă?')) {
                return;
            }

            printButton.disabled = true;
            var originalText = printButton.innerHTML;
            printButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Se trimite...';
            var data = new FormData();
            data.append('action', 'print');
            data.append('csrf', form.querySelector('[name="csrf"]').value);
            data.append('preview_token', previewToken);
            try {
                var response = await fetch(endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' },
                    body: data
                });
                var payload = await readJson(response);
                previewToken = '';
                showAlert('success', payload.message);
            } catch (error) {
                printButton.disabled = false;
                showAlert('danger', error.message);
            } finally {
                printButton.innerHTML = originalText;
            }
        }

        openButton.addEventListener('click', function () {
            if (!document.getElementById('productReportDateStart').value) {
                applyPreset('today');
            }
            loadDepartments();
            showModal();
        });

        form.querySelectorAll('input[name="report_type"]').forEach(function (control) {
            control.addEventListener('change', updateReportType);
        });
        form.querySelectorAll('input:not([name="report_type"]), select').forEach(function (control) {
            control.addEventListener('change', invalidatePreview);
        });
        modal.querySelectorAll('[data-report-preset]').forEach(function (button) {
            button.addEventListener('click', function () {
                applyPreset(button.getAttribute('data-report-preset'));
            });
        });
        modal.querySelectorAll('[data-report-view]').forEach(function (button) {
            button.addEventListener('click', function () {
                switchView(button.getAttribute('data-report-view'));
            });
        });
        document.getElementById('productReportNoteRows').addEventListener('click', function (event) {
            var button = event.target.closest('[data-report-note-index]');
            if (button) {
                showNoteDetail(Number(button.getAttribute('data-report-note-index')));
            }
        });
        document.getElementById('productReportNoteDetailClose').addEventListener('click', closeNoteDetail);
        previewButton.addEventListener('click', generatePreview);
        printButton.addEventListener('click', printPreview);
        updateReportType();
    });
}());
</script>
