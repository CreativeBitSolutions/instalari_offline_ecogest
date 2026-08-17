<!-- Modal: Tastatură numerică cantitate -->
<div class="modal fade" id="numpadModal" tabindex="-1" role="dialog" aria-labelledby="numpadModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document" style="max-width:300px;">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="numpadModalLabel">Tastatură Numerică</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Închide"><span aria-hidden="true">&times;</span></button>
      </div>

      <div class="modal-body">
        <div id="numpad-grid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;">
          <?php foreach ([1,2,3,4,5,6,7,8,9,'.',0] as $key): ?>
            <button class="btn btn-lg btn-light numpad-btn" data-value="<?php echo $key; ?>"><?php echo $key; ?></button>
          <?php endforeach; ?>
          <button class="btn btn-lg btn-warning" id="numpad-backspace">⌫</button>
          <button class="btn btn-lg btn-danger btn-block" id="numpad-clear" style="grid-column:1 / span 3;">Golește</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
$(function(){
  const cantitateInput = $('#cantitate_de_adaugat_prod');
  let first = true;

  $('#openNumpadBtn').on('click', function(){ $('#numpadModal').modal('show'); });
  $('#numpadModal').on('shown.bs.modal', function(){ first = true; });

  $(document).on('click','.numpad-btn', function(){
    // MODIFICARE AICI: am folosit .attr('data-value') pentru a-l prelua direct ca text (string)
    const val = $(this).attr('data-value') || ''; 
    
    if (first && cantitateInput.val() === '1') cantitateInput.val('');
    first = false;
    cantitateInput.val(cantitateInput.val() + val);
  });

  $('#numpad-backspace').on('click', function(){
    const v = cantitateInput.val(); cantitateInput.val(v.slice(0,-1));
  });
  $('#numpad-clear').on('click', function(){
    cantitateInput.val('1'); first = true;
  });
});
</script>