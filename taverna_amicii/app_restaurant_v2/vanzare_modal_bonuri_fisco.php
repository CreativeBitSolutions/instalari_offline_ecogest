<!-- Modal Bonuri FISCO -->
<div class="modal fade" id="modalBonuriFisco" tabindex="-1" role="dialog" aria-labelledby="modalBonuriFiscoLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document" style="max-width:1200px;">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="modalBonuriFiscoLabel">Bonuri FISCO (procesate)</h4>
        <div class="ml-auto d-flex align-items-center">
          <label class="mb-0 mr-2 small text-muted">Limita:</label>
          <select id="bf_limit" class="custom-select custom-select-sm" style="width:90px;">
            <option value="50" selected>50</option>
            <option value="100">100</option>
            <option value="200">200</option>
          </select>
          <button type="button" class="btn btn-sm btn-outline-secondary ml-2" id="bf_refresh">
            <i class="fas fa-sync-alt"></i> Refresh
          </button>
        </div>
        <button type="button" class="close" data-dismiss="modal" aria-label="Închide"><span aria-hidden="true">&times;</span></button>
      </div>

      <div class="modal-body p-0">
        <!-- toolbar filtrare -->
        <div class="p-3 border-bottom">
          <div class="form-row">
            <div class="col-md-6">
              <input type="text" id="bf_filter" class="form-control" placeholder="Filtrează după nume/conținut (live)…">
            </div>
            <div class="col-md-6 text-right">
              <span class="small text-muted">Căutare în cele mai recente fișiere din folderul clientului curent.</span>
            </div>
          </div>
        </div>

        <!-- container dinamic -->
        <div id="bonuri_fisco_content" class="bf-container">
          <div class="py-5 text-center"><i class="fas fa-spinner fa-spin fa-2x"></i></div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
  /* Scroll doar în body-ul tab-urilor; pagina nu se mișcă */
  #modalBonuriFisco .bf-scroll {
    max-height: 70vh;
    overflow: auto;
    padding: 12px 16px;
  }
  #modalBonuriFisco .bf-item {
    border: 1px solid #e9ecef;
    border-radius: 6px;
    margin-bottom: 12px;
    background: #fff;
  }
  #modalBonuriFisco .bf-head {
    padding: 8px 12px;
    border-bottom: 1px solid #f1f3f5;
    display: flex; align-items: center; justify-content: space-between;
    background: #f8f9fa;
  }
  #modalBonuriFisco .bf-pre {
    margin: 0; padding: 12px;
    white-space: pre-wrap; word-break: break-word;
    font-family: Menlo, Consolas, monospace; font-size: 12.5px;
  }
  #modalBonuriFisco .nav-tabs .nav-link { padding: .5rem .9rem; }
  #modalBonuriFisco .badge-pill { vertical-align: middle; }
</style>
