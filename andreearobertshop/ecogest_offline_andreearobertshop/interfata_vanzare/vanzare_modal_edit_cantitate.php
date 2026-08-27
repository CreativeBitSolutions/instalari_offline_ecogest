<div class="modal fade" id="editQtyModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifică Cantitate</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editQtyForm" onsubmit="return false;">
                    <input type="hidden" id="qty_id_vanz">
                    <div class="form-group">
                        <label id="qty_product_name_label" class="font-weight-bold"></label>
                        <input type="number" step="0.001" class="form-control text-center" id="new_qty_input" style="font-size: 1.5rem;" required autocomplete="off">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Anulează</button>
                <button type="button" class="btn btn-primary" id="saveQtyChange">Salvează</button>
            </div>
        </div>
    </div>
</div>