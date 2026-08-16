<!-- Modal de autentificare operator -->
<div class="modal fade" id="operatorPasswordModal" tabindex="-1" role="dialog" aria-labelledby="operatorPasswordModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document" style="max-width:420px;">
    <div class="modal-content">
      <form id="operatorLoginForm" method="POST" action="javascript:void(0);">
        <div class="modal-header">
          <h4 class="modal-title" id="operatorPasswordModalLabel">Autentificare Operator</h4>
          <button type="button" class="close" data-dismiss="modal" aria-label="Inchide"><span aria-hidden="true">&times;</span></button>
        </div>

        <div class="modal-body">
          <div id="loginMessage" class="alert alert-info" style="display:none;font-size:.9em;text-align:left;"></div>

          <div class="form-group">
            <label for="operatorPassword">Parola</label>
            <input type="password" class="form-control" id="operatorPassword" name="operatorPassword" required readonly>
          </div>

          <div id="operatorKeypad" style="display:flex;flex-wrap:wrap;justify-content:center;gap:8px;margin-top:10px;">
            <?php for($i=1;$i<=9;$i++): ?>
              <button type="button" class="btn btn-light keypad-btn" data-digit="<?php echo $i; ?>" style="width:30%;font-size:1.4em;"><?php echo $i; ?></button>
            <?php endfor; ?>
            <button type="button" class="btn btn-secondary" id="keypad-backspace" style="width:30%;font-size:1.4em;">&#9003;</button>
            <button type="button" class="btn btn-light keypad-btn" data-digit="0" style="width:30%;font-size:1.4em;">0</button>
            <button type="button" class="btn btn-warning" id="keypad-clear" style="width:30%;font-size:1.1em;color:#fff;">Goleste</button>
          </div>

          <input type="hidden" id="selectedMasa" name="selectedMasa">
          <input type="hidden" id="occupiedOperator" name="occupiedOperator">
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Autentifica-te</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
(function(){
  var modal = document.getElementById('operatorPasswordModal');
  if (!modal) return;

  var currentOperatorId = <?php echo json_encode((string)($_SESSION['admin_id'] ?? '')); ?>;
  var passwordInput = modal.querySelector('#operatorPassword');
  var loginMessageDiv = modal.querySelector('#loginMessage');
  var form = modal.querySelector('#operatorLoginForm');
  var selectedMasaInput = document.getElementById('selectedMasa');
  var occupiedInput = document.getElementById('occupiedOperator');

  function showError(html){
    loginMessageDiv.className = 'alert alert-danger';
    loginMessageDiv.innerHTML = html;
    loginMessageDiv.style.display = 'block';
    passwordInput.classList.add('is-invalid');
    passwordInput.focus();
  }

  function clearMessage(){
    loginMessageDiv.style.display = 'none';
    loginMessageDiv.innerHTML = '';
    passwordInput.classList.remove('is-invalid');
  }

  function goToSetareMasa(masa){
    window.location.href = "vanzare_setare_masa.php?masa=" + encodeURIComponent(masa);
  }

  function setOccupiedFlagAndRedirect(masa, isOccupied){
    var formData = new FormData();
    formData.append('action', 'set_occupied_flag');
    formData.append('occupied', isOccupied ? '1' : '0');

    fetch('vanzare_operator_login.php', { method:'POST', body:formData })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (data && data.success) {
          goToSetareMasa(masa);
          return;
        }
        alert("Nu s-a putut seta sesiunea pentru masa selectata.");
      })
      .catch(function(){
        alert("Eroare de retea.");
      });
  }

  document.querySelectorAll('.table-btn').forEach(function(button) {
    button.addEventListener('click', function() {
      var masa = this.getAttribute('data-masa');
      var occupiedOp = this.getAttribute('data-operator') || "";
      var hasOccupiedOperator = occupiedOp !== "";
      var isCurrentOperatorTable = hasOccupiedOperator && String(occupiedOp) === String(currentOperatorId);

      selectedMasaInput.value = masa;
      occupiedInput.value = occupiedOp;

      if (!hasOccupiedOperator) {
        setOccupiedFlagAndRedirect(masa, false);
        return;
      }

      if (isCurrentOperatorTable) {
        setOccupiedFlagAndRedirect(masa, true);
        return;
      }

      passwordInput.value = '';
      clearMessage();
      $('#operatorPasswordModal').modal('show');
    });
  });

  modal.querySelectorAll('.keypad-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      passwordInput.value += String(this.getAttribute('data-digit') || '');
      passwordInput.classList.remove('is-invalid');
      if (loginMessageDiv.classList.contains('alert-danger')) {
        loginMessageDiv.style.display = 'none';
      }
    });
  });

  modal.querySelector('#keypad-backspace').addEventListener('click', function() {
    passwordInput.value = passwordInput.value.slice(0, -1);
    passwordInput.classList.remove('is-invalid');
  });

  modal.querySelector('#keypad-clear').addEventListener('click', function() {
    passwordInput.value = '';
    clearMessage();
  });

  form.addEventListener('submit', function(e) {
    e.preventDefault();

    var operatorPassword = (passwordInput.value || '').trim();
    var occupiedOperator = modal.querySelector('#occupiedOperator').value;
    var masa = selectedMasaInput.value;

    if (!operatorPassword.length) {
      showError('Introdu parola operatorului inainte de autentificare.');
      return;
    }

    clearMessage();

    var formData = new FormData();
    formData.append('action', 'login');
    formData.append('operatorPassword', operatorPassword);
    formData.append('occupiedOperator', occupiedOperator);

    fetch('vanzare_operator_login.php', { method:'POST', body:formData })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (data && data.success) {
          goToSetareMasa(masa);
          return;
        }
        alert("Autentificare esuata: " + (data && data.message ? data.message : ""));
        window.location.href = "vanzare_restaurant.php";
      })
      .catch(function(){
        alert("Eroare de retea.");
      });
  });
})();
</script>
