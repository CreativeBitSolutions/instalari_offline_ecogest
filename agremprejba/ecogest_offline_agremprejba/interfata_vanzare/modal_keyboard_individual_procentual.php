<?php // modal_keyboard_individual_procentual.php ?>
<div class="modal fade" id="keyboardModalIndividualProcentual" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document" style="max-width: 320px;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Introduceți Procentul</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="text" id="keyboard-display-individual-procentual" class="form-control text-right mb-3" style="font-size: 1.8rem; height: auto;" readonly>
                <div class="numeric-keyboard">
                    <button class="btn btn-light key" data-key="1">1</button>
                    <button class="btn btn-light key" data-key="2">2</button>
                    <button class="btn btn-light key" data-key="3">3</button>
                    <button class="btn btn-light key" data-key="4">4</button>
                    <button class="btn btn-light key" data-key="5">5</button>
                    <button class="btn btn-light key" data-key="6">6</button>
                    <button class="btn btn-light key" data-key="7">7</button>
                    <button class="btn btn-light key" data-key="8">8</button>
                    <button class="btn btn-light key" data-key="9">9</button>
                    <button class="btn btn-light key" data-key=".">.</button>
                    <button class="btn btn-light key" data-key="0">0</button>
                    <button class="btn btn-warning key" data-action="backspace"><i class="fas fa-backspace"></i></button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger mr-auto key" data-action="clear">Șterge</button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Anulează</button>
                <button type="button" class="btn btn-primary" id="keyboard-save-individual-procentual">Salvează</button>
            </div>
        </div>
    </div>
</div>